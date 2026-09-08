<?php
/**
 * Business TRIZ Portal — Kern: Konfiguration, Anmeldung, Rechte, Datenzugriff.
 *
 * Setzt auf lib/bootstrap.php auf und nutzt dessen Datenbankverbindung,
 * Sitzung und Sicherheits-Header. Was hier steht, ist ausschließlich das, was
 * das Portal zusätzlich braucht.
 *
 * Die Anmeldung ist bewusst von der des Schulungsbereichs getrennt: eigene
 * Tabelle, eigene Einladung, eigenes Passwort, eigener Sitzungsschlüssel. Wer
 * im Schulungsbereich angemeldet ist, ist damit im Portal nicht angemeldet.
 */

declare(strict_types=1);

// --- Konfiguration ---------------------------------------------------------

/**
 * Liest einen Wert aus dem Abschnitt 'triz' der config.php.
 *
 * Alle Werte haben eine Vorgabe, damit das Portal auch dann läuft, wenn die
 * config.php noch aus der Zeit vor dem Portal stammt - eine fehlende
 * Einstellung darf keine weiße Seite ergeben.
 */
function triz_einstellung(string $schluessel, mixed $vorgabe = null): mixed
{
    global $CONFIG;
    $wert = $CONFIG['triz'][$schluessel] ?? null;
    return ($wert === null || $wert === '') ? $vorgabe : $wert;
}

/** Verzeichnis der internen Dokumente. Liegt außerhalb des Web-Verzeichnisses. */
function triz_inhalte_pfad(): string
{
    return PRIVAT_PFAD . '/triz_inhalte';
}

// --- Protokoll und Brute-Force-Bremse --------------------------------------

function triz_protokoll(string $ereignis, string $email = '', ?int $benutzer_id = null, string $details = ''): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO triz_protokoll (benutzer_id, email, ereignis, details, ip_hash)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $benutzer_id,
            mb_substr($email, 0, 190),
            mb_substr($ereignis, 0, 40),
            mb_substr($details, 0, 255),
            ip_hash(),
        ]);
    } catch (Throwable $e) {
        // Ein fehlgeschlagener Protokolleintrag darf nie die eigentliche
        // Aktion verhindern - sonst legt ein volles Log das Portal lahm.
        error_log('TRIZ-Protokoll fehlgeschlagen: ' . $e->getMessage());
    }
}

/**
 * Gezählt wird getrennt nach E-Mail und nach IP: Nur nach E-Mail zu sperren
 * würde es Fremden erlauben, ein bestimmtes Konto gezielt lahmzulegen; nur
 * nach IP zu sperren ließe verteilte Angriffe durch.
 */
function triz_anmeldung_gesperrt(string $email): bool
{
    global $CONFIG;

    $minuten = (int)($CONFIG['sicherheit']['sperrdauer_minuten'] ?? 15);
    $seit = (new DateTimeImmutable("-{$minuten} minutes"))->format('Y-m-d H:i:s');

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM triz_anmeldeversuche
         WHERE erfolg = 0 AND email = ? AND zeitpunkt > ?'
    );
    $stmt->execute([$email, $seit]);
    if ((int)$stmt->fetchColumn() >= (int)($CONFIG['sicherheit']['max_versuche_email'] ?? 5)) {
        return true;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM triz_anmeldeversuche
         WHERE erfolg = 0 AND ip_hash = ? AND zeitpunkt > ?'
    );
    $stmt->execute([ip_hash(), $seit]);
    return (int)$stmt->fetchColumn() >= (int)($CONFIG['sicherheit']['max_versuche_ip'] ?? 20);
}

function triz_versuch_merken(string $email, bool $erfolg): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO triz_anmeldeversuche (email, ip_hash, erfolg) VALUES (?, ?, ?)'
        );
        $stmt->execute([mb_substr($email, 0, 190), ip_hash(), $erfolg ? 1 : 0]);
    } catch (Throwable $e) {
        error_log('TRIZ-Anmeldeversuch nicht gespeichert: ' . $e->getMessage());
    }
}

function triz_versuche_loeschen(string $email): void
{
    try {
        $stmt = db()->prepare('DELETE FROM triz_anmeldeversuche WHERE email = ? AND erfolg = 0');
        $stmt->execute([$email]);
    } catch (Throwable $e) {
        error_log('TRIZ-Anmeldeversuche nicht gelöscht: ' . $e->getMessage());
    }
}

// --- Anmeldestatus ---------------------------------------------------------

/**
 * Der aktuell angemeldete Portalbenutzer oder null.
 *
 * Der Status wird bei jedem Aufruf gegen die Datenbank geprüft: Wird ein Konto
 * gesperrt, während die Sitzung läuft, endet der Zugang sofort und nicht erst
 * bei der nächsten Anmeldung.
 */
function triz_benutzer(): ?array
{
    static $cache = null;

    if (empty($_SESSION['triz_benutzer_id'])) {
        return null;
    }
    if ($cache !== null) {
        return $cache;
    }

    // Eigene Zeitgrenzen: Die des Schulungsbereichs greifen nur, wenn dort
    // jemand angemeldet ist.
    $jetzt    = time();
    $leerlauf = (int)triz_einstellung('sitzung_leerlauf_minuten', 120) * 60;
    $maximal  = (int)triz_einstellung('sitzung_maximal_stunden', 12) * 3600;

    $untaetig = isset($_SESSION['triz_aktiv']) && ($jetzt - (int)$_SESSION['triz_aktiv']) > $leerlauf;
    $zu_alt   = isset($_SESSION['triz_seit'])  && ($jetzt - (int)$_SESSION['triz_seit'])  > $maximal;

    if ($untaetig || $zu_alt) {
        triz_abmelden();
        $_SESSION['triz_hinweis'] = 'abgemeldet_sicherheit';
        return null;
    }
    $_SESSION['triz_aktiv'] = $jetzt;

    $stmt = db()->prepare('SELECT * FROM triz_benutzer WHERE id = ?');
    $stmt->execute([(int)$_SESSION['triz_benutzer_id']]);
    $b = $stmt->fetch();

    if (!$b || $b['status'] !== 'aktiv') {
        triz_abmelden();
        return null;
    }
    return $cache = $b;
}

function triz_angemeldet(): bool
{
    return triz_benutzer() !== null;
}

function triz_ist_admin(): bool
{
    $b = triz_benutzer();
    return $b !== null && $b['rolle'] === 'admin';
}

/** Schützt eine Seite. Nicht angemeldete Besucher landen bei der Anmeldung. */
function triz_login_verlangen(): array
{
    sitzung_starten();
    $b = triz_benutzer();
    if ($b === null) {
        $_SESSION['triz_nach_login'] = $_SERVER['REQUEST_URI'] ?? '/TRIZ/';
        weiter_zu('/TRIZ/login.php');
    }
    return $b;
}

function triz_admin_verlangen(): array
{
    $b = triz_login_verlangen();
    if ($b['rolle'] !== 'admin') {
        http_response_code(403);
        exit('Kein Zugriff.');
    }
    return $b;
}

function triz_anmelden(array $benutzer): void
{
    // Neue Sitzungs-ID nach der Anmeldung: Eine vorher untergeschobene ID
    // wird damit wertlos (Schutz gegen Session Fixation).
    session_regenerate_id(true);

    $_SESSION['triz_benutzer_id'] = (int)$benutzer['id'];
    $_SESSION['triz_seit']        = time();
    $_SESSION['triz_aktiv']       = time();
    $_SESSION['triz_sprache']     = $benutzer['sprache'] ?? 'de';
    $_SESSION['triz_design']      = $benutzer['design'] ?? 'auto';

    $stmt = db()->prepare('UPDATE triz_benutzer SET letzter_login = NOW() WHERE id = ?');
    $stmt->execute([(int)$benutzer['id']]);
}

/**
 * Meldet nur aus dem Portal ab. Eine parallel laufende Anmeldung im
 * Schulungsbereich bleibt bestehen - beides sind getrennte Zugänge, und ein
 * Abmelden hier darf den anderen nicht mit beenden.
 */
function triz_abmelden(): void
{
    unset(
        $_SESSION['triz_benutzer_id'],
        $_SESSION['triz_seit'],
        $_SESSION['triz_aktiv'],
        $_SESSION['triz_nach_login']
    );
}

// --- Sprache und Darstellung ----------------------------------------------

/**
 * Die Oberflächensprache: 'de' oder 'ru'.
 *
 * Reihenfolge: ausdrückliche Wahl in dieser Sitzung, dann die am Konto
 * gespeicherte, sonst Deutsch. Inhalte bleiben davon unberührt - ein
 * russischer Beitrag bleibt russisch, auch wenn die Oberfläche deutsch ist.
 * Genau das ist mit "kann gemischt verwendet werden" gemeint.
 */
function triz_sprache(): string
{
    $s = $_SESSION['triz_sprache'] ?? 'de';
    return $s === 'ru' ? 'ru' : 'de';
}

/** 'auto', 'hell' oder 'dunkel'. */
function triz_design(): string
{
    $d = $_SESSION['triz_design'] ?? 'auto';
    return in_array($d, ['hell', 'dunkel'], true) ? $d : 'auto';
}

/**
 * Nimmt ?sprache=ru / ?design=dunkel entgegen, speichert die Wahl und leitet
 * auf dieselbe Seite ohne diese Parameter zurück.
 *
 * Warum eine Weiterleitung: Bliebe der Parameter in der Adresse stehen, wäre
 * jeder Reload und jedes Lesezeichen an die einmal gewählte Sprache gebunden.
 */
function triz_einstellungen_uebernehmen(): void
{
    $aenderung = false;

    if (isset($_GET['sprache'])) {
        $_SESSION['triz_sprache'] = $_GET['sprache'] === 'ru' ? 'ru' : 'de';
        $aenderung = true;
    }
    if (isset($_GET['design'])) {
        $d = (string)$_GET['design'];
        $_SESSION['triz_design'] = in_array($d, ['hell', 'dunkel', 'auto'], true) ? $d : 'auto';
        $aenderung = true;
    }
    if (!$aenderung) {
        return;
    }

    // Bei angemeldeten Benutzern die Wahl am Konto festhalten, damit sie auf
    // dem nächsten Gerät wieder gilt.
    if (!empty($_SESSION['triz_benutzer_id'])) {
        try {
            $stmt = db()->prepare('UPDATE triz_benutzer SET sprache = ?, design = ? WHERE id = ?');
            $stmt->execute([triz_sprache(), triz_design(), (int)$_SESSION['triz_benutzer_id']]);
        } catch (Throwable $e) {
            error_log('TRIZ-Einstellung nicht gespeichert: ' . $e->getMessage());
        }
    }

    $ziel = strtok((string)($_SERVER['REQUEST_URI'] ?? '/TRIZ/'), '?');
    $rest = $_GET;
    unset($rest['sprache'], $rest['design']);
    if ($rest !== []) {
        $ziel .= '?' . http_build_query($rest);
    }
    weiter_zu($ziel);
}

// --- Kategorien ------------------------------------------------------------

/** Alle Kategorien eines Bereichs, nach Sortierung. */
function triz_kategorien(string $bereich): array
{
    static $cache = [];
    if (isset($cache[$bereich])) {
        return $cache[$bereich];
    }
    $stmt = db()->prepare(
        'SELECT * FROM triz_kategorien WHERE bereich = ? ORDER BY sortierung, name_de'
    );
    $stmt->execute([$bereich]);
    return $cache[$bereich] = $stmt->fetchAll();
}

/** Kategoriename in der aktuellen Oberflächensprache, mit Rückfall auf Deutsch. */
function triz_kategorie_name(?array $kategorie): string
{
    if ($kategorie === null) {
        return '';
    }
    if (triz_sprache() === 'ru' && ($kategorie['name_ru'] ?? '') !== '') {
        return (string)$kategorie['name_ru'];
    }
    return (string)($kategorie['name_de'] ?? '');
}

/** Nachschlagetabelle id => Name, für die Ausgabe von Listen. */
function triz_kategorie_namen(string $bereich): array
{
    $namen = [];
    foreach (triz_kategorien($bereich) as $k) {
        $namen[(int)$k['id']] = triz_kategorie_name($k);
    }
    return $namen;
}

/** Die acht festen Ordner der Wissensdatenbank. */
function triz_ordner(): array
{
    return [
        'schulungen'           => ['de' => 'Schulungen',          'ru' => 'Обучение'],
        'methoden'             => ['de' => 'Methoden',            'ru' => 'Методы'],
        'fallstudien'          => ['de' => 'Fallstudien',         'ru' => 'Кейсы'],
        'vorlagen'             => ['de' => 'Vorlagen',            'ru' => 'Шаблоны'],
        'praesentationen'      => ['de' => 'Präsentationen',      'ru' => 'Презентации'],
        'strategien'           => ['de' => 'Strategien',          'ru' => 'Стратегии'],
        'prozessbeschreibungen'=> ['de' => 'Prozessbeschreibungen','ru' => 'Описания процессов'],
        'unternehmenswissen'   => ['de' => 'Unternehmenswissen',  'ru' => 'Знания компании'],
    ];
}

function triz_ordner_name(string $schluessel): string
{
    $o = triz_ordner()[$schluessel] ?? null;
    return $o === null ? $schluessel : $o[triz_sprache()];
}

// --- Favoriten -------------------------------------------------------------

function triz_ist_favorit(int $benutzer_id, string $art, int $objekt_id): bool
{
    $stmt = db()->prepare(
        'SELECT 1 FROM triz_favoriten WHERE benutzer_id = ? AND objekt_art = ? AND objekt_id = ?'
    );
    $stmt->execute([$benutzer_id, $art, $objekt_id]);
    return (bool)$stmt->fetchColumn();
}

/** Alle Favoriten eines Benutzers als [art => [id, id, ...]]. */
function triz_favoriten_ids(int $benutzer_id): array
{
    $stmt = db()->prepare('SELECT objekt_art, objekt_id FROM triz_favoriten WHERE benutzer_id = ?');
    $stmt->execute([$benutzer_id]);
    $karte = ['beitrag' => [], 'video' => [], 'dokument' => [], 'bibliothek' => []];
    foreach ($stmt->fetchAll() as $z) {
        $karte[$z['objekt_art']][] = (int)$z['objekt_id'];
    }
    return $karte;
}

/** Setzt oder entfernt einen Favoriten. Rückgabe: gesetzt (true) oder entfernt. */
function triz_favorit_umschalten(int $benutzer_id, string $art, int $objekt_id): bool
{
    if (!in_array($art, ['beitrag', 'video', 'dokument', 'bibliothek'], true)) {
        return false;
    }
    if (triz_ist_favorit($benutzer_id, $art, $objekt_id)) {
        $stmt = db()->prepare(
            'DELETE FROM triz_favoriten WHERE benutzer_id = ? AND objekt_art = ? AND objekt_id = ?'
        );
        $stmt->execute([$benutzer_id, $art, $objekt_id]);
        return false;
    }
    $stmt = db()->prepare(
        'INSERT INTO triz_favoriten (benutzer_id, objekt_art, objekt_id) VALUES (?, ?, ?)'
    );
    $stmt->execute([$benutzer_id, $art, $objekt_id]);
    return true;
}

// --- Kommentare ------------------------------------------------------------

function triz_kommentare(string $art, int $objekt_id): array
{
    $stmt = db()->prepare(
        'SELECT k.*, b.name, b.email
         FROM triz_kommentare k
         JOIN triz_benutzer b ON b.id = k.benutzer_id
         WHERE k.objekt_art = ? AND k.objekt_id = ?
         ORDER BY k.erstellt_am'
    );
    $stmt->execute([$art, $objekt_id]);
    return $stmt->fetchAll();
}

function triz_kommentar_speichern(int $benutzer_id, string $art, int $objekt_id, string $text): bool
{
    $text = trim($text);
    if ($text === '' || !in_array($art, ['beitrag', 'video', 'dokument', 'bibliothek'], true)) {
        return false;
    }
    $stmt = db()->prepare(
        'INSERT INTO triz_kommentare (benutzer_id, objekt_art, objekt_id, text)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$benutzer_id, $art, $objekt_id, mb_substr($text, 0, 4000)]);
    return true;
}

// --- Suche -----------------------------------------------------------------

/**
 * Volltextsuche über alle Bereiche.
 *
 * Bewusst mit LIKE statt einem FULLTEXT-Index: Der Index von MySQL zerlegt
 * Text an Wortgrenzen und ignoriert kurze Wörter - bei gemischt deutsch,
 * russisch und englisch beschriftetem Bestand liefert das unberechenbare
 * Ergebnisse. Bei der hier erwarteten Menge (einige zehntausend Zeilen) ist
 * LIKE schnell genug und vor allem vorhersagbar.
 *
 * Rückgabe: Liste aus [art, id, titel, text, datum, url].
 */
function triz_suchen(string $begriff, int $grenze = 60): array
{
    $begriff = trim($begriff);
    if (mb_strlen($begriff) < 2) {
        return [];
    }
    $m = '%' . str_replace(['%', '_'], ['\%', '\_'], $begriff) . '%';
    $treffer = [];

    $stmt = db()->prepare(
        'SELECT id, titel, kurzfassung, veroeffentlicht_am, url, sprache
         FROM triz_beitraege
         WHERE titel LIKE ? OR kurzfassung LIKE ? OR quelle_name LIKE ?
         ORDER BY COALESCE(veroeffentlicht_am, gefunden_am) DESC LIMIT ?'
    );
    $stmt->bindValue(1, $m); $stmt->bindValue(2, $m); $stmt->bindValue(3, $m);
    $stmt->bindValue(4, $grenze, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $z) {
        $treffer[] = [
            'art' => 'beitrag', 'id' => (int)$z['id'], 'titel' => $z['titel'],
            'text' => $z['kurzfassung'], 'datum' => $z['veroeffentlicht_am'],
            'sprache' => $z['sprache'], 'ziel' => '/TRIZ/news.php?q=' . urlencode($begriff),
            'extern' => $z['url'],
        ];
    }

    $stmt = db()->prepare(
        'SELECT id, video_id, titel, beschreibung, kanal, veroeffentlicht_am, sprache
         FROM triz_videos
         WHERE titel LIKE ? OR beschreibung LIKE ? OR kanal LIKE ?
         ORDER BY COALESCE(veroeffentlicht_am, gefunden_am) DESC LIMIT ?'
    );
    $stmt->bindValue(1, $m); $stmt->bindValue(2, $m); $stmt->bindValue(3, $m);
    $stmt->bindValue(4, $grenze, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $z) {
        $treffer[] = [
            'art' => 'video', 'id' => (int)$z['id'], 'titel' => $z['titel'],
            'text' => $z['beschreibung'], 'datum' => $z['veroeffentlicht_am'],
            'sprache' => $z['sprache'], 'ziel' => '/TRIZ/videos.php?q=' . urlencode($begriff),
            'extern' => 'https://www.youtube.com/watch?v=' . $z['video_id'],
        ];
    }

    $stmt = db()->prepare(
        'SELECT id, titel, beschreibung, schlagwoerter, hochgeladen_am, sprache, ordner
         FROM triz_dokumente
         WHERE aktuell = 1 AND (titel LIKE ? OR beschreibung LIKE ?
               OR schlagwoerter LIKE ? OR volltext LIKE ?)
         ORDER BY hochgeladen_am DESC LIMIT ?'
    );
    $stmt->bindValue(1, $m); $stmt->bindValue(2, $m);
    $stmt->bindValue(3, $m); $stmt->bindValue(4, $m);
    $stmt->bindValue(5, $grenze, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $z) {
        $treffer[] = [
            'art' => 'dokument', 'id' => (int)$z['id'], 'titel' => $z['titel'],
            'text' => $z['beschreibung'] !== '' ? $z['beschreibung'] : $z['schlagwoerter'],
            'datum' => $z['hochgeladen_am'], 'sprache' => $z['sprache'],
            'ziel' => '/TRIZ/wissen.php?ordner=' . urlencode($z['ordner']),
            'extern' => '',
        ];
    }

    $sp = triz_sprache();
    $stmt = db()->prepare(
        'SELECT id, art, nummer, titel_de, titel_ru, text_de, text_ru
         FROM triz_bibliothek
         WHERE titel_de LIKE ? OR titel_ru LIKE ? OR text_de LIKE ? OR text_ru LIKE ?
            OR beispiel_de LIKE ? OR beispiel_ru LIKE ?
         ORDER BY art, sortierung LIMIT ?'
    );
    for ($i = 1; $i <= 6; $i++) {
        $stmt->bindValue($i, $m);
    }
    $stmt->bindValue(7, $grenze, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $z) {
        $titel = ($sp === 'ru' && $z['titel_ru'] !== '') ? $z['titel_ru'] : $z['titel_de'];
        $text  = ($sp === 'ru' && $z['text_ru']  !== '') ? $z['text_ru']  : $z['text_de'];
        $treffer[] = [
            'art' => 'bibliothek', 'id' => (int)$z['id'],
            'titel' => ($z['nummer'] !== null ? $z['nummer'] . '. ' : '') . $titel,
            'text' => $text, 'datum' => null, 'sprache' => $sp,
            'ziel' => '/TRIZ/bibliothek.php?art=' . urlencode((string)$z['art']) . '#e' . (int)$z['id'],
            'extern' => '',
        ];
    }

    return $treffer;
}

// --- Formatierung ----------------------------------------------------------

/** Datum in der Oberflächensprache. */
function triz_datum(?string $iso, bool $mit_uhrzeit = false): string
{
    if ($iso === null || $iso === '' || str_starts_with($iso, '0000')) {
        return '';
    }
    $t = strtotime($iso);
    if ($t === false) {
        return (string)$iso;
    }
    $monate_de = [1 => 'Januar','Februar','März','April','Mai','Juni','Juli',
                  'August','September','Oktober','November','Dezember'];
    $monate_ru = [1 => 'января','февраля','марта','апреля','мая','июня','июля',
                  'августа','сентября','октября','ноября','декабря'];

    $m = (int)date('n', $t);
    $text = triz_sprache() === 'ru'
        ? (int)date('j', $t) . ' ' . $monate_ru[$m] . ' ' . date('Y', $t)
        : date('d.', $t) . ' ' . $monate_de[$m] . ' ' . date('Y', $t);

    return $mit_uhrzeit ? $text . ', ' . date('H:i', $t) : $text;
}

/** Kürzt einen Text auf ganze Wörter. */
function triz_kuerzen(string $text, int $zeichen = 220): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    if (mb_strlen($text) <= $zeichen) {
        return $text;
    }
    $kurz = mb_substr($text, 0, $zeichen);
    $luecke = mb_strrpos($kurz, ' ');
    if ($luecke !== false && $luecke > $zeichen * 0.6) {
        $kurz = mb_substr($kurz, 0, $luecke);
    }
    return $kurz . '…';
}

/** Dateigröße menschenlesbar. */
function triz_groesse(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

function triz_dauer(int $sekunden): string
{
    if ($sekunden <= 0) {
        return '';
    }
    $h = intdiv($sekunden, 3600);
    $m = intdiv($sekunden % 3600, 60);
    $s = $sekunden % 60;
    return $h > 0
        ? sprintf('%d:%02d:%02d', $h, $m, $s)
        : sprintf('%d:%02d', $m, $s);
}

// --- E-Mails ---------------------------------------------------------------
//
// Eigene Texte statt der des Schulungsbereichs: Eine Einladung, die vom
// "Schulungsbereich" spricht, während sie zum TRIZ-Portal führt, verwirrt den
// Empfänger und lässt ihn im falschen Bereich nach seinem Passwort suchen.

function triz_mail_einladung(string $an, string $name, string $link, int $gueltig_stunden): bool
{
    global $CONFIG;
    $tage = max(1, (int)round($gueltig_stunden / 24));
    $anrede = $name !== '' ? "Hallo {$name}," : 'Hallo,';
    $basis = $CONFIG['basis_url'];

    $text = <<<TEXT
{$anrede}

für Sie wurde ein Zugang zum Business TRIZ Portal der elektromas GmbH
angelegt - der internen Wissens- und Schulungsplattform für TRIZ im
Management.

Über den folgenden Link vergeben Sie Ihr Passwort und schließen die
Einrichtung ab:

{$link}

Der Link ist {$tage} Tage gültig und lässt sich nur einmal verwenden.

Danach erreichen Sie das Portal jederzeit unter:
{$basis}/TRIZ/

Wichtig: Dieser Zugang ist unabhängig vom Schulungsbereich. Auch wenn Sie
dort bereits ein Konto haben, brauchen Sie hier ein eigenes Passwort.

Falls Sie mit dieser Einladung nichts anfangen können, ignorieren Sie diese
Nachricht bitte einfach - ohne den Link passiert nichts.

Mit freundlichen Grüßen
elektromas GmbH
TEXT;

    return mail_senden($an, 'Ihr Zugang zum Business TRIZ Portal', $text);
}

function triz_mail_reset(string $an, string $link, int $gueltig_minuten): bool
{
    $text = <<<TEXT
Hallo,

für Ihren Zugang zum Business TRIZ Portal der elektromas GmbH wurde ein neues
Passwort angefordert.

Über diesen Link vergeben Sie ein neues Passwort:

{$link}

Der Link ist {$gueltig_minuten} Minuten gültig und lässt sich nur einmal
verwenden. Er gilt ausschließlich für das TRIZ-Portal, nicht für den
Schulungsbereich.

Haben Sie das nicht angefordert? Dann ist nichts passiert - Ihr bisheriges
Passwort gilt unverändert weiter.

Mit freundlichen Grüßen
elektromas GmbH
TEXT;

    return mail_senden($an, 'Passwort für das TRIZ-Portal zurücksetzen', $text);
}

/**
 * Hebt den Suchbegriff im bereits escapten Text hervor.
 *
 * Reihenfolge ist wichtig: Erst escapen, dann Markierungen einsetzen. Andersrum
 * würde das Escaping die eigenen <mark>-Tags gleich wieder unschädlich machen.
 */
function triz_hervorheben(string $text, string $begriff): string
{
    $sicher = e($text);
    $begriff = trim($begriff);
    if ($begriff === '' || mb_strlen($begriff) < 2) {
        return $sicher;
    }
    $muster = '/' . preg_quote(e($begriff), '/') . '/iu';
    return (string)preg_replace($muster, '<mark>$0</mark>', $sicher);
}

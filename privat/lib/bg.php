<?php
/**
 * Gefährdungsbeurteilungs-Portal (/bg) — Anmeldung, Rechte, Datenzugriff.
 *
 * Setzt auf lib/bootstrap.php auf und nutzt dessen Datenbankverbindung,
 * Sitzung und Sicherheits-Header.
 *
 * Die Anmeldung ist von der des Schulungsbereichs und des TRIZ-Portals
 * getrennt: eigene Tabelle, eigene Einladung, eigenes Passwort, eigener
 * Sitzungsschlüssel. Wer Schulungen sehen darf, ist damit hier nicht
 * angemeldet.
 */

declare(strict_types=1);

// --- Protokoll und Brute-Force-Bremse --------------------------------------

function bg_protokoll(string $ereignis, string $email = '', ?int $benutzer_id = null, string $details = ''): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO bg_protokoll (benutzer_id, email, ereignis, details, ip_hash)
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
        error_log('BG-Protokoll fehlgeschlagen: ' . $e->getMessage());
    }
}

/**
 * Gezählt wird getrennt nach E-Mail und nach IP: Nur nach E-Mail zu sperren
 * würde es Fremden erlauben, ein bestimmtes Konto gezielt lahmzulegen; nur
 * nach IP zu sperren ließe verteilte Angriffe durch.
 */
function bg_anmeldung_gesperrt(string $email): bool
{
    global $CONFIG;

    $minuten = (int)($CONFIG['sicherheit']['sperrdauer_minuten'] ?? 15);
    $seit = (new DateTimeImmutable("-{$minuten} minutes"))->format('Y-m-d H:i:s');

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM bg_anmeldeversuche WHERE erfolg = 0 AND email = ? AND zeitpunkt > ?'
    );
    $stmt->execute([$email, $seit]);
    if ((int)$stmt->fetchColumn() >= (int)($CONFIG['sicherheit']['max_versuche_email'] ?? 5)) {
        return true;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM bg_anmeldeversuche WHERE erfolg = 0 AND ip_hash = ? AND zeitpunkt > ?'
    );
    $stmt->execute([ip_hash(), $seit]);
    return (int)$stmt->fetchColumn() >= (int)($CONFIG['sicherheit']['max_versuche_ip'] ?? 20);
}

function bg_versuch_merken(string $email, bool $erfolg): void
{
    try {
        $stmt = db()->prepare('INSERT INTO bg_anmeldeversuche (email, ip_hash, erfolg) VALUES (?, ?, ?)');
        $stmt->execute([mb_substr($email, 0, 190), ip_hash(), $erfolg ? 1 : 0]);
    } catch (Throwable $e) {
        error_log('BG-Anmeldeversuch nicht gespeichert: ' . $e->getMessage());
    }
}

function bg_versuche_loeschen(string $email): void
{
    try {
        $stmt = db()->prepare('DELETE FROM bg_anmeldeversuche WHERE email = ? AND erfolg = 0');
        $stmt->execute([$email]);
    } catch (Throwable $e) {
        error_log('BG-Anmeldeversuche nicht gelöscht: ' . $e->getMessage());
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
function bg_benutzer(): ?array
{
    static $cache = null;
    global $CONFIG;

    if (empty($_SESSION['bg_benutzer_id'])) {
        return null;
    }
    if ($cache !== null) {
        return $cache;
    }

    $jetzt    = time();
    $leerlauf = (int)($CONFIG['sicherheit']['sitzung_leerlauf_minuten'] ?? 120) * 60;
    $maximal  = (int)($CONFIG['sicherheit']['sitzung_maximal_stunden'] ?? 12) * 3600;

    $untaetig = isset($_SESSION['bg_aktiv']) && ($jetzt - (int)$_SESSION['bg_aktiv']) > $leerlauf;
    $zu_alt   = isset($_SESSION['bg_seit'])  && ($jetzt - (int)$_SESSION['bg_seit'])  > $maximal;

    if ($untaetig || $zu_alt) {
        bg_abmelden();
        $_SESSION['bg_hinweis'] = 'Sie wurden aus Sicherheitsgründen abgemeldet.';
        return null;
    }
    $_SESSION['bg_aktiv'] = $jetzt;

    $stmt = db()->prepare('SELECT * FROM bg_benutzer WHERE id = ?');
    $stmt->execute([(int)$_SESSION['bg_benutzer_id']]);
    $b = $stmt->fetch();

    if (!$b || $b['status'] !== 'aktiv') {
        bg_abmelden();
        return null;
    }
    return $cache = $b;
}

function bg_angemeldet(): bool
{
    return bg_benutzer() !== null;
}

function bg_ist_admin(): bool
{
    $b = bg_benutzer();
    return $b !== null && $b['rolle'] === 'admin';
}

/** Schützt eine Seite. Nicht angemeldete Besucher landen bei der Anmeldung. */
function bg_login_verlangen(): array
{
    sitzung_starten();
    $b = bg_benutzer();
    if ($b === null) {
        $_SESSION['bg_nach_login'] = $_SERVER['REQUEST_URI'] ?? '/bg/';
        weiter_zu('/bg/login.php');
    }
    return $b;
}

function bg_admin_verlangen(): array
{
    $b = bg_login_verlangen();
    if ($b['rolle'] !== 'admin') {
        http_response_code(403);
        exit('Kein Zugriff.');
    }
    return $b;
}

function bg_anmelden(array $benutzer): void
{
    // Neue Sitzungs-ID nach der Anmeldung: Eine vorher untergeschobene ID
    // wird damit wertlos (Schutz gegen Session Fixation).
    session_regenerate_id(true);

    $_SESSION['bg_benutzer_id'] = (int)$benutzer['id'];
    $_SESSION['bg_seit']        = time();
    $_SESSION['bg_aktiv']       = time();

    $stmt = db()->prepare('UPDATE bg_benutzer SET letzter_login = NOW() WHERE id = ?');
    $stmt->execute([(int)$benutzer['id']]);
}

/**
 * Meldet nur aus diesem Portal ab. Eine parallel laufende Anmeldung im
 * Schulungsbereich oder im TRIZ-Portal bleibt bestehen.
 */
function bg_abmelden(): void
{
    unset(
        $_SESSION['bg_benutzer_id'],
        $_SESSION['bg_seit'],
        $_SESSION['bg_aktiv'],
        $_SESSION['bg_nach_login']
    );
}

// --- Gefährdungsbeurteilungen ----------------------------------------------

/** Obergrenze je Dokument. Großzügig bemessen; schützt vor entgleisten Daten. */
const BG_MAX_BYTES = 400000;

/**
 * Alle Gefährdungsbeurteilungen als Liste von Datensätzen, wie die Oberfläche
 * sie erwartet (der JSON-Inhalt selbst, ergänzt um die id).
 */
function bg_gbus_lesen(): array
{
    $zeilen = db()->query('SELECT id, daten FROM bg_gbu ORDER BY geaendert_am DESC')->fetchAll();

    $liste = [];
    foreach ($zeilen as $z) {
        $daten = json_decode((string)$z['daten'], true);
        if (!is_array($daten)) {
            // Unlesbare Datensätze überspringen statt das ganze Portal
            // scheitern zu lassen.
            error_log('BG: unlesbarer Datensatz ' . $z['id']);
            continue;
        }
        $daten['id'] = $z['id'];
        $liste[] = $daten;
    }
    return $liste;
}

/**
 * Legt eine Gefährdungsbeurteilung an oder überschreibt sie.
 *
 * Die Oberfläche schickt immer das vollständige Dokument, deshalb genügt ein
 * Schreibvorgang ohne Feldvergleich.
 */
function bg_gbu_speichern(array $gbu, int $benutzer_id): void
{
    $id = (string)($gbu['id'] ?? '');
    if ($id === '' || !preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id)) {
        throw new InvalidArgumentException('ungueltige_id');
    }

    $json = json_encode($gbu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || strlen($json) > BG_MAX_BYTES) {
        throw new InvalidArgumentException('zu_gross');
    }

    // Denselben Platzhalter zweimal zu verwenden geht nicht: Ohne Emulation
    // reicht PDO die Namen unverändert an MariaDB weiter, und dort ist jeder
    // Platzhalter genau einmal zu belegen.
    $stmt = db()->prepare(
        'INSERT INTO bg_gbu (id, bezeichnung, projekt_nr, status, daten, erstellt_von, geaendert_von)
         VALUES (:id, :bez, :nr, :status, :daten, :erstellt_von, :geaendert_von)
         ON DUPLICATE KEY UPDATE
            bezeichnung   = VALUES(bezeichnung),
            projekt_nr    = VALUES(projekt_nr),
            status        = VALUES(status),
            daten         = VALUES(daten),
            geaendert_am  = NOW(),
            geaendert_von = VALUES(geaendert_von)'
    );
    $stmt->execute([
        ':id'            => $id,
        ':bez'           => mb_substr((string)($gbu['projekt']['name'] ?? ''), 0, 190),
        ':nr'            => mb_substr((string)($gbu['projekt']['nr'] ?? ''), 0, 120),
        ':status'        => mb_substr((string)($gbu['status'] ?? 'entwurf'), 0, 20),
        ':daten'         => $json,
        ':erstellt_von'  => $benutzer_id ?: null,
        ':geaendert_von' => $benutzer_id ?: null,
    ]);
}

function bg_gbu_loeschen(string $id): void
{
    $stmt = db()->prepare('DELETE FROM bg_gbu WHERE id = ?');
    $stmt->execute([$id]);
}

// --- Einstellungen ---------------------------------------------------------

function bg_einstellung_lesen(string $schluessel): array
{
    $stmt = db()->prepare('SELECT wert FROM bg_einstellungen WHERE schluessel = ?');
    $stmt->execute([$schluessel]);
    $wert = $stmt->fetchColumn();
    if ($wert === false) {
        return [];
    }
    $daten = json_decode((string)$wert, true);
    return is_array($daten) ? $daten : [];
}

function bg_einstellung_speichern(string $schluessel, array $wert): void
{
    $json = json_encode($wert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || strlen($json) > BG_MAX_BYTES) {
        throw new InvalidArgumentException('zu_gross');
    }
    $stmt = db()->prepare(
        'INSERT INTO bg_einstellungen (schluessel, wert) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE wert = VALUES(wert), geaendert_am = NOW()'
    );
    $stmt->execute([$schluessel, $json]);
}

// --- E-Mails ---------------------------------------------------------------

function bg_mail_einladung(string $an, string $name, string $link, int $gueltig_stunden): bool
{
    $tage = max(1, (int)round($gueltig_stunden / 24));
    $anrede = $name !== '' ? "Hallo {$name}," : 'Hallo,';

    $text = <<<TEXT
{$anrede}

für Sie wurde ein Zugang zum Gefährdungsbeurteilungs-Portal der elektromas
GmbH angelegt. Dort erstellen Sie Gefährdungsbeurteilungen für Baustellen und
geben sie frei.

Über den folgenden Link vergeben Sie Ihr Passwort und schließen die
Einrichtung ab:

{$link}

Der Link ist {$tage} Tage gültig und lässt sich nur einmal verwenden.

Danach erreichen Sie das Portal jederzeit unter:
https://elektromas.cool/bg/

Falls Sie mit dieser Einladung nichts anfangen können, ignorieren Sie diese
Nachricht bitte einfach - ohne den Link passiert nichts.

Mit freundlichen Grüßen
elektromas GmbH
TEXT;

    return mail_senden($an, 'Ihr Zugang zum Gefährdungsbeurteilungs-Portal', $text);
}

function bg_mail_reset(string $an, string $link, int $gueltig_minuten): bool
{
    $text = <<<TEXT
Hallo,

für Ihren Zugang zum Gefährdungsbeurteilungs-Portal der elektromas GmbH wurde
ein neues Passwort angefordert.

Über diesen Link vergeben Sie ein neues Passwort:

{$link}

Der Link ist {$gueltig_minuten} Minuten gültig und lässt sich nur einmal
verwenden.

Haben Sie das nicht angefordert? Dann ist nichts passiert - Ihr bisheriges
Passwort gilt unverändert weiter. Sie müssen nichts tun.

Mit freundlichen Grüßen
elektromas GmbH
TEXT;

    return mail_senden($an, 'Passwort zurücksetzen', $text);
}

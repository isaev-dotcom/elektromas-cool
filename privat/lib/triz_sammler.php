<?php
/**
 * Business TRIZ Portal — die tägliche Sammlung.
 *
 * Holt Nachrichtenquellen (RSS/Atom) und YouTube-Kanäle ab, wirft Dubletten
 * weg und legt neue Einträge in der Datenbank ab.
 *
 * Warum Feeds und keine Suchmaschine: Ein Feed ist eine Zusage des Anbieters,
 * dass diese Adresse maschinell gelesen werden darf. Eine Suchergebnisseite
 * automatisiert abzurufen ist bei fast jedem Anbieter untersagt und bricht
 * zudem bei jeder Layoutänderung. Für Suchbegriffe gibt es Feed-Adressen
 * (Google News, Yandex, Bing) - die stehen als Quellen in der Datenbank und
 * sind dort jederzeit änderbar, ohne dass jemand Programmcode anfassen muss.
 *
 * Der Lauf ist so gebaut, dass er nichts kaputtmachen kann: Er schreibt nur
 * neue Zeilen, aktualisiert nie bestehende und bricht bei einer toten Quelle
 * nicht ab, sondern vermerkt den Fehler an der Quelle.
 */

declare(strict_types=1);

/** Wie viele Zeichen einer Kurzfassung übernommen werden. */
const TRIZ_KURZFASSUNG_MAX = 900;

// --- Abruf -----------------------------------------------------------------

/**
 * Holt eine Adresse. Rückgabe: Inhalt oder null.
 *
 * Nutzt cURL, wenn vorhanden - sonst file_get_contents mit Kontext. Auf dem
 * Shared-Host ist allow_url_fopen üblicherweise an, aber verlassen sollte man
 * sich darauf nicht.
 */
function triz_abrufen(string $url, ?string &$fehler = null): ?string
{
    $fehler = null;
    $zeit = (int)triz_einstellung('abruf_timeout_sekunden', 20);

    // Nur http/https, und keine Adressen im eigenen Netz: Eine Quelle wird von
    // einem Menschen eingetragen, aber ein Tippfehler auf 127.0.0.1 oder eine
    // Umleitung dorthin würde den Server sonst gegen sich selbst abfragen
    // lassen (SSRF).
    $teile = parse_url($url);
    if ($teile === false || !in_array($teile['scheme'] ?? '', ['http', 'https'], true)) {
        $fehler = 'Ungültige Adresse';
        return null;
    }

    $kopf = [
        'User-Agent: elektromas-TRIZ-Portal/1.0 (+https://elektromas.cool)',
        'Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*',
        'Accept-Language: de,ru;q=0.9,en;q=0.8',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $zeit,
            CURLOPT_HTTPHEADER     => $kopf,
            CURLOPT_ENCODING       => '',      // gzip automatisch auspacken
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $inhalt = curl_exec($ch);
        $code   = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $cfehler= curl_error($ch);
        curl_close($ch);

        if ($inhalt === false || $inhalt === '') {
            $fehler = $cfehler !== '' ? mb_substr($cfehler, 0, 200) : 'Keine Antwort';
            return null;
        }
        if ($code >= 400) {
            $fehler = 'HTTP ' . $code;
            return null;
        }
        return (string)$inhalt;
    }

    $kontext = stream_context_create(['http' => [
        'method'        => 'GET',
        'header'        => implode("\r\n", $kopf),
        'timeout'       => $zeit,
        'follow_location'=> 1,
        'max_redirects' => 4,
    ]]);
    $inhalt = @file_get_contents($url, false, $kontext);
    if ($inhalt === false) {
        $fehler = 'Abruf fehlgeschlagen';
        return null;
    }
    return $inhalt;
}

// --- Feeds lesen -----------------------------------------------------------

/**
 * Zerlegt RSS 2.0 und Atom in eine gemeinsame Form.
 *
 * Rückgabe je Eintrag: titel, url, text, datum (ISO oder null).
 *
 * LIBXML_NONET und die Prüfung auf ENTITY sind der Schutz gegen XXE: Ein
 * fremder Feed darf uns nicht dazu bringen, lokale Dateien nachzuladen.
 */
function triz_feed_lesen(string $xml): array
{
    if (str_contains($xml, '<!ENTITY')) {
        return [];
    }

    $vorher = libxml_use_internal_errors(true);
    $wurzel = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($vorher);

    if ($wurzel === false) {
        return [];
    }

    $eintraege = [];

    // --- RSS 2.0 / RDF ---
    if (isset($wurzel->channel->item) || isset($wurzel->item)) {
        $liste = isset($wurzel->channel->item) ? $wurzel->channel->item : $wurzel->item;
        foreach ($liste as $i) {
            $dc = $i->children('http://purl.org/dc/elements/1.1/');
            $inhalt = $i->children('http://purl.org/rss/1.0/modules/content/');

            $datum = (string)($i->pubDate ?? '');
            if ($datum === '' && isset($dc->date)) {
                $datum = (string)$dc->date;
            }
            $text = (string)($i->description ?? '');
            if ($text === '' && isset($inhalt->encoded)) {
                $text = (string)$inhalt->encoded;
            }

            $eintraege[] = [
                'titel' => (string)($i->title ?? ''),
                'url'   => trim((string)($i->link ?? ($i->guid ?? ''))),
                'text'  => $text,
                'datum' => $datum,
            ];
        }
        return $eintraege;
    }

    // --- Atom ---
    $atom = $wurzel->children('http://www.w3.org/2005/Atom');
    $liste = isset($atom->entry) ? $atom->entry : ($wurzel->entry ?? null);
    if ($liste === null) {
        return [];
    }

    foreach ($liste as $e) {
        // Atom erlaubt mehrere <link>; gebraucht wird der mit rel="alternate"
        // oder, wenn keiner das sagt, schlicht der erste.
        //
        // Die Attribute werden über attributes() geholt, nicht über $l['href']:
        // Das Element stammt aus children('…/Atom') und der eckige-Klammer-
        // Zugriff sucht die Attribute dann ebenfalls im Atom-Namensraum. Dort
        // stehen sie aber nicht - href und rel sind namensraumlos, und die
        // Abfrage käme still leer zurück.
        $url = '';
        foreach ($e->link ?? [] as $l) {
            $attribute = $l->attributes();
            $rel = (string)($attribute['rel'] ?? 'alternate');
            if ($rel === '') {
                $rel = 'alternate';
            }
            if ($rel === 'alternate' || $url === '') {
                $url = (string)($attribute['href'] ?? '');
            }
            if ($rel === 'alternate') {
                break;
            }
        }
        $eintraege[] = [
            'titel' => (string)($e->title ?? ''),
            'url'   => trim($url),
            'text'  => (string)($e->summary ?? ($e->content ?? '')),
            'datum' => (string)($e->published ?? ($e->updated ?? '')),
        ];
    }
    return $eintraege;
}

/**
 * Vereinheitlicht eine Adresse für den Dublettenvergleich.
 *
 * Kampagnenparameter (utm_*) und Anker unterscheiden zwei Verweise auf
 * denselben Artikel, sind für die Sache aber bedeutungslos. Ohne diese
 * Bereinigung stünde derselbe Beitrag mehrfach in der Liste.
 */
function triz_url_schluessel(string $url): string
{
    $t = parse_url(trim($url));
    if ($t === false || empty($t['host'])) {
        return hash('sha256', trim($url));
    }
    $host = strtolower($t['host']);
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    $pfad = rtrim($t['path'] ?? '/', '/');
    if ($pfad === '') {
        $pfad = '/';
    }

    $frage = '';
    if (!empty($t['query'])) {
        parse_str($t['query'], $p);
        foreach (array_keys($p) as $k) {
            if (str_starts_with((string)$k, 'utm_') ||
                in_array($k, ['fbclid', 'gclid', 'yclid', 'ref', 'from'], true)) {
                unset($p[$k]);
            }
        }
        ksort($p);
        $frage = $p === [] ? '' : '?' . http_build_query($p);
    }
    return hash('sha256', $host . $pfad . $frage);
}

/**
 * Rät die Sprache eines Textes.
 *
 * Nur die Unterscheidung kyrillisch/lateinisch, und die ist zuverlässig. Bei
 * lateinischer Schrift entscheiden ein paar häufige Funktionswörter zwischen
 * Deutsch und Englisch; im Zweifel gilt die an der Quelle eingetragene
 * Sprache, die dieser Funktion als Vorgabe mitgegeben wird.
 */
function triz_sprache_raten(string $text, string $vorgabe = 'de'): string
{
    $kyrillisch = preg_match_all('/[\x{0400}-\x{04FF}]/u', $text);
    $lateinisch = preg_match_all('/[A-Za-zÄÖÜäöüß]/u', $text);

    if ($kyrillisch > 0 && $kyrillisch > $lateinisch * 0.3) {
        return 'ru';
    }
    if ($lateinisch === 0) {
        return $vorgabe;
    }

    $klein = ' ' . mb_strtolower($text) . ' ';
    $deutsch  = 0;
    $englisch = 0;
    foreach ([' der ', ' die ', ' das ', ' und ', ' für ', ' mit ', ' nicht ', ' eine ', ' ist ', ' im '] as $w) {
        $deutsch += substr_count($klein, $w);
    }
    foreach ([' the ', ' and ', ' for ', ' with ', ' this ', ' that ', ' from ', ' are ', ' how '] as $w) {
        $englisch += substr_count($klein, $w);
    }
    if ($deutsch === 0 && $englisch === 0) {
        return $vorgabe;
    }
    return $deutsch >= $englisch ? 'de' : 'en';
}

/**
 * Ordnet einen Beitrag einer Kategorie zu.
 *
 * Schlichte Stichwortsuche: Sie liegt oft richtig und liegt nie
 * unerklärlich falsch. Trifft nichts zu, bleibt die Kategorie der Quelle -
 * und wenn auch die fehlt, eben keine. Eine leere Kategorie ist ehrlicher
 * als eine falsche.
 */
function triz_kategorie_raten(string $text, ?int $vorgabe = null): ?int
{
    static $muster = null;

    if ($muster === null) {
        $muster = [];
        // schluessel => Stichwörter in allen drei Sprachen
        $woerter = [
            'ki-triz'          => ['künstliche intelligenz', 'ki-', ' ki ', 'artificial intelligence', ' ai ', 'machine learning', 'искусственн', 'нейросет', ' ии '],
            'business-triz'    => ['business triz', 'business-triz', 'бизнес-триз', 'бизнес триз'],
            'management-triz'  => ['management triz', 'management-triz', 'триз в управлении'],
            'innovation'       => ['innovationsmanagement', 'innovation', 'инноваци'],
            'systemdenken'     => ['systemisches denken', 'systemdenken', 'systems thinking', 'системное мышление'],
            'lean'             => ['lean', 'kaizen', 'kanban', 'бережлив'],
            'prozesse'         => ['prozessoptimierung', 'prozess', 'process improvement', 'процесс'],
            'unternehmen'      => ['unternehmensentwicklung', 'organisationsentwicklung', 'развитие бизнеса', 'развитие компании'],
            'strategie'        => ['strategisches management', 'strategie', 'strategy', 'стратег'],
            'geschaeftsmodell' => ['geschäftsmodell', 'business model', 'бизнес-модел'],
            'produktion'       => ['produktion', 'fertigung', 'manufacturing', 'производств'],
            'handwerk'         => ['handwerk', 'elektrohandwerk', 'ремесл', 'монтаж'],
            'digitalisierung'  => ['digitalisierung', 'digitalization', 'digital', 'цифровизаци', 'цифров'],
        ];
        foreach ($woerter as $schluessel => $liste) {
            $muster[$schluessel] = $liste;
        }
    }

    $klein = ' ' . mb_strtolower($text) . ' ';
    $namen = [];
    foreach (triz_kategorien('news') as $k) {
        $namen[$k['schluessel']] = (int)$k['id'];
    }

    foreach ($muster as $schluessel => $liste) {
        if (!isset($namen[$schluessel])) {
            continue;
        }
        foreach ($liste as $wort) {
            if (str_contains($klein, $wort)) {
                return $namen[$schluessel];
            }
        }
    }
    return $vorgabe;
}

// --- Eine Nachrichtenquelle abarbeiten -------------------------------------

/** Rückgabe: Anzahl neu aufgenommener Beiträge. */
function triz_quelle_sammeln(array $quelle): int
{
    $inhalt = triz_abrufen((string)$quelle['url'], $fehler);
    if ($inhalt === null) {
        triz_quelle_vermerken((int)$quelle['id'], 0, (string)$fehler);
        return 0;
    }

    $eintraege = triz_feed_lesen($inhalt);
    if ($eintraege === []) {
        triz_quelle_vermerken((int)$quelle['id'], 0, 'Feed ohne verwertbare Einträge');
        return 0;
    }

    $grenze_tage = (int)triz_einstellung('beitrag_hoechstalter_tage', 400);
    $aeltestens  = time() - $grenze_tage * 86400;

    $stmt = db()->prepare(
        'INSERT IGNORE INTO triz_beitraege
           (quelle_id, titel, kurzfassung, url, url_hash, quelle_name,
            sprache, region, kategorie_id, veroeffentlicht_am)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $neu = 0;
    foreach ($eintraege as $e) {
        $titel = trim(html_entity_decode(strip_tags((string)$e['titel']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $url   = (string)$e['url'];
        if ($titel === '' || $url === '' || !str_starts_with($url, 'http')) {
            continue;
        }

        $text = trim(html_entity_decode(strip_tags((string)$e['text']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = (string)preg_replace('/\s+/u', ' ', $text);
        $text = mb_substr($text, 0, TRIZ_KURZFASSUNG_MAX);

        $zeit = $e['datum'] !== '' ? strtotime((string)$e['datum']) : false;
        // Ein Datum aus der Zukunft ist immer ein Fehler in der Quelle; dann
        // lieber ohne Datum als mit einem falschen ganz oben in der Liste.
        if ($zeit !== false && ($zeit < $aeltestens || $zeit > time() + 86400)) {
            if ($zeit < $aeltestens) {
                continue;
            }
            $zeit = false;
        }

        $sprache = triz_sprache_raten($titel . ' ' . $text, (string)$quelle['sprache']);
        $kategorie = triz_kategorie_raten(
            $titel . ' ' . $text,
            $quelle['kategorie_id'] !== null ? (int)$quelle['kategorie_id'] : null
        );

        $stmt->execute([
            (int)$quelle['id'],
            mb_substr($titel, 0, 400),
            $text,
            mb_substr($url, 0, 1000),
            triz_url_schluessel($url),
            mb_substr((string)$quelle['name'], 0, 160),
            $sprache,
            (string)$quelle['region'],
            $kategorie,
            $zeit !== false ? date('Y-m-d H:i:s', $zeit) : null,
        ]);
        $neu += $stmt->rowCount();
    }

    triz_quelle_vermerken((int)$quelle['id'], $neu, '');
    return $neu;
}

// --- YouTube ---------------------------------------------------------------

/**
 * Liest einen YouTube-Kanal über seinen öffentlichen Atom-Feed.
 *
 * Der Feed braucht keinen API-Schlüssel, liefert aber nur die letzten 15
 * Videos und keine Laufzeit. Ist ein Schlüssel hinterlegt, wird die Laufzeit
 * nachgeholt (siehe triz_dauern_nachtragen).
 */
function triz_youtube_sammeln(array $quelle): int
{
    $feed = triz_youtube_feed_adresse((string)$quelle['url']);
    if ($feed === null) {
        triz_quelle_vermerken((int)$quelle['id'], 0, 'Kanal-Adresse nicht erkannt');
        return 0;
    }

    $inhalt = triz_abrufen($feed, $fehler);
    if ($inhalt === null) {
        triz_quelle_vermerken((int)$quelle['id'], 0, (string)$fehler);
        return 0;
    }
    if (str_contains($inhalt, '<!ENTITY')) {
        return 0;
    }

    $vorher = libxml_use_internal_errors(true);
    $wurzel = simplexml_load_string($inhalt, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($vorher);

    if ($wurzel === false) {
        triz_quelle_vermerken((int)$quelle['id'], 0, 'Feed nicht lesbar');
        return 0;
    }

    $kanal = trim((string)($wurzel->title ?? $quelle['name']));

    $stmt = db()->prepare(
        'INSERT IGNORE INTO triz_videos
           (quelle_id, video_id, titel, kanal, beschreibung, thumbnail_url,
            sprache, kategorie_id, veroeffentlicht_am)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $neu = 0;
    foreach ($wurzel->entry ?? [] as $e) {
        $yt    = $e->children('http://www.youtube.com/xml/schemas/2015');
        $media = $e->children('http://search.yahoo.com/mrss/');

        $video_id = trim((string)($yt->videoId ?? ''));
        $titel    = trim((string)($e->title ?? ''));
        if ($video_id === '' || $titel === '') {
            continue;
        }

        $beschreibung = '';
        $bild = '';
        if (isset($media->group)) {
            $beschreibung = trim((string)($media->group->description ?? ''));
            // attributes() statt ['url'] - siehe die Begründung in
            // triz_feed_lesen(): Das Element steckt im Media-Namensraum, das
            // Attribut nicht.
            if (isset($media->group->thumbnail)) {
                $bild = (string)($media->group->thumbnail->attributes()['url'] ?? '');
            }
        }
        $beschreibung = mb_substr((string)preg_replace('/\s+/u', ' ', $beschreibung), 0, 1500);

        $zeit = strtotime((string)($e->published ?? ''));

        $stmt->execute([
            (int)$quelle['id'],
            mb_substr($video_id, 0, 20),
            mb_substr($titel, 0, 400),
            mb_substr($kanal, 0, 160),
            $beschreibung,
            mb_substr($bild, 0, 500),
            triz_sprache_raten($titel . ' ' . $beschreibung, (string)$quelle['sprache']),
            $quelle['kategorie_id'] !== null ? (int)$quelle['kategorie_id'] : null,
            $zeit !== false ? date('Y-m-d H:i:s', $zeit) : null,
        ]);
        $neu += $stmt->rowCount();
    }

    triz_quelle_vermerken((int)$quelle['id'], $neu, '');
    return $neu;
}

/**
 * Macht aus einer beliebigen Kanaladresse die Feed-Adresse.
 *
 * Für /channel/UC… geht das direkt. Bei @handle und /c/Name steht die
 * Kanalkennung nur in der Seite selbst; die wird dafür einmal geholt. Das
 * Ergebnis speichert die Verwaltung als Kanal-Adresse zurück, damit dieser
 * Umweg nicht bei jedem Lauf anfällt.
 */
function triz_youtube_feed_adresse(string $url): ?string
{
    $url = trim($url);

    if (str_contains($url, 'feeds/videos.xml')) {
        return $url;
    }
    if (preg_match('~/channel/(UC[\w-]{20,})~', $url, $t)) {
        return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $t[1];
    }
    if (preg_match('~^UC[\w-]{20,}$~', $url)) {
        return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $url;
    }
    if (!str_starts_with($url, 'http')) {
        // Ein bloßes @handle
        $url = 'https://www.youtube.com/' . ltrim($url, '/');
    }

    $seite = triz_abrufen($url);
    if ($seite === null) {
        return null;
    }
    if (preg_match('~"(?:channelId|externalId)":"(UC[\w-]{20,})"~', $seite, $t)) {
        return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $t[1];
    }
    if (preg_match('~/channel/(UC[\w-]{20,})~', $seite, $t)) {
        return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $t[1];
    }
    return null;
}

/**
 * Trägt die Laufzeiten nach — nur mit YouTube-API-Schlüssel.
 *
 * Ohne Schlüssel bleibt dauer_sekunden auf 0 und die Oberfläche zeigt keine
 * Dauer an. Das ist Absicht: Eine geschätzte Laufzeit wäre eine Angabe, die
 * wie eine Tatsache aussieht.
 */
function triz_dauern_nachtragen(int $hoechstens = 50): int
{
    $schluessel = (string)triz_einstellung('youtube_api_schluessel', '');
    if ($schluessel === '') {
        return 0;
    }

    $stmt = db()->prepare(
        'SELECT video_id FROM triz_videos WHERE dauer_sekunden = 0
         ORDER BY gefunden_am DESC LIMIT ?'
    );
    $stmt->bindValue(1, $hoechstens, PDO::PARAM_INT);
    $stmt->execute();
    $ids = array_column($stmt->fetchAll(), 'video_id');
    if ($ids === []) {
        return 0;
    }

    $geaendert = 0;
    // Die API nimmt bis zu 50 Kennungen auf einmal - ein Abruf statt fünfzig.
    foreach (array_chunk($ids, 50) as $gruppe) {
        $adresse = 'https://www.googleapis.com/youtube/v3/videos?part=contentDetails&id='
                 . urlencode(implode(',', $gruppe)) . '&key=' . urlencode($schluessel);
        $antwort = triz_abrufen($adresse);
        if ($antwort === null) {
            continue;
        }
        $daten = json_decode($antwort, true);
        if (!is_array($daten) || !isset($daten['items'])) {
            continue;
        }
        $up = db()->prepare('UPDATE triz_videos SET dauer_sekunden = ? WHERE video_id = ?');
        foreach ($daten['items'] as $eintrag) {
            $iso = $eintrag['contentDetails']['duration'] ?? '';
            $sek = triz_iso_dauer($iso);
            if ($sek > 0) {
                $up->execute([$sek, $eintrag['id']]);
                $geaendert++;
            }
        }
    }
    return $geaendert;
}

/** Wandelt eine ISO-8601-Dauer wie PT1H2M3S in Sekunden. */
function triz_iso_dauer(string $iso): int
{
    if (!preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $t)) {
        return 0;
    }
    return ((int)($t[1] ?? 0)) * 86400 + ((int)($t[2] ?? 0)) * 3600
         + ((int)($t[3] ?? 0)) * 60 + (int)($t[4] ?? 0);
}

// --- Vorschaubilder --------------------------------------------------------

/**
 * Legt das Vorschaubild eines Videos lokal ab.
 *
 * Grund: Ein <img src="https://i.ytimg.com/…"> ließe den Browser jedes
 * Besuchers eine Verbindung zu Google aufbauen, samt IP-Adresse und
 * Referrer. Das widerspricht der Zusage der Datenschutzerklärung, dass die
 * Seiten ausschließlich lokale Dateien laden. Der Server holt das Bild
 * deshalb einmal und liefert es danach selbst aus.
 *
 * Rückgabe: Pfad der lokalen Datei oder null.
 */
function triz_thumbnail_datei(string $video_id, bool $holen = true): ?string
{
    if (!preg_match('/^[\w-]{5,20}$/', $video_id)) {
        return null;
    }
    $ordner = triz_inhalte_pfad() . '/thumbs';
    $datei  = $ordner . '/' . $video_id . '.jpg';

    if (is_file($datei) && filesize($datei) > 0) {
        return $datei;
    }
    if (!$holen) {
        return null;
    }
    if (!is_dir($ordner) && !@mkdir($ordner, 0750, true) && !is_dir($ordner)) {
        return null;
    }

    // mqdefault gibt es zu jedem Video; die höher auflösenden Varianten fehlen
    // bei älteren Videos und lieferten dann ein Platzhalterbild.
    foreach (['hqdefault', 'mqdefault'] as $art) {
        $bild = triz_abrufen("https://i.ytimg.com/vi/{$video_id}/{$art}.jpg");
        // Ein JPEG beginnt mit FF D8. Damit fällt eine Fehlerseite auf, die
        // mit Status 200 ausgeliefert wird.
        if ($bild !== null && strlen($bild) > 1000 && str_starts_with($bild, "\xFF\xD8")) {
            @file_put_contents($datei, $bild);
            return is_file($datei) ? $datei : null;
        }
    }
    return null;
}

// --- Lauf ------------------------------------------------------------------

function triz_quelle_vermerken(int $quelle_id, int $treffer, string $fehler): void
{
    try {
        $stmt = db()->prepare(
            'UPDATE triz_quellen
             SET letzter_lauf = NOW(), letzter_fehler = ?, treffer_gesamt = treffer_gesamt + ?
             WHERE id = ?'
        );
        $stmt->execute([mb_substr($fehler, 0, 255), max(0, $treffer), $quelle_id]);
    } catch (Throwable $e) {
        error_log('TRIZ-Quelle nicht aktualisiert: ' . $e->getMessage());
    }
}

/**
 * Der komplette Lauf über alle aktiven Quellen.
 *
 * Rückgabe: ['beitraege' => int, 'videos' => int, 'quellen' => int, 'fehler' => string[]]
 */
function triz_alles_sammeln(?callable $melden = null): array
{
    $stmt = db()->query('SELECT * FROM triz_quellen WHERE aktiv = 1 ORDER BY art, id');
    $quellen = $stmt->fetchAll();

    $ergebnis = ['beitraege' => 0, 'videos' => 0, 'quellen' => count($quellen), 'fehler' => []];

    foreach ($quellen as $q) {
        try {
            if ($q['art'] === 'youtube') {
                $ergebnis['videos'] += triz_youtube_sammeln($q);
            } else {
                $ergebnis['beitraege'] += triz_quelle_sammeln($q);
            }
        } catch (Throwable $e) {
            // Eine kaputte Quelle darf den Lauf nicht beenden - sonst bleibt
            // alles Nachfolgende ungesammelt.
            $ergebnis['fehler'][] = $q['name'] . ': ' . $e->getMessage();
            triz_quelle_vermerken((int)$q['id'], 0, mb_substr($e->getMessage(), 0, 200));
        }
        if ($melden !== null) {
            $melden($q, $ergebnis);
        }
    }

    $ergebnis['dauern'] = triz_dauern_nachtragen();

    // Vorschaubilder der neuesten Videos gleich mitnehmen, damit die
    // Videothek beim ersten Aufruf nicht auf Abrufe warten muss.
    $neue = db()->query(
        'SELECT video_id FROM triz_videos ORDER BY gefunden_am DESC LIMIT 60'
    )->fetchAll();
    foreach ($neue as $v) {
        triz_thumbnail_datei((string)$v['video_id']);
    }

    return $ergebnis;
}

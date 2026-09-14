<?php
/**
 * Persönliche YouTube-Abos im TRIZ-Portal.
 *
 * Jeder Benutzer kann eigene Kanäle führen und sieht deren neue Videos auf
 * einer Seite, die nur ihm gehört. Warum eigene Tabellen und nicht die
 * Videothek: siehe den Kommentar zu triz_abos in schema_triz.sql.
 *
 * Grundregel dieser Datei: Jede Abfrage, die Abos oder Abo-Videos liest oder
 * ändert, bindet die benutzer_id des Besitzers mit ein. Eine Abo-ID allein
 * reicht nie - sie stammt aus einem Formular und lässt sich beliebig ändern.
 *
 * Gelesen wird über denselben Feed-Leser wie die Videothek
 * (triz_youtube_feed_lesen in triz_sammler.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/triz_sammler.php';

/**
 * Wie alt ein Video höchstens sein darf, um aufgenommen zu werden.
 *
 * Derselbe Wert gilt beim Sammeln und beim Aufräumen. Wichen beide
 * voneinander ab, würde das Aufräumen um 4 Uhr Videos löschen, die der
 * Sammellauf um 5 Uhr aus dem Feed sofort wieder einträgt - jeden Tag aufs
 * Neue.
 */
function triz_abo_hoechstalter_tage(): int
{
    return max(7, (int)triz_einstellung('abo_hoechstalter_tage', 180));
}

/**
 * Die Bereiche, in die sich Abos einteilen lassen - in Anzeigereihenfolge.
 *
 * Schlüssel => Übersetzungsschlüssel. Die einzige Stelle, an der die Bereiche
 * festgelegt sind: Die Datenbank speichert nur den Schlüssel als Text, damit
 * ein neuer Bereich hier eine Zeile ist und kein Umbau der Tabelle.
 * "verschiedenes" ist der Auffangbereich und muss letzter bleiben.
 */
function triz_abo_bereiche(): array
{
    return [
        'triz'          => 'abo_bereich_triz',
        'ki'            => 'abo_bereich_ki',
        'bienen'        => 'abo_bereich_bienen',
        'sport'         => 'abo_bereich_sport',
        'verschiedenes' => 'abo_bereich_verschiedenes',
    ];
}

/** Anzeigename eines Bereichs; Unbekanntes fällt auf "Verschiedenes". */
function triz_abo_bereich_name(string $bereich): string
{
    $alle = triz_abo_bereiche();
    return t($alle[$bereich] ?? $alle['verschiedenes']);
}

/** Setzt den Bereich eines eigenen Abos. Rückgabe: Kanalname, oder null. */
function triz_abo_bereich_setzen(int $benutzer_id, int $abo_id, string $bereich): ?string
{
    if (!array_key_exists($bereich, triz_abo_bereiche())) {
        return null;
    }
    $stmt = db()->prepare('SELECT name FROM triz_abos WHERE id = ? AND benutzer_id = ?');
    $stmt->execute([$abo_id, $benutzer_id]);
    $name = $stmt->fetchColumn();
    if ($name === false) {
        return null;
    }
    $up = db()->prepare('UPDATE triz_abos SET bereich = ? WHERE id = ? AND benutzer_id = ?');
    $up->execute([$bereich, $abo_id, $benutzer_id]);
    return (string)$name;
}

/** Hat der Benutzer mindestens ein Abo? Steuert, ob der Menüpunkt erscheint. */
function triz_hat_abos(int $benutzer_id): bool
{
    static $cache = [];
    if (!array_key_exists($benutzer_id, $cache)) {
        try {
            $stmt = db()->prepare('SELECT 1 FROM triz_abos WHERE benutzer_id = ? LIMIT 1');
            $stmt->execute([$benutzer_id]);
            $cache[$benutzer_id] = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            // Tabelle noch nicht eingespielt: dann eben keine Abos - die
            // Navigation darf daran nicht scheitern.
            $cache[$benutzer_id] = false;
        }
    }
    return $cache[$benutzer_id];
}

/** Prüft die Form einer YouTube-Kanal-ID (UC gefolgt von 22 Zeichen). */
function triz_ist_kanal_id(string $id): bool
{
    return (bool)preg_match('/^UC[\w-]{22}$/', $id);
}

/**
 * Macht aus einer Eingabe - Kanal-ID, Kanaladresse oder @handle - die
 * Kanal-ID. Bei einem @handle wird dafür einmal die Kanalseite geholt.
 */
function triz_abo_kanal_id(string $eingabe): ?string
{
    $eingabe = trim($eingabe);
    if ($eingabe === '') {
        return null;
    }
    if (triz_ist_kanal_id($eingabe)) {
        return $eingabe;
    }
    $feed = triz_youtube_feed_adresse($eingabe);
    if ($feed !== null && preg_match('/channel_id=(UC[\w-]{22})/', $feed, $t)) {
        return $t[1];
    }
    return null;
}

/**
 * Übernimmt eine Liste von Kanälen für einen Benutzer.
 *
 * $liste: [['id' => 'UC…', 'name' => '…'], …]. Schon vorhandene Kanäle werden
 * übergangen, nicht verdoppelt. Rückgabe: Anzahl neu angelegter Abos.
 */
function triz_abos_importieren(int $benutzer_id, array $liste): int
{
    $stmt = db()->prepare(
        'INSERT IGNORE INTO triz_abos (benutzer_id, kanal_id, name) VALUES (?, ?, ?)'
    );
    $neu = 0;
    foreach ($liste as $k) {
        $id = trim((string)($k['id'] ?? ''));
        if (!triz_ist_kanal_id($id)) {
            continue;
        }
        $stmt->execute([$benutzer_id, $id, mb_substr(trim((string)($k['name'] ?? '')), 0, 160)]);
        $neu += $stmt->rowCount();
    }
    return $neu;
}

/**
 * Fügt einen einzelnen Kanal hinzu und holt gleich seine Videos.
 *
 * Rückgabe: ['ok' => bool, 'text' => Meldung für die Oberfläche]
 */
function triz_abo_hinzufuegen(int $benutzer_id, string $eingabe, string $bereich = 'verschiedenes'): array
{
    if (!array_key_exists($bereich, triz_abo_bereiche())) {
        $bereich = 'verschiedenes';
    }

    $kanal_id = triz_abo_kanal_id($eingabe);
    if ($kanal_id === null) {
        return ['ok' => false, 'text' => t('abo_nicht_erkannt')];
    }

    // Den Feed einmal lesen: Das beweist, dass der Kanal existiert, und
    // liefert den Namen, wie YouTube ihn führt.
    $gelesen = triz_youtube_feed_lesen(
        'https://www.youtube.com/feeds/videos.xml?channel_id=' . $kanal_id,
        $fehler
    );
    if ($gelesen === null) {
        return ['ok' => false, 'text' => t('abo_nicht_erreichbar', (string)$fehler)];
    }

    $stmt = db()->prepare(
        'INSERT IGNORE INTO triz_abos (benutzer_id, kanal_id, name, bereich) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$benutzer_id, $kanal_id, mb_substr($gelesen['kanal'], 0, 160), $bereich]);

    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'text' => t('abo_schon_da', $gelesen['kanal'])];
    }

    $abo = db()->prepare('SELECT * FROM triz_abos WHERE benutzer_id = ? AND kanal_id = ?');
    $abo->execute([$benutzer_id, $kanal_id]);
    $zeile = $abo->fetch();
    $videos = $zeile ? triz_abo_eintraege_speichern($zeile, $gelesen) : 0;

    return ['ok' => true, 'text' => t('abo_hinzugefuegt', $gelesen['kanal'], $videos)];
}

/**
 * Entfernt ein Abo - ausschließlich eines des angegebenen Benutzers.
 * Die zugehörigen Videos gehen per ON DELETE CASCADE mit.
 */
function triz_abo_entfernen(int $benutzer_id, int $abo_id): ?string
{
    $stmt = db()->prepare('SELECT name FROM triz_abos WHERE id = ? AND benutzer_id = ?');
    $stmt->execute([$abo_id, $benutzer_id]);
    $name = $stmt->fetchColumn();
    if ($name === false) {
        return null;
    }
    $del = db()->prepare('DELETE FROM triz_abos WHERE id = ? AND benutzer_id = ?');
    $del->execute([$abo_id, $benutzer_id]);
    return (string)$name;
}

/**
 * Schreibt die Einträge eines gelesenen Feeds für ein Abo in die Datenbank.
 * Rückgabe: Anzahl neuer Videos.
 */
function triz_abo_eintraege_speichern(array $abo, array $gelesen): int
{
    $grenze = time() - triz_abo_hoechstalter_tage() * 86400;

    $stmt = db()->prepare(
        'INSERT IGNORE INTO triz_abo_videos
           (abo_id, video_id, titel, beschreibung, veroeffentlicht_am)
         VALUES (?, ?, ?, ?, ?)'
    );

    $neu = 0;
    foreach ($gelesen['eintraege'] as $v) {
        if ($v['zeit'] !== null && strtotime($v['zeit']) < $grenze) {
            continue;
        }
        $stmt->execute([
            (int)$abo['id'],
            $v['video_id'],
            $v['titel'],
            $v['beschreibung'],
            $v['zeit'],
        ]);
        $neu += $stmt->rowCount();
    }

    // Den Namen nachziehen, falls YouTube ihn geändert hat oder er beim
    // Import leer blieb.
    $up = db()->prepare(
        "UPDATE triz_abos SET letzter_lauf = NOW(), letzter_fehler = '',
                name = IF(? <> '', ?, name)
         WHERE id = ?"
    );
    $kanal = mb_substr($gelesen['kanal'], 0, 160);
    $up->execute([$kanal, $kanal, (int)$abo['id']]);

    return $neu;
}

/**
 * Sammelt neue Videos aus den Abos.
 *
 * Ohne $benutzer_id alle aktiven Abos aller Benutzer (für den nächtlichen
 * Lauf), mit $benutzer_id nur die eigenen (für den Knopf auf der Abo-Seite).
 *
 * Rückgabe: ['abos' => n, 'videos' => n, 'fehler' => [Kanal => Grund]]
 */
function triz_abos_sammeln(?int $benutzer_id = null, ?callable $melden = null): array
{
    $ergebnis = ['abos' => 0, 'videos' => 0, 'fehler' => []];

    try {
        if ($benutzer_id === null) {
            $abos = db()->query('SELECT * FROM triz_abos WHERE aktiv = 1 ORDER BY id')->fetchAll();
        } else {
            $stmt = db()->prepare('SELECT * FROM triz_abos WHERE aktiv = 1 AND benutzer_id = ? ORDER BY id');
            $stmt->execute([$benutzer_id]);
            $abos = $stmt->fetchAll();
        }
    } catch (Throwable $e) {
        // Tabellen noch nicht eingespielt - dann gibt es schlicht nichts zu tun.
        return $ergebnis;
    }

    $fehler_stmt = db()->prepare(
        'UPDATE triz_abos SET letzter_lauf = NOW(), letzter_fehler = ? WHERE id = ?'
    );

    foreach ($abos as $abo) {
        $ergebnis['abos']++;
        $name = $abo['name'] !== '' ? (string)$abo['name'] : (string)$abo['kanal_id'];

        try {
            $gelesen = triz_youtube_feed_lesen(
                'https://www.youtube.com/feeds/videos.xml?channel_id=' . $abo['kanal_id'],
                $fehler
            );
            if ($gelesen === null) {
                $fehler_stmt->execute([mb_substr((string)$fehler, 0, 255), (int)$abo['id']]);
                $ergebnis['fehler'][$name] = (string)$fehler;
            } else {
                $ergebnis['videos'] += triz_abo_eintraege_speichern($abo, $gelesen);
            }
        } catch (Throwable $e) {
            // Ein kaputter Kanal darf die übrigen nicht aufhalten.
            $fehler_stmt->execute([mb_substr($e->getMessage(), 0, 255), (int)$abo['id']]);
            $ergebnis['fehler'][$name] = $e->getMessage();
        }

        if ($melden !== null) {
            $melden($abo, $ergebnis);
        }
    }

    // Vorschaubilder der neuesten Videos vorab holen, damit die Seite beim
    // ersten Aufruf nicht auf Dutzende Einzelabrufe warten muss. Der Rest
    // kommt bei Bedarf über thumbnail.php.
    try {
        $sql = 'SELECT v.video_id FROM triz_abo_videos v JOIN triz_abos a ON a.id = v.abo_id';
        $werte = [];
        if ($benutzer_id !== null) {
            $sql .= ' WHERE a.benutzer_id = ?';
            $werte[] = $benutzer_id;
        }
        $sql .= ' ORDER BY v.gefunden_am DESC, v.veroeffentlicht_am DESC LIMIT 40';
        $stmt = db()->prepare($sql);
        $stmt->execute($werte);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $video_id) {
            triz_thumbnail_datei((string)$video_id);
        }
    } catch (Throwable $e) {
        error_log('Abo-Vorschaubilder: ' . $e->getMessage());
    }

    return $ergebnis;
}

/** Darf dieser Benutzer das Vorschaubild zu dieser Video-ID sehen? */
function triz_abo_video_gehoert(int $benutzer_id, string $video_id): bool
{
    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM triz_abo_videos v JOIN triz_abos a ON a.id = v.abo_id
             WHERE v.video_id = ? AND a.benutzer_id = ? LIMIT 1'
        );
        $stmt->execute([$video_id, $benutzer_id]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

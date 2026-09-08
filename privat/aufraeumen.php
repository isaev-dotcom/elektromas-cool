<?php
/**
 * Aufräumen — löscht abgelaufene Token und alte Protokolldaten.
 *
 * Art. 5 Abs. 1 lit. e DSGVO verlangt, personenbezogene Daten nicht länger
 * aufzubewahren als nötig. Ohne diesen Lauf wüchse das Protokoll unbegrenzt.
 *
 * Einrichten im Alfahosting-Panel unter CRONJOBS, täglich, Befehl:
 *
 *   /usr/bin/php /var/www/vhosts/h283886.host298.alfahosting-server.de/privat/aufraeumen.php
 *
 * Läuft absichtlich nur auf der Kommandozeile, nicht über den Browser.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Dieses Skript läuft nur über die Kommandozeile.\n");
}

require_once __DIR__ . '/lib/bootstrap.php';

$protokoll_tage = (int)($CONFIG['aufbewahrung']['protokoll_tage'] ?? 90);
$versuche_tage  = (int)($CONFIG['aufbewahrung']['anmeldeversuche_tage'] ?? 7);

$pdo = db();
$bericht = [];

/*
 * Die Fristen werden als Ganzzahl in die Abfrage geschrieben statt gebunden:
 * Ein Platzhalter hinter INTERVAL wird nicht von jeder MySQL- und
 * MariaDB-Version akzeptiert. Sicher ist das, weil der Wert aus der
 * Konfiguration stammt und mit (int) erzwungen wird - eine Eingabe von außen
 * erreicht diese Stelle nie.
 */
$protokoll_tage = max(1, $protokoll_tage);
$versuche_tage  = max(1, $versuche_tage);

// Protokolleinträge nach Frist löschen.
$anzahl = $pdo->exec(
    'DELETE FROM protokoll WHERE zeitpunkt < (NOW() - INTERVAL ' . $protokoll_tage . ' DAY)'
);
$bericht[] = $anzahl . " Protokolleinträge (älter als {$protokoll_tage} Tage)";

// Anmeldeversuche werden nur für die Brute-Force-Bremse gebraucht.
$anzahl = $pdo->exec(
    'DELETE FROM anmeldeversuche WHERE zeitpunkt < (NOW() - INTERVAL ' . $versuche_tage . ' DAY)'
);
$bericht[] = $anzahl . " Anmeldeversuche (älter als {$versuche_tage} Tage)";

// Abgelaufene oder benutzte Token haben keinen Zweck mehr.
$anzahl = $pdo->exec(
    'DELETE FROM passwort_resets WHERE gueltig_bis < NOW() OR benutzt_am IS NOT NULL'
);
$bericht[] = $anzahl . ' Passwort-Token';

$anzahl = $pdo->exec(
    'DELETE FROM einladungen WHERE gueltig_bis < NOW() OR eingeloest_am IS NOT NULL'
);
$bericht[] = $anzahl . ' Einladungs-Token';

/*
 * Business TRIZ Portal.
 *
 * Der ganze Block hängt daran, dass die Tabellen existieren: Wer schema.sql
 * eingespielt hat, aber schema_triz.sql noch nicht, soll hier keinen
 * Abbruch bekommen. Ein einzelner Blick in die Tabellenliste ist billiger
 * als ein try/catch um jede Abfrage.
 */
$triz_da = (bool)$pdo->query("SHOW TABLES LIKE 'triz_protokoll'")->fetchColumn();

if ($triz_da) {
    $anzahl = $pdo->exec(
        'DELETE FROM triz_protokoll WHERE zeitpunkt < (NOW() - INTERVAL ' . $protokoll_tage . ' DAY)'
    );
    $bericht[] = $anzahl . " TRIZ-Protokolleinträge (älter als {$protokoll_tage} Tage)";

    $anzahl = $pdo->exec(
        'DELETE FROM triz_anmeldeversuche WHERE zeitpunkt < (NOW() - INTERVAL ' . $versuche_tage . ' DAY)'
    );
    $bericht[] = $anzahl . ' TRIZ-Anmeldeversuche';

    $anzahl = $pdo->exec(
        'DELETE FROM triz_resets WHERE gueltig_bis < NOW() OR benutzt_am IS NOT NULL'
    );
    $bericht[] = $anzahl . ' TRIZ-Passwort-Token';

    $anzahl = $pdo->exec(
        'DELETE FROM triz_einladungen WHERE gueltig_bis < NOW() OR eingeloest_am IS NOT NULL'
    );
    $bericht[] = $anzahl . ' TRIZ-Einladungs-Token';

    /*
     * Die Fragen an den KI-Assistenten sind personenbezogene Daten und
     * gehören nicht dauerhaft in die Datenbank. Die Frist steht in der
     * Konfiguration und ist bewusst kurz: Der Verlauf dient dazu, ein
     * Gespräch fortzusetzen, nicht als Archiv.
     */
    $ki_tage = max(1, (int)($CONFIG['triz']['ki_verlauf_tage'] ?? 30));
    $anzahl = $pdo->exec(
        'DELETE FROM triz_ki_verlauf WHERE erstellt_am < (NOW() - INTERVAL ' . $ki_tage . ' DAY)'
    );
    $bericht[] = $anzahl . " Einträge im KI-Verlauf (älter als {$ki_tage} Tage)";

    /*
     * Favoriten und Kommentare zeigen ohne Fremdschlüssel auf ihr Objekt -
     * siehe den Kommentar in schema_triz.sql. Wird ein Dokument gelöscht,
     * bleibt der Favorit als Leiche zurück; hier fliegt er raus.
     */
    $verwaist = 0;
    foreach ([
        'beitrag'    => 'triz_beitraege',
        'video'      => 'triz_videos',
        'dokument'   => 'triz_dokumente',
        'bibliothek' => 'triz_bibliothek',
    ] as $art => $tabelle) {
        $verwaist += (int)$pdo->exec(
            "DELETE f FROM triz_favoriten f
             LEFT JOIN {$tabelle} o ON o.id = f.objekt_id
             WHERE f.objekt_art = '{$art}' AND o.id IS NULL"
        );
        $verwaist += (int)$pdo->exec(
            "DELETE k FROM triz_kommentare k
             LEFT JOIN {$tabelle} o ON o.id = k.objekt_id
             WHERE k.objekt_art = '{$art}' AND o.id IS NULL"
        );
    }
    $bericht[] = $verwaist . ' verwaiste Favoriten und Kommentare';

    /*
     * Vorschaubilder zu Videos, die es nicht mehr gibt. Sie kosten nur Platz
     * und werden beim nächsten Bedarf ohnehin neu geholt.
     */
    $thumbs = __DIR__ . '/triz_inhalte/thumbs';
    $bilder = 0;
    if (is_dir($thumbs)) {
        $bekannt = $pdo->query('SELECT video_id FROM triz_videos')->fetchAll(PDO::FETCH_COLUMN);
        $bekannt = array_flip($bekannt);
        foreach (glob($thumbs . '/*.jpg') ?: [] as $datei) {
            $id = basename($datei, '.jpg');
            if (!isset($bekannt[$id]) && @unlink($datei)) {
                $bilder++;
            }
        }
    }
    $bericht[] = $bilder . ' verwaiste Vorschaubilder';
}

// Verwaiste Sitzungsdateien.
$sitzungen = __DIR__ . '/sessions';
$geloescht = 0;
if (is_dir($sitzungen)) {
    $grenze = time() - 24 * 3600;
    foreach (glob($sitzungen . '/sess_*') ?: [] as $datei) {
        if (is_file($datei) && filemtime($datei) < $grenze && @unlink($datei)) {
            $geloescht++;
        }
    }
}
$bericht[] = $geloescht . ' Sitzungsdateien';

echo date('Y-m-d H:i:s') . " Aufräumen erledigt:\n  - " . implode("\n  - ", $bericht) . "\n";

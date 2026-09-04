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

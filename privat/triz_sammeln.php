<?php
/**
 * Tägliche Sammlung für das Business TRIZ Portal.
 *
 * Ruft alle aktiven Quellen ab und legt neue Beiträge und Videos an.
 *
 * Einrichten im Alfahosting-Panel unter CRONJOBS, täglich (etwa 5:00 Uhr):
 *
 *   /usr/bin/php /var/www/vhosts/h283886.host298.alfahosting-server.de/privat/triz_sammeln.php
 *
 * Läuft absichtlich nur auf der Kommandozeile: Über den Browser aufrufbar
 * wäre es ein Weg, den Server von außen zu beschäftigen. Für „jetzt sofort"
 * gibt es den Knopf in der Verwaltung.
 *
 * Der Lauf ist gutmütig: Eine tote Quelle beendet ihn nicht, sondern wird an
 * der Quelle vermerkt und in der Verwaltung sichtbar.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Dieses Skript läuft nur über die Kommandozeile.\n");
}

require_once __DIR__ . '/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/triz.php';
require_once PRIVAT_PFAD . '/lib/triz_sammler.php';

// Feeds antworten unterschiedlich schnell; bei zwei Dutzend Quellen kommen
// leicht ein paar Minuten zusammen. Auf der Kommandozeile gibt es dafür
// üblicherweise ohnehin keine Zeitgrenze - hier trotzdem ausdrücklich.
@set_time_limit(0);

$beginn = microtime(true);
echo date('Y-m-d H:i:s') . " Sammlung gestartet\n";

$ergebnis = triz_alles_sammeln(static function (array $quelle): void {
    // Fortschritt mitschreiben: Bricht der Lauf ab, steht in der
    // Cron-Mail, an welcher Quelle es passiert ist.
    echo '  - ' . $quelle['name'] . "\n";
});

$dauer = round(microtime(true) - $beginn, 1);

echo sprintf(
    "%s Fertig nach %s s: %d Quellen, %d neue Beiträge, %d neue Videos, %d Laufzeiten ergänzt\n",
    date('Y-m-d H:i:s'),
    $dauer,
    $ergebnis['quellen'],
    $ergebnis['beitraege'],
    $ergebnis['videos'],
    $ergebnis['dauern'] ?? 0
);

if ($ergebnis['fehler'] !== []) {
    echo "Fehler:\n  - " . implode("\n  - ", $ergebnis['fehler']) . "\n";
}

// Quellen, die seit einer Woche nichts liefern, gehören angesehen. Die
// Meldung landet in der Cron-Mail an den Administrator.
$stumm = db()->query(
    "SELECT name, letzter_fehler FROM triz_quellen
     WHERE aktiv = 1 AND letzter_fehler <> '' ORDER BY name"
)->fetchAll();

if ($stumm !== []) {
    echo "Quellen mit Fehlermeldung:\n";
    foreach ($stumm as $q) {
        echo '  - ' . $q['name'] . ': ' . $q['letzter_fehler'] . "\n";
    }
}

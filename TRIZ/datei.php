<?php
/**
 * Liefert ein internes Dokument aus.
 *
 * Die Dateien liegen unter privat/triz_inhalte/ und damit außerhalb des
 * Web-Verzeichnisses. Es gibt keine Adresse, unter der sie direkt abrufbar
 * wären - jeder Abruf läuft durch diese Datei und damit durch die
 * Anmeldeprüfung. Dasselbe Prinzip wie Schulungen/datei.php.
 *
 *   datei.php?d=12        im Browser anzeigen, wenn das Format es hergibt
 *   datei.php?d=12&dl=1   herunterladen
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

// true: PDF- und Videovorschau brauchen data:/blob: als Medienquelle.
sicherheits_header(true);
$ich = triz_login_verlangen();

$id = (int)($_GET['d'] ?? 0);
$herunterladen = isset($_GET['dl']);

$stmt = db()->prepare('SELECT * FROM triz_dokumente WHERE id = ?');
$stmt->execute([$id]);
$dok = $stmt->fetch();

if (!$dok) {
    http_response_code(404);
    exit('Dokument nicht gefunden.');
}

/*
 * Der Dateiname kommt ausschließlich aus der Datenbank, nie aus der
 * Adresszeile. basename() ist die zweite Sperre: Selbst wenn dort je
 * "../../config.php" stünde, bliebe davon nur "config.php" übrig und der Pfad
 * zeigt weiter in das Inhaltsverzeichnis. Damit ist Path Traversal
 * ausgeschlossen.
 */
$pfad = triz_inhalte_pfad() . '/' . basename((string)$dok['dateiname']);

if (!is_file($pfad) || !is_readable($pfad)) {
    error_log('TRIZ-Dokument fehlt: ' . $pfad);
    http_response_code(404);
    exit('Die Datei ist nicht verfügbar.');
}

$zaehler = db()->prepare('UPDATE triz_dokumente SET downloads = downloads + 1 WHERE id = ?');
$zaehler->execute([$id]);

triz_protokoll('dokument_geoeffnet', (string)$ich['email'], (int)$ich['id'], (string)$dok['titel']);

$mime = (string)$dok['mime'];

// Nur Formate anzeigen, die der Browser sicher darstellt. Ein Word-Dokument
// "inline" auszuliefern führt ohnehin zum Download - dann lieber gleich mit
// dem richtigen Namen.
$anzeigbar = in_array($mime, [
    'application/pdf', 'text/plain', 'text/csv', 'video/mp4', 'video/webm',
], true);

$art = ($herunterladen || !$anzeigbar) ? 'attachment' : 'inline';

// Der Originalname geht in eine Kopfzeile: Zeilenumbrüche und
// Anführungszeichen müssen raus, sonst ließen sich weitere Kopfzeilen
// einschleusen. Zusätzlich der RFC-5987-Name für Umlaute und Kyrillisch.
$name = (string)($dok['original_name'] !== '' ? $dok['original_name'] : $dok['dateiname']);
$name = str_replace(["\r", "\n", '"'], '', $name);
$name_ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'dokument';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($pfad));
header(sprintf(
    'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
    $art,
    $name_ascii,
    rawurlencode($name)
));
// Nicht in Zwischenspeichern ablegen, die mehrere Nutzer teilen.
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

// readfile streamt in Blöcken - auch eine 200-MB-Videodatei belegt damit
// nicht den gesamten Arbeitsspeicher.
readfile($pfad);

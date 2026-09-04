<?php
/**
 * Liefert eine Schulungsdatei aus.
 *
 * Die Dateien liegen unter privat/inhalte/ und damit außerhalb des
 * Web-Verzeichnisses. Es gibt keine Adresse, unter der sie direkt abrufbar
 * wären - jeder Abruf läuft durch diese Datei und damit durch die
 * Anmeldeprüfung.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';

// true: die Schulungen bringen eingebettete Videos als data:/blob: mit.
sicherheits_header(true);
$benutzer = login_verlangen();

$id = (string)($_GET['s'] ?? '');

$katalog_datei = PRIVAT_PFAD . '/inhalte/schulungen.json';
$katalog = is_file($katalog_datei)
    ? json_decode((string)file_get_contents($katalog_datei), true)
    : [];

$treffer = null;
foreach ((is_array($katalog) ? $katalog : []) as $s) {
    if (is_array($s) && ($s['id'] ?? '') === $id) {
        $treffer = $s;
        break;
    }
}

if ($treffer === null) {
    http_response_code(404);
    exit('Schulung nicht gefunden.');
}

/*
 * Der Dateiname kommt ausschließlich aus dem Katalog, nie aus der Adresszeile.
 * basename() ist die zweite Sperre: Selbst wenn im Katalog "../../config.php"
 * stünde, bliebe davon nur "config.php" übrig und der Pfad zeigt weiter in
 * das Inhaltsverzeichnis. Damit ist Path Traversal ausgeschlossen.
 */
$datei = PRIVAT_PFAD . '/inhalte/' . basename((string)$treffer['datei']);

if (!is_file($datei) || !is_readable($datei)) {
    error_log('Schulungsdatei fehlt: ' . $datei);
    http_response_code(404);
    exit('Die Schulungsdatei ist nicht verfügbar.');
}

protokoll('schulung_geoeffnet', $benutzer['email'], (int)$benutzer['id'], (string)$treffer['id']);

header('Content-Type: text/html; charset=UTF-8');
header('Content-Length: ' . filesize($datei));
// Nicht in Zwischenspeichern ablegen, die mehrere Nutzer teilen.
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

// readfile streamt in Blöcken, der Arbeitsspeicher bleibt also auch bei den
// 37-MB-Dateien niedrig.
readfile($datei);

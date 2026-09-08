<?php
/**
 * Liefert das Vorschaubild eines Videos aus.
 *
 * Warum nicht direkt von YouTube einbinden: Ein <img> auf i.ytimg.com ließe
 * den Browser jedes Besuchers eine Verbindung zu Google aufbauen - mit
 * IP-Adresse und Referrer, ohne Einwilligung und entgegen der Zusage der
 * Datenschutzerklärung, dass die Seiten ausschließlich lokale Dateien laden.
 *
 * Der Server holt das Bild deshalb einmal und legt es unter
 * privat/triz_inhalte/thumbs/ ab. Von dort aus liefert diese Datei es aus -
 * nach Prüfung der Anmeldung, wie jede andere Auslieferung im Portal auch.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';
require_once PRIVAT_PFAD . '/lib/triz_sammler.php';

triz_login_verlangen();

$video_id = (string)($_GET['v'] ?? '');

// Die Kennung kommt aus der Adresszeile und wird deshalb streng geprüft: Sie
// geht in einen Dateinamen ein, und ein Muster ohne Punkte und Schrägstriche
// schließt jeden Ausbruch aus dem Verzeichnis aus.
if (!preg_match('/^[\w-]{5,20}$/', $video_id)) {
    http_response_code(400);
    exit;
}

// Nur Bilder zu Videos, die wir auch führen. Sonst wäre das hier ein offener
// Bildabruf-Dienst für beliebige YouTube-Kennungen.
$stmt = db()->prepare('SELECT 1 FROM triz_videos WHERE video_id = ?');
$stmt->execute([$video_id]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    exit;
}

$datei = triz_thumbnail_datei($video_id);

if ($datei === null || !is_file($datei)) {
    // Kein Bild verfügbar - ein durchsichtiges Ein-Pixel-Bild statt eines
    // 404, damit die Karte im Raster nicht als kaputtes Bild erscheint.
    header('Content-Type: image/gif');
    header('Cache-Control: private, max-age=300');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($datei));
// Privat, weil die Auslieferung an die Anmeldung gebunden ist: Ein geteilter
// Zwischenspeicher dürfte das Bild nicht an Nichtangemeldete weiterreichen.
header('Cache-Control: private, max-age=604800');
header('X-Robots-Tag: noindex, nofollow');

readfile($datei);

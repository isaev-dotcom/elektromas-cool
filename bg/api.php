<?php
/**
 * Datenschnittstelle des Gefährdungsbeurteilungs-Portals.
 *
 * Nimmt JSON entgegen und antwortet mit JSON. Alles läuft über POST, auch das
 * Lesen: Damit trägt jede Anfrage den CSRF-Schlüssel, und nichts landet in
 * Server-Logs oder im Browserverlauf.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/bg.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/** @param array<string,mixed> $daten */
function bg_antwort(array $daten, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!ist_post()) {
    bg_antwort(['ok' => false, 'fehler' => 'nur_post'], 405);
}

sitzung_starten();
$benutzer = bg_benutzer();
if ($benutzer === null) {
    // 401 statt Weiterleitung: Die Oberfläche soll den Hinweis anzeigen
    // können, statt HTML einer Anmeldeseite zu bekommen.
    bg_antwort(['ok' => false, 'fehler' => 'abgemeldet'], 401);
}

// Das Token steckt im Kopf, nicht im Formularfeld - deshalb hier die eigene
// Prüfung statt csrf_pruefen().
$gesendet = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$erwartet = (string)($_SESSION['csrf_token'] ?? '');
if ($erwartet === '' || !hash_equals($erwartet, $gesendet)) {
    bg_antwort(['ok' => false, 'fehler' => 'csrf'], 400);
}

$roh = file_get_contents('php://input');
$eingabe = json_decode((string)$roh, true);
if (!is_array($eingabe)) {
    bg_antwort(['ok' => false, 'fehler' => 'kein_json'], 400);
}

$aktion = (string)($eingabe['aktion'] ?? '');
$ich = (int)$benutzer['id'];

try {
    switch ($aktion) {
        case 'liste':
            bg_antwort([
                'ok'    => true,
                'gbus'  => bg_gbus_lesen(),
                'firma' => bg_einstellung_lesen('firma'),
            ]);

        case 'speichern':
            $gbu = $eingabe['gbu'] ?? null;
            if (!is_array($gbu)) {
                bg_antwort(['ok' => false, 'fehler' => 'kein_dokument'], 400);
            }
            bg_gbu_speichern($gbu, $ich);
            bg_antwort(['ok' => true]);

        case 'loeschen':
            $id = (string)($eingabe['id'] ?? '');
            if ($id === '') {
                bg_antwort(['ok' => false, 'fehler' => 'keine_id'], 400);
            }
            bg_gbu_loeschen($id);
            bg_protokoll('gbu_geloescht', (string)$benutzer['email'], $ich, mb_substr($id, 0, 60));
            bg_antwort(['ok' => true]);

        case 'firma':
            $firma = $eingabe['firma'] ?? null;
            if (!is_array($firma)) {
                bg_antwort(['ok' => false, 'fehler' => 'keine_daten'], 400);
            }
            bg_einstellung_speichern('firma', $firma);
            bg_antwort(['ok' => true]);

        default:
            bg_antwort(['ok' => false, 'fehler' => 'unbekannte_aktion'], 400);
    }
} catch (InvalidArgumentException $e) {
    bg_antwort(['ok' => false, 'fehler' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('BG-API (' . $aktion . '): ' . $e->getMessage());
    bg_antwort(['ok' => false, 'fehler' => 'unavailable'], 500);
}

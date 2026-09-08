<?php
/**
 * Nimmt einen Kommentar entgegen und kehrt zur Ausgangsseite zurück.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$ich = triz_login_verlangen();

if (!ist_post()) {
    weiter_zu('/TRIZ/');
}
csrf_pruefen();

$art  = (string)($_POST['art'] ?? '');
$id   = (int)($_POST['id'] ?? 0);
$text = (string)($_POST['text'] ?? '');

if ($id > 0 && trim($text) !== '') {
    if (triz_kommentar_speichern((int)$ich['id'], $art, $id, $text)) {
        triz_protokoll('kommentar', (string)$ich['email'], (int)$ich['id'], $art . '/' . $id);
    }
}

$ziel = (string)($_POST['ziel'] ?? '/TRIZ/');
if (!str_starts_with($ziel, '/TRIZ/') || str_starts_with($ziel, '//')) {
    $ziel = '/TRIZ/';
}
weiter_zu($ziel);

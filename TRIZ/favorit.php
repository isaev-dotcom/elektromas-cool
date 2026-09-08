<?php
/**
 * Setzt oder entfernt einen Favoriten und kehrt zur Ausgangsseite zurück.
 *
 * Nimmt ausschließlich POST entgegen: Ein Klick ändert Daten, und was Daten
 * ändert, gehört nicht hinter ein GET - sonst genügte ein Vorschau-Abruf durch
 * einen Mailclient, um Favoriten zu setzen.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$ich = triz_login_verlangen();

if (!ist_post()) {
    weiter_zu('/TRIZ/');
}
csrf_pruefen();

$art = (string)($_POST['art'] ?? '');
$id  = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    triz_favorit_umschalten((int)$ich['id'], $art, $id);
}

// Nur seiteneigene Ziele im Portal: verhindert, dass dieses Formular zur
// offenen Weiterleitung auf fremde Adressen wird.
$ziel = (string)($_POST['ziel'] ?? '/TRIZ/');
if (!str_starts_with($ziel, '/TRIZ/') || str_starts_with($ziel, '//')) {
    $ziel = '/TRIZ/';
}
weiter_zu($ziel);

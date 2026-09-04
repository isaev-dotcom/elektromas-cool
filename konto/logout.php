<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';

sicherheits_header();
sitzung_starten();

$b = angemeldet() ? aktueller_benutzer() : null;
if ($b !== null) {
    protokoll('abmeldung', $b['email'], (int)$b['id']);
}

sitzung_beenden();
session_start();
$_SESSION['hinweis'] = 'Sie wurden abgemeldet.';

weiter_zu('/konto/login.php');

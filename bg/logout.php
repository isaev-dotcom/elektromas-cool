<?php
/**
 * Abmeldung aus dem Gefährdungsbeurteilungs-Portal.
 *
 * Beendet nur diesen Zugang. Eine parallel laufende Anmeldung im
 * Schulungsbereich oder im TRIZ-Portal bleibt bestehen.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/bg.php';

sicherheits_header();
sitzung_starten();

$b = bg_benutzer();
if ($b !== null) {
    bg_protokoll('abmeldung', (string)$b['email'], (int)$b['id']);
}

bg_abmelden();
$_SESSION['bg_hinweis'] = 'Sie wurden abgemeldet.';
weiter_zu('/bg/login.php');

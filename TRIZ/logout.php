<?php
/**
 * Abmeldung aus dem Portal.
 *
 * Beendet nur die Portalanmeldung. Eine parallel laufende Anmeldung im
 * Schulungsbereich bleibt bestehen - es sind zwei getrennte Zugänge, und wer
 * das Portal verlässt, will nicht zwangsläufig auch dort abgemeldet werden.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$b = triz_benutzer();
if ($b !== null) {
    triz_protokoll('abmeldung', (string)$b['email'], (int)$b['id']);
}

triz_abmelden();
weiter_zu('/TRIZ/login.php');

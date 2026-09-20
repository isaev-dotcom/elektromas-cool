<?php
/**
 * Gefährdungsbeurteilungs-Portal.
 *
 * Die Seite ist nur ein Rahmen: Aufbau und Logik stecken in app.js, die Daten
 * kommen über api.php aus der Datenbank. Hier steht nur, was der Browser vor
 * dem ersten Aufruf wissen muss - Anmeldung, CSRF-Schlüssel, eigener Name.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/bg.php';

sicherheits_header();
$benutzer = bg_login_verlangen();

// Für Cache-Busting: Nach einem Deploy sollen Browser die neue Fassung holen.
$v_js  = (string)@filemtime(__DIR__ . '/app.js');
$v_css = (string)@filemtime(__DIR__ . '/bg.css');
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Gefährdungsbeurteilung – elektromas GmbH</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/assets/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/favicon-192.png">
<link rel="stylesheet" href="/bg/bg.css?v=<?= e($v_css) ?>">
</head>
<body>
<div id="top"></div>
<main class="wrap" id="main"><p class="loading">Lade Gefährdungsbeurteilungen …</p></main>
<div id="modal"></div>
<div id="toast" hidden></div>

<footer class="bg-fuss no-print">
  <p>
    Angemeldet als <strong><?= e((string)$benutzer['email']) ?></strong>
    <?php if ($benutzer['rolle'] === 'admin'): ?>
      &middot; <a href="/bg/verwaltung.php">Zugänge verwalten</a>
    <?php endif; ?>
    &middot; <a href="/bg/logout.php">Abmelden</a>
  </p>
  <p><a href="/">Startseite</a> &middot; <a href="/impressum.html">Impressum</a>
     &middot; <a href="/datenschutz.html">Datenschutz</a></p>
</footer>

<script>
window.BG = {
  csrf: <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) ?>,
  name: <?= json_encode((string)($benutzer['name'] ?: $benutzer['email']), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="/bg/app.js?v=<?= e($v_js) ?>"></script>
</body>
</html>

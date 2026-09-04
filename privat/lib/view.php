<?php
/**
 * Gemeinsames Seitengerüst für Login-, Konto- und Verwaltungsseiten.
 * Nutzt style.css der Hauptseite plus konto.css.
 */

declare(strict_types=1);

function seite_kopf(string $titel, string $breite = 'schmal'): void
{
    $klasse = $breite === 'breit' ? 'konto konto--breit' : 'konto';
    ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titel) ?> – elektromas GmbH</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/assets/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/favicon-192.png">
<link rel="stylesheet" href="/style.css">
<link rel="stylesheet" href="/konto/konto.css">
</head>
<body class="<?= e($klasse) ?>">
<main class="konto__karte">
  <header class="konto__kopf">
    <a href="/"><img class="logo" src="/assets/elektromas-logo.png"
         alt="elektromas GmbH" width="389" height="149"></a>
    <h1><?= e($titel) ?></h1>
  </header>
<?php
}

function seite_fuss(): void
{
    $b = angemeldet() ? aktueller_benutzer() : null;
    ?>
  <footer class="konto__fuss">
    <?php if ($b !== null): ?>
      <p>
        Angemeldet als <strong><?= e($b['email']) ?></strong>
        <?php if ($b['rolle'] === 'admin'): ?>
          &middot; <a href="/admin/">Benutzerverwaltung</a>
        <?php endif; ?>
        &middot; <a href="/konto/logout.php">Abmelden</a>
      </p>
    <?php endif; ?>
    <p><a href="/">Startseite</a> &middot; <a href="/impressum.html">Impressum</a>
       &middot; <a href="/datenschutz.html">Datenschutz</a></p>
  </footer>
</main>
</body>
</html>
<?php
}

/** Meldungsblock. $art: 'fehler', 'ok' oder 'hinweis'. */
function meldung(string $text, string $art = 'hinweis'): void
{
    if ($text === '') {
        return;
    }
    echo '<p class="meldung meldung--' . e($art) . '">' . e($text) . '</p>';
}

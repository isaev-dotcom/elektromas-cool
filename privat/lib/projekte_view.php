<?php
/**
 * Seitengerüst des Projekt-Dashboards: Kopf mit Navigation, Fuß, Ampel.
 */

declare(strict_types=1);

function projekte_kopf(string $titel, array $benutzer, string $untertitel = '', string $aktiv = ''): void
{
    $ist_admin = ($benutzer['rolle'] ?? '') === 'admin';
    $nav = [
        'dashboard' => ['/Projekte/', 'Dashboard'],
        'neu'       => ['/Projekte/projekt.php?neu=1', 'Projekt anlegen'],
    ];
    if ($ist_admin) {
        $nav['import']        = ['/Projekte/import.php', 'KWP-Import'];
        $nav['einstellungen'] = ['/Projekte/einstellungen.php', 'Einstellungen'];
    }
    ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titel) ?> – Projekte – elektromas GmbH</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/assets/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/favicon-192.png">
<link rel="stylesheet" href="/style.css">
<link rel="stylesheet" href="/Projekte/projekte.css">
</head>
<body class="projekte">

<div class="wrap">

  <header class="page__head">
    <a href="/"><img class="logo" src="/assets/elektromas-logo.png"
         alt="elektromas GmbH" width="389" height="149"></a>
    <div class="page__title">
      <h1><?= e($titel) ?></h1>
      <?php if ($untertitel !== ''): ?><p><?= e($untertitel) ?></p><?php endif; ?>
    </div>
    <div class="page__konto">
      <span class="leise"><?= e($benutzer['name'] !== '' ? $benutzer['name'] : $benutzer['email']) ?></span>
      <a href="/Schulungen/">Schulungen</a>
      <?php if ($ist_admin): ?><a href="/admin/">Verwaltung</a><?php endif; ?>
      <a href="/konto/logout.php">Abmelden</a>
    </div>
  </header>

  <nav class="page__nav" aria-label="Projektbereich">
    <?php foreach ($nav as $schluessel => [$href, $text]): ?>
      <a href="<?= e($href) ?>"<?= $schluessel === $aktiv ? ' class="aktiv" aria-current="page"' : '' ?>><?= e($text) ?></a>
    <?php endforeach; ?>
  </nav>

  <main>
<?php
}

function projekte_fuss(): void
{
    ?>
  </main>

  <footer class="page__foot">
    <p>&copy; <?= date('Y') ?> elektromas GmbH &middot; Stand <?= date('d.m.Y H:i') ?> Uhr</p>
    <p><a href="/">&larr; Startseite</a></p>
  </footer>

</div>
</body>
</html>
<?php
}

/** Meldung aus der Sitzung (nach einer Weiterleitung) einmalig anzeigen. */
function projekte_meldung_anzeigen(): void
{
    $text = $_SESSION['projekte_meldung'] ?? '';
    $art  = $_SESSION['projekte_meldung_art'] ?? 'ok';
    unset($_SESSION['projekte_meldung'], $_SESSION['projekte_meldung_art']);
    if ($text !== '') {
        echo '<p class="meldung meldung--' . e($art) . '">' . e($text) . '</p>';
    }
}

function projekte_melden(string $text, string $art, string $ziel): never
{
    $_SESSION['projekte_meldung'] = $text;
    $_SESSION['projekte_meldung_art'] = $art;
    weiter_zu($ziel);
}

/** Farbiger Ampelpunkt mit Text für Screenreader. */
function ampel_punkt(string $ampel, string $titel = ''): string
{
    $text = AMPEL_TEXT[$ampel] ?? $ampel;
    $titel = $titel !== '' ? $titel : $text;
    return '<span class="ampel ampel--' . e($ampel) . '" title="' . e($titel) . '">'
         . '<span class="visually-hidden">' . e($text) . '</span></span>';
}

<?php
/**
 * Übersicht der Schulungen. Nur für angemeldete, aktive Benutzer.
 *
 * Die Liste wird serverseitig gerendert. Früher holte sie der Browser aus
 * schulungen.json - diese Datei wäre auch ohne Anmeldung abrufbar gewesen und
 * hätte alle Schulungstitel verraten.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';

sicherheits_header();
$benutzer = login_verlangen();

$katalog_datei = PRIVAT_PFAD . '/inhalte/schulungen.json';
$schulungen = [];

if (is_file($katalog_datei)) {
    $roh = json_decode((string)file_get_contents($katalog_datei), true);
    if (is_array($roh)) {
        foreach ($roh as $s) {
            // id und datei sind Pflicht: ohne id ließe sich kein Link bauen,
            // ohne datei gäbe es nichts auszuliefern. Unvollständige Einträge
            // werden übersprungen statt zu einem kaputten Link zu führen.
            if (is_array($s) && !empty($s['id']) && !empty($s['titel']) && !empty($s['datei'])) {
                $schulungen[] = $s;
            }
        }
    }
}

// Neueste zuerst.
usort($schulungen, static fn($a, $b) => strcmp((string)($b['datum'] ?? ''), (string)($a['datum'] ?? '')));

/*
 * Zwei Gruppen: Pflichtschulungen schließen mit einem Zertifikat für die
 * Personalakte ab, alles Übrige steht unter "Allgemeine Schulungen".
 *
 * Maßgeblich ist das Feld "pflicht" im Katalog, nicht ein Suchen nach dem
 * Wort "Zertifikat" im Text - sonst entschiede eine Formulierung darüber,
 * was verbindlich ist.
 */
$pflicht   = array_values(array_filter($schulungen, static fn($s) => !empty($s['pflicht'])));
$allgemein = array_values(array_filter($schulungen, static fn($s) => empty($s['pflicht'])));

function datum_lang(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $t = strtotime($iso);
    if ($t === false) {
        return $iso;
    }
    $monate = [1 => 'Januar','Februar','März','April','Mai','Juni','Juli',
               'August','September','Oktober','November','Dezember'];
    return date('d.', $t) . ' ' . $monate[(int)date('n', $t)] . ' ' . date('Y', $t);
}

/** Gibt eine Schulungskachel aus. Einmal geschrieben, in beiden Gruppen genutzt. */
function kachel(array $s): void
{
    $suchtext = mb_strtolower(
        ($s['titel'] ?? '') . ' ' . ($s['beschreibung'] ?? '') . ' ' .
        ($s['kategorie'] ?? '') . ' ' . ($s['typ'] ?? '')
    );
    ?>
    <li data-suche="<?= e($suchtext) ?>">
      <a class="kurs" href="/Schulungen/datei.php?s=<?= e(urlencode((string)$s['id'])) ?>">
        <div class="kurs__meta">
          <?php if (!empty($s['typ'])): ?>
            <span class="tag tag--typ"><?= e($s['typ']) ?></span>
          <?php endif; ?>
          <?php if (!empty($s['kategorie'])): ?>
            <span class="tag"><?= e($s['kategorie']) ?></span>
          <?php endif; ?>
        </div>
        <h3><?= e($s['titel']) ?></h3>
        <?php if (!empty($s['beschreibung'])): ?>
          <p><?= e($s['beschreibung']) ?></p>
        <?php endif; ?>
        <div class="kurs__foot">
          <?= e(trim(datum_lang($s['datum'] ?? null) . ' · ' . ($s['dauer'] ?? ''), ' ·')) ?>
        </div>
      </a>
    </li>
    <?php
}
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Schulungsbereich – elektromas GmbH</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/assets/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/favicon-192.png">
<link rel="stylesheet" href="/style.css">
<link rel="stylesheet" href="/Schulungen/schulung.css">
</head>
<body>

<div class="wrap">

  <header class="page__head">
    <a href="/"><img class="logo" src="/assets/elektromas-logo.png"
         alt="elektromas GmbH" width="389" height="149"></a>
    <div class="page__title">
      <h1>Schulungsbereich</h1>
      <p>Alle freigegebenen Schulungen der elektromas GmbH</p>
    </div>
    <div class="page__konto">
      <span class="leise"><?= e($benutzer['name'] !== '' ? $benutzer['name'] : $benutzer['email']) ?></span>
      <?php if ($benutzer['rolle'] === 'admin'): ?>
        <a href="/admin/">Verwaltung</a>
      <?php endif; ?>
      <a href="/konto/logout.php">Abmelden</a>
    </div>
  </header>

  <main>
    <label class="visually-hidden" for="filter">Schulungen durchsuchen</label>
    <input class="filter" id="filter" type="search" autocomplete="off"
           placeholder="Schulung suchen – Titel, Thema oder Kategorie …">

    <?php if ($pflicht): ?>
      <section class="gruppe" data-gruppe>
        <h2 class="gruppe__titel">
          Pflichtschulungen
          <span class="gruppe__zahl"><?= count($pflicht) ?></span>
        </h2>
        <p class="gruppe__lead">
          Verbindlich für alle Mitarbeitenden. Jede schließt mit einem
          Zertifikat ab, das Sie ausdrucken und zur Ablage in der Personalakte
          abgeben.
        </p>
        <ul class="kurse">
          <?php foreach ($pflicht as $s) { kachel($s); } ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($allgemein): ?>
      <section class="gruppe" data-gruppe>
        <h2 class="gruppe__titel">
          Allgemeine Schulungen
          <span class="gruppe__zahl"><?= count($allgemein) ?></span>
        </h2>
        <p class="gruppe__lead">
          Hilfreich für die tägliche Arbeit, aber ohne Nachweispflicht.
        </p>
        <ul class="kurse">
          <?php foreach ($allgemein as $s) { kachel($s); } ?>
        </ul>
      </section>
    <?php endif; ?>

    <p class="hinweis" id="hinweis"<?= $schulungen ? ' hidden' : '' ?>>
      Aktuell sind keine Schulungen veröffentlicht.
    </p>
  </main>

  <footer class="page__foot">
    <p>&copy; <?= date('Y') ?> elektromas GmbH</p>
    <p><a href="/">&larr; Zurück zur Startseite</a></p>
  </footer>

</div>

<script>
/* Suche über die bereits gerenderten Gruppen - kein Nachladen nötig. */
(function () {
  var filter  = document.getElementById('filter');
  var hinweis = document.getElementById('hinweis');
  var gruppen = Array.prototype.slice.call(document.querySelectorAll('[data-gruppe]'));

  if (!filter || !gruppen.length) { return; }

  filter.addEventListener('input', function () {
    var q = filter.value.trim().toLowerCase();
    var gesamt = 0;

    gruppen.forEach(function (gruppe) {
      var punkte = Array.prototype.slice.call(gruppe.querySelectorAll('li[data-suche]'));
      var sichtbar = 0;

      punkte.forEach(function (li) {
        var treffer = q === '' || li.dataset.suche.indexOf(q) !== -1;
        li.hidden = !treffer;
        if (treffer) { sichtbar++; }
      });

      // Eine Gruppe ohne Treffer ganz ausblenden - sonst stünde dort eine
      // Überschrift über einer leeren Fläche.
      gruppe.hidden = sichtbar === 0;

      // Die Zahl neben der Überschrift zeigt, was gerade zu sehen ist.
      var zahl = gruppe.querySelector('.gruppe__zahl');
      if (zahl) { zahl.textContent = sichtbar; }

      gesamt += sichtbar;
    });

    hinweis.hidden = gesamt > 0;
    hinweis.textContent = 'Keine Schulung passt zu Ihrer Suche.';
  });
})();
</script>

</body>
</html>

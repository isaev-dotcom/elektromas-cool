<?php
/**
 * Suche über alle Inhalte des Portals.
 *
 * Nachrichten, Videos, interne Dokumente und die Wissensbibliothek in einer
 * Trefferliste, gruppiert nach Bereich. Die Suchlogik selbst steht in
 * triz_suchen() - siehe dort auch die Begründung, warum mit LIKE statt einem
 * Volltextindex gesucht wird.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$ich = triz_login_verlangen();

$begriff = trim((string)($_GET['q'] ?? ''));
$treffer = $begriff !== '' ? triz_suchen($begriff) : [];

$gruppen = ['beitrag' => [], 'video' => [], 'dokument' => [], 'bibliothek' => []];
foreach ($treffer as $tr) {
    $gruppen[$tr['art']][] = $tr;
}

$ueberschrift = [
    'beitrag'    => 'nav_news',
    'video'      => 'nav_videos',
    'dokument'   => 'nav_wissen',
    'bibliothek' => 'nav_bibliothek',
];

triz_kopf(t('suche'), '');
triz_seitenkopf(
    t('suche'),
    $begriff !== '' ? t('beitraege_anzahl', count($treffer)) : ''
);
?>

<form class="filter" method="get" role="search">
  <div class="filter__feld" style="flex:1 1 320px">
    <label for="q2"><?= e(t('suche')) ?></label>
    <input type="search" id="q2" name="q" value="<?= e($begriff) ?>" autofocus
           style="width:100%">
  </div>
  <button type="submit" class="knopf knopf--primaer"><?= e(t('suchen')) ?></button>
</form>

<?php if ($begriff === ''): ?>
  <p class="karte leise"><?= e(t('suche')) ?> …</p>
<?php elseif ($treffer === []): ?>
  <p class="karte leise"><?= e(t('keine_treffer')) ?></p>
<?php else: ?>
  <?php foreach ($gruppen as $art => $liste): ?>
    <?php if ($liste === []) { continue; } ?>
    <section class="abschnitt">
      <h2><?= e(t($ueberschrift[$art])) ?> <span class="leise">(<?= count($liste) ?>)</span></h2>
      <ul class="eintraege">
        <?php foreach ($liste as $tr): ?>
          <li class="eintrag" style="padding-right:18px">
            <div class="eintrag__meta">
              <?= triz_sprachmarke((string)$tr['sprache']) ?>
              <?php if ($tr['datum']): ?>
                <span><?= e(triz_datum((string)$tr['datum'])) ?></span>
              <?php endif; ?>
            </div>
            <h3 class="eintrag__titel">
              <?php if ($tr['art'] === 'dokument'): ?>
                <a href="/TRIZ/datei.php?d=<?= (int)$tr['id'] ?>">
                  <?= triz_hervorheben((string)$tr['titel'], $begriff) ?></a>
              <?php elseif ($tr['extern'] !== ''): ?>
                <a href="<?= e((string)$tr['extern']) ?>" target="_blank" rel="noopener noreferrer external">
                  <?= triz_hervorheben((string)$tr['titel'], $begriff) ?></a>
              <?php else: ?>
                <a href="<?= e((string)$tr['ziel']) ?>">
                  <?= triz_hervorheben((string)$tr['titel'], $begriff) ?></a>
              <?php endif; ?>
            </h3>
            <?php if (trim((string)$tr['text']) !== ''): ?>
              <p class="eintrag__text">
                <?= triz_hervorheben(triz_kuerzen((string)$tr['text'], 260), $begriff) ?>
              </p>
            <?php endif; ?>
            <?php if ($tr['extern'] !== '' && $tr['art'] !== 'dokument'): ?>
              <p class="eintrag__fuss">
                <a href="<?= e((string)$tr['ziel']) ?>"><?= e(t('filter')) ?> →</a>
              </p>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php
triz_fuss();

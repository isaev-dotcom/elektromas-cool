<?php
/**
 * TRIZ Wissensbibliothek.
 *
 * Sieben Reiter: die 40 Prinzipien, die 39 Parameter, die Widerspruchsmatrix,
 * die Entwicklungsgesetze, die Business-TRIZ-Methoden, die Management-
 * Werkzeuge und Praxisbeispiele. Alles zweisprachig gepflegt; angezeigt wird
 * die Oberflächensprache, mit Rückfall auf Deutsch.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$ich = triz_login_verlangen();
$sp  = triz_sprache();

$reiter = [
    'prinzip'   => 'bib_prinzip',
    'matrix'    => 'bib_matrix',
    'parameter' => 'bib_parameter',
    'trend'     => 'bib_trend',
    'methode'   => 'bib_methode',
    'werkzeug'  => 'bib_werkzeug',
    'beispiel'  => 'bib_beispiel',
];

$art = (string)($_GET['art'] ?? 'prinzip');
if (!isset($reiter[$art])) {
    $art = 'prinzip';
}

$favoriten = triz_favoriten_ids((int)$ich['id']);

triz_kopf(t('bib_titel'), 'bibliothek');
triz_seitenkopf(t('bib_titel'), t('bib_lead'));
?>

<nav class="reiter">
  <?php foreach ($reiter as $schluessel => $text): ?>
    <a href="/TRIZ/bibliothek.php?art=<?= e($schluessel) ?>"
       class="<?= $art === $schluessel ? 'ist-an' : '' ?>"><?= e(t($text)) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($art === 'matrix'): ?>

  <?php
  /*
   * Widerspruchsmatrix. Sie wird leer ausgeliefert und über die Verwaltung
   * als CSV eingespielt - siehe den Kommentar in schema_triz.sql. Die
   * Parameterliste steht vollständig zur Verfügung, auch ohne Matrix.
   */
  $gefuellt = (int)db()->query('SELECT COUNT(*) FROM triz_matrix')->fetchColumn();

  $verbessert     = (int)($_GET['v'] ?? 0);
  $verschlechtert = (int)($_GET['w'] ?? 0);

  $parameter = db()->query(
      "SELECT nummer, titel_de, titel_ru FROM triz_bibliothek
       WHERE art = 'parameter' ORDER BY nummer"
  )->fetchAll();

  $prinzipien = [];
  foreach (db()->query(
      "SELECT id, nummer, titel_de, titel_ru, text_de, text_ru FROM triz_bibliothek
       WHERE art = 'prinzip' ORDER BY nummer"
  )->fetchAll() as $p) {
      $prinzipien[(int)$p['nummer']] = $p;
  }

  $empfohlen = null;
  if ($gefuellt > 0 && $verbessert > 0 && $verschlechtert > 0) {
      $stmt = db()->prepare(
          'SELECT prinzipien FROM triz_matrix WHERE verbessert = ? AND verschlechtert = ?'
      );
      $stmt->execute([$verbessert, $verschlechtert]);
      $empfohlen = $stmt->fetchColumn();
      $empfohlen = $empfohlen === false ? '' : (string)$empfohlen;
  }
  ?>

  <?php if ($gefuellt === 0): ?>
    <?php triz_meldung(t('matrix_leer'), 'hinweis'); ?>
  <?php endif; ?>

  <form class="filter" method="get">
    <input type="hidden" name="art" value="matrix">

    <div class="filter__feld">
      <label for="v"><?= e(t('matrix_verbessert')) ?></label>
      <select id="v" name="v" style="min-width:280px">
        <option value="0">–</option>
        <?php foreach ($parameter as $p): ?>
          <option value="<?= (int)$p['nummer'] ?>" <?= $verbessert === (int)$p['nummer'] ? 'selected' : '' ?>>
            <?= (int)$p['nummer'] ?>. <?= e($sp === 'ru' && $p['titel_ru'] !== '' ? $p['titel_ru'] : $p['titel_de']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter__feld">
      <label for="w"><?= e(t('matrix_verschlechtert')) ?></label>
      <select id="w" name="w" style="min-width:280px">
        <option value="0">–</option>
        <?php foreach ($parameter as $p): ?>
          <option value="<?= (int)$p['nummer'] ?>" <?= $verschlechtert === (int)$p['nummer'] ? 'selected' : '' ?>>
            <?= (int)$p['nummer'] ?>. <?= e($sp === 'ru' && $p['titel_ru'] !== '' ? $p['titel_ru'] : $p['titel_de']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button type="submit" class="knopf knopf--primaer"><?= e(t('matrix_zeigen')) ?></button>
  </form>

  <?php if ($empfohlen !== null): ?>
    <?php
    $nummern = array_values(array_filter(array_map('intval',
        preg_split('/[^\d]+/', $empfohlen) ?: []
    )));
    ?>
    <?php if ($nummern === []): ?>
      <p class="karte leise"><?= e(t('matrix_kein_eintrag')) ?></p>
    <?php else: ?>
      <ul class="bib">
        <?php foreach ($nummern as $n): ?>
          <?php $p = $prinzipien[$n] ?? null; ?>
          <?php if ($p === null) { continue; } ?>
          <li class="bib__eintrag">
            <h3 class="bib__titel">
              <span class="bib__nummer"><?= $n ?></span>
              <?= e($sp === 'ru' && $p['titel_ru'] !== '' ? $p['titel_ru'] : $p['titel_de']) ?>
            </h3>
            <p class="bib__text"><?= e($sp === 'ru' && $p['text_ru'] !== '' ? $p['text_ru'] : $p['text_de']) ?></p>
            <?php triz_favorit_knopf('bibliothek', (int)$p['id'],
                in_array((int)$p['id'], $favoriten['bibliothek'], true)); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>

<?php else: ?>

  <?php
  $stmt = db()->prepare('SELECT * FROM triz_bibliothek WHERE art = ? ORDER BY sortierung, nummer');
  $stmt->execute([$art]);
  $eintraege = $stmt->fetchAll();
  ?>

  <?php if ($eintraege === []): ?>
    <p class="karte leise"><?= e(t('noch_leer')) ?></p>
  <?php else: ?>
    <ul class="bib">
      <?php foreach ($eintraege as $b): ?>
        <?php
        $titel   = $sp === 'ru' && $b['titel_ru']   !== '' ? $b['titel_ru']   : $b['titel_de'];
        $text    = $sp === 'ru' && $b['text_ru']    !== '' ? $b['text_ru']    : $b['text_de'];
        $beispiel= $sp === 'ru' && $b['beispiel_ru']!== '' ? $b['beispiel_ru']: $b['beispiel_de'];
        ?>
        <li class="bib__eintrag" id="e<?= (int)$b['id'] ?>">
          <h3 class="bib__titel">
            <?php if ($b['nummer'] !== null): ?>
              <span class="bib__nummer"><?= (int)$b['nummer'] ?></span>
            <?php endif; ?>
            <?= e((string)$titel) ?>
          </h3>
          <?php if (trim((string)$text) !== ''): ?>
            <p class="bib__text"><?= e((string)$text) ?></p>
          <?php endif; ?>
          <?php if (trim((string)$beispiel) !== ''): ?>
            <p class="bib__beispiel"><strong><?= e(t('bib_anwendung')) ?></strong><?= e((string)$beispiel) ?></p>
          <?php endif; ?>
          <?php triz_favorit_knopf('bibliothek', (int)$b['id'],
              in_array((int)$b['id'], $favoriten['bibliothek'], true)); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

<?php endif; ?>

<?php
triz_fuss();

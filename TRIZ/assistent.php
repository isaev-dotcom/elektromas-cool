<?php
/**
 * KI-Assistent.
 *
 * Beantwortet Fragen zu Business TRIZ auf Grundlage der internen
 * Wissensdatenbank und der TRIZ-Bibliothek. Ohne hinterlegten API-Schlüssel
 * bleibt der Bereich abgeschaltet und erklärt, was fehlt - der Rest des
 * Portals arbeitet davon unberührt weiter.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';
require_once PRIVAT_PFAD . '/lib/triz_ki.php';

$ich = triz_login_verlangen();

$fehler  = '';
$quellen = [];

if (ist_post()) {
    csrf_pruefen();

    if (($_POST['aktion'] ?? '') === 'leeren') {
        triz_ki_verlauf_loeschen((int)$ich['id']);
        weiter_zu('/TRIZ/assistent.php');
    }

    $frage = trim((string)($_POST['frage'] ?? ''));

    if ($frage !== '' && triz_ki_verfuegbar()) {
        $verlauf = triz_ki_verlauf((int)$ich['id']);
        $ergebnis = triz_ki_fragen($frage, $verlauf);

        if ($ergebnis['fehler'] === '') {
            triz_ki_merken((int)$ich['id'], 'frage', $frage);
            triz_ki_merken((int)$ich['id'], 'antwort', $ergebnis['text']);
            triz_protokoll('ki_frage', (string)$ich['email'], (int)$ich['id'], mb_substr($frage, 0, 120));

            // Nach dem Schreiben umleiten: Ein Neuladen der Seite schickt
            // sonst dieselbe Frage noch einmal an die API - und die kostet.
            $_SESSION['triz_ki_quellen'] = $ergebnis['quellen'];
            weiter_zu('/TRIZ/assistent.php');
        }

        $fehler = t('ki_fehler');
        error_log('TRIZ-KI Fehlerart: ' . $ergebnis['fehler']);
    }
}

if (!empty($_SESSION['triz_ki_quellen'])) {
    $quellen = (array)$_SESSION['triz_ki_quellen'];
    unset($_SESSION['triz_ki_quellen']);
}

$verlauf = triz_ki_verfuegbar() ? triz_ki_verlauf((int)$ich['id'], 12) : [];

triz_kopf(t('ki_titel'), 'assistent');
triz_seitenkopf(t('ki_titel'), t('ki_lead'));

if (!triz_ki_verfuegbar()) {
    triz_meldung(t('ki_aus'), 'hinweis');
    triz_fuss();
    exit;
}

triz_meldung($fehler, 'fehler');
?>

<?php if ($verlauf === []): ?>
  <p class="karte leise" style="margin-bottom:20px"><?= e(t('ki_hinweis')) ?></p>
<?php else: ?>
  <div class="dialog">
    <?php foreach ($verlauf as $z): ?>
      <?php if ($z['rolle'] === 'frage'): ?>
        <div class="rede rede--frage"><?= nl2br(e((string)$z['text'])) ?></div>
      <?php else: ?>
        <div class="rede rede--antwort"><?= triz_ki_html((string)$z['text']) ?></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($quellen !== []): ?>
    <div class="karte" style="margin-bottom:20px">
      <h3><?= e(t('ki_quellen')) ?></h3>
      <p>
        <?php foreach ($quellen as $q): ?>
          <?php
          $ziel = $q['art'] === 'dokument'
              ? '/TRIZ/datei.php?d=' . (int)$q['id']
              : '/TRIZ/bibliothek.php#e' . (int)$q['id'];
          ?>
          <a class="marke marke--akzent" style="margin:0 6px 6px 0"
             href="<?= e($ziel) ?>"><?= e((string)$q['titel']) ?></a>
        <?php endforeach; ?>
      </p>
    </div>
  <?php endif; ?>
<?php endif; ?>

<form method="post">
  <?= csrf_feld() ?>

  <?php if ($verlauf === []): ?>
    <div class="vorschlaege">
      <?php foreach (['ki_vorschlag_1', 'ki_vorschlag_2', 'ki_vorschlag_3'] as $v): ?>
        <button type="submit" name="frage" value="<?= e(t($v)) ?>"><?= e(t($v)) ?></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <label for="frage"><?= e(t('ki_frage')) ?></label>
  <textarea id="frage" name="frage" rows="4" required
            placeholder="<?= e(t('ki_frage')) ?> …"></textarea>

  <p style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap">
    <button type="submit" class="knopf knopf--primaer"><?= e(t('ki_fragen')) ?></button>
  </p>
</form>

<?php if ($verlauf !== []): ?>
  <form method="post" style="margin-top:12px">
    <?= csrf_feld() ?>
    <input type="hidden" name="aktion" value="leeren">
    <button type="submit" class="knopf knopf--klein"><?= e(t('ki_verlauf_loeschen')) ?></button>
  </form>
  <p class="leise" style="margin-top:16px"><?= e(t('ki_hinweis')) ?></p>
<?php endif; ?>

<?php
triz_fuss();

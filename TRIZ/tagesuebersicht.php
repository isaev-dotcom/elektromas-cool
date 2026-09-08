<?php
/**
 * Tagesübersicht.
 *
 * Was an einem Tag hinzugekommen ist: Artikel, Videos, interne Dokumente und
 * die Empfehlung dieses Tages.
 *
 * Gerechnet wird über gefunden_am, nicht über das Veröffentlichungsdatum:
 * Gefragt ist, was für uns neu ist. Ein Fachartikel von vorletzter Woche, den
 * die Sammlung heute erstmals findet, gehört in die heutige Übersicht.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$ich = triz_login_verlangen();
$sp  = triz_sprache();

$tag = (string)($_GET['tag'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tag) || strtotime($tag) === false) {
    $tag = date('Y-m-d');
}
$heute = $tag === date('Y-m-d');

$vortag   = date('Y-m-d', (int)strtotime($tag . ' -1 day'));
$folgetag = date('Y-m-d', (int)strtotime($tag . ' +1 day'));

$stmt = db()->prepare(
    'SELECT * FROM triz_beitraege WHERE DATE(gefunden_am) = ?
     ORDER BY region, COALESCE(veroeffentlicht_am, gefunden_am) DESC LIMIT 60'
);
$stmt->execute([$tag]);
$beitraege = $stmt->fetchAll();

$stmt = db()->prepare(
    'SELECT * FROM triz_videos WHERE DATE(gefunden_am) = ?
     ORDER BY COALESCE(veroeffentlicht_am, gefunden_am) DESC LIMIT 24'
);
$stmt->execute([$tag]);
$videos = $stmt->fetchAll();

$stmt = db()->prepare(
    'SELECT * FROM triz_dokumente WHERE DATE(hochgeladen_am) = ? AND aktuell = 1
     ORDER BY hochgeladen_am DESC LIMIT 30'
);
$stmt->execute([$tag]);
$dokumente = $stmt->fetchAll();

// Dieselbe Rechnung wie auf der Übersicht, nur für den gewählten Tag.
$anzahl = (int)db()->query("SELECT COUNT(*) FROM triz_bibliothek WHERE art = 'prinzip'")->fetchColumn();
$empfehlung = null;
if ($anzahl > 0) {
    $nummer = ((int)date('z', (int)strtotime($tag)) % $anzahl) + 1;
    $stmt = db()->prepare("SELECT * FROM triz_bibliothek WHERE art = 'prinzip' AND nummer = ?");
    $stmt->execute([$nummer]);
    $empfehlung = $stmt->fetch() ?: null;
}

$favoriten = triz_favoriten_ids((int)$ich['id']);
$kat_news  = triz_kategorie_namen('news');
$kat_video = triz_kategorie_namen('video');

triz_kopf(t('tag_titel'), 'tag');
triz_seitenkopf(
    t('tag_titel') . ' – ' . ($heute ? t('tag_heute') : triz_datum($tag)),
    t('tag_lead')
);
?>

<nav class="reiter" style="margin-bottom:24px">
  <a href="/TRIZ/tagesuebersicht.php?tag=<?= e($vortag) ?>">← <?= e(t('tag_vortag')) ?></a>
  <?php if (!$heute): ?>
    <a href="/TRIZ/tagesuebersicht.php"><?= e(t('tag_heute')) ?></a>
    <a href="/TRIZ/tagesuebersicht.php?tag=<?= e($folgetag) ?>"><?= e(t('tag_folgetag')) ?> →</a>
  <?php endif; ?>
</nav>

<div class="zahlen" style="margin-bottom:28px">
  <div class="zahl">
    <div class="zahl__wert"><?= count($beitraege) ?></div>
    <div class="zahl__text"><?= e(t('tag_neue_artikel')) ?></div>
  </div>
  <div class="zahl">
    <div class="zahl__wert"><?= count($videos) ?></div>
    <div class="zahl__text"><?= e(t('tag_neue_videos')) ?></div>
  </div>
  <div class="zahl">
    <div class="zahl__wert"><?= count($dokumente) ?></div>
    <div class="zahl__text"><?= e(t('tag_neue_dokumente')) ?></div>
  </div>
</div>

<?php if ($empfehlung !== null): ?>
  <section class="abschnitt">
    <h2><?= e(t('dash_empfehlung')) ?></h2>
    <article class="karte">
      <h3 class="bib__titel">
        <span class="bib__nummer"><?= (int)$empfehlung['nummer'] ?></span>
        <?= e($sp === 'ru' && $empfehlung['titel_ru'] !== '' ? $empfehlung['titel_ru'] : $empfehlung['titel_de']) ?>
      </h3>
      <p class="bib__text"><?= e($sp === 'ru' && $empfehlung['text_ru'] !== '' ? $empfehlung['text_ru'] : $empfehlung['text_de']) ?></p>
    </article>
  </section>
<?php endif; ?>

<?php if ($beitraege === [] && $videos === [] && $dokumente === []): ?>
  <p class="karte leise"><?= e(t('tag_nichts')) ?></p>
<?php endif; ?>

<?php if ($beitraege !== []): ?>
  <section class="abschnitt">
    <h2><?= e(t('tag_neue_artikel')) ?></h2>
    <ul class="eintraege">
      <?php foreach ($beitraege as $b): ?>
        <?php triz_beitrag_zeile(
            $b,
            in_array((int)$b['id'], $favoriten['beitrag'], true),
            '',
            $kat_news[(int)($b['kategorie_id'] ?? 0)] ?? ''
        ); ?>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($videos !== []): ?>
  <section class="abschnitt">
    <h2><?= e(t('tag_neue_videos')) ?></h2>
    <ul class="videos">
      <?php foreach ($videos as $v): ?>
        <?php triz_video_karte(
            $v,
            in_array((int)$v['id'], $favoriten['video'], true),
            $kat_video[(int)($v['kategorie_id'] ?? 0)] ?? ''
        ); ?>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($dokumente !== []): ?>
  <section class="abschnitt">
    <h2><?= e(t('tag_neue_dokumente')) ?></h2>
    <ul class="eintraege">
      <?php foreach ($dokumente as $d): ?>
        <li class="eintrag">
          <div class="eintrag__meta">
            <?= triz_sprachmarke((string)$d['sprache']) ?>
            <span class="marke"><?= e(triz_ordner_name((string)$d['ordner'])) ?></span>
          </div>
          <h3 class="eintrag__titel">
            <a href="/TRIZ/datei.php?d=<?= (int)$d['id'] ?>"><?= e((string)$d['titel']) ?></a>
          </h3>
          <?php triz_favorit_knopf('dokument', (int)$d['id'],
              in_array((int)$d['id'], $favoriten['dokument'], true)); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php
triz_fuss();

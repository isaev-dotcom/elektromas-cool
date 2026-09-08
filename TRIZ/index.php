<?php
/**
 * Übersicht (Dashboard) des Business TRIZ Portals.
 *
 * Zeigt in einer Ansicht, was seit dem letzten Besuch dazugekommen ist:
 * Nachrichten, russischsprachige Quellen, Videos, interne Dokumente, die
 * Empfehlung des Tages und die eigenen Favoriten.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$ich = triz_login_verlangen();
$favoriten = triz_favoriten_ids((int)$ich['id']);
$kat_news  = triz_kategorie_namen('news');
$kat_video = triz_kategorie_namen('video');

// --- Daten holen -----------------------------------------------------------

$neueste = db()->query(
    "SELECT * FROM triz_beitraege WHERE region <> 'ru'
     ORDER BY COALESCE(veroeffentlicht_am, gefunden_am) DESC LIMIT 6"
)->fetchAll();

$russisch = db()->query(
    "SELECT * FROM triz_beitraege WHERE region = 'ru'
     ORDER BY COALESCE(veroeffentlicht_am, gefunden_am) DESC LIMIT 4"
)->fetchAll();

$videos = db()->query(
    'SELECT * FROM triz_videos
     ORDER BY COALESCE(veroeffentlicht_am, gefunden_am) DESC LIMIT 3'
)->fetchAll();

$dokumente = db()->query(
    'SELECT * FROM triz_dokumente WHERE aktuell = 1 ORDER BY hochgeladen_am DESC LIMIT 5'
)->fetchAll();

/*
 * Empfehlung des Tages: Der Eintrag wird aus dem Datum abgeleitet, nicht
 * gewürfelt. So sehen alle Mitarbeitenden dasselbe und können darüber
 * sprechen - und ein Neuladen der Seite wechselt sie nicht weg.
 */
$anzahl_prinzipien = (int)db()->query("SELECT COUNT(*) FROM triz_bibliothek WHERE art = 'prinzip'")->fetchColumn();
$empfehlung = null;
if ($anzahl_prinzipien > 0) {
    $nummer = ((int)date('z') % $anzahl_prinzipien) + 1;
    $stmt = db()->prepare("SELECT * FROM triz_bibliothek WHERE art = 'prinzip' AND nummer = ?");
    $stmt->execute([$nummer]);
    $empfehlung = $stmt->fetch() ?: null;
}

$stmt = db()->prepare(
    'SELECT objekt_art, objekt_id FROM triz_favoriten
     WHERE benutzer_id = ? ORDER BY erstellt_am DESC LIMIT 6'
);
$stmt->execute([(int)$ich['id']]);
$fav_liste = $stmt->fetchAll();

$letzter_lauf = (string)(db()->query('SELECT MAX(letzter_lauf) FROM triz_quellen')->fetchColumn() ?: '');

$sp = triz_sprache();

triz_kopf(t('nav_dashboard'), 'dashboard');
?>

<div class="seitenkopf">
  <h1><?= e(t('dash_willkommen', $ich['name'] !== '' ? $ich['name'] : $ich['email'])) ?></h1>
  <p><?= e(t('dash_lead')) ?>
     · <span class="leise"><?= e(t('dash_stand', $letzter_lauf !== '' ? triz_datum($letzter_lauf, true) : t('dash_nie'))) ?></span></p>
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
      <?php $bsp = $sp === 'ru' && $empfehlung['beispiel_ru'] !== '' ? $empfehlung['beispiel_ru'] : $empfehlung['beispiel_de']; ?>
      <?php if ($bsp !== ''): ?>
        <p class="bib__beispiel"><strong><?= e(t('bib_anwendung')) ?></strong><?= e($bsp) ?></p>
      <?php endif; ?>
      <p style="margin-top:14px">
        <a href="/TRIZ/bibliothek.php?art=prinzip#e<?= (int)$empfehlung['id'] ?>"><?= e(t('bib_titel')) ?> →</a>
      </p>
    </article>
  </section>
<?php endif; ?>

<div class="raster raster--2">

  <section class="abschnitt">
    <div class="abschnitt__kopf">
      <h2><?= e(t('dash_news')) ?></h2>
      <a href="/TRIZ/news.php"><?= e(t('mehr')) ?> →</a>
    </div>
    <?php if ($neueste === []): ?>
      <p class="karte leise"><?= e(t('noch_leer')) ?></p>
    <?php else: ?>
      <ul class="eintraege">
        <?php foreach ($neueste as $b): ?>
          <?php triz_beitrag_zeile(
              $b,
              in_array((int)$b['id'], $favoriten['beitrag'], true),
              '',
              $kat_news[(int)($b['kategorie_id'] ?? 0)] ?? ''
          ); ?>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="abschnitt">
    <div class="abschnitt__kopf">
      <h2><?= e(t('dash_ru')) ?></h2>
      <a href="/TRIZ/news.php?raum=ru"><?= e(t('mehr')) ?> →</a>
    </div>
    <?php if ($russisch === []): ?>
      <p class="karte leise"><?= e(t('noch_leer')) ?></p>
    <?php else: ?>
      <ul class="eintraege">
        <?php foreach ($russisch as $b): ?>
          <?php triz_beitrag_zeile(
              $b,
              in_array((int)$b['id'], $favoriten['beitrag'], true),
              '',
              $kat_news[(int)($b['kategorie_id'] ?? 0)] ?? ''
          ); ?>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="abschnitt__kopf" style="margin-top:26px">
      <h2><?= e(t('dash_dokumente')) ?></h2>
      <a href="/TRIZ/wissen.php"><?= e(t('mehr')) ?> →</a>
    </div>
    <?php if ($dokumente === []): ?>
      <p class="karte leise"><?= e(t('noch_leer')) ?></p>
    <?php else: ?>
      <ul class="eintraege">
        <?php foreach ($dokumente as $d): ?>
          <li class="eintrag">
            <div class="eintrag__meta">
              <?= triz_sprachmarke((string)$d['sprache']) ?>
              <span class="marke"><?= e(triz_ordner_name((string)$d['ordner'])) ?></span>
              <span><?= e(triz_datum($d['hochgeladen_am'])) ?></span>
            </div>
            <h3 class="eintrag__titel">
              <a href="/TRIZ/datei.php?d=<?= (int)$d['id'] ?>"><?= e((string)$d['titel']) ?></a>
            </h3>
            <?php triz_favorit_knopf('dokument', (int)$d['id'], in_array((int)$d['id'], $favoriten['dokument'], true)); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

</div>

<section class="abschnitt">
  <div class="abschnitt__kopf">
    <h2><?= e(t('dash_videos')) ?></h2>
    <a href="/TRIZ/videos.php"><?= e(t('mehr')) ?> →</a>
  </div>
  <?php if ($videos === []): ?>
    <p class="karte leise"><?= e(t('noch_leer')) ?></p>
  <?php else: ?>
    <ul class="videos">
      <?php foreach ($videos as $v): ?>
        <?php triz_video_karte(
            $v,
            in_array((int)$v['id'], $favoriten['video'], true),
            $kat_video[(int)($v['kategorie_id'] ?? 0)] ?? ''
        ); ?>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="abschnitt">
  <div class="abschnitt__kopf">
    <h2><?= e(t('dash_favoriten')) ?></h2>
    <a href="/TRIZ/favoriten.php"><?= e(t('mehr')) ?> →</a>
  </div>
  <?php if ($fav_liste === []): ?>
    <p class="karte leise"><?= e(t('fav_leer')) ?></p>
  <?php else: ?>
    <p class="karte">
      <?php foreach ($fav_liste as $f): ?>
        <span class="marke marke--akzent" style="margin-right:8px"><?= e($f['objekt_art']) ?></span>
      <?php endforeach; ?>
      <a href="/TRIZ/favoriten.php"><?= e(t('fav_titel')) ?> →</a>
    </p>
  <?php endif; ?>
</section>

<?php
triz_fuss();

<?php
/**
 * YouTube Wissenscenter.
 *
 * Zeigt die gesammelten Videos mit Vorschaubild, Kanal und Datum. Abgespielt
 * wird bei YouTube; erst mit dem Klick baut der Browser eine Verbindung
 * dorthin auf. Die Vorschaubilder liegen auf unserem Server - siehe
 * thumbnail.php.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$ich = triz_login_verlangen();

$kategorie = (int)($_GET['kategorie'] ?? 0);
$sprache_f = (string)($_GET['sprache_filter'] ?? '');
$suche     = trim((string)($_GET['q'] ?? ''));
$seite     = max(1, (int)($_GET['seite'] ?? 1));
$pro_seite = 24;

$bedingungen = [];
$werte = [];

if ($kategorie > 0) {
    $bedingungen[] = 'kategorie_id = ?';
    $werte[] = $kategorie;
}
if (in_array($sprache_f, ['de', 'ru', 'en'], true)) {
    $bedingungen[] = 'sprache = ?';
    $werte[] = $sprache_f;
}
if (mb_strlen($suche) >= 2) {
    $m = '%' . str_replace(['%', '_'], ['\%', '\_'], $suche) . '%';
    $bedingungen[] = '(titel LIKE ? OR beschreibung LIKE ? OR kanal LIKE ?)';
    array_push($werte, $m, $m, $m);
}

$wo = $bedingungen === [] ? '' : ' WHERE ' . implode(' AND ', $bedingungen);

$zaehler = db()->prepare('SELECT COUNT(*) FROM triz_videos' . $wo);
$zaehler->execute($werte);
$gesamt = (int)$zaehler->fetchColumn();

$stmt = db()->prepare(
    'SELECT * FROM triz_videos' . $wo .
    ' ORDER BY COALESCE(veroeffentlicht_am, gefunden_am) DESC LIMIT ? OFFSET ?'
);
foreach ($werte as $i => $w) {
    $stmt->bindValue($i + 1, $w);
}
$stmt->bindValue(count($werte) + 1, $pro_seite, PDO::PARAM_INT);
$stmt->bindValue(count($werte) + 2, ($seite - 1) * $pro_seite, PDO::PARAM_INT);
$stmt->execute();
$videos = $stmt->fetchAll();

$favoriten = triz_favoriten_ids((int)$ich['id']);
$kat_namen = triz_kategorie_namen('video');

triz_kopf(t('videos_titel'), 'videos');
triz_seitenkopf(t('videos_titel'), t('videos_lead'));
?>

<form class="filter" method="get">
  <div class="filter__feld">
    <label for="f-kategorie"><?= e(t('kategorie')) ?></label>
    <select id="f-kategorie" name="kategorie">
      <option value="0"><?= e(t('alle')) ?></option>
      <?php foreach (triz_kategorien('video') as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= $kategorie === (int)$k['id'] ? 'selected' : '' ?>>
          <?= e(triz_kategorie_name($k)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="filter__feld">
    <label for="f-sprache"><?= e(t('sprache')) ?></label>
    <select id="f-sprache" name="sprache_filter">
      <option value=""><?= e(t('alle')) ?></option>
      <option value="de" <?= $sprache_f === 'de' ? 'selected' : '' ?>><?= e(t('deutsch')) ?></option>
      <option value="ru" <?= $sprache_f === 'ru' ? 'selected' : '' ?>><?= e(t('russisch')) ?></option>
      <option value="en" <?= $sprache_f === 'en' ? 'selected' : '' ?>><?= e(t('englisch')) ?></option>
    </select>
  </div>

  <div class="filter__feld">
    <label for="f-q"><?= e(t('suche')) ?></label>
    <input type="search" id="f-q" name="q" value="<?= e($suche) ?>">
  </div>

  <button type="submit" class="knopf knopf--primaer"><?= e(t('filter')) ?></button>
</form>

<p class="leise" style="margin-bottom:14px"><?= e(t('video_extern')) ?></p>

<?php if ($videos === []): ?>
  <p class="karte leise">
    <?= e($gesamt === 0 && $suche === '' ? t('noch_leer') : t('keine_treffer')) ?>
    <?php if ($gesamt === 0 && triz_ist_admin()): ?>
      <a href="/TRIZ/verwaltung.php?bereich=quellen"><?= e(t('quelle_neu')) ?> →</a>
    <?php endif; ?>
  </p>
<?php else: ?>
  <ul class="videos">
    <?php foreach ($videos as $v): ?>
      <?php triz_video_karte(
          $v,
          in_array((int)$v['id'], $favoriten['video'], true),
          $kat_namen[(int)($v['kategorie_id'] ?? 0)] ?? ''
      ); ?>
    <?php endforeach; ?>
  </ul>

  <?php
  $parameter = array_filter([
      'kategorie'      => $kategorie > 0 ? $kategorie : '',
      'sprache_filter' => $sprache_f,
      'q'              => $suche,
  ], static fn($v) => $v !== '' && $v !== 0);
  triz_blaettern($seite, $gesamt, $pro_seite, $parameter);
  ?>
<?php endif; ?>

<?php
triz_fuss();

<?php
/**
 * TRIZ News und russische Informationsquellen.
 *
 * Beide Bereiche des Auftrags laufen über diese Seite: ?raum=ru schaltet auf
 * die russischsprachigen Quellen um. Der Grund für eine Seite statt zweier:
 * Filter, Blättern und Darstellung wären sonst zweimal dasselbe, und jede
 * spätere Änderung müsste an zwei Stellen nachgezogen werden.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$ich = triz_login_verlangen();

$raum      = (string)($_GET['raum'] ?? '');
$sprache_f = (string)($_GET['sprache_filter'] ?? '');
$kategorie = (int)($_GET['kategorie'] ?? 0);
$suche     = trim((string)($_GET['q'] ?? ''));
$seite     = max(1, (int)($_GET['seite'] ?? 1));
$pro_seite = 25;

$nur_russisch = $raum === 'ru';

// --- Abfrage zusammensetzen ------------------------------------------------
//
// Bedingungen und Werte wachsen parallel; gebunden wird ausschließlich über
// Platzhalter, damit kein Filterwert je als SQL gelesen werden kann.

$bedingungen = [];
$werte = [];

if ($nur_russisch) {
    $bedingungen[] = "region = 'ru'";
} elseif ($raum === 'de' || $raum === 'int') {
    $bedingungen[] = 'region = ?';
    $werte[] = $raum;
}
if (in_array($sprache_f, ['de', 'ru', 'en'], true)) {
    $bedingungen[] = 'sprache = ?';
    $werte[] = $sprache_f;
}
if ($kategorie > 0) {
    $bedingungen[] = 'kategorie_id = ?';
    $werte[] = $kategorie;
}
if (mb_strlen($suche) >= 2) {
    $m = '%' . str_replace(['%', '_'], ['\%', '\_'], $suche) . '%';
    $bedingungen[] = '(titel LIKE ? OR kurzfassung LIKE ? OR quelle_name LIKE ?)';
    array_push($werte, $m, $m, $m);
}

$wo = $bedingungen === [] ? '' : ' WHERE ' . implode(' AND ', $bedingungen);

$zaehler = db()->prepare('SELECT COUNT(*) FROM triz_beitraege' . $wo);
$zaehler->execute($werte);
$gesamt = (int)$zaehler->fetchColumn();

$stmt = db()->prepare(
    'SELECT * FROM triz_beitraege' . $wo .
    ' ORDER BY COALESCE(veroeffentlicht_am, gefunden_am) DESC LIMIT ? OFFSET ?'
);
foreach ($werte as $i => $w) {
    $stmt->bindValue($i + 1, $w);
}
$stmt->bindValue(count($werte) + 1, $pro_seite, PDO::PARAM_INT);
$stmt->bindValue(count($werte) + 2, ($seite - 1) * $pro_seite, PDO::PARAM_INT);
$stmt->execute();
$beitraege = $stmt->fetchAll();

$favoriten = triz_favoriten_ids((int)$ich['id']);
$kat_namen = triz_kategorie_namen('news');

triz_kopf($nur_russisch ? t('ru_titel') : t('news_titel'), $nur_russisch ? 'ru' : 'news');
triz_seitenkopf(
    $nur_russisch ? t('ru_titel') : t('news_titel'),
    $nur_russisch ? t('ru_lead') : t('news_lead')
);
?>

<form class="filter" method="get">
  <?php if ($raum !== ''): ?>
    <input type="hidden" name="raum" value="<?= e($raum) ?>">
  <?php endif; ?>

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
    <label for="f-kategorie"><?= e(t('kategorie')) ?></label>
    <select id="f-kategorie" name="kategorie">
      <option value="0"><?= e(t('alle')) ?></option>
      <?php foreach (triz_kategorien('news') as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= $kategorie === (int)$k['id'] ? 'selected' : '' ?>>
          <?= e(triz_kategorie_name($k)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="filter__feld">
    <label for="f-q"><?= e(t('suche')) ?></label>
    <input type="search" id="f-q" name="q" value="<?= e($suche) ?>">
  </div>

  <button type="submit" class="knopf knopf--primaer"><?= e(t('filter')) ?></button>
  <?php if ($sprache_f !== '' || $kategorie > 0 || $suche !== ''): ?>
    <a class="knopf" href="/TRIZ/news.php<?= $raum !== '' ? '?raum=' . e($raum) : '' ?>"><?= e(t('alle')) ?></a>
  <?php endif; ?>
</form>

<p class="leise" style="margin-bottom:14px"><?= e(t('beitraege_anzahl', $gesamt)) ?></p>

<?php if ($beitraege === []): ?>
  <p class="karte leise"><?= e($gesamt === 0 && $suche === '' ? t('noch_leer') : t('keine_treffer')) ?></p>
<?php else: ?>
  <ul class="eintraege">
    <?php foreach ($beitraege as $b): ?>
      <?php triz_beitrag_zeile(
          $b,
          in_array((int)$b['id'], $favoriten['beitrag'], true),
          $suche,
          $kat_namen[(int)($b['kategorie_id'] ?? 0)] ?? ''
      ); ?>
    <?php endforeach; ?>
  </ul>

  <?php
  $parameter = array_filter([
      'raum'           => $raum,
      'sprache_filter' => $sprache_f,
      'kategorie'      => $kategorie > 0 ? $kategorie : '',
      'q'              => $suche,
  ], static fn($v) => $v !== '' && $v !== 0);
  triz_blaettern($seite, $gesamt, $pro_seite, $parameter);
  ?>
<?php endif; ?>

<?php
triz_fuss();

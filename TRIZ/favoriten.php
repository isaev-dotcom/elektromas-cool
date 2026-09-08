<?php
/**
 * Persönliche Favoriten über alle Bereiche hinweg.
 *
 * Die Favoritentabelle hält nur Art und Kennung; die eigentlichen Daten
 * werden hier je Art nachgeladen. Ein Favorit auf etwas, das inzwischen
 * gelöscht wurde, fällt dabei still heraus - triz_aufraeumen.php räumt die
 * verwaisten Zeilen später weg.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$ich = triz_login_verlangen();
$sp  = triz_sprache();

$stmt = db()->prepare(
    'SELECT objekt_art, objekt_id FROM triz_favoriten
     WHERE benutzer_id = ? ORDER BY erstellt_am DESC'
);
$stmt->execute([(int)$ich['id']]);

$nach_art = ['beitrag' => [], 'video' => [], 'dokument' => [], 'bibliothek' => []];
foreach ($stmt->fetchAll() as $f) {
    $nach_art[$f['objekt_art']][] = (int)$f['objekt_id'];
}

/** Holt Zeilen zu einer Liste von Kennungen, in der Reihenfolge der Tabelle. */
function triz_favoriten_laden(string $tabelle, array $ids): array
{
    if ($ids === []) {
        return [];
    }
    // Die Kennungen sind bereits durch (int) gegangen, deshalb dürfen sie
    // direkt in die Liste - Platzhalter in variabler Zahl wären hier nur
    // umständlicher, nicht sicherer.
    $liste = implode(',', array_map('intval', $ids));
    return db()->query("SELECT * FROM {$tabelle} WHERE id IN ({$liste})")->fetchAll();
}

$beitraege  = triz_favoriten_laden('triz_beitraege',  $nach_art['beitrag']);
$videos     = triz_favoriten_laden('triz_videos',     $nach_art['video']);
$dokumente  = triz_favoriten_laden('triz_dokumente',  $nach_art['dokument']);
$bibliothek = triz_favoriten_laden('triz_bibliothek', $nach_art['bibliothek']);

$kat_news  = triz_kategorie_namen('news');
$kat_video = triz_kategorie_namen('video');
$leer = $beitraege === [] && $videos === [] && $dokumente === [] && $bibliothek === [];

triz_kopf(t('fav_titel'), 'favoriten');
triz_seitenkopf(t('fav_titel'), t('fav_lead'));
?>

<?php if ($leer): ?>
  <p class="karte leise"><?= e(t('fav_leer')) ?></p>
<?php endif; ?>

<?php if ($beitraege !== []): ?>
  <section class="abschnitt">
    <h2><?= e(t('nav_news')) ?></h2>
    <ul class="eintraege">
      <?php foreach ($beitraege as $b): ?>
        <?php triz_beitrag_zeile($b, true, '', $kat_news[(int)($b['kategorie_id'] ?? 0)] ?? ''); ?>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($videos !== []): ?>
  <section class="abschnitt">
    <h2><?= e(t('nav_videos')) ?></h2>
    <ul class="videos">
      <?php foreach ($videos as $v): ?>
        <?php triz_video_karte($v, true, $kat_video[(int)($v['kategorie_id'] ?? 0)] ?? ''); ?>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($dokumente !== []): ?>
  <section class="abschnitt">
    <h2><?= e(t('nav_wissen')) ?></h2>
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
          <?php triz_favorit_knopf('dokument', (int)$d['id'], true); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($bibliothek !== []): ?>
  <section class="abschnitt">
    <h2><?= e(t('nav_bibliothek')) ?></h2>
    <ul class="bib">
      <?php foreach ($bibliothek as $b): ?>
        <?php
        $titel = $sp === 'ru' && $b['titel_ru'] !== '' ? $b['titel_ru'] : $b['titel_de'];
        $text  = $sp === 'ru' && $b['text_ru']  !== '' ? $b['text_ru']  : $b['text_de'];
        ?>
        <li class="bib__eintrag">
          <h3 class="bib__titel">
            <?php if ($b['nummer'] !== null): ?>
              <span class="bib__nummer"><?= (int)$b['nummer'] ?></span>
            <?php endif; ?>
            <a href="/TRIZ/bibliothek.php?art=<?= e((string)$b['art']) ?>#e<?= (int)$b['id'] ?>">
              <?= e((string)$titel) ?>
            </a>
          </h3>
          <p class="bib__text"><?= e(triz_kuerzen((string)$text, 300)) ?></p>
          <?php triz_favorit_knopf('bibliothek', (int)$b['id'], true); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php
triz_fuss();

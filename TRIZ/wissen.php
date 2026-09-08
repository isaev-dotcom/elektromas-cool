<?php
/**
 * Interne Wissensdatenbank.
 *
 * Acht feste Ordner, Volltextsuche, Schlagwörter, Versionierung. Die Dateien
 * liegen unter privat/triz_inhalte/ und sind über keine Adresse direkt
 * erreichbar; ausgeliefert werden sie ausschließlich über datei.php.
 *
 * Hochladen und Löschen sind Administratoren vorbehalten, Lesen und
 * Herunterladen allen angemeldeten Mitarbeitenden.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$ich = triz_login_verlangen();

/** Zugelassene Dateitypen: Endung => MIME-Typ für die Auslieferung. */
function triz_erlaubte_typen(): array
{
    return [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp'  => 'application/vnd.oasis.opendocument.presentation',
        'txt'  => 'text/plain',
        'md'   => 'text/plain',
        'csv'  => 'text/csv',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'm4v'  => 'video/mp4',
    ];
}

/**
 * Zieht Text aus einer hochgeladenen Datei, für Volltextsuche und den
 * KI-Assistenten.
 *
 * Textdateien werden direkt gelesen. Für PDF und Office-Formate gibt es in
 * PHP kein eingebautes Mittel; wenn auf dem Server pdftotext liegt, wird es
 * genutzt, sonst bleibt der Volltext leer. Dann tragen Titel, Beschreibung
 * und Schlagwörter die Suche - deshalb sind sie im Formular auch das, was
 * ausgefüllt gehört.
 */
function triz_volltext_lesen(string $pfad, string $endung): ?string
{
    if (in_array($endung, ['txt', 'md', 'csv'], true)) {
        $roh = (string)file_get_contents($pfad, false, null, 0, 2_000_000);
        return mb_substr(mb_convert_encoding($roh, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252'), 0, 1_000_000);
    }

    if ($endung === 'pdf' && function_exists('shell_exec')) {
        $werkzeug = (string)triz_einstellung('pdftotext_pfad', '');
        if ($werkzeug !== '' && is_executable($werkzeug)) {
            $aus = @shell_exec(escapeshellcmd($werkzeug) . ' -q -enc UTF-8 ' .
                   escapeshellarg($pfad) . ' - 2>/dev/null');
            if (is_string($aus) && trim($aus) !== '') {
                return mb_substr($aus, 0, 1_000_000);
            }
        }
    }
    return null;
}

$ordner_gewaehlt = (string)($_GET['ordner'] ?? '');
$suche  = trim((string)($_GET['q'] ?? ''));
$fehler = '';
$ok     = '';

// --- Hochladen und Löschen -------------------------------------------------

if (ist_post()) {
    csrf_pruefen();
    $ich = triz_admin_verlangen();
    $aktion = (string)($_POST['aktion'] ?? '');

    if ($aktion === 'loeschen') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM triz_dokumente WHERE id = ?');
        $stmt->execute([$id]);
        $dok = $stmt->fetch();

        if ($dok) {
            // basename(): Selbst wenn in der Datenbank je ein Pfad stünde,
            // bleibt davon nur der Dateiname übrig.
            $pfad = triz_inhalte_pfad() . '/' . basename((string)$dok['dateiname']);
            if (is_file($pfad)) {
                @unlink($pfad);
            }
            $del = db()->prepare('DELETE FROM triz_dokumente WHERE id = ?');
            $del->execute([$id]);

            // Die Vorgängerversion rückt wieder nach vorn, sonst verschwände
            // das Dokument samt Vorgeschichte aus der Liste.
            if ($dok['vorgaenger_id'] !== null) {
                $auf = db()->prepare('UPDATE triz_dokumente SET aktuell = 1 WHERE id = ?');
                $auf->execute([(int)$dok['vorgaenger_id']]);
            }
            triz_protokoll('dokument_geloescht', (string)$ich['email'], (int)$ich['id'], (string)$dok['titel']);
            $ok = t('speichern');
        }
    } elseif ($aktion === 'hochladen') {
        $titel        = trim((string)($_POST['titel'] ?? ''));
        $ordner       = (string)($_POST['ordner'] ?? 'unternehmenswissen');
        $beschreibung = trim((string)($_POST['beschreibung'] ?? ''));
        $schlagwoerter= trim((string)($_POST['schlagwoerter'] ?? ''));
        $sprache_d    = (string)($_POST['sprache_dok'] ?? 'de');
        $vorgaenger   = (int)($_POST['vorgaenger'] ?? 0);

        $grenze = (int)triz_einstellung('upload_max_mb', 64) * 1024 * 1024;
        $datei  = $_FILES['datei'] ?? null;

        if (!isset(triz_ordner()[$ordner])) {
            $ordner = 'unternehmenswissen';
        }
        if (!in_array($sprache_d, ['de', 'ru', 'en'], true)) {
            $sprache_d = 'de';
        }

        if (!is_array($datei) || ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $fehler = match ((int)($datei['error'] ?? UPLOAD_ERR_NO_FILE)) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    t('upload_fehler_gross', ini_get('upload_max_filesize')),
                default => t('datei') . ': ' . t('upload_fehler_typ'),
            };
        } elseif ((int)$datei['size'] > $grenze) {
            $fehler = t('upload_fehler_gross', triz_groesse($grenze));
        } else {
            $endung = strtolower(pathinfo((string)$datei['name'], PATHINFO_EXTENSION));
            $typen  = triz_erlaubte_typen();

            if (!isset($typen[$endung])) {
                $fehler = t('upload_fehler_typ');
            } else {
                $ordner_pfad = triz_inhalte_pfad();
                if (!is_dir($ordner_pfad) && !@mkdir($ordner_pfad, 0750, true) && !is_dir($ordner_pfad)) {
                    $fehler = 'Das Ablageverzeichnis lässt sich nicht anlegen.';
                }
            }

            if ($fehler === '') {
                // Der Dateiname auf der Platte wird neu vergeben und enthält
                // nichts aus der Eingabe: Der Originalname steht in der
                // Datenbank, aus ihm kann damit kein Pfad entstehen.
                $dateiname = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $endung;
                $ziel = triz_inhalte_pfad() . '/' . $dateiname;

                if (!move_uploaded_file((string)$datei['tmp_name'], $ziel)) {
                    error_log('TRIZ-Upload fehlgeschlagen: ' . $ziel);
                    $fehler = 'Die Datei konnte nicht gespeichert werden.';
                } else {
                    @chmod($ziel, 0640);

                    $version = 1;
                    $vorgaenger_id = null;
                    if ($vorgaenger > 0) {
                        $v = db()->prepare('SELECT id, version, titel FROM triz_dokumente WHERE id = ?');
                        $v->execute([$vorgaenger]);
                        if ($alt = $v->fetch()) {
                            $version = (int)$alt['version'] + 1;
                            $vorgaenger_id = (int)$alt['id'];
                            if ($titel === '') {
                                $titel = (string)$alt['titel'];
                            }
                            $aus = db()->prepare('UPDATE triz_dokumente SET aktuell = 0 WHERE id = ?');
                            $aus->execute([$vorgaenger_id]);
                        }
                    }
                    if ($titel === '') {
                        $titel = pathinfo((string)$datei['name'], PATHINFO_FILENAME);
                    }

                    $ins = db()->prepare(
                        'INSERT INTO triz_dokumente
                           (ordner, titel, beschreibung, schlagwoerter, sprache, dateiname,
                            original_name, mime, groesse, version, vorgaenger_id, volltext,
                            hochgeladen_von)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $ins->execute([
                        $ordner,
                        mb_substr($titel, 0, 300),
                        $beschreibung,
                        mb_substr($schlagwoerter, 0, 400),
                        $sprache_d,
                        $dateiname,
                        mb_substr((string)$datei['name'], 0, 250),
                        triz_erlaubte_typen()[$endung],
                        (int)$datei['size'],
                        $version,
                        $vorgaenger_id,
                        triz_volltext_lesen($ziel, $endung),
                        (int)$ich['id'],
                    ]);

                    triz_protokoll('dokument_hochgeladen', (string)$ich['email'], (int)$ich['id'], $titel);
                    $ok = t('upload_ok');
                    $ordner_gewaehlt = $ordner;
                }
            }
        }
    }
}

// --- Liste -----------------------------------------------------------------

$bedingungen = ['aktuell = 1'];
$werte = [];

if (isset(triz_ordner()[$ordner_gewaehlt])) {
    $bedingungen[] = 'ordner = ?';
    $werte[] = $ordner_gewaehlt;
}
if (mb_strlen($suche) >= 2) {
    $m = '%' . str_replace(['%', '_'], ['\%', '\_'], $suche) . '%';
    $bedingungen[] = '(titel LIKE ? OR beschreibung LIKE ? OR schlagwoerter LIKE ? OR volltext LIKE ?)';
    array_push($werte, $m, $m, $m, $m);
}

$stmt = db()->prepare(
    'SELECT d.*, b.name AS hochlader
     FROM triz_dokumente d
     LEFT JOIN triz_benutzer b ON b.id = d.hochgeladen_von
     WHERE ' . implode(' AND ', $bedingungen) . '
     ORDER BY d.hochgeladen_am DESC LIMIT 200'
);
$stmt->execute($werte);
$dokumente = $stmt->fetchAll();

// Zahl der Dokumente je Ordner für die Reiterleiste.
$je_ordner = [];
foreach (db()->query(
    'SELECT ordner, COUNT(*) AS anzahl FROM triz_dokumente WHERE aktuell = 1 GROUP BY ordner'
)->fetchAll() as $z) {
    $je_ordner[(string)$z['ordner']] = (int)$z['anzahl'];
}

$favoriten = triz_favoriten_ids((int)$ich['id']);

triz_kopf(t('wissen_titel'), 'wissen');
triz_seitenkopf(t('wissen_titel'), t('wissen_lead'));
triz_meldung($fehler, 'fehler');
triz_meldung($ok, 'ok');
?>

<nav class="reiter">
  <a href="/TRIZ/wissen.php" class="<?= $ordner_gewaehlt === '' ? 'ist-an' : '' ?>">
    <?= e(t('alle')) ?>
  </a>
  <?php foreach (triz_ordner() as $schluessel => $namen): ?>
    <a href="/TRIZ/wissen.php?ordner=<?= e(urlencode($schluessel)) ?>"
       class="<?= $ordner_gewaehlt === $schluessel ? 'ist-an' : '' ?>">
      <?= e($namen[triz_sprache()]) ?>
      <?php if (!empty($je_ordner[$schluessel])): ?>
        <span class="leise">(<?= (int)$je_ordner[$schluessel] ?>)</span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<form class="filter" method="get">
  <?php if ($ordner_gewaehlt !== ''): ?>
    <input type="hidden" name="ordner" value="<?= e($ordner_gewaehlt) ?>">
  <?php endif; ?>
  <div class="filter__feld">
    <label for="f-q"><?= e(t('suche')) ?></label>
    <input type="search" id="f-q" name="q" value="<?= e($suche) ?>"
           placeholder="<?= e(t('schlagwoerter')) ?>, <?= e(t('titel')) ?> …">
  </div>
  <button type="submit" class="knopf knopf--primaer"><?= e(t('suchen')) ?></button>
</form>

<?php if ($dokumente === []): ?>
  <p class="karte leise"><?= e($suche !== '' ? t('keine_treffer') : t('noch_leer')) ?></p>
<?php else: ?>
  <ul class="eintraege">
    <?php foreach ($dokumente as $d): ?>
      <li class="eintrag" id="d<?= (int)$d['id'] ?>">
        <div class="eintrag__meta">
          <?= triz_sprachmarke((string)$d['sprache']) ?>
          <span class="marke"><?= e(triz_ordner_name((string)$d['ordner'])) ?></span>
          <?php if ((int)$d['version'] > 1): ?>
            <span class="marke marke--akzent"><?= e(t('version')) ?> <?= (int)$d['version'] ?></span>
          <?php endif; ?>
          <span><?= e(triz_datum($d['hochgeladen_am'])) ?></span>
          <span>· <?= e(triz_groesse((int)$d['groesse'])) ?></span>
          <span>· <?= e(t('downloads')) ?>: <?= (int)$d['downloads'] ?></span>
        </div>
        <h3 class="eintrag__titel">
          <a href="/TRIZ/datei.php?d=<?= (int)$d['id'] ?>">
            <?= triz_hervorheben((string)$d['titel'], $suche) ?>
          </a>
        </h3>
        <?php if (trim((string)$d['beschreibung']) !== ''): ?>
          <p class="eintrag__text"><?= triz_hervorheben(triz_kuerzen((string)$d['beschreibung']), $suche) ?></p>
        <?php endif; ?>
        <div class="eintrag__fuss">
          <?php foreach (array_filter(array_map('trim', explode(',', (string)$d['schlagwoerter']))) as $wort): ?>
            <span class="marke"><?= e($wort) ?></span>
          <?php endforeach; ?>
          <span class="leise"><?= e(t('hochgeladen_von', (string)($d['hochlader'] ?? '–'))) ?></span>
          <a href="/TRIZ/datei.php?d=<?= (int)$d['id'] ?>&amp;dl=1"><?= e(t('herunterladen')) ?></a>
          <?php if (triz_ist_admin()): ?>
            <form method="post" onsubmit="return confirm('<?= e(t('loeschen')) ?>?');">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="loeschen">
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <button type="submit" class="knopf knopf--klein knopf--warnung"><?= e(t('loeschen')) ?></button>
            </form>
          <?php endif; ?>
        </div>
        <?php triz_favorit_knopf('dokument', (int)$d['id'], in_array((int)$d['id'], $favoriten['dokument'], true)); ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if (triz_ist_admin()): ?>
  <?php
  $vorhandene = db()->query(
      'SELECT id, titel, version FROM triz_dokumente WHERE aktuell = 1 ORDER BY titel LIMIT 300'
  )->fetchAll();
  $grenze = (int)triz_einstellung('upload_max_mb', 64);
  ?>
  <section class="abschnitt" style="margin-top:36px">
    <h2><?= e(t('hochladen')) ?></h2>
    <div class="karte">
      <p class="leise" style="margin-bottom:16px"><?= e(t('upload_hinweis', $grenze . ' MB')) ?></p>

      <form class="formular" method="post" enctype="multipart/form-data">
        <?= csrf_feld() ?>
        <input type="hidden" name="aktion" value="hochladen">

        <label for="datei"><?= e(t('datei')) ?></label>
        <input type="file" id="datei" name="datei" required>

        <label for="titel"><?= e(t('titel')) ?></label>
        <input type="text" id="titel" name="titel" maxlength="300">

        <label for="ordner"><?= e(t('ordner')) ?></label>
        <select id="ordner" name="ordner">
          <?php foreach (triz_ordner() as $schluessel => $namen): ?>
            <option value="<?= e($schluessel) ?>" <?= $ordner_gewaehlt === $schluessel ? 'selected' : '' ?>>
              <?= e($namen[triz_sprache()]) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label for="beschreibung"><?= e(t('beschreibung')) ?></label>
        <textarea id="beschreibung" name="beschreibung" rows="3"></textarea>

        <label for="schlagwoerter"><?= e(t('schlagwoerter')) ?></label>
        <input type="text" id="schlagwoerter" name="schlagwoerter" maxlength="400"
               placeholder="<?= e(t('schlagwoerter_hilfe')) ?>">

        <label for="sprache_dok"><?= e(t('sprache')) ?></label>
        <select id="sprache_dok" name="sprache_dok">
          <option value="de"><?= e(t('deutsch')) ?></option>
          <option value="ru"><?= e(t('russisch')) ?></option>
          <option value="en"><?= e(t('englisch')) ?></option>
        </select>

        <label for="vorgaenger"><?= e(t('neue_version')) ?></label>
        <select id="vorgaenger" name="vorgaenger">
          <option value="0">–</option>
          <?php foreach ($vorhandene as $v): ?>
            <option value="<?= (int)$v['id'] ?>">
              <?= e($v['titel']) ?> (<?= e(t('version')) ?> <?= (int)$v['version'] ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="knopf knopf--primaer"><?= e(t('hochladen')) ?></button>
      </form>
    </div>
  </section>
<?php endif; ?>

<?php
triz_fuss();

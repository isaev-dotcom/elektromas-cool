<?php
/**
 * Meine YouTube-Abos.
 *
 * Neue Videos aus den Kanälen, die der angemeldete Benutzer führt. Die Seite
 * gehört ausschließlich ihm: Jede Abfrage bindet seine benutzer_id, auch für
 * Administratoren gibt es keinen Blick in fremde Abos.
 *
 * Abgespielt wird bei YouTube; die Vorschaubilder kommen wie in der Videothek
 * über thumbnail.php vom eigenen Server.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';
require_once PRIVAT_PFAD . '/lib/triz_abos.php';

$ich = triz_login_verlangen();
$uid = (int)$ich['id'];

// ===========================================================================
// Aktionen - nach dem Absenden umleiten, damit ein Neuladen nichts doppelt
// auslöst.
// ===========================================================================

if (ist_post()) {
    csrf_pruefen();
    $aktion  = (string)($_POST['aktion'] ?? '');
    $meldung = ['', 'ok'];

    switch ($aktion) {
        case 'aktualisieren':
            // Rund 130 ms je Kanal; bei gut 80 Kanälen eine halbe Minute.
            @set_time_limit(300);
            $r = triz_abos_sammeln($uid);
            $text = t('abo_aktualisiert', $r['abos'], $r['videos']);
            if ($r['fehler'] !== []) {
                $text .= ' ' . t('abo_fehlerhaft', count($r['fehler']));
            }
            $meldung = [$text, $r['fehler'] === [] ? 'ok' : 'hinweis'];
            break;

        case 'hinzufuegen':
            @set_time_limit(60);
            $r = triz_abo_hinzufuegen(
                $uid,
                (string)($_POST['kanal'] ?? ''),
                (string)($_POST['bereich'] ?? 'verschiedenes')
            );
            $meldung = [$r['text'], $r['ok'] ? 'ok' : 'fehler'];
            break;

        case 'bereich':
            $neu  = (string)($_POST['bereich'] ?? '');
            $name = triz_abo_bereich_setzen($uid, (int)($_POST['abo'] ?? 0), $neu);
            $meldung = $name === null
                ? [t('abo_nicht_gefunden'), 'fehler']
                : [t('abo_bereich_geaendert', $name, triz_abo_bereich_name($neu)), 'ok'];
            // Zurück zur Kanalliste, dort wurde umgestellt.
            $_SESSION['triz_abo_meldung'] = $meldung;
            weiter_zu('/TRIZ/abos.php#kanaele');

        case 'entfernen':
            $name = triz_abo_entfernen($uid, (int)($_POST['abo'] ?? 0));
            $meldung = $name === null
                ? [t('abo_nicht_gefunden'), 'fehler']
                : [t('abo_entfernt', $name), 'ok'];
            break;
    }

    $_SESSION['triz_abo_meldung'] = $meldung;
    weiter_zu('/TRIZ/abos.php');
}

$meldung = $_SESSION['triz_abo_meldung'] ?? ['', 'ok'];
unset($_SESSION['triz_abo_meldung']);

// ===========================================================================
// "Neu" - gemessen am vorigen Besuch.
//
// Die Schwelle wird einmal je Sitzung festgehalten. Sonst verlöre ein Video
// seine Markierung schon beim Blättern auf die zweite Seite, weil der erste
// Seitenaufruf den Besuchszeitpunkt bereits vorgerückt hätte.
// ===========================================================================

if (!array_key_exists('triz_abo_neu_seit', $_SESSION)) {
    $stmt = db()->prepare('SELECT zuletzt_angesehen FROM triz_abo_stand WHERE benutzer_id = ?');
    $stmt->execute([$uid]);
    $_SESSION['triz_abo_neu_seit'] = (string)($stmt->fetchColumn() ?: '');

    $up = db()->prepare(
        'INSERT INTO triz_abo_stand (benutzer_id, zuletzt_angesehen) VALUES (?, NOW())
         ON DUPLICATE KEY UPDATE zuletzt_angesehen = NOW()'
    );
    $up->execute([$uid]);
}
$neu_seit = (string)$_SESSION['triz_abo_neu_seit'];

// Beim allerersten Besuch gibt es keinen Vergleichspunkt. Dann gilt als neu,
// was in den letzten drei Tagen erschienen ist - statt alles oder nichts.
$ist_neu = static function (array $v) use ($neu_seit): bool {
    if ($neu_seit !== '') {
        return strtotime((string)$v['gefunden_am']) > strtotime($neu_seit);
    }
    $zeit = $v['veroeffentlicht_am'] ?? null;
    return $zeit !== null && strtotime((string)$zeit) > time() - 3 * 86400;
};

// ===========================================================================
// Daten
// ===========================================================================

$bereiche = triz_abo_bereiche();

// Sortierung der Kanäle nach der Reihenfolge der Bereiche, dann nach Name.
// FIELD() mit Platzhaltern, damit die Reihenfolge allein aus
// triz_abo_bereiche() kommt.
$feld = 'FIELD(a.bereich, ' . implode(', ', array_fill(0, count($bereiche), '?')) . ')';

$kanaele = db()->prepare(
    'SELECT a.id, a.name, a.kanal_id, a.bereich, a.letzter_lauf, a.letzter_fehler,
            COUNT(v.id) AS anzahl, MAX(v.veroeffentlicht_am) AS letztes
     FROM triz_abos a
     LEFT JOIN triz_abo_videos v ON v.abo_id = a.id
     WHERE a.benutzer_id = ?
     GROUP BY a.id, a.name, a.kanal_id, a.bereich, a.letzter_lauf, a.letzter_fehler
     ORDER BY ' . $feld . ', a.name'
);
$kanaele->execute(array_merge([$uid], array_keys($bereiche)));
$kanaele = $kanaele->fetchAll();

$kanal_bereich = [];
foreach ($kanaele as $k) {
    $kanal_bereich[(int)$k['id']] = (string)$k['bereich'];
}

$bereich  = (string)($_GET['bereich'] ?? '');
$kanal    = (int)($_GET['kanal'] ?? 0);
$suche    = trim((string)($_GET['q'] ?? ''));
$nur_neu  = !empty($_GET['neu']);
$seite    = max(1, (int)($_GET['seite'] ?? 1));
$pro_seite = 24;

if (!array_key_exists($bereich, $bereiche)) {
    $bereich = '';
}

// Ein Kanal aus der Adresszeile, der nicht zu diesem Benutzer gehört - oder
// nicht in den gewählten Bereich -, wird ignoriert. Die Abfrage unten bindet
// ohnehin die benutzer_id; das hier verhindert nur eine irreführend leere
// Liste.
if ($kanal > 0 && (!isset($kanal_bereich[$kanal]) || ($bereich !== '' && $kanal_bereich[$kanal] !== $bereich))) {
    $kanal = 0;
}

$bedingungen = ['a.benutzer_id = ?'];
$werte = [$uid];

if ($bereich !== '') {
    $bedingungen[] = 'a.bereich = ?';
    $werte[] = $bereich;
}
if ($kanal > 0) {
    $bedingungen[] = 'a.id = ?';
    $werte[] = $kanal;
}
if (mb_strlen($suche) >= 2) {
    $m = '%' . str_replace(['%', '_'], ['\%', '\_'], $suche) . '%';
    $bedingungen[] = '(v.titel LIKE ? OR v.beschreibung LIKE ? OR a.name LIKE ?)';
    array_push($werte, $m, $m, $m);
}
if ($nur_neu) {
    if ($neu_seit !== '') {
        $bedingungen[] = 'v.gefunden_am > ?';
        $werte[] = $neu_seit;
    } else {
        $bedingungen[] = 'v.veroeffentlicht_am > (NOW() - INTERVAL 3 DAY)';
    }
}

$wo = ' WHERE ' . implode(' AND ', $bedingungen);
$von = ' FROM triz_abo_videos v JOIN triz_abos a ON a.id = v.abo_id';

$zaehler = db()->prepare('SELECT COUNT(*)' . $von . $wo);
$zaehler->execute($werte);
$gesamt = (int)$zaehler->fetchColumn();

$stmt = db()->prepare(
    'SELECT v.*, a.name AS kanal' . $von . $wo .
    ' ORDER BY COALESCE(v.veroeffentlicht_am, v.gefunden_am) DESC, v.id DESC LIMIT ? OFFSET ?'
);
foreach ($werte as $i => $w) {
    $stmt->bindValue($i + 1, $w);
}
$stmt->bindValue(count($werte) + 1, $pro_seite, PDO::PARAM_INT);
$stmt->bindValue(count($werte) + 2, ($seite - 1) * $pro_seite, PDO::PARAM_INT);
$stmt->execute();
$videos = $stmt->fetchAll();

// Kennzahlen für die Kopfzeile - über alle eigenen Videos, unabhängig vom Filter.
$alle_videos = array_sum(array_map(static fn($k) => (int)$k['anzahl'], $kanaele));
if ($neu_seit !== '') {
    $n = db()->prepare(
        'SELECT COUNT(*)' . $von . ' WHERE a.benutzer_id = ? AND v.gefunden_am > ?'
    );
    $n->execute([$uid, $neu_seit]);
} else {
    $n = db()->prepare(
        'SELECT COUNT(*)' . $von . ' WHERE a.benutzer_id = ? AND v.veroeffentlicht_am > (NOW() - INTERVAL 3 DAY)'
    );
    $n->execute([$uid]);
}
$anzahl_neu = (int)$n->fetchColumn();

$letzter_lauf = null;
foreach ($kanaele as $k) {
    if ($k['letzter_lauf'] !== null && ($letzter_lauf === null || $k['letzter_lauf'] > $letzter_lauf)) {
        $letzter_lauf = $k['letzter_lauf'];
    }
}
$mit_fehler = array_values(array_filter($kanaele, static fn($k) => $k['letzter_fehler'] !== ''));

// Kennzahlen je Bereich für die Reiter: Kanäle, Videos und neue Videos.
$je_bereich = [];
foreach (array_keys($bereiche) as $b) {
    $je_bereich[$b] = ['kanaele' => 0, 'videos' => 0, 'neu' => 0];
}
foreach ($kanaele as $k) {
    $b = isset($je_bereich[$k['bereich']]) ? (string)$k['bereich'] : 'verschiedenes';
    $je_bereich[$b]['kanaele']++;
    $je_bereich[$b]['videos'] += (int)$k['anzahl'];
}
$neu_bedingung = $neu_seit !== ''
    ? 'v.gefunden_am > ?'
    : 'v.veroeffentlicht_am > (NOW() - INTERVAL 3 DAY)';
$nb = db()->prepare(
    'SELECT a.bereich, COUNT(*) AS n' . $von .
    ' WHERE a.benutzer_id = ? AND ' . $neu_bedingung . ' GROUP BY a.bereich'
);
$nb->execute($neu_seit !== '' ? [$uid, $neu_seit] : [$uid]);
foreach ($nb->fetchAll() as $r) {
    if (isset($je_bereich[$r['bereich']])) {
        $je_bereich[$r['bereich']]['neu'] = (int)$r['n'];
    }
}

/** Baut eine Adresse der Abo-Seite aus den aktuell sinnvollen Filtern. */
$adresse = static function (array $mit) use ($suche, $nur_neu): string {
    $p = array_filter(
        array_merge(['q' => $suche, 'neu' => $nur_neu ? 1 : ''], $mit),
        static fn($x) => $x !== '' && $x !== 0 && $x !== null
    );
    return '/TRIZ/abos.php' . ($p !== [] ? '?' . http_build_query($p) : '');
};

// ===========================================================================
// Ausgabe
// ===========================================================================

triz_kopf(t('abos_titel'), 'abos');
triz_seitenkopf(t('abos_titel'), t('abos_lead'));
?>

<?php triz_meldung((string)$meldung[0], (string)$meldung[1]); ?>

<p class="leise abo-privat"><?= e(t('abos_privat')) ?></p>

<?php if ($kanaele === []): ?>

  <p class="karte leise"><?= e(t('abo_keine')) ?></p>

<?php else: ?>

  <div class="abo-kopf">
    <p>
      <strong><?= e(t('abo_zusammenfassung', count($kanaele), $alle_videos, $anzahl_neu)) ?></strong>
      <br>
      <span class="leise">
        <?= e(t('abo_letzter_lauf', $letzter_lauf !== null ? triz_datum($letzter_lauf, true) : t('abo_nie'))) ?>
      </span>
    </p>
    <form method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="aktion" value="aktualisieren">
      <button type="submit" class="knopf knopf--primaer"><?= e(t('abo_aktualisieren')) ?></button>
    </form>
  </div>
  <p class="leise abo-hinweis"><?= e(t('abo_hinweis_aktualisieren')) ?></p>

  <?php
  /*
   * Reiter je Bereich. Die Zahl zeigt die neuen Videos, wenn es welche gibt,
   * sonst die Anzahl insgesamt - so springt ins Auge, wo sich etwas getan
   * hat. Ein Bereich ohne Kanäle bekommt keinen Reiter.
   */
  ?>
  <nav class="reiter abo-bereiche" aria-label="<?= e(t('abo_bereich')) ?>">
    <a href="<?= e($adresse([])) ?>" class="<?= $bereich === '' ? 'ist-an' : '' ?>">
      <?= e(t('abo_alle_bereiche')) ?>
      <span class="abo-zahl<?= $anzahl_neu > 0 ? ' abo-zahl--neu' : '' ?>"><?= $anzahl_neu > 0 ? '+' . $anzahl_neu : $alle_videos ?></span>
    </a>
    <?php foreach ($bereiche as $schluessel => $text):
      $z = $je_bereich[$schluessel];
      if ($z['kanaele'] === 0) { continue; }
    ?>
      <a href="<?= e($adresse(['bereich' => $schluessel])) ?>"
         class="<?= $bereich === $schluessel ? 'ist-an' : '' ?>"
         <?= $bereich === $schluessel ? 'aria-current="page"' : '' ?>>
        <?= e(t($text)) ?>
        <span class="abo-zahl<?= $z['neu'] > 0 ? ' abo-zahl--neu' : '' ?>"><?= $z['neu'] > 0 ? '+' . $z['neu'] : $z['videos'] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <form class="filter" method="get">
    <?php if ($bereich !== ''): ?>
      <input type="hidden" name="bereich" value="<?= e($bereich) ?>">
    <?php endif; ?>
    <div class="filter__feld">
      <label for="f-kanal"><?= e(t('abo_kanal')) ?></label>
      <select id="f-kanal" name="kanal">
        <option value="0"><?= e(t('abo_alle_kanaele')) ?></option>
        <?php
        // Nach Bereichen gruppiert; ist ein Bereich gewählt, nur dessen Kanäle.
        $gruppe = null;
        foreach ($kanaele as $k):
          if ($bereich !== '' && $k['bereich'] !== $bereich) { continue; }
          if ($bereich === '' && $k['bereich'] !== $gruppe):
            if ($gruppe !== null): ?></optgroup><?php endif;
            $gruppe = (string)$k['bereich']; ?>
            <optgroup label="<?= e(triz_abo_bereich_name($gruppe)) ?>">
          <?php endif; ?>
          <option value="<?= (int)$k['id'] ?>" <?= $kanal === (int)$k['id'] ? 'selected' : '' ?>>
            <?= e($k['name'] !== '' ? $k['name'] : $k['kanal_id']) ?> (<?= (int)$k['anzahl'] ?>)
          </option>
        <?php endforeach;
        if ($gruppe !== null): ?></optgroup><?php endif; ?>
      </select>
    </div>

    <div class="filter__feld">
      <label for="f-q"><?= e(t('suche')) ?></label>
      <input type="search" id="f-q" name="q" value="<?= e($suche) ?>">
    </div>

    <label class="abo-nurneu">
      <input type="checkbox" name="neu" value="1" <?= $nur_neu ? 'checked' : '' ?>>
      <?= e(t('abo_nur_neue')) ?>
    </label>

    <button type="submit" class="knopf knopf--primaer"><?= e(t('filter')) ?></button>
  </form>

  <p class="leise" style="margin-bottom:14px"><?= e(t('video_extern')) ?></p>

  <?php if ($videos === []): ?>
    <p class="karte leise"><?= e($gesamt === 0 && $suche === '' && !$nur_neu && $kanal === 0 && $bereich === '' ? t('noch_leer') : t('keine_treffer')) ?></p>
  <?php else: ?>
    <ul class="videos">
      <?php foreach ($videos as $v):
        $link = 'https://www.youtube.com/watch?v=' . rawurlencode((string)$v['video_id']);
        $neu  = $ist_neu($v);
      ?>
        <li class="video<?= $neu ? ' video--neu' : '' ?>">
          <a class="video__bild" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer external">
            <img src="/TRIZ/thumbnail.php?v=<?= e(urlencode((string)$v['video_id'])) ?>"
                 alt="" loading="lazy" width="480" height="270">
          </a>
          <div class="video__text">
            <div class="eintrag__meta">
              <?php if ($neu): ?><span class="marke marke--akzent"><?= e(t('abo_neu')) ?></span><?php endif; ?>
              <span class="marke"><?= e((string)$v['kanal']) ?></span>
            </div>
            <h3 class="video__titel">
              <a href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer external">
                <?= e((string)$v['titel']) ?>
              </a>
            </h3>
            <?php $d = triz_datum($v['veroeffentlicht_am'] ?? null); ?>
            <?php if ($d !== ''): ?><p class="leise"><?= e($d) ?></p><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php
    $parameter = array_filter([
        'bereich' => $bereich,
        'kanal' => $kanal > 0 ? $kanal : '',
        'q'     => $suche,
        'neu'   => $nur_neu ? 1 : '',
    ], static fn($x) => $x !== '' && $x !== 0);
    triz_blaettern($seite, $gesamt, $pro_seite, $parameter);
    ?>
  <?php endif; ?>

<?php endif; ?>

<section class="abschnitt abo-verwalten" id="kanaele">
  <h2><?= e(t('abo_verwalten')) ?></h2>

  <?php if ($mit_fehler !== []): ?>
    <p class="meldung meldung--hinweis"><?= e(t('abo_fehlerhaft', count($mit_fehler))) ?></p>
  <?php endif; ?>

  <form class="formular" method="post">
    <?= csrf_feld() ?>
    <input type="hidden" name="aktion" value="hinzufuegen">
    <label for="abo-kanal"><?= e(t('abo_hinzufuegen')) ?></label>
    <input type="text" id="abo-kanal" name="kanal" required maxlength="300"
           placeholder="<?= e(t('abo_eingabe')) ?>">
    <label for="abo-bereich-neu"><?= e(t('abo_bereich')) ?></label>
    <select id="abo-bereich-neu" name="bereich">
      <?php foreach ($bereiche as $schluessel => $text): ?>
        <option value="<?= e($schluessel) ?>" <?= ($bereich !== '' ? $bereich : 'verschiedenes') === $schluessel ? 'selected' : '' ?>>
          <?= e(t($text)) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="knopf"><?= e(t('abo_hinzufuegen_knopf')) ?></button>
  </form>

  <?php if ($kanaele !== []): ?>
    <div class="tabelle-huelle" style="margin-top:22px">
      <table>
        <thead>
          <tr>
            <th><?= e(t('abo_kanal')) ?></th>
            <th><?= e(t('abo_bereich')) ?></th>
            <th><?= e(t('abo_videos')) ?></th>
            <th><?= e(t('abo_letztes_video')) ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $gruppe = null;
          foreach ($kanaele as $k):
            // Zwischenzeile je Bereich - die Tabelle ist nach Bereich sortiert.
            if ($k['bereich'] !== $gruppe):
              $gruppe = (string)$k['bereich']; ?>
              <tr class="abo-gruppe">
                <th colspan="5" scope="rowgroup">
                  <?= e(triz_abo_bereich_name($gruppe)) ?>
                  <span class="leise">· <?= (int)($je_bereich[$gruppe]['kanaele'] ?? 0) ?></span>
                </th>
              </tr>
            <?php endif; ?>
            <tr>
              <td>
                <a href="<?= e($adresse(['kanal' => (int)$k['id'], 'bereich' => (string)$k['bereich']])) ?>"><?= e($k['name'] !== '' ? $k['name'] : $k['kanal_id']) ?></a>
                <?php if ($k['letzter_fehler'] !== ''): ?>
                  <br><span class="leise abo-fehler" title="<?= e($k['letzter_fehler']) ?>">
                    <?= e(t('abo_fehler_letzter')) ?>: <?= e(triz_kuerzen((string)$k['letzter_fehler'], 60)) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                /*
                 * Auswahl mit sofortigem Absenden. Ohne JavaScript bleibt der
                 * Knopf daneben sichtbar (noscript) - die Umstellung
                 * funktioniert also auch dann.
                 */
                ?>
                <form method="post" class="abo-bereich-form">
                  <?= csrf_feld() ?>
                  <input type="hidden" name="aktion" value="bereich">
                  <input type="hidden" name="abo" value="<?= (int)$k['id'] ?>">
                  <label class="nur-vorlesen" for="bereich-<?= (int)$k['id'] ?>"><?= e(t('abo_bereich')) ?></label>
                  <select id="bereich-<?= (int)$k['id'] ?>" name="bereich" onchange="this.form.submit()">
                    <?php foreach ($bereiche as $schluessel => $text): ?>
                      <option value="<?= e($schluessel) ?>" <?= $k['bereich'] === $schluessel ? 'selected' : '' ?>><?= e(t($text)) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <noscript><button type="submit" class="knopf knopf--klein">OK</button></noscript>
                </form>
              </td>
              <td><?= (int)$k['anzahl'] ?></td>
              <td class="leise"><?= e($k['letztes'] !== null ? triz_datum($k['letztes']) : '—') ?></td>
              <td>
                <form method="post" class="abo-entfernen"
                      onsubmit="return confirm(<?= e(json_encode(t('abo_entfernen_frage'), JSON_UNESCAPED_UNICODE)) ?>)">
                  <?= csrf_feld() ?>
                  <input type="hidden" name="aktion" value="entfernen">
                  <input type="hidden" name="abo" value="<?= (int)$k['id'] ?>">
                  <button type="submit" class="knopf knopf--klein knopf--warnung"><?= e(t('abo_entfernen')) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php
triz_fuss();

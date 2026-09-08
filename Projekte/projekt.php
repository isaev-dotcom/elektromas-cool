<?php
/**
 * Ein Projekt: Stammdaten, Wochenmeldung des Projektleiters und Verlauf.
 *
 *   projekt.php?neu=1    neues Projekt anlegen
 *   projekt.php?id=12    Projekt bearbeiten und melden
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/projekte.php';
require_once PRIVAT_PFAD . '/lib/projekte_view.php';

sicherheits_header();
$benutzer = login_verlangen();
$ist_admin = $benutzer['rolle'] === 'admin';

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$neu = $id === 0;
$projekt = $neu ? null : projekt_laden($id);
if (!$neu && $projekt === null) {
    projekte_melden('Projekt nicht gefunden.', 'fehler', '/Projekte/');
}

$pl_liste = projektleiter_liste(false);
$fehler = [];
$eingabe = [];   // hält die Formulareingabe bei einem Fehler

// --- Speichern -------------------------------------------------------------

if (ist_post()) {
    csrf_pruefen();
    $aktion = (string)($_POST['aktion'] ?? '');

    if ($aktion === 'stamm') {
        $eingabe = $_POST;
        $nummer = strtoupper(trim((string)($_POST['nummer'] ?? '')));
        $bezeichnung = trim((string)($_POST['bezeichnung'] ?? ''));
        $auftraggeber = trim((string)($_POST['auftraggeber'] ?? ''));
        $anlagedatum = datum_lesen($_POST['anlagedatum'] ?? '');
        $kalk = zahl_lesen($_POST['kalk_stunden'] ?? '');
        $summe = zahl_lesen($_POST['auftragssumme'] ?? '');
        $phase = (string)($_POST['phase'] ?? 'laufend');
        $notiz = trim((string)($_POST['notiz'] ?? ''));

        if ($nummer === '' || !preg_match('/^[A-Z0-9][A-Z0-9\-_.\/]{1,29}$/', $nummer)) {
            $fehler[] = 'Bitte eine Projektnummer wie P260104 eingeben (Buchstaben, Ziffern, Bindestrich).';
        }
        if ($bezeichnung === '') {
            $fehler[] = 'Die Bezeichnung fehlt.';
        }
        if (!isset(PHASEN[$phase])) {
            $phase = 'laufend';
        }
        if (trim((string)($_POST['anlagedatum'] ?? '')) !== '' && $anlagedatum === null) {
            $fehler[] = 'Das Anlagedatum ist nicht lesbar (Format TT.MM.JJJJ).';
        }
        if (trim((string)($_POST['kalk_stunden'] ?? '')) !== '' && $kalk === null) {
            $fehler[] = 'Die kalkulierten Stunden sind keine Zahl.';
        }
        if ($kalk !== null && $kalk < 0) {
            $fehler[] = 'Die kalkulierten Stunden dürfen nicht negativ sein.';
        }
        if (trim((string)($_POST['auftragssumme'] ?? '')) !== '' && $summe === null) {
            $fehler[] = 'Die Auftragssumme ist keine Zahl.';
        }

        if (!$fehler) {
            // Der Projektleiter ist bewusst nicht dabei: Er kommt ausschließlich
            // aus KWP (Feld "Sachbearbeiter") über den Import.
            $werte = [
                $nummer, mb_substr($bezeichnung, 0, 255),
                mb_substr($auftraggeber, 0, 190), $anlagedatum, $kalk, $summe, $phase, mb_substr($notiz, 0, 500),
            ];
            try {
                if ($neu) {
                    $stmt = db()->prepare(
                        'INSERT INTO projekte (nummer, bezeichnung, auftraggeber, anlagedatum,
                                               kalk_stunden, auftragssumme, phase, notiz)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute($werte);
                    $id = (int)db()->lastInsertId();
                    protokoll('projekt_angelegt', $benutzer['email'], (int)$benutzer['id'], $nummer);
                    projekte_melden("Projekt {$nummer} angelegt. Jetzt die erste Wochenmeldung eintragen.", 'ok', "/Projekte/projekt.php?id={$id}#meldung");
                }
                $werte[] = $id;
                $stmt = db()->prepare(
                    'UPDATE projekte SET nummer = ?, bezeichnung = ?, auftraggeber = ?,
                            anlagedatum = ?, kalk_stunden = ?, auftragssumme = ?, phase = ?, notiz = ?
                     WHERE id = ?'
                );
                $stmt->execute($werte);
                protokoll('projekt_geaendert', $benutzer['email'], (int)$benutzer['id'], $nummer);
                projekte_melden('Stammdaten gespeichert.', 'ok', "/Projekte/projekt.php?id={$id}");
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $fehler[] = "Die Projektnummer {$nummer} gibt es schon.";
                } else {
                    error_log('Projekt speichern: ' . $e->getMessage());
                    $fehler[] = 'Speichern fehlgeschlagen. Bitte später erneut versuchen.';
                }
            }
        }
    } elseif ($aktion === 'status' && !$neu) {
        $eingabe = $_POST;
        $kw = (string)($_POST['kw'] ?? kw_aktuell());
        $ist = zahl_lesen($_POST['ist_stunden'] ?? '');
        $fertig = zahl_lesen($_POST['fertig_prozent'] ?? '');
        $termin = (string)($_POST['terminstatus'] ?? '');
        $material = (string)($_POST['materialstatus'] ?? '');
        $maengel = zahl_lesen($_POST['maengel_offen'] ?? '');
        $n_offen = zahl_lesen($_POST['nachtraege_offen'] ?? '');
        $n_genehmigt = zahl_lesen($_POST['nachtraege_genehmigt'] ?? '');
        $meilenstein = trim((string)($_POST['meilenstein'] ?? ''));
        $meilenstein_datum = datum_lesen($_POST['meilenstein_datum'] ?? '');
        $kommentar = trim((string)($_POST['kommentar'] ?? ''));

        if (!kw_gueltig($kw) || strcmp($kw, kw_aktuell()) > 0) {
            $fehler[] = 'Die Kalenderwoche ist ungültig oder liegt in der Zukunft.';
        }
        if (trim((string)($_POST['ist_stunden'] ?? '')) !== '' && $ist === null) {
            $fehler[] = 'Die Iststunden sind keine Zahl.';
        }
        if ($ist !== null && $ist < 0) {
            $fehler[] = 'Iststunden dürfen nicht negativ sein.';
        }
        if (trim((string)($_POST['fertig_prozent'] ?? '')) !== '' && $fertig === null) {
            $fehler[] = 'Der Fertigstellungsgrad ist keine Zahl.';
        }
        if ($fertig !== null && ($fertig < 0 || $fertig > 100)) {
            $fehler[] = 'Der Fertigstellungsgrad muss zwischen 0 und 100 liegen.';
        }
        if ($termin !== '' && !isset(TERMINSTATUS[$termin])) {
            $termin = '';
        }
        if ($material !== '' && !isset(MATERIALSTATUS[$material])) {
            $material = '';
        }
        if (trim((string)($_POST['meilenstein_datum'] ?? '')) !== '' && $meilenstein_datum === null) {
            $fehler[] = 'Das Meilenstein-Datum ist nicht lesbar (Format TT.MM.JJJJ).';
        }

        if (!$fehler) {
            $stmt = db()->prepare(
                'INSERT INTO projekt_status
                    (projekt_id, kw, ist_stunden, fertig_prozent, terminstatus, materialstatus, maengel_offen,
                     nachtraege_offen, nachtraege_genehmigt, meilenstein, meilenstein_datum, kommentar, benutzer_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    ist_stunden = VALUES(ist_stunden), fertig_prozent = VALUES(fertig_prozent),
                    terminstatus = VALUES(terminstatus), materialstatus = VALUES(materialstatus),
                    maengel_offen = VALUES(maengel_offen), nachtraege_offen = VALUES(nachtraege_offen),
                    nachtraege_genehmigt = VALUES(nachtraege_genehmigt), meilenstein = VALUES(meilenstein),
                    meilenstein_datum = VALUES(meilenstein_datum), kommentar = VALUES(kommentar),
                    benutzer_id = VALUES(benutzer_id)'
            );
            $stmt->execute([
                $id, $kw, $ist, $fertig,
                $termin !== '' ? $termin : null,
                $material !== '' ? $material : null,
                (int)max(0, $maengel ?? 0),
                max(0.0, $n_offen ?? 0.0),
                max(0.0, $n_genehmigt ?? 0.0),
                mb_substr($meilenstein, 0, 190),
                $meilenstein_datum,
                mb_substr($kommentar, 0, 500),
                (int)$benutzer['id'],
            ]);
            protokoll('projekt_gemeldet', $benutzer['email'], (int)$benutzer['id'], $projekt['nummer'] . ' ' . $kw);
            projekte_melden('Meldung für ' . kw_label($kw) . ' gespeichert.', 'ok', "/Projekte/projekt.php?id={$id}");
        }
    } elseif ($aktion === 'meldung_loeschen' && !$neu) {
        $kw = (string)($_POST['kw'] ?? '');
        $stmt = db()->prepare('DELETE FROM projekt_status WHERE projekt_id = ? AND kw = ?');
        $stmt->execute([$id, $kw]);
        protokoll('meldung_geloescht', $benutzer['email'], (int)$benutzer['id'], $projekt['nummer'] . ' ' . $kw);
        projekte_melden('Meldung ' . kw_label($kw) . ' gelöscht.', 'ok', "/Projekte/projekt.php?id={$id}");
    } elseif ($aktion === 'loeschen' && !$neu) {
        if (!$ist_admin) {
            http_response_code(403);
            exit('Nur Administratoren dürfen Projekte löschen.');
        }
        $stmt = db()->prepare('DELETE FROM projekte WHERE id = ?');
        $stmt->execute([$id]);
        protokoll('projekt_geloescht', $benutzer['email'], (int)$benutzer['id'], $projekt['nummer']);
        projekte_melden('Projekt ' . $projekt['nummer'] . ' samt Meldungen gelöscht.', 'ok', '/Projekte/');
    }
}

// --- Anzeige vorbereiten ---------------------------------------------------

$meldungen = $neu ? [] : meldungen_laden($id);
$letzte = $meldungen[0] ?? null;
$bewertung = $neu ? null : projekt_bewerten($projekt['kalk_stunden'], $letzte);
$kw_jetzt = kw_aktuell();

/** Formularwert: Eingabe (nach Fehler) vor Datenbankwert vor Vorgabe. */
function wert(string $feld, mixed $vorgabe = ''): string
{
    global $eingabe;
    if (array_key_exists($feld, $eingabe)) {
        return (string)$eingabe[$feld];
    }
    return $vorgabe === null ? '' : (string)$vorgabe;
}

function zahl_feld(?float $v, int $dez = 2): string
{
    if ($v === null) {
        return '';
    }
    // Deutsche Schreibweise mit Tausenderpunkt; zahl_lesen() liest sie zurück.
    $s = number_format($v, $dez, ',', '.');
    return str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s;
}

$titel = $neu ? 'Neues Projekt' : $projekt['nummer'] . ' – ' . $projekt['bezeichnung'];
$untertitel = $neu
    ? 'Stammdaten anlegen, danach die Wochenmeldung eintragen'
    : PHASEN[$projekt['phase']] . ' · Projektleiter ' . ($projekt['projektleiter'] !== '' ? projektleiter_name($projekt['projektleiter'], $pl_liste) : 'nicht zugeordnet')
      . ($projekt['auftraggeber'] !== '' ? ' · ' . $projekt['auftraggeber'] : '');
projekte_kopf($titel, $benutzer, $untertitel, $neu ? 'neu' : '');
projekte_meldung_anzeigen();

foreach ($fehler as $f) {
    echo '<p class="meldung meldung--fehler">' . e($f) . '</p>';
}
?>

<div class="karten">

  <?php if (!$neu): ?>
  <section class="karte karte--voll" id="stand">
    <h2>Aktueller Stand</h2>
    <?php if ($letzte === null): ?>
      <p class="erklaerung">Für dieses Projekt gibt es noch keine Wochenmeldung. Bitte unten die erste eintragen.</p>
    <?php else: ?>
      <p class="erklaerung">
        Letzte Meldung <?= e(kw_label($letzte['kw'])) ?>
        <?= $letzte['kw'] === $kw_jetzt ? '(diese Woche)' : '<span class="kw--alt">(nicht aus dieser Woche)</span>' ?>
        von <?= e($letzte['benutzer_name'] ?: ($letzte['benutzer_email'] ?? '–')) ?>,
        erfasst am <?= e(date('d.m.Y H:i', strtotime((string)$letzte['erfasst_am']))) ?> Uhr.
      </p>
    <?php endif; ?>
    <dl class="stamm">
      <div><dt>Gesamt-Ampel</dt><dd><?= ampel_punkt($bewertung['gesamt']) ?> <?= e(AMPEL_TEXT[$bewertung['gesamt']]) ?></dd></div>
      <div><dt>Ampel Stunden</dt><dd><?= ampel_punkt($bewertung['ampel_std']) ?> <?= e($bewertung['abw_prozent'] !== null ? prozent($bewertung['abw_prozent'], 1) : AMPEL_TEXT[$bewertung['ampel_std']]) ?></dd></div>
      <div><dt>Ampel Termin</dt><dd><?= ampel_punkt($bewertung['ampel_termin']) ?> <?= e($letzte !== null && $letzte['terminstatus'] ? TERMINSTATUS[$letzte['terminstatus']] : 'keine Angabe') ?></dd></div>
      <div><dt>Kalkuliert</dt><dd><?= e(zahl($projekt['kalk_stunden'])) ?> h</dd></div>
      <div><dt>Ist bisher</dt><dd><?= e(zahl($bewertung['ist'])) ?> h</dd></div>
      <div><dt>Fertigstellung</dt><dd><?= $bewertung['fertig'] !== null ? e(zahl($bewertung['fertig'])) . ' %' : '–' ?></dd></div>
      <div><dt>Soll bis heute</dt><dd><?= e(zahl($bewertung['soll'])) ?> h</dd></div>
      <div><dt>Abweichung</dt><dd class="<?= $bewertung['abw'] !== null ? ($bewertung['abw'] > 0 ? 'plus' : 'minus') : '' ?>"><?= $bewertung['abw'] !== null ? e(($bewertung['abw'] > 0 ? '+' : '') . zahl($bewertung['abw'])) . ' h' : '–' ?></dd></div>
      <div><dt>Auftragssumme</dt><dd><?= e(euro($projekt['auftragssumme'])) ?></dd></div>
      <div><dt>KWP-Zustand</dt><dd><?= e($projekt['kwp_zustand'] !== '' ? $projekt['kwp_zustand'] : '–') ?></dd></div>
    </dl>
  </section>
  <?php endif; ?>

  <section class="karte" id="stammdaten">
    <h2>Stammdaten</h2>
    <p class="erklaerung">Nummer, Bezeichnung und Auftraggeber kommen aus dem KWP-Import. Der Projektleiter ist das KWP-Feld „Sachbearbeiter“ und wird ausschließlich dort gepflegt. Die kalkulierten Stunden stammen aus dem Leistungsverzeichnis (KWP: Auftragszeit).</p>
    <form method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="aktion" value="stamm">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <div class="felder">
        <div class="feld">
          <label for="nummer">Projekt-Nr. <span class="pflicht">*</span></label>
          <input type="text" id="nummer" name="nummer" required maxlength="30" value="<?= e(wert('nummer', $projekt['nummer'] ?? '')) ?>" placeholder="P260104">
        </div>
        <div class="feld">
          <label>Projektleiter</label>
          <?php $pl_kuerzel = (string)($projekt['projektleiter'] ?? ''); ?>
          <div class="nur-lesen">
            <?php if ($pl_kuerzel !== ''): ?>
              <?= e(projektleiter_name($pl_kuerzel, $pl_liste)) ?> <span class="leise">(<?= e($pl_kuerzel) ?>)</span>
            <?php else: ?>
              <span class="leise">noch nicht aus KWP übernommen</span>
            <?php endif; ?>
          </div>
          <span class="unter">Wird aus dem KWP-Feld „Sachbearbeiter“ übernommen und lässt sich hier nicht ändern.</span>
        </div>
        <div class="feld">
          <label for="phase">Phase</label>
          <select id="phase" name="phase">
            <?php $phase_wert = wert('phase', $projekt['phase'] ?? 'laufend'); foreach (PHASEN as $wert_ => $text): ?>
              <option value="<?= e($wert_) ?>"<?= $wert_ === $phase_wert ? ' selected' : '' ?>><?= e($text) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="feld feld--breit">
          <label for="bezeichnung">Bezeichnung <span class="pflicht">*</span></label>
          <input type="text" id="bezeichnung" name="bezeichnung" required maxlength="255" value="<?= e(wert('bezeichnung', $projekt['bezeichnung'] ?? '')) ?>">
        </div>
        <div class="feld feld--breit">
          <label for="auftraggeber">Auftraggeber</label>
          <input type="text" id="auftraggeber" name="auftraggeber" maxlength="190" value="<?= e(wert('auftraggeber', $projekt['auftraggeber'] ?? '')) ?>">
        </div>
        <div class="feld">
          <label for="kalk_stunden">Kalk. Stunden (LV)</label>
          <input type="text" id="kalk_stunden" name="kalk_stunden" inputmode="decimal" value="<?= e(wert('kalk_stunden', zahl_feld($projekt['kalk_stunden'] ?? null))) ?>" placeholder="z. B. 880,5">
          <span class="unter">Grundlage der Soll-Berechnung</span>
        </div>
        <div class="feld">
          <label for="auftragssumme">Auftragssumme (netto)</label>
          <input type="text" id="auftragssumme" name="auftragssumme" inputmode="decimal" value="<?= e(wert('auftragssumme', zahl_feld($projekt['auftragssumme'] ?? null))) ?>" placeholder="z. B. 138.436,73">
        </div>
        <div class="feld">
          <label for="anlagedatum">Anlagedatum</label>
          <input type="text" id="anlagedatum" name="anlagedatum" inputmode="numeric" value="<?= e(wert('anlagedatum', isset($projekt['anlagedatum']) && $projekt['anlagedatum'] ? datum_de($projekt['anlagedatum']) : '')) ?>" placeholder="TT.MM.JJJJ">
        </div>
        <div class="feld feld--breit">
          <label for="notiz">Notiz zum Projekt</label>
          <textarea id="notiz" name="notiz" maxlength="500"><?= e(wert('notiz', $projekt['notiz'] ?? '')) ?></textarea>
        </div>
      </div>
      <div class="form-fuss">
        <button class="knopf knopf--primaer"><?= $neu ? 'Projekt anlegen' : 'Stammdaten speichern' ?></button>
        <?php if (!$neu && $projekt['kwp_status'] !== ''): ?>
          <span class="leise">KWP: <?= e($projekt['kwp_status']) ?> · <?= e($projekt['kwp_zustand']) ?></span>
        <?php endif; ?>
      </div>
    </form>
    <?php if (!$neu && $ist_admin): ?>
      <form method="post" class="form-fuss" onsubmit="return confirm('Projekt <?= e($projekt['nummer']) ?> mit allen Wochenmeldungen endgültig löschen?')">
        <?= csrf_feld() ?>
        <input type="hidden" name="aktion" value="loeschen">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <button class="mini mini--warnung rechts">Projekt löschen</button>
      </form>
    <?php endif; ?>
  </section>

  <?php if (!$neu): ?>
  <section class="karte" id="meldung">
    <h2>Wochenmeldung</h2>
    <p class="erklaerung">Jeden Freitag (oder zu Wochenbeginn) vom Projektleiter zu füllen. Die Felder sind mit der letzten Meldung vorbelegt – nur anpassen, was sich geändert hat. Eine zweite Meldung in derselben Woche überschreibt die erste.</p>
    <form method="post" id="meldeform">
      <?= csrf_feld() ?>
      <input type="hidden" name="aktion" value="status">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <div class="felder">
        <div class="feld feld--eingabe">
          <label for="kw">Kalenderwoche</label>
          <select id="kw" name="kw">
            <?php $kw_wert = wert('kw', $kw_jetzt); foreach (kw_auswahl(8) as $kw_): $z = kw_zeitraum($kw_); ?>
              <option value="<?= e($kw_) ?>"<?= $kw_ === $kw_wert ? ' selected' : '' ?>><?= e(kw_label($kw_)) ?> (<?= e($z[0]->format('d.m.')) ?> – <?= e($z[1]->format('d.m.')) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="feld feld--eingabe">
          <label for="ist_stunden">Iststunden bisher</label>
          <input type="text" id="ist_stunden" name="ist_stunden" inputmode="decimal" value="<?= e(wert('ist_stunden', zahl_feld($letzte !== null && $letzte['ist_stunden'] !== null ? (float)$letzte['ist_stunden'] : null))) ?>" placeholder="aus der Zeiterfassung">
          <span class="unter">Summe aller gebuchten Stunden</span>
        </div>
        <div class="feld feld--eingabe">
          <label for="fertig_prozent">Fertigstellungsgrad %</label>
          <input type="text" id="fertig_prozent" name="fertig_prozent" inputmode="decimal" value="<?= e(wert('fertig_prozent', zahl_feld($letzte !== null && $letzte['fertig_prozent'] !== null ? (float)$letzte['fertig_prozent'] : null, 1))) ?>" placeholder="0 – 100">
          <span class="unter">Schätzung nach Aufwand / Baufortschritt</span>
        </div>
        <div class="feld feld--eingabe">
          <label for="terminstatus">Terminstatus</label>
          <select id="terminstatus" name="terminstatus">
            <option value="">– bitte wählen –</option>
            <?php $t_wert = wert('terminstatus', $letzte['terminstatus'] ?? ''); foreach (TERMINSTATUS as $wert_ => $text): ?>
              <option value="<?= e($wert_) ?>"<?= $wert_ === $t_wert ? ' selected' : '' ?>><?= e($text) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="feld feld--eingabe">
          <label for="materialstatus">Materialstatus</label>
          <select id="materialstatus" name="materialstatus">
            <option value="">– keine Angabe –</option>
            <?php $m_wert = wert('materialstatus', $letzte['materialstatus'] ?? ''); foreach (MATERIALSTATUS as $wert_ => $text): ?>
              <option value="<?= e($wert_) ?>"<?= $wert_ === $m_wert ? ' selected' : '' ?>><?= e($text) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="feld feld--eingabe">
          <label for="maengel_offen">Offene Mängel (Anzahl)</label>
          <input type="text" id="maengel_offen" name="maengel_offen" inputmode="numeric" value="<?= e(wert('maengel_offen', (string)($letzte['maengel_offen'] ?? '0'))) ?>">
        </div>
        <div class="feld feld--eingabe">
          <label for="nachtraege_offen">Nachträge offen (€)</label>
          <input type="text" id="nachtraege_offen" name="nachtraege_offen" inputmode="decimal" value="<?= e(wert('nachtraege_offen', zahl_feld($letzte !== null ? (float)$letzte['nachtraege_offen'] : 0.0))) ?>">
        </div>
        <div class="feld feld--eingabe">
          <label for="nachtraege_genehmigt">Nachträge genehmigt (€)</label>
          <input type="text" id="nachtraege_genehmigt" name="nachtraege_genehmigt" inputmode="decimal" value="<?= e(wert('nachtraege_genehmigt', zahl_feld($letzte !== null ? (float)$letzte['nachtraege_genehmigt'] : 0.0))) ?>">
        </div>
        <div class="feld feld--eingabe feld--breit">
          <label for="meilenstein">Nächster Meilenstein</label>
          <input type="text" id="meilenstein" name="meilenstein" maxlength="190" value="<?= e(wert('meilenstein', $letzte['meilenstein'] ?? '')) ?>" placeholder="z. B. Elektroinstallation Kühlung fertig">
        </div>
        <div class="feld feld--eingabe">
          <label for="meilenstein_datum">Meilenstein-Datum</label>
          <input type="text" id="meilenstein_datum" name="meilenstein_datum" inputmode="numeric" value="<?= e(wert('meilenstein_datum', isset($letzte['meilenstein_datum']) && $letzte['meilenstein_datum'] ? datum_de($letzte['meilenstein_datum']) : '')) ?>" placeholder="TT.MM.JJJJ">
        </div>
        <div class="feld feld--eingabe feld--breit">
          <label for="kommentar">Kurzkommentar</label>
          <textarea id="kommentar" name="kommentar" maxlength="500" placeholder="Ein Satz: Was ist der Grund für Abweichungen?"><?= e(wert('kommentar', $letzte['kommentar'] ?? '')) ?></textarea>
        </div>
      </div>

      <div class="vorschau" id="vorschau" data-kalk="<?= e((string)($projekt['kalk_stunden'] ?? '')) ?>"
           data-gruen="<?= e((string)ampel_schwellen()['gruen_bis']) ?>" data-gelb="<?= e((string)ampel_schwellen()['gelb_bis']) ?>">
        <div><span class="leise">Soll bis heute</span><b id="v-soll">–</b></div>
        <div><span class="leise">Abweichung</span><b id="v-abw">–</b></div>
        <div><span class="leise">Abweichung %</span><b id="v-proz">–</b></div>
        <div><span class="leise">Ampel Stunden</span><b id="v-std">–</b></div>
        <div><span class="leise">Gesamt-Ampel</span><b id="v-ges">–</b></div>
      </div>

      <div class="form-fuss">
        <button class="knopf knopf--primaer">Meldung speichern</button>
        <a class="knopf" href="/Projekte/">Zurück zum Dashboard</a>
      </div>
    </form>
  </section>

  <section class="karte karte--voll" id="verlauf">
    <h2>Verlauf</h2>
    <?php if (count($meldungen) < 2): ?>
      <p class="erklaerung">Sobald mindestens zwei Wochenmeldungen vorliegen, erscheint hier der Verlauf von Ist- und Sollstunden.</p>
    <?php else:
      // Grafik: Ist und Soll je Woche, chronologisch.
      $chron = array_reverse($meldungen);
      $punkte = [];
      $max = (float)($projekt['kalk_stunden'] ?? 0);
      foreach ($chron as $m) {
          $bw = projekt_bewerten($projekt['kalk_stunden'], $m);
          $punkte[] = ['kw' => $m['kw'], 'ist' => $bw['ist'], 'soll' => $bw['soll'], 'ampel' => $bw['gesamt']];
          $max = max($max, $bw['ist'] ?? 0, $bw['soll'] ?? 0);
      }
      $max = $max > 0 ? $max * 1.08 : 1;
      $W = 900; $H = 220; $L = 50; $R = 16; $T = 14; $B = 34;
      $n = count($punkte);
      $x = static fn(int $i) => $L + ($n > 1 ? $i * ($W - $L - $R) / ($n - 1) : 0);
      $y = static fn(float $v) => $T + ($H - $T - $B) - $v / $max * ($H - $T - $B);
      $pfad = static function (string $feld) use ($punkte, $x, $y): string {
          $teile = [];
          foreach ($punkte as $i => $p) {
              if ($p[$feld] === null) { continue; }
              $teile[] = (count($teile) ? 'L' : 'M') . round($x($i), 1) . ' ' . round($y((float)$p[$feld]), 1);
          }
          return implode(' ', $teile);
      };
      $kalk = (float)($projekt['kalk_stunden'] ?? 0);
    ?>
      <svg class="grafik" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Verlauf Ist- und Sollstunden je Kalenderwoche">
        <?php for ($g = 0; $g <= 4; $g++): $v = $max / 1.08 * $g / 4; ?>
          <line x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= round($y($v), 1) ?>" y2="<?= round($y($v), 1) ?>" stroke="#e3e9f2" stroke-width="1"/>
          <text x="<?= $L - 6 ?>" y="<?= round($y($v) + 4, 1) ?>" text-anchor="end"><?= e(zahl($v)) ?></text>
        <?php endfor; ?>
        <?php if ($kalk > 0): ?>
          <line x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= round($y($kalk), 1) ?>" y2="<?= round($y($kalk), 1) ?>" stroke="#8a97ab" stroke-width="1.5" stroke-dasharray="6 5"/>
        <?php endif; ?>
        <path d="<?= e($pfad('soll')) ?>" fill="none" stroke="#1a56a8" stroke-width="2.5" stroke-linejoin="round"/>
        <path d="<?= e($pfad('ist')) ?>" fill="none" stroke="#f69f00" stroke-width="2.5" stroke-linejoin="round"/>
        <?php foreach ($punkte as $i => $p): ?>
          <?php if ($p['ist'] !== null): ?>
            <circle cx="<?= round($x($i), 1) ?>" cy="<?= round($y((float)$p['ist']), 1) ?>" r="4" fill="#f69f00"><title><?= e(kw_label($p['kw']) . ': Ist ' . zahl($p['ist']) . ' h' . ($p['soll'] !== null ? ', Soll ' . zahl($p['soll']) . ' h' : '')) ?></title></circle>
          <?php endif; ?>
          <text x="<?= round($x($i), 1) ?>" y="<?= $H - 12 ?>" text-anchor="middle"><?= e(preg_replace('/^\d{4}-W/', 'KW ', $p['kw'])) ?></text>
        <?php endforeach; ?>
      </svg>
      <div class="legende">
        <span><i style="background:#f69f00"></i>Ist-Stunden</span>
        <span><i style="background:#1a56a8"></i>Soll-Stunden (Kalk. × Fertigstellung)</span>
        <?php if ($kalk > 0): ?><span><i style="background:#8a97ab"></i>Kalkulation <?= e(zahl($kalk)) ?> h</span><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($meldungen): ?>
    <div class="tabelle-rahmen" style="margin-top:14px">
    <table class="tabelle">
      <thead>
        <tr>
          <th>KW</th><th class="mitte">Ampel</th><th class="th-zahl">Ist h</th><th class="th-zahl">Fertig</th>
          <th class="th-zahl">Soll h</th><th class="th-zahl">Abw. h</th><th class="th-zahl">Abw. %</th>
          <th>Termin</th><th>Material</th><th class="th-zahl">Mängel</th><th class="th-zahl">Nachträge €</th>
          <th>Meilenstein</th><th>Kommentar</th><th>Erfasst</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($meldungen as $m): $bw = projekt_bewerten($projekt['kalk_stunden'], $m); ?>
        <tr>
          <td class="nummer"><?= e(kw_label($m['kw'])) ?></td>
          <td class="mitte"><?= ampel_punkt($bw['gesamt']) ?></td>
          <td class="zahl"><?= e(zahl($bw['ist'])) ?></td>
          <td class="zahl"><?= $bw['fertig'] !== null ? e(zahl($bw['fertig'])) . ' %' : '–' ?></td>
          <td class="zahl"><?= e(zahl($bw['soll'])) ?></td>
          <td class="zahl <?= $bw['abw'] !== null ? ($bw['abw'] > 0 ? 'plus' : 'minus') : '' ?>"><?= $bw['abw'] !== null ? e(($bw['abw'] > 0 ? '+' : '') . zahl($bw['abw'])) : '–' ?></td>
          <td class="zahl"><?= $bw['abw_prozent'] !== null ? ampel_punkt($bw['ampel_std']) . ' ' . e(prozent($bw['abw_prozent'])) : '–' ?></td>
          <td><?= $m['terminstatus'] ? ampel_punkt($bw['ampel_termin']) . ' ' . e(TERMINSTATUS[$m['terminstatus']]) : '–' ?></td>
          <td><?= $m['materialstatus'] ? e(MATERIALSTATUS[$m['materialstatus']]) : '–' ?></td>
          <td class="zahl"><?= (int)$m['maengel_offen'] ?></td>
          <td class="zahl"><?= e(zahl((float)$m['nachtraege_offen'])) ?> <span class="leise">/ <?= e(zahl((float)$m['nachtraege_genehmigt'])) ?></span></td>
          <td><?= e($m['meilenstein']) ?><?= $m['meilenstein_datum'] ? ' <span class="leise">' . e(datum_de($m['meilenstein_datum'])) . '</span>' : '' ?></td>
          <td class="kommentar"><?= e($m['kommentar']) ?></td>
          <td class="leise"><?= e(date('d.m.Y', strtotime((string)$m['erfasst_am']))) ?><br><?= e($m['benutzer_name'] ?: ($m['benutzer_email'] ?? '')) ?></td>
          <td>
            <form method="post" class="inline" onsubmit="return confirm('Meldung <?= e(kw_label($m['kw'])) ?> löschen?')">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="meldung_loeschen">
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="kw" value="<?= e($m['kw']) ?>">
              <button class="mini mini--warnung" title="Diese Meldung löschen">×</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

</div>

<script>
/* Live-Vorschau: rechnet Soll, Abweichung und Ampel beim Tippen - mit
   denselben Regeln wie der Server. */
(function () {
  var v = document.getElementById('vorschau');
  if (!v) { return; }
  var kalk = parseFloat(v.dataset.kalk), gruen = parseFloat(v.dataset.gruen), gelb = parseFloat(v.dataset.gelb);
  var fIst = document.getElementById('ist_stunden'), fFertig = document.getElementById('fertig_prozent'),
      fTermin = document.getElementById('terminstatus');
  var nf = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 });
  var nf1 = new Intl.NumberFormat('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  var farben = { gruen: '#2e9e5b', gelb: '#f0a500', rot: '#d33', keine: '#b5bfcc' };
  var texte = { gruen: 'Grün', gelb: 'Gelb', rot: 'Rot', keine: '–' };
  var rang = { keine: 0, gruen: 1, gelb: 2, rot: 3 };

  function zahl(s) {
    s = (s || '').trim().replace(/\s/g, '');
    if (s === '') { return null; }
    if (s.indexOf(',') !== -1) { s = s.replace(/\./g, '').replace(',', '.'); }
    var n = parseFloat(s);
    return isNaN(n) ? null : n;
  }
  function ampel(id, a) {
    var el = document.getElementById(id);
    el.textContent = texte[a];
    el.style.color = farben[a];
  }
  function rechnen() {
    var ist = zahl(fIst.value), fertig = zahl(fFertig.value);
    var soll = null, abw = null, proz = null, aStd = 'keine';
    if (!isNaN(kalk) && fertig !== null) {
      soll = kalk * fertig / 100;
      if (ist !== null) {
        abw = ist - soll;
        if (soll > 0) {
          proz = abw / soll;
          aStd = proz <= gruen ? 'gruen' : (proz <= gelb ? 'gelb' : 'rot');
        }
      }
    }
    var aTermin = { im_plan: 'gruen', verzoegert: 'gelb', kritisch: 'rot' }[fTermin.value] || 'keine';
    var ges = rang[aStd] >= rang[aTermin] ? aStd : aTermin;
    document.getElementById('v-soll').textContent = soll === null ? '–' : nf.format(soll) + ' h';
    document.getElementById('v-abw').textContent = abw === null ? '–' : (abw > 0 ? '+' : '') + nf.format(abw) + ' h';
    document.getElementById('v-proz').textContent = proz === null ? '–' : (proz > 0 ? '+' : '') + nf1.format(proz * 100) + ' %';
    ampel('v-std', aStd);
    ampel('v-ges', ges);
  }
  [fIst, fFertig, fTermin].forEach(function (f) { f.addEventListener('input', rechnen); f.addEventListener('change', rechnen); });
  rechnen();
})();
</script>

<?php
projekte_fuss();

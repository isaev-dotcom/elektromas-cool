<?php
/**
 * Projekt-Dashboard: alle Projekte mit ihrer letzten Wochenmeldung,
 * Soll/Ist-Vergleich und Ampeln. Ersetzt das Blatt "Projekt-Dashboard"
 * der Excel-Vorlage.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/projekte.php';
require_once PRIVAT_PFAD . '/lib/projekte_view.php';

sicherheits_header();
$benutzer = login_verlangen();

// --- Filter ----------------------------------------------------------------

$phase = (string)($_GET['phase'] ?? 'laufend');
if ($phase !== 'alle' && !isset(PHASEN[$phase])) {
    $phase = 'laufend';
}
$pl_filter = (string)($_GET['pl'] ?? '');
$pl_liste  = projektleiter_liste(false);
if ($pl_filter !== '' && !isset($pl_liste[$pl_filter])) {
    $pl_filter = '';
}

$projekte = projekte_laden(['phase' => $phase, 'projektleiter' => $pl_filter]);
projekte_sortieren($projekte);

$kw_jetzt = kw_aktuell();
$kw_vorige = kw_auswahl(1)[1];

// --- Kennzahlen ------------------------------------------------------------

$k = [
    'anzahl' => count($projekte),
    'rot' => 0, 'gelb' => 0, 'gruen' => 0, 'keine' => 0,
    'gemeldet' => 0,
    'kalk' => 0.0, 'ist' => 0.0, 'soll' => 0.0,
    'nachtraege_offen' => 0.0, 'nachtraege_genehmigt' => 0.0,
    'maengel' => 0, 'auftragssumme' => 0.0,
];
foreach ($projekte as $p) {
    $b = $p['bewertung'];
    $k[$b['gesamt']]++;
    if ($p['status'] !== null && $p['status']['kw'] === $kw_jetzt) {
        $k['gemeldet']++;
    }
    $k['kalk'] += $p['kalk_stunden'] ?? 0;
    $k['ist']  += $b['ist'] ?? 0;
    $k['soll'] += $b['soll'] ?? 0;
    $k['auftragssumme'] += $p['auftragssumme'] ?? 0;
    if ($p['status'] !== null) {
        $k['nachtraege_offen']     += (float)$p['status']['nachtraege_offen'];
        $k['nachtraege_genehmigt'] += (float)$p['status']['nachtraege_genehmigt'];
        $k['maengel']              += (int)$p['status']['maengel_offen'];
    }
}
$abw_gesamt = $k['soll'] > 0 ? ($k['ist'] - $k['soll']) / $k['soll'] : null;

$export_url = '/Projekte/export.php?phase=' . urlencode($phase) . ($pl_filter !== '' ? '&pl=' . urlencode($pl_filter) : '');

projekte_kopf('Projekt-Dashboard', $benutzer, 'Wöchentlicher Soll/Ist-Stand aller Projekte · ' . kw_label($kw_jetzt), 'dashboard');
projekte_meldung_anzeigen();
?>

  <div class="kacheln" id="kacheln">
    <div class="kachel">
      <span class="kachel__wert"><?= (int)$k['anzahl'] ?></span>
      <span class="kachel__text"><?= e($phase === 'alle' ? 'Projekte gesamt' : PHASEN[$phase] . 'e Projekte') ?></span>
    </div>
    <div class="kachel kachel--ampel kachel--rot" data-ampel="rot" role="button" tabindex="0" title="Nur rote Projekte zeigen">
      <span class="kachel__wert"><?= (int)$k['rot'] ?></span>
      <span class="kachel__text">Rot – Eingreifen nötig</span>
    </div>
    <div class="kachel kachel--ampel kachel--gelb" data-ampel="gelb" role="button" tabindex="0" title="Nur gelbe Projekte zeigen">
      <span class="kachel__wert"><?= (int)$k['gelb'] ?></span>
      <span class="kachel__text">Gelb – beobachten</span>
    </div>
    <div class="kachel kachel--ampel kachel--gruen" data-ampel="gruen" role="button" tabindex="0" title="Nur grüne Projekte zeigen">
      <span class="kachel__wert"><?= (int)$k['gruen'] ?></span>
      <span class="kachel__text">Grün – im Rahmen</span>
    </div>
    <div class="kachel kachel--ampel" data-ampel="keine" role="button" tabindex="0" title="Projekte ohne Bewertung zeigen">
      <span class="kachel__wert"><?= (int)$k['keine'] ?></span>
      <span class="kachel__text">Ohne Bewertung</span>
    </div>
    <div class="kachel">
      <span class="kachel__wert"><?= (int)$k['gemeldet'] ?><small>von <?= (int)$k['anzahl'] ?></small></span>
      <span class="kachel__text">diese Woche gemeldet</span>
    </div>
    <div class="kachel">
      <span class="kachel__wert"><?= e(zahl($k['ist'])) ?><small>h</small></span>
      <span class="kachel__text">Ist-Stunden · Soll <?= e(zahl($k['soll'])) ?> h · Kalk. <?= e(zahl($k['kalk'])) ?> h</span>
    </div>
    <div class="kachel">
      <span class="kachel__wert <?= $abw_gesamt !== null && $abw_gesamt > 0 ? 'plus' : '' ?>"><?= e(prozent($abw_gesamt, 1)) ?></span>
      <span class="kachel__text">Abweichung Ist zu Soll gesamt</span>
    </div>
    <div class="kachel">
      <span class="kachel__wert"><?= e(euro($k['nachtraege_offen'])) ?></span>
      <span class="kachel__text">Nachträge offen · genehmigt <?= e(euro($k['nachtraege_genehmigt'])) ?></span>
    </div>
    <div class="kachel">
      <span class="kachel__wert"><?= (int)$k['maengel'] ?></span>
      <span class="kachel__text">offene Mängel · Auftragssumme <?= e(euro($k['auftragssumme'])) ?></span>
    </div>
  </div>

  <div class="leiste">
    <form method="get" id="filterform">
      <label class="visually-hidden" for="phase">Phase</label>
      <select name="phase" id="phase" onchange="this.form.submit()">
        <?php foreach (PHASEN + ['alle' => 'Alle Phasen'] as $wert => $text): ?>
          <option value="<?= e($wert) ?>"<?= $wert === $phase ? ' selected' : '' ?>><?= e($text) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="visually-hidden" for="pl">Projektleiter</label>
      <select name="pl" id="pl" onchange="this.form.submit()">
        <option value="">Alle Projektleiter</option>
        <?php foreach ($pl_liste as $kuerzel => $name): ?>
          <option value="<?= e($kuerzel) ?>"<?= $kuerzel === $pl_filter ? ' selected' : '' ?>><?= e($name) ?> (<?= e($kuerzel) ?>)</option>
        <?php endforeach; ?>
      </select>
    </form>
    <label class="visually-hidden" for="suche">Suchen</label>
    <input type="search" id="suche" autocomplete="off" placeholder="Suchen – Nummer, Projekt, Auftraggeber, Kommentar …">
    <div class="rechts">
      <a class="knopf" href="<?= e($export_url) ?>">CSV exportieren</a>
      <a class="knopf knopf--primaer" href="/Projekte/projekt.php?neu=1">Projekt anlegen</a>
    </div>
  </div>

  <?php if (!$projekte): ?>
    <p class="hinweis">
      Noch keine Projekte in dieser Auswahl.
      <?php if ($benutzer['rolle'] === 'admin'): ?>
        Projekte können <a href="/Projekte/import.php">aus KWP importiert</a> oder
        <a href="/Projekte/projekt.php?neu=1">von Hand angelegt</a> werden.
      <?php else: ?>
        Ein Projekt lässt sich <a href="/Projekte/projekt.php?neu=1">von Hand anlegen</a>.
      <?php endif; ?>
    </p>
  <?php else: ?>

  <div class="tabelle-rahmen">
  <table class="tabelle" id="tabelle">
    <thead>
      <tr>
        <th class="sortierbar mitte" data-sort="num" title="Gesamt-Ampel: die schlechtere aus Stunden und Termin">Ampel</th>
        <th class="sortierbar" data-sort="text">Nr.</th>
        <th class="sortierbar" data-sort="text">Projekt</th>
        <th class="sortierbar" data-sort="text" title="Projektleiter = Sachbearbeiter in KWP">PL</th>
        <th class="sortierbar th-zahl" data-sort="num" title="Kalkulierte Stunden aus dem LV">Kalk. h</th>
        <th class="sortierbar th-zahl" data-sort="num" title="Bisher gebuchte Iststunden">Ist h</th>
        <th class="sortierbar" data-sort="num" title="Geschätzter Fertigstellungsgrad">Fertig</th>
        <th class="sortierbar th-zahl" data-sort="num" title="Sollstunden bis heute = Kalk. × Fertigstellungsgrad">Soll h</th>
        <th class="sortierbar th-zahl" data-sort="num" title="Iststunden minus Sollstunden">Abw. h</th>
        <th class="sortierbar th-zahl" data-sort="num">Abw. %</th>
        <th class="sortierbar" data-sort="text">Termin</th>
        <th class="sortierbar" data-sort="text">Material</th>
        <th class="sortierbar th-zahl" data-sort="num" title="Offene Mängel / Nacharbeiten">Mängel</th>
        <th class="sortierbar th-zahl" data-sort="num" title="Nachträge offen / genehmigt">Nachträge €</th>
        <th class="sortierbar" data-sort="text">Nächster Meilenstein</th>
        <th class="sortierbar" data-sort="text" title="Kalenderwoche der letzten Meldung">Meldung</th>
        <th>Kommentar</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($projekte as $p):
        $b = $p['bewertung'];
        $s = $p['status'];
        $suche = mb_strtolower($p['nummer'] . ' ' . $p['bezeichnung'] . ' ' . $p['auftraggeber'] . ' ' . $p['pl_name'] . ' ' . $p['projektleiter']
               . ' ' . ($s['kommentar'] ?? '') . ' ' . ($s['meilenstein'] ?? ''));
        $kw_klasse = $s === null ? 'kw--fehlt' : ($s['kw'] === $kw_jetzt ? '' : ($s['kw'] === $kw_vorige ? 'kw--alt' : 'kw--fehlt'));
        $zeilen_klasse = $b['gesamt'] === 'rot' ? 'rot-zeile' : ($b['gesamt'] === 'gelb' ? 'gelb-zeile' : '');
    ?>
      <tr class="<?= e($zeilen_klasse) ?>" data-ampel="<?= e($b['gesamt']) ?>" data-suche="<?= e($suche) ?>">
        <td class="mitte" data-wert="<?= (int)$b['rang'] ?>">
          <?= ampel_punkt($b['gesamt'], 'Gesamt: ' . AMPEL_TEXT[$b['gesamt']] . ' · Stunden: ' . AMPEL_TEXT[$b['ampel_std']] . ' · Termin: ' . AMPEL_TEXT[$b['ampel_termin']]) ?>
        </td>
        <td class="nummer"><a href="/Projekte/projekt.php?id=<?= (int)$p['id'] ?>"><?= e($p['nummer']) ?></a></td>
        <td class="projekt">
          <?= e($p['bezeichnung']) ?>
          <?php if ($p['auftraggeber'] !== ''): ?><span class="leise"><?= e($p['auftraggeber']) ?></span><?php endif; ?>
        </td>
        <td title="<?= e($p['pl_name']) ?>"><?= e($p['projektleiter'] !== '' ? $p['projektleiter'] : '–') ?></td>
        <td class="zahl" data-wert="<?= e((string)($p['kalk_stunden'] ?? -1)) ?>"><?= e(zahl($p['kalk_stunden'])) ?></td>
        <td class="zahl" data-wert="<?= e((string)($b['ist'] ?? -1)) ?>"><?= e(zahl($b['ist'])) ?></td>
        <td data-wert="<?= e((string)($b['fertig'] ?? -1)) ?>">
          <?php if ($b['fertig'] !== null): ?>
            <?= e(zahl($b['fertig'])) ?> %
            <span class="balken" aria-hidden="true"><span style="width:<?= (int)max(0, min(100, $b['fertig'])) ?>%"></span></span>
          <?php else: ?>–<?php endif; ?>
        </td>
        <td class="zahl" data-wert="<?= e((string)($b['soll'] ?? -1)) ?>"><?= e(zahl($b['soll'])) ?></td>
        <td class="zahl <?= $b['abw'] !== null ? ($b['abw'] > 0 ? 'plus' : 'minus') : '' ?>" data-wert="<?= e((string)($b['abw'] ?? -99999)) ?>">
          <?= $b['abw'] !== null ? e(($b['abw'] > 0 ? '+' : '') . zahl($b['abw'])) : '–' ?>
        </td>
        <td class="zahl" data-wert="<?= e((string)($b['abw_prozent'] ?? -99)) ?>">
          <?= $b['abw_prozent'] !== null ? ampel_punkt($b['ampel_std']) . ' ' . e(prozent($b['abw_prozent'])) : '–' ?>
        </td>
        <td>
          <?php if ($s !== null && $s['terminstatus'] !== null): ?>
            <?= ampel_punkt($b['ampel_termin']) ?> <?= e(TERMINSTATUS[$s['terminstatus']] ?? $s['terminstatus']) ?>
          <?php else: ?>–<?php endif; ?>
        </td>
        <td><?= $s !== null && $s['materialstatus'] !== null ? e(MATERIALSTATUS[$s['materialstatus']] ?? $s['materialstatus']) : '–' ?></td>
        <td class="zahl" data-wert="<?= $s !== null ? (int)$s['maengel_offen'] : -1 ?>"><?= $s !== null ? (int)$s['maengel_offen'] : '–' ?></td>
        <td class="zahl" data-wert="<?= $s !== null ? e((string)$s['nachtraege_offen']) : -1 ?>">
          <?php if ($s !== null): ?>
            <?= e(zahl((float)$s['nachtraege_offen'])) ?>
            <span class="leise">/ <?= e(zahl((float)$s['nachtraege_genehmigt'])) ?></span>
          <?php else: ?>–<?php endif; ?>
        </td>
        <td>
          <?php if ($s !== null && $s['meilenstein'] !== ''): ?>
            <?= e($s['meilenstein']) ?>
            <?php if ($s['meilenstein_datum']): ?><span class="leise"><?= e(datum_de($s['meilenstein_datum'])) ?></span><?php endif; ?>
          <?php else: ?>–<?php endif; ?>
        </td>
        <td class="<?= e($kw_klasse) ?>" title="<?= $s !== null ? e('Erfasst ' . date('d.m.Y H:i', strtotime((string)$s['erfasst_am']))) : 'Noch keine Meldung' ?>">
          <?= $s !== null ? e(kw_label($s['kw'])) : 'fehlt' ?>
        </td>
        <td class="kommentar"><?= $s !== null ? e($s['kommentar']) : '' ?></td>
        <td><a class="mini" href="/Projekte/projekt.php?id=<?= (int)$p['id'] ?>#meldung">Melden</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4">Summe (<span id="sichtbar"><?= (int)$k['anzahl'] ?></span> Projekte)</td>
        <td class="zahl"><?= e(zahl($k['kalk'])) ?></td>
        <td class="zahl"><?= e(zahl($k['ist'])) ?></td>
        <td></td>
        <td class="zahl"><?= e(zahl($k['soll'])) ?></td>
        <td class="zahl <?= $k['ist'] - $k['soll'] > 0 ? 'plus' : 'minus' ?>"><?= e(($k['ist'] - $k['soll'] > 0 ? '+' : '') . zahl($k['ist'] - $k['soll'])) ?></td>
        <td class="zahl"><?= e(prozent($abw_gesamt, 1)) ?></td>
        <td colspan="2"></td>
        <td class="zahl"><?= (int)$k['maengel'] ?></td>
        <td class="zahl"><?= e(zahl($k['nachtraege_offen'])) ?> <span class="leise">/ <?= e(zahl($k['nachtraege_genehmigt'])) ?></span></td>
        <td colspan="4"></td>
      </tr>
    </tfoot>
  </table>
  </div>

  <p class="leise" style="margin-top:10px">
    Ampel Stunden: Abweichung zum Soll bis <?= e(zahl(ampel_schwellen()['gruen_bis'] * 100)) ?> % grün,
    bis <?= e(zahl(ampel_schwellen()['gelb_bis'] * 100)) ?> % gelb, darüber rot.
    Gesamt-Ampel = die schlechtere aus Stunden- und Termin-Ampel.
    „Meldung“ in Orange = Stand der Vorwoche, in Rot = älter oder fehlt.
  </p>

  <?php endif; ?>

<script>
/* Suche, Ampel-Filter über die Kacheln und Sortierung – alles über die bereits
   gerenderte Tabelle, kein Nachladen. */
(function () {
  var tabelle = document.getElementById('tabelle');
  if (!tabelle) { return; }
  var zeilen = Array.prototype.slice.call(tabelle.tBodies[0].rows);
  var suche = document.getElementById('suche');
  var sichtbar = document.getElementById('sichtbar');
  var ampelFilter = '';

  function filtern() {
    var q = suche.value.trim().toLowerCase();
    var n = 0;
    zeilen.forEach(function (tr) {
      var ok = (q === '' || tr.dataset.suche.indexOf(q) !== -1)
            && (ampelFilter === '' || tr.dataset.ampel === ampelFilter);
      tr.hidden = !ok;
      if (ok) { n++; }
    });
    sichtbar.textContent = n;
  }
  suche.addEventListener('input', filtern);

  Array.prototype.forEach.call(document.querySelectorAll('.kachel--ampel'), function (k) {
    function umschalten() {
      ampelFilter = ampelFilter === k.dataset.ampel ? '' : k.dataset.ampel;
      Array.prototype.forEach.call(document.querySelectorAll('.kachel--ampel'), function (x) {
        x.classList.toggle('gewaehlt', x.dataset.ampel === ampelFilter);
      });
      filtern();
    }
    k.addEventListener('click', umschalten);
    k.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); umschalten(); }
    });
  });

  var koepfe = tabelle.tHead.rows[0].cells;
  Array.prototype.forEach.call(koepfe, function (th, i) {
    if (!th.classList.contains('sortierbar')) { return; }
    th.addEventListener('click', function () {
      var auf = !th.classList.contains('sortiert-auf');
      Array.prototype.forEach.call(koepfe, function (x) { x.classList.remove('sortiert-auf', 'sortiert-ab'); });
      th.classList.add(auf ? 'sortiert-auf' : 'sortiert-ab');
      var num = th.dataset.sort === 'num';
      zeilen.sort(function (a, b) {
        var ca = a.cells[i], cb = b.cells[i];
        var va = num ? parseFloat(ca.dataset.wert !== undefined ? ca.dataset.wert : ca.textContent.replace(/\./g, '').replace(',', '.')) : ca.textContent.trim().toLowerCase();
        var vb = num ? parseFloat(cb.dataset.wert !== undefined ? cb.dataset.wert : cb.textContent.replace(/\./g, '').replace(',', '.')) : cb.textContent.trim().toLowerCase();
        if (num) { va = isNaN(va) ? -Infinity : va; vb = isNaN(vb) ? -Infinity : vb; }
        if (va < vb) { return auf ? -1 : 1; }
        if (va > vb) { return auf ? 1 : -1; }
        return 0;
      });
      zeilen.forEach(function (tr) { tabelle.tBodies[0].appendChild(tr); });
    });
  });
})();
</script>

<?php
projekte_fuss();

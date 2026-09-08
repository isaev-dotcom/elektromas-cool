<?php
/**
 * Einstellungen des Projekt-Dashboards: Ampel-Schwellenwerte und
 * Projektleiter (KWP-Kürzel -> Name). Nur für Administratoren.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/projekte.php';
require_once PRIVAT_PFAD . '/lib/projekte_view.php';

sicherheits_header();
$benutzer = admin_verlangen();

if (ist_post()) {
    csrf_pruefen();
    $aktion = (string)($_POST['aktion'] ?? '');

    if ($aktion === 'schwellen') {
        $gruen = zahl_lesen($_POST['gruen_bis'] ?? '');
        $gelb  = zahl_lesen($_POST['gelb_bis'] ?? '');
        if ($gruen === null || $gelb === null || $gruen < 0 || $gelb <= $gruen || $gelb > 100) {
            projekte_melden('Bitte zwei Prozentwerte eingeben, Gelb größer als Grün.', 'fehler', '/Projekte/einstellungen.php');
        }
        $stmt = db()->prepare('INSERT INTO projekt_einstellungen (schluessel, wert) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE wert = VALUES(wert)');
        $stmt->execute(['ampel_gruen_bis', (string)$gruen]);
        $stmt->execute(['ampel_gelb_bis', (string)$gelb]);
        protokoll('projekte_schwellen', $benutzer['email'], (int)$benutzer['id'], "grün {$gruen} %, gelb {$gelb} %");
        projekte_melden('Schwellenwerte gespeichert.', 'ok', '/Projekte/einstellungen.php');
    }

    if ($aktion === 'pl_neu' || $aktion === 'pl_name') {
        $kuerzel = strtoupper(trim((string)($_POST['kuerzel'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $kuerzel) || $name === '') {
            projekte_melden('Kürzel (2–10 Buchstaben, wie in KWP) und Name sind Pflicht.', 'fehler', '/Projekte/einstellungen.php');
        }
        $stmt = db()->prepare('INSERT INTO projektleiter (kuerzel, name, aktiv) VALUES (?, ?, 1)
                               ON DUPLICATE KEY UPDATE name = VALUES(name)');
        $stmt->execute([$kuerzel, mb_substr($name, 0, 120)]);
        protokoll('projektleiter_gepflegt', $benutzer['email'], (int)$benutzer['id'], "$kuerzel = $name");
        projekte_melden("Projektleiter {$kuerzel} gespeichert.", 'ok', '/Projekte/einstellungen.php');
    }

    if ($aktion === 'pl_aktiv') {
        $kuerzel = strtoupper(trim((string)($_POST['kuerzel'] ?? '')));
        $aktiv = !empty($_POST['aktiv']) ? 1 : 0;
        $stmt = db()->prepare('UPDATE projektleiter SET aktiv = ? WHERE kuerzel = ?');
        $stmt->execute([$aktiv, $kuerzel]);
        projekte_melden($aktiv ? "{$kuerzel} ist wieder aktiv." : "{$kuerzel} ist stillgelegt und erscheint nicht mehr in der Auswahl.", 'ok', '/Projekte/einstellungen.php');
    }

    if ($aktion === 'pl_loeschen') {
        $kuerzel = strtoupper(trim((string)($_POST['kuerzel'] ?? '')));
        $stmt = db()->prepare('SELECT COUNT(*) FROM projekte WHERE projektleiter = ?');
        $stmt->execute([$kuerzel]);
        if ((int)$stmt->fetchColumn() > 0) {
            projekte_melden("{$kuerzel} ist noch Projekten zugeordnet und kann nur stillgelegt werden.", 'fehler', '/Projekte/einstellungen.php');
        }
        $stmt = db()->prepare('DELETE FROM projektleiter WHERE kuerzel = ?');
        $stmt->execute([$kuerzel]);
        projekte_melden("{$kuerzel} gelöscht.", 'ok', '/Projekte/einstellungen.php');
    }
}

$schwellen = ampel_schwellen();
$pl = db()->query('SELECT kuerzel, name, aktiv FROM projektleiter ORDER BY aktiv DESC, name')->fetchAll();

// Kürzel, die in Projekten vorkommen, aber noch keinen Namen haben
$bekannt = array_column($pl, 'kuerzel');
$fremd = [];
foreach (db()->query("SELECT projektleiter, COUNT(*) AS n FROM projekte WHERE projektleiter <> '' GROUP BY projektleiter")->fetchAll() as $r) {
    if (!in_array($r['projektleiter'], $bekannt, true)) {
        $fremd[$r['projektleiter']] = (int)$r['n'];
    }
}

projekte_kopf('Einstellungen', $benutzer, 'Ampel-Schwellenwerte und Projektleiter', 'einstellungen');
projekte_meldung_anzeigen();
?>

<div class="karten">

  <section class="karte">
    <h2>Ampel Stunden</h2>
    <p class="erklaerung">
      Bewertet wird die Abweichung der Iststunden vom Soll (kalkulierte Stunden × Fertigstellungsgrad),
      in Prozent über Soll. Entspricht den Zellen B27:B29 im Blatt „Anleitung“ der Excel-Vorlage.
      Eine Änderung wirkt sofort – auch rückwirkend auf alle Wochenmeldungen.
    </p>
    <form method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="aktion" value="schwellen">
      <div class="felder">
        <div class="feld">
          <label for="gruen_bis">Grün bis (% über Soll)</label>
          <input type="text" id="gruen_bis" name="gruen_bis" inputmode="decimal" value="<?= e(zahl($schwellen['gruen_bis'] * 100, 1)) ?>">
        </div>
        <div class="feld">
          <label for="gelb_bis">Gelb bis (% über Soll)</label>
          <input type="text" id="gelb_bis" name="gelb_bis" inputmode="decimal" value="<?= e(zahl($schwellen['gelb_bis'] * 100, 1)) ?>">
          <span class="unter">darüber Rot</span>
        </div>
      </div>
      <div class="form-fuss">
        <button class="knopf knopf--primaer">Speichern</button>
      </div>
    </form>
  </section>

  <section class="karte">
    <h2>Projektleiter</h2>
    <p class="erklaerung">Das Kürzel ist das Feld „Sachbearbeiter“ in KWP. Stillgelegte Kürzel bleiben in alten Projekten sichtbar, erscheinen aber nicht mehr in der Auswahl.</p>

    <?php if ($fremd): ?>
      <p class="meldung meldung--hinweis">
        In Projekten kommen Kürzel ohne Namen vor:
        <?php foreach ($fremd as $k => $n): ?><strong><?= e($k) ?></strong> (<?= (int)$n ?>) <?php endforeach; ?>
        – unten einen Namen zuordnen.
      </p>
    <?php endif; ?>

    <div class="tabelle-rahmen">
    <table class="tabelle">
      <thead><tr><th>Kürzel</th><th>Name</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($pl as $p): ?>
        <tr>
          <td class="nummer"><?= e($p['kuerzel']) ?></td>
          <td>
            <form method="post" class="inline">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="pl_name">
              <input type="hidden" name="kuerzel" value="<?= e($p['kuerzel']) ?>">
              <input type="text" name="name" value="<?= e($p['name']) ?>" maxlength="120" style="padding:5px 8px;font:inherit;font-size:.85rem;border:1px solid var(--border);border-radius:7px;width:220px;max-width:100%">
              <button class="mini">Umbenennen</button>
            </form>
          </td>
          <td><?= $p['aktiv'] ? 'aktiv' : '<span class="leise">stillgelegt</span>' ?></td>
          <td>
            <form method="post" class="inline">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="pl_aktiv">
              <input type="hidden" name="kuerzel" value="<?= e($p['kuerzel']) ?>">
              <input type="hidden" name="aktiv" value="<?= $p['aktiv'] ? '0' : '1' ?>">
              <button class="mini"><?= $p['aktiv'] ? 'Stilllegen' : 'Aktivieren' ?></button>
            </form>
            <form method="post" class="inline" onsubmit="return confirm('<?= e($p['kuerzel']) ?> löschen?')">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="pl_loeschen">
              <input type="hidden" name="kuerzel" value="<?= e($p['kuerzel']) ?>">
              <button class="mini mini--warnung">Löschen</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <form method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="aktion" value="pl_neu">
      <div class="felder">
        <div class="feld">
          <label for="kuerzel">Kürzel (wie in KWP)</label>
          <input type="text" id="kuerzel" name="kuerzel" maxlength="10" placeholder="z. B. JAKO" value="<?= e(array_key_first($fremd) ?? '') ?>">
        </div>
        <div class="feld">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" maxlength="120" placeholder="Vorname Nachname">
        </div>
      </div>
      <div class="form-fuss">
        <button class="knopf">Projektleiter hinzufügen</button>
      </div>
    </form>
  </section>

</div>

<?php
projekte_fuss();

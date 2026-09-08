<?php
/**
 * Import der Projektstammdaten aus KWP.
 *
 * Drei Wege:
 *   1. In KWP die Projektliste markieren, kopieren (Strg+C) und hier einfügen.
 *   2. Eine CSV-Datei hochladen.
 *   3. Eine XLSX-Datei hochladen (z. B. die Kalkulationsübersicht mit
 *      Auftragszeit und Auftragssumme).
 *
 * Ablauf: Vorschau -> prüfen -> übernehmen. Bestehende Projekte werden nur in
 * den Feldern geändert, die der Import liefert; Wochenmeldungen bleiben
 * unberührt.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/projekte.php';
require_once PRIVAT_PFAD . '/lib/projekte_view.php';

sicherheits_header();
$benutzer = admin_verlangen();

$fehler = [];
$hinweise = [];
$plan = null;
$format = '';

if (ist_post()) {
    csrf_pruefen();
    $schritt = (string)($_POST['schritt'] ?? '');

    if ($schritt === 'vorschau') {
        $sammel_ueberspringen = !empty($_POST['sammel_ueberspringen']);
        $rows = [];
        try {
            if (!empty($_FILES['datei']['tmp_name']) && is_uploaded_file($_FILES['datei']['tmp_name'])) {
                if ((int)$_FILES['datei']['size'] > 5 * 1024 * 1024) {
                    throw new RuntimeException('Die Datei ist größer als 5 MB.');
                }
                $name = strtolower((string)$_FILES['datei']['name']);
                if (str_ends_with($name, '.xlsx') || str_ends_with($name, '.xlsm')) {
                    $rows = xlsx_lesen($_FILES['datei']['tmp_name']);
                } else {
                    $rows = import_text_zerlegen((string)file_get_contents($_FILES['datei']['tmp_name']));
                }
            } elseif (!empty($_FILES['datei']['error']) && (int)$_FILES['datei']['error'] !== UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Der Upload ist fehlgeschlagen (Fehler ' . (int)$_FILES['datei']['error'] . ').');
            } else {
                $text = (string)($_POST['text'] ?? '');
                if (trim($text) === '') {
                    throw new RuntimeException('Bitte Text einfügen oder eine Datei auswählen.');
                }
                $rows = import_text_zerlegen($text);
            }

            $erg = import_zuordnen($rows);
            $format = $erg['format'];
            $hinweise = $erg['hinweise'];
            if ($erg['zeilen']) {
                $plan = import_planen($erg['zeilen'], $sammel_ueberspringen);
                $_SESSION['projekte_import'] = ['plan' => $plan, 'zeit' => time()];
            }
        } catch (Throwable $e) {
            $fehler[] = $e->getMessage();
        }
    } elseif ($schritt === 'uebernehmen') {
        $gemerkt = $_SESSION['projekte_import'] ?? null;
        unset($_SESSION['projekte_import']);
        if (!$gemerkt || (time() - (int)$gemerkt['zeit']) > 1800) {
            projekte_melden('Die Vorschau ist abgelaufen. Bitte den Import erneut starten.', 'fehler', '/Projekte/import.php');
        }
        try {
            $z = import_ausfuehren($gemerkt['plan']);
            protokoll('projekte_import', $benutzer['email'], (int)$benutzer['id'],
                "neu {$z['neu']}, aktualisiert {$z['aktualisieren']}, unverändert {$z['unveraendert']}, übersprungen {$z['uebersprungen']}");
            projekte_melden(
                "Import abgeschlossen: {$z['neu']} neu, {$z['aktualisieren']} aktualisiert, {$z['unveraendert']} unverändert, {$z['uebersprungen']} übersprungen.",
                'ok', '/Projekte/'
            );
        } catch (Throwable $e) {
            error_log('Projekte-Import: ' . $e->getMessage());
            projekte_melden('Der Import ist fehlgeschlagen, es wurde nichts geändert.', 'fehler', '/Projekte/import.php');
        }
    }
}

$feldnamen = [
    'nummer' => 'Nr.', 'bezeichnung' => 'Bezeichnung', 'projektleiter' => 'PL', 'auftraggeber' => 'Auftraggeber',
    'anlagedatum' => 'Anlagedatum', 'kwp_status' => 'KWP-Status', 'kwp_zustand' => 'KWP-Zustand',
    'kalk_stunden' => 'Kalk. h', 'auftragssumme' => 'Auftragssumme', 'phase' => 'Phase',
];
$formatnamen = [
    'kwp_liste' => 'KWP-Projektliste', 'kwp_liste_ohne_kopf' => 'KWP-Projektliste ohne Überschriften',
    'kwp_kalkulation' => 'KWP-Kalkulationsübersicht (Auftragszeit / Auftragssumme)', 'eigene' => 'Tabelle mit eigenen Überschriften',
];

projekte_kopf('Import aus KWP', $benutzer, 'Projektstammdaten übernehmen – Wochenmeldungen bleiben erhalten', 'import');
projekte_meldung_anzeigen();
foreach ($fehler as $f) {
    echo '<p class="meldung meldung--fehler">' . e($f) . '</p>';
}
foreach ($hinweise as $h) {
    echo '<p class="meldung meldung--hinweis">' . e($h) . '</p>';
}

if ($plan !== null):
    $zaehler = ['neu' => 0, 'aktualisieren' => 0, 'unveraendert' => 0, 'uebersprungen' => 0];
    foreach ($plan as $p) {
        $zaehler[$p['aktion']]++;
    }
    $aktionstext = ['neu' => 'Neu', 'aktualisieren' => 'Aktualisieren', 'unveraendert' => 'Unverändert', 'uebersprungen' => 'Übersprungen'];
?>
  <section class="karte karte--voll">
    <h2>Vorschau</h2>
    <p class="erklaerung">
      Erkannt: <strong><?= e($formatnamen[$format] ?? $format) ?></strong> ·
      <?= (int)$zaehler['neu'] ?> neu, <?= (int)$zaehler['aktualisieren'] ?> zu aktualisieren,
      <?= (int)$zaehler['unveraendert'] ?> unverändert, <?= (int)$zaehler['uebersprungen'] ?> übersprungen.
      Noch ist nichts gespeichert – bitte prüfen und dann übernehmen.
    </p>
    <div class="tabelle-rahmen">
    <table class="tabelle">
      <thead>
        <tr><th>Aktion</th><th>Nr.</th><th>Bezeichnung</th><th>PL</th><th>Auftraggeber</th><th>Zustand</th><th class="th-zahl">Kalk. h</th><th class="th-zahl">Summe</th><th>Änderungen</th></tr>
      </thead>
      <tbody>
      <?php foreach ($plan as $p): $d = $p['daten']; ?>
        <tr>
          <td><span class="aktion aktion--<?= e($p['aktion']) ?>"><?= e($aktionstext[$p['aktion']]) ?></span><?= $p['grund'] !== '' ? '<br><span class="leise">' . e($p['grund']) . '</span>' : '' ?></td>
          <td class="nummer"><?= e($d['nummer']) ?></td>
          <td><?= e((string)($d['bezeichnung'] ?? '')) ?></td>
          <td><?= e((string)($d['projektleiter'] ?? '')) ?></td>
          <td><?= e((string)($d['auftraggeber'] ?? '')) ?></td>
          <td><?= e((string)($d['kwp_zustand'] ?? '')) ?></td>
          <td class="zahl"><?= isset($d['kalk_stunden']) ? e(zahl($d['kalk_stunden'], 1)) : '' ?></td>
          <td class="zahl"><?= isset($d['auftragssumme']) ? e(euro($d['auftragssumme'])) : '' ?></td>
          <td>
            <?php foreach ($p['aenderungen'] as $feld => [$alt, $neu_]): ?>
              <span class="aenderung"><strong><?= e($feldnamen[$feld] ?? $feld) ?>:</strong>
                <s><?= e($alt === null || $alt === '' ? '–' : (string)$alt) ?></s> → <?= e((string)$neu_) ?></span>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <form method="post" class="form-fuss">
      <?= csrf_feld() ?>
      <input type="hidden" name="schritt" value="uebernehmen">
      <button class="knopf knopf--primaer"<?= $zaehler['neu'] + $zaehler['aktualisieren'] === 0 ? ' disabled' : '' ?>>
        <?= (int)($zaehler['neu'] + $zaehler['aktualisieren']) ?> Projekte übernehmen
      </button>
      <a class="knopf" href="/Projekte/import.php">Abbrechen</a>
    </form>
  </section>
<?php endif; ?>

<div class="karten">
  <section class="karte">
    <h2>Aus KWP einfügen</h2>
    <p class="erklaerung">
      In KWP die Projektliste öffnen, alle Zeilen markieren, mit <kbd>Strg</kbd>+<kbd>C</kbd> kopieren
      und hier einfügen. Erkannt werden die Spalten Projekt-Nr, Projekt-Bezeichnung, Anlagedatum,
      Status, Zustand, Sachbearbeiter (= Projektleiter) und Auftraggeber.
      Ebenso die Kalkulationsübersicht mit „Auftrag“, „Auftragszeit“ und „Auftragssumme“.
    </p>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_feld() ?>
      <input type="hidden" name="schritt" value="vorschau">
      <div class="felder">
        <div class="feld feld--breit">
          <label for="text">Eingefügter Text</label>
          <textarea id="text" name="text" class="pasten" placeholder="Projekt-Nr&#9;Projekt-Bezeichnung&#9;Anlagedatum&#9;Status&#9;Zustand&#9;…&#10;P260104&#9;Rückbau Beleuchtung …"></textarea>
        </div>
        <div class="feld feld--breit">
          <label for="datei">… oder Datei hochladen (CSV, TXT, XLSX)</label>
          <input type="file" id="datei" name="datei" accept=".csv,.txt,.tsv,.xlsx,.xlsm">
        </div>
        <div class="feld feld--breit">
          <label class="kreuz"><input type="checkbox" name="sammel_ueberspringen" value="1" checked> Sammelprojekte (X26-WERKZEUG, X26-KFZ, …) überspringen</label>
        </div>
      </div>
      <div class="form-fuss">
        <button class="knopf knopf--primaer">Vorschau anzeigen</button>
      </div>
    </form>
  </section>

  <section class="karte">
    <h2>Was der Import macht</h2>
    <ul class="steps">
      <li><strong>Neue Nummern</strong> werden als Projekt angelegt. Die Phase ergibt sich aus dem KWP-Zustand: „Auftrag erhalten“ → laufend, „Auftrag noch nicht vergeben“ → Angebot.</li>
      <li><strong>Bekannte Nummern</strong> werden nur in den Feldern aktualisiert, die der Import liefert und die sich geändert haben – die Vorschau zeigt jede Änderung. Der Projektleiter folgt dabei immer dem KWP-Feld „Sachbearbeiter“; ein Wechsel in KWP kommt mit dem nächsten Import an.</li>
      <li><strong>Wochenmeldungen</strong> der Projektleiter werden nie angefasst.</li>
      <li><strong>Abgeschlossene Projekte</strong> bleiben abgeschlossen, auch wenn KWP noch „Auftrag erhalten“ meldet.</li>
      <li><strong>Kalkulierte Stunden</strong> kommen aus der Kalkulationsübersicht („Auftragszeit“). Wer sie von Hand gepflegt hat, sieht in der Vorschau, ob der Import sie überschreiben würde.</li>
      <li><strong>Unbekannte Sachbearbeiter-Kürzel</strong> (z. B. JAKO) bleiben als Kürzel stehen und können unter Einstellungen einem Namen zugeordnet werden.</li>
    </ul>
  </section>
</div>

<?php
projekte_fuss();

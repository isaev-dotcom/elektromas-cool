<?php
/**
 * Verwaltung des Portals — nur für Administratoren.
 *
 * Sechs Bereiche: Benutzer, Quellen, Kategorien, Widerspruchsmatrix,
 * Statistik und Protokoll. Alles in einer Datei, weil die Bereiche sich
 * dieselbe Rechteprüfung, dieselbe Reiterleiste und dieselben Formularmuster
 * teilen; sechs Dateien wären sechsmal derselbe Kopf.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';
require_once PRIVAT_PFAD . '/lib/triz_sammler.php';

$ich = triz_admin_verlangen();

$bereiche = [
    'benutzer'   => 'verw_benutzer',
    'quellen'    => 'verw_quellen',
    'kategorien' => 'verw_kategorien',
    'matrix'     => 'verw_matrix',
    'statistik'  => 'verw_statistik',
    'protokoll'  => 'verw_protokoll',
];
$bereich = (string)($_GET['bereich'] ?? 'benutzer');
if (!isset($bereiche[$bereich])) {
    $bereich = 'benutzer';
}

$fehler = '';
$ok     = '';
$einmal_link = '';

// ===========================================================================
// Aktionen
// ===========================================================================

if (ist_post()) {
    csrf_pruefen();
    $aktion = (string)($_POST['aktion'] ?? '');

    switch ($aktion) {

        // --- Benutzer ------------------------------------------------------

        case 'einladen': {
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $name  = trim((string)($_POST['name'] ?? ''));
            $rolle = ($_POST['rolle'] ?? '') === 'admin' ? 'admin' : 'mitarbeiter';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
                $fehler = 'Bitte eine gültige E-Mail-Adresse eingeben.';
                break;
            }

            $stmt = db()->prepare('SELECT id, status FROM triz_benutzer WHERE email = ?');
            $stmt->execute([$email]);
            if ($vorhanden = $stmt->fetch()) {
                $fehler = 'Für diese Adresse gibt es bereits einen Portalzugang (Status: '
                        . $vorhanden['status'] . '). Nutzen Sie in der Liste „Einladung neu".';
                break;
            }

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO triz_benutzer (email, name, rolle, status)
                     VALUES (?, ?, ?, 'eingeladen')"
                );
                $ins->execute([$email, mb_substr($name, 0, 120), $rolle]);
                $benutzer_id = (int)$pdo->lastInsertId();

                [$klartext, $hash] = token_erzeugen();
                $stunden = (int)($CONFIG['sicherheit']['einladung_gueltig_stunden'] ?? 168);
                $bis = (new DateTimeImmutable("+{$stunden} hours"))->format('Y-m-d H:i:s');

                $ins2 = $pdo->prepare(
                    'INSERT INTO triz_einladungen (benutzer_id, token_hash, gueltig_bis, erstellt_von)
                     VALUES (?, ?, ?, ?)'
                );
                $ins2->execute([$benutzer_id, $hash, $bis, (int)$ich['id']]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('TRIZ-Einladung fehlgeschlagen: ' . $e->getMessage());
                $fehler = 'Der Zugang konnte nicht angelegt werden.';
                break;
            }

            $link = $CONFIG['basis_url'] . '/TRIZ/einladung.php?token=' . urlencode($klartext);
            triz_protokoll('einladung_erstellt', $email, $benutzer_id, 'durch ' . $ich['email']);

            if (triz_mail_einladung($email, $name, $link, $stunden)) {
                $ok = 'Einladung verschickt an ' . $email . '.';
            } else {
                // Der Zugang existiert, nur die Mail ging nicht raus. Den Link
                // hier einmalig zeigen - in der Datenbank steht nur sein Hash,
                // er lässt sich später nicht wiederherstellen.
                $ok = 'Zugang angelegt, aber der Mailversand schlug fehl. '
                    . 'Geben Sie den folgenden Link persönlich weiter:';
                $einmal_link = $link;
            }
            break;
        }

        case 'neu_einladen': {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare('SELECT * FROM triz_benutzer WHERE id = ?');
            $stmt->execute([$id]);
            $b = $stmt->fetch();
            if (!$b) {
                break;
            }

            [$klartext, $hash] = token_erzeugen();
            $stunden = (int)($CONFIG['sicherheit']['einladung_gueltig_stunden'] ?? 168);
            $bis = (new DateTimeImmutable("+{$stunden} hours"))->format('Y-m-d H:i:s');

            // Offene Einladungen entwerten: Sonst blieben mehrere gültige
            // Links gleichzeitig im Umlauf.
            $alt = db()->prepare(
                'UPDATE triz_einladungen SET gueltig_bis = NOW()
                 WHERE benutzer_id = ? AND eingeloest_am IS NULL'
            );
            $alt->execute([$id]);

            $ins = db()->prepare(
                'INSERT INTO triz_einladungen (benutzer_id, token_hash, gueltig_bis, erstellt_von)
                 VALUES (?, ?, ?, ?)'
            );
            $ins->execute([$id, $hash, $bis, (int)$ich['id']]);

            $link = $CONFIG['basis_url'] . '/TRIZ/einladung.php?token=' . urlencode($klartext);
            triz_protokoll('einladung_erneuert', (string)$b['email'], $id, 'durch ' . $ich['email']);

            if (triz_mail_einladung((string)$b['email'], (string)$b['name'], $link, $stunden)) {
                $ok = 'Neue Einladung verschickt an ' . $b['email'] . '.';
            } else {
                $ok = 'Neue Einladung erstellt, Mailversand fehlgeschlagen:';
                $einmal_link = $link;
            }
            break;
        }

        case 'status': {
            $id  = (int)($_POST['id'] ?? 0);
            $neu = (string)($_POST['neu'] ?? '');
            if (!in_array($neu, ['aktiv', 'gesperrt'], true)) {
                break;
            }
            // Sich selbst zu sperren ist der zuverlässigste Weg, sich
            // auszusperren - deshalb ausgeschlossen.
            if ($id === (int)$ich['id']) {
                $fehler = 'Der eigene Zugang lässt sich hier nicht ändern.';
                break;
            }
            $stmt = db()->prepare('UPDATE triz_benutzer SET status = ? WHERE id = ? AND status <> ?');
            $stmt->execute([$neu, $id, 'eingeladen']);
            triz_protokoll('status_geaendert', '', $id, $neu . ' durch ' . $ich['email']);
            $ok = 'Status geändert.';
            break;
        }

        case 'rolle': {
            $id  = (int)($_POST['id'] ?? 0);
            $neu = ($_POST['neu'] ?? '') === 'admin' ? 'admin' : 'mitarbeiter';
            if ($id === (int)$ich['id']) {
                $fehler = 'Die eigene Rolle lässt sich hier nicht ändern.';
                break;
            }
            $stmt = db()->prepare('UPDATE triz_benutzer SET rolle = ? WHERE id = ?');
            $stmt->execute([$neu, $id]);
            triz_protokoll('rolle_geaendert', '', $id, $neu . ' durch ' . $ich['email']);
            $ok = 'Rolle geändert.';
            break;
        }

        // --- Quellen -------------------------------------------------------

        case 'quelle_neu': {
            $art    = ($_POST['art'] ?? '') === 'youtube' ? 'youtube' : 'rss';
            $name   = trim((string)($_POST['name'] ?? ''));
            $url    = trim((string)($_POST['url'] ?? ''));
            $sprache= (string)($_POST['sprache_q'] ?? 'de');
            $region = (string)($_POST['region'] ?? 'de');
            $kat    = (int)($_POST['kategorie'] ?? 0);

            if (!in_array($sprache, ['de', 'ru', 'en'], true)) { $sprache = 'de'; }
            if (!in_array($region, ['de', 'ru', 'int'], true)) { $region = 'de'; }

            if ($url === '' || $name === '') {
                $fehler = 'Name und Adresse werden gebraucht.';
                break;
            }

            if ($art === 'youtube') {
                // Aus @handle oder Kanalseite die Feed-Adresse ermitteln und
                // diese speichern: Der Umweg über die Kanalseite fällt damit
                // nur einmal an, nicht bei jedem Lauf.
                $feed = triz_youtube_feed_adresse($url);
                if ($feed === null) {
                    $fehler = 'Zu dieser Adresse ließ sich kein YouTube-Kanal ermitteln. '
                            . 'Bitte die vollständige Kanaladresse (…/channel/UC…) angeben.';
                    break;
                }
                $url = $feed;
            } elseif (!preg_match('~^https?://~', $url)) {
                $fehler = 'Die Adresse muss mit http:// oder https:// beginnen.';
                break;
            }

            $ins = db()->prepare(
                'INSERT IGNORE INTO triz_quellen (art, name, url, sprache, region, kategorie_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([$art, mb_substr($name, 0, 160), mb_substr($url, 0, 500),
                           $sprache, $region, $kat > 0 ? $kat : null]);

            $ok = $ins->rowCount() > 0 ? 'Quelle angelegt.' : 'Diese Adresse ist bereits eingetragen.';
            triz_protokoll('quelle_angelegt', (string)$ich['email'], (int)$ich['id'], $name);
            break;
        }

        case 'quelle_umschalten': {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare('UPDATE triz_quellen SET aktiv = 1 - aktiv WHERE id = ?');
            $stmt->execute([$id]);
            break;
        }

        case 'quelle_loeschen': {
            $id = (int)($_POST['id'] ?? 0);
            // Die gesammelten Beiträge bleiben: Sie sind unabhängig davon
            // brauchbar, dass die Quelle nicht mehr abgefragt wird.
            $stmt = db()->prepare('DELETE FROM triz_quellen WHERE id = ?');
            $stmt->execute([$id]);
            $ok = 'Quelle entfernt.';
            break;
        }

        case 'sammeln': {
            // Der reguläre Weg ist der nächtliche Cronjob; das hier ist der
            // Knopf für „jetzt sofort". Bei vielen Quellen kann das über die
            // Zeitgrenze der PHP-Ausführung laufen - deshalb heraufgesetzt,
            // soweit der Server es zulässt.
            @set_time_limit(300);
            $ergebnis = triz_alles_sammeln();
            $ok = t('sammeln_fertig', $ergebnis['beitraege'], $ergebnis['videos']);
            triz_protokoll('sammlung_manuell', (string)$ich['email'], (int)$ich['id'],
                $ergebnis['beitraege'] . '/' . $ergebnis['videos']);
            break;
        }

        // --- Kategorien ----------------------------------------------------

        case 'kategorie_neu': {
            $b_bereich = (string)($_POST['kat_bereich'] ?? 'news');
            if (!in_array($b_bereich, ['news', 'video', 'dokument'], true)) {
                $b_bereich = 'news';
            }
            $schluessel = strtolower(trim((string)($_POST['schluessel'] ?? '')));
            $schluessel = preg_replace('/[^a-z0-9-]/', '-', $schluessel) ?? '';
            $name_de = trim((string)($_POST['name_de'] ?? ''));
            $name_ru = trim((string)($_POST['name_ru'] ?? ''));
            $sort    = (int)($_POST['sortierung'] ?? 100);

            if ($schluessel === '' || $name_de === '') {
                $fehler = 'Schlüssel und deutscher Name werden gebraucht.';
                break;
            }
            $ins = db()->prepare(
                'INSERT IGNORE INTO triz_kategorien (bereich, schluessel, name_de, name_ru, sortierung)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute([$b_bereich, $schluessel, $name_de, $name_ru, $sort]);
            $ok = $ins->rowCount() > 0 ? 'Kategorie angelegt.' : 'Diesen Schlüssel gibt es bereits.';
            break;
        }

        case 'kategorie_speichern': {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare(
                'UPDATE triz_kategorien SET name_de = ?, name_ru = ?, sortierung = ? WHERE id = ?'
            );
            $stmt->execute([
                trim((string)($_POST['name_de'] ?? '')),
                trim((string)($_POST['name_ru'] ?? '')),
                (int)($_POST['sortierung'] ?? 100),
                $id,
            ]);
            $ok = 'Kategorie gespeichert.';
            break;
        }

        case 'kategorie_loeschen': {
            $id = (int)($_POST['id'] ?? 0);
            // Zuordnungen lösen, sonst zeigten Beiträge auf eine Kategorie,
            // die es nicht mehr gibt.
            db()->prepare('UPDATE triz_beitraege SET kategorie_id = NULL WHERE kategorie_id = ?')
                ->execute([$id]);
            db()->prepare('UPDATE triz_videos SET kategorie_id = NULL WHERE kategorie_id = ?')
                ->execute([$id]);
            db()->prepare('UPDATE triz_quellen SET kategorie_id = NULL WHERE kategorie_id = ?')
                ->execute([$id]);
            db()->prepare('DELETE FROM triz_kategorien WHERE id = ?')->execute([$id]);
            $ok = 'Kategorie entfernt.';
            break;
        }

        // --- Widerspruchsmatrix --------------------------------------------

        case 'matrix_import': {
            $datei = $_FILES['csv'] ?? null;
            if (!is_array($datei) || ($datei['error'] ?? 1) !== UPLOAD_ERR_OK) {
                $fehler = 'Es wurde keine Datei übertragen.';
                break;
            }

            $griff = @fopen((string)$datei['tmp_name'], 'r');
            if ($griff === false) {
                $fehler = 'Die Datei ließ sich nicht lesen.';
                break;
            }

            $ins = db()->prepare(
                'REPLACE INTO triz_matrix (verbessert, verschlechtert, prinzipien) VALUES (?, ?, ?)'
            );
            $zeilen = 0;
            $uebergangen = 0;

            while (($z = fgets($griff)) !== false) {
                $z = trim($z);
                if ($z === '' || str_starts_with($z, '#')) {
                    continue;
                }
                /*
                 * Erst die beiden Parameternummern, dann der ganze Rest als
                 * Prinzipienliste. Das ist gutmütiger als eine feste
                 * Spaltentrennung: Excel schreibt je nach Ländereinstellung
                 * Komma oder Semikolon, und die dritte Spalte enthält selbst
                 * Kommas - mal in Anführungszeichen, mal nicht.
                 */
                if (!preg_match('/^\s*"?(\d{1,2})"?\s*[;,\t]\s*"?(\d{1,2})"?\s*[;,\t]\s*(.*)$/', $z, $felder)) {
                    $uebergangen++;
                    continue;
                }

                $v = (int)$felder[1];
                $w = (int)$felder[2];

                // Aus dem Rest bleiben die Zahlen 1 bis 40 übrig, in der
                // Reihenfolge, in der sie dastehen. Alles andere - Trenner,
                // Anführungszeichen, Leerzeichen - fällt weg.
                $p = implode(',', array_filter(
                    array_map('intval', preg_split('/[^\d]+/', $felder[3]) ?: []),
                    static fn(int $n): bool => $n >= 1 && $n <= 40
                ));

                if ($v < 1 || $v > 39 || $w < 1 || $w > 39) {
                    $uebergangen++;
                    continue;
                }
                $ins->execute([$v, $w, mb_substr($p, 0, 40)]);
                $zeilen++;
            }
            fclose($griff);

            $ok = $zeilen . ' Matrixfelder eingespielt'
                . ($uebergangen > 0 ? ', ' . $uebergangen . ' Zeilen übergangen.' : '.');
            triz_protokoll('matrix_import', (string)$ich['email'], (int)$ich['id'], (string)$zeilen);
            break;
        }

        case 'matrix_leeren': {
            db()->exec('DELETE FROM triz_matrix');
            $ok = 'Matrix geleert.';
            break;
        }
    }
}

triz_kopf(t('verw_titel'), 'verwaltung');
triz_seitenkopf(t('verw_titel'));
triz_meldung($fehler, 'fehler');
triz_meldung($ok, $einmal_link !== '' ? 'hinweis' : 'ok');

if ($einmal_link !== '') {
    echo '<p class="einmal-link"><code>' . e($einmal_link) . '</code></p>';
    echo '<p class="leise" style="margin-bottom:20px">Dieser Link wird nur jetzt angezeigt. '
       . 'In der Datenbank steht lediglich seine Prüfsumme.</p>';
}
?>

<nav class="reiter">
  <?php foreach ($bereiche as $schluessel => $text): ?>
    <a href="/TRIZ/verwaltung.php?bereich=<?= e($schluessel) ?>"
       class="<?= $bereich === $schluessel ? 'ist-an' : '' ?>"><?= e(t($text)) ?></a>
  <?php endforeach; ?>
</nav>

<?php
// ===========================================================================
// Benutzer
// ===========================================================================
if ($bereich === 'benutzer'):
    $benutzer = db()->query(
        'SELECT * FROM triz_benutzer ORDER BY status, name, email'
    )->fetchAll();
?>

<div class="tabelle-huelle">
  <table>
    <thead>
      <tr>
        <th><?= e(t('name')) ?></th>
        <th><?= e(t('email')) ?></th>
        <th><?= e(t('rolle')) ?></th>
        <th><?= e(t('status')) ?></th>
        <th><?= e(t('letzter_login')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($benutzer as $b): ?>
        <tr>
          <td><?= e($b['name'] !== '' ? $b['name'] : '–') ?></td>
          <td><?= e($b['email']) ?></td>
          <td>
            <?php if ((int)$b['id'] === (int)$ich['id']): ?>
              <?= e(t('rolle_' . $b['rolle'])) ?>
            <?php else: ?>
              <form method="post">
                <?= csrf_feld() ?>
                <input type="hidden" name="aktion" value="rolle">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="neu" value="<?= $b['rolle'] === 'admin' ? 'mitarbeiter' : 'admin' ?>">
                <button type="submit" class="knopf knopf--klein">
                  <?= e(t('rolle_' . $b['rolle'])) ?> ⇄
                </button>
              </form>
            <?php endif; ?>
          </td>
          <td><span class="marke"><?= e(t('status_' . $b['status'])) ?></span></td>
          <td><?= e($b['letzter_login'] ? triz_datum($b['letzter_login'], true) : t('nie')) ?></td>
          <td>
            <?php if ($b['status'] === 'eingeladen'): ?>
              <form method="post">
                <?= csrf_feld() ?>
                <input type="hidden" name="aktion" value="neu_einladen">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button type="submit" class="knopf knopf--klein"><?= e(t('einladung_neu')) ?></button>
              </form>
            <?php elseif ((int)$b['id'] !== (int)$ich['id']): ?>
              <form method="post">
                <?= csrf_feld() ?>
                <input type="hidden" name="aktion" value="status">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="neu" value="<?= $b['status'] === 'aktiv' ? 'gesperrt' : 'aktiv' ?>">
                <button type="submit" class="knopf knopf--klein <?= $b['status'] === 'aktiv' ? 'knopf--warnung' : '' ?>">
                  <?= e($b['status'] === 'aktiv' ? t('sperren') : t('freigeben')) ?>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<section class="abschnitt" style="margin-top:32px">
  <h2><?= e(t('einladen')) ?></h2>
  <div class="karte">
    <p class="leise" style="margin-bottom:16px">
      Es gibt keine öffentliche Registrierung. Ein Portalzugang entsteht nur
      hier. Die eingeladene Person setzt ihr Passwort selbst — Sie vergeben
      also nie ein Passwort für andere. Der Zugang ist unabhängig vom
      Schulungsbereich: Wer dort ein Konto hat, braucht hier trotzdem eine
      eigene Einladung.
    </p>
    <form class="formular" method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="aktion" value="einladen">

      <label for="email"><?= e(t('email')) ?></label>
      <input type="email" id="email" name="email" required maxlength="190">

      <label for="name"><?= e(t('name')) ?></label>
      <input type="text" id="name" name="name" maxlength="120">

      <label for="rolle"><?= e(t('rolle')) ?></label>
      <select id="rolle" name="rolle">
        <option value="mitarbeiter"><?= e(t('rolle_mitarbeiter')) ?></option>
        <option value="admin"><?= e(t('rolle_admin')) ?></option>
      </select>

      <button type="submit" class="knopf knopf--primaer"><?= e(t('einladen')) ?></button>
    </form>
  </div>
</section>

<?php
// ===========================================================================
// Quellen
// ===========================================================================
elseif ($bereich === 'quellen'):
    $quellen = db()->query('SELECT * FROM triz_quellen ORDER BY art, region, name')->fetchAll();
?>

<form method="post" style="margin-bottom:20px">
  <?= csrf_feld() ?>
  <input type="hidden" name="aktion" value="sammeln">
  <button type="submit" class="knopf knopf--primaer"><?= e(t('jetzt_sammeln')) ?></button>
  <span class="leise"><?= e(t('sammeln_laeuft')) ?></span>
</form>

<div class="tabelle-huelle">
  <table>
    <thead>
      <tr>
        <th><?= e(t('quelle')) ?></th>
        <th><?= e(t('quelle_art')) ?></th>
        <th><?= e(t('sprache')) ?> / <?= e(t('region')) ?></th>
        <th><?= e(t('letzter_lauf')) ?></th>
        <th><?= e(t('treffer')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($quellen as $q): ?>
        <tr>
          <td>
            <strong><?= e($q['name']) ?></strong><br>
            <span class="leise" style="word-break:break-all"><?= e(triz_kuerzen((string)$q['url'], 90)) ?></span>
          </td>
          <td><?= e($q['art'] === 'youtube' ? t('quelle_youtube') : t('quelle_rss')) ?></td>
          <td><?= e(strtoupper((string)$q['sprache'])) ?> / <?= e(t('region_' . $q['region'])) ?></td>
          <td>
            <?= e($q['letzter_lauf'] ? triz_datum($q['letzter_lauf'], true) : t('nie')) ?>
            <?php if ((string)$q['letzter_fehler'] !== ''): ?>
              <br><span style="color:var(--fehler)"><?= e((string)$q['letzter_fehler']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= (int)$q['treffer_gesamt'] ?></td>
          <td>
            <form method="post">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="quelle_umschalten">
              <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
              <button type="submit" class="knopf knopf--klein">
                <?= e((int)$q['aktiv'] === 1 ? t('aktiv') : t('inaktiv')) ?>
              </button>
            </form>
            <form method="post" onsubmit="return confirm('<?= e(t('loeschen')) ?>?');">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="quelle_loeschen">
              <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
              <button type="submit" class="knopf knopf--klein knopf--warnung"><?= e(t('loeschen')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<section class="abschnitt" style="margin-top:32px">
  <h2><?= e(t('quelle_neu')) ?></h2>
  <div class="karte">
    <p class="leise" style="margin-bottom:16px">
      Für YouTube genügt die Kanaladresse oder das @handle — die Feed-Adresse
      wird daraus einmalig ermittelt und gespeichert. Für Nachrichtenquellen
      wird die RSS- oder Atom-Adresse gebraucht, nicht die Adresse der
      Webseite.
    </p>
    <form class="formular" method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="aktion" value="quelle_neu">

      <label for="art"><?= e(t('quelle_art')) ?></label>
      <select id="art" name="art">
        <option value="rss"><?= e(t('quelle_rss')) ?></option>
        <option value="youtube"><?= e(t('quelle_youtube')) ?></option>
      </select>

      <label for="qname"><?= e(t('name')) ?></label>
      <input type="text" id="qname" name="name" required maxlength="160">

      <label for="url">URL</label>
      <input type="text" id="url" name="url" required maxlength="500"
             placeholder="https://… oder @kanalname">

      <label for="sprache_q"><?= e(t('sprache')) ?></label>
      <select id="sprache_q" name="sprache_q">
        <option value="de"><?= e(t('deutsch')) ?></option>
        <option value="ru"><?= e(t('russisch')) ?></option>
        <option value="en"><?= e(t('englisch')) ?></option>
      </select>

      <label for="region"><?= e(t('region')) ?></label>
      <select id="region" name="region">
        <option value="de"><?= e(t('region_de')) ?></option>
        <option value="ru"><?= e(t('region_ru')) ?></option>
        <option value="int"><?= e(t('region_int')) ?></option>
      </select>

      <label for="kategorie"><?= e(t('kategorie')) ?></label>
      <select id="kategorie" name="kategorie">
        <option value="0">–</option>
        <optgroup label="<?= e(t('nav_news')) ?>">
          <?php foreach (triz_kategorien('news') as $k): ?>
            <option value="<?= (int)$k['id'] ?>"><?= e(triz_kategorie_name($k)) ?></option>
          <?php endforeach; ?>
        </optgroup>
        <optgroup label="<?= e(t('nav_videos')) ?>">
          <?php foreach (triz_kategorien('video') as $k): ?>
            <option value="<?= (int)$k['id'] ?>"><?= e(triz_kategorie_name($k)) ?></option>
          <?php endforeach; ?>
        </optgroup>
      </select>

      <button type="submit" class="knopf knopf--primaer"><?= e(t('speichern')) ?></button>
    </form>
  </div>
</section>

<?php
// ===========================================================================
// Kategorien
// ===========================================================================
elseif ($bereich === 'kategorien'):
    $alle = db()->query('SELECT * FROM triz_kategorien ORDER BY bereich, sortierung, name_de')->fetchAll();
?>

<div class="tabelle-huelle">
  <table>
    <thead>
      <tr>
        <th><?= e(t('kategorie')) ?></th>
        <th><?= e(t('name')) ?> (DE)</th>
        <th><?= e(t('name')) ?> (RU)</th>
        <th>#</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($alle as $k): ?>
        <tr>
          <td>
            <span class="marke"><?= e($k['bereich']) ?></span><br>
            <span class="leise"><?= e($k['schluessel']) ?></span>
          </td>
          <td colspan="3">
            <form method="post" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="kategorie_speichern">
              <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
              <input type="text" name="name_de" value="<?= e($k['name_de']) ?>" style="width:200px">
              <input type="text" name="name_ru" value="<?= e($k['name_ru']) ?>" style="width:200px" lang="ru">
              <input type="number" name="sortierung" value="<?= (int)$k['sortierung'] ?>" style="width:80px">
              <button type="submit" class="knopf knopf--klein"><?= e(t('speichern')) ?></button>
            </form>
          </td>
          <td>
            <form method="post" onsubmit="return confirm('<?= e(t('loeschen')) ?>?');">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="kategorie_loeschen">
              <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
              <button type="submit" class="knopf knopf--klein knopf--warnung"><?= e(t('loeschen')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<section class="abschnitt" style="margin-top:32px">
  <h2><?= e(t('kategorien')) ?></h2>
  <div class="karte">
    <form class="formular" method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="aktion" value="kategorie_neu">

      <label for="kat_bereich"><?= e(t('kategorie')) ?></label>
      <select id="kat_bereich" name="kat_bereich">
        <option value="news"><?= e(t('nav_news')) ?></option>
        <option value="video"><?= e(t('nav_videos')) ?></option>
      </select>

      <label for="schluessel">Schlüssel</label>
      <input type="text" id="schluessel" name="schluessel" required maxlength="60"
             placeholder="nur-kleinbuchstaben-und-bindestriche">

      <label for="name_de"><?= e(t('name')) ?> (DE)</label>
      <input type="text" id="name_de" name="name_de" required maxlength="120">

      <label for="name_ru"><?= e(t('name')) ?> (RU)</label>
      <input type="text" id="name_ru" name="name_ru" maxlength="120" lang="ru">

      <label for="sortierung">#</label>
      <input type="number" id="sortierung" name="sortierung" value="100">

      <button type="submit" class="knopf knopf--primaer"><?= e(t('speichern')) ?></button>
    </form>
  </div>
</section>

<?php
// ===========================================================================
// Widerspruchsmatrix
// ===========================================================================
elseif ($bereich === 'matrix'):
    $gefuellt = (int)db()->query('SELECT COUNT(*) FROM triz_matrix')->fetchColumn();
?>

<div class="zahlen" style="margin-bottom:24px">
  <div class="zahl">
    <div class="zahl__wert"><?= $gefuellt ?></div>
    <div class="zahl__text">von 1521 Feldern</div>
  </div>
</div>

<div class="karte">
  <h2><?= e(t('matrix_import')) ?></h2>
  <p class="leise" style="margin:10px 0 16px"><?= e(t('matrix_import_hilfe')) ?></p>
  <p class="einmal-link" style="margin-bottom:16px"><code>1;10;8,15,29,34<br>1;15;2,8,29,34</code></p>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_feld() ?>
    <input type="hidden" name="aktion" value="matrix_import">
    <label for="csv">CSV</label>
    <input type="file" id="csv" name="csv" accept=".csv,text/csv,text/plain" required>
    <button type="submit" class="knopf knopf--primaer" style="margin-top:16px">
      <?= e(t('hochladen')) ?>
    </button>
  </form>
</div>

<?php if ($gefuellt > 0): ?>
  <form method="post" style="margin-top:16px" onsubmit="return confirm('<?= e(t('loeschen')) ?>?');">
    <?= csrf_feld() ?>
    <input type="hidden" name="aktion" value="matrix_leeren">
    <button type="submit" class="knopf knopf--klein knopf--warnung"><?= e(t('loeschen')) ?></button>
  </form>
<?php endif; ?>

<?php
// ===========================================================================
// Statistik
// ===========================================================================
elseif ($bereich === 'statistik'):
    $zahl = static fn(string $sql): int => (int)db()->query($sql)->fetchColumn();

    $kennzahlen = [
        'stat_benutzer'   => $zahl("SELECT COUNT(*) FROM triz_benutzer WHERE status = 'aktiv'"),
        'stat_beitraege'  => $zahl('SELECT COUNT(*) FROM triz_beitraege'),
        'stat_videos'     => $zahl('SELECT COUNT(*) FROM triz_videos'),
        'stat_dokumente'  => $zahl('SELECT COUNT(*) FROM triz_dokumente WHERE aktuell = 1'),
        'stat_anmeldungen'=> $zahl("SELECT COUNT(*) FROM triz_protokoll
                                    WHERE ereignis = 'anmeldung'
                                      AND zeitpunkt > (NOW() - INTERVAL 30 DAY)"),
    ];

    $woche = db()->query(
        'SELECT DATE(gefunden_am) AS tag, COUNT(*) AS anzahl
         FROM triz_beitraege
         WHERE gefunden_am > (NOW() - INTERVAL 7 DAY)
         GROUP BY DATE(gefunden_am) ORDER BY tag DESC'
    )->fetchAll();

    $top = db()->query(
        'SELECT titel, downloads FROM triz_dokumente
         WHERE downloads > 0 ORDER BY downloads DESC LIMIT 10'
    )->fetchAll();
?>

<div class="zahlen" style="margin-bottom:28px">
  <?php foreach ($kennzahlen as $schluessel => $wert): ?>
    <div class="zahl">
      <div class="zahl__wert"><?= (int)$wert ?></div>
      <div class="zahl__text"><?= e(t($schluessel)) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="raster raster--2">
  <section>
    <h2><?= e(t('stat_letzte_woche')) ?></h2>
    <div class="tabelle-huelle">
      <table>
        <tbody>
          <?php foreach ($woche as $z): ?>
            <tr>
              <td><?= e(triz_datum((string)$z['tag'])) ?></td>
              <td style="text-align:right"><strong><?= (int)$z['anzahl'] ?></strong></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($woche === []): ?>
            <tr><td class="leise"><?= e(t('noch_leer')) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section>
    <h2><?= e(t('stat_top_dokumente')) ?></h2>
    <div class="tabelle-huelle">
      <table>
        <tbody>
          <?php foreach ($top as $d): ?>
            <tr>
              <td><?= e((string)$d['titel']) ?></td>
              <td style="text-align:right"><strong><?= (int)$d['downloads'] ?></strong></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($top === []): ?>
            <tr><td class="leise"><?= e(t('noch_leer')) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php
// ===========================================================================
// Protokoll
// ===========================================================================
else:
    $eintraege = db()->query(
        'SELECT * FROM triz_protokoll ORDER BY zeitpunkt DESC LIMIT 200'
    )->fetchAll();
    $tage = (int)($CONFIG['aufbewahrung']['protokoll_tage'] ?? 90);
?>

<p class="leise" style="margin-bottom:16px">
  Die IP-Adresse steht nur als Prüfsumme im Protokoll (Pseudonymisierung nach
  Art. 32 DSGVO). Einträge werden nach <?= $tage ?> Tagen gelöscht.
</p>

<div class="tabelle-huelle">
  <table>
    <thead>
      <tr>
        <th><?= e(t('datum')) ?></th>
        <th>Ereignis</th>
        <th><?= e(t('email')) ?></th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($eintraege as $p): ?>
        <tr>
          <td style="white-space:nowrap"><?= e(triz_datum($p['zeitpunkt'], true)) ?></td>
          <td><span class="marke"><?= e($p['ereignis']) ?></span></td>
          <td><?= e($p['email']) ?></td>
          <td class="leise"><?= e($p['details']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($eintraege === []): ?>
        <tr><td colspan="4" class="leise"><?= e(t('noch_leer')) ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php endif;

triz_fuss();

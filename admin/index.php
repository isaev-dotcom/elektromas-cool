<?php
/**
 * Benutzerverwaltung.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';

sicherheits_header();
$ich = admin_verlangen();

$meldung_text = $_SESSION['admin_meldung'] ?? '';
$meldung_art  = $_SESSION['admin_meldung_art'] ?? 'ok';
unset($_SESSION['admin_meldung'], $_SESSION['admin_meldung_art']);

function admin_melden(string $text, string $art = 'ok'): never
{
    $_SESSION['admin_meldung'] = $text;
    $_SESSION['admin_meldung_art'] = $art;
    weiter_zu('/admin/');
}

if (ist_post()) {
    csrf_pruefen();

    $aktion = (string)($_POST['aktion'] ?? '');
    $ziel_id = (int)($_POST['id'] ?? 0);

    $stmt = db()->prepare('SELECT * FROM benutzer WHERE id = ?');
    $stmt->execute([$ziel_id]);
    $ziel = $stmt->fetch();

    if (!$ziel) {
        admin_melden('Benutzer nicht gefunden.', 'fehler');
    }

    // Sich selbst zu sperren oder zu löschen würde im schlimmsten Fall den
    // letzten Zugang zur Verwaltung kosten.
    if ((int)$ziel['id'] === (int)$ich['id'] && in_array($aktion, ['sperren', 'loeschen', 'rolle'], true)) {
        admin_melden('Das eigene Konto lässt sich hier nicht ändern.', 'fehler');
    }

    switch ($aktion) {
        case 'freigeben':
            $up = db()->prepare("UPDATE benutzer SET status = 'aktiv' WHERE id = ?");
            $up->execute([$ziel_id]);
            protokoll('konto_freigegeben', $ziel['email'], $ziel_id, 'durch ' . $ich['email']);
            admin_melden('Zugang freigegeben: ' . $ziel['email']);
            // no break — admin_melden beendet

        case 'sperren':
            $up = db()->prepare("UPDATE benutzer SET status = 'gesperrt' WHERE id = ?");
            $up->execute([$ziel_id]);
            protokoll('konto_gesperrt', $ziel['email'], $ziel_id, 'durch ' . $ich['email']);
            admin_melden('Zugang gesperrt: ' . $ziel['email']);

        case 'loeschen':
            // Vor dem Löschen protokollieren, danach ist die Zuordnung weg.
            protokoll('konto_geloescht', $ziel['email'], null, 'durch ' . $ich['email']);
            $del = db()->prepare('DELETE FROM benutzer WHERE id = ?');
            $del->execute([$ziel_id]);
            admin_melden('Zugang gelöscht: ' . $ziel['email']);

        case 'rolle':
            $neu = ($_POST['rolle'] ?? '') === 'admin' ? 'admin' : 'mitarbeiter';
            $up = db()->prepare('UPDATE benutzer SET rolle = ? WHERE id = ?');
            $up->execute([$neu, $ziel_id]);
            protokoll('rolle_geaendert', $ziel['email'], $ziel_id, $neu . ' durch ' . $ich['email']);
            admin_melden('Rolle geändert: ' . $ziel['email'] . ' ist jetzt ' . $neu . '.');

        case 'neu_einladen':
            [$klartext, $hash] = token_erzeugen();
            $stunden = (int)($CONFIG['sicherheit']['einladung_gueltig_stunden'] ?? 168);
            $bis = (new DateTimeImmutable("+{$stunden} hours"))->format('Y-m-d H:i:s');

            $alt = db()->prepare('UPDATE einladungen SET eingeloest_am = NOW()
                                  WHERE benutzer_id = ? AND eingeloest_am IS NULL');
            $alt->execute([$ziel_id]);

            $ins = db()->prepare(
                'INSERT INTO einladungen (benutzer_id, token_hash, gueltig_bis, erstellt_von)
                 VALUES (?, ?, ?, ?)'
            );
            $ins->execute([$ziel_id, $hash, $bis, (int)$ich['id']]);

            $link = $CONFIG['basis_url'] . '/konto/einladung.php?token=' . urlencode($klartext);
            $ok = mail_einladung($ziel['email'], (string)$ziel['name'], $link, $stunden);
            protokoll('einladung_erneut', $ziel['email'], $ziel_id, 'durch ' . $ich['email']);

            admin_melden(
                $ok ? 'Neue Einladung verschickt an ' . $ziel['email']
                    : 'Einladung angelegt, aber der Mailversand schlug fehl.',
                $ok ? 'ok' : 'fehler'
            );

        default:
            admin_melden('Unbekannte Aktion.', 'fehler');
    }
}

$benutzer = db()->query(
    'SELECT * FROM benutzer ORDER BY FIELD(status, "eingeladen", "aktiv", "gesperrt"), email'
)->fetchAll();

$anzahl = ['aktiv' => 0, 'eingeladen' => 0, 'gesperrt' => 0];
foreach ($benutzer as $b) {
    $anzahl[$b['status']]++;
}

seite_kopf('Benutzerverwaltung', 'breit');
?>
  <?php meldung($meldung_text, $meldung_art); ?>

  <p class="konto__lead">
    <?= (int)$anzahl['aktiv'] ?> aktiv &middot;
    <?= (int)$anzahl['eingeladen'] ?> eingeladen &middot;
    <?= (int)$anzahl['gesperrt'] ?> gesperrt
  </p>

  <p class="admin__aktionen">
    <a class="knopf knopf--primaer" href="/admin/einladen.php">Person einladen</a>
    <a class="knopf" href="/admin/protokoll.php">Protokoll ansehen</a>
  </p>

  <div class="tabelle-rahmen">
  <table class="tabelle">
    <thead>
      <tr>
        <th>Name / E-Mail</th>
        <th>Rolle</th>
        <th>Status</th>
        <th>Letzte Anmeldung</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($benutzer as $b): $selbst = (int)$b['id'] === (int)$ich['id']; ?>
      <tr>
        <td>
          <strong><?= e($b['name'] !== '' ? $b['name'] : '—') ?></strong>
          <?php if ($selbst): ?><span class="marke">Sie</span><?php endif; ?>
          <br><span class="leise"><?= e($b['email']) ?></span>
        </td>
        <td>
          <?php if ($selbst): ?>
            <?= e($b['rolle']) ?>
          <?php else: ?>
            <form method="post" class="inline">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="rolle">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <select name="rolle" onchange="this.form.submit()">
                <option value="mitarbeiter" <?= $b['rolle'] === 'mitarbeiter' ? 'selected' : '' ?>>Mitarbeiter</option>
                <option value="admin" <?= $b['rolle'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
              </select>
            </form>
          <?php endif; ?>
        </td>
        <td><span class="status status--<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
        <td class="leise">
          <?= $b['letzter_login'] ? e(date('d.m.Y H:i', strtotime($b['letzter_login']))) : '—' ?>
        </td>
        <td class="aktionen">
          <?php if (!$selbst): ?>
            <?php if ($b['status'] !== 'aktiv'): ?>
              <form method="post" class="inline">
                <?= csrf_feld() ?>
                <input type="hidden" name="aktion" value="freigeben">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button class="mini">Freigeben</button>
              </form>
            <?php endif; ?>
            <?php if ($b['status'] !== 'gesperrt'): ?>
              <form method="post" class="inline">
                <?= csrf_feld() ?>
                <input type="hidden" name="aktion" value="sperren">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button class="mini">Sperren</button>
              </form>
            <?php endif; ?>
            <form method="post" class="inline">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="neu_einladen">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button class="mini">Einladung neu</button>
            </form>
            <form method="post" class="inline"
                  onsubmit="return confirm('Zugang von <?= e($b['email']) ?> endgültig löschen?')">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="loeschen">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button class="mini mini--warnung">Löschen</button>
            </form>
          <?php else: ?>
            <span class="leise">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php
seite_fuss();

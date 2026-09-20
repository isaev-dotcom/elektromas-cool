<?php
/**
 * Zugänge des Gefährdungsbeurteilungs-Portals verwalten.
 *
 * Nur für Administratoren dieses Portals. Zugänge entstehen ausschließlich
 * durch eine Einladung von hier - es gibt keine Registrierung.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';
require_once PRIVAT_PFAD . '/lib/bg.php';

sicherheits_header();
$ich = bg_admin_verlangen();

$fehler = '';
$ok     = '';
$einmal_link    = '';
$versand_fehler = false;

/** Legt eine neue Einladung an und gibt den Link im Klartext zurück. */
function bg_einladung_anlegen(int $benutzer_id, int $erstellt_von, int $stunden): string
{
    global $CONFIG;

    [$klartext, $hash] = token_erzeugen();
    $bis = (new DateTimeImmutable("+{$stunden} hours"))->format('Y-m-d H:i:s');

    // Offene Einladungen entwerten: Sonst blieben mehrere gültige Links
    // gleichzeitig im Umlauf.
    $alt = db()->prepare(
        'UPDATE bg_einladungen SET gueltig_bis = NOW()
         WHERE benutzer_id = ? AND eingeloest_am IS NULL'
    );
    $alt->execute([$benutzer_id]);

    $ins = db()->prepare(
        'INSERT INTO bg_einladungen (benutzer_id, token_hash, gueltig_bis, erstellt_von)
         VALUES (?, ?, ?, ?)'
    );
    $ins->execute([$benutzer_id, $hash, $bis, $erstellt_von]);

    return $CONFIG['basis_url'] . '/bg/einladung.php?token=' . urlencode($klartext);
}

if (ist_post()) {
    csrf_pruefen();

    $aktion  = (string)($_POST['aktion'] ?? '');
    $stunden = (int)($CONFIG['sicherheit']['einladung_gueltig_stunden'] ?? 168);

    switch ($aktion) {
        case 'einladen': {
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $name  = trim((string)($_POST['name'] ?? ''));
            $rolle = (string)($_POST['rolle'] ?? 'mitarbeiter') === 'admin' ? 'admin' : 'mitarbeiter';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fehler = 'Bitte eine gültige E-Mail-Adresse angeben.';
                break;
            }

            $stmt = db()->prepare('SELECT id FROM bg_benutzer WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $fehler = 'Für diese Adresse gibt es bereits einen Zugang. '
                        . 'Nutzen Sie in der Liste „Neu einladen“.';
                break;
            }

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO bg_benutzer (email, name, rolle, status)
                     VALUES (?, ?, ?, 'eingeladen')"
                );
                $ins->execute([$email, mb_substr($name, 0, 120), $rolle]);
                $benutzer_id = (int)$pdo->lastInsertId();

                $link = bg_einladung_anlegen($benutzer_id, (int)$ich['id'], $stunden);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('BG-Einladung fehlgeschlagen: ' . $e->getMessage());
                $fehler = 'Der Zugang konnte nicht angelegt werden.';
                break;
            }

            bg_protokoll('einladung_erstellt', $email, $benutzer_id, 'durch ' . $ich['email']);

            // Den Link immer einmalig zeigen, auch nach erfolgreichem Versand:
            // Eine angenommene Mail kann im Spamordner landen. In der
            // Datenbank steht nur sein Hash, später ist er nicht mehr abrufbar.
            $einmal_link = $link;
            if (bg_mail_einladung($email, $name, $link, $stunden)) {
                $ok = 'Einladung verschickt an ' . $email . '. Kommt sie nicht an, '
                    . 'geben Sie den folgenden Link persönlich weiter:';
            } else {
                $ok = 'Zugang angelegt, aber der Mailversand schlug fehl. '
                    . 'Geben Sie den folgenden Link persönlich weiter:';
                $versand_fehler = true;
            }
            break;
        }

        case 'neu_einladen': {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare('SELECT * FROM bg_benutzer WHERE id = ?');
            $stmt->execute([$id]);
            $b = $stmt->fetch();
            if (!$b) {
                $fehler = 'Zugang nicht gefunden.';
                break;
            }

            $link = bg_einladung_anlegen($id, (int)$ich['id'], $stunden);
            bg_protokoll('einladung_erneuert', (string)$b['email'], $id, 'durch ' . $ich['email']);

            $einmal_link = $link;
            if (bg_mail_einladung((string)$b['email'], (string)$b['name'], $link, $stunden)) {
                $ok = 'Neue Einladung verschickt an ' . $b['email'] . '. Kommt sie nicht an, '
                    . 'geben Sie den folgenden Link persönlich weiter:';
            } else {
                $ok = 'Neue Einladung erstellt, Mailversand fehlgeschlagen:';
                $versand_fehler = true;
            }
            break;
        }

        case 'status': {
            $id  = (int)($_POST['id'] ?? 0);
            $neu = (string)($_POST['neu'] ?? '');
            if (!in_array($neu, ['aktiv', 'gesperrt'], true)) {
                $fehler = 'Unbekannter Status.';
                break;
            }
            if ($id === (int)$ich['id']) {
                $fehler = 'Das eigene Konto lässt sich hier nicht ändern.';
                break;
            }
            $up = db()->prepare('UPDATE bg_benutzer SET status = ? WHERE id = ?');
            $up->execute([$neu, $id]);
            $ok = $neu === 'aktiv' ? 'Zugang freigegeben.' : 'Zugang gesperrt.';
            bg_protokoll($neu === 'aktiv' ? 'konto_freigegeben' : 'konto_gesperrt', '', $id, 'durch ' . $ich['email']);
            break;
        }

        case 'loeschen': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$ich['id']) {
                $fehler = 'Das eigene Konto lässt sich hier nicht löschen.';
                break;
            }
            $stmt = db()->prepare('SELECT email FROM bg_benutzer WHERE id = ?');
            $stmt->execute([$id]);
            $email = (string)($stmt->fetchColumn() ?: '');

            $del = db()->prepare('DELETE FROM bg_benutzer WHERE id = ?');
            $del->execute([$id]);
            $ok = 'Zugang gelöscht: ' . $email;
            bg_protokoll('konto_geloescht', $email, null, 'durch ' . $ich['email']);
            break;
        }

        case 'rolle': {
            $id  = (int)($_POST['id'] ?? 0);
            $neu = (string)($_POST['neu'] ?? '') === 'admin' ? 'admin' : 'mitarbeiter';
            if ($id === (int)$ich['id']) {
                $fehler = 'Die eigene Rolle lässt sich hier nicht ändern.';
                break;
            }
            $up = db()->prepare('UPDATE bg_benutzer SET rolle = ? WHERE id = ?');
            $up->execute([$neu, $id]);
            $ok = 'Rolle geändert.';
            bg_protokoll('rolle_geaendert', '', $id, $neu . ' durch ' . $ich['email']);
            break;
        }
    }
}

$benutzer = db()->query(
    'SELECT * FROM bg_benutzer ORDER BY FIELD(status, "eingeladen", "aktiv", "gesperrt"), email'
)->fetchAll();

seite_kopf('Zugänge Gefährdungsbeurteilung', 'breit');
?>
  <?php meldung($fehler, 'fehler'); ?>
  <?php meldung($ok, $versand_fehler ? 'hinweis' : 'ok'); ?>

  <?php if ($einmal_link !== ''): ?>
    <p class="einmal-link"><code><?= e($einmal_link) ?></code></p>
    <p class="konto__hinweis">
      Dieser Link wird nur jetzt angezeigt. In der Datenbank steht lediglich
      seine Prüfsumme, er lässt sich später nicht wiederherstellen.
    </p>
  <?php endif; ?>

  <p class="konto__lead">
    Diese Zugänge gelten nur für die Gefährdungsbeurteilungen. Die Zugänge für
    Schulungen und das TRIZ-Portal sind davon getrennt.
  </p>

  <div class="tabelle-rahmen">
  <table class="tabelle">
    <thead>
      <tr>
        <th>E-Mail</th><th>Name</th><th>Rolle</th><th>Status</th><th>Zuletzt angemeldet</th><th>Aktion</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($benutzer as $b): ?>
      <tr>
        <td><?= e((string)$b['email']) ?></td>
        <td><?= e((string)$b['name']) ?></td>
        <td>
          <?php if ((int)$b['id'] === (int)$ich['id']): ?>
            <?= e((string)$b['rolle']) ?>
          <?php else: ?>
            <form method="post" class="inline">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="rolle">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="neu" value="<?= $b['rolle'] === 'admin' ? 'mitarbeiter' : 'admin' ?>">
              <button type="submit" class="mini">
                <?= e((string)$b['rolle']) ?> &rarr; <?= $b['rolle'] === 'admin' ? 'mitarbeiter' : 'admin' ?>
              </button>
            </form>
          <?php endif; ?>
        </td>
        <td><?= e((string)$b['status']) ?></td>
        <td><?= e($b['letzter_login'] ? date('d.m.Y H:i', strtotime((string)$b['letzter_login'])) : '–') ?></td>
        <td class="aktionen">
          <?php if ((int)$b['id'] !== (int)$ich['id']): ?>
            <form method="post" class="inline">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="neu_einladen">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button type="submit" class="mini">Neu einladen</button>
            </form>
            <form method="post" class="inline">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="status">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="neu" value="<?= $b['status'] === 'gesperrt' ? 'aktiv' : 'gesperrt' ?>">
              <button type="submit" class="mini">
                <?= $b['status'] === 'gesperrt' ? 'Freigeben' : 'Sperren' ?>
              </button>
            </form>
            <form method="post" class="inline"
                  onsubmit="return confirm('Zugang <?= e((string)$b['email']) ?> endgültig löschen?')">
              <?= csrf_feld() ?>
              <input type="hidden" name="aktion" value="loeschen">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button type="submit" class="mini">Löschen</button>
            </form>
          <?php else: ?>
            <span class="leise">eigenes Konto</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <h2>Person einladen</h2>
  <form method="post">
    <?= csrf_feld() ?>
    <input type="hidden" name="aktion" value="einladen">

    <label for="email">E-Mail-Adresse</label>
    <input type="email" id="email" name="email" required maxlength="190">

    <label for="name">Name (optional)</label>
    <input type="text" id="name" name="name" maxlength="120">

    <label for="rolle">Rolle</label>
    <select id="rolle" name="rolle">
      <option value="mitarbeiter">Mitarbeiter — darf Gefährdungsbeurteilungen erstellen</option>
      <option value="admin">Administrator — darf zusätzlich Zugänge verwalten</option>
    </select>

    <button type="submit" class="knopf knopf--primaer">Einladung verschicken</button>
  </form>

  <p class="konto__zusatz"><a href="/bg/">Zurück zum Portal</a></p>
<?php
seite_fuss();

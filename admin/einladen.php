<?php
/**
 * Person einladen — legt das Konto an und verschickt den Einladungslink.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';

sicherheits_header();
$ich = admin_verlangen();

$fehler = '';
$ok_text = '';
$manueller_link = '';

if (ist_post()) {
    csrf_pruefen();

    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $name  = trim((string)($_POST['name'] ?? ''));
    $rolle = ($_POST['rolle'] ?? '') === 'admin' ? 'admin' : 'mitarbeiter';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    } elseif (mb_strlen($email) > 190) {
        $fehler = 'Die E-Mail-Adresse ist zu lang.';
    } else {
        $stmt = db()->prepare('SELECT id, status FROM benutzer WHERE email = ?');
        $stmt->execute([$email]);
        $vorhanden = $stmt->fetch();

        if ($vorhanden) {
            $fehler = 'Für diese Adresse gibt es bereits einen Zugang (Status: '
                . $vorhanden['status'] . '). Nutzen Sie in der Übersicht '
                . '"Einladung neu", um erneut einzuladen.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO benutzer (email, name, rolle, status)
                     VALUES (?, ?, ?, 'eingeladen')"
                );
                $ins->execute([$email, mb_substr($name, 0, 120), $rolle]);
                $benutzer_id = (int)$pdo->lastInsertId();

                [$klartext, $hash] = token_erzeugen();
                $stunden = (int)($CONFIG['sicherheit']['einladung_gueltig_stunden'] ?? 168);
                $bis = (new DateTimeImmutable("+{$stunden} hours"))->format('Y-m-d H:i:s');

                $ins2 = $pdo->prepare(
                    'INSERT INTO einladungen (benutzer_id, token_hash, gueltig_bis, erstellt_von)
                     VALUES (?, ?, ?, ?)'
                );
                $ins2->execute([$benutzer_id, $hash, $bis, (int)$ich['id']]);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('Einladung fehlgeschlagen: ' . $e->getMessage());
                $fehler = 'Der Zugang konnte nicht angelegt werden.';
            }

            if ($fehler === '') {
                $link = $CONFIG['basis_url'] . '/konto/einladung.php?token=' . urlencode($klartext);
                $versandt = mail_einladung($email, $name, $link, $stunden);

                protokoll('einladung_erstellt', $email, $benutzer_id, 'durch ' . $ich['email']);

                if ($versandt) {
                    $ok_text = 'Einladung verschickt an ' . $email . '.';
                } else {
                    // Der Zugang existiert, nur die Mail ging nicht raus.
                    // Den Link hier einmalig anzeigen, damit die Einladung
                    // nicht verloren ist - er ist danach nicht mehr abrufbar.
                    $ok_text = 'Zugang angelegt, aber der Mailversand schlug fehl. '
                             . 'Geben Sie den folgenden Link persönlich weiter:';
                    $manueller_link = $link;
                }
            }
        }
    }
}

seite_kopf('Person einladen', 'breit');
?>
  <?php meldung($fehler, 'fehler'); ?>
  <?php meldung($ok_text, $manueller_link !== '' ? 'hinweis' : 'ok'); ?>

  <?php if ($manueller_link !== ''): ?>
    <p class="einmal-link"><code><?= e($manueller_link) ?></code></p>
    <p class="konto__hinweis">
      Dieser Link wird nur jetzt angezeigt. In der Datenbank steht lediglich
      seine Prüfsumme, er lässt sich später nicht wiederherstellen.
    </p>
  <?php endif; ?>

  <p class="konto__lead">
    Es gibt keine öffentliche Registrierung. Ein Zugang entsteht nur hier.
    Die eingeladene Person erhält eine E-Mail mit einem Link, über den sie ihr
    Passwort selbst setzt — Sie vergeben also nie ein Passwort für andere.
  </p>

  <form method="post">
    <?= csrf_feld() ?>

    <label for="email">E-Mail-Adresse</label>
    <input type="email" id="email" name="email" required autofocus maxlength="190">

    <label for="name">Name (optional)</label>
    <input type="text" id="name" name="name" maxlength="120">

    <label for="rolle">Rolle</label>
    <select id="rolle" name="rolle">
      <option value="mitarbeiter">Mitarbeiter — darf Schulungen ansehen</option>
      <option value="admin">Administrator — darf zusätzlich Zugänge verwalten</option>
    </select>

    <button type="submit" class="knopf knopf--primaer">Einladung verschicken</button>
  </form>

  <p class="konto__zusatz"><a href="/admin/">Zurück zur Übersicht</a></p>
<?php
seite_fuss();

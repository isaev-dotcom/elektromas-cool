<?php
/**
 * Einladung annehmen — Passwort setzen und Konto aktivieren.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';

sicherheits_header();
sitzung_starten();

$token    = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$fehler   = '';
$erledigt = false;
$eintrag  = null;

if ($token !== '') {
    $stmt = db()->prepare(
        'SELECT e.*, b.email, b.name, b.status
         FROM einladungen e
         JOIN benutzer b ON b.id = e.benutzer_id
         WHERE e.token_hash = ? AND e.eingeloest_am IS NULL AND e.gueltig_bis > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    $eintrag = $stmt->fetch() ?: null;
}

if ($eintrag === null) {
    seite_kopf('Einladung ungültig');
    ?>
    <p class="meldung meldung--fehler">
      Diese Einladung ist abgelaufen oder wurde bereits eingelöst.
    </p>
    <p class="konto__lead">
      Bitte wenden Sie sich an
      <a href="mailto:isaev@elektromas.de">isaev@elektromas.de</a>,
      dann wird Ihnen eine neue Einladung geschickt.
    </p>
    <?php
    seite_fuss();
    exit;
}

if (ist_post()) {
    csrf_pruefen();

    $name = trim((string)($_POST['name'] ?? ''));
    $p1   = (string)($_POST['passwort'] ?? '');
    $p2   = (string)($_POST['passwort2'] ?? '');

    if ($name === '') {
        $fehler = 'Bitte geben Sie Ihren Namen an.';
    } elseif ($p1 !== $p2) {
        $fehler = 'Die beiden Passwörter stimmen nicht überein.';
    } elseif ($m = passwort_pruefen($p1)) {
        $fehler = $m;
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare(
                "UPDATE benutzer
                 SET passwort_hash = ?, name = ?, status = 'aktiv', aktiviert_am = NOW()
                 WHERE id = ?"
            );
            $up->execute([passwort_hashen($p1), mb_substr($name, 0, 120), (int)$eintrag['benutzer_id']]);

            $mark = $pdo->prepare('UPDATE einladungen SET eingeloest_am = NOW() WHERE id = ?');
            $mark->execute([(int)$eintrag['id']]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Einladung einlösen fehlgeschlagen: ' . $e->getMessage());
            $fehler = 'Der Zugang konnte nicht eingerichtet werden. Bitte später erneut versuchen.';
        }

        if ($fehler === '') {
            protokoll('einladung_eingeloest', $eintrag['email'], (int)$eintrag['benutzer_id'], $name);

            mail_admin_info(
                'Neuer Zugang eingerichtet',
                "Ein eingeladener Zugang wurde soeben aktiviert.\n\n"
                . "Name:  {$name}\n"
                . "E-Mail: {$eintrag['email']}\n"
                . 'Zeit:  ' . date('d.m.Y H:i') . "\n\n"
                . "Übersicht: {$CONFIG['basis_url']}/admin/\n"
            );

            $erledigt = true;
        }
    }
}

seite_kopf('Zugang einrichten');

if ($erledigt) {
    ?>
    <p class="meldung meldung--ok">Ihr Zugang ist eingerichtet.</p>
    <p class="konto__zusatz"><a href="/konto/login.php">Jetzt anmelden</a></p>
    <?php
} else {
    $min = (int)($CONFIG['sicherheit']['mindest_passwortlaenge'] ?? 12);
    ?>
    <p class="konto__lead">
      Willkommen. Für <strong><?= e($eintrag['email']) ?></strong> wurde ein
      Zugang zum Schulungsbereich angelegt. Vergeben Sie hier Ihr Passwort.
    </p>

    <?php meldung($fehler, 'fehler'); ?>

    <form method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <label for="name">Vor- und Nachname</label>
      <input type="text" id="name" name="name" required autofocus maxlength="120"
             autocomplete="name" value="<?= e($eintrag['name'] ?? '') ?>">

      <label for="passwort">Passwort</label>
      <input type="password" id="passwort" name="passwort" required
             minlength="<?= $min ?>" autocomplete="new-password">

      <label for="passwort2">Passwort wiederholen</label>
      <input type="password" id="passwort2" name="passwort2" required
             minlength="<?= $min ?>" autocomplete="new-password">

      <p class="konto__hinweis">
        Mindestens <?= $min ?> Zeichen. Ein ganzer Satz ist sicherer und
        leichter zu merken als eine kurze Folge aus Sonderzeichen.
      </p>

      <button type="submit" class="knopf knopf--primaer">Zugang einrichten</button>
    </form>
    <?php
}

seite_fuss();

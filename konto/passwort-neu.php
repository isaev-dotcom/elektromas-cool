<?php
/**
 * Neues Passwort setzen — über den Link aus der Reset-E-Mail.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';

sicherheits_header();
sitzung_starten();

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$fehler = '';
$erledigt = false;
$eintrag = null;

if ($token !== '') {
    $stmt = db()->prepare(
        'SELECT r.*, b.email
         FROM passwort_resets r
         JOIN benutzer b ON b.id = r.benutzer_id
         WHERE r.token_hash = ? AND r.benutzt_am IS NULL AND r.gueltig_bis > NOW()'
    );
    // In der Datenbank steht nur der Hash - siehe token_erzeugen().
    $stmt->execute([hash('sha256', $token)]);
    $eintrag = $stmt->fetch() ?: null;
}

if ($eintrag === null) {
    seite_kopf('Link ungültig');
    ?>
    <p class="meldung meldung--fehler">
      Dieser Link ist abgelaufen oder wurde bereits verwendet.
    </p>
    <p class="konto__lead">
      Aus Sicherheitsgründen gilt jeder Link nur einmal und nur für kurze Zeit.
      Fordern Sie einfach einen neuen an.
    </p>
    <p class="konto__zusatz">
      <a href="/konto/passwort-vergessen.php">Neuen Link anfordern</a>
    </p>
    <?php
    seite_fuss();
    exit;
}

if (ist_post()) {
    csrf_pruefen();

    $p1 = (string)($_POST['passwort'] ?? '');
    $p2 = (string)($_POST['passwort2'] ?? '');

    if ($p1 !== $p2) {
        $fehler = 'Die beiden Passwörter stimmen nicht überein.';
    } elseif ($meldung = passwort_pruefen($p1)) {
        $fehler = $meldung;
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare('UPDATE benutzer SET passwort_hash = ? WHERE id = ?');
            $up->execute([passwort_hashen($p1), (int)$eintrag['benutzer_id']]);

            $mark = $pdo->prepare('UPDATE passwort_resets SET benutzt_am = NOW() WHERE id = ?');
            $mark->execute([(int)$eintrag['id']]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Passwortänderung fehlgeschlagen: ' . $e->getMessage());
            $fehler = 'Das Passwort konnte nicht gespeichert werden. Bitte später erneut versuchen.';
        }

        if ($fehler === '') {
            protokoll('passwort_geaendert', $eintrag['email'], (int)$eintrag['benutzer_id']);
            // Laufende Sitzungen dieses Kontos sollen nicht weiterlaufen.
            sitzung_beenden();
            session_start();
            $erledigt = true;
        }
    }
}

seite_kopf('Neues Passwort');

if ($erledigt) {
    ?>
    <p class="meldung meldung--ok">Ihr Passwort wurde geändert.</p>
    <p class="konto__zusatz"><a href="/konto/login.php">Jetzt anmelden</a></p>
    <?php
} else {
    $min = (int)($CONFIG['sicherheit']['mindest_passwortlaenge'] ?? 12);
    ?>
    <p class="konto__lead">
      Vergeben Sie ein neues Passwort für <strong><?= e($eintrag['email']) ?></strong>.
    </p>

    <?php meldung($fehler, 'fehler'); ?>

    <form method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <label for="passwort">Neues Passwort</label>
      <input type="password" id="passwort" name="passwort" required autofocus
             minlength="<?= $min ?>" autocomplete="new-password">

      <label for="passwort2">Passwort wiederholen</label>
      <input type="password" id="passwort2" name="passwort2" required
             minlength="<?= $min ?>" autocomplete="new-password">

      <p class="konto__hinweis">
        Mindestens <?= $min ?> Zeichen. Ein ganzer Satz ist sicherer und
        leichter zu merken als eine kurze Folge aus Sonderzeichen.
      </p>

      <button type="submit" class="knopf knopf--primaer">Passwort speichern</button>
    </form>
    <?php
}

seite_fuss();

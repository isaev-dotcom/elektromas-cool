<?php
/**
 * Passwort zurücksetzen — Schritt 2: neues Passwort vergeben.
 *
 * Der Token gilt einmal und läuft ab. Nach dem Setzen wird er als benutzt
 * markiert, in derselben Transaktion wie die Passwortänderung: Bricht eines
 * ab, bleibt auch das andere ungeschehen.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';
require_once PRIVAT_PFAD . '/lib/bg.php';

sicherheits_header();
sitzung_starten();

$token    = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$fehler   = '';
$erledigt = false;
$eintrag  = null;

if ($token !== '') {
    $stmt = db()->prepare(
        'SELECT r.*, b.email
         FROM bg_resets r
         JOIN bg_benutzer b ON b.id = r.benutzer_id
         WHERE r.token_hash = ? AND r.benutzt_am IS NULL AND r.gueltig_bis > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    $eintrag = $stmt->fetch() ?: null;
}

if ($eintrag === null) {
    seite_kopf('Neues Passwort');
    meldung('Dieser Link ist ungültig, abgelaufen oder bereits benutzt.', 'fehler');
    ?>
    <p class="konto__zusatz">
      <a href="/bg/passwort-vergessen.php">Neuen Link anfordern</a>
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
    } elseif ($m = passwort_pruefen($p1)) {
        $fehler = $m;
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare('UPDATE bg_benutzer SET passwort_hash = ? WHERE id = ?');
            $up->execute([passwort_hashen($p1), (int)$eintrag['benutzer_id']]);

            $mark = $pdo->prepare('UPDATE bg_resets SET benutzt_am = NOW() WHERE id = ?');
            $mark->execute([(int)$eintrag['id']]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('BG-Passwort zurücksetzen fehlgeschlagen: ' . $e->getMessage());
            $fehler = 'Das Passwort konnte nicht geändert werden. Bitte später erneut versuchen.';
        }

        if ($fehler === '') {
            bg_protokoll('passwort_geaendert', (string)$eintrag['email'], (int)$eintrag['benutzer_id']);
            // Offene Sitzungen dieses Kontos in diesem Browser beenden -
            // wer das Passwort ändert, will in der Regel genau das.
            bg_abmelden();
            $erledigt = true;
        }
    }
}

seite_kopf('Neues Passwort');

if ($erledigt) {
    meldung('Ihr Passwort ist geändert. Sie können sich jetzt anmelden.', 'ok');
    ?>
    <p class="konto__zusatz">
      <a class="knopf knopf--primaer" href="/bg/login.php">Jetzt anmelden</a>
    </p>
    <?php
} else {
    $min = (int)($CONFIG['sicherheit']['mindest_passwortlaenge'] ?? 12);
    ?>
    <p class="konto__lead">
      Neues Passwort für <strong><?= e((string)$eintrag['email']) ?></strong>.
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

      <p class="konto__hinweis">Mindestens <?= $min ?> Zeichen.</p>

      <button type="submit" class="knopf knopf--primaer">Passwort speichern</button>
    </form>
    <?php
}

seite_fuss();

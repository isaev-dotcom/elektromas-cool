<?php
/**
 * Passwort zurücksetzen — Schritt 2: neues Passwort vergeben.
 *
 * Der Token gilt einmal und läuft ab. Nach dem Setzen wird er als benutzt
 * markiert, in derselben Transaktion wie die Passwortänderung: Bricht eines
 * ab, bleibt auch das andere ungeschehen.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$token    = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$fehler   = '';
$erledigt = false;
$eintrag  = null;

if ($token !== '') {
    $stmt = db()->prepare(
        'SELECT r.*, b.email
         FROM triz_resets r
         JOIN triz_benutzer b ON b.id = r.benutzer_id
         WHERE r.token_hash = ? AND r.benutzt_am IS NULL AND r.gueltig_bis > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    $eintrag = $stmt->fetch() ?: null;
}

if ($eintrag === null) {
    triz_kopf(t('reset_neu'), '', true);
    triz_meldung(t('reset_ungueltig'), 'fehler');
    ?>
    <p class="karte-mitte__zusatz">
      <a href="/TRIZ/passwort-vergessen.php"><?= e(t('reset_titel')) ?></a>
    </p>
    <?php
    triz_fuss(true);
    exit;
}

if (ist_post()) {
    csrf_pruefen();

    $p1 = (string)($_POST['passwort'] ?? '');
    $p2 = (string)($_POST['passwort2'] ?? '');

    if ($p1 !== $p2) {
        $fehler = t('passwort_ungleich');
    } elseif ($m = passwort_pruefen($p1)) {
        $fehler = $m;
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare('UPDATE triz_benutzer SET passwort_hash = ? WHERE id = ?');
            $up->execute([passwort_hashen($p1), (int)$eintrag['benutzer_id']]);

            $mark = $pdo->prepare('UPDATE triz_resets SET benutzt_am = NOW() WHERE id = ?');
            $mark->execute([(int)$eintrag['id']]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('TRIZ-Passwort setzen fehlgeschlagen: ' . $e->getMessage());
            $fehler = 'Das Passwort konnte nicht geändert werden. Bitte später erneut versuchen.';
        }

        if ($fehler === '') {
            // Offene Fehlversuche zurücksetzen, sonst sperrt die Bremse den
            // Zugang aus, den man gerade wiederhergestellt hat.
            triz_versuche_loeschen((string)$eintrag['email']);
            triz_protokoll('passwort_geaendert', (string)$eintrag['email'], (int)$eintrag['benutzer_id']);
            $erledigt = true;
        }
    }
}

triz_kopf(t('reset_neu'), '', true);

if ($erledigt) {
    triz_meldung(t('reset_fertig'), 'ok');
    ?>
    <p class="karte-mitte__zusatz">
      <a class="knopf knopf--primaer" href="/TRIZ/login.php"><?= e(t('jetzt_anmelden')) ?></a>
    </p>
    <?php
} else {
    $min = (int)($CONFIG['sicherheit']['mindest_passwortlaenge'] ?? 12);
    ?>
    <p class="karte-mitte__lead"><?= e($eintrag['email']) ?></p>

    <?php triz_meldung($fehler, 'fehler'); ?>

    <form class="formular" method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <label for="passwort"><?= e(t('passwort')) ?></label>
      <input type="password" id="passwort" name="passwort" required autofocus
             minlength="<?= $min ?>" autocomplete="new-password">

      <label for="passwort2"><?= e(t('passwort_wdh')) ?></label>
      <input type="password" id="passwort2" name="passwort2" required
             minlength="<?= $min ?>" autocomplete="new-password">

      <p class="leise" style="margin-top:12px"><?= e(t('passwort_regel', $min)) ?></p>

      <button type="submit" class="knopf knopf--primaer"><?= e(t('speichern')) ?></button>
    </form>
    <?php
}

triz_fuss(true);

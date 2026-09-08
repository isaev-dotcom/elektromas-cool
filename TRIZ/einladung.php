<?php
/**
 * Einladung annehmen — Passwort setzen und Portalzugang aktivieren.
 *
 * Es gibt keine öffentliche Registrierung. Ein Konto entsteht ausschließlich
 * dadurch, dass ein Administrator in der Verwaltung eine Einladung ausspricht.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$token    = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$fehler   = '';
$erledigt = false;
$eintrag  = null;

if ($token !== '') {
    $stmt = db()->prepare(
        'SELECT e.*, b.email, b.name, b.status
         FROM triz_einladungen e
         JOIN triz_benutzer b ON b.id = e.benutzer_id
         WHERE e.token_hash = ? AND e.eingeloest_am IS NULL AND e.gueltig_bis > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    $eintrag = $stmt->fetch() ?: null;
}

if ($eintrag === null) {
    triz_kopf(t('einladung_titel'), '', true);
    triz_meldung(t('einladung_ungueltig'), 'fehler');
    ?>
    <p class="karte-mitte__lead">
      <a href="mailto:isaev@elektromas.de">isaev@elektromas.de</a>
    </p>
    <?php
    triz_fuss(true);
    exit;
}

if (ist_post()) {
    csrf_pruefen();

    $name = trim((string)($_POST['name'] ?? ''));
    $p1   = (string)($_POST['passwort'] ?? '');
    $p2   = (string)($_POST['passwort2'] ?? '');

    if ($name === '') {
        $fehler = t('name_fehlt');
    } elseif ($p1 !== $p2) {
        $fehler = t('passwort_ungleich');
    } elseif ($m = passwort_pruefen($p1)) {
        $fehler = $m;
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare(
                "UPDATE triz_benutzer
                 SET passwort_hash = ?, name = ?, sprache = ?, status = 'aktiv', aktiviert_am = NOW()
                 WHERE id = ?"
            );
            $up->execute([
                passwort_hashen($p1),
                mb_substr($name, 0, 120),
                triz_sprache(),
                (int)$eintrag['benutzer_id'],
            ]);

            $mark = $pdo->prepare('UPDATE triz_einladungen SET eingeloest_am = NOW() WHERE id = ?');
            $mark->execute([(int)$eintrag['id']]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('TRIZ-Einladung einlösen fehlgeschlagen: ' . $e->getMessage());
            $fehler = 'Der Zugang konnte nicht eingerichtet werden. Bitte später erneut versuchen.';
        }

        if ($fehler === '') {
            triz_protokoll('einladung_eingeloest', (string)$eintrag['email'], (int)$eintrag['benutzer_id'], $name);

            mail_admin_info(
                'Neuer Zugang im TRIZ-Portal eingerichtet',
                "Ein eingeladener Portalzugang wurde soeben aktiviert.\n\n"
                . "Name:   {$name}\n"
                . "E-Mail: {$eintrag['email']}\n"
                . 'Zeit:   ' . date('d.m.Y H:i') . "\n\n"
                . "Verwaltung: {$CONFIG['basis_url']}/TRIZ/verwaltung.php\n"
            );

            $erledigt = true;
        }
    }
}

triz_kopf(t('einladung_titel'), '', true);

if ($erledigt) {
    triz_meldung(t('einladung_fertig'), 'ok');
    ?>
    <p class="karte-mitte__zusatz">
      <a class="knopf knopf--primaer" href="/TRIZ/login.php"><?= e(t('jetzt_anmelden')) ?></a>
    </p>
    <?php
} else {
    $min = (int)($CONFIG['sicherheit']['mindest_passwortlaenge'] ?? 12);
    ?>
    <p class="karte-mitte__lead"><?= e(t('einladung_lead', $eintrag['email'])) ?></p>

    <?php triz_meldung($fehler, 'fehler'); ?>

    <form class="formular" method="post">
      <?= csrf_feld() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <label for="name"><?= e(t('name')) ?></label>
      <input type="text" id="name" name="name" required autofocus maxlength="120"
             autocomplete="name" value="<?= e((string)($eintrag['name'] ?? '')) ?>">

      <label for="passwort"><?= e(t('passwort')) ?></label>
      <input type="password" id="passwort" name="passwort" required
             minlength="<?= $min ?>" autocomplete="new-password">

      <label for="passwort2"><?= e(t('passwort_wdh')) ?></label>
      <input type="password" id="passwort2" name="passwort2" required
             minlength="<?= $min ?>" autocomplete="new-password">

      <p class="leise" style="margin-top:12px"><?= e(t('passwort_regel', $min)) ?></p>

      <button type="submit" class="knopf knopf--primaer"><?= e(t('einladung_titel')) ?></button>
    </form>
    <?php
}

triz_fuss(true);

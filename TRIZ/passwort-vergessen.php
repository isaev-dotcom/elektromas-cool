<?php
/**
 * Passwort zurücksetzen — Schritt 1: Link anfordern.
 *
 * Die Rückmeldung ist immer dieselbe, ob die Adresse bekannt ist oder nicht.
 * Andernfalls ließe sich hier durchprobieren, wer im Portal ein Konto hat.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

$fehler   = '';
$erledigt = false;

if (ist_post()) {
    csrf_pruefen();

    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler = t('login_leer');
    } elseif (triz_anmeldung_gesperrt($email)) {
        // Dieselbe Bremse wie bei der Anmeldung: Sonst wäre dieses Formular
        // der bequeme Weg, unbegrenzt Mails an eine Adresse auszulösen.
        $erledigt = true;
        triz_protokoll('reset_gesperrt', $email);
    } else {
        $stmt = db()->prepare("SELECT * FROM triz_benutzer WHERE email = ? AND status = 'aktiv'");
        $stmt->execute([$email]);
        $benutzer = $stmt->fetch();

        if ($benutzer) {
            [$klartext, $hash] = token_erzeugen();
            $minuten = (int)($CONFIG['sicherheit']['reset_gueltig_minuten'] ?? 60);
            $bis = (new DateTimeImmutable("+{$minuten} minutes"))->format('Y-m-d H:i:s');

            $ins = db()->prepare(
                'INSERT INTO triz_resets (benutzer_id, token_hash, gueltig_bis) VALUES (?, ?, ?)'
            );
            $ins->execute([(int)$benutzer['id'], $hash, $bis]);

            $link = $CONFIG['basis_url'] . '/TRIZ/passwort-neu.php?token=' . urlencode($klartext);
            triz_mail_reset($email, $link, $minuten);
            triz_protokoll('reset_angefordert', $email, (int)$benutzer['id']);
        } else {
            triz_protokoll('reset_unbekannt', $email);
        }

        $erledigt = true;
        triz_versuch_merken($email, false);
    }
}

triz_kopf(t('reset_titel'), '', true);

if ($erledigt) {
    triz_meldung(t('reset_gesendet'), 'ok');
    ?>
    <p class="karte-mitte__zusatz"><a href="/TRIZ/login.php"><?= e(t('zurueck')) ?></a></p>
    <?php
} else {
    ?>
    <p class="karte-mitte__lead"><?= e(t('reset_lead')) ?></p>

    <?php triz_meldung($fehler, 'fehler'); ?>

    <form class="formular" method="post">
      <?= csrf_feld() ?>
      <label for="email"><?= e(t('email')) ?></label>
      <input type="email" id="email" name="email" required autofocus autocomplete="username">
      <button type="submit" class="knopf knopf--primaer"><?= e(t('absenden')) ?></button>
    </form>

    <p class="karte-mitte__zusatz"><a href="/TRIZ/login.php"><?= e(t('zurueck')) ?></a></p>
    <?php
}

triz_fuss(true);

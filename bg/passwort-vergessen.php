<?php
/**
 * Passwort zurücksetzen — Schritt 1: Link anfordern.
 *
 * Die Rückmeldung ist immer dieselbe, ob die Adresse bekannt ist oder nicht.
 * Andernfalls ließe sich hier durchprobieren, wer einen Zugang hat.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';
require_once PRIVAT_PFAD . '/lib/bg.php';

sicherheits_header();
sitzung_starten();

$fehler   = '';
$erledigt = false;

if (ist_post()) {
    csrf_pruefen();

    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    } elseif (bg_anmeldung_gesperrt($email)) {
        // Dieselbe Bremse wie bei der Anmeldung: Sonst wäre dieses Formular
        // der bequeme Weg, unbegrenzt Mails an eine Adresse auszulösen.
        $erledigt = true;
        bg_protokoll('reset_gesperrt', $email);
    } else {
        $stmt = db()->prepare("SELECT * FROM bg_benutzer WHERE email = ? AND status = 'aktiv'");
        $stmt->execute([$email]);
        $benutzer = $stmt->fetch();

        if ($benutzer) {
            [$klartext, $hash] = token_erzeugen();
            $minuten = (int)($CONFIG['sicherheit']['reset_gueltig_minuten'] ?? 60);
            $bis = (new DateTimeImmutable("+{$minuten} minutes"))->format('Y-m-d H:i:s');

            $ins = db()->prepare('INSERT INTO bg_resets (benutzer_id, token_hash, gueltig_bis) VALUES (?, ?, ?)');
            $ins->execute([(int)$benutzer['id'], $hash, $bis]);

            $link = $CONFIG['basis_url'] . '/bg/passwort-neu.php?token=' . urlencode($klartext);
            bg_mail_reset($email, $link, $minuten);
            bg_protokoll('reset_angefordert', $email, (int)$benutzer['id']);
        } else {
            bg_protokoll('reset_unbekannt', $email);
        }

        $erledigt = true;
        bg_versuch_merken($email, false);
    }
}

seite_kopf('Passwort zurücksetzen');

if ($erledigt) {
    meldung('Falls für diese Adresse ein Zugang besteht, ist der Link unterwegs. '
          . 'Bitte sehen Sie auch im Spamordner nach.', 'ok');
    ?>
    <p class="konto__zusatz"><a href="/bg/login.php">Zurück zur Anmeldung</a></p>
    <?php
} else {
    ?>
    <p class="konto__lead">
      Geben Sie Ihre E-Mail-Adresse ein. Sie bekommen einen Link, mit dem Sie ein
      neues Passwort vergeben können.
    </p>

    <?php meldung($fehler, 'fehler'); ?>

    <form method="post">
      <?= csrf_feld() ?>
      <label for="email">E-Mail-Adresse</label>
      <input type="email" id="email" name="email" required autofocus autocomplete="username">
      <button type="submit" class="knopf knopf--primaer">Link anfordern</button>
    </form>

    <p class="konto__zusatz"><a href="/bg/login.php">Zurück zur Anmeldung</a></p>
    <?php
}

seite_fuss();

<?php
/**
 * Neues Passwort anfordern.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';

sicherheits_header();
sitzung_starten();

$erledigt = false;
$fehler   = '';

if (ist_post()) {
    csrf_pruefen();
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    } elseif (anmeldung_gesperrt($email)) {
        $fehler = 'Zu viele Versuche. Bitte warten Sie einige Minuten.';
    } else {
        $stmt = db()->prepare("SELECT * FROM benutzer WHERE email = ? AND status = 'aktiv'");
        $stmt->execute([$email]);
        $benutzer = $stmt->fetch();

        if ($benutzer) {
            // Ältere, noch offene Anforderungen entwerten - es soll immer nur
            // ein gültiger Link im Umlauf sein.
            $up = db()->prepare(
                'UPDATE passwort_resets SET benutzt_am = NOW()
                 WHERE benutzer_id = ? AND benutzt_am IS NULL'
            );
            $up->execute([(int)$benutzer['id']]);

            [$klartext, $hash] = token_erzeugen();
            $minuten = (int)($CONFIG['sicherheit']['reset_gueltig_minuten'] ?? 60);
            $bis = (new DateTimeImmutable("+{$minuten} minutes"))->format('Y-m-d H:i:s');

            $ins = db()->prepare(
                'INSERT INTO passwort_resets (benutzer_id, token_hash, gueltig_bis)
                 VALUES (?, ?, ?)'
            );
            $ins->execute([(int)$benutzer['id'], $hash, $bis]);

            $link = $CONFIG['basis_url'] . '/konto/passwort-neu.php?token=' . urlencode($klartext);
            mail_passwort_reset($benutzer['email'], $link, $minuten);
            protokoll('passwort_reset_angefordert', $email, (int)$benutzer['id']);
        } else {
            // Kein Konto - trotzdem protokollieren, aber nach außen dieselbe
            // Antwort geben.
            protokoll('passwort_reset_unbekannt', $email);
        }

        anmeldeversuch_merken($email, false);
        $erledigt = true;
    }
}

seite_kopf('Passwort vergessen');

if ($erledigt) {
    /*
     * Immer dieselbe Bestätigung, unabhängig davon, ob es das Konto gibt.
     * Andernfalls ließe sich hier durchprobieren, welche Adressen bei uns
     * hinterlegt sind.
     */
    ?>
    <p class="meldung meldung--ok">
      Wenn zu dieser Adresse ein aktiver Zugang besteht, ist eine E-Mail mit
      einem Link zum Zurücksetzen unterwegs.
    </p>
    <p class="konto__lead">
      Schauen Sie bitte auch im Spam-Ordner nach. Der Link ist
      <?= (int)($CONFIG['sicherheit']['reset_gueltig_minuten'] ?? 60) ?> Minuten gültig.
    </p>
    <p class="konto__zusatz"><a href="/konto/login.php">Zurück zur Anmeldung</a></p>
    <?php
} else {
    ?>
    <p class="konto__lead">
      Geben Sie Ihre E-Mail-Adresse ein. Sie erhalten dann einen Link, über
      den Sie ein neues Passwort vergeben können.
    </p>

    <?php meldung($fehler, 'fehler'); ?>

    <form method="post">
      <?= csrf_feld() ?>
      <label for="email">E-Mail-Adresse</label>
      <input type="email" id="email" name="email" required autofocus
             autocomplete="username">
      <button type="submit" class="knopf knopf--primaer">Link anfordern</button>
    </form>

    <p class="konto__zusatz"><a href="/konto/login.php">Zurück zur Anmeldung</a></p>
    <?php
}

seite_fuss();

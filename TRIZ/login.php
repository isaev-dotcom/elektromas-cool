<?php
/**
 * Anmeldung am Business TRIZ Portal.
 *
 * Eigene Anmeldung, eigenes Passwort, eigene Brute-Force-Bremse - siehe den
 * Kommentar am Anfang von schema_triz.sql. Wer im Schulungsbereich angemeldet
 * ist, kommt hier trotzdem an diese Seite.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/triz_bootstrap.php';

if (triz_benutzer() !== null) {
    weiter_zu('/TRIZ/');
}

$fehler  = '';
$hinweis = '';
if (!empty($_SESSION['triz_hinweis'])) {
    $hinweis = t((string)$_SESSION['triz_hinweis']);
    unset($_SESSION['triz_hinweis']);
}
$email_vorbelegt = '';

if (ist_post()) {
    csrf_pruefen();

    $email    = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $passwort = (string)($_POST['passwort'] ?? '');
    $email_vorbelegt = $email;

    if ($email === '' || $passwort === '') {
        $fehler = t('login_leer');
    } elseif (triz_anmeldung_gesperrt($email)) {
        $fehler = t('login_gesperrt', (int)($CONFIG['sicherheit']['sperrdauer_minuten'] ?? 15));
        triz_protokoll('anmeldung_gesperrt', $email);
    } else {
        $stmt = db()->prepare('SELECT * FROM triz_benutzer WHERE email = ?');
        $stmt->execute([$email]);
        $benutzer = $stmt->fetch();

        $passt = $benutzer
            && $benutzer['passwort_hash'] !== null
            && password_verify($passwort, $benutzer['passwort_hash']);

        if ($passt && $benutzer['status'] === 'aktiv') {
            // Rechenaufwand nachziehen, falls sich die Argon2-Parameter
            // geändert haben - mit denselben Optionen wie beim Hashen, sonst
            // gilt jeder Hash als veraltet und wird jedes Mal neu berechnet.
            if (password_needs_rehash($benutzer['passwort_hash'], PASSWORD_ARGON2ID, argon_optionen())) {
                $up = db()->prepare('UPDATE triz_benutzer SET passwort_hash = ? WHERE id = ?');
                $up->execute([passwort_hashen($passwort), $benutzer['id']]);
            }

            triz_versuch_merken($email, true);
            triz_versuche_loeschen($email);
            triz_anmelden($benutzer);
            triz_protokoll('anmeldung', $email, (int)$benutzer['id']);

            $ziel = $_SESSION['triz_nach_login'] ?? '/TRIZ/';
            unset($_SESSION['triz_nach_login']);
            // Nur seiteneigene Ziele - "//fremde.example" ist keins.
            if (!is_string($ziel) || !str_starts_with($ziel, '/TRIZ/')) {
                $ziel = '/TRIZ/';
            }
            weiter_zu($ziel);
        }

        triz_versuch_merken($email, false);

        if ($passt && $benutzer['status'] === 'gesperrt') {
            $fehler = t('konto_gesperrt');
            triz_protokoll('anmeldung_gesperrtes_konto', $email, (int)$benutzer['id']);
        } elseif ($passt && $benutzer['status'] === 'eingeladen') {
            $fehler = t('konto_ohne_passwort');
        } else {
            // Eine einzige, unspezifische Meldung. Stünde hier "E-Mail
            // unbekannt", ließe sich damit durchprobieren, wer im Portal ein
            // Konto hat (User Enumeration).
            $fehler = t('login_fehler');
            triz_protokoll('anmeldung_fehlgeschlagen', $email, $benutzer ? (int)$benutzer['id'] : null);
        }
    }
}

triz_kopf(t('login_titel'), '', true);
?>
  <p class="karte-mitte__lead"><?= e(t('login_lead')) ?></p>

  <?php triz_meldung($fehler, 'fehler'); ?>
  <?php triz_meldung($hinweis, 'hinweis'); ?>

  <form class="formular" method="post" autocomplete="on">
    <?= csrf_feld() ?>

    <label for="email"><?= e(t('email')) ?></label>
    <input type="email" id="email" name="email" required autocomplete="username"
           autofocus value="<?= e($email_vorbelegt) ?>">

    <label for="passwort"><?= e(t('passwort')) ?></label>
    <input type="password" id="passwort" name="passwort" required
           autocomplete="current-password">

    <button type="submit" class="knopf knopf--primaer"><?= e(t('anmelden')) ?></button>
  </form>

  <p class="karte-mitte__zusatz">
    <a href="/TRIZ/passwort-vergessen.php"><?= e(t('passwort_vergessen')) ?></a>
  </p>
  <p class="karte-mitte__zusatz karte-mitte__zusatz--klein">
    <?= e(t('kein_zugang')) ?>
  </p>
<?php
triz_fuss(true);

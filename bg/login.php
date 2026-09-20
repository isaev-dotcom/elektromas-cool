<?php
/**
 * Anmeldung am Gefährdungsbeurteilungs-Portal.
 *
 * Eigene Anmeldung, eigenes Passwort, eigene Brute-Force-Bremse - siehe den
 * Kommentar am Anfang von schema_bg.sql. Wer im Schulungsbereich angemeldet
 * ist, kommt hier trotzdem an diese Seite.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';
require_once PRIVAT_PFAD . '/lib/bg.php';

sicherheits_header();
sitzung_starten();

if (bg_benutzer() !== null) {
    weiter_zu('/bg/');
}

$fehler  = '';
$hinweis = (string)($_SESSION['bg_hinweis'] ?? '');
unset($_SESSION['bg_hinweis']);
$email_vorbelegt = '';

if (ist_post()) {
    csrf_pruefen();

    $email    = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $passwort = (string)($_POST['passwort'] ?? '');
    $email_vorbelegt = $email;

    if ($email === '' || $passwort === '') {
        $fehler = 'Bitte E-Mail-Adresse und Passwort eingeben.';
    } elseif (bg_anmeldung_gesperrt($email)) {
        $minuten = (int)($CONFIG['sicherheit']['sperrdauer_minuten'] ?? 15);
        $fehler = "Zu viele Fehlversuche. Bitte warten Sie {$minuten} Minuten.";
        bg_protokoll('anmeldung_gesperrt', $email);
    } else {
        $stmt = db()->prepare('SELECT * FROM bg_benutzer WHERE email = ?');
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
                $up = db()->prepare('UPDATE bg_benutzer SET passwort_hash = ? WHERE id = ?');
                $up->execute([passwort_hashen($passwort), $benutzer['id']]);
            }

            bg_versuch_merken($email, true);
            bg_versuche_loeschen($email);
            bg_anmelden($benutzer);
            bg_protokoll('anmeldung', $email, (int)$benutzer['id']);

            $ziel = $_SESSION['bg_nach_login'] ?? '/bg/';
            unset($_SESSION['bg_nach_login']);
            // Nur seiteneigene Ziele zulassen.
            if (!is_string($ziel) || !str_starts_with($ziel, '/') || str_starts_with($ziel, '//')) {
                $ziel = '/bg/';
            }
            weiter_zu($ziel);
        }

        bg_versuch_merken($email, false);

        if ($passt && $benutzer['status'] === 'gesperrt') {
            $fehler = 'Dieser Zugang ist gesperrt. Bitte wenden Sie sich an isaev@elektromas.de.';
            bg_protokoll('anmeldung_gesperrtes_konto', $email, (int)$benutzer['id']);
        } elseif ($passt && $benutzer['status'] === 'eingeladen') {
            $fehler = 'Für diesen Zugang wurde noch kein Passwort gesetzt. Bitte nutzen Sie den Link aus Ihrer Einladung.';
        } else {
            // Eine einzige, unspezifische Meldung. Stünde hier "E-Mail
            // unbekannt", ließe sich damit durchprobieren, wer einen Zugang
            // hat (User Enumeration).
            $fehler = 'E-Mail-Adresse oder Passwort ist falsch.';
            bg_protokoll('anmeldung_fehlgeschlagen', $email, $benutzer ? (int)$benutzer['id'] : null);
        }
    }
}

seite_kopf('Gefährdungsbeurteilung');
?>
  <p class="konto__lead">
    Das Portal für Gefährdungsbeurteilungen auf Baustellen. Der Zugang ist von
    dem für die Schulungen getrennt - bitte melden Sie sich hier eigens an.
  </p>

  <?php meldung($fehler, 'fehler'); ?>
  <?php meldung($hinweis, 'hinweis'); ?>

  <form method="post" autocomplete="on">
    <?= csrf_feld() ?>

    <label for="email">E-Mail-Adresse</label>
    <input type="email" id="email" name="email" required autocomplete="username"
           autofocus value="<?= e($email_vorbelegt) ?>">

    <label for="passwort">Passwort</label>
    <input type="password" id="passwort" name="passwort" required
           autocomplete="current-password">

    <button type="submit" class="knopf knopf--primaer">Anmelden</button>
  </form>

  <p class="konto__zusatz">
    <a href="/bg/passwort-vergessen.php">Passwort vergessen?</a>
  </p>
  <p class="konto__zusatz konto__zusatz--klein">
    Sie haben noch keinen Zugang? Zugänge werden ausschließlich von der
    Geschäftsführung vergeben. Wenden Sie sich an
    <a href="mailto:isaev@elektromas.de">isaev@elektromas.de</a>.
  </p>
<?php
seite_fuss();

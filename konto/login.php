<?php
/**
 * Anmeldung mit E-Mail und Passwort.
 */

declare(strict_types=1);

// httpdocs/konto/ -> zwei Ebenen hoch ist die Vhost-Wurzel, dort liegt privat/
require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';

sicherheits_header();
sitzung_starten();

// Wer schon angemeldet ist, hat hier nichts verloren.
if (aktueller_benutzer() !== null) {
    weiter_zu('/Schulungen/');
}

$fehler  = '';
$hinweis = $_SESSION['hinweis'] ?? '';
unset($_SESSION['hinweis']);
$email_vorbelegt = '';

if (ist_post()) {
    csrf_pruefen();

    $email    = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $passwort = (string)($_POST['passwort'] ?? '');
    $email_vorbelegt = $email;

    if ($email === '' || $passwort === '') {
        $fehler = 'Bitte E-Mail-Adresse und Passwort eingeben.';
    } elseif (anmeldung_gesperrt($email)) {
        // Bewusst deutlich: Der Nutzer soll wissen, warum es gerade nicht geht.
        $minuten = (int)($CONFIG['sicherheit']['sperrdauer_minuten'] ?? 15);
        $fehler = "Zu viele Fehlversuche. Bitte warten Sie {$minuten} Minuten.";
        protokoll('anmeldung_gesperrt', $email);
    } else {
        $stmt = db()->prepare('SELECT * FROM benutzer WHERE email = ?');
        $stmt->execute([$email]);
        $benutzer = $stmt->fetch();

        $passt = $benutzer
            && $benutzer['passwort_hash'] !== null
            && password_verify($passwort, $benutzer['passwort_hash']);

        if ($passt && $benutzer['status'] === 'aktiv') {
            // Rechenaufwand nachziehen, falls sich die Parameter geändert
            // haben. Dieselben Optionen wie beim Hashen, sonst gilt jeder Hash
            // als veraltet und wird bei jeder Anmeldung neu berechnet.
            if (password_needs_rehash($benutzer['passwort_hash'], PASSWORD_ARGON2ID, argon_optionen())) {
                $neu = passwort_hashen($passwort);
                $up = db()->prepare('UPDATE benutzer SET passwort_hash = ? WHERE id = ?');
                $up->execute([$neu, $benutzer['id']]);
            }

            anmeldeversuch_merken($email, true);
            anmeldeversuche_loeschen($email);
            anmelden($benutzer);
            protokoll('anmeldung', $email, (int)$benutzer['id']);

            $ziel = $_SESSION['nach_login'] ?? '/Schulungen/';
            unset($_SESSION['nach_login']);
            // Nur seiteneigene Ziele zulassen.
            if (!is_string($ziel) || !str_starts_with($ziel, '/') || str_starts_with($ziel, '//')) {
                $ziel = '/Schulungen/';
            }
            weiter_zu($ziel);
        }

        anmeldeversuch_merken($email, false);

        if ($passt && $benutzer['status'] === 'gesperrt') {
            $fehler = 'Dieser Zugang ist gesperrt. Bitte wenden Sie sich an isaev@elektromas.de.';
            protokoll('anmeldung_gesperrtes_konto', $email, (int)$benutzer['id']);
        } elseif ($passt && $benutzer['status'] === 'eingeladen') {
            $fehler = 'Für diesen Zugang wurde noch kein Passwort gesetzt. Bitte nutzen Sie den Link aus Ihrer Einladung.';
        } else {
            // Eine einzige, unspezifische Meldung. Stünde hier "E-Mail
            // unbekannt", könnte man damit durchprobieren, wer bei uns ein
            // Konto hat (User Enumeration).
            $fehler = 'E-Mail-Adresse oder Passwort ist falsch.';
            protokoll('anmeldung_fehlgeschlagen', $email, $benutzer ? (int)$benutzer['id'] : null);
        }
    }
}

seite_kopf('Anmeldung');
?>
  <p class="konto__lead">
    Der Schulungsbereich ist den Mitarbeitenden der elektromas GmbH
    vorbehalten. Bitte melden Sie sich an.
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
    <a href="/konto/passwort-vergessen.php">Passwort vergessen?</a>
  </p>
  <p class="konto__zusatz konto__zusatz--klein">
    Sie haben noch keinen Zugang? Zugänge werden ausschließlich von der
    Geschäftsführung vergeben. Wenden Sie sich an
    <a href="mailto:isaev@elektromas.de">isaev@elektromas.de</a>.
  </p>
<?php
seite_fuss();

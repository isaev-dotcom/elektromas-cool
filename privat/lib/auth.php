<?php
/**
 * Anmeldung, Rechte und Token-Verwaltung.
 */

declare(strict_types=1);

// --- Passwörter ------------------------------------------------------------

/**
 * Argon2id ist der Nachfolger von bcrypt und seit PHP 7.3 eingebaut; der
 * Server hat es. Anders als bcrypt kostet es dem Angreifer nicht nur Rechen-,
 * sondern auch Arbeitsspeicher, was Grafikkarten-Angriffe teuer macht.
 * Fällt Argon2id einmal weg, greift bcrypt.
 */
function passwort_hashen(string $klartext): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($klartext, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,   // 64 MB
            'time_cost'   => 4,
            'threads'     => 2,
        ]);
    }
    return password_hash($klartext, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Prüft die Passwortstärke. Bewusst nur eine Mindestlänge statt der üblichen
 * Zeichenklassen-Regeln: Eine lange Passphrase ist stärker als "Herbst1!" und
 * leichter zu merken. Das entspricht auch der Empfehlung des BSI.
 */
function passwort_pruefen(string $passwort): ?string
{
    global $CONFIG;
    $min = (int)($CONFIG['sicherheit']['mindest_passwortlaenge'] ?? 12);

    if (mb_strlen($passwort) < $min) {
        return "Das Passwort muss mindestens {$min} Zeichen lang sein.";
    }
    if (mb_strlen($passwort) > 200) {
        return 'Das Passwort darf höchstens 200 Zeichen lang sein.';
    }
    $schwach = ['passwort', 'password', '12345678', 'elektromas', 'qwertz', 'schulung'];
    $klein = mb_strtolower($passwort);
    foreach ($schwach as $wort) {
        if (str_contains($klein, $wort)) {
            return 'Das Passwort enthält einen zu leicht zu erratenden Bestandteil.';
        }
    }
    return null;
}

// --- Token -----------------------------------------------------------------

/**
 * Erzeugt ein Token für Einladungs- und Reset-Links.
 * Rückgabe: [klartext, hash]. Nur der Hash wandert in die Datenbank.
 */
function token_erzeugen(): array
{
    $klartext = bin2hex(random_bytes(32));
    return [$klartext, hash('sha256', $klartext)];
}

// --- Anmeldestatus ---------------------------------------------------------

function angemeldet(): bool
{
    return isset($_SESSION['benutzer_id']);
}

function aktueller_benutzer(): ?array
{
    static $cache = null;
    if (!angemeldet()) {
        return null;
    }
    if ($cache !== null) {
        return $cache;
    }
    $stmt = db()->prepare('SELECT * FROM benutzer WHERE id = ?');
    $stmt->execute([$_SESSION['benutzer_id']]);
    $b = $stmt->fetch();

    // Bei jedem Aufruf gegen die Datenbank prüfen: Wird ein Konto gesperrt,
    // während die Sitzung läuft, endet der Zugang sofort und nicht erst beim
    // nächsten Anmelden.
    if (!$b || $b['status'] !== 'aktiv') {
        sitzung_beenden();
        return null;
    }
    return $cache = $b;
}

function ist_admin(): bool
{
    $b = aktueller_benutzer();
    return $b !== null && $b['rolle'] === 'admin';
}

/** Schützt eine Seite. Nicht angemeldete Besucher landen beim Login. */
function login_verlangen(): array
{
    sitzung_starten();
    $b = aktueller_benutzer();
    if ($b === null) {
        $ziel = $_SERVER['REQUEST_URI'] ?? '/Schulungen/';
        $_SESSION['nach_login'] = $ziel;
        weiter_zu('/konto/login.php');
    }
    return $b;
}

function admin_verlangen(): array
{
    $b = login_verlangen();
    if ($b['rolle'] !== 'admin') {
        http_response_code(403);
        exit('Kein Zugriff.');
    }
    return $b;
}

/** Meldet einen geprüften Benutzer an. */
function anmelden(array $benutzer): void
{
    // Neue Sitzungs-ID nach der Anmeldung: Eine vorher untergeschobene ID
    // wird damit wertlos (Schutz gegen Session Fixation).
    session_regenerate_id(true);

    $_SESSION['benutzer_id']     = (int)$benutzer['id'];
    $_SESSION['angemeldet_seit'] = time();
    $_SESSION['zuletzt_aktiv']   = time();

    $stmt = db()->prepare('UPDATE benutzer SET letzter_login = NOW() WHERE id = ?');
    $stmt->execute([(int)$benutzer['id']]);
}

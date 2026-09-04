<?php
/**
 * Gemeinsamer Einstiegspunkt. Jede PHP-Seite bindet als Erstes diese Datei ein.
 *
 * Zuständig für: Konfiguration, Sicherheits-Header, Sitzung, Datenbank.
 */

declare(strict_types=1);

// Fehler nie an den Besucher ausgeben - Pfade und SQL in einer Fehlermeldung
// sind eine Steilvorlage für Angreifer. Ins Log dürfen sie.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

define('PRIVAT_PFAD', dirname(__DIR__));

// --- Konfiguration ---------------------------------------------------------

$config_datei = PRIVAT_PFAD . '/config.php';
if (!is_file($config_datei)) {
    http_response_code(500);
    exit('Konfiguration fehlt. Bitte config.example.php nach config.php kopieren und ausfüllen.');
}
$CONFIG = require $config_datei;

if (($CONFIG['sicherheit']['ip_pfeffer'] ?? '') === '') {
    http_response_code(500);
    exit('Konfiguration unvollständig: ip_pfeffer ist nicht gesetzt.');
}

// --- Sicherheits-Header ----------------------------------------------------

/**
 * Content-Security-Policy: Die eingebetteten Schulungen bringen eigene
 * <style>- und <script>-Blöcke mit, deshalb ist 'unsafe-inline' dort nötig.
 * Externe Quellen bleiben komplett gesperrt - genau das, was die
 * Datenschutzerklärung zusichert. Medien kommen als data: URI.
 */
function sicherheits_header(bool $mit_inline_medien = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin');

    // Nur über HTTPS senden, sonst sperrt man sich bei einem lokalen Test aus.
    if (!empty($_SERVER['HTTPS'])) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    $medien = $mit_inline_medien ? " data: blob:" : " data:";
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline'; " .
        "style-src 'self' 'unsafe-inline'; " .
        "img-src 'self'{$medien}; " .
        "media-src 'self'{$medien}; " .
        "font-src 'self' data:; " .
        "connect-src 'self'; " .
        "form-action 'self'; " .
        "frame-ancestors 'none'; " .
        "base-uri 'self'; " .
        "object-src 'none'"
    );
}

// --- Sitzung ---------------------------------------------------------------

/**
 * Sitzungsdateien liegen in einem eigenen Verzeichnis außerhalb des
 * Web-Verzeichnisses. Die Voreinstellung /tmp teilen sich auf einem
 * Shared-Host alle Kunden desselben Servers.
 */
function sitzung_starten(): void
{
    global $CONFIG;

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $pfad = PRIVAT_PFAD . '/sessions';
    if (is_dir($pfad) && is_writable($pfad)) {
        session_save_path($pfad);
    }

    session_name('emas_sitzung');
    session_set_cookie_params([
        'lifetime' => 0,            // Cookie endet mit dem Browser
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,         // für JavaScript unsichtbar -> XSS kann es nicht stehlen
        'samesite' => 'Lax',        // Lax statt Strict, damit Links aus E-Mails funktionieren
    ]);
    // Verhindert, dass ein Angreifer eine selbst gewählte Sitzungs-ID
    // vorgibt (Session Fixation).
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_start();

    $jetzt = time();
    $leerlauf = ($CONFIG['sicherheit']['sitzung_leerlauf_minuten'] ?? 120) * 60;
    $maximal  = ($CONFIG['sicherheit']['sitzung_maximal_stunden'] ?? 12) * 3600;

    if (isset($_SESSION['benutzer_id'])) {
        $zu_lange_untaetig = isset($_SESSION['zuletzt_aktiv'])
            && ($jetzt - $_SESSION['zuletzt_aktiv']) > $leerlauf;
        $zu_lange_offen = isset($_SESSION['angemeldet_seit'])
            && ($jetzt - $_SESSION['angemeldet_seit']) > $maximal;

        if ($zu_lange_untaetig || $zu_lange_offen) {
            sitzung_beenden();
            session_start();
            $_SESSION['hinweis'] = 'Sie wurden aus Sicherheitsgründen abgemeldet.';
        }
    }
    $_SESSION['zuletzt_aktiv'] = $jetzt;
}

function sitzung_beenden(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}

// --- Datenbank -------------------------------------------------------------

function db(): PDO
{
    global $CONFIG;
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $CONFIG['db']['host'],
        $CONFIG['db']['name']
    );

    try {
        $pdo = new PDO($dsn, $CONFIG['db']['benutzer'], $CONFIG['db']['passwort'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Echte Prepared Statements statt Emulation. Damit trennt die
            // Datenbank Befehl und Daten selbst - SQL-Injection ist über
            // Platzhalter dann nicht möglich.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB-Verbindung fehlgeschlagen: ' . $e->getMessage());
        http_response_code(500);
        exit('Datenbank nicht erreichbar. Bitte später erneut versuchen.');
    }

    return $pdo;
}

// --- Kleine Helfer ---------------------------------------------------------

/** Ausgabe-Escaping gegen XSS. Kurzer Name, weil er überall steht. */
function e(?string $wert): string
{
    return htmlspecialchars($wert ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** IP nur pseudonymisiert speichern - siehe Kommentar in schema.sql. */
function ip_hash(): string
{
    global $CONFIG;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', $CONFIG['sicherheit']['ip_pfeffer'] . '|' . $ip);
}

function ist_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function weiter_zu(string $pfad): never
{
    global $CONFIG;
    // Nur seiteneigene Ziele - verhindert offene Weiterleitungen.
    if (!str_starts_with($pfad, '/')) {
        $pfad = '/' . $pfad;
    }
    header('Location: ' . $CONFIG['basis_url'] . $pfad);
    exit;
}

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/protokoll.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail.php';

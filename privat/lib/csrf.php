<?php
/**
 * Schutz gegen Cross-Site Request Forgery.
 *
 * Ohne diesen Schutz könnte eine fremde Seite, die ein angemeldeter Nutzer
 * besucht, in seinem Namen Formulare an uns abschicken - etwa "Benutzer
 * löschen". Das Token ist der Beweis, dass das Formular von uns stammt.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Fertiges verstecktes Feld für Formulare. */
function csrf_feld(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Prüft das Token einer POST-Anfrage. Bricht bei Misserfolg hart ab, statt
 * die Anfrage stillschweigend zu verwerfen.
 */
function csrf_pruefen(): void
{
    $gesendet = (string)($_POST['csrf'] ?? '');
    $erwartet = (string)($_SESSION['csrf_token'] ?? '');

    // hash_equals vergleicht in konstanter Zeit. Ein normaler Vergleich
    // bricht beim ersten falschen Zeichen ab und verrät über die Laufzeit,
    // wie viele Zeichen stimmten.
    if ($erwartet === '' || !hash_equals($erwartet, $gesendet)) {
        http_response_code(400);
        exit('Ungültige Anfrage. Bitte laden Sie die Seite neu und versuchen es erneut.');
    }
}

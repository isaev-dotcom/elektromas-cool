<?php
/**
 * E-Mail-Versand über die eingebaute mail()-Funktion (sendmail ist auf dem
 * Server vorhanden).
 */

declare(strict_types=1);

/**
 * Verschickt eine Nur-Text-Mail.
 *
 * Betreff und Empfänger werden von Zeilenumbrüchen befreit. Ohne das könnte
 * jemand über ein Eingabefeld zusätzliche Kopfzeilen einschleusen und den
 * Server als Spam-Schleuder missbrauchen (Header-Injection).
 */
function mail_senden(string $an, string $betreff, string $text): bool
{
    global $CONFIG;

    $an = trim(str_replace(["\r", "\n"], '', $an));
    if (!filter_var($an, FILTER_VALIDATE_EMAIL)) {
        error_log('Mail nicht gesendet, ungültige Adresse: ' . $an);
        return false;
    }
    $betreff = trim(str_replace(["\r", "\n"], ' ', $betreff));

    $absender_adresse = $CONFIG['mail']['absender_adresse'];
    $absender_name    = str_replace(["\r", "\n", '"'], '', $CONFIG['mail']['absender_name']);

    $kopf = implode("\r\n", [
        'From: "' . $absender_name . '" <' . $absender_adresse . '>',
        'Reply-To: ' . $CONFIG['mail']['admin_adresse'],
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: elektromas-schulungen',
        'Auto-Submitted: auto-generated',
    ]);

    // Betreff MIME-kodieren, sonst zerlegen Umlaute die Kopfzeile.
    $betreff_kodiert = '=?UTF-8?B?' . base64_encode($betreff) . '?=';

    // -f setzt den Envelope-Absender; ohne ihn versendet der Server unter
    // einer Systemadresse, was die Zustellbarkeit verschlechtert.
    $ok = @mail($an, $betreff_kodiert, $text, $kopf, '-f' . $absender_adresse);

    if (!$ok) {
        error_log('Mailversand fehlgeschlagen an ' . $an);
    }
    return $ok;
}

function mail_einladung(string $an, string $name, string $link, int $gueltig_stunden): bool
{
    $tage = max(1, (int)round($gueltig_stunden / 24));
    $anrede = $name !== '' ? "Hallo {$name}," : 'Hallo,';

    $text = <<<TEXT
{$anrede}

für Sie wurde ein Zugang zum Schulungsbereich der elektromas GmbH angelegt.

Über den folgenden Link vergeben Sie Ihr Passwort und schließen die
Einrichtung ab:

{$link}

Der Link ist {$tage} Tage gültig und lässt sich nur einmal verwenden.

Danach erreichen Sie die Schulungen jederzeit unter:
https://elektromas.cool/Schulungen/

Falls Sie mit dieser Einladung nichts anfangen können, ignorieren Sie diese
Nachricht bitte einfach - ohne den Link passiert nichts.

Mit freundlichen Grüßen
elektromas GmbH
TEXT;

    return mail_senden($an, 'Ihr Zugang zum Schulungsbereich', $text);
}

function mail_passwort_reset(string $an, string $link, int $gueltig_minuten): bool
{
    $text = <<<TEXT
Hallo,

für Ihren Zugang zum Schulungsbereich der elektromas GmbH wurde ein neues
Passwort angefordert.

Über diesen Link vergeben Sie ein neues Passwort:

{$link}

Der Link ist {$gueltig_minuten} Minuten gültig und lässt sich nur einmal
verwenden.

Haben Sie das nicht angefordert? Dann ist nichts passiert - Ihr bisheriges
Passwort gilt unverändert weiter. Sie müssen nichts tun.

Mit freundlichen Grüßen
elektromas GmbH
TEXT;

    return mail_senden($an, 'Passwort zurücksetzen', $text);
}

/** Benachrichtigt den Administrator, wenn ein Konto in Betrieb geht. */
function mail_admin_info(string $betreff, string $text): bool
{
    global $CONFIG;
    return mail_senden($CONFIG['mail']['admin_adresse'], $betreff, $text);
}

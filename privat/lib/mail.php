<?php
/**
 * E-Mail-Versand.
 *
 * Ist ein Microsoft-365-Postfach konfiguriert, geht die Mail über die
 * Microsoft-Graph-Schnittstelle hinaus, sonst über die eingebaute
 * mail()-Funktion des Servers.
 *
 * Warum nicht einfach mail(): Auf diesem Webspace sind elektromas-alarm.de
 * und immo-stand.de noch als lokale Maildomains eingerichtet, samt Postfächern.
 * Ihre MX-Einträge zeigen aber längst auf Microsoft 365. Der Mailserver des
 * Hosters hält sich trotzdem für zuständig, stellt Mails an diese Domains in
 * die alten Postfächer zu oder weist sie ab - die Mail verlässt den Server nie,
 * und mail() meldet dennoch Erfolg. So gingen Einladungen verloren.
 *
 * Direkt an den Mailserver des Empfängers zu liefern geht auf diesem Webspace
 * nicht: Ausgehender Port 25 ist über IPv4 gesperrt, und die IPv6-Adresse des
 * Servers hat keinen Reverse-DNS-Eintrag, weshalb Microsoft dort abweist
 * (450 4.7.25, gemessen 14.09.2026). Der Weg über Graph ist reines HTTPS und
 * umgeht beides; die Mail geht dann mit SPF und DKIM von elektromas.de raus.
 */

declare(strict_types=1);

/**
 * Merkt sich den letzten Versandfehler im Klartext.
 *
 * error_log landet in einer Datei, an die im Webhosting niemand herankommt.
 * Die Prüfseite unter /admin/mailtest.php zeigt deshalb diesen Text an.
 */
function mail_letzter_fehler(?string $neu = null): string
{
    static $fehler = '';
    if ($neu !== null) {
        $fehler = $neu;
        error_log($neu);
    }
    return $fehler;
}

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
        mail_letzter_fehler('Mail nicht gesendet, ungültige Adresse: ' . $an);
        return false;
    }
    $betreff = trim(str_replace(["\r", "\n"], ' ', $betreff));

    if (mail_graph_eingerichtet()) {
        return mail_graph_senden($an, $betreff, $text);
    }

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
        mail_letzter_fehler('Der Mailserver des Webhosters hat die Nachricht an ' . $an . ' nicht angenommen.');
    }
    return $ok;
}

// ===========================================================================
// Versand über Microsoft 365 (Graph)
// ===========================================================================

/** Ist ein Absenderpostfach samt Zugangsdaten hinterlegt? */
function mail_graph_eingerichtet(): bool
{
    global $CONFIG;
    $g = $CONFIG['mail']['graph'] ?? [];
    foreach (['mandant_id', 'client_id', 'client_secret', 'postfach'] as $feld) {
        if (trim((string)($g[$feld] ?? '')) === '') {
            return false;
        }
    }
    return true;
}

/**
 * Verschickt über POST /users/{postfach}/sendMail.
 *
 * Der Absender ist immer das konfigurierte Postfach - Graph lässt keine
 * fremde Absenderadresse zu. Antworten gehen an die Admin-Adresse.
 */
function mail_graph_senden(string $an, string $betreff, string $text): bool
{
    global $CONFIG;
    $postfach = trim((string)$CONFIG['mail']['graph']['postfach']);

    $nachricht = [
        'message' => [
            'subject'      => $betreff,
            'body'         => ['contentType' => 'Text', 'content' => $text],
            'toRecipients' => [['emailAddress' => ['address' => $an]]],
        ],
        // Der Postausgang des Postfachs soll nicht mit Einladungen volllaufen.
        'saveToSentItems' => false,
    ];
    $antwort_adresse = trim((string)$CONFIG['mail']['admin_adresse']);
    if (filter_var($antwort_adresse, FILTER_VALIDATE_EMAIL)) {
        $nachricht['message']['replyTo'] = [['emailAddress' => ['address' => $antwort_adresse]]];
    }

    $url  = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($postfach) . '/sendMail';
    $rumpf = json_encode($nachricht, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Zwei Anläufe: Ein abgelehntes Token kann auch an einem zwischengespeicherten
    // Wert liegen, der serverseitig ungültig wurde. Dann einmal frisch holen.
    for ($versuch = 1; $versuch <= 2; $versuch++) {
        $token = mail_graph_token($versuch === 2);
        if ($token === '') {
            return false;
        }
        [$code, $inhalt] = mail_graph_http($url, $rumpf, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        if ($code === 202) {
            return true;
        }
        if ($code === 401 && $versuch === 1) {
            continue;
        }
        mail_letzter_fehler('Graph-Versand an ' . $an . ' fehlgeschlagen (HTTP ' . $code . '): '
                            . substr(preg_replace('/\s+/', ' ', (string)$inhalt) ?? '', 0, 400));
        return false;
    }
    return false;
}

/**
 * Holt ein Zugriffstoken (OAuth2 client_credentials) und legt es bis kurz vor
 * Ablauf in einer Datei ab - ein Token gilt rund eine Stunde, ohne Ablage
 * bräuchte jede einzelne Mail einen zusätzlichen Anmeldevorgang.
 */
function mail_graph_token(bool $erzwingen = false): string
{
    global $CONFIG;
    $g = $CONFIG['mail']['graph'];
    $ablage = (defined('PRIVAT_PFAD') ? PRIVAT_PFAD : dirname(__DIR__)) . '/graph_token.json';

    if (!$erzwingen && is_readable($ablage)) {
        $alt = json_decode((string)@file_get_contents($ablage), true);
        if (is_array($alt) && ($alt['gilt_bis'] ?? 0) > time() + 60 && ($alt['token'] ?? '') !== '') {
            return (string)$alt['token'];
        }
    }

    [$code, $inhalt] = mail_graph_http(
        'https://login.microsoftonline.com/' . rawurlencode(trim((string)$g['mandant_id']))
            . '/oauth2/v2.0/token',
        http_build_query([
            'client_id'     => trim((string)$g['client_id']),
            'client_secret' => trim((string)$g['client_secret']),
            'scope'         => 'https://graph.microsoft.com/.default',
            'grant_type'    => 'client_credentials',
        ]),
        ['Content-Type: application/x-www-form-urlencoded']
    );

    $daten = json_decode((string)$inhalt, true);
    if ($code !== 200 || !is_array($daten) || ($daten['access_token'] ?? '') === '') {
        // Die Fehlermeldung von Microsoft nennt die Ursache (falsches Geheimnis,
        // fehlende Freigabe), enthält aber keine Zugangsdaten.
        mail_letzter_fehler('Graph-Anmeldung fehlgeschlagen (HTTP ' . $code . '): '
                            . substr(preg_replace('/\s+/', ' ', (string)$inhalt) ?? '', 0, 400));
        return '';
    }

    $inhalt_neu = json_encode([
        'token'    => $daten['access_token'],
        'gilt_bis' => time() + (int)($daten['expires_in'] ?? 3600),
    ]);
    // Erst in eine Nebendatei schreiben und dann umbenennen: Sonst läse ein
    // paralleler Aufruf womöglich ein halb geschriebenes Token.
    $temp = $ablage . '.' . bin2hex(random_bytes(4));
    if (@file_put_contents($temp, $inhalt_neu) !== false) {
        @chmod($temp, 0600);
        @rename($temp, $ablage);
    }
    return (string)$daten['access_token'];
}

/**
 * Ein HTTPS-POST an Microsoft.
 *
 * @return array{0: int, 1: string} HTTP-Status (0 = keine Antwort) und Rumpf
 */
function mail_graph_http(string $url, string $rumpf, array $kopfzeilen): array
{
    if (!function_exists('curl_init')) {
        mail_letzter_fehler('Graph-Versand nicht möglich: cURL fehlt auf dem Server.');
        return [0, ''];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $rumpf,
        CURLOPT_HTTPHEADER     => $kopfzeilen,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $antwort = curl_exec($ch);
    $code    = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $fehler  = curl_error($ch);
    curl_close($ch);

    if ($antwort === false) {
        mail_letzter_fehler('Graph-Verbindung fehlgeschlagen: ' . $fehler);
        return [0, ''];
    }
    return [$code, (string)$antwort];
}

// ===========================================================================
// Einzelne Nachrichten
// ===========================================================================

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

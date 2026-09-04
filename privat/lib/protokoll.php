<?php
/**
 * Protokollierung von Registrierungen, Anmeldungen und Verwaltungsschritten.
 */

declare(strict_types=1);

function protokoll(string $ereignis, string $email = '', ?int $benutzer_id = null, string $details = ''): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO protokoll (benutzer_id, email, ereignis, details, ip_hash)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $benutzer_id,
            mb_substr($email, 0, 190),
            mb_substr($ereignis, 0, 40),
            mb_substr($details, 0, 255),
            ip_hash(),
        ]);
    } catch (Throwable $e) {
        // Ein fehlgeschlagener Protokolleintrag darf niemals die eigentliche
        // Aktion verhindern - sonst sperrt ein volles Log alle Anmeldungen.
        error_log('Protokoll fehlgeschlagen: ' . $e->getMessage());
    }
}

/**
 * Brute-Force-Bremse.
 *
 * Gezählt wird getrennt nach E-Mail und nach IP: Nur nach E-Mail zu sperren
 * würde es Fremden erlauben, ein bestimmtes Konto gezielt lahmzulegen; nur
 * nach IP zu sperren ließe verteilte Angriffe durch.
 */
function anmeldung_gesperrt(string $email): bool
{
    global $CONFIG;

    $minuten = (int)($CONFIG['sicherheit']['sperrdauer_minuten'] ?? 15);
    $seit = (new DateTimeImmutable("-{$minuten} minutes"))->format('Y-m-d H:i:s');

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM anmeldeversuche
         WHERE erfolg = 0 AND email = ? AND zeitpunkt > ?'
    );
    $stmt->execute([$email, $seit]);
    if ((int)$stmt->fetchColumn() >= (int)($CONFIG['sicherheit']['max_versuche_email'] ?? 5)) {
        return true;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM anmeldeversuche
         WHERE erfolg = 0 AND ip_hash = ? AND zeitpunkt > ?'
    );
    $stmt->execute([ip_hash(), $seit]);
    return (int)$stmt->fetchColumn() >= (int)($CONFIG['sicherheit']['max_versuche_ip'] ?? 20);
}

function anmeldeversuch_merken(string $email, bool $erfolg): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO anmeldeversuche (email, ip_hash, erfolg) VALUES (?, ?, ?)'
        );
        $stmt->execute([mb_substr($email, 0, 190), ip_hash(), $erfolg ? 1 : 0]);
    } catch (Throwable $e) {
        error_log('Anmeldeversuch nicht gespeichert: ' . $e->getMessage());
    }
}

/** Nach erfolgreicher Anmeldung die Fehlversuche dieser E-Mail zurücksetzen. */
function anmeldeversuche_loeschen(string $email): void
{
    try {
        $stmt = db()->prepare('DELETE FROM anmeldeversuche WHERE email = ? AND erfolg = 0');
        $stmt->execute([$email]);
    } catch (Throwable $e) {
        error_log('Anmeldeversuche nicht gelöscht: ' . $e->getMessage());
    }
}

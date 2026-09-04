<?php
/**
 * Konfiguration der Benutzerverwaltung.
 *
 * ANLEITUNG
 *   1. Diese Datei zu "config.php" kopieren.
 *   2. Werte eintragen.
 *   3. Nur die config.php wird gelesen; sie liegt außerhalb des
 *      Web-Verzeichnisses und ist damit über den Browser nicht erreichbar.
 *
 * Die Datenbank legst du im Alfahosting-Panel unter DATENBANKEN an. Dort
 * bekommst du Name, Benutzer und Passwort.
 */

return [

    // --- Datenbank ---------------------------------------------------------
    'db' => [
        'host'     => 'localhost',
        'name'     => '',   // z. B. usr_web123_1
        'benutzer' => '',
        'passwort' => '',
    ],

    // --- Adressen ----------------------------------------------------------
    // Ohne Schrägstrich am Ende.
    'basis_url' => 'https://elektromas.cool',

    // --- E-Mail ------------------------------------------------------------
    // Absender muss zur Domain passen, sonst landen die Mails im Spam.
    'mail' => [
        'absender_adresse' => 'noreply@elektromas.cool',
        'absender_name'    => 'elektromas Schulungen',
        // Hierhin gehen Benachrichtigungen über neue Konten und Anmeldungen.
        'admin_adresse'    => 'isaev@elektromas.de',
    ],

    // --- Sicherheit --------------------------------------------------------
    'sicherheit' => [
        // Zufälliger Wert, mit dem IP-Adressen gehasht werden. Einmalig setzen
        // und nie wieder ändern - sonst passen alte Protokolleinträge nicht
        // mehr zu neuen. Erzeugen z. B. mit:
        //   php -r "echo bin2hex(random_bytes(32));"
        'ip_pfeffer' => '',

        // Brute-Force-Bremse
        'max_versuche_email'   => 5,    // je E-Mail
        'max_versuche_ip'      => 20,   // je IP
        'sperrdauer_minuten'   => 15,

        // Gültigkeit der Links aus E-Mails
        'einladung_gueltig_stunden' => 168,  // 7 Tage
        'reset_gueltig_minuten'     => 60,

        // Sitzung
        'sitzung_leerlauf_minuten' => 120,   // Abmeldung nach Untätigkeit
        'sitzung_maximal_stunden'  => 12,    // harte Obergrenze

        'mindest_passwortlaenge' => 12,
    ],

    // --- Datenschutz -------------------------------------------------------
    'aufbewahrung' => [
        // Nach dieser Frist löscht aufraeumen.php Protokolleinträge und alte
        // Anmeldeversuche. Art. 5 Abs. 1 lit. e DSGVO: nicht länger speichern
        // als nötig.
        'protokoll_tage'        => 90,
        'anmeldeversuche_tage'  => 7,
    ],
];

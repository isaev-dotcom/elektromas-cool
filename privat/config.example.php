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

    // --- Business TRIZ Portal ----------------------------------------------
    //
    // Der gesamte Abschnitt ist freiwillig. Fehlt er, laufen alle Bereiche
    // des Portals mit den hier angegebenen Vorgabewerten - nur der
    // KI-Assistent bleibt ohne Schlüssel abgeschaltet.
    'triz' => [

        // Schlüssel für den KI-Assistenten (Anthropic, console.anthropic.com).
        // Ohne ihn zeigt der Bereich einen Hinweis statt einer Antwort.
        'ki_api_schluessel' => '',
        'ki_timeout_sekunden' => 120,

        // Fragen und Antworten sind personenbezogene Daten. aufraeumen.php
        // löscht den Verlauf nach dieser Frist.
        'ki_verlauf_tage' => 30,

        // Optional: Ohne diesen Schlüssel bleibt die Laufzeit der Videos
        // leer, weil der öffentliche YouTube-Feed sie nicht mitliefert.
        // Alles andere - Titel, Kanal, Datum, Vorschaubild - kommt auch
        // ohne ihn.
        'youtube_api_schluessel' => '',

        // Sammlung
        'abruf_timeout_sekunden'    => 20,   // je Quelle
        'beitrag_hoechstalter_tage' => 400,  // ältere Feed-Einträge übergehen

        // Wissensdatenbank
        'upload_max_mb' => 64,

        // Optional: Pfad zu pdftotext. Ist er gesetzt, wandert der Text aus
        // hochgeladenen PDF-Dateien in die Volltextsuche und steht dem
        // KI-Assistenten zur Verfügung. Ohne ihn tragen Titel, Beschreibung
        // und Schlagwörter die Suche.
        //   which pdftotext
        'pdftotext_pfad' => '',

        // Sitzung im Portal - unabhängig von der des Schulungsbereichs.
        'sitzung_leerlauf_minuten' => 120,
        'sitzung_maximal_stunden'  => 12,
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

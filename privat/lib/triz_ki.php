<?php
/**
 * Business TRIZ Portal — der KI-Assistent.
 *
 * Beantwortet Fragen zu Business TRIZ und stützt sich dabei auf den eigenen
 * Bestand: Vor jeder Anfrage werden die interne Wissensdatenbank und die
 * TRIZ-Bibliothek durchsucht und die passenden Auszüge mitgeschickt. Das
 * Modell antwortet damit auf unserer Grundlage statt aus dem Gedächtnis, und
 * die herangezogenen Unterlagen stehen unter der Antwort.
 *
 * Angesprochen wird die Messages-API von Anthropic direkt über HTTPS. Der
 * offizielle PHP-SDK käme per Composer samt vendor-Verzeichnis; dieses Projekt
 * hat bewusst keine Abhängigkeiten und wird per FTP hochgeladen. Ein einzelner
 * Aufruf mit cURL ist hier das kleinere Übel als eine Paketverwaltung, die
 * niemand auf dem Server pflegen kann.
 *
 * Ohne hinterlegten Schlüssel bleibt der Bereich abgeschaltet; alles andere
 * im Portal funktioniert unabhängig davon.
 */

declare(strict_types=1);

const TRIZ_KI_MODELL   = 'claude-opus-5';
const TRIZ_KI_ENDPUNKT = 'https://api.anthropic.com/v1/messages';
const TRIZ_KI_VERSION  = '2023-06-01';

function triz_ki_verfuegbar(): bool
{
    return (string)triz_einstellung('ki_api_schluessel', '') !== '';
}

/**
 * Sucht Auszüge aus dem eigenen Bestand, die zur Frage passen.
 *
 * Die Suche läuft über die Wörter der Frage, nicht über die Frage als Ganzes:
 * "Wie verkürzen wir die Rüstzeit?" käme als ganzer Satz in keinem Dokument
 * vor. Kurze Wörter fallen weg, sie träfen auf alles zu.
 *
 * Rückgabe: ['text' => zusammengesetzter Kontext, 'quellen' => Liste]
 */
function triz_ki_kontext(string $frage, int $hoechstens = 8): array
{
    $woerter = preg_split('/[^\p{L}\p{N}-]+/u', mb_strtolower($frage)) ?: [];
    $woerter = array_values(array_filter($woerter, static fn($w) => mb_strlen($w) >= 4));
    if ($woerter === []) {
        return ['text' => '', 'quellen' => []];
    }
    $woerter = array_slice(array_unique($woerter), 0, 8);

    $treffer = [];
    $quellen = [];

    // --- Interne Dokumente ---
    $bedingung = implode(' OR ', array_fill(0, count($woerter),
        '(titel LIKE ? OR schlagwoerter LIKE ? OR beschreibung LIKE ? OR volltext LIKE ?)'));
    $werte = [];
    foreach ($woerter as $w) {
        $m = '%' . str_replace(['%', '_'], ['\%', '\_'], $w) . '%';
        array_push($werte, $m, $m, $m, $m);
    }

    $stmt = db()->prepare(
        "SELECT id, titel, ordner, beschreibung, volltext
         FROM triz_dokumente
         WHERE aktuell = 1 AND ({$bedingung})
         ORDER BY hochgeladen_am DESC LIMIT " . (int)$hoechstens
    );
    $stmt->execute($werte);

    foreach ($stmt->fetchAll() as $d) {
        $auszug = triz_ki_auszug((string)($d['volltext'] ?? ''), $woerter);
        if ($auszug === '') {
            $auszug = triz_kuerzen((string)$d['beschreibung'], 600);
        }
        if ($auszug === '') {
            continue;
        }
        $treffer[] = "[Dokument] {$d['titel']} (" . triz_ordner_name((string)$d['ordner']) . ")\n" . $auszug;
        $quellen[] = ['art' => 'dokument', 'id' => (int)$d['id'], 'titel' => (string)$d['titel']];
    }

    // --- Wissensbibliothek ---
    $bedingung = implode(' OR ', array_fill(0, count($woerter),
        '(titel_de LIKE ? OR titel_ru LIKE ? OR text_de LIKE ? OR text_ru LIKE ?)'));
    $stmt = db()->prepare(
        "SELECT id, art, nummer, titel_de, text_de, beispiel_de
         FROM triz_bibliothek
         WHERE {$bedingung}
         ORDER BY art, sortierung LIMIT " . (int)$hoechstens
    );
    $stmt->execute($werte);

    foreach ($stmt->fetchAll() as $b) {
        $nummer = $b['nummer'] !== null ? ' ' . $b['nummer'] : '';
        $treffer[] = "[Bibliothek{$nummer}] {$b['titel_de']}\n"
            . triz_kuerzen((string)$b['text_de'], 700)
            . ((string)$b['beispiel_de'] !== '' ? "\nBeispiel: " . triz_kuerzen((string)$b['beispiel_de'], 400) : '');
        $quellen[] = ['art' => 'bibliothek', 'id' => (int)$b['id'], 'titel' => (string)$b['titel_de']];
    }

    return [
        'text'    => implode("\n\n---\n\n", $treffer),
        'quellen' => $quellen,
    ];
}

/**
 * Schneidet die Stelle aus einem langen Text heraus, an der ein Suchwort
 * vorkommt. Den ganzen Volltext mitzuschicken wäre teuer und würde die
 * eigentliche Fundstelle im Rauschen begraben.
 */
function triz_ki_auszug(string $volltext, array $woerter, int $laenge = 700): string
{
    if ($volltext === '') {
        return '';
    }
    $klein = mb_strtolower($volltext);
    foreach ($woerter as $w) {
        $pos = mb_strpos($klein, $w);
        if ($pos !== false) {
            $start = max(0, $pos - (int)($laenge / 3));
            return trim(mb_substr($volltext, $start, $laenge));
        }
    }
    return trim(mb_substr($volltext, 0, $laenge));
}

/**
 * Stellt eine Frage.
 *
 * $verlauf ist die bisherige Unterhaltung als Liste aus
 * ['rolle' => 'frage'|'antwort', 'text' => ...], älteste zuerst.
 *
 * Rückgabe: ['text' => string, 'quellen' => array, 'fehler' => string]
 */
function triz_ki_fragen(string $frage, array $verlauf = []): array
{
    $schluessel = (string)triz_einstellung('ki_api_schluessel', '');
    if ($schluessel === '') {
        return ['text' => '', 'quellen' => [], 'fehler' => 'nicht_eingerichtet'];
    }
    if (!function_exists('curl_init')) {
        return ['text' => '', 'quellen' => [], 'fehler' => 'cURL fehlt auf dem Server'];
    }

    $kontext = triz_ki_kontext($frage);
    $sprache = triz_sprache() === 'ru' ? 'Russisch' : 'Deutsch';

    $anweisung = <<<TEXT
Du bist der Business-TRIZ-Assistent der elektromas GmbH, eines
Elektrotechnik-Unternehmens. Du hilfst Mitarbeitenden, TRIZ auf Fragen aus
Management, Organisation, Produktion und Handwerk anzuwenden.

Arbeitsweise:
- Antworte in der Sprache der Frage. Ist die Frage auf Russisch, antworte auf
  Russisch. Ist sie auf Deutsch, antworte auf Deutsch. Ohne erkennbare Sprache
  antworte auf {$sprache}.
- Bei einer Problemstellung: benenne zuerst den Widerspruch (technisch oder
  physikalisch), dann die passenden Prinzipien oder Methoden, dann konkrete
  Lösungsansätze für dieses Unternehmen.
- Beziehe dich auf die unten mitgelieferten internen Unterlagen, wenn sie
  etwas zur Sache beitragen, und sage dazu, aus welcher Unterlage etwas stammt.
- Wenn die Unterlagen nichts hergeben, sage das und antworte aus deinem
  allgemeinen TRIZ-Wissen. Erfinde keine internen Vorgänge, Zahlen oder
  Dokumente.
- Fasse dich; Aufzählungen sind erwünscht, Fülltext nicht.
TEXT;

    if ($kontext['text'] !== '') {
        $anweisung .= "\n\n=== Auszüge aus dem internen Bestand ===\n" . $kontext['text'];
    }

    $nachrichten = [];
    foreach ($verlauf as $z) {
        $nachrichten[] = [
            'role'    => ($z['rolle'] ?? '') === 'antwort' ? 'assistant' : 'user',
            'content' => (string)($z['text'] ?? ''),
        ];
    }
    $nachrichten[] = ['role' => 'user', 'content' => $frage];

    $rumpf = [
        'model'      => TRIZ_KI_MODELL,
        // Bewusst knapp: Die Antworten sollen im Browser lesbar bleiben und
        // nicht zu Aufsätzen werden. Für diese Länge genügt ein einfacher
        // Abruf ohne Datenstrom.
        'max_tokens' => 4000,
        'system'     => [[
            'type' => 'text',
            'text' => $anweisung,
            // Die Anweisung ist bei jeder Frage dieselbe - zwischenspeichern
            // spart Kosten und Zeit. Die Auszüge stehen bewusst mit darin:
            // Sie ändern sich je Frage, aber der Aufwand für den Cache-Eintrag
            // lohnt sich schon innerhalb einer Unterhaltung zum selben Thema.
            'cache_control' => ['type' => 'ephemeral'],
        ]],
        'messages'   => $nachrichten,
        // Sicherheitsprüfungen können eine Anfrage ablehnen; dann übernimmt
        // ein Ersatzmodell, statt dass die Antwort ausbleibt.
        'fallbacks'  => 'default',
    ];

    $ch = curl_init(TRIZ_KI_ENDPUNKT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($rumpf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => (int)triz_einstellung('ki_timeout_sekunden', 120),
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $schluessel,
            'anthropic-version: ' . TRIZ_KI_VERSION,
            'anthropic-beta: server-side-fallback-2026-07-01',
        ],
    ]);
    $antwort = curl_exec($ch);
    $code    = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $cfehler = curl_error($ch);
    curl_close($ch);

    if ($antwort === false || $antwort === '') {
        error_log('TRIZ-KI: kein Ergebnis, ' . $cfehler);
        return ['text' => '', 'quellen' => [], 'fehler' => 'keine_antwort'];
    }

    $daten = json_decode((string)$antwort, true);
    if (!is_array($daten)) {
        error_log('TRIZ-KI: Antwort nicht lesbar');
        return ['text' => '', 'quellen' => [], 'fehler' => 'unlesbar'];
    }

    if ($code >= 400) {
        // Die Fehlermeldung des Dienstes gehört ins Log, nicht auf den
        // Bildschirm: Sie kann Teile des Schlüssels oder der Anfrage enthalten.
        error_log('TRIZ-KI: HTTP ' . $code . ' ' . ($daten['error']['message'] ?? ''));
        return ['text' => '', 'quellen' => [], 'fehler' => 'http_' . $code];
    }

    // Eine abgelehnte Anfrage kommt mit Status 200 zurück - deshalb vor dem
    // Auslesen des Inhalts den Grund des Endes prüfen.
    if (($daten['stop_reason'] ?? '') === 'refusal') {
        return ['text' => '', 'quellen' => [], 'fehler' => 'abgelehnt'];
    }

    $text = '';
    foreach ($daten['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }
    $text = trim($text);
    if ($text === '') {
        return ['text' => '', 'quellen' => [], 'fehler' => 'leer'];
    }

    return ['text' => $text, 'quellen' => $kontext['quellen'], 'fehler' => ''];
}

// --- Verlauf ---------------------------------------------------------------

function triz_ki_verlauf(int $benutzer_id, int $paare = 6): array
{
    $stmt = db()->prepare(
        'SELECT rolle, text FROM (
           SELECT id, rolle, text FROM triz_ki_verlauf
           WHERE benutzer_id = ? ORDER BY id DESC LIMIT ?
         ) AS letzte ORDER BY id'
    );
    $stmt->bindValue(1, $benutzer_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $paare * 2, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function triz_ki_merken(int $benutzer_id, string $rolle, string $text): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO triz_ki_verlauf (benutzer_id, rolle, text) VALUES (?, ?, ?)'
        );
        $stmt->execute([$benutzer_id, $rolle === 'antwort' ? 'antwort' : 'frage', $text]);
    } catch (Throwable $e) {
        error_log('TRIZ-KI-Verlauf nicht gespeichert: ' . $e->getMessage());
    }
}

function triz_ki_verlauf_loeschen(int $benutzer_id): void
{
    $stmt = db()->prepare('DELETE FROM triz_ki_verlauf WHERE benutzer_id = ?');
    $stmt->execute([$benutzer_id]);
}

/**
 * Wandelt die Antwort in HTML.
 *
 * Erst wird alles escapt, danach werden genau die Auszeichnungen wieder
 * eingesetzt, die das Modell üblicherweise nutzt: Überschriften, Listen,
 * Fettdruck, Code. Andersherum - erst Markup, dann escapen - würde die
 * eigenen Tags gleich wieder unschädlich machen; ohne Escaping wäre die
 * Antwort ein Einfallstor für eingeschleustes HTML.
 */
function triz_ki_html(string $text): string
{
    $zeilen = preg_split('/\R/u', $text) ?: [];
    $aus = [];
    $liste = null;   // null, 'ul' oder 'ol'

    $inline = static function (string $z): string {
        $s = e($z);
        $s = (string)preg_replace('/`([^`]+)`/u', '<code>$1</code>', $s);
        $s = (string)preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $s);
        $s = (string)preg_replace('/(?<![\p{L}\d*])\*([^*\n]+)\*(?![\p{L}\d*])/u', '<em>$1</em>', $s);
        return $s;
    };
    $liste_schliessen = static function () use (&$liste, &$aus): void {
        if ($liste !== null) {
            $aus[] = "</{$liste}>";
            $liste = null;
        }
    };

    foreach ($zeilen as $zeile) {
        $z = rtrim($zeile);
        $roh = ltrim($z);

        if ($roh === '') {
            $liste_schliessen();
            continue;
        }
        if (preg_match('/^#{1,6}\s+(.*)$/u', $roh, $t)) {
            $liste_schliessen();
            $aus[] = '<h3>' . $inline($t[1]) . '</h3>';
            continue;
        }
        if (preg_match('/^[-*•]\s+(.*)$/u', $roh, $t)) {
            if ($liste !== 'ul') {
                $liste_schliessen();
                $aus[] = '<ul>';
                $liste = 'ul';
            }
            $aus[] = '<li>' . $inline($t[1]) . '</li>';
            continue;
        }
        if (preg_match('/^(\d{1,2})[.)]\s+(.*)$/u', $roh, $t)) {
            if ($liste !== 'ol') {
                $liste_schliessen();
                $aus[] = '<ol>';
                $liste = 'ol';
            }
            $aus[] = '<li>' . $inline($t[2]) . '</li>';
            continue;
        }
        $liste_schliessen();
        $aus[] = '<p>' . $inline($roh) . '</p>';
    }
    $liste_schliessen();

    return implode("\n", $aus);
}

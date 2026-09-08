<?php
/**
 * Projekt-Dashboard: gemeinsame Funktionen.
 *
 * Laden der Projekte samt letzter Wochenmeldung, Soll/Ist-Bewertung mit
 * Ampeln, Kalenderwochen, Zahlenformate und der KWP-Import (eingefügter
 * Text, CSV, XLSX).
 *
 * Die Rechenregeln stammen aus der Excel-Vorlage
 * "Projekt-KPI-Dashboard_elektromas.xlsx":
 *   Sollstunden   = kalkulierte Stunden × Fertigstellungsgrad
 *   Abweichung    = Iststunden − Sollstunden
 *   Abweichung %  = Abweichung / Sollstunden
 *   Ampel Stunden = grün bis 5 %, gelb bis 15 %, sonst rot (Schwellen einstellbar)
 *   Ampel Termin  = Im Plan grün, Verzögert gelb, Kritisch rot
 *   Gesamt-Ampel  = die schlechtere der beiden
 */

declare(strict_types=1);

// --- Feste Listen ----------------------------------------------------------

const TERMINSTATUS = [
    'im_plan'    => 'Im Plan',
    'verzoegert' => 'Verzögert',
    'kritisch'   => 'Kritisch',
];

const MATERIALSTATUS = [
    'bestellt'     => 'Bestellt',
    'geliefert'    => 'Geliefert',
    'vollstaendig' => 'Vollständig',
    'fehlt'        => 'Fehlt / Verzug',
];

const PHASEN = [
    'laufend'       => 'Laufend',
    'angebot'       => 'Angebot / noch nicht vergeben',
    'abgeschlossen' => 'Abgeschlossen',
];

const AMPEL_TEXT = [
    'gruen' => 'Grün',
    'gelb'  => 'Gelb',
    'rot'   => 'Rot',
    'keine' => 'Keine Bewertung',
];

// --- Einstellungen und Projektleiter ---------------------------------------

/** Schwellenwerte der Stunden-Ampel als Anteil (0.05 = 5 %). */
function ampel_schwellen(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $werte = ['ampel_gruen_bis' => 5.0, 'ampel_gelb_bis' => 15.0];
    try {
        $rows = db()->query('SELECT schluessel, wert FROM projekt_einstellungen')->fetchAll();
        foreach ($rows as $r) {
            if (isset($werte[$r['schluessel']]) && is_numeric($r['wert'])) {
                $werte[$r['schluessel']] = (float)$r['wert'];
            }
        }
    } catch (Throwable $e) {
        error_log('Einstellungen nicht lesbar: ' . $e->getMessage());
    }
    return $cache = [
        'gruen_bis' => $werte['ampel_gruen_bis'] / 100,
        'gelb_bis'  => $werte['ampel_gelb_bis'] / 100,
    ];
}

/** Kürzel => Name. Mit $nur_aktive = false auch die stillgelegten. */
function projektleiter_liste(bool $nur_aktive = true): array
{
    $sql = 'SELECT kuerzel, name FROM projektleiter'
         . ($nur_aktive ? ' WHERE aktiv = 1' : '')
         . ' ORDER BY name';
    $liste = [];
    foreach (db()->query($sql)->fetchAll() as $r) {
        $liste[$r['kuerzel']] = $r['name'];
    }
    return $liste;
}

/** Name zum Kürzel; unbekannte Kürzel kommen unverändert zurück. */
function projektleiter_name(string $kuerzel, ?array $liste = null): string
{
    if ($kuerzel === '') {
        return '';
    }
    $liste ??= projektleiter_liste(false);
    return $liste[$kuerzel] ?? $kuerzel;
}

// --- Kalenderwochen --------------------------------------------------------

/** Aktuelle ISO-Woche, z. B. "2026-W36". */
function kw_aktuell(): string
{
    return date('o-\WW');
}

/** "2026-W36" -> "KW 36/2026" */
function kw_label(string $kw): string
{
    if (preg_match('/^(\d{4})-W(\d{2})$/', $kw, $m)) {
        return 'KW ' . (int)$m[2] . '/' . $m[1];
    }
    return $kw;
}

/** Montag und Sonntag einer ISO-Woche als DateTimeImmutable, oder null. */
function kw_zeitraum(string $kw): ?array
{
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $kw, $m)) {
        return null;
    }
    $montag = (new DateTimeImmutable())->setISODate((int)$m[1], (int)$m[2]);
    return [$montag, $montag->modify('+6 days')];
}

/** Die aktuelle Woche und die $anzahl Wochen davor, neueste zuerst. */
function kw_auswahl(int $anzahl = 8): array
{
    $liste = [];
    $d = new DateTimeImmutable('monday this week');
    for ($i = 0; $i <= $anzahl; $i++) {
        $liste[] = $d->format('o-\WW');
        $d = $d->modify('-7 days');
    }
    return $liste;
}

function kw_gueltig(string $kw): bool
{
    return (bool)preg_match('/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/', $kw);
}

// --- Formate ---------------------------------------------------------------

function zahl(?float $v, int $dezimalen = 0): string
{
    if ($v === null) {
        return '–';
    }
    return number_format($v, $dezimalen, ',', '.');
}

function euro(?float $v): string
{
    return $v === null ? '–' : number_format($v, 0, ',', '.') . ' €';
}

function prozent(?float $anteil, int $dezimalen = 0): string
{
    if ($anteil === null) {
        return '–';
    }
    $s = number_format($anteil * 100, $dezimalen, ',', '.');
    return ($anteil > 0 ? '+' : '') . $s . ' %';
}

function datum_de(?string $iso): string
{
    if (!$iso || $iso === '0000-00-00') {
        return '–';
    }
    $t = strtotime($iso);
    return $t === false ? $iso : date('d.m.Y', $t);
}

/**
 * Zahl aus einer Eingabe. Versteht "1.234,56", "1234.56", "1 234,5" und
 * leere Eingaben (null). Unlesbares ergibt ebenfalls null.
 */
function zahl_lesen(mixed $roh): ?float
{
    if ($roh === null) {
        return null;
    }
    if (is_int($roh) || is_float($roh)) {
        return (float)$roh;
    }
    $s = trim((string)$roh);
    $s = str_replace(['€', ' ', "\u{a0}", 'h', 'Std.', 'Std'], '', $s);
    if ($s === '' || $s === '-' || $s === '–') {
        return null;
    }
    // Deutsches Format: Punkt als Tausender, Komma als Dezimaltrenner.
    if (str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
        // "12.345" ohne Komma: Tausenderpunkte
        $s = str_replace('.', '', $s);
    }
    return is_numeric($s) ? (float)$s : null;
}

/** Datum aus "02.09.2026 08:23", "2026-09-02" oder Excel-Seriennummer. */
function datum_lesen(mixed $roh): ?string
{
    if ($roh === null) {
        return null;
    }
    if (is_int($roh) || is_float($roh)) {
        // Excel zählt Tage seit dem 30.12.1899.
        if ($roh < 20000 || $roh > 80000) {
            return null;
        }
        return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int)$roh . ' days')->format('Y-m-d');
    }
    $s = trim((string)$roh);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})/', $s, $m)) {
        $jahr = (int)$m[3];
        if ($jahr < 100) {
            $jahr += 2000;
        }
        if (!checkdate((int)$m[2], (int)$m[1], $jahr)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $jahr, (int)$m[2], (int)$m[1]);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;
    }
    return null;
}

// --- Bewertung -------------------------------------------------------------

/**
 * Berechnet Soll, Abweichung und die drei Ampeln für ein Projekt.
 *
 * $status ist die letzte Wochenmeldung (oder null). Ohne Kalkulation oder
 * ohne Fertigstellungsgrad gibt es keine Stundenbewertung - anders als in
 * der Excel-Vorlage, die dann still "grün" zeigte.
 */
function projekt_bewerten(?float $kalk, ?array $status): array
{
    $schwellen = ampel_schwellen();

    $ist    = $status !== null && $status['ist_stunden'] !== null ? (float)$status['ist_stunden'] : null;
    $fertig = $status !== null && $status['fertig_prozent'] !== null ? (float)$status['fertig_prozent'] : null;

    $soll = null;
    $abw = null;
    $abw_prozent = null;
    $ampel_std = 'keine';

    if ($kalk !== null && $fertig !== null) {
        $soll = $kalk * $fertig / 100;
        if ($ist !== null) {
            $abw = $ist - $soll;
            if ($soll > 0) {
                $abw_prozent = $abw / $soll;
                if ($abw_prozent <= $schwellen['gruen_bis']) {
                    $ampel_std = 'gruen';
                } elseif ($abw_prozent <= $schwellen['gelb_bis']) {
                    $ampel_std = 'gelb';
                } else {
                    $ampel_std = 'rot';
                }
            }
        }
    }

    $ampel_termin = 'keine';
    if ($status !== null) {
        $ampel_termin = match ($status['terminstatus'] ?? null) {
            'im_plan'    => 'gruen',
            'verzoegert' => 'gelb',
            'kritisch'   => 'rot',
            default      => 'keine',
        };
    }

    $rang = ['keine' => 0, 'gruen' => 1, 'gelb' => 2, 'rot' => 3];
    $gesamt = $rang[$ampel_std] >= $rang[$ampel_termin] ? $ampel_std : $ampel_termin;

    return [
        'ist'          => $ist,
        'fertig'       => $fertig,
        'soll'         => $soll,
        'abw'          => $abw,
        'abw_prozent'  => $abw_prozent,
        'ampel_std'    => $ampel_std,
        'ampel_termin' => $ampel_termin,
        'gesamt'       => $gesamt,
        'rang'         => $rang[$gesamt],
    ];
}

// --- Laden -----------------------------------------------------------------

const STATUS_SPALTEN = [
    'kw', 'ist_stunden', 'fertig_prozent', 'terminstatus', 'materialstatus',
    'maengel_offen', 'nachtraege_offen', 'nachtraege_genehmigt',
    'meilenstein', 'meilenstein_datum', 'kommentar', 'benutzer_id', 'erfasst_am',
];

/**
 * Projekte mit ihrer jeweils letzten Wochenmeldung und der Bewertung.
 *
 * $filter: phase (laufend|angebot|abgeschlossen|alle), projektleiter (Kürzel).
 * Jede Zeile: alle Spalten aus projekte, dazu 'status' (Array oder null),
 * 'bewertung' und 'pl_name'.
 */
function projekte_laden(array $filter = []): array
{
    $where = [];
    $param = [];

    $phase = $filter['phase'] ?? 'laufend';
    if ($phase !== 'alle' && isset(PHASEN[$phase])) {
        $where[] = 'p.phase = ?';
        $param[] = $phase;
    }
    if (!empty($filter['projektleiter'])) {
        $where[] = 'p.projektleiter = ?';
        $param[] = $filter['projektleiter'];
    }

    $status_select = implode(', ', array_map(static fn($s) => "s.$s AS s_$s", STATUS_SPALTEN));

    $sql = "SELECT p.*, $status_select
            FROM projekte p
            LEFT JOIN projekt_status s
                   ON s.projekt_id = p.id
                  AND s.kw = (SELECT MAX(kw) FROM projekt_status WHERE projekt_id = p.id)"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY p.nummer DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($param);

    $pl = projektleiter_liste(false);
    $liste = [];
    foreach ($stmt->fetchAll() as $row) {
        $status = null;
        if ($row['s_kw'] !== null) {
            $status = [];
            foreach (STATUS_SPALTEN as $s) {
                $status[$s] = $row["s_$s"];
            }
        }
        foreach (STATUS_SPALTEN as $s) {
            unset($row["s_$s"]);
        }
        $row['kalk_stunden']  = $row['kalk_stunden'] !== null ? (float)$row['kalk_stunden'] : null;
        $row['auftragssumme'] = $row['auftragssumme'] !== null ? (float)$row['auftragssumme'] : null;
        $row['status']    = $status;
        $row['bewertung'] = projekt_bewerten($row['kalk_stunden'], $status);
        $row['pl_name']   = projektleiter_name((string)$row['projektleiter'], $pl);
        $liste[] = $row;
    }
    return $liste;
}

/** Ein Projekt nach id, ohne Meldung. */
function projekt_laden(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM projekte WHERE id = ?');
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) {
        return null;
    }
    $p['kalk_stunden']  = $p['kalk_stunden'] !== null ? (float)$p['kalk_stunden'] : null;
    $p['auftragssumme'] = $p['auftragssumme'] !== null ? (float)$p['auftragssumme'] : null;
    return $p;
}

/** Alle Wochenmeldungen eines Projekts, neueste zuerst. */
function meldungen_laden(int $projekt_id): array
{
    $stmt = db()->prepare(
        'SELECT s.*, b.name AS benutzer_name, b.email AS benutzer_email
         FROM projekt_status s
         LEFT JOIN benutzer b ON b.id = s.benutzer_id
         WHERE s.projekt_id = ?
         ORDER BY s.kw DESC'
    );
    $stmt->execute([$projekt_id]);
    return $stmt->fetchAll();
}

/**
 * Sortierung fürs Dashboard: Rot zuerst, dann Gelb, Grün, ohne Bewertung;
 * innerhalb davon die größte prozentuale Abweichung zuerst.
 */
function projekte_sortieren(array &$liste): void
{
    usort($liste, static function (array $a, array $b): int {
        $ra = $a['bewertung']['rang'];
        $rb = $b['bewertung']['rang'];
        if ($ra !== $rb) {
            return $rb <=> $ra;
        }
        $pa = $a['bewertung']['abw_prozent'] ?? -INF;
        $pb = $b['bewertung']['abw_prozent'] ?? -INF;
        if ($pa !== $pb) {
            return $pb <=> $pa;
        }
        return strcmp((string)$b['nummer'], (string)$a['nummer']);
    });
}

// --- Import aus KWP --------------------------------------------------------

/**
 * Text (eingefügt oder CSV-Datei) in Zeilen aus Feldern zerlegen.
 * Trenner wird erkannt: Tabulator (Zwischenablage aus KWP), Semikolon
 * (deutsches CSV) oder Komma.
 */
function import_text_zerlegen(string $text): array
{
    // Byte-Order-Mark und Windows-Zeilenenden
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $zeilen = array_values(array_filter(explode("\n", $text), static fn($z) => trim($z) !== ''));
    if (!$zeilen) {
        return [];
    }

    $probe = implode("\n", array_slice($zeilen, 0, 5));
    $trenner = "\t";
    if (substr_count($probe, "\t") === 0) {
        $trenner = substr_count($probe, ';') >= substr_count($probe, ',') ? ';' : ',';
    }

    $rows = [];
    foreach ($zeilen as $z) {
        $felder = str_getcsv($z, $trenner, '"', '\\');
        $rows[] = array_map(static fn($f) => trim((string)$f), $felder);
    }
    return $rows;
}

/**
 * Erstes Tabellenblatt einer XLSX-Datei lesen. Ohne Fremdbibliothek: eine
 * XLSX ist ein ZIP mit XML-Dateien. Zahlen kommen als float, Texte als string.
 */
function xlsx_lesen(string $pfad): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Der Server kann keine XLSX-Dateien lesen (ZipArchive fehlt). Bitte als CSV speichern.');
    }
    $zip = new ZipArchive();
    if ($zip->open($pfad) !== true) {
        throw new RuntimeException('Die Datei ist keine gültige XLSX-Datei.');
    }

    $xml_laden = static function (string $name) use ($zip): ?SimpleXMLElement {
        $inhalt = $zip->getFromName($name);
        if ($inhalt === false) {
            return null;
        }
        $vorher = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($inhalt);
        libxml_use_internal_errors($vorher);
        return $xml === false ? null : $xml;
    };

    // Erstes Blatt über workbook.xml und die Beziehungen finden.
    $blatt_pfad = 'xl/worksheets/sheet1.xml';
    $wb = $xml_laden('xl/workbook.xml');
    $rels = $xml_laden('xl/_rels/workbook.xml.rels');
    if ($wb !== null && $rels !== null && isset($wb->sheets->sheet[0])) {
        $rid = (string)$wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
        foreach ($rels->Relationship as $rel) {
            if ((string)$rel['Id'] === $rid) {
                $ziel = (string)$rel['Target'];
                $blatt_pfad = str_starts_with($ziel, '/') ? ltrim($ziel, '/') : 'xl/' . $ziel;
                break;
            }
        }
    }

    $shared = [];
    $ss = $xml_laden('xl/sharedStrings.xml');
    if ($ss !== null) {
        foreach ($ss->si as $si) {
            // Ein Eintrag kann aus mehreren formatierten Teilstücken bestehen.
            $teile = [];
            foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $t) {
                $teile[] = (string)$t;
            }
            $shared[] = implode('', $teile);
        }
    }

    $blatt = $xml_laden($blatt_pfad);
    $zip->close();
    if ($blatt === null) {
        throw new RuntimeException('Das Tabellenblatt konnte nicht gelesen werden.');
    }

    $spalten_index = static function (string $ref): int {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $n = 0;
        foreach (str_split($m[1] ?? 'A') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    };

    $rows = [];
    foreach ($blatt->sheetData->row as $row) {
        $werte = [];
        foreach ($row->c as $c) {
            $idx = $spalten_index((string)$c['r']);
            $typ = (string)$c['t'];
            $wert = null;
            if ($typ === 's') {
                $wert = $shared[(int)$c->v] ?? '';
            } elseif ($typ === 'inlineStr') {
                $wert = (string)$c->is->t;
            } elseif ($typ === 'b') {
                $wert = (string)$c->v === '1' ? 'ja' : 'nein';
            } elseif (isset($c->v)) {
                $v = (string)$c->v;
                $wert = is_numeric($v) ? (float)$v : $v;
            }
            $werte[$idx] = $wert;
        }
        if (!$werte) {
            continue;
        }
        $max = max(array_keys($werte));
        $zeile = [];
        for ($i = 0; $i <= $max; $i++) {
            $zeile[] = $werte[$i] ?? null;
        }
        $rows[] = $zeile;
    }
    return $rows;
}

/** Spaltenüberschrift auf Kleinbuchstaben und Buchstaben/Ziffern eindampfen. */
function import_kopf_normieren(mixed $s): string
{
    $s = mb_strtolower(trim((string)$s));
    $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);
    return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
}

/**
 * Erkennt die Spalten und liefert je Zeile ein einheitliches Feld-Array.
 *
 * Verstanden werden zwei KWP-Ausgaben:
 *   1. Projektliste  – Projekt-Nr, Projekt-Bezeichnung, Anlagedatum, Status,
 *                      Zustand, Sachbearb., Auftraggeber
 *   2. Kalkulation   – Auftrag ("P260038 Minigolfanlage"), Auftragszeit,
 *                      Auftragssumme
 * sowie eine Tabelle mit eigenen Überschriften (Nummer, Bezeichnung,
 * Projektleiter, Kalk. Std, Auftragssumme, ...).
 *
 * Rückgabe: ['format' => ..., 'zeilen' => [...], 'hinweise' => [...]]
 * Jede Zeile: nummer, bezeichnung, projektleiter, auftraggeber, anlagedatum,
 * kwp_status, kwp_zustand, kalk_stunden, auftragssumme - nicht erkannte
 * Felder fehlen (null).
 */
function import_zuordnen(array $rows): array
{
    $hinweise = [];
    if (!$rows) {
        return ['format' => 'leer', 'zeilen' => [], 'hinweise' => ['Keine Daten gefunden.']];
    }

    // Bekannte Überschriften -> Zielfeld
    $bekannt = [
        'projektnr' => 'nummer', 'projektnummer' => 'nummer', 'nummer' => 'nummer', 'nr' => 'nummer',
        'projektbezeichnung' => 'bezeichnung', 'bezeichnung' => 'bezeichnung', 'projektname' => 'bezeichnung', 'projekt' => 'bezeichnung',
        'auftrag' => 'auftrag',
        'anlagedatum' => 'anlagedatum',
        'status' => 'kwp_status',
        'zustand' => 'kwp_zustand',
        'sachbearb' => 'projektleiter', 'sachbearbeiter' => 'projektleiter', 'sb' => 'projektleiter', 'projektleiter' => 'projektleiter', 'pl' => 'projektleiter',
        'auftraggeber' => 'auftraggeber', 'kunde' => 'auftraggeber',
        'auftragszeit' => 'kalk_stunden', 'kalkstd' => 'kalk_stunden', 'kalkstdlv' => 'kalk_stunden',
        'kalkstunden' => 'kalk_stunden', 'kalkuliertestunden' => 'kalk_stunden', 'stunden' => 'kalk_stunden',
        'auftragssumme' => 'auftragssumme', 'summe' => 'auftragssumme', 'auftragswert' => 'auftragssumme',
    ];

    // Kopfzeile suchen: die erste Zeile, in der mindestens zwei bekannte
    // Überschriften vorkommen. KWP-Kalkulationslisten haben eine Titelzeile
    // davor ("Auftrag erhalten").
    $kopf_index = null;
    $zuordnung = [];
    foreach (array_slice($rows, 0, 5, true) as $i => $row) {
        $treffer = [];
        foreach ($row as $spalte => $zelle) {
            $n = import_kopf_normieren($zelle);
            if ($n === '') {
                continue;
            }
            $ziel = $bekannt[$n] ?? null;
            if ($ziel === null && str_starts_with($n, 'sachbearb')) {
                $ziel = 'projektleiter';
            }
            if ($ziel !== null && !in_array($ziel, $treffer, true)) {
                $treffer[$spalte] = $ziel;
            }
        }
        if (count($treffer) >= 2) {
            $kopf_index = $i;
            $zuordnung = $treffer;
            break;
        }
    }

    $format = 'eigene';
    if ($kopf_index === null) {
        // Keine Überschriften: Aus der KWP-Liste ohne Kopf kopiert? Dann gilt
        // die Spaltenfolge der Projektliste, erkennbar an "P2600.." vorn.
        $erste = $rows[0][0] ?? '';
        if (is_string($erste) && preg_match('/^[A-Z]{1,3}\d{2,}/', $erste)) {
            $zuordnung = [0 => 'nummer', 1 => 'bezeichnung', 2 => 'anlagedatum', 3 => 'kwp_status',
                          4 => 'kwp_zustand', 10 => 'projektleiter', 11 => 'auftraggeber'];
            $kopf_index = -1;
            $format = 'kwp_liste_ohne_kopf';
            $hinweise[] = 'Keine Überschriften gefunden – es wurde die Spaltenfolge der KWP-Projektliste angenommen (Nr, Bezeichnung, Anlagedatum, Status, Zustand, …, Sachbearbeiter, Auftraggeber). Bitte die Vorschau prüfen.';
        } else {
            return ['format' => 'unbekannt', 'zeilen' => [], 'hinweise' => [
                'Die Spalten wurden nicht erkannt. Erwartet wird eine Kopfzeile mit z. B. "Projekt-Nr" und "Projekt-Bezeichnung" (KWP-Projektliste) oder "Auftrag", "Auftragszeit" und "Auftragssumme" (KWP-Kalkulation).',
            ]];
        }
    } elseif (in_array('auftrag', $zuordnung, true)) {
        $format = 'kwp_kalkulation';
    } elseif (in_array('kwp_zustand', $zuordnung, true) || in_array('anlagedatum', $zuordnung, true)) {
        $format = 'kwp_liste';
    }

    // Die Kalkulationsliste hat rechts daneben oft einen zweiten Block
    // ("Auftrag noch nicht vergeben") mit denselben Überschriften. Der wird
    // mitgelesen, weil er die Angebote enthält.
    $bloecke = [$zuordnung];
    if ($format === 'kwp_kalkulation' && $kopf_index >= 0) {
        $kopf = $rows[$kopf_index];
        $erster_auftrag = array_search('auftrag', $zuordnung, true);
        foreach ($kopf as $spalte => $zelle) {
            if ($spalte !== $erster_auftrag && import_kopf_normieren($zelle) === 'auftrag') {
                $bloecke[] = [$spalte => 'auftrag', $spalte + 1 => 'kalk_stunden', $spalte + 2 => 'auftragssumme'];
            }
        }
        // Zustand je Block aus der Titelzeile darüber ("Auftrag erhalten" /
        // "Auftrag noch nicht vergeben"), falls vorhanden.
        $titel = $kopf_index > 0 ? $rows[$kopf_index - 1] : [];
        foreach ($bloecke as $bi => $block) {
            $spalte = array_search('auftrag', $block, true);
            $t = mb_strtolower((string)($titel[$spalte] ?? ''));
            $bloecke[$bi]['_zustand'] = str_contains($t, 'nicht vergeben') ? 'Auftrag noch nicht vergeben'
                                     : (str_contains($t, 'erhalten') ? 'Auftrag erhalten' : '');
        }
    }

    $zeilen = [];
    foreach (array_slice($rows, $kopf_index + 1) as $row) {
        foreach ($bloecke as $block) {
            $z = [];
            foreach ($block as $spalte => $ziel) {
                if ($ziel === '_zustand') {
                    continue;
                }
                $wert = $row[$spalte] ?? null;
                if ($wert === null || (is_string($wert) && trim($wert) === '')) {
                    continue;
                }
                $z[$ziel] = is_string($wert) ? trim($wert) : $wert;
            }
            if (isset($z['auftrag'])) {
                // "P260038 Minigolfanlage" -> Nummer + Bezeichnung
                if (preg_match('/^([A-Za-z]{1,3}\d{3,}[A-Za-z0-9\-]*)\s+(.+)$/u', (string)$z['auftrag'], $m)) {
                    $z['nummer'] = $m[1];
                    $z['bezeichnung'] = $m[2];
                } elseif (preg_match('/^[A-Za-z]{1,3}\d{3,}/', (string)$z['auftrag'])) {
                    $z['nummer'] = (string)$z['auftrag'];
                }
                unset($z['auftrag']);
                if (isset($block['_zustand']) && $block['_zustand'] !== '') {
                    $z['kwp_zustand'] = $block['_zustand'];
                }
            }
            if (empty($z['nummer'])) {
                continue;
            }
            $z['nummer'] = strtoupper(trim((string)$z['nummer']));
            // Summenzeilen und Freitext ("Ohne KD", "Ist Stunden") aussortieren.
            if (!preg_match('/^[A-Z]{1,3}\d{2,}/', $z['nummer'])) {
                continue;
            }
            if (isset($z['kalk_stunden'])) {
                $z['kalk_stunden'] = zahl_lesen($z['kalk_stunden']);
            }
            if (isset($z['auftragssumme'])) {
                $z['auftragssumme'] = zahl_lesen($z['auftragssumme']);
            }
            if (isset($z['anlagedatum'])) {
                $z['anlagedatum'] = datum_lesen($z['anlagedatum']);
            }
            foreach (['bezeichnung', 'projektleiter', 'auftraggeber', 'kwp_status', 'kwp_zustand'] as $f) {
                if (isset($z[$f])) {
                    $z[$f] = trim((string)$z[$f]);
                }
            }
            if (isset($z['projektleiter'])) {
                $z['projektleiter'] = strtoupper(mb_substr($z['projektleiter'], 0, 10));
            }
            $zeilen[] = $z;
        }
    }

    if (!$zeilen) {
        $hinweise[] = 'Es wurden keine Projektzeilen gefunden (Projektnummern wie P260104 erwartet).';
    }
    return ['format' => $format, 'zeilen' => $zeilen, 'hinweise' => $hinweise];
}

/** Phase aus dem KWP-Zustand. null = nicht ableitbar. */
function phase_aus_zustand(?string $zustand): ?string
{
    if ($zustand === null || $zustand === '') {
        return null;
    }
    $z = mb_strtolower($zustand);
    if (str_contains($z, 'nicht vergeben') || str_contains($z, 'angebot')) {
        return 'angebot';
    }
    if (str_contains($z, 'erhalten') || str_contains($z, 'zugesagt') || str_contains($z, 'eingegangen')) {
        return 'laufend';
    }
    if (str_contains($z, 'abgeschlossen') || str_contains($z, 'erledigt') || str_contains($z, 'fertig')) {
        return 'abgeschlossen';
    }
    return null;
}

/**
 * Vergleicht Importzeilen mit dem Bestand und plant je Zeile die Aktion.
 * Ergebnis je Zeile: ['aktion' => neu|aktualisieren|unveraendert|uebersprungen,
 *                     'daten' => ..., 'aenderungen' => [feld => [alt, neu]], 'grund' => ...]
 */
function import_planen(array $zeilen, bool $sammel_ueberspringen = true): array
{
    $bestand = [];
    foreach (db()->query('SELECT * FROM projekte')->fetchAll() as $p) {
        $bestand[$p['nummer']] = $p;
    }

    $plan = [];
    $gesehen = [];
    foreach ($zeilen as $z) {
        $nr = $z['nummer'];
        if (isset($gesehen[$nr])) {
            $plan[] = ['aktion' => 'uebersprungen', 'daten' => $z, 'aenderungen' => [], 'grund' => 'Nummer kommt doppelt vor'];
            continue;
        }
        $gesehen[$nr] = true;

        if ($sammel_ueberspringen && str_starts_with($nr, 'X')) {
            $plan[] = ['aktion' => 'uebersprungen', 'daten' => $z, 'aenderungen' => [], 'grund' => 'Sammelprojekt'];
            continue;
        }

        $alt = $bestand[$nr] ?? null;
        if ($alt === null) {
            $plan[] = ['aktion' => 'neu', 'daten' => $z, 'aenderungen' => [], 'grund' => ''];
            continue;
        }

        $aenderungen = [];
        foreach (['bezeichnung', 'projektleiter', 'auftraggeber', 'anlagedatum', 'kwp_status', 'kwp_zustand', 'kalk_stunden', 'auftragssumme'] as $f) {
            if (!array_key_exists($f, $z) || $z[$f] === null || $z[$f] === '') {
                continue;
            }
            $vorher = $alt[$f];
            $nachher = $z[$f];
            if (in_array($f, ['kalk_stunden', 'auftragssumme'], true)) {
                $vorher = $vorher === null ? null : round((float)$vorher, 2);
                $nachher = round((float)$nachher, 2);
                if ($vorher !== null && abs($vorher - $nachher) < 0.005) {
                    continue;
                }
            } elseif ((string)$vorher === (string)$nachher) {
                continue;
            }
            $aenderungen[$f] = [$vorher, $nachher];
        }

        $neue_phase = phase_aus_zustand($z['kwp_zustand'] ?? null);
        if ($neue_phase !== null && $alt['phase'] !== 'abgeschlossen' && $neue_phase !== $alt['phase']) {
            $aenderungen['phase'] = [$alt['phase'], $neue_phase];
        }

        $plan[] = [
            'aktion'      => $aenderungen ? 'aktualisieren' : 'unveraendert',
            'daten'       => $z,
            'aenderungen' => $aenderungen,
            'grund'       => '',
            'id'          => (int)$alt['id'],
        ];
    }
    return $plan;
}

/** Führt einen geplanten Import aus. Rückgabe: Zähler je Aktion. */
function import_ausfuehren(array $plan): array
{
    $zaehler = ['neu' => 0, 'aktualisieren' => 0, 'unveraendert' => 0, 'uebersprungen' => 0];
    $db = db();
    $db->beginTransaction();
    try {
        foreach ($plan as $eintrag) {
            $z = $eintrag['daten'];
            $zaehler[$eintrag['aktion']]++;

            if ($eintrag['aktion'] === 'neu') {
                $phase = phase_aus_zustand($z['kwp_zustand'] ?? null) ?? 'laufend';
                $ins = $db->prepare(
                    'INSERT INTO projekte (nummer, bezeichnung, projektleiter, auftraggeber, anlagedatum,
                                           kwp_status, kwp_zustand, kalk_stunden, auftragssumme, phase)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $z['nummer'],
                    mb_substr((string)($z['bezeichnung'] ?? $z['nummer']), 0, 255),
                    mb_substr((string)($z['projektleiter'] ?? ''), 0, 10),
                    mb_substr((string)($z['auftraggeber'] ?? ''), 0, 190),
                    $z['anlagedatum'] ?? null,
                    mb_substr((string)($z['kwp_status'] ?? ''), 0, 80),
                    mb_substr((string)($z['kwp_zustand'] ?? ''), 0, 80),
                    $z['kalk_stunden'] ?? null,
                    $z['auftragssumme'] ?? null,
                    $phase,
                ]);
            } elseif ($eintrag['aktion'] === 'aktualisieren') {
                $sets = [];
                $werte = [];
                $laengen = ['bezeichnung' => 255, 'projektleiter' => 10, 'auftraggeber' => 190, 'kwp_status' => 80, 'kwp_zustand' => 80];
                foreach ($eintrag['aenderungen'] as $feld => [$vorher, $nachher]) {
                    $sets[] = "$feld = ?";
                    $werte[] = isset($laengen[$feld]) ? mb_substr((string)$nachher, 0, $laengen[$feld]) : $nachher;
                }
                $werte[] = $eintrag['id'];
                $up = $db->prepare('UPDATE projekte SET ' . implode(', ', $sets) . ' WHERE id = ?');
                $up->execute($werte);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return $zaehler;
}

<?php
/**
 * Dashboard als CSV (Semikolon, UTF-8 mit BOM - öffnet sich in Excel direkt
 * mit Umlauten und deutschen Zahlen). Dieselben Filter wie das Dashboard.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/projekte.php';

sicherheits_header();
$benutzer = login_verlangen();

$phase = (string)($_GET['phase'] ?? 'laufend');
if ($phase !== 'alle' && !isset(PHASEN[$phase])) {
    $phase = 'laufend';
}
$pl_filter = (string)($_GET['pl'] ?? '');

$projekte = projekte_laden(['phase' => $phase, 'projektleiter' => $pl_filter]);
projekte_sortieren($projekte);

$kw = kw_aktuell();
$dateiname = 'Projekt-Dashboard_' . preg_replace('/^(\d{4})-W(\d{2})$/', 'KW$2-$1', $kw) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $dateiname . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

$csv_zahl = static fn(?float $v, int $dez = 2): string => $v === null ? '' : number_format($v, $dez, ',', '');

fputcsv($out, [
    'Projekt-Nr.', 'Projektname', 'Projektleiter', 'Auftraggeber', 'Phase',
    'Kalk. Std. (LV)', 'Iststunden bisher', 'Fertigstellungsgrad %', 'Sollstunden bis heute',
    'Abweichung Std.', 'Abweichung %', 'Ampel Stunden', 'Terminstatus', 'Ampel Termin',
    'Nächster Meilenstein', 'Meilenstein-Datum', 'Materialstatus', 'Offene Mängel (Anz.)',
    'Nachträge offen (€)', 'Nachträge genehmigt (€)', 'Gesamt-Ampel', 'Kurzkommentar',
    'Letzte Meldung (KW)', 'Auftragssumme', 'KWP-Zustand',
], ';', '"', '\\');

foreach ($projekte as $p) {
    $b = $p['bewertung'];
    $s = $p['status'];
    fputcsv($out, [
        $p['nummer'],
        $p['bezeichnung'],
        $p['pl_name'],
        $p['auftraggeber'],
        PHASEN[$p['phase']] ?? $p['phase'],
        $csv_zahl($p['kalk_stunden']),
        $csv_zahl($b['ist']),
        $csv_zahl($b['fertig'], 1),
        $csv_zahl($b['soll']),
        $csv_zahl($b['abw']),
        $b['abw_prozent'] !== null ? $csv_zahl($b['abw_prozent'] * 100, 1) : '',
        AMPEL_TEXT[$b['ampel_std']],
        $s !== null && $s['terminstatus'] ? TERMINSTATUS[$s['terminstatus']] : '',
        AMPEL_TEXT[$b['ampel_termin']],
        $s['meilenstein'] ?? '',
        $s !== null && $s['meilenstein_datum'] ? datum_de($s['meilenstein_datum']) : '',
        $s !== null && $s['materialstatus'] ? MATERIALSTATUS[$s['materialstatus']] : '',
        $s !== null ? (string)(int)$s['maengel_offen'] : '',
        $s !== null ? $csv_zahl((float)$s['nachtraege_offen']) : '',
        $s !== null ? $csv_zahl((float)$s['nachtraege_genehmigt']) : '',
        AMPEL_TEXT[$b['gesamt']],
        $s['kommentar'] ?? '',
        $s !== null ? kw_label($s['kw']) : '',
        $csv_zahl($p['auftragssumme']),
        $p['kwp_zustand'],
    ], ';', '"', '\\');
}
fclose($out);

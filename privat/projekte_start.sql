-- ===========================================================================
-- Startdaten für das Projekt-Dashboard (optional)
--
-- Quelle: KWP-Kalkulationsübersicht vom 29.07.2026 (Auftragszeit = kalkulierte
-- Stunden, Auftragssumme) und die KWP-Projektliste vom 07.09.2026
-- (Projektleiter, Anlagedatum, Auftraggeber der Nummern P260089–P260104).
--
-- Nach schema_projekte.sql einspielen. Vorhandene Nummern werden nur in den
-- Feldern ergänzt, die hier gefüllt sind; Wochenmeldungen sind nicht betroffen.
-- Aktueller geht es über /Projekte/import.php direkt aus KWP.
-- ===========================================================================

SET NAMES utf8mb4;

INSERT INTO projekte (nummer, bezeichnung, projektleiter, auftraggeber, anlagedatum, kwp_zustand, kalk_stunden, auftragssumme, phase) VALUES
  ('P260006', 'Salzgitter Albert-Schweitzer-Str.', '', '', NULL, 'Auftrag noch nicht vergeben', 0.0, 0.0, 'angebot'),
  ('P260007', 'EDEKA Voigt Bosau', '', '', NULL, 'Auftrag erhalten', 2115.55, 292569.39, 'laufend'),
  ('P260008', 'Decathlon Rahmenvertrag', '', '', NULL, 'Auftrag erhalten', NULL, NULL, 'laufend'),
  ('P260010', 'BV Kaninchenborn 35', '', '', NULL, 'Auftrag erhalten', 261.07, 40485.58, 'laufend'),
  ('P260011', 'Lagerhut Objekte', '', '', NULL, 'Auftrag noch nicht vergeben', 386.54, 44526.74, 'angebot'),
  ('P260012', 'KiTa Robinson', '', '', NULL, 'Auftrag erhalten', 1624.81, 287188.05, 'laufend'),
  ('P260015', 'Erweiterung Ladeninfrastruktur ALDI BAR', '', '', NULL, 'Auftrag noch nicht vergeben', 42.34, 3009.54, 'angebot'),
  ('P260017', 'ALDI Flensburg', '', '', NULL, 'Auftrag erhalten', 221.34, 26549.41, 'laufend'),
  ('P260018', 'ALDI Hamburg', '', '', NULL, 'Auftrag erhalten', 925.86, 110298.14, 'laufend'),
  ('P260020', 'ALDI Wilster', '', '', NULL, 'Auftrag erhalten', 91.0, 5416.8, 'laufend'),
  ('P260021', 'EDEKA Großhansdorf', '', '', NULL, 'Auftrag erhalten', 72.78, 29508.02, 'laufend'),
  ('P260024', 'EDEKA Ziegelstr.', '', '', NULL, 'Auftrag erhalten', 80.48, 7200.44, 'laufend'),
  ('P260027', 'ALDI Ahrensbök', '', '', NULL, 'Auftrag erhalten', 880.11, 138436.73, 'laufend'),
  ('P260032', 'ALDI Kisdorf', '', '', NULL, 'Auftrag erhalten', 880.11, 138436.73, 'laufend'),
  ('P260033', 'Angebot Mängelbeseitigung 2.Wartung', '', '', NULL, 'Auftrag noch nicht vergeben', 0.75, 185.88, 'angebot'),
  ('P260035', 'Sanierung +Erweitunger Sportforum Halenreie', '', '', NULL, 'Auftrag erhalten', 3899.97, 661180.37, 'laufend'),
  ('P260038', 'Minigolfanlage', '', '', NULL, 'Auftrag erhalten', 270.85, 34946.11, 'laufend'),
  ('P260041', 'Kameras Zigarettenlager', '', '', NULL, 'Auftrag erhalten', 47.02, 6353.77, 'laufend'),
  ('P260042', 'Notausgang Piktrogramme', '', '', NULL, 'Auftrag erhalten', 0.0, 6839.44, 'laufend'),
  ('P260043', 'Klimaanlage', '', '', NULL, 'Auftrag noch nicht vergeben', 0.0, 5064.87, 'angebot'),
  ('P260048', 'Langzeitmessung Trafo', '', '', NULL, 'Auftrag erhalten', 0.0, 2684.88, 'laufend'),
  ('P260050', 'ALDI Kühlungsborn', '', '', NULL, 'Auftrag erhalten', 551.7, 102846.42, 'laufend'),
  ('P260052', 'ALDI Borsteler Chaussee 86', '', '', NULL, 'Auftrag noch nicht vergeben', 1083.84, 147453.9, 'angebot'),
  ('P260055', 'Errichtung Baustrom Sportforum Halenreie', '', '', NULL, 'Auftrag erhalten', 316.0, 24043.4, 'laufend'),
  ('P260056', 'BMA-Mängelbeseitigung', '', '', NULL, 'Auftrag erhalten', 480.0, 32311.7, 'laufend'),
  ('P260057', 'ALDI Paul-Dessau-Str. 6', '', '', NULL, 'Auftrag erhalten', 151.28, 13890.29, 'laufend'),
  ('P260058', 'Umbau Warenannahme ALDI ZL-NOR', '', '', NULL, 'Auftrag erhalten', 0.0, 0.0, 'laufend'),
  ('P260061', 'Am Stadtrand 29', '', '', NULL, 'Auftrag noch nicht vergeben', 1503.5, 255495.9, 'angebot'),
  ('P260062', 'BV Lüdwigstr. 37', '', '', NULL, 'Auftrag erhalten', 0.0, 0.0, 'laufend'),
  ('P260065', 'ALDI Laboe', '', '', NULL, 'Auftrag erhalten', 236.19, 20269.33, 'laufend'),
  ('P260068', 'DGUV3 Zentrale', '', '', NULL, 'Auftrag erhalten', 1.32, 150.58, 'laufend'),
  ('P260069', 'EDEKA Itzehoe', '', '', NULL, 'Auftrag noch nicht vergeben', 99.58, 38971.19, 'angebot'),
  ('P260070', 'ALDI Schleswiger Str. 130', '', '', NULL, 'Auftrag noch nicht vergeben', 953.74, 172011.33, 'angebot'),
  ('P260072', 'REWE Itzehoe', '', '', NULL, 'Auftrag noch nicht vergeben', 2102.42, 333039.28, 'angebot'),
  ('P260073', 'ALDI Itzehoe', '', '', NULL, 'Auftrag noch nicht vergeben', 880.11, 137495.1, 'angebot'),
  ('P260074', 'Austausch RWA Zentrale', '', '', NULL, 'Auftrag erhalten', 58.17, 10470.62, 'laufend'),
  ('P260075', 'E-Ladesäule LKW', '', '', NULL, 'Auftrag noch nicht vergeben', 725.07, 143937.27, 'angebot'),
  ('P260076', 'Garage Aldermannweg', '', '', NULL, 'Auftrag erhalten', 0.0, 0.0, 'laufend'),
  ('P260078', 'Anbau Mehrzwecksaal Ahrensburg', '', '', NULL, 'Auftrag noch nicht vergeben', 845.35, 103068.59, 'angebot'),
  ('P260084', 'Decathlon A2 Center Isernhagen', '', '', NULL, 'Auftrag erhalten', 355.44, 131264.84, 'laufend'),
  ('P260085', 'Austausch Schalter nd Klemmdosen', '', '', NULL, 'Auftrag noch nicht vergeben', 23.1, 2410.39, 'angebot'),
  ('P260086', 'Austausch Rasterleuchten', '', '', NULL, 'Auftrag noch nicht vergeben', 40.83, 9237.2, 'angebot'),
  ('P260087', 'Friseur Plönitz', '', '', NULL, 'Auftrag noch nicht vergeben', 96.0, 9563.26, 'angebot'),
  ('P260088', 'ALDI Winsen', '', '', NULL, 'Auftrag noch nicht vergeben', 0.0, 0.0, 'angebot'),
  ('P260089', 'EDEKA Cirpan Wentorf', 'ILIS', 'EDEKA HANDELS', '2026-07-30', 'Auftrag erhalten', NULL, NULL, 'laufend'),
  ('P260090', 'EDEKA Cirpan Wentorf DGUV', 'ILIS', 'EDEKA HANDELS', '2026-07-30', 'Auftrag erhalten', NULL, NULL, 'laufend'),
  ('P260091', 'Serverschränke Aldi Kisdorf / HH-Gustav Adolf Straße', 'FLKA', 'ALDI BARGTEHEIDE', '2026-08-05', 'Auftrag erhalten', NULL, NULL, 'laufend'),
  ('P260092', 'Pressen im Lager ergänzen', 'FLKA', 'ALDI BARGTEHEIDE', '2026-08-05', 'Auftrag zugesagt', NULL, NULL, 'laufend'),
  ('P260095', 'Ottendorfer Str. 16 Kesdorf/Süsel', 'JAKO', 'OTTENDORFER', '2026-08-11', 'Auftrag erhalten', NULL, NULL, 'laufend'),
  ('P260096', 'Installation Beleuchtung für Kameraüberwachung Zigaretten', 'STRA', 'ALDI BARGTEHEIDE', '2026-08-13', 'Auftrag noch nicht vergeben', NULL, NULL, 'angebot'),
  ('P260097', 'Objekt HH - Ikea', 'ILIS', 'DECATHLON1', '2026-08-17', 'Auftrag noch nicht vergeben', NULL, NULL, 'angebot'),
  ('P260098', 'Lieferung Zähleranlage', 'STRA', 'KINDER1', '2026-08-20', 'Auftrag noch nicht vergeben', NULL, NULL, 'angebot'),
  ('P260101', 'Bäckerei Junge, Einsiedelstraße 41 a, 23554 Lübeck', 'ILIS', 'SZ BAU1', '2026-08-25', 'Auftrag noch nicht vergeben', NULL, NULL, 'angebot'),
  ('P260102', 'Decathlon HH IKEA', 'ILIS', 'DECATHLON1', '2026-08-26', 'Auftrag noch nicht vergeben', NULL, NULL, 'angebot'),
  ('P260103', 'Austausch Pollerleuchten BV Ballin Kita Ankerplatz, Reddert', 'STRA', 'BALLIN STIFTUNG', '2026-08-31', 'Auftrag noch nicht vergeben', NULL, NULL, 'angebot'),
  ('P260104', 'Rückbau Beleuchtung Liese Meitner Straße 2', 'ILIS', 'GEBR MAY EGE', '2026-09-02', 'Auftrag erhalten', NULL, NULL, 'laufend')
ON DUPLICATE KEY UPDATE
  bezeichnung   = IF(bezeichnung = '', VALUES(bezeichnung), bezeichnung),
  projektleiter = IF(projektleiter = '', VALUES(projektleiter), projektleiter),
  auftraggeber  = IF(auftraggeber = '', VALUES(auftraggeber), auftraggeber),
  anlagedatum   = COALESCE(anlagedatum, VALUES(anlagedatum)),
  kwp_zustand   = IF(kwp_zustand = '', VALUES(kwp_zustand), kwp_zustand),
  kalk_stunden  = COALESCE(kalk_stunden, VALUES(kalk_stunden)),
  auftragssumme = COALESCE(auftragssumme, VALUES(auftragssumme));

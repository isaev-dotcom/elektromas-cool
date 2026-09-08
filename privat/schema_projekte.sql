-- ===========================================================================
-- Projekt-Dashboard elektromas.cool
--
-- Ersetzt die Excel-Datei "Projekt-KPI-Dashboard_elektromas.xlsx".
--
-- Einspielen im Alfahosting-Panel unter DATENBANKEN -> phpMyAdmin ->
-- Datenbank auswählen -> Reiter "SQL" -> Inhalt dieser Datei einfügen -> OK.
-- Setzt die Tabellen aus schema.sql voraus (Benutzerverwaltung).
-- ===========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Projektleiter
--
-- Das Kürzel ist das Feld "Sachbearbeiter" in KWP. Über diese Tabelle wird
-- daraus ein Name. Unbekannte Kürzel aus einem Import bleiben als Kürzel
-- stehen und können hier nachgetragen werden.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projektleiter (
  kuerzel         VARCHAR(10)   NOT NULL,
  name            VARCHAR(120)  NOT NULL,
  aktiv           TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (kuerzel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO projektleiter (kuerzel, name) VALUES
  ('STRA', 'Stefan Räder'),
  ('ILIS', 'Ilya Isaev'),
  ('FLKA', 'Florian Kahlstatt');

-- ---------------------------------------------------------------------------
-- Projekte — die Stammdaten, einmal je Projekt gepflegt
--
-- nummer          Projekt-Nr aus KWP (P260104). Eindeutig, Schlüssel für den Import.
-- kalk_stunden    kalkulierte Stunden aus dem LV (KWP: "Auftragszeit")
-- auftragssumme   Netto-Auftragssumme (KWP: "Auftragssumme")
-- phase           angebot       = KWP "Auftrag noch nicht vergeben"
--                 laufend       = KWP "Auftrag erhalten", erscheint im Dashboard
--                 abgeschlossen = fertig, nur noch im Archiv
-- kwp_status /    die beiden KWP-Felder "Status" und "Zustand", unverändert
-- kwp_zustand     übernommen, damit man sieht, was KWP zuletzt gemeldet hat
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projekte (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nummer          VARCHAR(30)   NOT NULL,
  bezeichnung     VARCHAR(255)  NOT NULL DEFAULT '',
  projektleiter   VARCHAR(10)   NOT NULL DEFAULT '',
  auftraggeber    VARCHAR(190)  NOT NULL DEFAULT '',
  anlagedatum     DATE          DEFAULT NULL,
  kwp_status      VARCHAR(80)   NOT NULL DEFAULT '',
  kwp_zustand     VARCHAR(80)   NOT NULL DEFAULT '',
  kalk_stunden    DECIMAL(10,2) DEFAULT NULL,
  auftragssumme   DECIMAL(12,2) DEFAULT NULL,
  phase           ENUM('angebot','laufend','abgeschlossen') NOT NULL DEFAULT 'laufend',
  notiz           VARCHAR(500)  NOT NULL DEFAULT '',
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  geaendert_am    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_nummer (nummer),
  KEY idx_phase (phase),
  KEY idx_projektleiter (projektleiter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Wochenmeldung — was der Projektleiter jede Woche einträgt
--
-- Je Projekt und Kalenderwoche genau eine Zeile; eine erneute Meldung in
-- derselben Woche überschreibt die vorige. So entsteht automatisch die
-- Historie, die in der Excel-Tabelle jede Woche verlorenging.
--
-- kw              ISO-Kalenderwoche als Text, z. B. 2026-W36. Sortiert sich
--                 als Zeichenkette richtig, auch über den Jahreswechsel.
-- fertig_prozent  geschätzter Fertigstellungsgrad 0–100
-- Sollstunden, Abweichung und Ampeln werden nicht gespeichert, sondern
-- beim Anzeigen berechnet - so wirken geänderte Schwellenwerte oder eine
-- korrigierte Kalkulation sofort auch rückwirkend.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projekt_status (
  id                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  projekt_id            INT UNSIGNED  NOT NULL,
  kw                    CHAR(8)       NOT NULL,
  ist_stunden           DECIMAL(10,2) DEFAULT NULL,
  fertig_prozent        DECIMAL(5,1)  DEFAULT NULL,
  terminstatus          ENUM('im_plan','verzoegert','kritisch') DEFAULT NULL,
  materialstatus        ENUM('bestellt','geliefert','vollstaendig','fehlt') DEFAULT NULL,
  maengel_offen         INT UNSIGNED  NOT NULL DEFAULT 0,
  nachtraege_offen      DECIMAL(12,2) NOT NULL DEFAULT 0,
  nachtraege_genehmigt  DECIMAL(12,2) NOT NULL DEFAULT 0,
  meilenstein           VARCHAR(190)  NOT NULL DEFAULT '',
  meilenstein_datum     DATE          DEFAULT NULL,
  kommentar             VARCHAR(500)  NOT NULL DEFAULT '',
  benutzer_id           INT UNSIGNED  DEFAULT NULL,
  erfasst_am            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_projekt_kw (projekt_id, kw),
  KEY idx_kw (kw),
  CONSTRAINT fk_status_projekt FOREIGN KEY (projekt_id)
    REFERENCES projekte (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Einstellungen — die Ampel-Schwellenwerte aus dem Blatt "Anleitung"
--
-- Werte in Prozent über Soll:  bis 5 % grün, bis 15 % gelb, darüber rot.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projekt_einstellungen (
  schluessel      VARCHAR(40)   NOT NULL,
  wert            VARCHAR(255)  NOT NULL DEFAULT '',
  PRIMARY KEY (schluessel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO projekt_einstellungen (schluessel, wert) VALUES
  ('ampel_gruen_bis', '5'),
  ('ampel_gelb_bis', '15');

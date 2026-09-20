-- ===========================================================================
-- Gefährdungsbeurteilungs-Portal (/bg)  —  Datenbankstruktur
--
-- Einspielen im Alfahosting-Panel unter DATENBANKEN -> phpMyAdmin ->
-- Datenbank auswählen -> Reiter "SQL" -> Inhalt einfügen -> OK.
-- Die Datei lässt sich gefahrlos mehrfach ausführen.
--
-- Eigene Benutzertabelle wie beim TRIZ-Portal: Wer Schulungen sehen darf, hat
-- damit noch keinen Zugang zu den Gefährdungsbeurteilungen. Getrennte
-- Tabellen machen diese Trennung zur Eigenschaft des Datenmodells statt zu
-- einer Frage der richtigen Abfrage an jeder einzelnen Stelle.
-- ===========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Benutzer des Portals
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bg_benutzer (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190)  NOT NULL,
  passwort_hash   VARCHAR(255)  DEFAULT NULL,
  name            VARCHAR(120)  NOT NULL DEFAULT '',
  rolle           ENUM('admin','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
  status          ENUM('eingeladen','aktiv','gesperrt') NOT NULL DEFAULT 'eingeladen',
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aktiviert_am    DATETIME      DEFAULT NULL,
  letzter_login   DATETIME      DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_bg_email (email),
  KEY idx_bg_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Einladungen und Passwort-Zurücksetzungen
--
-- Gespeichert wird nur der SHA-256-Hash des Tokens: Wer die Datenbank liest,
-- kann daraus keinen gültigen Link bauen.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bg_einladungen (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  NOT NULL,
  token_hash      CHAR(64)      NOT NULL,
  gueltig_bis     DATETIME      NOT NULL,
  eingeloest_am   DATETIME      DEFAULT NULL,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  erstellt_von    INT UNSIGNED  DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_bg_token (token_hash),
  KEY idx_bg_einl_benutzer (benutzer_id),
  CONSTRAINT fk_bg_einl_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES bg_benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_resets (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  NOT NULL,
  token_hash      CHAR(64)      NOT NULL,
  gueltig_bis     DATETIME      NOT NULL,
  benutzt_am      DATETIME      DEFAULT NULL,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_bg_reset_token (token_hash),
  KEY idx_bg_reset_benutzer (benutzer_id),
  CONSTRAINT fk_bg_reset_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES bg_benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Anmeldeversuche und Protokoll
--
-- Eigene Tabellen, damit die Brute-Force-Bremse der anderen Bereiche nicht
-- durch Fehlversuche hier auslöst und umgekehrt. Die IP steht nur als Hash
-- darin - Pseudonymisierung nach Art. 32 DSGVO.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bg_anmeldeversuche (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190)  NOT NULL DEFAULT '',
  ip_hash         CHAR(64)      NOT NULL,
  erfolg          TINYINT(1)    NOT NULL DEFAULT 0,
  zeitpunkt       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bg_versuch_email (email, zeitpunkt),
  KEY idx_bg_versuch_ip (ip_hash, zeitpunkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_protokoll (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  DEFAULT NULL,
  email           VARCHAR(190)  NOT NULL DEFAULT '',
  ereignis        VARCHAR(40)   NOT NULL,
  details         VARCHAR(255)  NOT NULL DEFAULT '',
  ip_hash         CHAR(64)      NOT NULL DEFAULT '',
  zeitpunkt       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bg_prot_zeit (zeitpunkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Die Gefährdungsbeurteilungen selbst
--
-- Der fachliche Inhalt steht als JSON in `daten`. Das ist bewusst so: Die
-- Oberfläche bearbeitet ein zusammenhängendes Dokument (Baustelle, gewählte
-- Tätigkeiten, Gefährdungen, Maßnahmen, PSA, Freigabe), und der Zuschnitt
-- dieser Felder ändert sich mit der Fachlage, nicht mit der Software. In
-- Spalten zerlegt müsste jede fachliche Ergänzung die Tabelle ändern.
--
-- Was zum Suchen, Sortieren und Anzeigen in der Liste nötig ist, steht
-- zusätzlich in eigenen Spalten.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bg_gbu (
  id              VARCHAR(40)   NOT NULL,
  bezeichnung     VARCHAR(190)  NOT NULL DEFAULT '',
  projekt_nr      VARCHAR(120)  NOT NULL DEFAULT '',
  status          VARCHAR(20)   NOT NULL DEFAULT 'entwurf',
  daten           LONGTEXT      NOT NULL,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  erstellt_von    INT UNSIGNED  DEFAULT NULL,
  geaendert_am    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  geaendert_von   INT UNSIGNED  DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_bg_gbu_geaendert (geaendert_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Portaleinstellungen, derzeit nur die Firmendaten im Kopf und Fuß der
-- Dokumente. Ein Schlüssel-Wert-Paar reicht dafür und bleibt offen für mehr.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bg_einstellungen (
  schluessel      VARCHAR(60)   NOT NULL,
  wert            LONGTEXT      NOT NULL,
  geaendert_am    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (schluessel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

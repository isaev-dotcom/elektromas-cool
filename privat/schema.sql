-- ===========================================================================
-- Benutzerverwaltung elektromas.cool
--
-- Einspielen im Alfahosting-Panel unter DATENBANKEN -> phpMyAdmin ->
-- Datenbank auswählen -> Reiter "SQL" -> Inhalt dieser Datei einfügen -> OK.
--
-- Zeichensatz utf8mb4: nötig für Umlaute und Emoji. utf8 allein reicht in
-- MySQL nicht, das ist dort nur ein 3-Byte-Zeichensatz.
-- ===========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Benutzer
--
-- Es gibt bewusst keine Selbstregistrierung. Ein Konto entsteht ausschließlich
-- dadurch, dass ein Administrator eine Einladung ausspricht.
--
-- status:
--   eingeladen  Einladung verschickt, Passwort noch nicht gesetzt
--   aktiv       darf sich anmelden
--   gesperrt    darf sich nicht anmelden, Daten bleiben erhalten
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS benutzer (
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
  -- 190 Zeichen, weil ein utf8mb4-Index bei 191 Zeichen an die
  -- 767-Byte-Grenze älterer MySQL-Versionen stößt.
  UNIQUE KEY uniq_email (email),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Einladungen
--
-- Gespeichert wird nur der SHA-256-Hash des Tokens. Wer die Datenbank liest,
-- kann daraus keinen gültigen Einladungslink bauen.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS einladungen (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  NOT NULL,
  token_hash      CHAR(64)      NOT NULL,
  gueltig_bis     DATETIME      NOT NULL,
  eingeloest_am   DATETIME      DEFAULT NULL,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  erstellt_von    INT UNSIGNED  DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_token (token_hash),
  KEY idx_benutzer (benutzer_id),
  CONSTRAINT fk_einladung_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Passwort-Zurücksetzungen
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS passwort_resets (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  NOT NULL,
  token_hash      CHAR(64)      NOT NULL,
  gueltig_bis     DATETIME      NOT NULL,
  benutzt_am      DATETIME      DEFAULT NULL,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_reset_token (token_hash),
  KEY idx_reset_benutzer (benutzer_id),
  CONSTRAINT fk_reset_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Anmeldeversuche — Grundlage der Brute-Force-Bremse
--
-- Gezählt wird nach E-Mail UND nach IP. Nur nach E-Mail zu sperren, würde es
-- erlauben, fremde Konten gezielt auszusperren; nur nach IP zu sperren, würde
-- verteilte Angriffe durchlassen.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS anmeldeversuche (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190)  NOT NULL DEFAULT '',
  ip_hash         CHAR(64)      NOT NULL,
  erfolg          TINYINT(1)    NOT NULL DEFAULT 0,
  zeitpunkt       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_zeit (email, zeitpunkt),
  KEY idx_ip_zeit (ip_hash, zeitpunkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Protokoll
--
-- Die IP wird nur als Hash abgelegt (Pseudonymisierung, Art. 32 DSGVO): für
-- die Missbrauchsabwehr genügt es zu erkennen, dass mehrere Versuche von
-- derselben Quelle stammen - wer dahintersteckt, muss dafür nicht gespeichert
-- werden. Ein Aufräum-Skript löscht Einträge nach der in der Konfiguration
-- eingestellten Frist.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS protokoll (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  DEFAULT NULL,
  email           VARCHAR(190)  NOT NULL DEFAULT '',
  ereignis        VARCHAR(40)   NOT NULL,
  details         VARCHAR(255)  NOT NULL DEFAULT '',
  ip_hash         CHAR(64)      NOT NULL DEFAULT '',
  zeitpunkt       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_zeit (zeitpunkt),
  KEY idx_ereignis (ereignis),
  KEY idx_prot_benutzer (benutzer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

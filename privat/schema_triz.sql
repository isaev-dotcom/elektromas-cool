-- ===========================================================================
-- Business TRIZ Portal  —  Datenbankstruktur
--
-- Einspielen nach schema.sql, im Alfahosting-Panel unter DATENBANKEN ->
-- phpMyAdmin -> Datenbank auswählen -> Reiter "SQL" -> Inhalt einfügen -> OK.
-- Die Datei lässt sich gefahrlos mehrfach ausführen.
--
-- Warum eigene Tabellen statt der vorhandenen `benutzer`?
-- Das Portal soll eine getrennte Einladung und ein eigenes Passwort haben:
-- Wer Schulungen sehen darf, hat damit noch keinen TRIZ-Zugang und umgekehrt.
-- Beides in einer Tabelle zu führen würde diese Trennung zu einer Frage der
-- richtigen Abfrage an jeder einzelnen Stelle machen; getrennte Tabellen
-- machen sie zur Eigenschaft des Datenmodells.
-- ===========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Benutzer des Portals
--
-- sprache/design merken die Oberflächeneinstellung über Geräte hinweg. Sie
-- sind kein Teil der Anmeldung, sondern Bequemlichkeit - deshalb mit
-- Vorgabewert und ohne eigene Tabelle.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_benutzer (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190)  NOT NULL,
  passwort_hash   VARCHAR(255)  DEFAULT NULL,
  name            VARCHAR(120)  NOT NULL DEFAULT '',
  rolle           ENUM('admin','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
  status          ENUM('eingeladen','aktiv','gesperrt') NOT NULL DEFAULT 'eingeladen',
  sprache         ENUM('de','ru') NOT NULL DEFAULT 'de',
  design          ENUM('auto','hell','dunkel') NOT NULL DEFAULT 'auto',
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aktiviert_am    DATETIME      DEFAULT NULL,
  letzter_login   DATETIME      DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_triz_email (email),
  KEY idx_triz_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Einladungen und Passwort-Zurücksetzungen
--
-- Gespeichert wird nur der SHA-256-Hash des Tokens: Wer die Datenbank liest,
-- kann daraus keinen gültigen Link bauen.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_einladungen (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  NOT NULL,
  token_hash      CHAR(64)      NOT NULL,
  gueltig_bis     DATETIME      NOT NULL,
  eingeloest_am   DATETIME      DEFAULT NULL,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  erstellt_von    INT UNSIGNED  DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_triz_token (token_hash),
  KEY idx_triz_einl_benutzer (benutzer_id),
  CONSTRAINT fk_triz_einl_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES triz_benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS triz_resets (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  NOT NULL,
  token_hash      CHAR(64)      NOT NULL,
  gueltig_bis     DATETIME      NOT NULL,
  benutzt_am      DATETIME      DEFAULT NULL,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_triz_reset_token (token_hash),
  KEY idx_triz_reset_benutzer (benutzer_id),
  CONSTRAINT fk_triz_reset_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES triz_benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Anmeldeversuche und Protokoll
--
-- Eigene Tabellen, damit die Brute-Force-Bremse des Schulungsbereichs nicht
-- durch Fehlversuche im Portal auslöst und umgekehrt. Die IP steht nur als
-- Hash darin - Pseudonymisierung nach Art. 32 DSGVO.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_anmeldeversuche (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190)  NOT NULL DEFAULT '',
  ip_hash         CHAR(64)      NOT NULL,
  erfolg          TINYINT(1)    NOT NULL DEFAULT 0,
  zeitpunkt       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_triz_email_zeit (email, zeitpunkt),
  KEY idx_triz_ip_zeit (ip_hash, zeitpunkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS triz_protokoll (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  DEFAULT NULL,
  email           VARCHAR(190)  NOT NULL DEFAULT '',
  ereignis        VARCHAR(40)   NOT NULL,
  details         VARCHAR(255)  NOT NULL DEFAULT '',
  ip_hash         CHAR(64)      NOT NULL DEFAULT '',
  zeitpunkt       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_triz_prot_zeit (zeitpunkt),
  KEY idx_triz_prot_ereignis (ereignis),
  KEY idx_triz_prot_benutzer (benutzer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Kategorien
--
-- bereich trennt die drei Listen: Nachrichten, Videos, Dokumentenordner.
-- schluessel ist der unveränderliche technische Name; angezeigt werden
-- name_de/name_ru, damit eine Umbenennung keine Verweise zerreißt.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_kategorien (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bereich         ENUM('news','video','dokument') NOT NULL,
  schluessel      VARCHAR(60)   NOT NULL,
  name_de         VARCHAR(120)  NOT NULL,
  name_ru         VARCHAR(120)  NOT NULL DEFAULT '',
  sortierung      SMALLINT      NOT NULL DEFAULT 100,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_triz_kat (bereich, schluessel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Quellen der täglichen Sammlung
--
-- art:    rss     = Nachrichtenquelle (RSS oder Atom)
--         youtube = YouTube-Kanal, gelesen über dessen Atom-Feed
-- region: de/ru/int - trennt "TRIZ News" von den "Russischen
--         Informationsquellen", ohne die Beiträge doppelt zu halten.
--
-- Fehler werden an der Quelle vermerkt statt in ein Logfile geschrieben: So
-- sieht die Verwaltung sofort, welche Quelle nicht mehr antwortet.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_quellen (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  art             ENUM('rss','youtube') NOT NULL DEFAULT 'rss',
  name            VARCHAR(160)  NOT NULL,
  url             VARCHAR(500)  NOT NULL,
  sprache         ENUM('de','ru','en') NOT NULL DEFAULT 'de',
  region          ENUM('de','ru','int') NOT NULL DEFAULT 'de',
  kategorie_id    INT UNSIGNED  DEFAULT NULL,
  aktiv           TINYINT(1)    NOT NULL DEFAULT 1,
  letzter_lauf    DATETIME      DEFAULT NULL,
  letzter_fehler  VARCHAR(255)  NOT NULL DEFAULT '',
  treffer_gesamt  INT UNSIGNED  NOT NULL DEFAULT 0,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_triz_quelle_url (url(190)),
  KEY idx_triz_quelle_aktiv (aktiv, art)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Gesammelte Beiträge
--
-- url_hash ist der Schutz gegen Dubletten: Dieselbe Meldung taucht in mehreren
-- Feeds auf. Der Hash über die normalisierte Adresse ist eindeutig
-- indizierbar, die volle Adresse wäre für einen UNIQUE-Index zu lang.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_beitraege (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quelle_id          INT UNSIGNED  DEFAULT NULL,
  titel              VARCHAR(400)  NOT NULL,
  kurzfassung        TEXT          NOT NULL,
  url                VARCHAR(1000) NOT NULL,
  url_hash           CHAR(64)      NOT NULL,
  quelle_name        VARCHAR(160)  NOT NULL DEFAULT '',
  sprache            ENUM('de','ru','en') NOT NULL DEFAULT 'de',
  region             ENUM('de','ru','int') NOT NULL DEFAULT 'de',
  kategorie_id       INT UNSIGNED  DEFAULT NULL,
  veroeffentlicht_am DATETIME      DEFAULT NULL,
  gefunden_am        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_triz_beitrag (url_hash),
  KEY idx_triz_beitrag_zeit (veroeffentlicht_am),
  KEY idx_triz_beitrag_gefunden (gefunden_am),
  KEY idx_triz_beitrag_sprache (sprache, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Gesammelte Videos
--
-- dauer_sekunden bleibt 0, solange kein YouTube-API-Schlüssel hinterlegt ist:
-- Der öffentliche Kanal-Feed liefert die Laufzeit nicht mit. Angezeigt wird
-- sie nur, wenn sie bekannt ist - eine erfundene Dauer wäre schlechter als
-- gar keine.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_videos (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quelle_id          INT UNSIGNED  DEFAULT NULL,
  video_id           VARCHAR(20)   NOT NULL,
  titel              VARCHAR(400)  NOT NULL,
  kanal              VARCHAR(160)  NOT NULL DEFAULT '',
  beschreibung       TEXT          NOT NULL,
  thumbnail_url      VARCHAR(500)  NOT NULL DEFAULT '',
  dauer_sekunden     INT UNSIGNED  NOT NULL DEFAULT 0,
  sprache            ENUM('de','ru','en') NOT NULL DEFAULT 'de',
  kategorie_id       INT UNSIGNED  DEFAULT NULL,
  veroeffentlicht_am DATETIME      DEFAULT NULL,
  gefunden_am        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_triz_video (video_id),
  KEY idx_triz_video_zeit (veroeffentlicht_am),
  KEY idx_triz_video_kat (kategorie_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Interne Dokumente
--
-- Die Dateien liegen unter privat/triz_inhalte/ und damit außerhalb des
-- Web-Verzeichnisses: Es gibt keine Adresse, unter der sie direkt abrufbar
-- wären. Ausgeliefert werden sie ausschließlich über TRIZ/datei.php, und die
-- prüft vorher die Anmeldung. Dasselbe Prinzip wie im Schulungsbereich.
--
-- Versionierung: Eine neue Fassung ist ein neuer Datensatz, der über
-- vorgaenger_id auf die alte zeigt. Die alte bleibt abrufbar, verschwindet
-- aber über aktuell=0 aus der Liste. Die Datei zu überschreiben würde die
-- Vorgeschichte vernichten.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_dokumente (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ordner            VARCHAR(60)   NOT NULL DEFAULT 'unternehmenswissen',
  titel             VARCHAR(300)  NOT NULL,
  beschreibung      TEXT          NOT NULL,
  schlagwoerter     VARCHAR(400)  NOT NULL DEFAULT '',
  sprache           ENUM('de','ru','en') NOT NULL DEFAULT 'de',
  dateiname         VARCHAR(200)  NOT NULL,
  original_name     VARCHAR(250)  NOT NULL DEFAULT '',
  mime              VARCHAR(120)  NOT NULL DEFAULT '',
  groesse           BIGINT UNSIGNED NOT NULL DEFAULT 0,
  version           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  vorgaenger_id     INT UNSIGNED  DEFAULT NULL,
  aktuell           TINYINT(1)    NOT NULL DEFAULT 1,
  volltext          MEDIUMTEXT    NULL,
  hochgeladen_von   INT UNSIGNED  DEFAULT NULL,
  hochgeladen_am    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  downloads         INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_triz_datei (dateiname),
  KEY idx_triz_dok_ordner (ordner, aktuell),
  KEY idx_triz_dok_zeit (hochgeladen_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Wissensbibliothek
--
-- Redaktionell gepflegte Inhalte: die 40 Prinzipien, die 39 Parameter, die
-- Entwicklungsgesetze, Business-Methoden und Praxisbeispiele. Beide Sprachen
-- stehen nebeneinander in einer Zeile, weil es dieselbe Sache ist - zwei
-- getrennte Datensätze müssten bei jeder Änderung von Hand synchron gehalten
-- werden.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_bibliothek (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  art             ENUM('prinzip','parameter','trend','methode','werkzeug','beispiel') NOT NULL,
  nummer          SMALLINT UNSIGNED DEFAULT NULL,
  titel_de        VARCHAR(300)  NOT NULL,
  titel_ru        VARCHAR(300)  NOT NULL DEFAULT '',
  text_de         TEXT          NOT NULL,
  text_ru         TEXT          NOT NULL,
  beispiel_de     TEXT          NOT NULL,
  beispiel_ru     TEXT          NOT NULL,
  sortierung      SMALLINT      NOT NULL DEFAULT 100,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_triz_bib (art, nummer),
  KEY idx_triz_bib_art (art, sortierung)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Widerspruchsmatrix
--
-- Wird leer ausgeliefert und über die Verwaltung als CSV eingespielt. Die
-- Matrix ist eine veröffentlichte Tabelle mit 1521 Feldern; sie aus dem
-- Gedächtnis zu befüllen hieße, an einzelnen Stellen falsche Prinzipien zu
-- empfehlen - und ein falscher Hinweis ist hier schlechter als gar keiner.
-- Die 39 Parameter und die 40 Prinzipien selbst sind vollständig enthalten.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_matrix (
  verbessert      SMALLINT UNSIGNED NOT NULL,
  verschlechtert  SMALLINT UNSIGNED NOT NULL,
  prinzipien      VARCHAR(40)   NOT NULL DEFAULT '',
  PRIMARY KEY (verbessert, verschlechtert)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Favoriten und Kommentare
--
-- objekt_art/objekt_id statt je einer Tabelle pro Inhaltsart: Ein Favorit ist
-- immer dasselbe, egal ob er auf einen Beitrag, ein Video, ein Dokument oder
-- einen Bibliothekseintrag zeigt. Ein Fremdschlüssel auf das Objekt ist
-- deshalb nicht möglich; verwaiste Einträge räumt triz_aufraeumen.php weg.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_favoriten (
  benutzer_id     INT UNSIGNED  NOT NULL,
  objekt_art      ENUM('beitrag','video','dokument','bibliothek') NOT NULL,
  objekt_id       INT UNSIGNED  NOT NULL,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (benutzer_id, objekt_art, objekt_id),
  CONSTRAINT fk_triz_fav_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES triz_benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS triz_kommentare (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  NOT NULL,
  objekt_art      ENUM('beitrag','video','dokument','bibliothek') NOT NULL,
  objekt_id       INT UNSIGNED  NOT NULL,
  text            TEXT          NOT NULL,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_triz_komm_objekt (objekt_art, objekt_id),
  CONSTRAINT fk_triz_komm_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES triz_benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Verlauf des KI-Assistenten
--
-- Bleibt beim Benutzer, damit ein Gespräch nach einem Seitenwechsel
-- weitergeht. Wird nach der in der Konfiguration eingestellten Frist
-- gelöscht: Die Fragen der Mitarbeitenden sind personenbezogene Daten und
-- gehören nicht dauerhaft in die Datenbank.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS triz_ki_verlauf (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  benutzer_id     INT UNSIGNED  NOT NULL,
  rolle           ENUM('frage','antwort') NOT NULL,
  text            MEDIUMTEXT    NOT NULL,
  erstellt_am     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_triz_ki_benutzer (benutzer_id, id),
  CONSTRAINT fk_triz_ki_benutzer FOREIGN KEY (benutzer_id)
    REFERENCES triz_benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

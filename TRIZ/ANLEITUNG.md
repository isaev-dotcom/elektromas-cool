# Business TRIZ Portal — Einrichtung und Betrieb

Interne Wissens-, Nachrichten- und Schulungsplattform für TRIZ im Management,
erreichbar unter <https://elektromas.cool/triz>.

Diese Datei wird **nicht** auf den Server hochgeladen (`deploy.sh` nimmt sie
aus), weil sie Serverpfade und Betriebsinterna beschreibt.

---

## 1. Was das Portal ist — und was es nicht ist

Das Portal ist von der Schulungsplattform unter `/Schulungen` **getrennt**:
eigene Benutzertabelle, eigene Einladung, eigenes Passwort, eigene
Anmeldung. Wer im Schulungsbereich ein Konto hat, kann sich damit hier
**nicht** anmelden. Das war die Vorgabe und steckt bewusst schon im
Datenmodell (siehe Kommentar am Anfang von `privat/schema_triz.sql`) und
nicht bloß in einer Abfrage, die man vergessen kann.

Gemeinsam genutzt werden nur die technische Grundlage (`privat/lib/bootstrap.php`),
das Sitzungs-Cookie und der Mailversand.

### Abweichung von der ursprünglichen Vorgabe

Der Auftrag nannte als Technik React/Next.js, Node.js und PostgreSQL. Auf dem
Alfahosting-Webspace gibt es davon nichts: Dort läuft PHP mit MySQL, das
Deployment ist FTP. Das Portal ist deshalb in derselben Technik gebaut wie
`/Schulungen` und `/Projekte`.

Funktional fehlt dadurch nichts. Was anders ist:

- Die Seiten werden auf dem Server erzeugt statt im Browser. Sprache und
  Hell/Dunkel stehen dadurch schon im ausgelieferten HTML — kein Flackern,
  und die Grundfunktionen arbeiten auch ohne JavaScript.
- „Echtzeit-Aktualisierung" heißt: Die Sammlung läuft nachts als Cronjob, und
  die Verwaltung hat einen Knopf für „jetzt sofort". Ein dauerhaft offener
  Kanal zum Browser wäre auf einem Shared-Host ohnehin nicht zu halten.

---

## 2. Einrichtung

### 2.1 Tabellen anlegen

Panel → **DATENBANKEN** → phpMyAdmin → Datenbank auswählen → Reiter **SQL**.
Nacheinander einspielen:

1. `privat/schema_triz.sql` — 14 Tabellen, alle mit `triz_`-Präfix.
2. `privat/triz_start.sql` — Kategorien, Nachrichtenquellen und die
   vollständige Wissensbibliothek (39 Parameter, 40 Prinzipien, 8
   Entwicklungsgesetze, 12 Methoden, 10 Werkzeuge, 5 Praxisbeispiele,
   jeweils deutsch und russisch).

Beide Dateien lassen sich gefahrlos mehrfach ausführen.

### 2.2 Konfiguration ergänzen

In `privat/config.php` den Abschnitt `'triz' => [...]` aus
`privat/config.example.php` übernehmen. **Alles darin ist freiwillig** — ohne
den Abschnitt läuft das Portal mit den Vorgabewerten, nur der KI-Assistent
bleibt abgeschaltet.

| Schlüssel | Wofür |
|---|---|
| `ki_api_schluessel` | KI-Assistent (Anthropic, console.anthropic.com). Fehlt er, zeigt der Bereich einen Hinweis. |
| `youtube_api_schluessel` | Nur für die **Laufzeit** der Videos. Alles andere kommt ohne. |
| `upload_max_mb` | Obergrenze je Dokument (Vorgabe 64). Die PHP-Einstellung `upload_max_filesize` des Servers geht davor. |
| `pdftotext_pfad` | Wenn gesetzt, landet der Text aus hochgeladenen PDF in der Volltextsuche. Prüfen mit `which pdftotext`. |
| `ki_verlauf_tage` | Nach dieser Frist löscht `aufraeumen.php` den Gesprächsverlauf. |

### 2.3 Hochladen

```bash
./deploy.sh
```

Das Skript legt `privat/` eine Ebene über `httpdocs` ab und erzeugt dort die
Verzeichnisse `triz_inhalte/` und `triz_inhalte/thumbs/`. Die hochgeladenen
Dokumente selbst überträgt es **nicht** — sie entstehen auf dem Server.

### 2.4 Ersten Administrator anlegen

Es gibt keine Selbstregistrierung und keine Einrichtungsseite. Der erste
Portalzugang wird von Hand in phpMyAdmin angelegt:

```sql
INSERT INTO triz_benutzer (email, name, rolle, status)
VALUES ('isaev@elektromas.de', 'Ilya Isaev', 'admin', 'eingeladen');

INSERT INTO triz_einladungen (benutzer_id, token_hash, gueltig_bis)
VALUES (LAST_INSERT_ID(), SHA2('EIGENES-GEHEIMNIS-HIER', 256),
        NOW() + INTERVAL 7 DAY);
```

Danach einmalig aufrufen:

```
https://elektromas.cool/TRIZ/einladung.php?token=EIGENES-GEHEIMNIS-HIER
```

Dort Namen und Passwort setzen — fertig. Für `EIGENES-GEHEIMNIS-HIER` einen
langen Zufallswert nehmen (`openssl rand -hex 32`) und ihn nach der Nutzung
vergessen; in der Datenbank steht ohnehin nur seine Prüfsumme.

Alle weiteren Zugänge entstehen danach bequem über
**Verwaltung → Benutzer → Person einladen**.

### 2.5 Cronjob für die tägliche Sammlung

Panel → **CRONJOBS** → täglich, etwa 5:00 Uhr:

```
/usr/bin/php /var/www/vhosts/h283886.host298.alfahosting-server.de/privat/triz_sammeln.php
```

Der Lauf schreibt eine kurze Bilanz auf die Standardausgabe; Alfahosting
schickt sie als Mail. Quellen, die nicht mehr antworten, stehen darin
namentlich — und zusätzlich in der Verwaltung unter *Quellen*.

Der vorhandene Aufräum-Cronjob (`privat/aufraeumen.php`) übernimmt die
TRIZ-Tabellen automatisch mit, sobald sie existieren. Er muss nicht geändert
werden.

---

## 3. Die Quellen

### 3.1 Was mitgeliefert wird

`triz_start.sql` legt 24 Nachrichtenquellen an: zehn deutschsprachige, elf
russischsprachige und drei internationale. Sie decken alle im Auftrag
genannten Themen und Suchbegriffe ab — von *Innovationsmanagement* über
*Системное мышление* bis *Альтшуллер*.

Verwendet werden **Feed-Adressen von Nachrichtensuchdiensten**. Der Grund
steht ausführlich im Kopf von `privat/lib/triz_sammler.php`, kurz: Ein Feed
ist eine Zusage des Anbieters, dass diese Adresse maschinell gelesen werden
darf. Eine Ergebnisseite automatisiert abzugreifen ist bei praktisch jedem
Anbieter untersagt und bricht bei der nächsten Layoutänderung.

### 3.2 Was fehlt und ergänzt werden muss

**YouTube-Kanäle sind bewusst keine dabei.** Ein Kanal wird über seine
Kanalkennung (`UC…`) angesprochen, und diese Kennungen aus dem Gedächtnis
einzutragen hieße, mit hoher Wahrscheinlichkeit falsche oder gar keine Kanäle
zu abonnieren. Bis ein Administrator Kanäle einträgt, bleibt die Videothek
leer.

So geht es: **Verwaltung → Quellen → Quelle hinzufügen**, Art *YouTube-Kanal*,
und dann einfach die Kanaladresse einfügen — `https://www.youtube.com/@kanalname`
oder `https://www.youtube.com/channel/UC…`. Das Portal ermittelt die
Feed-Adresse einmalig selbst und speichert sie; der Umweg fällt danach nicht
mehr an. Klappt das nicht, meldet das Formular es sofort.

Ebenso lassen sich **Fachportale mit eigenem Feed** ergänzen (Art
*Nachrichtenquelle*). Wichtig: die RSS-/Atom-Adresse eintragen, nicht die
Adresse der Webseite.

### 3.3 Sprache und Raum

Zwei verschiedene Dinge, die oft verwechselt werden:

- **Sprache** wird je Beitrag aus dem Text erkannt (kyrillisch → russisch,
  sonst Funktionswörter deutsch/englisch).
- **Raum** ist eine Eigenschaft der *Quelle*: deutschsprachig, russischsprachig
  oder international.

Der Menüpunkt *Russische Quellen* zeigt den Raum `ru` — also alles aus
russischsprachigen Quellen. Ein russischer Artikel aus einer deutschen Quelle
erscheint dort nicht, wohl aber über den Sprachfilter unter *TRIZ News*.

---

## 4. Die Widerspruchsmatrix

Die 39 Parameter und die 40 Prinzipien sind vollständig enthalten. Die
**Matrix selbst wird leer ausgeliefert**.

Das ist Absicht: Die Matrix hat 1521 Felder. Sie aus dem Gedächtnis zu
befüllen hieße, an einzelnen Stellen falsche Prinzipien zu empfehlen — und
ein falscher Hinweis ist in einer Wissensdatenbank schlechter als gar keiner.

**Einspielen:** Verwaltung → *Widerspruchsmatrix* → CSV hochladen. Erwartet
werden drei Spalten je Zeile:

```
1;10;8,15,29,34
1;15;2,8,29,34
```

Also: verbesserter Parameter (1–39), sich verschlechternder Parameter (1–39),
empfohlene Prinzipien als Nummern. Komma, Semikolon und Tabulator werden als
Trennzeichen erkannt, Anführungszeichen um die dritte Spalte sind erlaubt.
Zeilen mit `#` am Anfang und alles Unbrauchbare werden übergangen und am Ende
gezählt gemeldet.

Solange die Matrix leer ist, sagt der Reiter *Widerspruchsmatrix* das
deutlich — statt so zu tun, als gäbe es keine Empfehlung.

---

## 5. Rollen

| | Mitarbeiter | Administrator |
|---|---|---|
| Inhalte lesen, Dokumente herunterladen, Videos ansehen | ja | ja |
| Favoriten, Kommentare | ja | ja |
| KI-Assistent, Suche | ja | ja |
| Dokumente hochladen und löschen | — | ja |
| Benutzer einladen, sperren, freigeben, Rolle ändern | — | ja |
| Quellen und Kategorien verwalten | — | ja |
| Statistik und Protokoll einsehen | — | ja |

Die Prüfung sitzt serverseitig in jeder Aktion, nicht nur an der Anzeige der
Knöpfe: Ein von Hand abgeschicktes Formular kommt ebenso wenig durch.

Der eigene Zugang lässt sich nicht selbst sperren und nicht selbst
herabstufen — das ist der zuverlässigste Weg, sich auszusperren.

---

## 6. Was noch in die Datenschutzerklärung gehört

**Bitte vor der Freigabe für Mitarbeitende erledigen.** Die
`datenschutz.html` beschreibt heute nur den Schulungsbereich (Abschnitt 7).
Ich habe sie bewusst nicht selbst ergänzt — das ist Rechtstext, und die
Angaben zum KI-Dienst hängen davon ab, welchen Vertrag ihr mit dem Anbieter
schließt.

Zwei Dinge sind neu und **müssen** hinein:

1. **Der KI-Assistent überträgt Daten an einen Dritten.** Wird eine Frage
   gestellt, gehen der Fragetext, der bisherige Gesprächsverlauf und
   *Auszüge aus den internen Dokumenten*, die zur Frage passen, an die API
   von Anthropic. Das ist eine Auftragsverarbeitung; ohne
   AV-Vertrag (Art. 28 DSGVO) sollte der Schlüssel nicht eingetragen werden.
   Solange `ki_api_schluessel` leer ist, verlässt nichts das Haus — der
   Bereich ist dann schlicht abgeschaltet.

2. **Der Gesprächsverlauf wird gespeichert**, damit ein Gespräch nach einem
   Seitenwechsel weitergeht. `aufraeumen.php` löscht ihn nach der in der
   Konfiguration eingestellten Frist (Vorgabe 30 Tage).

Was **nicht** hinein muss, weil es nicht passiert:

- Keine Verbindung zu YouTube beim bloßen Ansehen der Videothek. Die
  Vorschaubilder holt der Server einmal und liefert sie selbst aus
  (`TRIZ/thumbnail.php`); erst der Klick auf ein Video führt zu YouTube.
- Keine externen Schriften, keine externen Skripte, kein Tracking.
- Kein zusätzliches Cookie: Das Portal nutzt dasselbe `emas_sitzung` wie der
  Schulungsbereich.

Ein brauchbarer Aufbau für den neuen Abschnitt ist der von Abschnitt 7:
Benutzerkonto (dieselben Felder, zusätzlich Sprach- und Designeinstellung),
Sitzungs-Cookie (unverändert), Protokollierung (eigene Tabelle
`triz_protokoll`, IP nur als Prüfsumme, Löschung nach 90 Tagen), dazu die
beiden Punkte oben und die hochgeladenen Dokumente.

---

## 7. Was wo liegt

```
TRIZ/                     im Web-Verzeichnis
  index.php               Übersicht (Dashboard)
  news.php                TRIZ News und russische Quellen (?raum=ru)
  videos.php              Videothek
  thumbnail.php           Vorschaubilder, vom eigenen Server
  wissen.php              Wissensdatenbank samt Upload
  datei.php               Auslieferung interner Dokumente
  assistent.php           KI-Assistent
  bibliothek.php          Prinzipien, Parameter, Matrix, Methoden …
  tagesuebersicht.php     Tagesübersicht
  favoriten.php           persönliche Favoriten
  suche.php               Suche über alle Bereiche
  favorit.php             nimmt das Favoriten-Formular entgegen
  kommentar.php           nimmt das Kommentar-Formular entgegen
  verwaltung.php          Benutzer, Quellen, Kategorien, Matrix, Statistik, Protokoll
  login.php  logout.php  einladung.php
  passwort-vergessen.php  passwort-neu.php
  triz.css

privat/                   NICHT im Web-Verzeichnis
  schema_triz.sql         Tabellenstruktur
  triz_start.sql          Kategorien, Quellen, Wissensbibliothek
  triz_sammeln.php        Cronjob der täglichen Sammlung
  triz_inhalte/           hochgeladene Dokumente  ← keine Adresse im Web
  triz_inhalte/thumbs/    zwischengespeicherte Vorschaubilder
  lib/triz_bootstrap.php  Einstiegspunkt jeder Portalseite
  lib/triz.php            Anmeldung, Rechte, Suche, Favoriten, Formatierung
  lib/triz_i18n.php       alle Oberflächentexte, deutsch und russisch
  lib/triz_view.php       Seitengerüst, Navigation, Bausteine
  lib/triz_sammler.php    Feeds abrufen, zerlegen, ablegen
  lib/triz_ki.php         KI-Assistent
```

Warum `triz_inhalte/` außerhalb von `httpdocs` liegt: Läge es darin, käme
jeder an die internen Dokumente, der die Adresse kennt — ganz gleich, was
davor an Anmeldung steht. So gibt es zu diesen Dateien **keine Adresse**;
jeder Abruf läuft über `datei.php`, und die prüft vorher die Anmeldung.
Dasselbe Prinzip wie bei den Schulungen.

---

## 8. Lokal testen

PHP 8.4 liegt lokal (winget, nicht auf dem PATH der Git-Bash). MySQL gibt es
nicht — für einen vollständigen Durchlauf genügt ein SQLite-Ersatz für `db()`
in einer Kopie von `bootstrap.php`. Der Weg ist im Sitzungsspeicher
beschrieben; die Umschreibungen, die es braucht, sind überschaubar:

- `INSERT IGNORE` → `INSERT OR IGNORE`
- `NOW()` → `datetime('now')`
- `NOW() - INTERVAL n DAY` → `datetime('now','-n day')`
- im Schema: `ENUM(…)` → `TEXT`, `AUTO_INCREMENT` → `INTEGER PRIMARY KEY
  AUTOINCREMENT`, `KEY`/`UNIQUE KEY` als eigene `CREATE INDEX`

Vor jedem Deployment gehört beides gemacht: `php -l` über alle geänderten
Dateien und ein Durchlauf über die Seiten.

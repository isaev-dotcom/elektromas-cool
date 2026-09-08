# Projekt-Dashboard – Anleitung

Adresse: `https://elektromas.cool/Projekte/`
(`/projekte`, `/projekt` und `/dashboard` werden dorthin weitergeleitet.)

Ersetzt die Excel-Datei `Projekt-KPI-Dashboard_elektromas.xlsx`. Gleiche
Logik, aber mit drei Vorteilen: alle Projektleiter tragen in dieselbe Liste
ein, jede Wochenmeldung bleibt als Verlauf erhalten, und die Stammdaten kommen
per Import direkt aus KWP.

**Der Bereich ist passwortgeschützt** – dieselben Zugänge wie für die
Schulungen. Jeder angemeldete Mitarbeiter darf Projekte anlegen, Stammdaten
pflegen und Wochenmeldungen abgeben. Nur Administratoren dürfen aus KWP
importieren, Projekte löschen und die Einstellungen ändern.

## Erstmalige Einrichtung

1. In phpMyAdmin `privat/schema_projekte.sql` einspielen (legt die Tabellen
   `projektleiter`, `projekte`, `projekt_status`, `projekt_einstellungen` an
   und trägt STRA, ILIS, FLKA sowie die Ampel-Schwellen 5 % / 15 % ein).
2. Optional `privat/projekte_start.sql` einspielen: 56 Projekte aus der
   KWP-Kalkulationsübersicht vom 29.07.2026 mit kalkulierten Stunden und
   Auftragssummen. Aktueller wird es über den KWP-Import (Schritt 3).
3. Als Administrator unter **KWP-Import** die aktuelle Projektliste aus KWP
   einfügen – siehe unten.

## Wöchentlicher Ablauf (wie im Blatt „Anleitung“ der Excel-Vorlage)

Jeden Freitag oder zu Wochenbeginn öffnet der Projektleiter das Dashboard,
filtert auf seinen Namen und klickt bei jedem laufenden Projekt auf
**Melden**. Die Felder sind mit der letzten Meldung vorbelegt; anzupassen ist
nur, was sich geändert hat:

| Feld | Bedeutung |
|---|---|
| Iststunden bisher | Summe aller gebuchten Stunden (aus der Zeiterfassung) |
| Fertigstellungsgrad % | Schätzung nach Aufwand / Baufortschritt |
| Terminstatus | Im Plan / Verzögert / Kritisch |
| Materialstatus | Bestellt / Geliefert / Vollständig / Fehlt |
| Offene Mängel | Anzahl offener Mängel und Nacharbeiten |
| Nachträge offen / genehmigt | in Euro |
| Nächster Meilenstein + Datum | |
| Kurzkommentar | ein Satz: Grund für Abweichungen |

Beim Tippen zeigt das Formular sofort Soll, Abweichung und Ampel. Eine
zweite Meldung in derselben Kalenderwoche überschreibt die erste. Ältere
Wochen lassen sich über die Auswahl „Kalenderwoche“ nachtragen (bis acht
Wochen zurück).

In der Geschäftsleitungsrunde wird nur nach roten und gelben Projekten
gefragt. Ein Klick auf die Kachel „Rot“ blendet alle anderen aus.

## Rechenregeln

Alles wird beim Anzeigen berechnet, nichts davon ist gespeichert. Eine
korrigierte Kalkulation oder geänderte Schwellenwerte wirken deshalb sofort
und auch rückwirkend.

```
Sollstunden bis heute = Kalk. Stunden (LV) × Fertigstellungsgrad
Abweichung            = Iststunden − Sollstunden
Abweichung %          = Abweichung / Sollstunden
Ampel Stunden         = bis 5 % über Soll grün, bis 15 % gelb, darüber rot
Ampel Termin          = Im Plan grün, Verzögert gelb, Kritisch rot
Gesamt-Ampel          = die schlechtere der beiden
```

Ein Unterschied zur Excel-Vorlage: Ohne kalkulierte Stunden oder ohne
Fertigstellungsgrad gibt es **keine Bewertung** (grauer Punkt). Die
Excel-Formel zeigte in diesem Fall stillschweigend Grün.

Die Schwellenwerte stehen unter **Einstellungen** (Blatt „Anleitung“, Zellen
B27:B29 der Vorlage).

## Import aus KWP

Unter **KWP-Import** (nur Administratoren). Drei Wege:

1. In KWP die Projektliste öffnen, alle Zeilen markieren, `Strg+C`, in das
   Textfeld einfügen. Erkannt werden die Spalten *Projekt-Nr,
   Projekt-Bezeichnung, Anlagedatum, Status, Zustand, Sachbearb.,
   Auftraggeber*. Das Feld „Sachbearbeiter“ in KWP ist der Projektleiter
   (STRA = Stefan Räder, ILIS = Ilya Isaev, FLKA = Florian Kahlstatt).
   Der Projektleiter lässt sich im Dashboard nirgends ändern: Er kommt mit
   jedem Import aus KWP und folgt einem Wechsel des Sachbearbeiters dort
   automatisch. Wer den Projektleiter ändern will, ändert ihn in KWP.
   Die Kalkulationsübersicht (Weg 3) enthält keinen Sachbearbeiter – deshalb
   nach dem Einspielen der Startdaten einmal die Projektliste einfügen.
2. Eine CSV-Datei hochladen (Semikolon oder Tabulator, Windows- oder
   UTF-8-Zeichensatz).
3. Eine XLSX-Datei hochladen, z. B. die Kalkulationsübersicht mit
   *Auftrag, Auftragszeit, Auftragssumme*. „Auftragszeit“ wird zu den
   kalkulierten Stunden, der Block „Auftrag noch nicht vergeben“ zu
   Angeboten.

Es gibt immer erst eine **Vorschau**: je Zeile *Neu*, *Aktualisieren* (mit
jeder einzelnen Änderung), *Unverändert* oder *Übersprungen*. Erst
„Übernehmen“ schreibt in die Datenbank. Regeln:

- Schlüssel ist die Projektnummer. Bekannte Nummern werden nur in den
  Feldern geändert, die der Import liefert.
- Wochenmeldungen werden nie angefasst.
- Sammelprojekte (X26-WERKZEUG, X26-KFZ, …) werden übersprungen, solange das
  Häkchen gesetzt ist.
- Abgeschlossene Projekte bleiben abgeschlossen, auch wenn KWP „Auftrag
  erhalten“ meldet.
- Unbekannte Sachbearbeiter-Kürzel (z. B. JAKO) bleiben als Kürzel stehen;
  unter Einstellungen bekommen sie einen Namen.

## Phasen

| Phase | Herkunft | Im Dashboard |
|---|---|---|
| Laufend | KWP-Zustand „Auftrag erhalten“ / „zugesagt“ | Standardansicht |
| Angebot | KWP-Zustand „Auftrag noch nicht vergeben“ | über den Filter |
| Abgeschlossen | von Hand in den Stammdaten setzen | über den Filter |

Ein fertiges Projekt wird nicht gelöscht, sondern auf „Abgeschlossen“
gesetzt – so bleibt der Verlauf für den Soll/Ist-Vergleich erhalten.

## Export

„CSV exportieren“ liefert die aktuelle Ansicht mit allen Spalten der
Excel-Vorlage als CSV (Semikolon, UTF-8 mit BOM). Excel öffnet die Datei
direkt mit Umlauten und deutschen Zahlen.

## Dateien

```
httpdocs/Projekte/
  index.php            Dashboard: Kacheln, Filter, Tabelle
  projekt.php          Stammdaten, Wochenmeldung, Verlauf
  import.php           KWP-Import (Administrator)
  einstellungen.php    Schwellenwerte, Projektleiter (Administrator)
  export.php           CSV-Export
  projekte.css
  ANLEITUNG.md         diese Datei, wird nicht hochgeladen

privat/
  schema_projekte.sql  Tabellen (einmalig einspielen)
  projekte_start.sql   Startdaten aus KWP vom 29.07.2026 (optional)
  lib/projekte.php     Laden, Bewertung, Import
  lib/projekte_view.php Seitengerüst
```

Protokolliert werden (Tabelle `protokoll`): Anlegen, Ändern und Löschen von
Projekten, jede Wochenmeldung, jeder Import und Änderungen an den
Einstellungen – jeweils mit Benutzer.

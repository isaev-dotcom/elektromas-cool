# Schulungsbereich – Anleitung

Adresse: `https://elektromas.cool/Schulungen/`
(`/schulungen`, `/Schulung` und ähnliche Schreibweisen werden weitergeleitet.)

**Der Bereich ist passwortgeschützt.** Zugänge gibt es nur auf Einladung durch
einen Administrator, eine öffentliche Registrierung existiert nicht.

## Wo liegt was

Die Schulungsdateien liegen **außerhalb** des Web-Verzeichnisses, eine Ebene
über `httpdocs`:

```
privat/
  inhalte/
    schulungen.json         <- hier neue Schulungen eintragen
    sicherheitsunterweisung.html
    ki-verordnung-2025.html
    mobiler-monteur.html
  lib/                      Programmbibliothek
  config.php                Zugangsdaten (nicht im Repository)

httpdocs/Schulungen/
  index.php                 Übersicht, prüft die Anmeldung
  datei.php                 liefert eine Schulung aus
  schulung.css
```

Das ist der Kern des Schutzes: Zu den Schulungsdateien führt **keine Adresse**.
Jeder Abruf läuft über `datei.php`, und die prüft vorher die Anmeldung. Lägen
die Dateien weiter unter `httpdocs`, käme jeder an sie heran, der die Adresse
kennt – ganz gleich, was davor an Login steht.

## Eine neue Schulung veröffentlichen

1. Die fertige HTML-Datei nach `privat/inhalte/` legen.

2. In `privat/inhalte/schulungen.json` einen Eintrag ergänzen:

   ```json
   {
     "id": "erste-hilfe",
     "datei": "erste-hilfe.html",
     "titel": "Erste Hilfe im Betrieb",
     "beschreibung": "Kurzbeschreibung, ein bis zwei Sätze.",
     "kategorie": "Arbeitssicherheit",
     "typ": "Interaktiv",
     "datum": "2026-10-01",
     "dauer": "20 Min."
   }
   ```

   | Feld | Pflicht | Bedeutung |
   |---|---|---|
   | `id` | ja | eindeutig, erscheint in der Adresse |
   | `datei` | ja | Dateiname in `privat/inhalte/` |
   | `titel` | ja | Überschrift in der Übersicht |
   | `beschreibung` | nein | zwei Sätze auf der Kachel |
   | `kategorie`, `typ` | nein | die beiden Marken auf der Kachel |
   | `datum` | nein | Sortierung, neueste zuerst |
   | `dauer` | nein | Angabe auf der Kachel |

3. Hochladen mit `./deploy.sh`.

**Vor dem Hochladen prüfen**, ob die Datei vollständig ist – bei einer aus
Vorlage und Medien gebauten Schulung:

```
grep -o "{{[A-Z0-9_]*}}" datei.html
```

Kommt dabei etwas heraus, fehlen Medien und die Schulung zeigt an diesen
Stellen leere Platzhalter.

## Zugänge verwalten

Unter `https://elektromas.cool/admin/` (nur für Administratoren):

- **Person einladen** – legt das Konto an und verschickt den Einladungslink.
  Das Passwort setzt die eingeladene Person selbst; niemand vergibt Passwörter
  für andere.
- **Freigeben / Sperren / Löschen** – Sperren behält die Daten, Löschen
  entfernt sie endgültig.
- **Rolle ändern** – Mitarbeiter sehen Schulungen, Administratoren zusätzlich
  die Verwaltung.
- **Protokoll** – Anmeldungen, Fehlversuche und Änderungen an Zugängen.

Das eigene Konto lässt sich nicht sperren oder löschen. Das ist Absicht: Sonst
könnte man sich versehentlich selbst aussperren.

## Wenn jemand sein Passwort vergisst

Die Person nutzt „Passwort vergessen" auf der Anmeldeseite. Es ist kein
Eingreifen nötig, und Sie sehen fremde Passwörter zu keinem Zeitpunkt – sie
sind nur als Argon2id-Hash gespeichert und lassen sich nicht zurückrechnen.

Kommt keine Mail an, prüfen Sie den Spam-Ordner. Hilft das nicht, schicken Sie
in der Verwaltung über „Einladung neu" einen frischen Link.

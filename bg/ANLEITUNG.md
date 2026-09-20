# Gefährdungsbeurteilungs-Portal – Anleitung

Adresse: `https://elektromas.cool/bg/` (`/gbu` leitet dorthin weiter.)

Hier entstehen die Gefährdungsbeurteilungen für Baustellen: Baustellendaten,
Tätigkeiten, Gefährdungen mit Maßnahmen, PSA, Unterweisung und Freigabe. Am
Ende steht ein fertiges Dokument zum Drucken oder als PDF.

**Der Zugang ist getrennt** von Schulungen und TRIZ-Portal: eigene
Benutzerliste, eigene Einladung, eigenes Passwort. Wer hier angemeldet ist, ist
im Schulungsbereich nicht angemeldet und umgekehrt.

## Zugänge vergeben

Administratoren des Portals finden unter **Zugänge verwalten**
(`/bg/verwaltung.php`) die Benutzerliste. Dort:

- **Person einladen** – E-Mail, Name, Rolle. Die eingeladene Person bekommt
  eine Mail mit einem einmaligen Link und setzt ihr Passwort selbst.
  Der Link wird zusätzlich auf der Seite angezeigt und lässt sich persönlich
  weitergeben, falls die Mail nicht ankommt (siehe [MAILVERSAND.md](../MAILVERSAND.md)).
- **Neu einladen** – erzeugt einen neuen Link und entwertet den alten.
- **Sperren / Freigeben / Löschen** und **Rolle wechseln**.

Rollen: *Mitarbeiter* darf Gefährdungsbeurteilungen anlegen, bearbeiten,
freigeben und löschen. *Administrator* darf zusätzlich Zugänge verwalten.

## Arbeiten mit dem Portal

Eine neue GBU entsteht über **+ Neue Gefährdungsbeurteilung** aus einer
Vorlage (Neubau, Sanierung im Bestand, Hallenbau, Wartung/Service oder leer).
Danach führen fünf Schritte durch:

1. **Baustelle** – Projektdaten, Zeitraum, Verantwortliche, Notfallangaben
2. **Tätigkeiten** – welche Arbeiten und Geräte vorkommen; daraus entstehen die
   Gefährdungen
3. **Gefährdungen** – Maßnahmen abhaken, eigene ergänzen, Restrisiko bewerten
4. **PSA & Unterweisung** – Schutzausrüstung, Unterweisungsdatum, Beteiligte
5. **Dokument & Freigabe** – Ansicht des fertigen Blatts, Drucken, PDF, Freigabe

Alle Angemeldeten sehen dieselben Gefährdungsbeurteilungen („Gemeinsame
Ablage“ oben rechts). Es wird laufend automatisch gespeichert; wird eine
freigegebene GBU geändert, fällt sie zurück auf *Entwurf* und muss erneut
freigegeben werden.

**Firmendaten** (Name, Anschrift, Fachkraft für Arbeitssicherheit,
Betriebsarzt) stehen im Kopf und Fuß jedes Dokuments und gelten für alle.

## Technik

```
bg/index.php            Rahmen der Anwendung, lädt app.js
bg/app.js               die gesamte Oberfläche und Fachlogik
bg/bg.css               Gestaltung
bg/api.php              JSON-Schnittstelle (liste, speichern, loeschen, firma)
bg/vendor/              jsPDF und AutoTable, lokal statt von einem CDN
bg/login.php …          Anmeldung, Einladung, Passwort, Verwaltung
privat/lib/bg.php       Anmeldung, Rechte, Datenzugriff
privat/schema_bg.sql    Tabellen (bg_*)
```

Die Anwendung kam ursprünglich aus einem claude.ai-Artifact und speicherte
dort in der Artifact-Datenbank. Beim Umzug wurde die Datenhaltung auf
MariaDB umgestellt, die Anmeldung ergänzt und jsPDF lokal abgelegt – externe
Ressourcen wären mit der Datenschutzerklärung der Seite nicht vereinbar.

Die fachlichen Bausteine (Gefährdungen, Maßnahmen, Vorlagen, PSA) stehen als
Tabellen am Anfang von `app.js`: `KAT`, `TEMPLATES`, `PSA`. Wer dort ergänzt,
ändert das Angebot in Schritt 2 und 3 – ohne Datenbankänderung.

Inhalt einer GBU steht als JSON in `bg_gbu.daten`; Bezeichnung, Projektnummer
und Status zusätzlich in eigenen Spalten für die Liste. Der Grund steht als
Kommentar in `privat/schema_bg.sql`.

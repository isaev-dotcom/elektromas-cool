# Schulungsbereich – Anleitung

Adresse: `https://elektromas.cool/Schulungen/`
(`/schulungen`, `/Schulung` und ähnliche Schreibweisen werden per Weiterleitung erkannt.)

**Aktuell ohne Login** – die Seite ist für jeden erreichbar, der die Adresse
kennt. Sie ist per `noindex` von Suchmaschinen ausgenommen, taucht also nicht
bei Google auf. Der Login lässt sich jederzeit nachrüsten, siehe Abschnitt 3.

## Dateien

```
Schulungen/
  index.html              Übersicht aller Schulungen
  schulungen.json         <- hier neue Schulungen eintragen
  schulung.css            Styling
  inhalte/                PDFs, Videos, HTML-Schulungen
    beispiel-schulung.html
  ANLEITUNG.md            diese Datei (nur intern)
  login-vorlage/          Bausteine für den späteren Passwortschutz
    .htaccess
    .htpasswd.example
```

---

## 1. Eine neue Schulung veröffentlichen

1. Inhalt nach `Schulungen/inhalte/` legen – PDF, Video oder HTML-Seite.
   Als Vorlage für eine HTML-Schulung dient `inhalte/beispiel-schulung.html`.

2. In `Schulungen/schulungen.json` einen Eintrag ergänzen:

   ```json
   {
     "titel": "Arbeiten unter Spannung",
     "beschreibung": "Kurzbeschreibung, ein bis zwei Sätze.",
     "kategorie": "Arbeitssicherheit",
     "typ": "PDF",
     "datum": "2026-10-01",
     "dauer": "30 Min.",
     "link": "inhalte/arbeiten-unter-spannung.pdf"
   }
   ```

   | Feld           | Pflicht | Bedeutung                                          |
   |----------------|---------|-----------------------------------------------------|
   | `titel`        | ja      | Überschrift der Kachel                               |
   | `link`         | ja      | Pfad relativ zu `Schulungen/`                          |
   | `beschreibung` | nein    | Text auf der Kachel                                  |
   | `kategorie`    | nein    | z. B. Arbeitssicherheit, Technik, Onboarding         |
   | `typ`          | nein    | PDF, Video, HTML …                                   |
   | `datum`        | nein    | `JJJJ-MM-TT`, bestimmt die Sortierung (neu zuerst)   |
   | `dauer`        | nein    | z. B. „45 Min."                                      |

   Auf die Kommas zwischen den Einträgen achten – nach dem letzten Eintrag steht
   **kein** Komma.

3. Änderungen committen und pushen. Der Webspace zieht den neuen Stand.

4. Den Beispiel-Eintrag löschen, sobald die erste echte Schulung online ist.

---

## 2. Was ohne Login gilt

Jeder mit der Adresse kommt an die Inhalte. Solange keine personenbezogenen oder
vertraulichen Unterlagen dort liegen, ist das unkritisch. Sobald Teilnehmerdaten,
Prüfungsnachweise oder lizenzpflichtiges Material dazukommen, sollte der Login
aktiviert werden.

---

## 3. Login später aktivieren

Der Login ist Apache-Basic-Auth: **Benutzername = E-Mail-Adresse**, das Passwort
vergeben Sie und teilen es per E-Mail mit. Anfragen laufen über
`isaev@elektromas.de`.

### Variante A – über das Alphahosting-Kundenmenü / Plesk (empfohlen)

1. Im Kundenmenü **„Passwortgeschützte Verzeichnisse"** (bzw. „Verzeichnisschutz")
   öffnen.
2. Verzeichnis `/Schulungen` schützen.
3. Bereichsname (Realm) z. B.: `Schulungsbereich elektromas GmbH`
4. Benutzer anlegen – **als Benutzername die E-Mail-Adresse** eintragen,
   Passwort vergeben.

Plesk erzeugt `.htaccess` und `.htpasswd` selbst; die Dateien in
`login-vorlage/` werden dann nicht gebraucht.

### Variante B – Dateien selbst hochladen

1. `Schulungen/login-vorlage/.htaccess` nach `Schulungen/.htaccess` kopieren.

2. Darin die Zeile `AuthUserFile` auf den **absoluten Serverpfad** ändern,
   zum Beispiel:

   ```
   AuthUserFile /var/www/vhosts/elektromas.cool/httpdocs/Schulungen/.htpasswd
   ```

   Den richtigen Pfad zeigt der Dateimanager im Kundenmenü an.

3. `.htpasswd` erzeugen (auf einem Rechner mit Apache-Tools):

   ```
   htpasswd -B -c .htpasswd max.mustermann@firma.de     # erster Benutzer
   htpasswd -B    .htpasswd anna.beispiel@firma.de      # weitere Benutzer
   ```

   `-c` legt die Datei neu an und **überschreibt** eine vorhandene – nur beim
   allerersten Benutzer verwenden.

4. `.htpasswd` per FTP nach `/Schulungen/.htpasswd` hochladen. Die Datei ist in
   `.gitignore` eingetragen und gehört nicht ins Repository.

> **Solange der Pfad in `AuthUserFile` nicht stimmt, liefert der Server beim
> Aufruf von `/Schulungen/` einen Fehler 500.** Das ist der häufigste Stolperstein.

### Neuen Teilnehmer freischalten

Einfach einen weiteren Benutzer mit dessen E-Mail-Adresse anlegen (Variante A)
oder eine Zeile per `htpasswd -B` ergänzen (Variante B) und das Passwort
zurückmailen.

### Hinweis zur Sicherheit

Basic-Auth überträgt die Zugangsdaten nur dann verschlüsselt, wenn die Seite über
**HTTPS** läuft. Bitte sicherstellen, dass das SSL-Zertifikat aktiv ist und
`http://` auf `https://` weitergeleitet wird.

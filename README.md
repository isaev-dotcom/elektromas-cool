# elektromas.cool

Statische Visitenkarten-Webseite der elektromas GmbH.

## Struktur

Alles außer `privat/` landet im Web-Verzeichnis `httpdocs`.

```
index.html            die Visitenkarte
impressum.html        Impressum
datenschutz.html      Datenschutzerklärung
style.css             Styling aller Seiten
assets/               Logo und Favicons (Quelle: elektromas.de)
einrichten.php        einmalige Ersteinrichtung, danach löschen

konto/                Anmeldung, Passwort vergessen, Einladung annehmen
admin/                Benutzerverwaltung und Protokoll
Schulungen/           geschützte Übersicht (index.php) und Auslieferung (datei.php)

privat/               NICHT im Web-Verzeichnis, eine Ebene darüber
  config.php            Zugangsdaten (nicht im Repository)
  schema.sql            Datenbankstruktur
  aufraeumen.php        täglicher Cronjob, löscht alte Daten
  lib/                  Programmbibliothek
  inhalte/              die Schulungsdateien und ihr Katalog
  sessions/             Sitzungsdaten
```

Warum die Trennung: Läge `inhalte/` unter `httpdocs`, käme jeder an die
Schulungen, der die Adresse kennt – ganz gleich, was davor an Anmeldung steht.
So gibt es zu diesen Dateien **keine Adresse**; jeder Abruf läuft über
`Schulungen/datei.php`, und die prüft vorher die Anmeldung.

Die Seiten laden ausschließlich lokale Dateien: keine Cookies, kein Tracking,
keine externen Schriften oder Skripte. Das ist bewusst so und die Grundlage
dafür, dass die Datenschutzerklärung so knapp ausfallen kann. Wer später
Analytics, Google Fonts oder ein Kontaktformular einbaut, muss sie anpassen.

## Deployment

Hoster ist Alfahosting, Zielverzeichnis `/httpdocs`.

Hochgeladen wird per **FTPS mit `./deploy.sh`**. Einmalige Einrichtung:

```bash
cp .env.example .env    # danach die Werte in .env eintragen
./deploy.sh --dry-run   # Testlauf, überträgt nichts
./deploy.sh             # hochladen
```

Die Zugangsdaten stehen ausschließlich in der `.env`, die per `.gitignore`
ausgeschlossen ist. Das Skript bricht ab, falls die `.env` je in der
Upload-Liste landen sollte.

`PROTOCOL` in der `.env` steuert den Weg:

- `ftps` (Standard) – FTP über TLS auf Port 21, per `curl`. Braucht kein
  Zusatzwerkzeug und kommt mit Passwort aus.
- `ftp` – dasselbe unverschlüsselt. Nur, wenn der Hoster kein TLS kann.
- `sftp` – über SSH auf Port 22. Braucht einen SSH-Schlüssel, weil der
  mitgelieferte OpenSSH-Client kein Passwort aus einer Datei lesen kann.

Ist `lftp` vorhanden, nutzt das Skript es bevorzugt: dann werden nur geänderte
Dateien übertragen und `--delete` räumt Verwaistes auf dem Server auf. Unter
Git Bash ist `lftp` nicht verfügbar und mangels Paketmanager auch nicht
nachrüstbar – dort läuft der `curl`-Weg, der jedes Mal alles überträgt. Bei
der aktuellen Größe von rund 60 KB fällt das nicht ins Gewicht.

Nicht hochgeladen wird `schulung/login-vorlage/`: Die dortige `.htaccess`
enthält einen Platzhalter statt eines echten `AuthUserFile`-Pfads, Apache
würde das Verzeichnis mit einem 500er quittieren.

### Git-Deployment: nicht in Betrieb

Der Versuch über die Git-Integration von Alfahosting wurde aufgegeben. Zum
Stand der Dinge, falls jemand es erneut versucht:

- Eine ältere Anbindung rollte nach `/httpdocs/git` aus – dort liegt noch der
  erste Commit. Der Ordner kann weg.
- Eine zweite Anbindung auf `/httpdocs` hat das Verzeichnis geleert, aber
  nicht befüllt. Zwei Repositories mit ineinanderliegenden Zielpfaden auf
  derselben Domain vertragen sich offenbar nicht.

## Benutzerverwaltung

Der Schulungsbereich ist passwortgeschützt. Zugänge gibt es nur auf Einladung,
eine öffentliche Registrierung existiert nicht.

**Einrichtung: siehe [privat/EINRICHTUNG.md](privat/EINRICHTUNG.md).** Ohne
`privat/config.php` und eine eingespielte Datenbank läuft der Bereich nicht.

Technik: PHP 8.4, MySQL, Passwörter als Argon2id-Hash. Node.js scheidet aus –
auf diesem Shared-Hosting gibt es keinen dauerhaften Prozess, PHP-FPM ist die
einzige Ausführungsumgebung.

## Offene Punkte

- Impressum und Datenschutzerklärung fachlich prüfen lassen. Insbesondere:
  Ist `DE 304877773` die USt-IdNr. (davon geht das Impressum aus) oder die
  Steuernummer? Und gibt es einen Datenschutzbeauftragten, der genannt werden
  muss?
- Auf elektromas.de verweist das Impressum noch auf TMG und RStV. Beide sind
  abgelöst (DDG bzw. MStV) – dort ebenfalls korrigieren.
- Der Schulungsbereich (`schulung/`) liegt lokal vor, gehört aber bewusst noch
  nicht ins Repository. Das CSS dafür (`.card__link`) steht bereits in
  `style.css`, der Link in `index.html` ist vorerst entfernt.

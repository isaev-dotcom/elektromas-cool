# elektromas.cool

Statische Visitenkarten-Webseite der elektromas GmbH.

## Struktur

- `index.html` – die Visitenkarte
- `impressum.html` – Impressum
- `datenschutz.html` – Datenschutzerklärung
- `style.css` – Styling aller Seiten
- `assets/` – Logo und Favicons (Quelle: elektromas.de)

Die Seiten laden ausschließlich lokale Dateien: keine Cookies, kein Tracking,
keine externen Schriften oder Skripte. Das ist bewusst so und die Grundlage
dafür, dass die Datenschutzerklärung so knapp ausfallen kann. Wer später
Analytics, Google Fonts oder ein Kontaktformular einbaut, muss sie anpassen.

## Deployment

Hoster ist Alfahosting, Zielverzeichnis `/httpdocs`.

Hochgeladen wird per **SFTP mit `./deploy.sh`**. Einmalige Einrichtung:

```bash
cp .env.example .env    # danach die Werte in .env eintragen
./deploy.sh --dry-run   # Testlauf, überträgt nichts
./deploy.sh             # hochladen
```

Die Zugangsdaten stehen ausschließlich in der `.env`, die per `.gitignore`
ausgeschlossen ist. Das Skript weigert sich, die `.env` mit hochzuladen.

Zum Werkzeug: `lftp` ist unter Git Bash nicht verfügbar und mangels
Paketmanager auch nicht nachinstallierbar. Das Skript nutzt es, falls es
vorhanden ist (dann lädt es nur Geändertes und kann per `--delete` aufräumen),
fällt sonst aber auf den mitgelieferten OpenSSH-Client zurück. Dieser Weg
braucht einen SSH-Schlüssel, weil er kein Passwort aus einer Datei lesen kann.

### Git-Deployment: nicht in Betrieb

Der Versuch über die Git-Integration von Alfahosting wurde aufgegeben. Zum
Stand der Dinge, falls jemand es erneut versucht:

- Eine ältere Anbindung rollte nach `/httpdocs/git` aus – dort liegt noch der
  erste Commit. Der Ordner kann weg.
- Eine zweite Anbindung auf `/httpdocs` hat das Verzeichnis geleert, aber
  nicht befüllt. Zwei Repositories mit ineinanderliegenden Zielpfaden auf
  derselben Domain vertragen sich offenbar nicht.

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

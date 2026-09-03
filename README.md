# elektromas.cool

Statische Visitenkarten-Webseite der elektromas GmbH.

## Struktur

- `index.html` – die komplette Seite
- `style.css` – Styling
- `assets/` – Logo und Favicons (Quelle: elektromas.de)
- `schulung/` – Schulungsbereich, siehe [schulung/ANLEITUNG.md](schulung/ANLEITUNG.md)
- `.htaccess` – Grundeinstellungen und Weiterleitung `/Schulung` → `/schulung/`

## Deployment

Der Webspace bei Alphahosting zieht die Dateien aus diesem GitHub-Repository.
Nach einem Push auf `main` wird der Stand veröffentlicht.

Alternativ lassen sich die drei Dateien auch per FTP direkt in das
Document-Root (z. B. `/html` oder `/httpdocs`) hochladen.

## Offene Punkte

- Impressum und Datenschutzerklärung als Unterseiten ergänzen (in Deutschland Pflicht)

## Schulungsbereich

`/schulung/` listet alle Schulungen. Neue Schulung veröffentlichen = Datei nach
`schulung/inhalte/` legen und einen Eintrag in `schulung/schulungen.json`
ergänzen.

Aktuell **ohne Login**, aber per `noindex` von Suchmaschinen ausgenommen. Der
Passwortschutz (Benutzername = E-Mail-Adresse) lässt sich mit den Bausteinen in
`schulung/login-vorlage/` nachrüsten – Details in
[schulung/ANLEITUNG.md](schulung/ANLEITUNG.md).

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

Beim Hoster Alfahosting (Plesk, Panel unter `host298.alfahosting-server.de:8443`).

**Stand: das Git-Deployment ist noch nicht eingerichtet.** Ein Push auf `main`
landet also nicht automatisch auf dem Webspace. Aktuell werden die Dateien von
Hand ins Document-Root (`httpdocs`) hochgeladen – dabei den Ordner `assets/`
nicht vergessen, sonst fehlt das Logo.

Zum Nachrüsten der Git-Anbindung: Plesk → Websites & Domains → elektromas.cool
→ Git. Voraussetzungen und Stolpersteine:

- Git gibt es bei Alfahosting nur im Tarif Business XL.
- Das Repository ist **privat**. Plesk kommt per HTTPS-URL nicht daran; es
  braucht die SSH-URL `git@github.com:isaev-dotcom/elektromas-cool.git` und den
  von Plesk erzeugten Schlüssel als Deploy Key auf GitHub.
- Zielverzeichnis auf `/httpdocs` setzen, nicht auf den vorgeschlagenen
  Unterordner.
- Branch: `main`.

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

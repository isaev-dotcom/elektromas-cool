# Mailversand über Microsoft 365 einrichten

Einladungen und Passwortlinks der Webseite gehen über ein Microsoft-365-Postfach
hinaus statt über den Mailserver des Webhosters.

**Warum:** Auf dem Webserver sind `elektromas-alarm.de` und `immo-stand.de` noch
als lokale Maildomains eingerichtet. Mails an diese Domains landen deshalb in den
alten Alfahosting-Postfächern oder werden verworfen — sie verlassen den Server
nie, obwohl PHP Erfolg meldet. Direkt an den Mailserver des Empfängers zuzustellen
geht auch nicht: Ausgehender Port 25 ist über IPv4 gesperrt, und die IPv6-Adresse
des Webservers hat keinen Reverse-DNS-Eintrag, weshalb Microsoft dort abweist.
Der Weg über Microsoft Graph ist reines HTTPS und umgeht beides. Die Mails gehen
dann mit SPF und DKIM von elektromas.de raus, was auch beim Spamfilter hilft.

## 1. App-Registrierung in Microsoft Entra anlegen

Im Microsoft-Admin-Center unter **Entra ID → App-Registrierungen → Neue
Registrierung**:

- Name: z. B. `elektromas.cool Mailversand`
- Kontotypen: **Nur Konten in diesem Organisationsverzeichnis**
- Umleitungs-URI: leer lassen

Nach dem Anlegen auf der Übersichtsseite notieren:

- **Anwendungs-ID (Client)** → `client_id`
- **Verzeichnis-ID (Mandant)** → `mandant_id`

## 2. Berechtigung erteilen

**API-Berechtigungen → Berechtigung hinzufügen → Microsoft Graph →
Anwendungsberechtigungen → `Mail.Send`** auswählen, hinzufügen und anschließend
**Administratorzustimmung erteilen** klicken. Ohne diese Zustimmung lehnt
Microsoft den Versand ab.

> Achtung: `Mail.Send` als Anwendungsberechtigung gilt zunächst für **alle**
> Postfächer des Mandanten. Schritt 4 grenzt das auf ein einziges Postfach ein.

## 3. Geheimnis erzeugen

**Zertifikate & Geheimnisse → Neuer geheimer Clientschlüssel**, Laufzeit wählen
(maximal 24 Monate). Der **Wert** wird nur einmal angezeigt — sofort kopieren,
nicht die „Geheimnis-ID“.

**Termin eintragen:** Läuft das Geheimnis ab, verschickt die Webseite keine
Einladungen mehr. Ein neues erzeugen und in der `config.php` austauschen.

## 4. Absenderpostfach festlegen und einschränken

Ein vorhandenes oder neues (gern geteiltes) Postfach verwenden, z. B.
`schulungen@elektromas.de`. Diese Adresse steht später als Absender in der Mail.

Damit die App nur aus diesem einen Postfach senden darf, in **Exchange Online
PowerShell** eine Anwendungszugriffsrichtlinie setzen:

```powershell
Connect-ExchangeOnline
New-ApplicationAccessPolicy -AppId <Anwendungs-ID> `
  -PolicyScopeGroupId schulungen@elektromas.de `
  -AccessRight RestrictAccess `
  -Description "Nur Versand aus dem Schulungspostfach"
```

Prüfen mit:

```powershell
Test-ApplicationAccessPolicy -Identity schulungen@elektromas.de -AppId <Anwendungs-ID>
```

## 5. Werte in die config.php eintragen

Die Datei liegt auf dem Server unter `/privat/config.php`, außerhalb des
Web-Verzeichnisses. Sie gehört **nicht** ins Repository und wird nie über den
Chat weitergegeben.

```php
'mail' => [
    // ...
    'graph' => [
        'mandant_id'    => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
        'client_id'     => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
        'client_secret' => 'der kopierte Wert aus Schritt 3',
        'postfach'      => 'schulungen@elektromas.de',
    ],
],
```

Solange `postfach` leer ist, verschickt die Webseite weiterhin über den
Mailserver des Webhosters.

## 6. Prüfen

Als Administrator anmelden und **Benutzerverwaltung → Mailversand prüfen**
(`/admin/mailtest.php`) aufrufen. Die Seite zeigt oben den aktiven Versandweg und
verschickt auf Knopfdruck eine Testmail an die eigene Adresse. Schlägt etwas
fehl, steht dort die Klartextmeldung von Microsoft, zum Beispiel:

| Meldung | Ursache |
| --- | --- |
| `AADSTS7000215: Invalid client secret` | Geheimnis falsch kopiert oder abgelaufen |
| `AADSTS700016: Application ... not found` | falsche `client_id` oder `mandant_id` |
| `HTTP 403 ... Access is denied` | Administratorzustimmung fehlt oder die Zugriffsrichtlinie sperrt das Postfach |
| `HTTP 404 ... ResourceNotFound` | Postfachadresse stimmt nicht |

## Gut zu wissen

- Das Zugriffstoken wird unter `/privat/graph_token.json` zwischengespeichert
  (rund eine Stunde gültig, Rechte 0600, nicht über den Browser erreichbar).
- Antworten auf Einladungen gehen an die `admin_adresse` aus der `config.php`.
- Die Einladungslinks zeigt die Benutzerverwaltung zusätzlich immer selbst an,
  falls eine Mail doch einmal im Spamordner landet.
- Die alten Postfächer auf `elektromas-alarm.de` und `immo-stand.de` bleiben
  unberührt. Kontaktformulare dieser Webseiten können weiterhin dorthin
  zustellen statt nach Microsoft 365 — das ist ein eigenes Thema.

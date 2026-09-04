# Benutzerverwaltung einrichten

Fünf Schritte. Danach ist `/Schulungen` geschützt.

---

## 1. Datenbank anlegen

Alfahosting-Panel → **DATENBANKEN** → neue MySQL-Datenbank anlegen.
Notieren Sie **Datenbankname, Benutzername und Passwort**.

## 2. Tabellen einspielen

Im Panel **phpMyAdmin** öffnen → die neue Datenbank auswählen → Reiter **SQL**
→ den Inhalt von `privat/schema.sql` einfügen → **OK**.

Es entstehen fünf Tabellen: `benutzer`, `einladungen`, `passwort_resets`,
`anmeldeversuche`, `protokoll`.

## 3. Konfiguration ausfüllen

Lokal im Projektordner:

```bash
cp privat/config.example.php privat/config.php
```

In `privat/config.php` eintragen:

- die drei Datenbankwerte aus Schritt 1
- `ip_pfeffer` – ein einmaliger Zufallswert. Erzeugen mit:

```bash
openssl rand -hex 32
```

Der Pfeffer wird **einmal** gesetzt und danach nie geändert. Er sorgt dafür,
dass aus den gespeicherten IP-Prüfsummen keine Adressen zurückgerechnet werden
können.

Die `config.php` ist von Git ausgeschlossen und liegt außerhalb des
Web-Verzeichnisses – über den Browser ist sie nicht erreichbar.

## 4. Hochladen

```bash
./deploy.sh
```

Das Skript legt `privat/` eine Ebene über `httpdocs` ab.

## 5. Ersten Administrator anlegen

Einmalig aufrufen:

```
https://elektromas.cool/einrichten.php
```

E-Mail eintragen, Einladung anfordern, über den Link das Passwort setzen.

Die Seite arbeitet nur, solange noch kein Konto existiert – danach verweigert
sie sich selbst. **Löschen Sie sie anschließend trotzdem:**

```bash
rm einrichten.php && ./deploy.sh
```

Zusätzlich vom Server entfernen (der alte, ungeschützte Stand):

- `httpdocs/Schulungen/index.html`
- `httpdocs/Schulungen/schulungen.json`
- `httpdocs/Schulungen/inhalte/` samt Inhalt

Solange diese Dateien dort liegen, sind die Schulungen weiterhin ohne
Anmeldung abrufbar. `deploy.sh` löscht nichts auf dem Server – das muss über
den Dateimanager oder FTP geschehen.

---

## 6. Aufräum-Job (empfohlen)

Panel → **CRONJOBS** → täglich:

```
/usr/bin/php /var/www/vhosts/h283886.host298.alfahosting-server.de/privat/aufraeumen.php
```

Löscht abgelaufene Token, Protokolleinträge nach 90 Tagen und Anmeldeversuche
nach 7 Tagen. Ohne diesen Lauf wächst das Protokoll unbegrenzt, was der
Datenminimierung nach Art. 5 Abs. 1 lit. e DSGVO widerspricht.

---

## Wenn die Mails nicht ankommen

Einladungen und Passwort-Links gehen über `mail()` mit Absender
`noreply@elektromas.cool`. Landen sie im Spam, fehlt der Domain vermutlich ein
**SPF-Eintrag**. Im Panel unter DNS ergänzen:

```
v=spf1 include:alfahosting.de ~all
```

Den genauen Wert nennt Alfahosting im Hilfebereich – bitte dort abgleichen,
statt ihn zu raten.

Zur Überbrückung zeigt die Verwaltung den Einladungslink direkt an, wenn der
Versand fehlschlägt. Er ist dann persönlich weiterzugeben und wird nur einmal
angezeigt.

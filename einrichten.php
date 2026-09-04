<?php
/**
 * Einmalige Einrichtung: legt den ersten Administrator an.
 *
 * Diese Seite arbeitet ausschließlich, solange die Benutzertabelle leer ist.
 * Sobald das erste Konto existiert, verweigert sie den Dienst - danach führt
 * jeder weitere Zugang über eine Einladung aus der Verwaltung.
 *
 * Nach der Einrichtung diese Datei löschen. Sie schadet zwar nicht mehr,
 * gehört aber nicht dauerhaft auf den Server.
 */

declare(strict_types=1);

require_once __DIR__ . '/../privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';

sicherheits_header();
sitzung_starten();

$anzahl = (int)db()->query('SELECT COUNT(*) FROM benutzer')->fetchColumn();

if ($anzahl > 0) {
    seite_kopf('Einrichtung abgeschlossen');
    ?>
    <p class="meldung meldung--ok">
      Die Einrichtung ist bereits erfolgt — es existieren <?= $anzahl ?> Zugänge.
    </p>
    <p class="konto__lead">
      Diese Seite tut nichts mehr. Bitte löschen Sie die Datei
      <code>einrichten.php</code> vom Server.
    </p>
    <p class="konto__zusatz"><a href="/konto/login.php">Zur Anmeldung</a></p>
    <?php
    seite_fuss();
    exit;
}

$fehler = '';
$link   = '';
$email_ok = '';

if (ist_post()) {
    csrf_pruefen();

    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $name  = trim((string)($_POST['name'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    } else {
        // Zwischen Prüfung und Anlegen könnte theoretisch ein zweiter Aufruf
        // dazwischenkommen; der eindeutige Index auf email fängt das ab.
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                "INSERT INTO benutzer (email, name, rolle, status)
                 VALUES (?, ?, 'admin', 'eingeladen')"
            );
            $ins->execute([$email, mb_substr($name, 0, 120)]);
            $id = (int)$pdo->lastInsertId();

            [$klartext, $hash] = token_erzeugen();
            $stunden = (int)($CONFIG['sicherheit']['einladung_gueltig_stunden'] ?? 168);
            $bis = (new DateTimeImmutable("+{$stunden} hours"))->format('Y-m-d H:i:s');

            $ins2 = $pdo->prepare(
                'INSERT INTO einladungen (benutzer_id, token_hash, gueltig_bis)
                 VALUES (?, ?, ?)'
            );
            $ins2->execute([$id, $hash, $bis]);

            $pdo->commit();

            $link = $CONFIG['basis_url'] . '/konto/einladung.php?token=' . urlencode($klartext);
            $email_ok = $email;

            mail_einladung($email, $name, $link, $stunden);
            protokoll('ersteinrichtung', $email, $id, 'Administrator angelegt');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Ersteinrichtung fehlgeschlagen: ' . $e->getMessage());
            $fehler = 'Der Zugang konnte nicht angelegt werden.';
        }
    }
}

seite_kopf('Ersteinrichtung');

if ($link !== '') {
    ?>
    <p class="meldung meldung--ok">
      Administrator-Zugang für <strong><?= e($email_ok) ?></strong> angelegt.
    </p>
    <p class="konto__lead">
      Eine E-Mail mit dem Einladungslink ist unterwegs. Falls sie nicht
      ankommt, nutzen Sie diesen Link — er wird nur jetzt angezeigt:
    </p>
    <p class="einmal-link"><code><?= e($link) ?></code></p>
    <p class="meldung meldung--hinweis">
      Bitte löschen Sie anschließend die Datei <code>einrichten.php</code>
      vom Server.
    </p>
    <?php
} else {
    ?>
    <p class="konto__lead">
      Die Benutzertabelle ist noch leer. Legen Sie hier den ersten
      Administrator an. Danach verweigert diese Seite jede weitere Nutzung.
    </p>

    <?php meldung($fehler, 'fehler'); ?>

    <form method="post">
      <?= csrf_feld() ?>
      <label for="email">E-Mail-Adresse des Administrators</label>
      <input type="email" id="email" name="email" required autofocus maxlength="190"
             value="isaev@elektromas.de">

      <label for="name">Name</label>
      <input type="text" id="name" name="name" maxlength="120" value="Ilya Isaev">

      <button type="submit" class="knopf knopf--primaer">Administrator anlegen</button>
    </form>
    <?php
}

seite_fuss();

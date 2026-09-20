<?php
/**
 * Prüfseite für den Mailversand.
 *
 * Verschickt auf Knopfdruck eine Testmail an die eigene Adresse und zeigt im
 * Fehlerfall den Klartext von Microsoft an. Ohne diese Seite stünde der Grund
 * nur im Fehlerprotokoll des Servers, an das im Webhosting niemand herankommt.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';

sicherheits_header();
$ich = admin_verlangen();

$ok_text = '';
$fehler  = '';

if (ist_post()) {
    csrf_pruefen();

    $an = (string)$ich['email'];
    $zeit = (new DateTimeImmutable())->format('d.m.Y H:i');
    $versandt = mail_senden(
        $an,
        'Testmail aus dem Schulungsbereich',
        "Diese Nachricht wurde am {$zeit} über die Prüfseite verschickt.\n"
        . "Kommt sie an, funktioniert der Mailversand der Webseite.\n"
    );

    if ($versandt) {
        $ok_text = 'Die Testmail wurde angenommen und ist unterwegs an ' . $an
                 . '. Schauen Sie auch in den Spamordner.';
    } else {
        $fehler = 'Der Versand schlug fehl: ' . mail_letzter_fehler();
    }
}

$graph = mail_graph_eingerichtet();
$postfach = trim((string)($CONFIG['mail']['graph']['postfach'] ?? ''));

seite_kopf('Mailversand prüfen', 'breit');
?>
  <?php meldung($fehler, 'fehler'); ?>
  <?php meldung($ok_text, 'ok'); ?>

  <p class="konto__lead">
    <?php if ($graph): ?>
      Versandweg: <strong>Microsoft 365</strong>, Absender <?= e($postfach) ?>.
      Die Mails verlassen damit den Webserver über Microsoft und erreichen auch
      elektromas-alarm.de und immo-stand.de.
    <?php else: ?>
      Versandweg: <strong>Mailserver des Webhosters</strong>. Mails an
      elektromas-alarm.de und immo-stand.de kommen auf diesem Weg nicht an, weil
      beide Domains dort noch als lokale Maildomains gelten. Tragen Sie die
      Microsoft-Zugangsdaten in der config.php ein - siehe MAILVERSAND.md.
    <?php endif; ?>
  </p>

  <form method="post">
    <?= csrf_feld() ?>
    <button type="submit" class="knopf knopf--primaer">
      Testmail an <?= e((string)$ich['email']) ?> schicken
    </button>
  </form>

  <p class="konto__zusatz"><a href="/admin/">Zurück zur Übersicht</a></p>
<?php
seite_fuss();

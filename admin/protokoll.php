<?php
/**
 * Protokoll der Registrierungen, Anmeldungen und Verwaltungsschritte.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/privat/lib/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/view.php';

sicherheits_header();
admin_verlangen();

$pro_seite = 100;
$seite = max(1, (int)($_GET['seite'] ?? 1));
$offset = ($seite - 1) * $pro_seite;

$gesamt = (int)db()->query('SELECT COUNT(*) FROM protokoll')->fetchColumn();
$seiten = max(1, (int)ceil($gesamt / $pro_seite));

// LIMIT und OFFSET vertragen keine Platzhalter in allen Treibern, deshalb
// hier auf Ganzzahlen gecastet statt gebunden.
$stmt = db()->query(
    'SELECT * FROM protokoll ORDER BY zeitpunkt DESC, id DESC
     LIMIT ' . (int)$pro_seite . ' OFFSET ' . (int)$offset
);
$zeilen = $stmt->fetchAll();

$klartext = [
    'anmeldung'                  => 'Anmeldung',
    'abmeldung'                  => 'Abmeldung',
    'anmeldung_fehlgeschlagen'   => 'Anmeldung fehlgeschlagen',
    'anmeldung_gesperrt'         => 'Anmeldung blockiert (zu viele Versuche)',
    'anmeldung_gesperrtes_konto' => 'Anmeldung bei gesperrtem Konto',
    'einladung_erstellt'         => 'Einladung erstellt',
    'einladung_erneut'           => 'Einladung erneut verschickt',
    'einladung_eingeloest'       => 'Einladung eingelöst',
    'passwort_reset_angefordert' => 'Passwort-Reset angefordert',
    'passwort_reset_unbekannt'   => 'Passwort-Reset für unbekannte Adresse',
    'passwort_geaendert'         => 'Passwort geändert',
    'konto_freigegeben'          => 'Zugang freigegeben',
    'konto_gesperrt'             => 'Zugang gesperrt',
    'konto_geloescht'            => 'Zugang gelöscht',
    'rolle_geaendert'            => 'Rolle geändert',
];

seite_kopf('Protokoll', 'breit');
?>
  <p class="konto__lead">
    <?= $gesamt ?> Einträge. Sie werden nach
    <?= (int)($CONFIG['aufbewahrung']['protokoll_tage'] ?? 90) ?> Tagen
    automatisch gelöscht. IP-Adressen stehen nur als Prüfsumme darin — man
    erkennt, dass mehrere Versuche von derselben Quelle kamen, aber nicht,
    von wem.
  </p>

  <div class="tabelle-rahmen">
  <table class="tabelle">
    <thead>
      <tr>
        <th>Zeitpunkt</th>
        <th>Ereignis</th>
        <th>E-Mail</th>
        <th>Details</th>
        <th>Quelle</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($zeilen as $z): ?>
      <tr>
        <td class="leise"><?= e(date('d.m.Y H:i:s', strtotime($z['zeitpunkt']))) ?></td>
        <td><?= e($klartext[$z['ereignis']] ?? $z['ereignis']) ?></td>
        <td class="leise"><?= e($z['email']) ?></td>
        <td class="leise"><?= e($z['details']) ?></td>
        <td class="leise"><code><?= e(substr($z['ip_hash'], 0, 8)) ?></code></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$zeilen): ?>
      <tr><td colspan="5" class="leise">Noch keine Einträge.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>

  <?php if ($seiten > 1): ?>
    <p class="blaettern">
      <?php if ($seite > 1): ?>
        <a href="?seite=<?= $seite - 1 ?>">&larr; Neuer</a>
      <?php endif; ?>
      <span class="leise">Seite <?= $seite ?> von <?= $seiten ?></span>
      <?php if ($seite < $seiten): ?>
        <a href="?seite=<?= $seite + 1 ?>">Älter &rarr;</a>
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <p class="konto__zusatz"><a href="/admin/">Zurück zur Übersicht</a></p>
<?php
seite_fuss();

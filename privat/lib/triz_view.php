<?php
/**
 * Business TRIZ Portal — Seitengerüst.
 *
 * Kopf, Navigation, Fuß und die wiederkehrenden Bausteine (Karten, Meldungen,
 * Favoritenknopf, Blättern). Alles serverseitig gerendert: Sprache und
 * Hell/Dunkel stehen schon im ausgelieferten HTML, es gibt also kein Flackern
 * beim Laden und keine Abhängigkeit von JavaScript für die Grundfunktionen.
 */

declare(strict_types=1);

/**
 * Kopf und Navigation.
 *
 * $aktiv ist der Schlüssel des Navigationspunkts, der hervorgehoben wird.
 * $schmal rendert die schmale Karte für Anmeldung und Einladung - dort gibt es
 * noch keinen angemeldeten Benutzer und damit auch keine Navigation.
 */
function triz_kopf(string $titel, string $aktiv = '', bool $schmal = false): void
{
    $sprache = triz_sprache();
    $design  = triz_design();
    $b       = triz_angemeldet() ? triz_benutzer() : null;

    // 'auto' setzt kein Attribut - dann entscheidet die Systemeinstellung
    // des Geräts über prefers-color-scheme.
    $thema = $design === 'auto' ? '' : ' data-thema="' . e($design) . '"';
    ?><!DOCTYPE html>
<html lang="<?= e($sprache) ?>"<?= $thema ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titel) ?> – <?= e(t('portal')) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<link rel="icon" href="/assets/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/favicon-192.png">
<link rel="stylesheet" href="/TRIZ/triz.css">
</head>
<body class="<?= $schmal ? 'triz triz--schmal' : 'triz' ?>">

<?php if ($schmal): ?>

<main class="karte-mitte">
  <header class="karte-mitte__kopf">
    <a href="/"><img class="karte-mitte__logo" src="/assets/elektromas-logo.png"
         alt="elektromas GmbH" width="389" height="149"></a>
    <p class="karte-mitte__marke"><?= e(t('portal')) ?></p>
    <h1><?= e($titel) ?></h1>
  </header>
  <?php triz_schalter_zeile(); ?>

<?php else: ?>

<a class="sprungmarke" href="#inhalt"><?= e(t('nav_dashboard')) ?></a>

<header class="kopf">
  <div class="kopf__marke">
    <button class="kopf__menue" type="button" popovertarget="hauptnavi"
            aria-label="<?= e(t('nav_menue')) ?>">
      <span aria-hidden="true"></span>
    </button>
    <a href="/TRIZ/">
      <img src="/assets/elektromas-logo.png" alt="elektromas GmbH" width="389" height="149">
      <span><?= e(t('portal')) ?></span>
    </a>
  </div>

  <form class="kopf__suche" action="/TRIZ/suche.php" method="get" role="search">
    <label class="nur-vorlesen" for="q"><?= e(t('suche')) ?></label>
    <input type="search" id="q" name="q" autocomplete="off"
           placeholder="<?= e(t('suche')) ?> …"
           value="<?= e((string)($_GET['q'] ?? '')) ?>">
    <button type="submit"><?= e(t('suchen')) ?></button>
  </form>

  <div class="kopf__rechts">
    <?php triz_schalter_zeile(); ?>
    <?php if ($b !== null): ?>
      <span class="kopf__person" title="<?= e($b['email']) ?>">
        <?= e($b['name'] !== '' ? $b['name'] : $b['email']) ?>
      </span>
      <a class="knopf knopf--klein" href="/TRIZ/logout.php"><?= e(t('abmelden')) ?></a>
    <?php endif; ?>
  </div>
</header>

<div class="rumpf">
  <nav class="navi" id="hauptnavi" popover>
    <?php triz_navigation($aktiv); ?>
  </nav>

  <main class="inhalt" id="inhalt">
<?php endif;
}

/**
 * Sprach- und Designumschaltung. Reine Links, kein JavaScript nötig.
 *
 * Die vorhandenen Parameter der Seite bleiben erhalten: Ein schlichtes
 * "?sprache=ru" würde beim Umschalten den eingestellten Filter, die Seitenzahl
 * und den Reiter mit wegwerfen - man landete auf der Russisch-Fassung einer
 * anderen Liste als der, die man gerade ansah.
 */
function triz_schalter_zeile(): void
{
    $sprache = triz_sprache();
    $design  = triz_design();

    $ziel = static function (string $feld, string $wert): string {
        $parameter = $_GET;
        unset($parameter['sprache'], $parameter['design']);
        $parameter[$feld] = $wert;
        return '?' . http_build_query($parameter);
    };
    ?>
    <div class="schalter">
      <div class="schalter__gruppe" role="group" aria-label="<?= e(t('sprache')) ?>">
        <a class="<?= $sprache === 'de' ? 'ist-an' : '' ?>" href="<?= e($ziel('sprache', 'de')) ?>"
           hreflang="de" lang="de">DE</a>
        <a class="<?= $sprache === 'ru' ? 'ist-an' : '' ?>" href="<?= e($ziel('sprache', 'ru')) ?>"
           hreflang="ru" lang="ru">RU</a>
      </div>
      <div class="schalter__gruppe" role="group" aria-label="<?= e(t('darstellung')) ?>">
        <a class="<?= $design === 'hell' ? 'ist-an' : '' ?>" href="<?= e($ziel('design', 'hell')) ?>"
           title="<?= e(t('hell')) ?>"><span aria-hidden="true">☀</span></a>
        <a class="<?= $design === 'auto' ? 'ist-an' : '' ?>" href="<?= e($ziel('design', 'auto')) ?>"
           title="<?= e(t('automatisch')) ?>"><span aria-hidden="true">◐</span></a>
        <a class="<?= $design === 'dunkel' ? 'ist-an' : '' ?>" href="<?= e($ziel('design', 'dunkel')) ?>"
           title="<?= e(t('dunkel')) ?>"><span aria-hidden="true">☾</span></a>
      </div>
    </div>
    <?php
}

function triz_navigation(string $aktiv): void
{
    $punkte = [
        ['dashboard',  '/TRIZ/',                'nav_dashboard',  '◈'],
        ['news',       '/TRIZ/news.php',        'nav_news',       '≡'],
        ['ru',         '/TRIZ/news.php?raum=ru','nav_ru',         'Ru'],
        ['videos',     '/TRIZ/videos.php',      'nav_videos',     '▶'],
        ['wissen',     '/TRIZ/wissen.php',      'nav_wissen',     '❐'],
        ['assistent',  '/TRIZ/assistent.php',   'nav_assistent',  '✳'],
        ['bibliothek', '/TRIZ/bibliothek.php',  'nav_bibliothek', '❖'],
        ['tag',        '/TRIZ/tagesuebersicht.php', 'nav_tag',    '☼'],
        ['favoriten',  '/TRIZ/favoriten.php',   'nav_favoriten',  '★'],
    ];
    ?>
    <ul>
      <?php foreach ($punkte as [$schluessel, $ziel, $text, $zeichen]): ?>
        <li>
          <a href="<?= e($ziel) ?>" class="<?= $aktiv === $schluessel ? 'ist-an' : '' ?>"
             <?= $aktiv === $schluessel ? 'aria-current="page"' : '' ?>>
            <span class="navi__zeichen" aria-hidden="true"><?= e($zeichen) ?></span>
            <?= e(t($text)) ?>
          </a>
        </li>
      <?php endforeach; ?>
      <?php if (triz_ist_admin()): ?>
        <li class="navi__trenner">
          <a href="/TRIZ/verwaltung.php" class="<?= $aktiv === 'verwaltung' ? 'ist-an' : '' ?>">
            <span class="navi__zeichen" aria-hidden="true">⚙</span>
            <?= e(t('nav_verwaltung')) ?>
          </a>
        </li>
      <?php endif; ?>
    </ul>
    <?php
}

function triz_fuss(bool $schmal = false): void
{
    if (!$schmal) {
        echo '</main></div>';
    }
    ?>
  <footer class="fuss">
    <p>&copy; <?= date('Y') ?> elektromas GmbH · <?= e(t('intern')) ?></p>
    <p>
      <a href="/"><?= e(t('startseite')) ?></a> ·
      <a href="/impressum.html"><?= e(t('impressum')) ?></a> ·
      <a href="/datenschutz.html"><?= e(t('datenschutz')) ?></a>
    </p>
  </footer>
<?php if ($schmal): ?>
</main>
<?php endif; ?>
</body>
</html>
<?php
}

/** Meldungsblock. $art: 'fehler', 'ok' oder 'hinweis'. */
function triz_meldung(string $text, string $art = 'hinweis'): void
{
    if ($text === '') {
        return;
    }
    echo '<p class="meldung meldung--' . e($art) . '">' . e($text) . '</p>';
}

/** Seitenüberschrift mit Beschreibung. */
function triz_seitenkopf(string $titel, string $lead = ''): void
{
    echo '<div class="seitenkopf"><h1>' . e($titel) . '</h1>';
    if ($lead !== '') {
        echo '<p>' . e($lead) . '</p>';
    }
    echo '</div>';
}

/**
 * Der Favoritenknopf.
 *
 * Ein Formular statt eines Links: Ein Klick ändert Daten, und was Daten
 * ändert, gehört nicht hinter ein GET - sonst genügt ein Vorschau-Abruf durch
 * einen Mailclient oder Suchroboter, um Favoriten zu setzen.
 */
function triz_favorit_knopf(string $art, int $id, bool $gesetzt): void
{
    $ziel = $_SERVER['REQUEST_URI'] ?? '/TRIZ/';
    ?>
    <form class="favknopf" method="post" action="/TRIZ/favorit.php">
      <?= csrf_feld() ?>
      <input type="hidden" name="art" value="<?= e($art) ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <input type="hidden" name="ziel" value="<?= e($ziel) ?>">
      <button type="submit" class="<?= $gesetzt ? 'ist-an' : '' ?>"
              title="<?= e($gesetzt ? t('favorit_entfernen') : t('favorit_setzen')) ?>"
              aria-label="<?= e($gesetzt ? t('favorit_entfernen') : t('favorit_setzen')) ?>">
        <span aria-hidden="true"><?= $gesetzt ? '★' : '☆' ?></span>
      </button>
    </form>
    <?php
}

/**
 * Ein Beitrag als Listeneintrag.
 *
 * Der Titel führt nach außen zur Quelle: Ein Nachrichtenbeitrag gehört dem,
 * der ihn veröffentlicht hat; ihn im Portal nachzubauen wäre eine
 * Urheberrechtsfrage und würde die Quelle um ihre Leser bringen. Gespeichert
 * ist deshalb nur die Kurzfassung aus dem Feed.
 */
function triz_beitrag_zeile(array $b, bool $favorit, string $suchbegriff = '', string $kategorie = ''): void
{
    ?>
    <li class="eintrag">
      <div class="eintrag__meta">
        <?= triz_sprachmarke((string)$b['sprache']) ?>
        <?php if ($kategorie !== ''): ?><span class="marke"><?= e($kategorie) ?></span><?php endif; ?>
        <span><?= e((string)$b['quelle_name']) ?></span>
        <?php $d = triz_datum($b['veroeffentlicht_am'] ?? null); ?>
        <?php if ($d !== ''): ?><span>· <?= e($d) ?></span><?php endif; ?>
      </div>
      <h3 class="eintrag__titel">
        <a href="<?= e((string)$b['url']) ?>" target="_blank" rel="noopener noreferrer external">
          <?= triz_hervorheben((string)$b['titel'], $suchbegriff) ?>
        </a>
      </h3>
      <?php if (trim((string)$b['kurzfassung']) !== ''): ?>
        <p class="eintrag__text"><?= triz_hervorheben(triz_kuerzen((string)$b['kurzfassung']), $suchbegriff) ?></p>
      <?php endif; ?>
      <?php triz_favorit_knopf('beitrag', (int)$b['id'], $favorit); ?>
    </li>
    <?php
}

/** Ein Video als Karte. Das Vorschaubild kommt vom eigenen Server. */
function triz_video_karte(array $v, bool $favorit, string $kategorie = ''): void
{
    $link = 'https://www.youtube.com/watch?v=' . rawurlencode((string)$v['video_id']);
    $dauer = triz_dauer((int)$v['dauer_sekunden']);
    ?>
    <li class="video">
      <a class="video__bild" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer external">
        <img src="/TRIZ/thumbnail.php?v=<?= e(urlencode((string)$v['video_id'])) ?>"
             alt="" loading="lazy" width="480" height="270">
        <?php if ($dauer !== ''): ?>
          <span class="video__dauer"><?= e($dauer) ?></span>
        <?php endif; ?>
      </a>
      <div class="video__text">
        <div class="eintrag__meta">
          <?= triz_sprachmarke((string)$v['sprache']) ?>
          <?php if ($kategorie !== ''): ?><span class="marke"><?= e($kategorie) ?></span><?php endif; ?>
        </div>
        <h3 class="video__titel">
          <a href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer external">
            <?= e((string)$v['titel']) ?>
          </a>
        </h3>
        <p class="leise">
          <?= e((string)$v['kanal']) ?>
          <?php $d = triz_datum($v['veroeffentlicht_am'] ?? null); ?>
          <?= $d !== '' ? '· ' . e($d) : '' ?>
        </p>
      </div>
      <?php triz_favorit_knopf('video', (int)$v['id'], $favorit); ?>
    </li>
    <?php
}

/** Kennzeichnet die Sprache eines Inhalts. */
function triz_sprachmarke(string $sprache): string
{
    $namen = ['de' => 'DE', 'ru' => 'RU', 'en' => 'EN'];
    $marke = $namen[$sprache] ?? strtoupper($sprache);
    return '<span class="marke marke--sprache" lang="' . e($sprache) . '">' . e($marke) . '</span>';
}

/**
 * Blättern.
 *
 * $basis ist die Adresse ohne Seitenparameter; die übrigen Filter bleiben so
 * beim Umblättern erhalten.
 */
function triz_blaettern(int $seite, int $gesamt, int $pro_seite, array $parameter): void
{
    $seiten = (int)ceil($gesamt / max(1, $pro_seite));
    if ($seiten <= 1) {
        return;
    }
    $link = static function (int $s) use ($parameter): string {
        $parameter['seite'] = $s;
        return '?' . http_build_query($parameter);
    };
    ?>
    <nav class="blaettern" aria-label="<?= e(t('weiter')) ?>">
      <?php if ($seite > 1): ?>
        <a href="<?= e($link($seite - 1)) ?>">←</a>
      <?php endif; ?>
      <span><?= $seite ?> / <?= $seiten ?></span>
      <?php if ($seite < $seiten): ?>
        <a href="<?= e($link($seite + 1)) ?>">→</a>
      <?php endif; ?>
    </nav>
    <?php
}

/**
 * Kommentarbereich unter einem Eintrag.
 *
 * Kommentare sind nach Art. 6 Abs. 1 lit. f DSGVO personenbezogen; deshalb
 * steht der Name des Verfassers dabei und nichts weiter.
 */
function triz_kommentarbereich(string $art, int $objekt_id, int $benutzer_id): void
{
    $liste = triz_kommentare($art, $objekt_id);
    $ziel  = $_SERVER['REQUEST_URI'] ?? '/TRIZ/';
    ?>
    <section class="kommentare">
      <h3><?= e(t('kommentare')) ?><?= $liste ? ' (' . count($liste) . ')' : '' ?></h3>
      <?php foreach ($liste as $k): ?>
        <article class="kommentar">
          <p class="kommentar__kopf">
            <strong><?= e($k['name'] !== '' ? $k['name'] : $k['email']) ?></strong>
            <span class="leise"><?= e(triz_datum($k['erstellt_am'], true)) ?></span>
          </p>
          <p><?= nl2br(e($k['text'])) ?></p>
        </article>
      <?php endforeach; ?>

      <form method="post" action="/TRIZ/kommentar.php" class="kommentar__form">
        <?= csrf_feld() ?>
        <input type="hidden" name="art" value="<?= e($art) ?>">
        <input type="hidden" name="id" value="<?= (int)$objekt_id ?>">
        <input type="hidden" name="ziel" value="<?= e($ziel) ?>">
        <label class="nur-vorlesen" for="ktext-<?= (int)$objekt_id ?>"><?= e(t('kommentar_schreiben')) ?></label>
        <textarea id="ktext-<?= (int)$objekt_id ?>" name="text" rows="3" maxlength="4000"
                  placeholder="<?= e(t('kommentar_schreiben')) ?> …"></textarea>
        <button type="submit" class="knopf knopf--klein"><?= e(t('absenden')) ?></button>
      </form>
    </section>
    <?php
}

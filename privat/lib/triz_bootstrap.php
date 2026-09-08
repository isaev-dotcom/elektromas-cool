<?php
/**
 * Einstiegspunkt aller Portalseiten.
 *
 * Bindet die gemeinsame Grundlage der Seite und die vier Portalbibliotheken
 * ein und erledigt, was jede Seite ohnehin täte: Sicherheits-Header setzen,
 * Sitzung starten und eine Sprach- oder Designumschaltung übernehmen.
 *
 * Eine Zeile je Seite statt fünf - und vor allem: Wer eine neue Seite anlegt,
 * kann keinen dieser Schritte vergessen.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once PRIVAT_PFAD . '/lib/triz.php';
require_once PRIVAT_PFAD . '/lib/triz_i18n.php';
require_once PRIVAT_PFAD . '/lib/triz_view.php';

// Für Seiten mit eingebetteten Medien (Dokumentenvorschau) rufen diese
// Seiten sicherheits_header(true) selbst noch einmal auf.
sicherheits_header();
sitzung_starten();

// Vor jeder Anmeldeprüfung: Die Umschaltung muss auch auf der Anmeldeseite
// funktionieren, sonst kann niemand auf Russisch lesen, was dort steht.
triz_einstellungen_uebernehmen();

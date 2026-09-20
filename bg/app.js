(function(){
'use strict';
const LOGO='/assets/elektromas-logo.png';

/* ---------- Katalog: Gefährdungen und Maßnahmen je Baustein ---------- */
const GROUPS=[
  {id:'basis',titel:'Grundlage'},
  {id:'elektro',titel:'Elektrotechnische Arbeiten'},
  {id:'hoehe',titel:'Arbeiten in der Höhe'},
  {id:'werkzeug',titel:'Elektrowerkzeuge'},
  {id:'taetigkeit',titel:'Weitere Tätigkeiten'},
  {id:'bedingung',titel:'Baustellenbedingungen'}
];
const LEITER_PRUEF=['Mängel an der Leiter (Holme, Sprossen, Füße, Spreizsicherung)',2,3,[
  ['O','Sichtprüfung vor jeder Benutzung'],
  ['O','Regelmäßige Prüfung durch befähigte Person, Leiterkontrollblatt führen, beschädigte Leitern aussondern']]];
const KAT=[
 {id:'baustelle',grp:'basis',immer:true,titel:'Baustelle allgemein',sub:'Immer enthalten',rg:'ArbSchG § 5, DGUV Vorschrift 1, BaustellV, ASR A1.8, ASR A3.4',psa:['schuhe','handschuhe'],gef:[
  ['Stolpern, Rutschen, Stürzen auf Verkehrswegen und Treppen',2,2,[
   ['O','Verkehrs- und Fluchtwege freihalten, Material geordnet lagern'],
   ['T','Leitungen und Schläuche außerhalb von Laufwegen verlegen oder mit Kabelbrücken abdecken'],
   ['T','Ausreichende Beleuchtung sicherstellen (ASR A3.4), bei Bedarf Baustrahler einsetzen'],
   ['O','Arbeitsbereich täglich aufräumen, Verpackungsreste entsorgen'],
   ['P','Sicherheitsschuhe S3 tragen']]],
  ['Herabfallende Gegenstände, Anstoßen des Kopfes',2,3,[
   ['O','Bereiche unter Arbeitsplätzen in der Höhe absperren und kennzeichnen'],
   ['O','Werkzeug und Material in der Höhe gegen Herabfallen sichern'],
   ['P','Schutzhelm tragen, wo Gefährdung durch herabfallende Teile oder Anstoßen besteht']]],
  ['Gegenseitige Gefährdung mit anderen Gewerken',2,3,[
   ['O','Arbeiten mit Bauleitung bzw. SiGeKo abstimmen, SiGe-Plan und Baustellenordnung beachten'],
   ['O','Tägliche Absprache mit anderen Gewerken zu Arbeitsbereichen und Zeiten']]],
  ['Heben und Tragen schwerer Lasten (Kabeltrommeln, Verteiler, Material)',2,2,[
   ['T','Transporthilfen nutzen (Kabeltrommelabroller, Sackkarre, Materialaufzug)'],
   ['O','Schwere oder sperrige Lasten zu zweit tragen'],
   ['O','Unterweisung in rückengerechtem Heben und Tragen'],
   ['P','Schutzhandschuhe tragen']]],
  ['Verzögerte Hilfe bei Unfällen',1,3,[
   ['O','Ersthelfer benannt und auf der Baustelle anwesend'],
   ['T','Verbandkasten nach DIN 13157 im Fahrzeug bzw. auf der Baustelle griffbereit'],
   ['O','Notruf 112, Baustellenadresse und nächstes Krankenhaus / D-Arzt allen bekannt (Aushang)'],
   ['O','Unfälle und Beinaheunfälle sofort dem Projektleiter melden, Verbandbuch führen']]]
 ]},
 {id:'elektro',grp:'elektro',titel:'Arbeiten an elektrischen Anlagen',sub:'Anschließen, Erweitern, Umklemmen, Verteilerbau',rg:'DGUV Vorschrift 3, DIN VDE 0105-100, DGUV Regel 103-011',psa:['brille','stoerlicht'],gef:[
  ['Elektrischer Schlag durch unter Spannung stehende Teile',2,3,[
   ['O','Arbeiten nur durch Elektrofachkräfte oder elektrotechnisch unterwiesene Personen unter Leitung und Aufsicht einer Elektrofachkraft'],
   ['T','Fünf Sicherheitsregeln anwenden: freischalten, gegen Wiedereinschalten sichern, Spannungsfreiheit allpolig feststellen, erden und kurzschließen, benachbarte aktive Teile abdecken oder abschranken'],
   ['T','Spannungsfreiheit mit zweipoligem Spannungsprüfer (DIN EN 61243-3) feststellen, Prüfer vorher und nachher auf Funktion testen'],
   ['T','Schaltstelle mit Sperrelement bzw. Schloss und Schild „Nicht schalten – es wird gearbeitet“ sichern'],
   ['T','VDE-isoliertes Werkzeug (1000 V) verwenden']]],
  ['Störlichtbogen bei Arbeiten in Verteilern und am Hausanschluss',1,3,[
   ['O','Arbeiten unter Spannung sind nicht vorgesehen – nur mit Spezialausbildung und schriftlicher Anweisung (DGUV Regel 103-011)'],
   ['T','Benachbarte aktive Teile mit isolierenden Abdeckungen und Tüchern abdecken'],
   ['P','Störlichtbogen-Schutzkleidung und Gesichtsschutz (Klasse 1) bei Arbeiten in der Nähe aktiver Teile tragen']]]
 ]},
 {id:'pruefen',grp:'elektro',titel:'Messen, Prüfen, Inbetriebnahme',sub:'Erstprüfung nach DIN VDE 0100-600',rg:'DIN VDE 0100-600, DIN VDE 0105-100, DGUV Vorschrift 3',psa:['brille'],gef:[
  ['Berührung aktiver Teile beim Messen unter Spannung',2,3,[
   ['T','Messgeräte und Messleitungen mit passender Messkategorie (CAT III / CAT IV) und Prüfspitzen mit Berührungsschutz verwenden'],
   ['O','Messungen nur durch Elektrofachkraft, Messgeräte vor Gebrauch auf Beschädigung prüfen'],
   ['O','Prüfbereich gegen Zutritt Dritter absperren'],
   ['P','Schutzbrille bzw. Gesichtsschutz bei Messungen im Hauptverteiler oder Zählerschrank tragen']]],
  ['Gefährdung Dritter durch unerwartetes Zuschalten',1,3,[
   ['O','Vor dem Zuschalten alle Beteiligten informieren, Stromkreise einzeln zuschalten'],
   ['O','Nicht fertiggestellte Stromkreise abklemmen und kennzeichnen']]]
 ]},
 {id:'baustrom',grp:'elektro',titel:'Baustrom & Geräteanschluss',sub:'Baustromverteiler, Verlängerungen, Kabeltrommeln',rg:'DGUV Vorschrift 3, DGUV Information 203-006, DIN VDE 0100-704',psa:[],gef:[
  ['Elektrischer Schlag durch beschädigte Leitungen, Stecker oder Geräte',2,3,[
   ['T','Anschluss nur über Baustromverteiler mit RCD 30 mA; in Bestandsanlagen PRCD-S zwischenschalten'],
   ['T','Nur gummiisolierte Leitungen (mind. H07RN-F) und baustellengeeignete Kabeltrommeln verwenden'],
   ['O','Ortsveränderliche Geräte nach DGUV Vorschrift 3 geprüft (gültige Prüfplakette)'],
   ['O','Sichtprüfung von Gerät, Leitung und Stecker vor jeder Benutzung; defekte Geräte sofort aussondern und kennzeichnen']]],
  ['Überhitzung und Brand an Kabeltrommeln und Leitungen',1,3,[
   ['T','Kabeltrommeln bei hoher Last vollständig abwickeln'],
   ['O','Leitungen nicht über scharfe Kanten führen, nicht einklemmen oder überfahren']]]
 ]},
 {id:'schere',grp:'hoehe',titel:'Elektrische Scherenbühne',sub:'Hubarbeitsbühne innen und außen',rg:'BetrSichV, DGUV Regel 100-500 Kap. 2.10, DGUV Grundsatz 308-008',psa:['helm','schuhe'],gef:[
  ['Absturz von der Arbeitsplattform',2,3,[
   ['T','Geländer vollständig, Zugangstür bzw. -kette vor dem Hochfahren geschlossen'],
   ['O','Nicht auf Geländer oder Zwischenholm steigen, keine Leitern oder Tritte auf der Plattform verwenden'],
   ['O','Nennlast und zulässige Personenzahl laut Typenschild einhalten']]],
  ['Umkippen der Bühne',1,3,[
   ['O','Tragfähigkeit und Ebenheit des Untergrunds prüfen (Schachtabdeckungen, Kanten, Gefälle, frischer Estrich)'],
   ['T','Neigungs- und Überlastwarnung nie überbrücken'],
   ['O','Im Freien zulässige Windgeschwindigkeit laut Hersteller einhalten; Innengeräte nur in Gebäuden einsetzen'],
   ['O','Verfahren in angehobener Stellung nur, wenn vom Hersteller zugelassen – langsam und auf ebenem Boden']]],
  ['Quetschen und Scheren an Decke, Trägern und am Scherenmechanismus',2,3,[
   ['O','Beim Hochfahren Abstand zu Decke, Trägern und Kabeltrassen im Blick behalten'],
   ['O','Arbeitsbereich am Boden absperren, niemand im Scherenbereich'],
   ['T','Not-Halt und Notablass vor Arbeitsbeginn auf Funktion prüfen']]],
  ['Fehlbedienung durch fehlende Qualifikation oder Mängel am Gerät',2,3,[
   ['O','Bedienung nur durch schriftlich beauftragte Personen ab 18 Jahren mit Ausbildung nach DGUV Grundsatz 308-008'],
   ['O','Arbeitstägliche Sichtprüfung nach Checkliste, jährliche Prüfung durch befähigte Person (gültige Prüfplakette)'],
   ['O','Rettung aus der Höhe geregelt: zweite Person am Boden kennt den Notablass']]],
  ['Anfahren von Personen und Bauteilen beim Verfahren',2,2,[
   ['O','Fahrweg vorab begehen, bei eingeschränkter Sicht Einweiser einsetzen'],
   ['T','Fahrbereich absperren']]],
  ['Brand beim Laden der Antriebsbatterie',1,2,[
   ['O','Nur in gut belüfteten Bereichen laden, nicht in Flucht- und Rettungswegen']]]
 ]},
 {id:'hubbuehne',grp:'hoehe',titel:'Hebebühne mit Arbeitskorb',sub:'Gelenk- oder Teleskopbühne: LKW, Anhänger, Raupe, Selbstfahrer',rg:'BetrSichV, DGUV Regel 100-500 Kap. 2.10, DGUV Grundsatz 308-008, DIN EN 280',psa:['helm','schuhe','gurt'],gef:[
  ['Herausschleudern oder Absturz aus dem Arbeitskorb',2,3,[
   ['P','Rückhaltesystem tragen: Auffanggurt mit kurzem Verbindungsmittel am Anschlagpunkt im Korb (Katapulteffekt bei Auslegerbühnen)'],
   ['T','Korbtür bzw. Zugangssperre vor dem Hochfahren geschlossen'],
   ['O','Nicht auf Geländer steigen, keine Leitern oder Tritte im Korb; Aussteigen in der Höhe nur, wenn vom Hersteller zugelassen und gesondert geregelt']]],
  ['Umkippen der Bühne',1,3,[
   ['T','Abstützungen vollständig ausfahren, auf nachgiebigem Boden lastverteilende Unterlegplatten verwenden'],
   ['O','Tragfähigkeit des Untergrunds prüfen, Abstand zu Böschungen, Gräben und Schachtabdeckungen halten'],
   ['T','Lastmomentbegrenzung und Neigungsüberwachung nie überbrücken; Korblast und Personenzahl laut Typenschild einhalten'],
   ['O','Zulässige Windgeschwindigkeit laut Hersteller beachten, bei Überschreitung Arbeiten einstellen']]],
  ['Quetschen im Korb zwischen Bedienpult und Bauteilen',2,3,[
   ['T','Bühnen mit Quetschschutz bzw. Abschaltbügel am Bedienpult einsetzen'],
   ['O','Langsam an Decken, Träger und Trassen heranfahren, Blick in Bewegungsrichtung']]],
  ['Fehlbedienung, Mängel am Gerät, verzögerte Rettung',2,3,[
   ['O','Bedienung nur durch schriftlich beauftragte Personen ab 18 Jahren mit Ausbildung nach DGUV Grundsatz 308-008 und Einweisung in den konkreten Bühnentyp'],
   ['O','Arbeitstägliche Sichtprüfung nach Checkliste, jährliche Prüfung durch befähigte Person (gültige Prüfplakette)'],
   ['O','Zweite Person am Boden kennt Bodensteuerung und Notablass, Rettung aus dem Korb ist geregelt']]],
  ['Stromüberschlag an Freileitungen und Oberleitungen',1,3,[
   ['O','Schutzabstände zu Freileitungen einhalten, bei Unterschreitung Freischaltung beim Netzbetreiber beantragen']]],
  ['Gefährdung von Personen im Schwenk- und Verkehrsbereich',2,3,[
   ['T','Schwenk- und Arbeitsbereich am Boden absperren'],
   ['O','Im öffentlichen Verkehrsraum Absicherung nach RSA mit verkehrsrechtlicher Anordnung'],
   ['P','Warnkleidung bei Arbeiten im Verkehrsraum tragen']]]
 ]},
 {id:'arbeitskorb',grp:'hoehe',titel:'Arbeitskorb am Stapler / Teleskoplader',sub:'Personenaufnahmemittel – nur ausnahmsweise',rg:'BetrSichV Anhang 1 Nr. 1.4, TRBS 2121 Teil 4, DGUV Grundsatz 308-001',psa:['helm','schuhe','gurt'],gef:[
  ['Unzulässiger Einsatz des Arbeitskorbs',2,3,[
   ['S','Vorrangig Hubarbeitsbühne oder Gerüst einsetzen – Arbeitskorb nur ausnahmsweise, Begründung in dieser GBU festhalten'],
   ['O','Nur Arbeitskörbe verwenden, die für das konkrete Trägergerät zugelassen sind (Typenschild, Tragfähigkeit, Herstellerfreigabe)']]],
  ['Absturz aus dem Korb oder Abrutschen des Korbs',1,3,[
   ['T','Korb formschlüssig an Gabelzinken bzw. Geräteträger gegen Abrutschen und Kippen sichern'],
   ['T','Geländer vollständig, Tür geschlossen, Schutzgitter zum Hubmast vorhanden'],
   ['P','Auffanggurt am Anschlagpunkt im Korb tragen, wenn vom Hersteller vorgesehen'],
   ['O','Nicht auf Geländer steigen, keine Leitern oder Tritte im Korb']]],
  ['Unbeabsichtigtes Bewegen des Trägergeräts',2,3,[
   ['O','Fahrer bleibt am Steuerstand, solange Personen im Korb sind; kein Verfahren mit angehobenem, besetztem Korb'],
   ['T','Feststellbremse angezogen, Hub- und Senkbewegungen nur langsam'],
   ['O','Verständigung zwischen Korb und Fahrer vorab vereinbaren (Handzeichen oder Funk)']]],
  ['Quetschen und Scheren an Hubmast, Decken und Bauteilen',2,3,[
   ['O','Hände und Körperteile im Korb halten, Abstand zu Decken, Trägern und Trassen einhalten']]],
  ['Fehlbedienung durch fehlende Qualifikation oder Mängel an Gerät und Korb',2,3,[
   ['O','Fahrer mit Ausbildung und schriftlicher Beauftragung (DGUV Grundsatz 308-001 bzw. für Teleskoplader), Einweisung in den Korbbetrieb'],
   ['O','Trägergerät und Arbeitskorb regelmäßig durch befähigte Person geprüft, Sichtprüfung vor jedem Einsatz']]]
 ]},
 {id:'anlege',grp:'hoehe',titel:'Anlegeleiter',sub:'Als Zugang oder kurzzeitiger Arbeitsplatz',rg:'BetrSichV, TRBS 2121 Teil 2, DGUV Information 208-016',psa:['schuhe'],gef:[
  ['Absturz beim Arbeiten von der Leiter',2,3,[
   ['O','Als Arbeitsplatz nur bei Standhöhe bis 5 m, höchstens 2 h pro Schicht und Werkzeug/Material bis 10 kg – sonst Scherenbühne oder Gerüst verwenden'],
   ['T','Anstellwinkel 65–75°, Leiter mindestens 1 m über die Austrittsstelle hinausragen lassen'],
   ['T','Gegen Wegrutschen, Umkanten und Einsinken sichern (Fußverbreiterung, rutschhemmende Leiterfüße)'],
   ['O','Nicht seitlich hinauslehnen – Körperschwerpunkt zwischen den Holmen halten']]],
  LEITER_PRUEF,
  ['Elektrischer Schlag bei Arbeiten in der Nähe aktiver Teile',1,3,[
   ['S','Bei Elektroarbeiten isolierende Leitern (GFK oder Holz) statt Aluminiumleitern verwenden']]]
 ]},
 {id:'steh',grp:'hoehe',titel:'Stehleiter / Podestleiter',sub:'Montage an Decke und Wand',rg:'BetrSichV, TRBS 2121 Teil 2, DGUV Information 208-016',psa:['schuhe'],gef:[
  ['Absturz von der Stehleiter',2,3,[
   ['S','Für längere Arbeiten Podest- oder Plattformleiter mit Haltebügel statt Sprossen-Stehleiter einsetzen'],
   ['T','Spreizsicherung vollständig gespannt'],
   ['O','Die obersten beiden Sprossen bzw. Stufen nicht besteigen (außer Plattform mit Haltebügel)'],
   ['O','Nicht von der Leiter auf Bauteile übersteigen, Stehleiter nicht als Anlegeleiter nutzen']]],
  ['Umkippen durch seitliche Kräfte (z. B. Bohren in Decke oder Wand)',2,2,[
   ['T','Nur auf ebenem, tragfähigem Untergrund aufstellen'],
   ['O','Bei seitlichen Kräften Podestleiter oder Scherenbühne verwenden']]],
  LEITER_PRUEF
 ]},
 {id:'rollgeruest',grp:'hoehe',titel:'Fahrbares Arbeitsgerüst (Rollgerüst)',sub:'Montage an Decken, Trassen und Fassaden',rg:'BetrSichV, TRBS 2121 Teil 1, DIN EN 1004-1, DGUV Information 201-011',psa:['helm','schuhe','handschuhe'],gef:[
  ['Absturz von der Belagfläche',2,3,[
   ['T','Dreiteiligen Seitenschutz (Geländer, Zwischenholm, Bordbrett) auf jeder genutzten Belagfläche vollständig montieren'],
   ['O','Auf- und Abstieg nur innen über die vorgesehenen Leitergänge, Durchstiegsklappen danach schließen'],
   ['O','Keine Leitern, Kisten oder Tritte auf der Belagfläche verwenden, nicht über den Seitenschutz lehnen']]],
  ['Umkippen oder Wegrollen des Gerüsts',1,3,[
   ['T','Ballast und Ausleger laut Aufbau- und Verwendungsanleitung anbringen'],
   ['T','Lenkrollen vor dem Betreten feststellen'],
   ['O','Nur auf ebenem, tragfähigem Untergrund aufstellen; zulässige Standhöhe laut Anleitung einhalten (in Gebäuden max. 12 m, im Freien max. 8 m)'],
   ['O','Verfahren nur ohne Personen und lose Teile auf dem Gerüst – langsam, ohne Stöße, nicht über Kanten oder Schwellen'],
   ['O','Im Freien bei Wind nach Herstellerangabe sichern oder abbauen']]],
  ['Fehler beim Auf-, Um- und Abbau',2,3,[
   ['O','Auf-, Um- und Abbau nur durch fachlich geeignete, unterwiesene Beschäftigte unter Aufsicht einer befähigten Person nach Aufbau- und Verwendungsanleitung (liegt vor Ort)'],
   ['T','Beim Aufbau vorlaufenden Seitenschutz bzw. Montagesicherungsgeländer verwenden'],
   ['O','Vor Benutzung Prüfung auf ordnungsgemäßen Aufbau, beschädigte Teile nicht verwenden, Gerüst kennzeichnen']]],
  ['Herabfallende Gegenstände',2,3,[
   ['T','Bordbretter montieren, Material sicher ablegen'],
   ['O','Bereich unter und neben dem Gerüst absperren']]]
 ]},
 {id:'flex',grp:'werkzeug',titel:'Winkelschleifer (Flex)',sub:'Trennen und Schleifen',rg:'BetrSichV, LärmVibrationsArbSchV, Herstellerbetriebsanleitung',psa:['brille','gesicht','gehoer','handschuhe'],gef:[
  ['Bruch der Scheibe, Rückschlag',2,3,[
   ['T','Schutzhaube immer montiert und zum Körper hin eingestellt'],
   ['T','Nur zugelassene Scheiben mit passender Höchstdrehzahl verwenden, Verfallsdatum beachten'],
   ['T','Zusatzhandgriff verwenden; Geräte mit Wiederanlaufschutz und Rückschlagabschaltung einsetzen'],
   ['O','Nicht verkanten, Werkstück sicher auflegen; Scheibenwechsel nur bei gezogenem Stecker bzw. entnommenem Akku']]],
  ['Augen- und Gesichtsverletzungen durch Funken und Partikel',3,2,[
   ['P','Schutzbrille, bei Trennarbeiten zusätzlich Gesichtsschutz tragen'],
   ['O','Andere Personen aus dem Funkenflugbereich fernhalten']]],
  ['Brand durch Funkenflug',2,3,[
   ['O','Brennbare Stoffe aus dem Funkenflugbereich entfernen oder abdecken'],
   ['T','Geeigneten Feuerlöscher griffbereit halten'],
   ['O','In brandgefährdeten Bereichen Erlaubnisschein für Heißarbeiten einholen, Brandwache und Nachkontrolle']]],
  ['Lärm und Hand-Arm-Vibration',3,2,[
   ['P','Gehörschutz tragen (Winkelschleifer typisch über 90 dB(A))'],
   ['T','Vibrationsgeminderte Geräte und Griffe verwenden'],
   ['O','Einsatzzeiten begrenzen, Tätigkeiten abwechseln']]]
 ]},
 {id:'bohr',grp:'werkzeug',titel:'Bohrmaschine / Bohrhammer',sub:'Dübel-, Durchbruch- und Dosenbohrungen',rg:'BetrSichV, LärmVibrationsArbSchV, GefStoffV, DIN 18015-3',psa:['brille','gehoer'],gef:[
  ['Anbohren verdeckter Strom-, Gas- oder Wasserleitungen',2,3,[
   ['T','Vor dem Bohren Leitungssuchgerät einsetzen'],
   ['O','Installationszonen nach DIN 18015-3 beachten, Bestandspläne einsehen, betroffene Stromkreise freischalten']]],
  ['Blockieren des Bohrers, Verdrehen des Handgelenks',2,2,[
   ['T','Geräte mit Sicherheitskupplung bzw. Kickback-Control und Zusatzhandgriff verwenden'],
   ['O','Auf sicheren Stand achten, große Durchmesser nicht von der Leiter bohren']]],
  ['Erfassen von Kleidung, Haaren oder Handschuhen',1,3,[
   ['O','Eng anliegende Kleidung, lange Haare zusammenbinden, keinen Schmuck tragen'],
   ['O','Keine Handschuhe beim Führen der Maschine mit rotierendem Bohrer']]],
  ['Bohrstaub und Lärm',2,2,[
   ['T','Bohrstaubabsaugung bzw. Absaughaube mit Entstauber (Staubklasse M) verwenden'],
   ['P','Schutzbrille tragen, bei Überkopfarbeiten zusätzlich FFP2-Maske'],
   ['P','Gehörschutz beim Bohrhammer tragen']]]
 ]},
 {id:'kernbohr',grp:'werkzeug',titel:'Kernbohrgerät',sub:'Durchbrüche in Wand und Decke, nass oder trocken',rg:'BetrSichV, LärmVibrationsArbSchV, GefStoffV, DGUV Vorschrift 3, Herstellerbetriebsanleitung',psa:['brille','gehoer','handschuhe'],gef:[
  ['Verdrehen oder Herausschlagen des Geräts beim Blockieren der Bohrkrone',2,3,[
   ['T','Ab dem vom Hersteller genannten Durchmesser nur mit Bohrständer bohren, sicher befestigt (Dübel, Spannsäule oder Vakuum)'],
   ['T','Geräte mit Rutschkupplung bzw. elektronischer Überlastabschaltung verwenden'],
   ['O','Vakuumbefestigung nur auf glatten, dichten Flächen und mit Vakuumanzeige; handgeführt nur kleine Durchmesser mit Zusatzhandgriff']]],
  ['Elektrischer Schlag durch Spülwasser beim Nassbohren',2,3,[
   ['T','Nassbohrgeräte nur über PRCD bzw. RCD 30 mA betreiben, Schutzart laut Hersteller beachten'],
   ['T','Wasserfangring und Wassersauger verwenden'],
   ['O','Stecker, Kupplungen und Leitungen aus dem Spülwasser fernhalten']]],
  ['Durchbohren von Leitungen, Bewehrung oder tragenden Bauteilen',2,3,[
   ['O','Bohrungen in tragenden Bauteilen nur nach Freigabe durch Bauleitung bzw. Statiker'],
   ['T','Bohrstelle vorab orten (Leitungen, Bewehrung), betroffene Stromkreise freischalten']]],
  ['Herabfallender Bohrkern auf der Gegenseite oder unter Deckenbohrungen',2,3,[
   ['O','Bereich hinter bzw. unter der Bohrung absperren, zweite Person oder Absprache auf der Gegenseite'],
   ['T','Bohrkern gegen Herabfallen sichern (Kernfänger, Unterstützung)']]],
  ['Lärm und Staub beim Trockenbohren',2,2,[
   ['T','Trockenbohren nur mit Absaugung über Entstauber (Staubklasse M)'],
   ['P','Gehörschutz und Schutzbrille tragen, beim Trockenbohren zusätzlich FFP2-Maske']]],
  ['Ausrutschen auf Bohrschlamm',2,2,[
   ['O','Bohrschlamm und Spülwasser sofort aufnehmen, Arbeitsbereich trocken halten'],
   ['P','Sicherheitsschuhe S3 tragen']]]
 ]},
 {id:'fraese',grp:'werkzeug',titel:'Mauerschlitzfräse',sub:'Schlitze in Mauerwerk und Beton',rg:'GefStoffV, TRGS 559, LärmVibrationsArbSchV, ArbMedVV',psa:['brille','gehoer','ffp2','handschuhe'],gef:[
  ['Einatmen von Quarzfeinstaub (A-Staub)',3,3,[
   ['T','Nur mit angeschlossenem Entstauber mindestens Staubklasse M betreiben, möglichst als geprüftes staubarmes System'],
   ['O','Arbeitsbereich abschotten (Staubschutzwand), für Lüftung sorgen'],
   ['O','Reinigung nur saugend – nicht trocken kehren oder abblasen'],
   ['P','Atemschutz mindestens FFP2 tragen, ohne geprüftes staubarmes System FFP3'],
   ['O','Arbeitsmedizinische Vorsorge nach ArbMedVV anbieten bzw. veranlassen']]],
  ['Anschneiden verdeckter Leitungen',2,3,[
   ['T','Leitungssuchgerät verwenden, Stromkreise im Arbeitsbereich freischalten']]],
  ['Rückschlag und Schnittverletzungen',2,3,[
   ['T','Schutzhaube und Tiefenanschlag korrekt einstellen, Gerät mit beiden Händen führen'],
   ['O','Scheiben nur bei gezogenem Netzstecker wechseln, Maschine erst nach Stillstand absetzen']]],
  ['Lärm und Hand-Arm-Vibration',3,2,[
   ['P','Gehörschutz tragen (Mauerschlitzfräsen typisch über 100 dB(A))'],
   ['O','Einsatzzeiten begrenzen, Tätigkeiten abwechseln']]]
 ]},
 {id:'sauger',grp:'werkzeug',titel:'Industriesauger / Entstauber',sub:'Absaugung und Reinigung',rg:'GefStoffV, TRGS 559, DIN EN 60335-2-69',psa:['ffp2'],gef:[
  ['Staubfreisetzung beim Entleeren und Filterwechsel',2,2,[
   ['T','Sauger mit Staubklasse M (bei Quarzstaub) einsetzen, Filterabreinigung nutzen'],
   ['T','Entsorgungssack staubdicht verschließen'],
   ['P','Beim Entleeren und Filterwechsel FFP2-Maske tragen']]],
  ['Unbemerkte Staubbelastung durch nachlassende Saugleistung',2,2,[
   ['T','Volumenstromüberwachung (Warnsignal) beachten, Filter regelmäßig abreinigen']]],
  ['Brand durch angesaugte Funken oder Glut',1,2,[
   ['O','Keine Funken, Glut oder brennbaren Flüssigkeiten aufsaugen']]]
 ]},
 {id:'akku',grp:'werkzeug',titel:'Akkuwerkzeuge & Ladegeräte',sub:'Lithium-Ionen-Akkus',rg:'BetrSichV, Herstellerangaben',psa:[],gef:[
  ['Brand von Lithium-Ionen-Akkus beim Laden oder nach Beschädigung',1,3,[
   ['O','Nur Original-Ladegeräte verwenden, auf nicht brennbarer Unterlage laden'],
   ['O','Nicht unbeaufsichtigt über Nacht laden; beschädigte oder heiße Akkus sofort aussondern']]]
 ]},
 {id:'kabel',grp:'taetigkeit',titel:'Kabelzug & Leitungsverlegung',sub:'Kabelrinnen, Trassen, Einziehen',rg:'LasthandhabV, BetrSichV',psa:['handschuhe'],gef:[
  ['Quetsch- und Schnittverletzungen an Händen (Kabelrinnen, Kanten)',2,2,[
   ['T','Grate an Kabelrinnen entfernen, Kantenschutz verwenden'],
   ['P','Schutzhandschuhe tragen']]],
  ['Überlastung beim Einziehen und Ziehen schwerer Kabel',2,2,[
   ['T','Kabelziehstrumpf, Umlenkrollen und Kabeltrommelbock verwenden'],
   ['O','Ausreichend Personen einplanen, Kommandos vorab absprechen']]]
 ]},
 {id:'asbest',grp:'bedingung',titel:'Bestandsgebäude, Baujahr vor 1993',sub:'Asbest und alte Mineralwolle möglich',rg:'GefStoffV, TRGS 519, TRGS 521, DGUV Information 201-012',psa:[],gef:[
  ['Freisetzung von Asbestfasern beim Bohren, Schlitzen, Stemmen',2,3,[
   ['O','Vor Arbeitsbeginn Schadstoffauskunft vom Auftraggeber einholen'],
   ['O','Bei Verdacht (Spachtelmassen, Putze, Fliesenkleber, Floor-Flex-Platten, Leichtbauplatten) Arbeiten sofort einstellen, Bereich sichern, Projektleiter informieren'],
   ['O','Tätigkeiten mit Asbest nur mit Sachkunde nach TRGS 519 und anerkannten emissionsarmen Verfahren (DGUV Information 201-012)']]],
  ['Hautreizung und Einatmen von Fasern aus alter Mineralwolle',2,2,[
   ['O','Alte Mineralwolle (vor 1996) nur nach TRGS 521 bearbeiten, möglichst nicht berühren'],
   ['P','Einweg-Schutzanzug, FFP2-Maske und Handschuhe beim Umgang tragen']]]
 ]},
 {id:'aussen',grp:'bedingung',titel:'Arbeiten im Freien',sub:'Witterung, UV, Wind',rg:'ArbSchG § 5, BetrSichV',psa:['wetter'],gef:[
  ['Nässe, Kälte, Hitze und UV-Strahlung',2,2,[
   ['O','Arbeiten an Witterung anpassen, Pausen im Warmen bzw. im Schatten, Trinkwasser bereitstellen'],
   ['P','Wetterschutzkleidung, bei Sonne Kopfbedeckung und Sonnenschutz tragen']]],
  ['Elektrische Gefährdung bei Nässe',1,3,[
   ['T','Im Freien nur Geräte und Steckverbindungen mit ausreichender Schutzart (mind. IP44) einsetzen']]],
  ['Gewitter und Sturm',1,3,[
   ['O','Bei Gewitter oder Sturm Arbeiten im Freien und auf Hubarbeitsbühnen einstellen']]]
 ]},
 {id:'freileitung',grp:'bedingung',titel:'Freileitungen in der Nähe',sub:'Bühnen, Leitern, lange Teile',rg:'DIN VDE 0105-100, BetrSichV',psa:[],gef:[
  ['Stromüberschlag bei Annäherung an Freileitungen',1,3,[
   ['O','Schutzabstände einhalten: bis 1 kV 1 m, bis 110 kV 3 m, bis 220 kV 4 m, bis 380 kV 5 m'],
   ['O','Bei Unterschreitung Freischaltung oder Abdeckung beim Netzbetreiber beantragen'],
   ['O','Beim Verfahren von Bühnen und beim Tragen langer Teile (Leitern, Rohre) auf Leitungen achten']]]
 ]},
 {id:'dritte',grp:'bedingung',titel:'Bewohnte Räume / öffentlicher Bereich',sub:'Kunden, Mieter, Passanten',rg:'ArbSchG § 8, BaustellV, StVO § 45',psa:['warn'],gef:[
  ['Gefährdung Dritter durch Arbeitsbereich, Werkzeuge und offene Installationen',2,3,[
   ['T','Arbeitsbereich absperren und kennzeichnen'],
   ['O','Offene Verteiler und Anschlussstellen nie unbeaufsichtigt lassen – abdecken oder verschließen'],
   ['O','Bewohner bzw. Nutzer über Arbeiten, Stromabschaltungen und Staub informieren']]],
  ['Gefährdung durch Straßenverkehr (bei Arbeiten im Verkehrsraum)',1,3,[
   ['O','Verkehrsrechtliche Anordnung einholen, Absicherung nach RSA'],
   ['P','Warnkleidung tragen']]]
 ]},
 {id:'allein',grp:'bedingung',titel:'Alleinarbeit',sub:'Einzelner Monteur vor Ort',rg:'DGUV Regel 112-139, DGUV Vorschrift 1',psa:[],gef:[
  ['Keine oder verspätete Hilfe bei Unfall',2,3,[
   ['O','Gefährliche Arbeiten (an elektrischen Anlagen, auf Hubarbeitsbühnen, Leiterarbeiten in größerer Höhe) nicht allein durchführen'],
   ['T','Personen-Notsignal-Gerät oder Mobiltelefon mit vereinbarten Kontrollanrufen'],
   ['O','Arbeitsbeginn, Ende und Aufenthaltsort mit Büro bzw. Projektleiter abstimmen']]]
 ]}
];
KAT.forEach(m=>{m.gef=m.gef.map((g,i)=>({id:String.fromCharCode(97+i),t:g[0],w:g[1],s:g[2],m:g[3]}))});
const EIGENE={id:'eigene',grp:'eigene',titel:'Eigene Gefährdungen',rg:''};

const TEMPLATES=[
 {id:'neubau',titel:'Neubau – Elektroinstallation',text:'Rohbau und Ausbau: Schlitze, Dosen, Leitungen, Verteiler, Erstprüfung',mods:['elektro','pruefen','baustrom','anlege','steh','bohr','kernbohr','flex','fraese','sauger','akku','kabel'],besch:'Elektroinstallation im Neubau: Schlitze und Dosen, Leitungsverlegung, Unterverteilungen, Erstprüfung und Inbetriebnahme'},
 {id:'bestand',titel:'Sanierung im Bestand',text:'Umbau in bewohnten oder genutzten Räumen, älteres Gebäude',mods:['elektro','pruefen','baustrom','steh','bohr','kernbohr','flex','fraese','sauger','akku','asbest','dritte'],besch:'Erneuerung der Elektroinstallation im Bestand bei laufender Nutzung'},
 {id:'halle',titel:'Hallen- & Deckenmontage',text:'Beleuchtung, Kabeltrassen, Montage in der Höhe mit Scherenbühne',mods:['elektro','pruefen','baustrom','schere','hubbuehne','rollgeruest','steh','bohr','flex','akku','kabel'],besch:'Montage von Kabeltrassen und Beleuchtung in einer Halle mit Scherenbühne'},
 {id:'leer',titel:'Leer beginnen',text:'Nur „Baustelle allgemein“ – Bausteine selbst wählen',mods:[],besch:''}
];

const P_SVG=(inner)=>`<svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="23.5" fill="#fff"/><circle cx="24" cy="24" r="21.5" fill="#005387"/>${inner}</svg>`;
const PSA=[
 {id:'schuhe',t:'Sicherheitsschuhe S3',svg:P_SVG('<path d="M17 11h9v14l8 3c3 1 4.5 3 4.5 5.5V36H14v-6l3-2z" fill="#fff"/><path d="M14 33h24.5" stroke="#005387" stroke-width="1.6"/>')},
 {id:'helm',t:'Schutzhelm',svg:P_SVG('<path d="M13 29c0-7 5-12.5 11-12.5S35 22 35 29z" fill="#fff"/><rect x="10" y="29" width="28" height="3.2" rx="1.6" fill="#fff"/><path d="M24 17v7" stroke="#005387" stroke-width="2"/>')},
 {id:'gurt',t:'Auffanggurt (PSAgA)',svg:P_SVG('<circle cx="22" cy="13" r="3.8" fill="#fff"/><path d="M17 19h10l1.2 11h-3.2l-1 10h-4l-1-10h-3.2z" fill="#fff"/><path d="M17.8 20.5l9 8.5M26.2 20.5l-9 8.5" stroke="#005387" stroke-width="1.8"/><path d="M25.5 19.5c5-2 7.5-6.5 7.5-11.5" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round"/>')},
 {id:'brille',t:'Schutzbrille',svg:P_SVG('<circle cx="17" cy="25" r="5.5" fill="none" stroke="#fff" stroke-width="3"/><circle cx="31" cy="25" r="5.5" fill="none" stroke="#fff" stroke-width="3"/><path d="M22.5 24.5q1.5-2.2 3 0M11.6 24L9 20.5M36.4 24L39 20.5" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round"/>')},
 {id:'gesicht',t:'Gesichtsschutz',svg:P_SVG('<circle cx="24" cy="23" r="6.5" fill="#fff"/><path d="M15.5 15h17v14c0 4.5-4 8-8.5 8s-8.5-3.5-8.5-8z" fill="none" stroke="#fff" stroke-width="2.4"/><rect x="14" y="11" width="20" height="3.4" rx="1" fill="#fff"/>')},
 {id:'gehoer',t:'Gehörschutz',svg:P_SVG('<path d="M14 26v-3a10 10 0 0 1 20 0v3" fill="none" stroke="#fff" stroke-width="3"/><rect x="10" y="24" width="7.5" height="12" rx="3.5" fill="#fff"/><rect x="30.5" y="24" width="7.5" height="12" rx="3.5" fill="#fff"/>')},
 {id:'ffp2',t:'Atemschutz FFP2',svg:P_SVG('<path d="M13 21c5-5 17-5 22 0l-1 8c-2.5 5.5-17.5 5.5-20 0z" fill="#fff"/><path d="M13.5 22.5L9 19.5M34.5 22.5L39 19.5M14 28.5l-4.5 3M34 28.5l4.5 3" stroke="#fff" stroke-width="2" stroke-linecap="round"/><text x="24" y="28" text-anchor="middle" font-size="7" font-family="Arial,sans-serif" font-weight="700" fill="#005387">FFP2</text>')},
 {id:'ffp3',t:'Atemschutz FFP3',svg:P_SVG('<path d="M13 21c5-5 17-5 22 0l-1 8c-2.5 5.5-17.5 5.5-20 0z" fill="#fff"/><path d="M13.5 22.5L9 19.5M34.5 22.5L39 19.5M14 28.5l-4.5 3M34 28.5l4.5 3" stroke="#fff" stroke-width="2" stroke-linecap="round"/><text x="24" y="28" text-anchor="middle" font-size="7" font-family="Arial,sans-serif" font-weight="700" fill="#005387">FFP3</text>')},
 {id:'handschuhe',t:'Schutzhandschuhe',svg:P_SVG('<rect x="17" y="21" width="14" height="14" rx="3" fill="#fff"/><rect x="17" y="13" width="3" height="11" rx="1.5" fill="#fff"/><rect x="20.8" y="10.5" width="3" height="13" rx="1.5" fill="#fff"/><rect x="24.6" y="11.5" width="3" height="12" rx="1.5" fill="#fff"/><rect x="28" y="14" width="3" height="10" rx="1.5" fill="#fff"/><rect x="30.5" y="21" width="3.2" height="9" rx="1.6" fill="#fff" transform="rotate(-38 32 25.5)"/><rect x="16" y="34" width="16" height="4" rx="1" fill="#fff"/>')},
 {id:'stoerlicht',t:'Störlichtbogen-Schutz',svg:P_SVG('<path d="M27 8.5L15 27h8.5l-3 12.5L33.5 20H25z" fill="#fff"/>')},
 {id:'warn',t:'Warnkleidung',svg:P_SVG('<path d="M17.5 11l-5.5 6v20h10.5V24.5L24 22l1.5 2.5V37H36V17l-5.5-6-4 5h-5z" fill="#fff"/><path d="M12 29h10.5M25.5 29H36" stroke="#005387" stroke-width="2.2"/>')},
 {id:'wetter',t:'Wetterschutzkleidung',svg:P_SVG('<path d="M15 27a5 5 0 0 1 1.6-9.7A7.5 7.5 0 0 1 31 15.5a6 6 0 0 1 2 11.5z" fill="#fff"/><path d="M18 31l-2 5M24 31l-2 5M30 31l-2 5" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/>')}
];

const STEPS=[['Baustelle','Projekt, Verantwortliche, Notfall'],['Tätigkeiten','Arbeitsmittel und Bedingungen'],['Gefährdungen','Risiko und Maßnahmen'],['PSA & Unterweisung','Schutzausrüstung, Beschäftigte'],['Dokument & Freigabe','Prüfen, PDF, Unterschrift']];
const STOPN={S:'Substitution',T:'Technische Maßnahme',O:'Organisatorische Maßnahme',P:'Persönliche Schutzausrüstung'};
const WL={1:'1 · unwahrscheinlich',2:'2 · möglich',3:'3 · wahrscheinlich'};
const SL={1:'1 · leicht',2:'2 · mittel',3:'3 · schwer / tödlich'};
const LVL=['gering','mittel','hoch'];

/* ---------- Zustand ---------- */
let DOCS={},FIRMA={},CUR=null,VIEW='liste',STEP=1,FILTER='alle',Q='',LOADED=false,MODE='init',DB=null;
let saveTimer=null,saving=false,dirty=false,lastSaved=null,saveErr=null,retried=false;
const LS_KEY='baustellen-gbu-v1',LS_PL='baustellen-gbu-pl';

const $=(s,r=document)=>r.querySelector(s);
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const clone=o=>JSON.parse(JSON.stringify(o));
const uid=()=>'g'+Date.now().toString(36)+Math.random().toString(36).slice(2,7);
const sid=k=>k.replace(/[^a-zA-Z0-9]/g,'_');
function todayISO(){const d=new Date();return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
function fmtDate(v){if(!v)return'';const p=String(v).slice(0,10).split('-');return p.length===3?p[2]+'.'+p[1]+'.'+p[0]:''}
function fmtTime(d){return String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')}
function range(P){const a=fmtDate(P.von),b=fmtDate(P.bis);return a&&b?a+' – '+b:a?'ab '+a:b?'bis '+b:''}
function getPath(o,p){return p.split('.').reduce((a,k)=>a==null?a:a[k],o)}
function setPath(o,p,v){const ks=p.split('.');let a=o;ks.slice(0,-1).forEach(k=>{if(a[k]==null||typeof a[k]!=='object')a[k]={};a=a[k]});a[ks[ks.length-1]]=v}
function lsGet(k,d){try{const v=localStorage.getItem(k);return v==null?d:JSON.parse(v)}catch(e){return d}}
function lsSet(k,v){try{localStorage.setItem(k,JSON.stringify(v));return true}catch(e){return false}}

function newDoc(tplId){
  const t=TEMPLATES.find(x=>x.id===tplId)||TEMPLATES[3];const now=new Date().toISOString();
  return {id:uid(),status:'entwurf',createdAt:now,updatedAt:now,vorlage:t.id,
    projekt:{name:'',nr:'',auftraggeber:'',adresse:'',von:'',bis:'',beschreibung:t.besch||'',projektleiter:lsGet(LS_PL,'')||'',aufsicht:'',ersthelfer:'',sigeko:'',notruf:'112',krankenhaus:'',sammelplatz:''},
    module:t.mods.slice(),gef:{},eigene:[],psa:{},mitarbeiter:'',unterweisung:{durch:'',datum:''},festlegungen:'',freigabe:{erstellt:'',datum:'',naechste:''}};
}
function normalize(d){
  const base={name:'',nr:'',auftraggeber:'',adresse:'',von:'',bis:'',beschreibung:'',projektleiter:'',aufsicht:'',ersthelfer:'',sigeko:'',notruf:'112',krankenhaus:'',sammelplatz:''};
  d.projekt=Object.assign(base,d.projekt||{});
  d.module=Array.isArray(d.module)?d.module:[];d.gef=d.gef&&typeof d.gef==='object'?d.gef:{};
  d.eigene=Array.isArray(d.eigene)?d.eigene:[];d.psa=d.psa&&typeof d.psa==='object'?d.psa:{};
  d.unterweisung=Object.assign({durch:'',datum:''},d.unterweisung||{});
  d.freigabe=Object.assign({erstellt:'',datum:'',naechste:''},d.freigabe||{});
  d.mitarbeiter=d.mitarbeiter||'';d.festlegungen=d.festlegungen||'';d.status=d.status||'entwurf';
  return d;
}

/* ---------- Ableitungen ---------- */
function risk(w,s){const p=w*s;return{p,lvl:p<=2?'gering':p<=4?'mittel':'hoch'}}
function modsOf(d){return KAT.filter(m=>m.immer||d.module.includes(m.id))}
function hazardsOf(d){
  const out=[];
  modsOf(d).forEach(mod=>mod.gef.forEach(h=>{
    const key=mod.id+'.'+h.id,st=d.gef[key]||{},off=st.off||[];
    const ms=h.m.map((m,i)=>({t:m[0],text:m[1],on:!off.includes(i),src:'b',i}));
    (st.extra||[]).forEach((m,i)=>ms.push({t:m.t,text:m.text,on:m.on!==false,src:'x',i}));
    out.push({key,mod,titel:h.t,w:st.w||h.w,s:st.s||h.s,rest:st.rest||'gering',ms,custom:false});
  }));
  d.eigene.forEach(x=>out.push({key:'x:'+x.id,mod:EIGENE,titel:x.titel,w:x.w||2,s:x.s||2,rest:x.rest||'gering',ms:(x.extra||[]).map((m,i)=>({t:m.t,text:m.text,on:m.on!==false,src:'x',i})),custom:true}));
  return out;
}
const sortMs=ms=>ms.slice().sort((a,b)=>'STOP'.indexOf(a.t)-'STOP'.indexOf(b.t));
function psaAuto(d){const s=new Set(['schuhe']);modsOf(d).forEach(m=>(m.psa||[]).forEach(p=>s.add(p)));return s}
function psaFinal(d){const a=psaAuto(d);return PSA.filter(p=>d.psa[p.id]!=null?d.psa[p.id]:a.has(p.id))}
function statusOf(d){if(d.status==='freigegeben'&&d.freigabe&&d.freigabe.naechste&&d.freigabe.naechste<=todayISO())return'faellig';return d.status==='freigegeben'?'freigegeben':'entwurf'}
const STL={entwurf:'Entwurf',freigegeben:'Freigegeben',faellig:'Überprüfung fällig'};
function statusPill(d){const s=statusOf(d);return`<span class="pill st-${s}">${STL[s]}</span>`}
function staff(d){return d.mitarbeiter.split(/\n/).map(s=>s.trim()).filter(Boolean)}
function validate(d){
  const hz=hazardsOf(d),P=d.projekt;
  const noM=hz.filter(h=>!h.ms.some(m=>m.on)).length,hi=hz.filter(h=>h.rest==='hoch').length;
  return[
    {ok:!!(P.name.trim()&&P.adresse.trim()),req:true,t:'Projektbezeichnung und Baustellenadresse angegeben',go:1},
    {ok:noM===0,req:true,t:'Jede Gefährdung hat mindestens eine Schutzmaßnahme',sub:noM?noM+' ohne Maßnahme':'',go:3},
    {ok:hi===0,req:true,t:'Kein hohes Restrisiko',sub:hi?hi+' × hoch – Arbeit so nicht freigeben':'',go:3},
    {ok:!!d.freigabe.erstellt.trim(),req:true,t:'Ersteller der Beurteilung eingetragen',go:5},
    {ok:!!P.aufsicht.trim(),req:false,t:'Aufsichtsführender vor Ort benannt',go:1},
    {ok:!!(P.ersthelfer.trim()&&P.krankenhaus.trim()),req:false,t:'Ersthelfer und Krankenhaus / D-Arzt eingetragen',go:1},
    {ok:staff(d).length>0,req:false,t:'Beschäftigte für die Unterweisung erfasst',go:4}
  ];
}
function stepDone(d,n){
  if(n===1)return!!(d.projekt.name.trim()&&d.projekt.adresse.trim());
  if(n===2)return d.module.length>0;
  if(n===3){const hz=hazardsOf(d);return hz.every(h=>h.ms.some(m=>m.on))&&!hz.some(h=>h.rest==='hoch')}
  if(n===4)return staff(d).length>0;
  return d.status==='freigegeben';
}

/* ---------- Speicher: gemeinsame Ablage auf dem Server ----------
   Früher lag alles in der Artifact-Datenbank von claude.ai. Hier spricht die
   Seite stattdessen /bg/api.php an; die Anmeldung steckt in der Sitzung, der
   CSRF-Schlüssel kommt aus dem Seitenkopf.                                   */
const API='/bg/api.php';
async function api(aktion,daten){
  let r;
  try{
    r=await fetch(API,{
      method:'POST',credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-CSRF-Token':window.BG.csrf},
      body:JSON.stringify(Object.assign({aktion:aktion},daten||{}))
    });
  }catch(e){throw{code:'unavailable'}}
  if(r.status===401)throw{code:'abgemeldet'};
  let j=null;try{j=await r.json()}catch(e){throw{code:'unavailable'}}
  if(!r.ok||!j.ok)throw{code:(j&&j.fehler)||'unavailable'};
  return j;
}
async function laden(){
  const j=await api('liste');
  const next={};
  (j.gbus||[]).forEach(o=>{const d=normalize(o);d.id=o.id;next[d.id]=d});
  DOCS=next;FIRMA=j.firma||{};
}
async function boot(){
  render();
  MODE='db';
  try{await laden();LOADED=true;render()}
  catch(e){LOADED=true;MODE='fehler';render();toast('Ablage nicht erreichbar: '+errText(e))}
  // Andere Nutzer arbeiten in derselben Ablage. Die Liste deshalb regelmäßig
  // auffrischen - aber nur, wenn hier gerade nichts bearbeitet oder
  // gespeichert wird, sonst überschriebe die Antwort eigene Eingaben.
  setInterval(async()=>{
    if(VIEW!=='liste'||dirty||saving||document.hidden)return;
    try{await laden();renderTop();renderMain()}catch(e){}
  },60000);
}
async function persist(doc){
  DOCS[doc.id]=doc;
  await api('speichern',{gbu:doc});
}
async function removeDoc(id){
  delete DOCS[id];
  await api('loeschen',{id:id});
}
async function saveFirma(){
  await api('firma',{firma:FIRMA});
}
function errText(e){
  const c=e&&e.code;
  if(c==='abgemeldet')return'Sitzung abgelaufen – bitte Seite neu laden und erneut anmelden.';
  if(c==='kein_zugriff')return'Keine Schreibberechtigung für diese Ablage.';
  if(c==='zu_gross')return'Die Gefährdungsbeurteilung ist zu umfangreich zum Speichern.';
  if(c==='unavailable')return'Ablage gerade nicht erreichbar – Internetverbindung prüfen.';
  return'Ablage gerade nicht erreichbar.';
}
function touch(keepStatus){
  if(!CUR)return;
  if(!keepStatus&&CUR.status==='freigegeben'){CUR.status='entwurf';toast('GBU geändert – Freigabe zurückgesetzt, bitte erneut freigeben.');updateHead()}
  CUR.updatedAt=new Date().toISOString();dirty=true;saveErr=null;updateSave();
  clearTimeout(saveTimer);saveTimer=setTimeout(flush,800);
}
async function flush(){
  clearTimeout(saveTimer);
  if(!CUR||!dirty)return;
  if(saving){saveTimer=setTimeout(flush,400);return}
  saving=true;dirty=false;const snap=clone(CUR);
  try{await persist(snap);lastSaved=new Date();saveErr=null;retried=false}
  catch(e){
    dirty=true;
    if(!retried&&(e&&(e.code==='unavailable'||e.code==='resource_exhausted'))){retried=true;saving=false;saveTimer=setTimeout(flush,1500+Math.random()*1000);return}
    saveErr=e;toast('Nicht gespeichert: '+errText(e));
  }
  saving=false;updateSave();
  if(dirty&&!saveErr)saveTimer=setTimeout(flush,400);
}
function updateSave(){
  const el=$('#save-state');if(!el)return;
  if(saveErr){el.innerHTML=`<span class="save-err">Nicht gespeichert – ${esc(errText(saveErr))}</span> <button class="btn sm" data-act="retry">Erneut speichern</button>`;return}
  if(dirty||saving){el.textContent='Wird gespeichert …';return}
  el.textContent=lastSaved?'Gespeichert '+fmtTime(lastSaved):'In gemeinsamer Ablage';
}

/* ---------- Render: Rahmen ---------- */
function render(){renderTop();renderMain()}
function renderTop(){
  const st=MODE==='db'?'<span class="store db" title="Alle Angemeldeten sehen dieselben Gefährdungsbeurteilungen"><i></i><span>Gemeinsame Ablage</span></span>':MODE==='fehler'?'<span class="store local" title="Der Server antwortet nicht"><i></i><span>Ablage nicht erreichbar</span></span>':'<span class="store"><i></i><span>Verbinde …</span></span>';
  $('#top').innerHTML=`<header class="top"><div class="top-in">
    <button class="brand" data-act="home" aria-label="Zur Übersicht"><img class="logo" src="${LOGO}" alt="elektromas"><span><b>Gefährdungsbeurteilung</b><small>Baustelle · ${esc(FIRMA.name||'elektromas GmbH')}</small></span></button>
    <div class="top-r">${st}<button class="btn ghost sm" data-act="firma">Firmendaten</button></div>
  </div></header>`;
}
function renderMain(){
  const m=$('#main');
  if(!LOADED){m.innerHTML='<p class="loading">Lade Gefährdungsbeurteilungen …</p>';return}
  m.innerHTML=VIEW==='liste'?listHTML():editorHTML();
  if(VIEW==='editor')updateSave();
}

/* ---------- Übersicht ---------- */
function listDocs(){return Object.values(DOCS).sort((a,b)=>String(b.updatedAt).localeCompare(String(a.updatedAt)))}
function listHTML(){
  const all=listDocs(),c={alle:all.length,entwurf:0,freigegeben:0,faellig:0};
  all.forEach(d=>c[statusOf(d)]++);
  const f=(id,lbl)=>`<button class="f${FILTER===id?' on':''}${id==='faellig'&&c.faellig?' warnf':''}" data-act="filter" data-v="${id}" aria-pressed="${FILTER===id}"><b>${c[id]}</b><span>${lbl}</span></button>`;
  return`<section class="list-head"><div><p class="eyebrow">Arbeitsschutz · §§ 5, 6 ArbSchG</p><h1>Gefährdungsbeurteilungen</h1>
    <p class="lead">Eine GBU je Projekt und Baustelle – aus Tätigkeiten und Arbeitsmitteln zusammengestellt, als PDF für Unterweisung und Baustellenordner.</p></div>
    <button class="btn primary lg" data-act="new">+ Neue Gefährdungsbeurteilung</button></section>
    <div class="filters" role="group" aria-label="Nach Status filtern">${f('alle','Alle')}${f('entwurf','Entwurf')}${f('freigegeben','Freigegeben')}${f('faellig','Überprüfung fällig')}</div>
    <div class="toolbar"><input id="q" type="search" placeholder="Projekt, Nummer oder Adresse suchen" value="${esc(Q)}" aria-label="Suchen"></div>
    <div id="rows">${rowsHTML()}</div>`;
}
function rowsHTML(){
  const q=Q.trim().toLowerCase();
  const list=listDocs().filter(d=>(FILTER==='alle'||statusOf(d)===FILTER)&&(!q||[d.projekt.name,d.projekt.nr,d.projekt.adresse,d.projekt.auftraggeber].join(' ').toLowerCase().includes(q)));
  if(!Object.keys(DOCS).length)return`<div class="rows"><div class="empty"><b>Noch keine Gefährdungsbeurteilung angelegt.</b><br>Mit den Baustellendaten beginnen – Tätigkeiten und Arbeitsmittel wählen Sie in Schritt 2.<div class="tpls"><button class="btn primary" data-act="new">+ Neue Gefährdungsbeurteilung</button></div></div></div>`;
  if(!list.length)return'<div class="rows"><div class="empty">Keine GBU passt zu Suche oder Filter.</div></div>';
  return'<div class="rows">'+list.map(d=>{
    const hz=hazardsOf(d),hi=hz.filter(h=>risk(h.w,h.s).lvl==='hoch').length,rh=hz.filter(h=>h.rest==='hoch').length;
    return`<article class="row"><div class="row-main"><div class="row-title"><button class="linkbtn" data-act="open" data-id="${esc(d.id)}">${esc(d.projekt.name||'Ohne Bezeichnung')}</button>${d.beispiel?'<span class="chip">Beispiel</span>':''}</div>
      <div class="row-meta">${esc([d.projekt.nr,d.projekt.adresse].filter(Boolean).join(' · ')||'Keine Adresse')}</div></div>
      <div class="cell"><span class="cell-l">Zeitraum</span>${esc(range(d.projekt)||'–')}</div>
      <div class="cell"><span class="cell-l">Gefährdungen</span>${hz.length} · ${hi} mit hohem Ausgangsrisiko${rh?` <span class="risk r-hoch">${rh} Restrisiko hoch</span>`:''}</div>
      <div class="cell">${statusPill(d)}</div>
      <div class="row-act"><button class="btn sm" data-act="open" data-id="${esc(d.id)}">Öffnen</button><button class="btn sm ghost" data-act="dup" data-id="${esc(d.id)}">Duplizieren</button><button class="btn sm ghost danger" data-act="del" data-id="${esc(d.id)}">Löschen</button></div></article>`;
  }).join('')+'</div>';
}

/* ---------- Editor ---------- */
function editorHTML(){
  return`<div class="ed-head"><button class="btn ghost sm" data-act="home">← Übersicht</button>
    <div class="ed-title"><h1 id="ed-title">${esc(CUR.projekt.name||'Neue Gefährdungsbeurteilung')}</h1><div class="ed-sub"><span id="ed-status">${statusPill(CUR)}</span><span id="save-state"></span></div></div>
    <button class="btn" data-act="pdf">PDF herunterladen</button></div>
    <div class="ed-grid"><nav class="steps" id="steps" aria-label="Schritte">${stepsHTML()}</nav><section class="ed-body" id="ed-body">${bodyHTML()}</section></div>`;
}
function stepsHTML(){return STEPS.map((s,i)=>{const n=i+1;return`<button class="stp${n===STEP?' on':''}${stepDone(CUR,n)&&n!==STEP?' done':''}" data-act="go" data-v="${n}"${n===STEP?' aria-current="step"':''}><span class="n">${stepDone(CUR,n)&&n!==STEP?'✓':n}</span><span><b>${s[0]}</b><small>${s[1]}</small></span></button>`}).join('')}
function renderSteps(){const e=$('#steps');if(e)e.innerHTML=stepsHTML()}
function renderBody(){const e=$('#ed-body');if(e)e.innerHTML=bodyHTML()}
function updateHead(){const t=$('#ed-title');if(t)t.textContent=CUR.projekt.name||'Neue Gefährdungsbeurteilung';const s=$('#ed-status');if(s)s.innerHTML=statusPill(CUR)}
function bodyHTML(){return[s1,s2,s3,s4,s5][STEP-1]()+footHTML()}
function footHTML(){
  const prev=STEP>1?`<button class="btn" data-act="go" data-v="${STEP-1}">← ${STEPS[STEP-2][0]}</button>`:'<span></span>';
  const next=STEP<5?`<button class="btn primary" data-act="go" data-v="${STEP+1}">Weiter: ${STEPS[STEP][0]} →</button>`:'<button class="btn primary" data-act="home">Fertig – zur Übersicht</button>';
  return`<div class="step-foot no-print">${prev}${next}</div>`;
}
function fld(label,path,o){
  o=o||{};const v=getPath(CUR,path);const id='f_'+path.replace(/\./g,'_');
  const ctl=o.rows?`<textarea id="${id}" data-bind="${path}" rows="${o.rows}" placeholder="${esc(o.ph||'')}">${esc(v)}</textarea>`:`<input id="${id}" data-bind="${path}" type="${o.type||'text'}" value="${esc(v)}" placeholder="${esc(o.ph||'')}">`;
  return`<div class="fld${o.full?' full':''}"><label for="${id}">${esc(label)}${o.req?' <span class="req" aria-hidden="true">*</span>':''}</label>${ctl}${o.hint?`<small class="hint">${esc(o.hint)}</small>`:''}</div>`;
}
const RESCUE='<svg class="rescue" viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="2" fill="#1F7A4D"/><path d="M9.5 5h5v4.5H19v5h-4.5V19h-5v-4.5H5v-5h4.5z" fill="#fff"/></svg>';

function s1(){
  return`<div class="panel"><h2>Projekt & Baustelle</h2><p class="panel-sub">Diese Angaben stehen im Kopf des Dokuments.</p><div class="fgrid">
    ${fld('Projektbezeichnung','projekt.name',{req:1,ph:'z. B. Neubau Kita Sonnenblick'})}
    ${fld('Projekt- / Auftragsnummer','projekt.nr',{ph:'z. B. P-2026-114'})}
    ${fld('Auftraggeber / Bauherr','projekt.auftraggeber')}
    ${fld('Baustellenadresse','projekt.adresse',{req:1,ph:'Straße, PLZ Ort'})}
    ${fld('Beginn','projekt.von',{type:'date'})}
    ${fld('Ende (voraussichtlich)','projekt.bis',{type:'date'})}
    ${fld('Art und Umfang der Arbeiten','projekt.beschreibung',{rows:3,full:1})}
  </div></div>
  <div class="panel"><h2>Verantwortliche</h2><p class="panel-sub">Wer die GBU verantwortet und wer vor Ort weisungsbefugt ist.</p><div class="fgrid">
    ${fld('Projektleiter','projekt.projektleiter')}
    ${fld('Aufsichtsführender vor Ort','projekt.aufsicht',{hint:'z. B. Obermonteur, weisungsbefugt'})}
    ${fld('Ersthelfer vor Ort','projekt.ersthelfer',{hint:'Mindestens ein ausgebildeter Ersthelfer je Baustelle'})}
    ${fld('SiGeKo','projekt.sigeko',{ph:'Name und Telefon, falls bestellt'})}
  </div></div>
  <div class="panel"><h2>${RESCUE}Notfall & Erste Hilfe</h2><p class="panel-sub">Erscheint hervorgehoben im Dokument – für den Aushang auf der Baustelle.</p><div class="fgrid">
    ${fld('Notruf','projekt.notruf')}
    ${fld('Nächstes Krankenhaus / D-Arzt','projekt.krankenhaus',{ph:'Name, Adresse, Telefon'})}
    ${fld('Sammelplatz / Treffpunkt','projekt.sammelplatz',{ph:'z. B. Bauzufahrt'})}
  </div></div>`;
}
function s2(){
  const tile=m=>{const on=m.immer||CUR.module.includes(m.id);return`<label class="tile${on?' on':''}${m.immer?' fixed':''}"><input type="checkbox" data-mod="${m.id}"${on?' checked':''}${m.immer?' disabled':''}><span class="tile-t">${esc(m.titel)}</span><span class="tile-s">${esc(m.sub)}</span><span class="tile-rg">${esc(m.rg)}</span><span class="tile-n">${m.gef.length} Gefährdung${m.gef.length===1?'':'en'}</span></label>`};
  return`<div class="panel"><h2>Schnellauswahl</h2><p class="panel-sub">Vorlagen ergänzen die Auswahl, sie ersetzen sie nicht – mehrere lassen sich kombinieren, wenn sich die Arbeiten mischen. Danach einzelne Bausteine an- oder abwählen.</p>
    <div class="tpls">${TEMPLATES.filter(t=>t.id!=='leer').map(t=>`<button class="btn" data-act="tpl" data-v="${t.id}">+ ${esc(t.titel)}</button>`).join('')}${CUR.module.length?'<button class="btn ghost danger" data-act="clearmods">Auswahl leeren</button>':''}</div></div>
    ${GROUPS.map(g=>{const ms=KAT.filter(m=>m.grp===g.id);return`<section class="grp"><h2 class="grp-h">${esc(g.titel)}</h2><div class="tiles">${ms.map(tile).join('')}</div></section>`}).join('')}`;
}
function s3(){
  const hz=hazardsOf(CUR),n=hz.length,cnt={gering:0,mittel:0,hoch:0};hz.forEach(h=>cnt[risk(h.w,h.s).lvl]++);
  const rh=hz.filter(h=>h.rest==='hoch').length,noM=hz.filter(h=>!h.ms.some(m=>m.on)).length;
  const pct=v=>n?(v/n*100).toFixed(1):0;
  let html=`<div class="panel"><h2>Gefährdungen und Schutzmaßnahmen</h2><p class="panel-sub">Vorgaben aus dem Katalog prüfen: Risiko anpassen, Maßnahmen abwählen oder ergänzen, Restrisiko festlegen.</p>
    <div class="sum"><div><b>${n}</b><span>Gefährdungen</span></div><div><b>${cnt.hoch}</b><span>hohes Ausgangsrisiko</span></div><div class="${rh?'bad':''}"><b>${rh}</b><span>Restrisiko hoch</span></div><div class="${noM?'bad':''}"><b>${noM}</b><span>ohne Maßnahme</span></div></div>
    <div class="dist" title="Ausgangsrisiko: ${cnt.gering} gering, ${cnt.mittel} mittel, ${cnt.hoch} hoch"><i class="g" style="width:${pct(cnt.gering)}%"></i><i class="m" style="width:${pct(cnt.mittel)}%"></i><i class="h" style="width:${pct(cnt.hoch)}%"></i></div>
    <div class="legend"><span>Risiko = Wahrscheinlichkeit × Schwere:</span><span class="risk r-gering">gering 1–2</span><span class="risk r-mittel">mittel 3–4</span><span class="risk r-hoch">hoch 6–9</span></div>
    <div class="legend"><span>Rangfolge nach § 4 ArbSchG:</span>${'STOP'.split('').map(t=>`<span><span class="stop s-${t}">${t}</span>${STOPN[t]}</span>`).join('')}</div></div>`;
  let last=null;
  hz.forEach(h=>{
    if(h.mod!==last){if(last)html+='</section>';last=h.mod;html+=`<section class="modsec"><div class="modsec-h"><h3>${esc(h.mod.titel)}</h3>${h.mod.rg?`<span class="rg">${esc(h.mod.rg)}</span>`:''}</div>`}
    html+=hzHTML(h);
  });
  if(last)html+='</section>';
  html+=`<div class="panel"><h2>Weitere Gefährdung ergänzen</h2><p class="panel-sub">Für Besonderheiten dieser Baustelle, die der Katalog nicht abdeckt.</p>
    <div class="addx"><input id="nx-titel" placeholder="z. B. Arbeiten im laufenden Klinikbetrieb" data-enterx="1" aria-label="Neue Gefährdung"><button class="btn" data-act="addx">Gefährdung hinzufügen</button></div></div>`;
  return html;
}
function hzHTML(h){
  const r=risk(h.w,h.s),k=esc(h.key),id=sid(h.key),noM=!h.ms.some(m=>m.on);
  const opt=(L,v)=>[1,2,3].map(i=>`<option value="${i}"${i===v?' selected':''}>${L[i]}</option>`).join('');
  return`<div class="hz${noM||h.rest==='hoch'?' alert':''}" id="hz-${id}">
    <div class="hz-top"><h4 class="hz-t">${esc(h.titel)}</h4>
      <div class="hz-risk"><select data-hz="${k}" data-f="w" aria-label="Wahrscheinlichkeit">${opt(WL,h.w)}</select><span>×</span><select data-hz="${k}" data-f="s" aria-label="Schwere">${opt(SL,h.s)}</select><span class="risk r-${r.lvl}" title="Ausgangsrisiko">${r.lvl} · ${r.p}</span></div></div>
    <ul class="ms">${sortMs(h.ms).map(m=>`<li class="${m.on?'':'off'}"><label><input type="checkbox" data-mz="${k}" data-src="${m.src}" data-i="${m.i}"${m.on?' checked':''}><span class="stop s-${m.t}" title="${STOPN[m.t]}">${m.t}</span><span class="mt">${esc(m.text)}</span></label>${m.src==='x'?`<button class="xbtn" data-act="delm" data-key="${k}" data-i="${m.i}" title="Maßnahme entfernen" aria-label="Maßnahme entfernen">×</button>`:''}</li>`).join('')}</ul>
    <div class="hz-add"><select id="nt-${id}" aria-label="Art der Maßnahme">${'STOP'.split('').map(t=>`<option value="${t}"${t==='O'?' selected':''}>${t}</option>`).join('')}</select><input id="nm-${id}" data-enter="${k}" placeholder="Maßnahme ergänzen …" aria-label="Maßnahme ergänzen"><button class="btn sm" data-act="addm" data-key="${k}">Hinzufügen</button></div>
    ${noM?'<p class="hz-warn">Keine Maßnahme ausgewählt – bitte mindestens eine festlegen.</p>':''}
    <div class="hz-rest"><span class="lbl">Restrisiko nach Maßnahmen</span><div class="seg" role="group" aria-label="Restrisiko">${LVL.map(v=>`<button class="${v===h.rest?'on r-'+v:''}" data-act="rest" data-key="${k}" data-v="${v}" aria-pressed="${v===h.rest}">${v}</button>`).join('')}</div>
      ${h.custom?`<button class="btn sm ghost danger" data-act="delx" data-key="${k}">Gefährdung entfernen</button>`:''}</div>
  </div>`;
}
function s4(){
  const auto=psaAuto(CUR),fin=new Set(psaFinal(CUR).map(p=>p.id));
  return`<div class="panel"><h2>Persönliche Schutzausrüstung</h2><p class="panel-sub">Automatisch aus den gewählten Tätigkeiten vorbelegt. Antippen, um PSA hinzuzufügen oder zu entfernen.</p>
    <div class="psa-grid">${PSA.map(p=>{const on=fin.has(p.id),manual=CUR.psa[p.id]!=null;return`<button class="psa${on?' on':''}" data-act="psa" data-v="${p.id}" aria-pressed="${on}">${p.svg}<span>${esc(p.t)}</span><small>${manual?'manuell '+(on?'ergänzt':'entfernt'):auto.has(p.id)?'aus Tätigkeiten':'nicht erforderlich'}</small></button>`}).join('')}</div></div>
  <div class="panel"><h2>Unterweisung</h2><p class="panel-sub">Beschäftigte vor Arbeitsaufnahme anhand dieser GBU unterweisen (§ 12 ArbSchG, § 4 DGUV Vorschrift 1). Die Namen erscheinen im Dokument mit Unterschriftsfeld.</p><div class="fgrid">
    ${fld('Beschäftigte auf der Baustelle (ein Name pro Zeile)','mitarbeiter',{rows:5,full:1,ph:'A. Monteur\nB. Monteur\nC. Auszubildender'})}
    ${fld('Unterwiesen durch','unterweisung.durch')}
    ${fld('Datum der Unterweisung','unterweisung.datum',{type:'date'})}
  </div></div>
  <div class="panel"><h2>Weitere Festlegungen</h2><p class="panel-sub">Absprachen, Besonderheiten, Zuständigkeiten – erscheint im Dokument.</p><div class="fgrid">${fld('Festlegungen','festlegungen',{rows:4,full:1,ph:'z. B. Hubarbeitsbühne über Vermieter, Einweisung bei Anlieferung'})}</div></div>`;
}
function s5(){
  const v=validate(CUR),reqOk=v.filter(x=>x.req).every(x=>x.ok),fr=CUR.status==='freigegeben';
  return`<div class="panel no-print"><h2>Prüfung vor Freigabe</h2><p class="panel-sub">Pflichtpunkte müssen erfüllt sein; Empfehlungen sollten es sein.</p>
    <ul class="chk">${v.map(x=>`<li><span class="ci ${x.ok?'ok':x.req?'bad':'warn'}" aria-hidden="true">${x.ok?'✓':'!'}</span><span>${esc(x.t)}${x.req?'':' <small>(Empfehlung)</small>'}${x.sub?`<br><small>${esc(x.sub)}</small>`:''}${!x.ok&&x.go!==5?` <button class="btn sm ghost" data-act="go" data-v="${x.go}">zu Schritt ${x.go}</button>`:''}</span></li>`).join('')}</ul></div>
  <div class="panel no-print"><h2>Freigabe</h2><p class="panel-sub">Wer die Beurteilung erstellt hat und wann sie spätestens überprüft wird – zusätzlich immer bei geänderten Arbeitsbedingungen.</p><div class="fgrid">
    ${fld('Erstellt von','freigabe.erstellt',{req:1,ph:'Name Projektleiter'})}
    ${fld('Datum','freigabe.datum',{type:'date'})}
    ${fld('Nächste Überprüfung','freigabe.naechste',{type:'date',hint:'Vorschlag: Ende der Baustelle'})}
  </div>
  <div class="actions">${fr?'<button class="btn" data-act="entwurf">Zurück auf Entwurf</button>':`<button class="btn primary" data-act="freigeben"${reqOk?'':' aria-disabled="true"'}>GBU freigeben</button>`}
    <button class="btn" data-act="pdf">PDF herunterladen</button><button class="btn" data-act="print">Drucken</button></div></div>
  <div class="sheet-wrap">${docHTML(CUR)}</div>`;
}

/* ---------- Dokument (Vorschau / Druck) ---------- */
function docHTML(d){
  const P=d.projekt,hz=hazardsOf(d);let rows='',n=0,last=null;
  hz.forEach(h=>{
    if(h.mod!==last){last=h.mod;rows+=`<tr class="grp"><td colspan="5">${esc(h.mod.titel)}${h.mod.rg?` <span class="sh-rg">${esc(h.mod.rg)}</span>`:''}</td></tr>`}
    n++;const r=risk(h.w,h.s),ms=sortMs(h.ms).filter(m=>m.on);
    rows+=`<tr><td class="c">${n}</td><td>${esc(h.titel)}</td><td class="c rf-${r.lvl}">${r.lvl}<br><small>W${h.w} × S${h.s}</small></td><td>${ms.length?`<ul class="ml">${ms.map(m=>`<li><b>${m.t}</b><span>${esc(m.text)}</span></li>`).join('')}</ul>`:'<em>keine Maßnahme festgelegt</em>'}</td><td class="c rf-${h.rest}">${h.rest}</td></tr>`;
  });
  const acts=GROUPS.filter(g=>g.id!=='basis').map(g=>{const ms=modsOf(d).filter(m=>m.grp===g.id);return ms.length?`<tr><td class="l">${esc(g.titel)}</td><td>${ms.map(m=>esc(m.titel)).join(', ')}</td></tr>`:''}).join('');
  const names=staff(d);while(names.length<6)names.push('');
  const kv=(a,b,c,e)=>`<tr><td class="l">${a}</td><td>${esc(b)}</td><td class="l">${c}</td><td>${esc(e)}</td></tr>`;
  const firmLine=[FIRMA.sifa?'Fachkraft für Arbeitssicherheit: '+FIRMA.sifa:'',FIRMA.arzt?'Betriebsarzt: '+FIRMA.arzt:''].filter(Boolean).join(' · ');
  return`<article class="sheet" id="sheet">
    <header class="sh-head"><div><div class="sh-firm">${esc(FIRMA.name||'elektromas GmbH')}${FIRMA.anschrift?' · '+esc(FIRMA.anschrift):''}</div><h2>Gefährdungsbeurteilung Baustelle</h2><div class="sh-sub">nach §§ 5, 6 ArbSchG, § 3 BetrSichV und DGUV Vorschrift 1</div></div>
      <div class="sh-meta"><img class="sh-logo" src="${LOGO}" alt="elektromas">Stand: ${fmtDate(d.updatedAt)}<br>Status: ${STL[statusOf(d)]}</div></header>
    <table>${kv('Projekt',P.name,'Projekt-Nr.',P.nr)}${kv('Auftraggeber',P.auftraggeber,'Zeitraum',range(P))}
      <tr><td class="l">Baustelle</td><td colspan="3">${esc(P.adresse)}</td></tr><tr><td class="l">Art der Arbeiten</td><td colspan="3">${esc(P.beschreibung)}</td></tr>
      ${kv('Projektleiter',P.projektleiter,'Aufsichtsführender',P.aufsicht)}${kv('Ersthelfer vor Ort',P.ersthelfer,'SiGeKo',P.sigeko)}</table>
    <table class="nf"><tr><th colspan="3">Notfall und Erste Hilfe</th></tr><tr><td><b>Notruf:</b> ${esc(P.notruf||'112')}</td><td><b>Krankenhaus / D-Arzt:</b> ${esc(P.krankenhaus)}</td><td><b>Sammelplatz:</b> ${esc(P.sammelplatz)}</td></tr></table>
    <h3>Tätigkeiten, Arbeitsmittel und Bedingungen</h3>
    <table>${acts||'<tr><td>Nur allgemeine Baustellengefährdungen</td></tr>'}</table>
    <h3>Gefährdungen und Schutzmaßnahmen</h3>
    <table><thead><tr><th style="width:30px">Nr.</th><th style="width:26%">Gefährdung</th><th style="width:70px">Risiko</th><th>Schutzmaßnahmen</th><th style="width:62px">Rest­risiko</th></tr></thead><tbody>${rows}</tbody></table>
    <p class="sh-legend">Risiko = Wahrscheinlichkeit (1–3) × Schadensschwere (1–3): 1–2 gering, 3–4 mittel, 6–9 hoch. Maßnahmen nach STOP: S Substitution, T technisch, O organisatorisch, P persönliche Schutzausrüstung.</p>
    <h3>Persönliche Schutzausrüstung</h3><p>${psaFinal(d).map(p=>esc(p.t)).join(' · ')}</p>
    ${d.festlegungen.trim()?`<h3>Weitere Festlegungen</h3><p>${esc(d.festlegungen).replace(/\n/g,'<br>')}</p>`:''}
    <h3>Unterweisung der Beschäftigten</h3>
    <p>Die Beschäftigten wurden vor Arbeitsaufnahme anhand dieser Gefährdungsbeurteilung unterwiesen${d.unterweisung.durch?' durch '+esc(d.unterweisung.durch):''}${d.unterweisung.datum?' am '+fmtDate(d.unterweisung.datum):''}.</p>
    <table class="sig"><thead><tr><th>Name</th><th style="width:110px">Datum</th><th style="width:38%">Unterschrift</th></tr></thead><tbody>${names.map(x=>`<tr><td>${esc(x)}</td><td></td><td></td></tr>`).join('')}</tbody></table>
    <h3>Freigabe</h3>
    <table class="sig"><thead><tr><th style="width:30%">Funktion</th><th>Name</th><th style="width:110px">Datum</th><th style="width:30%">Unterschrift</th></tr></thead><tbody>
      <tr><td>Erstellt (Projektleitung)</td><td>${esc(d.freigabe.erstellt)}</td><td>${fmtDate(d.freigabe.datum)}</td><td></td></tr>
      <tr><td>Aufsichtsführender vor Ort</td><td>${esc(P.aufsicht)}</td><td></td><td></td></tr>
      <tr><td>Nächste Überprüfung</td><td colspan="3">${d.freigabe.naechste?fmtDate(d.freigabe.naechste)+' sowie ':''}bei jeder Änderung der Arbeitsbedingungen</td></tr></tbody></table>
    ${firmLine?`<div class="sh-foot">${esc(firmLine)}</div>`:''}
  </article>`;
}

/* ---------- PDF ---------- */
/* Das Logo liegt als Datei auf dem Server. jsPDF kann aber nur Bilddaten
   einbetten, keine Adressen - deshalb einmal laden und als Daten merken. */
let LOGO_DATA=null;
async function logoDaten(){
  if(LOGO_DATA!==null)return LOGO_DATA;
  try{
    const r=await fetch(LOGO);
    const b=await r.blob();
    LOGO_DATA=await new Promise(res=>{const fr=new FileReader();fr.onload=()=>res(String(fr.result));fr.onerror=()=>res('');fr.readAsDataURL(b)});
  }catch(e){LOGO_DATA=''}
  return LOGO_DATA;
}
function loadScript(src){return new Promise((res,rej)=>{const s=document.createElement('script');s.src=src;s.onload=res;s.onerror=()=>rej(new Error('load'));document.head.appendChild(s)})}
async function ensurePdf(){
  if(!(window.jspdf&&window.jspdf.jsPDF))await loadScript('/bg/vendor/jspdf.umd.min.js');
  if(!window.jspdf.jsPDF.API.autoTable)await loadScript('/bg/vendor/jspdf.plugin.autotable.min.js');
}
function AT(pdf,o){
  if(typeof pdf.autoTable==='function')return pdf.autoTable(o);
  const g=window.jspdf_autotable||window.jspdfAutoTable;
  if(g&&g.applyPlugin){g.applyPlugin(window.jspdf.jsPDF);return pdf.autoTable(o)}
  if(g&&g.default)return g.default(pdf,o);
  throw new Error('autotable');
}
const cl=s=>String(s==null?'':s).replace(/[„“”]/g,'"').replace(/[‚‘’]/g,"'").replace(/[–—]/g,'-').replace(/­/g,'').replace(/…/g,'...');
const RF={gering:[225,240,231],mittel:[251,239,199],hoch:[247,224,223]};
async function makePdf(d){
  await ensurePdf();
  const pdf=new window.jspdf.jsPDF({unit:'mm',format:'a4'});
  const M=14,W=210,P=d.projekt,ink=[26,31,35];
  const base={fontSize:8.5,cellPadding:1.6,textColor:ink,lineColor:[201,206,200],lineWidth:0.2,valign:'top'};
  const lab={fontStyle:'bold',fillColor:[243,244,241],cellWidth:34};
  let y=13;
  const logo=await logoDaten();
  if(logo){try{pdf.addImage(logo,'PNG',W-M-26,8,26,9.96)}catch(e){console.warn(e)}}
  pdf.setFont('helvetica','bold');pdf.setFontSize(9);pdf.setTextColor(91,101,107);
  pdf.text(cl((FIRMA.name||'elektromas GmbH')+(FIRMA.anschrift?'  ·  '+FIRMA.anschrift:'')),M,y);
  pdf.setTextColor(...ink);pdf.setFontSize(18);pdf.text('Gefährdungsbeurteilung Baustelle',M,y+9);
  pdf.setFont('helvetica','normal');pdf.setFontSize(8.5);pdf.setTextColor(91,101,107);
  pdf.text('nach §§ 5, 6 ArbSchG, § 3 BetrSichV und DGUV Vorschrift 1',M,y+14);
  pdf.text(cl('Stand: '+fmtDate(d.updatedAt)),W-M,y+9,{align:'right'});
  pdf.text(cl('Status: '+STL[statusOf(d)]),W-M,y+14,{align:'right'});
  pdf.setDrawColor(1,53,131);pdf.setLineWidth(0.7);pdf.line(M,y+17,W-M,y+17);
  pdf.setDrawColor(243,160,0);pdf.setLineWidth(1.4);pdf.line(M,y+17.9,M+34,y+17.9);
  y+=20;
  const next=()=>pdf.lastAutoTable.finalY;
  AT(pdf,{startY:y,theme:'grid',margin:{left:M,right:M},styles:base,columnStyles:{0:lab,2:lab},body:[
    ['Projekt',cl(P.name),'Projekt-Nr.',cl(P.nr)],
    ['Auftraggeber',cl(P.auftraggeber),'Zeitraum',cl(range(P))],
    ['Baustelle',{content:cl(P.adresse),colSpan:3}],
    ['Art der Arbeiten',{content:cl(P.beschreibung),colSpan:3}],
    ['Projektleiter',cl(P.projektleiter),'Aufsichtsführender',cl(P.aufsicht)],
    ['Ersthelfer vor Ort',cl(P.ersthelfer),'SiGeKo',cl(P.sigeko)]]});
  AT(pdf,{startY:next()+3,theme:'grid',margin:{left:M,right:M},styles:base,headStyles:{fillColor:[31,122,77],textColor:255,fontStyle:'bold'},
    head:[[{content:'Notfall und Erste Hilfe',colSpan:3}]],body:[['Notruf: '+cl(P.notruf||'112'),'Krankenhaus / D-Arzt: '+cl(P.krankenhaus),'Sammelplatz: '+cl(P.sammelplatz)]]});
  y=next()+7;
  const head=t=>{if(y>268){pdf.addPage();y=16}pdf.setFont('helvetica','bold');pdf.setFontSize(11);pdf.setTextColor(...ink);pdf.text(cl(t),M,y);y+=2};
  head('Tätigkeiten, Arbeitsmittel und Bedingungen');
  const acts=GROUPS.filter(g=>g.id!=='basis').map(g=>{const ms=modsOf(d).filter(m=>m.grp===g.id);return ms.length?[g.titel,cl(ms.map(m=>m.titel).join(', '))]:null}).filter(Boolean);
  AT(pdf,{startY:y,theme:'grid',margin:{left:M,right:M},styles:base,columnStyles:{0:{fontStyle:'bold',fillColor:[243,244,241],cellWidth:50}},body:acts.length?acts:[['Nur allgemeine Baustellengefährdungen','']]});
  y=next()+7;head('Gefährdungen und Schutzmaßnahmen');
  const body=[];let n=0,last=null;
  hazardsOf(d).forEach(h=>{
    if(h.mod!==last){last=h.mod;body.push([{content:cl(h.mod.titel)+(h.mod.rg?'    '+cl(h.mod.rg):''),colSpan:5,styles:{fillColor:[228,234,245],textColor:[1,53,131],fontStyle:'bold'}}])}
    n++;const r=risk(h.w,h.s);
    const ms=sortMs(h.ms).filter(m=>m.on).map(m=>m.t+'   '+cl(m.text)).join('\n')||'- keine Maßnahme festgelegt -';
    body.push([String(n),cl(h.titel),{content:r.lvl+'\nW'+h.w+' x S'+h.s,styles:{fillColor:RF[r.lvl],halign:'center'}},ms,{content:h.rest,styles:{fillColor:RF[h.rest],halign:'center'}}]);
  });
  AT(pdf,{startY:y,theme:'grid',margin:{left:M,right:M,top:16},styles:Object.assign({},base,{fontSize:8}),headStyles:{fillColor:[1,53,131],textColor:255,fontStyle:'bold'},
    head:[['Nr.','Gefährdung','Risiko','Schutzmaßnahmen (S/T/O/P)','Restrisiko']],body,rowPageBreak:'avoid',
    columnStyles:{0:{cellWidth:9,halign:'center'},1:{cellWidth:44},2:{cellWidth:18},4:{cellWidth:18}}});
  y=next()+4;
  pdf.setFont('helvetica','normal');pdf.setFontSize(7.5);pdf.setTextColor(91,101,107);
  const leg=pdf.splitTextToSize('Risiko = Wahrscheinlichkeit (1-3) x Schadensschwere (1-3): 1-2 gering, 3-4 mittel, 6-9 hoch. Maßnahmen nach STOP: S Substitution, T technisch, O organisatorisch, P persönliche Schutzausrüstung.',W-2*M);
  pdf.text(leg,M,y);y+=leg.length*3.4+5;
  const para=(t)=>{pdf.setFont('helvetica','normal');pdf.setFontSize(9);pdf.setTextColor(...ink);const ls=pdf.splitTextToSize(cl(t),W-2*M);if(y+ls.length*4>285){pdf.addPage();y=16}pdf.text(ls,M,y+3);y+=ls.length*4+5};
  head('Persönliche Schutzausrüstung');para(psaFinal(d).map(p=>p.t).join(', '));
  if(d.festlegungen.trim()){head('Weitere Festlegungen');para(d.festlegungen)}
  if(y>225){pdf.addPage();y=16}
  head('Unterweisung der Beschäftigten');
  para('Die Beschäftigten wurden vor Arbeitsaufnahme anhand dieser Gefährdungsbeurteilung unterwiesen'+(d.unterweisung.durch?' durch '+d.unterweisung.durch:'')+(d.unterweisung.datum?' am '+fmtDate(d.unterweisung.datum):'')+' (§ 12 ArbSchG, § 4 DGUV Vorschrift 1).');
  const names=staff(d);while(names.length<6)names.push('');
  AT(pdf,{startY:y-2,theme:'grid',margin:{left:M,right:M},styles:Object.assign({},base,{minCellHeight:8,valign:'middle'}),headStyles:{fillColor:[1,53,131],textColor:255,fontStyle:'bold'},
    head:[['Name','Datum','Unterschrift']],body:names.map(x=>[cl(x),'','']),columnStyles:{1:{cellWidth:30},2:{cellWidth:70}},rowPageBreak:'avoid'});
  y=next()+7;if(y>245){pdf.addPage();y=16}
  head('Freigabe');
  AT(pdf,{startY:y,theme:'grid',margin:{left:M,right:M},styles:Object.assign({},base,{minCellHeight:9,valign:'middle'}),headStyles:{fillColor:[1,53,131],textColor:255,fontStyle:'bold'},
    head:[['Funktion','Name','Datum','Unterschrift']],columnStyles:{0:{cellWidth:48},2:{cellWidth:26},3:{cellWidth:55}},body:[
      ['Erstellt (Projektleitung)',cl(d.freigabe.erstellt),fmtDate(d.freigabe.datum),''],
      ['Aufsichtsführender vor Ort',cl(P.aufsicht),'',''],
      ['Nächste Überprüfung',{content:(d.freigabe.naechste?fmtDate(d.freigabe.naechste)+' sowie ':'')+'bei jeder Änderung der Arbeitsbedingungen',colSpan:3}]]});
  const firmLine=[FIRMA.sifa?'Fachkraft für Arbeitssicherheit: '+FIRMA.sifa:'',FIRMA.arzt?'Betriebsarzt: '+FIRMA.arzt:''].filter(Boolean).join('   ·   ');
  const pages=pdf.internal.getNumberOfPages();
  for(let i=1;i<=pages;i++){
    pdf.setPage(i);pdf.setFont('helvetica','normal');pdf.setFontSize(7.5);pdf.setTextColor(120,128,133);
    pdf.text(cl('GBU '+(P.name||'')+(P.nr?' ('+P.nr+')':'')),M,291);
    pdf.text('Seite '+i+' von '+pages,W-M,291,{align:'right'});
    if(firmLine&&i===pages)pdf.text(cl(firmLine),M,287);
  }
  return pdf.output('blob');
}
function fileName(d){const b=(d.projekt.nr||d.projekt.name||'Baustelle').replace(/[^\wäöüÄÖÜß-]+/g,'_').replace(/^_+|_+$/g,'').slice(0,60)||'Baustelle';return'GBU_'+b+'_'+todayISO()+'.pdf'}
async function doPdf(btn){
  const btns=document.querySelectorAll('[data-act="pdf"]');btns.forEach(b=>{b.disabled=true});
  toast('PDF wird erstellt …',60000);
  try{
    await flush();
    const blob=await makePdf(CUR);
    const name=fileName(CUR);
    const url=URL.createObjectURL(blob);
    const a=document.createElement('a');a.href=url;a.download=name;
    document.body.appendChild(a);a.click();a.remove();
    // Erst nach dem Speichern freigeben, sonst bricht der Download ab.
    setTimeout(()=>URL.revokeObjectURL(url),10000);
    toast('PDF gespeichert: '+name);
  }catch(e){
    if(e&&e.message==='load')toast('PDF-Baustein konnte nicht geladen werden – Internetverbindung prüfen oder „Drucken“ verwenden.');
    else{toast('PDF konnte nicht erstellt werden – bitte „Drucken“ verwenden.');console.error(e)}
  }finally{btns.forEach(b=>{b.disabled=false})}
}

/* ---------- Dialoge & Toast ---------- */
let toastT=null;
function toast(msg,ms){const t=$('#toast');t.textContent=msg;t.hidden=false;clearTimeout(toastT);toastT=setTimeout(()=>{t.hidden=true},ms||3800)}
function modal(html){$('#modal').innerHTML=`<div class="backdrop" data-act="close-bg"><div class="dialog" role="dialog" aria-modal="true">${html}</div></div>`;const f=$('#modal input,#modal button.tpl,#modal .btn');if(f)f.focus()}
function closeModal(){$('#modal').innerHTML=''}
function firmaModal(){
  const f=(l,k,ph)=>`<div class="fld"><label for="fm_${k}">${l}</label><input id="fm_${k}" value="${esc(FIRMA[k]||'')}" placeholder="${esc(ph||'')}"></div>`;
  modal(`<h2>Firmendaten</h2><p>Erscheinen im Kopf und Fuß jeder Gefährdungsbeurteilung – für alle Nutzer des Portals.</p>
    <div class="fgrid">${f('Firmenname','name','z. B. Muster Elektrotechnik GmbH')}${f('Anschrift','anschrift','Straße, PLZ Ort')}${f('Fachkraft für Arbeitssicherheit','sifa','Name, Telefon')}${f('Betriebsarzt','arzt','Name, Telefon')}</div>
    <div class="dlg-foot"><button class="btn" data-act="close">Abbrechen</button><button class="btn primary" data-act="firma-save">Speichern</button></div>`);
}

/* ---------- Aktionen ---------- */
function openDoc(id){const d=DOCS[id];if(!d)return;CUR=normalize(clone(d));VIEW='editor';STEP=1;dirty=false;saveErr=null;lastSaved=null;renderMain();window.scrollTo(0,0)}
async function createDoc(tpl){
  const d=newDoc(tpl);closeModal();CUR=d;VIEW='editor';STEP=1;dirty=false;saveErr=null;lastSaved=null;renderMain();window.scrollTo(0,0);
  dirty=true;flush();const n=$('#f_projekt_name');if(n)n.focus();
}
async function leaveEditor(){if(CUR){await flush();DOCS[CUR.id]=clone(CUR)}CUR=null;VIEW='liste';closeModal();renderMain();window.scrollTo(0,0)}
function hzState(key){if(key.indexOf('x:')===0)return CUR.eigene.find(x=>'x:'+x.id===key);return CUR.gef[key]||(CUR.gef[key]={})}
function addMeasure(key){
  const id=sid(key),inp=document.getElementById('nm-'+id),sel=document.getElementById('nt-'+id);if(!inp)return;
  const text=inp.value.trim();if(!text){inp.focus();return}
  const st=hzState(key);st.extra=st.extra||[];st.extra.push({t:sel.value,text,on:true});touch();renderBody();renderSteps();
  const again=document.getElementById('nm-'+id);if(again)again.focus();
}
function addHazard(){
  const inp=$('#nx-titel');const t=inp&&inp.value.trim();if(!t){if(inp)inp.focus();return}
  const x={id:uid(),titel:t,w:2,s:2,rest:'gering',extra:[]};CUR.eigene.push(x);touch();renderBody();renderSteps();
  const el=document.getElementById('nm-'+sid('x:'+x.id));if(el){el.scrollIntoView({block:'center'});el.focus()}
}

document.addEventListener('click',async e=>{
  const b=e.target.closest('[data-act]');if(!b)return;
  const a=b.dataset.act,v=b.dataset.v,key=b.dataset.key;
  if(a==='close-bg'){if(e.target===b)closeModal();return}
  switch(a){
    case'home':e.preventDefault();leaveEditor();break;
    case'new':createDoc('leer');break;
    case'create':createDoc(v);break;
    case'clearmods':CUR.module=[];touch();renderBody();renderSteps();break;
    case'close':closeModal();break;
    case'filter':FILTER=v;renderMain();break;
    case'open':openDoc(b.dataset.id);break;
    case'dup':{const s=DOCS[b.dataset.id];if(!s)return;const d=normalize(clone(s));const now=new Date().toISOString();
      d.id=uid();d.projekt.name=(d.projekt.name||'GBU')+' (Kopie)';d.status='entwurf';d.beispiel=false;d.createdAt=now;d.updatedAt=now;d.freigabe={erstellt:'',datum:'',naechste:''};d.unterweisung.datum='';
      try{await persist(d);toast('Kopie angelegt – Projektdaten anpassen.');openDoc(d.id)}catch(err){toast('Kopie nicht gespeichert: '+errText(err))}break}
    case'del':{const d=DOCS[b.dataset.id];if(!d)return;modal(`<h2>GBU löschen?</h2><p>„${esc(d.projekt.name||'Ohne Bezeichnung')}“ wird endgültig gelöscht – für alle Nutzer. Das lässt sich nicht rückgängig machen.</p><div class="dlg-foot"><button class="btn" data-act="close">Abbrechen</button><button class="btn danger-fill" data-act="del-yes" data-id="${esc(d.id)}">Endgültig löschen</button></div>`);break}
    case'del-yes':closeModal();try{await removeDoc(b.dataset.id);toast('GBU gelöscht.')}catch(err){toast('Löschen fehlgeschlagen: '+errText(err))}renderMain();break;
    case'firma':firmaModal();break;
    case'firma-save':['name','anschrift','sifa','arzt'].forEach(k=>{const i=document.getElementById('fm_'+k);FIRMA[k]=i?i.value.trim():''});
      closeModal();try{await saveFirma();toast('Firmendaten gespeichert.')}catch(err){toast('Nicht gespeichert: '+errText(err))}renderTop();if(VIEW==='liste')renderMain();else renderBody();break;
    case'go':STEP=+v;renderSteps();renderBody();window.scrollTo(0,0);break;
    case'tpl':{const t=TEMPLATES.find(x=>x.id===v),neu=t.mods.filter(m=>!CUR.module.includes(m));
      CUR.module=CUR.module.concat(neu);if(!CUR.projekt.beschreibung)CUR.projekt.beschreibung=t.besch;touch();renderBody();renderSteps();
      toast(neu.length?'Vorlage „'+t.titel+'“ ergänzt: '+neu.length+' Baustein'+(neu.length===1?'':'e')+' hinzugefügt.':'Alle Bausteine dieser Vorlage waren schon ausgewählt.');break}
    case'psa':{const auto=psaAuto(CUR).has(v),cur=CUR.psa[v]!=null?CUR.psa[v]:auto,nv=!cur;if(nv===auto)delete CUR.psa[v];else CUR.psa[v]=nv;touch();renderBody();break}
    case'addm':addMeasure(key);break;
    case'delm':{const st=hzState(key);if(st&&st.extra){st.extra.splice(+b.dataset.i,1);touch();renderBody();renderSteps()}break}
    case'rest':{const st=hzState(key);st.rest=v;touch();renderBody();renderSteps();break}
    case'addx':addHazard();break;
    case'delx':CUR.eigene=CUR.eigene.filter(x=>'x:'+x.id!==key);touch();renderBody();renderSteps();break;
    case'freigeben':{const miss=validate(CUR).filter(x=>x.req&&!x.ok);if(miss.length){toast('Freigabe noch nicht möglich: '+miss[0].t+'.');return}
      CUR.status='freigegeben';if(!CUR.freigabe.datum)CUR.freigabe.datum=todayISO();if(!CUR.freigabe.naechste&&CUR.projekt.bis)CUR.freigabe.naechste=CUR.projekt.bis;
      touch(true);updateHead();renderSteps();renderBody();toast('GBU freigegeben.');break}
    case'entwurf':CUR.status='entwurf';touch(true);updateHead();renderSteps();renderBody();break;
    case'pdf':doPdf(b);break;
    case'print':try{window.print()}catch(err){toast('Drucken ist in dieser Ansicht nicht möglich – bitte PDF herunterladen.')}break;
    case'retry':saveErr=null;dirty=true;retried=false;flush();break;
  }
});
document.addEventListener('input',e=>{
  const el=e.target;
  if(el.id==='q'){Q=el.value;const r=$('#rows');if(r)r.innerHTML=rowsHTML();return}
  if(el.dataset.bind&&CUR){
    setPath(CUR,el.dataset.bind,el.value);touch(el.dataset.bind.indexOf('freigabe.')===0);
    if(el.dataset.bind==='projekt.name')updateHead();
    if(el.dataset.bind==='projekt.projektleiter')lsSet(LS_PL,el.value);
    renderSteps();
  }
});
document.addEventListener('change',e=>{
  const el=e.target;if(!CUR)return;
  if(el.dataset.mod){const id=el.dataset.mod;CUR.module=el.checked?CUR.module.concat(CUR.module.includes(id)?[]:[id]):CUR.module.filter(x=>x!==id);touch();renderBody();renderSteps();return}
  if(el.dataset.hz){const st=hzState(el.dataset.hz);st[el.dataset.f]=+el.value;touch();renderBody();return}
  if(el.dataset.mz){
    const st=hzState(el.dataset.mz),i=+el.dataset.i;
    if(el.dataset.src==='b'){st.off=(st.off||[]).filter(x=>x!==i);if(!el.checked)st.off.push(i)}
    else if(st.extra&&st.extra[i])st.extra[i].on=el.checked;
    touch();renderBody();renderSteps();
  }
});
document.addEventListener('keydown',e=>{
  if(e.key==='Escape'&&$('#modal').innerHTML){closeModal();return}
  if(e.key==='Enter'&&e.target.dataset){
    if(e.target.dataset.enter){e.preventDefault();addMeasure(e.target.dataset.enter)}
    else if(e.target.dataset.enterx){e.preventDefault();addHazard()}
  }
});
window.addEventListener('beforeunload',()=>{if(dirty)flush()});

boot();
})();

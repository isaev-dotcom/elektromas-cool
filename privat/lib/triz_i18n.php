<?php
/**
 * Business TRIZ Portal — Oberflächentexte in Deutsch und Russisch.
 *
 * Nur die Oberfläche wird übersetzt, nicht der Inhalt: Ein russischer Beitrag
 * bleibt russisch, auch wenn die Bedienung auf Deutsch steht. Beides zu
 * mischen ist ausdrücklich vorgesehen - viele Quellen gibt es nur in einer
 * Sprache, und eine maschinelle Übersetzung würde Fachbegriffe verfälschen.
 *
 * Fehlt ein Schlüssel auf Russisch, erscheint der deutsche Text. Ein sichtbar
 * deutscher Satz ist besser als ein leeres Feld oder ein technischer
 * Schlüsselname.
 */

declare(strict_types=1);

function triz_texte(): array
{
    return [

        // --- Allgemein -----------------------------------------------------
        'portal'            => ['de' => 'Business TRIZ Portal',      'ru' => 'Портал «Бизнес‑ТРИЗ»'],
        'firma'             => ['de' => 'elektromas GmbH',           'ru' => 'elektromas GmbH'],
        'intern'            => ['de' => 'Interner Bereich',          'ru' => 'Внутренний раздел'],
        'anmelden'          => ['de' => 'Anmelden',                  'ru' => 'Войти'],
        'abmelden'          => ['de' => 'Abmelden',                  'ru' => 'Выйти'],
        'speichern'         => ['de' => 'Speichern',                 'ru' => 'Сохранить'],
        'abbrechen'         => ['de' => 'Abbrechen',                 'ru' => 'Отмена'],
        'loeschen'          => ['de' => 'Löschen',                   'ru' => 'Удалить'],
        'zurueck'           => ['de' => 'Zurück',                    'ru' => 'Назад'],
        'weiter'            => ['de' => 'Weiter',                    'ru' => 'Далее'],
        'alle'              => ['de' => 'Alle',                      'ru' => 'Все'],
        'mehr'              => ['de' => 'Mehr',                      'ru' => 'Подробнее'],
        'oeffnen'           => ['de' => 'Öffnen',                    'ru' => 'Открыть'],
        'kategorie'         => ['de' => 'Kategorie',                 'ru' => 'Категория'],
        'kategorien'        => ['de' => 'Kategorien',                'ru' => 'Категории'],
        'sprache'           => ['de' => 'Sprache',                   'ru' => 'Язык'],
        'quelle'            => ['de' => 'Quelle',                    'ru' => 'Источник'],
        'quellen'           => ['de' => 'Quellen',                   'ru' => 'Источники'],
        'datum'             => ['de' => 'Datum',                     'ru' => 'Дата'],
        'suchen'            => ['de' => 'Suchen',                    'ru' => 'Поиск'],
        'suche'             => ['de' => 'Suche',                     'ru' => 'Поиск'],
        'filter'            => ['de' => 'Filter',                    'ru' => 'Фильтр'],
        'keine_treffer'     => ['de' => 'Keine Treffer.',            'ru' => 'Ничего не найдено.'],
        'noch_leer'         => ['de' => 'Hier ist noch nichts.',     'ru' => 'Здесь пока пусто.'],
        'favorit'           => ['de' => 'Favorit',                   'ru' => 'Избранное'],
        'favorit_setzen'    => ['de' => 'Zu Favoriten',              'ru' => 'В избранное'],
        'favorit_entfernen' => ['de' => 'Favorit entfernen',         'ru' => 'Убрать из избранного'],
        'kommentare'        => ['de' => 'Kommentare',                'ru' => 'Комментарии'],
        'kommentar_schreiben' => ['de' => 'Kommentar schreiben',     'ru' => 'Написать комментарий'],
        'absenden'          => ['de' => 'Absenden',                  'ru' => 'Отправить'],
        'deutsch'           => ['de' => 'Deutsch',                   'ru' => 'Немецкий'],
        'russisch'          => ['de' => 'Russisch',                  'ru' => 'Русский'],
        'englisch'          => ['de' => 'Englisch',                  'ru' => 'Английский'],
        'hell'              => ['de' => 'Hell',                      'ru' => 'Светлая'],
        'dunkel'            => ['de' => 'Dunkel',                    'ru' => 'Тёмная'],
        'automatisch'       => ['de' => 'Automatisch',               'ru' => 'Авто'],
        'darstellung'       => ['de' => 'Darstellung',               'ru' => 'Оформление'],

        // --- Navigation ----------------------------------------------------
        'nav_dashboard'     => ['de' => 'Übersicht',                 'ru' => 'Обзор'],
        'nav_news'          => ['de' => 'TRIZ News',                 'ru' => 'Новости ТРИЗ'],
        'nav_ru'            => ['de' => 'Russische Quellen',         'ru' => 'Русскоязычные источники'],
        'nav_videos'        => ['de' => 'Videothek',                 'ru' => 'Видеотека'],
        'nav_wissen'        => ['de' => 'Wissensdatenbank',          'ru' => 'База знаний'],
        'nav_assistent'     => ['de' => 'KI-Assistent',              'ru' => 'ИИ‑ассистент'],
        'nav_bibliothek'    => ['de' => 'TRIZ-Bibliothek',           'ru' => 'Библиотека ТРИЗ'],
        'nav_tag'           => ['de' => 'Tagesübersicht',            'ru' => 'Сводка дня'],
        'nav_favoriten'     => ['de' => 'Favoriten',                 'ru' => 'Избранное'],
        'nav_verwaltung'    => ['de' => 'Verwaltung',                'ru' => 'Администрирование'],
        'nav_menue'         => ['de' => 'Menü',                      'ru' => 'Меню'],

        // --- Anmeldung -----------------------------------------------------
        'login_titel'       => ['de' => 'Anmeldung',                 'ru' => 'Вход'],
        'login_lead'        => [
            'de' => 'Das Business TRIZ Portal ist den Mitarbeitenden der elektromas GmbH vorbehalten. Der Zugang ist von dem des Schulungsbereichs getrennt – hier gelten eine eigene Einladung und ein eigenes Passwort.',
            'ru' => 'Портал «Бизнес‑ТРИЗ» доступен только сотрудникам elektromas GmbH. Доступ отделён от учебного раздела: здесь действуют отдельное приглашение и отдельный пароль.',
        ],
        'email'             => ['de' => 'E-Mail-Adresse',            'ru' => 'Адрес эл. почты'],
        'passwort'          => ['de' => 'Passwort',                  'ru' => 'Пароль'],
        'passwort_wdh'      => ['de' => 'Passwort wiederholen',      'ru' => 'Повторите пароль'],
        'passwort_vergessen'=> ['de' => 'Passwort vergessen?',       'ru' => 'Забыли пароль?'],
        'name'              => ['de' => 'Vor- und Nachname',         'ru' => 'Имя и фамилия'],
        'login_fehler'      => ['de' => 'E-Mail-Adresse oder Passwort ist falsch.', 'ru' => 'Неверный адрес эл. почты или пароль.'],
        'login_leer'        => ['de' => 'Bitte E-Mail-Adresse und Passwort eingeben.', 'ru' => 'Введите адрес эл. почты и пароль.'],
        'login_gesperrt'    => ['de' => 'Zu viele Fehlversuche. Bitte warten Sie %d Minuten.', 'ru' => 'Слишком много неудачных попыток. Подождите %d мин.'],
        'konto_gesperrt'    => ['de' => 'Dieser Zugang ist gesperrt. Bitte wenden Sie sich an isaev@elektromas.de.', 'ru' => 'Этот доступ заблокирован. Обратитесь: isaev@elektromas.de.'],
        'konto_ohne_passwort' => ['de' => 'Für diesen Zugang wurde noch kein Passwort gesetzt. Bitte nutzen Sie den Link aus Ihrer Einladung.', 'ru' => 'Для этого доступа ещё не задан пароль. Воспользуйтесь ссылкой из приглашения.'],
        'abgemeldet_sicherheit' => ['de' => 'Sie wurden aus Sicherheitsgründen abgemeldet.', 'ru' => 'Вы были отключены из соображений безопасности.'],
        'kein_zugang'       => [
            'de' => 'Sie haben noch keinen Zugang? Zugänge zum Portal vergibt ausschließlich die Geschäftsführung. Wenden Sie sich an isaev@elektromas.de.',
            'ru' => 'Ещё нет доступа? Доступ к порталу выдаёт только руководство. Обратитесь: isaev@elektromas.de.',
        ],
        'einladung_titel'   => ['de' => 'Zugang einrichten',         'ru' => 'Настройка доступа'],
        'einladung_lead'    => ['de' => 'Für %s wurde ein Zugang zum Business TRIZ Portal angelegt. Vergeben Sie hier Ihr Passwort.', 'ru' => 'Для %s создан доступ к порталу «Бизнес‑ТРИЗ». Задайте здесь свой пароль.'],
        'einladung_ungueltig' => ['de' => 'Diese Einladung ist abgelaufen oder wurde bereits eingelöst.', 'ru' => 'Приглашение истекло или уже использовано.'],
        'einladung_fertig'  => ['de' => 'Ihr Zugang ist eingerichtet.', 'ru' => 'Доступ настроен.'],
        'jetzt_anmelden'    => ['de' => 'Jetzt anmelden',            'ru' => 'Войти'],
        'passwort_regel'    => ['de' => 'Mindestens %d Zeichen. Ein ganzer Satz ist sicherer und leichter zu merken als eine kurze Folge aus Sonderzeichen.', 'ru' => 'Не менее %d знаков. Целая фраза надёжнее и легче запоминается, чем короткий набор символов.'],
        'passwort_ungleich' => ['de' => 'Die beiden Passwörter stimmen nicht überein.', 'ru' => 'Пароли не совпадают.'],
        'name_fehlt'        => ['de' => 'Bitte geben Sie Ihren Namen an.', 'ru' => 'Укажите своё имя.'],
        'reset_titel'       => ['de' => 'Passwort zurücksetzen',     'ru' => 'Сброс пароля'],
        'reset_lead'        => ['de' => 'Geben Sie Ihre E-Mail-Adresse an. Falls dazu ein Portalzugang besteht, schicken wir Ihnen einen Link zum Zurücksetzen.', 'ru' => 'Укажите адрес эл. почты. Если доступ к порталу существует, мы отправим ссылку для сброса.'],
        'reset_gesendet'    => ['de' => 'Falls zu dieser Adresse ein Zugang besteht, ist die E-Mail unterwegs. Bitte sehen Sie auch im Spam-Ordner nach.', 'ru' => 'Если доступ с таким адресом существует, письмо отправлено. Проверьте также папку «Спам».'],
        'reset_neu'         => ['de' => 'Neues Passwort vergeben',   'ru' => 'Задать новый пароль'],
        'reset_fertig'      => ['de' => 'Das Passwort wurde geändert.', 'ru' => 'Пароль изменён.'],
        'reset_ungueltig'   => ['de' => 'Dieser Link ist abgelaufen oder wurde bereits benutzt.', 'ru' => 'Ссылка истекла или уже использована.'],

        // --- Dashboard -----------------------------------------------------
        'dash_willkommen'   => ['de' => 'Willkommen, %s',            'ru' => 'Здравствуйте, %s'],
        'dash_lead'         => ['de' => 'Zentrale Wissens- und Innovationsplattform für Business TRIZ.', 'ru' => 'Центральная платформа знаний и инноваций по бизнес‑ТРИЗ.'],
        'dash_news'         => ['de' => 'Neueste TRIZ-News',         'ru' => 'Свежие новости ТРИЗ'],
        'dash_ru'           => ['de' => 'Aus russischen Quellen',    'ru' => 'Из русскоязычных источников'],
        'dash_videos'       => ['de' => 'Neue Videos',               'ru' => 'Новые видео'],
        'dash_dokumente'    => ['de' => 'Neue interne Dokumente',    'ru' => 'Новые внутренние документы'],
        'dash_empfehlung'   => ['de' => 'Empfehlung des Tages',      'ru' => 'Рекомендация дня'],
        'dash_favoriten'    => ['de' => 'Meine Favoriten',           'ru' => 'Моё избранное'],
        'dash_stand'        => ['de' => 'Letzte Sammlung: %s',       'ru' => 'Последний сбор: %s'],
        'dash_nie'          => ['de' => 'noch nicht gelaufen',       'ru' => 'ещё не выполнялся'],

        // --- News ----------------------------------------------------------
        'news_titel'        => ['de' => 'TRIZ News',                 'ru' => 'Новости ТРИЗ'],
        'news_lead'         => ['de' => 'Täglich automatisch gesammelt aus Fachportalen, Blogs und Nachrichtenquellen.', 'ru' => 'Ежедневно собирается автоматически из отраслевых порталов, блогов и новостных источников.'],
        'ru_titel'          => ['de' => 'Russische Informationsquellen', 'ru' => 'Русскоязычные источники'],
        'ru_lead'           => ['de' => 'Fachportale, TRIZ-Communities, Forschungsinstitute und Veröffentlichungen russischsprachiger TRIZ-Experten.', 'ru' => 'Отраслевые порталы, сообщества ТРИЗ, научные институты и публикации русскоязычных экспертов ТРИЗ.'],
        'zur_quelle'        => ['de' => 'Zur Quelle',                'ru' => 'К источнику'],
        'gefunden_am'       => ['de' => 'Gefunden am %s',            'ru' => 'Найдено %s'],
        'beitraege_anzahl'  => ['de' => '%d Beiträge',               'ru' => 'Материалов: %d'],

        // --- Videos --------------------------------------------------------
        'videos_titel'      => ['de' => 'YouTube Wissenscenter',     'ru' => 'Видеоцентр знаний'],
        'videos_lead'       => ['de' => 'Automatisch gesammelte Videos aus deutschen, russischen und internationalen Kanälen.', 'ru' => 'Автоматически собранные видео с немецких, русских и международных каналов.'],
        'kanal'             => ['de' => 'Kanal',                     'ru' => 'Канал'],
        'dauer'             => ['de' => 'Dauer',                     'ru' => 'Длительность'],
        'video_ansehen'     => ['de' => 'Auf YouTube ansehen',       'ru' => 'Смотреть на YouTube'],
        'video_extern'      => [
            'de' => 'Das Video wird bei YouTube abgespielt. Erst beim Klick baut Ihr Browser eine Verbindung dorthin auf – die Vorschaubilder liegen auf unserem Server.',
            'ru' => 'Видео воспроизводится на YouTube. Соединение устанавливается только по нажатию — превью хранятся на нашем сервере.',
        ],

        // --- Wissensdatenbank ----------------------------------------------
        'wissen_titel'      => ['de' => 'Interne Wissensdatenbank',  'ru' => 'Внутренняя база знаний'],
        'wissen_lead'       => ['de' => 'Dokumente der elektromas GmbH. Sie liegen außerhalb des Web-Verzeichnisses und werden nur nach Anmeldung ausgeliefert.', 'ru' => 'Документы elektromas GmbH. Они хранятся вне веб‑каталога и выдаются только после входа.'],
        'ordner'            => ['de' => 'Ordner',                    'ru' => 'Папка'],
        'hochladen'         => ['de' => 'Hochladen',                 'ru' => 'Загрузить'],
        'datei'             => ['de' => 'Datei',                     'ru' => 'Файл'],
        'titel'             => ['de' => 'Titel',                     'ru' => 'Заголовок'],
        'beschreibung'      => ['de' => 'Beschreibung',              'ru' => 'Описание'],
        'schlagwoerter'     => ['de' => 'Schlagwörter',              'ru' => 'Ключевые слова'],
        'schlagwoerter_hilfe' => ['de' => 'Mit Komma getrennt.',     'ru' => 'Через запятую.'],
        'version'           => ['de' => 'Version',                   'ru' => 'Версия'],
        'neue_version'      => ['de' => 'Neue Version',              'ru' => 'Новая версия'],
        'herunterladen'     => ['de' => 'Herunterladen',             'ru' => 'Скачать'],
        'downloads'         => ['de' => 'Abrufe',                    'ru' => 'Загрузок'],
        'hochgeladen_von'   => ['de' => 'Hochgeladen von %s',        'ru' => 'Загрузил(а): %s'],
        'versionen_zeigen'  => ['de' => 'Frühere Versionen',         'ru' => 'Прежние версии'],
        'upload_fehler_typ' => ['de' => 'Dieser Dateityp ist nicht zugelassen.', 'ru' => 'Этот тип файла не разрешён.'],
        'upload_fehler_gross' => ['de' => 'Die Datei ist größer als %s.', 'ru' => 'Файл больше, чем %s.'],
        'upload_ok'         => ['de' => 'Das Dokument wurde gespeichert.', 'ru' => 'Документ сохранён.'],
        'upload_hinweis'    => ['de' => 'Zugelassen: PDF, Word, Excel, PowerPoint, Text und Video. Höchstens %s je Datei.', 'ru' => 'Разрешено: PDF, Word, Excel, PowerPoint, текст и видео. Не более %s на файл.'],

        // --- KI-Assistent ---------------------------------------------------
        'ki_titel'          => ['de' => 'Business-TRIZ-Assistent',   'ru' => 'Ассистент «Бизнес‑ТРИЗ»'],
        'ki_lead'           => ['de' => 'Fragen zu TRIZ, Widersprüche analysieren, Lösungsansätze und Ideen entwickeln – auf Deutsch oder Russisch.', 'ru' => 'Вопросы по ТРИЗ, анализ противоречий, поиск решений и идей — на немецком или русском.'],
        'ki_frage'          => ['de' => 'Ihre Frage',                'ru' => 'Ваш вопрос'],
        'ki_fragen'         => ['de' => 'Fragen',                    'ru' => 'Спросить'],
        'ki_verlauf_loeschen' => ['de' => 'Verlauf löschen',         'ru' => 'Очистить историю'],
        'ki_aus'            => [
            'de' => 'Der Assistent ist noch nicht eingerichtet. In der config.php fehlt der Abschnitt triz.ki_api_schluessel. Bis dahin bleiben alle anderen Bereiche uneingeschränkt nutzbar.',
            'ru' => 'Ассистент ещё не настроен: в config.php отсутствует triz.ki_api_schluessel. До тех пор все остальные разделы работают без ограничений.',
        ],
        'ki_fehler'         => ['de' => 'Der Assistent antwortet gerade nicht. Bitte später erneut versuchen.', 'ru' => 'Ассистент сейчас не отвечает. Попробуйте позже.'],
        'ki_quellen'        => ['de' => 'Herangezogene interne Unterlagen', 'ru' => 'Использованные внутренние материалы'],
        'ki_hinweis'        => [
            'de' => 'Der Assistent durchsucht die interne Wissensdatenbank und die TRIZ-Bibliothek und schickt die passenden Auszüge zusammen mit Ihrer Frage an das Sprachmodell. Prüfen Sie die Antwort, bevor Sie danach handeln.',
            'ru' => 'Ассистент ищет по внутренней базе знаний и библиотеке ТРИЗ и отправляет подходящие фрагменты вместе с вопросом языковой модели. Проверяйте ответ, прежде чем действовать.',
        ],
        'ki_vorschlag_1'    => ['de' => 'Formuliere den technischen Widerspruch in unserem Angebotsprozess.', 'ru' => 'Сформулируй техническое противоречие в нашем процессе подготовки предложений.'],
        'ki_vorschlag_2'    => ['de' => 'Welche TRIZ-Prinzipien passen zu langen Rüstzeiten in der Vorfertigung?', 'ru' => 'Какие принципы ТРИЗ подходят при долгой переналадке в предпроизводстве?'],
        'ki_vorschlag_3'    => ['de' => 'Entwickle drei Ideen zur Geschäftsmodellinnovation im Elektrohandwerk.', 'ru' => 'Предложи три идеи инновации бизнес‑модели в электромонтажном ремесле.'],

        // --- Bibliothek ------------------------------------------------------
        'bib_titel'         => ['de' => 'TRIZ Wissensbibliothek',    'ru' => 'Библиотека знаний ТРИЗ'],
        'bib_lead'          => ['de' => 'Die Grundlagen in strukturierter Form – nachschlagen, lernen, anwenden.', 'ru' => 'Основы в структурированном виде — справка, обучение, применение.'],
        'bib_prinzip'       => ['de' => 'Die 40 innovativen Prinzipien', 'ru' => '40 приёмов'],
        'bib_parameter'     => ['de' => 'Die 39 technischen Parameter', 'ru' => '39 технических параметров'],
        'bib_matrix'        => ['de' => 'Widerspruchsmatrix',        'ru' => 'Матрица противоречий'],
        'bib_trend'         => ['de' => 'Entwicklungsgesetze',       'ru' => 'Законы развития систем'],
        'bib_methode'       => ['de' => 'Business-TRIZ-Methoden',    'ru' => 'Методы бизнес‑ТРИЗ'],
        'bib_werkzeug'      => ['de' => 'Innovations- und Management-Werkzeuge', 'ru' => 'Инструменты инноваций и управления'],
        'bib_beispiel'      => ['de' => 'Praxisbeispiele',           'ru' => 'Примеры из практики'],
        'bib_anwendung'     => ['de' => 'Anwendung',                 'ru' => 'Применение'],
        'matrix_verbessert' => ['de' => 'Verbessern',                'ru' => 'Улучшаем'],
        'matrix_verschlechtert' => ['de' => 'Verschlechtert sich',   'ru' => 'Ухудшается'],
        'matrix_zeigen'     => ['de' => 'Prinzipien anzeigen',       'ru' => 'Показать приёмы'],
        'matrix_leer'       => [
            'de' => 'Die Widerspruchsmatrix ist noch nicht eingespielt. Ein Administrator kann sie in der Verwaltung als CSV importieren; die 39 Parameter und die 40 Prinzipien stehen bereits vollständig zur Verfügung.',
            'ru' => 'Матрица противоречий ещё не загружена. Администратор может импортировать её в разделе администрирования в виде CSV; 39 параметров и 40 приёмов уже доступны полностью.',
        ],
        'matrix_kein_eintrag' => ['de' => 'Für diese Kombination nennt die Matrix kein Prinzip. Das kommt vor – arbeiten Sie dann mit den Entwicklungsgesetzen oder der Stoff-Feld-Analyse weiter.', 'ru' => 'Для этой комбинации матрица не даёт приёма. Так бывает — тогда работайте с законами развития систем или вепольным анализом.'],

        // --- Tagesübersicht --------------------------------------------------
        'tag_titel'         => ['de' => 'Tagesübersicht',            'ru' => 'Сводка дня'],
        'tag_lead'          => ['de' => 'Was seit dem Vortag hinzugekommen ist.', 'ru' => 'Что появилось со вчерашнего дня.'],
        'tag_heute'         => ['de' => 'Heute',                     'ru' => 'Сегодня'],
        'tag_nichts'        => ['de' => 'An diesem Tag ist nichts hinzugekommen.', 'ru' => 'За этот день ничего не добавилось.'],
        'tag_neue_artikel'  => ['de' => 'Neue Artikel',              'ru' => 'Новые статьи'],
        'tag_neue_videos'   => ['de' => 'Neue Videos',               'ru' => 'Новые видео'],
        'tag_neue_dokumente'=> ['de' => 'Neue interne Dokumente',    'ru' => 'Новые внутренние документы'],
        'tag_vortag'        => ['de' => 'Vorheriger Tag',            'ru' => 'Предыдущий день'],
        'tag_folgetag'      => ['de' => 'Nächster Tag',              'ru' => 'Следующий день'],

        // --- Favoriten -------------------------------------------------------
        'fav_titel'         => ['de' => 'Meine Favoriten',           'ru' => 'Моё избранное'],
        'fav_lead'          => ['de' => 'Alles, was Sie sich gemerkt haben – über alle Bereiche hinweg.', 'ru' => 'Всё, что вы сохранили — по всем разделам.'],
        'fav_leer'          => ['de' => 'Sie haben noch nichts als Favorit gespeichert. Das Sternsymbol an jedem Eintrag legt ihn hier ab.', 'ru' => 'Вы пока ничего не добавили в избранное. Значок звезды у любой записи помещает её сюда.'],

        // --- Verwaltung ------------------------------------------------------
        'verw_titel'        => ['de' => 'Verwaltung',                'ru' => 'Администрирование'],
        'verw_benutzer'     => ['de' => 'Benutzer',                  'ru' => 'Пользователи'],
        'verw_quellen'      => ['de' => 'Quellen',                   'ru' => 'Источники'],
        'verw_kategorien'   => ['de' => 'Kategorien',                'ru' => 'Категории'],
        'verw_statistik'    => ['de' => 'Statistik',                 'ru' => 'Статистика'],
        'verw_protokoll'    => ['de' => 'Protokoll',                 'ru' => 'Журнал'],
        'verw_matrix'       => ['de' => 'Widerspruchsmatrix',        'ru' => 'Матрица противоречий'],
        'einladen'          => ['de' => 'Person einladen',           'ru' => 'Пригласить пользователя'],
        'einladung_neu'     => ['de' => 'Einladung neu',             'ru' => 'Новое приглашение'],
        'rolle'             => ['de' => 'Rolle',                     'ru' => 'Роль'],
        'rolle_admin'       => ['de' => 'Administrator',             'ru' => 'Администратор'],
        'rolle_mitarbeiter' => ['de' => 'Mitarbeiter',               'ru' => 'Сотрудник'],
        'status'            => ['de' => 'Status',                    'ru' => 'Статус'],
        'status_eingeladen' => ['de' => 'eingeladen',                'ru' => 'приглашён'],
        'status_aktiv'      => ['de' => 'aktiv',                     'ru' => 'активен'],
        'status_gesperrt'   => ['de' => 'gesperrt',                  'ru' => 'заблокирован'],
        'sperren'           => ['de' => 'Sperren',                   'ru' => 'Заблокировать'],
        'freigeben'         => ['de' => 'Freigeben',                 'ru' => 'Разблокировать'],
        'letzter_login'     => ['de' => 'Letzte Anmeldung',          'ru' => 'Последний вход'],
        'nie'               => ['de' => 'nie',                       'ru' => 'никогда'],
        'quelle_neu'        => ['de' => 'Quelle hinzufügen',         'ru' => 'Добавить источник'],
        'quelle_art'        => ['de' => 'Art',                       'ru' => 'Тип'],
        'quelle_rss'        => ['de' => 'Nachrichtenquelle (RSS/Atom)', 'ru' => 'Новостной источник (RSS/Atom)'],
        'quelle_youtube'    => ['de' => 'YouTube-Kanal',             'ru' => 'Канал YouTube'],
        'region'            => ['de' => 'Raum',                      'ru' => 'Регион'],
        'region_de'         => ['de' => 'Deutschsprachig',           'ru' => 'Немецкоязычный'],
        'region_ru'         => ['de' => 'Russischsprachig',          'ru' => 'Русскоязычный'],
        'region_int'        => ['de' => 'International',             'ru' => 'Международный'],
        'aktiv'             => ['de' => 'Aktiv',                     'ru' => 'Активен'],
        'inaktiv'           => ['de' => 'Inaktiv',                   'ru' => 'Неактивен'],
        'letzter_lauf'      => ['de' => 'Letzter Lauf',              'ru' => 'Последний запуск'],
        'treffer'           => ['de' => 'Treffer',                   'ru' => 'Найдено'],
        'jetzt_sammeln'     => ['de' => 'Jetzt sammeln',             'ru' => 'Собрать сейчас'],
        'sammeln_laeuft'    => ['de' => 'Die Sammlung läuft und kann eine Minute dauern.', 'ru' => 'Сбор запущен, это может занять минуту.'],
        'sammeln_fertig'    => ['de' => '%d neue Beiträge, %d neue Videos.', 'ru' => 'Новых материалов: %d, новых видео: %d.'],
        'matrix_import'     => ['de' => 'Matrix als CSV einspielen', 'ru' => 'Импорт матрицы (CSV)'],
        'matrix_import_hilfe' => [
            'de' => 'Erwartet wird eine Datei mit drei Spalten je Zeile: verbesserter Parameter (1–39), sich verschlechternder Parameter (1–39), empfohlene Prinzipien als Nummern mit Komma. Trennzeichen Komma oder Semikolon.',
            'ru' => 'Ожидается файл с тремя столбцами в строке: улучшаемый параметр (1–39), ухудшаемый параметр (1–39), рекомендуемые приёмы номерами через запятую. Разделитель — запятая или точка с запятой.',
        ],
        'stat_benutzer'     => ['de' => 'Zugänge',                   'ru' => 'Доступы'],
        'stat_beitraege'    => ['de' => 'Beiträge',                  'ru' => 'Материалы'],
        'stat_videos'       => ['de' => 'Videos',                    'ru' => 'Видео'],
        'stat_dokumente'    => ['de' => 'Dokumente',                 'ru' => 'Документы'],
        'stat_anmeldungen'  => ['de' => 'Anmeldungen (30 Tage)',     'ru' => 'Входы (30 дней)'],
        'stat_top_dokumente'=> ['de' => 'Meistgeladene Dokumente',   'ru' => 'Самые скачиваемые документы'],
        'stat_letzte_woche' => ['de' => 'Zuwachs der letzten 7 Tage','ru' => 'Прирост за 7 дней'],

        // --- Fuß und Recht ---------------------------------------------------
        'impressum'         => ['de' => 'Impressum',                 'ru' => 'Выходные данные'],
        'datenschutz'       => ['de' => 'Datenschutz',               'ru' => 'Защита данных'],
        'startseite'        => ['de' => 'Startseite',                'ru' => 'Главная'],
        'angemeldet_als'    => ['de' => 'Angemeldet als %s',         'ru' => 'Вы вошли как %s'],
    ];
}

/**
 * Übersetzt einen Schlüssel. Weitere Argumente werden wie bei sprintf
 * eingesetzt, damit Zahlen und Namen an der sprachrichtigen Stelle stehen.
 */
function t(string $schluessel, mixed ...$args): string
{
    static $texte = null;
    $texte ??= triz_texte();

    $eintrag = $texte[$schluessel] ?? null;
    if ($eintrag === null) {
        // Sichtbar, aber harmlos: So fällt ein vergessener Schlüssel beim
        // Testen auf, ohne dass die Seite bricht.
        return $schluessel;
    }

    $sprache = triz_sprache();
    $text = ($sprache === 'ru' && ($eintrag['ru'] ?? '') !== '') ? $eintrag['ru'] : $eintrag['de'];

    return $args === [] ? $text : vsprintf($text, $args);
}

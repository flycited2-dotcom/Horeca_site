<?php

return [

    'sources' => [
        'rosholod.catalog_xml' => 'Росхолод: каталог (XML)',
        'rosholod.stock_xml' => 'Росхолод: остатки (XML)',
    ],

    'errors' => [
        'unknown_source' => 'Неизвестный источник импорта «:source».',
        'no_url' => 'У профиля импорта не указан адрес выгрузки.',
        'download_failed' => 'Не удалось скачать выгрузку: :reason',
        'http_status' => 'Поставщик ответил кодом :status.',
        'empty_file' => 'Поставщик прислал пустой файл.',
        'too_large' => 'Файл выгрузки больше :limit МБ.',
        'cannot_open' => 'Не удалось открыть XML-файл выгрузки.',
        'malformed' => 'Файл выгрузки повреждён или обрезан: :message (строка :line).',
        'mojibake' => 'Неверная кодировка выгрузки: «:sample» вместо кириллицы.',
        'no_records' => 'В выгрузке нет ни одной записи.',
        'too_many_invalid' => 'Слишком много ошибочных записей: :invalid из :total, допустимо :percent%.',
        'too_few_records' => 'Подозрительно мало записей: :current против :previous в прошлый раз.',
        'already_running' => 'Импорт поставщика «:supplier» уже идёт.',
        'owned_fields_conflict' => 'Поля :fields уже заполняет активный профиль «:profile». Выключите его перед включением этого профиля.',
        'invalid_schedule' => 'Расписание должно быть cron-выражением, например «0 5-23 * * *».',
    ],

    'records' => [
        'missing_external_id' => 'Нет идентификатора ID.',
        'missing_name' => 'Нет наименования.',
        'invalid_price' => 'Не удалось разобрать цену «:price».',
        'negative_price' => 'Отрицательная цена «:price».',
        'duplicate_external_id' => 'ID повторяется в выгрузке, запись пропущена.',
        'unknown_stock_value' => 'Неизвестное значение остатка «:value» на складе «:warehouse»: считаем, что товара нет.',
        'ambiguous_category' => 'Несколько категорий с именем «:name» (:keys), выбрана первая.',
        'unknown_category' => 'Категории «:name» нет в дереве поставщика, товар оставлен без категории.',
        'category_deleted' => 'Витринная категория для «:name» удалена, товары остаются без категории.',
        'products_not_in_catalog' => 'Позиций из файла остатков нет в каталоге: :count, они пропущены.',
    ],

    'messages' => [
        'not_modified' => 'Источник не изменился (HTTP 304).',
        'same_file' => 'Содержимое файла не изменилось с прошлого успешного импорта.',
        'already_running' => 'Импорт этого поставщика уже идёт, прогон поставлен в очередь повторно.',
    ],

    'telegram' => [
        'failed' => "Импорт «:profile» не выполнен.\n:error\nПрогон №:run.",
        'digest_title' => 'Итоги импорта за :date',
        'digest_line' => '«:profile»: прогонов :runs (успешно :success, без изменений :skipped, с ошибкой :failed); создано :created, обновлено :updated, снято с производства :discontinued, ошибочных строк :errors.',
        'digest_empty' => 'Импорт сегодня не запускался.',
    ],

    'mail' => [
        'failed_subject' => 'Импорт «:profile» не выполнен',
        'failed_greeting' => 'Импорт не выполнен',
        'failed_intro' => 'Прогон №:run профиля «:profile» завершился ошибкой. Каталог не изменён.',
        'failed_reason' => 'Причина: :error',
        'failed_action' => 'Открыть прогон',
    ],

    'command' => [
        'profile_not_found' => 'Профиль импорта №:id не найден.',
        'finished' => 'Прогон №:run: :status.',
        'dispatched' => 'Поставлен в очередь импорт «:profile».',
        'still_running' => 'Импорт «:profile» ещё не завершён, новый прогон не запускается.',
        'digest_sent' => 'Итоги импорта отправлены.',
    ],

];

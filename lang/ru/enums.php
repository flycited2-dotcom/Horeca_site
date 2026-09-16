<?php

return [

    'user_role' => [
        'customer' => 'Клиент',
        'manager' => 'Менеджер',
        'admin' => 'Администратор',
    ],

    'company_status' => [
        'pending' => 'На проверке',
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена',
        'blocked' => 'Заблокирована',
    ],

    'company_segment' => [
        'restaurant' => 'Ресторан',
        'cafe' => 'Кафе',
        'bar' => 'Бар',
        'hotel' => 'Отель',
        'canteen' => 'Столовая',
        'bakery' => 'Кондитерская',
        'production' => 'Производство',
        'retail_chain' => 'Сеть',
        'other' => 'Другое',
    ],

    'price_kind' => [
        'rrp' => 'РРЦ',
        'dealer' => 'Дилерская цена',
    ],

    'supplier_ref_entity' => [
        'category' => 'Категория',
        'brand' => 'Бренд',
        'warehouse' => 'Склад',
        'attribute' => 'Характеристика',
    ],

    'availability' => [
        'in_stock' => 'В наличии',
        'low' => 'В наличии: мало',
        'incoming' => 'Ожидается',
        'on_order' => 'Под заказ',
        'discontinued' => 'Снят с производства',
    ],

    'warehouse_stock_status' => [
        'in_stock' => 'В наличии',
        'low' => 'Мало',
        'out' => 'Нет',
    ],

    'attribute_type' => [
        'string' => 'Текст',
        'number' => 'Число',
        'bool' => 'Да или нет',
    ],

    'attribute_value_source' => [
        'supplier' => 'Поставщик',
        'manual' => 'Вручную',
    ],

    'import_run_status' => [
        'queued' => 'В очереди',
        'running' => 'Выполняется',
        'success' => 'Успешно',
        'skipped' => 'Без изменений',
        'failed' => 'Ошибка',
    ],

    'import_trigger' => [
        'schedule' => 'По расписанию',
        'manual' => 'Вручную',
        'cli' => 'Из консоли',
    ],

    'import_entity' => [
        'category' => 'Категория',
        'product' => 'Товар',
        'stock' => 'Остаток',
    ],

    'order_type' => [
        'retail' => 'Розница',
        'wholesale' => 'Опт',
    ],

    'order_status' => [
        'new' => 'Новая',
        'processing' => 'В работе',
        'confirmed' => 'Подтверждена',
        'invoiced' => 'Выставлен счёт',
        'paid' => 'Оплачена',
        'shipped' => 'Отгружена',
        'completed' => 'Выполнена',
        'canceled' => 'Отменена',
    ],

    'delivery_method' => [
        'pickup' => 'Самовывоз',
        'transport_company' => 'Транспортная компания',
        'courier_city' => 'Курьер по городу',
    ],

    'payment_method' => [
        'invoice' => 'Счёт на оплату',
        'cash' => 'Наличными при получении',
        'card_on_delivery' => 'Картой при получении',
        'online' => 'Онлайн',
    ],

    'lead_type' => [
        'callback' => 'Перезвонить',
        'question' => 'Вопрос',
        'price_request' => 'Запрос цены',
        'availability_request' => 'Запрос срока поставки',
        'analog_request' => 'Подбор аналога',
        'one_click' => 'Купить в 1 клик',
        'not_found' => 'Не нашли товар',
    ],

    'lead_status' => [
        'new' => 'Новый',
        'in_work' => 'В работе',
        'done' => 'Обработан',
    ],

];

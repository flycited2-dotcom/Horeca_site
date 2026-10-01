<?php

/*
 * Уведомления о заявках (ТЗ §13): Telegram менеджеров и письма.
 */

return [

    'order' => [
        'telegram_title' => 'Новая заявка :number · :type',
        'telegram_time' => 'Время: :time',
        'telegram_total' => 'Сумма: :total, :positions',
        'telegram_more' => '…и ещё :count позиция|…и ещё :count позиции|…и ещё :count позиций',
        'telegram_delivery' => 'Получение: :delivery',
        'telegram_payment' => 'Оплата: :payment',
        'telegram_contacts' => 'Клиент: :name, :phone',
        'telegram_email' => 'Почта: :email',
        'telegram_company' => 'Организация: :company, ИНН :inn',
        'telegram_address' => 'Адрес доставки: :address',
        'telegram_comment' => 'Комментарий: :comment',
        'telegram_source' => 'Источник: :source',

        'manager_subject' => 'Новая заявка :number · :total',
        'manager_heading' => 'Новая заявка :number',
        'open' => 'Открыть заявку в админке',

        'customer_subject' => 'Заявка :number принята',
        'customer_heading' => 'Заявка :number принята',
        'customer_intro' => ':name, спасибо! Вот что вы заказали.',
        'questions' => 'Вопросы по заявке:',

        'status_subject' => 'Заявка :number: :status',
        'status_heading' => 'Заявка :number — :status',
        'status_text' => [
            'confirmed' => 'Мы подтвердили заявку: наличие и сроки согласованы.',
            'invoiced' => 'Счёт по заявке выставлен и отправлен вам.',
            'paid' => 'Оплата получена — готовим заказ к отгрузке.',
            'shipped' => 'Заказ отгружен.',
            'completed' => 'Заявка выполнена. Спасибо, что выбрали нас!',
            'canceled' => 'Заявка отменена.',
        ],

        'type' => 'Тип',
        'customer' => 'Клиент',
        'phone' => 'Телефон',
        'email' => 'Почта',
        'company' => 'Организация',
        'inn' => 'ИНН',
        'delivery' => 'Получение',
        'payment' => 'Оплата',
        'comment' => 'Комментарий',
        'item' => 'Наименование',
        'quantity' => 'Кол-во',
        'sum' => 'Сумма',
        'total' => 'Итого',
    ],

    'lead' => [
        'title' => 'Лид: :type',
        'time' => 'Время: :time',
        'product' => 'Товар: :name',
        'sku' => 'Артикул: :sku',
        'price' => 'Цена: :price',
        'on_request' => 'по запросу',
        'link' => 'Страница: :url',
        'message' => 'Сообщение: :message',
        'name' => 'Имя: :name',
        'phone' => 'Телефон: :phone',
        'email' => 'Почта: :email',
        'source' => 'Источник: :source',
    ],

    'wholesale' => [
        'telegram_title' => 'Заявка на опт: :company',
        'telegram_time' => 'Время: :time',
        'telegram_details' => 'ИНН :inn · :segment:city',
        'telegram_contacts' => 'Контакт: :name, :phone',
        'telegram_email' => 'Почта: :email',
        'managers_subject' => 'Заявка на опт: :company',
        'managers_heading' => 'Заявка на опт',
        'managers_intro' => 'Проверьте реквизиты, назначьте ценовую группу и одобрите компанию в админке — клиенту уйдёт письмо «Оптовые цены открыты».',
        'open' => 'Открыть заявку',
        'recheck_title' => 'Повторная проверка: :company',
        'recheck_heading' => 'Компания сменила реквизиты',
        'recheck_intro' => 'Клиент изменил в кабинете ИНН или название организации. Компания вернулась на проверку, до одобрения цены у клиента розничные.',
        'received_subject' => 'Заявка на опт принята',
        'received_heading' => 'Заявка на опт принята',
        'received_intro' => 'Здравствуйте, :name! Заявку от «:company» получили. Менеджер проверит реквизиты в рабочее время и откроет оптовые цены — об этом придёт отдельное письмо.',
        'received_until' => 'До проверки на сайте работают каталог, корзина и заявки по розничным ценам.',
        'approved_subject' => 'Оптовые цены открыты',
        'approved_heading' => 'Оптовые цены открыты',
        'approved_intro' => 'Компания «:company» проверена. Войдите на сайт под своей почтой — в каталоге и корзине будут ваши цены.',
        'approved_button' => 'Перейти в каталог',
    ],

    'password' => [
        'subject' => 'Новый пароль для кабинета',
        'heading' => 'Новый пароль для кабинета',
        'intro' => 'Здравствуйте, :name! Для кабинета на сайте попросили сменить пароль. Задать новый можно по кнопке ниже.',
        'button' => 'Задать новый пароль',
        'expires' => 'Ссылка действует :minutes минут и работает один раз.',
        'ignore' => 'Если вы не просили сменить пароль, ничего не делайте — прежний пароль продолжит работать.',
    ],

];

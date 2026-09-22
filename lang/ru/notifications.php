<?php

/*
 * Уведомления о заявках (ТЗ §13): Telegram менеджеров и письма.
 */

return [

    'order' => [
        'telegram_title' => 'Новая заявка :number · :type',
        'telegram_total' => 'Сумма: :total, :positions',
        'telegram_more' => '…и ещё :count позиция|…и ещё :count позиции|…и ещё :count позиций',
        'telegram_delivery' => 'Получение: :delivery',
        'telegram_contacts' => 'Клиент: :name, :phone',

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
        'product' => 'Товар: :name:sku',
        'message' => 'Сообщение: :message',
    ],

];

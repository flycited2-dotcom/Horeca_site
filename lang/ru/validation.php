<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Russian messages for the Laravel validator. Keys mirror the framework's
    | lang/en/validation.php of Laravel 13.
    |
    */

    'accepted' => 'Необходимо принять «:attribute».',
    'accepted_if' => 'Необходимо принять «:attribute», когда «:other» равно «:value».',
    'active_url' => 'Поле «:attribute» должно содержать действующий URL.',
    'after' => 'Поле «:attribute» должно содержать дату после :date.',
    'after_or_equal' => 'Поле «:attribute» должно содержать дату не раньше :date.',
    'alpha' => 'Поле «:attribute» может содержать только буквы.',
    'alpha_dash' => 'Поле «:attribute» может содержать только буквы, цифры, дефис и подчёркивание.',
    'alpha_num' => 'Поле «:attribute» может содержать только буквы и цифры.',
    'any_of' => 'Поле «:attribute» заполнено неверно.',
    'array' => 'Поле «:attribute» должно быть списком.',
    'array_keys' => 'Поле «:attribute» может содержать только ключи: :values.',
    'ascii' => 'Поле «:attribute» может содержать только латинские буквы, цифры и символы.',
    'base64' => 'Поле «:attribute» должно быть строкой в кодировке Base64.',
    'before' => 'Поле «:attribute» должно содержать дату до :date.',
    'before_or_equal' => 'Поле «:attribute» должно содержать дату не позже :date.',
    'between' => [
        'array' => 'Поле «:attribute» должно содержать от :min до :max элементов.',
        'file' => 'Размер файла «:attribute» должен быть от :min до :max КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть от :min до :max.',
        'string' => 'Поле «:attribute» должно содержать от :min до :max символов.',
    ],
    'boolean' => 'Поле «:attribute» должно быть «да» или «нет».',
    'can' => 'Поле «:attribute» содержит недопустимое значение.',
    'confirmed' => 'Значение поля «:attribute» не совпадает с подтверждением.',
    'contains' => 'В поле «:attribute» не хватает обязательного значения.',
    'current_password' => 'Неверный пароль.',
    'date' => 'Поле «:attribute» должно содержать корректную дату.',
    'date_equals' => 'Поле «:attribute» должно содержать дату :date.',
    'date_format' => 'Поле «:attribute» должно соответствовать формату :format.',
    'decimal' => 'Поле «:attribute» должно содержать :decimal знаков после запятой.',
    'declined' => 'Необходимо отклонить «:attribute».',
    'declined_if' => 'Необходимо отклонить «:attribute», когда «:other» равно «:value».',
    'different' => 'Поля «:attribute» и «:other» должны различаться.',
    'digits' => 'Поле «:attribute» должно содержать :digits цифр.',
    'digits_between' => 'Поле «:attribute» должно содержать от :min до :max цифр.',
    'dimensions' => 'Изображение «:attribute» имеет недопустимые размеры.',
    'distinct' => 'Поле «:attribute» содержит повторяющееся значение.',
    'doesnt_contain' => 'Поле «:attribute» не должно содержать: :values.',
    'doesnt_end_with' => 'Поле «:attribute» не должно заканчиваться на: :values.',
    'doesnt_start_with' => 'Поле «:attribute» не должно начинаться с: :values.',
    'email' => 'Поле «:attribute» должно содержать корректный адрес электронной почты, например name@mail.ru.',
    'encoding' => 'Поле «:attribute» должно быть в кодировке :encoding.',
    'ends_with' => 'Поле «:attribute» должно заканчиваться на одно из значений: :values.',
    'enum' => 'Выбрано недопустимое значение поля «:attribute».',
    'exists' => 'Выбрано недопустимое значение поля «:attribute».',
    'extensions' => 'Файл «:attribute» должен иметь одно из расширений: :values.',
    'file' => 'В поле «:attribute» нужно загрузить файл.',
    'filled' => 'Поле «:attribute» не может быть пустым.',
    'gt' => [
        'array' => 'Поле «:attribute» должно содержать больше :value элементов.',
        'file' => 'Размер файла «:attribute» должен быть больше :value КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть больше :value.',
        'string' => 'Поле «:attribute» должно содержать больше :value символов.',
    ],
    'gte' => [
        'array' => 'Поле «:attribute» должно содержать не меньше :value элементов.',
        'file' => 'Размер файла «:attribute» должен быть не меньше :value КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть не меньше :value.',
        'string' => 'Поле «:attribute» должно содержать не меньше :value символов.',
    ],
    'hex_color' => 'Поле «:attribute» должно содержать цвет в шестнадцатеричном формате.',
    'image' => 'Файл «:attribute» должен быть изображением.',
    'in' => 'Выбрано недопустимое значение поля «:attribute».',
    'in_array' => 'Значение поля «:attribute» должно присутствовать в «:other».',
    'in_array_keys' => 'Поле «:attribute» должно содержать хотя бы один из ключей: :values.',
    'integer' => 'Поле «:attribute» должно быть целым числом.',
    'ip' => 'Поле «:attribute» должно содержать корректный IP-адрес.',
    'ipv4' => 'Поле «:attribute» должно содержать корректный IPv4-адрес.',
    'ipv6' => 'Поле «:attribute» должно содержать корректный IPv6-адрес.',
    'json' => 'Поле «:attribute» должно содержать корректную JSON-строку.',
    'list' => 'Поле «:attribute» должно быть списком.',
    'lowercase' => 'Поле «:attribute» должно быть в нижнем регистре.',
    'lt' => [
        'array' => 'Поле «:attribute» должно содержать меньше :value элементов.',
        'file' => 'Размер файла «:attribute» должен быть меньше :value КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть меньше :value.',
        'string' => 'Поле «:attribute» должно содержать меньше :value символов.',
    ],
    'lte' => [
        'array' => 'Поле «:attribute» должно содержать не больше :value элементов.',
        'file' => 'Размер файла «:attribute» должен быть не больше :value КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть не больше :value.',
        'string' => 'Поле «:attribute» должно содержать не больше :value символов.',
    ],
    'mac_address' => 'Поле «:attribute» должно содержать корректный MAC-адрес.',
    'max' => [
        'array' => 'Поле «:attribute» может содержать не больше :max элементов.',
        'file' => 'Размер файла «:attribute» не может быть больше :max КБ.',
        'numeric' => 'Значение поля «:attribute» не может быть больше :max.',
        'string' => 'Поле «:attribute» может содержать не больше :max символов.',
    ],
    'max_digits' => 'Поле «:attribute» может содержать не больше :max цифр.',
    'mimes' => 'Файл «:attribute» должен быть одного из типов: :values.',
    'mimetypes' => 'Файл «:attribute» должен быть одного из типов: :values.',
    'min' => [
        'array' => 'Поле «:attribute» должно содержать не меньше :min элементов.',
        'file' => 'Размер файла «:attribute» должен быть не меньше :min КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть не меньше :min.',
        'string' => 'Поле «:attribute» должно содержать не меньше :min символов.',
    ],
    'min_digits' => 'Поле «:attribute» должно содержать не меньше :min цифр.',
    'missing' => 'Поле «:attribute» должно отсутствовать.',
    'missing_if' => 'Поле «:attribute» должно отсутствовать, когда «:other» равно «:value».',
    'missing_unless' => 'Поле «:attribute» должно отсутствовать, если «:other» не равно «:value».',
    'missing_with' => 'Поле «:attribute» должно отсутствовать, если заполнено «:values».',
    'missing_with_all' => 'Поле «:attribute» должно отсутствовать, если заполнены «:values».',
    'multiple_of' => 'Значение поля «:attribute» должно быть кратно :value.',
    'not_in' => 'Выбрано недопустимое значение поля «:attribute».',
    'not_regex' => 'Поле «:attribute» заполнено в неверном формате.',
    'numeric' => 'Поле «:attribute» должно быть числом.',
    'password' => [
        'letters' => 'Поле «:attribute» должно содержать хотя бы одну букву.',
        'mixed' => 'Поле «:attribute» должно содержать хотя бы одну заглавную и одну строчную букву.',
        'numbers' => 'Поле «:attribute» должно содержать хотя бы одну цифру.',
        'symbols' => 'Поле «:attribute» должно содержать хотя бы один специальный символ.',
        'uncompromised' => 'Такой «:attribute» встречался в утечках данных. Придумайте другой.',
    ],
    'present' => 'Поле «:attribute» должно присутствовать.',
    'present_if' => 'Поле «:attribute» должно присутствовать, когда «:other» равно «:value».',
    'present_unless' => 'Поле «:attribute» должно присутствовать, если «:other» не равно «:value».',
    'present_with' => 'Поле «:attribute» должно присутствовать, если заполнено «:values».',
    'present_with_all' => 'Поле «:attribute» должно присутствовать, если заполнены «:values».',
    'prohibited' => 'Поле «:attribute» заполнять нельзя.',
    'prohibited_if' => 'Поле «:attribute» заполнять нельзя, когда «:other» равно «:value».',
    'prohibited_if_accepted' => 'Поле «:attribute» заполнять нельзя, когда принято «:other».',
    'prohibited_if_declined' => 'Поле «:attribute» заполнять нельзя, когда отклонено «:other».',
    'prohibited_unless' => 'Поле «:attribute» заполнять нельзя, если «:other» не входит в :values.',
    'prohibits' => 'Если заполнено поле «:attribute», поле «:other» заполнять нельзя.',
    'regex' => 'Поле «:attribute» заполнено в неверном формате.',
    'required' => 'Заполните поле «:attribute».',
    'required_array_keys' => 'Поле «:attribute» должно содержать значения для: :values.',
    'required_if' => 'Заполните поле «:attribute», когда «:other» равно «:value».',
    'required_if_accepted' => 'Заполните поле «:attribute», когда принято «:other».',
    'required_if_declined' => 'Заполните поле «:attribute», когда отклонено «:other».',
    'required_unless' => 'Заполните поле «:attribute», если «:other» не входит в :values.',
    'required_with' => 'Заполните поле «:attribute», когда заполнено «:values».',
    'required_with_all' => 'Заполните поле «:attribute», когда заполнены «:values».',
    'required_without' => 'Заполните поле «:attribute», когда не заполнено «:values».',
    'required_without_all' => 'Заполните поле «:attribute», когда не заполнено ни одно из «:values».',
    'same' => 'Поля «:attribute» и «:other» должны совпадать.',
    'size' => [
        'array' => 'Поле «:attribute» должно содержать :size элементов.',
        'file' => 'Размер файла «:attribute» должен быть :size КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть :size.',
        'string' => 'Поле «:attribute» должно содержать :size символов.',
    ],
    'starts_with' => 'Поле «:attribute» должно начинаться с одного из значений: :values.',
    'string' => 'Поле «:attribute» должно быть строкой.',
    'timezone' => 'Поле «:attribute» должно содержать корректный часовой пояс.',
    'unique' => 'Такое значение поля «:attribute» уже используется.',
    'uploaded' => 'Не удалось загрузить файл «:attribute».',
    'uppercase' => 'Поле «:attribute» должно быть в верхнем регистре.',
    'url' => 'Поле «:attribute» должно содержать корректный URL.',
    'ulid' => 'Поле «:attribute» должно содержать корректный ULID.',
    'uuid' => 'Поле «:attribute» должно содержать корректный UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Human-readable field names used in the messages above.
    |
    */

    'attributes' => [
        'name' => 'имя',
        'email' => 'e-mail',
        'phone' => 'телефон',
        'password' => 'пароль',
        'password_confirmation' => 'подтверждение пароля',
        'inn' => 'ИНН',
        'kpp' => 'КПП',
        'ogrn' => 'ОГРН',
        'legal_name' => 'юридическое название',
        'company_name' => 'название организации',
        'contact_person' => 'контактное лицо',
        'segment' => 'тип заведения',
        'city' => 'город',
        'delivery_city' => 'город доставки',
        'delivery_address' => 'адрес доставки',
        'comment' => 'комментарий',
        'message' => 'сообщение',
        'qty' => 'количество',
        'consent' => 'согласие на обработку персональных данных',
    ],

];

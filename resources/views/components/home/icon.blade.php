@props(['name'])

{{--
    Пиктограмма главной в облике «Свечение»: стрелка круглой кнопки, корзина, лупа. Рисует
    App\View\HomeIcon; класс из атрибутов дописывается к gl-ic (штрих 2 px, цвет текста).
--}}
{{ \App\View\HomeIcon::svg($name, (string) $attributes->get('class', '')) }}

{{--
    Живая сцена облика «Свечение» (ТЗ §9): холодная и горячая ауры, точки, искры и зерно лежат за
    всем содержимым страницы. Чистый CSS без скриптов и картинок; слои не принимают нажатий и скрыты
    от вспомогательных технологий, движение отключает prefers-reduced-motion. Стили — в glow.css и
    glow-shell.css. Сцену рисуют и каркас витрины, и страница ошибки.
--}}
<div class="gl-stage__bg" aria-hidden="true">
    <div class="gl-aura gl-aura--gl-c"></div>
    <div class="gl-aura gl-aura--gl-h"></div>
    <div class="gl-aura gl-aura--gl-e"></div>
    <div class="gl-aura gl-aura--gl-m"></div>
    <div class="gl-dots"></div>
    <div class="gl-motes gl-motes--gl-c"></div>
    <div class="gl-motes gl-motes--gl-h"></div>
    <div class="gl-motes gl-motes--gl-m"></div>
    <div class="gl-motes gl-motes--gl-lo"></div>
    <div class="gl-grain"></div>
</div>

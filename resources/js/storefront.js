/*
 * Улучшения витрины поверх вёрстки, которая работает без скриптов (ТЗ §9: каталог
 * читается без JS). Здесь только то, чего не умеет HTML.
 */

// Выпадающие панели на <details data-dismissable>: меню каталога и «Ещё». Закрываются
// по Esc и по нажатию мимо панели.
const openPanels = () => document.querySelectorAll('details[data-dismissable][open]');

document.addEventListener('click', (event) => {
    for (const panel of openPanels()) {
        if (!panel.contains(event.target)) {
            panel.open = false;
        }
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    for (const panel of openPanels()) {
        panel.open = false;
        panel.querySelector('summary')?.focus();
    }
});

// Ряд категорий (макет, экран 5): разделы, которые не поместились в строку, уходят на
// скрытую вторую строку; в «Ещё» остаются только они. Без скрипта «Ещё» показывает все.
for (const nav of document.querySelectorAll('[data-priority-nav]')) {
    const items = [...nav.querySelectorAll('[data-priority-item]')];
    const extras = [...nav.querySelectorAll('[data-priority-extra]')];
    const more = nav.querySelector('[data-priority-more]');

    if (items.length === 0 || more === null) {
        continue;
    }

    const update = () => {
        // «Ещё» занимает место в строке, поэтому считаем при показанном «Ещё».
        more.hidden = false;

        const firstRow = items[0].offsetTop;
        const overflow = items.map((item) => item.offsetTop > firstRow);

        extras.forEach((extra, index) => {
            extra.hidden = !overflow[index];
        });

        more.hidden = !overflow.includes(true);
    };

    new ResizeObserver(update).observe(nav);
}

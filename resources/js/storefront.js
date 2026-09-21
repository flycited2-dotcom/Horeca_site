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

// Вкладки карточки товара (ТЗ §8.3): с 768 px — вкладки, ниже — аккордеоны. Неактивный
// раздел помечен data-inactive, стили прячут его.
for (const tabs of document.querySelectorAll('[data-tabs]')) {
    const panels = [...tabs.querySelectorAll('[data-panel]')];
    const buttons = [...tabs.querySelectorAll('[data-tab-button]')];

    const show = (key) => {
        for (const panel of panels) {
            const active = panel.dataset.panel === key;
            panel.toggleAttribute('data-inactive', !active);
            panel.querySelector('[data-panel-toggle]')?.setAttribute('aria-expanded', String(active));
        }

        for (const button of buttons) {
            button.setAttribute('aria-selected', String(button.dataset.tabButton === key));
        }
    };

    for (const button of buttons) {
        button.addEventListener('click', () => show(button.dataset.tabButton));
        button.addEventListener('keydown', (event) => {
            const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];

            if (step === undefined) {
                return;
            }

            const next = buttons[(buttons.indexOf(button) + step + buttons.length) % buttons.length];
            next.focus();
            show(next.dataset.tabButton);
        });
    }

    for (const panel of panels) {
        panel.querySelector('[data-panel-toggle]')?.addEventListener('click', (event) => {
            const opening = panel.hasAttribute('data-inactive');
            panel.toggleAttribute('data-inactive', !opening);
            event.currentTarget.setAttribute('aria-expanded', String(opening));
        });
    }
}

// Галерея: миниатюра меняет главное фото на месте; без скрипта открывает фото целиком.
for (const gallery of document.querySelectorAll('[data-gallery]')) {
    const main = gallery.querySelector('[data-gallery-main]');
    const image = main?.querySelector('img');
    const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];

    for (const thumb of thumbs) {
        thumb.addEventListener('click', (event) => {
            event.preventDefault();
            image.src = thumb.dataset.full;
            main.href = thumb.href;
            thumbs.forEach((other) => other.toggleAttribute('aria-current', other === thumb));
            thumb.setAttribute('aria-current', 'true');
        });
    }
}

// Уведомления об итоге действия (шаблон — в каркасе): новое заменяет прежнее, закрывается
// крестиком или само через несколько секунд.
const notices = document.querySelector('[data-notices]');
const noticeTemplate = document.querySelector('template[data-notice-template]');
let noticeTimer;

const showNotice = ({ text, href, link }) => {
    if (!notices || !noticeTemplate) {
        return;
    }

    const notice = noticeTemplate.content.firstElementChild.cloneNode(true);
    notice.querySelector('[data-notice-text]').textContent = text;

    const anchor = notice.querySelector('[data-notice-link]');
    anchor.hidden = !href;

    if (href) {
        anchor.href = href;
        anchor.textContent = link;
    }

    notices.replaceChildren(notice);
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => notice.remove(), 6000);
};

document.addEventListener('click', (event) => {
    event.target.closest('[data-notice-close]')?.closest('[data-notice-item]')?.remove();
});

// Сравнение (ТЗ §8.5): формы «Сравнить» работают и без скриптов; со скриптами кнопка
// меняется на месте — на всех карточках этого товара, — счётчик в шапке обновляется.
// Обработчик на документе: Livewire перерисовывает листинг, формы появляются заново.
document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-compare-form')) {
        return;
    }

    event.preventDefault();

    let result;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        result = await response.json();
    } catch {
        form.submit();

        return;
    }

    const holder = form.closest('[data-compare]');

    for (const toggle of document.querySelectorAll(`[data-compare="${result.product}"]`)) {
        const [add, remove] = toggle.querySelectorAll('form[data-compare-form]');
        add.hidden = result.compared;
        remove.hidden = !result.compared;
    }

    // Нажатая кнопка спряталась: фокус переходит на ту, что встала на её место.
    holder?.querySelector('form[data-compare-form]:not([hidden]) button')?.focus();

    const link = document.querySelector('[data-compare-link]');

    if (link) {
        link.hidden = result.count === 0;
        link.querySelector('[data-compare-count]').textContent = String(result.count);
    }

    showNotice(result.notice);
});

// Липкая полоса покупки ниже 1024 px: появляется, когда панель покупки ушла вверх за экран.
const buyPanel = document.querySelector('[data-buy-panel]');
const stickyBuy = document.querySelector('[data-sticky-buy]');

if (buyPanel && stickyBuy) {
    new IntersectionObserver(([entry]) => {
        stickyBuy.hidden = entry.isIntersecting || entry.boundingClientRect.top > 0;
    }).observe(buyPanel);
}

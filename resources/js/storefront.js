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

// Кнопка «Назад» закрывает открытое окно — просмотр фото, шторку подбора, форму запроса,
// меню, — а не уводит со страницы. Открытое окно кладёт в историю свою запись, закрытие
// крестиком или нажатием мимо снимает её. Запись, на которую вернулись, когда окна уже нет
// (после перехода по ссылке из окна или шага «Вперёд»), пропускается одним нажатием.
const pushOverlay = (key) => history.pushState({ overlay: key }, '');

// Окно, закрытое самой кнопкой «Назад», запись в истории уже сняло: событие закрытия
// приходит позже и второй раз назад идти не должно.
let closedByHistory = false;

const dropOverlay = (key) => {
    if (!closedByHistory && history.state?.overlay === key) {
        history.back();
    }
};

const overlayIsOpen = (key) => {
    if (key === 'gallery') {
        return document.querySelector('dialog[data-gallery-viewer][open]') !== null;
    }

    if (key === 'menu') {
        return document.querySelector('details[data-history-overlay][open]') !== null;
    }

    return document.getElementById(key)?.matches(':popover-open') ?? false;
};

const skipStaleOverlay = () => {
    const key = history.state?.overlay;

    if (key && !overlayIsOpen(key)) {
        history.back();
    }
};

for (const popover of document.querySelectorAll('[popover]')) {
    popover.addEventListener('toggle', (event) => {
        if (event.newState === 'open') {
            pushOverlay(popover.id);
        } else {
            dropOverlay(popover.id);
        }
    });
}

for (const menu of document.querySelectorAll('details[data-history-overlay]')) {
    menu.addEventListener('toggle', () => {
        if (menu.open) {
            pushOverlay('menu');
        } else {
            dropOverlay('menu');
        }
    });
}

window.addEventListener('popstate', () => {
    closedByHistory = true;
    setTimeout(() => {
        closedByHistory = false;
    }, 150);

    for (const popover of document.querySelectorAll('[popover]:popover-open')) {
        popover.hidePopover();
    }

    for (const menu of document.querySelectorAll('details[data-history-overlay][open]')) {
        menu.open = false;
    }

    for (const viewer of document.querySelectorAll('dialog[data-gallery-viewer][open]')) {
        viewer.close();
    }

    skipStaleOverlay();
});

window.addEventListener('pageshow', skipStaleOverlay);

// Ряд категорий (макет, экран 5): главные разделы — не больше двух строк плашек; те, что не
// поместились, уходят в «Ещё» к остальным разделам. Без скрипта «Ещё» показывает все.
const NAV_ROWS = 2;

for (const nav of document.querySelectorAll('[data-priority-nav]')) {
    const items = [...nav.querySelectorAll('[data-priority-item]')];
    const extras = [...nav.querySelectorAll('[data-priority-extra]')];
    const more = nav.querySelector('[data-priority-more]');

    if (items.length === 0 || more === null) {
        continue;
    }

    // Есть разделы, которых в ряду нет совсем: «Ещё» нужно всегда.
    const always = more.hasAttribute('data-priority-always');

    const update = () => {
        // «Ещё» занимает место в ряду, поэтому считаем при показанном «Ещё».
        more.hidden = false;

        const firstRow = items[0].offsetTop;
        const rowHeight = items[0].offsetHeight;
        const overflow = items.map((item) => item.offsetTop - firstRow >= rowHeight * NAV_ROWS);

        extras.forEach((extra, index) => {
            extra.hidden = !overflow[index];
        });

        more.hidden = !always && !overflow.includes(true);
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

// Галерея карточки товара: главное фото листается свайпом (прокрутка с привязкой — HTML),
// скрипт ведёт счётчик и миниатюры, а нажатие открывает фото на весь экран. Просмотр —
// <dialog>: фото вписано в экран, листается так же, закрывается крестиком, Esc и «Назад».
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const slideTo = (track, index, smooth) => {
    track.scrollTo({ left: index * track.clientWidth, behavior: smooth && !reducedMotion.matches ? 'smooth' : 'auto' });
};

const currentSlide = (track) => Math.round(track.scrollLeft / track.clientWidth);

const onSlide = (track, callback) => {
    track.addEventListener('scroll', () => {
        // Скрытое окно просмотра ширины не имеет: считать номер по нему нельзя.
        if (track.clientWidth > 0) {
            callback(currentSlide(track));
        }
    }, { passive: true });
};

const fillCounter = (counter, index) => {
    if (counter) {
        counter.textContent = counter.dataset.format.replace('{current}', index + 1).replace('{total}', counter.dataset.total);
    }
};

for (const gallery of document.querySelectorAll('[data-gallery]')) {
    const track = gallery.querySelector('[data-gallery-track]');
    const viewer = gallery.querySelector('[data-gallery-viewer]');
    const viewerTrack = viewer.querySelector('[data-viewer-track]');
    const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];
    const counter = gallery.querySelector('[data-gallery-counter]');
    const viewerCounter = viewer.querySelector('[data-viewer-counter]');

    const showCurrent = (index) => {
        thumbs.forEach((thumb, position) => {
            if (position === index) {
                thumb.setAttribute('aria-current', 'true');
            } else {
                thumb.removeAttribute('aria-current');
            }
        });

        fillCounter(counter, index);
    };

    // Номер фото в просмотре помним сами: после закрытия окна его ширина — ноль.
    let viewerIndex = 0;

    onSlide(track, showCurrent);
    onSlide(viewerTrack, (index) => {
        viewerIndex = index;
        fillCounter(viewerCounter, index);
    });

    thumbs.forEach((thumb, index) => {
        thumb.addEventListener('click', (event) => {
            event.preventDefault();
            slideTo(track, index, true);
        });
    });

    const requestClose = () => {
        if (history.state?.overlay === 'gallery') {
            history.back();
        } else if (viewer.open) {
            viewer.close();
        }
    };

    for (const open of gallery.querySelectorAll('[data-gallery-open]')) {
        open.addEventListener('click', (event) => {
            event.preventDefault();

            viewerIndex = Number(open.dataset.galleryOpen);

            viewer.showModal();
            document.documentElement.classList.add('overflow-hidden');
            slideTo(viewerTrack, viewerIndex, false);
            fillCounter(viewerCounter, viewerIndex);
            pushOverlay('gallery');
        });
    }

    // Закрытие любым путём возвращает главное фото к тому, на котором остановился просмотр.
    viewer.addEventListener('close', () => {
        document.documentElement.classList.remove('overflow-hidden');
        slideTo(track, viewerIndex, false);
        showCurrent(viewerIndex);
    });

    viewer.addEventListener('cancel', (event) => {
        event.preventDefault();
        requestClose();
    });

    viewer.querySelector('[data-viewer-close]').addEventListener('click', requestClose);

    for (const step of viewer.querySelectorAll('[data-viewer-step]')) {
        step.addEventListener('click', () => {
            viewerTrack.scrollBy({ left: Number(step.dataset.viewerStep) * viewerTrack.clientWidth, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
        });
    }

    viewer.addEventListener('keydown', (event) => {
        const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];

        if (step !== undefined) {
            event.preventDefault();
            viewerTrack.scrollBy({ left: step * viewerTrack.clientWidth, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
        }
    });
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

// Формы, которые скрипт отправляет без перезагрузки: «Сравнить», «В корзину» и короткие
// заявки (ТЗ §8.5, §10.1, §5: leads). Без скриптов это обычные формы. Ответ — JSON;
// 422 — отказ с объяснением («цена по запросу») или ошибки полей; при любом другом сбое
// форма уходит обычным способом. Обработчик на документе: Livewire перерисовывает листинг, формы
// появляются заново.
const sendForm = async (form, submitter = null) => {
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            // Нажатая кнопка с именем (cookie-баннер: «Принять все» или «Только необходимые») — часть формы.
            body: new FormData(form, submitter),
            headers: { Accept: 'application/json' },
        });

        if (response.ok || response.status === 422) {
            return { ok: response.ok, result: await response.json() };
        }
    } catch {
        // Сеть или сервер не ответили — ниже форма уйдёт обычным способом.
    }

    if (submitter?.name) {
        const choice = document.createElement('input');
        choice.type = 'hidden';
        choice.name = submitter.name;
        choice.value = submitter.value;
        form.append(choice);
    }

    form.submit();

    return null;
};

// Яндекс Метрика (ТЗ §14): загружается только после согласия на аналитические cookie (§15.10)
// и когда в настройках задан номер счётчика. Цели и электронная коммерция — события
// {goal, params, ecommerce} со страницы (data-metrika-events) и из ответов форм.
window.dataLayer = window.dataLayer || [];

const metrikaId = () => document.querySelector('meta[name="metrika"]')?.content ?? null;

const metrikaAllowed = () => metrikaId() !== null && document.documentElement.dataset.consent === 'all';

const track = (events) => {
    if (!metrikaAllowed() || !Array.isArray(events)) {
        return;
    }

    for (const event of events) {
        if (event.ecommerce) {
            window.dataLayer.push({ ecommerce: event.ecommerce });
        }

        if (event.goal) {
            window.ym(Number(metrikaId()), 'reachGoal', event.goal, event.params ?? {});
        }
    }
};

const startMetrika = () => {
    if (!metrikaAllowed() || window.ym) {
        return;
    }

    window.ym = function (...args) {
        (window.ym.a = window.ym.a || []).push(args);
    };
    window.ym.l = Date.now();

    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://mc.yandex.ru/metrika/tag.js';
    document.head.append(script);

    window.ym(Number(metrikaId()), 'init', { clickmap: true, trackLinks: true, accurateTrackBounce: true, ecommerce: 'dataLayer' });

    const events = document.querySelector('script[data-metrika-events]');
    track(events ? JSON.parse(events.textContent) : []);
};

startMetrika();

// Выбор в cookie-баннере: баннер прячется, при согласии на аналитику загружается счётчик.
const onConsent = (form, result) => {
    document.documentElement.dataset.consent = result.consent;
    form.closest('[data-cookie-banner]')?.remove();
    startMetrika();
};

// Переключатель «добавить / убрать» (сравнение, избранное): кнопка меняется на всех карточках
// этого товара, плитка в шапке показывает новое число и прячется, когда список пуст.
const toggleList = (name, form, product, active, count) => {
    for (const toggle of document.querySelectorAll(`[data-${name}="${product}"]`)) {
        const [add, remove] = toggle.querySelectorAll(`form[data-${name}-form]`);
        add.hidden = active;
        remove.hidden = !active;
    }

    // Нажатая кнопка спряталась: фокус переходит на ту, что встала на её место.
    form.closest(`[data-${name}]`)?.querySelector(`form[data-${name}-form]:not([hidden]) button`)?.focus();

    const link = document.querySelector(`[data-${name}-link]`);

    if (link) {
        link.hidden = count === 0;
        link.querySelector(`[data-${name}-count]`).textContent = String(count);
    }
};

const onCompared = (form, result) => toggleList('compare', form, result.product, result.compared, result.count);

const onFavorited = (form, result) => toggleList('favorite', form, result.product, result.favorite, result.count);

// Корзина в шапке: число позиций и сумма (макет, экран 5).
const onAddedToCart = (form, result) => {
    const link = document.querySelector('[data-cart-link]');

    if (!link) {
        return;
    }

    const { positions, label, total } = result.headline;
    const empty = positions === 0;

    link.querySelector('[data-cart-positions]').textContent = label;
    link.querySelector('[data-cart-caption]').hidden = empty;

    const sum = link.querySelector('[data-cart-total]');
    sum.textContent = total;
    sum.hidden = empty;

    const badge = link.querySelector('[data-cart-badge]');
    badge.textContent = String(positions);
    badge.hidden = empty;
};

// Короткая заявка отправлена: окно закрывается, форма очищается (ТЗ §5: leads).
const onLeadSent = (form) => {
    form.reset();
    showFieldErrors(form, {});
    form.closest('[popover]')?.hidePopover();
};

// Ошибки у полей формы лида (data-field-error): первая — в фокус.
const showFieldErrors = (form, errors) => {
    for (const slot of form.querySelectorAll('[data-field-error]')) {
        const messages = errors[slot.dataset.fieldError];
        slot.hidden = !messages;
        slot.textContent = messages ? messages[0] : '';
    }

    const first = Object.keys(errors)[0];

    if (first) {
        form.querySelector(`[name="${first}"]`)?.focus();
    }
};

const formHandlers = {
    'data-compare-form': onCompared,
    'data-favorite-form': onFavorited,
    'data-cart-form': onAddedToCart,
    'data-lead-form': onLeadSent,
    'data-consent-form': onConsent,
};

// Общее окно «Запросить цену» узнаёт товар от кнопки, которая его открыла.
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-lead-product-id]');
    const dialog = opener && document.getElementById(opener.getAttribute('popovertarget'));

    if (!dialog) {
        return;
    }

    dialog.querySelector('[data-lead-product-field]').value = opener.dataset.leadProductId;

    const name = dialog.querySelector('[data-lead-product-name]');

    if (name) {
        name.textContent = opener.dataset.leadProductName;
        name.hidden = false;
    }
});

// Страница корзины (Livewire) после каждого изменения сообщает новые число позиций и сумму.
window.addEventListener('cart-updated', (event) => onAddedToCart(null, event.detail));

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const kind = Object.keys(formHandlers).find((name) => form.hasAttribute(name));

    if (kind === undefined) {
        return;
    }

    event.preventDefault();

    // Второе нажатие, пока первое не вернулось, не кладёт товар дважды.
    if (form.hasAttribute('aria-busy')) {
        return;
    }

    form.setAttribute('aria-busy', 'true');
    const answer = await sendForm(form, event.submitter);
    form.removeAttribute('aria-busy');

    if (answer === null) {
        return;
    }

    if (answer.ok) {
        formHandlers[kind](form, answer.result);
        track(answer.result.metrika);
    } else if (answer.result.errors) {
        // Ошибки проверки полей (лиды): показываются у полей, уведомление не нужно.
        showFieldErrors(form, answer.result.errors);

        return;
    }

    if (answer.result.notice) {
        showNotice(answer.result.notice);
    }
});
// Маска телефона «+7 ___ ___-__-__» (ТЗ §10.2). Восьмёрку или семёрку в начале заменяет
// на +7; без скриптов поле принимает номер в любом виде — сервер приведёт его к тому же.
const formatPhone = (value) => {
    const typed = value.trim();
    const masked = typed.startsWith('+7');
    let digits = (masked ? typed.slice(2) : typed).replace(/\D/g, '');

    // Поле ещё без маски (первая цифра или вставка «8 978…»): 8 или 7 в начале — код страны.
    if (!masked && (digits[0] === '7' || digits[0] === '8')) {
        digits = digits.slice(1);
    }

    digits = digits.slice(0, 10);

    if (digits === '') {
        return '';
    }

    const parts = [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6, 8), digits.slice(8, 10)];
    let formatted = `+7 ${parts[0]}`;

    if (parts[1]) {
        formatted += ` ${parts[1]}`;
    }

    if (parts[2]) {
        formatted += `-${parts[2]}`;
    }

    if (parts[3]) {
        formatted += `-${parts[3]}`;
    }

    return formatted;
};

document.addEventListener('input', (event) => {
    const field = event.target;

    if (field instanceof HTMLInputElement && field.hasAttribute('data-phone-mask')) {
        field.value = formatPhone(field.value);
    }
});

// Липкая полоса покупки ниже 1024 px: появляется, когда панель покупки ушла вверх за экран.
const buyPanel = document.querySelector('[data-buy-panel]');
const stickyBuy = document.querySelector('[data-sticky-buy]');

if (buyPanel && stickyBuy) {
    new IntersectionObserver(([entry]) => {
        stickyBuy.hidden = entry.isIntersecting || entry.boundingClientRect.top > 0;
    }).observe(buyPanel);
}

// Livewire (ТЗ §14): листинги и корзина запускают его сразу, а страницам, где он нужен только
// мгновенному поиску в шапке, — карточке товара, главной, статическим страницам — его 98 КБ не
// мешают показать главное: он загружается при первом касании страницы или вскоре после загрузки.
// Поиск и без него — обычная форма. Что успели набрать до запуска, уходит в поиск после него.
let livewireStarted = false;

const startLivewire = async () => {
    if (livewireStarted) {
        return;
    }

    livewireStarted = true;

    const { Livewire } = await import('../../vendor/livewire/livewire/dist/livewire.esm');
    const typed = [...document.querySelectorAll('[wire\\:name="instant-search"] input[name="q"]')]
        .map((field) => [field, field.value]);

    Livewire.start();

    for (const [field, value] of typed) {
        if (value !== '' && field.value !== value) {
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
};

const onlySearch = [...document.querySelectorAll('[wire\\:name]')]
    .every((component) => component.getAttribute('wire:name') === 'instant-search');

if (!onlySearch) {
    startLivewire();
} else if (document.querySelector('[wire\\:name]')) {
    for (const type of ['focusin', 'pointerdown', 'keydown', 'touchstart']) {
        document.addEventListener(type, startLivewire, { once: true, passive: true });
    }

    const later = () => setTimeout(startLivewire, 3000);

    if (document.readyState === 'complete') {
        later();
    } else {
        window.addEventListener('load', later, { once: true });
    }
}

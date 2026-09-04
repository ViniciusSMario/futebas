import Alpine from 'alpinejs';
import { createPushStore } from './push';

window.Alpine = Alpine;

// Web Push state, shared by the toggle component and any page that wants
// to know whether this device is subscribed.
Alpine.data('pushNotifications', createPushStore);

/**
 * Progressive Brazilian phone mask: (00) 0000-0000 / (00) 00000-0000.
 * Applied via delegation to any input carrying `data-phone-mask`, so it
 * also works on inputs added later (e.g. inside an Alpine `x-if` template).
 */
function formatPhone(value) {
    const digits = value.replace(/\D/g, '').slice(0, 11);

    if (digits.length > 10) {
        return digits.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3').replace(/[-\s]+$/, '');
    }
    if (digits.length > 6) {
        return digits.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3').replace(/[-\s]+$/, '');
    }
    if (digits.length > 2) {
        return digits.replace(/(\d{2})(\d{0,5})/, '($1) $2').replace(/\s+$/, '');
    }
    if (digits.length > 0) {
        return digits.replace(/(\d{0,2})/, '($1');
    }

    return digits;
}

document.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches('[data-phone-mask]')) {
        event.target.value = formatPhone(event.target.value);
    }
});

/**
 * Cascading state -> city selects.
 *
 * The list comes from the app itself (`/cidades/{uf}`), not from IBGE's
 * API: the data ships with the project so a flaky connection can't turn
 * "cidade" into a dead field, and so the server can validate what comes
 * back. Responses are cached per UF for the life of the page, on top of
 * the long Cache-Control the endpoint already sends.
 *
 * The state <select> carries `data-state-select="<city-select-id>"`; the
 * city <select> may carry `data-selected="<current value>"` to preselect
 * once its options load (used when editing existing data).
 *
 * Wired via delegation (for the `change` event) plus a MutationObserver
 * (to auto-load cities for a pre-filled state as soon as the pair of
 * selects appears in the DOM), so it works even when the fields live
 * inside an Alpine `x-if` template that isn't rendered at page load.
 */
const cityCache = new Map();

function fetchCities(uf) {
    if (!cityCache.has(uf)) {
        cityCache.set(
            uf,
            fetch(`/cidades/${uf}`, { headers: { Accept: 'application/json' } })
                .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
                .catch((error) => {
                    // Uma falha não pode virar cache: a próxima tentativa
                    // precisa poder dar certo.
                    cityCache.delete(uf);
                    return Promise.reject(error);
                }),
        );
    }

    return cityCache.get(uf);
}

function loadCitiesInto(citySelect, uf, selectedCity) {
    if (!uf) {
        citySelect.innerHTML = '<option value="">Selecione o estado primeiro</option>';
        return;
    }

    const placeholder = citySelect.dataset.placeholder || 'Selecione...';

    citySelect.innerHTML = '<option value="">Carregando...</option>';

    fetchCities(uf)
        .then((cities) => {
            citySelect.innerHTML = `<option value="">${placeholder}</option>`;
            cities.forEach((city) => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                if (selectedCity && selectedCity === city) {
                    option.selected = true;
                }
                citySelect.appendChild(option);
            });
        })
        .catch(() => {
            citySelect.innerHTML = '<option value="">Não foi possível carregar as cidades</option>';
        });
}

function citySelectFor(stateSelect) {
    return document.getElementById(stateSelect.dataset.stateSelect);
}

document.addEventListener('change', (event) => {
    if (event.target instanceof HTMLSelectElement && event.target.matches('[data-state-select]')) {
        const citySelect = citySelectFor(event.target);
        if (citySelect) {
            loadCitiesInto(citySelect, event.target.value, null);
        }
    }
});

const initializedStateSelects = new WeakSet();

function initStateSelectIfPrefilled(stateSelect) {
    if (initializedStateSelects.has(stateSelect) || !stateSelect.value) {
        return;
    }

    const citySelect = citySelectFor(stateSelect);

    if (!citySelect) {
        return;
    }

    initializedStateSelects.add(stateSelect);

    // Quando o servidor já mandou as opções da UF escolhida — o caso do
    // componente <x-city-select> — não há nada a buscar: a lista já está
    // na página, com a cidade atual selecionada.
    if (citySelect.options.length > 1) {
        return;
    }

    loadCitiesInto(citySelect, stateSelect.value, citySelect.dataset.selected || null);
}

document.querySelectorAll('[data-state-select]').forEach(initStateSelectIfPrefilled);

new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (!(node instanceof HTMLElement)) {
                return;
            }

            if (node.matches('[data-state-select]')) {
                initStateSelectIfPrefilled(node);
            }

            node.querySelectorAll?.('[data-state-select]').forEach(initStateSelectIfPrefilled);
        });
    });
}).observe(document.body, { childList: true, subtree: true });

/**
 * Scroll reveal. Any element carrying `.reveal` fades/slides in the first
 * time it enters the viewport; `data-delay="1..6"` staggers a group.
 * Lives here rather than in a per-page <script> so the landing page and
 * the authenticated app share one implementation.
 */
function initScrollReveal() {
    const elements = document.querySelectorAll('.reveal:not(.is-visible)');

    if (!elements.length) {
        return;
    }

    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReduced || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.12 }
    );

    elements.forEach((element) => observer.observe(element));
}

initScrollReveal();

Alpine.start();

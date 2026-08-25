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
 * Cascading state -> city selects, populated from IBGE's public API.
 * The state <select> carries `data-state-select="<city-select-id>"`;
 * the city <select> may carry `data-selected="<current value>"` to
 * preselect once its options load (used when editing existing data).
 *
 * Wired via delegation (for the `change` event) plus a MutationObserver
 * (to auto-load cities for a pre-filled state as soon as the pair of
 * selects appears in the DOM), so it works even when the fields live
 * inside an Alpine `x-if` template that isn't rendered at page load.
 */
function loadCitiesInto(citySelect, uf, selectedCity) {
    if (!uf) {
        citySelect.innerHTML = '<option value="">Selecione o estado primeiro</option>';
        return;
    }

    citySelect.innerHTML = '<option value="">Carregando...</option>';

    fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios`)
        .then((response) => response.json())
        .then((cities) => {
            citySelect.innerHTML = '<option value="">Selecione...</option>';
            cities.forEach((city) => {
                const option = document.createElement('option');
                option.value = city.nome;
                option.textContent = city.nome;
                if (selectedCity && selectedCity === city.nome) {
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

Alpine.start();

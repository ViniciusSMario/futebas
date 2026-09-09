import Swal from 'sweetalert2';

/**
 * Duas coisas que todo formulário do app ganha de graça: pedir confirmação
 * antes de uma ação destrutiva, e não deixar ser enviado duas vezes.
 *
 * O `confirm()` do navegador saiu por dois motivos. Dentro do app instalado
 * ele aparece como alerta do sistema, com a cara do navegador e não a do
 * Futebas — no meio de uma tela escura. E, mais importante, ele é uma linha
 * só: "Cancelar esse Game?" nunca teve onde dizer que os SOS abertos morrem
 * junto, nem que os goleiros candidatos serão avisados. A pergunta e a
 * consequência são coisas diferentes, e a segunda é a que faz alguém mudar
 * de ideia.
 *
 * A trava de envio existe pela beira do campo: no 4G, um toque duplo em
 * "Sortear times" ou "Confirmar presença" manda dois POSTs, e o segundo
 * chega depois de o primeiro já ter mudado o estado.
 *
 * Ambos são delegados no `document`, e não montados por formulário, para
 * valerem também para o que aparece depois — dentro de um `x-modal`, de um
 * `x-if` do Alpine, de qualquer coisa renderizada mais tarde.
 */

/** Marca que este formulário já passou pela pergunta. */
const CONFIRMED = 'fiConfirmed';

/**
 * O tema. `buttonsStyling: false` desliga o CSS de botão do SweetAlert para
 * os botões serem os mesmos do resto do app — o `content` do
 * `tailwind.config.js` já inclui `resources/js`, então estas classes são
 * vistas pelo build como qualquer outra.
 */
const BASE_CLASSES = {
    popup: 'rounded-3xl border border-pitch-800 shadow-2xl shadow-black/70',
    title: 'text-lg font-black text-white',
    htmlContainer: 'text-sm text-pitch-300 leading-relaxed',
    actions: 'gap-3',
    cancelButton:
        'inline-flex items-center justify-center min-h-[44px] px-5 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition',
};

const dialog = Swal.mixin({
    background: '#101c18',
    color: '#d7e3dc',
    backdrop: 'rgba(0, 0, 0, 0.7)',
    buttonsStyling: false,
    // Cancelar à esquerda, confirmar à direita: a mesma ordem do
    // `<x-modal>` que o app já usa para cancelar participação.
    reverseButtons: true,
    customClass: BASE_CLASSES,
});

const CONFIRM_BUTTON = {
    danger: 'inline-flex items-center justify-center min-h-[44px] px-5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-red-600 hover:bg-red-500 transition',
    default:
        'inline-flex items-center justify-center min-h-[44px] px-5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition',
};

/**
 * A pergunta. Resolve para `true` só quando a pessoa confirma — fechar no
 * ESC, no backdrop ou no "Voltar" é sempre "não".
 */
function ask(form) {
    const tone = form.dataset.confirmTone === 'default' ? 'default' : 'danger';

    return dialog
        .fire({
            title: form.dataset.confirm,
            text: form.dataset.confirmText || undefined,
            icon: tone === 'danger' ? 'warning' : 'question',
            iconColor: tone === 'danger' ? '#f87171' : '#34d399',
            showCancelButton: true,
            confirmButtonText: form.dataset.confirmButton || 'Confirmar',
            cancelButtonText: form.dataset.confirmCancel || 'Voltar',
            // Numa ação destrutiva o foco começa no botão seguro: quem
            // chega apertando Enter não deveria conseguir destruir nada.
            focusCancel: tone === 'danger',
            // O `mixin` do SweetAlert mescla parâmetros de forma rasa: o
            // `customClass` daqui substitui o do mixin inteiro, então a
            // base vai junto em vez de ser herdada.
            customClass: { ...BASE_CLASSES, confirmButton: CONFIRM_BUTTON[tone] },
        })
        .then((result) => result.isConfirmed);
}

/**
 * Trava os botões de envio do formulário.
 *
 * O `disabled` só entra no tique seguinte de propósito: um botão desativado
 * durante o próprio evento de submit sai da serialização, e um formulário
 * que dependesse do `name`/`value` do botão perderia esse campo.
 */
function lock(form) {
    const buttons = form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]');

    if (buttons.length === 0) {
        return;
    }

    setTimeout(() => {
        buttons.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.classList.add('is-submitting');
        });
    }, 0);
}

function unlockAll() {
    document.querySelectorAll('.is-submitting').forEach((button) => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.classList.remove('is-submitting');
    });
}

export function initFormGuards() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.dataset.noLock !== undefined) {
            return;
        }

        if (form.dataset.confirm && form.dataset[CONFIRMED] === undefined) {
            event.preventDefault();

            ask(form).then((confirmed) => {
                if (!confirmed) {
                    return;
                }

                form.dataset[CONFIRMED] = '';

                // `requestSubmit` dispara o evento de novo, e desta vez a
                // marca acima o deixa passar direto para a trava. O
                // `submit()` cru não dispara evento nenhum, por isso o
                // fallback tranca na mão.
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    lock(form);
                    form.submit();
                }
            });

            return;
        }

        lock(form);
    });

    // Voltar pelo histórico pode devolver a página do cache do navegador
    // exatamente como ela estava — inclusive com os botões travados, e aí
    // o formulário fica morto sem nenhum aviso.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            unlockAll();
        }
    });
}

/** Para quem precisa perguntar fora de um formulário. */
export { dialog };

/**
 * Convite para instalar o app na tela de início.
 *
 * São dois mundos diferentes por baixo do mesmo cartão. No Android o
 * navegador dispara `beforeinstallprompt`, que dá um instalador de verdade —
 * mas só se o evento for guardado, porque ele é oferecido uma vez e some. No
 * iPhone esse evento não existe: o Safari só instala pelo menu Compartilhar,
 * e a única coisa que o app pode fazer é ensinar o caminho. Por isso a
 * store tem dois estados de "dá para instalar", e não um.
 *
 * O convite se cala sozinho em três situações: já instalado, recusado há
 * pouco, ou primeira página que a pessoa vê. A última é a que mais importa —
 * um banner de instalação no primeiro segundo de uso é o anúncio que ensina
 * a ignorar tudo o que vier depois.
 */

const DISMISSED_AT = 'fi_install_dismissed_at';
const PAGE_VIEWS = 'fi_install_views';

/** Quanto tempo o "agora não" vale. Recusar não é para sempre. */
const SNOOZE_DAYS = 14;

/** Só a partir da segunda página: primeiro deixa a pessoa usar o app. */
const VIEWS_BEFORE_ASKING = 2;

/**
 * localStorage joga em aba anônima e com cookies bloqueados, e aqui ele é
 * conveniência: sem ele o convite simplesmente volta a aparecer.
 */
function readStorage(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Sem memória: o convite reaparece na próxima visita. Tudo bem.
    }
}

/**
 * O evento chega antes de o Alpine subir, e só é oferecido uma vez — se
 * ninguém o guardar aqui no carregamento do módulo, o botão "Instalar"
 * nunca teria o que chamar.
 */
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    window.dispatchEvent(new CustomEvent('fi:installable'));
});

/** Já está rodando como app instalado? Então não há o que convidar. */
function isStandalone() {
    return (
        window.matchMedia?.('(display-mode: standalone)').matches === true ||
        window.navigator.standalone === true
    );
}

/**
 * iPhone/iPad **no Safari**. Os outros navegadores do iOS não conseguem
 * adicionar à tela de início, então ensinar o caminho para eles seria dar
 * uma instrução que não funciona.
 */
function isIosSafari() {
    const ua = navigator.userAgent;
    const ios = /iPad|iPhone|iPod/.test(ua) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const otherBrowser = /CriOS|FxiOS|EdgiOS|OPiOS|Chrome/.test(ua);

    return ios && !otherBrowser;
}

function snoozing() {
    const at = Number(readStorage(DISMISSED_AT));

    if (!at) {
        return false;
    }

    return Date.now() - at < SNOOZE_DAYS * 24 * 60 * 60 * 1000;
}

export function createInstallStore() {
    return {
        /** Android e desktop: existe instalador de verdade a chamar. */
        installable: false,
        /** iOS: não existe instalador, existe um caminho a ensinar. */
        needsIosSteps: false,
        /** O cartão flutuante está aberto? */
        open: false,
        /** Instalado durante esta visita — some tudo, sem piscar de volta. */
        installed: false,
        /** Páginas vistas neste navegador, para não convidar de cara. */
        views: 0,

        init() {
            if (isStandalone()) {
                return;
            }

            this.countView();

            if (deferredPrompt) {
                this.installable = true;
            }

            window.addEventListener('fi:installable', () => {
                this.installable = true;
                this.offer();
            });

            window.addEventListener('appinstalled', () => {
                this.installed = true;
                this.open = false;
                this.installable = false;
                this.needsIosSteps = false;
                // Instalou: o convite não volta nunca mais neste navegador.
                writeStorage(DISMISSED_AT, String(Date.now()));
            });

            if (isIosSafari()) {
                this.needsIosSteps = true;
            }

            this.offer();
        },

        /** Verdadeiro quando há algo a oferecer, aberto ou não. */
        get available() {
            return !this.installed && (this.installable || this.needsIosSteps);
        },

        countView() {
            const views = Number(readStorage(PAGE_VIEWS) || 0) + 1;
            writeStorage(PAGE_VIEWS, String(views));
            this.views = views;
        },

        offer() {
            if (!this.available || snoozing() || this.views < VIEWS_BEFORE_ASKING) {
                return;
            }

            this.open = true;
        },

        /** Reabre por vontade da pessoa — pelo menu "Mais". Ignora a soneca. */
        show() {
            if (this.available) {
                this.open = true;
            }
        },

        async install() {
            if (!deferredPrompt) {
                return;
            }

            const prompt = deferredPrompt;
            // O evento vale uma vez só: guardar depois de usar seria oferecer
            // um instalador que o navegador já descartou.
            deferredPrompt = null;
            this.installable = false;

            prompt.prompt();

            const { outcome } = await prompt.userChoice;

            this.open = false;

            if (outcome !== 'accepted') {
                this.dismiss();
            }
        },

        dismiss() {
            this.open = false;
            writeStorage(DISMISSED_AT, String(Date.now()));
        },
    };
}

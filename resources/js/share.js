/**
 * Compartilhar o link público de uma partida.
 *
 * Este link é o laço inteiro de crescimento do app: o organizador cria a
 * pelada aqui e as vagas se preenchem no grupo do WhatsApp. Mesmo assim,
 * até agora ele era um `<input readonly>` com `onclick="this.select()"` ao
 * lado de um botão que *abria* o link em outra aba — nenhum dos dois
 * compartilha nada, e no celular selecionar texto dentro de um input é
 * exatamente o gesto que ninguém faz.
 *
 * São três caminhos, em ordem de preferência:
 *
 * 1. `navigator.share` — a folha nativa do sistema. No Android e no iOS ela
 *    entrega WhatsApp, Instagram, Telegram e o resto de uma vez, e é a
 *    única que já vem com o texto pronto no app de destino.
 * 2. Copiar — o que sobra no desktop, onde `navigator.share` quase nunca
 *    existe.
 * 3. O link direto do WhatsApp (`wa.me`), que é `<a href>` puro no Blade e
 *    funciona mesmo se este módulo nunca carregar.
 */

/**
 * `navigator.clipboard` só existe em contexto seguro. Em HTTP — um staging
 * sem certificado, o IP da máquina na rede local — ele simplesmente não
 * está lá, e sem a segunda tentativa o botão "Copiar" seria um botão morto
 * justamente em quem está testando.
 */
async function writeToClipboard(text) {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Permissão negada ou fora de contexto seguro: cai no plano B.
        }
    }

    const field = document.createElement('textarea');
    field.value = text;
    // Fora da tela, mas não `display:none` nem `hidden`: o que não é
    // renderizado não é selecionável, e sem seleção não há o que copiar.
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.top = '-1000px';
    field.style.opacity = '0';
    document.body.appendChild(field);

    try {
        field.select();
        field.setSelectionRange(0, field.value.length);

        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        field.remove();
    }
}

export function createShareLink({ url, title, text }) {
    return {
        /** 'idle' | 'copied' | 'failed' */
        state: 'idle',

        /**
         * Só depois do Alpine montar: renderizar "Compartilhar" no servidor
         * e trocar para "Copiar" no cliente faria o botão principal piscar
         * de rótulo em toda visita de desktop.
         */
        canShare: false,

        init() {
            this.canShare = typeof navigator.share === 'function';
        },

        async share() {
            if (!this.canShare) {
                return this.copy();
            }

            try {
                await navigator.share({ title, text, url });
            } catch (error) {
                // Fechar a folha é `AbortError` e não é falha nenhuma —
                // avisar "não deu" a quem desistiu de propósito é pior do
                // que ficar calado. Qualquer outro erro cai na cópia.
                if (error?.name !== 'AbortError') {
                    await this.copy();
                }
            }
        },

        async copy() {
            this.state = (await writeToClipboard(url)) ? 'copied' : 'failed';

            setTimeout(() => {
                this.state = 'idle';
            }, 2500);
        },
    };
}

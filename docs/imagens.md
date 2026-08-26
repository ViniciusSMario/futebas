# Imagens — onde entram e em que tamanho

Todo espaço reservado para imagem no projeto usa o componente
`<x-img-placeholder>`. Enquanto o arquivo não existe, ele desenha uma moldura
tracejada com o nome, o tamanho sugerido e o caminho do arquivo — nada quebra
e nada fica em branco.

## Como trocar o placeholder pela imagem real

1. Salve o arquivo no caminho indicado na moldura (as pastas
   `public/images/landing/` e `public/images/app/` já existem).
2. Acrescente `src` na chamada do componente — só isso. O recorte, a proporção
   e o arredondamento continuam idênticos:

```blade
{{-- antes --}}
<x-img-placeholder ratio="4/5" label="Foto principal" path="images/landing/hero.jpg" size="1200x1500" />

{{-- depois --}}
<x-img-placeholder ratio="4/5" src="images/landing/hero.jpg" alt="Pelada de quinta na Arena Central" />
```

O componente já cuida de `loading="lazy"`, `decoding="async"` e
`object-cover`. Para uma imagem acima da dobra, passe `:eager="true"` — isso
troca por `loading="eager"` e `fetchpriority="high"`.

### Props

| Prop | Padrão | Para que serve |
| --- | --- | --- |
| `ratio` | `16/9` | Proporção CSS (`4/5`, `1/1`, `9/19.5`, `21/9`…) |
| `src` | — | Arquivo real. Com ele, o placeholder some |
| `alt` | usa `label` | Texto alternativo da imagem real |
| `label` | `Imagem` | O que é a imagem, mostrado na moldura |
| `path` | — | Caminho sugerido, mostrado na moldura |
| `size` | — | Tamanho de exportação sugerido |
| `note` | — | Direção de arte para quem produz a foto |
| `tone` | `dark` | `dark`, `light` ou `accent`, conforme o fundo |
| `rounded` | `rounded-3xl` | Classe de arredondamento |
| `icon` | `heroicon-o-photo` | Ícone dentro da moldura |
| `eager` | `false` | Desliga o lazy-load (imagens acima da dobra) |

## Landing (`resources/views/welcome.blade.php`)

| Arquivo | Tamanho | Proporção | Onde aparece / direção de arte |
| --- | --- | --- | --- |
| `images/landing/og-image.jpg` | 1200x630 | 1.91/1 | Prévia do link no WhatsApp e Instagram. **A mais importante para divulgação** — precisa ter logo e uma frase legível em miniatura |
| `images/landing/hero.jpg` | 1200x1500 | 4/5 | Foto principal do topo. Vertical, alto contraste, rosto visível. É a primeira imagem que o público vê |
| `images/landing/jogador-1..4.jpg` | 200x200 | 1/1 | Quatro avatares na prova social do topo. Rostos diferentes, enquadramento fechado |
| `images/landing/sos-goleiro.jpg` | 900x1200 | 3/4 | Bloco SOS Goleiro. Momento de defesa. Fica sobre fundo vermelho: prefira imagem escura e contrastada |
| `images/landing/passo-1.jpg` | 800x500 | 16/10 | "Crie seu perfil" |
| `images/landing/passo-2.jpg` | 800x500 | 16/10 | "Monte ou entre na partida" |
| `images/landing/passo-3.jpg` | 800x500 | 16/10 | "Complete o time" |
| `images/landing/jogadores.jpg` | 1000x563 | 16/9 | Card "Para jogadores" |
| `images/landing/organizadores.jpg` | 1000x563 | 16/9 | Card "Para organizadores" |
| `images/landing/app-tela.png` | 1080x2340 | 9/19.5 | Print real do app dentro do mockup de celular, no tema escuro |
| `images/landing/depoimento-1..3.jpg` | 200x200 | 1/1 | Avatares dos depoimentos |
| `images/landing/cta-fundo.jpg` | 1920x1080 | 16/9 | Fundo do CTA final. Panorâmica e escura — fica atrás do texto, com sobreposição verde por cima |

## App (`resources/views/dashboard/`)

| Arquivo | Tamanho | Proporção | Onde aparece |
| --- | --- | --- | --- |
| `images/app/banner-organizador.jpg` | 1600x680 | 21/9 | Fundo da saudação no painel do organizador |
| `images/app/banner-jogador.jpg` | 1600x680 | 21/9 | Fundo da saudação no painel do jogador |

Os dois banners ficam sob um gradiente verde forte: use fotos escuras e sem
detalhe importante no lado esquerdo, que é onde ficam o avatar e o "Olá".

## Fotos de pessoas dentro do app

Não são placeholders: avatares de usuários vêm de `<x-avatar>`, que lê
`users.photo_path` e cai para `player_profiles.photo_path`; sem nenhuma das
duas, desenha a inicial do nome. Nada a fazer aqui.

## Textos de exemplo

Os números da faixa de estatísticas (`+2.000 jogadores`, `+500 peladas`,
`15 min`, `30+ cidades`) e os três depoimentos da landing são **exemplos**.
Troque por dados reais antes de divulgar.

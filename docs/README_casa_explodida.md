# Casa explodida: modelagem, animação e linhas

Registro do que foi feito até aqui para a animação de abertura do site de arquitetura e das escolhas por trás de cada etapa.

## Objetivo

Reproduzir, em 3D, o visual das perspectivas explodidas usadas como referência (casa moderna de dois pavimentos, em desenho de linhas), e usar esse modelo em uma animação de abertura em que a casa aparece desmontada e vai se montando. A animação roda em three.js no desktop e, no celular, como vídeo renderizado no Blender.

## Arquivos

| Arquivo | Função |
| --- | --- |
| `casa_explodida.py` | Script do Blender que modela a casa em componentes separados e gera os keyframes de montagem. |
| `casa_linhas.html` | Viewer three.js que carrega o `casa.glb`, desenha o modelo em linhas e anima a montagem no navegador. |
| `casa.glb` | Exportação do Blender, gerada por você a partir do script. Precisa ficar na mesma pasta do HTML. |

## 1. Modelagem no Blender (`casa_explodida.py`)

### Como o modelo é construído

Toda a geometria é feita de caixas montadas por código, sem `bpy.ops`, através de `criar_objeto()`. Cada objeto recebe uma lista de caixas (centro local e dimensões) e tem a origem no centro da peça. Isso foi escolhido por dois motivos: o script roda em modo headless sem depender de contexto de viewport, e a origem no centro torna a animação por `location` direta.

Componentes compostos têm construtores próprios: `criar_janela`, `criar_porta_correr`, `criar_brise`, `criar_guarda_corpo`, `criar_portao_enrolar` e `criar_escada`. Todos aceitam um parâmetro `plano`: `'xz'` para peças voltadas para frente ou fundo, `'yz'` para as laterais. A função `_p()` converte coordenadas "no plano" para xyz, o que evita repetir a lógica para cada orientação.

Janelas e portas de correr são hierárquicas: a moldura é o objeto pai e o vidro e as folhas são filhos. Animar a moldura arrasta o conjunto inteiro.

### Organização por coleções

| Coleção | Conteúdo |
| --- | --- |
| `00_Base` | Laje inferior e laje do térreo |
| `01_Terreo_Estrutura` | Pilares, pórtico de entrada, pilares e laje da garagem |
| `02_Terreo_Paredes` | Paredes externas, internas, escada e paredes da garagem |
| `03_Terreo_Esquadrias` | Janelas, portas de correr, brise e portão de enrolar |
| `04_Laje_Superior` | Laje do pavimento superior e vigas de borda |
| `05_Superior_Paredes` | Paredes do pavimento superior |
| `06_Superior_Esquadrias_Brises` | Esquadrias, brises e guarda-corpos da varanda |
| `07_Cobertura` | Pontaletes, cumeeira, terças, caibros, chapas das águas e ripas |

### Dimensões principais

Unidades em metros. Corpo principal de 10 m por 8 m, térreo com 3 m de pé-direito, laje de 0,30 m, pavimento superior com 3 m. Frente da casa em y negativo. Cobertura em duas águas com cumeeira em y = 0, beiral até y = ±6 e 14 m de extensão em x; a função `z_telhado(y)` dá a altura da superfície em qualquer ponto. Garagem como anexo mais baixo à direita, portão de enrolar de lâminas.

### Materiais

Cinco materiais Principled de cor chapada: Concreto, Madeira, Metal, Esquadria e Vidro (com alpha 0,3). Sem nós procedurais nem texturas de imagem, porque o exportador glTF não converte nós procedurais e porque texturas pesariam no carregamento do site.

## 2. Preparação para a animação

Todo objeto de topo recebe duas propriedades personalizadas:

- `explode_vec`: vetor (x, y, z) que indica para onde a peça se afasta na desmontagem.
- `explode_ordem`: número do grupo. 0 é o telhado (as ripas têm ordens fracionárias para sair em cascata), 5,5 é a base.

A função `gerar_animacao()` lê essas propriedades e cria dois keyframes de `location` por peça, com atraso proporcional à ordem. A constante `MODO_ANIMACAO` escolhe entre `'desmontar'` (casa montada que se abre, telhado primeiro) e `'montar'` (casa espalhada que se fecha, base primeiro). A versão de montagem inverte a ordem, e não apenas espelha o tempo, para que a base chegue antes do telhado, o que parece mais natural.

Ajustes rápidos ficam no bloco CONFIGURAÇÃO no topo do script: `GERAR_ANIMACAO`, `MODO_ANIMACAO`, `DURACAO_MOVIMENTO`, `ATRASO_POR_ORDEM` e `FATOR_EXPLOSAO`. Para mudar uma peça específica, edita-se o `explode` e o `ordem` na chamada correspondente dentro de `modelar()`.

Também foi vista a alternativa de inverter direto no Blender (Dope Sheet, selecionar keyframes, S, -1), que apenas espelha o tempo.

## 3. Visual em linhas

Decisão: não investir em texturas realistas. As referências são desenhos de linha, a geometria é toda de caixas retas (textura em caixa sem chanfro parece plástico) e texturas de imagem aumentam o .glb, que carrega na primeira dobra do site. O visual de desenho técnico comunica melhor a área e pesa muito menos.

As linhas são geradas de duas formas independentes, uma para cada destino.

### No navegador (`casa_linhas.html`)

O viewer troca o material de cada malha por branco chapado (`MeshBasicMaterial` com `polygonOffset` para evitar z-fighting) e adiciona por cima um `LineSegments` criado com `EdgesGeometry`, que só traça arestas com ângulo maior que `ANGULO_ARESTA`. Malhas cujo material contém "vidro" no nome ficam semitransparentes, com linhas mais fracas.

A montagem é feita pelo próprio three.js, sem usar os keyframes do Blender: o viewer lê `explode_vec` e `explode_ordem` do `userData` de cada objeto (é para isso que a exportação precisa de Custom Properties) e interpola a posição com ease-out. O vetor é convertido de Z para cima (Blender) para Y para cima (glTF): (x, y, z) vira (x, z, -y). `prefers-reduced-motion` é respeitado, mostrando a casa já montada.

### No Blender (vídeo para celular)

A função `configurar_render_linhas()` liga o Freestyle sobre um render Workbench branco e plano, com fundo transparente para compor sobre a cor do site. Ela afeta apenas o render: as linhas do Freestyle não aparecem no viewport, só em F12 ou Ctrl+F12. Ela também não altera o .glb. Como o Workbench renderiza quase instantaneamente, gerar todos os frames leva poucos minutos.

Alternativa registrada: modificador Line Art do Grease Pencil, que gera traços editáveis e permite animar o "desenhar" das linhas.

## 4. Fluxo de trabalho

Para o site (desktop):

1. Rodar `casa_explodida.py` no Blender sem chamar `configurar_render_linhas()`.
2. File > Export > glTF 2.0, formato glb, com "Custom Properties" marcado na aba Include. Salvar como `casa.glb` ao lado do HTML.
3. Abrir a pasta com um servidor local (`python -m http.server`) e acessar `casa_linhas.html`. Abrir por `file://` não funciona porque o navegador bloqueia o carregamento do .glb.

Para o vídeo (celular):

1. No mesmo arquivo do Blender, chamar `configurar_render_linhas()`.
2. Posicionar uma câmera e renderizar a animação com Ctrl+F12.

## 5. Decisões sobre peso e compressão

- Draco: não usar por enquanto. O modelo é leve (centenas de KB) e o decodificador do Draco pesa cerca de 300 KB mais uma etapa de decodificação, o que provavelmente custaria mais do que economizaria. Se o arquivo crescer além de 1 ou 2 MB, preferir Meshopt via `gltf-transform`, que tem decodificador menor.
- O que mais importa no carregamento é o servidor entregar o .glb com gzip ou brotli, o que a maioria das hospedagens já faz.
- No celular, testar o three.js antes de partir para vídeo em frames: um modelo desse tamanho roda bem em celulares recentes e a sequência de imagens costuma pesar mais que o .glb. Se o fallback for necessário, preferir um vídeo curto com `currentTime` controlado por scroll a centenas de JPGs.

## Próximos passos possíveis

- Ligar a montagem ao scroll da página (GSAP ScrollTrigger ou controle manual do tempo em `quadro()`).
- Adicionar um modificador Bevel pequeno se um dia o modelo for renderizado com luz e sombra.
- Ajustar `ANGULO_ARESTA` e a espessura das linhas para casar o HTML com o vídeo renderizado.

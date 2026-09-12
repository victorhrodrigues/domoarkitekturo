# Guia de Estudos — Arquitetura do Domoarkitekturo

> Diferente do [`contexto-do-projeto.md`](./contexto-do-projeto.md) (que registra decisões e estado do projeto, pra eu — a IA — manter contexto entre conversas), este arquivo é pra **você**: uma referência pra estudar depois, explicando o porquê das coisas com mais profundidade. Ele cresce conforme o projeto avança — cada vez que aprendermos algo novo relevante, isso entra aqui.

---

## 1. Por que Next.js em vez de um SPA React puro (Vite)

O projeto começou com Vite + React (SPA — *Single Page Application*). Um SPA funciona assim: o servidor manda um HTML quase vazio (`<div id="root"></div>`) e um arquivo JavaScript. O navegador baixa o JS, executa, busca dados via `fetch`, e só depois disso o conteúdo aparece na tela — tudo acontece no **cliente** (o navegador).

Isso é um problema real para um site de portfólio/blog que depende de:
1. **SEO** — o Google indexa SPAs, mas em duas etapas (primeiro o HTML vazio, depois enfileira pra renderizar o JS — pode levar horas/dias, e não é garantido pra sites novos sem autoridade de domínio).
2. **Preview em redes sociais** — WhatsApp, Instagram e Facebook **não executam JavaScript** quando alguém compartilha um link. Eles só leem as tags `<meta property="og:title">` etc. que já estão no HTML inicial. Numa SPA pura, isso é estático e genérico pra todas as páginas.

**Next.js (App Router)** resolve isso porque, por padrão, os componentes são **Server Components**: eles rodam no servidor e viram HTML *antes* de chegar no navegador — sem esperar JS nenhum. Isso também dá acesso nativo a:
- **`metadata`/`generateMetadata`** — define título/descrição/OG tags por página, já embutidos no HTML.
- **`middleware.ts`** — pode proteger rotas (tipo `/admin`) no servidor, antes de renderizar qualquer coisa — diferente de um SPA, onde a checagem de "está logado?" só acontece depois do JS carregar.

## 2. Server Components vs. Client Components

Essa é a distinção mais importante do App Router:

| | Server Component (padrão) | Client Component (`"use client"`) |
|---|---|---|
| Onde roda | No servidor, uma vez, vira HTML | No navegador, pode re-executar |
| Pode usar `useState`, `onClick`, `onSubmit`? | Não | Sim |
| Pode ser `async` e buscar dados direto? | Sim | Não diretamente (precisa de `useEffect` ou de uma Server Action) |
| Vai no bundle JS que o navegador baixa? | Não | Sim |

Regra prática: comece todo componente como Server Component (é o padrão, não precisa fazer nada) e só adicione `"use client"` quando precisar de interatividade de verdade (formulário, estado, evento de clique). É por isso que `frontend/src/app/login/page.tsx` começa com `"use client"` — ele tem `useState` e `onSubmit`, que não existem no servidor.

## 3. Route Groups — `(site)` e `(admin)`

Uma pasta entre parênteses, tipo `(admin)`, é um **route group**: organiza arquivos e permite layouts diferentes, mas **não aparece na URL**. `frontend/src/app/(admin)/admin/page.tsx` vira a rota `/admin`, não `/(admin)/admin`. Usamos isso pra separar o layout do site público do layout do painel administrativo, sem misturar os dois vindo de pastas soltas.

## 4. Organização de pastas: `lib/`, `types/`, `services/` (e o que falta)

Conforme o projeto cresce, separar "o que a tela mostra" de "como a gente busca dado" evita que tudo vire uma bagunça só de componente. A convenção que adotamos:

```
lib/api.ts        → infraestrutura genérica: um fetch configurado (base URL, headers padrão, tratamento de erro). Não sabe nada sobre "login" ou "projeto".
types/user.ts      → contratos TypeScript compartilhados entre várias partes do app.
services/auth.ts    → lógica de domínio (login, logout, buscar usuário logado), construída em cima da lib/.
```

**Por que separar `lib/` de `services/`?** `lib/api.ts` não sabe o que é um "usuário" ou um "login" — ele só sabe fazer requisição HTTP e tratar erro de forma genérica. `services/auth.ts` sabe que login é um `POST /api/login` com email/senha. Se amanhã criarmos `services/projects.ts` pra buscar o portfólio, ele reaproveita a mesma `lib/api.ts`, sem duplicar a lógica de headers/erro.

**Peças que ainda não existem, e o critério pra criar cada uma quando chegar a hora** (evitar pasta vazia — "estrutura decorativa" sem conteúdo real é pior que não ter estrutura):
- `components/` — quando houver repetição de UI pra extrair (hoje só existe um formulário, nada se repete ainda).
- `actions/` — terminologia específica do Next.js pra **Server Actions** (funções `"use server"` chamadas direto de um formulário, sem passar por `fetch` manual no cliente). Faz sentido a partir do painel admin (criar/editar/apagar projeto, post, produto).
- `hooks/` — se a lógica de autenticação precisar ser reusada em mais de uma página (hoje só `/login` usa).

## 5. Autenticação: por que token Bearer, não sessão/cookie

O projeto testou os dois:

**Sessão/cookie** (como o Laravel Breeze faz por padrão): o navegador loga, recebe um cookie, e o navegador reenvia esse cookie automaticamente em toda chamada seguinte. Funciona bem quando é o **navegador** que faz todas as chamadas.

**Token Bearer**: o login devolve um token (texto), que quem chama guarda e reenvia manualmente no header `Authorization: Bearer <token>` em cada requisição.

O motivo de termos escolhido token: no Next.js, muita busca de dado acontece no **servidor** (Server Components), não no navegador do visitante. Se a autenticação fosse por cookie, o servidor do Next.js precisaria ler manualmente o cookie que chegou na requisição do visitante e reenviá-lo pro Laravel, junto com proteção CSRF — funciona, mas é frágil. Com token, é só guardar o token em algum lugar acessível ao servidor e mandar o header — sem cookie, sem CSRF, sem depender de domínio/porta baterem.

O Laravel Sanctum, por padrão, tenta suportar os dois modelos ao mesmo tempo (é o middleware `EnsureFrontendRequestsAreStateful`) — só que isso pode causar comportamento inesperado quando você só quer token puro (veja a lição do erro 419 abaixo). Removemos esse middleware por completo quando decidimos ir 100% token.

## 6. Padrões de código que valem lembrar

### Fetch não lança erro em 4xx/5xx
`fetch()` só rejeita a Promise em falha de rede de verdade (servidor fora do ar, DNS, CORS bloqueado). Uma resposta `404` ou `401` ainda é uma resposta "bem-sucedida" do ponto de vista do `fetch` — `response.ok` é que diz se o status HTTP foi 200–299. Por isso sempre checamos `if (!response.ok) throw ...` manualmente.

### `unknown` em vez de `any` no `catch`
Em TypeScript, o que cai num `catch (err)` é tipado como `unknown` (não dá pra saber de antemão o que foi lançado — tecnicamente qualquer valor pode ser jogado com `throw`). Por isso sempre checamos `err instanceof Error` antes de acessar `err.message` — é mais seguro que `any`, que desliga a checagem de tipos.

### Componentes controlados (`useState` + `value` + `onChange`)
Um `<input>` "controlado" tem seu valor vindo do state do React (`value={email}`), e cada tecla digitada atualiza o state (`onChange={(e) => setEmail(e.target.value)}`). Isso faz o React ser a "fonte da verdade" do campo, em vez do DOM guardar o valor sozinho — necessário pra validar/usar o valor digitado no `handleSubmit`.

### `e.preventDefault()` em formulários
Sem isso, o comportamento nativo do HTML pra um `<form>` é recarregar a página inteira ao ser submetido — o que apagaria todo o state do React e cancelaria qualquer `fetch` em andamento.

### Variáveis de ambiente no Next.js
Só variáveis com prefixo `NEXT_PUBLIC_` ficam disponíveis no código que roda no navegador (`process.env.NEXT_PUBLIC_API_URL`) — é assim que o framework decide o que pode "vazar" pro cliente. Além disso, essas variáveis são embutidas no bundle **no momento em que o servidor de dev sobe** — editar `.env.local` com o `pnpm dev` já rodando não tem efeito até reiniciar o processo.

---

## 7. Erros reais que já caímos (e o porquê de cada um)

Fica aqui como "sala de troféus" de bugs — vale reler quando um erro parecido aparecer de novo.

### 405 "Método não permitido" ao testar rota protegida sem token válido
Esperávamos 401, veio 405. Causa: sem o header `Accept: application/json`, o Laravel acha que quem está chamando é um navegador pedindo uma página HTML (não uma API), e tenta **redirecionar** pra uma rota de login nomeada. Como essa rota de login (na época, a de sessão) só aceitava `POST`, o redirect virou um `GET` que bateu numa rota que não aceita `GET` → 405.
**Lição**: todo cliente de API deve sempre mandar `Accept: application/json` explicitamente.

### 419 "CSRF token mismatch" numa rota pensada pra token puro
O middleware `EnsureFrontendRequestsAreStateful` (Sanctum) ficava de olho em todas as rotas de `api.php` e ligava sessão+CSRF automaticamente pra qualquer requisição vinda de um domínio listado em `SANCTUM_STATEFUL_DOMAINS` — mesmo numa rota que só queria token. Quando esse valor passou a incluir a porta do Next.js, toda chamada do frontend passou a exigir um CSRF token que nunca pedimos.
**Lição**: se a decisão é ir 100% token, o middleware de "stateful" nem deveria estar registrado — misturar os dois modelos "por via das dúvidas" cria bugs sutis.

### Variável de ambiente não carregada → fetch foi pro lugar errado
`NEXT_PUBLIC_API_URL` não existia ainda quando `pnpm dev` subiu pela primeira vez, então virou `undefined` dentro do componente. `` `${undefined}/api/login` `` virou o texto literal `"undefined/api/login"` — uma URL relativa que o navegador resolveu contra o próprio Next.js (`localhost:3000/undefined/api/login`), não contra o Laravel.
**Lição**: sempre reiniciar o `pnpm dev` depois de criar/editar `.env.local`.

### `response.json()` quebrando numa resposta 204
Um `POST /api/logout` que devolve 204 (sem corpo) faz `response.json()` lançar erro, porque não tem JSON nenhum pra ler. Resolvido tratando o caso `status === 204` separadamente em `lib/api.ts`, devolvendo `null` sem tentar parsear.

### Renomear pasta no Windows deu "Permission denied"
`git mv frontend-next frontend` falhou porque o VSCode (que roda essa própria sessão) mantém um watch persistente na pasta do workspace — o Windows não deixa renomear (nem apagar) um diretório enquanto algum processo tem uma referência aberta nele. Contornado copiando o conteúdo pra uma pasta nova (via `robocopy`, sem `node_modules`/`.next`) e reinstalando as dependências — o `git` reconheceu como rename automaticamente pela similaridade de conteúdo.
**Lição**: no Windows, operações de arquivo em pastas que o editor está observando podem falhar mesmo sem nenhum arquivo "aberto" visivelmente.

### `curl.exe` no PowerShell 5.1 comendo aspas
Passar JSON com aspas duplas pro `curl.exe` a partir do PowerShell (`-d '{"email":"..."}'` ou até com `\"` escapado) pode sair corrompido, porque o PowerShell tem sua própria forma de remontar a linha de comando pra um executável externo, e isso nem sempre preserva aspas internas corretamente.
**Lição**: usar `Invoke-RestMethod` (nativo do PowerShell, trabalha com objetos em vez de montar uma string de comando) em vez de `curl.exe` pra testes manuais de API no Windows.

### Animação de montagem não "fechava" a casa (pipeline Blender → glTF → Three.js)
A casa explodida (`casa_explodida.py` → `casa.glb` → `casa_linhas.html`) tinha uma animação que nunca chegava na forma montada — parava numa versão ainda bem explodida. Causa raiz, em cadeia:
1. O script gera keyframes de posição por objeto (`gerar_animacao()`) e termina deixando a timeline do Blender no frame 1 (`cena.frame_current = FRAME_INICIO`).
2. No Blender, uma curva de animação **antes da primeira keyframe** fica constante no valor dessa primeira keyframe (extrapolação padrão "Constant"). Como quase todo objeto só começa a se mover bem depois do frame 1, no frame 1 **toda peça está avaliando pra sua posição explodida**, não a montada.
3. É essa posição (explodida) que fica gravada como "repouso" do objeto no momento da exportação — não porque o exportador está errado, mas porque é literalmente a pose que a cena tinha no frame em que foi exportada.
4. O `casa_linhas.html` assume que a posição vinda do `.glb` já é a posição **montada**, e soma `explode_vec` em cima pra achar de onde a peça parte (`origem = destino + vetor`). Com o `destino` já errado (explodido), a peça anima de "muito explodido" pra "pouco menos explodido" — nunca chega no formato certo.

**Lição**: numa cena com objetos animados via keyframes, a pose exportada pra qualquer formato (glTF incluso) é sempre **a pose do frame atual da timeline no momento da exportação**, não necessariamente a pose "de repouso" que você imagina — vale sempre conferir em que frame a cena está antes de exportar, especialmente quando (como aqui) a real animação vai ser refeita depois por outro sistema (aqui, JS) e o Blender só precisa fornecer a geometria numa pose de referência específica. Fix aplicado: `GERAR_ANIMACAO = False` antes de exportar o `.glb` do site, deixando cada objeto na posição que `modelar()` atribuiu diretamente, sem nenhuma keyframe por cima.

### Recuperar transparência de uma imagem "com fundo xadrez" salva em JPEG
A logo tinha duas versões: uma com fundo sólido, outra mostrando o padrão xadrez que editores de imagem usam pra indicar transparência — só que essa segunda também estava salva como `.jfif`/JPEG, formato que **não tem canal alpha**. O xadrez em si já são só pixels comuns, não transparência de verdade.

Em vez de tentar recorte por IA (que erra a borda em arte com bordas suaves/aquareladas, deixando halo), reconstruí o alpha original a partir do próprio xadrez: como o padrão usa só duas cores conhecidas (branco e cinza-claro), qualquer pixel próximo dessas duas cores é fundo (alpha baixo); qualquer pixel bem diferente delas é arte de verdade (alpha alto). Isso reaproveita o corte que já existia na imagem original (antes de virar xadrez), em vez de adivinhar um novo. A compressão JPEG borra os quadradinhos do xadrez nas bordas, então sobra ruído fraco — resolvido com um filtro de mediana + abertura morfológica (erosão seguida de dilatação, remove pontinhos isolados sem encolher a silhueta principal).
**Lição**: quando existe uma versão "com fundo de transparência visível" de uma imagem, mesmo que salva num formato sem alpha, ela carrega mais informação de recorte do que tentar segmentar a versão de fundo sólido do zero — vale reconstruir o alpha a partir do padrão, não descartar esse arquivo.

Tecnicamente essa versão saiu com borda mais fiel, mas visualmente o resultado do recorte por IA (`rembg`/u2net, mais suave, com leve halo) acabou sendo o preferido de verdade e é o que ficou em `frontend/public/images/logo-domo-512.png`. Fica registrado como técnica válida pro caso de precisar de um recorte mais preciso de novo no futuro — a escolha final entre as duas é estética, não teve resposta "certa".

### Custom Properties precisam estar marcadas na exportação glTF
`explode_vec`/`explode_ordem` são Custom Properties do Blender, e só viram `extras` no glTF (e, por consequência, `userData` no Three.js) se a opção **"Custom Properties"** estiver marcada na aba Include da exportação. Sem isso, o carregamento do modelo não dá nenhum erro — ele só fica estático, porque o código do viewer ignora silenciosamente (`if (!v) return;`) qualquer objeto sem esse dado.

### Textura carregada "estourada"/lavada — falta declarar o espaço de cor
Uma imagem (a logo, como `THREE.Sprite`) apareceu com brilho/contraste errado, parecendo "lavada". Não tinha relação com iluminação (a cena não usa nenhuma luz — os materiais são todos do tipo que ignora luz, de propósito, pelo estilo de linha). A causa: desde a versão 152 do Three.js, toda textura de cor carregada via `TextureLoader` precisa declarar `textura.colorSpace = THREE.SRGBColorSpace` explicitamente — sem isso, o Three.js trata os valores da imagem (que estão em sRGB, o padrão de qualquer imagem comum) como se já fossem lineares, o que estraga o contraste.
**Lição**: sempre que uma textura carregada aparecer com cor "errada" (lavada, contraste estranho), suspeitar primeiro do `colorSpace` da textura antes de mexer em iluminação ou no próprio arquivo de imagem.

### Migração pra React Three Fiber + drei: uma leva de bugs de integração
A "casa explodida" saiu do protótipo standalone (`casa_linhas.html`, Three.js puro) e virou um componente de verdade (`HomeAnimation.tsx`) usando React Three Fiber + drei, escolhido de propósito porque é uma ferramenta que o usuário quer aprender (não por ser a opção mais simples — ver decisão de arquitetura registrada em `docs/contexto-do-projeto.md`). Essa migração trouxe uma sequência de bugs de integração, todos com causa não óbvia:

**Import nomeado vs. `export default`** — `import { HomeAnimation } from "..."` com chaves só funciona se o arquivo tiver `export function HomeAnimation() {}` (nomeado). Como `HomeAnimation.tsx` usa `export default function HomeAnimation() {}`, o import certo é sem chaves: `import HomeAnimation from "..."`. Com chaves, o valor importado vem `undefined` e o React reclama de "elemento inválido" — sem dizer que o problema é o import.

**`<Canvas>` sem altura definida = canvas de 0px** — o `<Canvas>` do R3F preenche 100% do elemento pai. Um `<div>` com `flex flex-col items-center justify-center` (sem altura fixa) encolhe pro tamanho do conteúdo — ou seja, "100% de um pai sem altura" também vira zero. Precisa de um pai com altura explícita (`h-screen`, por exemplo) — e isso importa ainda mais com `<ScrollControls>` por perto, porque o tamanho da área de scroll dele é calculado como múltiplo da altura desse contêiner.

**Uma peça específica nunca terminava de animar** — ao converter a animação de "segundos" pra "fração de 0 a 1 do scroll", cada peça acabou com uma duração *diferente*, calculada como `1 - inicioDaPeca`. Pra exatamente uma peça (a que começa por último), isso dá `1 - 1 = 0` — divisão por zero, escondida atrás de um `|| 1` de proteção que mascarou o sintoma real (o `k` dela nunca chegava em `1`). Fix: toda peça precisa da **mesma** duração fixa (uma fração constante do scroll), só o início de cada uma muda — exatamente como era no protótipo por tempo (`DURACAO_PECA` era igual pra todo mundo lá).
**Lição**: ao portar uma animação de "tempo absoluto" pra "progresso normalizado de 0 a 1", cuidado pra não acidentalmente fazer a *duração* de cada elemento depender da posição dele na fila — só o *início* deveria variar.

**`pointer-events-none` travou o scroll de vez** — pra fazer o cross-fade entre a cena 3D e a próxima seção, a camada que fica invisível recebia `pointer-events-none`. Isso também bloqueia eventos de **wheel/scroll**, não só clique — então assim que a transição acontecia uma vez, a camada do `<Canvas>` (onde vive o `<ScrollControls>`) parava de receber scroll pra sempre, e não tinha como voltar. Fix: a camada do `Canvas` nunca leva `pointer-events-none`; só a camada de conteúdo (que por enquanto é só texto) fica sempre com `pointer-events-none`, deixando o scroll vazar pra baixo o tempo todo.
**Lição**: `pointer-events-none` desliga *todos* os eventos de ponteiro, não só clique — inclusive os que alimentam bibliotecas de scroll customizado.

**`OrbitControls` e `ScrollControls` brigando pela roda do mouse** — ao liberar o `OrbitControls` no fim da animação, ele passou a capturar a roda do mouse pra zoom (comportamento padrão dele), roubando o evento que o `ScrollControls` precisava pra voltar a animação. Sintoma confuso: parecia que "o scroll não voltava", mas na real a câmera é que estava se aproximando demais do modelo a cada tentativa. Fix: `enableZoom={false}` no `OrbitControls`, mantendo giro (arraste) e pan livres, sem competir pela roda.
**Lição**: ao combinar duas bibliotecas que escutam o mesmo tipo de evento (aqui, a roda do mouse) pra coisas diferentes, uma delas vai "vencer" e roubar o gesto da outra — quase sempre sem erro nenhum no console.

**Sem janela de tempo pra olhar o resultado antes da transição** — a câmera destravava e a troca de seção dispara no mesmo ponto exato do scroll (`0.999`), então não sobrava espaço pra girar e admirar a casa montada antes dela sumir. Fix: separar em três fases de scroll (casa monta → logo entra → zona livre), destravando a câmera bem antes do ponto que de fato dispara a troca de seção — dando um intervalo real de scroll só pra explorar.

**Mudanças da câmera livre "grudavam" depois de travar de novo** — depois que a câmera é liberada (zona livre) e o usuário gira/mexe nela, voltar pra zona travada (scroll pra cima) não devolvia a câmera pro enquadramento original — ela só "parava onde estava". Causa: nada no código resetava a posição/alvo da câmera ao sair da zona livre. Fix: guardar se a câmera "estava livre" no frame anterior, e no exato frame em que ela deixa de estar (acabou de travar de novo), copiar a posição/alvo originais de volta e chamar `controls.update()`.
**Lição**: uma câmera "livre" (`OrbitControls` habilitado) é estado mutável que persiste sozinho — se uma interação deveria ser temporária, precisa de código explícito pra desfazer, não desliga sozinha ao trocar o `enabled` de volta pra `false`.

---

## 8. Como continuar usando este arquivo

Sempre que fizermos algo novo que valha a pena guardar como aprendizado — um padrão de código, uma decisão de arquitetura com trade-off relevante, ou um bug real com causa não óbvia — isso entra aqui, na seção correspondente (ou numa nova seção, se for um tópico novo). O [`contexto-do-projeto.md`](./contexto-do-projeto.md) continua sendo o lugar do "o que existe e por quê"; este arquivo é o "o que aprendemos e como pensar sobre isso".

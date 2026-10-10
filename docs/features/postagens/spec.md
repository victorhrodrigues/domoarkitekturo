# Postagens (blog) — Spec

## Contexto

A arquiteta (mãe do usuário) precisa escrever postagens de blog pelo painel admin e ter essas postagens servidas no site público. Os temas misturam as frentes do negócio (arquitetura, café, loja), e o blog precisa ser bem indexado pelo Google e gerar bom preview ao ser compartilhado (motivo da escolha do Next.js, ver [`contexto-do-projeto.md`](../../contexto-do-projeto.md)).

## Decisões fechadas

- **Conteúdo** em HTML sanitizado, editado no painel com **TipTap** (editor rich-text). O site público só renderiza o HTML salvo; o editor existe apenas no admin.
- **Status**: `draft`, `published` e `archived` (enum PHP `PostStatus`, coluna `string`). **Arquivada** = postagem concluída que não deve aparecer no site, mas também não deve ser apagada.
- **Agendamento derivado**: não existe status `scheduled`. "Agendada" é uma postagem `published` com `published_at` no futuro.
- **Publicar sem agendar sai na hora**: se o status é `published` e não há `published_at`, o model grava o momento atual. Só não aparece de imediato se a data informada for futura.
- **Sem status "excluída"**: apagar remove a linha do banco (hard delete).
- **Categorias n:n**: uma postagem pode ter várias categorias e aparece no filtro de cada uma. Sem tags por enquanto (podem ser adicionadas depois sem migrar nada).
- **Título único** entre todas as postagens (qualquer status): evita canibalização de palavras-chave no Google, confusão para o leitor na busca e nas categorias, e ambiguidade para quem linka o conteúdo de fora.
- **`slug`** na postagem (e na categoria), para URLs legíveis e SEO.
- **`excerpt`** (resumo) e **autor** (`user_id`) incluídos.
- **Capa**: o campo guarda o **caminho** (texto) devolvido por um endpoint de upload separado, e não o arquivo no mesmo request do post (requests continuam JSON).
- IDs em **UUID**, como o restante do projeto.

## Modelo de dados

**`posts`**
| Campo | Tipo | Observação |
|---|---|---|
| `id` | uuid, pk | |
| `user_id` | foreignUuid, **nullable** | autor; `nullOnDelete` (apagar o usuário não apaga as postagens). Vem do usuário autenticado, nunca do corpo do request |
| `title` | string, **único** | |
| `slug` | string, único | gerado de `title`, ver regras |
| `excerpt` | string (máx. 300), nullable | resumo para cards da listagem e meta description |
| `content` | longText, nullable | HTML sanitizado |
| `cover_image` | string, nullable | **caminho relativo** no disco `public` (ex: `posts/covers/abc.jpg`), URL montada na resposta da API |
| `status` | string, default `draft` | enum PHP `PostStatus` (`draft`, `published`, `archived`), cast no model |
| `published_at` | timestamp, nullable | nulo em rascunho; preenchido automaticamente ao publicar sem data |
| `created_at`/`updated_at` | timestamps | |

`excerpt` e `content` são nullable no banco porque o rascunho pode estar incompleto; a obrigatoriedade para publicar é validada nos Form Requests. Índice em `(status, published_at)` (é a consulta do site público).

**`categories`**: `id` (uuid), `name` (único), `slug` (único), timestamps.

**`category_post`** (pivô): `post_id` e `category_id` (`foreignUuid`, ambos `cascadeOnDelete`), chave primária composta, sem `id` nem timestamps. Apagar uma categoria remove só as linhas da pivô; as postagens continuam existindo.

## Regras

- **Slug da postagem**: gerado **uma vez**, na criação, a partir do título (`Str::slug`, que remove acentos). Em colisão, acrescenta sufixo (`-2`, `-3`...). **Não é regerado quando o título é editado**, para não quebrar links já compartilhados ou indexados. `slug` é `prohibited` nos requests.
- **Slug de categoria**: mesma regra (gerado do `name` no evento `creating`, uma vez, com sufixo `-2`, `-3`... em colisão). O slug é **travado**: `slug` é proibido (`prohibited`) tanto no store quanto no update, então nunca é informado nem alterado por requisição. `name` único não garante `slug` único, porque o nome pode ser editado e o slug antigo fica preso (renomear "Iluminação" para "Luz" mantém `iluminacao`, e uma nova "Iluminação" vira `iluminacao-2`). O `name` precisa conter ao menos uma letra ou número, senão o slug sairia vazio (validado no Form Request).
- **Visibilidade pública**: `status = published AND published_at <= agora`, encapsulada no scope `Post::published()`. Rascunho, arquivada e agendada (data futura) ficam fora. O painel exibe o rótulo "Agendada" quando `status = published` e `published_at` está no futuro.
- **Publicar sem data**: o evento `saving` do model grava `published_at = now()` quando o status é `published` e a data está nula (vale na criação e na edição). Arquivar mantém a data; republicar mantém a data original.
- **Rascunho** pode estar incompleto. **Publicar exige**: `title`, `excerpt`, `content` e pelo menos uma categoria (senão a postagem não é achada por nenhum filtro). Validado nos Form Requests (`required_if` no store, `Rule::requiredIf` no update).
- **Update é tratado como formulário completo.** A regra de "publicar exige..." considera o status enviado e, se não vier, o status **atual** da postagem (assim não dá para apagar o conteúdo de uma postagem já publicada). Consequência: um `PATCH` parcial numa postagem publicada que não reenvie `content`/`excerpt`/`categories` é recusado; o painel deve reenviar todos os campos. Mudar só o status para `archived` ou `draft` funciona sem reenviar nada.
- **Ao apagar uma postagem**, a capa é apagada do disco (evento `deleted` do model, em `Storage::disk('public')`, com falha registrada no log em vez de engolida). Imagens dentro do `content` ficam órfãs no disco por enquanto.
- **Ao trocar ou remover a capa**, o arquivo anterior também precisa ser apagado (a implementar no controller ou num evento `updated`).
- **`categories` no update**: ausente = não mexe nas categorias; presente (inclusive `[]`) = `sync()`.
- **Sanitização**: o backend sanitiza o HTML antes de gravar (ex: `mews/purifier`), com lista de tags permitidas restrita: parágrafos, títulos `h2`/`h3`, `strong`, `em`, `ul`/`ol`/`li`, `img` (`src`, `alt`), `br` e o alinhamento de texto que o TipTap gera. Um editor vazio devolve algo como `<p></p>`, então a validação de "tem conteúdo" deve olhar o texto depois de remover as tags.
- **"Aumentar o texto" = títulos (`h2`/`h3`)**, não tamanho livre de fonte: a tipografia fica sob controle do CSS do site.

## API

> O backend não usa o prefixo `/api` (`apiPrefix: ''` em `bootstrap/app.php`), então as URLs abaixo são direto na raiz. Na implementação das categorias, as rotas de admin usam o mesmo caminho do recurso (`POST /categories`, `PUT /categories/{category}`...), diferenciadas das públicas pelos middlewares, sem prefixo `/admin`.

**Pública** (sem autenticação):
- `GET /posts` — só publicadas (scope `published()`), paginada, com filtro opcional por categoria (`?categoria=<slug>`).
- `GET /posts/{slug}` — uma postagem publicada.
- `GET /categories` — categorias para o filtro.

**Admin** (`auth:sanctum` + `admin`):
- CRUD de `posts` (enxerga todos os status, sem o scope) e de `categories`.
- `POST /uploads` — recebe uma imagem, valida tipo e tamanho, salva no disco `public` e devolve `path` e `url`. Usado pelo editor (imagens do `content`) e pela capa (a postagem guarda só o `path`).
- Conflito a resolver ao construir os públicos: `GET /posts` do admin (todos os status) e o público (só publicadas) disputam a mesma URL, então um deles precisa de método ou caminho próprio.

## Frontend

- **Público**: `/blog` (listagem paginada com filtro por categoria) e `/blog/[slug]` (Server Component com `generateMetadata`: título, `excerpt` e capa para SEO e preview de compartilhamento).
- **Admin**: listagem de postagens, formulário de criar/editar com TipTap (negrito, itálico, listas ordenada e não ordenada, títulos, alinhamento, imagem), CRUD de categorias. O formulário de edição reenvia todos os campos.
- **Pré-requisito do admin**: persistência do token e proteção da rota `/admin` (pendência de [`papeis-de-usuario`](../papeis-de-usuario/task.md)).

## Fora de escopo (por enquanto)

Tags, `SoftDeletes`/lixeira, comentários, limpeza de imagens órfãs do `content`, agendamento via cron/Scheduler, edição manual do slug, endpoint próprio para trocar só o status ("publicar com um clique" na listagem).

## Pontos em aberto

- Capa obrigatória para publicar? (recomendável pelo preview de compartilhamento, mas não definido)
- Redimensionar/comprimir imagens no upload (fotos de celular chegam a vários MB).

# Postagens (blog) — Spec

## Contexto

A arquiteta (mãe do usuário) precisa escrever postagens de blog pelo painel admin e ter essas postagens servidas no site público. Os temas misturam as frentes do negócio (arquitetura, café, loja), e o blog precisa ser bem indexado pelo Google e gerar bom preview ao ser compartilhado (motivo da escolha do Next.js, ver [`contexto-do-projeto.md`](../../contexto-do-projeto.md)).

## Decisões fechadas

- **Conteúdo** em HTML sanitizado, editado no painel com **TipTap** (editor rich-text). O site público só renderiza o HTML salvo; o editor existe apenas no admin.
- **Agendamento derivado**: não existe status `scheduled`. Status guarda só `draft`/`published`; "agendada" é uma postagem `published` com `published_at` no futuro.
- **Sem status "excluída"**: apagar remove a linha do banco (hard delete).
- **Categorias n:n**: uma postagem pode ter várias categorias e aparece no filtro de cada uma. Sem tags por enquanto (podem ser adicionadas depois sem migrar nada).
- **`slug`** na postagem (e na categoria), para URLs legíveis e SEO.
- **`excerpt`** (resumo) e **autor** (`user_id`) incluídos.
- IDs em **UUID**, como o restante do projeto.

## Modelo de dados

**`posts`**
| Campo | Tipo | Observação |
|---|---|---|
| `id` | uuid, pk | |
| `user_id` | foreignUuid, nullable | autor; `nullOnDelete` (apagar o usuário não apaga as postagens) |
| `title` | string | |
| `slug` | string, único | gerado de `title`, ver regras |
| `excerpt` | string (máx. 300) | resumo para cards da listagem e meta description |
| `content` | longText | HTML sanitizado |
| `cover_image` | string, nullable | **caminho relativo** no disco `public` (ex: `posts/covers/abc.jpg`), URL montada na resposta da API |
| `status` | string, default `draft` | enum PHP `PostStatus` (`draft`, `published`), cast no model |
| `published_at` | timestamp, nullable | nulo em rascunho |
| `created_at`/`updated_at` | timestamps | |

Índice em `(status, published_at)` (é a consulta do site público).

**`categories`**: `id` (uuid), `name` (único), `slug` (único), timestamps.

**`category_post`** (pivô): `post_id` e `category_id` (`foreignUuid`, ambos `cascadeOnDelete`), chave primária composta. Apagar uma categoria remove só as linhas da pivô; as postagens continuam existindo.

## Regras

- **Slug**: gerado **uma vez**, na criação, a partir do título (`Str::slug`, que remove acentos). Em colisão, acrescenta sufixo (`-2`, `-3`...). **Não é regerado quando o título é editado**, para não quebrar links já compartilhados ou indexados. (Edição manual do slug: decisão futura.)
- **Slug de categoria**: mesma regra (gerado do `name` no evento `creating`, uma vez, com sufixo `-2`, `-3`... em colisão, já que `name` único não garante `slug` único: "Iluminação" e "Iluminacao" geram o mesmo). O `name` precisa conter ao menos uma letra ou número, senão o slug sairia vazio (validado no Form Request).
- **Visibilidade pública**: `status = published AND published_at <= agora`. O painel exibe o rótulo "Agendada" quando `status = published` e `published_at` está no futuro.
- **Ao publicar sem data informada**, `published_at` recebe o momento atual.
- **Rascunho** pode estar incompleto. **Publicar exige** (proposta, confirmar): `title`, `content`, `excerpt` e pelo menos uma categoria (senão a postagem não é achada por nenhum filtro).
- **Ao apagar uma postagem**, a capa é apagada do disco (evento `deleted` do model, com guard clause para `cover_image` nulo, mesmo padrão do `User`). Imagens dentro do `content` ficam órfãs no disco por enquanto.
- **Sanitização**: o backend sanitiza o HTML antes de gravar (ex: `mews/purifier`), com lista de tags permitidas restrita: parágrafos, títulos `h2`/`h3`, `strong`, `em`, `ul`/`ol`/`li`, `img` (`src`, `alt`), `br` e o alinhamento de texto que o TipTap gera.
- **"Aumentar o texto" = títulos (`h2`/`h3`)**, não tamanho livre de fonte: a tipografia fica sob controle do CSS do site.

## API

**Pública** (sem autenticação):
- `GET /api/posts` — só publicadas, paginada, com filtro opcional por categoria (`?categoria=<slug>`).
- `GET /api/posts/{slug}` — uma postagem publicada.
- `GET /api/categories` — categorias para o filtro.

**Admin** (`auth:sanctum` + `admin`, sob `/api/admin`):
- CRUD de `posts` (enxerga todos os status) e de `categories`.
- `POST /api/admin/uploads` — recebe uma imagem, valida tipo e tamanho, salva no disco `public` e devolve `path` e `url`. Usado pelo editor (imagens do `content`) e pela capa (a postagem guarda só o `path`). (Proposta: um único endpoint de upload para os dois usos.)

## Frontend

- **Público**: `/blog` (listagem paginada com filtro por categoria) e `/blog/[slug]` (Server Component com `generateMetadata`: título, `excerpt` e capa para SEO e preview de compartilhamento).
- **Admin**: listagem de postagens, formulário de criar/editar com TipTap (negrito, itálico, listas ordenada e não ordenada, títulos, alinhamento, imagem), CRUD de categorias.
- **Pré-requisito do admin**: persistência do token e proteção da rota `/admin` (pendência de [`papeis-de-usuario`](../papeis-de-usuario/task.md)).

## Fora de escopo (por enquanto)

Tags, `SoftDeletes`/lixeira, comentários, limpeza de imagens órfãs do `content`, agendamento via cron/Scheduler, edição manual do slug.

## Pontos em aberto

- Capa obrigatória para publicar? (recomendável pelo preview de compartilhamento, mas não definido)
- Redimensionar/comprimir imagens no upload (fotos de celular chegam a vários MB).

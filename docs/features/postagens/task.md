# Postagens (blog) — Task

> Status: em andamento (começou em 2026-10-08). Detalhes de campos e regras em [`spec.md`](./spec.md).
>
> **Onde parou (2026-10-10)**: banco, models, Form Requests, `store` e `update` de `Post` prontos e testados no Postman. **Próximo passo: `destroy`** (`$id` + `findOrFail`, 204), depois `index`/`show` do admin, sanitização do HTML, `POST /uploads`, API Resource e as rotas públicas.
>
> **Para o que falta no controller, lembrar**: `create()`/`edit()` do esqueleto ainda precisam ser apagados; `show` e `destroy` ainda têm `Post $post` (trocar por `$id` + `findOrFail`); o admin vê todos os status (sem o scope `published()`); `GET /posts` do admin e o público disputam a mesma URL; a sanitização do `content` entra no `store` e no `update` (há um `TODO` no `store`).

## Parte 1 — Backend: banco e models

- [x] Migration `categories` (uuid, `name` único, `slug` único, timestamps)
- [x] Migration `posts`: `user_id` nullable com `nullOnDelete`, `title` e `slug` únicos, `excerpt` e `content` nullable (rascunho incompleto), `status` como `string` default `draft`, índice `(status, published_at)`
- [x] Migration pivô `category_post`: `foreignUuid` nos dois lados com `constrained()` e `cascadeOnDelete()`, chave primária composta, sem `id`/timestamps — todas as migrations já rodaram (`migrate:status`)
- [x] Enum `App\Enums\PostStatus` (`Published`, `Draft`, `Archived`)
- [x] Model `Category` (`HasUuids`, `$fillable`, slug no `creating` com sufixo `-2`, `-3`..., trava do slug no `updating`, relação `posts()`)
- [x] Slug de categoria testado: `Iluminação` → `iluminacao`; depois de renomear a antiga para `Luz` (slug mantido), nova `Iluminação` → `iluminacao-2`
- [x] Model `Post`: `HasUuids`, `$fillable`, `$attributes` com status `Draft`, casts (`status` → enum, `published_at` → datetime), relações `categories()` e `user()`, scope `published()`
- [x] Eventos do `Post`: slug no `creating`; `published_at = now()` no `saving` quando publicada sem data; `deleted` apagando a capa em `Storage::disk('public')` com falha registrada em `Log::warning`
- [x] Relação testada no tinker: `sync()`, os dois lados (`$post->categories`, `$category->posts`) e cascade da pivô ao apagar o post (0 linhas restantes)
- [x] Scope `published()` testado: só a publicada com data passada e a publicada sem data (que agora ganha `now()`) aparecem; futura, rascunho e arquivada ficam de fora
- [x] Trava do slug no `Post` (gancho `updating` restaurando o valor original, como na `Category`) e `Category::posts()` com tipo de retorno `BelongsToMany`
- [ ] `php artisan storage:link` (já existe um `public/storage` apontando para `storage/app/public`; conferir se abre `http://localhost:8000/storage/<arquivo>` e, se sim, marcar como feito)

## Parte 2 — Backend: admin

- [x] `StoreCategoryRequest` / `UpdateCategoryRequest`: `authorize()` em `true`; `name` com `bail`, `required`/`sometimes`, `string`, `max:255`, `unique` (no update com `Rule::unique(...)->ignore($this->route('category'))`) e closure que rejeita nome que gere slug vazio; `slug` com `prohibited` nos dois; mensagens em português
- [x] `CategoryController` (um só, em `app/Http/Controllers/`): `index`, `store` (201), `show`, `update`, `destroy` (204), sem `create()`/`edit()`. `index` e `show` são a versão inicial, para evoluir depois (filtro por nome e paginação no admin, lista pública completa em método próprio)
- [x] Rotas de categorias: `index` e `show` públicas; `store`/`update`/`destroy` dentro de `Route::middleware(['auth:sanctum', 'admin'])`
- [x] Testes de categorias no Postman: store, duplicado, `???`, `slug` enviado, update, destroy, 401 sem token, 403 como `cliente`, `index`/`show` públicos
- [x] Limpar sobras do `-a` de categoria: `CategoryPolicy` apagado. `CategorySeeder`/`CategoryFactory` seguem no projeto, opcionais (o `DatabaseSeeder` usa `WithoutModelEvents`)
- [x] `StorePostRequest`: `title` (`bail`, `unique`, closure de slug vazio), `status` (`sometimes` + `Rule::enum`), `excerpt`/`content`/`categories` com `required_if:status,published`, `published_at` (`nullable|date`), `cover_image` (`nullable|string|max:255`), `categories.*` (`uuid` + `exists`), `slug` proibido, mensagens em português. Validado com 14 cenários
- [x] `UpdatePostRequest`: mesmas regras com `sometimes`/`ignore` no título e `Rule::requiredIf` baseado no status enviado ou, se ausente, no status atual da postagem (método `estaPublicando()`). Validado com 16 cenários
- [x] Remover a mensagem sobrando `cover_image.image` do `StorePostRequest` (a regra `image` não existe mais)
- [ ] `PostController` (CRUD) usando `sync()` para as categorias (ver lembretes no topo)
  - [x] `store` com `Arr::pull` das categorias, `user_id` de `$request->user()->id` e `create` + `sync` dentro de `DB::transaction`; rota `POST /posts` no grupo admin. Testado no Postman (JSON): rascunho, publicada, agendada, sem categoria (422), `slug` enviado (422), `user_id` forjado ignorado, `???` (422), título duplicado (422), sem token (401), `cliente` (403)
  - [ ] Controller usa `$id` + `findOrFail` (padrão do `CategoryController`): feito no `update`; `show` e `destroy` ainda com `Post $post`; apagar `create()`/`edit()` do esqueleto
  - [x] `UpdatePostRequest::estaPublicando()` busca o post por `Post::find()` quando `route('post')` é string, com cache numa propriedade. Testado: PATCH numa publicada sem reenviar `content`/`excerpt`/`categories` dá 422
  - [x] `update`: `sync` só se `categories` veio (`array_key_exists` antes do `Arr::pull`), `update` + `sync` em `DB::transaction`, capa antiga apagada **depois** da transação só se mudou. Testado no Postman (publicada completa, rascunho, arquivada, erros, capa, 401/403); regra "update é formulário completo" mantida (PATCH só com `title` numa publicada dá 422 de propósito)
  - [ ] `index`, `show` e `destroy` (`destroy` com `$id` + `findOrFail`, 204; a capa já é apagada pelo evento `deleted`)
- [ ] Sanitização do HTML do `content` antes de gravar (instalar lib e configurar tags permitidas; validar que sobra texto depois de remover as tags)
- [ ] `POST /uploads` (valida tipo e tamanho, salva em `public`, devolve `path` e `url`)
- [ ] Rotas de admin de postagens dentro de `Route::middleware(['auth:sanctum', 'admin'])`: `Route::apiResource('posts', PostController::class)->except('index', 'show')` já cobre `store`, `update` e `destroy`; faltam `index`/`show` do admin (ver conflito com o `GET /posts` público)
- [ ] API Resources (monta a URL da capa a partir do caminho relativo)
- [ ] Testar tudo no Postman (admin com sucesso; cliente com 403 e sem token com 401)

## Parte 3 — Backend: público

- [ ] `GET /posts` (só publicadas via `Post::published()`, paginada, filtro `?categoria=slug`)
- [ ] `GET /posts/{slug}`
- [x] `GET /categories` (já existe como `index` público do `CategoryController`)
- [ ] Testar no Postman: rascunho, arquivada e postagem agendada não aparecem nas rotas públicas

## Parte 4 — Frontend

- [x] Chamadas de `services/auth.ts` ajustadas ao backend sem prefixo `/api` (`/login`, `/user`, `/logout`), login conferido pela página `/login`
- [ ] **Pré-requisitos** (de `papeis-de-usuario`): persistência do token e proteção da rota `/admin`
- [ ] `types/` e `services/` para posts e categorias
- [ ] Páginas públicas: `/blog` (listagem + filtro por categoria) e `/blog/[slug]` com `generateMetadata`
- [ ] Admin: listagem de postagens
- [ ] Admin: formulário criar/editar com TipTap (negrito, itálico, listas, títulos, alinhamento, imagem via upload), reenviando todos os campos no update
- [ ] Admin: CRUD de categorias
- [ ] Rótulo "Agendada" quando `published_at` é futuro
- [ ] Confirmação antes de apagar uma postagem

## Pendências / decisões em aberto

- [ ] Refatorar: o `try/catch` que apaga a capa existe no evento `deleted` do `Post` e no `update` do controller. Extrair para um método único (ex.: `Post::apagarArquivo(?string $path)`) ou mover a troca de capa para um evento `updated`
- [ ] Capa obrigatória para publicar?
- [ ] Redimensionar/comprimir imagens no upload
- [ ] Fuso horário: `published_at` enviado como `2030-01-01 10:00:00` é tratado como UTC. Definir `APP_TIMEZONE` ou fazer o frontend enviar ISO 8601 com fuso, para a arquiteta agendar no horário de Brasília
- [ ] O JSON do `store` omite campos `null` (`excerpt`, `content`, `cover_image`, `published_at` no rascunho); o API Resource deve padronizar as chaves
- [ ] Em produção: `APP_DEBUG=false`. Com `true`, respostas de erro (ex.: o 403) incluem `file`, `line` e `trace` com caminhos do servidor.

# Postagens (blog) — Task

> Status: em andamento (começou pelas categorias, 2026-10-08). Detalhes de campos e regras em [`spec.md`](./spec.md).
>
> **Onde parou**: migration e model de `Category` prontos (inclui geração do slug com sufixo em colisão). Os arquivos gerados pelo `make:model Category -a` (controller, Form Requests, policy, factory, seeder) ainda são **stubs vazios**. Próximo passo: Form Requests de categoria.

## Parte 1 — Backend: banco e models

- [x] Migration `categories` (uuid, `name` único, `slug` único, timestamps) — escrita; confirmar que já rodou com `php artisan migrate`
- [ ] Migration `posts` (campos da spec, `user_id` com `nullOnDelete`, índice `(status, published_at)`)
- [ ] Migration pivô `category_post` (`foreignUuid` nos dois lados com `cascadeOnDelete`, chave primária composta) — criar **depois** de `categories` e `posts` (o Laravel roda as migrations pela ordem do nome do arquivo)
- [ ] Enum `App\Enums\PostStatus` (`Draft`, `Published`)
- [x] Model `Category` (`HasUuids`, `$fillable`, slug gerado no evento `creating` com sufixo `-2`, `-3`... em colisão) — falta só a relação `belongsToMany` com posts, quando o model `Post` existir
- [ ] Testar o slug no `php artisan tinker` (`Iluminação` → `iluminacao`; `Iluminacao` → `iluminacao-2`)
- [ ] Model `Post` (`HasUuids`, cast de `status` e `published_at`, `belongsToMany` categorias, `belongsTo` autor)
- [ ] Geração do slug na criação (`Str::slug` + sufixo em colisão), sem regerar ao editar o título
- [ ] Evento `deleted` do `Post` apagando `cover_image` do disco (guard clause para nulo)
- [ ] `php artisan storage:link`

## Parte 2 — Backend: admin

- [ ] `StoreCategoryRequest` / `UpdateCategoryRequest`: trocar `authorize()` de `false` para `true`; regras de `name` (obrigatório, string, `max:255`, único; no update, `Rule::unique(...)->ignore(...)` para ignorar a própria categoria); regra impedindo nome que gere slug vazio (só símbolos)
- [ ] `CategoryController` (um só, em `app/Http/Controllers/`: `index` em rota pública; `store`/`update`/`destroy` no grupo `auth:sanctum` + `admin`): apagar `create()` e `edit()`, devolver JSON (`201` no store, `204` no destroy) — **fazer primeiro**: é a entidade mais simples e postagem publicada exige categoria
- [ ] Limpar sobras do `-a`: apagar `CategoryPolicy` (autorização é pelo middleware `admin`); `CategorySeeder`/`CategoryFactory` opcionais (o `DatabaseSeeder` usa `WithoutModelEvents`, então o seeder precisaria informar o slug manualmente)
- [ ] `StorePostRequest` / `UpdatePostRequest` (regras da spec, incluindo exigências para publicar)
- [ ] Sanitização do HTML do `content` antes de gravar (instalar lib e configurar tags permitidas)
- [ ] `Admin\PostController` (CRUD) usando `sync()` para as categorias
- [ ] `POST /api/admin/uploads` (valida tipo e tamanho, salva em `public`, devolve `path` e `url`)
- [ ] Rotas sob `/api/admin` dentro de `Route::middleware(['auth:sanctum', 'admin'])`
- [ ] API Resources (monta a URL da capa a partir do caminho relativo)
- [ ] Testar tudo no Postman (admin com sucesso; cliente e sem token com 403/401)

## Parte 3 — Backend: público

- [ ] `GET /api/posts` (só publicadas, `published_at <= agora`, paginada, filtro `?categoria=slug`)
- [ ] `GET /api/posts/{slug}`
- [ ] `GET /api/categories`
- [ ] Testar no Postman: rascunho e postagem agendada não aparecem nas rotas públicas

## Parte 4 — Frontend

- [ ] **Pré-requisitos** (de `papeis-de-usuario`): persistência do token e proteção da rota `/admin`
- [ ] `types/` e `services/` para posts e categorias
- [ ] Páginas públicas: `/blog` (listagem + filtro por categoria) e `/blog/[slug]` com `generateMetadata`
- [ ] Admin: listagem de postagens
- [ ] Admin: formulário criar/editar com TipTap (negrito, itálico, listas, títulos, alinhamento, imagem via upload)
- [ ] Admin: CRUD de categorias
- [ ] Rótulo "Agendada" quando `published_at` é futuro
- [ ] Confirmação antes de apagar uma postagem

## Pendências / decisões em aberto

- [ ] Capa obrigatória para publicar?
- [ ] Redimensionar/comprimir imagens no upload

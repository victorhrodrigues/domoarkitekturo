# Postagens (blog) — Task

> Status: em andamento (começou pelas categorias, 2026-10-08). Detalhes de campos e regras em [`spec.md`](./spec.md).
>
> **Onde parou**: o CRUD de **categorias** está pronto no backend e testado no Postman (2026-10-09). Próximo passo: migration e model de `posts`.
>
> As chamadas de `frontend/src/services/auth.ts` foram ajustadas para `/login`, `/user` e `/logout` (o prefixo `/api` saiu do backend com `apiPrefix: ''`) e o login pela página `/login` do front foi conferido funcionando. O `CategoryPolicy` sem uso já foi apagado.

## Parte 1 — Backend: banco e models

- [x] Migration `categories` (uuid, `name` único, `slug` único, timestamps)
- [ ] Migration `posts` (campos da spec, `user_id` com `nullOnDelete`, índice `(status, published_at)`)
- [ ] Migration pivô `category_post` (`foreignUuid` nos dois lados com `cascadeOnDelete`, chave primária composta) — criar **depois** de `categories` e `posts` (o Laravel roda as migrations pela ordem do nome do arquivo)
- [ ] Enum `App\Enums\PostStatus` (`Draft`, `Published`)
- [x] Model `Category` (`HasUuids`, `$fillable`, slug gerado no evento `creating` com sufixo `-2`, `-3`... em colisão) — falta só a relação `belongsToMany` com posts, quando o model `Post` existir
- [x] Slug de categoria testado: `Iluminação` → `iluminacao`; depois de renomear a antiga para `Luz` (slug mantido), nova `Iluminação` → `iluminacao-2`
- [ ] Model `Post` (`HasUuids`, cast de `status` e `published_at`, `belongsToMany` categorias, `belongsTo` autor)
- [ ] Geração do slug na criação (`Str::slug` + sufixo em colisão), sem regerar ao editar o título
- [ ] Evento `deleted` do `Post` apagando `cover_image` do disco (guard clause para nulo)
- [ ] `php artisan storage:link`

## Parte 2 — Backend: admin

- [x] `StoreCategoryRequest` / `UpdateCategoryRequest`: `authorize()` em `true`; `name` com `bail`, `required`/`sometimes`, `string`, `max:255`, `unique` (no update com `Rule::unique(...)->ignore($this->route('category'))`) e closure que rejeita nome que gere slug vazio; `slug` com `prohibited` nos dois (slug automático e travado depois de criado); mensagens em português
- [x] `CategoryController` (um só, em `app/Http/Controllers/`): `index`, `store` (201), `show`, `update`, `destroy` (204), sem `create()`/`edit()`. `index` e `show` são a versão inicial, para evoluir depois (filtro por nome e paginação no admin, lista pública completa em método próprio)
- [x] Rotas: `index` e `show` públicas; `store`/`update`/`destroy` dentro de `Route::middleware(['auth:sanctum', 'admin'])` (`Route::apiResource(...)->except('index', 'show')`)
- [x] Testes no Postman: store 201 com slug gerado; nome duplicado e `???` com 422; `slug` enviado com 422; update de nome mantendo o slug; destroy 204 e categoria some da lista; sem token 401; logado como `cliente` 403 em store/update/destroy; `index`/`show` públicos 200
- [x] Testes do update no Postman: reenviando o próprio nome (200), usando o nome de outra categoria (422) e enviando `slug` (422) — todos passaram
- [x] Limpar sobras do `-a`: `CategoryPolicy` apagado. `CategorySeeder`/`CategoryFactory` seguem no projeto, opcionais (o `DatabaseSeeder` usa `WithoutModelEvents`, então o seeder precisaria informar o slug manualmente)
- [ ] `StorePostRequest` / `UpdatePostRequest` (regras da spec, incluindo exigências para publicar)
- [ ] Sanitização do HTML do `content` antes de gravar (instalar lib e configurar tags permitidas)
- [ ] `PostController` (CRUD) usando `sync()` para as categorias
- [ ] `POST /uploads` (valida tipo e tamanho, salva em `public`, devolve `path` e `url`)
- [ ] Rotas de admin de postagens dentro de `Route::middleware(['auth:sanctum', 'admin'])`
- [ ] API Resources (monta a URL da capa a partir do caminho relativo)
- [ ] Testar tudo no Postman (admin com sucesso; cliente com 403 e sem token com 401)

## Parte 3 — Backend: público

- [ ] `GET /posts` (só publicadas, `published_at <= agora`, paginada, filtro `?categoria=slug`)
- [ ] `GET /posts/{slug}`
- [x] `GET /categories` (já existe como `index` público do `CategoryController`)
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
- [ ] Em produção: `APP_DEBUG=false`. Com `true`, respostas de erro (ex.: o 403) incluem `file`, `line` e `trace` com caminhos do servidor.

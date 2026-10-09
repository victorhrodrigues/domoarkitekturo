# Contexto do projeto — Domoarkitekturo

> Documento vivo. Atualizar conforme o projeto evolui (novas features, decisões de arquitetura, mudanças de stack). Para explicações pedagógicas e lições aprendidas (material de estudo do usuário), ver [`guia-de-estudos.md`](./guia-de-estudos.md).

## Stack

**Backend** (`backend/`)
- Laravel 12 + Laravel Sanctum ^4.0 — autenticação **só por token Bearer** (`HasApiTokens`), sem sessão/cookie (ver decisão de limpeza abaixo).
- MySQL como banco de dados (`DB_CONNECTION=mysql` no `.env` real; o `sqlite` que aparece no `.env.example` é só o default do scaffold, não reflete o ambiente real do projeto).

**Frontend** (`frontend/`)
- Next.js 16 (App Router) + React 19 + TypeScript.
- Tailwind CSS v4 (config CSS-first via `@tailwindcss/postcss`, sem `tailwind.config.js`).
- Gerenciador de pacotes: pnpm.

**Comunicação**
- REST API, CORS habilitado no backend (`config/cors.php`, `allowed_origins` lido de `FRONTEND_URL` = `http://localhost:3000`, porta padrão do Next.js).
- Rotas sem prefixo `/api` (`apiPrefix: ''` em `bootstrap/app.php`, decisão de 2026-10-09: o backend vai ficar num subdomínio próprio, então `api.site.com/api/...` repetiria "api"). O frontend já foi ajustado (`services/auth.ts` chama `/login`, `/user`, `/logout`; `NEXT_PUBLIC_API_URL` continua `http://localhost:8000`, sem `/api`). As menções a `/api/...` nas seções históricas abaixo (Fases 1 e 2) descrevem o estado da época.
- Autenticação: `POST /login` devolve um token Bearer, reenviado em `Authorization: Bearer <token>` nas chamadas seguintes. Sem CSRF, sem cookie de sessão.

## Estrutura de pastas (resumo)

```
backend/
  app/Http/Controllers/Auth/AuthenticatedTokenController.php  → login (store) / logout (destroy) por token
  app/Http/Requests/Auth/LoginRequest.php                      → validação + rate limiting do login (reaproveitado do Breeze)
  app/Http/Middleware/EnsureUserIsAdmin.php                    → bloqueia rota pra quem não tem role admin (alias "admin")
  app/Enums/UserRole.php         → enum PHP (Admin/Cliente), backed por string, usado no cast do User
  app/Models/User.php           → usa HasApiTokens, HasUuids (id é UUID), cast de role
  app/Models/Category.php       → categorias do blog (em andamento, ver docs/features/postagens/)
  database/migrations/          → users (com coluna role), sessions, cache, jobs, personal_access_tokens (Sanctum, uuidMorphs)
  app/Http/Controllers/CategoryController.php → CRUD de categorias (index/show públicos; store/update/destroy só admin)
  app/Http/Requests/{Store,Update}CategoryRequest.php → validação de categoria (slug proibido: automático e travado)
  routes/api.php                → /login, /logout, /user, /admin (teste), /categories (protegidas por auth:sanctum + admin onde aplicável)

frontend/
  src/app/layout.tsx             → layout raiz (metadata global)
  src/app/(site)/page.tsx         → home pública (com a animação 3D de abertura)
  src/app/(admin)/admin/          → painel administrativo (placeholder)
  src/components/HomeAnimation.tsx → cena 3D (R3F + drei) da home: casa explodida que monta e logo que entra, ambos guiados pelo scroll
  src/components/Loader.tsx        → tela de carregamento dos assets 3D (croqui SVG "sendo desenhado")
  src/components/BlueprintSvg.tsx  → o croqui em si (283 <path>, desenhado no Figma sobre a perspectiva real da câmera)
  src/hooks/useSmoothProgress.ts   → suaviza o progresso bruto do drei (que pula em degraus) numa barra de carregamento agradável
  public/models/casa_corrigida.glb → modelo 3D exportado do Blender (casa em exploded axonometric view)
  public/images/logo-domo*.png     → logo com fundo transparente, usada como THREE.Sprite na animação
```

## Objetivo do site

Site para a mãe do usuário: arquiteta, dona também de um café com loja geek (venda de itens de decoração e colecionáveis). Ver planejamento completo (sitemap, fluxos de usuário, fases do MVP) em [`Motivação-do-projeto-e-proposta-de-organizacao.md`](./Motivação-do-projeto-e-proposta-de-organizacao.md).

Resumo das seções previstas: Home, Portfólio (residencial/comercial), Serviços/Consultoria, Loja/Catálogo (decoração & geek), Blog de decoração, "O Espaço" (sobre & café), Contato/Orçamento.

MVP (Fase 1): site institucional com catálogo vitrine (checkout via WhatsApp, sem carrinho/pagamento ainda). Fase 2: e-commerce completo (carrinho, gateway de pagamento, estoque).

## Estado atual (atualizado em 2026-10-08)

**Catálogo de features**: cada recurso em construção tem uma pasta em [`features/`](./features/README.md) com `spec.md` (o que é) e `task.md` (etapas e progresso). Hoje: [`papeis-de-usuario`](./features/papeis-de-usuario/task.md) (concluída no backend; falta proteger a página `/admin` no frontend) e [`postagens`](./features/postagens/task.md) (blog, em andamento: começou pelo model/migration de `Category`).

**Histórico do estado inicial (2026-09-02):**

O projeto está no **início**. A primeira coisa implementada foi um esqueleto de autenticação — inicialmente por sessão/cookie (Breeze, prova de conceito com o antigo frontend Vite), depois migrado para token Bearer (Sanctum) para funcionar com Server Components do Next.js. O Vite e a sessão/cookie foram descartados por completo em 2026-09-02 (ver seção de limpeza abaixo) — hoje só existe o fluxo por token.

Naquela data não existia nenhum model, migration, controller ou rota de **domínio**; a modelagem de domínio (Fase 3 do roteiro abaixo) começou com o blog (`Category`, depois `Post`). Regras de negócio serão registradas em [`regras-de-negocio.md`](./regras-de-negocio.md) conforme forem definidas.

## Decisão de arquitetura: migração para Next.js (2026-09-02)

O frontend atual (Vite + React SPA, client-side rendering puro) tem um problema real para este projeto: páginas públicas (Home, Portfólio, Blog) dependem de SEO orgânico e de compartilhamento em redes sociais/WhatsApp, e uma SPA pura serve HTML inicial vazio — o que atrasa a indexação no Google e quebra o preview (título/imagem) ao compartilhar um link no WhatsApp/Instagram, já que esses apps não executam JS.

Decisão: reconstruir o frontend com **Next.js (App Router)**, aproveitando Server Components + a API de `metadata`/`generateMetadata` (HTML já renderizado no servidor, com tags corretas por página) e `middleware.ts` para proteger rotas do painel admin no servidor. A migração será feita **aos poucos**, não como reescrita única.

Essa decisão foi validada observando um template Next.js+Laravel de referência (pasta `adapti-project-template-develop/` neste repo) que resolve exatamente esse problema — **mas esse template é propriedade da empresa do usuário e não pode ser copiado**; ele serve só como referência conceitual de arquitetura (padrões como Server Components por padrão, Sanctum com Bearer token em vez de cookie/sessão, sistema de permissões simples), tudo a ser reimplementado do zero.

Ainda não decidido: se a autenticação do backend também migra de sessão/cookie (atual) para token Bearer do Sanctum — provável necessidade para Next.js, mas a ser confirmado quando essa etapa for planejada.

### Roteiro das fases

1. ✅ **Scaffold do Next.js** (`frontend/`) — feito em 2026-09-02.
2. ✅ **Autenticação backend → token Bearer (Sanctum)** — feito em 2026-09-02.
3. ⬜ Modelagem de domínio (`Project`, `Post`, `Product`, `Lead`) + rotas API.
4. ⬜ Páginas públicas com SEO real (`metadata`/`generateMetadata`).
5. ⬜ Painel admin (CRUD protegido por `middleware.ts`).

### `frontend/` (Fase 1 — concluída)

Next.js 16 (App Router, Turbopack), TypeScript, Tailwind v4, pnpm. Criado do zero via `create-next-app` (não copiado do template da empresa — ver nota acima). Estrutura:

```
frontend/src/app/
  layout.tsx           → layout raiz (metadata global, lang="pt-BR")
  (site)/page.tsx       → home pública (placeholder)
  (admin)/admin/
    layout.tsx          → layout do painel (metadata "Painel")
    page.tsx             → home do painel (placeholder)
```

Route groups `(site)` e `(admin)` só organizam layouts diferentes — não entram na URL. `.env.local.example` já aponta `NEXT_PUBLIC_API_URL=http://localhost:8000` para o backend Laravel. `pnpm build` validado sem erros.

Nessa época o projeto ainda tinha, lado a lado, o antigo frontend Vite (que também se chamava `frontend/`) — removido depois na limpeza descrita abaixo. Em seguida, a pasta do Next.js (então `frontend-next/`) foi renomeada para `frontend/`, já que não havia mais colisão de nomes (ver "Renomeação" no fim desta seção).

### Backend — autenticação por token (Fase 2 — concluída)

Novo `App\Http\Controllers\Auth\AuthenticatedTokenController` (`store`/`destroy`), usando `Laravel\Sanctum\HasApiTokens` (adicionada ao model `User`) para emitir/revogar tokens Bearer. Rotas em `routes/api.php`:
- `POST /api/login` — pública, gera token (`createToken()->plainTextToken`).
- `POST /api/logout` — dentro do grupo `auth:sanctum`, revoga só o token atual (`currentAccessToken()->delete()`).
- `GET /api/user` — inalterada, já aceitava sessão e token ao mesmo tempo via `auth:sanctum`.

Inicialmente coexistiu com o login por sessão/cookie do Breeze (servindo o antigo frontend Vite, que também se chamava `frontend/` na época) — ver seção de limpeza abaixo sobre a remoção desse mecanismo.

Testado manualmente via `Invoke-RestMethod` (PowerShell): login → token → `GET /api/user` autenticado → logout → `GET /api/user` com o mesmo token dá 401. Nota de depuração: sem o header `Accept: application/json`, o Laravel trata a falha de auth como se fosse um navegador e tenta redirecionar pra `route('login')` — todo cliente de API (inclusive o que vamos construir no Next.js) precisa sempre mandar `Accept: application/json`.

### Limpeza: fim do Vite e da sessão/cookie (2026-09-02)

Decisão: já que a autenticação real do projeto é por token (pro Next.js), o antigo frontend Vite (nessa época também chamado `frontend/`) — cujo único propósito era validar o fluxo de sessão/cookie — deixou de fazer sentido. Removido nessa limpeza:
- Pasta do Vite inteira.
- `AuthenticatedSessionController`, `routes/auth.php` (e o `require` dele em `web.php`) — o login/logout por sessão.
- Como consequência (routes/auth.php continha mais que login/logout): `RegisteredUserController`, `PasswordResetLinkController`, `NewPasswordController`, `VerifyEmailController`, `EmailVerificationNotificationController` e o middleware `EnsureEmailIsVerified` ficaram órfãos (nada mais os roteava) e também foram removidos, junto dos 4 testes que dependiam deles (`AuthenticationTest`, `RegistrationTest`, `PasswordResetTest`, `EmailVerificationTest`).
- O callback `ResetPassword::createUrlUsing(...)` em `AppServiceProvider` (dependia do reset de senha removido).

**Decisão consciente**: registro público, reset de senha e verificação de e-mail **não existem mais** por enquanto. Não é um bug — é porque esse é um site de admin único (a mãe do usuário), sem necessidade de auto-registro. Reset de senha será reconstruído (adaptado pra token) quando for realmente necessário, não está no roteiro atual.

`.env`/`.env.example`: `FRONTEND_URL` e `SANCTUM_STATEFUL_DOMAINS` agora apontam pra `localhost:3000` (porta padrão do Next.js, não mais 5173 do Vite); as duas chaves passaram a estar documentadas no `.env.example` (lacuna identificada na Fase 1, agora fechada).

Confirmado depois da limpeza: `php artisan route:list` sem erros, `php artisan test` passando (só restam os `ExampleTest` padrão).

### Primeira comunicação `frontend` ↔ backend (2026-09-02)

Criada `frontend/src/app/login/page.tsx` (Client Component, `"use client"`) só pra testar o fluxo ponta a ponta: formulário → `POST /api/login` (fetch) → guarda o token → `GET /api/user` com `Authorization: Bearer`. Não é a arquitetura final (isso vem nas fases seguintes, provavelmente via Server Actions/NextAuth) — é só uma prova de conectividade.

Duas pegadinhas reais encontradas e corrigidas nesse processo:
- `frontend/.env.local` precisa existir de verdade (`.env.local.example` não é lido pelo Next.js) — e o `pnpm dev` precisa ser **reiniciado** depois de criar/mudar esse arquivo, porque variáveis `NEXT_PUBLIC_*` são embutidas no bundle no momento em que o servidor de dev sobe, não recarregadas a quente.
- **Erro 419 (CSRF) numa rota de token**: o `EnsureFrontendRequestsAreStateful` (Sanctum) continuava com `prepend` em todas as rotas de `api.php` (`bootstrap/app.php`), e liga sessão+CSRF automaticamente pra qualquer requisição vinda de um domínio listado em `SANCTUM_STATEFUL_DOMAINS`. Como esse valor foi atualizado pra `localhost:3000` (porta do Next) durante a limpeza do Vite, toda chamada do `frontend` passou a ser tratada como "SPA stateful" e exigir CSRF — que nunca pedimos. Removido o `$middleware->api(prepend: [...])` de `bootstrap/app.php` e a variável `SANCTUM_STATEFUL_DOMAINS` (agora morta) do `.env`/`.env.example`. **Mudança em `bootstrap/app.php` exige reiniciar `php artisan serve`** — não é hot-reload como as rotas.

### Organização de pastas do `frontend/src/` (2026-09-02)

```
lib/api.ts        → wrapper fino sobre fetch: base URL, headers padrão, extrai mensagem de erro do Laravel, trata 204 sem corpo
types/user.ts      → tipos compartilhados (User, LoginResponse)
services/auth.ts    → login()/getMe()/logout(), construídos sobre lib/api.ts
```
Distinção `lib/` vs `services/`: `lib/` é infraestrutura genérica (não sabe nada sobre login/domínio), `services/` é lógica específica de domínio construída em cima da `lib/`. `login/page.tsx` foi refatorado pra só cuidar de UI/estado, delegando as chamadas de rede pra `services/auth.ts`.

Decisão consciente, na época, de **não** criar ainda `actions/`, `components/` nem `hooks/` — sem conteúdo real pra colocar neles, criar vazio seria estrutura decorativa. `components/` e `hooks/` deixaram de ser hipotéticas com a animação 3D da home (ver seção abaixo); `actions/` continua sem uso — critério inalterado: só quando a Fase 5 (Server Actions do painel admin) chegar.

## Animação 3D da home (React Three Fiber + drei) — 2026-09-13

A home (`(site)/page.tsx`) abre com uma cena 3D: um modelo da casa (modelada e animada no Blender pelo usuário, exportada em `.glb` como "exploded axonometric view" — peças afastadas que se encaixam) que se monta conforme o usuário rola a página, seguida da logo entrando por trás da câmera. Escolhido deliberadamente **React Three Fiber + `@react-three/drei`** em vez de Three.js puro (que já tinha um protótipo funcional em `public/models/casa_linhas.html`, removido depois de a migração ser validada) — o motivo é aprendizado dessas ferramentas, não necessidade técnica (ver `guia-de-estudos.md` para a lista de bugs de integração encontrados nessa migração).

Enquanto o `.glb` e a textura da logo carregam, um loader cobre a tela: um croqui em SVG (283 traços, desenhados no Figma sobre um screenshot da própria cena na posição inicial da câmera, pra bater com a perspectiva) que vai "se desenhando" conforme os arquivos baixam. Como o progresso bruto do drei (`useProgress`) só reporta por arquivo concluído (poucos degraus grandes, não uma subida suave), o hook `useSmoothProgress` simula uma subida suave por tempo, com um teto que só libera pra 100% quando o carregamento real de fato termina — evita tanto uma barra "pulando" quanto uma barra que mente sobre estar pronta.

## Controle de acesso: UUID + papéis de usuário (RBAC) — 2026-10-07

`users.id` deixou de ser auto-incremento e passou a ser **UUID** (`HasUuids` no model, `$table->uuid('id')->primary()` na migration) — decisão tomada cedo de propósito, antes de existir qualquer tabela de domínio referenciando `user_id`, pra não precisar converter nada depois. Toda tabela que referencia o usuário foi ajustada em conjunto: `personal_access_tokens` usa `uuidMorphs('tokenable')` (em vez do `morphs` padrão do Sanctum, que esperava bigint) e `sessions.user_id` é `foreignUuid`.

Usuário agora tem um papel (`role`, coluna `string` — não `enum` de banco, pra não exigir migration toda vez que um papel novo for adicionado), mapeado pro enum PHP `App\Enums\UserRole` (`Admin`/`Cliente`) via cast no model (`'role' => UserRole::class`). Rotas administrativas usam o middleware `App\Http\Middleware\EnsureUserIsAdmin` (alias `admin`, registrado em `bootstrap/app.php`), aplicado em conjunto com `auth:sanctum`: `Route::middleware(['auth:sanctum', 'admin'])->group(...)`. Testado manualmente via `Invoke-RestMethod` com um usuário admin (200) e um usuário cliente (403) contra uma rota de teste (`GET /admin`) — fluxo completo confirmado funcionando. Depois reconfirmado no Postman com as rotas reais de categorias: sem token 401, `cliente` 403, admin 2xx.

Em dev (`APP_DEBUG=true`) as respostas de erro, como o 403, incluem `file`, `line` e `trace` com caminhos do servidor. Em produção, `APP_DEBUG` precisa ser `false`.

Decisão em aberto, ainda não resolvida: se o papel `cliente` vai ter cadastro público (reabriria o registro removido na limpeza do Vite) ou só existe pra uso interno/futuro por enquanto — ver [`features/papeis-de-usuario/spec.md`](./features/papeis-de-usuario/spec.md).

### Renomeação `frontend-next/` → `frontend/` (2026-09-02)

Com o Vite removido, não havia mais motivo pro Next.js se chamar `frontend-next` (era só pra não colidir com o Vite). Renomeado pra `frontend/`. Detalhe técnico: um `git mv`/`Rename-Item` direto falhou com "Permission denied" — o VSCode (rodando essa própria sessão) mantém um watch persistente na pasta do workspace, o que trava renomeação atômica de diretório no Windows. Contornado copiando o conteúdo (só código-fonte, sem `node_modules`/`.next`, que são gerados) pra uma pasta nova via `robocopy`, reinstalando dependências (`pnpm install`) e apagando a pasta antiga — o git reconheceu automaticamente como rename (similaridade de conteúdo), não como arquivos novos.


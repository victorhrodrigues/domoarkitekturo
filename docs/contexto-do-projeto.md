# Contexto do projeto — Domoarkitekturo

> Documento vivo. Atualizar conforme o projeto evolui (novas features, decisões de arquitetura, mudanças de stack).

## Stack

**Backend** (`backend/`)
- Laravel 12 + Laravel Sanctum ^4.0 — autenticação **só por token Bearer** (`HasApiTokens`), sem sessão/cookie (ver decisão de limpeza abaixo).
- MySQL como banco de dados (`DB_CONNECTION=mysql` no `.env` real; o `sqlite` que aparece no `.env.example` é só o default do scaffold, não reflete o ambiente real do projeto).

**Frontend** (`frontend-next/`)
- Next.js 16 (App Router) + React 19 + TypeScript.
- Tailwind CSS v4 (config CSS-first via `@tailwindcss/postcss`, sem `tailwind.config.js`).
- Gerenciador de pacotes: pnpm.

**Comunicação**
- REST API, CORS habilitado no backend (`config/cors.php`, `allowed_origins` lido de `FRONTEND_URL` = `http://localhost:3000`, porta padrão do Next.js).
- Autenticação: `POST /api/login` devolve um token Bearer, reenviado em `Authorization: Bearer <token>` nas chamadas seguintes. Sem CSRF, sem cookie de sessão.

## Estrutura de pastas (resumo)

```
backend/
  app/Http/Controllers/Auth/AuthenticatedTokenController.php  → login (store) / logout (destroy) por token
  app/Http/Requests/Auth/LoginRequest.php                      → validação + rate limiting do login (reaproveitado do Breeze)
  app/Models/User.php           → único model de domínio existente (usa HasApiTokens)
  database/migrations/          → users, sessions, cache, jobs, personal_access_tokens (Sanctum)
  routes/api.php                → /api/login, /api/logout, /api/user, /api/teste (debug do CORS)

frontend-next/
  src/app/layout.tsx             → layout raiz (metadata global)
  src/app/(site)/page.tsx         → home pública (placeholder)
  src/app/(admin)/admin/          → painel administrativo (placeholder)
```

## Objetivo do site

Site para a mãe do usuário: arquiteta, dona também de um café com loja geek (venda de itens de decoração e colecionáveis). Ver planejamento completo (sitemap, fluxos de usuário, fases do MVP) em [`Motivação-do-projeto-e-proposta-de-organizacao.md`](./Motivação-do-projeto-e-proposta-de-organizacao.md).

Resumo das seções previstas: Home, Portfólio (residencial/comercial), Serviços/Consultoria, Loja/Catálogo (decoração & geek), Blog de decoração, "O Espaço" (sobre & café), Contato/Orçamento.

MVP (Fase 1): site institucional com catálogo vitrine (checkout via WhatsApp, sem carrinho/pagamento ainda). Fase 2: e-commerce completo (carrinho, gateway de pagamento, estoque).

## Estado atual (2026-09-02)

O projeto está no **início**. A primeira coisa implementada foi um esqueleto de autenticação — inicialmente por sessão/cookie (Breeze, prova de conceito com o antigo frontend Vite), depois migrado para token Bearer (Sanctum) para funcionar com Server Components do Next.js. O Vite e a sessão/cookie foram descartados por completo em 2026-09-02 (ver seção de limpeza abaixo) — hoje só existe o fluxo por token.

Não existe nenhum model, migration, controller ou rota de **domínio** ainda (isso é a Fase 3 do roteiro abaixo). Regras de negócio serão registradas em [`regras-de-negocio.md`](./regras-de-negocio.md) conforme forem definidas.

## Decisão de arquitetura: migração para Next.js (2026-09-02)

O frontend atual (Vite + React SPA, client-side rendering puro) tem um problema real para este projeto: páginas públicas (Home, Portfólio, Blog) dependem de SEO orgânico e de compartilhamento em redes sociais/WhatsApp, e uma SPA pura serve HTML inicial vazio — o que atrasa a indexação no Google e quebra o preview (título/imagem) ao compartilhar um link no WhatsApp/Instagram, já que esses apps não executam JS.

Decisão: reconstruir o frontend com **Next.js (App Router)**, aproveitando Server Components + a API de `metadata`/`generateMetadata` (HTML já renderizado no servidor, com tags corretas por página) e `middleware.ts` para proteger rotas do painel admin no servidor. A migração será feita **aos poucos**, não como reescrita única.

Essa decisão foi validada observando um template Next.js+Laravel de referência (pasta `adapti-project-template-develop/` neste repo) que resolve exatamente esse problema — **mas esse template é propriedade da empresa do usuário e não pode ser copiado**; ele serve só como referência conceitual de arquitetura (padrões como Server Components por padrão, Sanctum com Bearer token em vez de cookie/sessão, sistema de permissões simples), tudo a ser reimplementado do zero.

Ainda não decidido: se a autenticação do backend também migra de sessão/cookie (atual) para token Bearer do Sanctum — provável necessidade para Next.js, mas a ser confirmado quando essa etapa for planejada.

### Roteiro das fases

1. ✅ **Scaffold do Next.js** (`frontend-next/`) — feito em 2026-09-02.
2. ✅ **Autenticação backend → token Bearer (Sanctum)** — feito em 2026-09-02.
3. ⬜ Modelagem de domínio (`Project`, `Post`, `Product`, `Lead`) + rotas API.
4. ⬜ Páginas públicas com SEO real (`metadata`/`generateMetadata`).
5. ⬜ Painel admin (CRUD protegido por `middleware.ts`).

### `frontend-next/` (Fase 1 — concluída)

Next.js 16 (App Router, Turbopack), TypeScript, Tailwind v4, pnpm. Criado do zero via `create-next-app` (não copiado do template da empresa — ver nota acima). Estrutura:

```
frontend-next/src/app/
  layout.tsx           → layout raiz (metadata global, lang="pt-BR")
  (site)/page.tsx       → home pública (placeholder)
  (admin)/admin/
    layout.tsx          → layout do painel (metadata "Painel")
    page.tsx             → home do painel (placeholder)
```

Route groups `(site)` e `(admin)` só organizam layouts diferentes — não entram na URL. `.env.local.example` já aponta `NEXT_PUBLIC_API_URL=http://localhost:8000` para o backend Laravel. `pnpm build` validado sem erros.

Coexiste por enquanto com `frontend/` (Vite, projeto antigo) — a substituição definitiva acontece quando `frontend-next/` cobrir as mesmas funcionalidades (fases 3–5).

### Backend — autenticação por token (Fase 2 — concluída)

Novo `App\Http\Controllers\Auth\AuthenticatedTokenController` (`store`/`destroy`), usando `Laravel\Sanctum\HasApiTokens` (adicionada ao model `User`) para emitir/revogar tokens Bearer. Rotas em `routes/api.php`:
- `POST /api/login` — pública, gera token (`createToken()->plainTextToken`).
- `POST /api/logout` — dentro do grupo `auth:sanctum`, revoga só o token atual (`currentAccessToken()->delete()`).
- `GET /api/user` — inalterada, já aceitava sessão e token ao mesmo tempo via `auth:sanctum`.

Inicialmente coexistiu com o login por sessão/cookie do Breeze (servindo o `frontend/` Vite antigo) — ver seção de limpeza abaixo sobre a remoção desse mecanismo.

Testado manualmente via `Invoke-RestMethod` (PowerShell): login → token → `GET /api/user` autenticado → logout → `GET /api/user` com o mesmo token dá 401. Nota de depuração: sem o header `Accept: application/json`, o Laravel trata a falha de auth como se fosse um navegador e tenta redirecionar pra `route('login')` — todo cliente de API (inclusive o que vamos construir no Next.js) precisa sempre mandar `Accept: application/json`.

### Limpeza: fim do Vite e da sessão/cookie (2026-09-02)

Decisão: já que a autenticação real do projeto é por token (pro Next.js), o `frontend/` Vite antigo — cujo único propósito era validar o fluxo de sessão/cookie — deixou de fazer sentido. Removido nessa limpeza:
- Pasta `frontend/` inteira (Vite).
- `AuthenticatedSessionController`, `routes/auth.php` (e o `require` dele em `web.php`) — o login/logout por sessão.
- Como consequência (routes/auth.php continha mais que login/logout): `RegisteredUserController`, `PasswordResetLinkController`, `NewPasswordController`, `VerifyEmailController`, `EmailVerificationNotificationController` e o middleware `EnsureEmailIsVerified` ficaram órfãos (nada mais os roteava) e também foram removidos, junto dos 4 testes que dependiam deles (`AuthenticationTest`, `RegistrationTest`, `PasswordResetTest`, `EmailVerificationTest`).
- O callback `ResetPassword::createUrlUsing(...)` em `AppServiceProvider` (dependia do reset de senha removido).

**Decisão consciente**: registro público, reset de senha e verificação de e-mail **não existem mais** por enquanto. Não é um bug — é porque esse é um site de admin único (a mãe do usuário), sem necessidade de auto-registro. Reset de senha será reconstruído (adaptado pra token) quando for realmente necessário, não está no roteiro atual.

`.env`/`.env.example`: `FRONTEND_URL` e `SANCTUM_STATEFUL_DOMAINS` agora apontam pra `localhost:3000` (porta padrão do Next.js, não mais 5173 do Vite); as duas chaves passaram a estar documentadas no `.env.example` (lacuna identificada na Fase 1, agora fechada).

Confirmado depois da limpeza: `php artisan route:list` sem erros, `php artisan test` passando (só restam os `ExampleTest` padrão).

### Primeira comunicação `frontend-next` ↔ backend (2026-09-02)

Criada `frontend-next/src/app/login/page.tsx` (Client Component, `"use client"`) só pra testar o fluxo ponta a ponta: formulário → `POST /api/login` (fetch) → guarda o token → `GET /api/user` com `Authorization: Bearer`. Não é a arquitetura final (isso vem nas fases seguintes, provavelmente via Server Actions/NextAuth) — é só uma prova de conectividade.

Duas pegadinhas reais encontradas e corrigidas nesse processo:
- `frontend-next/.env.local` precisa existir de verdade (`.env.local.example` não é lido pelo Next.js) — e o `pnpm dev` precisa ser **reiniciado** depois de criar/mudar esse arquivo, porque variáveis `NEXT_PUBLIC_*` são embutidas no bundle no momento em que o servidor de dev sobe, não recarregadas a quente.
- **Erro 419 (CSRF) numa rota de token**: o `EnsureFrontendRequestsAreStateful` (Sanctum) continuava com `prepend` em todas as rotas de `api.php` (`bootstrap/app.php`), e liga sessão+CSRF automaticamente pra qualquer requisição vinda de um domínio listado em `SANCTUM_STATEFUL_DOMAINS`. Como esse valor foi atualizado pra `localhost:3000` (porta do Next) durante a limpeza do Vite, toda chamada do `frontend-next` passou a ser tratada como "SPA stateful" e exigir CSRF — que nunca pedimos. Removido o `$middleware->api(prepend: [...])` de `bootstrap/app.php` e a variável `SANCTUM_STATEFUL_DOMAINS` (agora morta) do `.env`/`.env.example`. **Mudança em `bootstrap/app.php` exige reiniciar `php artisan serve`** — não é hot-reload como as rotas.

## Pontos técnicos a ter em mente

Não são bugs urgentes — são notas para quando as áreas relacionadas forem retomadas:

- A rota `/api/teste` (routes/api.php) é um artefato da fase de teste de conectividade inicial — candidata a remoção quando a Fase 3 (domínio) estiver rodando de verdade.

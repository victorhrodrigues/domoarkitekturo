# Papéis de usuário (RBAC) — Task

> Status: ✅ concluído e testado (login como admin → `200` em `/api/admin`; login como cliente → `403`).

## Parte 1 — Adicionar papéis ao model `User`

- [x] **Etapa 1**: migration nova adicionando a coluna `role` em `users` (`$table->string('role')->default('cliente')`). `string`, não `enum` de banco — mais fácil de evoluir sem precisar de migration pra cada papel novo.
- [x] **Etapa 2**: `app/Enums/UserRole.php` — enum PHP backed por string (`Admin`/`Cliente`), dá autocomplete e evita erro de digitação silencioso.
- [x] **Etapa 3**: cast no `User::casts()` — `'role' => UserRole::class`. `$user->role` passa a devolver `UserRole::Admin`/`UserRole::Cliente` em vez da string crua.
- [x] **Etapa 4**: `DatabaseSeeder` atualizado pra criar o usuário de teste já com `role: 'admin'`.

## Parte 2 — Middleware para rotas só de administrador

- [x] **Etapa 5**: `app/Http/Middleware/EnsureUserIsAdmin.php` — `handle()` checa `$request->user()?->role !== UserRole::Admin` e barra com `abort(403, ...)`.
- [x] **Etapa 6**: alias `'admin' => EnsureUserIsAdmin::class` registrado em `bootstrap/app.php` (`withMiddleware`).
- [x] **Etapa 7**: rota de teste `GET /api/admin` em `routes/api.php`, dentro de `Route::middleware(['auth:sanctum', 'admin'])->group(...)`.

## Pendências

- [ ] Decidir e, se necessário, implementar a Opção B da [spec](./spec.md) (cadastro público de cliente) — não é bloqueante, fica pra quando a Fase 2 (e-commerce) for retomada.
- [ ] Proteger a página `/admin` do **frontend** (hoje só a API está protegida — a casca visual da página em `frontend/src/app/(admin)/admin/page.tsx` é acessível por qualquer um, sem checagem nenhuma). Decisão em aberto entre `middleware.ts` (precisa do token num cookie, não só `localStorage`) e um guard client-side — ver discussão no chat de 2026-10-08.

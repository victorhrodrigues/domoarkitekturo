# Implementação: papéis de usuário (admin/cliente) + rotas protegidas

> Guia de etapas pra você implementar você mesmo. Não é um documento de estado do projeto (isso é o [`contexto-do-projeto.md`](./contexto-do-projeto.md)) — é um roteiro de "o que fazer e por quê" pra essa tarefa específica.

## Contexto

Hoje o `User` (`backend/app/Models/User.php`) não tem nenhum conceito de papel — qualquer usuário autenticado tem o mesmo nível de acesso. Pra existir uma distinção admin/cliente, e rotas que só o admin pode acessar, faltam três coisas: um jeito de guardar o papel no banco, um jeito type-safe de checar esse papel no código, e um middleware que barre quem não é admin antes mesmo de chegar no controller.

Isso é independente da decisão sobre cadastro público de cliente (Parte 3) — dá pra fazer a Parte 1 e 2 agora, e decidir a Parte 3 depois.

---

## Parte 1 — Adicionar papéis ao model `User`

### Etapa 1: Migration adicionando a coluna de papel

Não edite a migration `0001_01_01_000000_create_users_table.php` que já existe — ela provavelmente já rodou no seu banco local, e editar uma migration já aplicada não refaz o schema sozinho. O jeito certo no Laravel é uma migration **nova**, só com a mudança:

```bash
php artisan make:migration add_role_to_users_table --table=users
```

Dentro dela, adicionar a coluna com um valor padrão (importante: o único usuário que existe hoje, criado pela seed, precisa continuar válido depois da migration rodar):

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->default('cliente')->after('password');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('role');
    });
}
```

Por que `string` e não um `enum` nativo do MySQL (`$table->enum('role', ['admin', 'cliente'])`)? Um `enum` de banco trava a lista de valores no schema — adicionar um terceiro papel no futuro exigiria outra migration alterando o tipo da coluna. Uma `string` comum, validada no nível do PHP (próxima etapa), é mais fácil de evoluir.

### Etapa 2: Enum PHP pra representar os papéis no código

Crie `backend/app/Enums/UserRole.php` (pasta nova — ainda não existe `app/Enums/`):

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Cliente = 'cliente';
}
```

Isso existe só no PHP, não no banco — é o que te dá autocomplete e evita erro de digitação (`'admim'` vs `'admin'`) espalhado pelo código. A próxima etapa conecta esse enum à coluna que você acabou de criar.

### Etapa 3: Cast no model `User`

Em `User.php`, dentro do método `casts()` que já existe, adicione a nova coluna:

```php
use App\Enums\UserRole;

// ...

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
    ];
}
```

Com isso, `$user->role` deixa de devolver a string crua do banco (`"admin"`) e passa a devolver `UserRole::Admin` — o enum de verdade. Comparações ficam assim: `$user->role === UserRole::Admin` (seguro, o PHP acusa erro de digitação na hora) em vez de `$user->role === 'admin'` (silenciosamente falso se você digitar errado).

### Etapa 4: Garantir que o usuário admin existente tenha o papel certo

Como a migration da Etapa 1 dá o papel `cliente` por padrão pra qualquer linha existente, o seu usuário de teste/admin atual vai precisar ser atualizado manualmente depois de rodar a migration — ou você ajusta o `DatabaseSeeder`/`UserFactory` pra criar esse usuário já com `role: 'admin'`, e recria o banco local (`php artisan migrate:fresh --seed`).

---

## Parte 2 — Middleware para rotas só de administrador

### Etapa 5: Criar o middleware

```bash
php artisan make:middleware EnsureUserIsAdmin
```

Isso cria `backend/app/Http/Middleware/EnsureUserIsAdmin.php` com um método `handle()` vazio. A lógica dele é simples: se não for admin, barra antes de chegar no controller:

```php
public function handle(Request $request, Closure $next): Response
{
    if ($request->user()?->role !== UserRole::Admin) {
        abort(403, 'Acesso restrito a administradores.');
    }

    return $next($request);
}
```

Esse middleware só faz sentido **depois** de `auth:sanctum` já ter rodado (precisa de um usuário autenticado pra checar o papel dele) — isso vai importar na Etapa 7, na ordem dentro do grupo de rota.

### Etapa 6: Registrar o middleware como um "alias"

Você já mexeu em `backend/bootstrap/app.php` antes (foi lá que removemos o `EnsureFrontendRequestsAreStateful` na limpeza do Vite/CSRF). Hoje o bloco `withMiddleware` está vazio — é ali que o alias entra:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
    ]);
})
```

O "alias" é só um apelido curto (`'admin'`) pra não precisar escrever o namespace completo toda vez que for usar o middleware numa rota.

### Etapa 7: Aplicar nas rotas que devem ser só de admin

Em `routes/api.php`, qualquer rota que deva ser exclusiva de admin entra num grupo com os dois middlewares, nessa ordem (`auth:sanctum` primeiro, pra garantir que exista um `$request->user()` antes do `admin` tentar checar o papel dele):

```php
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // rotas administrativas futuras (CRUD de produtos, posts, etc.)
});
```

Rotas que qualquer usuário autenticado pode acessar (como o `/api/user` e `/api/logout` que já existem) continuam só com `auth:sanctum`, sem o `admin`.

---

## Parte 3 — A questão dos cadastros (decisão em aberto)

As Partes 1 e 2 acima funcionam independente dessa decisão. O que muda dependendo da resposta é se existe (ou não) uma rota pública de registro.

**Contexto importante**: o registro público (`RegisteredUserController` + rota) foi removido de propósito na limpeza do Vite (ver `contexto-do-projeto.md`, seção "Limpeza: fim do Vite e da sessão/cookie") — na época, a decisão foi "esse é um site de admin único, sem necessidade de auto-registro". Introduzir o papel `cliente` reabre essa pergunta.

### Opção A — Só preparar o model, sem cadastro público (mais simples)

Ninguém se registra sozinho. Contas de `cliente`, se chegarem a existir, seriam criadas de outra forma — por exemplo, manualmente por você/sua mãe, ou criadas automaticamente junto de um pedido/lead quando a Fase 2 (e-commerce) for implementada. Não precisa de nenhuma rota nova agora. Essa opção mantém a decisão original intacta (continua sem auto-registro) e só adiciona a *capacidade* de um usuário ser diferenciado como cliente no futuro.

### Opção B — Cadastro público de verdade

Um visitante consegue criar a própria conta de cliente sozinho, sem você precisar criar nada manualmente. Isso exige reconstruir o fluxo de registro que foi removido — mas adaptado pra token (Sanctum Bearer), não mais sessão/cookie como era no Breeze original:

- Um novo `RegisteredUserController` (ou método no `AuthenticatedTokenController`) que valida nome/e-mail/senha, cria o `User` com `role: UserRole::Cliente`, e devolve um token (igual o login já faz hoje).
- Uma rota pública `POST /api/register` (fora do grupo `auth:sanctum`, já que ninguém está autenticado ainda nesse momento).
- Decidir separadamente se verificação de e-mail entra ou não pra contas de cliente (foi removida de propósito pro admin único; pra clientes públicos pode fazer mais sentido ter de volta, mas é uma decisão nova, não a mesma de antes).

**Recomendação**: a Opção A é suficiente pra você avançar agora (prepara a estrutura, não fecha porta nenhuma) — a Opção B só vale a pena implementar quando a Fase 2 (e-commerce) estiver realmente sendo construída e o motivo de ter clientes logados (histórico de pedido, dados salvos, etc.) existir de verdade. Implementar cadastro público antes disso é funcionalidade sem uso real ainda.

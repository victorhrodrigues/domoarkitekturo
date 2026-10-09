# Papéis de usuário (RBAC) — Spec

## Contexto

O `User` (`backend/app/Models/User.php`) não tinha nenhum conceito de papel — qualquer usuário autenticado tinha o mesmo nível de acesso. Pra existir uma distinção admin/cliente, e rotas que só o admin pode acessar, era preciso: um jeito de guardar o papel no banco, um jeito type-safe de checar esse papel no código, e um middleware que barre quem não é admin antes mesmo de chegar no controller.

## Requisitos

- Usuário tem um papel: `admin` ou `cliente` (`App\Enums\UserRole`, enum PHP backed por string — não `enum` de banco, pra não exigir migration toda vez que um papel novo for adicionado).
- Rotas administrativas devem recusar qualquer usuário que não seja `admin`, com `403`.
- A checagem de papel é sobre o usuário **já autenticado** (via token Sanctum) — não tem relação com validação de dados no momento da criação de um usuário.

## Decisão em aberto: cadastro público de cliente

**Contexto importante**: o registro público (`RegisteredUserController` + rota) foi removido de propósito na limpeza do Vite (ver [`contexto-do-projeto.md`](../../contexto-do-projeto.md), seção "Limpeza: fim do Vite e da sessão/cookie") — na época, a decisão foi "esse é um site de admin único, sem necessidade de auto-registro". Introduzir o papel `cliente` reabre essa pergunta.

### Opção A — Só preparar o model, sem cadastro público (mais simples) — **adotada por enquanto**

Ninguém se registra sozinho. Contas de `cliente`, se chegarem a existir, seriam criadas de outra forma — por exemplo, manualmente, ou criadas automaticamente junto de um pedido/lead quando a Fase 2 (e-commerce) for implementada. Não precisa de nenhuma rota nova agora. Essa opção mantém a decisão original intacta (continua sem auto-registro) e só adiciona a *capacidade* de um usuário ser diferenciado como cliente no futuro.

### Opção B — Cadastro público de verdade (não implementada)

Um visitante consegue criar a própria conta de cliente sozinho, sem ninguém precisar criar nada manualmente. Isso exige reconstruir o fluxo de registro que foi removido — mas adaptado pra token (Sanctum Bearer), não mais sessão/cookie como era no Breeze original:

- Um novo `RegisteredUserController` (ou método no `AuthenticatedTokenController`) que valida nome/e-mail/senha, cria o `User` com `role: UserRole::Cliente`, e devolve um token (igual o login já faz hoje).
- Uma rota pública `POST /register` (fora do grupo `auth:sanctum`, já que ninguém está autenticado ainda nesse momento).
- Decidir separadamente se verificação de e-mail entra ou não pra contas de cliente (foi removida de propósito pro admin único; pra clientes públicos pode fazer mais sentido ter de volta, mas é uma decisão nova, não a mesma de antes).

**Recomendação**: a Opção A é suficiente pra avançar agora (prepara a estrutura, não fecha porta nenhuma) — a Opção B só vale a pena implementar quando a Fase 2 (e-commerce) estiver realmente sendo construída e o motivo de ter clientes logados (histórico de pedido, dados salvos, etc.) existir de verdade.

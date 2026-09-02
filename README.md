# Domoarkitekturo

Site institucional para um estúdio de arquitetura (portfólio, blog, loja/catálogo e formulário de contato). Veja o contexto completo do projeto em [`docs/contexto-do-projeto.md`](docs/contexto-do-projeto.md).

## Stack

- **Backend** (`backend/`): Laravel 12 + Sanctum (autenticação por token Bearer) + MySQL.
- **Frontend** (`frontend/`): Next.js 16 (App Router) + TypeScript + Tailwind CSS v4.
- Comunicação: API REST, CORS liberado do backend para o frontend.

## Pré-requisitos

- PHP 8.2+ e [Composer](https://getcomposer.org)
- MySQL (servidor local ou remoto)
- Node.js 20+ e [pnpm](https://pnpm.io) (`npm install -g pnpm`)

## Backend (`backend/`)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Edite o `.env` gerado:
- Troque `DB_CONNECTION=sqlite` por `DB_CONNECTION=mysql` e preencha `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` com as credenciais do seu MySQL.
- Confirme que `FRONTEND_URL=http://localhost:3000` (porta padrão do Next.js em dev).

Crie o banco no MySQL (o Laravel não cria o database sozinho):
```sql
CREATE DATABASE domoarkitekturo;
```

Rode as migrations (com seed, pra criar um usuário de teste):
```bash
php artisan migrate --seed
```
Isso cria o usuário `test@example.com` / senha `password` (definido em `database/factories/UserFactory.php`).

Suba o servidor:
```bash
php artisan serve
```
Backend disponível em `http://localhost:8000`.

## Frontend (`frontend/`)

```bash
cd frontend
pnpm install
cp .env.local.example .env.local
pnpm dev
```
Frontend disponível em `http://localhost:3000`.

> Se você editar `.env.local` depois que o `pnpm dev` já estava rodando, reinicie o servidor — variáveis `NEXT_PUBLIC_*` só são lidas quando o processo sobe.

## Rodando o projeto completo

Os dois servidores precisam rodar ao mesmo tempo, em terminais separados:
```bash
# terminal 1
cd backend && php artisan serve

# terminal 2
cd frontend && pnpm dev
```

## Estrutura

```
backend/        → API Laravel
frontend/  → aplicação Next.js
docs/           → contexto do projeto, decisões de arquitetura e regras de negócio
```

# Catálogo de features

Cada feature do projeto (login, cadastro de produtos, cadastro de usuários, etc.) ganha uma pasta aqui dentro, nomeada com o nome da feature em kebab-case, com dois arquivos:

```
docs/features/
  <nome-da-feature>/
    spec.md   → o que a feature é, o que ela precisa fazer (requisitos, regras, comportamento esperado)
    task.md   → quebra da implementação em etapas (o "como fazer", passo a passo)
```

Exemplo: `docs/features/login/spec.md` + `docs/features/login/task.md`.

Isso é diferente dos outros documentos em `docs/`:
- [`contexto-do-projeto.md`](../contexto-do-projeto.md) — estado geral do projeto (stack, decisões de arquitetura), não detalhe de uma feature específica.
- [`guia-de-estudos.md`](../guia-de-estudos.md) — lições aprendidas, pedagógico.
- [`regras-de-negocio.md`](../regras-de-negocio.md) — regras de domínio que atravessam várias features.
- `features/<nome>/` — o catálogo vivo de cada recurso sendo construído, um por um.

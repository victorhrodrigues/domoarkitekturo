# Documento de Arquitetura de Informação e Planejamento: Site do Estúdio de Arquitetura & Espaço Conceito

> **Contexto Inicial:** "Pretendo criar um site para minha mae, voce já deve saber mas estou cursando o ultimo período de ciência da computação e tenho experiencia na criação de sites por meio do framewock Laravel com React e Tailwind. Esse site para minha mãe está relacionada a profissao dela, atualmente ela é arquiteta e também é dona de um café com loja geek. Acredito que o ideal para ela seria um site onde ela consegue mostrar seu portifolio, ideias para decoraçao de interiores, postagens como um blog, alem disso preciso de um formulário para que possíveis clientes que gostem do seu trabalho possam entrar em contato. Por fim pensando na parte da loja/café, nao sei se seria legal misturar com esse site mas se voce achar bom (ela tambem vende produtos para decoraçao de casa nessa lojinha) seria legal criar uma loja para venda online desses produtos também nesse site."

---

## 1. Análise Estratégica: Unificação de Marcas
* **O Dilema:** Misturar serviços de arquitetura de alto padrão com colecionáveis geek na primeira dobra da página poderia gerar ruído na percepção de valor.
* **A Solução:** A arquitetura e o design de interiores são o núcleo central do site. O café e a loja de decoração funcionam como uma extensão do estilo de vida e bom gosto da profissional, integrados de forma fluida através de seções dedicadas e um catálogo/e-commerce.

---

## 2. Arquitetura de Informação (Sitemap)

```text
├── Home
├── Portfólio (Arquitetura & Interiores)
│   ├── Residencial
│   ├── Comercial
│   └── Página Interna do Projeto
├── Serviços / Consultoria
│   └── Como Funciona o Atendimento
├── Loja / Catálogo (Decoração & Geek)
│   ├── Objetos de Decoração
│   ├── Itens Selecionados
│   └── Página do Produto / Checkout
├── Blog / Ideias de Decoração
│   └── Artigo / Dicas de Interiores
├── O Espaço (Sobre & Café)
│   └── A História, Conceito do Café e Fotos do Local
└── Contato / Solicitar Orçamento
```

---

## 3. Destaques de Cada Seção
Página Inicial (Home)
* Header: Logo, navegação limpa e botão fixo de Ação (Call to Action) para "Solicitar Orçamento".

* Hero Section: Mensagem direta e impactante sobre o estúdio de arquitetura com imagens marcantes.

* Seção do Portfólio: Mosaico visual com os projetos mais recentes.

* Seção "O Espaço & Loja": Bloco institucional mostrando o diferencial do escritório integrado ao café e loja física.

* Provas Sociais e Blog: Depoimentos de clientes e últimas dicas de decoração.

Portfólio
* Grid responsivo com filtros por categoria (Residencial, Comercial, Consultoria).

* Páginas internas detalhando o conceito, galeria antes/depois e materiais utilizados.

Loja / Catálogo de Decoração
* Vitrine de produtos com foco em decoração e itens selecionados.

* Opção inicial de transição rápida para compra via WhatsApp ou integração progressiva com e-commerce completo.

Blog / Ideias de Decoração
* Artigos educativos para atrair tráfego orgânico (SEO) e demonstrar autoridade em tendências, iluminação e otimização de espaços.

O Espaço (Sobre & Café)
* Seção humanizada contando a história da profissional, o conceito do café e a atmosfera do espaço físico.

Formulário de Contato Qualificado
* Coleta estruturada de leads com campos como: Tipo de serviço desejado, Cidade/Estado e Previsão de início do projeto.

---

## 4. Fluxos do Usuário Prioritários (User Flows)
* Fluxo de Contratação de Arquitetura: `Home -> Portfólio -> Projeto Específico -> Formulário de Orçamento`

* Fluxo de E-commerce / Loja: Post no Blog -> Produto Relacionado -> Página do Produto -> Checkout / WhatsApp

---

## 5. Planejamento em Fases (MVP)
* Fase 1: Lançamento institucional (Home, Portfólio, Blog, Espaço/Café, Contato e Catálogo vitrine com redirecionamento para WhatsApp).

* Fase 2: Evolução para e-commerce completo com carrinho, gateway de pagamento e gestão de estoque.

---
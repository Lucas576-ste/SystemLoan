# SystemLoan — Plano completo do projeto

## Visão geral
Sistema web de empréstimo de ferramentas entre usuários.
- Backend: PHP 8.3 puro, API REST, MVC manual
- Frontend: React + Vite (JavaScript)
- Banco: PostgreSQL
- Auth: JWT (firebase/php-jwt)
- Raiz: C:\Users\menez\Documents\Projetos\systemLoan

---

## Requisitos acadêmicos
- Manter usuário, validação, login, criptografia de senha
- 3 funcionalidades de menor complexidade:
  1. Cadastrar ferramenta
  2. Editar ferramenta
  3. Excluir ferramenta
- 2 funcionalidades de maior complexidade:
  1. Empréstimo com regras de negócio (limite 3 ativos, status atômico)
  2. Histórico de empréstimos com filtros (status, data, ferramenta)

---

## Status das etapas

### ETAPA 1 — Estrutura e configuração ✅ CONCLUÍDA
- Estrutura de pastas backend/ e frontend/ criadas
- composer.json com firebase/php-jwt e vlucas/phpdotenv
- vendor/ gerado via composer install
- .env configurado (DB_*, JWT_SECRET, JWT_EXPIRATION)
- index.php com CORS para localhost:5173
- router.php para servidor embutido PHP
- routes/api.php com roteamento manual
- config/database.php com PDO PostgreSQL
- helpers/JwtHelper.php com encode/decode JWT
- middleware/AuthMiddleware.php com validação Bearer
- Esqueleto de controllers e models criados
- Projeto React criado com Vite + axios + react-router-dom

### ETAPA 2 — Schema do banco ✅ CONCLUÍDA
- Arquivo database/schema.sql criado e executado no pgAdmin
- Tabela users: id, name, email, password, created_at, updated_at
- Tabela tools: id, user_id(FK), name, description, is_available, created_at, updated_at
- Tabela loans: id, tool_id(FK), borrower_id(FK), status, loan_date, return_date, created_at, updated_at
- Check constraint em loans.status ('active', 'returned')
- Índices: idx_loans_borrower_id, idx_loans_tool_id, idx_loans_status
- View loan_history com JOIN de loans + tools + users

### ETAPA 3 — Autenticação com JWT ✅ CONCLUÍDA
- models/User.php: findByEmail, create, findById
- controllers/AuthController.php: register, login
- POST /api/register — cria usuário com senha bcrypt
- POST /api/login — valida senha e retorna JWT
- GET /api/me — rota protegida retorna usuário logado
- GET /api/health — endpoint de health-check retornando status 200
- Testado via curl: registro e login implementados, health funcionando

### ETAPA 4 — CRUD de ferramentas ✅ CONCLUÍDA
- Model `Tool` implementado com PDO e prepared statements:
  - `findAll()`, `findByUser()`, `findById()`, `create()`, `update()`, `delete()`
- Controller `ToolController` implementado com autenticação JWT:
  - `GET /api/tools`
  - `GET /api/tools/mine`
  - `POST /api/tools`
  - `PUT /api/tools/{id}`
  - `DELETE /api/tools/{id}`
- Ownership validado no SQL para update/delete (`id` + `user_id`)
- Validação de payload e mensagens em português
- Testes via curl realizados com sucesso (incluindo cenários de sem permissão)

### ETAPA 5 — Empréstimo e devolução ✅ CONCLUÍDA
- Model `Loan` implementado:
  - `countActive(int $borrowerId): int`
  - `create(int $toolId, int $borrowerId): ?array`
  - `findByBorrower(int $borrowerId): array`
  - `returnLoan(int $loanId, int $borrowerId): bool`
- Controller `LoanController` implementado:
  - `POST /api/loans`
  - `GET /api/loans/mine`
  - `PATCH /api/loans/{id}/return`
- Regras de negócio aplicadas:
  - Ferramenta só pode ser emprestada se `is_available = true`
  - Limite de 3 empréstimos ativos por borrower
  - Respostas `422` para violações de regra de negócio
- Transações atômicas implementadas para empréstimo e devolução:
  - Empréstimo: cria em `loans` e marca `tools.is_available = false`
  - Devolução: atualiza `loans.status = returned` e marca `tools.is_available = true`
- Testes via curl executados com sucesso para:
  - criação de empréstimo (`201`)
  - bloqueio de ferramenta indisponível (`422`)
  - listagem de empréstimos ativos (`200`)
  - devolução (`200`) e segunda devolução inválida (`403`)

### ETAPA 6 — Histórico com filtros ✅ CONCLUÍDA
- `Loan::history(int $borrowerId, array $filters)` implementado com SQL seguro (prepared statements) sobre `loan_history`
- Endpoint protegido implementado:
  - `GET /api/loans/history`
- Filtros implementados:
  - `status` (`active`/`returned`)
  - `date_from`
  - `date_to`
  - `tool` (busca parcial em nome da ferramenta)
- Validações implementadas no controller:
  - status inválido -> `400`
  - data inválida -> `400`
  - intervalo inválido (`date_from` > `date_to`) -> `400`
- Testes validados com sucesso:
  - histórico sem filtros (`200`)
  - filtros válidos (`200`)
  - cenários inválidos (`400`)

### ETAPA 7 — Frontend React: autenticação ✅ CONCLUÍDA
- `src/services/api.js` implementado com axios e interceptor Bearer token
- `src/contexts/AuthContext.jsx` implementado com estado global de sessão (`user`, `token`, `loading`)
- `src/hooks/useAuth.js` implementado para consumo simples do contexto
- Rotas de autenticação implementadas:
  - `src/pages/Login.jsx`
  - `src/pages/Register.jsx`
- Proteção de rotas implementada:
  - `src/components/PrivateRoute.jsx`
- Roteamento configurado em `src/App.jsx` com rotas públicas e privadas
- Persistência de token em `localStorage` e hidratação de sessão via `/api/me`
- Redirecionamento pós-login para `/dashboard`
- Interface de autenticação aplicada com layout clean e UX de loading/erro

### ETAPA 8 — Frontend React: telas principais ✅ CONCLUÍDA
- Dashboard implementado com 3 abas:
  - Lista de ferramentas
  - Minhas ferramentas
  - Meus empréstimos
- Páginas implementadas:
  - `src/pages/ToolList.jsx` (lista geral e empréstimo)
  - `src/pages/MyTools.jsx` (cadastro, edição, exclusão e modal)
  - `src/pages/MyLoans.jsx` (lista de ativos e devolução)
  - `src/pages/History.jsx` (histórico com filtros)
- Navegação privada implementada com:
  - `src/components/Navbar.jsx`
  - `src/components/PrivateLayout.jsx`
- Integração com backend concluída para endpoints de tools, loans e history
- Feedback visual de erro e loading aplicado nas principais ações
- Botão de empréstimo desabilitado para ferramenta indisponível
- Frontend validado com `npm run lint` e `npm run build`

### ETAPA 9 — Integração e testes finais ⏳ PENDENTE
- Testar fluxo completo: cadastro → login → cadastrar ferramenta → emprestar → devolver
- Testar regras de negócio: limite de 3 empréstimos, ferramenta indisponível
- Testar filtros do histórico
- Ajustar mensagens de erro para português
- Verificar CORS em todas as rotas
- Revisar validações de campos no frontend e backend

---

## Onde estamos agora
- Backend da API está funcional até histórico com filtros.
- Frontend está funcional com autenticação, dashboard, ferramentas, empréstimos e histórico.
- Endpoint de saúde `/api/health` está ativo e respondendo 200.
- Próxima etapa recomendada: **ETAPA 9 — Integração e testes finais**.

---

## Como rodar o projeto

### Backend
```bash
cd C:\Users\menez\Documents\Projetos\systemLoan\backend
php -S localhost:8000 router.php
```

### Frontend
```bash
cd C:\Users\menez\Documents\Projetos\systemLoan\frontend
npm run dev
```

### Banco
- PostgreSQL rodando localmente na porta 5432
- Banco: systemloan
- Schema em: database/schema.sql

---

## Estimativa de tempo restante
Com 1 a 2 horas por dia:
- Etapa 9 — Integração e testes: 1 a 2 dias
- Total estimado restante: 2 a 4 dias

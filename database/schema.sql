-- ==========================================================
-- SystemLoan - PostgreSQL schema
-- Este script cria o schema base para o sistema de emprestimo
-- de ferramentas entre usuarios.
-- Ordem:
-- 1) DROP VIEW/TABLES
-- 2) CREATE TABLES
-- 3) CREATE INDEXES
-- 4) CREATE VIEW
-- ==========================================================

-- ==========================================================
-- Limpeza de objetos existentes (ordem segura para recriacao)
-- ==========================================================
DROP VIEW IF EXISTS loan_history;

DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS tool_images;
DROP TABLE IF EXISTS tools;
DROP TABLE IF EXISTS users;

-- ==========================================================
-- Tabela: users
-- Armazena os usuarios do sistema e suas credenciais.
-- Campo password deve receber hash bcrypt gerado na aplicacao.
-- ==========================================================
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================================
-- Tabela: tools
-- Armazena ferramentas cadastradas por usuarios proprietarios.
-- is_available = TRUE significa disponivel para emprestimo.
-- ==========================================================
CREATE TABLE tools (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================================
-- Tabela: tool_images
-- Armazena as imagens das ferramentas (ate 4 por ferramenta).
-- image_path guarda o caminho relativo servido pelo backend.
-- ==========================================================
CREATE TABLE tool_images (
    id SERIAL PRIMARY KEY,
    tool_id INTEGER NOT NULL REFERENCES tools(id) ON DELETE CASCADE,
    image_path VARCHAR(255) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================================
-- Tabela: loans
-- Registra emprestimos de ferramentas entre usuarios.
-- status: 'active' (emprestimo em andamento) ou 'returned'.
-- ==========================================================
CREATE TABLE loans (
    id SERIAL PRIMARY KEY,
    tool_id INTEGER NOT NULL REFERENCES tools(id) ON DELETE CASCADE,
    borrower_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'active',
    loan_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    return_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_loans_status CHECK (status IN ('active', 'returned'))
);

-- ==========================================================
-- Indices para performance das consultas principais
-- ==========================================================
CREATE INDEX idx_loans_borrower_id ON loans (borrower_id);
CREATE INDEX idx_loans_tool_id ON loans (tool_id);
CREATE INDEX idx_loans_status ON loans (status);
CREATE INDEX idx_tool_images_tool_id ON tool_images (tool_id);

-- ==========================================================
-- View: loan_history
-- Historico consolidado de emprestimos com dados da ferramenta
-- e do usuario que pegou emprestado.
-- ==========================================================
CREATE VIEW loan_history AS
SELECT
    l.id AS loan_id,
    t.name AS tool_name,
    t.description AS tool_description,
    u.name AS borrower_name,
    u.email AS borrower_email,
    l.status,
    l.loan_date,
    l.return_date
FROM loans l
JOIN tools t ON t.id = l.tool_id
JOIN users u ON u.id = l.borrower_id;

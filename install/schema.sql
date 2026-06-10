-- ============================================================
-- BANCO DE DADOS: SiteCatalogo
-- Sistema Catalogo Profissional com Painel Admin
-- Convertido de Node.js para PHP
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- TABELA: sc_usuarios (Administradores locais)
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_completo VARCHAR(150) NOT NULL,
    login VARCHAR(50) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    telefone VARCHAR(20) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    nivel ENUM('admin', 'gerente', 'vendedor') DEFAULT 'vendedor',
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    ultimo_acesso TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_login (login),
    KEY idx_status (status),
    KEY idx_nivel (nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_clientes
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_razaosocial VARCHAR(200) NOT NULL,
    tipo_pessoa ENUM('fisica', 'juridica') DEFAULT 'fisica',
    cpf_cnpj VARCHAR(20) DEFAULT NULL,
    rg_ie VARCHAR(20) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    telefone VARCHAR(20) DEFAULT NULL,
    celular VARCHAR(20) DEFAULT NULL,
    cep VARCHAR(10) DEFAULT NULL,
    endereco VARCHAR(200) DEFAULT NULL,
    numero VARCHAR(20) DEFAULT NULL,
    complemento VARCHAR(100) DEFAULT NULL,
    bairro VARCHAR(100) DEFAULT NULL,
    cidade VARCHAR(100) DEFAULT NULL,
    estado CHAR(2) DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cpf_cnpj (cpf_cnpj),
    KEY idx_nome (nome_razaosocial),
    KEY idx_tipo (tipo_pessoa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_categorias
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_categorias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    descricao TEXT DEFAULT NULL,
    imagem VARCHAR(255) DEFAULT NULL,
    icone VARCHAR(50) DEFAULT NULL,
    ordem INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_slug (slug),
    KEY idx_ativo (ativo),
    KEY idx_ordem (ordem),
    KEY idx_parent (parent_id),
    FOREIGN KEY (parent_id) REFERENCES sc_categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_produtos
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_produtos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    descricao_curta VARCHAR(500) DEFAULT NULL,
    descricao_completa LONGTEXT DEFAULT NULL,
    sku VARCHAR(50) DEFAULT NULL,
    preco DECIMAL(15,2) DEFAULT 0.00,
    preco_promocional DECIMAL(15,2) DEFAULT NULL,
    custo DECIMAL(15,2) DEFAULT NULL,
    unidade VARCHAR(20) DEFAULT 'un',
    peso DECIMAL(10,3) DEFAULT NULL,
    largura DECIMAL(10,2) DEFAULT NULL,
    altura DECIMAL(10,2) DEFAULT NULL,
    profundidade DECIMAL(10,2) DEFAULT NULL,
    quantidade_estoque INT DEFAULT 0,
    estoque_minimo INT DEFAULT 0,
    destaque TINYINT(1) DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    categoria_id BIGINT UNSIGNED DEFAULT NULL,
    tags VARCHAR(500) DEFAULT NULL,
    seo_title VARCHAR(200) DEFAULT NULL,
    seo_description VARCHAR(500) DEFAULT NULL,
    seo_keywords VARCHAR(300) DEFAULT NULL,
    imagem_principal VARCHAR(255) DEFAULT NULL,
    visualizacoes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_slug (slug),
    UNIQUE KEY uk_sku (sku),
    KEY idx_ativo (ativo),
    KEY idx_destaque (destaque),
    KEY idx_categoria (categoria_id),
    KEY idx_preco (preco),
    FULLTEXT KEY ft_nome_desc (nome, descricao_curta, descricao_completa),
    FOREIGN KEY (categoria_id) REFERENCES sc_categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_produto_imagens
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_produto_imagens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id BIGINT UNSIGNED NOT NULL,
    imagem VARCHAR(255) NOT NULL,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_produto (produto_id),
    FOREIGN KEY (produto_id) REFERENCES sc_produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_produto_estoque
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_produto_estoque (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('entrada', 'saida', 'ajuste', 'inicial') NOT NULL,
    quantidade INT NOT NULL,
    quantidade_anterior INT DEFAULT 0,
    motivo VARCHAR(255) DEFAULT NULL,
    usuario_id BIGINT UNSIGNED DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_produto (produto_id),
    KEY idx_tipo (tipo),
    KEY idx_created (created_at),
    FOREIGN KEY (produto_id) REFERENCES sc_produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES sc_usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_banners
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_banners (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(200) NOT NULL,
    subtitulo VARCHAR(500) DEFAULT NULL,
    imagem VARCHAR(255) NOT NULL,
    link VARCHAR(500) DEFAULT NULL,
    texto_botao VARCHAR(50) DEFAULT 'Saiba Mais',
    posicao ENUM('home_topo', 'home_meio', 'home_rodape', 'sidebar') DEFAULT 'home_topo',
    ordem INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    data_inicio DATE DEFAULT NULL,
    data_fim DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_posicao (posicao),
    KEY idx_ativo (ativo),
    KEY idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_orcamentos
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_orcamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(20) NOT NULL,
    cliente_nome VARCHAR(200) NOT NULL,
    cliente_email VARCHAR(150) DEFAULT NULL,
    cliente_telefone VARCHAR(20) DEFAULT NULL,
    cliente_cpf_cnpj VARCHAR(20) DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    status ENUM('novo', 'pendente', 'em_analise', 'respondido', 'aprovado', 'rejeitado', 'cancelado') DEFAULT 'novo',
    tipo_contato ENUM('whatsapp', 'email', 'telefone') DEFAULT 'whatsapp',
    valor_total DECIMAL(15,2) DEFAULT 0.00,
    usuario_id BIGINT UNSIGNED DEFAULT NULL,
    data_resposta TIMESTAMP NULL DEFAULT NULL,
    resposta TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_codigo (codigo),
    KEY idx_status (status),
    KEY idx_created (created_at),
    KEY idx_cliente_nome (cliente_nome),
    FOREIGN KEY (usuario_id) REFERENCES sc_usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_orcamento_itens
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_orcamento_itens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    orcamento_id BIGINT UNSIGNED NOT NULL,
    produto_id BIGINT UNSIGNED DEFAULT NULL,
    produto_nome VARCHAR(200) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    preco_unitario DECIMAL(15,2) DEFAULT 0.00,
    subtotal DECIMAL(15,2) DEFAULT 0.00,
    observacao VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orcamento (orcamento_id),
    FOREIGN KEY (orcamento_id) REFERENCES sc_orcamentos(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES sc_produtos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_configuracoes
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_configuracoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    chave VARCHAR(100) NOT NULL,
    valor LONGTEXT DEFAULT NULL,
    descricao VARCHAR(255) DEFAULT NULL,
    grupo VARCHAR(50) DEFAULT 'geral',
    tipo ENUM('text', 'textarea', 'number', 'boolean', 'select', 'color', 'file') DEFAULT 'text',
    opcoes TEXT DEFAULT NULL,
    ordem INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_chave (chave),
    KEY idx_grupo (grupo),
    KEY idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_logs_atividade
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_logs_atividade (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED DEFAULT NULL,
    usuario_nome VARCHAR(150) DEFAULT NULL,
    acao VARCHAR(50) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    descricao TEXT DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_usuario (usuario_id),
    KEY idx_acao (acao),
    KEY idx_modulo (modulo),
    KEY idx_created (created_at),
    FOREIGN KEY (usuario_id) REFERENCES sc_usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_seo_pages
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_seo_pages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pagina VARCHAR(100) NOT NULL,
    url VARCHAR(255) DEFAULT NULL,
    title VARCHAR(200) DEFAULT NULL,
    description VARCHAR(500) DEFAULT NULL,
    keywords VARCHAR(300) DEFAULT NULL,
    og_image VARCHAR(255) DEFAULT NULL,
    og_title VARCHAR(200) DEFAULT NULL,
    og_description VARCHAR(500) DEFAULT NULL,
    canonical VARCHAR(255) DEFAULT NULL,
    robots VARCHAR(50) DEFAULT 'index,follow',
    schema_json LONGTEXT DEFAULT NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pagina (pagina),
    KEY idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: sc_newsletter
-- ============================================================
CREATE TABLE IF NOT EXISTS sc_newsletter (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(150) NOT NULL,
    nome VARCHAR(100) DEFAULT NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SiteCatalogo - Banco de Dados Completo
-- Prefixo: sc_
-- Charset: utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Tabela: sc_usuarios
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_usuarios`;
CREATE TABLE `sc_usuarios` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('admin','gerente','vendedor') DEFAULT 'vendedor',
  `status` enum('ativo','inativo','bloqueado') DEFAULT 'ativo',
  `ultimo_acesso` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sc_usuarios` (`nome`, `email`, `senha`, `role`, `status`) VALUES
('Administrador', 'admin@sitecatalogo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ativo');

-- --------------------------------------------------------
-- Tabela: sc_categorias
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_categorias`;
CREATE TABLE `sc_categorias` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `descricao` text,
  `imagem` varchar(255) DEFAULT NULL,
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabela: sc_produtos
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_produtos`;
CREATE TABLE `sc_produtos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) unsigned DEFAULT NULL,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `descricao` text,
  `descricao_curta` varchar(500) DEFAULT NULL,
  `imagem_principal` varchar(255) DEFAULT NULL,
  `imagens` text,
  `preco` decimal(10,2) DEFAULT 0.00,
  `preco_promocional` decimal(10,2) DEFAULT NULL,
  `quantidade_estoque` int(11) DEFAULT 0,
  `estoque_minimo` int(11) DEFAULT 5,
  `sku` varchar(100) DEFAULT NULL,
  `visualizacoes` int(11) DEFAULT 0,
  `destaque` tinyint(1) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `sc_categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabela: sc_clientes (ATUALIZADA)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_clientes`;
CREATE TABLE `sc_clientes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome_razaosocial` varchar(255) NOT NULL,
  `tipo_pessoa` enum('fisica','juridica') DEFAULT 'fisica',
  `cpf_cnpj` varchar(20) DEFAULT NULL,
  `rg_ie` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `observacoes` text,
  `categoria` varchar(50) DEFAULT 'cliente_final',
  `foto` varchar(255) DEFAULT '',
  `status` varchar(20) DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `nome_razaosocial` (`nome_razaosocial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabela: sc_orcamentos
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_orcamentos`;
CREATE TABLE `sc_orcamentos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `cliente_nome` varchar(255) NOT NULL,
  `cliente_email` varchar(255) DEFAULT NULL,
  `cliente_telefone` varchar(20) DEFAULT NULL,
  `cliente_empresa` varchar(255) DEFAULT NULL,
  `status` enum('novo','em_analise','aprovado','rejeitado','concluido','cancelado') DEFAULT 'novo',
  `observacoes` text,
  `total` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabela: sc_orcamento_itens
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_orcamento_itens`;
CREATE TABLE `sc_orcamento_itens` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `orcamento_id` int(11) unsigned NOT NULL,
  `produto_id` int(11) unsigned DEFAULT NULL,
  `produto_nome` varchar(255) NOT NULL,
  `quantidade` int(11) DEFAULT 1,
  `preco_unitario` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `orcamento_id` (`orcamento_id`),
  CONSTRAINT `fk_itens_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `sc_orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabela: sc_configuracoes (SEM EMAIL - movido para pagina Email)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_configuracoes`;
CREATE TABLE `sc_configuracoes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text,
  `descricao` varchar(255) DEFAULT NULL,
  `grupo` varchar(50) DEFAULT 'geral',
  `tipo` enum('text','textarea','file','select','number','color') DEFAULT 'text',
  `opcoes` text,
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sc_configuracoes` (`chave`, `valor`, `descricao`, `grupo`, `tipo`, `ordem`, `ativo`) VALUES
('site_nome', 'SiteCatalogo', 'Nome do site', 'geral', 'text', 1, 1),
('site_descricao', 'Catalogo de produtos online', 'Descricao do site', 'geral', 'textarea', 2, 1),
('whatsapp', '', 'WhatsApp para contato', 'contato', 'text', 1, 1),
('email_contato', '', 'Email de contato', 'contato', 'text', 2, 1),
('endereco', '', 'Endereco da empresa', 'contato', 'textarea', 3, 1),
('facebook', '', 'Facebook', 'social', 'text', 1, 1),
('instagram', '', 'Instagram', 'social', 'text', 2, 1),
('linkedin', '', 'LinkedIn', 'social', 'text', 3, 1),
('cor_primaria', '#3b82f6', 'Cor primaria', 'aparencia', 'color', 1, 1),
('logo_cliente', '', 'Logo do cliente', 'aparencia', 'file', 2, 1),
('navbar_tipo', 'imagem_texto', 'Tipo de navbar', 'aparencia', 'select', 3, 1),
('mostrar_preco', '1', 'Mostrar precos no site', 'geral', 'select', 5, 1);

-- --------------------------------------------------------
-- Tabela: sc_atividades_log
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_atividades_log`;
CREATE TABLE `sc_atividades_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `acao` varchar(50) DEFAULT NULL,
  `tabela` varchar(50) DEFAULT NULL,
  `descricao` text,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabela: sc_emails (NOVA - Caixa de Entrada)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_emails`;
CREATE TABLE `sc_emails` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `remetente_nome` varchar(255) DEFAULT '',
  `remetente_email` varchar(255) DEFAULT '',
  `destinatario_email` varchar(255) DEFAULT '',
  `assunto` varchar(500) DEFAULT '',
  `corpo` text,
  `corpo_html` text,
  `pasta` enum('inbox','sent','drafts','trash','spam','archive') DEFAULT 'inbox',
  `status` enum('nao_lido','lido','respondido','encaminhado') DEFAULT 'nao_lido',
  `starred` tinyint(1) DEFAULT 0,
  `anexos` text,
  `message_id` varchar(255) DEFAULT NULL,
  `in_reply_to` varchar(255) DEFAULT NULL,
  `data_envio` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_recebimento` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pasta` (`pasta`),
  KEY `idx_status` (`status`),
  KEY `idx_starred` (`starred`),
  KEY `idx_data` (`data_envio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabela: sc_email_contas (NOVA - Contas de Email)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_email_contas`;
CREATE TABLE `sc_email_contas` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) DEFAULT '',
  `email` varchar(255) NOT NULL,
  `tipo` enum('gmail','outlook','yahoo','proprio') DEFAULT 'gmail',
  `imap_host` varchar(255) DEFAULT '',
  `imap_port` int(11) DEFAULT 993,
  `imap_secure` enum('ssl','tls','none') DEFAULT 'ssl',
  `smtp_host` varchar(255) DEFAULT '',
  `smtp_port` int(11) DEFAULT 587,
  `smtp_secure` enum('tls','ssl','none') DEFAULT 'tls',
  `usuario` varchar(255) DEFAULT '',
  `senha` text,
  `ativo` tinyint(1) DEFAULT 1,
  `padrao` tinyint(1) DEFAULT 0,
  `ultimo_sync` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
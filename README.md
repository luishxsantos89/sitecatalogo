# SiteCatalogo - Sistema de Catalogo Profissional

SiteCatalogo e um sistema completo de catalogo de produtos com painel administrativo, desenvolvido em PHP puro (sem frameworks).

## Requisitos

- PHP 8.0+
- MySQL 5.7+ ou MariaDB 10.3+
- Apache com mod_rewrite habilitado
- Extensoes PHP: PDO, PDO_MySQL, GD (para imagens)

## Instalacao

1. **Upload dos arquivos** para seu servidor
2. **Acesse o instalador**: `http://seusite.com/install/`
3. **Siga os passos**:
   - Passo 1: Configure o banco de dados (MySQL)
   - Passo 2: Configure as informacoes do site
   - Passo 3: Crie a conta de administrador
4. **Remova a pasta `/install/`** por seguranca apos a instalacao

## Acesso

- **Site publico**: `http://seusite.com/`
- **Painel Admin**: `http://seusite.com/admin/`

## Funcionalidades

### Site Publico
- Catalogo de produtos com busca e filtros
- Carrinho de orcamentos (envio via WhatsApp/Email)
- Banners rotativos na home
- Categorias de produtos
- Design responsivo

### Painel Administrativo
- **Dashboard**: Estatisticas e visao geral
- **Produtos**: CRUD completo com imagens, precos, estoque, SEO
- **Categorias**: Organizacao hierarquica
- **Banners**: Gerenciamento de banners por posicao
- **Orcamentos**: Gestao de solicitacoes com resposta
- **Estoque**: Controle de entrada, saida e ajustes
- **Clientes**: Cadastro completo
- **Usuarios**: Multiplos niveis (Admin, Gerente, Vendedor)
- **Configuracoes**: Personalizacao do site
- **SEO**: Otimizacao para mecanismos de busca

## Estrutura de Diretorios

```
sitecatalogo-php/
├── admin/              # Painel administrativo
│   ├── includes/       # Header, footer, funcoes
│   ├── assets/         # CSS, JS do admin
│   ├── index.php       # Dashboard
│   ├── login.php       # Tela de login
│   ├── produtos.php    # CRUD de produtos
│   ├── categorias.php  # CRUD de categorias
│   ├── banners.php     # CRUD de banners
│   ├── orcamentos.php  # Gestao de orcamentos
│   ├── estoque.php     # Controle de estoque
│   ├── clientes.php    # CRUD de clientes
│   ├── usuarios.php    # CRUD de usuarios
│   ├── configuracoes.php
│   ├── seo.php
│   └── perfil.php
├── api/                # Endpoints da API
│   └── orcamento.php
├── assets/             # Assets publicos
│   ├── css/
│   └── images/
├── includes/           # Funcoes globais
│   ├── db.php
│   └── functions.php
├── install/            # Instalador
│   ├── index.php
│   ├── style.css
│   └── schema.sql
├── uploads/            # Uploads de imagens
├── config.php          # Configuracoes (gerado pelo instalador)
├── .htaccess
├── index.php           # Pagina principal
└── README.md
```

## Niveis de Usuario

- **Administrador**: Acesso total ao sistema
- **Gerente**: Acesso a produtos, orcamentos, clientes, estoque
- **Vendedor**: Acesso a orcamentos e clientes

## Seguranca

- Senhas criptografadas com bcrypt
- Protecao CSRF nos formularios
- SQL Injection prevention via prepared statements
- XSS protection via htmlspecialchars
- Protecao de arquivos sensíveis via .htaccess

## Licenca

Este projeto e de uso livre.

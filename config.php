<?php
/**
 * SiteCatalogo - Configuracoes
 * Gerado automaticamente pelo instalador
 * NAO EDITE MANUALMENTE!
 */

define('INSTALLED', true);
define('INSTALL_DATE', '2026-06-08 23:48:28');
define('VERSION', '1.0.0');

define('DB_HOST', 'localhost');
define('DB_NAME', 'sitecatalogo');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PREFIX', 'sc_');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'Modelo Site Catalogo');
define('SITE_DESCRIPTION', 'Catálogo Comercial Inteligente — Produtos, Preços e Orçamentos em um Só Lugar');
define('SITE_EMAIL', 'luishxsantos89@gmail.com');
define('WHATSAPP', '21973408712');
define('CURRENCY', 'BRL');
define('TIMEZONE', 'America/Sao_Paulo');

define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . '/');
define('ADMIN_URL', SITE_URL . 'admin/');
define('ASSETS_URL', SITE_URL . 'assets/');
define('UPLOADS_URL', SITE_URL . 'uploads/');

define('ROOT_DIR', dirname(__FILE__));
define('UPLOADS_DIR', ROOT_DIR . '/uploads');
define('ADMIN_DIR', ROOT_DIR . '/admin');

define('SESSION_NAME', 'sitecatalogo_session');
define('SESSION_LIFETIME', 7200);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_HASH_COST', 12);

define('ITEMS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 20);

define('WHATSAPP_DEFAULT_MSG', 'Ola! Gostaria de solicitar um orcamento para os seguintes produtos:');

define('DEBUG_MODE', true);

date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
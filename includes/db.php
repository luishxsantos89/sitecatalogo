<?php
/**
 * SiteCatalogo - Classe de Conexao com Banco de Dados
 * Usa PDO para MySQL
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__FILE__)));
}

// Carregar config se existir
if (file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

class Database {
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $host = defined('DB_HOST') ? DB_HOST : 'localhost';
                $name = defined('DB_NAME') ? DB_NAME : '';
                $user = defined('DB_USER') ? DB_USER : '';
                $pass = defined('DB_PASS') ? DB_PASS : '';
                
                self::$instance = new PDO(
                    "mysql:host={$host};dbname={$name};charset=utf8mb4",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]
                );
            } catch (PDOException $e) {
                die('Erro de conexao com o banco de dados: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }
    
    public static function prefix(string $table): string {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sc_';
        return $prefix . $table;
    }
    
    // Evitar clone
    private function __clone() {}
}

// Helper function
function db(): PDO {
    return Database::getInstance();
}

function table(string $name): string {
    return Database::prefix($name);
}

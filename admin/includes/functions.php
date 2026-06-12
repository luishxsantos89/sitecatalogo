<?php
/**
 * SiteCatalogo - Funcoes do Painel Admin
 */

require_once dirname(dirname(dirname(__FILE__))) . '/includes/functions.php';

// Verificar autenticacao em todas as paginas admin
session_check();
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Pagina atual para menu ativo
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Contadores para dashboard
function get_counts(): array {
    try {
        $produtos = db()->query("SELECT COUNT(*) FROM " . table('produtos'))->fetchColumn();
        $categorias = db()->query("SELECT COUNT(*) FROM " . table('categorias'))->fetchColumn();
        $orcamentos_novos = db()->query("SELECT COUNT(*) FROM " . table('orcamentos') . " WHERE status = 'novo'")->fetchColumn();
        $orcamentos_total = db()->query("SELECT COUNT(*) FROM " . table('orcamentos'))->fetchColumn();
        $clientes = db()->query("SELECT COUNT(*) FROM " . table('clientes'))->fetchColumn();
        $usuarios = db()->query("SELECT COUNT(*) FROM " . table('usuarios') . " WHERE status = 'ativo'")->fetchColumn();
        $estoque_baixo = db()->query("SELECT COUNT(*) FROM " . table('produtos') . " WHERE quantidade_estoque <= estoque_minimo AND ativo = 1")->fetchColumn();
        
        return compact('produtos', 'categorias', 'orcamentos_novos', 'orcamentos_total', 'clientes', 'usuarios', 'estoque_baixo');
    } catch (Exception $e) {
        return array_fill_keys(['produtos', 'categorias', 'orcamentos_novos', 'orcamentos_total', 'clientes', 'usuarios', 'estoque_baixo'], 0);
    }
}

// Contar orcamentos pendentes
function count_orcamentos_pendentes(): int {
    try {
        return (int) db()->query("SELECT COUNT(*) FROM " . table('orcamentos') . " WHERE status = 'novo'")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// Upload helper
function handle_upload(array $file, string $folder = 'produtos'): ?string {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    return upload_file($file, $folder, $allowed);
}

// Gerar slug unico
function unique_slug(string $table, string $slug, ?int $exclude_id = null): string {
    $original = $slug;
    $counter = 1;
    
    while (true) {
        $sql = "SELECT id FROM " . table($table) . " WHERE slug = ?";
        $params = [$slug];
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $original . '-' . $counter;
        $counter++;
    }
}

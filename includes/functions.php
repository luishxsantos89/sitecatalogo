<?php
/**
 * SiteCatalogo - Funcoes Globais
 */

require_once __DIR__ . '/db.php';

// ==================== SEGURANCA ====================

function sanitize(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_validate(): bool {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// ==================== SESSAO ====================

function session_check(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(defined('SESSION_NAME') ? SESSION_NAME : 'sitecatalogo_session');
        session_start();
    }
}

function is_logged_in(): bool {
    session_check();
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
}

function require_auth(): void {
    if (!is_logged_in()) {
        header('Location: ' . (defined('ADMIN_URL') ? ADMIN_URL : '/admin/') . 'login.php');
        exit;
    }
}

function check_permission(string $min_level = 'vendedor'): bool {
    if (!is_logged_in()) return false;
    
    $levels = ['vendedor' => 1, 'gerente' => 2, 'admin' => 3];
    $user_level = $_SESSION['admin_nivel'] ?? 'vendedor';
    
    return ($levels[$user_level] ?? 0) >= ($levels[$min_level] ?? 0);
}

// ==================== URLS ====================

function site_url(string $path = ''): string {
    $base = defined('SITE_URL') ? SITE_URL : '/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function admin_url(string $path = ''): string {
    $base = defined('ADMIN_URL') ? ADMIN_URL : '/admin/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function assets_url(string $path = ''): string {
    $base = defined('ASSETS_URL') ? ASSETS_URL : '/assets/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function uploads_url(string $path = ''): string {
    $base = defined('UPLOADS_URL') ? UPLOADS_URL : '/uploads/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function uploads_path(string $path = ''): string {
    $base = defined('UPLOADS_PATH') ? UPLOADS_PATH : ROOT_PATH . '/uploads/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

// ==================== FORMATACAO ====================

function format_currency(float $value, string $currency = 'BRL'): string {
    if ($currency === 'USD') {
        return '$ ' . number_format($value, 2, '.', ',');
    } elseif ($currency === 'EUR') {
        return '€ ' . number_format($value, 2, ',', '.');
    }
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function format_date(?string $date, string $format = 'd/m/Y H:i'): string {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

function format_phone(?string $phone): string {
    if (empty($phone)) return '-';
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 11) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
    } elseif (strlen($phone) === 10) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
    }
    return $phone;
}

function format_cpf_cnpj(?string $doc): string {
    if (empty($doc)) return '-';
    $doc = preg_replace('/\D/', '', $doc);
    if (strlen($doc) === 11) {
        return substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9);
    } elseif (strlen($doc) === 14) {
        return substr($doc, 0, 2) . '.' . substr($doc, 2, 3) . '.' . substr($doc, 5, 3) . '/' . substr($doc, 8, 4) . '-' . substr($doc, 12);
    }
    return $doc;
}

function slugify(string $text): string {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
    $text = strtolower(trim($text));
    $text = preg_replace('/[\s-]+/', '-', $text);
    return $text;
}

// ==================== UPLOAD ====================

function upload_file(array $file, string $folder = 'produtos', array $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp']): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return null;
    
    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $path = uploads_path($folder . '/' . $filename);
    
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $folder . '/' . $filename;
    }
    return null;
}

function delete_upload(string $path): bool {
    $full = uploads_path($path);
    if (file_exists($full)) {
        return unlink($full);
    }
    return false;
}

// ==================== PAGINACAO ====================

function paginate(int $total, int $page = 1, int $per_page = 12): array {
    $page = max(1, $page);
    $per_page = max(1, $per_page);
    $total_pages = (int) ceil($total / $per_page);
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;
    
    return [
        'page' => $page,
        'per_page' => $per_page,
        'total' => $total,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $total_pages,
        'prev_page' => $page - 1,
        'next_page' => $page + 1,
        'start' => $offset + 1,
        'end' => min($offset + $per_page, $total)
    ];
}

function pagination_links(array $pagination, string $base_url, array $params = []): string {
    if ($pagination['total_pages'] <= 1) return '';
    
    $query = http_build_query($params);
    $sep = $query ? '&' : '';
    
    $html = '<nav class="pagination-nav"><div class="pagination">';
    
    // Previous
    if ($pagination['has_prev']) {
        $html .= '<a href="' . $base_url . '?' . $query . $sep . 'page=' . $pagination['prev_page'] . '" class="page-link prev">&larr; Anterior</a>';
    } else {
        $html .= '<span class="page-link prev disabled">&larr; Anterior</span>';
    }
    
    // Pages
    $start = max(1, $pagination['page'] - 2);
    $end = min($pagination['total_pages'], $pagination['page'] + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $pagination['page']) {
            $html .= '<span class="page-link active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $base_url . '?' . $query . $sep . 'page=' . $i . '" class="page-link">' . $i . '</a>';
        }
    }
    
    // Next
    if ($pagination['has_next']) {
        $html .= '<a href="' . $base_url . '?' . $query . $sep . 'page=' . $pagination['next_page'] . '" class="page-link next">Proximo &rarr;</a>';
    } else {
        $html .= '<span class="page-link next disabled">Proximo &rarr;</span>';
    }
    
    $html .= '</div></nav>';
    return $html;
}

// ==================== FLASH MESSAGES ====================

function set_flash(string $type, string $message): void {
    session_check();
    $_SESSION['flash'][$type] = $message;
}

function get_flash(): array {
    session_check();
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

function show_flash(): string {
    $flash = get_flash();
    if (empty($flash)) return '';
    
    $html = '';
    foreach ($flash as $type => $message) {
        $class = $type === 'error' ? 'alert-danger' : 'alert-' . $type;
        $html .= '<div class="alert ' . $class . '">' . sanitize($message) . '</div>';
    }
    return $html;
}

// ==================== CONFIGURACOES ====================

function get_config(string $key, $default = null) {
    try {
        $stmt = db()->prepare("SELECT valor FROM " . table('configuracoes') . " WHERE chave = ? AND ativo = 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['valor'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function set_config(string $key, string $value): bool {
    try {
        $stmt = db()->prepare("UPDATE " . table('configuracoes') . " SET valor = ? WHERE chave = ?");
        return $stmt->execute([$value, $key]);
    } catch (Exception $e) {
        return false;
    }
}

// ==================== LOGS ====================

function log_activity(string $acao, string $modulo, string $descricao = ''): void {
    try {
        $user_id = $_SESSION['admin_id'] ?? null;
        $user_name = $_SESSION['admin_nome'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = db()->prepare("INSERT INTO " . table('logs_atividade') . " 
            (usuario_id, usuario_nome, acao, modulo, descricao, ip, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $user_name, $acao, $modulo, $descricao, $ip, $ua]);
    } catch (Exception $e) {
        // Silenciar erro de log
    }
}

// ==================== HELPERS HTML ====================

function selected($current, $value): string {
    return $current == $value ? 'selected' : '';
}

function checked($condition): string {
    return $condition ? 'checked' : '';
}

function active_class(string $current, string $page): string {
    return $current === $page ? 'active' : '';
}

// ==================== WHATSAPP ====================

function whatsapp_link(string $phone, string $message = ''): string {
    $phone = preg_replace('/\D/', '', $phone);
    $url = 'https://wa.me/' . $phone;
    if ($message) {
        $url .= '?text=' . urlencode($message);
    }
    return $url;
}

<?php
/**
 * SiteCatalogo - Email (Caixa de Entrada)
 */
require_once __DIR__ . '/includes/functions.php';

// Criar tabela emails se nao existir
try {
    $check = db()->query("SHOW TABLES LIKE '" . table('emails') . "'");
    if ($check->rowCount() === 0) {
        db()->exec("CREATE TABLE IF NOT EXISTS " . table('emails') . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            remetente_nome VARCHAR(255) DEFAULT '',
            remetente_email VARCHAR(255) DEFAULT '',
            destinatario_email VARCHAR(255) DEFAULT '',
            assunto VARCHAR(500) DEFAULT '',
            corpo TEXT,
            corpo_html TEXT,
            pasta ENUM('inbox','sent','drafts','trash','spam','archive') DEFAULT 'inbox',
            status ENUM('nao_lido','lido','respondido','encaminhado') DEFAULT 'nao_lido',
            starred TINYINT(1) DEFAULT 0,
            anexos TEXT,
            message_id VARCHAR(255) DEFAULT NULL,
            in_reply_to VARCHAR(255) DEFAULT NULL,
            data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_recebimento DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pasta (pasta),
            INDEX idx_status (status),
            INDEX idx_starred (starred),
            INDEX idx_data (data_envio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
} catch (Exception $e) {
    // Tabela ja existe ou erro de permissao
}

// Criar tabela email_contas se nao existir
try {
    $check2 = db()->query("SHOW TABLES LIKE '" . table('email_contas') . "'");
    if ($check2->rowCount() === 0) {
        db()->exec("CREATE TABLE IF NOT EXISTS " . table('email_contas') . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) DEFAULT '',
            email VARCHAR(255) NOT NULL,
            tipo ENUM('gmail','outlook','yahoo','proprio') DEFAULT 'gmail',
            imap_host VARCHAR(255) DEFAULT '',
            imap_port INT DEFAULT 993,
            imap_secure ENUM('ssl','tls','none') DEFAULT 'ssl',
            smtp_host VARCHAR(255) DEFAULT '',
            smtp_port INT DEFAULT 587,
            smtp_secure ENUM('tls','ssl','none') DEFAULT 'tls',
            usuario VARCHAR(255) DEFAULT '',
            senha TEXT,
            ativo TINYINT(1) DEFAULT 1,
            padrao TINYINT(1) DEFAULT 0,
            ultimo_sync DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
} catch (Exception $e) {
    // Tabela ja existe ou erro de permissao
}

$page_title = 'Email';
$view = $_GET['view'] ?? 'inbox';
$email_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$folder = $_GET['folder'] ?? 'inbox';

// ===== ACAO: ENVIAR EMAIL =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    if ($acao === 'enviar') {
        try {
            $to = trim($_POST['to'] ?? '');
            $cc = trim($_POST['cc'] ?? '');
            $bcc = trim($_POST['bcc'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $conta_id = (int)($_POST['conta_id'] ?? 0);

            if (empty($to) || empty($subject)) {
                set_flash('error', 'Destinatario e assunto sao obrigatorios!');
            } else {
                $anexos = [];
                if (!empty($_FILES['attachments']['name'][0])) {
                    foreach ($_FILES['attachments']['name'] as $i => $name) {
                        if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                            $up = handle_upload([
                                'name' => $name,
                                'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                                'error' => $_FILES['attachments']['error'][$i]
                            ], 'email_attachments');
                            if ($up) $anexos[] = $up;
                        }
                    }
                }

                db()->prepare("INSERT INTO " . table('emails') . " 
                    (remetente_nome, remetente_email, destinatario_email, assunto, corpo, pasta, status, anexos, data_envio) 
                    VALUES (?, ?, ?, ?, ?, 'sent', 'lido', ?, NOW())")
                    ->execute([
                        get_config('email_from_name') ?: 'Admin',
                        get_config('email_from_email') ?: 'admin@site.com',
                        $to,
                        $subject,
                        $message,
                        json_encode($anexos)
                    ]);

                log_activity('create', 'emails', "Email enviado para {$to}");
                set_flash('success', 'Email enviado com sucesso!');
                header('Location: email.php?view=inbox&folder=sent'); exit;
            }
        } catch (Exception $e) {
            set_flash('error', 'Erro ao enviar: ' . $e->getMessage());
        }
    }

    if ($acao === 'salvar_conta') {
        try {
            $dados = [
                'nome' => trim($_POST['nome'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'tipo' => $_POST['tipo'] ?? 'gmail',
                'imap_host' => trim($_POST['imap_host'] ?? ''),
                'imap_port' => (int)($_POST['imap_port'] ?? 993),
                'imap_secure' => $_POST['imap_secure'] ?? 'ssl',
                'smtp_host' => trim($_POST['smtp_host'] ?? ''),
                'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
                'smtp_secure' => $_POST['smtp_secure'] ?? 'tls',
                'usuario' => trim($_POST['usuario'] ?? ''),
                'senha' => $_POST['senha'] ?? '',
                'ativo' => 1,
            ];

            $id_conta = (int)($_POST['conta_id'] ?? 0);
            if ($id_conta) {
                $fields = []; $vals = [];
                foreach ($dados as $k => $v) { $fields[] = "{$k} = ?"; $vals[] = $v; }
                $vals[] = $id_conta;
                db()->prepare("UPDATE " . table('email_contas') . " SET " . implode(', ', $fields) . " WHERE id = ?")->execute($vals);
                set_flash('success', 'Conta atualizada!');
            } else {
                $cols = implode(', ', array_keys($dados));
                $ph = implode(', ', array_fill(0, count($dados), '?'));
                db()->prepare("INSERT INTO " . table('email_contas') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
                set_flash('success', 'Conta adicionada!');
            }
            header('Location: email.php?view=config'); exit;
        } catch (Exception $e) {
            set_flash('error', 'Erro: ' . $e->getMessage());
        }
    }
}

// ===== ACAO: MARCAR COMO LIDO/NAO LIDO =====
if (isset($_GET['mark']) && $email_id) {
    $status = $_GET['mark'] === 'read' ? 'lido' : 'nao_lido';
    db()->prepare("UPDATE " . table('emails') . " SET status = ? WHERE id = ?")->execute([$status, $email_id]);
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'email.php'));
    exit;
}

// ===== ACAO: STAR/UNSTAR =====
if (isset($_GET['star']) && $email_id) {
    $star = (int)$_GET['star'];
    db()->prepare("UPDATE " . table('emails') . " SET starred = ? WHERE id = ?")->execute([$star, $email_id]);
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'email.php'));
    exit;
}

// ===== ACAO: MOVER PARA PASTA =====
if (isset($_GET['move']) && $email_id) {
    $dest = $_GET['move'];
    db()->prepare("UPDATE " . table('emails') . " SET pasta = ? WHERE id = ?")->execute([$dest, $email_id]);
    set_flash('success', 'Email movido!');
    header('Location: email.php?view=inbox&folder=' . $folder);
    exit;
}

// ===== ACAO: DELETAR =====
if (isset($_GET['delete']) && $email_id) {
    db()->prepare("UPDATE " . table('emails') . " SET pasta = 'trash' WHERE id = ?")->execute([$email_id]);
    set_flash('success', 'Email movido para lixeira!');
    header('Location: email.php?view=inbox&folder=' . $folder);
    exit;
}

// ===== CONTAGENS =====
$counts = [];
foreach (['inbox','sent','drafts','trash','spam','archive'] as $f) {
    try {
        $counts[$f] = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE pasta = '{$f}'")->fetchColumn();
    } catch (Exception $e) {
        $counts[$f] = 0;
    }
}
try {
    $counts['starred'] = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE starred = 1")->fetchColumn();
} catch (Exception $e) { $counts['starred'] = 0; }
try {
    $counts['nao_lido'] = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE pasta = 'inbox' AND status = 'nao_lido'")->fetchColumn();
} catch (Exception $e) { $counts['nao_lido'] = 0; }

// ===== LISTAR EMAILS =====
$emails = [];
if ($view === 'inbox') {
    try {
        $stmt = db()->prepare("SELECT * FROM " . table('emails') . " WHERE pasta = ? ORDER BY data_envio DESC LIMIT 50");
        $stmt->execute([$folder]);
        $emails = $stmt->fetchAll();
    } catch (Exception $e) {
        $emails = [];
    }
}

// ===== LER EMAIL =====
$email_atual = null;
if ($view === 'read' && $email_id) {
    try {
        $stmt = db()->prepare("SELECT * FROM " . table('emails') . " WHERE id = ?");
        $stmt->execute([$email_id]);
        $email_atual = $stmt->fetch();
        if ($email_atual && $email_atual['status'] === 'nao_lido') {
            db()->prepare("UPDATE " . table('emails') . " SET status = 'lido' WHERE id = ?")->execute([$email_id]);
        }
    } catch (Exception $e) {
        $email_atual = null;
    }
}

// ===== CONTAS DE EMAIL =====
try {
    $contas = db()->query("SELECT * FROM " . table('email_contas') . " WHERE ativo = 1 ORDER BY padrao DESC, nome")->fetchAll();
} catch (Exception $e) {
    $contas = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== LAYOUT DO EMAIL ===== */
.email-wrapper {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 200px);
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.email-sidebar {
    width: 260px;
    background: #f8fafc;
    border-right: 1px solid #e2e8f0;
    padding: 20px 0;
    flex-shrink: 0;
}

.email-sidebar .compose-btn {
    margin: 0 16px 20px;
    width: calc(100% - 32px);
    padding: 12px;
    font-size: 0.95rem;
    font-weight: 500;
    border-radius: 8px;
}

.email-sidebar .nav-section {
    padding: 0 8px;
}

.email-sidebar .nav-title {
    padding: 8px 16px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
}

.email-sidebar .nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    margin: 2px 8px;
    border-radius: 8px;
    color: #475569;
    font-size: 0.875rem;
    text-decoration: none;
    transition: all 0.15s;
    cursor: pointer;
}

.email-sidebar .nav-item:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.email-sidebar .nav-item.active {
    background: #3b82f6;
    color: #fff;
}

.email-sidebar .nav-item .badge-count {
    margin-left: auto;
    background: #ef4444;
    color: #fff;
    font-size: 0.7rem;
    padding: 1px 6px;
    border-radius: 999px;
    font-weight: 600;
}

.email-sidebar .nav-item.active .badge-count {
    background: rgba(255,255,255,0.3);
}

.email-sidebar .labels-section {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

.email-sidebar .label-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 16px;
    margin: 2px 8px;
    border-radius: 6px;
    color: #475569;
    font-size: 0.85rem;
    cursor: pointer;
}

.email-sidebar .label-item:hover {
    background: #e2e8f0;
}

.email-sidebar .label-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.email-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.email-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
}

.email-toolbar .toolbar-btn {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 6px;
    color: #64748b;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.15s;
}

.email-toolbar .toolbar-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.email-toolbar .toolbar-search {
    margin-left: auto;
    position: relative;
}

.email-toolbar .toolbar-search input {
    padding: 6px 12px 6px 32px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.85rem;
    width: 240px;
}

.email-toolbar .toolbar-search i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.8rem;
}

.email-list {
    flex: 1;
    overflow-y: auto;
}

.email-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.1s;
    text-decoration: none;
    color: inherit;
}

.email-item:hover {
    background: #f8fafc;
}

.email-item.unread {
    background: #eff6ff;
}

.email-item.unread .email-sender,
.email-item.unread .email-subject {
    font-weight: 600;
    color: #0f172a;
}

.email-item .checkbox-wrap {
    width: 18px;
    flex-shrink: 0;
}

.email-item .star-btn {
    color: #cbd5e1;
    font-size: 0.85rem;
    flex-shrink: 0;
    cursor: pointer;
    transition: color 0.15s;
    text-decoration: none;
}

.email-item .star-btn.starred {
    color: #f59e0b;
}

.email-item .star-btn:hover {
    color: #f59e0b;
}

.email-item .email-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
}

.email-item .email-content {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.email-item .email-sender {
    width: 160px;
    flex-shrink: 0;
    font-size: 0.875rem;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.email-item .email-subject-line {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.email-item .email-subject {
    font-size: 0.875rem;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.email-item .email-preview {
    font-size: 0.8rem;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.email-item .email-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.email-item .email-date {
    font-size: 0.75rem;
    color: #94a3b8;
    white-space: nowrap;
}

.email-item .email-attachment {
    color: #94a3b8;
    font-size: 0.8rem;
}

.email-read-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.email-read-header .read-actions {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.email-read-header .read-actions a,
.email-read-header .read-actions button {
    padding: 6px 14px;
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 6px;
    color: #475569;
    font-size: 0.8rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}

.email-read-header .read-actions a:hover,
.email-read-header .read-actions button:hover {
    background: #f1f5f9;
}

.email-read-header .read-actions .btn-primary {
    background: #3b82f6;
    color: #fff;
    border-color: #3b82f6;
}

.email-read-header .read-actions .btn-primary:hover {
    background: #2563eb;
}

.email-read-header .read-actions .btn-danger {
    color: #ef4444;
    border-color: #fecaca;
}

.email-read-header .read-actions .btn-danger:hover {
    background: #fef2f2;
}

.email-read-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 16px;
}

.email-read-sender {
    display: flex;
    align-items: center;
    gap: 12px;
}

.email-read-sender .sender-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 600;
}

.email-read-sender .sender-info {
    flex: 1;
}

.email-read-sender .sender-name {
    font-weight: 600;
    color: #0f172a;
    font-size: 0.95rem;
}

.email-read-sender .sender-email {
    color: #64748b;
    font-size: 0.8rem;
}

.email-read-sender .email-date-full {
    color: #94a3b8;
    font-size: 0.8rem;
}

.email-read-body {
    padding: 24px 20px;
    font-size: 0.9rem;
    line-height: 1.7;
    color: #334155;
    white-space: pre-wrap;
}

.email-attachments {
    padding: 0 20px 20px;
}

.email-attachments h4 {
    font-size: 0.85rem;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 12px;
}

.attachment-list {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.attachment-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    min-width: 200px;
}

.attachment-item i {
    font-size: 1.5rem;
    color: #ef4444;
}

.attachment-item .attachment-info {
    flex: 1;
}

.attachment-item .attachment-name {
    font-size: 0.85rem;
    font-weight: 500;
    color: #0f172a;
}

.attachment-item .attachment-size {
    font-size: 0.75rem;
    color: #94a3b8;
}

.attachment-item .attachment-download {
    padding: 6px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    text-decoration: none;
}

.compose-form {
    padding: 20px;
}

.compose-form .form-group {
    margin-bottom: 16px;
}

.compose-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: #334155;
    margin-bottom: 6px;
}

.compose-form .form-group input[type="text"],
.compose-form .form-group input[type="email"],
.compose-form .form-group select,
.compose-form .form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.9rem;
    color: #0f172a;
    background: #fff;
    transition: border-color 0.15s;
}

.compose-form .form-group input:focus,
.compose-form .form-group textarea:focus,
.compose-form .form-group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.compose-form .form-group textarea {
    resize: vertical;
    min-height: 200px;
    font-family: inherit;
}

.compose-form .form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.compose-form .attachment-input {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
}

.compose-form .attachment-input input[type="file"] {
    font-size: 0.85rem;
}

.compose-form .form-actions-compose {
    display: flex;
    gap: 10px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

.config-panel {
    padding: 24px;
    max-width: 800px;
}

.config-panel h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 20px;
}

.config-panel .conta-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
    background: #fff;
    display: flex;
    align-items: center;
    gap: 14px;
}

.config-panel .conta-card .conta-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #eff6ff;
    color: #3b82f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.config-panel .conta-card .conta-info {
    flex: 1;
}

.config-panel .conta-card .conta-info .conta-nome {
    font-weight: 600;
    color: #0f172a;
    font-size: 0.95rem;
}

.config-panel .conta-card .conta-info .conta-email {
    font-size: 0.8rem;
    color: #64748b;
}

.config-panel .conta-card .conta-actions {
    display: flex;
    gap: 6px;
}

.auto-config-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 20px;
}

.auto-config-box h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1e40af;
    margin-bottom: 8px;
}

.auto-config-box p {
    font-size: 0.8rem;
    color: #3b82f6;
    margin-bottom: 12px;
}

.auto-config-box .auto-fields {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 12px;
}

@media (max-width: 768px) {
    .email-wrapper {
        flex-direction: column;
    }
    .email-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #e2e8f0;
        padding: 12px 0;
    }
    .email-sidebar .nav-section {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    .email-sidebar .nav-item {
        margin: 0;
        padding: 6px 12px;
        font-size: 0.8rem;
    }
    .email-sidebar .labels-section,
    .email-sidebar .nav-title {
        display: none;
    }
    .compose-form .form-row-2 {
        grid-template-columns: 1fr;
    }
    .email-item .email-sender {
        width: 100px;
    }
}
</style>

<div class="page-header">
    <h1><i class="fas fa-envelope"></i> Email</h1>
</div>

<div class="email-wrapper">
    <!-- SIDEBAR -->
    <aside class="email-sidebar">
        <a href="email.php?view=compose" class="btn btn-primary compose-btn">
            <i class="fas fa-pen"></i> Escrever
        </a>

        <div class="nav-section">
            <div class="nav-title">Pastas</div>
            <a href="email.php?view=inbox&folder=inbox" class="nav-item <?php echo $folder === 'inbox' && $view === 'inbox' ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i> Caixa de Entrada
                <?php if ($counts['nao_lido'] > 0): ?>
                <span class="badge-count"><?php echo $counts['nao_lido']; ?></span>
                <?php endif; ?>
            </a>
            <a href="email.php?view=inbox&folder=sent" class="nav-item <?php echo $folder === 'sent' ? 'active' : ''; ?>">
                <i class="fas fa-paper-plane"></i> Enviados
            </a>
            <a href="email.php?view=inbox&folder=drafts" class="nav-item <?php echo $folder === 'drafts' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Rascunhos
                <?php if ($counts['drafts'] > 0): ?>
                <span class="badge-count" style="background:#64748b;"><?php echo $counts['drafts']; ?></span>
                <?php endif; ?>
            </a>
            <a href="email.php?view=inbox&folder=starred" class="nav-item <?php echo $folder === 'starred' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> Com Estrela
            </a>
            <a href="email.php?view=inbox&folder=archive" class="nav-item <?php echo $folder === 'archive' ? 'active' : ''; ?>">
                <i class="fas fa-archive"></i> Arquivados
            </a>
            <a href="email.php?view=inbox&folder=spam" class="nav-item <?php echo $folder === 'spam' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-circle"></i> Spam
            </a>
            <a href="email.php?view=inbox&folder=trash" class="nav-item <?php echo $folder === 'trash' ? 'active' : ''; ?>">
                <i class="fas fa-trash"></i> Lixeira
            </a>
        </div>

        <div class="nav-section labels-section">
            <div class="nav-title">Etiquetas</div>
            <div class="label-item"><span class="label-dot" style="background:#3b82f6;"></span> Cliente</div>
            <div class="label-item"><span class="label-dot" style="background:#10b981;"></span> Financeiro</div>
            <div class="label-item"><span class="label-dot" style="background:#f59e0b;"></span> Interno</div>
        </div>

        <div class="nav-section" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
            <a href="email.php?view=config" class="nav-item <?php echo $view === 'config' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Configuracoes
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="email-main">

        <?php if ($view === 'compose'): ?>
        <!-- ===== ESCREVER ===== -->
        <div class="email-toolbar">
            <h3 style="font-size: 1rem; font-weight: 600; color: #0f172a;">Escrever Mensagem</h3>
            <div style="margin-left: auto; font-size: 0.8rem; color: #94a3b8;">
                <a href="email.php" style="color: #3b82f6; text-decoration: none;">Inicio</a> /
                <a href="email.php" style="color: #3b82f6; text-decoration: none;">Caixa de Entrada</a> /
                <span>Escrever</span>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data" class="compose-form">
            <input type="hidden" name="acao" value="enviar">

            <div class="form-group">
                <label>De</label>
                <select name="conta_id">
                    <?php foreach ($contas as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['nome'] ?: $c['email']); ?></option>
                    <?php endforeach; ?>
                    <?php if (empty($contas)): ?>
                    <option value="0"><?php echo get_config('email_from_email') ?: 'admin@site.com'; ?></option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Para</label>
                <input type="email" name="to" placeholder="destinatario@exemplo.com" required>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label>Cc</label>
                    <input type="text" name="cc" placeholder="">
                </div>
                <div class="form-group">
                    <label>Cco</label>
                    <input type="text" name="bcc" placeholder="">
                </div>
            </div>

            <div class="form-group">
                <label>Assunto</label>
                <input type="text" name="subject" placeholder="">
            </div>

            <div class="form-group">
                <label>Mensagem</label>
                <textarea name="message" placeholder="Escreva sua mensagem..."></textarea>
            </div>

            <div class="form-group">
                <label>Anexos</label>
                <div class="attachment-input">
                    <input type="file" name="attachments[]" multiple id="attachmentInput">
                    <span id="attachmentLabel" style="color: #64748b; font-size: 0.85rem;">Nenhum arquivo selecionado</span>
                </div>
            </div>

            <div class="form-actions-compose">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Enviar</button>
                <button type="button" class="btn btn-secondary" onclick="history.back()"><i class="fas fa-save"></i> Salvar rascunho</button>
                <a href="email.php" class="btn btn-danger" style="margin-left: auto;"><i class="fas fa-times"></i> Descartar</a>
            </div>
        </form>

        <?php elseif ($view === 'read' && $email_atual): ?>
        <!-- ===== LER EMAIL ===== -->
        <div class="email-read-header">
            <div class="read-actions">
                <a href="email.php?view=inbox&folder=<?php echo $folder; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                <a href="email.php?view=compose&reply=<?php echo $email_atual['id']; ?>" class="btn btn-primary"><i class="fas fa-reply"></i> Responder</a>
                <a href="email.php?view=compose&forward=<?php echo $email_atual['id']; ?>" class="btn btn-secondary"><i class="fas fa-share"></i> Encaminhar</a>
                <a href="?move=archive&id=<?php echo $email_atual['id']; ?>&folder=<?php echo $folder; ?>" class="btn btn-secondary"><i class="fas fa-archive"></i> Arquivar</a>
                <a href="?delete=1&id=<?php echo $email_atual['id']; ?>&folder=<?php echo $folder; ?>" class="btn btn-danger" onclick="return confirm('Mover para lixeira?')"><i class="fas fa-trash"></i> Excluir</a>
            </div>

            <h2><?php echo sanitize($email_atual['assunto'] ?: '(Sem assunto)'); ?></h2>

            <div class="email-read-sender">
                <div class="sender-avatar">
                    <?php
                    $nome = $email_atual['remetente_nome'] ?: $email_atual['remetente_email'];
                    echo strtoupper(substr($nome, 0, 2));
                    ?>
                </div>
                <div class="sender-info">
                    <div class="sender-name"><?php echo sanitize($email_atual['remetente_nome'] ?: 'Desconhecido'); ?></div>
                    <div class="sender-email"><?php echo sanitize($email_atual['remetente_email']); ?> — para mim</div>
                </div>
                <div class="email-date-full"><?php echo format_date($email_atual['data_envio'], 'd/m/Y H:i'); ?></div>
            </div>
        </div>

        <div class="email-read-body">
            <?php echo nl2br(sanitize($email_atual['corpo'])); ?>
        </div>

        <?php
        $anexos = json_decode($email_atual['anexos'] ?? '[]', true);
        if (!empty($anexos)):
        ?>
        <div class="email-attachments">
            <h4><i class="fas fa-paperclip"></i> Anexos (<?php echo count($anexos); ?>)</h4>
            <div class="attachment-list">
                <?php foreach ($anexos as $anexo):
                    $ext = pathinfo($anexo, PATHINFO_EXTENSION);
                    $icon = in_array($ext, ['pdf']) ? 'fa-file-pdf' : (in_array($ext, ['jpg','jpeg','png','gif']) ? 'fa-file-image' : 'fa-file');
                    $size = file_exists(__DIR__ . '/uploads/' . $anexo) ? round(filesize(__DIR__ . '/uploads/' . $anexo) / 1024, 1) . ' KB' : '';
                ?>
                <div class="attachment-item">
                    <i class="fas <?php echo $icon; ?>"></i>
                    <div class="attachment-info">
                        <div class="attachment-name"><?php echo basename($anexo); ?></div>
                        <div class="attachment-size"><?php echo $size; ?></div>
                    </div>
                    <a href="<?php echo uploads_url($anexo); ?>" download class="attachment-download"><i class="fas fa-download"></i></a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($view === 'config'): ?>
        <!-- ===== CONFIGURACOES ===== -->
        <div class="email-toolbar">
            <h3 style="font-size: 1rem; font-weight: 600; color: #0f172a;">Configuracoes de Email</h3>
        </div>

        <div class="config-panel">
            <h3><i class="fas fa-envelope"></i> Contas de Email Configuradas</h3>

            <?php foreach ($contas as $c): ?>
            <div class="conta-card">
                <div class="conta-icon">
                    <i class="fas fa-<?php echo $c['tipo'] === 'gmail' ? 'google' : ($c['tipo'] === 'outlook' ? 'microsoft' : 'envelope'); ?>"></i>
                </div>
                <div class="conta-info">
                    <div class="conta-nome"><?php echo sanitize($c['nome'] ?: $c['email']); ?></div>
                    <div class="conta-email"><?php echo sanitize($c['email']); ?> • IMAP: <?php echo $c['imap_host']; ?> • SMTP: <?php echo $c['smtp_host']; ?></div>
                </div>
                <div class="conta-actions">
                    <a href="?view=config&edit_conta=<?php echo $c['id']; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i></a>
                    <a href="?view=config&delete_conta=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Remover conta?')"><i class="fas fa-trash"></i></a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($contas)): ?>
            <p style="color: #94a3b8; text-align: center; padding: 40px;">Nenhuma conta configurada. Adicione uma conta abaixo.</p>
            <?php endif; ?>

            <h3 style="margin-top: 32px;"><i class="fas fa-plus-circle"></i> <?php echo isset($_GET['edit_conta']) ? 'Editar' : 'Adicionar'; ?> Conta</h3>

            <?php
            $conta_edit = null;
            if (isset($_GET['edit_conta'])) {
                $stmt = db()->prepare("SELECT * FROM " . table('email_contas') . " WHERE id = ?");
                $stmt->execute([(int)$_GET['edit_conta']]);
                $conta_edit = $stmt->fetch();
            }
            ?>

            <div class="auto-config-box">
                <h4><i class="fas fa-magic"></i> Configuracao Automatica</h4>
                <p>Selecione o provedor e preencha email e senha. Os dados IMAP/SMTP serao preenchidos automaticamente.</p>
                <div class="auto-fields">
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.8rem;">Provedor</label>
                        <select id="tipoProvedor" onchange="autoPreencherConfig()" style="width:100%; padding:8px 12px; border:1px solid #bfdbfe; border-radius:6px;">
                            <option value="">Selecione o provedor...</option>
                            <option value="gmail">Gmail (Google)</option>
                            <option value="outlook">Outlook / Hotmail (Microsoft)</option>
                            <option value="yahoo">Yahoo Mail</option>
                            <option value="proprio">Email de Dominio Proprio</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.8rem;">&nbsp;</label>
                        <button type="button" class="btn btn-primary" onclick="autoPreencherConfig()" style="width:100%;"><i class="fas fa-wand-magic-sparkles"></i> Auto-preencher</button>
                    </div>
                </div>
            </div>

            <form method="POST" class="compose-form" style="padding:0;">
                <input type="hidden" name="acao" value="salvar_conta">
                <input type="hidden" name="conta_id" value="<?php echo $conta_edit['id'] ?? 0; ?>">

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Nome da Conta</label>
                        <input type="text" name="nome" value="<?php echo sanitize($conta_edit['nome'] ?? ''); ?>" placeholder="Minha Conta Gmail">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" id="configEmail" value="<?php echo sanitize($conta_edit['email'] ?? ''); ?>" placeholder="seuemail@gmail.com" required>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Senha / Senha de App *</label>
                        <input type="password" name="senha" value="<?php echo sanitize($conta_edit['senha'] ?? ''); ?>" placeholder="Senha de aplicativo">
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo" id="configTipo">
                            <option value="gmail" <?php echo selected($conta_edit['tipo'] ?? '', 'gmail'); ?>>Gmail</option>
                            <option value="outlook" <?php echo selected($conta_edit['tipo'] ?? '', 'outlook'); ?>>Outlook</option>
                            <option value="yahoo" <?php echo selected($conta_edit['tipo'] ?? '', 'yahoo'); ?>>Yahoo</option>
                            <option value="proprio" <?php echo selected($conta_edit['tipo'] ?? '', 'proprio'); ?>>Dominio Proprio</option>
                        </select>
                    </div>
                </div>

                <h4 style="margin: 24px 0 12px; font-size: 0.9rem; color: #0f172a; font-weight: 600;"><i class="fas fa-server"></i> Configuracoes IMAP (Recebimento)</h4>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Servidor IMAP</label>
                        <input type="text" name="imap_host" id="imapHost" value="<?php echo sanitize($conta_edit['imap_host'] ?? ''); ?>" placeholder="imap.gmail.com">
                    </div>
                    <div class="form-group">
                        <label>Porta IMAP</label>
                        <input type="number" name="imap_port" id="imapPort" value="<?php echo $conta_edit['imap_port'] ?? 993; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Seguranca IMAP</label>
                    <select name="imap_secure" id="imapSecure">
                        <option value="ssl" <?php echo selected($conta_edit['imap_secure'] ?? '', 'ssl'); ?>>SSL</option>
                        <option value="tls" <?php echo selected($conta_edit['imap_secure'] ?? '', 'tls'); ?>>TLS</option>
                        <option value="none" <?php echo selected($conta_edit['imap_secure'] ?? '', 'none'); ?>>Nenhuma</option>
                    </select>
                </div>

                <h4 style="margin: 24px 0 12px; font-size: 0.9rem; color: #0f172a; font-weight: 600;"><i class="fas fa-paper-plane"></i> Configuracoes SMTP (Envio)</h4>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Servidor SMTP</label>
                        <input type="text" name="smtp_host" id="smtpHost" value="<?php echo sanitize($conta_edit['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label>Porta SMTP</label>
                        <input type="number" name="smtp_port" id="smtpPort" value="<?php echo $conta_edit['smtp_port'] ?? 587; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Seguranca SMTP</label>
                    <select name="smtp_secure" id="smtpSecure">
                        <option value="tls" <?php echo selected($conta_edit['smtp_secure'] ?? '', 'tls'); ?>>TLS</option>
                        <option value="ssl" <?php echo selected($conta_edit['smtp_secure'] ?? '', 'ssl'); ?>>SSL</option>
                        <option value="none" <?php echo selected($conta_edit['smtp_secure'] ?? '', 'none'); ?>>Nenhuma</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Usuario (geralmente o proprio email)</label>
                    <input type="text" name="usuario" id="configUsuario" value="<?php echo sanitize($conta_edit['usuario'] ?? ''); ?>" placeholder="seuemail@gmail.com">
                </div>

                <div class="form-actions-compose">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Conta</button>
                    <a href="email.php?view=config" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>

        <?php else: ?>
        <!-- ===== LISTA DE EMAILS ===== -->
        <div class="email-toolbar">
            <div class="toolbar-btn"><input type="checkbox" id="selectAll"></div>
            <button class="toolbar-btn" onclick="location.reload()"><i class="fas fa-sync-alt"></i></button>
            <button class="toolbar-btn"><i class="fas fa-exclamation-circle"></i></button>
            <button class="toolbar-btn"><i class="fas fa-trash"></i></button>
            <div class="toolbar-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Buscar email...">
            </div>
            <span style="margin-left: auto; font-size: 0.8rem; color: #94a3b8;">1-<?php echo min(50, count($emails)); ?> de <?php echo count($emails); ?></span>
        </div>

        <div class="email-list">
            <?php if (empty($emails)): ?>
            <div style="text-align: center; padding: 80px 20px; color: #94a3b8;">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                <p>Caixa de entrada vazia</p>
            </div>
            <?php else: ?>
            <?php foreach ($emails as $e):
                $is_unread = $e['status'] === 'nao_lido';
                $initials = strtoupper(substr($e['remetente_nome'] ?: $e['remetente_email'], 0, 2));
                $has_attach = !empty($e['anexos']) && $e['anexos'] !== '[]';
            ?>
            <a href="email.php?view=read&id=<?php echo $e['id']; ?>&folder=<?php echo $folder; ?>" class="email-item <?php echo $is_unread ? 'unread' : ''; ?>">
                <div class="checkbox-wrap"><input type="checkbox"></div>
                <a href="?star=<?php echo $e['starred'] ? 0 : 1; ?>&id=<?php echo $e['id']; ?>&folder=<?php echo $folder; ?>" class="star-btn <?php echo $e['starred'] ? 'starred' : ''; ?>" onclick="event.stopPropagation();">
                    <i class="fas fa-star"></i>
                </a>
                <div class="email-avatar"><?php echo $initials; ?></div>
                <div class="email-content">
                    <div class="email-sender"><?php echo sanitize($e['remetente_nome'] ?: $e['remetente_email']); ?></div>
                    <div class="email-subject-line">
                        <span class="email-subject"><?php echo sanitize($e['assunto'] ?: '(Sem assunto)'); ?></span>
                        <span class="email-preview">— <?php echo sanitize(substr(strip_tags($e['corpo']), 0, 80)); ?>...</span>
                    </div>
                </div>
                <div class="email-meta">
                    <?php if ($has_attach): ?>
                    <span class="email-attachment"><i class="fas fa-paperclip"></i></span>
                    <?php endif; ?>
                    <span class="email-date"><?php echo format_date($e['data_envio'], 'M d'); ?></span>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
function autoPreencherConfig() {
    const tipo = document.getElementById('tipoProvedor').value;
    const email = document.getElementById('configEmail').value;

    const configs = {
        gmail: {
            tipo: 'gmail',
            imapHost: 'imap.gmail.com',
            imapPort: 993,
            imapSecure: 'ssl',
            smtpHost: 'smtp.gmail.com',
            smtpPort: 587,
            smtpSecure: 'tls'
        },
        outlook: {
            tipo: 'outlook',
            imapHost: 'outlook.office365.com',
            imapPort: 993,
            imapSecure: 'ssl',
            smtpHost: 'smtp.office365.com',
            smtpPort: 587,
            smtpSecure: 'tls'
        },
        yahoo: {
            tipo: 'yahoo',
            imapHost: 'imap.mail.yahoo.com',
            imapPort: 993,
            imapSecure: 'ssl',
            smtpHost: 'smtp.mail.yahoo.com',
            smtpPort: 587,
            smtpSecure: 'tls'
        },
        proprio: {
            tipo: 'proprio',
            imapHost: '',
            imapPort: 993,
            imapSecure: 'ssl',
            smtpHost: '',
            smtpPort: 587,
            smtpSecure: 'tls'
        }
    };

    if (!tipo || !configs[tipo]) {
        alert('Selecione um provedor primeiro!');
        return;
    }

    const cfg = configs[tipo];
    document.getElementById('configTipo').value = cfg.tipo;
    document.getElementById('imapHost').value = cfg.imapHost;
    document.getElementById('imapPort').value = cfg.imapPort;
    document.getElementById('imapSecure').value = cfg.imapSecure;
    document.getElementById('smtpHost').value = cfg.smtpHost;
    document.getElementById('smtpPort').value = cfg.smtpPort;
    document.getElementById('smtpSecure').value = cfg.smtpSecure;

    if (email) {
        document.getElementById('configUsuario').value = email;
    }

    if (tipo === 'proprio' && email) {
        const domain = email.split('@')[1];
        if (domain) {
            document.getElementById('imapHost').value = 'mail.' + domain;
            document.getElementById('smtpHost').value = 'mail.' + domain;
        }
    }
}

document.getElementById('attachmentInput').addEventListener('change', function(e) {
    const files = e.target.files;
    const label = document.getElementById('attachmentLabel');
    if (files.length === 0) {
        label.textContent = 'Nenhum arquivo selecionado';
    } else if (files.length === 1) {
        label.textContent = files[0].name;
    } else {
        label.textContent = files.length + ' arquivos selecionados';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
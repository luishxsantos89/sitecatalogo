<?php
/**
 * SiteCatalogo - Usuarios / Administradores (CRUD)
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Usuarios';

// Apenas admin pode gerenciar usuarios
if (!check_permission('admin')) {
    set_flash('error', 'Acesso negado. Apenas administradores.');
    header('Location: ./');
    exit;
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar') {
        $nome = trim($_POST['nome_completo'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $nivel = $_POST['nivel'] ?? 'vendedor';
        $status = isset($_POST['status']) ? 'ativo' : 'inativo';
        $senha = $_POST['senha'] ?? '';
        
        if (empty($nome) || empty($login)) { set_flash('error', 'Nome e login sao obrigatorios'); }
        else {
            try {
                if ($id) {
                    // Update - nao mudar login se ja existe
                    $sql = "UPDATE " . table('usuarios') . " SET nome_completo = ?, email = ?, telefone = ?, nivel = ?, status = ?";
                    $params = [$nome, $email, $telefone, $nivel, $status];
                    if (!empty($senha)) {
                        $sql .= ", senha = ?";
                        $params[] = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $id;
                    db()->prepare($sql)->execute($params);
                    log_activity('update', 'usuarios', "Usuario #{$id} atualizado");
                    set_flash('success', 'Usuario atualizado!');
                } else {
                    if (empty($senha) || strlen($senha) < 6) { set_flash('error', 'Senha obrigatoria com minimo 6 caracteres'); }
                    else {
                        // Verificar login unico
                        $check = db()->prepare("SELECT id FROM " . table('usuarios') . " WHERE login = ?");
                        $check->execute([$login]);
                        if ($check->fetch()) { set_flash('error', 'Login ja existe'); }
                        else {
                            db()->prepare("INSERT INTO " . table('usuarios') . " (nome_completo, login, senha, email, telefone, nivel, status) VALUES (?, ?, ?, ?, ?, ?, ?)")
                                ->execute([$nome, $login, password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]), $email, $telefone, $nivel, $status]);
                            log_activity('create', 'usuarios', "Usuario criado: {$login}");
                            set_flash('success', 'Usuario criado!');
                        }
                    }
                }
                header('Location: usuarios.php'); exit;
            } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
        }
    }
}

if ($action === 'delete' && $id) {
    try {
        // Nao deixar excluir a si mesmo
        if ($id == ($_SESSION['admin_id'] ?? 0)) { set_flash('error', 'Nao pode excluir seu proprio usuario'); }
        else {
            db()->prepare("DELETE FROM " . table('usuarios') . " WHERE id = ?")->execute([$id]);
            log_activity('delete', 'usuarios', "Usuario #{$id} excluido");
            set_flash('success', 'Usuario excluido!');
        }
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: usuarios.php'); exit;
}

$usuario = null;
if ($action === 'edit' && $id) {
    $stmt = db()->prepare("SELECT id, nome_completo, login, email, telefone, nivel, status FROM " . table('usuarios') . " WHERE id = ?");
    $stmt->execute([$id]); $usuario = $stmt->fetch();
}

$usuarios = db()->query("SELECT * FROM " . table('usuarios') . " ORDER BY created_at DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';

if ($action === 'edit' || $action === 'new'):
?>
<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> <?php echo $id ? 'Editar' : 'Novo'; ?> Usuario</h1>
    <a href="usuarios.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="acao" value="salvar">
            <div class="form-row">
                <div class="form-group"><label>Nome Completo *</label><input type="text" name="nome_completo" value="<?php echo sanitize($usuario['nome_completo'] ?? ''); ?>" required></div>
                <div class="form-group"><label>Login *</label><input type="text" name="login" value="<?php echo sanitize($usuario['login'] ?? ''); ?>" <?php echo $id ? 'readonly' : ''; ?> required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize($usuario['email'] ?? ''); ?>"></div>
                <div class="form-group"><label>Telefone</label><input type="text" name="telefone" value="<?php echo sanitize($usuario['telefone'] ?? ''); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Nivel</label><select name="nivel"><option value="admin" <?php echo selected($usuario['nivel'] ?? '', 'admin'); ?>>Administrador</option><option value="gerente" <?php echo selected($usuario['nivel'] ?? '', 'gerente'); ?>>Gerente</option><option value="vendedor" <?php echo selected($usuario['nivel'] ?? '', 'vendedor'); ?>>Vendedor</option></select></div>
                <div class="form-group"><label>Senha <?php echo $id ? '(deixe em branco para nao alterar)' : '*'; ?></label><input type="password" name="senha" <?php echo $id ? '' : 'required'; ?>></div>
            </div>
            <div class="form-group"><label class="form-check"><input type="checkbox" name="status" <?php echo checked(($usuario['status'] ?? 'ativo') === 'ativo'); ?>> Usuario Ativo</label></div>
            <div class="form-actions">
                <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> Usuarios</h1>
    <a href="usuarios.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Usuario</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Nome</th><th>Login</th><th>Nivel</th><th>Status</th><th>Ultimo Acesso</th><th width="100">Acoes</th></tr></thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><strong><?php echo sanitize($u['nome_completo']); ?></strong></td>
                    <td><code><?php echo sanitize($u['login']); ?></code></td>
                    <td><span class="badge-status" style="background:<?php echo $u['nivel']==='admin'?'#dbeafe':($u['nivel']==='gerente'?'#fef3c7':'#d1fae5'); ?>;color:<?php echo $u['nivel']==='admin'?'#1e40af':($u['nivel']==='gerente'?'#92400e':'#065f46'); ?>"><?php echo ucfirst($u['nivel']); ?></span></td>
                    <td><span class="badge-status status-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                    <td><?php echo $u['ultimo_acesso'] ? format_date($u['ultimo_acesso']) : 'Nunca'; ?></td>
                    <td class="actions">
                        <a href="usuarios.php?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-secondary btn-icon"><i class="fas fa-edit"></i></a>
                        <?php if ($u['id'] != ($_SESSION['admin_id'] ?? 0)): ?>
                        <a href="?action=delete&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger btn-icon btn-delete"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * SiteCatalogo - Perfil do Usuario
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Meu Perfil';

$user_id = $_SESSION['admin_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'atualizar') {
        try {
            $nome = trim($_POST['nome_completo'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            
            $sql = "UPDATE " . table('usuarios') . " SET nome_completo = ?, email = ?, telefone = ?";
            $params = [$nome, $email, $telefone];
            
            if (!empty($_POST['senha_atual']) && !empty($_POST['senha_nova'])) {
                $stmt = db()->prepare("SELECT senha FROM " . table('usuarios') . " WHERE id = ?");
                $stmt->execute([$user_id]);
                $u = $stmt->fetch();
                if ($u && password_verify($_POST['senha_atual'], $u['senha'])) {
                    if (strlen($_POST['senha_nova']) >= 6) {
                        $sql .= ", senha = ?";
                        $params[] = password_hash($_POST['senha_nova'], PASSWORD_BCRYPT, ['cost' => 12]);
                    } else { set_flash('error', 'Nova senha deve ter no minimo 6 caracteres'); header('Location: perfil.php'); exit; }
                } else { set_flash('error', 'Senha atual incorreta'); header('Location: perfil.php'); exit; }
            }
            
            // Upload avatar
            if (!empty($_FILES['avatar']['name'])) {
                $upload = upload_file($_FILES['avatar'], 'avatars', ['jpg','jpeg','png','gif']);
                if ($upload) {
                    $sql .= ", avatar = ?";
                    $params[] = $upload;
                    $_SESSION['admin_avatar'] = $upload;
                }
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $user_id;
            db()->prepare($sql)->execute($params);
            
            $_SESSION['admin_nome'] = $nome;
            $_SESSION['admin_email'] = $email;
            log_activity('update', 'perfil', "Perfil atualizado");
            set_flash('success', 'Perfil atualizado!');
        } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
        header('Location: perfil.php'); exit;
    }
}

$stmt = db()->prepare("SELECT * FROM " . table('usuarios') . " WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-user"></i> Meu Perfil</h1>
</div>

<div class="card" style="max-width:600px;">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="atualizar">
            
            <div class="form-row">
                <div class="form-group"><label>Nome Completo</label><input type="text" name="nome_completo" value="<?php echo sanitize($usuario['nome_completo'] ?? ''); ?>"></div>
                <div class="form-group"><label>Login</label><input type="text" value="<?php echo sanitize($usuario['login'] ?? ''); ?>" disabled></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize($usuario['email'] ?? ''); ?>"></div>
                <div class="form-group"><label>Telefone</label><input type="text" name="telefone" value="<?php echo sanitize($usuario['telefone'] ?? ''); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Senha Atual</label><input type="password" name="senha_atual" placeholder="Para alterar senha"></div>
                <div class="form-group"><label>Nova Senha</label><input type="password" name="senha_nova" placeholder="Min. 6 caracteres"></div>
            </div>
            <div class="form-group">
                <label>Avatar</label>
                <input type="file" name="avatar" accept="image/*">
                <?php if (!empty($usuario['avatar'])): ?>
                <img src="<?php echo uploads_url($usuario['avatar']); ?>" alt="" class="form-image-preview" style="border-radius:50%;width:80px;height:80px;">
                <?php endif; ?>
            </div>
            <div class="form-group"><label>Nivel</label><input type="text" value="<?php echo ucfirst($usuario['nivel'] ?? ''); ?>" disabled></div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Alteracoes</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

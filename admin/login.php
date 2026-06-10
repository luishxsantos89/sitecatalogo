<?php
/**
 * SiteCatalogo - Login do Painel Admin
 */

require_once __DIR__ . '/../includes/functions.php';
session_check();

// Redirecionar se ja estiver logado
if (is_logged_in()) {
    header('Location: ./');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    if (empty($login) || empty($senha)) {
        $error = 'Preencha todos os campos.';
    } else {
        try {
            $stmt = db()->prepare("SELECT * FROM " . table('usuarios') . " WHERE login = ? AND status = 'ativo' LIMIT 1");
            $stmt->execute([$login]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($senha, $user['senha'])) {
                // Login ok
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_nome'] = $user['nome_completo'];
                $_SESSION['admin_login'] = $user['login'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_nivel'] = $user['nivel'];
                $_SESSION['admin_avatar'] = $user['avatar'];
                
                // Atualizar ultimo acesso
                db()->prepare("UPDATE " . table('usuarios') . " SET ultimo_acesso = NOW() WHERE id = ?")
                    ->execute([$user['id']]);
                
                log_activity('login', 'auth', "Usuario {$user['login']} fez login");
                
                header('Location: ./');
                exit;
            } else {
                $error = 'Login ou senha incorretos.';
            }
        } catch (Exception $e) {
            $error = 'Erro no sistema. Tente novamente.';
        }
    }
}

$site_name = get_config('site_name', 'SiteCatalogo');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo sanitize($site_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <svg width="48" height="48" viewBox="0 0 36 36" fill="none">
                    <rect width="36" height="36" rx="9" fill="#3b82f6"/>
                    <path d="M10 24V14l8-5 8 5v10H10z" stroke="white" stroke-width="2" fill="none"/>
                    <circle cx="18" cy="19" r="2.5" fill="white"/>
                </svg>
                <h1><?php echo sanitize($site_name); ?></h1>
                <p>Painel Administrativo</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo sanitize($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="login">Usuario</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="login" name="login" required autofocus placeholder="Seu usuario">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="senha" name="senha" required placeholder="Sua senha">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>
            
            <div class="login-footer">
                <a href="../">&larr; Voltar para o site</a>
            </div>
        </div>
    </div>
</body>
</html>

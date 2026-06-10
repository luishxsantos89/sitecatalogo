<?php
/**
 * SiteCatalogo - Instalador
 * Estilo WordPress - Configuracao passo a passo
 */

session_start();

define('INSTALL_PATH', dirname(__FILE__));
define('ROOT_PATH', dirname(INSTALL_PATH));

// ============================================
// VERIFICACAO: Se ja instalado, redireciona
// ============================================
if (file_exists(ROOT_PATH . '/config.php')) {
    $config_content = file_get_contents(ROOT_PATH . '/config.php');
    if (strpos($config_content, "INSTALLED") !== false && strpos($config_content, "true") !== false) {
        header('Location: ../');
        exit;
    }
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Processar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            $db_host = $_POST['db_host'] ?? 'localhost';
            $db_name = $_POST['db_name'] ?? '';
            $db_user = $_POST['db_user'] ?? '';
            $db_pass = $_POST['db_pass'] ?? '';
            $db_prefix = $_POST['db_prefix'] ?? 'sc_';

            try {
                $pdo = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);

                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$db_name}`");

                $_SESSION['install_db'] = [
                    'host' => $db_host,
                    'name' => $db_name,
                    'user' => $db_user,
                    'pass' => $db_pass,
                    'prefix' => $db_prefix
                ];

                header('Location: ?step=2');
                exit;
            } catch (PDOException $e) {
                $error = 'Erro na conexao: ' . $e->getMessage();
            }
            break;

        case 2:
            $_SESSION['install_site'] = [
                'site_name' => $_POST['site_name'] ?? 'SiteCatalogo',
                'site_description' => $_POST['site_description'] ?? 'Catalogo Profissional de Produtos',
                'site_email' => $_POST['site_email'] ?? '',
                'whatsapp' => $_POST['whatsapp'] ?? '',
                'currency' => $_POST['currency'] ?? 'BRL',
                'timezone' => $_POST['timezone'] ?? 'America/Sao_Paulo'
            ];
            header('Location: ?step=3');
            exit;

        case 3:
            $admin_name = $_POST['admin_name'] ?? '';
            $admin_login = $_POST['admin_login'] ?? '';
            $admin_email = $_POST['admin_email'] ?? '';
            $admin_pass = $_POST['admin_pass'] ?? '';
            $admin_pass2 = $_POST['admin_pass2'] ?? '';

            if (empty($admin_name) || empty($admin_login) || empty($admin_email) || empty($admin_pass)) {
                $error = 'Preencha todos os campos obrigatorios.';
            } elseif ($admin_pass !== $admin_pass2) {
                $error = 'As senhas nao conferem.';
            } elseif (strlen($admin_pass) < 6) {
                $error = 'A senha deve ter no minimo 6 caracteres.';
            } else {
                try {
                    $db = $_SESSION['install_db'];
                    $site = $_SESSION['install_site'];

                    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);

                    // Executar schema.sql
                    $schema = file_get_contents(INSTALL_PATH . '/schema.sql');
                    $schema = str_replace('sc_', $db['prefix'], $schema);
                    $schema = preg_replace('/--.*\n/', "\n", $schema);
                    $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);

                    $commands = array_filter(array_map('trim', explode(';', $schema)));
                    foreach ($commands as $command) {
                        if (!empty($command)) {
                            $pdo->exec($command);
                        }
                    }

                    // Inserir configuracoes do site
                    $stmt = $pdo->prepare("INSERT INTO {$db['prefix']}configuracoes (chave, valor, descricao) VALUES (?, ?, ?)");
                    $configs = [
                        ['site_name', $site['site_name'], 'Nome do Site'],
                        ['site_description', $site['site_description'], 'Descricao do Site'],
                        ['site_email', $site['site_email'], 'E-mail de Contato'],
                        ['whatsapp', $site['whatsapp'], 'WhatsApp'],
                        ['currency', $site['currency'], 'Moeda'],
                        ['timezone', $site['timezone'], 'Fuso Horario'],
                        ['theme_primary', '#0f172a', 'Cor Primaria'],
                        ['theme_secondary', '#3b82f6', 'Cor Secundaria'],
                        ['items_per_page', '12', 'Itens por Pagina'],
                        ['orcamento_whatsapp_msg', 'Ola! Gostaria de solicitar um orcamento para os seguintes produtos:', 'Mensagem WhatsApp Orcamento'],
                    ];
                    foreach ($configs as $cfg) {
                        $stmt->execute($cfg);
                    }

                    // Criar usuario admin
                    $hash = password_hash($admin_pass, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = $pdo->prepare("INSERT INTO {$db['prefix']}usuarios (nome_completo, login, senha, email, nivel, status) VALUES (?, ?, ?, ?, 'admin', 'ativo')");
                    $stmt->execute([$admin_name, $admin_login, $hash, $admin_email]);

                    // ============================================
                    // CRIAR config.php - USANDO HEREDOC PARA EVITAR ERROS
                    // ============================================
                    $now = date('Y-m-d H:i:s');
                    $host = addslashes($db['host']);
                    $name = addslashes($db['name']);
                    $user = addslashes($db['user']);
                    $pass = addslashes($db['pass']);
                    $prefix = addslashes($db['prefix']);
                    $site_name_esc = addslashes($site['site_name']);
                    $site_desc_esc = addslashes($site['site_description']);
                    $site_email_esc = addslashes($site['site_email']);
                    $whatsapp_esc = addslashes($site['whatsapp']);
                    $currency_esc = addslashes($site['currency']);
                    $timezone_esc = addslashes($site['timezone']);

                    $config_content = <<<PHP
<?php
/**
 * SiteCatalogo - Configuracoes
 * Gerado automaticamente pelo instalador
 * NAO EDITE MANUALMENTE!
 */

define('INSTALLED', true);
define('INSTALL_DATE', '{$now}');
define('VERSION', '1.0.0');

define('DB_HOST', '{$host}');
define('DB_NAME', '{$name}');
define('DB_USER', '{$user}');
define('DB_PASS', '{$pass}');
define('DB_PREFIX', '{$prefix}');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', '{$site_name_esc}');
define('SITE_DESCRIPTION', '{$site_desc_esc}');
define('SITE_EMAIL', '{$site_email_esc}');
define('WHATSAPP', '{$whatsapp_esc}');
define('CURRENCY', '{$currency_esc}');
define('TIMEZONE', '{$timezone_esc}');

define('SITE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['PHP_SELF'], 2) . '/');
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

date_default_timezone_set('{$timezone_esc}');

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
PHP;

                    file_put_contents(ROOT_PATH . '/config.php', $config_content);

                    $success = 'Instalacao concluida com sucesso!';
                    $_SESSION['install_complete'] = true;

                } catch (Exception $e) {
                    $error = 'Erro na instalacao: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Carregar dados da sessao
$db_host = $_SESSION['install_db']['host'] ?? 'localhost';
$db_name = $_SESSION['install_db']['name'] ?? '';
$db_user = $_SESSION['install_db']['user'] ?? '';
$db_pass = $_SESSION['install_db']['pass'] ?? '';
$db_prefix = $_SESSION['install_db']['prefix'] ?? 'sc_';

$site_name = $_SESSION['install_site']['site_name'] ?? 'SiteCatalogo';
$site_description = $_SESSION['install_site']['site_description'] ?? 'Catalogo Profissional de Produtos';
$site_email = $_SESSION['install_site']['site_email'] ?? '';
$whatsapp = $_SESSION['install_site']['whatsapp'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiteCatalogo - Instalacao</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="install-wrapper">
        <div class="install-container">
            <!-- Header -->
            <div class="install-header">
                <div class="logo">
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                        <rect width="40" height="40" rx="10" fill="#3b82f6"/>
                        <path d="M12 28V16l8-6 8 6v12H12z" stroke="white" stroke-width="2" fill="none"/>
                        <circle cx="20" cy="22" r="3" fill="white"/>
                    </svg>
                    <h1>SiteCatalogo</h1>
                </div>
                <p class="version">Versao 1.0.0</p>
            </div>

            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <div class="step-number">1</div>
                    <div class="step-label">Banco de Dados</div>
                </div>
                <div class="step-connector"></div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-label">Configuracoes</div>
                </div>
                <div class="step-connector"></div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-label">Conta Admin</div>
                </div>
            </div>

            <!-- Content -->
            <div class="install-content">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/><path d="M7 7l6 6M13 7l-6 6" stroke="currentColor" stroke-width="2"/></svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/><path d="M6 10l3 3 5-5" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <div class="success-box">
                        <h3>Instalacao Concluida!</h3>
                        <p>O SiteCatalogo foi instalado com sucesso. Voce ja pode comecar a usar o sistema.</p>
                        <div class="success-actions">
                            <a href="../admin/" class="btn btn-primary">Acessar Painel Admin</a>
                            <a href="../" class="btn btn-secondary">Ver Site</a>
                        </div>
                        <div class="security-notice">
                            <strong>Importante:</strong> Por seguranca, remova a pasta <code>/install/</code> do seu servidor.
                        </div>
                    </div>
                <?php else: ?>

                    <?php if ($step === 1): ?>
                        <form method="POST" action="?step=1" class="install-form">
                            <h2>Configuracao do Banco de Dados</h2>
                            <p class="form-desc">Informe os dados de conexao com seu servidor MySQL. O banco de dados sera criado automaticamente.</p>

                            <div class="form-group">
                                <label for="db_host">Servidor do Banco *</label>
                                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($db_host); ?>" required placeholder="localhost">
                                <span class="help-text">Geralmente 'localhost' ou IP do servidor</span>
                            </div>

                            <div class="form-group">
                                <label for="db_name">Nome do Banco *</label>
                                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($db_name); ?>" required placeholder="sitecatalogo">
                                <span class="help-text">Sera criado se nao existir</span>
                            </div>

                            <div class="form-group">
                                <label for="db_user">Usuario do Banco *</label>
                                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($db_user); ?>" required placeholder="root">
                            </div>

                            <div class="form-group">
                                <label for="db_pass">Senha do Banco</label>
                                <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($db_pass); ?>" placeholder="Sua senha">
                            </div>

                            <div class="form-group">
                                <label for="db_prefix">Prefixo das Tabelas</label>
                                <input type="text" id="db_prefix" name="db_prefix" value="<?php echo htmlspecialchars($db_prefix); ?>" placeholder="sc_">
                                <span class="help-text">Ex: sc_ -> tabelas serao sc_produtos, sc_categorias...</span>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Proximo Passo -></button>
                            </div>
                        </form>

                    <?php elseif ($step === 2): ?>
                        <form method="POST" action="?step=2" class="install-form">
                            <h2>Configuracoes do Site</h2>
                            <p class="form-desc">Personalize as informacoes basicas do seu catalogo.</p>

                            <div class="form-group">
                                <label for="site_name">Nome do Site *</label>
                                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" required placeholder="Meu Catalogo">
                            </div>

                            <div class="form-group">
                                <label for="site_description">Descricao do Site</label>
                                <textarea id="site_description" name="site_description" rows="3" placeholder="Breve descricao do seu catalogo"><?php echo htmlspecialchars($site_description); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="site_email">E-mail de Contato *</label>
                                <input type="email" id="site_email" name="site_email" value="<?php echo htmlspecialchars($site_email); ?>" required placeholder="contato@seusite.com">
                            </div>

                            <div class="form-group">
                                <label for="whatsapp">WhatsApp para Orcamentos</label>
                                <input type="text" id="whatsapp" name="whatsapp" value="<?php echo htmlspecialchars($whatsapp); ?>" placeholder="5511999999999">
                                <span class="help-text">Formato: 55 + DDD + Numero (somente numeros)</span>
                            </div>

                            <div class="form-group">
                                <label for="currency">Moeda</label>
                                <select id="currency" name="currency">
                                    <option value="BRL" selected>Real Brasileiro (R$)</option>
                                    <option value="USD">Dolar Americano ($)</option>
                                    <option value="EUR">Euro (EUR)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="timezone">Fuso Horario</label>
                                <select id="timezone" name="timezone">
                                    <option value="America/Sao_Paulo" selected>Brasilia (America/Sao_Paulo)</option>
                                    <option value="America/Fortaleza">Fortaleza (America/Fortaleza)</option>
                                    <option value="America/Manaus">Manaus (America/Manaus)</option>
                                    <option value="America/Recife">Recife (America/Recife)</option>
                                </select>
                            </div>

                            <div class="form-actions">
                                <a href="?step=1" class="btn btn-secondary"><- Voltar</a>
                                <button type="submit" class="btn btn-primary">Proximo Passo -></button>
                            </div>
                        </form>

                    <?php elseif ($step === 3): ?>
                        <form method="POST" action="?step=3" class="install-form">
                            <h2>Criar Conta Administrativa</h2>
                            <p class="form-desc">Crie o primeiro usuario administrador do sistema.</p>

                            <div class="form-group">
                                <label for="admin_name">Nome Completo *</label>
                                <input type="text" id="admin_name" name="admin_name" required placeholder="Joao Silva">
                            </div>

                            <div class="form-group">
                                <label for="admin_login">Nome de Usuario (Login) *</label>
                                <input type="text" id="admin_login" name="admin_login" required placeholder="admin">
                                <span class="help-text">Sera usado para fazer login no painel</span>
                            </div>

                            <div class="form-group">
                                <label for="admin_email">E-mail do Admin *</label>
                                <input type="email" id="admin_email" name="admin_email" required placeholder="admin@seusite.com">
                            </div>

                            <div class="form-group">
                                <label for="admin_pass">Senha *</label>
                                <input type="password" id="admin_pass" name="admin_pass" required placeholder="Min. 6 caracteres" minlength="6">
                                <span class="help-text">Minimo 6 caracteres</span>
                            </div>

                            <div class="form-group">
                                <label for="admin_pass2">Confirmar Senha *</label>
                                <input type="password" id="admin_pass2" name="admin_pass2" required placeholder="Repita a senha">
                            </div>

                            <div class="form-actions">
                                <a href="?step=2" class="btn btn-secondary"><- Voltar</a>
                                <button type="submit" class="btn btn-primary btn-install">Finalizar Instalacao</button>
                            </div>
                        </form>
                    <?php endif; ?>

                <?php endif; ?>
            </div>

            <div class="install-footer">
                <p>SiteCatalogo v1.0.0 - Sistema de Catalogo Profissional</p>
            </div>
        </div>
    </div>
</body>
</html>
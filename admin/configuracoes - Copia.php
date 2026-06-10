<?php
/**
 * SiteCatalogo - Configuracoes
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Configuracoes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST['config'] as $chave => $valor) {
            set_config($chave, trim($valor));
        }

        // Processar upload de logo
        if (!empty($_FILES['config']['name']['logo_cliente'])) {
            $upload = handle_upload([
                'name' => $_FILES['config']['name']['logo_cliente'],
                'tmp_name' => $_FILES['config']['tmp_name']['logo_cliente'],
                'error' => $_FILES['config']['error']['logo_cliente']
            ], 'config');
            if ($upload) {
                // Remover logo anterior
                $old_logo = get_config('logo_cliente');
                if ($old_logo) delete_upload($old_logo);
                set_config('logo_cliente', $upload);
            }
        }

        log_activity('update', 'configuracoes', "Configuracoes atualizadas");
        set_flash('success', 'Configuracoes salvas com sucesso!');
    } catch (Exception $e) {
        set_flash('error', 'Erro ao salvar: ' . $e->getMessage());
    }
    header('Location: configuracoes.php');
    exit;
}

// Garantir campo mostrar_preco existe
$exists = db()->prepare("SELECT COUNT(*) FROM " . table('configuracoes') . " WHERE chave = ?");
$exists->execute(['mostrar_preco']);
if ((int)$exists->fetchColumn() === 0) {
    db()->prepare("INSERT INTO " . table('configuracoes') . " (chave, valor, descricao, grupo, tipo, ordem, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)")
        ->execute(['mostrar_preco', '1', 'Mostrar precos e estoque no site', 'geral', 'select', 5]);
    // Adicionar opcoes
    db()->prepare("UPDATE " . table('configuracoes') . " SET opcoes = ? WHERE chave = ?")
        ->execute([json_encode(['1' => 'Sim - Mostrar precos e estoque', '0' => 'Nao - Ocultar precos e estoque']), 'mostrar_preco']);
}

// Garantir campo logo_cliente existe
$exists->execute(['logo_cliente']);
if ((int)$exists->fetchColumn() === 0) {
    db()->prepare("INSERT INTO " . table('configuracoes') . " (chave, valor, descricao, grupo, tipo, ordem, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)")
        ->execute(['logo_cliente', '', 'Logo do cliente (aparece no navbar e orcamentos)', 'aparencia', 'file', 1]);
}

// Garantir campo navbar_tipo existe
$exists->execute(['navbar_tipo']);
if ((int)$exists->fetchColumn() === 0) {
    db()->prepare("INSERT INTO " . table('configuracoes') . " (chave, valor, descricao, grupo, tipo, ordem, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)")
        ->execute(['navbar_tipo', 'imagem_texto', 'Tipo de exibicao do navbar', 'aparencia', 'select', 2]);
    db()->prepare("UPDATE " . table('configuracoes') . " SET opcoes = ? WHERE chave = ?")
        ->execute([json_encode(['imagem_texto' => 'Logo + Nome', 'imagem' => 'Apenas Logo', 'texto' => 'Apenas Nome']), 'navbar_tipo']);
}

$configuracoes = db()->query("SELECT * FROM " . table('configuracoes') . " WHERE ativo = 1 ORDER BY 
    CASE 
        WHEN grupo = 'geral' THEN 1
        WHEN grupo = 'contato' THEN 2
        WHEN grupo = 'email' THEN 3
        WHEN grupo = 'social' THEN 4
        WHEN grupo = 'aparencia' THEN 5
        ELSE 6
    END, ordem, id")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-cog"></i> Configuracoes</h1>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?php 
            $grupo_atual = '';
            foreach ($configuracoes as $cfg): 
                if ($cfg['grupo'] !== $grupo_atual):
                    if ($grupo_atual !== '') echo '</div>';
                    $grupo_atual = $cfg['grupo'];
                    $grupo_nome = [
                        'geral' => 'Configuracoes Gerais',
                        'contato' => 'Dados de Contato',
                        'social' => 'Redes Sociais',
                        'aparencia' => 'Aparência',
                    ][$grupo_atual] ?? ucfirst($grupo_atual);
            ?>
            <h3 style="margin: 32px 0 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; color: #0f172a; font-size: 1.125rem; font-weight: 600;">
                <i class="fas fa-folder" style="color: #3b82f6; margin-right: 8px;"></i><?php echo $grupo_nome; ?>
            </h3>
            <div class="form-row">
            <?php endif; ?>
                <div class="form-group">
                    <label for="cfg_<?php echo $cfg['chave']; ?>">
                        <?php echo sanitize($cfg['descricao'] ?: $cfg['chave']); ?>
                        <?php if ($cfg['chave'] === 'email_smtp_pass'): ?>
                        <i class="fas fa-lock" style="color: #ef4444; margin-left: 4px;" title="Campo sensivel"></i>
                        <?php endif; ?>
                    </label>
                    <?php if ($cfg['tipo'] === 'textarea'): ?>
                    <textarea id="cfg_<?php echo $cfg['chave']; ?>" name="config[<?php echo $cfg['chave']; ?>]" rows="3"><?php echo sanitize($cfg['valor']); ?></textarea>
                    <?php elseif ($cfg['tipo'] === 'file'): ?>
                    <input type="file" id="cfg_<?php echo $cfg['chave']; ?>" name="config[<?php echo $cfg['chave']; ?>]" accept="image/*">
                    <?php if (!empty($cfg['valor'])): ?>
                    <div style="margin-top: 8px;">
                        <img src="<?php echo uploads_url($cfg['valor']); ?>" alt="Logo atual" style="max-height: 60px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <p style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">Logo atual</p>
                    </div>
                    <?php endif; ?>
                    <?php elseif ($cfg['tipo'] === 'color'): ?>
                    <input type="color" id="cfg_<?php echo $cfg['chave']; ?>" name="config[<?php echo $cfg['chave']; ?>]" value="<?php echo sanitize($cfg['valor'] ?: '#3b82f6'); ?>" style="width:60px;height:40px;padding:2px;">
                    <?php elseif ($cfg['tipo'] === 'select' && $cfg['opcoes']): 
                        $opcoes = json_decode($cfg['opcoes'], true) ?: [];
                    ?>
                    <select id="cfg_<?php echo $cfg['chave']; ?>" name="config[<?php echo $cfg['chave']; ?>]">
                        <?php foreach ($opcoes as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo selected($cfg['valor'], $val); ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php elseif ($cfg['tipo'] === 'number'): ?>
                    <input type="number" id="cfg_<?php echo $cfg['chave']; ?>" name="config[<?php echo $cfg['chave']; ?>]" value="<?php echo (int)$cfg['valor']; ?>">
                    <?php else: ?>
                    <input type="<?php echo $cfg['chave'] === 'email_smtp_pass' ? 'password' : 'text'; ?>" id="cfg_<?php echo $cfg['chave']; ?>" name="config[<?php echo $cfg['chave']; ?>]" value="<?php echo sanitize($cfg['valor']); ?>" placeholder="<?php 
                        $placeholders = [
                            'email_smtp_host' => 'smtp.gmail.com, smtp.office365.com, smtp.mail.yahoo.com',
                            'email_smtp_port' => '587 (Gmail/Outlook), 465 (Yahoo), 25',
                            'email_smtp_user' => 'seuemail@gmail.com',
                            'email_smtp_pass' => 'Senha de app (nao a senha normal)',
                            'email_smtp_secure' => 'tls ou ssl',
                            'email_from_name' => 'Nome que aparece no e-mail enviado',
                            'email_from_email' => 'seuemail@gmail.com',
                        ];
                        echo $placeholders[$cfg['chave']] ?? '';
                    ?>">
                    <?php endif; ?>
                    <?php 
                    $helps = [
                        'whatsapp' => 'Formato: 55 + DDD + Numero (somente numeros)',
                        'email_smtp_host' => 'Gmail: smtp.gmail.com | Outlook: smtp.office365.com | Yahoo: smtp.mail.yahoo.com',
                        'email_smtp_port' => 'Gmail/Outlook: 587 | Yahoo: 465 | Use TLS para 587, SSL para 465',
                        'email_smtp_pass' => 'IMPORTANTE: Use "Senha de App", nao a senha normal da conta!',
                        'mostrar_preco' => 'Se definido como "Nao", precos e estoque nao serao exibidos no site publico',
                    ];
                    if (isset($helps[$cfg['chave']])): ?>
                    <small style="display: block; margin-top: 4px; color: #64748b; font-size: 0.75rem;">
                        <i class="fas fa-info-circle" style="margin-right: 4px;"></i><?php echo $helps[$cfg['chave']]; ?>
                    </small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
            
            <div class="form-actions" style="margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar Configuracoes</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
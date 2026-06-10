<?php
/**
 * SiteCatalogo - Banners (CRUD)
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Banners';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar') {
        $dados = [
            'titulo' => trim($_POST['titulo'] ?? ''),
            'subtitulo' => trim($_POST['subtitulo'] ?? ''),
            'link' => trim($_POST['link'] ?? ''),
            'texto_botao' => trim($_POST['texto_botao'] ?? 'Saiba Mais'),
            'posicao' => $_POST['posicao'] ?? 'home_topo',
            'ordem' => (int)($_POST['ordem'] ?? 0),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
            'data_inicio' => !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null,
            'data_fim' => !empty($_POST['data_fim']) ? $_POST['data_fim'] : null,
        ];
        
        if (empty($dados['titulo'])) {
            set_flash('error', 'Titulo obrigatorio');
        } else {
            try {
                // Upload imagem
                if (!empty($_FILES['imagem']['name'])) {
                    $upload = handle_upload($_FILES['imagem'], 'banners');
                    if ($upload) {
                        if ($id && !empty($_POST['imagem_atual'])) {
                            delete_upload($_POST['imagem_atual']);
                        }
                        $dados['imagem'] = $upload;
                    }
                }
                
                if ($id) {
                    $fields = []; $values = [];
                    foreach ($dados as $k => $v) { $fields[] = "{$k} = ?"; $values[] = $v; }
                    $values[] = $id;
                    db()->prepare("UPDATE " . table('banners') . " SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                    log_activity('update', 'banners', "Banner #{$id} atualizado");
                    set_flash('success', 'Banner atualizado!');
                } else {
                    if (empty($dados['imagem'])) {
                        set_flash('error', 'Imagem obrigatoria para novo banner');
                        goto skip_redirect;
                    }
                    $cols = implode(', ', array_keys($dados));
                    $ph = implode(', ', array_fill(0, count($dados), '?'));
                    db()->prepare("INSERT INTO " . table('banners') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
                    log_activity('create', 'banners', "Banner criado");
                    set_flash('success', 'Banner criado!');
                }
                header('Location: banners.php');
                exit;
            } catch (Exception $e) {
                set_flash('error', 'Erro: ' . $e->getMessage());
            }
        }
        skip_redirect:
    }
}

if ($action === 'delete' && $id) {
    try {
        $b = db()->prepare("SELECT imagem FROM " . table('banners') . " WHERE id = ?");
        $b->execute([$id]);
        $banner = $b->fetch();
        if ($banner && $banner['imagem']) delete_upload($banner['imagem']);
        db()->prepare("DELETE FROM " . table('banners') . " WHERE id = ?")->execute([$id]);
        log_activity('delete', 'banners', "Banner #{$id} excluido");
        set_flash('success', 'Banner excluido!');
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: banners.php'); exit;
}

$banner = null;
if ($action === 'edit' && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('banners') . " WHERE id = ?");
    $stmt->execute([$id]); $banner = $stmt->fetch();
}

$banners = db()->query("SELECT * FROM " . table('banners') . " ORDER BY posicao, ordem, id DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';

if ($action === 'edit' || $action === 'new'):
?>

<div class="page-header">
    <h1><i class="fas fa-image"></i> <?php echo $id ? 'Editar Banner' : 'Novo Banner'; ?></h1>
    <a href="banners.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="salvar">
            <?php if ($id): ?>
            <input type="hidden" name="imagem_atual" value="<?php echo $banner['imagem'] ?? ''; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="titulo">Titulo *</label>
                    <input type="text" id="titulo" name="titulo" value="<?php echo sanitize($banner['titulo'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="subtitulo">Subtitulo</label>
                    <input type="text" id="subtitulo" name="subtitulo" value="<?php echo sanitize($banner['subtitulo'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="link">Link do Botao</label>
                    <input type="url" id="link" name="link" value="<?php echo sanitize($banner['link'] ?? ''); ?>" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label for="texto_botao">Texto do Botao</label>
                    <input type="text" id="texto_botao" name="texto_botao" value="<?php echo sanitize($banner['texto_botao'] ?? 'Saiba Mais'); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="posicao">Posicao</label>
                    <select id="posicao" name="posicao">
                        <option value="home_topo" <?php echo selected($banner['posicao'] ?? '', 'home_topo'); ?>>Home - Topo</option>
                        <option value="home_meio" <?php echo selected($banner['posicao'] ?? '', 'home_meio'); ?>>Home - Meio</option>
                        <option value="home_rodape" <?php echo selected($banner['posicao'] ?? '', 'home_rodape'); ?>>Home - Rodape</option>
                        <option value="sidebar" <?php echo selected($banner['posicao'] ?? '', 'sidebar'); ?>>Sidebar</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ordem">Ordem</label>
                    <input type="number" id="ordem" name="ordem" value="<?php echo $banner['ordem'] ?? 0; ?>" min="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="data_inicio">Data Inicio</label>
                    <input type="date" id="data_inicio" name="data_inicio" value="<?php echo $banner['data_inicio'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label for="data_fim">Data Fim</label>
                    <input type="date" id="data_fim" name="data_fim" value="<?php echo $banner['data_fim'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="imagem">Imagem <?php echo $id ? '' : '*'; ?></label>
                    <input type="file" id="imagem" name="imagem" accept="image/*" <?php echo $id ? '' : 'required'; ?>>
                    <small>Recomendado: 1920x600px para banner topo</small>
                    <?php if (!empty($banner['imagem'])): ?>
                    <div style="margin-top: 12px;">
                        <img src="<?php echo uploads_url($banner['imagem']); ?>" alt="" style="max-width: 400px; max-height: 200px; border-radius: 8px; object-fit: cover;">
                    </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Opcoes</label>
                    <div class="form-check">
                        <input type="checkbox" id="ativo" name="ativo" <?php echo checked(($banner['ativo'] ?? 1) == 1); ?>>
                        <label for="ativo">Banner Ativo</label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="banners.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Banner</button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="page-header">
    <h1><i class="fas fa-image"></i> Banners</h1>
    <a href="banners.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Banner</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Imagem</th><th>Titulo</th><th>Posicao</th><th>Ordem</th><th>Status</th><th width="100">Acoes</th></tr></thead>
            <tbody>
                <?php foreach ($banners as $b): 
                    $img = $b['imagem'] ? uploads_url($b['imagem']) : assets_url('images/no-image.jpg');
                ?>
                <tr>
                    <td><img src="<?php echo $img; ?>" alt="" style="width:120px;height:60px;object-fit:cover;border-radius:6px;"></td>
                    <td>
                        <strong><?php echo sanitize($b['titulo']); ?></strong>
                        <?php if ($b['subtitulo']): ?><br><small style="color:var(--gray-400)"><?php echo sanitize($b['subtitulo']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $b['posicao'])); ?></td>
                    <td><?php echo $b['ordem']; ?></td>
                    <td><span class="badge-status status-<?php echo $b['ativo'] ? 'ativo' : 'inativo'; ?>"><?php echo $b['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                    <td class="actions">
                        <a href="banners.php?action=edit&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-secondary btn-icon"><i class="fas fa-edit"></i></a>
                        <a href="?action=delete&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-danger btn-icon btn-delete"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
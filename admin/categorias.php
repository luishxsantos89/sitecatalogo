<?php
/**
 * SiteCatalogo - Categorias (CRUD)
<<<<<<< HEAD
 * CORRIGIDO: Removidas colunas icone e parent_id que nao existem no banco
=======
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Categorias';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar') {
        $dados = [
            'nome' => trim($_POST['nome'] ?? ''),
            'slug' => slugify(trim($_POST['nome'] ?? '')),
            'descricao' => trim($_POST['descricao'] ?? ''),
<<<<<<< HEAD
            'ordem' => (int)($_POST['ordem'] ?? 0),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
=======
            'icone' => trim($_POST['icone'] ?? ''),
            'ordem' => (int)($_POST['ordem'] ?? 0),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
            'parent_id' => $_POST['parent_id'] ?: null,
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
        ];
        if (empty($dados['nome'])) {
            set_flash('error', 'Nome obrigatorio');
        } else {
            try {
                if (!empty($_FILES['imagem']['name'])) {
                    $upload = handle_upload($_FILES['imagem'], 'categorias');
                    if ($upload) {
                        if ($id && !empty($_POST['imagem_atual'])) delete_upload($_POST['imagem_atual']);
                        $dados['imagem'] = $upload;
                    }
                }
                if ($id) {
                    $dados['slug'] = unique_slug('categorias', $dados['slug'], $id);
                    $fields = []; $values = [];
                    foreach ($dados as $k => $v) { $fields[] = "{$k} = ?"; $values[] = $v; }
                    $values[] = $id;
                    db()->prepare("UPDATE " . table('categorias') . " SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                    log_activity('update', 'categorias', "Categoria #{$id} atualizada");
                    set_flash('success', 'Categoria atualizada!');
                } else {
                    $dados['slug'] = unique_slug('categorias', $dados['slug']);
                    $cols = implode(', ', array_keys($dados));
                    $ph = implode(', ', array_fill(0, count($dados), '?'));
                    db()->prepare("INSERT INTO " . table('categorias') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
                    log_activity('create', 'categorias', "Categoria criada");
                    set_flash('success', 'Categoria criada!');
                }
                header('Location: categorias.php'); exit;
            } catch (Exception $e) {
                set_flash('error', 'Erro: ' . $e->getMessage());
            }
        }
    }
}

if ($action === 'delete' && $id) {
    try {
        $c = db()->prepare("SELECT imagem FROM " . table('categorias') . " WHERE id = ?"); $c->execute([$id]); $cat = $c->fetch();
        if ($cat && $cat['imagem']) delete_upload($cat['imagem']);
        db()->prepare("DELETE FROM " . table('categorias') . " WHERE id = ?")->execute([$id]);
        log_activity('delete', 'categorias', "Categoria #{$id} excluida");
        set_flash('success', 'Categoria excluida!');
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: categorias.php'); exit;
}

$categoria = null;
if ($action === 'edit' && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('categorias') . " WHERE id = ?"); $stmt->execute([$id]); $categoria = $stmt->fetch();
}

<<<<<<< HEAD
$categorias = db()->query("SELECT * FROM " . table('categorias') . " ORDER BY ordem, nome")->fetchAll();
=======
$categorias = db()->query("SELECT c.*, p.nome as parent_nome FROM " . table('categorias') . " c LEFT JOIN " . table('categorias') . " p ON c.parent_id = p.id ORDER BY c.ordem, c.nome")->fetchAll();
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359

require_once __DIR__ . '/includes/header.php';

if ($action === 'edit' || $action === 'new'):
?>
<div class="page-header">
    <h1><i class="fas fa-tags"></i> <?php echo $id ? 'Editar' : 'Nova'; ?> Categoria</h1>
    <a href="categorias.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="salvar">
<<<<<<< HEAD
            <?php if ($id): ?><input type="hidden" name="imagem_atual" value="<?php echo htmlspecialchars($categoria['imagem'] ?? ''); ?>"><?php endif; ?>
=======
            <?php if ($id): ?><input type="hidden" name="imagem_atual" value="<?php echo $categoria['imagem'] ?? ''; ?>"><?php endif; ?>
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
            <div class="form-row">
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" value="<?php echo sanitize($categoria['nome'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
<<<<<<< HEAD
                    <label>Ordem</label>
                    <input type="number" name="ordem" value="<?php echo $categoria['ordem'] ?? 0; ?>">
=======
                    <label>Categoria Pai</label>
                    <select name="parent_id">
                        <option value="">Nenhuma</option>
                        <?php foreach ($categorias as $c): if ($c['id'] == $id) continue; ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo selected($categoria['parent_id'] ?? '', $c['id']); ?>><?php echo sanitize($c['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
                </div>
            </div>
            <div class="form-group">
                <label>Descricao</label>
                <textarea name="descricao" rows="3"><?php echo sanitize($categoria['descricao'] ?? ''); ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
<<<<<<< HEAD
=======
                    <label>Icone (classe FontAwesome)</label>
                    <input type="text" name="icone" value="<?php echo sanitize($categoria['icone'] ?? ''); ?>" placeholder="fa-box">
                </div>
                <div class="form-group">
                    <label>Ordem</label>
                    <input type="number" name="ordem" value="<?php echo $categoria['ordem'] ?? 0; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
                    <label>Imagem</label>
                    <input type="file" name="imagem" accept="image/*">
                    <?php if (!empty($categoria['imagem'])): ?>
                    <img src="<?php echo uploads_url($categoria['imagem']); ?>" class="form-image-preview">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-check"><input type="checkbox" name="ativo" <?php echo checked(($categoria['ativo'] ?? 1) == 1); ?>> Ativo</label>
                </div>
            </div>
            <div class="form-actions">
                <a href="categorias.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="page-header">
    <h1><i class="fas fa-tags"></i> Categorias</h1>
    <a href="categorias.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Nova Categoria</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table">
<<<<<<< HEAD
            <thead><tr><th>Nome</th><th>Slug</th><th>Ordem</th><th>Status</th><th width="100">Acoes</th></tr></thead>
            <tbody>
                <?php foreach ($categorias as $c): ?>
                <tr>
                    <td><?php echo sanitize($c['nome']); ?></td>
                    <td><code><?php echo sanitize($c['slug']); ?></code></td>
=======
            <thead><tr><th>Nome</th><th>Slug</th><th>Pai</th><th>Ordem</th><th>Status</th><th width="100">Acoes</th></tr></thead>
            <tbody>
                <?php foreach ($categorias as $c): ?>
                <tr>
                    <td><i class="fas <?php echo $c['icone'] ?: 'fa-tag'; ?>" style="color:var(--primary);margin-right:8px;"></i><?php echo sanitize($c['nome']); ?></td>
                    <td><code><?php echo sanitize($c['slug']); ?></code></td>
                    <td><?php echo sanitize($c['parent_nome'] ?? '-'); ?></td>
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
                    <td><?php echo $c['ordem']; ?></td>
                    <td><span class="badge-status status-<?php echo $c['ativo'] ? 'ativo' : 'inativo'; ?>"><?php echo $c['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                    <td class="actions">
                        <a href="categorias.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-secondary btn-icon"><i class="fas fa-edit"></i></a>
<<<<<<< HEAD
                        <a href="?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger btn-icon btn-delete" onclick="return confirm('Excluir categoria?')"><i class="fas fa-trash"></i></a>
=======
                        <a href="?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger btn-icon btn-delete"><i class="fas fa-trash"></i></a>
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<<<<<<< HEAD
<?php require_once __DIR__ . '/includes/footer.php'; ?>
=======
<?php require_once __DIR__ . '/includes/footer.php'; ?>
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359

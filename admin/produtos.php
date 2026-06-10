<?php
/**
 * SiteCatalogo - Produtos (CRUD)
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Produtos';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Processar form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'salvar') {
        $dados = [
            'nome' => trim($_POST['nome'] ?? ''),
            'slug' => slugify(trim($_POST['nome'] ?? '')),
            'descricao_curta' => trim($_POST['descricao_curta'] ?? ''),
            'descricao_completa' => trim($_POST['descricao_completa'] ?? ''),
            'sku' => trim($_POST['sku'] ?? ''),
            'preco' => str_replace(',', '.', $_POST['preco'] ?? 0),
            'preco_promocional' => !empty($_POST['preco_promocional']) ? str_replace(',', '.', $_POST['preco_promocional']) : null,
            'custo' => !empty($_POST['custo']) ? str_replace(',', '.', $_POST['custo']) : null,
            'unidade' => $_POST['unidade'] ?? 'un',
            'peso' => $_POST['peso'] ?: null,
            'largura' => !empty($_POST['largura']) ? str_replace(',', '.', $_POST['largura']) : null,
            'altura' => !empty($_POST['altura']) ? str_replace(',', '.', $_POST['altura']) : null,
            'mt2' => !empty($_POST['mt2']) ? str_replace(',', '.', $_POST['mt2']) : null,
            'quantidade_estoque' => (int)($_POST['quantidade_estoque'] ?? 0),
            'estoque_minimo' => (int)($_POST['estoque_minimo'] ?? 0),
            'destaque' => isset($_POST['destaque']) ? 1 : 0,
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
            'categoria_id' => $_POST['categoria_id'] ?: null,
            'tags' => trim($_POST['tags'] ?? ''),
            'seo_title' => trim($_POST['seo_title'] ?? ''),
            'seo_description' => trim($_POST['seo_description'] ?? ''),
            'seo_keywords' => trim($_POST['seo_keywords'] ?? ''),
        ];
        
        if (empty($dados['nome'])) {
            set_flash('error', 'Nome do produto e obrigatorio');
        } else {
            try {
                if (!empty($_FILES['imagem']['name'])) {
                    $upload = handle_upload($_FILES['imagem'], 'produtos');
                    if ($upload) {
                        if ($id && !empty($_POST['imagem_atual'])) {
                            delete_upload($_POST['imagem_atual']);
                        }
                        $dados['imagem_principal'] = $upload;
                    }
                }
                
                if ($id) {
                    $dados['slug'] = unique_slug('produtos', $dados['slug'], $id);
                    $fields = [];
                    $values = [];
                    foreach ($dados as $k => $v) {
                        $fields[] = "{$k} = ?";
                        $values[] = $v;
                    }
                    $values[] = $id;
                    
                    db()->prepare("UPDATE " . table('produtos') . " SET " . implode(', ', $fields) . " WHERE id = ?")
                        ->execute($values);
                    
                    log_activity('update', 'produtos', "Produto #{$id} atualizado");
                    set_flash('success', 'Produto atualizado com sucesso!');
                } else {
                    $dados['slug'] = unique_slug('produtos', $dados['slug']);
                    $cols = implode(', ', array_keys($dados));
                    $placeholders = implode(', ', array_fill(0, count($dados), '?'));
                    
                    db()->prepare("INSERT INTO " . table('produtos') . " ({$cols}) VALUES ({$placeholders})")
                        ->execute(array_values($dados));
                    
                    $id = db()->lastInsertId();
                    log_activity('create', 'produtos', "Produto #{$id} criado");
                    set_flash('success', 'Produto criado com sucesso!');
                }
                
                if (!empty($_FILES['imagens']['name'][0])) {
                    foreach ($_FILES['imagens']['tmp_name'] as $i => $tmp) {
                        if ($_FILES['imagens']['error'][$i] === UPLOAD_ERR_OK) {
                            $fake_file = [
                                'name' => $_FILES['imagens']['name'][$i],
                                'tmp_name' => $tmp,
                                'error' => 0
                            ];
                            $upload = handle_upload($fake_file, 'produtos');
                            if ($upload) {
                                db()->prepare("INSERT INTO " . table('produto_imagens') . " (produto_id, imagem) VALUES (?, ?)")
                                    ->execute([$id, $upload]);
                            }
                        }
                    }
                }
                
                header('Location: produtos.php');
                exit;
            } catch (Exception $e) {
                set_flash('error', 'Erro: ' . $e->getMessage());
            }
        }
    }
}

// Deletar
if ($action === 'delete' && $id) {
    try {
        $prod = db()->prepare("SELECT imagem_principal FROM " . table('produtos') . " WHERE id = ?");
        $prod->execute([$id]);
        $p = $prod->fetch();
        
        if ($p && $p['imagem_principal']) {
            delete_upload($p['imagem_principal']);
        }
        
        $imgs = db()->prepare("SELECT imagem FROM " . table('produto_imagens') . " WHERE produto_id = ?");
        $imgs->execute([$id]);
        foreach ($imgs->fetchAll() as $img) {
            delete_upload($img['imagem']);
        }
        
        db()->prepare("DELETE FROM " . table('produtos') . " WHERE id = ?")->execute([$id]);
        log_activity('delete', 'produtos', "Produto #{$id} excluido");
        set_flash('success', 'Produto excluido com sucesso!');
    } catch (Exception $e) {
        set_flash('error', 'Erro ao excluir: ' . $e->getMessage());
    }
    header('Location: produtos.php');
    exit;
}

// Deletar imagem
if ($action === 'delete_img' && isset($_GET['img_id'])) {
    try {
        $img = db()->prepare("SELECT * FROM " . table('produto_imagens') . " WHERE id = ?");
        $img->execute([(int)$_GET['img_id']]);
        $i = $img->fetch();
        if ($i) {
            delete_upload($i['imagem']);
            db()->prepare("DELETE FROM " . table('produto_imagens') . " WHERE id = ?")->execute([$i['id']]);
            set_flash('success', 'Imagem removida');
        }
    } catch (Exception $e) {}
    header('Location: produtos.php?action=edit&id=' . $id);
    exit;
}

// Buscar produto para editar
$produto = null;
$imagens = [];
if ($action === 'edit' && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('produtos') . " WHERE id = ?");
    $stmt->execute([$id]);
    $produto = $stmt->fetch();
    
    if ($produto) {
        $stmt = db()->prepare("SELECT * FROM " . table('produto_imagens') . " WHERE produto_id = ? ORDER BY ordem");
        $stmt->execute([$id]);
        $imagens = $stmt->fetchAll();
    }
}

// Listar
$busca = $_GET['busca'] ?? '';
$categoria_filtro = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$where = ["1=1"];
$params = [];
if ($busca) {
    $where[] = "(p.nome LIKE ? OR p.sku LIKE ?)";
    $like = "%{$busca}%";
    $params = [$like, $like];
}
if ($categoria_filtro) {
    $where[] = "categoria_id = ?";
    $params[] = $categoria_filtro;
}
$where_sql = implode(' AND ', $where);

$stmt = db()->prepare("SELECT COUNT(*) FROM " . table('produtos') . " WHERE {$where_sql}");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pagination = paginate($total, $page, 15);

// CORRECAO: Garantir offset nao negativo
$offset = max(0, $pagination['offset']);

$stmt = db()->prepare("SELECT p.*, c.nome as categoria_nome 
    FROM " . table('produtos') . " p 
    LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id 
    WHERE {$where_sql} 
    ORDER BY p.created_at DESC 
    LIMIT {$offset}, {$pagination['per_page']}");
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// Categorias para select
$categorias = db()->query("SELECT * FROM " . table('categorias') . " WHERE ativo = 1 ORDER BY nome")->fetchAll();

require_once __DIR__ . '/includes/header.php';

if ($action === 'edit' || $action === 'new'):
?>

<div class="page-header">
    <h1><i class="fas fa-box-open"></i> <?php echo $id ? 'Editar Produto' : 'Novo Produto'; ?></h1>
    <div class="page-actions">
        <a href="produtos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="salvar">
            <?php if ($id): ?>
            <input type="hidden" name="imagem_atual" value="<?php echo $produto['imagem_principal'] ?? ''; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nome">Nome do Produto *</label>
                    <input type="text" id="nome" name="nome" value="<?php echo sanitize($produto['nome'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="sku">SKU / Codigo</label>
                    <input type="text" id="sku" name="sku" value="<?php echo sanitize($produto['sku'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="categoria_id">Categoria</label>
                    <select id="categoria_id" name="categoria_id">
                        <option value="">Sem Categoria</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo selected($produto['categoria_id'] ?? '', $cat['id']); ?>>
                            <?php echo sanitize($cat['nome']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tags">Tags</label>
                    <input type="text" id="tags" name="tags" value="<?php echo sanitize($produto['tags'] ?? ''); ?>" placeholder="tag1, tag2, tag3">
                </div>
            </div>
            
            <div class="form-group">
                <label for="descricao_curta">Descricao Curta</label>
                <textarea id="descricao_curta" name="descricao_curta" rows="2"><?php echo sanitize($produto['descricao_curta'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="descricao_completa">Descricao Completa</label>
                <textarea id="descricao_completa" name="descricao_completa" rows="6"><?php echo sanitize($produto['descricao_completa'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row-3">
                <div class="form-group">
                    <label for="preco">Preco (R$)</label>
                    <input type="text" id="preco" name="preco" value="<?php echo $produto['preco'] ?? ''; ?>" placeholder="0,00">
                </div>
                <div class="form-group">
                    <label for="preco_promocional">Preco Promocional (R$)</label>
                    <input type="text" id="preco_promocional" name="preco_promocional" value="<?php echo $produto['preco_promocional'] ?? ''; ?>" placeholder="0,00">
                </div>
                <div class="form-group">
                    <label for="custo">Custo (R$)</label>
                    <input type="text" id="custo" name="custo" value="<?php echo $produto['custo'] ?? ''; ?>" placeholder="0,00">
                </div>
            </div>
            
            <div class="form-row-3">
                <div class="form-group">
                    <label for="unidade">Unidade</label>
                    <input type="text" id="unidade" name="unidade" value="<?php echo sanitize($produto['unidade'] ?? 'un'); ?>">
                </div>
                <div class="form-group">
                    <label for="quantidade_estoque">Estoque Atual</label>
                    <input type="number" id="quantidade_estoque" name="quantidade_estoque" value="<?php echo $produto['quantidade_estoque'] ?? 0; ?>">
                </div>
                <div class="form-group">
                    <label for="estoque_minimo">Estoque Minimo</label>
                    <input type="number" id="estoque_minimo" name="estoque_minimo" value="<?php echo $produto['estoque_minimo'] ?? 0; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="imagem">Imagem Principal</label>
                    <input type="file" id="imagem" name="imagem" accept="image/*">
                    <?php if (!empty($produto['imagem_principal'])): ?>
                    <img src="<?php echo uploads_url($produto['imagem_principal']); ?>" alt="" class="form-image-preview">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="imagens">Imagens Adicionais</label>
                    <input type="file" id="imagens" name="imagens[]" accept="image/*" multiple>
                    <?php if (!empty($imagens)): ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                        <?php foreach ($imagens as $img): ?>
                        <div style="position:relative;">
                            <img src="<?php echo uploads_url($img['imagem']); ?>" alt="" style="width:80px;height:60px;object-fit:cover;border-radius:6px;">
                            <a href="?action=delete_img&id=<?php echo $id; ?>&img_id=<?php echo $img['id']; ?>" class="btn-delete" style="position:absolute;top:-4px;right:-4px;width:20px;height:20px;background:var(--danger);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;text-decoration:none;" onclick="return confirm('Remover imagem?')">&times;</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>SEO</label>
                    <input type="text" name="seo_title" value="<?php echo sanitize($produto['seo_title'] ?? ''); ?>" placeholder="Titulo SEO">
                    <input type="text" name="seo_description" value="<?php echo sanitize($produto['seo_description'] ?? ''); ?>" placeholder="Descricao SEO" style="margin-top:8px;">
                    <input type="text" name="seo_keywords" value="<?php echo sanitize($produto['seo_keywords'] ?? ''); ?>" placeholder="Keywords SEO" style="margin-top:8px;">
                </div>
                <div class="form-group">
                    <label>Opcoes</label>
                    <div class="form-check">
                        <input type="checkbox" id="destaque" name="destaque" <?php echo checked(($produto['destaque'] ?? 0) == 1); ?>>
                        <label for="destaque">Produto em Destaque</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="ativo" name="ativo" <?php echo checked(($produto['ativo'] ?? 1) == 1); ?>>
                        <label for="ativo">Produto Ativo</label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions" style="margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);">
                <a href="produtos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Produto</button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="page-header">
    <h1><i class="fas fa-box-open"></i> Produtos</h1>
    <div class="page-actions">
        <a href="produtos.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Produto</a>
    </div>
</div>

<!-- Filters -->
<div class="filters-bar">
    <div class="form-group">
        <input type="text" id="busca" name="busca" value="<?php echo sanitize($busca); ?>" placeholder="Buscar produtos..." form="filterForm">
    </div>
    <div class="form-group">
        <select name="categoria_id" form="filterForm">
            <option value="">Todas Categorias</option>
            <?php foreach ($categorias as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo selected($categoria_filtro, $cat['id']); ?>><?php echo sanitize($cat['nome']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary" form="filterForm"><i class="fas fa-search"></i> Filtrar</button>
    <a href="produtos.php" class="btn btn-secondary">Limpar</a>
    <form id="filterForm" method="GET" action="" style="display:none;"></form>
</div>

<!-- List -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Imagem</th>
                    <th>Nome / SKU</th>
                    <th>Categoria</th>
                    <th>Preco</th>
                    <th>Dimensoes</th>
                    <th>Estoque</th>
                    <th>Status</th>
                    <th width="120">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $p): 
                    $img = $p['imagem_principal'] ? uploads_url($p['imagem_principal']) : assets_url('images/no-image.jpg');
                ?>
                <tr>
                    <td>
                        <img src="<?php echo $img; ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">
                    </td>
                    <td>
                        <strong><?php echo sanitize($p['nome']); ?></strong>
                        <?php if ($p['sku']): ?><br><small style="color:var(--gray-400)"><?php echo sanitize($p['sku']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo sanitize($p['categoria_nome'] ?? '-'); ?></td>
                    <td>
                        <?php 
                        $dims = [];
                        if (isset($p['largura']) && $p['largura'] > 0) $dims[] = 'L: ' . number_format((float)$p['largura'], 2, ',', '.') . 'm';
                        if (isset($p['altura']) && $p['altura'] > 0) $dims[] = 'A: ' . number_format((float)$p['altura'], 2, ',', '.') . 'm';
                        if (isset($p['mt2']) && $p['mt2'] > 0) $dims[] = number_format((float)$p['mt2'], 2, ',', '.') . 'm²';
                        echo !empty($dims) ? implode('<br>', $dims) : '-';
                        ?>
                    </td>
                    <td>
                        <?php if ($p['preco_promocional']): ?>
                        <span style="text-decoration:line-through;color:var(--gray-400);font-size:0.8125rem;"><?php echo format_currency((float)$p['preco']); ?></span><br>
                        <strong style="color:var(--primary);"><?php echo format_currency((float)$p['preco_promocional']); ?></strong>
                        <?php elseif ($p['preco'] > 0): ?>
                        <strong><?php echo format_currency((float)$p['preco']); ?></strong>
                        <?php else: ?>
                        <span style="color:var(--gray-400)">Sob consulta</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['quantidade_estoque'] <= $p['estoque_minimo'] && $p['estoque_minimo'] > 0): ?>
                        <span class="text-danger"><strong><?php echo $p['quantidade_estoque']; ?></strong></span>
                        <?php else: ?>
                        <?php echo $p['quantidade_estoque']; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['destaque']): ?><span class="badge-status" style="background:#fef3c7;color:#92400e;margin-bottom:4px;">Destaque</span><br><?php endif; ?>
                        <span class="badge-status status-<?php echo $p['ativo'] ? 'ativo' : 'inativo'; ?>"><?php echo $p['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="produtos.php?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-secondary btn-icon" title="Editar"><i class="fas fa-edit"></i></a>
                            <a href="?action=delete&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger btn-icon btn-delete" title="Excluir"><i class="fas fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination-wrap">
        <?php 
        $base_params = [];
        if ($busca) $base_params['busca'] = $busca;
        if ($categoria_filtro) $base_params['categoria_id'] = $categoria_filtro;
        echo pagination_links($pagination, 'produtos.php', $base_params); 
        ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
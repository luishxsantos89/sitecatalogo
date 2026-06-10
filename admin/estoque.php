<?php
/**
 * SiteCatalogo - Estoque
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Estoque';

// Movimentacao
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'movimentar') {
    try {
        $pid = (int)$_POST['produto_id'];
        $tipo = $_POST['tipo'];
        $qtd = (int)$_POST['quantidade'];
        $motivo = trim($_POST['motivo'] ?? '');
        $obs = trim($_POST['observacoes'] ?? '');
        $uid = $_SESSION['admin_id'] ?? null;
        
        if ($qtd <= 0) { set_flash('error', 'Quantidade invalida'); }
        else {
            $prod = db()->prepare("SELECT quantidade_estoque FROM " . table('produtos') . " WHERE id = ?");
            $prod->execute([$pid]); $p = $prod->fetch();
            
            if ($p) {
                $qtd_anterior = (int)$p['quantidade_estoque'];
                if ($tipo === 'saida' || $tipo === 'ajuste') { $qtd = -$qtd; }
                $nova_qtd = max(0, $qtd_anterior + $qtd);
                
                db()->prepare("UPDATE " . table('produtos') . " SET quantidade_estoque = ? WHERE id = ?")->execute([$nova_qtd, $pid]);
                db()->prepare("INSERT INTO " . table('produto_estoque') . " (produto_id, tipo, quantidade, quantidade_anterior, motivo, usuario_id, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$pid, $tipo === 'entrada' ? 'entrada' : ($tipo === 'saida' ? 'saida' : 'ajuste'), abs($qtd), $qtd_anterior, $motivo, $uid, $obs]);
                
                log_activity('update', 'estoque', "Estoque produto #{$pid}: {$tipo} de " . abs($qtd));
                set_flash('success', 'Movimentacao registrada!');
            }
        }
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: estoque.php'); exit;
}

// Filtros
$filtro = $_GET['filtro'] ?? '';
$busca = $_GET['busca'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$where = ["1=1"]; $params = [];
if ($busca) { $where[] = "(p.nome LIKE ? OR p.sku LIKE ?)"; $like = "%{$busca}%"; $params = [$like, $like]; }
if ($filtro === 'baixo') { $where[] = "p.quantidade_estoque <= p.estoque_minimo AND p.estoque_minimo > 0"; }
if ($filtro === 'zero') { $where[] = "p.quantidade_estoque = 0"; }
$where_sql = implode(' AND ', $where);

$stmt = db()->prepare("SELECT COUNT(*) FROM " . table('produtos') . " p WHERE {$where_sql}");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pagination = paginate($total, $page, 20);

// CORRECAO: Garantir offset nao negativo
$offset = max(0, $pagination['offset']);

$stmt = db()->prepare("SELECT p.*, c.nome as categoria_nome FROM " . table('produtos') . " p LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id WHERE {$where_sql} ORDER BY p.quantidade_estoque ASC LIMIT {$offset}, 20");
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// Historico recente
$historico = db()->query("SELECT e.*, p.nome as produto_nome, u.nome_completo as usuario_nome FROM " . table('produto_estoque') . " e LEFT JOIN " . table('produtos') . " p ON e.produto_id = p.id LEFT JOIN " . table('usuarios') . " u ON e.usuario_id = u.id ORDER BY e.created_at DESC LIMIT 20")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-warehouse"></i> Controle de Estoque</h1>
</div>

<div class="filters-bar">
    <div class="form-group"><input type="text" name="busca" value="<?php echo sanitize($busca); ?>" placeholder="Buscar produto..." form="filterForm"></div>
    <div class="form-group">
        <select name="filtro" form="filterForm">
            <option value="">Todos</option>
            <option value="baixo" <?php echo selected($filtro, 'baixo'); ?>>Estoque Baixo</option>
            <option value="zero" <?php echo selected($filtro, 'zero'); ?>>Sem Estoque</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary" form="filterForm"><i class="fas fa-search"></i></button>
    <a href="estoque.php" class="btn btn-secondary">Limpar</a>
    <form id="filterForm" method="GET" style="display:none;"></form>
</div>

<!-- Movimentar -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3><i class="fas fa-exchange-alt"></i> Movimentar Estoque</h3></div>
    <div class="card-body">
        <form method="POST" class="form-row">
            <input type="hidden" name="acao" value="movimentar">
            <div class="form-group">
                <label>Produto</label>
                <select name="produto_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach (db()->query("SELECT id, nome, quantidade_estoque FROM " . table('produtos') . " WHERE ativo = 1 ORDER BY nome")->fetchAll() as $p): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo sanitize($p['nome']); ?> (<?php echo $p['quantidade_estoque']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Tipo</label><select name="tipo" required><option value="entrada">Entrada</option><option value="saida">Saida</option><option value="ajuste">Ajuste</option></select></div>
            <div class="form-group"><label>Quantidade</label><input type="number" name="quantidade" min="1" required></div>
            <div class="form-group"><label>Motivo</label><input type="text" name="motivo" placeholder="Ex: Compra, Venda, Perda..."></div>
            <div class="form-group" style="display:flex;align-items:flex-end;"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Registrar</button></div>
        </form>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Produtos -->
    <div class="card">
        <div class="card-header"><h3>Produtos</h3></div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Produto</th><th>Estoque</th><th>Minimo</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($produtos as $p): 
                            $estado = $p['quantidade_estoque'] <= 0 ? 'esgotado' : ($p['quantidade_estoque'] <= $p['estoque_minimo'] && $p['estoque_minimo'] > 0 ? 'baixo' : 'ok');
                        ?>
                        <tr>
                            <td><strong><?php echo sanitize($p['nome']); ?></strong><br><small style="color:var(--gray-400)"><?php echo sanitize($p['categoria_nome'] ?? ''); ?></small></td>
                            <td><strong style="color:<?php echo $estado === 'esgotado' ? 'var(--danger)' : ($estado === 'baixo' ? 'var(--warning)' : 'var(--accent)'); ?>"><?php echo $p['quantidade_estoque']; ?></strong></td>
                            <td><?php echo $p['estoque_minimo']; ?></td>
                            <td>
                                <?php if ($estado === 'esgotado'): ?><span class="badge-status" style="background:var(--danger-light);color:var(--danger)">Esgotado</span>
                                <?php elseif ($estado === 'baixo'): ?><span class="badge-status" style="background:#fef3c7;color:#92400e">Baixo</span>
                                <?php else: ?><span class="badge-status status-ativo">OK</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pagination['total_pages'] > 1): ?>
            <div class="pagination-wrap"><?php echo pagination_links($pagination, 'estoque.php', array_filter(['filtro' => $filtro, 'busca' => $busca])); ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Historico -->
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-history"></i> Historico</h3></div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Qtd</th><th>Motivo</th></tr></thead>
                    <tbody>
                        <?php foreach ($historico as $h): ?>
                        <tr>
                            <td><?php echo format_date($h['created_at'], 'd/m H:i'); ?></td>
                            <td><?php echo sanitize($h['produto_nome'] ?? ''); ?></td>
                            <td><span class="badge-status" style="background:<?php echo $h['tipo']==='entrada'?'#d1fae5':($h['tipo']==='saida'?'var(--danger-light)':'#fef3c7'); ?>;color:<?php echo $h['tipo']==='entrada'?'#065f46':($h['tipo']==='saida'?'var(--danger)':'#92400e'); ?>"><?php echo ucfirst($h['tipo']); ?></span></td>
                            <td><?php echo $h['quantidade']; ?></td>
                            <td><?php echo sanitize($h['motivo'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
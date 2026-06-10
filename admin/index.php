<?php
/**
 * SiteCatalogo - Dashboard
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Dashboard';

$counts = get_counts();

// Ultimos orcamentos
$stmt = db()->query("SELECT o.*, 
    (SELECT COUNT(*) FROM " . table('orcamento_itens') . " WHERE orcamento_id = o.id) as total_itens 
    FROM " . table('orcamentos') . " o ORDER BY o.created_at DESC LIMIT 5");
$ultimos_orcamentos = $stmt->fetchAll();

// Produtos em estoque baixo
$stmt = db()->query("SELECT p.*, c.nome as categoria_nome 
    FROM " . table('produtos') . " p 
    LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id 
    WHERE p.quantidade_estoque <= p.estoque_minimo AND p.ativo = 1 
    ORDER BY p.quantidade_estoque ASC LIMIT 5");
$estoque_baixo_list = $stmt->fetchAll();

// Produtos mais vistos
$stmt = db()->query("SELECT * FROM " . table('produtos') . " WHERE ativo = 1 ORDER BY visualizacoes DESC LIMIT 5");
$mais_vistos = $stmt->fetchAll();

// Estatisticas mensais
$stmt = db()->query("SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as mes,
    COUNT(*) as total 
    FROM " . table('orcamentos') . " 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
    GROUP BY mes ORDER BY mes");
$orcamentos_mes = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-chart-pie"></i> Dashboard</h1>
    <p class="text-muted">Visao geral do seu catalogo</p>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-box-open"></i>
        </div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['produtos']; ?></span>
            <span class="stat-label">Produtos</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-tags"></i>
        </div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['categorias']; ?></span>
            <span class="stat-label">Categorias</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['orcamentos_novos']; ?></span>
            <span class="stat-label">Orcamentos Novos</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['clientes']; ?></span>
            <span class="stat-label">Clientes</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['estoque_baixo']; ?></span>
            <span class="stat-label">Estoque Baixo</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal">
            <i class="fas fa-user-shield"></i>
        </div>
        <div class="stat-info">
            <span class="stat-number"><?php echo $counts['usuarios']; ?></span>
            <span class="stat-label">Usuarios</span>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Ultimos Orcamentos -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice-dollar"></i> Ultimos Orcamentos</h3>
            <a href="orcamentos.php" class="btn btn-sm btn-secondary">Ver Todos</a>
        </div>
        <div class="card-body">
            <?php if (empty($ultimos_orcamentos)): ?>
            <div class="empty-state-sm">
                <p>Nenhum orcamento ainda</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Cliente</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimos_orcamentos as $o): ?>
                        <tr>
                            <td><strong><?php echo sanitize($o['codigo']); ?></strong></td>
                            <td><?php echo sanitize($o['cliente_nome']); ?></td>
                            <td><span class="badge-status status-<?php echo $o['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $o['status'])); ?></span></td>
                            <td><?php echo format_date($o['created_at'], 'd/m/Y'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Estoque Baixo -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Estoque Baixo</h3>
            <a href="estoque.php" class="btn btn-sm btn-secondary">Ver Todos</a>
        </div>
        <div class="card-body">
            <?php if (empty($estoque_baixo_list)): ?>
            <div class="empty-state-sm">
                <p>Todos os produtos com estoque ok</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Atual</th>
                            <th>Minimo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estoque_baixo_list as $p): ?>
                        <tr>
                            <td><?php echo sanitize($p['nome']); ?></td>
                            <td><span class="text-danger"><strong><?php echo $p['quantidade_estoque']; ?></strong></span></td>
                            <td><?php echo $p['estoque_minimo']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * SiteCatalogo - Clientes (CRUD)
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Clientes';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar') {
        $dados = [
            'nome_razaosocial' => trim($_POST['nome_razaosocial'] ?? ''),
            'tipo_pessoa' => $_POST['tipo_pessoa'] ?? 'fisica',
            'cpf_cnpj' => preg_replace('/\D/', '', $_POST['cpf_cnpj'] ?? ''),
            'rg_ie' => trim($_POST['rg_ie'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'telefone' => preg_replace('/\D/', '', $_POST['telefone'] ?? ''),
            'celular' => preg_replace('/\D/', '', $_POST['celular'] ?? ''),
            'cep' => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
            'endereco' => trim($_POST['endereco'] ?? ''),
            'numero' => trim($_POST['numero'] ?? ''),
            'complemento' => trim($_POST['complemento'] ?? ''),
            'bairro' => trim($_POST['bairro'] ?? ''),
            'cidade' => trim($_POST['cidade'] ?? ''),
            'estado' => $_POST['estado'] ?? '',
            'observacoes' => trim($_POST['observacoes'] ?? ''),
        ];
        if (empty($dados['nome_razaosocial'])) { set_flash('error', 'Nome obrigatorio'); }
        else {
            try {
                if ($id) {
                    $fields = []; $values = [];
                    foreach ($dados as $k => $v) { $fields[] = "{$k} = ?"; $values[] = $v; }
                    $values[] = $id;
                    db()->prepare("UPDATE " . table('clientes') . " SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                    log_activity('update', 'clientes', "Cliente #{$id} atualizado");
                    set_flash('success', 'Cliente atualizado!');
                } else {
                    $cols = implode(', ', array_keys($dados));
                    $ph = implode(', ', array_fill(0, count($dados), '?'));
                    db()->prepare("INSERT INTO " . table('clientes') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
                    log_activity('create', 'clientes', "Cliente criado");
                    set_flash('success', 'Cliente criado!');
                }
                header('Location: clientes.php'); exit;
            } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
        }
    }
}

if ($action === 'delete' && $id) {
    try {
        db()->prepare("DELETE FROM " . table('clientes') . " WHERE id = ?")->execute([$id]);
        log_activity('delete', 'clientes', "Cliente #{$id} excluido");
        set_flash('success', 'Cliente excluido!');
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: clientes.php'); exit;
}

$cliente = null;
if ($action === 'edit' && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('clientes') . " WHERE id = ?"); $stmt->execute([$id]); $cliente = $stmt->fetch();
}

$busca = $_GET['busca'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$where = "1=1"; $params = [];
if ($busca) { $where .= " AND (nome_razaosocial LIKE ? OR cpf_cnpj LIKE ? OR email LIKE ?)"; $like = "%{$busca}%"; $params = [$like, $like, $like]; }

$stmt = db()->prepare("SELECT COUNT(*) FROM " . table('clientes') . " WHERE {$where}");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pagination = paginate($total, $page, 15);

// CORRECAO: Garantir offset nao negativo
$offset = max(0, $pagination['offset']);

$stmt = db()->prepare("SELECT * FROM " . table('clientes') . " WHERE {$where} ORDER BY nome_razaosocial LIMIT {$offset}, {$pagination['per_page']}");
$stmt->execute($params);
$clientes = $stmt->fetchAll();

$estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

require_once __DIR__ . '/includes/header.php';

if ($action === 'edit' || $action === 'new'):
?>
<div class="page-header">
    <h1><i class="fas fa-users"></i> <?php echo $id ? 'Editar' : 'Novo'; ?> Cliente</h1>
    <a href="clientes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="acao" value="salvar">
            <div class="form-row">
                <div class="form-group"><label>Nome / Razao Social *</label><input type="text" name="nome_razaosocial" value="<?php echo sanitize($cliente['nome_razaosocial'] ?? ''); ?>" required></div>
                <div class="form-group"><label>Tipo</label><select name="tipo_pessoa"><option value="fisica" <?php echo selected($cliente['tipo_pessoa'] ?? '', 'fisica'); ?>>Pessoa Fisica</option><option value="juridica" <?php echo selected($cliente['tipo_pessoa'] ?? '', 'juridica'); ?>>Pessoa Juridica</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>CPF/CNPJ</label><input type="text" name="cpf_cnpj" value="<?php echo sanitize($cliente['cpf_cnpj'] ?? ''); ?>"></div>
                <div class="form-group"><label>RG/IE</label><input type="text" name="rg_ie" value="<?php echo sanitize($cliente['rg_ie'] ?? ''); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize($cliente['email'] ?? ''); ?>"></div>
                <div class="form-group"><label>Telefone</label><input type="text" name="telefone" value="<?php echo sanitize($cliente['telefone'] ?? ''); ?>"></div>
                <div class="form-group"><label>Celular</label><input type="text" name="celular" value="<?php echo sanitize($cliente['celular'] ?? ''); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>CEP</label><input type="text" name="cep" value="<?php echo sanitize($cliente['cep'] ?? ''); ?>"></div>
                <div class="form-group"><label>Endereco</label><input type="text" name="endereco" value="<?php echo sanitize($cliente['endereco'] ?? ''); ?>"></div>
                <div class="form-group"><label>Numero</label><input type="text" name="numero" value="<?php echo sanitize($cliente['numero'] ?? ''); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Complemento</label><input type="text" name="complemento" value="<?php echo sanitize($cliente['complemento'] ?? ''); ?>"></div>
                <div class="form-group"><label>Bairro</label><input type="text" name="bairro" value="<?php echo sanitize($cliente['bairro'] ?? ''); ?>"></div>
                <div class="form-group"><label>Cidade</label><input type="text" name="cidade" value="<?php echo sanitize($cliente['cidade'] ?? ''); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Estado</label><select name="estado"><option value="">Selecione</option><?php foreach ($estados as $e): ?><option value="<?php echo $e; ?>" <?php echo selected($cliente['estado'] ?? '', $e); ?>><?php echo $e; ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-group"><label>Observacoes</label><textarea name="observacoes" rows="3"><?php echo sanitize($cliente['observacoes'] ?? ''); ?></textarea></div>
            <div class="form-actions">
                <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="page-header">
    <h1><i class="fas fa-users"></i> Clientes</h1>
    <a href="clientes.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Cliente</a>
</div>
<div class="filters-bar">
    <div class="form-group"><input type="text" name="busca" value="<?php echo sanitize($busca); ?>" placeholder="Buscar..." form="filterForm"></div>
    <button type="submit" class="btn btn-primary" form="filterForm"><i class="fas fa-search"></i></button>
    <a href="clientes.php" class="btn btn-secondary">Limpar</a>
    <form id="filterForm" method="GET" style="display:none;"></form>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Nome</th><th>CPF/CNPJ</th><th>Contato</th><th>Cidade/UF</th><th width="100">Acoes</th></tr></thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><strong><?php echo sanitize($c['nome_razaosocial']); ?></strong></td>
                    <td><?php echo format_cpf_cnpj($c['cpf_cnpj']); ?></td>
                    <td><?php echo format_phone($c['telefone'] ?: $c['celular']); ?><br><small style="color:var(--gray-400)"><?php echo sanitize($c['email'] ?? ''); ?></small></td>
                    <td><?php echo sanitize($c['cidade'] ?? ''); ?>/<?php echo $c['estado'] ?? ''; ?></td>
                    <td class="actions"><a href="clientes.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-secondary btn-icon"><i class="fas fa-edit"></i></a><a href="?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger btn-icon btn-delete"><i class="fas fa-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination-wrap"><?php echo pagination_links($pagination, 'clientes.php', array_filter(['busca' => $busca])); ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
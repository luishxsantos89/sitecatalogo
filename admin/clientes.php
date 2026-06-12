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
            'categoria' => $_POST['categoria'] ?? 'cliente_final',
            'foto' => '',
            'status' => $_POST['status'] ?? 'ativo',
        ];

        // Processar upload de foto
        if (!empty($_FILES['foto']['name'])) {
            $upload = handle_upload([
                'name' => $_FILES['foto']['name'],
                'tmp_name' => $_FILES['foto']['tmp_name'],
                'error' => $_FILES['foto']['error']
            ], 'clientes');
            if ($upload) {
                // Remover foto anterior se editando
                if ($id) {
                    $old_foto = db()->prepare("SELECT foto FROM " . table('clientes') . " WHERE id = ?");
                    $old_foto->execute([$id]);
                    $old = $old_foto->fetchColumn();
                    if ($old) delete_upload($old);
                }
                $dados['foto'] = $upload;
            }
        }

        if (empty($dados['nome_razaosocial'])) { set_flash('error', 'Nome obrigatorio'); }
        else {
            try {
                if ($id) {
                    // Se nao enviou nova foto, manter a antiga
                    if (empty($dados['foto'])) {
                        unset($dados['foto']);
                    }
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
        // Remover foto antes de deletar
        $foto_stmt = db()->prepare("SELECT foto FROM " . table('clientes') . " WHERE id = ?");
        $foto_stmt->execute([$id]);
        $foto = $foto_stmt->fetchColumn();
        if ($foto) delete_upload($foto);

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
$categorias_cliente = [
    'cliente_final' => 'Cliente Final',
    'empresa' => 'Empresa',
    'fornecedor' => 'Fornecedor'
];
$status_opcoes = [
    'ativo' => 'Ativo',
    'bloqueado' => 'Bloqueado'
];

require_once __DIR__ . '/includes/header.php';

if ($action === 'edit' || $action === 'new'):
?>
<div class="page-header">
    <h1><i class="fas fa-users"></i> <?php echo $id ? 'Editar' : 'Novo'; ?> Cliente</h1>
    <a href="clientes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="formCliente">
            <input type="hidden" name="acao" value="salvar">

            <!-- Linha 1: CEP (primeiro campo) + Dados principais -->
            <div class="form-row form-row-3cols">
                <div class="form-group">
                    <label>CEP * <small style="color: #3b82f6; font-weight: 500;"><i class="fas fa-magic"></i> Preencha o CEP para auto-completar o endereco</small></label>
                    <input type="text" name="cep" id="cep" value="<?php echo format_cep($cliente['cep'] ?? ''); ?>" placeholder="00000-000" maxlength="9" onblur="buscarCEP(this.value)">
                </div>
                <div class="form-group">
                    <label>Nome / Razao Social *</label>
                    <input type="text" name="nome_razaosocial" value="<?php echo sanitize($cliente['nome_razaosocial'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Tipo de Pessoa</label>
                    <select name="tipo_pessoa">
                        <option value="fisica" <?php echo selected($cliente['tipo_pessoa'] ?? '', 'fisica'); ?>>Pessoa Fisica</option>
                        <option value="juridica" <?php echo selected($cliente['tipo_pessoa'] ?? '', 'juridica'); ?>>Pessoa Juridica</option>
                    </select>
                </div>
            </div>

            <!-- Linha 2: Documentos -->
            <div class="form-row form-row-3cols">
                <div class="form-group">
                    <label>CPF/CNPJ</label>
                    <input type="text" name="cpf_cnpj" id="cpf_cnpj" value="<?php echo sanitize($cliente['cpf_cnpj'] ?? ''); ?>" placeholder="CPF ou CNPJ">
                </div>
                <div class="form-group">
                    <label>RG/IE</label>
                    <input type="text" name="rg_ie" value="<?php echo sanitize($cliente['rg_ie'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo sanitize($cliente['email'] ?? ''); ?>">
                </div>
            </div>

            <!-- Linha 3: Telefones com mascara -->
            <div class="form-row form-row-3cols">
                <div class="form-group">
                    <label>Telefone Fixo</label>
                    <input type="tel" name="telefone" id="telefone" value="<?php echo format_phone($cliente['telefone'] ?? ''); ?>" placeholder="(00) 0000-0000" maxlength="15">
                </div>
                <div class="form-group">
                    <label>Celular / WhatsApp</label>
                    <input type="tel" name="celular" id="celular" value="<?php echo format_phone($cliente['celular'] ?? ''); ?>" placeholder="(00) 00000-0000" maxlength="15">
                </div>
                <div class="form-group">
                    <label>Categoria</label>
                    <select name="categoria">
                        <?php foreach ($categorias_cliente as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo selected($cliente['categoria'] ?? 'cliente_final', $val); ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Linha 4: Endereco completo -->
            <div class="form-row form-row-3cols">
                <div class="form-group">
                    <label>Endereco</label>
                    <input type="text" name="endereco" id="endereco" value="<?php echo sanitize($cliente['endereco'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Numero</label>
                    <input type="text" name="numero" id="numero" value="<?php echo sanitize($cliente['numero'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Complemento</label>
                    <input type="text" name="complemento" id="complemento" value="<?php echo sanitize($cliente['complemento'] ?? ''); ?>">
                </div>
            </div>

            <!-- Linha 5: Bairro, Cidade, Estado -->
            <div class="form-row form-row-3cols">
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" name="bairro" id="bairro" value="<?php echo sanitize($cliente['bairro'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" name="cidade" id="cidade" value="<?php echo sanitize($cliente['cidade'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" id="estado">
                        <option value="">Selecione</option>
                        <?php foreach ($estados as $e): ?>
                        <option value="<?php echo $e; ?>" <?php echo selected($cliente['estado'] ?? '', $e); ?>><?php echo $e; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Linha 6: Foto + Status + Observacoes -->
            <div class="form-row form-row-3cols">
                <div class="form-group">
                    <label>Foto / Logo <small style="color: #64748b;">(opcional)</small></label>
                    <input type="file" name="foto" accept="image/*" id="fotoInput">
                    <?php if (!empty($cliente['foto'])): ?>
                    <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                        <img src="<?php echo uploads_url($cliente['foto']); ?>" alt="Foto atual" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <span style="font-size: 0.75rem; color: #64748b;">Foto atual</span>
                    </div>
                    <?php endif; ?>
                    <div id="fotoPreview" style="margin-top: 8px; display: none;">
                        <img src="" alt="Preview" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;">
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach ($status_opcoes as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo selected($cliente['status'] ?? 'ativo', $val); ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Observacoes</label>
                    <textarea name="observacoes" rows="3"><?php echo sanitize($cliente['observacoes'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
// ===== MASCARA DE TELEFONE COM DDD =====
function formatPhone(input) {
    let value = input.value.replace(/\D/g, '');

    if (value.length > 11) value = value.slice(0, 11);

    if (value.length > 10) {
        // Celular: (XX) XXXXX-XXXX
        input.value = '(' + value.slice(0, 2) + ') ' + value.slice(2, 7) + '-' + value.slice(7, 11);
    } else if (value.length > 6) {
        // Fixo: (XX) XXXX-XXXX
        input.value = '(' + value.slice(0, 2) + ') ' + value.slice(2, 6) + '-' + value.slice(6, 10);
    } else if (value.length > 2) {
        input.value = '(' + value.slice(0, 2) + ') ' + value.slice(2);
    } else if (value.length > 0) {
        input.value = '(' + value;
    }
}

document.getElementById('telefone').addEventListener('input', function() { formatPhone(this); });
document.getElementById('celular').addEventListener('input', function() { formatPhone(this); });

// ===== MASCARA DE CEP =====
document.getElementById('cep').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 8) value = value.slice(0, 8);
    if (value.length > 5) {
        e.target.value = value.slice(0, 5) + '-' + value.slice(5, 8);
    } else {
        e.target.value = value;
    }
});

// ===== AUTO-COMPLETE CEP VIA VIACEP =====
function buscarCEP(cep) {
    cep = cep.replace(/\D/g, '');
    if (cep.length !== 8) return;

    const btn = document.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando CEP...';

    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(r => r.json())
        .then(data => {
            if (data.erro) {
                alert('CEP nao encontrado. Verifique e tente novamente.');
                return;
            }
            document.getElementById('endereco').value = data.logradouro || '';
            document.getElementById('bairro').value = data.bairro || '';
            document.getElementById('cidade').value = data.localidade || '';
            document.getElementById('estado').value = data.uf || '';
            document.getElementById('complemento').value = data.complemento || '';
            // Foca no numero
            document.getElementById('numero').focus();
        })
        .catch(err => {
            console.error('Erro ao buscar CEP:', err);
            alert('Erro ao buscar CEP. Tente novamente.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
}

// ===== PREVIEW DA FOTO =====
document.getElementById('fotoInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('fotoPreview');
            preview.style.display = 'block';
            preview.querySelector('img').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// ===== MASCARA CPF/CNPJ =====
document.getElementById('cpf_cnpj').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 14) value = value.slice(0, 14);

    if (value.length > 11) {
        // CNPJ: XX.XXX.XXX/XXXX-XX
        e.target.value = value.slice(0, 2) + '.' + value.slice(2, 5) + '.' + value.slice(5, 8) + '/' + value.slice(8, 12) + '-' + value.slice(12, 14);
    } else if (value.length > 9) {
        // CPF: XXX.XXX.XXX-XX
        e.target.value = value.slice(0, 3) + '.' + value.slice(3, 6) + '.' + value.slice(6, 9) + '-' + value.slice(9, 11);
    } else if (value.length > 6) {
        e.target.value = value.slice(0, 3) + '.' + value.slice(3, 6) + '.' + value.slice(6);
    } else if (value.length > 3) {
        e.target.value = value.slice(0, 3) + '.' + value.slice(3);
    } else {
        e.target.value = value;
    }
});
</script>

<style>
/* Grid responsivo para clientes - 3 colunas desktop, 2 tablet, 1 mobile */
.form-row-3cols {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

/* Tablet: 2 colunas */
@media (max-width: 1024px) {
    .form-row-3cols {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Mobile: 1 coluna */
@media (max-width: 640px) {
    .form-row-3cols {
        grid-template-columns: 1fr;
    }
}

/* Destaque para info do CEP */
.form-group label small {
    display: block;
    margin-top: 2px;
    font-size: 0.7rem;
}

/* Badge de status na listagem */
.badge-status-cliente {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
}
.badge-status-cliente.ativo { background: #dcfce7; color: #166534; }
.badge-status-cliente.bloqueado { background: #fee2e2; color: #991b1b; }
</style>

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
            <thead><tr><th width="50"></th><th>Nome</th><th>CPF/CNPJ</th><th>Contato</th><th>Categoria</th><th>Cidade/UF</th><th>Status</th><th width="100">Acoes</th></tr></thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td>
                        <?php if (!empty($c['foto'])): ?>
                        <img src="<?php echo uploads_url($c['foto']); ?>" alt="" style="width: 36px; height: 36px; object-fit: cover; border-radius: 50%; border: 1px solid #e2e8f0;">
                        <?php else: ?>
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 0.75rem; font-weight: 600;">
                            <?php echo strtoupper(substr($c['nome_razaosocial'], 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo sanitize($c['nome_razaosocial']); ?></strong></td>
                    <td><?php echo format_cpf_cnpj($c['cpf_cnpj']); ?></td>
                    <td><?php echo format_phone($c['telefone'] ?: $c['celular']); ?><br><small style="color:var(--gray-400)"><?php echo sanitize($c['email'] ?? ''); ?></small></td>
                    <td><span class="badge" style="background: <?php echo $c['categoria'] === 'cliente_final' ? '#dbeafe' : ($c['categoria'] === 'empresa' ? '#f3e8ff' : '#fef3c7'); ?>; color: <?php echo $c['categoria'] === 'cliente_final' ? '#1e40af' : ($c['categoria'] === 'empresa' ? '#7c3aed' : '#92400e'); ?>;"><?php echo $categorias_cliente[$c['categoria']] ?? 'Cliente Final'; ?></span></td>
                    <td><?php echo sanitize($c['cidade'] ?? ''); ?>/<?php echo $c['estado'] ?? ''; ?></td>
                    <td><span class="badge-status-cliente <?php echo $c['status'] ?? 'ativo'; ?>"><i class="fas fa-circle" style="font-size: 6px;"></i> <?php echo $status_opcoes[$c['status'] ?? 'ativo']; ?></span></td>
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
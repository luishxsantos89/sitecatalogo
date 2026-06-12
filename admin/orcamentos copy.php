<?php
/**
 * SiteCatalogo - Orçamentos (Admin)
 * Com suporte a: listar, visualizar, criar manualmente, imprimir, PDF, WhatsApp
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Orçamentos';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// AJAX: Buscar produtos para orçamento manual
if ($action === 'ajax_buscar_produtos' && isset($_GET['termo'])) {
    header('Content-Type: application/json');
    $termo = '%' . $_GET['termo'] . '%';
    try {
        $stmt = db()->prepare("SELECT id, nome, sku, preco, preco_promocional, unidade, peso, quantidade_estoque, imagem_principal 
            FROM " . table('produtos') . " 
            WHERE (nome LIKE ? OR sku LIKE ?) 
            ORDER BY nome LIMIT 20");
        $stmt->execute([$termo, $termo]);
        echo json_encode($stmt->fetchAll());
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// AJAX: Buscar cliente por telefone/nome
if ($action === 'ajax_buscar_cliente' && isset($_GET['termo'])) {
    header('Content-Type: application/json');
    $termo = '%' . $_GET['termo'] . '%';
    try {
        $stmt = db()->prepare("SELECT DISTINCT cliente_nome, cliente_email, cliente_telefone, cliente_cpf_cnpj 
            FROM " . table('orcamentos') . " 
            WHERE cliente_nome LIKE ? OR cliente_telefone LIKE ? 
            ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$termo, $termo]);
        echo json_encode($stmt->fetchAll());
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// Salvar orçamento manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'new' || $action === 'save_manual')) {
    $cliente_nome = trim($_POST['cliente_nome'] ?? '');
    $cliente_email = trim($_POST['cliente_email'] ?? '');
    $cliente_telefone = trim($_POST['cliente_telefone'] ?? '');
    $cliente_cpf_cnpj = trim($_POST['cliente_cpf_cnpj'] ?? '');
    $tipo_contato = $_POST['tipo_contato'] ?? 'whatsapp';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $data_entrega = !empty($_POST['data_entrega']) ? $_POST['data_entrega'] : null;
    $tabela_preco = $_POST['tabela_preco'] ?? 'padrao';
    $desconto = str_replace(',', '.', $_POST['desconto'] ?? 0);
    $produtos_json = $_POST['produtos_json'] ?? '[]';

    if (empty($cliente_nome)) {
        set_flash('error', 'Nome do cliente é obrigatório');
        header('Location: orcamentos.php?action=new');
        exit;
    }

    $produtos = json_decode($produtos_json, true);
    if (empty($produtos)) {
        set_flash('error', 'Adicione pelo menos um produto ao orçamento');
        header('Location: orcamentos.php?action=new');
        exit;
    }

    try {
        $codigo = 'ORC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $total = 0;
        $valor_produtos = 0;
        $valor_servicos = 0;

        foreach ($produtos as $prod) {
            $subtotal = (float)$prod['preco'] * (int)$prod['qtd'];
            $total += $subtotal;
            $valor_produtos += $subtotal;
        }

        $total -= (float)$desconto;
        $total = max(0, $total);

        // Verifica quais colunas existem na tabela (compatibilidade com bancos antigos)
        $colunas = db()->query("SHOW COLUMNS FROM " . table('orcamentos'))->fetchAll(PDO::FETCH_COLUMN);
        $tem_data_entrega = in_array('data_entrega', $colunas);
        $tem_tabela_preco = in_array('tabela_preco', $colunas);
        $tem_valor_produtos = in_array('valor_produtos', $colunas);
        $tem_valor_servicos = in_array('valor_servicos', $colunas);
        $tem_desconto = in_array('desconto', $colunas);

        // Monta INSERT dinamico conforme colunas existentes
        $campos = ['codigo', 'cliente_nome', 'cliente_email', 'cliente_telefone', 'cliente_cpf_cnpj', 'tipo_contato', 'observacoes', 'status', 'usuario_id', 'created_at'];
        $valores = [$codigo, $cliente_nome, $cliente_email, $cliente_telefone, $cliente_cpf_cnpj, $tipo_contato, $observacoes, 'novo', $_SESSION['admin_id'] ?? null, date('Y-m-d H:i:s')];
        $placeholders = array_fill(0, count($campos), '?');

        if ($tem_data_entrega) { $campos[] = 'data_entrega'; $valores[] = $data_entrega; $placeholders[] = '?'; }
        if ($tem_tabela_preco) { $campos[] = 'tabela_preco'; $valores[] = $tabela_preco; $placeholders[] = '?'; }
        if ($tem_valor_produtos) { $campos[] = 'valor_produtos'; $valores[] = $valor_produtos; $placeholders[] = '?'; }
        if ($tem_valor_servicos) { $campos[] = 'valor_servicos'; $valores[] = $valor_servicos; $placeholders[] = '?'; }
        if ($tem_desconto) { $campos[] = 'desconto'; $valores[] = $desconto; $placeholders[] = '?'; }

        // valor_total sempre existe (ja existia no banco original)
        $campos[] = 'valor_total';
        $valores[] = $total;
        $placeholders[] = '?';

        $sql = "INSERT INTO " . table('orcamentos') . " (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $placeholders) . ")";
        db()->prepare($sql)->execute($valores);

        $orc_id = db()->lastInsertId();

        foreach ($produtos as $prod) {
            $preco_unit = (float)$prod['preco'];
            $qtd = (int)$prod['qtd'];
            $subtotal = $preco_unit * $qtd;

            // Verifica colunas da tabela itens (compatibilidade)
            static $colunas_itens = null;
            if ($colunas_itens === null) {
                $colunas_itens = db()->query("SHOW COLUMNS FROM " . table('orcamento_itens'))->fetchAll(PDO::FETCH_COLUMN);
            }
            $tem_sku = in_array('sku', $colunas_itens);
            $tem_unidade = in_array('unidade', $colunas_itens);
            $tem_peso = in_array('peso', $colunas_itens);

            $campos_itens = ['orcamento_id', 'produto_id', 'produto_nome', 'quantidade', 'preco_unitario', 'subtotal'];
            $valores_itens = [$orc_id, $prod['id'] ?? null, $prod['nome'], $qtd, $preco_unit, $subtotal];
            $placeholders_itens = array_fill(0, count($campos_itens), '?');

            if ($tem_sku) { $campos_itens[] = 'sku'; $valores_itens[] = $prod['sku'] ?? ''; $placeholders_itens[] = '?'; }
            if ($tem_unidade) { $campos_itens[] = 'unidade'; $valores_itens[] = $prod['unidade'] ?? 'un'; $placeholders_itens[] = '?'; }
            if ($tem_peso) { $campos_itens[] = 'peso'; $valores_itens[] = $prod['peso'] ?? 0; $placeholders_itens[] = '?'; }

            $sql_itens = "INSERT INTO " . table('orcamento_itens') . " (" . implode(', ', $campos_itens) . ") VALUES (" . implode(', ', $placeholders_itens) . ")";
            db()->prepare($sql_itens)->execute($valores_itens);
        }

        log_activity('create', 'orcamentos', "Orçamento manual #{$orc_id} criado");
        set_flash('success', 'Orçamento criado com sucesso! Código: ' . $codigo);
        header('Location: orcamentos.php?action=view&id=' . $orc_id);
        exit;
    } catch (Exception $e) {
        set_flash('error', 'Erro ao criar orçamento: ' . $e->getMessage());
        header('Location: orcamentos.php?action=new');
        exit;
    }
}

// Atualizar status
if ($action === 'status' && $id && isset($_GET['status'])) {
    try {
        $new_status = $_GET['status'];
        $valid = ['novo','pendente','em_analise','respondido','aprovado','rejeitado','cancelado'];
        if (in_array($new_status, $valid)) {
            db()->prepare("UPDATE " . table('orcamentos') . " SET status = ? WHERE id = ?")->execute([$new_status, $id]);

            if ($new_status === 'aprovado') {
                $itens = db()->prepare("SELECT * FROM " . table('orcamento_itens') . " WHERE orcamento_id = ?");
                $itens->execute([$id]);
                foreach ($itens->fetchAll() as $item) {
                    if ($item['produto_id']) {
                        $prod = db()->prepare("SELECT quantidade_estoque, nome FROM " . table('produtos') . " WHERE id = ?");
                        $prod->execute([$item['produto_id']]);
                        $p = $prod->fetch();
                        if ($p) {
                            $nova_qtd = max(0, (int)$p['quantidade_estoque'] - (int)$item['quantidade']);
                            db()->prepare("UPDATE " . table('produtos') . " SET quantidade_estoque = ? WHERE id = ?")
                                ->execute([$nova_qtd, $item['produto_id']]);
                            db()->prepare("INSERT INTO " . table('produto_estoque') . " (produto_id, tipo, quantidade, quantidade_anterior, motivo, usuario_id) VALUES (?, ?, ?, ?, ?, ?)")
                                ->execute([$item['produto_id'], 'saida', $item['quantidade'], $p['quantidade_estoque'], 'Aprovacao orcamento #' . $id, $_SESSION['admin_id'] ?? null]);
                        }
                    }
                }
            }

            log_activity('update', 'orcamentos', "Orçamento #{$id} status -> {$new_status}");
            set_flash('success', 'Status atualizado!');
        }
    } catch (Exception $e) {}
    header('Location: orcamentos.php' . ($id ? '?action=view&id=' . $id : '')); exit;
}

// Deletar
if ($action === 'delete' && $id) {
    try {
        db()->prepare("DELETE FROM " . table('orcamento_itens') . " WHERE orcamento_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM " . table('orcamentos') . " WHERE id = ?")->execute([$id]);
        log_activity('delete', 'orcamentos', "Orçamento #{$id} excluído");
        set_flash('success', 'Orçamento excluído!');
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: orcamentos.php'); exit;
}

// Ver detalhes
$orcamento = null; $itens = [];
if ($action === 'view' && $id) {
    $stmt = db()->prepare("SELECT o.*, u.nome_completo as atendente FROM " . table('orcamentos') . " o LEFT JOIN " . table('usuarios') . " u ON o.usuario_id = u.id WHERE o.id = ?");
    $stmt->execute([$id]); $orcamento = $stmt->fetch();
    // Valores padrão para campos que podem não existir em orçamentos antigos
    if ($orcamento) {
        $orcamento['data_entrega'] = $orcamento['data_entrega'] ?? null;
        $orcamento['tabela_preco'] = $orcamento['tabela_preco'] ?? null;
        $orcamento['valor_produtos'] = $orcamento['valor_produtos'] ?? 0;
        $orcamento['valor_servicos'] = $orcamento['valor_servicos'] ?? 0;
        $orcamento['desconto'] = $orcamento['desconto'] ?? 0;
    }
    if ($orcamento) {
        $stmt = db()->prepare("SELECT i.*, p.imagem_principal, p.slug, p.quantidade_estoque as estoque_atual, p.estoque_minimo FROM " . table('orcamento_itens') . " i LEFT JOIN " . table('produtos') . " p ON i.produto_id = p.id WHERE i.orcamento_id = ?");
        $stmt->execute([$id]); $itens = $stmt->fetchAll();
        if ($orcamento['status'] === 'novo') {
            db()->prepare("UPDATE " . table('orcamentos') . " SET status = 'pendente' WHERE id = ?")->execute([$id]);
        }
    }
}

// Listar
$status_filtro = $_GET['status'] ?? '';
$busca = $_GET['busca'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$where = ["1=1"]; $params = [];
if ($status_filtro) { $where[] = "o.status = ?"; $params[] = $status_filtro; }
if ($busca) { $where[] = "(o.cliente_nome LIKE ? OR o.codigo LIKE ?)"; $like = "%{$busca}%"; $params = array_merge($params, [$like, $like]); }
$where_sql = implode(' AND ', $where);

$stmt = db()->prepare("SELECT COUNT(*) FROM " . table('orcamentos') . " o WHERE {$where_sql}");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pagination = paginate($total, $page, 15);
$offset = max(0, $pagination['offset']);

$stmt = db()->prepare("SELECT o.*, (SELECT COUNT(*) FROM " . table('orcamento_itens') . " WHERE orcamento_id = o.id) as total_itens FROM " . table('orcamentos') . " o WHERE {$where_sql} ORDER BY o.created_at DESC LIMIT {$offset}, {$pagination['per_page']}");
$stmt->execute($params);
$orcamentos = $stmt->fetchAll();

$status_list = ['novo','pendente','em_analise','respondido','aprovado','rejeitado','cancelado'];

// Contadores para dashboard
$status_counts = [];
foreach ($status_list as $s) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM " . table('orcamentos') . " WHERE status = ?");
    $stmt->execute([$s]);
    $status_counts[$s] = (int) $stmt->fetchColumn();
}

// Dados da empresa para cabeçalho
$empresa_nome = get_config('site_name', 'SiteCatalogo');
$empresa_email = get_config('site_email', '');
$empresa_whatsapp = get_config('whatsapp', '');
$empresa_endereco = get_config('endereco', '');
$empresa_telefone = get_config('telefone', '');
$empresa_responsavel = get_config('responsavel_nome', '');

require_once __DIR__ . '/includes/header.php';

// ============================================
// NOVO ORÇAMENTO MANUAL
// ============================================
if ($action === 'new'):
?>
<style>
.orcamento-form { max-width: 1200px; }
.form-section { background: white; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.form-section h3 { margin: 0 0 20px 0; font-size: 1.125rem; color: #0f172a; display: flex; align-items: center; gap: 10px; }
.form-section h3 i { color: #3b82f6; }
.form-row-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.form-row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.form-row-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
@media (max-width: 768px) { .form-row-4, .form-row-3, .form-row-2 { grid-template-columns: 1fr; } }

.busca-produto-wrap { position: relative; }
.busca-produto-resultados { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 100; max-height: 300px; overflow-y: auto; display: none; }
.busca-produto-resultados.active { display: block; }
.busca-produto-item { display: flex; align-items: center; gap: 12px; padding: 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
.busca-produto-item:hover { background: #f8fafc; }
.busca-produto-item img { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; }
.busca-produto-item-info { flex: 1; }
.busca-produto-item-info strong { display: block; font-size: 0.875rem; color: #0f172a; }
.busca-produto-item-info small { font-size: 0.75rem; color: #64748b; }
.busca-produto-item-preco { font-weight: 700; color: #3b82f6; font-size: 0.875rem; }

.tabela-orcamento { width: 100%; border-collapse: collapse; margin-top: 16px; table-layout: fixed; }
.tabela-orcamento th { background: #1e3a5f; color: white; padding: 12px; text-align: left; font-size: 0.8125rem; font-weight: 600; text-transform: uppercase; }
.tabela-orcamento td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
.tabela-orcamento td input { width: 100%; padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.875rem; box-sizing: border-box; }
.tabela-orcamento td input:focus { outline: none; border-color: #3b82f6; }
.tabela-orcamento .btn-remover-item { background: #ef4444; color: white; border: none; border-radius: 6px; padding: 6px 10px; cursor: pointer; font-size: 0.75rem; }
.tabela-orcamento .btn-remover-item:hover { background: #dc2626; }
.tabela-orcamento .total-row { background: #f8fafc; font-weight: 700; }
.tabela-orcamento .total-row td { font-size: 1rem; }

.totais-orcamento { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 20px; }
.totais-orcamento .form-group label { font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; }
.totais-orcamento .form-group input { font-size: 1.125rem; font-weight: 700; color: #0f172a; }
.totais-orcamento .total-final input { color: #3b82f6; font-size: 1.5rem; }

.btn-adicionar-produto { background: #22c55e; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
.btn-adicionar-produto:hover { background: #16a34a; }

.observacoes-orcamento { width: 100%; min-height: 100px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 0.875rem; resize: vertical; }

.btn-salvar-orcamento { background: #22c55e; color: white; border: none; padding: 14px 32px; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 1rem; display: inline-flex; align-items: center; gap: 10px; }
.btn-salvar-orcamento:hover { background: #16a34a; }
.btn-cancelar-orcamento { background: #64748b; color: white; border: none; padding: 14px 32px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
.btn-cancelar-orcamento:hover { background: #475569; }

.busca-cliente-wrap { position: relative; }
.busca-cliente-resultados { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 100; max-height: 200px; overflow-y: auto; display: none; }
.busca-cliente-resultados.active { display: block; }
.busca-cliente-item { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
.busca-cliente-item:hover { background: #f8fafc; }
.busca-cliente-item strong { color: #0f172a; }
.busca-cliente-item small { color: #64748b; }
</style>

<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Novo Orçamento</h1>
    <a href="orcamentos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<form method="POST" action="orcamentos.php?action=save_manual" class="orcamento-form" id="formOrcamento" onsubmit="return prepararEnvio()">
    <input type="hidden" name="produtos_json" id="produtosJson" value="[]">

    <!-- DADOS DO ORÇAMENTO -->
    <div class="form-section">
        <h3><i class="fas fa-hashtag"></i> Dados do Orçamento</h3>
        <div class="form-row-4">
            <div class="form-group">
                <label>Nº</label>
                <input type="text" value="AUTO" disabled style="background: #f1f5f9;">
            </div>
            <div class="form-group">
                <label>Data</label>
                <input type="text" value="<?php echo date('d/m/Y'); ?>" disabled style="background: #f1f5f9;">
            </div>
            <div class="form-group">
                <label>Tabela</label>
                <select name="tabela_preco">
                    <option value="a_vista">À Vista</option>
                    <option value="parcelado">Parcelado</option>
                    <option value="atacado">Atacado</option>
                    <option value="promocional">Promocional</option>
                </select>
            </div>
            <div class="form-group">
                <label>Data Entrega</label>
                <input type="date" name="data_entrega">
            </div>
        </div>
    </div>

    <!-- DADOS DO CLIENTE -->
    <div class="form-section">
        <h3><i class="fas fa-user"></i> Dados do Cliente</h3>
        <div class="form-row-2">
            <div class="form-group busca-cliente-wrap">
                <label>Cliente *</label>
                <input type="text" id="buscaCliente" placeholder="Digite nome ou telefone..." autocomplete="off">
                <div class="busca-cliente-resultados" id="resultadosCliente"></div>
            </div>
            <div class="form-group">
                <label>Nome Completo *</label>
                <input type="text" name="cliente_nome" id="clienteNome" required>
            </div>
        </div>
        <div class="form-row-4">
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" name="cliente_email" id="clienteEmail">
            </div>
            <div class="form-group">
                <label>Telefone / WhatsApp</label>
                <input type="text" name="cliente_telefone" id="clienteTelefone">
            </div>
            <div class="form-group">
                <label>CPF/CNPJ</label>
                <input type="text" name="cliente_cpf_cnpj" id="clienteCpfCnpj">
            </div>
            <div class="form-group">
                <label>Contato Preferido</label>
                <select name="tipo_contato">
                    <option value="whatsapp">WhatsApp</option>
                    <option value="email">E-mail</option>
                    <option value="telefone">Telefone</option>
                </select>
            </div>
        </div>
    </div>

    <!-- PRODUTOS -->
    <div class="form-section">
        <h3><i class="fas fa-boxes"></i> Produtos</h3>
        <div class="busca-produto-wrap">
            <input type="text" id="buscaProduto" placeholder="Buscar produto por nome ou SKU..." autocomplete="off" style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9375rem;">
            <div class="busca-produto-resultados" id="resultadosProduto"></div>
        </div>
        <button type="button" class="btn-adicionar-produto" onclick="adicionarProdutoManual()" style="margin-top: 12px;">
            <i class="fas fa-plus"></i> Adicionar Produto Manual
        </button>

        <table class="tabela-orcamento" id="tabelaProdutos">
            <thead>
                <tr>
                    <th width="40">Nº</th>
                    <th>Descrição</th>
                    <th width="80">Un</th>
                    <th width="100">Qtd</th>
                    <th width="120">Valor Unit.</th>
                    <th width="120">Valor Total</th>
                    <th width="60">Ação</th>
                </tr>
            </thead>
            <tbody id="listaProdutos">
                <!-- Produtos adicionados via JS -->
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="5" style="text-align: right;">TOTAL:</td>
                    <td id="totalOrcamento" style="color: #3b82f6; font-size: 1.25rem;">R$ 0,00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- TOTAIS -->
    <div class="form-section">
        <h3><i class="fas fa-calculator"></i> Totais</h3>
        <div class="totais-orcamento">
            <div class="form-group">
                <label>Valor Produtos</label>
                <input type="text" id="valorProdutos" value="0,00" readonly style="background: #f1f5f9;">
            </div>
            <div class="form-group">
                <label>Valor Serviços</label>
                <input type="text" name="valor_servicos" value="0,00" onchange="calcularTotal()">
            </div>
            <div class="form-group">
                <label>Desconto</label>
                <input type="text" name="desconto" id="desconto" value="0,00" onchange="calcularTotal()">
            </div>
            <div class="form-group total-final">
                <label>TOTAL</label>
                <input type="text" id="totalFinal" value="0,00" readonly style="background: #dbeafe; color: #1e40af; border: 2px solid #3b82f6;">
            </div>
        </div>
    </div>

    <!-- OBSERVAÇÕES -->
    <div class="form-section">
        <h3><i class="fas fa-comment-alt"></i> Observações</h3>
        <textarea name="observacoes" class="observacoes-orcamento" placeholder="Observações gerais do orçamento..."></textarea>
    </div>

    <!-- BOTÕES -->
    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
        <a href="orcamentos.php" class="btn-cancelar-orcamento"><i class="fas fa-times"></i> Cancelar</a>
        <button type="submit" class="btn-salvar-orcamento"><i class="fas fa-check"></i> Salvar Orçamento</button>
    </div>
</form>

<script>
let produtosOrcamento = [];
let contadorItem = 0;

// Buscar produtos
const buscaProduto = document.getElementById('buscaProduto');
const resultadosProduto = document.getElementById('resultadosProduto');
let timeoutBusca;

buscaProduto.addEventListener('input', function() {
    clearTimeout(timeoutBusca);
    const termo = this.value.trim();
    if (termo.length < 2) { resultadosProduto.classList.remove('active'); return; }

    timeoutBusca = setTimeout(() => {
        fetch('orcamentos.php?action=ajax_buscar_produtos&termo=' + encodeURIComponent(termo))
        .then(r => r.json())
        .then(produtos => {
            resultadosProduto.innerHTML = '';
            if (produtos.length === 0) {
                resultadosProduto.innerHTML = '<div style="padding: 12px; color: #64748b; font-size: 0.875rem;">Nenhum produto encontrado</div>';
            } else {
                produtos.forEach(p => {
                    const preco = p.preco_promocional && p.preco_promocional > 0 ? p.preco_promocional : p.preco;
                    const div = document.createElement('div');
                    div.className = 'busca-produto-item';
                    div.innerHTML = `
                        <img src="${p.imagem_principal ? '/uploads/produtos/' + p.imagem_principal : '/assets/images/no-image.jpg'}" alt="" onerror="this.src='/assets/images/no-image.jpg'">
                        <div class="busca-produto-item-info">
                            <strong>${escapeHtml(p.nome)}</strong>
                            <small>SKU: ${escapeHtml(p.sku || 'N/A')} | Estoque: ${p.quantidade_estoque}</small>
                        </div>
                        <div class="busca-produto-item-preco">R$ ${parseFloat(preco).toFixed(2).replace('.', ',')}</div>
                    `;
                    div.onclick = () => adicionarProduto(p);
                    resultadosProduto.appendChild(div);
                });
            }
            resultadosProduto.classList.add('active');
        });
    }, 300);
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.busca-produto-wrap')) resultadosProduto.classList.remove('active');
});

// Buscar clientes
const buscaCliente = document.getElementById('buscaCliente');
const resultadosCliente = document.getElementById('resultadosCliente');

buscaCliente.addEventListener('input', function() {
    clearTimeout(timeoutBusca);
    const termo = this.value.trim();
    if (termo.length < 2) { resultadosCliente.classList.remove('active'); return; }

    timeoutBusca = setTimeout(() => {
        fetch('orcamentos.php?action=ajax_buscar_cliente&termo=' + encodeURIComponent(termo))
        .then(r => r.json())
        .then(clientes => {
            resultadosCliente.innerHTML = '';
            if (clientes.length === 0) {
                resultadosCliente.innerHTML = '<div style="padding: 12px; color: #64748b; font-size: 0.875rem;">Nenhum cliente encontrado</div>';
            } else {
                clientes.forEach(c => {
                    const div = document.createElement('div');
                    div.className = 'busca-cliente-item';
                    div.innerHTML = `<strong>${escapeHtml(c.cliente_nome)}</strong><br><small>${escapeHtml(c.cliente_telefone || '')} ${c.cliente_email ? '| ' + escapeHtml(c.cliente_email) : ''}</small>`;
                    div.onclick = () => {
                        document.getElementById('clienteNome').value = c.cliente_nome;
                        document.getElementById('clienteEmail').value = c.cliente_email || '';
                        document.getElementById('clienteTelefone').value = c.cliente_telefone || '';
                        document.getElementById('clienteCpfCnpj').value = c.cliente_cpf_cnpj || '';
                        buscaCliente.value = c.cliente_nome;
                        resultadosCliente.classList.remove('active');
                    };
                    resultadosCliente.appendChild(div);
                });
            }
            resultadosCliente.classList.add('active');
        });
    }, 300);
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.busca-cliente-wrap')) resultadosCliente.classList.remove('active');
});

function adicionarProduto(produto) {
    contadorItem++;
    const preco = produto.preco_promocional && produto.preco_promocional > 0 ? produto.preco_promocional : produto.preco;
    const item = {
        id: produto.id,
        nome: produto.nome,
        sku: produto.sku || '',
        unidade: produto.unidade || 'un',
        peso: produto.peso || 0,
        preco: parseFloat(preco) || 0,
        qtd: 1
    };
    produtosOrcamento.push(item);
    renderizarTabela();
    buscaProduto.value = '';
    resultadosProduto.classList.remove('active');
}

function adicionarProdutoManual() {
    contadorItem++;
    const item = {
        id: null,
        nome: 'Produto Manual',
        sku: '',
        unidade: 'un',
        peso: 0,
        preco: 0,
        qtd: 1
    };
    produtosOrcamento.push(item);
    renderizarTabela();
}

function renderizarTabela() {
    const tbody = document.getElementById('listaProdutos');
    tbody.innerHTML = '';

    let total = 0;

    produtosOrcamento.forEach((item, index) => {
        const subtotal = item.preco * item.qtd;
        total += subtotal;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${index + 1}</td>
            <td><input type="text" value="${escapeHtml(item.nome)}" onchange="atualizarItem(${index}, 'nome', this.value)" style="width: 100%;"></td>
            <td><input type="text" value="${escapeHtml(item.unidade)}" onchange="atualizarItem(${index}, 'unidade', this.value)" style="width: 100%;"></td>
            <td><input type="number" min="1" value="${item.qtd}" onchange="atualizarItem(${index}, 'qtd', this.value); calcularTotal()" style="width: 100%;"></td>
            <td><input type="number" step="0.01" value="${item.preco.toFixed(2)}" onchange="atualizarItem(${index}, 'preco', this.value); calcularTotal()" style="width: 100%;"></td>
            <td>R$ ${subtotal.toFixed(2).replace('.', ',')}</td>
            <td><button type="button" class="btn-remover-item" onclick="removerItem(${index})"><i class="fas fa-trash"></i></button></td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('valorProdutos').value = total.toFixed(2).replace('.', ',');
    calcularTotal();
}

function atualizarItem(index, campo, valor) {
    if (campo === 'qtd') valor = parseInt(valor) || 1;
    else if (campo === 'preco') valor = parseFloat(valor) || 0;
    produtosOrcamento[index][campo] = valor;
    renderizarTabela();
}

function removerItem(index) {
    produtosOrcamento.splice(index, 1);
    renderizarTabela();
}

function calcularTotal() {
    let valorProdutos = 0;
    produtosOrcamento.forEach(p => {
        valorProdutos += p.preco * p.qtd;
    });

    const desconto = parseFloat(document.getElementById('desconto').value.replace(',', '.')) || 0;
    const total = Math.max(0, valorProdutos - desconto);

    document.getElementById('totalOrcamento').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    document.getElementById('totalFinal').value = total.toFixed(2).replace('.', ',');
}

function prepararEnvio() {
    if (produtosOrcamento.length === 0) {
        alert('Adicione pelo menos um produto ao orçamento');
        return false;
    }
    document.getElementById('produtosJson').value = JSON.stringify(produtosOrcamento);
    return true;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php 
// ============================================
// VISUALIZAR ORÇAMENTO
// ============================================
elseif ($action === 'view' && $orcamento):
?>
<style>
@media print {
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }

    html, body { 
        margin: 0 !important; 
        padding: 0 !important; 
        background: white !important; 
        color: #1a1a1a !important;
        font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif !important;
    }

    .sidebar, .sidebar-menu, .page-header, .filters-bar, .btn-actions, .no-print, 
    .app-header, .navbar, .menu, nav, header, footer, .footer, 
    .card-header .no-print, .btn, button, .actions, .pagination-wrap,
    .status-timeline, .dashboard-sidebar, .admin-sidebar, .left-sidebar { 
        display: none !important; visibility: hidden !important; opacity: 0 !important; 
    }

    .main-content, .content-wrapper, .page-content, .container-fluid { 
        margin-left: 0 !important; 
        padding: 0 !important; 
        width: 100% !important; 
        max-width: 100% !important; 
    }

    .print-only { display: block !important; visibility: visible !important; }

    /* ===== CABEÇALHO PROFISSIONAL ===== */
    .orcamento-print-header {
        text-align: center;
        padding: 30px 40px 25px;
        border-bottom: 3px solid #1e3a5f;
        margin-bottom: 35px;
        background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    }

    .orcamento-print-logo {
        max-width: 100px;
        max-height: 100px;
        margin-bottom: 15px;
        object-fit: contain;
    }

    .orcamento-print-header h1 { 
        margin: 0 0 8px; 
        color: #1e3a5f; 
        font-size: 26pt; 
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .orcamento-print-header .empresa-dados {
        color: #475569;
        font-size: 10pt;
        line-height: 1.6;
        margin: 4px 0;
    }

    .orcamento-print-header .orcamento-titulo {
        display: inline-block;
        margin-top: 20px;
        padding: 8px 30px;
        background: #1e3a5f;
        color: white;
        font-size: 14pt;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        border-radius: 4px;
    }

    /* ===== GRID DE INFORMAÇÕES ===== */
    .info-grid { 
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 25px !important;
        margin-bottom: 30px !important;
        padding: 0 40px !important;
    }

    .info-grid .card { 
        box-shadow: none !important; 
        border: 1px solid #e2e8f0 !important; 
        border-radius: 8px !important;
        page-break-inside: avoid; 
        margin-bottom: 0 !important;
        overflow: hidden;
    }

    .info-grid .card-header {
        background: #f1f5f9 !important;
        padding: 12px 18px !important;
        border-bottom: 2px solid #e2e8f0 !important;
    }

    .info-grid .card-header h3 {
        font-size: 11pt !important;
        color: #1e3a5f !important;
        margin: 0 !important;
        font-weight: 700 !important;
    }

    .info-grid .card-body {
        padding: 18px !important;
        font-size: 10pt !important;
        line-height: 1.7 !important;
    }

    .info-grid .card-body p {
        margin: 6px 0 !important;
        display: flex !important;
        justify-content: space-between !important;
    }

    .info-grid .card-body strong {
        color: #334155 !important;
        font-weight: 600 !important;
    }

    .info-grid .card-body code {
        background: #f1f5f9 !important;
        padding: 2px 8px !important;
        border-radius: 4px !important;
        font-size: 9pt !important;
        color: #1e3a5f !important;
    }

    /* Badge de status */
    .badge-status {
        display: inline-block !important;
        padding: 4px 14px !important;
        border-radius: 20px !important;
        font-size: 9pt !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }

    .status-novo { background: #dbeafe !important; color: #1e40af !important; }
    .status-pendente { background: #fef3c7 !important; color: #92400e !important; }
    .status-aprovado { background: #dcfce7 !important; color: #166534 !important; }
    .status-rejeitado { background: #fee2e2 !important; color: #991b1b !important; }
    .status-cancelado { background: #f3f4f6 !important; color: #4b5563 !important; }
    .status-em_analise { background: #e0e7ff !important; color: #3730a3 !important; }
    .status-respondido { background: #d1fae5 !important; color: #065f46 !important; }

    /* ===== TABELA DE ITENS ===== */
    .card:has(.table-responsive) {
        margin: 0 40px 30px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        overflow: hidden !important;
        page-break-inside: avoid;
    }

    .card:has(.table-responsive) .card-header {
        background: #f1f5f9 !important;
        padding: 14px 20px !important;
        border-bottom: 2px solid #e2e8f0 !important;
    }

    .card:has(.table-responsive) .card-header h3 {
        font-size: 12pt !important;
        color: #1e3a5f !important;
        margin: 0 !important;
        font-weight: 700 !important;
    }

    .table-responsive {
        padding: 0 !important;
    }

    table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 10pt;
    }

    thead tr {
        background: #1e3a5f !important;
    }

    th { 
        background: #1e3a5f !important; 
        color: white !important; 
        padding: 12px 14px !important; 
        text-align: left !important; 
        font-weight: 600 !important;
        font-size: 9pt !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        border: none !important;
    }

    td { 
        padding: 10px 14px !important; 
        border-bottom: 1px solid #e2e8f0 !important;
        font-size: 10pt !important;
        color: #334155 !important;
    }

    tbody tr:nth-child(even) {
        background: #f8fafc !important;
    }

    tbody tr:hover {
        background: #f1f5f9 !important;
    }

    /* Totais no tfoot */
    tfoot tr {
        border-top: 2px solid #1e3a5f !important;
    }

    tfoot td {
        padding: 10px 14px !important;
        font-weight: 600 !important;
        font-size: 10pt !important;
        color: #1e3a5f !important;
    }

    tfoot tr:last-child td {
        font-size: 13pt !important;
        font-weight: 700 !important;
        color: #1e3a5f !important;
        padding-top: 12px !important;
        padding-bottom: 12px !important;
        border-top: 3px solid #1e3a5f !important;
    }

    /* ===== CARD DE OBSERVAÇÕES ===== */
    .card:has(.card-body > p) {
        margin: 0 40px 30px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        page-break-inside: avoid;
    }

    /* ===== RODAPÉ ===== */
    .print-footer {
        margin: 40px 40px 0 !important;
        padding-top: 20px !important;
        border-top: 2px solid #e2e8f0 !important;
        text-align: center !important;
        font-size: 9pt !important;
        color: #64748b !important;
        line-height: 1.6 !important;
    }

    .print-footer strong {
        color: #1e3a5f !important;
        font-size: 10pt !important;
    }

    /* ===== UTILITÁRIOS ===== */
    /* ===== CARD DE ITENS ESPECÍFICO ===== */
    .print-card-itens {
        margin: 0 40px 30px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        overflow: hidden !important;
        page-break-inside: avoid;
    }

    .print-card-itens .card-header {
        background: #f1f5f9 !important;
        padding: 14px 20px !important;
        border-bottom: 2px solid #e2e8f0 !important;
    }

    .print-card-itens .card-header h3 {
        font-size: 12pt !important;
        color: #1e3a5f !important;
        margin: 0 !important;
        font-weight: 700 !important;
    }

    .print-card-itens .card-body {
        padding: 0 !important;
    }

    a { text-decoration: none !important; color: inherit !important; }
    .page-break { page-break-before: always; }
}
.print-only { display: none; }

.btn-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
.btn-action { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 0.875rem; }
.btn-action-whatsapp { background: #22c55e; color: white; }
.btn-action-whatsapp:hover { background: #16a34a; }
.btn-action-email { background: #4f46e5; color: white; }
.btn-action-pdf { background: #ef4444; color: white; }
.btn-action-print { background: #64748b; color: white; }

.orcamento-header-print { text-align: center; padding: 20px; border-bottom: 2px solid #3b82f6; margin-bottom: 20px; }
.orcamento-header-print h2 { margin: 0; color: #0f172a; font-size: 1.5rem; }
.orcamento-header-print p { margin: 4px 0; color: #64748b; font-size: 0.875rem; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
@media (max-width: 768px) { .info-grid { grid-template-columns: 1fr; } }

.status-timeline { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.status-timeline a { text-decoration: none; }
</style>

<!-- CABEÇALHO PARA IMPRESSÃO -->
<div class="print-only orcamento-print-header">
    <?php if (get_config('site_logo')): ?>
    <img src="<?php echo uploads_url(get_config('site_logo')); ?>" alt="" class="orcamento-print-logo">
    <?php endif; ?>
    <h1><?php echo sanitize($empresa_nome); ?></h1>
    <?php if ($empresa_endereco): ?><div class="empresa-dados"><i class="fas fa-map-marker-alt"></i> <?php echo sanitize($empresa_endereco); ?></div><?php endif; ?>
    <?php if ($empresa_telefone): ?><div class="empresa-dados"><i class="fas fa-phone"></i> <?php echo sanitize($empresa_telefone); ?></div><?php endif; ?>
    <?php if ($empresa_whatsapp): ?><div class="empresa-dados"><i class="fab fa-whatsapp"></i> <?php echo sanitize($empresa_whatsapp); ?></div><?php endif; ?>
    <?php if ($empresa_email): ?><div class="empresa-dados"><i class="fas fa-envelope"></i> <?php echo sanitize($empresa_email); ?></div><?php endif; ?>
    <?php if ($empresa_responsavel): ?><div class="empresa-dados"><i class="fas fa-user-tie"></i> Responsável: <?php echo sanitize($empresa_responsavel); ?></div><?php endif; ?>
    <div class="orcamento-titulo">ORÇAMENTO <?php echo sanitize($orcamento['codigo']); ?></div>
</div>

<div class="page-header no-print">
    <h1><i class="fas fa-file-invoice-dollar"></i> Orçamento <?php echo sanitize($orcamento['codigo']); ?></h1>
    <a href="orcamentos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<!-- BOTÕES DE AÇÃO -->
<div class="btn-actions no-print">
    <?php 
    // WhatsApp só aparece se o cliente tiver telefone
    $tem_whatsapp = !empty($orcamento['cliente_telefone']);
    if ($tem_whatsapp): 
        $whatsapp_numero = preg_replace('/\D/', '', $orcamento['cliente_telefone']);

        // Formatar mensagem
        $msg = "*" . sanitize($empresa_nome) . "*%0A%0A";
        $msg .= "Olá " . sanitize($orcamento['cliente_nome']) . "!%0A%0A";
        if ($empresa_responsavel) {
            $msg .= "Responsável: " . sanitize($empresa_responsavel) . "%0A%0A";
        }
        $msg .= "*INFORMAÇÕES DO ORÇAMENTO*%0A";
        $msg .= "Código: " . sanitize($orcamento['codigo']) . "%0A";
        $msg .= "Status: " . ucfirst(str_replace('_', ' ', $orcamento['status'])) . "%0A";
        $msg .= "Data: " . format_date($orcamento['created_at']) . "%0A";
        $msg .= "Total: R$ " . number_format((float)$orcamento['valor_total'], 2, ',', '.') . "%0A%0A";
        $msg .= "*ITENS DO ORÇAMENTO*%0A";
        foreach ($itens as $i) {
            $msg .= ($i['quantidade']) . "x " . sanitize($i['produto_nome']);
            if ($i['preco_unitario'] > 0) {
                $msg .= " - R$ " . number_format((float)$i['subtotal'], 2, ',', '.');
            }
            $msg .= "%0A";
        }
        $msg .= "%0ATotal: R$ " . number_format((float)$orcamento['valor_total'], 2, ',', '.') . "%0A%0A";
        $msg .= "Aguardamos seu retorno!";
    ?>
    <a href="https://wa.me/<?php echo $whatsapp_numero; ?>?text=<?php echo $msg; ?>" target="_blank" class="btn-action btn-action-whatsapp">
        <i class="fab fa-whatsapp"></i> Enviar WhatsApp
    </a>
    <?php endif; ?>

    <a href="mailto:<?php echo sanitize($orcamento['cliente_email'] ?? ''); ?>?subject=Orçamento%20<?php echo urlencode($orcamento['codigo']); ?>&body=Prezado%20<?php echo urlencode($orcamento['cliente_nome']); ?>,%0A%0ASegue%20o%20orçamento%20<?php echo urlencode($orcamento['codigo']); ?>%20no%20valor%20de%20R$%20<?php echo number_format((float)$orcamento['valor_total'], 2, ',', '.'); ?>." class="btn-action btn-action-email">
        <i class="fas fa-envelope"></i> Enviar E-mail
    </a>
    <button onclick="imprimirOrcamento()" class="btn-action btn-action-print">
        <i class="fas fa-print"></i> Imprimir
    </button>
    <button onclick="imprimirOrcamento()" class="btn-action btn-action-pdf">
        <i class="fas fa-file-pdf"></i> Gerar PDF
    </button>
</div>

<div class="info-grid">
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-user"></i> Dados do Cliente</h3></div>
        <div class="card-body">
            <p><strong>Nome:</strong> <?php echo sanitize($orcamento['cliente_nome']); ?></p>
            <p><strong>E-mail:</strong> <?php echo sanitize($orcamento['cliente_email'] ?? '-'); ?></p>
            <p><strong>Telefone:</strong> <?php echo format_phone($orcamento['cliente_telefone']); ?></p>
            <p><strong>CPF/CNPJ:</strong> <?php echo format_cpf_cnpj($orcamento['cliente_cpf_cnpj']); ?></p>
            <p><strong>Contato preferido:</strong> <?php echo ucfirst($orcamento['tipo_contato']); ?></p>
            <?php if ($orcamento['observacoes']): ?><p><strong>Observações:</strong> <?php echo nl2br(sanitize($orcamento['observacoes'])); ?></p><?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-info-circle"></i> Informações</h3></div>
        <div class="card-body">
            <p><strong>Código:</strong> <code><?php echo sanitize($orcamento['codigo']); ?></code></p>
            <p><strong>Status:</strong> <span class="badge-status status-<?php echo $orcamento['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$orcamento['status'])); ?></span></p>
            <p><strong>Data:</strong> <?php echo format_date($orcamento['created_at']); ?></p>
            <p><strong>Total:</strong> <strong style="color:var(--primary);font-size:1.25rem;"><?php echo format_currency((float)$orcamento['valor_total']); ?></strong></p>
            <?php if ($orcamento['atendente']): ?><p><strong>Atendente:</strong> <?php echo sanitize($orcamento['atendente']); ?></p><?php endif; ?>
            <?php if (isset($orcamento['data_entrega']) && !empty($orcamento['data_entrega'])): ?><p><strong>Data Entrega:</strong> <?php echo date('d/m/Y', strtotime($orcamento['data_entrega'])); ?></p><?php endif; ?>
            <?php if (isset($orcamento['tabela_preco']) && !empty($orcamento['tabela_preco'])): ?><p><strong>Tabela:</strong> <?php echo ucfirst(str_replace('_', ' ', $orcamento['tabela_preco'])); ?></p><?php endif; ?>

            <?php 
            $estoque_baixo = false;
            foreach ($itens as $i) {
                if ($i['estoque_atual'] !== null && $i['estoque_atual'] <= ($i['estoque_minimo'] ?? 0) && ($i['estoque_minimo'] ?? 0) > 0) {
                    $estoque_baixo = true;
                    break;
                }
            }
            if ($estoque_baixo): ?>
            <div style="margin-top: 12px; padding: 12px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; color: #92400e; font-size: 0.875rem;">
                <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i>
                <strong>Atenção:</strong> Alguns produtos deste orçamento estão com estoque baixo!
            </div>
            <?php endif; ?>

            <div class="status-timeline no-print">
                <?php foreach ($status_list as $s): if ($s === $orcamento['status']) continue; ?>
                <a href="?action=status&id=<?php echo $id; ?>&status=<?php echo $s; ?>" class="btn btn-sm btn-secondary" onclick="return confirm('Alterar status para <?php echo ucfirst(str_replace('_',' ',$s)); ?>?<?php echo $s === 'aprovado' ? ' Isso dará baixa no estoque!' : ''; ?>')">Marcar como <?php echo ucfirst(str_replace('_',' ',$s)); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card print-card-itens" style="margin-top:20px;">
    <div class="card-header"><h3><i class="fas fa-shopping-cart"></i> Itens do Orçamento</h3></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Nº</th><th>Produto</th><th>Un</th><th>Qtd</th><th>Preço Unit.</th><th>Subtotal</th><th>Estoque</th></tr></thead>
                <tbody>
                    <?php $n = 1; foreach ($itens as $i): 
                        $estoque_status = '';
                        $estoque_style = '';
                        if ($i['estoque_atual'] !== null) {
                            if ($i['estoque_atual'] <= 0) {
                                $estoque_status = 'Esgotado';
                                $estoque_style = 'color: #ef4444; font-weight: bold;';
                            } elseif ($i['estoque_atual'] <= ($i['estoque_minimo'] ?? 0) && ($i['estoque_minimo'] ?? 0) > 0) {
                                $estoque_status = 'Baixo';
                                $estoque_style = 'color: #f59e0b; font-weight: bold;';
                            } else {
                                $estoque_status = 'OK';
                                $estoque_style = 'color: #22c55e;';
                            }
                        }
                    ?>
                    <tr>
                        <td><?php echo $n++; ?></td>
                        <td><strong><?php echo sanitize($i['produto_nome']); ?></strong></td>
                        <td><?php echo sanitize($i['unidade'] ?? 'un'); ?></td>
                        <td><?php echo $i['quantidade']; ?></td>
                        <td><?php echo $i['preco_unitario'] > 0 ? format_currency((float)$i['preco_unitario']) : '-'; ?></td>
                        <td><strong><?php echo $i['subtotal'] > 0 ? format_currency((float)$i['subtotal']) : '-'; ?></strong></td>
                        <td style="<?php echo $estoque_style; ?>">
                            <?php echo $i['estoque_atual'] !== null ? $i['estoque_atual'] . ' (' . $estoque_status . ')' : '-'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php if (!empty($orcamento['valor_produtos']) && (float)$orcamento['valor_produtos'] > 0): ?>
                    <tr style="border-top: 1px solid #e2e8f0;">
                        <td colspan="5" style="text-align: right; font-weight: 600;">Valor Produtos:</td>
                        <td colspan="2"><?php echo format_currency((float)$orcamento['valor_produtos']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($orcamento['valor_servicos']) && (float)$orcamento['valor_servicos'] > 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: right; font-weight: 600;">Valor Serviços:</td>
                        <td colspan="2"><?php echo format_currency((float)$orcamento['valor_servicos']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($orcamento['desconto']) && (float)$orcamento['desconto'] > 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: right; font-weight: 600;">Desconto:</td>
                        <td colspan="2" style="color: #ef4444;">- <?php echo format_currency((float)$orcamento['desconto']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr style="border-top: 2px solid #e2e8f0;">
                        <td colspan="5" style="text-align: right; font-weight: 700; font-size: 1.125rem;">TOTAL:</td>
                        <td colspan="2" style="font-weight: 700; font-size: 1.25rem; color: #3b82f6;"><?php echo format_currency((float)$orcamento['valor_total']); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php if ($orcamento['observacoes']): ?>
<div class="card" style="margin-top:20px;">
    <div class="card-header"><h3><i class="fas fa-comment-alt"></i> Observações</h3></div>
    <div class="card-body">
        <p style="white-space: pre-wrap;"><?php echo nl2br(sanitize($orcamento['observacoes'])); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- RODAPÉ PROFISSIONAL PARA IMPRESSÃO -->
<div class="print-only print-footer">
    <strong><?php echo sanitize($empresa_nome); ?></strong><br>
    <?php if ($empresa_endereco): echo sanitize($empresa_endereco); ?> | <?php endif; ?>
    <?php if ($empresa_telefone): echo sanitize($empresa_telefone); ?> | <?php endif; ?>
    <?php if ($empresa_whatsapp): echo sanitize($empresa_whatsapp); ?> | <?php endif; ?>
    <?php if ($empresa_email): echo sanitize($empresa_email); endif; ?><br><br>
    <em>Orçamento gerado em <?php echo date('d/m/Y \\à\\s H:i'); ?></em><br>
    <em>Este orçamento é válido por 7 dias a partir da data de emissão.</em><br><br>
    <div style="margin-top: 15px; border-top: 1px dashed #cbd5e1; padding-top: 15px;">
        <strong>Assinatura do Cliente:</strong> _________________________________<br><br>
        <strong>Assinatura do Responsável:</strong> _________________________________
    </div>
</div>

<script>
function gerarPDF() {
    window.print();
}

function imprimirOrcamento() {
    const janela = window.open('', '_blank');
    janela.document.write(`<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Orçamento - <?php echo sanitize($orcamento['codigo']); ?></title>
    <style>
        @page { size: A4; margin: 15mm; }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; color-adjust: exact; }

        body { 
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; 
            padding: 0; 
            color: #1e293b; 
            font-size: 11pt;
            line-height: 1.5;
            max-width: 100%;
            margin: 0 auto;
            background: white;
        }

        /* ===== CABEÇALHO ===== */
        .cabecalho { 
            text-align: center; 
            padding: 20px 0 25px; 
            border-bottom: 3px solid #1e3a5f; 
            margin-bottom: 30px; 
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }

        .cabecalho img { 
            max-height: 80px; 
            margin-bottom: 12px;
            object-fit: contain;
        }

        .cabecalho h1 { 
            color: #1e3a5f; 
            font-size: 24pt; 
            margin-bottom: 6px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .cabecalho .dados-empresa {
            font-size: 9.5pt;
            color: #475569;
            margin: 2px 0;
            line-height: 1.5;
        }

        .cabecalho .titulo-orcamento {
            display: inline-block;
            margin-top: 18px;
            padding: 6px 25px;
            background: #1e3a5f;
            color: white;
            font-size: 12pt;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            border-radius: 4px;
        }

        /* ===== GRID 2 COLUNAS ===== */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        /* ===== SEÇÕES/CARDS ===== */
        .secao {
            margin-bottom: 20px;
            page-break-inside: avoid;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .secao-titulo {
            font-size: 10.5pt;
            font-weight: 700;
            color: #1e3a5f;
            padding: 10px 15px;
            background: #f1f5f9;
            border-bottom: 2px solid #e2e8f0;
            margin: 0;
        }

        .secao-conteudo {
            padding: 12px 15px;
        }

        .info-linha {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 10pt;
            padding: 3px 0;
            border-bottom: 1px dotted #e2e8f0;
        }

        .info-linha:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #334155;
            min-width: 100px;
        }

        .info-valor {
            color: #475569;
            text-align: right;
            flex: 1;
        }

        /* ===== TABELA ===== */
        .tabela-container {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .tabela-titulo {
            font-size: 10.5pt;
            font-weight: 700;
            color: #1e3a5f;
            padding: 10px 15px;
            background: #f1f5f9;
            border-bottom: 2px solid #e2e8f0;
            margin: 0;
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 10pt;
        }

        thead {
            display: table-header-group;
        }

        thead tr {
            background: #1e3a5f;
        }

        th { 
            background: #1e3a5f; 
            color: white; 
            padding: 10px 12px; 
            text-align: left; 
            font-weight: 600;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }

        td { 
            padding: 8px 12px; 
            border-bottom: 1px solid #e2e8f0;
            font-size: 10pt;
            color: #334155;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: 2px solid #1e3a5f;
        }

        tfoot td {
            padding: 8px 12px;
            font-weight: 600;
            font-size: 10pt;
            color: #1e3a5f;
        }

        tfoot tr:last-child td {
            font-size: 12pt;
            font-weight: 700;
            color: #1e3a5f;
            padding-top: 10px;
            padding-bottom: 10px;
            border-top: 3px solid #1e3a5f;
            border-bottom: none;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; }

        /* ===== BADGE STATUS ===== */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 8.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-aprovado { background: #dcfce7; color: #166534; }
        .status-pendente { background: #fef3c7; color: #92400e; }
        .status-novo { background: #dbeafe; color: #1e40af; }
        .status-respondido { background: #d1fae5; color: #065f46; }
        .status-rejeitado { background: #fee2e2; color: #991b1b; }
        .status-cancelado { background: #f3f4f6; color: #4b5563; }
        .status-em_analise { background: #e0e7ff; color: #3730a3; }

        /* ===== OBSERVAÇÕES ===== */
        .observacoes-container {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .observacoes-titulo {
            font-size: 10.5pt;
            font-weight: 700;
            color: #1e3a5f;
            padding: 10px 15px;
            background: #f1f5f9;
            border-bottom: 2px solid #e2e8f0;
            margin: 0;
        }

        .observacoes-box {
            padding: 12px 15px;
            font-size: 10pt;
            color: #475569;
            line-height: 1.6;
            min-height: 60px;
            background: #fafafa;
        }

        /* ===== RODAPÉ ===== */
        .rodape {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            font-size: 9pt;
            color: #64748b;
            line-height: 1.6;
        }

        .rodape strong {
            color: #1e3a5f;
            font-size: 10pt;
        }

        .assinaturas {
            margin-top: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            text-align: center;
        }

        .assinatura-linha {
            border-top: 1px solid #94a3b8;
            padding-top: 8px;
            margin-top: 40px;
            font-size: 9pt;
            color: #64748b;
        }

        @media print {
            body { padding: 0; margin: 0; }
        }
    </style>
</head>
<body>
    <!-- CABEÇALHO -->
    <div class="cabecalho">
        <?php 
        $logo_cliente = get_config('logo_cliente');
        if ($logo_cliente): 
        ?>
        <img src="<?php echo uploads_url($logo_cliente); ?>" alt="">
        <?php endif; ?>
        <h1><?php echo sanitize($empresa_nome); ?></h1>
        <?php if ($empresa_endereco): ?><div class="dados-empresa"><i class="fas fa-map-marker-alt"></i> <?php echo sanitize($empresa_endereco); ?></div><?php endif; ?>
        <?php if ($empresa_telefone): ?><div class="dados-empresa"><i class="fas fa-phone"></i> <?php echo sanitize($empresa_telefone); ?></div><?php endif; ?>
        <?php if ($empresa_whatsapp): ?><div class="dados-empresa"><i class="fab fa-whatsapp"></i> <?php echo sanitize($empresa_whatsapp); ?></div><?php endif; ?>
        <?php if ($empresa_email): ?><div class="dados-empresa"><i class="fas fa-envelope"></i> <?php echo sanitize($empresa_email); ?></div><?php endif; ?>
        <?php if ($empresa_responsavel): ?><div class="dados-empresa"><i class="fas fa-user-tie"></i> Responsável: <?php echo sanitize($empresa_responsavel); ?></div><?php endif; ?>
        <div class="titulo-orcamento">ORÇAMENTO <?php echo sanitize($orcamento['codigo']); ?></div>
    </div>

    <!-- GRID DADOS -->
    <div class="grid-2">
        <div class="secao">
            <div class="secao-titulo"><i class="fas fa-user"></i> Dados do Cliente</div>
            <div class="secao-conteudo">
                <div class="info-linha"><span class="info-label">Nome:</span><span class="info-valor"><?php echo sanitize($orcamento['cliente_nome']); ?></span></div>
                <div class="info-linha"><span class="info-label">E-mail:</span><span class="info-valor"><?php echo sanitize($orcamento['cliente_email'] ?? '-'); ?></span></div>
                <div class="info-linha"><span class="info-label">Telefone:</span><span class="info-valor"><?php echo format_phone($orcamento['cliente_telefone']); ?></span></div>
                <div class="info-linha"><span class="info-label">CPF/CNPJ:</span><span class="info-valor"><?php echo format_cpf_cnpj($orcamento['cliente_cpf_cnpj']); ?></span></div>
                <div class="info-linha"><span class="info-label">Contato:</span><span class="info-valor"><?php echo ucfirst($orcamento['tipo_contato']); ?></span></div>
            </div>
        </div>

        <div class="secao">
            <div class="secao-titulo"><i class="fas fa-info-circle"></i> Informações</div>
            <div class="secao-conteudo">
                <div class="info-linha"><span class="info-label">Código:</span><span class="info-valor"><?php echo sanitize($orcamento['codigo']); ?></span></div>
                <div class="info-linha"><span class="info-label">Status:</span><span class="info-valor"><span class="status-badge status-<?php echo $orcamento['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$orcamento['status'])); ?></span></span></div>
                <div class="info-linha"><span class="info-label">Data:</span><span class="info-valor"><?php echo format_date($orcamento['created_at']); ?></span></div>
                <?php if (isset($orcamento['data_entrega']) && !empty($orcamento['data_entrega'])): ?>
                <div class="info-linha"><span class="info-label">Entrega:</span><span class="info-valor"><?php echo date('d/m/Y', strtotime($orcamento['data_entrega'])); ?></span></div>
                <?php endif; ?>
                <div class="info-linha"><span class="info-label">Total:</span><span class="info-valor font-bold"><?php echo format_currency((float)$orcamento['valor_total']); ?></span></div>
            </div>
        </div>
    </div>

    <!-- TABELA DE ITENS -->
    <div class="tabela-container">
        <div class="tabela-titulo"><i class="fas fa-shopping-cart"></i> Itens do Orçamento</div>
        <table>
            <thead>
                <tr>
                    <th width="40" class="text-center">Nº</th>
                    <th>Produto</th>
                    <th width="60" class="text-center">Un</th>
                    <th width="60" class="text-center">Qtd</th>
                    <th width="100" class="text-right">Preço Unit.</th>
                    <th width="100" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $n = 1; foreach ($itens as $i): ?>
                <tr>
                    <td class="text-center"><?php echo $n++; ?></td>
                    <td class="font-bold"><?php echo sanitize($i['produto_nome']); ?></td>
                    <td class="text-center"><?php echo sanitize($i['unidade'] ?? 'un'); ?></td>
                    <td class="text-center"><?php echo $i['quantidade']; ?></td>
                    <td class="text-right"><?php echo $i['preco_unitario'] > 0 ? format_currency((float)$i['preco_unitario']) : '-'; ?></td>
                    <td class="text-right font-bold"><?php echo $i['subtotal'] > 0 ? format_currency((float)$i['subtotal']) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php if (!empty($orcamento['valor_produtos']) && (float)$orcamento['valor_produtos'] > 0): ?>
                <tr>
                    <td colspan="5" class="text-right">Valor Produtos:</td>
                    <td class="text-right"><?php echo format_currency((float)$orcamento['valor_produtos']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($orcamento['valor_servicos']) && (float)$orcamento['valor_servicos'] > 0): ?>
                <tr>
                    <td colspan="5" class="text-right">Valor Serviços:</td>
                    <td class="text-right"><?php echo format_currency((float)$orcamento['valor_servicos']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($orcamento['desconto']) && (float)$orcamento['desconto'] > 0): ?>
                <tr>
                    <td colspan="5" class="text-right">Desconto:</td>
                    <td class="text-right" style="color: #ef4444;">- <?php echo format_currency((float)$orcamento['desconto']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="5" class="text-right font-bold">TOTAL:</td>
                    <td class="text-right font-bold"><?php echo format_currency((float)$orcamento['valor_total']); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- OBSERVAÇÕES -->
    <?php if (!empty($orcamento['observacoes'])): ?>
    <div class="observacoes-container">
        <div class="observacoes-titulo"><i class="fas fa-comment-alt"></i> Observações</div>
        <div class="observacoes-box"><?php echo nl2br(sanitize($orcamento['observacoes'])); ?></div>
    </div>
    <?php endif; ?>

    <!-- RODAPÉ -->
    <div class="rodape">
        <strong><?php echo sanitize($empresa_nome); ?></strong><br>
        <?php if ($empresa_endereco): echo sanitize($empresa_endereco); ?> | <?php endif; ?>
        <?php if ($empresa_telefone): echo sanitize($empresa_telefone); ?> | <?php endif; ?>
        <?php if ($empresa_whatsapp): echo sanitize($empresa_whatsapp); ?> | <?php endif; ?>
        <?php if ($empresa_email): echo sanitize($empresa_email); endif; ?><br><br>
        <em>Orçamento gerado em <?php echo date('d/m/Y \à\s H:i'); ?></em><br>
        <em>Este orçamento é válido por 7 dias a partir da data de emissão.</em><br><br>
        <div class="assinaturas">
            <div>
                <div class="assinatura-linha">Assinatura do Cliente</div>
            </div>
            <div>
                <div class="assinatura-linha">Assinatura do Responsável</div>
            </div>
        </div>
    </div>
</body>
</html>`);
    janela.document.close();
    janela.focus();
    setTimeout(() => { janela.print(); }, 800);
}
</script>

<?php 
// ============================================
// LISTAR ORÇAMENTOS
// ============================================
else: 
?>
<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Orçamentos</h1>
    <div class="page-actions no-print">
        <a href="orcamentos.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Orçamento</a>
    </div>
</div>

<!-- CARD DE STATUS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 24px;">
    <?php foreach ($status_list as $s): 
        $cores = [
            'novo' => ['bg' => '#dbeafe', 'text' => '#1e40af', 'icon' => 'fa-star'],
            'pendente' => ['bg' => '#fef3c7', 'text' => '#92400e', 'icon' => 'fa-clock'],
            'em_analise' => ['bg' => '#e0e7ff', 'text' => '#3730a3', 'icon' => 'fa-search'],
            'respondido' => ['bg' => '#d1fae5', 'text' => '#065f46', 'icon' => 'fa-reply'],
            'aprovado' => ['bg' => '#dcfce7', 'text' => '#166534', 'icon' => 'fa-check-circle'],
            'rejeitado' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'icon' => 'fa-times-circle'],
            'cancelado' => ['bg' => '#f3f4f6', 'text' => '#4b5563', 'icon' => 'fa-ban'],
        ];
        $c = $cores[$s] ?? ['bg' => '#f3f4f6', 'text' => '#4b5563', 'icon' => 'fa-circle'];
    ?>
    <a href="orcamentos.php?status=<?php echo $s; ?>" style="text-decoration: none; display: block;">
        <div style="background: <?php echo $c['bg']; ?>; border-radius: 12px; padding: 16px; display: flex; align-items: center; gap: 12px; transition: transform 0.2s; border: 2px solid <?php echo $status_filtro === $s ? $c['text'] : 'transparent'; ?>;">
            <div style="width: 40px; height: 40px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <i class="fas <?php echo $c['icon']; ?>" style="color: <?php echo $c['text']; ?>; font-size: 1.125rem;"></i>
            </div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 700; color: <?php echo $c['text']; ?>; line-height: 1;"><?php echo $status_counts[$s]; ?></div>
                <div style="font-size: 0.8125rem; color: <?php echo $c['text']; ?>; opacity: 0.8; text-transform: capitalize;"><?php echo str_replace('_', ' ', $s); ?></div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<div class="filters-bar no-print">
    <div class="form-group"><input type="text" name="busca" value="<?php echo sanitize($busca); ?>" placeholder="Buscar..." form="filterForm"></div>
    <div class="form-group">
        <select name="status" form="filterForm">
            <option value="">Todos Status</option>
            <?php foreach ($status_list as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo selected($status_filtro, $s); ?>><?php echo ucfirst(str_replace('_',' ',$s)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary" form="filterForm"><i class="fas fa-search"></i></button>
    <a href="orcamentos.php" class="btn btn-secondary">Limpar</a>
    <form id="filterForm" method="GET" style="display:none;"></form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Código</th><th>Cliente</th><th>Itens</th><th>Total</th><th>Status</th><th>Data</th><th width="120" class="no-print">Ações</th></tr></thead>
            <tbody>
                <?php foreach ($orcamentos as $o): ?>
                <tr>
                    <td><strong><a href="?action=view&id=<?php echo $o['id']; ?>"><?php echo sanitize($o['codigo']); ?></a></strong></td>
                    <td><?php echo sanitize($o['cliente_nome']); ?><br><small style="color:var(--gray-400)"><?php echo format_phone($o['cliente_telefone']); ?></small></td>
                    <td><?php echo $o['total_itens']; ?> item(s)</td>
                    <td><strong><?php echo $o['valor_total'] > 0 ? format_currency((float)$o['valor_total']) : '-'; ?></strong></td>
                    <td><span class="badge-status status-<?php echo $o['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$o['status'])); ?></span></td>
                    <td><?php echo format_date($o['created_at']); ?></td>
                    <td class="actions no-print"><a href="?action=view&id=<?php echo $o['id']; ?>" class="btn btn-sm btn-secondary btn-icon" title="Ver"><i class="fas fa-eye"></i></a><a href="?action=delete&id=<?php echo $o['id']; ?>" class="btn btn-sm btn-danger btn-icon btn-delete" title="Excluir" onclick="return confirm('Excluir orçamento?')"><i class="fas fa-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination-wrap"><?php echo pagination_links($pagination, 'orcamentos.php', array_filter(['status' => $status_filtro, 'busca' => $busca])); ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
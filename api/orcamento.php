<?php
/**
 * SiteCatalogo - API para Orcamentos
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido']);
    exit;
}

try {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $tipo_contato = $_POST['tipo_contato'] ?? 'whatsapp';
    $itens_json = $_POST['itens'] ?? '[]';
    
    // Validacao
    if (empty($nome) || empty($email) || empty($telefone)) {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatorios']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'E-mail invalido']);
        exit;
    }
    
    $itens = json_decode($itens_json, true);
    if (empty($itens) || !is_array($itens)) {
        echo json_encode(['success' => false, 'message' => 'Adicione pelo menos um produto']);
        exit;
    }
    
    // Gerar codigo
    $codigo = 'ORC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Calcular total
    $total = 0;
    foreach ($itens as $item) {
        $total += ($item['preco'] ?? 0) * ($item['qtd'] ?? 1);
    }
    
    // Inserir orcamento
    $stmt = db()->prepare("INSERT INTO " . table('orcamentos') . " 
        (codigo, cliente_nome, cliente_email, cliente_telefone, cliente_cpf_cnpj, 
         observacoes, status, tipo_contato, valor_total) 
        VALUES (?, ?, ?, ?, ?, ?, 'novo', ?, ?)");
    $stmt->execute([$codigo, $nome, $email, $telefone, $cpf_cnpj, $observacoes, $tipo_contato, $total]);
    
    $orcamento_id = db()->lastInsertId();
    
    // Inserir itens
    $stmt_item = db()->prepare("INSERT INTO " . table('orcamento_itens') . " 
        (orcamento_id, produto_id, produto_nome, quantidade, preco_unitario, subtotal) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($itens as $item) {
        $qtd = $item['qtd'] ?? 1;
        $preco = $item['preco'] ?? 0;
        $subtotal = $preco * $qtd;
        $stmt_item->execute([
            $orcamento_id,
            $item['id'] ?? null,
            $item['nome'] ?? 'Produto',
            $qtd,
            $preco,
            $subtotal
        ]);
    }
    
    // Enviar notificacao WhatsApp se configurado
    $whatsapp = get_config('whatsapp', '');
    if ($whatsapp && $tipo_contato === 'whatsapp') {
        $msg = "*Novo Orcamento*\n";
        $msg .= "*Codigo:* {$codigo}\n";
        $msg .= "*Cliente:* {$nome}\n";
        $msg .= "*Telefone:* {$telefone}\n";
        $msg .= "*Total:* R$ " . number_format($total, 2, ',', '.');
        
        $wa_url = "https://wa.me/" . preg_replace('/\D/', '', $whatsapp) . "?text=" . urlencode($msg);
    }
    
    echo json_encode([
        'success' => true,
        'codigo' => $codigo,
        'message' => 'Orcamento enviado com sucesso!'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar: ' . $e->getMessage()]);
}

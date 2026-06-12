<?php
/**
 * SiteCatalogo - Pagina Principal do Catalogo Publico
 */

if (!file_exists(__DIR__ . '/config.php')) {
    if (file_exists(__DIR__ . '/install/index.php')) {
        header('Location: install/');
        exit;
    }
    die('Erro: O sistema nao esta instalado.');
}

require_once __DIR__ . '/config.php';

if (!defined('INSTALLED') || INSTALLED !== true) {
    if (file_exists(__DIR__ . '/install/index.php')) {
        header('Location: install/');
        exit;
    }
    die('Erro: Sistema nao configurado corretamente.');
}

require_once __DIR__ . '/includes/functions.php';

if (function_exists('session_check')) {
    session_check();
}

// Configs
$site_name = get_config('site_name', defined('SITE_NAME') ? SITE_NAME : 'SiteCatalogo');
$site_description = get_config('site_description', defined('SITE_DESCRIPTION') ? SITE_DESCRIPTION : 'Catalogo Profissional');
$whatsapp = get_config('whatsapp', defined('WHATSAPP') ? WHATSAPP : '');
$orcamento_msg = get_config('orcamento_whatsapp_msg', defined('WHATSAPP_DEFAULT_MSG') ? WHATSAPP_DEFAULT_MSG : 'Ola! Gostaria de solicitar um orcamento para os seguintes produtos:');
$mostrar_preco = get_config('mostrar_preco', '1') === '1'; // Controla exibicao de precos E estoque no catalogo

$site_email = get_config('site_email', '');
$telefone = get_config('telefone', '');
$endereco = get_config('endereco', '');
$horario = get_config('horario_atendimento', 'Segunda a Sexta: 08h as 18h');
$facebook_url = get_config('facebook_url', '');
$instagram_url = get_config('instagram_url', '');
$linkedin_url = get_config('linkedin_url', '');
$youtube_url = get_config('youtube_url', '');
$tiktok_url = get_config('tiktok_url', '');

// Busca
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$categoria_id = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 12;

// Dados
try {
    $stmt = db()->query("SELECT * FROM " . table('categorias') . " WHERE ativo = 1 ORDER BY ordem, nome");
    $categorias = $stmt->fetchAll();
} catch (Exception $e) { $categorias = []; }

try {
    $stmt = db()->query("SELECT * FROM " . table('banners') . " WHERE ativo = 1 AND posicao = 'home_topo' ORDER BY ordem LIMIT 5");
    $banners = $stmt->fetchAll();
} catch (Exception $e) { $banners = []; }

// Produtos
$where = ["p.ativo = 1"];
$params = [];

if ($busca) {
    $where[] = "(p.nome LIKE ? OR p.descricao_curta LIKE ? OR p.sku LIKE ?)";
    $like = "%{$busca}%";
    $params = [$like, $like, $like];
}

if ($categoria_id) {
    $where[] = "p.categoria_id = ?";
    $params[] = $categoria_id;
}

$where_sql = implode(' AND ', $where);

try {
    $stmt = db()->prepare("SELECT COUNT(*) FROM " . table('produtos') . " p WHERE {$where_sql}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
} catch (Exception $e) { $total = 0; }

$pagination = paginate($total, $page, $per_page);
$offset = max(0, $pagination['offset']);

try {
    $stmt = db()->prepare("SELECT p.*, c.nome as categoria_nome, c.slug as categoria_slug 
        FROM " . table('produtos') . " p 
        LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id 
        WHERE {$where_sql} 
        ORDER BY p.destaque DESC, p.created_at DESC 
        LIMIT {$offset}, {$per_page}");
    $stmt->execute($params);
    $produtos = $stmt->fetchAll();
} catch (Exception $e) { $produtos = []; }

// Imagens
$produto_ids = array_column($produtos, 'id');
$imagens_por_produto = [];
if (!empty($produto_ids)) {
    try {
        $placeholders = implode(',', array_fill(0, count($produto_ids), '?'));
        $stmt = db()->prepare("SELECT * FROM " . table('produto_imagens') . " WHERE produto_id IN ({$placeholders}) ORDER BY ordem");
        $stmt->execute($produto_ids);
        foreach ($stmt->fetchAll() as $img) {
            $imagens_por_produto[$img['produto_id']][] = $img;
        }
    } catch (Exception $e) { $imagens_por_produto = []; }
}

// Produto detalhe para modal
$produto_modal = null;
$produto_imagens_modal = [];
if (isset($_GET['produto_id'])) {
    $pid = (int)$_GET['produto_id'];
    $stmt = db()->prepare("SELECT p.*, c.nome as categoria_nome FROM " . table('produtos') . " p LEFT JOIN " . table('categorias') . " c ON p.categoria_id = c.id WHERE p.id = ? AND p.ativo = 1");
    $stmt->execute([$pid]);
    $produto_modal = $stmt->fetch();
    
    if ($produto_modal) {
        $stmt = db()->prepare("SELECT * FROM " . table('produto_imagens') . " WHERE produto_id = ? ORDER BY ordem");
        $stmt->execute([$pid]);
        $produto_imagens_modal = $stmt->fetchAll();
    }
}

// Titulo da pagina
$titulo_pagina = 'Todos os Produtos';
if ($busca) {
    $titulo_pagina = 'Resultados para "' . $busca . '"';
} elseif ($categoria_id) {
    $cat_atual = array_filter($categorias, fn($c) => $c['id'] == $categoria_id);
    $cat_atual = $cat_atual ? array_values($cat_atual)[0] : null;
    $titulo_pagina = $cat_atual ? $cat_atual['nome'] : 'Categoria';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($site_name); ?> - <?php echo sanitize($site_description); ?></title>
    <meta name="description" content="<?php echo sanitize($site_description); ?>">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>">
    <meta property="og:title" content="<?php echo sanitize($site_name); ?>">
    <meta property="og:description" content="<?php echo sanitize($site_description); ?>">
    
    <?php if (get_config('google_analytics')): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo get_config('google_analytics'); ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo get_config('google_analytics'); ?>');</script>
    <?php endif; ?>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo assets_url('css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
    /* MODAL PRODUTO */
    .modal-produto-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; }
    .modal-produto-overlay.open { display: flex; }
    .modal-produto { background: white; border-radius: 20px; max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
    .modal-produto-close { position: absolute; top: 16px; right: 16px; width: 40px; height: 40px; background: #f1f5f9; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #64748b; z-index: 10; transition: all 0.2s; }
    .modal-produto-close:hover { background: #e2e8f0; color: #0f172a; }
    .modal-produto-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
    @media (max-width: 768px) { .modal-produto-grid { grid-template-columns: 1fr; } }
    .modal-produto-imagens { padding: 24px; background: #f8fafc; }
    .modal-produto-img-principal { width: 100%; height: 300px; object-fit: contain; border-radius: 12px; background: white; margin-bottom: 12px; }
    .modal-produto-galeria { display: flex; gap: 8px; overflow-x: auto; }
    .modal-produto-galeria img { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; }
    .modal-produto-galeria img:hover, .modal-produto-galeria img.active { border-color: #3b82f6; }
    .modal-produto-info { padding: 32px; }
    .modal-produto-categoria { color: #3b82f6; font-size: 0.875rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
    .modal-produto-nome { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 12px; line-height: 1.3; }
    .modal-produto-desc { color: #64748b; font-size: 0.9375rem; line-height: 1.6; margin-bottom: 20px; }
    .modal-produto-preco { margin-bottom: 20px; }
    .modal-produto-preco .old { text-decoration: line-through; color: #94a3b8; font-size: 1rem; }
    .modal-produto-preco .current { color: #3b82f6; font-size: 1.75rem; font-weight: 700; }
    .modal-produto-preco .consulta { color: #64748b; font-size: 1.125rem; }
    .modal-produto-detalhes { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
    .modal-produto-detalhe { background: #f8fafc; padding: 12px; border-radius: 8px; }
    .modal-produto-detalhe label { display: block; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
    .modal-produto-detalhe span { font-size: 0.9375rem; color: #0f172a; font-weight: 500; }
    .modal-produto-estoque { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 0.8125rem; font-weight: 500; margin-bottom: 20px; }
    .estoque-ok { background: #dcfce7; color: #166534; }
    .estoque-baixo { background: #fef3c7; color: #92400e; }
    .estoque-esgotado { background: #fee2e2; color: #991b1b; }
    .modal-produto-btns { display: flex; gap: 12px; }
    .modal-produto-btns .btn { flex: 1; padding: 14px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.9375rem; transition: all 0.2s; }
    .btn-add-orcamento { background: #3b82f6; color: white; }
    .btn-add-orcamento:hover { background: #2563eb; }
    .btn-wp-produto { background: #22c55e; color: white; text-decoration: none; }
    .btn-wp-produto:hover { background: #16a34a; }
    
    /* WHATSAPP FLOAT */
    .whatsapp-float { position: fixed; bottom: 100px; right: 24px; width: 56px; height: 56px; background: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.75rem; box-shadow: 0 4px 12px rgba(34,197,94,0.4); cursor: pointer; z-index: 999; text-decoration: none; transition: all 0.3s; animation: pulse-whatsapp 2s infinite; }
    .whatsapp-float:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(34,197,94,0.5); }
    @keyframes pulse-whatsapp { 0%, 100% { box-shadow: 0 4px 12px rgba(34,197,94,0.4); } 50% { box-shadow: 0 4px 24px rgba(34,197,94,0.6); } }
    

    /* ===== RESPONSIVO MOBILE ===== */
    @media (max-width: 768px) {
        .products-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 12px;
        }
        .product-card {
            min-width: 0;
        }
        .product-image {
            height: 160px;
        }
        .product-image img {
            height: 100%;
        }
        .product-name {
            font-size: 0.8125rem;
            line-height: 1.3;
        }
        .product-desc {
            display: none;
        }
        .product-price .price-current {
            font-size: 0.9375rem;
        }
        .product-price .price-old {
            font-size: 0.75rem;
        }
        .btn-add-cart {
            width: 32px;
            height: 32px;
        }
        .content-layout {
            grid-template-columns: 1fr;
        }
        .sidebar {
            order: -1;
        }
        .sidebar-card {
            padding: 16px;
        }
        .category-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .category-link {
            padding: 8px 12px;
            border-radius: 20px;
            background: #f1f5f9;
            font-size: 0.8125rem;
        }
        .category-link.active {
            background: #3b82f6;
            color: white;
        }
        .toolbar {
            padding: 12px;
        }
        .search-form {
            flex-wrap: wrap;
        }
        .search-input-wrap {
            min-width: 100%;
        }
        .hero-section {
            min-height: 200px;
        }
        .hero-content h1 {
            font-size: 1.5rem;
        }
    }
    @media (max-width: 480px) {
        .products-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 8px;
        }
        .product-image {
            height: 130px;
        }
        .product-info {
            padding: 10px;
        }
        .product-name {
            font-size: 0.75rem;
        }
    }
    </style>
</head>
<body>
<<<<<<< HEAD
=======
    <!-- Header -->
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
    <header class="site-header">
        <div class="container">
            <div class="header-inner">
                <?php 
                $logo_cliente = get_config('logo_cliente');
                $navbar_tipo = get_config('navbar_tipo', 'imagem_texto');
                ?>
                <a href="./" class="logo">
                    <?php if ($logo_cliente && ($navbar_tipo === 'imagem' || $navbar_tipo === 'imagem_texto')): ?>
                    <img src="<?php echo uploads_url($logo_cliente); ?>" alt="<?php echo sanitize($site_name); ?>" style="height: 36px; width: auto; object-fit: contain; border-radius: 6px;">
                    <?php endif; ?>
                    <?php if (!$logo_cliente && ($navbar_tipo === 'imagem' || $navbar_tipo === 'imagem_texto')): ?>
                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                        <rect width="36" height="36" rx="9" fill="#3b82f6"/>
                        <path d="M10 24V14l8-5 8 5v10H10z" stroke="white" stroke-width="2" fill="none"/>
                        <circle cx="18" cy="19" r="2.5" fill="white"/>
                    </svg>
                    <?php endif; ?>
                    <?php if ($navbar_tipo === 'texto' || $navbar_tipo === 'imagem_texto'): ?>
                    <span class="logo-text"><?php echo sanitize($site_name); ?></span>
                    <?php endif; ?>
                </a>
                <div class="header-actions">
                    <?php if ($whatsapp): ?>
                    <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $whatsapp); ?>" target="_blank" class="btn-whatsapp">
                        <i class="fab fa-whatsapp"></i><span>WhatsApp</span>
                    </a>
                    <?php endif; ?>
                    <a href="admin/" class="btn-login"><i class="fas fa-lock"></i><span>Admin</span></a>
                </div>
            </div>
        </div>
    </header>

    <?php if (!empty($banners)): ?>
    <section class="hero-section">
        <?php if (count($banners) === 1): ?>
        <div class="hero-banner" style="background: <?php echo !empty($banners[0]['imagem']) ? 'url(' . uploads_url($banners[0]['imagem']) . ')' : 'linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%)'; ?>; background-size: cover; background-position: center; min-height: 320px; display: flex; align-items: center; position: relative;">
            <?php if (!empty($banners[0]['imagem'])): ?><div style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(15,23,42,0.7) 0%, rgba(30,58,95,0.8) 100%);"></div><?php endif; ?>
            <div class="container" style="position: relative; z-index: 1;">
                <div class="hero-content">
                    <h1><?php echo sanitize($banners[0]['titulo']); ?></h1>
                    <?php if ($banners[0]['subtitulo']): ?><p><?php echo sanitize($banners[0]['subtitulo']); ?></p><?php endif; ?>
                    <?php if ($banners[0]['link']): ?><a href="<?php echo sanitize($banners[0]['link']); ?>" class="btn btn-primary"><?php echo sanitize($banners[0]['texto_botao'] ?: 'Saiba Mais'); ?></a><?php endif; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="hero-slider" id="heroSlider">
            <?php foreach ($banners as $i => $banner): ?>
            <div class="hero-slide" data-slide="<?php echo $i; ?>" style="background: <?php echo !empty($banner['imagem']) ? 'url(' . uploads_url($banner['imagem']) . ')' : 'linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%)'; ?>; background-size: cover; background-position: center; <?php echo $i === 0 ? '' : 'display:none;'; ?>; position: relative; min-height: 320px; display: flex; align-items: center;">
                <?php if (!empty($banner['imagem'])): ?><div style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(15,23,42,0.7) 0%, rgba(30,58,95,0.8) 100%);"></div><?php endif; ?>
                <div class="container" style="position: relative; z-index: 1;">
                    <div class="hero-content">
                        <h1><?php echo sanitize($banner['titulo']); ?></h1>
                        <?php if ($banner['subtitulo']): ?><p><?php echo sanitize($banner['subtitulo']); ?></p><?php endif; ?>
                        <?php if ($banner['link']): ?><a href="<?php echo sanitize($banner['link']); ?>" class="btn btn-primary"><?php echo sanitize($banner['texto_botao'] ?: 'Saiba Mais'); ?></a><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (count($banners) > 1): ?>
            <div class="hero-dots">
                <?php foreach ($banners as $i => $b): ?>
                <span class="dot <?php echo $i === 0 ? 'active' : ''; ?>" data-slide="<?php echo $i; ?>"></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <main class="main-content">
        <div class="container">
            <div class="toolbar">
                <form class="search-form" method="GET" action="" style="width: 100%;">
                    <div class="search-input-wrap" style="flex: 1; max-width: none;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="busca" value="<?php echo sanitize($busca); ?>" placeholder="Buscar produtos..." class="search-input" style="width: 100%;">
                        <?php if ($categoria_id): ?><input type="hidden" name="categoria" value="<?php echo $categoria_id; ?>"><?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <?php if ($busca || $categoria_id): ?><a href="./" class="btn btn-secondary">Limpar</a><?php endif; ?>
                </form>
            </div>

            <div class="content-layout">
                <aside class="sidebar">
                    <div class="sidebar-card">
                        <h3><i class="fas fa-tags"></i> Categorias</h3>
                        <nav class="category-nav">
                            <a href="./" class="category-link <?php echo !$categoria_id ? 'active' : ''; ?>">
                                <i class="fas fa-th-large"></i><span>Todos os Produtos</span><span class="count"><?php echo $total; ?></span>
                            </a>
                            <?php foreach ($categorias as $cat): ?>
                            <a href="?categoria=<?php echo $cat['id']; ?>" class="category-link <?php echo $categoria_id == $cat['id'] ? 'active' : ''; ?>">
                                <?php if ($cat['icone']): ?><i class="fas <?php echo sanitize($cat['icone']); ?>"></i><?php else: ?><i class="fas fa-tag"></i><?php endif; ?>
                                <span><?php echo sanitize($cat['nome']); ?></span>
                            </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                </aside>

                <div class="products-area">
                    <div class="products-header">
                        <h2><?php echo sanitize($titulo_pagina); ?></h2>
                        <span class="results-count"><?php echo $total; ?> produto(s)</span>
                    </div>

                    <?php if (empty($produtos)): ?>
                    <div class="empty-state">
                        <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
                            <rect x="15" y="25" width="50" height="40" rx="6" stroke="#cbd5e1" stroke-width="2" fill="none"/>
                            <circle cx="40" cy="45" r="10" stroke="#cbd5e1" stroke-width="2" fill="none"/>
                            <path d="M30 20h20M35 15h10" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <h3>Nenhum produto encontrado</h3>
                        <p>Tente ajustar sua busca ou explore outras categorias.</p>
                    </div>
                    <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($produtos as $prod): 
                            $img = $prod['imagem_principal'] ? uploads_url($prod['imagem_principal']) : assets_url('images/no-image.jpg');
                            $imgs = $imagens_por_produto[$prod['id']] ?? [];
                            $tem_preco = $prod['preco'] > 0;
                            $tem_promo = $prod['preco_promocional'] && $prod['preco_promocional'] > 0;
                            
                            $estoque_class = '';
                            $estoque_text = '';
                            if ($prod['quantidade_estoque'] <= 0) {
                                $estoque_class = 'estoque-esgotado';
                                $estoque_text = 'Esgotado';
                            } elseif ($prod['quantidade_estoque'] <= $prod['estoque_minimo'] && $prod['estoque_minimo'] > 0) {
                                $estoque_class = 'estoque-baixo';
                                $estoque_text = 'Estoque Baixo';
                            }
                        ?>
                        <div class="product-card" data-id="<?php echo $prod['id']; ?>">
                            <div class="product-image" style="position: relative;">
                                <img src="<?php echo $img; ?>" alt="<?php echo sanitize($prod['nome']); ?>" loading="lazy" style="cursor: pointer;" onclick="abrirProduto(<?php echo $prod['id']; ?>)">
                                <?php if ($prod['destaque']): ?><span class="badge badge-destaque">Destaque</span><?php endif; ?>
                                <?php if ($mostrar_preco && $tem_promo): ?><span class="badge badge-promo">Promo</span><?php endif; ?>
                                <?php if ($mostrar_preco && $estoque_text): ?><span class="badge" style="background: <?php echo $estoque_class === 'estoque-esgotado' ? '#ef4444' : '#f59e0b'; ?>;"><?php echo $estoque_text; ?></span><?php endif; ?>
                                <button type="button" onclick="abrirProduto(<?php echo $prod['id']; ?>)" style="position: absolute; bottom: 8px; right: 8px; width: 36px; height: 36px; background: rgba(255,255,255,0.9); border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.15); color: #3b82f6; font-size: 0.875rem;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="product-info">
                                <?php if ($prod['categoria_nome']): ?><span class="product-category"><?php echo sanitize($prod['categoria_nome']); ?></span><?php endif; ?>
                                <h4 class="product-name" style="cursor: pointer;" onclick="abrirProduto(<?php echo $prod['id']; ?>)"><?php echo sanitize($prod['nome']); ?></h4>
                                <?php if ($prod['descricao_curta']): ?><p class="product-desc"><?php echo sanitize($prod['descricao_curta']); ?></p><?php endif; ?>
                                <div class="product-footer">
                                    <div class="product-price">
                                        <?php if ($mostrar_preco && $tem_preco): ?>
                                            <?php if ($tem_promo): ?>
                                            <span class="price-old"><?php echo format_currency((float)$prod['preco']); ?></span>
                                            <span class="price-current"><?php echo format_currency((float)$prod['preco_promocional']); ?></span>
                                            <?php else: ?>
                                            <span class="price-current"><?php echo format_currency((float)$prod['preco']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="price-consult">Sob consulta</span>
                                        <?php endif; ?>
                                    </div>
<<<<<<< HEAD
                                    <button type="button" class="btn-add-cart" onclick="addToCart(<?php echo $prod['id']; ?>, '<?php echo htmlspecialchars(addslashes($prod['nome']), ENT_QUOTES, 'UTF-8'); ?>', <?php echo $mostrar_preco ? ($tem_promo ? $prod['preco_promocional'] : ($tem_preco ? $prod['preco'] : 0)) : 0; ?>)">
=======
                                    <button type="button" class="btn-add-cart" onclick="addToCart(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['nome']); ?>', <?php echo $mostrar_preco ? ($tem_promo ? $prod['preco_promocional'] : ($tem_preco ? $prod['preco'] : 0)) : 0; ?>)">
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination-wrap">
                        <?php 
                        $base_params = [];
                        if ($busca) $base_params['busca'] = $busca;
                        if ($categoria_id) $base_params['categoria'] = $categoria_id;
                        echo pagination_links($pagination, './', $base_params); 
                        ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

<<<<<<< HEAD
=======
    <!-- MODAL PRODUTO -->
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
    <div class="modal-produto-overlay" id="modalProdutoOverlay" onclick="fecharProduto(event)">
        <div class="modal-produto" id="modalProduto" onclick="event.stopPropagation()">
            <button class="modal-produto-close" onclick="fecharProduto()"><i class="fas fa-times"></i></button>
            <div id="modalProdutoContent">
                <?php if ($produto_modal): ?>
                <div class="modal-produto-grid">
                    <div class="modal-produto-imagens">
                        <img id="imgPrincipal" src="<?php echo $produto_modal['imagem_principal'] ? uploads_url($produto_modal['imagem_principal']) : assets_url('images/no-image.jpg'); ?>" alt="" class="modal-produto-img-principal">
                        <?php if (!empty($produto_imagens_modal)): ?>
                        <div class="modal-produto-galeria">
                            <?php if ($produto_modal['imagem_principal']): ?>
                            <img src="<?php echo uploads_url($produto_modal['imagem_principal']); ?>" class="active" onclick="document.getElementById('imgPrincipal').src=this.src">
                            <?php endif; ?>
                            <?php foreach ($produto_imagens_modal as $img): ?>
                            <img src="<?php echo uploads_url($img['imagem']); ?>" onclick="document.getElementById('imgPrincipal').src=this.src">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-produto-info">
                        <?php if ($produto_modal['categoria_nome']): ?><div class="modal-produto-categoria"><?php echo sanitize($produto_modal['categoria_nome']); ?></div><?php endif; ?>
                        <h3 class="modal-produto-nome"><?php echo sanitize($produto_modal['nome']); ?></h3>
                        <?php if ($produto_modal['descricao_completa']): ?><p class="modal-produto-desc"><?php echo sanitize($produto_modal['descricao_completa']); ?></p><?php endif; ?>
                        
                        <div class="modal-produto-preco">
                            <?php if ($mostrar_preco && $produto_modal['preco_promocional'] && $produto_modal['preco_promocional'] > 0): ?>
                            <span class="old"><?php echo format_currency((float)$produto_modal['preco']); ?></span><br>
                            <span class="current"><?php echo format_currency((float)$produto_modal['preco_promocional']); ?></span>
                            <?php elseif ($mostrar_preco && $produto_modal['preco'] > 0): ?>
                            <span class="current"><?php echo format_currency((float)$produto_modal['preco']); ?></span>
                            <?php else: ?>
                            <span class="consulta">Sob consulta</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php 
                        $estoque_modal = '';
                        $estoque_modal_class = '';
                        if ($produto_modal['quantidade_estoque'] <= 0) {
                            $estoque_modal = 'Esgotado';
                            $estoque_modal_class = 'estoque-esgotado';
                        } elseif ($produto_modal['quantidade_estoque'] <= $produto_modal['estoque_minimo'] && $produto_modal['estoque_minimo'] > 0) {
                            $estoque_modal = 'Estoque Baixo - ' . $produto_modal['quantidade_estoque'] . ' unidades';
                            $estoque_modal_class = 'estoque-baixo';
                        } else {
                            $estoque_modal = 'Em estoque - ' . $produto_modal['quantidade_estoque'] . ' unidades';
                            $estoque_modal_class = 'estoque-ok';
                        }
                        ?>
                        <?php if ($mostrar_preco): ?>
                        <div class="modal-produto-estoque <?php echo $estoque_modal_class; ?>">
                            <i class="fas <?php echo $estoque_modal_class === 'estoque-ok' ? 'fa-check-circle' : ($estoque_modal_class === 'estoque-baixo' ? 'fa-exclamation-triangle' : 'fa-times-circle'); ?>"></i>
                            <?php echo $estoque_modal; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="modal-produto-detalhes">
                            <?php if ($produto_modal['sku']): ?>
                            <div class="modal-produto-detalhe"><label>SKU</label><span><?php echo sanitize($produto_modal['sku']); ?></span></div>
                            <?php endif; ?>
                            <?php if ($produto_modal['unidade']): ?>
                            <div class="modal-produto-detalhe"><label>Unidade</label><span><?php echo sanitize($produto_modal['unidade']); ?></span></div>
                            <?php endif; ?>
                            <?php if ($produto_modal['peso']): ?>
                            <div class="modal-produto-detalhe"><label>Peso</label><span><?php echo $produto_modal['peso']; ?> kg</span></div>
                            <?php endif; ?>
                            <?php if ($produto_modal['tags']): ?>
                            <div class="modal-produto-detalhe"><label>Tags</label><span><?php echo sanitize($produto_modal['tags']); ?></span></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="modal-produto-btns">
<<<<<<< HEAD
                            <button class="btn btn-add-orcamento" onclick="addToCart(<?php echo $produto_modal['id']; ?>, '<?php echo htmlspecialchars(addslashes($produto_modal['nome']), ENT_QUOTES, 'UTF-8'); ?>', <?php echo $mostrar_preco ? (($produto_modal['preco_promocional'] && $produto_modal['preco_promocional'] > 0) ? $produto_modal['preco_promocional'] : ($produto_modal['preco'] > 0 ? $produto_modal['preco'] : 0)) : 0; ?>); fecharProduto(); toggleCart();">
=======
                            <button class="btn btn-add-orcamento" onclick="addToCart(<?php echo $produto_modal['id']; ?>, '<?php echo addslashes($produto_modal['nome']); ?>', <?php echo $mostrar_preco ? (($produto_modal['preco_promocional'] && $produto_modal['preco_promocional'] > 0) ? $produto_modal['preco_promocional'] : ($produto_modal['preco'] > 0 ? $produto_modal['preco'] : 0)) : 0; ?>); fecharProduto(); toggleCart();">
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
                                <i class="fas fa-plus"></i> Adicionar ao Orcamento
                            </button>
                            <?php if ($whatsapp): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $whatsapp); ?>?text=Ola!%20Tenho%20interesse%20no%20produto:%20<?php echo urlencode($produto_modal['nome']); ?>" target="_blank" class="btn btn-wp-produto">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div style="padding: 40px; text-align: center; color: #94a3b8;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 12px;"></i>
                    <p>Carregando produto...</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<<<<<<< HEAD
=======
    <!-- BOTÕES FLUTUANTES MOBILE -->
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
    <div class="mobile-float-bar">
        <button type="button" class="mobile-float-btn mobile-float-categoria" onclick="toggleMobileSidebar()">
            <i class="fas fa-th-large"></i>
            <span>Categoria</span>
        </button>
        <button type="button" class="mobile-float-btn mobile-float-sacola" onclick="toggleCart()">
            <i class="fas fa-shopping-bag"></i>
            <span>Sacola</span>
            <span class="mobile-float-count" id="mobileCartCount">0</span>
        </button>
        <?php if ($whatsapp): ?>
        <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $whatsapp); ?>" target="_blank" class="mobile-float-btn mobile-float-whatsapp">
            <i class="fab fa-whatsapp"></i>
            <span>WhatsApp</span>
        </a>
        <?php endif; ?>
    </div>

    <style>
    /* Barra flutuante mobile */
    .mobile-float-bar {
        display: none;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
        z-index: 1000;
        padding: 8px 16px;
        justify-content: space-around;
        align-items: center;
        gap: 8px;
    }
    .mobile-float-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        padding: 8px 16px;
        border-radius: 12px;
        border: none;
        color: white;
        font-size: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        flex: 1;
        min-width: 0;
        position: relative;
    }
    .mobile-float-btn i {
        font-size: 1.25rem;
    }
    .mobile-float-btn span {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    .mobile-float-categoria { background: #ef4444; }
    .mobile-float-categoria:hover { background: #dc2626; }
    .mobile-float-sacola { background: #3b82f6; }
    .mobile-float-sacola:hover { background: #2563eb; }
    .mobile-float-whatsapp { background: #22c55e; }
    .mobile-float-whatsapp:hover { background: #16a34a; }
    .mobile-float-count {
        position: absolute;
        top: -4px;
        right: 8px;
        background: #ef4444;
        color: white;
        font-size: 0.625rem;
        font-weight: 700;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @media (max-width: 768px) {
        .mobile-float-bar { display: flex; }
        .whatsapp-float { display: none !important; }
        .cart-toggle { display: none !important; }
        body { padding-bottom: 80px; }
    }
    </style>

    <script>
<<<<<<< HEAD
=======
    // Sincronizar contador do carrinho mobile
    function updateMobileCartCount() {
        const count = cart.reduce((sum, item) => sum + item.qtd, 0);
        const el = document.getElementById('mobileCartCount');
        if (el) {
            el.textContent = count;
            el.style.display = count > 0 ? 'flex' : 'none';
        }
    }
    // Sobrescrever updateCartUI para incluir mobile
    const originalUpdateCartUI = updateCartUI;
    updateCartUI = function() {
        originalUpdateCartUI();
        updateMobileCartCount();
    };

>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
    // Toggle sidebar mobile
    function toggleMobileSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.scrollIntoView({behavior:'smooth'});
            // Destacar visualmente
            sidebar.style.boxShadow = '0 0 0 3px #3b82f6';
            setTimeout(() => { sidebar.style.boxShadow = ''; }, 1500);
        }
    }
    </script>

<<<<<<< HEAD
=======
    <!-- CART -->
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
    <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h3><i class="fas fa-shopping-bag"></i> Meu Orcamento</h3>
            <button class="cart-close" onclick="toggleCart()"><i class="fas fa-times"></i></button>
        </div>
        <div class="cart-body" id="cartBody">
            <div class="cart-empty" id="cartEmpty">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                    <rect x="12" y="20" width="40" height="32" rx="6" stroke="#cbd5e1" stroke-width="2" fill="none"/>
                    <circle cx="32" cy="40" r="8" stroke="#cbd5e1" stroke-width="2" fill="none"/>
                    <path d="M24 16h16M28 12h8" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <p>Seu orcamento esta vazio</p>
                <span>Adicione produtos para solicitar um orcamento</span>
            </div>
            <div class="cart-items" id="cartItems"></div>
        </div>
        <div class="cart-footer" id="cartFooter" style="display:none;">
            <div class="cart-total"><span>Total:</span><strong id="cartTotal">R$ 0,00</strong></div>
            <button class="btn btn-primary btn-block" onclick="openOrcamentoModal()"><i class="fab fa-whatsapp"></i> Solicitar Orcamento</button>
            <button class="btn btn-text btn-block" onclick="clearCart()">Limpar Orcamento</button>
        </div>
    </div>

    <button class="cart-toggle" id="cartToggle" onclick="toggleCart()">
        <i class="fas fa-shopping-bag"></i><span class="cart-count" id="cartCount">0</span>
    </button>

<<<<<<< HEAD
=======
    <!-- ORCAMENTO MODAL -->
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
    <div class="modal-overlay" id="modalOverlay"></div>
    <div class="modal" id="orcamentoModal">
        <div class="modal-header">
            <h3><i class="fab fa-whatsapp"></i> Solicitar Orcamento</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="orcamentoForm" onsubmit="submitOrcamento(event)">
                <div class="form-group"><label for="orc_nome">Nome completo *</label><input type="text" id="orc_nome" name="nome" required></div>
                <div class="form-row">
                    <div class="form-group"><label for="orc_email">E-mail *</label><input type="email" id="orc_email" name="email" required></div>
                    <div class="form-group"><label for="orc_telefone">Telefone / WhatsApp *</label><input type="tel" id="orc_telefone" name="telefone" required></div>
                </div>
                <div class="form-group"><label for="orc_cpf">CPF/CNPJ</label><input type="text" id="orc_cpf" name="cpf_cnpj"></div>
                <div class="form-group"><label for="orc_obs">Observacoes</label><textarea id="orc_obs" name="observacoes" rows="3"></textarea></div>
                <div class="form-group">
                    <label>Forma de contato preferida</label>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="tipo_contato" value="whatsapp" checked><i class="fab fa-whatsapp"></i> WhatsApp</label>
                        <label class="radio-label"><input type="radio" name="tipo_contato" value="email"><i class="fas fa-envelope"></i> E-mail</label>
                        <label class="radio-label"><input type="radio" name="tipo_contato" value="telefone"><i class="fas fa-phone"></i> Telefone</label>
                    </div>
                </div>
                <div class="cart-summary" id="cartSummary"></div>
                <button type="submit" class="btn btn-primary btn-block btn-lg"><i class="fab fa-whatsapp"></i> Enviar Orcamento</button>
            </form>
            <div class="success-message" id="successMessage" style="display:none;">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                    <circle cx="32" cy="32" r="28" stroke="#10b981" stroke-width="2" fill="none"/>
                    <path d="M22 32l7 7 13-13" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
                <h3>Orcamento enviado com sucesso!</h3>
<<<<<<< HEAD
                <p>Entraremos em contato em breve pelo WhatsApp.</p>
=======
                <p>Entraremos em contato em breve.</p>
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
                <button class="btn btn-secondary" onclick="closeModal()">Fechar</button>
            </div>
        </div>
    </div>

<<<<<<< HEAD
=======
    <!-- CONTATO -->
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
    <section class="contact-section" style="background: #f8fafc; padding: 60px 0; border-top: 1px solid #e2e8f0;">
        <div class="container">
            <div style="text-align: center; margin-bottom: 40px;">
                <h2 style="font-size: 1.75rem; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Entre em Contato</h2>
                <p style="color: #64748b; font-size: 1rem;">Solicite um orcamento ou tire suas duvidas conosco.</p>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin: 0 auto;">
                <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: #0f172a; margin-bottom: 20px;"><i class="fas fa-building" style="color: #3b82f6; margin-right: 8px;"></i> Nossos Dados</h3>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: #dbeafe; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-building" style="color: #3b82f6;"></i>
                            </div>
                            <div>
                                <strong style="color: #0f172a; font-size: 0.9375rem;"><?php echo sanitize($site_name); ?></strong>
                                <p style="color: #64748b; font-size: 0.875rem; margin-top: 2px;"><?php echo sanitize($site_description); ?></p>
                            </div>
                        </div>
                        <?php if ($endereco): ?>
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: #dbeafe; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-map-marker-alt" style="color: #3b82f6;"></i></div>
                            <div><strong style="color: #0f172a; font-size: 0.9375rem;">Endereco</strong><p style="color: #64748b; font-size: 0.875rem; margin-top: 2px;"><?php echo sanitize($endereco); ?></p></div>
                        </div>
                        <?php endif; ?>
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: #dbeafe; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-clock" style="color: #3b82f6;"></i></div>
                            <div><strong style="color: #0f172a; font-size: 0.9375rem;">Horario</strong><p style="color: #64748b; font-size: 0.875rem; margin-top: 2px;"><?php echo sanitize($horario); ?></p></div>
                        </div>
                        <?php if ($telefone): ?>
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: #dbeafe; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-phone" style="color: #3b82f6;"></i></div>
                            <div><strong style="color: #0f172a; font-size: 0.9375rem;">Telefone</strong><p style="color: #64748b; font-size: 0.875rem; margin-top: 2px;"><?php echo sanitize($telefone); ?></p></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($site_email): ?>
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: #dbeafe; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-envelope" style="color: #3b82f6;"></i></div>
                            <div><strong style="color: #0f172a; font-size: 0.9375rem;">E-mail</strong><p style="color: #64748b; font-size: 0.875rem; margin-top: 2px;"><?php echo sanitize($site_email); ?></p></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: #0f172a; margin-bottom: 20px;"><i class="fas fa-comments" style="color: #3b82f6; margin-right: 8px;"></i> Fale Conosco</h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php if ($whatsapp): ?>
                        <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $whatsapp); ?>" target="_blank" style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 14px 20px; background: #22c55e; color: white; border-radius: 10px; text-decoration: none; font-weight: 600;">
                            <i class="fab fa-whatsapp" style="font-size: 1.25rem;"></i>Conversar no WhatsApp
                        </a>
                        <?php endif; ?>
                        <?php if ($site_email): ?>
                        <a href="mailto:<?php echo sanitize($site_email); ?>" style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 14px 20px; background: #4f46e5; color: white; border-radius: 10px; text-decoration: none; font-weight: 600;">
                            <i class="fas fa-envelope" style="font-size: 1.25rem;"></i>Enviar E-mail
                        </a>
                        <?php endif; ?>
                        <button onclick="toggleCart()" style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 14px 20px; background: #f97316; color: white; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 1rem;">
                            <i class="fas fa-file-invoice-dollar" style="font-size: 1.25rem;"></i>Solicitar Orcamento
                        </button>
                    </div>
                </div>
                <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: #0f172a; margin-bottom: 20px;"><i class="fas fa-map-marked-alt" style="color: #3b82f6; margin-right: 8px;"></i> Localizacao</h3>
                    <div style="width: 100%; height: 200px; background: #e2e8f0; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                        <div style="text-align: center; color: #94a3b8;"><i class="fas fa-map-marker-alt" style="font-size: 2rem; margin-bottom: 8px;"></i><p style="font-size: 0.875rem;">Mapa</p></div>
                    </div>
                    <?php if ($endereco): ?><p style="color: #64748b; font-size: 0.875rem; margin-bottom: 12px;"><i class="fas fa-map-pin" style="margin-right: 6px; color: #3b82f6;"></i><?php echo sanitize($endereco); ?></p><?php endif; ?>
                    <a href="https://maps.google.com/?q=<?php echo urlencode($endereco ?: $site_name); ?>" target="_blank" style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px 20px; background: #2563eb; color: white; border-radius: 10px; text-decoration: none; font-weight: 600;">
                        <i class="fab fa-google" style="font-size: 1rem;"></i>Abrir no Google Maps
                    </a>
                </div>
            </div>
        </div>
    </section>

<<<<<<< HEAD
=======
    <!-- FOOTER -->
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
    <footer class="site-footer" style="background: #1e293b; color: #94a3b8; padding: 0;">
        <div style="padding: 48px 0 32px;">
            <div class="container">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 32px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
                            <svg width="32" height="32" viewBox="0 0 36 36" fill="none">
                                <rect width="36" height="36" rx="9" fill="#3b82f6"/>
                                <path d="M10 24V14l8-5 8 5v10H10z" stroke="white" stroke-width="2" fill="none"/>
                                <circle cx="18" cy="19" r="2.5" fill="white"/>
                            </svg>
                            <span style="color: white; font-weight: 700; font-size: 1.125rem;"><?php echo sanitize($site_name); ?></span>
                        </div>
                        <p style="font-size: 0.875rem; line-height: 1.6; margin-bottom: 16px;"><?php echo sanitize($site_description); ?></p>
                        <div style="display: flex; gap: 10px;">
                            <?php if ($facebook_url): ?><a href="<?php echo sanitize($facebook_url); ?>" target="_blank" style="width: 36px; height: 36px; background: #334155; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none;"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
                            <?php if ($instagram_url): ?><a href="<?php echo sanitize($instagram_url); ?>" target="_blank" style="width: 36px; height: 36px; background: #334155; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none;"><i class="fab fa-instagram"></i></a><?php endif; ?>
                            <?php if ($linkedin_url): ?><a href="<?php echo sanitize($linkedin_url); ?>" target="_blank" style="width: 36px; height: 36px; background: #334155; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none;"><i class="fab fa-linkedin-in"></i></a><?php endif; ?>
                            <?php if ($youtube_url): ?><a href="<?php echo sanitize($youtube_url); ?>" target="_blank" style="width: 36px; height: 36px; background: #334155; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none;"><i class="fab fa-youtube"></i></a><?php endif; ?>
                            <?php if ($tiktok_url): ?><a href="<?php echo sanitize($tiktok_url); ?>" target="_blank" style="width: 36px; height: 36px; background: #334155; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none;"><i class="fab fa-tiktok"></i></a><?php endif; ?>
                            <?php if ($whatsapp): ?><a href="https://wa.me/<?php echo preg_replace('/\D/', '', $whatsapp); ?>" target="_blank" style="width: 36px; height: 36px; background: #334155; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none;"><i class="fab fa-whatsapp"></i></a><?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <h4 style="color: white; font-size: 0.9375rem; font-weight: 600; margin-bottom: 16px;">Links Rapidos</h4>
                        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px;">
                            <li><a href="./" style="color: #94a3b8; text-decoration: none; font-size: 0.875rem;">Inicio</a></li>
                            <li><a href="admin/" style="color: #94a3b8; text-decoration: none; font-size: 0.875rem;">Painel Admin</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 style="color: white; font-size: 0.9375rem; font-weight: 600; margin-bottom: 16px;">Contato</h4>
                        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px;">
                            <?php if ($telefone): ?><li style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem;"><i class="fas fa-phone" style="color: #3b82f6; width: 16px;"></i><?php echo sanitize($telefone); ?></li><?php endif; ?>
                            <?php if ($whatsapp): ?><li style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem;"><i class="fab fa-whatsapp" style="color: #22c55e; width: 16px;"></i><?php echo sanitize($whatsapp); ?></li><?php endif; ?>
                            <?php if ($site_email): ?><li style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem;"><i class="fas fa-envelope" style="color: #3b82f6; width: 16px;"></i><?php echo sanitize($site_email); ?></li><?php endif; ?>
                            <?php if ($endereco): ?><li style="display: flex; align-items: flex-start; gap: 8px; font-size: 0.875rem;"><i class="fas fa-map-marker-alt" style="color: #3b82f6; width: 16px; margin-top: 2px;"></i><?php echo sanitize($endereco); ?></li><?php endif; ?>
                        </ul>
                    </div>
                    <div>
                        <h4 style="color: white; font-size: 0.9375rem; font-weight: 600; margin-bottom: 16px;">Horario</h4>
                        <p style="font-size: 0.875rem; line-height: 1.6; margin-bottom: 12px;"><?php echo sanitize($horario); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div style="border-top: 1px solid #334155; padding: 20px 0;">
            <div class="container">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
<<<<<<< HEAD
                    <p style="margin: 0; font-size: 0.8125rem;">© <?php echo date('Y'); ?> <?php echo sanitize($site_name); ?>. Todos os direitos reservados.</p>
=======
                    <p style="margin: 0; font-size: 0.8125rem;">&copy; <?php echo date('Y'); ?> <?php echo sanitize($site_name); ?>. Todos os direitos reservados.</p>
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
                    <p style="margin: 0; font-size: 0.75rem; color: #64748b;">Desenvolvido com <i class="fas fa-heart" style="color: #ef4444;"></i> SiteCatalogo</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
    let cart = JSON.parse(localStorage.getItem('sitecatalogo_cart')) || [];
    
    function saveCart() {
        localStorage.setItem('sitecatalogo_cart', JSON.stringify(cart));
        updateCartUI();
    }
    
    function addToCart(id, nome, preco) {
        const existing = cart.find(item => item.id === id);
        if (existing) { existing.qtd++; } else { cart.push({ id, nome, preco, qtd: 1 }); }
        saveCart();
        const btn = event.target.closest('.btn-add-cart') || event.target.closest('.btn-add-orcamento');
        if (btn) {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => btn.innerHTML = original, 800);
        }
    }
    
    function removeFromCart(id) { cart = cart.filter(item => item.id !== id); saveCart(); }
    function updateQtd(id, delta) {
        const item = cart.find(i => i.id === id);
        if (item) { item.qtd += delta; if (item.qtd <= 0) removeFromCart(id); else saveCart(); }
    }
    function clearCart() { cart = []; saveCart(); }
    
    function updateCartUI() {
        const count = cart.reduce((sum, item) => sum + item.qtd, 0);
        document.getElementById('cartCount').textContent = count;
<<<<<<< HEAD
        
        // Correcao: O contador mobile agora eh atualizado aqui dentro com seguranca
        const mobileEl = document.getElementById('mobileCartCount');
        if (mobileEl) {
            mobileEl.textContent = count;
            mobileEl.style.display = count > 0 ? 'flex' : 'none';
        }
        
=======
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
        const emptyEl = document.getElementById('cartEmpty');
        const itemsEl = document.getElementById('cartItems');
        const footerEl = document.getElementById('cartFooter');
        if (cart.length === 0) {
            emptyEl.style.display = 'flex'; itemsEl.style.display = 'none'; footerEl.style.display = 'none'; return;
        }
        emptyEl.style.display = 'none'; itemsEl.style.display = 'block'; footerEl.style.display = 'block';
        let total = 0;
        itemsEl.innerHTML = cart.map(item => {
            const subtotal = item.preco * item.qtd; total += subtotal;
            return `<div class="cart-item"><div class="cart-item-info"><span class="cart-item-name">${escapeHtml(item.nome)}</span>${item.preco > 0 ? `<span class="cart-item-price">R$ ${item.preco.toFixed(2).replace('.', ',')}</span>` : ''}</div><div class="cart-item-actions"><button onclick="updateQtd(${item.id}, -1)"><i class="fas fa-minus"></i></button><span>${item.qtd}</span><button onclick="updateQtd(${item.id}, 1)"><i class="fas fa-plus"></i></button><button onclick="removeFromCart(${item.id})" class="btn-remove"><i class="fas fa-trash"></i></button></div></div>`;
        }).join('');
        document.getElementById('cartTotal').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    }
    
    function toggleCart() {
        document.getElementById('cartSidebar').classList.toggle('open');
        document.getElementById('cartOverlay').classList.toggle('open');
    }
    function openOrcamentoModal() { closeCartUI(); document.getElementById('modalOverlay').classList.add('open'); document.getElementById('orcamentoModal').classList.add('open'); let total = 0; const summary = cart.map(item => { const sub = item.preco * item.qtd; total += sub; return `<p>${item.qtd}x ${escapeHtml(item.nome)} ${item.preco > 0 ? '- R$ ' + sub.toFixed(2).replace('.', ',') : ''}</p>`; }).join(''); document.getElementById('cartSummary').innerHTML = summary + `<hr><p><strong>Total: R$ ${total.toFixed(2).replace('.', ',')}</strong></p>`; }
    function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); document.getElementById('orcamentoModal').classList.remove('open'); document.getElementById('orcamentoForm').style.display = 'block'; document.getElementById('successMessage').style.display = 'none'; }
    function closeCartUI() { document.getElementById('cartSidebar').classList.remove('open'); document.getElementById('cartOverlay').classList.remove('open'); }
    
    function submitOrcamento(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        data.append('itens', JSON.stringify(cart));
<<<<<<< HEAD
        
=======
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
        fetch('api/orcamento.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
<<<<<<< HEAD
                // Correcao: Alem de salvar na API, agora envia e abre direto no WhatsApp!
                let texto = "<?php echo addslashes($orcamento_msg); ?>\n\n";
                texto += "*DADOS DO CLIENTE:*\n";
                texto += "Nome: " + data.get('nome') + "\n";
                texto += "E-mail: " + data.get('email') + "\n";
                texto += "Telefone: " + data.get('telefone') + "\n\n";
                
                texto += "*PRODUTOS:*\n";
                let total = 0;
                cart.forEach(item => {
                    let sub = item.preco * item.qtd;
                    total += sub;
                    texto += `*${item.qtd}x* ${item.nome} `;
                    <?php if ($mostrar_preco): ?>
                    if (item.preco > 0) texto += `- R$ ${sub.toFixed(2).replace('.', ',')}\n`;
                    else texto += `\n`;
                    <?php else: ?>
                    texto += `\n`;
                    <?php endif; ?>
                });
                
                <?php if ($mostrar_preco): ?>
                if (total > 0) texto += `\n*Total Estimado: R$ ${total.toFixed(2).replace('.', ',')}*`;
                <?php endif; ?>
                
                let num = "<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>";
                if (num && data.get('tipo_contato') === 'whatsapp') {
                    let url = `https://wa.me/55${num}?text=${encodeURIComponent(texto)}`;
                    window.open(url, '_blank');
                }

                form.style.display = 'none';
                document.getElementById('successMessage').style.display = 'block';
                clearCart();
            } else { 
                alert('Erro: ' + (resp.message || 'Tente novamente')); 
            }
        })
        .catch(err => { alert('Erro ao enviar. Verifique a conexao ou a configuracao da sua API.'); });
=======
                form.style.display = 'none';
                document.getElementById('successMessage').style.display = 'block';
                clearCart();
            } else { alert('Erro: ' + (resp.message || 'Tente novamente')); }
        })
        .catch(err => { alert('Erro ao enviar. Tente novamente.'); });
>>>>>>> 8561693cd0ec14eb8341364e3af39ea63aae5359
    }
    
    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    
    // MODAL PRODUTO
    function abrirProduto(id) {
        document.getElementById('modalProdutoOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
        
        fetch('?produto_id=' + id)
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const content = doc.getElementById('modalProdutoContent');
            if (content) {
                document.getElementById('modalProdutoContent').innerHTML = content.innerHTML;
            }
        })
        .catch(() => {
            document.getElementById('modalProdutoContent').innerHTML = '<div style="padding: 40px; text-align: center; color: #ef4444;"><i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 12px;"></i><p>Erro ao carregar produto.</p></div>';
        });
    }
    
    function fecharProduto(e) {
        if (e && e.target !== document.getElementById('modalProdutoOverlay')) return;
        document.getElementById('modalProdutoOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }
    
    // SLIDER
    let currentSlide = 0;
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.hero-dots .dot');
    if (slides.length > 1) {
        setInterval(() => {
            slides[currentSlide].style.display = 'none';
            dots[currentSlide]?.classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].style.display = 'block';
            dots[currentSlide]?.classList.add('active');
        }, 5000);
        dots.forEach((dot, i) => {
            dot.addEventListener('click', () => {
                slides[currentSlide].style.display = 'none';
                dots[currentSlide]?.classList.remove('active');
                currentSlide = i;
                slides[currentSlide].style.display = 'block';
                dots[currentSlide]?.classList.add('active');
            });
        });
    }
    
    updateCartUI();
    </script>
</body>
</html>
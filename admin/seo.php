<?php
/**
 * SiteCatalogo - SEO e Integracoes
 */
require_once __DIR__ . '/includes/functions.php';
$page_title = 'SEO e Integracoes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['config'])) {
            foreach ($_POST['config'] as $chave => $valor) {
                set_config($chave, trim($valor));
            }
        }
        if (isset($_POST['seo'])) {
            foreach ($_POST['seo'] as $id => $dados) {
                $stmt = db()->prepare("UPDATE " . table('seo_pages') . " SET title = ?, description = ?, keywords = ?, og_title = ?, og_description = ?, robots = ?, ativo = ? WHERE id = ?");
                $stmt->execute([
                    trim($dados['title']),
                    trim($dados['description']),
                    trim($dados['keywords']),
                    trim($dados['og_title']),
                    trim($dados['og_description']),
                    $dados['robots'],
                    isset($dados['ativo']) ? 1 : 0,
                    $id
                ]);
            }
        }
        log_activity('update', 'seo', "SEO e integracoes atualizados");
        set_flash('success', 'Configuracoes salvas com sucesso!');
    } catch (Exception $e) {
        set_flash('error', 'Erro: ' . $e->getMessage());
    }
    header('Location: seo.php'); exit;
}

// SEO configs - incluindo todas as integracoes
$seo_configs = [
    'site_name' => get_config('site_name', 'SiteCatalogo'),
    'site_description' => get_config('site_description', ''),
    'google_analytics' => get_config('google_analytics', ''),
    'google_tag_manager' => get_config('google_tag_manager', ''),
    'google_verification' => get_config('google_verification', ''),
    'google_ads_id' => get_config('google_ads_id', ''),
    'facebook_pixel' => get_config('facebook_pixel', ''),
    'facebook_app_id' => get_config('facebook_app_id', ''),
    'facebook_domain_verification' => get_config('facebook_domain_verification', ''),
    'tiktok_pixel' => get_config('tiktok_pixel', ''),
    'linkedin_pixel' => get_config('linkedin_pixel', ''),
    'twitter_pixel' => get_config('twitter_pixel', ''),
    'pinterest_tag' => get_config('pinterest_tag', ''),
    'hotjar_id' => get_config('hotjar_id', ''),
    'crisp_website_id' => get_config('crisp_website_id', ''),
    'tawkto_id' => get_config('tawkto_id', ''),
    'whatsapp_number' => get_config('whatsapp', ''),
    'whatsapp_message' => get_config('orcamento_whatsapp_msg', ''),
];

// Paginas SEO
$paginas = db()->query("SELECT * FROM " . table('seo_pages') . " ORDER BY pagina")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.seo-card { margin-bottom: 20px; }
.seo-card .card-header h3 { display: flex; align-items: center; gap: 10px; }
.seo-card .card-header i { font-size: 1.25rem; }
.help-icon { 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    width: 20px; 
    height: 20px; 
    background: #3b82f6; 
    color: white; 
    border-radius: 50%; 
    font-size: 0.6875rem; 
    margin-left: 6px; 
    text-decoration: none; 
    cursor: pointer;
    transition: background 0.2s;
}
.help-icon:hover { background: #2563eb; }
.label-with-help { display: flex; align-items: center; }
.code-preview { 
    background: #1e293b; 
    color: #e2e8f0; 
    padding: 12px; 
    border-radius: 8px; 
    font-family: 'Fira Code', 'Consolas', monospace; 
    font-size: 0.8125rem; 
    margin-top: 8px; 
    overflow-x: auto; 
    white-space: pre-wrap; 
    word-break: break-all;
    line-height: 1.5;
}
.code-preview .tag { color: #f472b6; }
.code-preview .attr { color: #60a5fa; }
.code-preview .val { color: #a3e635; }
.code-preview .comment { color: #64748b; }
</style>

<div class="page-header">
    <h1><i class="fas fa-search"></i> SEO e Integracoes</h1>
</div>

<form method="POST">
    <!-- Geral -->
    <div class="card seo-card">
        <div class="card-header"><h3><i class="fas fa-globe"></i> Configuracoes Gerais</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="label-with-help">Titulo do Site</label>
                    <input type="text" name="config[site_name]" value="<?php echo sanitize($seo_configs['site_name']); ?>">
                </div>
                <div class="form-group">
                    <label class="label-with-help">Descricao Padrao</label>
                    <input type="text" name="config[site_description]" value="<?php echo sanitize($seo_configs['site_description']); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Google -->
    <div class="card seo-card">
        <div class="card-header"><h3><i class="fab fa-google"></i> Google</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="label-with-help">
                        Google Analytics ID
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[google_analytics]" value="<?php echo sanitize($seo_configs['google_analytics']); ?>" placeholder="G-XXXXXXXXXX ou UA-XXXXX-X">
                    <div class="code-preview"><span class="comment">&lt;!-- Google Analytics --&gt;</span>
<span class="tag">&lt;script</span> <span class="attr">async</span> <span class="attr">src</span>=<span class="val">"https://www.googletagmanager.com/gtag/js?id=<strong>SEU_ID</strong>"</span><span class="tag">&gt;&lt;/script&gt;</span>
<span class="tag">&lt;script&gt;</span>
  <span class="attr">window</span>.dataLayer = <span class="attr">window</span>.dataLayer || [];
  <span class="attr">function</span> gtag(){dataLayer.push(arguments);}
  gtag(<span class="val">'js'</span>, <span class="attr">new</span> Date());
  gtag(<span class="val">'config'</span>, <span class="val">'<strong>SEU_ID</strong>'</span>);
<span class="tag">&lt;/script&gt;</span></div>
                </div>
                <div class="form-group">
                    <label class="label-with-help">
                        Google Tag Manager
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[google_tag_manager]" value="<?php echo sanitize($seo_configs['google_tag_manager']); ?>" placeholder="GTM-XXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- Google Tag Manager --&gt;</span>
<span class="tag">&lt;script&gt;</span>(<span class="attr">function</span>(w,d,s,l,i){
  w[l]=w[l]||[];w[l].push({<span class="val">'gtm.start'</span>:
  <span class="attr">new</span> Date().getTime(),event:<span class="val">'gtm.js'</span>});
  <span class="attr">var</span> f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!=<span class="val">'dataLayer'</span>?<span class="val">'&l='+l</span>:<span class="val">''</span>;
  j.async=<span class="attr">true</span>;j.src=<span class="val">'https://www.googletagmanager.com/gtm.js?id='+i+dl</span>;
  f.parentNode.insertBefore(j,f);
})(<span class="attr">window</span>,<span class="attr">document</span>,<span class="val">'script'</span>,<span class="val">'dataLayer'</span>,<span class="val">'<strong>GTM-XXXXXX</strong>'</span>);<span class="tag">&lt;/script&gt;</span>
<span class="comment">&lt;noscript&gt;&lt;iframe src="https://www.googletagmanager.com/ns.html?id=<strong>GTM-XXXXXX</strong>"
height="0" width="0" style="display:none;visibility:hidden"&gt;&lt;/iframe&gt;&lt;/noscript&gt;</span></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="label-with-help">
                        Google Verification
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[google_verification]" value="<?php echo sanitize($seo_configs['google_verification']); ?>" placeholder="Codigo de verificacao">
                    <div class="code-preview"><span class="comment">&lt;!-- Google Site Verification --&gt;</span>
<span class="tag">&lt;meta</span> <span class="attr">name</span>=<span class="val">"google-site-verification"</span> <span class="attr">content</span>=<span class="val">"<strong>SEU_CODIGO</strong>"</span> <span class="tag">/&gt;</span></div>
                </div>
                <div class="form-group">
                    <label class="label-with-help">
                        Google Ads ID
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[google_ads_id]" value="<?php echo sanitize($seo_configs['google_ads_id']); ?>" placeholder="AW-XXXXXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- Google Ads (gtag.js) --&gt;</span>
<span class="tag">&lt;script</span> <span class="attr">async</span> <span class="attr">src</span>=<span class="val">"https://www.googletagmanager.com/gtag/js?id=<strong>AW-XXXXXXXXX</strong>"</span><span class="tag">&gt;&lt;/script&gt;</span>
<span class="tag">&lt;script&gt;</span>
  <span class="attr">window</span>.dataLayer = <span class="attr">window</span>.dataLayer || [];
  <span class="attr">function</span> gtag(){dataLayer.push(arguments);}
  gtag(<span class="val">'js'</span>, <span class="attr">new</span> Date());
  gtag(<span class="val">'config'</span>, <span class="val">'<strong>AW-XXXXXXXXX</strong>'</span>);
<span class="tag">&lt;/script&gt;</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Meta/Facebook -->
    <div class="card seo-card">
        <div class="card-header"><h3><i class="fab fa-facebook"></i> Meta / Facebook</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="label-with-help">
                        Facebook Pixel ID
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[facebook_pixel]" value="<?php echo sanitize($seo_configs['facebook_pixel']); ?>" placeholder="XXXXXXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- Meta Pixel Code --&gt;</span>
<span class="tag">&lt;script&gt;</span>
  !<span class="attr">function</span>(f,b,e,v,n,t,s)
  {<span class="attr">if</span>(f.fbq)<span class="attr">return</span>;n=f.fbq=<span class="attr">function</span>(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  <span class="attr">if</span>(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=<span class="val">'2.0'</span>;
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(<span class="attr">window</span>, <span class="attr">document</span>,<span class="val">'script'</span>,
  <span class="val">'https://connect.facebook.net/en_US/fbevents.js'</span>);
  fbq(<span class="val">'init'</span>, <span class="val">'<strong>SEU_PIXEL_ID</strong>'</span>);
  fbq(<span class="val">'track'</span>, <span class="val">'PageView'</span>);
<span class="tag">&lt;/script&gt;</span>
<span class="tag">&lt;noscript&gt;&lt;img</span> <span class="attr">height</span>=<span class="val">"1"</span> <span class="attr">width</span>=<span class="val">"1"</span> <span class="attr">style</span>=<span class="val">"display:none"</span>
  <span class="attr">src</span>=<span class="val">"https://www.facebook.com/tr?id=<strong>SEU_PIXEL_ID</strong>&ev=PageView&noscript=1"</span><span class="tag">/&gt;&lt;/noscript&gt;</span>
<span class="comment">&lt;!-- End Meta Pixel Code --&gt;</span></div>
                </div>
                <div class="form-group">
                    <label class="label-with-help">
                        Facebook App ID
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[facebook_app_id]" value="<?php echo sanitize($seo_configs['facebook_app_id']); ?>" placeholder="XXXXXXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- Facebook App ID (Open Graph) --&gt;</span>
<span class="tag">&lt;meta</span> <span class="attr">property</span>=<span class="val">"fb:app_id"</span> <span class="attr">content</span>=<span class="val">"<strong>SEU_APP_ID</strong>"</span> <span class="tag">/&gt;</span></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="label-with-help">
                        Domain Verification
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[facebook_domain_verification]" value="<?php echo sanitize($seo_configs['facebook_domain_verification']); ?>" placeholder="Codigo de verificacao">
                    <div class="code-preview"><span class="comment">&lt;!-- Facebook Domain Verification --&gt;</span>
<span class="tag">&lt;meta</span> <span class="attr">name</span>=<span class="val">"facebook-domain-verification"</span> <span class="attr">content</span>=<span class="val">"<strong>SEU_CODIGO</strong>"</span> <span class="tag">/&gt;</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- TikTok -->
    <div class="card seo-card">
        <div class="card-header"><h3><i class="fab fa-tiktok"></i> TikTok</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="label-with-help">
                        TikTok Pixel ID
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[tiktok_pixel]" value="<?php echo sanitize($seo_configs['tiktok_pixel']); ?>" placeholder="XXXXXXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- TikTok Pixel Code --&gt;</span>
<span class="tag">&lt;script&gt;</span>
  !<span class="attr">function</span> (w, d, t) {
    w.TiktokAnalyticsObject=t;<span class="attr">var</span> ttq=w[t]=w[t]||[];
    ttq.methods=[<span class="val">"page"</span>,<span class="val">"track"</span>,<span class="val">"identify"</span>,<span class="val">"instances"</span>,<span class="val">"debug"</span>,<span class="val">"on"</span>,<span class="val">"off"</span>,<span class="val">"once"</span>,<span class="val">"ready"</span>,<span class="val">"alias"</span>,<span class="val">"group"</span>,<span class="val">"enableCookie"</span>,<span class="val">"disableCookie"</span>];
    ttq.setAndDefer=<span class="attr">function</span>(t,e){t[e]=<span class="attr">function</span>(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};
    <span class="attr">for</span>(<span class="attr">var</span> i=0;i&lt;ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);
    ttq.instance=<span class="attr">function</span>(t){<span class="attr">var</span> e=ttq._i[t]||[];<span class="attr">for</span>(<span class="attr">var</span> n=0;n&lt;ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);<span class="attr">return</span> e};
    ttq.load=<span class="attr">function</span>(e,n){<span class="attr">var</span> i=<span class="val">"https://analytics.tiktok.com/i18n/pixel/events.js"</span>;
    ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};
    <span class="attr">var</span> o=document.createElement(<span class="val">"script"</span>);o.type=<span class="val">"text/javascript"</span>,o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;
    <span class="attr">var</span> a=document.getElementsByTagName(<span class="val">"script"</span>)[0];a.parentNode.insertBefore(o,a)};
    ttq.load(<span class="val">'<strong>SEU_PIXEL_ID</strong>'</span>);
    ttq.page();
  }(<span class="attr">window</span>, <span class="attr">document</span>, <span class="val">'ttq'</span>);
<span class="tag">&lt;/script&gt;</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Outras Redes -->
    <div class="card seo-card">
        <div class="card-header"><h3><i class="fas fa-share-alt"></i> Outras Redes Sociais</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="label-with-help">
                        LinkedIn Insight Tag
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[linkedin_pixel]" value="<?php echo sanitize($seo_configs['linkedin_pixel']); ?>" placeholder="XXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- LinkedIn Insight Tag --&gt;</span>
<span class="tag">&lt;script</span> <span class="attr">type</span>=<span class="val">"text/javascript"</span><span class="tag">&gt;</span>
  _linkedin_partner_id = <span class="val">"<strong>SEU_PARTNER_ID</strong>"</span>;
  <span class="attr">window</span>._linkedin_data_partner_ids = <span class="attr">window</span>._linkedin_data_partner_ids || [];
  <span class="attr">window</span>._linkedin_data_partner_ids.push(_linkedin_partner_id);
<span class="tag">&lt;/script&gt;</span>
<span class="tag">&lt;script</span> <span class="attr">type</span>=<span class="val">"text/javascript"</span><span class="tag">&gt;</span>
  (<span class="attr">function</span>(l) {
    <span class="attr">if</span> (!l){<span class="attr">window</span>.lintrk=<span class="attr">function</span>(a,b){<span class="attr">window</span>.lintrk.q.push([a,b])};
    <span class="attr">window</span>.lintrk.q=[]}
    <span class="attr">var</span> s=document.getElementsByTagName(<span class="val">"script"</span>)[0];
    <span class="attr">var</span> b=document.createElement(<span class="val">"script"</span>);
    b.type=<span class="val">"text/javascript"</span>;b.async=<span class="attr">true</span>;
    b.src=<span class="val">"https://snap.licdn.com/li.lms-analytics/insight.min.js"</span>;
    s.parentNode.insertBefore(b,s);
  })(<span class="attr">window</span>.lintrk);
<span class="tag">&lt;/script&gt;</span>
<span class="tag">&lt;noscript&gt;</span>
  <span class="tag">&lt;img</span> <span class="attr">height</span>=<span class="val">"1"</span> <span class="attr">width</span>=<span class="val">"1"</span> <span class="attr">style</span>=<span class="val">"display:none;"</span> <span class="attr">alt</span>=<span class="val">""</span>
  <span class="attr">src</span>=<span class="val">"https://px.ads.linkedin.com/collect/?pid=<strong>SEU_PARTNER_ID</strong>&fmt=gif"</span> <span class="tag">/&gt;</span>
<span class="tag">&lt;/noscript&gt;</span></div>
                </div>
                <div class="form-group">
                    <label class="label-with-help">
                        Twitter Pixel
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[twitter_pixel]" value="<?php echo sanitize($seo_configs['twitter_pixel']); ?>" placeholder="XXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- Twitter conversion tracking base code --&gt;</span>
<span class="tag">&lt;script&gt;</span>
  !<span class="attr">function</span>(e,t,n,s,u,a){e.twq||(s=e.twq=<span class="attr">function</span>(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments);
  },s.version=<span class="val">'1.1'</span>,s.queue=[],u=t.createElement(n),u.async=!0,u.src=<span class="val">'https://static.ads-twitter.com/uwt.js'</span>,
  a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(<span class="attr">window</span>,<span class="attr">document</span>,<span class="val">'script'</span>);
  twq(<span class="val">'config'</span>,<span class="val">'<strong>SEU_PIXEL_ID</strong>'</span>);
<span class="tag">&lt;/script&gt;</span></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="label-with-help">
                        Pinterest Tag
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[pinterest_tag]" value="<?php echo sanitize($seo_configs['pinterest_tag']); ?>" placeholder="XXXXXXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- Pinterest Tag --&gt;</span>
<span class="tag">&lt;script&gt;</span>
  !<span class="attr">function</span>(e){<span class="attr">if</span>(!window.pintrk){window.pintrk=<span class="attr">function</span>(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};
  <span class="attr">var</span> n=window.pintrk;n.queue=[],n.version=<span class="val">"3.0"</span>;
  <span class="attr">var</span> t=document.createElement(<span class="val">"script"</span>);t.async=!0,t.src=e;
  <span class="attr">var</span> r=document.getElementsByTagName(<span class="val">"script"</span>)[0];r.parentNode.insertBefore(t,r)}}
  (<span class="val">"https://s.pinimg.com/ct/core.js"</span>);
  pintrk(<span class="val">'load'</span>, <span class="val">'<strong>SEU_TAG_ID</strong>'</span>);
  pintrk(<span class="val">'page'</span>);
<span class="tag">&lt;/script&gt;</span>
<span class="tag">&lt;noscript&gt;</span>
  <span class="tag">&lt;img</span> <span class="attr">height</span>=<span class="val">"1"</span> <span class="attr">width</span>=<span class="val">"1"</span> <span class="attr">style</span>=<span class="val">"display:none;"</span> <span class="attr">alt</span>=<span class="val">""</span>
  <span class="attr">src</span>=<span class="val">"https://ct.pinterest.com/v3/?tid=<strong>SEU_TAG_ID</strong>&noscript=1"</span> <span class="tag">/&gt;</span>
<span class="tag">&lt;/noscript&gt;</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat e Analytics -->
    <div class="card seo-card">
        <div class="card-header"><h3><i class="fas fa-comments"></i> Chat e Analytics</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="label-with-help">
                        Hotjar Site ID
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[hotjar_id]" value="<?php echo sanitize($seo_configs['hotjar_id']); ?>" placeholder="XXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- Hotjar Tracking Code --&gt;</span>
<span class="tag">&lt;script&gt;</span>
  (<span class="attr">function</span>(h,o,t,j,a,r){
    h.hj=h.hj||<span class="attr">function</span>(){(h.hj.q=h.hj.q||[]).push(arguments)};
    h._hjSettings={hjid:<span class="val"><strong>SEU_SITE_ID</strong></span>,hjsv:6};
    a=o.getElementsByTagName(<span class="val">'head'</span>)[0];
    r=o.createElement(<span class="val">'script'</span>);r.async=1;
    r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
    a.appendChild(r);
  })(<span class="attr">window</span>,<span class="attr">document</span>,<span class="val">'https://static.hotjar.com/c/hotjar-'</span>,<span class="val">'.js?sv='</span>);
<span class="tag">&lt;/script&gt;</span></div>
                </div>
                <div class="form-group">
                    <label class="label-with-help">
                        Crisp Website ID
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[crisp_website_id]" value="<?php echo sanitize($seo_configs['crisp_website_id']); ?>" placeholder="XXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- Crisp Chat --&gt;</span>
<span class="tag">&lt;script</span> <span class="attr">type</span>=<span class="val">"text/javascript"</span><span class="tag">&gt;</span>
  <span class="attr">window</span>.$crisp=[];<span class="attr">window</span>.CRISP_WEBSITE_ID=<span class="val">"<strong>SEU_WEBSITE_ID</strong>"</span>;
  (<span class="attr">function</span>(){d=document;s=d.createElement(<span class="val">"script"</span>);
  s.src=<span class="val">"https://client.crisp.chat/l.js"</span>;s.async=1;
  d.getElementsByTagName(<span class="val">"head"</span>)[0].appendChild(s);})();
<span class="tag">&lt;/script&gt;</span></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="label-with-help">
                        Tawk.to Property ID
                        <a href="https://www.luishenriquedesign.com.br" target="_blank" class="help-icon" title="Ver tutorial"><i class="fas fa-question"></i></a>
                    </label>
                    <input type="text" name="config[tawkto_id]" value="<?php echo sanitize($seo_configs['tawkto_id']); ?>" placeholder="XXXXXXXXX">
                    <div class="code-preview"><span class="comment">&lt;!-- Tawk.to Live Chat --&gt;</span>
<span class="tag">&lt;script</span> <span class="attr">type</span>=<span class="val">"text/javascript"</span><span class="tag">&gt;</span>
  <span class="attr">var</span> Tawk_API=Tawk_API||{}, Tawk_LoadStart=<span class="attr">new</span> Date();
  (<span class="attr">function</span>(){
    <span class="attr">var</span> s1=document.createElement(<span class="val">"script"</span>),s0=document.getElementsByTagName(<span class="val">"script"</span>)[0];
    s1.async=<span class="attr">true</span>;
    s1.src=<span class="val">'https://embed.tawk.to/<strong>SEU_PROPERTY_ID</strong>/default'</span>;
    s1.charset=<span class="val">'UTF-8'</span>;
    s1.setAttribute(<span class="val">'crossorigin'</span>,<span class="val">'*'</span>);
    s0.parentNode.insertBefore(s1,s0);
  })();
<span class="tag">&lt;/script&gt;</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Paginas -->
    <div class="card seo-card">
        <div class="card-header"><h3><i class="fas fa-file-alt"></i> Paginas</h3></div>
        <div class="card-body">
            <?php foreach ($paginas as $p): ?>
            <div style="border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <h4 style="margin:0;font-size:0.9375rem;"><?php echo ucfirst(sanitize($p['pagina'])); ?></h4>
                    <code style="font-size:0.75rem;color:var(--gray-400);"><?php echo sanitize($p['url'] ?? ''); ?></code>
                    <label class="form-check" style="margin-left:auto;"><input type="checkbox" name="seo[<?php echo $p['id']; ?>][ativo]" <?php echo checked($p['ativo'] == 1); ?>> Ativo</label>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Title</label><input type="text" name="seo[<?php echo $p['id']; ?>][title]" value="<?php echo sanitize($p['title'] ?? ''); ?>"></div>
                    <div class="form-group"><label>OG Title</label><input type="text" name="seo[<?php echo $p['id']; ?>][og_title]" value="<?php echo sanitize($p['og_title'] ?? ''); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Description</label><textarea name="seo[<?php echo $p['id']; ?>][description]" rows="2"><?php echo sanitize($p['description'] ?? ''); ?></textarea></div>
                    <div class="form-group"><label>OG Description</label><textarea name="seo[<?php echo $p['id']; ?>][og_description]" rows="2"><?php echo sanitize($p['og_description'] ?? ''); ?></textarea></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Keywords</label><input type="text" name="seo[<?php echo $p['id']; ?>][keywords]" value="<?php echo sanitize($p['keywords'] ?? ''); ?>" placeholder="palavra1, palavra2, palavra3"></div>
                    <div class="form-group"><label>Robots</label><select name="seo[<?php echo $p['id']; ?>][robots]"><option value="index,follow" <?php echo selected($p['robots'], 'index,follow'); ?>>Index, Follow</option><option value="noindex,follow" <?php echo selected($p['robots'], 'noindex,follow'); ?>>Noindex, Follow</option><option value="index,nofollow" <?php echo selected($p['robots'], 'index,nofollow'); ?>>Index, Nofollow</option><option value="noindex,nofollow" <?php echo selected($p['robots'], 'noindex,nofollow'); ?>>Noindex, Nofollow</option></select></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar SEO e Integracoes</button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
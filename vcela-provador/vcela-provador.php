<?php
/**
 * Plugin Name: Vcela Provador
 * Description: Provador virtual para lojas de roupa. Cada loja tem seu endereço (/slug), cadastro com aceite dos termos, lista de clientes e peças próprias.
 * Version: 1.0.0
 * Author: Divulgar Criações
 * Text Domain: vcela-provador
 */
if (!defined('ABSPATH')) exit;

define('VCP_DIR', __DIR__ . '/');
define('VCP_URL', plugin_dir_url(__FILE__));
const VCP_DB = 1;
const VCP_TERMOS = '2026-11-25';
const VCP_TAMS = ['PP', 'P', 'M', 'G', 'GG', 'XG'];

/* ------------------------------------------------------------------ dados */

function vcp_tab() { global $wpdb; return $wpdb->prefix . 'vcela_clientes'; }

/** Todas as lojas: [slug => [...]] */
function vcp_lojas() { $l = get_option('vcp_lojas', []); return is_array($l) ? $l : []; }
function vcp_loja($slug) { $l = vcp_lojas(); return $l[$slug] ?? null; }
function vcp_salvar_lojas($l) { update_option('vcp_lojas', $l, false); }

function vcp_padrao() {
	return ['nome' => '', 'logo' => '', 'cor1' => '#FF0066', 'cor2' => '#FF6699', 'acc' => '#E0115F', 'acc2' => '#FF5C95',
		'whatsapp' => '', 'instagram' => '', 'ativa' => 1, 'chave' => bin2hex(random_bytes(16)), 'pecas' => []];
}

register_activation_hook(__FILE__, 'vcp_instalar');
add_action('plugins_loaded', function () { if ((int) get_option('vcp_db') !== VCP_DB) vcp_instalar(); });
function vcp_instalar() {
	global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = vcp_tab(); $c = $wpdb->get_charset_collate();
	dbDelta("CREATE TABLE $t (
		id bigint unsigned NOT NULL AUTO_INCREMENT,
		loja varchar(40) NOT NULL,
		nome varchar(80) NOT NULL,
		whatsapp varchar(20) NOT NULL,
		tamanho varchar(4) NOT NULL DEFAULT '',
		novidades tinyint(1) NOT NULL DEFAULT 0,
		termos varchar(20) NOT NULL,
		aceite_em datetime NOT NULL,
		token char(32) NOT NULL,
		provas int unsigned NOT NULL DEFAULT 0,
		pecas text NULL,
		ultima datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY loja_whats (loja,whatsapp),
		KEY token (token)
	) $c;");
	update_option('vcp_db', VCP_DB, false);
	// Loja modelo: Vcela Store
	$l = vcp_lojas();
	if (!$l) {
		$l['vcelastore'] = array_merge(vcp_padrao(), ['nome' => 'Vcela Store', 'logo' => VCP_URL . 'app/vcela.svg',
			'whatsapp' => '5511951864697', 'instagram' => 'vcelastore']);
		vcp_salvar_lojas($l);
	}
}

/* ------------------------------------------------------------- páginas */

// Endereços: /{loja}  /{loja}/termos  /{loja}/clientes?k=CHAVE[&csv=1]
add_action('parse_request', function () {
	$p = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
	$home = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
	if ($home !== '' && strpos($p, $home) === 0) $p = trim(substr($p, strlen($home)), '/');
	if (!preg_match('#^([a-z0-9-]{2,40})(?:/(termos|clientes))?$#', $p, $m)) return;
	$loja = vcp_loja($m[1]);
	if (!$loja || empty($loja['ativa'])) return;
	$pag = $m[2] ?? '';
	nocache_headers(); header('X-Robots-Tag: noindex, nofollow');
	if ($pag === 'clientes') { vcp_painel($m[1], $loja); exit; }
	header('Content-Type: text/html; charset=utf-8');
	header('Permissions-Policy: camera=(self)');
	echo vcp_render($pag === 'termos' ? 'termos' : 'provador', $m[1], $loja);
	exit;
}, 1);

function vcp_render($tpl, $slug, $l) {
	$h = file_get_contents(VCP_DIR . 'app/' . $tpl . '.html');
	$base = home_url('/' . $slug . '/');
	$pecas = [];
	foreach ((array) $l['pecas'] as $p) if (!empty($p['img'])) $pecas[] = ['nome' => $p['nome'], 'img' => $p['img'], 'anc' => $p['anc'] ?? null];
	$cfg = ['slug' => $slug, 'nome' => $l['nome'], 'cor1' => $l['cor1'], 'whatsapp' => $l['whatsapp'], 'instagram' => $l['instagram'],
		'api' => rest_url('vcela/v1/'), 'termos' => VCP_TERMOS, 'pecas' => $pecas];
	$cont = [];
	if ($l['whatsapp']) $cont[] = 'pelo WhatsApp <a href="https://wa.me/' . esc_attr($l['whatsapp']) . '">' . esc_html(vcp_fone($l['whatsapp'])) . '</a>';
	if ($l['instagram']) $cont[] = 'pelo Instagram <a href="https://instagram.com/' . esc_attr($l['instagram']) . '">@' . esc_html($l['instagram']) . '</a>';
	$rep = [
		'{{NOME}}' => esc_html($l['nome']), '{{LOGO}}' => esc_url($l['logo'] ?: VCP_URL . 'app/vcela.svg'), '{{BASE}}' => esc_url($base),
		'{{COR1}}' => vcp_cor($l['cor1']), '{{COR2}}' => vcp_cor($l['cor2']), '{{ACC}}' => vcp_cor($l['acc']), '{{ACC2}}' => vcp_cor($l['acc2']),
		'{{CONFIG}}' => str_replace('<', '<', wp_json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
		'{{CONTATOS}}' => $cont ? implode(' ou ', $cont) : 'pelos canais da loja',
		'{{TERMOS_DATA}}' => esc_html(mysql2date('d/m/Y', VCP_TERMOS)),
	];
	return strtr($h, $rep);
}
function vcp_cor($c) { return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c) ? $c : '#E0115F'; }
function vcp_fone($w) { $d = substr($w, 2); return strlen($d) === 11 ? substr($d, 0, 2) . ' ' . substr($d, 2, 5) . '-' . substr($d, 7) : $d; }

/* ------------------------------------------------------------------ API */

add_action('rest_api_init', function () {
	register_rest_route('vcela/v1', '/cadastro', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'vcp_cadastro']);
	register_rest_route('vcela/v1', '/prova', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'vcp_prova']);
});

function vcp_cadastro(WP_REST_Request $r) {
	global $wpdb;
	$ipk = 'vcp_ip_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
	$n = (int) get_transient($ipk);
	if ($n >= 20) return new WP_Error('limite', 'Muitas tentativas. Tente mais tarde.', ['status' => 429]);
	set_transient($ipk, $n + 1, HOUR_IN_SECONDS);
	$slug = sanitize_key($r['loja']); $l = vcp_loja($slug);
	if (!$l || empty($l['ativa'])) return new WP_Error('loja', 'Loja inválida.', ['status' => 400]);
	$nome = trim(sanitize_text_field($r['nome']));
	$w = preg_replace('/\D/', '', (string) $r['whatsapp']);
	if (strlen($w) >= 10 && strlen($w) <= 11) $w = '55' . $w;
	$tam = in_array($r['tamanho'], VCP_TAMS, true) ? $r['tamanho'] : '';
	if (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) return new WP_Error('nome', 'Digite seu nome.', ['status' => 400]);
	if (strlen($w) < 12 || strlen($w) > 13) return new WP_Error('whats', 'WhatsApp inválido. Use DDD + número.', ['status' => 400]);
	if (empty($r['termos']) || $r['termos_versao'] !== VCP_TERMOS) return new WP_Error('termos', 'É preciso aceitar os termos.', ['status' => 400]);
	$t = vcp_tab(); $tok = bin2hex(random_bytes(16));
	$d = ['nome' => $nome, 'tamanho' => $tam, 'novidades' => empty($r['novidades']) ? 0 : 1, 'termos' => VCP_TERMOS, 'aceite_em' => current_time('mysql'), 'token' => $tok];
	$id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE loja=%s AND whatsapp=%s", $slug, $w));
	if ($id) $wpdb->update($t, $d, ['id' => $id]); else $wpdb->insert($t, $d + ['loja' => $slug, 'whatsapp' => $w]);
	return ['token' => $tok, 'nome' => $nome];
}

function vcp_prova(WP_REST_Request $r) {
	global $wpdb; $t = vcp_tab();
	$tok = preg_replace('/[^a-f0-9]/', '', (string) $r['token']);
	$c = strlen($tok) === 32 ? $wpdb->get_row($wpdb->prepare("SELECT id,pecas FROM $t WHERE token=%s", $tok)) : null;
	if (!$c) return new WP_Error('token', 'Cadastro não encontrado.', ['status' => 401]);
	$peca = mb_substr(sanitize_text_field($r['peca']), 0, 60);
	$p = json_decode((string) $c->pecas, true) ?: [];
	if ($peca !== '') $p[$peca] = ($p[$peca] ?? 0) + 1;
	$wpdb->query($wpdb->prepare("UPDATE $t SET provas=provas+1, pecas=%s, ultima=%s WHERE id=%d", wp_json_encode($p, JSON_UNESCAPED_UNICODE), current_time('mysql'), $c->id));
	return ['ok' => true];
}

/* ------------------------------------------------- lista da lojista */

function vcp_painel($slug, $l) {
	if (!hash_equals((string) $l['chave'], (string) ($_GET['k'] ?? ''))) { status_header(404); echo 'Página não encontrada.'; return; }
	global $wpdb; $t = vcp_tab();
	$rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE loja=%s ORDER BY COALESCE(ultima,aceite_em) DESC", $slug));
	if (!empty($_GET['csv'])) {
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=clientes-provador-' . $slug . '.csv');
		$o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
		fputcsv($o, ['Nome', 'WhatsApp', 'Tamanho', 'Aceita novidades', 'Provas', 'Peças provadas', 'Cadastro', 'Última prova'], ';');
		foreach ($rows as $c) { $p = json_decode((string) $c->pecas, true) ?: []; arsort($p);
			fputcsv($o, [$c->nome, $c->whatsapp, $c->tamanho, $c->novidades ? 'Sim' : 'Não', $c->provas, implode(', ', array_keys($p)), $c->aceite_em, $c->ultima], ';'); }
		return;
	}
	$tot = []; foreach ($rows as $c) foreach ((json_decode((string) $c->pecas, true) ?: []) as $pc => $q) $tot[$pc] = ($tot[$pc] ?? 0) + $q;
	arsort($tot);
	$np = array_sum(array_map(fn($c) => (int) $c->provas, $rows));
	$e = 'esc_html'; $acc = vcp_cor($l['acc']);
	header('Content-Type: text/html; charset=utf-8');
	echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Clientes do provador</title><style>:root{--bg:#F6F1EE;--fg:#2A1F24;--mut:#7A6B71;--card:#fff;--line:#E8DDD8;--acc:' . $acc . '}@media(prefers-color-scheme:dark){:root{--bg:#171114;--fg:#F3ECEF;--mut:#B3A4AA;--card:#221A1E;--line:#3A2E33;--acc:' . vcp_cor($l['acc2']) . '}}body{margin:0;background:var(--bg);color:var(--fg);font:15px/1.45 system-ui,-apple-system,"Segoe UI",Arial,sans-serif}.w{max-width:980px;margin:0 auto;padding:20px 16px}h1{margin:0 0 4px;font-size:24px}.mut{color:var(--mut)}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:16px 0}.k{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:12px}.k b{display:block;font-size:24px;font-variant-numeric:tabular-nums}.cli{display:grid;gap:10px}.c{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:12px 14px;display:grid;grid-template-columns:1fr auto;gap:6px 12px;align-items:center}.c small{color:var(--mut)}.wa{background:#25D366;color:#fff;text-decoration:none;border-radius:10px;padding:9px 12px;font-weight:700;white-space:nowrap}.bt{display:inline-block;margin-top:6px;color:var(--acc);font-weight:700}</style></head><body><div class="w">';
	echo '<h1>Clientes do provador · ' . $e($l['nome']) . '</h1><div class="mut">Quem se cadastrou e aceitou os termos. <a class="bt" href="?k=' . esc_attr($l['chave']) . '&csv=1">Baixar planilha</a></div>';
	echo '<div class="cards"><div class="k"><span class="mut">Clientes</span><b>' . count($rows) . '</b></div><div class="k"><span class="mut">Peças provadas</span><b>' . $np . '</b></div><div class="k"><span class="mut">Mais provada</span><b style="font-size:16px">' . $e($tot ? array_key_first($tot) : '—') . '</b></div></div>';
	if (!$rows) echo '<p class="mut">Ainda ninguém se cadastrou. Quando a primeira cliente usar o provador, ela aparece aqui.</p>';
	echo '<div class="cli">';
	foreach ($rows as $c) {
		$p = json_decode((string) $c->pecas, true) ?: []; arsort($p);
		$msg = rawurlencode('Oi ' . strtok($c->nome, ' ') . '! Aqui é da ' . $l['nome'] . '.');
		echo '<div class="c"><div><b>' . $e($c->nome) . '</b>' . ($c->tamanho ? ' · ' . $e($c->tamanho) : '') . '<br><small>' . ($p ? 'Provou: ' . $e(implode(', ', array_slice(array_keys($p), 0, 5))) : 'Ainda não provou') . ' · ' . ($c->novidades ? 'aceita novidades' : 'não quer novidades') . ' · ' . $e(mysql2date('d/m H:i', $c->ultima ?: $c->aceite_em)) . '</small></div>';
		echo '<a class="wa" target="_blank" rel="noopener" href="https://wa.me/' . esc_attr($c->whatsapp) . '?text=' . $msg . '">WhatsApp</a></div>';
	}
	echo '</div></div></body></html>';
}

/* ------------------------------------------------------ painel admin */

add_action('admin_menu', function () {
	add_menu_page('Vcela Provador', 'Vcela Provador', 'manage_options', 'vcela-lojas', 'vcp_admin', 'dashicons-format-image', 26);
});

add_action('admin_post_vcp_salvar', function () {
	if (!current_user_can('manage_options')) wp_die('Sem permissão.');
	check_admin_referer('vcp_salvar');
	$l = vcp_lojas();
	$orig = sanitize_key($_POST['orig'] ?? '');
	$slug = sanitize_title($_POST['slug'] ?? '');
	$slug = preg_replace('/[^a-z0-9-]/', '', $slug);
	$reserv = ['wp-admin', 'wp-json', 'wp-content', 'wp-includes', 'wp-login', 'feed', 'termos', 'clientes'];
	if (strlen($slug) < 2 || in_array($slug, $reserv, true) || ($slug !== $orig && isset($l[$slug]))) {
		wp_safe_redirect(add_query_arg(['page' => 'vcela-lojas', 'loja' => $orig, 'erro' => 'endereco'], admin_url('admin.php'))); exit;
	}
	$d = $orig && isset($l[$orig]) ? $l[$orig] : vcp_padrao();
	$w = preg_replace('/\D/', '', (string) ($_POST['whatsapp'] ?? '')); if (strlen($w) >= 10 && strlen($w) <= 11) $w = '55' . $w;
	$d['nome'] = sanitize_text_field($_POST['nome'] ?? '');
	$d['logo'] = esc_url_raw($_POST['logo'] ?? '');
	foreach (['cor1', 'cor2', 'acc', 'acc2'] as $k) $d[$k] = vcp_cor($_POST[$k] ?? '');
	$d['whatsapp'] = $w;
	$d['instagram'] = preg_replace('/[^a-zA-Z0-9._]/', '', ltrim((string) ($_POST['instagram'] ?? ''), '@'));
	$d['ativa'] = empty($_POST['ativa']) ? 0 : 1;
	$pecas = [];
	foreach ((array) ($_POST['pecas'] ?? []) as $p) {
		$img = esc_url_raw($p['img'] ?? ''); $nome = sanitize_text_field($p['nome'] ?? '');
		if ($img && $nome) $pecas[] = ['nome' => $nome, 'img' => $img];
	}
	$d['pecas'] = $pecas;
	if (!empty($_POST['nova_chave'])) $d['chave'] = bin2hex(random_bytes(16));
	if ($orig && $orig !== $slug) {
		unset($l[$orig]);
		global $wpdb; $wpdb->update(vcp_tab(), ['loja' => $slug], ['loja' => $orig]);
	}
	$l[$slug] = $d; vcp_salvar_lojas($l);
	wp_safe_redirect(add_query_arg(['page' => 'vcela-lojas', 'loja' => $slug, 'ok' => 1], admin_url('admin.php'))); exit;
});

function vcp_admin() {
	$l = vcp_lojas();
	$sel = isset($_GET['loja']) ? sanitize_key($_GET['loja']) : null;
	echo '<div class="wrap"><h1 class="wp-heading-inline">Vcela Provador</h1>';
	if ($sel === null) {
		echo ' <a class="page-title-action" href="' . esc_url(admin_url('admin.php?page=vcela-lojas&loja=')) . '">Nova loja</a><hr class="wp-header-end">';
		global $wpdb; $cont = $wpdb->get_results('SELECT loja, COUNT(*) n, SUM(provas) p FROM ' . vcp_tab() . ' GROUP BY loja', OBJECT_K);
		echo '<table class="widefat striped" style="max-width:980px;margin-top:12px"><thead><tr><th>Loja</th><th>Endereço do provador</th><th>Clientes</th><th>Provas</th><th>Situação</th></tr></thead><tbody>';
		if (!$l) echo '<tr><td colspan="5">Nenhuma loja ainda.</td></tr>';
		foreach ($l as $s => $x) {
			$u = home_url('/' . $s . '/');
			echo '<tr><td><a href="' . esc_url(admin_url('admin.php?page=vcela-lojas&loja=' . $s)) . '"><b>' . esc_html($x['nome']) . '</b></a></td><td><a href="' . esc_url($u) . '" target="_blank">' . esc_html($u) . '</a></td><td>' . (int) ($cont[$s]->n ?? 0) . '</td><td>' . (int) ($cont[$s]->p ?? 0) . '</td><td>' . ($x['ativa'] ? 'Ativa' : 'Pausada') . '</td></tr>';
		}
		echo '</tbody></table></div>';
		return;
	}
	wp_enqueue_media();
	$d = ($sel !== '' && isset($l[$sel])) ? $l[$sel] : vcp_padrao();
	echo '<hr class="wp-header-end">';
	if (!empty($_GET['ok'])) echo '<div class="notice notice-success"><p>Loja salva.</p></div>';
	if (!empty($_GET['erro'])) echo '<div class="notice notice-error"><p>Endereço inválido ou já usado por outra loja.</p></div>';
	echo '<p><a href="' . esc_url(admin_url('admin.php?page=vcela-lojas')) . '">← Todas as lojas</a></p>';
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:820px">';
	wp_nonce_field('vcp_salvar');
	echo '<input type="hidden" name="action" value="vcp_salvar"><input type="hidden" name="orig" value="' . esc_attr($sel) . '">';
	$f = fn($lab, $html, $aj = '') => '<tr><th scope="row">' . $lab . '</th><td>' . $html . ($aj ? '<p class="description">' . $aj . '</p>' : '') . '</td></tr>';
	echo '<table class="form-table">';
	echo $f('Nome da loja', '<input class="regular-text" name="nome" required value="' . esc_attr($d['nome']) . '">');
	echo $f('Endereço', '<code>' . esc_html(home_url('/')) . '</code><input name="slug" required pattern="[a-z0-9-]{2,40}" value="' . esc_attr($sel) . '">', 'Só letras minúsculas, números e hífen. Ex.: vcelastore');
	echo $f('Logo', '<input class="regular-text" id="vcp-logo" name="logo" value="' . esc_attr($d['logo']) . '"> <button type="button" class="button vcp-media" data-alvo="vcp-logo">Escolher</button>', 'De preferência branco, PNG ou SVG sem fundo: ele fica sobre a faixa colorida.');
	echo $f('Cores da faixa', '<input type="color" name="cor1" value="' . esc_attr($d['cor1']) . '"> → <input type="color" name="cor2" value="' . esc_attr($d['cor2']) . '">', 'Degradê do topo e da tela de cadastro.');
	echo $f('Cor dos botões', '<input type="color" name="acc" value="' . esc_attr($d['acc']) . '"> modo escuro: <input type="color" name="acc2" value="' . esc_attr($d['acc2']) . '">', 'Precisa ter bom contraste com texto branco.');
	echo $f('WhatsApp da loja', '<input name="whatsapp" value="' . esc_attr(vcp_fone($d['whatsapp'])) . '" placeholder="11 90000-0000">');
	echo $f('Instagram', '@<input name="instagram" value="' . esc_attr($d['instagram']) . '">');
	echo $f('Situação', '<label><input type="checkbox" name="ativa" value="1"' . checked($d['ativa'], 1, false) . '> Provador no ar</label>');
	// Peças
	$rows = '';
	$pecas = $d['pecas'] ?: [];
	$pecas[] = ['nome' => '', 'img' => ''];
	foreach ($pecas as $i => $p) {
		$rows .= '<tr><td><input name="pecas[' . $i . '][nome]" placeholder="Nome da peça" value="' . esc_attr($p['nome']) . '"></td><td><input class="regular-text" id="vcp-p' . $i . '" name="pecas[' . $i . '][img]" value="' . esc_attr($p['img']) . '"> <button type="button" class="button vcp-media" data-alvo="vcp-p' . $i . '">Escolher</button></td><td>' . ($p['img'] ? '<img src="' . esc_url($p['img']) . '" style="height:48px">' : '') . '</td></tr>';
	}
	echo $f('Peças', '<table class="widefat" style="max-width:640px"><tbody>' . $rows . '</tbody></table>', 'Foto da peça de frente, em PNG sem fundo. Para apagar uma peça, deixe o nome vazio. Salve para abrir mais uma linha.');
	if ($sel) {
		$lk = home_url('/' . $sel . '/clientes?k=' . $d['chave']);
		echo $f('Lista de clientes', '<input class="large-text" readonly value="' . esc_attr($lk) . '" onclick="this.select()"><br><label><input type="checkbox" name="nova_chave" value="1"> Gerar link novo (o antigo para de funcionar)</label>', 'Mande só para a dona da loja: quem tiver o link vê os contatos.');
		echo $f('Provador', '<a href="' . esc_url(home_url('/' . $sel . '/')) . '" target="_blank">' . esc_html(home_url('/' . $sel . '/')) . '</a>');
	}
	echo '</table>';
	submit_button('Salvar loja');
	echo '</form></div>';
	echo "<script>jQuery(function($){ $('.vcp-media').on('click',function(e){e.preventDefault();var alvo=$('#'+$(this).data('alvo'));var f=wp.media({title:'Escolher imagem',multiple:false,library:{type:'image'}});f.on('select',function(){alvo.val(f.state().get('selection').first().toJSON().url);});f.open();});});</script>";
}

// Libera SVG só para quem administra (logo da loja)
add_filter('upload_mimes', function ($m) { if (current_user_can('manage_options')) $m['svg'] = 'image/svg+xml'; return $m; });

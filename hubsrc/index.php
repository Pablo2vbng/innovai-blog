<?php
// innovAI Hub · panel de control
declare(strict_types=1);
require __DIR__ . '/lib/core.php';
require __DIR__ . '/lib/tools.php';
const INSTALL_HASH = '$2y$12$K4vt.s5GrxJFX1CZSL6mFuYrf79eURQ.l3FJ5xv3T24ZlBcZ8RSN2';
session_boot();
$admin = store_get('admin', []);
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'setup' && empty($admin['hash'])) {
        if (!password_verify(trim($_POST['install'] ?? ''), INSTALL_HASH)) $err = 'Código de instalación incorrecto.';
        elseif (strlen($_POST['pass'] ?? '') < 10) $err = 'La contraseña debe tener al menos 10 caracteres.';
        elseif (($_POST['pass'] ?? '') !== ($_POST['pass2'] ?? '')) $err = 'Las contraseñas no coinciden.';
        else { store_put('admin', ['hash' => password_hash($_POST['pass'], PASSWORD_DEFAULT), 'created' => date('c')]);
            if (!cfg('mcp_key')) cfg_set(['mcp_key' => bin2hex(random_bytes(24))]);
            session_regenerate_id(true); $_SESSION['admin'] = 1; header('Location: index.php'); exit; }
    } elseif ($act === 'login') {
        $fails = store_get('login_fails', ['n' => 0, 't' => 0]);
        if ($fails['n'] >= 5 && time() - $fails['t'] < 900) $err = 'Demasiados intentos. Espera 15 minutos.';
        elseif (!empty($admin['hash']) && password_verify($_POST['pass'] ?? '', $admin['hash'])) { store_put('login_fails', ['n' => 0, 't' => 0]); session_regenerate_id(true); $_SESSION['admin'] = 1; header('Location: index.php'); exit; }
        else { store_put('login_fails', ['n' => $fails['n'] + 1, 't' => time()]); $err = 'Contraseña incorrecta.'; }
    } elseif (is_admin()) {
        csrf_check();
        if ($act === 'config') {
            $fields = ['meta_app_id', 'meta_config_id', 'meta_page_id', 'google_client_id', 'gsc_site', 'merchant_id', 'ga4_property', 'gbp_location', 'linkedin_client_id'];
            $secrets = ['meta_app_secret', 'google_client_secret', 'linkedin_client_secret'];
            $kv = []; foreach ($fields as $f) $kv[$f] = trim($_POST[$f] ?? '') ?: null;
            foreach ($secrets as $f) if (trim($_POST[$f] ?? '') !== '') $kv[$f] = trim($_POST[$f]);
            cfg_set($kv); $_SESSION['flash'] = 'Configuración guardada.';
        } elseif ($act === 'newkey') { cfg_set(['mcp_key' => bin2hex(random_bytes(24))]); $_SESSION['flash'] = 'Nueva clave del conector creada: actualiza la URL en Claude.'; }
        elseif ($act === 'disconnect') { $t = store_get('tokens'); unset($t[$_POST['p'] ?? '']); store_put('tokens', $t); $_SESSION['flash'] = 'Desconectado.'; }
        elseif ($act === 'logout') { session_destroy(); header('Location: index.php'); exit; }
        header('Location: index.php'); exit;
    }
}
if (isset($_GET['connect']) && is_admin()) {
    $p = $_GET['connect'];
    if (in_array($p, ['meta', 'google', 'linkedin'], true)) { header('Location: ' . ("{$p}_auth_url")()); exit; }
}
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>innovAI Hub</title><link rel="icon" href="/img/favicon-64.png">
<style>
:root{--bg:#05070B;--card:#0E141F;--line:#1D2636;--txt:#F3F6FA;--mut:#97A3B6;--blue:#2F6BFF;--ok:#3DD68C;--bad:#FF6B6B}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--txt);font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
.w{max-width:1120px;margin:0 auto;padding:24px 16px 60px}header{display:flex;align-items:center;gap:12px;margin-bottom:22px}header img{width:40px;height:40px;border-radius:9px}
h1{font-size:22px;margin:0}h1 span{color:var(--blue)}h2{font-size:17px;margin:0 0 12px}.sub{color:var(--mut);font-size:13px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:16px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
.conn{border:1px solid var(--line);border-radius:12px;padding:14px}.conn b{display:block;font-size:15px}.st{font-size:13px;margin:4px 0 10px}.st.ok{color:var(--ok)}.st.no{color:var(--mut)}
a.btn,button{display:inline-block;background:var(--blue);color:#fff;border:0;border-radius:9px;padding:9px 14px;font:600 14px system-ui;text-decoration:none;cursor:pointer}
button.sec,a.btn.sec{background:transparent;border:1px solid var(--line);color:var(--mut);font-weight:500;font-size:13px;padding:6px 11px}.st.warn{color:#f5b942}.okall{background:rgba(52,199,89,.12);border:1px solid rgba(52,199,89,.35);color:var(--ok);border-radius:10px;padding:10px 14px;margin:0 0 14px;font-size:14px}a.btn.dis{opacity:.4;pointer-events:none}
label{display:block;font-size:13px;color:var(--mut);margin:10px 0 4px}input{width:100%;background:#070B12;border:1px solid var(--line);color:var(--txt);border-radius:9px;padding:9px 11px;font:14px system-ui}
code,.mono{font:12.5px ui-monospace,Consolas,monospace;word-break:break-all;background:#070B12;border:1px solid var(--line);border-radius:8px;padding:8px 10px;display:block}
.flash{background:#10213F;border:1px solid var(--blue);border-radius:10px;padding:10px 14px;margin-bottom:16px}.err{background:#3A1515;border-color:var(--bad)}
table{width:100%;border-collapse:collapse;font-size:13px}td{border-bottom:1px solid var(--line);padding:6px 4px;vertical-align:top}td:first-child{white-space:nowrap;color:var(--blue)}
.login{max-width:380px;margin:12vh auto}
.tabs{display:flex;gap:6px;margin:-6px 0 18px;border-bottom:1px solid var(--line)}.tabs a{color:var(--mut);text-decoration:none;padding:9px 12px;font-weight:600;font-size:14px;border-bottom:2px solid transparent;margin-bottom:-1px}.tabs a.on{color:var(--txt);border-color:var(--blue)}
</style></head><body><div class="w">
<header><img src="/img/icono-innovai-180.png" alt=""><div><h1>innov<span>AI</span> Hub</h1><div class="sub">Tu centro propio de redes y Google · v<?= HUB_VERSION ?></div></div>
<?php if (is_admin()): ?><form method="post" style="margin-left:auto"><input type="hidden" name="csrf" value="<?= csrf() ?>"><button class="sec" name="act" value="logout">Salir</button></form><?php endif; ?></header>
<?php if ($err): ?><div class="flash err"><?= h($err) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="flash<?= str_starts_with($flash, 'Error') ? ' err' : '' ?>"><?= h($flash) ?></div><?php endif; ?>
<?php if (empty($admin['hash'])): ?>
<div class="card login"><h2>Primera puesta en marcha</h2><p class="sub">Crea la contraseña del panel. Necesitas el código de instalación que te ha dado Claude.</p>
<form method="post"><input type="hidden" name="act" value="setup"><label>Código de instalación</label><input name="install" autocomplete="off" required>
<label>Contraseña nueva (mín. 10 caracteres)</label><input type="password" name="pass" required autocomplete="new-password"><label>Repite la contraseña</label><input type="password" name="pass2" required autocomplete="new-password">
<p><button>Crear y entrar</button></p></form></div>
<?php elseif (!is_admin()): ?>
<div class="card login"><h2>Entrar</h2><form method="post"><input type="hidden" name="act" value="login"><label>Contraseña</label><input type="password" name="pass" required autocomplete="current-password"><p><button>Entrar</button></p></form></div>
<?php else: $m = tok('meta'); $g = tok('google'); $l = tok('linkedin'); $base = base_url(); ?>
<?php $v = ($_GET['v'] ?? 'resultados') === 'ajustes' ? 'ajustes' : 'resultados'; ?>
<nav class="tabs"><a href="?v=resultados" class="<?= $v === 'resultados' ? 'on' : '' ?>">Resultados</a><a href="?v=ajustes" class="<?= $v === 'ajustes' ? 'on' : '' ?>">Conexiones y ajustes</a></nav>
<?php if ($v === 'resultados'): include __DIR__ . '/lib/panel.html.php'; else: ?>
<?php $lok = $l && ($l['expires'] ?? 0) > time(); $lsoon = $lok && $l['expires'] - time() < 10 * 86400; $todo = $m && $g && $lok && !$lsoon; ?>
<div class="card"><h2>Conexiones</h2>
<?php if ($todo): ?><div class="okall">✓ Todo conectado. No tienes que pulsar nada: las conexiones se renuevan solas.</div><?php endif; ?>
<div class="grid">
 <div class="conn"><b>Meta · Instagram + Facebook</b><div class="st <?= $m ? 'ok' : 'no' ?>"><?= $m ? '✓ Conectado · ' . h($m['page_name']) . ($m['ig_username'] ? ' · @' . h($m['ig_username']) : '') : '○ Sin conectar' ?></div>
  <a class="btn<?= $m ? ' sec' : '' ?><?= cfg('meta_app_id') && cfg('meta_app_secret') ? '' : ' dis' ?>" href="?connect=meta"><?= $m ? 'Reconectar (solo si falla)' : 'Conectar Meta' ?></a></div>
 <div class="conn"><b>Google · Business, Search Console, Merchant, Analytics</b><div class="st <?= $g ? 'ok' : 'no' ?>"><?= $g ? '✓ Conectado · ' . h($g['email']) : '○ Sin conectar' ?></div>
  <a class="btn<?= $g ? ' sec' : '' ?><?= cfg('google_client_id') && cfg('google_client_secret') ? '' : ' dis' ?>" href="?connect=google"><?= $g ? 'Reconectar (solo si falla)' : 'Conectar Google' ?></a></div>
 <div class="conn"><b>LinkedIn · perfil personal</b><div class="st <?= $lsoon ? 'warn' : ($lok ? 'ok' : 'no') ?>"><?= $l ? ($lok ? ($lsoon ? '⚠ Caduca el ' . date('d/m/Y', $l['expires']) . ': pulsa Renovar' : '✓ Conectado · ' . h($l['name']) . ' · renovar antes del ' . date('d/m/Y', $l['expires'])) : '○ Caducado: pulsa Renovar') : '○ Sin conectar' ?></div>
  <a class="btn<?= $lok && !$lsoon ? ' sec' : '' ?><?= cfg('linkedin_client_id') && cfg('linkedin_client_secret') ? '' : ' dis' ?>" href="?connect=linkedin"><?= $l ? ($lok && !$lsoon ? 'Reconectar (solo si falla)' : 'Renovar') : 'Conectar LinkedIn' ?></a></div>
 <div class="conn"><b>ChatGPT para negocios · catálogo</b><div class="st ok">● <?= count(chatgpt_products()) ?> productos en el feed</div><div class="sub">Pega esta URL en Business Hub → Productos → «Usar una URL alojada»:</div><code><?= h(chatgpt_feed_url()) ?></code></div>
</div></div>
<div class="card"><h2>Conector para Claude</h2><p class="sub">En claude.ai → Configuración → Conectores → «Añadir conector personalizado», pega esta URL (es secreta: no la compartas).</p>
<code><?= h($base . '/mcp.php?k=' . cfg('mcp_key')) ?></code>
<form method="post" style="margin-top:10px"><input type="hidden" name="csrf" value="<?= csrf() ?>"><button class="sec" name="act" value="newkey" onclick="return confirm('¿Crear una clave nueva? La URL anterior dejará de funcionar.')">Crear clave nueva</button></form></div>
<div class="card"><h2>Claves de las apps</h2><p class="sub">URLs de redirección que tienes que poner en cada app:</p>
<table><tr><td>Meta</td><td class="mono"><?= h($base) ?>/oauth/callback.php?p=meta</td></tr><tr><td>Google</td><td class="mono"><?= h($base) ?>/oauth/callback.php?p=google</td></tr><tr><td>LinkedIn</td><td class="mono"><?= h($base) ?>/oauth/callback.php?p=linkedin</td></tr></table>
<form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="act" value="config"><div class="grid">
<?php foreach ([['meta_app_id','Meta · ID de la app'],['meta_app_secret','Meta · clave secreta',1],['meta_config_id','Meta · ID del ajuste de inicio de sesión'],['meta_page_id','Meta · ID de página (opcional)'],['google_client_id','Google · ID de cliente'],['google_client_secret','Google · secreto de cliente',1],['gsc_site','Search Console · propiedad (sc-domain:innovai.es)'],['merchant_id','Merchant Center · ID de cuenta'],['ga4_property','Analytics 4 · ID de propiedad (numérico)'],['gbp_location','Google Business · ficha (locations/…)'],['linkedin_client_id','LinkedIn · Client ID'],['linkedin_client_secret','LinkedIn · Client Secret',1]] as $f): $sec = !empty($f[2]); ?>
<div><label><?= h($f[1]) ?><?= $sec && cfg($f[0]) ? ' · <span style="color:var(--ok)">guardada</span>' : '' ?></label><input name="<?= $f[0] ?>" <?= $sec ? 'type="password" autocomplete="new-password" placeholder="' . (cfg($f[0]) ? '•••••• (déjalo vacío para no cambiarla)' : '') . '"' : 'value="' . h((string)cfg($f[0], '')) . '"' ?>></div>
<?php endforeach; ?></div><p><button>Guardar</button></p></form></div>
<div class="card"><h2>Herramientas disponibles (<?= count($GLOBALS['HUB_TOOLS']) ?>)</h2><table><?php foreach ($GLOBALS['HUB_TOOLS'] as $n => $t): ?><tr><td><?= h($n) ?></td><td><?= h($t['description']) ?></td></tr><?php endforeach; ?></table></div>
<?php endif; endif; ?></div></body></html>

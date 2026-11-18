<?php /* innovAI Hub · pestaña Clientes (solo Pablo). Se incluye desde index.php. */
$clis = clients_all();
$e = (string)($_GET['e'] ?? '');
$ed = $e === 'nuevo' ? ['slug' => ''] : ($e !== '' ? client_get($e) : null);
$labels = [
    'gsc_site' => ['Search Console · propiedad', 'p. ej. sc-domain:naranjasycaquis.com'],
    'ga4_property' => ['Analytics 4 · ID de propiedad', 'solo números, p. ej. 512345678'],
    'gbp_location' => ['Google Business · ficha', 'locations/…'],
    'meta_page_id' => ['Facebook · ID de la página', 'su Instagram se detecta solo si está vinculado'],
    'merchant_id' => ['Merchant Center · ID (si tiene tienda)', 'opcional'],
    'linkedin_org' => ['LinkedIn · página de empresa', 'URL o ID (aún no se leen sus datos)'],
    'tiktok_user' => ['TikTok · usuario', '@usuario (aún no se leen sus datos)'],
];
// Ayudas para rellenar: propiedades y páginas a las que ya tienes acceso.
$sites = []; try { $sites = array_column($GLOBALS['HUB_TOOLS']['search_console_sitios']['fn']([]), 'siteUrl'); } catch (Throwable $x) {}
$pages = tok('meta')['pages'] ?? [];
?>
<style>.cl td{white-space:normal!important;color:var(--txt)!important}.cl .tag{display:inline-block;font-size:11px;border:1px solid var(--line);border-radius:12px;padding:1px 7px;margin:1px 3px 1px 0;color:var(--mut)}.cl .tag.ok{color:var(--ok);border-color:rgba(61,214,140,.4)}
.chk{display:flex;align-items:center;gap:8px;margin-top:12px}.chk input{width:auto}</style>
<?php if ($ed === null): ?>
<div class="card"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><h2 style="margin:0">Clientes</h2><a class="btn" href="?v=clientes&amp;e=nuevo">+ Nuevo cliente</a></div>
<p class="sub">Cada cliente entra en esta misma dirección con su usuario y su contraseña y solo ve los resultados de su negocio. No puede publicar ni cambiar nada.</p>
<?php if (!$clis): ?><div class="sub">Todavía no hay clientes.</div><?php else: ?>
<table class="cl"><tr><td class="sub">Negocio</td><td class="sub">Usuario</td><td class="sub">Cuentas</td><td></td></tr>
<?php foreach ($clis as $k => $c): ?><tr><td><b><?= h($c['nombre'] ?? $k) ?></b><?= !empty($c['desactivado']) ? ' <span class="tag">desactivado</span>' : '' ?><br><span class="sub"><?= h($c['web'] ?? '') ?></span></td>
<td><?= h($c['usuario'] ?? '') ?></td>
<td><?php foreach (['gsc_site' => 'Search Console', 'ga4_property' => 'Analytics', 'gbp_location' => 'Google Business', 'meta_page_id' => 'Facebook/Instagram', 'linkedin_org' => 'LinkedIn', 'tiktok_user' => 'TikTok'] as $f => $n): ?><span class="tag<?= !empty($c[$f]) ? ' ok' : '' ?>"><?= !empty($c[$f]) ? '✓ ' : '' ?><?= $n ?></span><?php endforeach; ?></td>
<td style="white-space:nowrap"><a class="btn sec" href="?v=resultados&amp;c=<?= h($k) ?>">Ver</a> <a class="btn sec" href="?v=clientes&amp;e=<?= h($k) ?>">Editar</a></td></tr>
<?php endforeach; ?></table><?php endif; ?>
<p class="sub" style="margin-top:14px">Dirección para tus clientes: <b><?= h(base_url()) ?>/</b></p></div>
<?php else: $isNew = ($ed['slug'] ?? '') === ''; ?>
<div class="card"><h2><?= $isNew ? 'Nuevo cliente' : 'Editar · ' . h($ed['nombre'] ?? '') ?></h2>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="act" value="cliente"><input type="hidden" name="slug" value="<?= h($ed['slug'] ?? '') ?>">
<div class="grid">
 <div><label>Nombre del negocio</label><input name="nombre" required value="<?= h($ed['nombre'] ?? '') ?>"></div>
 <div><label>Web</label><input name="web" placeholder="https://…" value="<?= h($ed['web'] ?? '') ?>"></div>
 <div><label>Usuario para entrar</label><input name="usuario" required autocapitalize="none" spellcheck="false" placeholder="p. ej. protege-t" value="<?= h($ed['usuario'] ?? '') ?>"></div>
 <div><label>Contraseña<?= $isNew ? ' (mín. 10 caracteres)' : ' nueva (déjala vacía para no cambiarla)' ?></label><input name="pass" type="password" autocomplete="new-password" <?= $isNew ? 'required minlength="10"' : '' ?>></div>
</div>
<h2 style="margin-top:20px">Cuentas del cliente</h2>
<p class="sub">Se leen con tus propias conexiones: para que funcionen, el cliente tiene que darte acceso (Search Console y Analytics: usuario con permiso de lectura; Facebook: administrador de la página).</p>
<div class="grid">
<?php foreach ($labels as $f => [$lab, $ph]): ?>
 <div><label><?= h($lab) ?></label><input name="<?= $f ?>" placeholder="<?= h($ph) ?>" value="<?= h((string)($ed[$f] ?? '')) ?>"<?= $f === 'gsc_site' && $sites ? ' list="dl-gsc"' : ($f === 'meta_page_id' && $pages ? ' list="dl-fb"' : '') ?>></div>
<?php endforeach; ?>
</div>
<?php if ($sites): ?><datalist id="dl-gsc"><?php foreach ($sites as $x): ?><option value="<?= h($x) ?>"><?php endforeach; ?></datalist><?php endif; ?>
<?php if ($pages): ?><datalist id="dl-fb"><?php foreach ($pages as $x): ?><option value="<?= h($x['id']) ?>"><?= h($x['name']) ?></option><?php endforeach; ?></datalist><?php endif; ?>
<?php if (!$isNew): ?><label class="chk"><input type="checkbox" name="desactivado" value="1"<?= !empty($ed['desactivado']) ? ' checked' : '' ?>> Desactivar el acceso de este cliente</label><?php endif; ?>
<p style="margin-top:16px"><button>Guardar</button> <a class="btn sec" href="?v=clientes">Cancelar</a></p></form></div>
<?php if ($sites || $pages): ?><div class="card"><h2>A qué tienes acceso ahora</h2>
<?php if ($sites): ?><p class="sub">Search Console: <?= h(implode(' · ', $sites)) ?></p><?php endif; ?>
<?php if ($pages): ?><p class="sub">Páginas de Facebook: <?= h(implode(' · ', array_map(fn($x) => $x['name'] . ' (' . $x['id'] . ')', $pages))) ?></p><?php endif; ?></div><?php endif; ?>
<?php endif; ?>

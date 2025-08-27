<?php
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors','1');

/* ================== CONFIG ================== */
$cfgPath = __DIR__.'/config/config.php';
if(!file_exists($cfgPath)){ http_response_code(500); exit("Falta config/config.php"); }
$cfg = require $cfgPath;

/* ================== DB (SQLite) ================== */
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  http_response_code(500);
  exit("SQLite no está habilitado. En tu php.ini activa:\nextension_dir=...\\ext\nextension=pdo_sqlite\nextension=sqlite3");
}
$dsnPath = $cfg['db_path'];
@mkdir(dirname($dsnPath), 0777, true);
$pdo = new PDO('sqlite:'.$dsnPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once __DIR__.'/migrations/scopeguard_tables.php';
scopeguard_migrate($pdo);

/* ================== LIBS & MODELS ================== */
require_once __DIR__.'/scopeguard/scopeguard_token.php';
require_once __DIR__.'/models/change_request_model.php';
require_once __DIR__.'/models/scopeguard_signature_model.php';
require_once __DIR__.'/models/scopeguard_audit_model.php';
require_once __DIR__.'/models/scopeguard_approval_model.php'; // matriz (roles)
require_once __DIR__.'/models/change_request_search_model.php'; // búsqueda/filtros/paginación
require_once __DIR__.'/models/scopeguard_comment_service.php';
require_once __DIR__.'/scopeguard/scopeguard_auth.php';
require_once __DIR__.'/models/scopeguard_user_model.php';
require_once __DIR__.'/models/scopeguard_settings_model.php';

sg_auth_bootstrap_start_session();
$auth       = new scopeguard_auth($pdo);
$crModel    = new change_request_model($pdo);
$signModel  = new scopeguard_signature_model($pdo);
$auditModel = new scopeguard_audit_model($pdo);
$tokenLib   = new scopeguard_token($cfg);
$apprModel  = new scopeguard_approval_model($pdo);
$searchModel= new change_request_search_model($pdo);
$settings   = new scopeguard_settings_model($pdo);

/* ================== HELPERS UI ================== */
function sg_current_user(): ?array {
  global $auth;
  return $auth->user();
}
function base_url(): string {
  global $cfg;
  if(!empty($cfg['base_url'])) return rtrim($cfg['base_url'],'/');
  $sch = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https':'http';
  $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
  return $sch.'://'.$host;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect_to(string $p){ header('Location: '.base_url().$p); exit; }

/* === Ajustes Helpers === */
function sg_currency_default(): string {
  global $settings, $cfg;
  return $settings->get('currency_default', $cfg['currency_default'] ?? 'USD') ?: 'USD';
}
function sg_tax_default(): float {
  global $settings, $cfg;
  return (float)$settings->get('tax_default_percent', $cfg['tax_default_percent'] ?? 0);
}
function sg_locale(): string {
  global $settings;
  $l = $settings->get('locale', 'es');
  return $l ?: 'es';
}
/** Dispara un webhook si está configurado */
function sg_webhook_fire(string $event, array $payload): void {
  global $settings;
  $url = trim((string)$settings->get('webhook_url',''));
  if($url==='') return;
  $body = json_encode(['event'=>$event,'payload'=>$payload,'ts'=>time()], JSON_UNESCAPED_UNICODE);
  $opts = [
    'http' => [
      'method'  => 'POST',
      'header'  => "Content-Type: application/json\r\nUser-Agent: ScopeGuard/1.0\r\n",
      'content' => $body,
      'timeout' => 5
    ]
  ];
  @file_get_contents($url, false, stream_context_create($opts));
}

function layout_header(string $title, bool $public=false){
  global $settings;
  $u = sg_current_user();
  $locale = sg_locale();
  $logo = trim((string)$settings->get('logo_url',''));
  $cPrimary = $settings->get('color_primary', '#4f46e5') ?: '#4f46e5';
  $cBorder  = $settings->get('color_border',  '#e5e5e5') ?: '#e5e5e5';
  $cNavBg   = $settings->get('color_navbar_bg','#ffffff') ?: '#ffffff';
  $cCardBg  = $settings->get('color_card_bg', '#ffffff') ?: '#ffffff';
  ?>
  <!doctype html>
  <html lang="<?= h($locale) ?>"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
      :root{
        --primary: <?= h($cPrimary) ?>;
        --border:  <?= h($cBorder) ?>;
        --navbg:   <?= h($cNavBg) ?>;
        --cardbg:  <?= h($cCardBg) ?>;
      }
      .navbar{ background: var(--navbg); }
      .card{ background: var(--cardbg); border-color: var(--border); }
      .btn.btn-primary{ background: var(--primary); border-color: var(--primary); }
      .btn.success{ background: #10b981; border-color:#10b981; }
      .btn.danger{ background: #ef4444; border-color:#ef4444; }
    </style>
  </head><body>
  <div class="navbar">
    <div class="wrap">
      <div class="brand">
        <?php if($logo): ?>
          <img src="<?= h($logo) ?>" alt="Logo" style="height:22px;margin-right:8px;display:block">
        <?php else: ?>
          <div class="logo"></div>
        <?php endif; ?>
        <div>ScopeGuard</div>
      </div>
      <?php if(!$public): ?>
      <div class="toolbar">
        <a class="btn btn-default" href="/admin/scopeguard/dashboard">Dashboard</a>
        <a class="btn btn-default" href="/admin/scopeguard">Panel</a>
        <a class="btn btn-primary" href="/admin/scopeguard/create">+ Nueva solicitud</a>
        <span class="sep"></span>
        <?php if($u): ?>
          <?php if(($u['role'] ?? '') === 'admin'): ?>
            <a class="btn btn-default" href="/admin/settings">Ajustes</a>
          <?php endif; ?>
          <span class="small">👤 <?= h($u['name']) ?> (<?= h($u['role']) ?>)</span>
          <a class="btn btn-default" href="/admin/profile">Mi Perfil</a>
          <a class="btn btn-default" href="/admin/logout">Salir</a>
        <?php else: ?>
          <a class="btn btn-default" href="/admin/login">Ingresar</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="container">
  <?php
}
function layout_footer(){ echo '<div class="footer-space"></div></div></body></html>'; }
function status_badge(string $status): string {
  $cls = ['draft'=>'draft','sent'=>'sent','approved'=>'approved','rejected'=>'rejected'][$status] ?? 'draft';
  return '<span class="badge '.$cls.'">'.h($status).'</span>';
}
function calc_totals(array $items): array {
  $sub=0;$tax=0;$disc=0;
  foreach($items as $it){
    $line = ((float)$it['qty'] * (float)$it['unit_price']);
    $disc += $line * ((float)$it['discount']/100);
    $lineAfter = $line * (1 - ((float)$it['discount']/100));
    $tax  += $lineAfter * ((float)$it['tax_rate']/100);
    $sub  += $line;
  }
  $total = $sub - $disc + $tax;
  return ['subtotal'=>$sub,'discount'=>$disc,'tax'=>$tax,'total'=>$total];
}

/* === Estado global a partir de la matriz === */
function sg_refresh_cr_status(int $crId): void {
  global $pdo;
  $st = $pdo->prepare("SELECT status, token FROM scopeguard_approvals WHERE change_request_id=?");
  $st->execute([$crId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) return;
  $anyRejected=false; $allApproved=true; $anySent=false;
  foreach($rows as $r){
    if($r['status']==='rejected') $anyRejected=true;
    if($r['status']!=='approved') $allApproved=false;
    if(!empty($r['token'])) $anySent=true;
  }
  $status='draft';
  if($anyRejected) $status='rejected';
  elseif($allApproved) $status='approved';
  elseif($anySent) $status='sent';
  $pdo->prepare("UPDATE scopeguard_change_requests SET status=?, updated_at=? WHERE id=?")
      ->execute([$status, time(), $crId]);
}

/* === SVG helpers (micro-gráficos) === */
function svg_sparkline(array $values, int $w=240, int $h=48): string {
  $n = max(1, count($values));
  $max = max(1.0, (float)max($values ?: [0]));
  $min = 0.0;
  $dx = $n > 1 ? ($w-2)/($n-1) : 0;
  $pts=[]; for($i=0;$i<$n;$i++){
    $v=(float)$values[$i];
    $x = 1 + $dx*$i;
    $y = $h-1 - (($v-$min)/($max-$min?:1))*($h-2);
    $pts[] = round($x,2).','.round($y,2);
  }
  $lastY = $h-1 - ((end($values)??0)/($max?:1))*($h-2);
  return '<svg width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="sparkline">
    <rect x="0" y="0" width="'.$w.'" height="'.$h.'" fill="none"/>
    <polyline fill="none" stroke="#3b82f6" stroke-width="2" points="'.implode(' ',$pts).'"/>
    <circle cx="'.($w-1).'" cy="'.round($lastY,2).'" r="3" fill="#3b82f6"/>
  </svg>';
}
function svg_bars(array $values, int $w=240, int $h=48): string {
  $n = max(1, count($values)); $max = max(1.0, (float)max($values ?: [0]));
  $gap = 3; $barW = max(2, (int)floor(($w - ($n+1)*$gap)/$n));
  $x=$gap; $rects='';
  foreach($values as $v){
    $vh = (int)round(((float)$v/$max)*($h-10));
    $y = $h-1-$vh;
    $rects .= '<rect x="'.$x.'" y="'.$y.'" width="'.$barW.'" height="'.$vh.'" fill="#10b981"/>';
    $x += $barW + $gap;
  }
  return '<svg width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="bars">
    <rect x="0" y="0" width="'.$w.'" height="'.$h.'" fill="none"/>
    '.$rects.'
  </svg>';
}
function svg_donut(float $percent, int $size=88, int $stroke=10, string $color='#6366f1'): string {
  $r = (int)(($size - $stroke)/2); $cx=$cy=(int)($size/2);
  $c = 2*pi()*$r;
  $p = max(0.0,min(100.0,$percent));
  $dash = ($p/100.0)*$c;
  $gap = $c - $dash;
  return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="donut">
    <circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="#e5e7eb" stroke-width="'.$stroke.'"/>
    <circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="'.$color.'" stroke-width="'.$stroke.'" stroke-linecap="round"
      stroke-dasharray="'.round($dash,2).' '.round($gap,2).'" transform="rotate(-90 '.$cx.' '.$cy.')" />
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="14" fill="#111827">'.round($p).'%</text>
  </svg>';
}

/* === Helper de URL (para paginación manteniendo filtros) === */
function url_with(array $overrides=[]): string {
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/admin/scopeguard';
  $q = $_GET;
  foreach($overrides as $k=>$v){
    if($v===null || $v===''){ unset($q[$k]); } else { $q[$k]=$v; }
  }
  $qs = http_build_query($q);
  return $path.($qs ? ('?'.$qs) : '');
}

/* ================== ROUTER BASE ================== */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ===== GUARDIA DE AUTENTICACIÓN PARA /admin/* ===== */
if (strpos($uri, '/admin')===0 && $uri !== '/admin/login') {
  if(!$auth->is_logged()){
    $next = urlencode($uri.(!empty($_SERVER['QUERY_STRING'])?('?'.$_SERVER['QUERY_STRING']):''));
    header('Location: /admin/login?next='.$next);
    exit;
  }
}

/* ===== LOGIN (PÚBLICO) ===== */
if ($uri==='/admin/login') {
  if ($method==='POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($auth->login($email,$pass)) {
      $next = $_GET['next'] ?? '/admin/scopeguard';
      header('Location: '.$next);
      exit;
    }
    $error = 'Credenciales inválidas';
  }
  layout_header('Ingresar · ScopeGuard', true);
  ?>
  <div class="center">
    <div class="card" style="max-width:420px;width:100%;border:1px solid var(--border)">
      <div class="card-pad">
        <h2 class="title">Ingresar</h2>
        <?php if(!empty($error)): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>
        <form method="post" action="/admin/login<?= !empty($_GET['next'])?('?next='.urlencode($_GET['next'])):'' ?>">
          <label class="label">Email</label>
          <input class="form-control" type="email" name="email" required placeholder="tu@empresa.com">
          <label class="label" style="margin-top:8px">Contraseña</label>
          <input class="form-control" type="password" name="password" required>
          <div class="toolbar" style="margin-top:12px">
            <button class="btn btn-primary" type="submit">Entrar</button>
          </div>
          <p class="help">Usuario inicial: <code>admin@local</code> / <code>admin123</code> (cámbialo en “Mi Perfil”).</p>
        </form>
      </div>
    </div>
  </div>
  <?php
  layout_footer(); exit;
}

/* ===== LOGOUT ===== */
if ($uri==='/admin/logout') {
  $auth->logout();
  header('Location: /admin/login');
  exit;
}

/* ===== MI PERFIL (STAFF) ===== */
if ($uri==='/admin/profile') {
  $u = sg_current_user();
  if(!$u){ header('Location:/admin/login'); exit; }

  $um = new scopeguard_user_model($pdo);
  if ($method==='POST') {
    $name = trim($_POST['name'] ?? $u['name']);
    $email= trim($_POST['email']?? $u['email']);
    $role = $u['role']; // no editable aquí
    $notify = [
      'on_external_comment' => !empty($_POST['on_external_comment']),
      'on_status_change'    => !empty($_POST['on_status_change']),
    ];
    $um->update_profile((int)$u['id'], $name, $email, $role, $notify);

    $newp = $_POST['new_password'] ?? '';
    $newp2= $_POST['new_password2'] ?? '';
    if($newp !== '' || $newp2 !== ''){
      if($newp === $newp2 && strlen($newp)>=8){
        $um->update_password((int)$u['id'], $newp);
      } else {
        $err="La nueva contraseña debe tener al menos 8 caracteres y coincidir.";
      }
    }
    if(empty($err)) { header('Location:/admin/profile'); exit; }
  }
  $u = sg_current_user(); // refrescar
  $prefs = json_decode($u['notify_prefs'] ?? '{}', true) ?: [];

  layout_header('Mi Perfil · ScopeGuard');
  ?>
  <div class="panel_s">
    <div class="panel-heading">Mi Perfil</div>
    <div class="panel-body">
      <?php if(!empty($err)): ?><div class="alert"><?= h($err) ?></div><?php endif; ?>
      <form method="post" action="/admin/profile">
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label class="label">Nombre</label>
            <input class="form-control" name="name" value="<?= h($u['name']) ?>" required>
          </div>
          <div>
            <label class="label">Email</label>
            <input class="form-control" type="email" name="email" value="<?= h($u['email']) ?>" required>
          </div>
        </div>

        <div class="card" style="border:1px solid var(--border);margin-top:12px">
          <div class="card-pad">
            <div class="kicker">Preferencias</div>
            <label style="display:flex;gap:8px;align-items:center;margin-top:6px">
              <input type="checkbox" name="on_external_comment" <?= !empty($prefs['on_external_comment'])?'checked':'' ?>>
              <span>Recibir correo cuando el cliente deje un comentario</span>
            </label>
            <label style="display:flex;gap:8px;align-items:center;margin-top:6px">
              <input type="checkbox" name="on_status_change" <?= !empty($prefs['on_status_change'])?'checked':'' ?>>
              <span>Recibir correo cuando cambie el estado (aprobado/rechazado)</span>
            </label>
          </div>
        </div>

        <div class="card" style="border:1px solid var(--border);margin-top:12px">
          <div class="card-pad">
            <div class="kicker">Cambiar contraseña</div>
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="label">Nueva contraseña</label>
                <input class="form-control" type="password" name="new_password" placeholder="mínimo 8 caracteres">
              </div>
              <div>
                <label class="label">Repite contraseña</label>
                <input class="form-control" type="password" name="new_password2">
              </div>
            </div>
          </div>
        </div>

        <div class="toolbar" style="margin-top:12px">
          <button class="btn btn-primary" type="submit">Guardar cambios</button>
          <a class="btn btn-default" href="/admin/scopeguard">Volver</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  layout_footer(); exit;
}

/* ===== AJUSTES (ADMIN) ===== */
if ($uri==='/admin/settings') {
  $u = sg_current_user();
  if(($u['role'] ?? '')!=='admin'){ http_response_code(403); layout_header('403'); echo '<div class="card"><div class="card-pad">Solo administradores</div></div>'; layout_footer(); exit; }

  $vals = $settings->all();

  if($method==='POST'){
    // Sanitizar colores hex
    $hex = function($v,$def){
      $v = trim((string)$v);
      if($v==='') return $def;
      if(preg_match('/^#?[0-9a-fA-F]{6}$/',$v)){
        return $v[0]==='#' ? $v : '#'.$v;
      }
      return $def;
    };
    $currency = strtoupper(trim($_POST['currency_default'] ?? sg_currency_default()));
    if($currency==='') $currency = 'USD';
    $tax = (float)($_POST['tax_default_percent'] ?? sg_tax_default());
    $webhook = trim((string)($_POST['webhook_url'] ?? ''));
    $locale  = trim((string)($_POST['locale'] ?? sg_locale()));
    if(!in_array($locale, ['es','en','pt','fr','de','it'], true)) $locale='es';

    // Logo
    $logoUrl = $settings->get('logo_url','');
    if(!empty($_POST['remove_logo'])){
      $logoUrl = '';
    } elseif(!empty($_FILES['logo']['tmp_name'])){
      $updir = __DIR__.'/uploads'; @mkdir($updir,0777,true);
      $name=basename($_FILES['logo']['name']);
      $safe = preg_replace('/[^a-z0-9._-]/i','_',$name);
      $dest=$updir.'/logo_'.time().'_'.$safe;
      if(move_uploaded_file($_FILES['logo']['tmp_name'],$dest)){
        $logoUrl = '/uploads/'.basename($dest);
      }
    }

    $settings->set_many([
      'currency_default'   => $currency,
      'tax_default_percent'=> (string)$tax,
      'webhook_url'        => $webhook,
      'locale'             => $locale,
      'logo_url'           => $logoUrl,
      'color_primary'      => $hex($_POST['color_primary'] ?? '', '#4f46e5'),
      'color_border'       => $hex($_POST['color_border'] ?? '', '#e5e5e5'),
      'color_navbar_bg'    => $hex($_POST['color_navbar_bg'] ?? '', '#ffffff'),
      'color_card_bg'      => $hex($_POST['color_card_bg'] ?? '', '#ffffff'),
    ]);
    header('Location: /admin/settings?ok=1'); exit;
  }

  $vals = $settings->all(); // refrescar
  layout_header('Ajustes · ScopeGuard');
  ?>
  <div class="panel_s">
    <div class="panel-heading">Ajustes</div>
    <div class="panel-body">
      <?php if(!empty($_GET['ok'])): ?><div class="alert success">Ajustes guardados.</div><?php endif; ?>
      <form method="post" action="/admin/settings" enctype="multipart/form-data">
        <div class="card" style="border:1px solid var(--border);margin-bottom:12px">
          <div class="card-pad">
            <div class="kicker">Generales</div>
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
              <div>
                <label class="label">Moneda por defecto</label>
                <input class="form-control" name="currency_default" value="<?= h($vals['currency_default'] ?? sg_currency_default()) ?>" placeholder="USD">
              </div>
              <div>
                <label class="label">IVA por defecto (%)</label>
                <input class="form-control" type="number" step="0.01" name="tax_default_percent" value="<?= h($vals['tax_default_percent'] ?? (string)sg_tax_default()) ?>">
              </div>
              <div>
                <label class="label">Idioma</label>
                <select class="form-control" name="locale">
                  <?php foreach(['es'=>'Español','en'=>'English','pt'=>'Português','fr'=>'Français','de'=>'Deutsch','it'=>'Italiano'] as $k=>$v): ?>
                    <option value="<?= h($k) ?>" <?= ($vals['locale'] ?? sg_locale())===$k?'selected':'' ?>><?= h($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="card" style="border:1px solid var(--border);margin-bottom:12px">
          <div class="card-pad">
            <div class="kicker">Marca</div>
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px">
              <div>
                <label class="label">Color primario</label>
                <input class="form-control" type="color" name="color_primary" value="<?= h($vals['color_primary'] ?? '#4f46e5') ?>">
              </div>
              <div>
                <label class="label">Color bordes</label>
                <input class="form-control" type="color" name="color_border" value="<?= h($vals['color_border'] ?? '#e5e5e5') ?>">
              </div>
              <div>
                <label class="label">NavBar fondo</label>
                <input class="form-control" type="color" name="color_navbar_bg" value="<?= h($vals['color_navbar_bg'] ?? '#ffffff') ?>">
              </div>
              <div>
                <label class="label">Card fondo</label>
                <input class="form-control" type="color" name="color_card_bg" value="<?= h($vals['color_card_bg'] ?? '#ffffff') ?>">
              </div>
            </div>
            <div style="margin-top:12px">
              <label class="label">Logo</label>
              <input class="form-control" type="file" name="logo" accept="image/*">
              <?php if(!empty($vals['logo_url'])): ?>
                <div class="small" style="margin-top:6px">
                  <img src="<?= h($vals['logo_url']) ?>" alt="logo" style="height:28px;vertical-align:middle">
                  <label style="margin-left:10px"><input type="checkbox" name="remove_logo" value="1"> Quitar logo</label>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="card" style="border:1px solid var(--border);margin-bottom:12px">
          <div class="card-pad">
            <div class="kicker">Integraciones</div>
            <label class="label">Webhook URL</label>
            <input class="form-control" type="url" name="webhook_url" placeholder="https://tuservidor.com/hook" value="<?= h($vals['webhook_url'] ?? '') ?>">
            <p class="help" style="margin-top:6px">Se hará <code>POST</code> JSON con <code>{event, payload, ts}</code> al aprobar/rechazar.</p>
          </div>
        </div>

        <div class="toolbar">
          <button class="btn btn-primary" type="submit">Guardar ajustes</button>
          <a class="btn btn-default" href="/admin/scopeguard">Volver</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  layout_footer(); exit;
}

/* ===== DASHBOARD (KPIs + micrográficos) ===== */
if($uri==='/admin/scopeguard/dashboard'){
  // KPIs base
  $countApproved = (int)$pdo->query("SELECT COUNT(*) FROM scopeguard_change_requests WHERE status='approved'")->fetchColumn();
  $countRejected = (int)$pdo->query("SELECT COUNT(*) FROM scopeguard_change_requests WHERE status='rejected'")->fetchColumn();
  $countSent     = (int)$pdo->query("SELECT COUNT(*) FROM scopeguard_change_requests WHERE status='sent'")->fetchColumn();
  $countDraft    = (int)$pdo->query("SELECT COUNT(*) FROM scopeguard_change_requests WHERE status='draft'")->fetchColumn();
  $pending       = $countSent + $countDraft;
  $sumApproved   = (float)$pdo->query("SELECT COALESCE(SUM(cost_delta),0) FROM scopeguard_change_requests WHERE status='approved'")->fetchColumn();

  // Serie últimos 6 meses
  $sinceTs = strtotime(date('Y-m-01', strtotime('-5 months')));
  $stmtC = $pdo->prepare("
    SELECT strftime('%Y-%m', datetime(created_at,'unixepoch')) ym, COUNT(*) c
    FROM scopeguard_change_requests
    WHERE created_at >= ?
    GROUP BY ym ORDER BY ym
  ");
  $stmtS = $pdo->prepare("
    SELECT strftime('%Y-%m', datetime(created_at,'unixepoch')) ym, COALESCE(SUM(cost_delta),0) s
    FROM scopeguard_change_requests
    WHERE created_at >= ? AND status='approved'
    GROUP BY ym ORDER BY ym
  ");
  $stmtC->execute([$sinceTs]); $rowsC=$stmtC->fetchAll(PDO::FETCH_KEY_PAIR);
  $stmtS->execute([$sinceTs]); $rowsS=$stmtS->fetchAll(PDO::FETCH_KEY_PAIR);

  $labels=[]; $counts=[]; $sums=[];
  for($i=5;$i>=0;$i--){
    $ym = date('Y-m', strtotime("-$i months"));
    $labels[] = $ym;
    $counts[] = (int)($rowsC[$ym] ?? 0);
    $sums[]   = (float)($rowsS[$ym] ?? 0);
  }

  $since30 = time()-30*86400;
  $ar = $pdo->prepare("SELECT status, COUNT(*) c FROM scopeguard_change_requests WHERE created_at>=? AND status IN ('approved','rejected') GROUP BY status");
  $ar->execute([$since30]); $m=$ar->fetchAll(PDO::FETCH_KEY_PAIR);
  $appr30 = (int)($m['approved'] ?? 0); $rej30 = (int)($m['rejected'] ?? 0);
  $rate = ($appr30+$rej30)>0 ? ($appr30/($appr30+$rej30))*100.0 : 0.0;

  layout_header('ScopeGuard · Dashboard');
  $curr = sg_currency_default();
  ?>
  <div class="panel_s">
    <div class="panel-heading">Dashboard</div>
    <div class="panel-body">
      <div class="grid" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
        <div class="card" style="border:1px solid var(--border)">
          <div class="card-pad">
            <div class="kicker">Aprobadas</div>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <h2 class="title" style="margin:2px 0"><?= (int)$countApproved ?></h2>
              <?= svg_donut($rate,88,10,'#10b981') ?>
            </div>
            <div class="small">Tasa 30 días: <?= number_format($rate,1) ?>%</div>
          </div>
        </div>
        <div class="card" style="border:1px solid var(--border)">
          <div class="card-pad">
            <div class="kicker">Rechazadas</div>
            <h2 class="title" style="margin:2px 0"><?= (int)$countRejected ?></h2>
            <div class="small">Últimos 30 días: <?= (int)$rej30 ?></div>
          </div>
        </div>
        <div class="card" style="border:1px solid var(--border)">
          <div class="card-pad">
            <div class="kicker">Pendientes</div>
            <h2 class="title" style="margin:2px 0"><?= (int)$pending ?></h2>
            <div class="small">Enviadas: <?= (int)$countSent ?> · Borrador: <?= (int)$countDraft ?></div>
          </div>
        </div>
        <div class="card" style="border:1px solid var(--border)">
          <div class="card-pad">
            <div class="kicker">Monto aprobado</div>
            <h2 class="title" style="margin:2px 0"><?= h($curr) ?> <?= number_format($sumApproved,2) ?></h2>
            <div class="small">Acum. histórico (cost_delta)</div>
          </div>
        </div>
      </div>

      <div class="grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px">
        <div class="card" style="border:1px solid var(--border)">
          <div class="card-pad">
            <div class="kicker">Solicitudes por mes (6M)</div>
            <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:8px">
              <div>
                <h3 class="title" style="margin:0">Volumen</h3>
                <div class="small"><?= implode(' · ', array_map(fn($l)=>date('M', strtotime($l)), $labels)) ?></div>
              </div>
              <div><?= svg_sparkline($counts, 280, 56) ?></div>
            </div>
          </div>
        </div>

        <div class="card" style="border:1px solid var(--border)">
          <div class="card-pad">
            <div class="kicker">Monto aprobado por mes (6M)</div>
            <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:8px">
              <div>
                <h3 class="title" style="margin:0">Importe</h3>
                <div class="small"><?= implode(' · ', array_map(fn($l)=>date('M', strtotime($l)), $labels)) ?></div>
              </div>
              <div><?= svg_bars($sums, 280, 56) ?></div>
            </div>
          </div>
        </div>
      </div>

      <?php $top = $pdo->query("SELECT id,title,status,currency,cost_delta,created_at FROM scopeguard_change_requests ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC); ?>
      <div class="card" style="border:1px solid var(--border); margin-top:12px">
        <div class="card-pad">
          <div class="kicker">Recientes</div>
          <table class="table" style="margin-top:6px">
            <thead><tr><th>ID</th><th>Título</th><th>Estado</th><th>Monto</th><th>Creado</th><th></th></tr></thead>
            <tbody>
              <?php foreach($top as $r): ?>
                <tr>
                  <td>#<?= (int)$r['id'] ?></td>
                  <td><?= h($r['title']) ?></td>
                  <td><?= status_badge($r['status']) ?></td>
                  <td><?= h($r['currency']) ?> <?= number_format((float)$r['cost_delta'],2) ?></td>
                  <td class="small"><?= date('Y-m-d', (int)$r['created_at']) ?></td>
                  <td><a class="btn btn-default" href="/admin/scopeguard/view?id=<?= (int)$r['id'] ?>">Ver</a></td>
                </tr>
              <?php endforeach; if(empty($top)): ?>
                <tr><td colspan="6" class="small">Sin registros aún.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
  <?php
  layout_footer(); exit;
}

/* ===== LISTA con filtros & paginación ===== */
if($uri==='/' || $uri==='/admin' || $uri==='/admin/scopeguard'){
  $result = $searchModel->search($_GET);
  $rows   = $result['rows'];
  $total  = $result['total'];
  $page   = $result['page'];
  $per    = $result['per'];
  $pages  = $result['pages'];
  $f      = $result['filters'];

  $from = $total ? (($page-1)*$per + 1) : 0;
  $to   = min($total, $from + count($rows) - 1);

  layout_header('ScopeGuard · Solicitudes');
  ?>
  <div class="panel_s">
    <div class="panel-heading">Solicitudes de Cambio</div>
    <div class="panel-body">

      <form method="get" action="/admin/scopeguard" class="form" style="margin-bottom:12px">
        <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr 1fr;gap:8px">
          <div>
            <label class="label">Buscar</label>
            <input class="form-control" name="q" placeholder="Título o descripción..." value="<?= h($f['q']) ?>">
          </div>
          <div>
            <label class="label">Estado</label>
            <select class="form-control" name="status">
              <?php $opts = ['' => 'Todos','draft'=>'Borrador','sent'=>'Enviado','approved'=>'Aprobado','rejected'=>'Rechazado'];
              foreach($opts as $k=>$v){ $sel = ($f['status']===$k) ? 'selected' : ''; echo '<option value="'.h($k).'" '.$sel.'>'.h($v).'</option>'; } ?>
            </select>
          </div>
          <div>
            <label class="label">Desde</label>
            <input type="date" class="form-control" name="date_from" value="<?= h($f['date_from']) ?>">
          </div>
          <div>
            <label class="label">Hasta</label>
            <input type="date" class="form-control" name="date_to" value="<?= h($f['date_to']) ?>">
          </div>
          <div>
            <label class="label">Importe mín.</label>
            <input type="number" step="0.01" class="form-control" name="amount_min" value="<?= $f['amount_min']!==null ? h((string)$f['amount_min']) : '' ?>">
          </div>
          <div>
            <label class="label">Importe máx.</label>
            <input type="number" step="0.01" class="form-control" name="amount_max" value="<?= $f['amount_max']!==null ? h((string)$f['amount_max']) : '' ?>">
          </div>
          <div>
            <label class="label">Por página</label>
            <select class="form-control" name="per">
              <?php foreach([10,25,50] as $pp): ?>
                <option value="<?= $pp ?>" <?= $per===$pp?'selected':'' ?>><?= $pp ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="toolbar" style="margin-top:10px">
          <button class="btn btn-primary" type="submit">Aplicar filtros</button>
          <a class="btn btn-default" href="/admin/scopeguard">Limpiar</a>
          <a class="btn btn-primary" style="margin-left:auto" href="/admin/scopeguard/create">+ Nueva solicitud</a>
          <a class="btn btn-default" href="/admin/scopeguard/dashboard">Dashboard</a>
        </div>
      </form>

      <div class="small" style="margin:6px 0 8px">
        Resultados: <?= (int)$from ?>–<?= (int)$to ?> de <?= (int)$total ?>
        <?php if($f['q']!==''): ?> · Búsqueda: “<?= h($f['q']) ?>”<?php endif; ?>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>ID</th><th>Título</th><th>Estado</th><th>Costo</th><th>Tiempo</th><th>Creado</th><th style="width:360px">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= h($r['title']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td><?= h($r['currency']) ?> <?= number_format((float)$r['cost_delta'],2) ?></td>
            <td><?= (int)$r['time_delta_hours'] ?>h</td>
            <td class="small"><?= date('Y-m-d', (int)$r['created_at']) ?></td>
            <td>
              <div class="toolbar">
                <a class="btn btn-default" href="/admin/scopeguard/view?id=<?= (int)$r['id'] ?>">Ver</a>
                <a class="btn btn-default" href="/admin/scopeguard/approvals/send-all?id=<?= (int)$r['id'] ?>">Enviar a todos (matriz)</a>
                <?php if(empty($r['public_token'])): ?>
                  <a class="btn btn-success" href="/admin/scopeguard/send?id=<?= (int)$r['id'] ?>">Enlace único</a>
                <?php else: ?>
                  <a class="btn btn-info" target="_blank" href="/scope-approve/<?= urlencode($r['public_token']) ?>">Público (único)</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; if (empty($rows)): ?>
          <tr><td colspan="7" class="small">No hay resultados para los filtros aplicados.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>

      <?php if($pages>1): ?>
        <div class="toolbar" style="margin-top:10px;flex-wrap:wrap;gap:6px">
          <?php $prev = max(1, $page-1); $next = min($pages, $page+1); ?>
          <a class="btn btn-default" href="<?= h(url_with(['p'=>1])) ?>"  <?= $page===1?'style="pointer-events:none;opacity:.5"':'' ?>>« Primero</a>
          <a class="btn btn-default" href="<?= h(url_with(['p'=>$prev])) ?>" <?= $page===1?'style="pointer-events:none;opacity:.5"':'' ?>>‹ Anterior</a>
          <?php
            $start = max(1, $page-2);
            $end   = min($pages, $page+2);
            for($i=$start;$i<=$end;$i++):
          ?>
            <?php if($i===$page): ?>
              <span class="btn btn-primary" style="pointer-events:none"><?= (int)$i ?></span>
            <?php else: ?>
              <a class="btn btn-default" href="<?= h(url_with(['p'=>$i])) ?>"><?= (int)$i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <a class="btn btn-default" href="<?= h(url_with(['p'=>$next])) ?>" <?= $page===$pages?'style="pointer-events:none;opacity:.5"':'' ?>>Siguiente ›</a>
          <a class="btn btn-default" href="<?= h(url_with(['p'=>$pages])) ?>" <?= $page===$pages?'style="pointer-events:none;opacity:.5"':'' ?>>Último »</a>
        </div>
      <?php endif; ?>

    </div>
  </div>
  <?php
  layout_footer(); exit;
}

/* ===== CREAR ===== */
if($uri==='/admin/scopeguard/create'){
  if($method==='POST'){
    $id = $crModel->create([
      'title' => $_POST['title'] ?? '',
      'description' => $_POST['description'] ?? '',
      'currency' => $_POST['currency'] ?? sg_currency_default(),
      'cost_delta' => (float)($_POST['cost_delta'] ?? 0),
      'time_delta_hours' => (int)($_POST['time_delta_hours'] ?? 0),
    ]);
    $apprModel->ensure_defaults($id); // matriz
    $auditModel->log($id,'created',[]);
    redirect_to('/admin/scopeguard/view?id='.$id);
  }
  layout_header('ScopeGuard · Nueva solicitud');
  ?>
  <div class="panel_s">
    <div class="panel-heading">Nueva Solicitud</div>
    <div class="panel-body">
      <form method="post">
        <label class="label">Título</label>
        <input class="form-control" name="title" required placeholder="Ej. Ajuste de alcance para integraciones"/>

        <label class="label" style="margin-top:12px">Descripción</label>
        <textarea class="form-control" name="description" rows="6" placeholder="Describe el cambio, impacto, supuestos..."></textarea>

        <div class="form-row" style="margin-top:12px">
          <div class="col"><label class="label">Moneda</label><input class="form-control" name="currency" value="<?= h(sg_currency_default()) ?>"/></div>
          <div class="col"><label class="label">Costo extra</label><input class="form-control" type="number" step="0.01" name="cost_delta" value="0.00"/></div>
          <div class="col"><label class="label">Tiempo extra (horas)</label><input class="form-control" type="number" name="time_delta_hours" value="0"/></div>
        </div>

        <div class="toolbar" style="margin-top:14px">
          <button class="btn btn-primary" type="submit">Guardar</button>
          <a class="btn btn-default" href="/admin/scopeguard">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
  <?php
  layout_footer(); exit;
}

/* ===== ENVIAR (ENLACE ÚNICO) ===== */
if($uri==='/admin/scopeguard/send'){
  $id=(int)($_GET['id']??0);
  $cr=$crModel->find($id); if(!$cr){ http_response_code(404); exit('Not found'); }
  $tok=$tokenLib->issue(['cr_id'=>$id]);
  $crModel->setToken($id,$tok, time()+60*($cfg['token_ttl_minutes'] ?? 10080));
  $auditModel->log($id,'sent',['token'=>$tok]);
  $public = base_url().'/scope-approve/'.urlencode($tok);

  layout_header('ScopeGuard · Enlace generado');
  ?>
  <div class="panel_s">
    <div class="panel-heading">Enlace público (único)</div>
    <div class="panel-body">
      <div class="alert">Enlace público generado correctamente.</div>
      <p><b><?= h($cr['title']) ?></b></p>
      <div class="copy">
        <input class="form-control" id="pubLink" value="<?= h($public) ?>" readonly />
        <button class="btn btn-default" id="copyBtn">Copiar</button>
        <a class="btn btn-info" target="_blank" href="<?= h($public) ?>">Abrir</a>
      </div>
      <div class="toolbar" style="margin-top:10px"><a class="btn btn-default" href="/admin/scopeguard">Volver al listado</a></div>
    </div>
  </div>
  <script>
    (function(){
      const i=document.getElementById('pubLink'), b=document.getElementById('copyBtn');
      if(i&&b){ b.addEventListener('click', async ()=>{ try{await navigator.clipboard.writeText(i.value); b.textContent='¡Copiado!'; setTimeout(()=>b.textContent='Copiar',1200);}catch(e){ i.select(); document.execCommand('copy'); b.textContent='¡Copiado!'; setTimeout(()=>b.textContent='Copiar',1200);} }); }
    }());
  </script>
  <?php
  layout_footer(); exit;
}

/* ===== ENVIAR A TODOS (MATRIZ) ===== */

if($uri==='/admin/scopeguard/approvals/send-all'){
  $id=(int)$_GET['id'];
  $cr=$crModel->find($id); if(!$cr){ http_response_code(404); exit('Not found'); }
  $apprModel->ensure_defaults($id);
  $rows = $apprModel->list_by_cr($id);
  foreach($rows as $ap){
    $tok=$tokenLib->issue(['appr_id'=>$ap['id'],'cr_id'=>$id,'role'=>$ap['role']]);
    $apprModel->issue_token((int)$ap['id'], $tok, time()+60*($cfg['token_ttl_minutes'] ?? 10080));
  }
  sg_refresh_cr_status($id);
  $auditModel->log($id,'sent_matrix',[]);
  layout_header('ScopeGuard · Enlaces por rol');
  echo '<div class="panel_s"><div class="panel-heading">Enlaces generados</div><div class="panel-body"><ul>';
  foreach($apprModel->list_by_cr($id) as $ap){
    $link = base_url().'/scope-approve/'.urlencode($ap['token']);
    echo '<li><b>'.h($ap['role']).'</b>: <a target="_blank" href="'.h($link).'">'.h($link).'</a></li>';
  }
  echo '</ul><div class="toolbar"><a class="btn btn-default" href="/admin/scopeguard/view?id='.$id.'">Volver</a></div></div></div>';
  layout_footer(); exit;
}

/* ===== ENVIAR UNO (MATRIZ) ===== */
if($uri==='/admin/scopeguard/approvals/send-one'){
  $id=(int)($_GET['id']??0);
  $role=trim($_GET['role'] ?? '');
  $cr=$crModel->find($id); if(!$cr){ http_response_code(404); exit('Not found'); }
  $apprModel->ensure_defaults($id);
  $ap = $apprModel->get_by_cr_role($id,$role); if(!$ap){ http_response_code(404); exit('Rol inválido'); }

  $tok=$tokenLib->issue(['appr_id'=>$ap['id'],'cr_id'=>$id,'role'=>$ap['role']]);
  $apprModel->issue_token((int)$ap['id'], $tok, time()+60*($cfg['token_ttl_minutes'] ?? 10080));
  sg_refresh_cr_status($id);
  $auditModel->log($id,'sent_role',['role'=>$role]);
  $public = base_url().'/scope-approve/'.urlencode($tok);

  layout_header('ScopeGuard · Enlace '.$role);
  echo '<div class="panel_s"><div class="panel-heading">Enlace '.$role.'</div><div class="panel-body">';
  echo '<div class="copy"><input class="form-control" value="'.h($public).'" readonly><a class="btn btn-default" target="_blank" href="'.h($public).'">Abrir</a></div>';
  echo '<div class="toolbar" style="margin-top:10px"><a class="btn btn-default" href="/admin/scopeguard/view?id='.$id.'">Volver</a></div>';
  echo '</div></div>';
  layout_footer(); exit;
}

/* ===== DETALLE ===== */
if($uri==='/admin/scopeguard/view'){
  $id=(int)($_GET['id']??0);
  $cr=$crModel->find($id); if(!$cr){ http_response_code(404); exit('No encontrado'); }

  require_once __DIR__.'/models/scopeguard_item_model.php';
  require_once __DIR__.'/models/scopeguard_comment_model.php';
  require_once __DIR__.'/models/scopeguard_attachment_model.php';
  $itemModel    = new scopeguard_item_model($pdo);
  $commentModel = new scopeguard_comment_model($pdo);
  $attModel     = new scopeguard_attachment_model($pdo);

  $apprModel->ensure_defaults($id);
  $approvals = $apprModel->list_by_cr($id);

  $items = $itemModel->list($id);
  $tot = calc_totals($items);
  $comments = $commentModel->list($id);
  $atts = $attModel->list($id);

  layout_header('ScopeGuard · Detalle');
  $taxDefault = sg_tax_default();
  ?>
  <div class="panel_s">
    <div class="panel-heading">Solicitud #<?= (int)$cr['id'] ?> — <?= h($cr['title']) ?></div>
    <div class="panel-body">
      <p class="small"><?= status_badge($cr['status']) ?> · Moneda: <b><?= h($cr['currency']) ?></b></p>
      <p><?= nl2br(h($cr['description'])) ?: '<span class="small">Sin descripción</span>' ?></p>

      <hr>
      <h4>Aprobaciones (matriz)</h4>
      <table class="table">
        <thead><tr><th>Rol</th><th>Aprobador</th><th>Email</th><th>Estado</th><th>Última acción</th><th>Enlace</th></tr></thead>
        <tbody>
          <?php foreach($approvals as $ap):
            $link = $ap['token'] ? (base_url().'/scope-approve/'.urlencode($ap['token'])) : '';
          ?>
          <tr>
            <td><?= h($ap['role']) ?></td>
            <td><?= h($ap['approver_name'] ?? '') ?></td>
            <td><?= h($ap['approver_email'] ?? '') ?></td>
            <td><?= status_badge($ap['status']) ?></td>
            <td class="small">
              <?php
                if($ap['acted_at']) echo date('Y-m-d H:i', (int)$ap['acted_at']);
                elseif($ap['last_sent_at']) echo 'enviado: '.date('Y-m-d H:i', (int)$ap['last_sent_at']);
                else echo '—';
              ?>
            </td>
            <td>
              <div class="toolbar">
                <?php if($link): ?>
                  <a class="btn btn-default" target="_blank" href="<?= h($link) ?>">Abrir</a>
                  <a class="btn btn-default" href="/admin/scopeguard/approvals/send-one?id=<?= (int)$cr['id'] ?>&role=<?= h($ap['role']) ?>">Reenviar</a>
                <?php else: ?>
                  <a class="btn btn-default" href="/admin/scopeguard/approvals/send-one?id=<?= (int)$cr['id'] ?>&role=<?= h($ap['role']) ?>">Generar</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="toolbar">
        <a class="btn btn-default" href="/admin/scopeguard/approvals/send-all?id=<?= (int)$cr['id'] ?>">Enviar a todos</a>
      </div>

      <hr>
      <h4>Ítems</h4>
      <form method="post" action="/admin/scopeguard/item/add?id=<?= (int)$cr['id'] ?>" class="form-inline" style="margin-bottom:10px">
        <input class="form-control" name="name" placeholder="Ítem" required>
        <input class="form-control" name="qty" type="number" step="0.01" placeholder="Cant." value="1">
        <input class="form-control" name="unit_price" type="number" step="0.01" placeholder="Precio" value="0.00">
        <input class="form-control" name="tax_rate" type="number" step="0.01" placeholder="% IVA" value="<?= h((string)$taxDefault) ?>">
        <input class="form-control" name="discount" type="number" step="0.01" placeholder="% Desc." value="0">
        <button class="btn btn-default" type="submit">Agregar</button>
      </form>

      <table class="table">
        <thead><tr><th>Ítem</th><th style="width:90px">Cant.</th><th style="width:120px">Precio</th><th style="width:90px">%Desc</th><th style="width:90px">%IVA</th><th style="width:120px">Importe</th><th style="width:60px"></th></tr></thead>
        <tbody>
          <?php foreach($items as $it):
            $line = (float)$it['qty']*(float)$it['unit_price'];
            $after = $line*(1-((float)$it['discount']/100));
            $lineTotal = $after*(1+((float)$it['tax_rate']/100));
          ?>
          <tr>
            <td><?= h($it['name']) ?></td>
            <td><?= (float)$it['qty'] ?></td>
            <td><?= number_format((float)$it['unit_price'],2) ?></td>
            <td><?= (float)$it['discount'] ?></td>
            <td><?= (float)$it['tax_rate'] ?></td>
            <td><?= number_format($lineTotal,2) ?></td>
            <td><a class="btn btn-link" href="/admin/scopeguard/item/del?id=<?= (int)$it['id'] ?>&cr=<?= (int)$cr['id'] ?>">✕</a></td>
          </tr>
          <?php endforeach; if(empty($items)): ?>
          <tr><td colspan="7" class="small">Sin ítems aún.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div style="text-align:right">
        <p>Subtotal: <b><?= h($cr['currency']) ?> <?= number_format($tot['subtotal'],2) ?></b></p>
        <p>Descuento: <b>- <?= h($cr['currency']) ?> <?= number_format($tot['discount'],2) ?></b></p>
        <p>IVA: <b><?= h($cr['currency']) ?> <?= number_format($tot['tax'],2) ?></b></p>
        <h3>Total: <b><?= h($cr['currency']) ?> <?= number_format($tot['total'],2) ?></b></h3>
      </div>

      <hr>
      <h4>Comentarios</h4>
      <form method="post" action="/admin/scopeguard/comment?id=<?= (int)$cr['id'] ?>">
        <textarea class="form-control" name="body" rows="3" placeholder="Comentario interno para el equipo..."></textarea>
        <div class="toolbar" style="margin-top:8px"><button class="btn btn-default">Agregar comentario</button></div>
      </form>
      <table class="table" style="margin-top:10px">
        <thead><tr><th>Autor</th><th>Mensaje</th><th>Fecha</th></tr></thead>
        <tbody>
          <?php foreach ($comments as $c): ?>
            <tr>
              <td><?= h($c['author_type']) ?><?= !empty($c['author_name']) ? ' · '.h($c['author_name']) : '' ?></td>
              <td><?= nl2br(h($c['body'])) ?></td>
              <td class="small"><?= date('Y-m-d H:i', (int)$c['created_at']) ?></td>
            </tr>
          <?php endforeach; if (empty($comments)): ?>
            <tr><td colspan="3" class="small">Sin comentarios aún.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <hr>
      <h4>Adjuntos</h4>
      <form method="post" enctype="multipart/form-data" action="/admin/scopeguard/upload?id=<?= (int)$cr['id'] ?>">
        <input type="file" name="file"> <button class="btn btn-default" type="submit">Subir</button>
        <p class="help">Si es muy grande, ajusta <code>upload_max_filesize</code> en tu php.ini.</p>
      </form>
      <table class="table" style="margin-top:10px">
        <thead><tr><th>Archivo</th><th>Tamaño</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($atts as $a): ?>
            <tr>
              <td><a href="<?= h($a['path']) ?>" target="_blank"><?= h($a['filename']) ?></a></td>
              <td class="small"><?= number_format((int)$a['size']/1024,1) ?> KB</td>
              <td><a class="btn btn-link" href="/admin/scopeguard/upload/del?id=<?= (int)$a['id'] ?>&cr=<?= (int)$cr['id'] ?>">✕</a></td>
            </tr>
          <?php endforeach; if (empty($atts)): ?>
            <tr><td colspan="3" class="small">Sin adjuntos.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="toolbar" style="margin-top:10px"><a class="btn btn-default" href="/admin/scopeguard">Volver</a></div>
    </div>
  </div>
  <?php
  layout_footer(); exit;
}

/* ===== ADD ITEM ===== */
if($uri==='/admin/scopeguard/item/add' && $method==='POST'){
  $crId=(int)($_GET['id']??0);
  require_once __DIR__.'/models/scopeguard_item_model.php';
  $itemModel = new scopeguard_item_model($pdo);
  $itemModel->add($crId, [
    'name'=>$_POST['name']??'',
    'qty'=>(float)($_POST['qty']??1),
    'unit_price'=>(float)($_POST['unit_price']??0),
    'hours'=>0,
    'tax_rate'=>(float)($_POST['tax_rate']??sg_tax_default()),
    'discount'=>(float)($_POST['discount']??0),
    'sort'=>0
  ]);
  redirect_to('/admin/scopeguard/view?id='.$crId);
}

/* ===== DELETE ITEM ===== */
if($uri==='/admin/scopeguard/item/del'){
  $id=(int)($_GET['id']??0); $crId=(int)($_GET['cr']??0);
  require_once __DIR__.'/models/scopeguard_item_model.php';
  (new scopeguard_item_model($pdo))->delete($id);
  redirect_to('/admin/scopeguard/view?id='.$crId);
}

/* ===== ADD COMMENT (con visibilidad + notificaciones en service) ===== */
if($uri==='/admin/scopeguard/comment' && $method==='POST'){
  $crId=(int)($_GET['id']??0);
  $body = trim($_POST['body'] ?? '');
  $visibility = ($_POST['visibility'] ?? 'internal'); // 'internal' o 'external'
  if(!in_array($visibility,['internal','external'],true)) $visibility='internal';
  $svc = new scopeguard_comment_service($pdo, $cfg);
  $svc->add_and_notify($crId, $body, 'staff', (sg_current_user()['name'] ?? 'Admin'), $visibility);
  redirect_to('/admin/scopeguard/view?id='.$crId);
}

/* ===== UPLOAD ATTACHMENT ===== */
if($uri==='/admin/scopeguard/upload' && $method==='POST'){
  $crId=(int)($_GET['id']??0);
  $updir = __DIR__.'/uploads'; @mkdir($updir,0777,true);
  if(!empty($_FILES['file']['tmp_name'])){
    $name=basename($_FILES['file']['name']);
    $safe = preg_replace('/[^a-z0-9._-]/i','_',$name);
    $dest=$updir.'/'.time().'_'.$safe;
    if(move_uploaded_file($_FILES['file']['tmp_name'],$dest)){
      require_once __DIR__.'/models/scopeguard_attachment_model.php';
      (new scopeguard_attachment_model($pdo))->add($crId,$name,'/uploads/'.basename($dest),(int)$_FILES['file']['size']);
    }
  }
  redirect_to('/admin/scopeguard/view?id='.$crId);
}

/* ===== DELETE ATTACHMENT ===== */
if($uri==='/admin/scopeguard/upload/del'){
  $id=(int)($_GET['id']??0); $crId=(int)($_GET['cr']??0);
  require_once __DIR__.'/models/scopeguard_attachment_model.php';
  (new scopeguard_attachment_model($pdo))->delete($id);
  redirect_to('/admin/scopeguard/view?id='.$crId);
}

/* ===== PÚBLICO: VER (token matriz o único) ===== */
if(preg_match('#^/scope-approve/([^/]+)$#',$uri,$m)){
  $tok=$m[1];

  // Token de matriz
  $ap = $apprModel->get_by_token($tok);
  if($ap){
    if (!empty($ap['token_expires_at']) && (int)$ap['token_expires_at'] < time()){
      http_response_code(403); exit('Enlace expirado');
    }
    $cr=$crModel->find((int)$ap['change_request_id']); if(!$cr){ http_response_code(404); exit('No encontrado'); }
    $status = $_GET['status'] ?? '';
    layout_header('Revisión de solicitud ('.$ap['role'].')', true);
    ?>
    <div class="center">
      <div class="card" style="max-width:720px;width:100%;border:1px solid var(--border)">
        <div class="card-pad">
          <p class="kicker">Solicitud de Cambio · Rol: <b><?= h($ap['role']) ?></b></p>
          <h2 class="title" style="margin-bottom:6px"><?= h($cr['title']) ?></h2>
          <p class="small" style="margin-top:0">Revisa el detalle antes de decidir:</p>

          <?php if($status==='approve'): ?>
            <div class="alert success">¡Listo! Aprobaste esta solicitud como <b><?= h($ap['role']) ?></b>.</div>
          <?php elseif($status==='reject'): ?>
            <div class="alert">Marcaste esta solicitud como <b>rechazada</b> (rol <?= h($ap['role']) ?>).</div>
          <?php endif; ?>

          <div class="grid" style="margin-top:6px">
            <div class="card" style="background:#f9fafb;border:1px solid var(--border)">
              <div class="card-pad">
                <div class="label">Descripción</div>
                <div><?= nl2br(h($cr['description'])) ?: '<span class="small">Sin descripción</span>' ?></div>
                <div class="grid" style="margin-top:12px;grid-template-columns:1fr 1fr;gap:12px">
                  <div><div class="label">Costo extra</div><strong><?= h($cr['currency']) ?> <?= number_format((float)$cr['cost_delta'],2) ?></strong></div>
                  <div><div class="label">Tiempo extra</div><strong><?= (int)$cr['time_delta_hours'] ?> horas</strong></div>
                </div>
              </div>
            </div>
          </div>

          <div class="actions" style="margin-top:16px">
            <a class="btn success" href="/scope-action/<?= urlencode($tok) ?>?do=approve">Aprobar</a>
            <a class="btn danger"  href="/scope-action/<?= urlencode($tok) ?>?do=reject">Rechazar</a>
          </div>

          <p class="small" style="margin-top:12px">Al hacer clic autorizas el cambio de alcance con impacto en costo y tiempo.</p>
        </div>
      </div>
    </div>
    <?php
    layout_footer(); exit;
  }

  // Token único
  $payload=$tokenLib->verify($tok);
  if(!$payload){ http_response_code(403); exit('Enlace inválido o expirado'); }
  $cr=$crModel->find((int)$payload['cr_id']); if(!$cr){ http_response_code(404); exit('No encontrado'); }
  $status = $_GET['status'] ?? '';
  layout_header('Revisión de solicitud', true);
  ?>
  <div class="center">
    <div class="card" style="max-width:720px;width:100%">
      <div class="card-pad">
        <p class="kicker">Solicitud de Cambio</p>
        <h2 class="title" style="margin-bottom:6px"><?= h($cr['title']) ?></h2>
        <p class="small" style="margin-top:0">Revisa el detalle antes de decidir:</p>

        <?php if($status==='approve'): ?>
          <div class="alert success">¡Listo! Aprobaste esta solicitud.</div>
        <?php elseif($status==='reject'): ?>
          <div class="alert">Marcaste esta solicitud como rechazada.</div>
        <?php endif; ?>

        <div class="grid" style="margin-top:6px">
          <div class="card" style="background:#0f1830;border:1px solid var(--border)">
            <div class="card-pad">
              <div class="label">Descripción</div>
              <div><?= nl2br(h($cr['description'])) ?: '<span class="small">Sin descripción</span>' ?></div>
              <div class="grid" style="margin-top:12px;grid-template-columns:1fr 1fr;gap:12px">
                <div><div class="label">Costo extra</div><strong><?= h($cr['currency']) ?> <?= number_format((float)$cr['cost_delta'],2) ?></strong></div>
                <div><div class="label">Tiempo extra</div><strong><?= (int)$cr['time_delta_hours'] ?> horas</strong></div>
              </div>
            </div>
          </div>
        </div>

        <div class="actions" style="margin-top:16px">
          <a class="btn success" href="/scope-action/<?= urlencode($tok) ?>?do=approve">Aprobar</a>
          <a class="btn danger"  href="/scope-action/<?= urlencode($tok) ?>?do=reject">Rechazar</a>
        </div>

        <p class="small" style="margin-top:12px">Al hacer clic autorizas el cambio de alcance con impacto en costo y tiempo.</p>
      </div>
    </div>
  </div>
  <?php
  layout_footer(); exit;
}

/* ===== PÚBLICO: ACCIÓN 1-CLIC (matriz o único) ===== */
if(preg_match('#^/scope-action/([^/]+)$#',$uri,$m)){
  $tok=$m[1];

  // Token de matriz
  $ap = $apprModel->get_by_token($tok);
  if($ap){
    if (!empty($ap['token_expires_at']) && (int)$ap['token_expires_at'] < time()){
      http_response_code(403); exit('Enlace expirado');
    }
    $cr=$crModel->find((int)$ap['change_request_id']); if(!$cr){ http_response_code(404); exit('No encontrado'); }
    $do=$_GET['do']??'';
    if($do==='approve') $apprModel->approve((int)$ap['id']);
    elseif($do==='reject') $apprModel->reject((int)$ap['id']);
    else { http_response_code(400); exit('Acción inválida'); }

    $signModel->add([
      'change_request_id'=>(int)$cr['id'],
      'signer_type'=>$ap['role'],
      'action'=>$do,
      'ip'=>$_SERVER['REMOTE_ADDR']??null,
      'user_agent'=>substr($_SERVER['HTTP_USER_AGENT']??'',0,250)
    ]);
    $auditModel->log((int)$cr['id'],'role_'.$do,['role'=>$ap['role']]);
    sg_refresh_cr_status((int)$cr['id']);

    // Webhook
    sg_webhook_fire('scopeguard.approval', [
      'change_request_id'=>(int)$cr['id'],
      'role'=>$ap['role'],
      'action'=>$do
    ]);

    redirect_to('/scope-approve/'.$tok.'?status='.$do);
  }

  // Token único
  $payload=$tokenLib->verify($tok);
  if(!$payload){ http_response_code(403); exit('Enlace inválido o expirado'); }
  $cr=$crModel->find((int)$payload['cr_id']); if(!$cr){ http_response_code(404); exit('No encontrado'); }
  $do=$_GET['do']??'';
  if($do==='approve') $crModel->approve((int)$cr['id']);
  elseif($do==='reject') $crModel->reject((int)$cr['id']);
  else { http_response_code(400); exit('Acción inválida'); }
  $signModel->add([
    'change_request_id'=>(int)$cr['id'],
    'signer_type'=>'client',
    'action'=>$do,
    'ip'=>$_SERVER['REMOTE_ADDR']??null,
    'user_agent'=>substr($_SERVER['HTTP_USER_AGENT']??'',0,250)
  ]);
  $auditModel->log((int)$cr['id'],$do,[]);

  // Webhook
  sg_webhook_fire('scopeguard.client_action', [
    'change_request_id'=>(int)$cr['id'],
    'action'=>$do
  ]);

  redirect_to('/scope-approve/'.$tok.'?status='.$do);
}

/* ===== 404 ===== */
http_response_code(404);
layout_header('404');
echo '<div class="card"><div class="card-pad">404</div></div>';
layout_footer();

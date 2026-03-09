<?php
// index.php — Quantum Astrology Portal (Dashboard)
declare(strict_types=1);

// If this is the built-in server and the file exists, serve it
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (file_exists(__DIR__ . $path) && is_file(__DIR__ . $path)) {
        return false;
    }
}

error_reporting(E_ALL);

require __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/config.php';

$requestPathForErrors = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$shouldDisplayErrors = (APP_DEBUG && !str_starts_with($requestPathForErrors, '/api/'));
ini_set('display_errors', $shouldDisplayErrors ? '1' : '0');

// Bootstrap the application router
$app = new \QuantumAstrology\Core\Application();
$app->run();

// Application::run() will handle specific pages/assets and exit.
// For the root path ('/'), it returns here to render the dashboard.

\QuantumAstrology\Core\Session::start();

use QuantumAstrology\Core\DB;
use QuantumAstrology\Core\User;
use QuantumAstrology\Core\AdminGate;

// Require login
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$uid   = (int)$_SESSION['user_id'];
$uname = isset($_SESSION['username']) ? (string)$_SESSION['username'] : 'meistro';
$portalUser = User::findById($uid);
$isAdminUser = AdminGate::canAccess($portalUser);

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$charts = [];
$counts = ['charts'=>0,'transits'=>0,'profiles'=>0,'insights'=>0,'precision'=>'99.9%'];
$schemaWarning = null;

try {
    $pdo = DB::conn();

    $stmt = $pdo->prepare("SELECT id, name, created_at FROM charts WHERE user_id = :uid ORDER BY id DESC LIMIT 200");
    $stmt->execute([':uid'=>$uid]);
    $charts = $stmt->fetchAll() ?: [];

    // count charts for this user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM charts WHERE user_id = :uid");
    $stmt->execute([':uid'=>$uid]);
    $counts['charts'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'user_id') !== false) {
        $schemaWarning = "Your database schema is missing the charts.user_id column. Run tools/migrate.php and backfill existing rows.";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quantum Astrology — Portal</title>
<meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
<link rel="stylesheet" href="/assets/css/quantum-dashboard.css?v=<?= urlencode((string) filemtime(ROOT_PATH . '/assets/css/quantum-dashboard.css')) ?>">
<style>
  :root{
    --bg:#0a0e13; --panel:rgba(255,255,255,.05); --panel2:rgba(255,255,255,.04);
    --accent1:var(--quantum-primary); --accent2:var(--quantum-purple);
    --text:var(--quantum-text); --muted:rgba(255,255,255,.7); --border:rgba(255,255,255,.1);
    --success:#2ecc71; --danger:#ff5c73; --warn:#f5c542;
  }
  *{box-sizing:border-box}
  body{margin:0;background:linear-gradient(135deg,var(--quantum-darker) 0%, var(--quantum-dark) 100%);
       color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Inter,Roboto,Arial,sans-serif}
  .container{max-width:1400px;margin:0 auto;padding:2rem}
  .page-header{text-align:center;margin-bottom:3rem}
  h1{font-size:2.5rem;letter-spacing:.2px;margin:0 0 .5rem;background:linear-gradient(135deg,var(--quantum-primary),var(--quantum-gold));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
  .subtitle{color:var(--muted);max-width:900px;line-height:1.5;margin:0 auto}
  .stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin:14px 0 26px}
  .stat{background:var(--panel);backdrop-filter:blur(20px);border:1px solid var(--border);border-radius:14px;padding:14px}
  .stat b{display:block;font-size:28px;margin-top:2px}
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
  .card{background:var(--panel);backdrop-filter:blur(20px);border:1px solid var(--border);border-radius:16px;padding:18px;min-height:150px}
  .card h3{margin:0 0 6px}
  .card p{color:var(--muted);margin:4px 0 14px}
  .btn{display:inline-block;padding:10px 14px;border-radius:12px;text-decoration:none;color:#fff;background:linear-gradient(135deg,var(--accent1),var(--accent2));font-weight:600}
  .btn.secondary{background:rgba(255,255,255,.06);color:var(--text);border:1px solid var(--border)}
  .row{display:grid;grid-template-columns:360px 1fr;gap:18px;margin-top:22px}
  ul.list{list-style:none;margin:0;padding:0;border:1px solid var(--border);border-radius:12px;max-height:62vh;overflow:auto;background:var(--panel2)}
  ul.list li{padding:11px 12px;border-bottom:1px solid var(--border);cursor:pointer}
  ul.list li:hover{background:rgba(255,255,255,.06)}
  ul.list li.active{background:linear-gradient(90deg,rgba(74,144,226,.18),rgba(139,92,246,.18));}
  .muted{color:var(--muted)}
  .imgwrap{border:1px solid var(--border);border-radius:14px;overflow:hidden;background:var(--panel2)}
  .quick{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}
  .pill{padding:8px 12px;border:1px solid var(--border);border-radius:999px;background:rgba(255,255,255,.04);color:var(--text);text-decoration:none}
  .pill.danger{border-color:#6e2231;background:rgba(255,92,115,.1);color:#ffb3bf}
  .note{background:#241d0b;border:1px solid #3a2f0a;color:#ffd166;padding:10px 12px;border-radius:10px;margin-bottom:14px}
  footer{margin:40px 0 20px;color:var(--muted);text-align:center}
  @media (max-width:980px){
    .container{padding:1rem}
    .grid{grid-template-columns:1fr}
    .row{grid-template-columns:1fr}
    .stats{grid-template-columns:1fr 1fr;row-gap:10px}
  }
</style>
</head>
<body>

<?php $showAdminLink = $isAdminUser; $activeNav = 'portal'; require __DIR__ . '/pages/_partials/portal_header.php'; ?>

<div class="container">
  <div class="page-header">
    <h1>Enter the Portal</h1>
    <div class="subtitle">Your command deck of cosmic awakening. Welcome, <b><?= htmlspecialchars($uname, ENT_QUOTES) ?></b>. One map to your astrological multiverse: charts, transits, calculations, and insights.</div>
  </div>

  <?php if (!empty($schemaWarning)): ?>
    <div class="note"><?= htmlspecialchars($schemaWarning, ENT_QUOTES) ?></div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat"><span class="muted">Charts Generated</span><b><?= number_format($counts['charts']) ?></b></div>
    <div class="stat"><span class="muted">Active Transits</span><b><?= number_format($counts['transits']) ?></b></div>
    <div class="stat"><span class="muted">Cosmic Profiles</span><b><?= number_format($counts['profiles']) ?></b></div>
    <div class="stat"><span class="muted">Insights Generated</span><b><?= number_format($counts['insights']) ?></b></div>
    <div class="stat"><span class="muted">Swiss Precision</span><b><?= $counts['precision'] ?></b></div>
  </div>

  <div class="grid">
    <div class="card">
      <h3>Natal Chart Generator</h3>
      <p>Align your energy centers with Swiss Ephemeris precision for chart activation and mapping.</p>
      <a class="btn" href="/charts/create">Generate Chart</a>
    </div>
    <div class="card">
      <h3>Chart Library</h3>
      <p>Access your digital archive of cosmic blueprints — curated and secure.</p>
      <a class="btn" href="/viewer.php">Browse Charts</a>
    </div>
    <div class="card">
      <h3>Transit Analysis</h3>
      <p>Track real-time planetary movements and their influence on your energy matrix.</p>
      <a class="btn secondary" href="/charts/transits">Analyze Transits</a>
    </div>
    <div class="card">
      <h3>Progression Engine</h3>
      <p>Secondary progressions, solar returns, and evolutionary timelines.</p>
      <a class="btn secondary" href="/charts/progressions">Calculate</a>
    </div>
    <div class="card">
      <h3>Timing Optimizer</h3>
      <p>Find optimal electional windows and synchronicity patterns.</p>
      <a class="btn secondary" href="/forecasting">Find Timing</a>
    </div>
    <div class="card">
      <h3>Relationship Matrix</h3>
      <p>Synastry & composite insights for meaningful connections.</p>
      <a class="btn secondary" href="/charts/relationships">Compare Charts</a>
    </div>
  </div>

  <div class="row">
    <div>
      <h3>Your Charts</h3>
      <ul id="list" class="list">
        <?php if (!$charts): ?>
          <li class="muted">No charts yet. Click “Generate Chart”.</li>
        <?php else: foreach ($charts as $i => $c): ?>
          <li data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['name'] ?? 'Untitled', ENT_QUOTES) ?>"<?= $i===0 ? ' class="active"' : '' ?>>
            #<?= (int)$c['id'] ?> — <?= htmlspecialchars($c['name'] ?? 'Untitled', ENT_QUOTES) ?>
          </li>
        <?php endforeach; endif; ?>
      </ul>
    </div>
    <div>
      <h3 id="title">Preview</h3>
      <div class="imgwrap">
        <img id="wheel" alt="Chart wheel" src="" style="display:block;width:100%;height:auto">
      </div>
      <div class="quick">
        <a class="pill" href="/charts/create">+ New Chart</a>
        <a class="pill" href="/viewer.php">Open Wheel Viewer</a>
        <button id="deleteBtn" class="pill danger" type="button">Delete Selected</button>
        <a class="pill" href="/settings">Settings</a>
      </div>
      <p class="muted" style="margin-top:8px">Tip: the preview updates as you click items in your list.</p>
    </div>
  </div>

  <footer>© <?= date('Y') ?> Quantum Minds United. Built with love, curiosity, and a dash of cosmic precision.</footer>
</div>

<?php if ($charts): ?>
<script>
(function(){
  const list  = document.getElementById('list');
  const wheel = document.getElementById('wheel');
  const title = document.getElementById('title');
  const del   = document.getElementById('deleteBtn');
  const csrf  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  function srcFor(id){ return `/api/chart_svg.php?id=${encodeURIComponent(id)}&size=900&ts=${Date.now()}`; }

  function activeItem(){
    return list.querySelector('li.active[data-id]') || list.querySelector('li[data-id]');
  }

  function setActive(li){
    [...list.children].forEach(n=>n.classList.remove('active'));
    if (li) li.classList.add('active');
  }

  function preview(li){
    if(!li) return;
    const id = li.dataset.id, name = li.dataset.name || li.textContent.trim();
    title.textContent = `Preview — #${id} ${name}`;
    const next = srcFor(id);
    if (wheel.src === next) { wheel.src = 'about:blank'; setTimeout(()=>{ wheel.src = next; }, 0); }
    else { wheel.src = next; }
  }

  list?.addEventListener('click', (e)=>{
    const li = e.target.closest('li[data-id]');
    if(!li) return;
    setActive(li);
    preview(li);
  });

  del?.addEventListener('click', async ()=>{
    const li = activeItem();
    if(!li){ alert('No chart selected.'); return; }
    const id = li.dataset.id, name = li.dataset.name || li.textContent.trim();
    if(!confirm(`Delete chart #${id} — ${name}? This cannot be undone.`)) return;

    try {
      const res = await fetch('/api/chart_delete.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: Number(id), csrf})
      });
      const data = await res.json();
      if(!res.ok || !data.ok) throw new Error((data.error && data.error.message) || `HTTP ${res.status}`);

      // Remove item from list
      const next = li.nextElementSibling?.dataset.id ? li.nextElementSibling :
                   li.previousElementSibling?.dataset.id ? li.previousElementSibling : null;
      li.remove();

      if(next){ setActive(next); preview(next); }
      else { title.textContent = 'Preview'; wheel.src='about:blank'; }

    } catch (err) {
      console.error(err);
      alert('Failed to delete chart: ' + err.message);
    }
  });

  // auto-load first
  const first = activeItem();
  if(first){ preview(first); }
})();
</script>
<?php endif; ?>

</body>
</html>

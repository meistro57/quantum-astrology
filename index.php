<?php
// index.php — Quantum Astrology Portal (Dashboard)
// Requires: PHP 8+, sessions, classes/autoload.php, classes/Core/DB.php
declare(strict_types=1);
session_start();

require __DIR__ . '/classes/autoload.php';

use QuantumAstrology\Core\DB;

$isAuthed = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
if (!$isAuthed) {
    header('Location: /login');
    exit;
}

$uid   = (int)$_SESSION['user_id'];
$uname = isset($_SESSION['username']) ? (string)$_SESSION['username'] : 'meistro';

$charts = [];
$counts = ['charts'=>0,'transits'=>0,'profiles'=>0,'insights'=>0,'precision'=>'99.9%'];

try {
    $pdo = DB::conn();

    // Charts list (only this user)
    $stmt = $pdo->prepare("SELECT id, name, created_at FROM charts WHERE user_id = :uid ORDER BY id DESC LIMIT 200");
    $stmt->execute([':uid'=>$uid]);
    $charts = $stmt->fetchAll() ?: [];

    // Counts (graceful fallbacks if tables don’t exist yet)
    $counts['charts'] = (int)($pdo->prepare("SELECT COUNT(*) FROM charts WHERE user_id = :uid")->execute([':uid'=>$uid]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : count($charts));
} catch (Throwable $e) {
    // If the repo hasn't added user_id to charts yet, explain nicely.
    if (strpos($e->getMessage(), 'user_id') !== false) {
        $charts = [];
        $counts['charts'] = 0;
        $schemaWarning = "Your database schema is missing the charts.user_id column. Add it and backfill existing rows so the dashboard can scope charts per user.";
    } else {
        $schemaWarning = null; // Silent fail for other cases
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quantum Astrology — Portal</title>
<style>
  :root{
    --bg:#0b0e14; --panel:#121725; --panel2:#161b2b; --accent1:#7b5cff; --accent2:#5cc8ff;
    --text:#e6e8ef; --muted:#98a0b3; --border:#232a40; --success:#2ecc71; --danger:#ff5c73; --warn:#f5c542;
  }
  *{box-sizing:border-box}
  body{margin:0;background:radial-gradient(1200px 800px at 30% -10%,#0f1530 0%,#0b0e14 60%), #0b0e14;
       color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Inter,Roboto,Arial,sans-serif}
  header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);background:rgba(10,13,20,.65);backdrop-filter:blur(6px);position:sticky;top:0;z-index:10}
  .brand{display:flex;align-items:center;gap:10px;font-weight:700}
  .brand .dot{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent1),var(--accent2))}
  nav a{color:var(--text);opacity:.85;text-decoration:none;margin-left:16px}
  nav a:hover{opacity:1}
  .active{border-bottom:2px solid var(--accent2);padding-bottom:4px}
  .container{max-width:1200px;margin:24px auto;padding:0 20px}
  h1{font-size:42px;letter-spacing:.3px;margin:10px 0 6px}
  .subtitle{color:var(--muted);max-width:900px;line-height:1.5;margin-bottom:22px}
  .stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin:14px 0 26px}
  .stat{background:linear-gradient(180deg,var(--panel),#0f1424);border:1px solid var(--border);border-radius:14px;padding:14px}
  .stat b{display:block;font-size:28px;margin-top:2px}
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
  .card{background:linear-gradient(180deg,var(--panel),#0f1424);border:1px solid var(--border);border-radius:16px;padding:18px;min-height:150px}
  .card h3{margin:0 0 6px}
  .card p{color:var(--muted);margin:4px 0 14px}
  .btn{display:inline-block;padding:10px 14px;border-radius:12px;text-decoration:none;color:#0b0e14;background:linear-gradient(135deg,var(--accent1),var(--accent2));font-weight:600}
  .btn.secondary{background:transparent;color:var(--text);border:1px solid var(--border)}
  .row{display:grid;grid-template-columns:360px 1fr;gap:18px;margin-top:22px}
  ul.list{list-style:none;margin:0;padding:0;border:1px solid var(--border);border-radius:12px;max-height:62vh;overflow:auto;background:var(--panel2)}
  ul.list li{padding:11px 12px;border-bottom:1px solid var(--border);cursor:pointer}
  ul.list li:hover{background:#0f1424}
  ul.list li.active{background:linear-gradient(90deg,rgba(123,92,255,.18),rgba(92,200,255,.18));}
  .muted{color:var(--muted)}
  .imgwrap{border:1px solid var(--border);border-radius:14px;overflow:hidden;background:#fff}
  .quick{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
  .pill{padding:8px 12px;border:1px solid var(--border);border-radius:999px;background:rgba(255,255,255,.04);color:var(--text);text-decoration:none}
  .warn{color:#ffd166}
  footer{margin:40px 0 20px;color:var(--muted);text-align:center}
  .note{background:#241d0b;border:1px solid #3a2f0a;color:#ffd166;padding:10px 12px;border-radius:10px;margin-bottom:14px}
  @media (max-width:980px){
    .grid{grid-template-columns:1fr}
    .row{grid-template-columns:1fr}
    .stats{grid-template-columns:1fr 1fr;row-gap:10px}
  }
</style>
</head>
<body>

<header>
  <div class="brand"><div class="dot"></div> Quantum Astrology <span style="opacity:.6;font-weight:500;margin-left:8px">· Quantum Minds United</span></div>
  <nav>
    <a class="active" href="/">Portal</a>
    <a href="/viewer.php">Charts</a>
    <a href="/reports">Reports</a>
    <a href="/profile">Profile</a>
    <a href="/logout">Logout</a>
  </nav>
</header>

<div class="container">
  <h1>Enter the Portal</h1>
  <div class="subtitle">Your command deck of cosmic awakening. Welcome, <b><?= htmlspecialchars($uname, ENT_QUOTES) ?></b>. One map to your astrological multiverse: charts, transits, calculations, and insights.</div>

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
      <a class="btn secondary" href="/transits">Analyze Transits</a>
    </div>
    <div class="card">
      <h3>Progression Engine</h3>
      <p>Secondary progressions, solar returns, and evolutionary timelines.</p>
      <a class="btn secondary" href="/progressions">Calculate</a>
    </div>
    <div class="card">
      <h3>Timing Optimizer</h3>
      <p>Find optimal electional windows and synchronicity patterns.</p>
      <a class="btn secondary" href="/timing">Find Timing</a>
    </div>
    <div class="card">
      <h3>Relationship Matrix</h3>
      <p>Synastry & composite insights for meaningful connections.</p>
      <a class="btn secondary" href="/relationships">Compare Charts</a>
    </div>
  </div>

  <div class="row">
    <div>
      <h3>Your Charts</h3>
      <ul id="list" class="list">
        <?php if (!$charts): ?>
          <li class="muted">No charts yet. Click “Generate Chart”.</li>
        <?php else: foreach ($charts as $i => $c): ?>
          <li data-id="<?= (int)$c['id'] ?>"<?= $i===0 ? ' class="active"' : '' ?>>
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

  function srcFor(id){ return `/api/chart_svg.php?id=${encodeURIComponent(id)}&size=900&ts=${Date.now()}`; }

  function preview(id, label){
    if(!id) return;
    title.textContent = `Preview — #${id} ${label || ''}`.trim();
    const next = srcFor(id);

    // If the URL would be identical (very fast re-click), force a hard refresh
    if (wheel.src === next) {
      wheel.src = 'about:blank';
      setTimeout(()=>{ wheel.src = next; }, 0);
    } else {
      wheel.src = next;
    }
  }

  // Click selection (event delegation)
  list?.addEventListener('click', (e)=>{
    const li = e.target.closest('li[data-id]');
    if(!li) return;
    [...list.children].forEach(n=>n.classList.remove('active'));
    li.classList.add('active');
    preview(li.dataset.id, li.dataset.name || li.textContent.trim());
  });

  // Keyboard support (up/down + enter)
  list?.addEventListener('keydown', (e)=>{
    const items = [...list.querySelectorAll('li[data-id]')];
    const idx = items.findIndex(n=>n.classList.contains('active'));
    if(e.key === 'ArrowDown' && idx < items.length-1){ e.preventDefault(); items[idx+1]?.click(); }
    if(e.key === 'ArrowUp'   && idx > 0){ e.preventDefault(); items[idx-1]?.click(); }
    if(e.key === 'Enter' && idx >= 0){ e.preventDefault(); items[idx]?.click(); }
  });

  // Auto-load first item on ready
  window.addEventListener('DOMContentLoaded', ()=>{
    const first = list?.querySelector('li[data-id]');
    if(first){ first.classList.add('active'); preview(first.dataset.id, first.dataset.name || first.textContent.trim()); }
  });
})();
</script>

<?php endif; ?>

</body>
</html>

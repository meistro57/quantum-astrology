<?php
// viewer.php — Standalone Wheel Viewer
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/autoload.php';

\QuantumAstrology\Core\Session::start();
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quantum Astrology — Wheel Viewer</title>
<meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
<link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
<style>
  .viewer-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 20px;
  }
  .viewer-header {
    margin-bottom: 30px;
  }
  .viewer-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--quantum-gold), var(--quantum-blue));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 20px;
  }
  .viewer-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 30px;
    align-items: start;
  }
  .charts-panel {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(15px);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    overflow: hidden;
  }
  .charts-panel h3 {
    padding: 20px;
    margin: 0;
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 1.1rem;
    color: var(--quantum-gold);
  }
  .charts-list {
    list-style: none;
    padding: 0;
    margin: 0;
    max-height: 60vh;
    overflow-y: auto;
  }
  .charts-list li {
    padding: 15px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--quantum-text-muted);
  }
  .charts-list li:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--quantum-text);
  }
  .charts-list li.active {
    background: linear-gradient(135deg, var(--quantum-blue), var(--quantum-purple));
    color: #fff;
  }
  .charts-list li.empty {
    color: var(--quantum-text-muted);
    font-style: italic;
    cursor: default;
  }
  .charts-list li.empty:hover {
    background: transparent;
  }
  .preview-panel {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(15px);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 30px;
    min-height: 500px;
  }
  .preview-panel h3 {
    margin: 0 0 20px 0;
    font-size: 1.3rem;
    color: var(--quantum-gold);
  }
  .preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
  }
  .preview-header h3 {
    margin: 0;
  }
  .preview-panel img {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    background: #0f1424;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
  }
  .btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    color: var(--quantum-text);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
  }
  .btn-back:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
  }
  .btn-danger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid rgba(220, 53, 69, 0.45);
    background: rgba(220, 53, 69, 0.2);
    color: #ffb3bf;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
  }
  .btn-danger:hover {
    background: rgba(220, 53, 69, 0.32);
  }
  .btn-danger:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  .toast-notice {
    position: fixed;
    right: 1.25rem;
    bottom: 1.25rem;
    z-index: 1200;
    background: rgba(34, 197, 94, 0.18);
    border: 1px solid rgba(34, 197, 94, 0.42);
    color: #86efac;
    padding: 0.8rem 1rem;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.25s ease, transform 0.25s ease;
    pointer-events: none;
  }
  .toast-notice.visible {
    opacity: 1;
    transform: translateY(0);
  }
  @media (max-width: 900px) {
    .viewer-grid {
      grid-template-columns: 1fr;
    }
    .charts-list {
      max-height: 200px;
    }
  }
</style>
</head>
<body>
  <div class="particles-bg" id="particles"></div>

  <div class="viewer-container">
    <div class="viewer-header">
      <h1>Wheel Viewer</h1>
      <a class="btn-back" href="/">← Back to Dashboard</a>
    </div>

    <div class="viewer-grid">
      <div class="charts-panel">
        <h3>Your Charts</h3>
        <ul class="charts-list" id="list">
          <li class="empty">Loading...</li>
        </ul>
      </div>

      <div class="preview-panel">
        <div class="preview-header">
          <h3 id="title">Select a Chart</h3>
          <button type="button" id="deleteBtn" class="btn-danger" disabled>Delete Selected</button>
        </div>
        <img id="wheel" alt="Chart wheel" src="" style="display:none;" />
      </div>
    </div>
  </div>
  <div id="delete-toast" class="toast-notice" role="status" aria-live="polite"></div>

<script>
// Particle animation
const particlesBg = document.getElementById('particles');
for (let i = 0; i < 50; i++) {
  const particle = document.createElement('div');
  particle.className = 'particle';
  particle.style.cssText = `
    left: ${Math.random() * 100}%;
    top: ${Math.random() * 100}%;
    width: ${Math.random() * 4 + 2}px;
    height: ${Math.random() * 4 + 2}px;
    animation-delay: ${Math.random() * 6}s;
    animation-duration: ${Math.random() * 4 + 4}s;
  `;
  particlesBg.appendChild(particle);
}

const list = document.getElementById('list');
const title = document.getElementById('title');
const wheel = document.getElementById('wheel');
const deleteBtn = document.getElementById('deleteBtn');
const deleteToast = document.getElementById('delete-toast');
const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let selectedChart = null;

function showDeleteToast(message) {
  if (!deleteToast) return;
  deleteToast.textContent = message;
  deleteToast.classList.add('visible');
  window.setTimeout(() => deleteToast.classList.remove('visible'), 2600);
}

async function deleteSelectedChart() {
  if (!selectedChart) return;
  const confirmed = window.confirm(`Delete chart #${selectedChart.id} — ${selectedChart.name}? This cannot be undone.`);
  if (!confirmed) return;

  try {
    const res = await fetch('/api/chart_delete.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id: Number(selectedChart.id), csrf})
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error((data?.error?.message) || `HTTP ${res.status}`);
    }

    const li = list.querySelector(`li[data-id="${selectedChart.id}"]`);
    const next = li?.nextElementSibling?.dataset?.id ? li.nextElementSibling :
                 li?.previousElementSibling?.dataset?.id ? li.previousElementSibling : null;
    if (li) li.remove();

    selectedChart = null;
    if (next) {
      next.click();
    } else {
      title.textContent = 'Select a Chart';
      wheel.src = '';
      wheel.style.display = 'none';
      deleteBtn.disabled = true;
      list.innerHTML = '<li class="empty">No charts yet. Create one from the dashboard!</li>';
    }

    showDeleteToast('Chart deleted.');
  } catch (err) {
    alert('Failed to delete chart: ' + (err.message || 'Unknown error'));
  }
}

deleteBtn?.addEventListener('click', deleteSelectedChart);

fetch('/api/charts_list.php').then(r=>r.json()).then(({ok,charts,error})=>{
  if(!ok || !charts || charts.length===0){
    list.innerHTML = '<li class="empty">No charts yet. Create one from the dashboard!</li>';
    return;
  }
  list.innerHTML='';
  charts.forEach((c,i)=>{
    const li=document.createElement('li');
    li.dataset.id = String(c.id);
    li.dataset.name = String(c.name || 'Untitled');
    li.textContent = `#${c.id} — ${c.name}`;
    li.onclick = ()=>{
      [...list.children].forEach(n=>n.classList.remove('active'));
      li.classList.add('active');
      selectedChart = { id: Number(c.id), name: String(c.name || 'Untitled') };
      deleteBtn.disabled = false;
      title.textContent = `Preview — #${c.id} ${c.name}`;
      wheel.style.display = 'block';
      wheel.src = `api/chart_svg.php?id=${c.id}&size=900&ts=${Date.now()}`;
    };
    list.appendChild(li);
    if(i===0) li.click();
  });
}).catch(err => {
  list.innerHTML = '<li class="empty">Error loading charts. Please try again.</li>';
  console.error('Failed to load charts:', err);
});
</script>
</body>
</html>

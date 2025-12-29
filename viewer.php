<?php
// viewer.php — Standalone Wheel Viewer
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quantum Astrology — Wheel Viewer</title>
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
  .preview-panel img {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    background: #fff;
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
        <h3 id="title">Select a Chart</h3>
        <img id="wheel" alt="Chart wheel" src="" style="display:none;" />
      </div>
    </div>
  </div>

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

fetch('/api/charts_list.php').then(r=>r.json()).then(({ok,charts,error})=>{
  if(!ok || !charts || charts.length===0){
    list.innerHTML = '<li class="empty">No charts yet. Create one from the dashboard!</li>';
    return;
  }
  list.innerHTML='';
  charts.forEach((c,i)=>{
    const li=document.createElement('li');
    li.textContent = `#${c.id} — ${c.name}`;
    li.onclick = ()=>{
      [...list.children].forEach(n=>n.classList.remove('active'));
      li.classList.add('active');
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

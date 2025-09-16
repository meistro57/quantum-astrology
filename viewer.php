<?php
// viewer.php — Standalone Wheel Viewer
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quantum Astrology — Wheel Viewer</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Inter,Roboto,Arial,sans-serif;margin:24px;color:#222}
  .row{display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start}
  ul{list-style:none;padding:0;margin:0;border:1px solid #ddd;border-radius:10px;max-height:70vh;overflow:auto}
  li{padding:10px 12px;border-bottom:1px solid #eee;cursor:pointer}
  li:hover{background:#f7f7f7}
  li.active{background:#111;color:#fff}
  .empty{color:#666}
  img{max-width:100%;height:auto;border:1px solid #ddd;border-radius:12px;background:#fff}
  a.btn{display:inline-block;margin-bottom:12px;padding:8px 12px;border:1px solid #ddd;border-radius:10px;text-decoration:none;color:#222}
  a.btn:hover{background:#f7f7f7}
</style>
</head>
<body>
  <h1>Quantum Astrology — Wheel Viewer</h1>
  <a class="btn" href="/">← Back to Home</a>
  <div class="row">
    <div>
      <h3>Charts</h3>
      <ul id="list"><li class="empty">Loading…</li></ul>
    </div>
    <div>
      <h3 id="title">Preview</h3>
      <img id="wheel" alt="Chart wheel" src="" />
    </div>
  </div>
<script>
const list = document.getElementById('list');
const title = document.getElementById('title');
const wheel = document.getElementById('wheel');

fetch('/api/charts_list.php').then(r=>r.json()).then(({ok,charts})=>{
  if(!ok || charts.length===0){ list.innerHTML = '<li class="empty">No charts yet.</li>'; return; }
  list.innerHTML='';
  charts.forEach((c,i)=>{
    const li=document.createElement('li');
    li.textContent = `#${c.id} — ${c.name}`;
    li.onclick = ()=>{
      [...list.children].forEach(n=>n.classList.remove('active')); li.classList.add('active');
      title.textContent = `Preview — #${c.id} ${c.name}`;
      wheel.src = `api/chart_svg.php?id=${c.id}&size=900&ts=${Date.now()}`;
    };
    list.appendChild(li);
    if(i===0) li.click();
  });
});
</script>
</body>
</html>

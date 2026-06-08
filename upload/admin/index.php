<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['ingelogd'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin – De Glansbus</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Inter, -apple-system, sans-serif; background: #f4f5f7; color: #1a1c1e; font-size: 0.9rem; }
    header { background: #1a1c1e; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
    header h1 { color: #fff; font-size: 1rem; font-weight: 700; }
    .uitloggen { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: rgba(255,255,255,0.7); padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-family: inherit; text-decoration: none; }
    .stats { display: flex; gap: 16px; padding: 24px 32px 0; }
    .stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 24px; min-width: 140px; }
    .stat-label { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
    .stat-value { font-size: 1.8rem; font-weight: 800; letter-spacing: -1px; }
    .stat-value.ongelezen { color: #2563eb; }
    .container { padding: 24px 32px; }
    .filter-bar { display: flex; gap: 8px; margin-bottom: 16px; }
    .filter-btn { padding: 6px 14px; border-radius: 6px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; font-size: 0.82rem; color: #6b7280; }
    .filter-btn.actief { background: #1a1c1e; color: #fff; border-color: #1a1c1e; }
    table { width: 100%; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; border-collapse: separate; border-spacing: 0; overflow: hidden; }
    thead th { background: #f9fafb; padding: 12px 16px; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e5e7eb; }
    tbody tr { border-bottom: 1px solid #f3f4f6; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: #fafafa; }
    tbody tr.ongelezen { background: #eff6ff; }
    td { padding: 14px 16px; vertical-align: top; }
    .badge-nieuw { display: inline-block; background: #2563eb; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-left: 6px; }
    .naam-cel { font-weight: 600; }
    .dienst-tag { display: inline-block; background: #f3f4f6; color: #374151; font-size: 0.75rem; padding: 3px 8px; border-radius: 5px; }
    .bericht-cel { max-width: 280px; color: #4b5563; word-break: break-word; }
    .datum-cel { color: #9ca3af; font-size: 0.8rem; white-space: nowrap; }
    .acties { display: flex; gap: 6px; }
    .btn { padding: 5px 10px; border-radius: 6px; border: 1px solid #e5e7eb; cursor: pointer; font-size: 0.78rem; background: #fff; color: #374151; font-family: inherit; }
    .btn-delete { border-color: #fecaca; color: #dc2626; }
    .btn-delete:hover { background: #fef2f2; }
    .leeg { text-align: center; padding: 64px; color: #9ca3af; }
  </style>
</head>
<body>

<header>
  <h1>De Glansbus — Admin</h1>
  <a href="logout.php" class="uitloggen">Uitloggen</a>
</header>

<div class="stats">
  <div class="stat"><div class="stat-label">Totaal leads</div><div class="stat-value" id="stat-totaal">–</div></div>
  <div class="stat"><div class="stat-label">Ongelezen</div><div class="stat-value ongelezen" id="stat-ongelezen">–</div></div>
  <div class="stat"><div class="stat-label">Vandaag</div><div class="stat-value" id="stat-vandaag">–</div></div>
</div>

<div class="container">
  <div class="filter-bar">
    <button class="filter-btn actief" onclick="setFilter('alle')">Alle</button>
    <button class="filter-btn" onclick="setFilter('ongelezen')">Ongelezen</button>
    <button class="filter-btn" onclick="setFilter('gelezen')">Gelezen</button>
  </div>
  <div id="tabel-container">Laden...</div>
</div>

<script>
  let leads = [], filter = 'alle';

  const DIENST = { exterior:'Exterior Detail', prowash:'Pro Wash', fulldetail:'Full Detail', andere:'Andere' };

  async function laad() {
    const res = await fetch('api.php?pad=leads');
    leads = await res.json();
    renderStats(); renderTabel();
  }

  function renderStats() {
    const vandaag = new Date().toLocaleDateString('nl-BE');
    document.getElementById('stat-totaal').textContent = leads.length;
    document.getElementById('stat-ongelezen').textContent = leads.filter(l => !l.gelezen).length;
    document.getElementById('stat-vandaag').textContent = leads.filter(l => (l.aangemaakt_op||'').startsWith(vandaag.split('/').reverse().join('/'))||false).length;
  }

  function setFilter(f) {
    filter = f;
    document.querySelectorAll('.filter-btn').forEach((b,i) => b.classList.toggle('actief', ['alle','ongelezen','gelezen'][i]===f));
    renderTabel();
  }

  function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  function renderTabel() {
    let rijen = leads;
    if (filter==='ongelezen') rijen = leads.filter(l=>!l.gelezen);
    if (filter==='gelezen')   rijen = leads.filter(l=>l.gelezen);

    if (!rijen.length) { document.getElementById('tabel-container').innerHTML='<div class="leeg">Geen leads.</div>'; return; }

    document.getElementById('tabel-container').innerHTML = `<table>
      <thead><tr><th>Naam</th><th>Contact</th><th>Dienst</th><th>Bericht</th><th>Datum</th><th>Acties</th></tr></thead>
      <tbody>${rijen.map(l=>`<tr class="${l.gelezen?'':'ongelezen'}" id="rij-${l.id}">
        <td class="naam-cel">${esc(l.naam)}${!l.gelezen?'<span class="badge-nieuw">NIEUW</span>':''}</td>
        <td><a href="mailto:${esc(l.email)}">${esc(l.email)}</a><br><span style="color:#9ca3af">${esc(l.telefoon)}</span></td>
        <td>${l.dienst?`<span class="dienst-tag">${DIENST[l.dienst]||esc(l.dienst)}</span>`:'–'}</td>
        <td class="bericht-cel">${esc(l.bericht||'–')}</td>
        <td class="datum-cel">${esc(l.aangemaakt_op)}</td>
        <td><div class="acties">
          ${!l.gelezen?`<button class="btn" onclick="gelezen(${l.id})">✓ Gelezen</button>`:''}
          <button class="btn btn-delete" onclick="verwijder(${l.id})">Verwijder</button>
        </div></td>
      </tr>`).join('')}</tbody></table>`;
  }

  async function gelezen(id) {
    await fetch(`api.php?pad=leads/${id}/gelezen`, {method:'POST'});
    const l = leads.find(l=>l.id===id); if(l) l.gelezen=true;
    renderStats(); renderTabel();
  }

  async function verwijder(id) {
    if (!confirm('Lead verwijderen?')) return;
    await fetch(`api.php?pad=leads/${id}`, {method:'DELETE'});
    leads = leads.filter(l=>l.id!==id);
    renderStats(); renderTabel();
  }

  laad();
</script>
</body>
</html>

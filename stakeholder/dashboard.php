<?php
declare(strict_types=1);

require_once __DIR__ . '/../components/auth.php';

require_login();
require_role(['admin', 'technician', 'equipment_user']);
// If you want STRICT stakeholder-only, change to: require_role(['equipment_user']);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Machine Status Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
    input, select, button { padding: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background: #f5f5f5; }
    .badge { padding: 4px 10px; border-radius: 999px; display: inline-block; font-size: 12px; }
  </style>
</head>
<body>

<h1>Machine Status Dashboard</h1>
<p>
  Logged in as: <strong><?= e((string)$_SESSION['role']) ?></strong>
</p>

<div class="row">
  <input id="q" type="text" placeholder="Search machine or part name..." maxlength="100">
  <select id="status">
    <option value="">All Status</option>
    <option value="Pending">Pending</option>
    <option value="In Progress">In Progress</option>
    <option value="Completed">Completed</option>
    <option value="Under Maintenance">Under Maintenance</option>
    <option value="Unknown">Unknown</option>
  </select>
  <button id="refreshBtn" type="button">Refresh</button>
</div>

<table>
  <thead>
    <tr>
      <th>Machine</th>
      <th>Part</th>
      <th>Status</th>
      <th>Last Updated</th>
    </tr>
  </thead>
  <tbody id="tbody">
    <tr><td colspan="4">Loading...</td></tr>
  </tbody>
</table>

<script>
  const tbody = document.getElementById('tbody');
  const qEl = document.getElementById('q');
  const statusEl = document.getElementById('status');
  const refreshBtn = document.getElementById('refreshBtn');

  function esc(s) {
    return String(s ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function badge(status) {
    const safe = esc(status);
    return `<span class="badge">${safe}</span>`;
  }

  async function loadData() {
    const q = qEl.value.trim();
    const status = statusEl.value;

    const url = new URL('/stakeholder/status_api.php', window.location.origin);
    if (q) url.searchParams.set('q', q);
    if (status) url.searchParams.set('status', status);

    tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;

    try {
      const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
      const json = await res.json();

      if (!json.ok) throw new Error('API returned not ok');

      const rows = json.data || [];
      if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="4">No results.</td></tr>`;
        return;
      }

      tbody.innerHTML = rows.map(r => `
        <tr>
          <td>${esc(r.machine_name)}</td>
          <td>${esc(r.part_name)}</td>
          <td>${badge(r.status)}</td>
          <td>${esc(r.updated_at)}</td>
        </tr>
      `).join('');
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="4">Failed to load data.</td></tr>`;
    }
  }

  refreshBtn.addEventListener('click', loadData);
  qEl.addEventListener('input', () => {
    // small debounce
    clearTimeout(window.__t);
    window.__t = setTimeout(loadData, 250);
  });
  statusEl.addEventListener('change', loadData);

  // “Real-time” refresh every 10s (adjust as needed)
  loadData();
  setInterval(loadData, 10000);
</script>

</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../components/auth.php';

require_login();
require_role(['admin', 'technician', 'equipment_user']);

$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Stakeholder Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; padding: 16px; }
    nav a { margin-right: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f5f5f5; }
    .pill { padding: 2px 8px; border-radius: 999px; background: #eee; display: inline-block; }
  </style>
</head>
<body>
  <nav>
    <a href="/swap/swaps/index.php">Home</a>
    <a href="/swap/swaps/stakeholder/dashboard.php">Dashboard</a>
    <a href="/swap/swaps/public/logout.php">Logout</a>
  </nav>

  <h2>Machine Status Dashboard</h2>
  <p>Logged in as: <b><?= e($user['username'] ?? 'user') ?></b> (<span class="pill"><?= e($user['role'] ?? 'role') ?></span>)</p>

  <label>Search by machine / part:</label><br>
  <input id="q" placeholder="e.g., CNC / PART-001" style="width:320px;">
  <button id="btn">Search</button>
  <button id="clear">Clear</button>

  <p id="msg"></p>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Machine</th>
        <th>Part</th>
        <th>Status</th>
        <th>Next Maintenance</th>
        <th>Notes</th>
        <th>Last Updated</th>
      </tr>
    </thead>
    <tbody id="rows">
      <tr><td colspan="7">Loading...</td></tr>
    </tbody>
  </table>

<script>
const rows = document.getElementById('rows');
const msg = document.getElementById('msg');
const qEl = document.getElementById('q');

async function loadData() {
  const q = qEl.value.trim();
  const url = `/swap/swaps/stakeholder/status_api.php` + (q ? `?q=${encodeURIComponent(q)}` : '');

  try {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();

    if (!data.ok) throw new Error('API returned not ok');

    const list = data.data || [];
    if (list.length === 0) {
      rows.innerHTML = `<tr><td colspan="7">No results found.</td></tr>`;
      msg.textContent = '';
      return;
    }

    rows.innerHTML = list.map(r => `
      <tr>
        <td>${r.id}</td>
        <td>${r.machine_number ?? ''}</td>
        <td>${r.part_number ?? ''}</td>
        <td><span class="pill">${r.status ?? 'Unknown'}</span></td>
        <td>${r.next_maintenance_date ?? ''}</td>
        <td>${r.notes ?? ''}</td>
        <td>${r.updated_at ?? r.created_at ?? ''}</td>
      </tr>
    `).join('');

    msg.textContent = `Showing ${list.length} record(s).`;
  } catch (e) {
    rows.innerHTML = `<tr><td colspan="7">Error loading data. Check DB + API.</td></tr>`;
    msg.textContent = '';
  }
}

document.getElementById('btn').addEventListener('click', loadData);
document.getElementById('clear').addEventListener('click', () => { qEl.value=''; loadData(); });

// Auto-refresh every 10s (real-time feel)
setInterval(loadData, 10000);
loadData();
</script>
</body>
</html>

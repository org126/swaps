<?php
/*
 * show_all.php
 * Simple development tool: connect to a MySQL database via PDO and display
 * table contents in HTML. Intended for local/dev usage only (do NOT use in
 * production as-is; it exposes DB contents and accepts credentials via GET).
 *
 * Query params:
 *  - host (default 127.0.0.1)
 *  - port (default 3306)
 *  - db   (database/schema name) REQUIRED to connect and list tables
 *  - user (default root)
 *  - pass (password for DB user)
 *  - limit (optional row limit per table; default 100)
 *  - table (optional specific table to display)
 *  - show_all (if set to 1, ignores limit and shows all rows for the selected table)
 */

// Escape helper for outputting HTML-safe values
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Connection and UI defaults (overridden by GET params)
$host = $_GET['host'] ?? '127.0.0.1';
$port = $_GET['port'] ?? '3306';
$db   = $_GET['db']   ?? '';
$user = $_GET['user'] ?? 'root';
$pass = $_GET['pass'] ?? '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

// State holders
$error = null;
$tables = [];

// If a database/schema name is provided, attempt to connect and list tables
if($db){
  try{
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    // Create PDO with exceptions enabled and associative fetch mode
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Query information_schema for the list of tables in the requested schema
    $stmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME");
    $stmt->execute([':schema' => $db]);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
  }catch(Exception $e){
    // Capture error message for display in the page
    $error = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DB Viewer - <?=h($db ?: 'no-db')?></title>
  <style>
    body{font-family:Inter,Segoe UI,Arial;margin:18px;color:#111}
    .cfg{margin-bottom:12px}
    input{padding:6px;margin:4px 6px 4px 0}
    table{border-collapse:collapse;width:100%;margin:12px 0}
    th,td{border:1px solid #ddd;padding:6px;vertical-align:top}
    th{background:#f3f3f3}
    .meta{font-size:0.9em;color:#555}
    pre{white-space:pre-wrap;word-break:break-word;margin:0}
  </style>
</head>
<body>
  <h2>Database Viewer</h2>
  <form method="get" class="cfg">
    <label>Host: <input name="host" value="<?=h($host)?>"></label>
    <label>Port: <input name="port" value="<?=h($port)?>" style="width:80px"></label>
    <label>DB: <input name="db" value="<?=h($db)?>"></label>
    <label>User: <input name="user" value="<?=h($user)?>"></label>
    <label>Password: <input name="pass" value="<?=h($pass)?>" type="password"></label>
    <label>Limit (optional): <input name="limit" value="<?=h($limit)?>" style="width:80px"></label>
    <button type="submit">Connect</button>
  </form>

  <?php if(!empty($tables)): ?>
    <form method="get" style="margin-top:8px">
      <input type="hidden" name="host" value="<?=h($host)?>">
      <input type="hidden" name="port" value="<?=h($port)?>">
      <input type="hidden" name="db" value="<?=h($db)?>">
      <input type="hidden" name="user" value="<?=h($user)?>">
      <input type="hidden" name="pass" value="<?=h($pass)?>">
      <label>Select table:
        <select name="table">
          <option value="">-- choose table --</option>
          <?php foreach($tables as $t): ?>
            <option value="<?=h($t)?>" <?=isset($_GET['table']) && $_GET['table']==$t? 'selected' : ''?>><?=h($t)?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="margin-left:8px"><input type="checkbox" name="show_all" value="1" <?=isset($_GET['show_all']) ? 'checked' : ''?>> Show all rows</label>
      <button type="submit">Show</button>
    </form>
  <?php endif; ?>

  <?php if($error): ?>
    <div class="err">Error: <?=h($error)?></div>
  <?php endif; ?>

  <?php if($db && empty($error)): ?>
    <div class="meta">Schema: <?=h($db)?> — <?=count($tables)?> table(s). Showing up to <?=h($limit)?> rows per table.</div>

    <?php
      $selected = $_GET['table'] ?? null;
      if($selected && in_array($selected, $tables, true)){
          try{
              // if show_all is set, fetch all rows; otherwise respect limit if provided
              if(isset($_GET['show_all']) && $_GET['show_all']=='1'){
                  $stmt = $pdo->query('SELECT * FROM `'.str_replace('`','``',$selected).'`');
              }else{
                  $lim = isset($_GET['limit']) && (int)$_GET['limit']>0 ? (int)$_GET['limit'] : $limit;
                  $stmt = $pdo->query('SELECT * FROM `'.str_replace('`','``',$selected).'` LIMIT '.(int)$lim);
              }
              $rows = $stmt->fetchAll();
          }catch(Exception $e){
              $rows = null; $tblErr = $e->getMessage();
          }

          echo '<h3>'.h($selected).'</h3>';
          if(isset($tblErr)){
              echo '<div class="err">Error reading table '.h($selected).': '.h($tblErr).'</div>';
              unset($tblErr);
          }elseif(empty($rows)){
              echo '<div class="meta">No rows.</div>';
          }else{
              echo '<table><thead><tr>';
              foreach(array_keys($rows[0]) as $col){ echo '<th>'.h($col).'</th>'; }
              echo '</tr></thead><tbody>';
              foreach($rows as $r){
                  echo '<tr>';
                  foreach($r as $v){ echo '<td><pre>'.h(var_export($v,true)).'</pre></td>'; }
                  echo '</tr>';
              }
              echo '</tbody></table>';
          }
      }
      else{
          echo '<div class="meta">Select a table to view its contents.</div>';
      }
      
    ?>
  <?php else: ?>
    <div class="meta">Enter connection info and a database name, then click Connect.</div>
  <?php endif; ?>
</body>
</html>
 
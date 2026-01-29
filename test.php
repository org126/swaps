<?php
// Database viewer tool - intended for development only
/*
 * Uses configuration from config.php for database connection
 * Displays table contents in HTML.
 * NOTE: For development use only - exposes database contents
 */

require_once __DIR__ . '/config.php';

// Escape helper for outputting HTML-safe values
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// State holders
$error = null;
$tables = [];
$selectedTable = $_GET['table'] ?? '';
$showAll = isset($_GET['show_all']) && $_GET['show_all'] == '1';

// Try to connect and load tables
try{
  $pdo = getPDOConnection();
  
  // Query information_schema for the list of tables in the configured database
  $stmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME");
  $stmt->execute([':schema' => DB_NAME]);
  $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
}catch(Exception $e){
  // Capture error message for display in the page
  error_log('Database error: ' . $e->getMessage());
  $error = 'Database connection failed.';
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
  <h2>Database Viewer (Development Only)</h2>
  <p style="color:#666; font-size:0.9em;">Connected to: <strong><?=h(DB_HOST . ':' . DB_PORT . '/' . DB_NAME)?></strong></p>

  <?php if($error): ?>
    <div style="color:red; margin:12px 0;">Error: <?=h($error)?></div>
  <?php elseif(!empty($tables)): ?>
    <form method="get" style="margin:12px 0">
      <label>Select table:
        <select name="table">
          <option value="">-- choose table --</option>
          <?php foreach($tables as $t): ?>
            <option value="<?=h($t)?>" <?=$selectedTable==$t? 'selected' : ''?>><?=h($t)?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="margin-left:8px"><input type="checkbox" name="show_all" value="1" <?=$showAll ? 'checked' : ''?>> Show all rows</label>
      <button type="submit">Show</button>
    </form>
    <div class="meta"><?=count($tables)?> table(s) available.</div>
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
 

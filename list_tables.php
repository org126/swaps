<?php
function h($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}
function fmtBytes($bytes){
    if($bytes<=0) return '0 B';
    $units=['B','KB','MB','GB','TB'];
    $i=(int)floor(log($bytes,1024));
    return round($bytes/pow(1024,$i),2).' '.$units[$i];
}

$host = $_GET['host'] ?? '127.0.0.1';
$port = $_GET['port'] ?? '3306';
$db   = $_GET['db']   ?? 'swaps';
$user = $_GET['user'] ?? 'root';
$pass = $_GET['pass'] ?? '';

$error = null;
$tables = [];
if($db){
    try{
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $sql = "SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_COMMENT
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = :schema
                ORDER BY TABLE_NAME";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':schema'=>$db]);
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }catch(Exception $e){
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>List Tables</title>
  <style>
    body{font-family:Inter,Segoe UI,Arial;margin:20px;color:#111}
    input{padding:6px;margin:4px 0;width:280px}
    table{border-collapse:collapse;width:100%;margin-top:12px}
    th,td{border:1px solid #ddd;padding:8px;text-align:left}
    th{background:#f7f7f7}
    .err{color:#900;margin-top:12px}
    .small{font-size:0.9em;color:#555}
  </style>
</head>
<body>
  <h2>Database Tables Viewer</h2>
  <form method="get">
    <div>
      <label>Host: <input name="host" value="<?=h($host)?>"></label>
      <label style="margin-left:8px">Port: <input name="port" value="<?=h($port)?>" style="width:80px"></label>
    </div>
    <div>
      <label>Database: <input name="db" value="<?=h($db)?>"></label>
    </div>
    <div>
      <label>User: <input name="user" value="<?=h($user)?>"></label>
      <label style="margin-left:8px">Password: <input name="pass" value="<?=h($pass)?>" type="password"></label>
    </div>
    <div>
      <button type="submit">Show tables</button>
    </div>
    <div class="small">Tip: you can pass params in the URL like <code>?host=127.0.0.1&db=mydb&user=root&pass=secret</code></div>
  </form>

  <?php if($error): ?>
    <div class="err">Error: <?=h($error)?></div>
  <?php endif; ?>

  <?php if($db && !$error): ?>
    <h3>Schema: <?=h($db)?> — <?=count($tables)?> table(s)</h3>
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Engine</th>
          <th>Rows</th>
          <th>Data</th>
          <th>Index</th>
          <th>Comment</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($tables as $t): ?>
        <tr>
          <td><?=h($t['TABLE_NAME'])?></td>
          <td><?=h($t['ENGINE'])?></td>
          <td><?=h($t['TABLE_ROWS'])?></td>
          <td><?=h(fmtBytes((int)$t['DATA_LENGTH']))?></td>
          <td><?=h(fmtBytes((int)$t['INDEX_LENGTH']))?></td>
          <td><?=h($t['TABLE_COMMENT'])?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php elseif($db): ?>
    <div class="small">No tables found for schema <?=h($db)?>.</div>
  <?php endif; ?>
</body>
</html>

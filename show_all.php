<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$host = $_GET['host'] ?? '127.0.0.1';
$port = $_GET['port'] ?? '3306';
$db   = $_GET['db']   ?? '';
$user = $_GET['user'] ?? 'root';
$pass = $_GET['pass'] ?? '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

$error = null;
$tables = [];

if($db){
    try{
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // list tables in the schema
        $stmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME");
        $stmt->execute([':schema' => $db]);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
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
    <label>Limit: <input name="limit" value="<?=h($limit)?>" style="width:80px"></label>
    <button type="submit">Connect</button>
  </form>

  <?php if($error): ?>
    <div class="err">Error: <?=h($error)?></div>
  <?php endif; ?>

  <?php if($db && empty($error)): ?>
    <div class="meta">Schema: <?=h($db)?> — <?=count($tables)?> table(s). Showing up to <?=h($limit)?> rows per table.</div>

    <?php foreach($tables as $table): ?>
      <?php
        // fetch rows for the table (with limit)
        try{
            $stmt = $pdo->query(sprintf('SELECT * FROM `%s` LIMIT %d', str_replace('`','``',$table), $limit));
            $rows = $stmt->fetchAll();
        }catch(Exception $e){
            $rows = null; $tblErr = $e->getMessage();
        }
      ?>

      <h3><?=h($table)?></h3>
      <?php if(isset($tblErr)): ?>
        <div class="err">Error reading table <?=h($table)?>: <?=h($tblErr)?></div>
        <?php unset($tblErr); ?>
      <?php elseif(empty($rows)): ?>
        <div class="meta">No rows.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <?php foreach(array_keys($rows[0]) as $col): ?>
                <th><?=h($col)?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <?php foreach($r as $v): ?>
                  <td><pre><?=h(var_export($v, true))?></pre></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="meta">Enter connection info and a database name, then click Connect.</div>
  <?php endif; ?>
</body>
</html>

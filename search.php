<?php
// Simple search page for machines by part_number or machine_number
$host = '127.0.0.1';
$port = '3306';
$db   = 'swaps';
$user = 'root';
$pass = '';

error_reporting(E_ALL);
ini_set('display_errors', '1');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$term = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$rows = [];
$error = null;

if ($term !== '') {
		try {
				$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
				$pdo = new PDO($dsn, $user, $pass, [
						PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
						PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				]);

				$sql = "
						SELECT id, part_number, machine_number, next_maintenance_date, notes, created_at
						FROM machines
						WHERE part_number LIKE :t OR machine_number LIKE :t
						ORDER BY created_at DESC
						LIMIT 100
				";
				$stmt = $pdo->prepare($sql);
				$like = '%' . $term . '%';
				$stmt->execute([':t' => $like]);
				$rows = $stmt->fetchAll();
		} catch (Exception $e) {
				$error = $e->getMessage();
		}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Machine Search</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 24px; }
		form { margin-bottom: 16px; }
		table { border-collapse: collapse; width: 100%; }
		th, td { border: 1px solid $\ccc; padding: 8px; }
		th { background: #f5f5f5; }
		.error { color: #c00; }
	</style>
</head>
<body>
	<h2>Search Machines</h2>
	<form method="get">
		<label>
			Search (part or machine number):
			<input type="text" name="q" value="<?php echo h($term); ?>" />
		</label>
		<button type="submit">Search</button>
	</form>

	<?php if ($error): ?>
		<div class="error">Error: <?php echo h($error); ?></div>
	<?php elseif ($term !== ''): ?>
		<p>Results for “<?php echo h($term); ?>” (<?php echo count($rows); ?> found)</p>
		<?php if (!$rows): ?>
			<div>No matches.</div>
		<?php else: ?>
			<table>
				<thead>
					<tr>
						<th>ID</th>
						<th>Part Number</th>
						<th>Machine Number</th>
						<th>Next Maintenance</th>
						<th>Notes</th>
						<th>Created</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $r): ?>
						<tr>
							<td><?php echo h($r['id']); ?></td>
							<td><?php echo h($r['part_number']); ?></td>
							<td><?php echo h($r['machine_number']); ?></td>
							<td><?php echo h($r['next_maintenance_date']); ?></td>
							<td><?php echo h($r['notes']); ?></td>
							<td><?php echo h($r['created_at']); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>
</body>
</html>

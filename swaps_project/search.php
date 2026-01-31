<?php
require_once 'session_check.php';
// Simple search page for machines by part_number or machine_number
require_once __DIR__ . '/config.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Logout handler
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params['path'], $params['domain'], $params['secure'], $params['httponly']
		);
	}
	session_destroy();
	header('Location: /swaps_project/loginpage.php');
	exit('Logged out');
}

$term = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$rows = [];
$error = null;

if ($term !== '') {
		try {
				$pdo = getPDOConnection();

				$sql = "
						SELECT id, part_number, machine_number, next_maintenance_date, notes, created_at
						FROM machines
						WHERE part_number LIKE ? OR machine_number LIKE ?
						ORDER BY created_at DESC
						LIMIT 100
				";
				$stmt = $pdo->prepare($sql);
				$like = '%' . $term . '%';
				$stmt->execute([$like, $like]);
				$rows = $stmt->fetchAll();
		} catch (Exception $e) {
				// Log the actual error internally but show generic message to user
				error_log('Search error: ' . $e->getMessage());
				$error = 'A search error occurred. Please try again.';
		}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Machine Search</title>
	<link rel="stylesheet" href="/swaps_project/styles.css">
</head>
<body>
	<h2>Search Machines</h2>
	<div class="nav">
		<?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'technician'], true)): ?>
			<a href="/swaps_project/admin_users.php">Admin Users</a>
			<a href="/swaps_project/technician.php?tech_id=1">check reports</a>
		<?php endif; ?>
		<a href="/swaps_project/Main_Report.php">Main Report</a>
		<a class="secondary" href="/swaps_project/search.php?logout=1">Log Out</a>
	</div>
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
							<td>
								<a href="/swaps_project/machine_page.php?machine_number=<?php echo urlencode((string)$r['machine_number']); ?>">
									<?php echo h($r['machine_number']); ?>
								</a>
							</td>
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

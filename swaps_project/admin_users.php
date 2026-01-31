<?php

/**
 * Admin page: manage users (add, edit, delete)
 * Only accessible to users with admin role
 */
require_once __DIR__ . '/session_check.php';
requireRole('admin');

require_once __DIR__ . '/config.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = null;
$error = null;
$success = null;
$users = [];
$editUser = null;

// Connect to database
try {
	$pdo = getPDOConnection();
} catch (Exception $e) {
	error_log('Database connection error: ' . $e->getMessage());
	$error = 'Database connection failed. Please try again.';
}

// Handle POST actions (add, edit, delete)
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? null;
	
	// Add new user
	if ($action === 'add') {
		$username = trim((string)($_POST['username'] ?? ''));
		$password = (string)($_POST['password'] ?? '');
		$role = (string)($_POST['role'] ?? 'equipment_user');
		
		if (!$username || !$password) {
			$error = 'Username and password are required.';
		} elseif (!in_array($role, ['admin', 'technician', 'equipment_user'])) {
			$error = 'Invalid role selected.';
		} else {
			try {
				$pwHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
				$stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (:username, :hash, :role)');
				$stmt->execute([
					':username' => $username,
					':hash' => $pwHash,
					':role' => $role,
				]);
				$success = 'User added successfully.';
			} catch (Exception $e) {
				error_log('Add user error: ' . $e->getMessage());
				if (strpos($e->getMessage(), 'Duplicate') !== false) {
					$error = 'Username already exists.';
				} else {
					$error = 'Failed to add user. Please try again.';
				}
			}
		}
	}
	
	// Edit user
	elseif ($action === 'edit') {
		$userId = (int)($_POST['user_id'] ?? 0);
		$username = trim((string)($_POST['username'] ?? ''));
		$password = (string)($_POST['password'] ?? '');
		$role = (string)($_POST['role'] ?? 'equipment_user');
		
		if (!$userId || !$username) {
			$error = 'Invalid user or username.';
		} elseif (!in_array($role, ['admin', 'technician', 'equipment_user'])) {
			$error = 'Invalid role selected.';
		} else {
			try {
				if ($password) {
					// Update with new password
					$pwHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
					$stmt = $pdo->prepare('UPDATE users SET username = :username, password_hash = :hash, role = :role WHERE user_id = :id');
					$stmt->execute([
						':id' => $userId,
						':username' => $username,
						':hash' => $pwHash,
						':role' => $role,
					]);
				} else {
					// Update without password
					$stmt = $pdo->prepare('UPDATE users SET username = :username, role = :role WHERE user_id = :id');
					$stmt->execute([
						':id' => $userId,
						':username' => $username,
						':role' => $role,
					]);
				}
				$success = 'User updated successfully.';
			} catch (Exception $e) {
				error_log('Edit user error: ' . $e->getMessage());
				$error = 'Failed to update user. Please try again.';
			}
		}
	}
	
	// Delete user
	elseif ($action === 'delete') {
		$userId = (int)($_POST['user_id'] ?? 0);
		
		if (!$userId) {
			$error = 'Invalid user.';
		} else {
			try {
				$stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :id');
				$stmt->execute([':id' => $userId]);
				$success = 'User deleted successfully.';
			} catch (Exception $e) {
				error_log('Delete user error: ' . $e->getMessage());
				$error = 'Failed to delete user. Please try again.';
			}
		}
	}
}

// Load all users
if ($pdo && !$error) {
	try {
		$stmt = $pdo->query('SELECT user_id, username, role, created_at FROM users ORDER BY created_at DESC');
		$users = $stmt->fetchAll();
	} catch (Exception $e) {
		error_log('Load users error: ' . $e->getMessage());
		$error = 'Failed to load users. Please try again.';
	}
}

// Load user for editing (if edit_id in GET)
if ($pdo && isset($_GET['edit_id'])) {
	$editId = (int)$_GET['edit_id'];
	try {
		$stmt = $pdo->prepare('SELECT user_id, username, role FROM users WHERE user_id = :id');
		$stmt->execute([':id' => $editId]);
		$editUser = $stmt->fetch();
	} catch (Exception $e) {
		error_log('Load edit user error: ' . $e->getMessage());
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Admin: Manage Users</title>
	<link rel="stylesheet" href="/swaps_project/styles.css">
</head>
<body>
	<div class="container">
		<h1>Admin: Manage Users</h1>
		<a href="/swaps_project/search.php" class="btn-back">← Back to Search</a>

		<?php if ($error): ?>
			<div class="error"><?php echo h($error); ?></div>
		<?php endif; ?>

		<?php if ($success): ?>
			<div class="success"><?php echo h($success); ?></div>
		<?php endif; ?>

		<!-- Add/Edit User Form -->
		<h2><?php echo $editUser ? 'Edit User' : 'Add New User'; ?></h2>
		<form method="post">
			<input type="hidden" name="action" value="<?php echo $editUser ? 'edit' : 'add'; ?>">
			<?php if ($editUser): ?>
				<input type="hidden" name="user_id" value="<?php echo h($editUser['user_id']); ?>">
			<?php endif; ?>

			<label for="username">Username:</label>
			<input type="text" id="username" name="username" value="<?php echo $editUser ? h($editUser['username']) : ''; ?>" required>

			<label for="password">
				Password:
				<?php if ($editUser): ?>
					<span class="small">(leave blank to keep current password)</span>
				<?php endif; ?>
			</label>
			<input type="password" id="password" name="password" <?php echo !$editUser ? 'required' : ''; ?>>

			<label for="role">Role:</label>
			<select id="role" name="role" required>
				<option value="equipment_user" <?php echo (!$editUser || $editUser['role'] === 'equipment_user') ? 'selected' : ''; ?>>Equipment User</option>
				<option value="technician" <?php echo ($editUser && $editUser['role'] === 'technician') ? 'selected' : ''; ?>>Technician</option>
				<option value="admin" <?php echo ($editUser && $editUser['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
			</select>

			<button type="submit"><?php echo $editUser ? 'Update User' : 'Add User'; ?></button>
			<?php if ($editUser): ?>
				<a href="?"><button type="button" class="cancel">Cancel</button></a>
			<?php endif; ?>
		</form>

		<!-- Users Table -->
		<h2>All Users</h2>
		<?php if ($users): ?>
			<table>
				<thead>
					<tr>
						<th>User ID</th>
						<th>Username</th>
						<th>Role</th>
						<th>Created</th>
						<th class="actions">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($users as $u): ?>
						<tr>
							<td><?php echo h($u['user_id']); ?></td>
							<td><?php echo h($u['username']); ?></td>
							<td><?php echo h($u['role']); ?></td>
							<td><?php echo h($u['created_at']); ?></td>
							<td class="actions">
								<a href="?edit_id=<?php echo h($u['user_id']); ?>"><button type="button">Edit</button></a>
								<form class="inline-form" method="post" onsubmit="return confirm('Delete user: <?php echo h($u['username']); ?>?');">
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="user_id" value="<?php echo h($u['user_id']); ?>">
									<button type="submit" class="delete">Delete</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else: ?>
			<p>No users found.</p>
		<?php endif; ?>
	</div>
</body>
</html>

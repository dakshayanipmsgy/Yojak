<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/functions.php';

$deptId = $_SESSION['dept_id'] ?? null;
$userId = $_SESSION['user_id'] ?? '';
$isSuperadmin = ($_SESSION['role_id'] ?? '') === 'superadmin';

$errorMessage = null;
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($isSuperadmin) {
        $errorMessage = 'Password changes for superadmin are not supported here.';
    } elseif (!$deptId) {
        $errorMessage = 'Department context is missing.';
    } elseif ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errorMessage = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'New password and confirmation do not match.';
    } else {
        $usersPath = __DIR__ . '/storage/departments/' . $deptId . '/users/users.json';
        $users = read_json($usersPath);

        if (!is_array($users)) {
            $errorMessage = 'User store could not be loaded.';
        } else {
            $userFound = false;
            foreach ($users as &$user) {
                if (($user['id'] ?? '') === $userId) {
                    $userFound = true;
                    if (!password_verify($oldPassword, $user['password_hash'] ?? '')) {
                        $errorMessage = 'Old password is incorrect.';
                    } else {
                        $user['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
                    }
                    break;
                }
            }
            unset($user);

            if (!$userFound) {
                $errorMessage = 'User record not found.';
            } elseif (!$errorMessage) {
                if (!write_json($usersPath, $users)) {
                    $errorMessage = 'Failed to update password file.';
                } else {
                    $deptPath = __DIR__ . '/storage/departments/' . $deptId;
                    append_master_log($deptPath, 'User ' . $userId . ' changed password');
                    $successMessage = 'Password updated successfully.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <main class="dashboard-shell">
        <section class="dashboard-card">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">My Profile</h1>
                    <p class="muted">Update your account password.</p>
                </div>
            </div>

            <div class="panel">
                <h3>Change Password</h3>
                <?php if ($errorMessage): ?>
                    <div class="status error"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                <?php if ($successMessage): ?>
                    <div class="status success"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>
                <form method="post" class="inline-form" autocomplete="off">
                    <div class="form-group">
                        <label for="old_password">Old Password</label>
                        <input id="old_password" name="old_password" type="password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input id="new_password" name="new_password" type="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" required>
                    </div>
                    <button type="submit">Update Password</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>

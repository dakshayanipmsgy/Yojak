<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/functions.php';

$deptId = $_SESSION['dept_id'] ?? '';
$userId = $_SESSION['user_id'] ?? '';
$roleId = $_SESSION['role_id'] ?? '';
$isSuperadmin = $roleId === 'superadmin';
$isAdmin = !$isSuperadmin && $deptId && checkPermission('admin.' . $deptId);

$userDisplayName = $userId;
if ($isSuperadmin) {
    $userDisplayName = 'Superadmin';
} elseif ($deptId) {
    $users = getDepartmentUsers($deptId);
    foreach ($users as $user) {
        if (($user['id'] ?? '') === $userId) {
            $userDisplayName = $user['name'] ?? $userId;
            break;
        }
    }
}

$deptLabel = $deptId !== '' ? $deptId : 'N/A';

$centerLinks = [];
if ($isSuperadmin) {
    $centerLinks = [
        ['label' => 'Dashboard', 'href' => 'dashboard.php'],
    ];
} elseif ($isAdmin) {
    $centerLinks = [
        ['label' => 'Dashboard', 'href' => 'dashboard.php'],
        ['label' => 'Manage Users', 'href' => 'dashboard.php#manage-users'],
        ['label' => 'Manage Roles', 'href' => 'dashboard.php#manage-roles'],
        ['label' => 'Contractors', 'href' => 'manage_contractors.php'],
        ['label' => 'Templates', 'href' => 'manage_templates.php'],
    ];
} else {
    $centerLinks = [
        ['label' => 'Inbox', 'href' => 'dashboard.php#inbox'],
        ['label' => 'Sent Items', 'href' => 'dashboard.php#sent-items'],
        ['label' => 'Create New Document', 'href' => 'create_document.php'],
    ];
}
?>
<nav class="navbar">
    <div class="navbar-left">
        <div class="brand-mark">Yojak</div>
        <div class="dept-label">Dept: <?php echo htmlspecialchars($deptLabel); ?></div>
    </div>
    <div class="navbar-center">
        <?php foreach ($centerLinks as $link): ?>
            <a class="nav-link" href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a>
        <?php endforeach; ?>
    </div>
    <div class="navbar-right">
        <span class="welcome">Welcome, <?php echo htmlspecialchars($userDisplayName); ?></span>
        <a class="nav-link" href="profile.php">My Profile</a>
        <a class="nav-button" href="logout.php">Logout</a>
    </div>
</nav>

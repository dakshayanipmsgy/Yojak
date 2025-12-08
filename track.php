<?php
require_once __DIR__ . '/functions.php';

$searchId = trim($_GET['ref'] ?? '');
$result = null;
$message = '';

if ($searchId !== '') {
    $departmentsDir = __DIR__ . '/storage/departments';
    if (is_dir($departmentsDir)) {
        foreach (scandir($departmentsDir) as $deptFolder) {
            if ($deptFolder === '.' || $deptFolder === '..') {
                continue;
            }

            $deptPath = $departmentsDir . '/' . $deptFolder;
            $documentPath = $deptPath . '/documents/' . $searchId . '.json';
            if (!is_file($documentPath)) {
                continue;
            }

            $document = read_json($documentPath);
            if (!is_array($document)) {
                continue;
            }

            $deptMeta = read_json($deptPath . '/department.json') ?? ['name' => $deptFolder];
            $deptName = $deptMeta['name'] ?? $deptFolder;

            $roles = read_json($deptPath . '/roles/roles.json');
            $roleMap = [];
            if (is_array($roles)) {
                foreach ($roles as $role) {
                    $roleMap[$role['id'] ?? ''] = $role['name'] ?? ($role['id'] ?? '');
                }
            }

            $users = read_json($deptPath . '/users/users.json');
            $currentOwnerRoleId = '';
            if (is_array($users)) {
                foreach ($users as $user) {
                    if (($user['id'] ?? '') === ($document['current_owner'] ?? '')) {
                        $currentOwnerRoleId = $user['roles'][0] ?? '';
                        break;
                    }
                }
            }

            $result = [
                'department' => $deptName,
                'status' => $document['status'] ?? 'Unknown',
                'current_owner' => $roleMap[$currentOwnerRoleId] ?? ($currentOwnerRoleId ?: 'Unassigned'),
                'received_date' => $document['received_date'] ?? ($document['created_at'] ?? ''),
            ];
            break;
        }
    }

    if (!$result) {
        $message = 'No document found with that reference number.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Document</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="main-shell">
        <section class="login-card" style="max-width: 640px;">
            <div class="brand" style="margin-bottom: 12px;">
                <div class="logo-placeholder">📄</div>
                <div>
                    <div class="title">Public Tracking Portal</div>
                    <p class="muted" style="margin: 4px 0 0 0;">Check the status of your document without logging in.</p>
                </div>
            </div>

            <form method="get" autocomplete="off" class="inline-form" style="grid-template-columns: 1fr auto;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="ref">Enter Document Reference No.</label>
                    <input type="text" id="ref" name="ref" value="<?php echo htmlspecialchars($searchId); ?>" placeholder="e.g., DOC_2023_001" required>
                </div>
                <div class="form-group" style="margin-bottom: 0; align-self: flex-end;">
                    <button type="submit">Track</button>
                </div>
            </form>

            <?php if ($message): ?>
                <div class="status error" style="margin-top: 16px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($result): ?>
                <div class="panel" style="margin-top: 18px;">
                    <h3 style="margin-top: 0;">Result for <?php echo htmlspecialchars($searchId); ?></h3>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($result['status']); ?></p>
                    <p><strong>Currently With:</strong> <?php echo htmlspecialchars($result['department'] . ' - ' . $result['current_owner']); ?></p>
                    <p><strong>Since:</strong>
                        <?php echo htmlspecialchars($result['received_date'] ? date('d-M-Y', strtotime($result['received_date'])) : 'Not Available'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>

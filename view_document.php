<?php
session_start();
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['role_id'], $_SESSION['user_id'], $_SESSION['dept_id'])) {
    header('Location: index.php');
    exit;
}

$deptId = $_SESSION['dept_id'];
$docId = $_GET['id'] ?? '';

if ($docId === '') {
    header('Location: dashboard.php');
    exit;
}

$deptPath = __DIR__ . '/storage/departments/' . $deptId;
$documentPath = $deptPath . '/documents/' . $docId . '.json';
$document = read_json($documentPath);

if (!is_array($document)) {
    $errorMessage = 'Document not found.';
} else {
    $errorMessage = null;
}

$deptUsers = getDepartmentUsers($deptId);
$userMap = [];
foreach ($deptUsers as $user) {
    $userMap[$user['id'] ?? ''] = $user['name'] ?? ($user['id'] ?? '');
}

$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errorMessage) {
    $action = $_POST['action'] ?? '';

    if ($action === 'forward') {
        $targetUserId = $_POST['target_user_id'] ?? '';
        if ($targetUserId === '') {
            $errorMessage = 'Please select a user to forward the document to.';
        } elseif (($document['current_owner'] ?? '') !== ($_SESSION['user_id'] ?? '')) {
            $errorMessage = 'Only the current owner can forward this document.';
        } else {
            $result = moveDocument($deptId, $docId, $targetUserId, $_SESSION['user_id'], 'pending');
            if ($result['success']) {
                $successMessage = $result['message'];
                $document = read_json($documentPath);
            } else {
                $errorMessage = $result['message'];
            }
        }
    } elseif ($action === 'save_edits') {
        if (!in_array($document['status'] ?? '', ['draft', 'correction'], true)) {
            $errorMessage = 'Editing is only allowed in draft or correction status.';
        } elseif (($document['current_owner'] ?? '') !== ($_SESSION['user_id'] ?? '')) {
            $errorMessage = 'Only the current owner can edit this document.';
        } else {
            $document['title'] = trim($_POST['title'] ?? ($document['title'] ?? ''));
            $document['content'] = $_POST['content'] ?? ($document['content'] ?? '');

            if (!isset($document['history']) || !is_array($document['history'])) {
                $document['history'] = [];
            }

            $document['history'][] = [
                'action' => 'edited',
                'by' => $_SESSION['user_id'],
                'time' => date('c'),
            ];

            if (write_json($documentPath, $document)) {
                $successMessage = 'Document updated successfully.';
            } else {
                $errorMessage = 'Unable to save document changes.';
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
    <title>Document View</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="dashboard-shell">
        <section class="dashboard-card">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Document: <?php echo htmlspecialchars($docId); ?></h1>
                    <?php if ($document): ?>
                        <p class="muted">Owner: <?php echo htmlspecialchars($document['current_owner'] ?? ''); ?> | Status: <?php echo htmlspecialchars($document['status'] ?? ''); ?></p>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <a href="dashboard.php" class="btn-secondary button-as-link">Back</a>
                </div>
            </div>

            <?php if ($errorMessage): ?>
                <div class="status error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <div class="status success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <?php if ($document): ?>
                <div class="panel">
                    <div class="page">
                        <?php echo $document['content'] ?? ''; ?>
                    </div>
                </div>

                <?php if (($document['current_owner'] ?? '') === ($_SESSION['user_id'] ?? '')): ?>
                    <div class="panel">
                        <h3>Actions</h3>
                        <form method="post" class="inline-form" autocomplete="off">
                            <input type="hidden" name="action" value="forward">
                            <div class="form-group" style="width: 100%;">
                                <label for="target_user_id">Forward/Send To</label>
                                <select id="target_user_id" name="target_user_id" required>
                                    <option value="" disabled selected>Select user</option>
                                    <?php foreach ($deptUsers as $user): ?>
                                        <?php if (($user['id'] ?? '') === ($_SESSION['user_id'] ?? '')) { continue; } ?>
                                        <option value="<?php echo htmlspecialchars($user['id']); ?>"><?php echo htmlspecialchars(($user['name'] ?? $user['id']) . ' (' . ($user['id'] ?? '') . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit">Forward</button>
                        </form>

                        <?php if (in_array($document['status'] ?? '', ['draft', 'correction'], true)): ?>
                            <form method="post" class="inline-form" style="margin-top: 16px;" autocomplete="off">
                                <input type="hidden" name="action" value="save_edits">
                                <div class="form-group" style="width: 100%;">
                                    <label for="title">Title</label>
                                    <input id="title" name="title" type="text" value="<?php echo htmlspecialchars($document['title'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group" style="width: 100%;">
                                    <label for="content">Content</label>
                                    <textarea id="content" name="content" rows="10" required><?php echo htmlspecialchars($document['content'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn-secondary">Save Changes</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="panel">
                    <h3>History / Audit Trail</h3>
                    <?php if (empty($document['history'])): ?>
                        <p class="muted">No history recorded yet.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>By</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($document['history'] as $entry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($entry['action'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($userMap[$entry['from'] ?? ''] ?? ($entry['from'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($userMap[$entry['to'] ?? ''] ?? ($entry['to'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($userMap[$entry['by'] ?? ''] ?? ($entry['by'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(isset($entry['time']) ? date('M d, Y H:i', strtotime($entry['time'])) : ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>

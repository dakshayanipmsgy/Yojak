<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['role_id'], $_SESSION['dept_id'])) {
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
$deptRoles = read_json($deptPath . '/roles/roles.json') ?? [];
$roleNameMap = [];
foreach ($deptRoles as $role) {
    $roleNameMap[$role['id'] ?? ''] = $role['name'] ?? ($role['id'] ?? '');
}

$noteUserLabel = ($_SESSION['role_id'] ?? '') !== ''
    ? (($roleNameMap[$_SESSION['role_id']] ?? $_SESSION['role_id']) . ' (' . ($_SESSION['user_id'] ?? 'user') . ')')
    : ($_SESSION['user_id'] ?? 'user');

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
$isCurrentOwner = ($document['current_owner'] ?? '') === ($_SESSION['user_id'] ?? '');
$queryStatus = $_GET['status'] ?? '';
if ($queryStatus === 'success') {
    $successMessage = $_GET['message'] ?? 'Action completed successfully.';
} elseif ($queryStatus === 'error') {
    $errorMessage = $_GET['message'] ?? 'Unable to complete the request.';
}

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
    } elseif ($action === 'add_note') {
        $noteText = trim($_POST['note_text'] ?? '');
        if ($noteText === '') {
            $errorMessage = 'Note text cannot be empty.';
        } else {
            if (!isset($document['note_sheet']) || !is_array($document['note_sheet'])) {
                $document['note_sheet'] = [];
            }

            $document['note_sheet'][] = [
                'user' => $noteUserLabel,
                'note' => $noteText,
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            if (!isset($document['history']) || !is_array($document['history'])) {
                $document['history'] = [];
            }

            $document['history'][] = [
                'action' => 'note_added',
                'from' => $document['current_owner'] ?? '',
                'to' => $document['current_owner'] ?? '',
                'by' => $_SESSION['user_id'] ?? 'unknown',
                'time' => date('c'),
            ];

            if (write_json($documentPath, $document)) {
                $successMessage = 'Note added to departmental sheet.';
                $document = read_json($documentPath);
            } else {
                $errorMessage = 'Unable to save note. Please try again.';
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
    <?php include __DIR__ . '/navbar.php'; ?>
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
                    <?php if ($document): ?>
                        <button type="button" class="btn-secondary" id="print-options">Print</button>
                    <?php endif; ?>
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
                <div class="document-layout">
                    <div class="document-main">
                        <div class="panel" id="letter-panel">
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
                            <h3>Supporting Documents</h3>
                            <?php $attachments = isset($document['attachments']) && is_array($document['attachments']) ? $document['attachments'] : []; ?>
                            <?php if (empty($attachments)): ?>
                                <p class="muted">No supporting documents uploaded.</p>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($attachments as $attachment): ?>
                                        <?php
                                            $filePath = $attachment['path'] ?? '';
                                            $fileName = $attachment['filename'] ?? basename($filePath);
                                        ?>
                                        <li style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span aria-hidden="true">📎</span>
                                            <a href="<?php echo htmlspecialchars($filePath); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo htmlspecialchars($fileName); ?>
                                            </a>
                                            <?php if ($isCurrentOwner): ?>
                                                <form action="upload_attachment.php" method="post" class="inline-form" style="margin: 0;">
                                                    <input type="hidden" name="action" value="delete_attachment">
                                                    <input type="hidden" name="doc_id" value="<?php echo htmlspecialchars($docId); ?>">
                                                    <input type="hidden" name="attachment_path" value="<?php echo htmlspecialchars($filePath); ?>">
                                                    <button type="submit" class="btn-secondary" style="padding: 4px 8px;">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if ($isCurrentOwner): ?>
                                <form action="upload_attachment.php" method="post" enctype="multipart/form-data" style="margin-top: 16px;">
                                    <input type="hidden" name="doc_id" value="<?php echo htmlspecialchars($docId); ?>">
                                    <input type="hidden" name="action" value="upload_attachment">
                                    <div class="form-group" style="width: 100%;">
                                        <label for="attachments">Upload Supporting Documents</label>
                                        <input type="file" id="attachments" name="attachments[]" multiple required>
                                        <p class="muted" style="margin-top: 4px;">Allowed: .pdf, .jpg, .png, .jpeg, .docx, .xlsx</p>
                                    </div>
                                    <button type="submit">Upload Files</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="panel" id="history-section">
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
                    </div>

                    <aside class="panel note-sheet-panel" id="note-sheet">
                        <div class="note-sheet-header">
                            <div>
                                <h3>Departmental Notes (Green Sheet)</h3>
                                <p class="muted">Permanent, append-only remarks for this file.</p>
                            </div>
                        </div>
                        <div class="note-sheet-body">
                            <?php $notes = isset($document['note_sheet']) && is_array($document['note_sheet']) ? $document['note_sheet'] : []; ?>
                            <?php if (empty($notes)): ?>
                                <p class="muted">No notes recorded yet.</p>
                            <?php else: ?>
                                <div class="note-list">
                                    <?php foreach ($notes as $note): ?>
                                        <div class="note-bubble">
                                            <div class="note-meta">
                                                <strong><?php echo htmlspecialchars($note['user'] ?? ''); ?></strong>
                                                <span><?php echo htmlspecialchars(isset($note['timestamp']) ? date('M d, Y H:i', strtotime($note['timestamp'])) : ''); ?></span>
                                            </div>
                                            <div class="note-text"><?php echo nl2br(htmlspecialchars($note['note'] ?? '')); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="note-sheet-footer no-print">
                            <form method="post" class="note-form" autocomplete="off">
                                <input type="hidden" name="action" value="add_note">
                                <label for="note_text" class="sr-only">Add Note</label>
                                <textarea id="note_text" name="note_text" rows="3" placeholder="Add a departmental note..." required></textarea>
                                <button type="submit">Add Note</button>
                            </form>
                            <p class="muted" style="margin-top: 8px;">Notes cannot be edited or deleted. They are part of the permanent record.</p>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div class="print-modal" id="print-modal" aria-hidden="true">
        <div class="print-modal-content">
            <h3 style="margin-top: 0;">Print Options</h3>
            <p class="muted">Choose what to send to the printer.</p>
            <div class="print-actions">
                <button type="button" data-print-target="letter" class="btn-primary">Print Letter</button>
                <button type="button" data-print-target="note_sheet" class="btn-secondary">Print Note Sheet</button>
            </div>
            <button type="button" class="button-as-link" id="close-print-modal" style="margin-top: 12px;">Cancel</button>
        </div>
    </div>

    <script>
        const printButton = document.getElementById('print-options');
        const printModal = document.getElementById('print-modal');
        const closePrintModal = document.getElementById('close-print-modal');

        function openPrintModal() {
            if (printModal) {
                printModal.setAttribute('aria-hidden', 'false');
                printModal.classList.add('open');
            }
        }

        function hidePrintModal() {
            if (printModal) {
                printModal.setAttribute('aria-hidden', 'true');
                printModal.classList.remove('open');
            }
        }

        function printSection(htmlContent, title = 'Print') {
            const printWindow = window.open('', 'PRINT', 'height=650,width=900');
            if (!printWindow) {
                return;
            }
            printWindow.document.write('<html><head><title>' + title + '</title>');
            printWindow.document.write('<link rel="stylesheet" href="style.css">');
            printWindow.document.write('</head><body>' + htmlContent + '</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }

        if (printButton) {
            printButton.addEventListener('click', openPrintModal);
        }

        if (closePrintModal) {
            closePrintModal.addEventListener('click', hidePrintModal);
        }

        document.addEventListener('click', function(event) {
            if (event.target === printModal) {
                hidePrintModal();
            }
        });

        document.querySelectorAll('[data-print-target]').forEach(function(button) {
            button.addEventListener('click', function() {
                const target = button.getAttribute('data-print-target');
                if (target === 'letter') {
                    const letterPanel = document.getElementById('letter-panel');
                    if (letterPanel) {
                        printSection(letterPanel.innerHTML, 'Letter');
                    }
                } else if (target === 'note_sheet') {
                    const notePanel = document.getElementById('note-sheet');
                    const historyPanel = document.getElementById('history-section');
                    let content = '';
                    if (notePanel) {
                        content += '<section>' + notePanel.innerHTML + '</section>';
                    }
                    if (historyPanel) {
                        content += '<section style="margin-top:16px;">' + historyPanel.innerHTML + '</section>';
                    }
                    if (content !== '') {
                        printSection(content, 'Note Sheet & Audit Trail');
                    }
                }
                hidePrintModal();
            });
        });
    </script>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['role_id'], $_SESSION['user_id'], $_SESSION['dept_id'])) {
    header('Location: index.php');
    exit;
}

$deptId = $_SESSION['dept_id'];
if (!checkPermission('admin.' . $deptId)) {
    header('Location: dashboard.php');
    exit;
}

$templatesDir = __DIR__ . '/storage/departments/' . $deptId . '/templates';
$templatesIndexPath = $templatesDir . '/templates.json';
if (!is_dir($templatesDir)) {
    mkdir($templatesDir, 0755, true);
}

$templates = read_json($templatesIndexPath);
if (!is_array($templates)) {
    $templates = [];
}

$successMessage = null;
$errorMessage = null;
$editingTemplate = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $beforeCount = count($templates);
        $templates = array_values(array_filter($templates, function ($tpl) use ($id) {
            return ($tpl['id'] ?? '') !== $id;
        }));

        if ($beforeCount === count($templates)) {
            $errorMessage = 'Template not found.';
        } else {
            $deletedFile = $_POST['filename'] ?? '';
            if ($deletedFile) {
                $filePath = $templatesDir . '/' . basename($deletedFile);
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            if (write_json($templatesIndexPath, $templates)) {
                $successMessage = 'Template deleted.';
            } else {
                $errorMessage = 'Failed to update template index.';
            }
        }
    } else {
        $name = trim($_POST['template_name'] ?? '');
        $content = $_POST['template_content'] ?? '';
        $id = $_POST['id'] ?? null;

        if ($name === '' || $content === '') {
            $errorMessage = 'Template name and content are required.';
        } else {
            if ($action === 'update') {
                foreach ($templates as &$template) {
                    if (($template['id'] ?? '') === $id) {
                        $targetFile = $templatesDir . '/' . ($template['filename'] ?? '');
                        if (!isset($template['filename']) || $template['filename'] === '') {
                            $errorMessage = 'Template file missing on disk.';
                        } elseif (file_put_contents($targetFile, $content, LOCK_EX) === false) {
                            $errorMessage = 'Unable to update template file.';
                        } else {
                            $template['title'] = $name;
                            $template['updated_at'] = date('c');
                            $successMessage = 'Template updated successfully.';
                        }
                        break;
                    }
                }
                unset($template);

                if (!$successMessage && !$errorMessage) {
                    $errorMessage = 'Template not found.';
                }
            } else {
                $slug = slugify_label($name);
                $filename = $slug . '-' . substr(generate_id(), 0, 8) . '.html';
                $filepath = $templatesDir . '/' . $filename;

                if (file_put_contents($filepath, $content, LOCK_EX) === false) {
                    $errorMessage = 'Failed to save template file.';
                } else {
                    $templates[] = [
                        'id' => generate_id(),
                        'title' => $name,
                        'filename' => $filename,
                        'created_at' => date('c'),
                    ];
                    $successMessage = 'Template saved successfully.';
                }
            }

            if ($successMessage && !write_json($templatesIndexPath, $templates)) {
                $errorMessage = 'Failed to update template index.';
                $successMessage = null;
            }
        }
    }
} elseif (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    foreach ($templates as $tpl) {
        if (($tpl['id'] ?? '') === $editId) {
            $editingTemplate = $tpl;
            $templatePath = $templatesDir . '/' . ($tpl['filename'] ?? '');
            $tplContent = file_exists($templatePath) ? file_get_contents($templatePath) : '';
            $editingTemplate['content'] = $tplContent !== false ? $tplContent : '';
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Templates</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="dashboard-shell">
        <section class="dashboard-card">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Template Manager</h1>
                    <p class="muted">Department: <?php echo htmlspecialchars($deptId); ?></p>
                </div>
                <div class="actions">
                    <a href="dashboard.php" class="btn-secondary button-as-link">Back</a>
                </div>
            </div>

            <div class="panel">
                <h3><?php echo $editingTemplate ? 'Edit Template' : 'Create New Template'; ?></h3>
                <p class="muted">Use {{variable}} syntax for placeholders. Available options include {{department_name}}, {{contractor_name}}, {{contractor_address}}, {{contractor_pan}}, {{contractor_gst}}, {{contractor_mobile}}, and {{current_date}}.</p>

                <?php if ($errorMessage): ?>
                    <div class="status error"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                <?php if ($successMessage): ?>
                    <div class="status success"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="<?php echo $editingTemplate ? 'update' : 'create'; ?>">
                    <?php if ($editingTemplate): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($editingTemplate['id']); ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="template_name">Template Name</label>
                        <input id="template_name" name="template_name" type="text" placeholder="e.g., Work Order" value="<?php echo htmlspecialchars($editingTemplate['title'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="template_content">Template Content (HTML)</label>
                        <textarea id="template_content" name="template_content" rows="10" placeholder="Enter HTML with placeholders" required><?php echo htmlspecialchars($editingTemplate['content'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit"><?php echo $editingTemplate ? 'Update Template' : 'Save Template'; ?></button>
                </form>
            </div>

            <div class="panel">
                <h3>Saved Templates</h3>
                <?php if (empty($templates)): ?>
                    <p class="muted">No templates created yet.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Filename</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($template['title'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($template['filename'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($template['created_at'] ?? 'now'))); ?></td>
                                    <td>
                                        <a class="button-as-link" href="?edit=<?php echo urlencode($template['id']); ?>">Edit</a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this template?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($template['id']); ?>">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($template['filename']); ?>">
                                            <button type="submit" class="btn-secondary">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>

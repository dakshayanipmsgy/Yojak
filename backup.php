<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['dept_id'], $_SESSION['role_id'])) {
    header('Location: index.php');
    exit;
}

$deptId = $_SESSION['dept_id'];
if (!checkPermission('admin.' . $deptId)) {
    http_response_code(403);
    echo 'Access denied: only department administrators can download backups.';
    exit;
}

$sourcePath = __DIR__ . '/storage/departments/' . $deptId;
if (!is_dir($sourcePath)) {
    http_response_code(404);
    echo 'Department storage not found.';
    exit;
}

$zip = new ZipArchive();
$tempFile = tempnam(sys_get_temp_dir(), 'dept_backup_');
$zipName = 'backup_' . $deptId . '_' . date('Ymd_His') . '.zip';

if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Unable to create backup archive.';
    exit;
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($files as $file) {
    $filePath = $file->getPathname();
    $relativePath = ltrim(str_replace($sourcePath, '', $filePath), DIRECTORY_SEPARATOR);

    if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
    } else {
        $zip->addFile($filePath, $relativePath);
    }
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipName) . '"');
header('Content-Length: ' . filesize($tempFile));

readfile($tempFile);
unlink($tempFile);
exit;

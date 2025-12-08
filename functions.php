<?php
/**
 * Utility functions for file-based storage operations.
 */

if (!function_exists('read_json')) {
    /**
     * Read JSON data from a file path.
     *
     * @param string $path
     * @return array|null
     */
    function read_json(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('write_json')) {
    /**
     * Write JSON data to a file path using an exclusive lock.
     *
     * @param string $path
     * @param array $data
     * @return bool
     */
    function write_json(string $path, array $data): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return file_put_contents($path, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('generate_id')) {
    /**
     * Generate a unique identifier for future entities.
     *
     * @return string
     */
    function generate_id(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception $e) {
            return uniqid('', true);
        }
    }
}

if (!function_exists('getDepartmentUsers')) {
    /**
     * Retrieve all users for a given department.
     *
     * @param string $deptId
     * @return array<int, array>
     */
    function getDepartmentUsers(string $deptId): array
    {
        $usersPath = __DIR__ . '/storage/departments/' . $deptId . '/users/users.json';
        $users = read_json($usersPath);
        return is_array($users) ? $users : [];
    }
}

if (!function_exists('generate_document_id')) {
    /**
     * Generate a unique document identifier for the department store.
     */
    function generate_document_id(string $deptPath): string
    {
        $documentsDir = rtrim($deptPath, '/');
        $documentsDir .= '/documents';

        if (!is_dir($documentsDir)) {
            mkdir($documentsDir, 0755, true);
        }

        $year = date('Y');
        $maxCounter = 0;

        foreach (scandir($documentsDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!preg_match('/^DOC_' . $year . '_(\d{4})\.json$/', $entry, $matches)) {
                continue;
            }

            $counter = (int) $matches[1];
            if ($counter > $maxCounter) {
                $maxCounter = $counter;
            }
        }

        $nextCounter = $maxCounter + 1;
        $candidate = 'DOC_' . $year . '_' . str_pad((string) $nextCounter, 4, '0', STR_PAD_LEFT);

        while (file_exists($documentsDir . '/' . $candidate . '.json')) {
            $nextCounter++;
            $candidate = 'DOC_' . $year . '_' . str_pad((string) $nextCounter, 4, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }
}

if (!function_exists('append_master_log')) {
    /**
     * Append a line to the immutable master log for the department.
     */
    function append_master_log(string $deptPath, string $line): void
    {
        $logDir = rtrim($deptPath, '/') . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logPath = $logDir . '/master_log.txt';
        $timestamp = date('c');
        $entry = '[' . $timestamp . '] ' . $line . PHP_EOL;
        file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('moveDocument')) {
    /**
     * Move a document to another user within the same department and log the action.
     *
     * @param string $deptId
     * @param string $docId
     * @param string $targetUserId
     * @param string $initiatorId
     * @param string|null $newStatus
     * @param string|null $dueDate     Date in YYYY-MM-DD format to mark when the document is required.
     * @return array{success: bool, message: string}
     */
    function moveDocument(string $deptId, string $docId, string $targetUserId, string $initiatorId, ?string $newStatus = 'pending', ?string $dueDate = null): array
    {
        $deptPath = __DIR__ . '/storage/departments/' . $deptId;
        $documentPath = $deptPath . '/documents/' . $docId . '.json';

        $users = getDepartmentUsers($deptId);
        $userIds = array_column($users, 'id');
        if (!in_array($targetUserId, $userIds, true)) {
            return ['success' => false, 'message' => 'Target user does not exist in this department.'];
        }

        $document = read_json($documentPath);
        if (!is_array($document)) {
            return ['success' => false, 'message' => 'Document not found.'];
        }

        $previousOwner = $document['current_owner'] ?? null;
        $document['current_owner'] = $targetUserId;
        if ($newStatus !== null) {
            $document['status'] = $newStatus;
        }

        if ($dueDate !== null) {
            $document['due_date'] = $dueDate;
        } elseif (!array_key_exists('due_date', $document)) {
            $document['due_date'] = null;
        }

        $historyEntry = [
            'action' => 'moved',
            'from' => $previousOwner,
            'to' => $targetUserId,
            'time' => date('c'),
            'by' => $initiatorId,
        ];

        if (array_key_exists('due_date', $document)) {
            $historyEntry['due_date'] = $document['due_date'];
        }

        if (!isset($document['history']) || !is_array($document['history'])) {
            $document['history'] = [];
        }
        $document['history'][] = $historyEntry;

        if (!write_json($documentPath, $document)) {
            return ['success' => false, 'message' => 'Failed to update document.'];
        }

        append_master_log($deptPath, $docId . ' moved from ' . ($previousOwner ?? 'unknown') . ' to ' . $targetUserId);

        return ['success' => true, 'message' => 'Document moved successfully.'];
    }
}

if (!function_exists('slugify_label')) {
    /**
     * Convert a human-readable label to a filesystem-safe slug.
     */
    function slugify_label(string $label): string
    {
        $slug = strtolower(trim($label));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        return $slug === '' ? 'item' : $slug;
    }
}

if (!function_exists('checkPermission')) {
    /**
     * Verify a user has the required role (or is superadmin).
     */
    function checkPermission(string $required_role): bool
    {
        if (!isset($_SESSION['role_id'])) {
            return false;
        }

        if ($_SESSION['role_id'] === 'superadmin') {
            return true;
        }

        return $_SESSION['role_id'] === $required_role;
    }
}

if (!function_exists('createDepartment')) {
    /**
     * Create a new department with the required folder and bootstrap files.
     *
     * @param string $name
     * @param string $id
     * @param string $password
     * @return array{success: bool, message: string}
     */
    function createDepartment(string $name, string $id, string $password): array
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_\-]/', '', $id ?? '');

        if ($id === '') {
            return ['success' => false, 'message' => 'Department ID cannot be empty.'];
        }

        $basePath = __DIR__ . '/storage/departments/' . $id;
        if (is_dir($basePath)) {
            return ['success' => false, 'message' => 'Department ID already exists.'];
        }

        $structure = [
            $basePath,
            "$basePath/users",
            "$basePath/roles",
            "$basePath/documents",
            "$basePath/data",
            "$basePath/templates",
            "$basePath/logs",
        ];

        foreach ($structure as $dir) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['success' => false, 'message' => 'Failed to create department directories.'];
            }
        }

        $roleId = 'admin.' . $id;
        $userId = 'user.admin.' . $id;

        $rolesPath = "$basePath/roles/roles.json";
        $usersPath = "$basePath/users/users.json";
        $metaPath = "$basePath/department.json";

        $roleData = [
            [
                'id' => $roleId,
                'name' => 'Department Administrator',
                'permissions' => ['ALL'],
            ],
        ];

        $userData = [
            [
                'id' => $userId,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'roles' => [$roleId],
            ],
        ];

        $metaData = [
            'id' => $id,
            'name' => $name,
            'created_at' => date('c'),
        ];

        $writes = [
            write_json($rolesPath, $roleData),
            write_json($usersPath, $userData),
            write_json($metaPath, $metaData),
        ];

        if (in_array(false, $writes, true)) {
            return ['success' => false, 'message' => 'Failed to write department bootstrap files.'];
        }

        return ['success' => true, 'message' => 'Department created successfully.'];
    }
}

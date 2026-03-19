<?php
// ============================================================
// RBAC Helpers (Roles + Permissions)
// ============================================================

require_once __DIR__ . '/../config/database.php';

function rbacTablesReady(): bool {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    try {
        $pdo = getDB();
        $tables = ['roles', 'permissions', 'role_permissions'];
        $stmt = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM information_schema.tables\n            WHERE table_schema = DATABASE() AND table_name = ?\n        ");
        foreach ($tables as $table) {
            $stmt->execute([$table]);
            if ((int)$stmt->fetchColumn() === 0) {
                $ready = false;
                return $ready;
            }
        }
        $ready = true;
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function legacyRolePermissionsMap(): array {
    return [
        'admin' => ['admin.access','roles.manage','staff.manage','users.manage','reports.view_all','reports.view_own','reports.view','ticket.assign.lead','ticket.assign.exec','ticket.update_status','tickets.view_all','tickets.view_involved','notify.management'],
        'ict_head' => ['reports.view_all','reports.view_own','reports.view','ticket.assign.lead','tickets.view_all','tickets.view_involved','notify.management'],
        'assistant_manager' => ['ticket.assign.lead','tickets.view_all','tickets.view_involved','notify.management'],
        'assistant_ict' => ['ticket.assign.lead','tickets.view_all','tickets.view_involved','notify.management'],
        'sr_it_executive' => ['ticket.assign.exec','ticket.update_status','tickets.view_involved'],
        'assistant_it' => ['ticket.update_status','tickets.view_involved'],
    ];
}

/**
 * Built-in permission catalog used for seeding and UI labels.
 */
function rbacPermissionCatalog(): array {
    return [
        'admin.access' => ['name' => 'Admin Panel Access', 'description' => 'Access admin dashboard and admin modules', 'group' => 'admin'],
        'roles.manage' => ['name' => 'Manage Roles & Permissions', 'description' => 'Create roles and assign permissions', 'group' => 'admin'],
        'staff.manage' => ['name' => 'Manage Staff', 'description' => 'Create, edit, activate/deactivate staff accounts', 'group' => 'admin'],
        'users.manage' => ['name' => 'Manage Users', 'description' => 'View and manage user accounts', 'group' => 'admin'],
        'reports.view_all' => ['name' => 'Organization Reports', 'description' => 'View and export all staff reports (organization scope)', 'group' => 'reports'],
        'reports.view_own' => ['name' => 'My Reports', 'description' => 'View and export only the logged-in staff member report scope', 'group' => 'reports'],
        'reports.view' => ['name' => 'View Reports (Legacy)', 'description' => 'Legacy permission mapped to organization reports for backward compatibility', 'group' => 'reports'],
        'ticket.assign.lead' => ['name' => 'Assign Tickets (Leadership)', 'description' => 'Assign/reassign tickets as a leadership role', 'group' => 'tickets'],
        'ticket.assign.exec' => ['name' => 'Assign Tickets (Executive)', 'description' => 'Delegate tickets to execution team', 'group' => 'tickets'],
        'ticket.update_status' => ['name' => 'Update Ticket Status', 'description' => 'Move ticket through processing states', 'group' => 'tickets'],
        'tickets.view_all' => ['name' => 'View All Tickets', 'description' => 'See all tickets in staff panel', 'group' => 'tickets'],
        'tickets.view_involved' => ['name' => 'View Involved Tickets', 'description' => 'See tickets assigned to/handled by the staff member', 'group' => 'tickets'],
        'notify.management' => ['name' => 'Receive Management Notifications', 'description' => 'Receive leadership notifications for new ticket/status updates', 'group' => 'notifications'],
    ];
}

/**
 * Ensure permission catalog rows exist and migrate legacy report grants.
 */
function syncPermissionCatalog(): void {
    static $synced = false;
    if ($synced || !rbacTablesReady()) {
        return;
    }
    $synced = true;

    $pdo = getDB();
    $catalog = rbacPermissionCatalog();

    $upsert = $pdo->prepare("\n        INSERT INTO permissions (slug, name, description, group_name)\n        VALUES (?, ?, ?, ?)\n        ON DUPLICATE KEY UPDATE\n            name = VALUES(name),\n            description = VALUES(description),\n            group_name = VALUES(group_name)\n    ");

    foreach ($catalog as $slug => $meta) {
        $upsert->execute([$slug, $meta['name'], $meta['description'], $meta['group']]);
    }

    // Upgrade legacy grants: reports.view => reports.view_all
    $pdo->exec("\n        INSERT IGNORE INTO role_permissions (role_id, permission_id)\n        SELECT rp.role_id, p_new.id\n        FROM role_permissions rp\n        INNER JOIN permissions p_old ON p_old.id = rp.permission_id AND p_old.slug = 'reports.view'\n        INNER JOIN permissions p_new ON p_new.slug = 'reports.view_all'\n    ");
}

function getAllRoles(bool $includeInactive = true): array {
    if (!rbacTablesReady()) {
        $roles = [
            ['slug' => 'admin', 'name' => 'System Admin', 'is_system' => 1, 'is_active' => 1],
            ['slug' => 'ict_head', 'name' => 'ICT Head', 'is_system' => 1, 'is_active' => 1],
            ['slug' => 'assistant_manager', 'name' => 'Assistant Manager', 'is_system' => 1, 'is_active' => 1],
            ['slug' => 'assistant_ict', 'name' => 'Assistant ICT', 'is_system' => 1, 'is_active' => 1],
            ['slug' => 'sr_it_executive', 'name' => 'Sr. IT Executive', 'is_system' => 1, 'is_active' => 1],
            ['slug' => 'assistant_it', 'name' => 'Assistant IT', 'is_system' => 1, 'is_active' => 1],
        ];
        return $roles;
    }

    $pdo = getDB();
    $sql = "SELECT slug, name, is_system, is_active FROM roles";
    if (!$includeInactive) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY is_system DESC, display_order ASC, name ASC";
    return $pdo->query($sql)->fetchAll() ?: [];
}

/**
 * Ensure every role slug currently used by staff exists in roles table.
 * This keeps the roles UI in sync when staff are seeded/imported directly.
 */
function syncMissingRolesFromStaff(): void {
    if (!rbacTablesReady()) {
        return;
    }

    $pdo = getDB();

    $staffRoleRows = $pdo->query("\n        SELECT DISTINCT LOWER(TRIM(role)) AS slug\n        FROM it_staff\n        WHERE role IS NOT NULL AND TRIM(role) <> ''\n    ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if (empty($staffRoleRows)) {
        return;
    }

    $existingRoleRows = $pdo->query("SELECT LOWER(slug) AS slug FROM roles")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $existingMap = array_fill_keys(array_map('strval', $existingRoleRows), true);

    $insert = $pdo->prepare("\n        INSERT INTO roles (slug, name, is_system, is_active, display_order)\n        VALUES (?, ?, 0, 1, 900)\n    ");

    foreach ($staffRoleRows as $slugRaw) {
        $slug = strtolower(trim((string)$slugRaw));
        if ($slug === '' || isset($existingMap[$slug])) {
            continue;
        }

        $label = ucwords(str_replace(['_', '-'], ' ', $slug));
        $insert->execute([$slug, $label]);
        $existingMap[$slug] = true;
    }
}

function getRoleBySlug(string $slug): ?array {
    if (!rbacTablesReady()) {
        foreach (getAllRoles() as $role) {
            if ($role['slug'] === $slug) {
                return $role;
            }
        }
        return null;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT slug, name, is_system, is_active FROM roles WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function roleExists(string $slug, bool $mustBeActive = true): bool {
    if (!rbacTablesReady()) {
        return getRoleBySlug($slug) !== null;
    }

    $pdo = getDB();
    $sql = "SELECT 1 FROM roles WHERE slug = ?";
    if ($mustBeActive) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$slug]);
    return (bool)$stmt->fetchColumn();
}

function getRoleName(string $slug): string {
    $role = getRoleBySlug($slug);
    if ($role && !empty($role['name'])) {
        return (string)$role['name'];
    }
    return ucwords(str_replace('_', ' ', $slug));
}

function getRolePermissions(string $roleSlug): array {
    if (!rbacTablesReady()) {
        return legacyRolePermissionsMap()[$roleSlug] ?? [];
    }

    syncPermissionCatalog();

    $pdo = getDB();
    $stmt = $pdo->prepare("\n        SELECT p.slug\n        FROM permissions p\n        INNER JOIN role_permissions rp ON rp.permission_id = p.id\n        INNER JOIN roles r ON r.id = rp.role_id\n        WHERE r.slug = ?\n    ");
    $stmt->execute([$roleSlug]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function roleHasPermission(string $roleSlug, string $permissionSlug): bool {
    if ($roleSlug === '') {
        return false;
    }
    if ($roleSlug === 'admin') {
        return true;
    }

    if (!rbacTablesReady()) {
        return in_array($permissionSlug, legacyRolePermissionsMap()[$roleSlug] ?? [], true);
    }

    syncPermissionCatalog();

    $pdo = getDB();
    $stmt = $pdo->prepare("\n        SELECT 1\n        FROM role_permissions rp\n        INNER JOIN roles r ON r.id = rp.role_id\n        INNER JOIN permissions p ON p.id = rp.permission_id\n        WHERE r.slug = ? AND p.slug = ?\n        LIMIT 1\n    ");
    $stmt->execute([$roleSlug, $permissionSlug]);
    return (bool)$stmt->fetchColumn();
}

function currentStaffHasPermission(string $permissionSlug): bool {
    if (($_SESSION['user_type'] ?? '') !== 'staff') {
        return false;
    }
    $role = (string)($_SESSION['staff_role'] ?? '');
    return roleHasPermission($role, $permissionSlug);
}

function currentStaffCanAccessReports(): bool {
    return currentStaffHasPermission('reports.view_all')
        || currentStaffHasPermission('reports.view_own')
        || currentStaffHasPermission('reports.view');
}

function currentStaffCanViewOrganizationReports(): bool {
    return currentStaffHasPermission('reports.view_all')
        || currentStaffHasPermission('reports.view');
}

function getPermissionsWithSelection(string $roleSlug = ''): array {
    $catalog = rbacPermissionCatalog();
    if (!rbacTablesReady()) {
        $permissions = [];
        foreach ($catalog as $slug => $meta) {
            $permissions[] = [
                'id' => 0,
                'slug' => $slug,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'group_name' => $meta['group'],
            ];
        }
    } else {
        syncPermissionCatalog();
        $pdo = getDB();
        $permissions = $pdo->query("SELECT id, slug, name, description, group_name FROM permissions ORDER BY group_name, name")->fetchAll() ?: [];
    }

    $selected = [];
    if ($roleSlug !== '') {
        $selected = getRolePermissions($roleSlug);
    }
    $selectedMap = array_flip($selected);

    foreach ($permissions as &$perm) {
        $slug = (string)$perm['slug'];
        if (isset($catalog[$slug])) {
            if (empty($perm['name'])) {
                $perm['name'] = $catalog[$slug]['name'];
            }
            if (empty($perm['description'])) {
                $perm['description'] = $catalog[$slug]['description'];
            }
        }
        $perm['selected'] = isset($selectedMap[$slug]);
    }

    return $permissions;
}

function getStaffByPermission(string $permissionSlug, ?int $excludeStaffId = null): array {
    if (!rbacTablesReady()) {
        $pdo = getDB();
        $legacyRoles = [];
        foreach (legacyRolePermissionsMap() as $role => $perms) {
            if (in_array($permissionSlug, $perms, true)) {
                $legacyRoles[] = $role;
            }
        }

        if (empty($legacyRoles)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($legacyRoles), '?'));
        $sql = "SELECT id, name, email, contact, role, designation FROM it_staff WHERE is_active = 1 AND role IN ($placeholders)";
        $params = $legacyRoles;
        if ($excludeStaffId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeStaffId;
        }
        $sql .= " ORDER BY role, name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    $pdo = getDB();
    $sql = "\n        SELECT s.id, s.name, s.email, s.contact, s.role, s.designation\n        FROM it_staff s\n        INNER JOIN roles r ON r.slug = s.role\n        INNER JOIN role_permissions rp ON rp.role_id = r.id\n        INNER JOIN permissions p ON p.id = rp.permission_id\n        WHERE s.is_active = 1 AND r.is_active = 1 AND p.slug = ?\n    ";
    $params = [$permissionSlug];

    if ($excludeStaffId !== null) {
        $sql .= " AND s.id != ?";
        $params[] = $excludeStaffId;
    }

    $sql .= " ORDER BY r.display_order ASC, s.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function saveRoleWithPermissions(string $originalSlug, string $newSlug, string $name, bool $isActive, array $permissionSlugs): void {
    if (!rbacTablesReady()) {
        throw new RuntimeException('RBAC tables are not initialized. Import sql/schema.sql first.');
    }

    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        $isEdit = $originalSlug !== '';

        if ($isEdit) {
            $stmt = $pdo->prepare("SELECT id, is_system, slug FROM roles WHERE slug = ? LIMIT 1");
            $stmt->execute([$originalSlug]);
            $current = $stmt->fetch();
            if (!$current) {
                throw new RuntimeException('Role not found.');
            }

            if ($originalSlug === 'admin' && $originalSlug !== $newSlug) {
                throw new RuntimeException('Admin role slug cannot be changed.');
            }
            if ($newSlug === 'admin' && $originalSlug !== 'admin') {
                throw new RuntimeException('Reserved slug: admin.');
            }
            if ($originalSlug === 'admin' && !$isActive) {
                throw new RuntimeException('Admin role cannot be disabled.');
            }

            $stmt = $pdo->prepare("UPDATE roles SET slug = ?, name = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$newSlug, $name, $isActive ? 1 : 0, (int)$current['id']]);
            $roleId = (int)$current['id'];

            if ($originalSlug !== $newSlug) {
                $stmt = $pdo->prepare("UPDATE it_staff SET role = ? WHERE role = ?");
                $stmt->execute([$newSlug, $originalSlug]);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO roles (slug, name, is_system, is_active, display_order) VALUES (?, ?, 0, ?, 999)");
            $stmt->execute([$newSlug, $name, $isActive ? 1 : 0]);
            $roleId = (int)$pdo->lastInsertId();
        }

        $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);

        if (!empty($permissionSlugs)) {
            $ins = $pdo->prepare("\n                INSERT INTO role_permissions (role_id, permission_id)\n                SELECT ?, p.id\n                FROM permissions p\n                WHERE p.slug = ?\n            ");
            foreach ($permissionSlugs as $perm) {
                $ins->execute([$roleId, $perm]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

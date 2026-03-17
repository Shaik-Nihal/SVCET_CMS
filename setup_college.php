<?php
/**
 * College Branding/Setup Updater
 *
 * Usage:
 * 1) Edit the $profile values below.
 * 2) Run: php setup_college.php
 * 3) Script updates config/constants.php and copies logo/background files.
 */

$profile = [
    // Core identity
    'org_name' => 'SVCET College',
    'primary_domain' => 'svcet.edu.in',

    // Contact details
    'support_phone' => '9876543210',
    'support_hours' => 'Mon-Sat, 9 AM - 6 PM',

    // Ticket and DB settings
    'ticket_prefix' => 'SVC',
    'db_name' => 'tms_svcet',

    // Optional: force APP_URL. Keep null to auto-detect (recommended)
    'app_url' => null,

    // Branding files (absolute or relative paths)
    // If file exists, it will be copied into assets/images/ and constants are updated.
    'logo_source_path' => null,        // e.g. '/home/user/Downloads/svcet_logo.png'
    'background_source_path' => null,  // e.g. '/home/user/Downloads/svcet_bg.jpg'

    // Target filenames inside assets/images
    'logo_target_name' => 'college_logo.png',
    'background_target_name' => 'college_background.jpg',
];

$basePath = __DIR__;
$constantsFile = $basePath . '/config/constants.php';
$schemaFile = $basePath . '/sql/schema.sql';
$imagesDir = $basePath . '/assets/images';

if (!is_file($constantsFile)) {
    fwrite(STDERR, "ERROR: constants.php not found at: {$constantsFile}\n");
    exit(1);
}

$constantsContent = file_get_contents($constantsFile);
if ($constantsContent === false) {
    fwrite(STDERR, "ERROR: Unable to read constants.php\n");
    exit(1);
}

// Backup current constants file before change
$backupFile = $constantsFile . '.bak.' . date('Ymd_His');
if (!copy($constantsFile, $backupFile)) {
    fwrite(STDERR, "ERROR: Unable to create backup file: {$backupFile}\n");
    exit(1);
}

echo "Backup created: {$backupFile}\n";

$replacements = [
    'ORG_NAME' => var_export($profile['org_name'], true),
    'PRIMARY_DOMAIN' => var_export($profile['primary_domain'], true),
    'SUPPORT_PHONE' => var_export($profile['support_phone'], true),
    'SUPPORT_HOURS' => var_export($profile['support_hours'], true),
    'TICKET_PREFIX' => var_export($profile['ticket_prefix'], true),
    'DB_NAME' => var_export($profile['db_name'], true),
];

// Optional APP_URL override. If null, auto-detect logic remains untouched.
if (!empty($profile['app_url'])) {
    $replacements['APP_URL'] = var_export(rtrim($profile['app_url'], '/'), true);
}

$replaceDefine = static function (string $content, string $constant, string $newValue): string {
    $pattern = "/define\\('" . preg_quote($constant, '/') . "',\\s*[^;]+;\\s*(?:\\/\\/.*)?/";
    $replacement = "define('{$constant}', {$newValue});";
    return preg_replace($pattern, $replacement, $content, 1) ?? $content;
};

foreach ($replacements as $constant => $value) {
    $before = $constantsContent;
    $constantsContent = $replaceDefine($constantsContent, $constant, $value);
    if ($before === $constantsContent) {
        echo "WARN: Could not find constant {$constant} to update.\n";
    } else {
        echo "Updated {$constant}\n";
    }
}

$copyBrandFile = static function (?string $sourcePath, string $targetName, string $imagesDir) {
    if (empty($sourcePath)) {
        return [null, null];
    }

    $resolvedSource = $sourcePath;
    if (!str_starts_with($sourcePath, '/')) {
        $resolvedSource = getcwd() . '/' . ltrim($sourcePath, '/');
    }

    if (!is_file($resolvedSource)) {
        fwrite(STDERR, "WARN: Branding file not found: {$resolvedSource}\n");
        return [null, null];
    }

    if (!is_dir($imagesDir) && !mkdir($imagesDir, 0755, true)) {
        fwrite(STDERR, "WARN: Could not create images directory: {$imagesDir}\n");
        return [null, null];
    }

    $targetPath = $imagesDir . '/' . $targetName;
    if (!copy($resolvedSource, $targetPath)) {
        fwrite(STDERR, "WARN: Could not copy {$resolvedSource} to {$targetPath}\n");
        return [null, null];
    }

    return [$targetName, $targetPath];
};

[$logoFileName, $logoPath] = $copyBrandFile($profile['logo_source_path'], $profile['logo_target_name'], $imagesDir);
if ($logoFileName !== null) {
    $constantsContent = $replaceDefine($constantsContent, 'APP_LOGO_FILE', var_export($logoFileName, true));
    echo "Updated APP_LOGO_FILE => {$logoFileName}\n";
    echo "Copied logo to: {$logoPath}\n";
}

[$bgFileName, $bgPath] = $copyBrandFile($profile['background_source_path'], $profile['background_target_name'], $imagesDir);
if ($bgFileName !== null) {
    $constantsContent = $replaceDefine($constantsContent, 'APP_BACKGROUND_FILE', var_export($bgFileName, true));
    echo "Updated APP_BACKGROUND_FILE => {$bgFileName}\n";
    echo "Copied background to: {$bgPath}\n";
}

if (file_put_contents($constantsFile, $constantsContent) === false) {
    fwrite(STDERR, "ERROR: Failed to write updated constants.php\n");
    exit(1);
}

// Keep schema DB name aligned with constants DB_NAME.
if (is_file($schemaFile)) {
    $schemaContent = file_get_contents($schemaFile);
    if ($schemaContent !== false) {
        $dbName = $profile['db_name'];
        $schemaContent = preg_replace(
            '/CREATE DATABASE IF NOT EXISTS\s+`?[^`\s;]+`?/i',
            'CREATE DATABASE IF NOT EXISTS ' . $dbName,
            $schemaContent,
            1
        ) ?? $schemaContent;
        $schemaContent = preg_replace(
            '/USE\s+`?[^`\s;]+`?;/i',
            'USE ' . $dbName . ';',
            $schemaContent,
            1
        ) ?? $schemaContent;

        if (file_put_contents($schemaFile, $schemaContent) !== false) {
            echo "Updated schema DB name in sql/schema.sql\n";
        } else {
            echo "WARN: Could not write updates to sql/schema.sql\n";
        }
    } else {
        echo "WARN: Could not read sql/schema.sql for DB sync\n";
    }
} else {
    echo "WARN: sql/schema.sql not found; skipped DB sync\n";
}

echo "\nSetup applied successfully.\n";
echo "Next steps:\n";
echo "1) Import schema if DB name changed: mysql -u root < sql/schema.sql\n";
echo "2) Re-run seed if needed: php admin_seed/seed.php\n";
echo "3) Open app: http://localhost/" . basename($basePath) . "/\n";

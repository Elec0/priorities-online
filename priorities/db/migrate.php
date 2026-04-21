<?php
declare(strict_types=1);

/**
 * migrate.php - Apply pending SQL migrations from priorities/db/migrations.
 *
 * Usage:
 *   php priorities/db/migrate.php
 *   php priorities/db/migrate.php --dry-run
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "Migration error: {$e->getMessage()}\n");
    exit(1);
});

$dbBootstrap = __DIR__ . '/../includes/db.php';
if (!is_file($dbBootstrap)) {
    fwrite(STDERR, "Missing DB bootstrap file: {$dbBootstrap}\n");
    exit(1);
}

require_once $dbBootstrap;

$configCandidates = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
];

$hasConfigFile = false;
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $hasConfigFile = true;
        break;
    }
}

if (!$hasConfigFile) {
    fwrite(
        STDERR,
        "Migration error: No config.php found for DB credentials.\n" .
        "Expected one of:\n" .
        " - {$configCandidates[0]}\n" .
        " - {$configCandidates[1]}\n" .
        "Without config.php, defaults are used (user 'priorities' with empty password).\n"
    );
    exit(1);
}

if (!extension_loaded('PDO')) {
    fwrite(
        STDERR,
        "Migration error: PDO extension is not enabled for this PHP CLI binary.\n" .
        "Enable 'pdo' and 'pdo_mysql' for CLI, or run with the same PHP binary used by your website.\n"
    );
    exit(1);
}

if (!extension_loaded('pdo_mysql')) {
    fwrite(
        STDERR,
        "Migration error: pdo_mysql extension is not enabled for this PHP CLI binary.\n" .
        "Enable 'pdo_mysql' for CLI, then rerun migrations.\n"
    );
    exit(1);
}

/**
 * Split SQL text into executable statements, preserving semicolons inside strings.
 *
 * @return string[]
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $len = strlen($sql);

    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
                $buffer .= $ch;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($ch === '-' && $next === '-') {
                $next2 = $i + 2 < $len ? $sql[$i + 2] : '';
                if ($next2 === ' ' || $next2 === "\t" || $next2 === "\n" || $next2 === "\r") {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
            }
            if ($ch === '#') {
                $inLineComment = true;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($ch === "'" && !$inDouble && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inSingle = !$inSingle;
            }
            $buffer .= $ch;
            continue;
        }

        if ($ch === '"' && !$inSingle && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inDouble = !$inDouble;
            }
            $buffer .= $ch;
            continue;
        }

        if ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            $buffer .= $ch;
            continue;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function usage(): void
{
    echo "Usage: php priorities/db/migrate.php [--dry-run] [--mark-applied]\n";
}

$dryRun = false;
$markApplied = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }

    if ($arg === '--mark-applied') {
        $markApplied = true;
        continue;
    }

    if ($arg === '--help' || $arg === '-h') {
        usage();
        exit(0);
    }

    fwrite(STDERR, "Unknown argument: {$arg}\n");
    usage();
    exit(1);
}

if ($dryRun && $markApplied) {
    fwrite(STDERR, "--dry-run and --mark-applied cannot be used together.\n");
    usage();
    exit(1);
}

$migrationsDir = __DIR__ . '/migrations';
if (!is_dir($migrationsDir)) {
    fwrite(STDERR, "Migrations directory not found: {$migrationsDir}\n");
    exit(1);
}

$files = glob($migrationsDir . '/*.sql');
if ($files === false) {
    fwrite(STDERR, "Failed to read migration files.\n");
    exit(1);
}
sort($files, SORT_NATURAL);

$db = get_db();

$db->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT NOT NULL AUTO_INCREMENT,
        filename VARCHAR(255) NOT NULL,
        checksum CHAR(40) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_schema_migrations_filename (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$appliedStmt = $db->query('SELECT filename, checksum FROM schema_migrations ORDER BY id ASC');
$applied = [];
foreach ($appliedStmt->fetchAll() as $row) {
    $applied[$row['filename']] = $row['checksum'];
}

$pending = [];
foreach ($files as $path) {
    $filename = basename($path);
    $checksum = sha1_file($path);
    if ($checksum === false) {
        fwrite(STDERR, "Failed to checksum migration: {$filename}\n");
        exit(1);
    }

    if (isset($applied[$filename])) {
        if ($applied[$filename] !== $checksum) {
            fwrite(
                STDERR,
                "Checksum mismatch for already-applied migration {$filename}. " .
                "Do not modify applied migration files; create a new migration instead.\n"
            );
            exit(1);
        }
        continue;
    }

    $pending[] = [
        'path' => $path,
        'filename' => $filename,
        'checksum' => $checksum,
    ];
}

if (count($pending) === 0) {
    echo "No pending migrations.\n";
    exit(0);
}

echo "Pending migrations: " . count($pending) . "\n";
foreach ($pending as $m) {
    echo " - {$m['filename']}\n";
}

if ($dryRun) {
    echo "Dry run complete. No changes were applied.\n";
    exit(0);
}

$insertStmt = $db->prepare(
    'INSERT INTO schema_migrations (filename, checksum) VALUES (:filename, :checksum)'
);

if ($markApplied) {
    foreach ($pending as $m) {
        echo "Marking as applied (no SQL execution): {$m['filename']}\n";
        $insertStmt->execute([
            ':filename' => $m['filename'],
            ':checksum' => $m['checksum'],
        ]);
    }
    echo "Pending migrations were marked as applied without execution.\n";
    exit(0);
}

foreach ($pending as $m) {
    $sql = file_get_contents($m['path']);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read migration: {$m['filename']}\n");
        exit(1);
    }

    $statements = split_sql_statements($sql);
    if (count($statements) === 0) {
        echo "Skipping empty migration: {$m['filename']}\n";
        $insertStmt->execute([
            ':filename' => $m['filename'],
            ':checksum' => $m['checksum'],
        ]);
        continue;
    }

    echo "Applying {$m['filename']}...\n";
    try {
        $db->beginTransaction();
        foreach ($statements as $stmt) {
            $db->exec($stmt);
        }

        $insertStmt->execute([
            ':filename' => $m['filename'],
            ':checksum' => $m['checksum'],
        ]);

        // MySQL auto-commits some DDL statements (e.g. ALTER TABLE),
        // which ends the transaction implicitly. Only commit if still active.
        if ($db->inTransaction()) {
            $db->commit();
        }
        echo "Applied {$m['filename']}\n";
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        fwrite(STDERR, "Failed migration {$m['filename']}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "All pending migrations applied successfully.\n";

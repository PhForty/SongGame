<?php
/**
 * ONE-SHOT schema applier — uploaded by .github/workflows/deploy.yml, called
 * exactly once over HTTPS, then removed again.
 *
 * Three independent safeguards keep the window small, because this file is the
 * only thing on the server that can drop the database:
 *   1. A per-run random token, required in the X-Deploy-Token header.
 *   2. A hard expiry a few minutes out — after that it refuses and unlinks.
 *   3. It unlinks itself once it has run; the workflow also deletes it over FTP
 *      in an `always()` step, so a failed run still cleans up.
 *
 * The @TOKEN@/@EXPIRES@ placeholders are substituted by the workflow.
 */

const SG_TOKEN   = '@TOKEN@';
const SG_EXPIRES = @EXPIRES@;

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

function sg_shred() {
    @unlink(__FILE__);
}

function sg_die($status, $message) {
    http_response_code($status);
    echo $message . "\n";
    exit($status >= 400 ? 1 : 0);
}

if (time() > SG_EXPIRES) {
    sg_shred();
    sg_die(410, 'EXPIRED');
}

$given = isset($_SERVER['HTTP_X_DEPLOY_TOKEN']) ? $_SERVER['HTTP_X_DEPLOY_TOKEN'] : '';
if (!is_string($given) || !hash_equals(SG_TOKEN, $given)) {
    // Deliberately no shred here: a wrong guess must not be able to sabotage
    // the deploy that is about to present the right token.
    sg_die(403, 'FORBIDDEN');
}

// From here on the caller is authenticated, so every exit shreds the file.
register_shutdown_function('sg_shred');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Migrations.php';

$schemaPath = __DIR__ . '/schema.sql';
$sql = @file_get_contents($schemaPath);
if ($sql === false || trim($sql) === '') {
    sg_die(500, 'SCHEMA_MISSING ' . $schemaPath);
}

$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_errno) {
    sg_die(500, 'DB_CONNECT_FAILED ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// schema.sql is a plain batch of DDL, so multi_query is enough — no delimiters,
// no stored routines. Results must be drained or errno stays stale.
if (!$conn->multi_query($sql)) {
    sg_die(500, 'SCHEMA_FAILED ' . $conn->error);
}
do {
    if ($result = $conn->store_result()) {
        $result->free();
    }
    if ($conn->errno) {
        sg_die(500, 'SCHEMA_FAILED ' . $conn->error);
    }
} while ($conn->next_result());

if ($conn->errno) {
    sg_die(500, 'SCHEMA_FAILED ' . $conn->error);
}

/*
 * schema.sql hardcodes schema_version = 1. Pin it to whatever Migrations.php
 * currently targets instead, so a fresh schema is never re-migrated by steps
 * that it already contains.
 */
$stmt = $conn->prepare(
    "INSERT INTO app_config (`key`, `value`) VALUES ('schema_version', ?)
     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
);
if ($stmt) {
    $version = (string) Migrations::TARGET_VERSION;
    $stmt->bind_param('s', $version);
    $stmt->execute();
    $stmt->close();
}

echo "SCHEMA_APPLIED db=" . DB_NAME . " schema_version=" . Migrations::TARGET_VERSION . "\n";

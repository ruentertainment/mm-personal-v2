<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes/DBHandler.php';
require_once __DIR__ . '/classes/SFTPHandler.php';
require_once __DIR__ . '/classes/CallImporter.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = require __DIR__ . '/config/config.php';
$dbConfig = $config['db'];
$sftpConfig = $config['sftp'];

$dryRun = in_array('--dry-run', $argv, true);

try {
    $dbHandler = new DBHandler(
        $dbConfig['host'],
        $dbConfig['dbname'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['port'] ?? 3306,
        $dbConfig['charset'] ?? 'utf8mb4'
    );
    $sftpHandler = new SFTPHandler(
        $sftpConfig['host'],
        $sftpConfig['port'] ?? 22,
        $sftpConfig['username'],
        $sftpConfig['password'],
        $sftpConfig['remoteDir'],
        $sftpConfig['processedDir'],
        $sftpConfig['failedDir']
    );

    $importer = new CallImporter($dbHandler, $sftpHandler);
    $result = $importer->importCalls($dryRun);
    echo "Processed: {$result['processed']} Failed: {$result['failed']}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Import error: ' . $e->getMessage() . PHP_EOL);
}
<?php

require_once __DIR__ . '/../helpers.php';

class CallImporter
{
    private DBHandler $db;
    private SFTPHandler $sftp;
    private string $logFile;

    public function __construct(DBHandler $db, SFTPHandler $sftp, string $logFile = __DIR__ . '/../logs/import.log')
    {
        $this->db = $db;
        $this->sftp = $sftp;
        $this->logFile = $logFile;
    }

    private function logError(string $file, \Throwable $e, string $step): void
    {
        $entry = [
            'timestamp' => date('c'),
            'file'      => $file,
            'step'      => $step,
            'error'     => get_class($e),
            'message'   => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
        ];
        file_put_contents($this->logFile, json_encode($entry) . PHP_EOL, FILE_APPEND);
    }

    public function importCalls(bool $dryRun = false): array
    {
        $processed = 0;
        $failed = 0;

        foreach ($this->sftp->getFiles() as $file) {
            if (!str_ends_with($file, '.log')) {
                continue;
            }

            $step = 'parse';
            try {
                $stream = $this->sftp->getFileStream($file);
                if (!$stream) {
                    throw new RuntimeException('unable to read file');
                }

                $pdo = $this->db->getPdo();
                $pdo = $this->db->getConnection();
                if (!$dryRun) {
                    $pdo->beginTransaction();
                }

                while (($line = fgets($stream)) !== false) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    if (str_starts_with($line, 'Call ')) {
                        $line = substr($line, 5);
                    }
                    $fields = explode(',', $line);
                    if (count($fields) < 20) {
                        throw new RuntimeException('insufficient fields');
                    }

                    // Normalize phone numbers before inserting
                    $fields[7]  = normalizePhoneNumber($fields[7]);
                    $fields[8]  = normalizePhoneNumber($fields[8]);
                    $fields[11] = normalizePhoneNumber($fields[11]);
                    $fields[13] = normalizePhoneNumber($fields[13]);

                    if (!$dryRun) {
                        $step = 'insert';
                        if (!$this->db->insertCall($fields)) {
                            throw new RuntimeException('DB insert failed');
                        }
                    }
                }

                if (!$dryRun) {
                    $pdo->commit();
                    $step = 'move';
                    if (!$this->sftp->moveToProcessed($file)) {
                        throw new RuntimeException('move to processed failed');
                    }
                }

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                if (!$dryRun && isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $this->logError($file, $e, $step);
                if (!$dryRun) {
                    $this->sftp->moveToFailed($file);
                    $info = [
                        'timestamp' => date('c'),
                        'file'      => $file,
                        'step'      => $step,
                        'error'     => get_class($e),
                        'message'   => $e->getMessage(),
                    ];
                    $this->sftp->writeFile($this->sftp->failedDir . '/' . $file . '.error.json', json_encode($info, JSON_PRETTY_PRINT));
                }
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }
}

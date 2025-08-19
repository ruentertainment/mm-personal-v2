<?php
use phpseclib3\Net\SFTP;

class SFTPHandler {

    private $host, $user, $pass, $port, $sftp;
    public $remoteDir;
    public $processedDir;
    public $failedDir;

    public function __construct($host, $port, $user, $pass, $remoteDir, $processedDir, $failedDir) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->remoteDir = $remoteDir;
        $this->processedDir = $processedDir;
        $this->failedDir = $failedDir;

        $this->sftp = new SFTP($host, $port);
        if (!$this->sftp->login($user, $pass)) {
            throw new RuntimeException('SFTP-Login fehlgeschlagen');
        }
    }

    public function getFiles(): array {
        return $this->sftp->nlist($this->remoteDir) ?: [];
    }

    public function getFileStream(string $file)
    {
        $stream = fopen('php://temp', 'r+');
        if (!$this->sftp->get("{$this->remoteDir}/$file", $stream)) {
            return false;
        }
        rewind($stream);
        return $stream;
    }

    public function moveToProcessed($file): bool {
        return $this->sftp->rename("{$this->remoteDir}/$file", "{$this->processedDir}/$file");
    }

    public function moveToFailed($file): bool {
        return $this->sftp->rename("{$this->remoteDir}/$file", "{$this->failedDir}/$file");
    }

    public function writeFile(string $path, string $content): bool {
        return $this->sftp->put($path, $content);
    }
}
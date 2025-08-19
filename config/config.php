<?php

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'call_rapport_db',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'sftp' => [
        'host' => '199.247.20.99',
        'port' => 22,
        'username' => 'root',
        'password' => 'a9$L?2Jx7UfqotPs',
        'remoteDir' => '/var/lib/3cxpbx/Instance1/Data/Logs/CDRLogs',
        'processedDir' => '/var/lib/3cxpbx/Instance1/Data/Logs/CDRLogs/processed',
        'failedDir' => '/var/lib/3cxpbx/Instance1/Data/Logs/CDRLogs/failed',
    ],
];
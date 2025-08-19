<?php

use Medoo\Medoo;

class DBHandler
{
    private Medoo $db;

    public function __construct($host, $dbname, $user, $pass, $port = 3306, $charset = 'utf8mb4')
    {
        $this->db = new Medoo([
            'type'     => 'mysql',
            'host'     => $host,
            'database' => $dbname,
            'username' => $user,
            'password' => $pass,
            'port'     => $port,
            'charset'  => $charset,
        ]);
    }

    public function insertCall(array $fields): bool
    {
        if (count($fields) < 20) {
            error_log('Insert failed: insufficient fields ' . json_encode($fields));
            return false;
        }

        try {
            $timeStart    = date('Y-m-d H:i:s', strtotime($fields[3]));
            $timeAnswered = date('Y-m-d H:i:s', strtotime($fields[4]));
            $timeEnd      = date('Y-m-d H:i:s', strtotime($fields[5]));

            $this->db->insert('calls_per_report', [
                'historyid'         => $fields[0],
                'callid'            => $fields[1],
                'duration'          => $fields[2],
                'time_start'        => $timeStart,
                'time_answered'     => $timeAnswered,
                'time_end'          => $timeEnd,
                'reason_terminated' => $fields[6],
                'from_no'           => $fields[7],
                'to_no'             => $fields[8],
                'from_dn'           => $fields[9],
                'to_dn'             => $fields[10],
                'dial_no'           => $fields[11],
                'reason_changed'    => $fields[12],
                'final_number'      => $fields[13],
                'final_dn'          => $fields[14],
                'bill_code'         => $fields[15],
                'bill_rate'         => $fields[16],
                'bill_cost'         => $fields[17],
                'bill_name'         => $fields[18],
                'chain'             => $fields[19],
            ]);

            return $this->db->id() !== null;
        } catch (\Throwable $e) {
            error_log('Insert failed: ' . $e->getMessage() . ' Data: ' . json_encode($fields));
            return false;
        }
    }

    public function getAllUsers(): array
    {
        return $this->db->select('calls_per_report', 'from_dn', ['GROUP' => 'from_dn']);
    }

    public function getCallStats($user): array
    {
        $result = $this->db->query(
            "SELECT COUNT(*) AS total,
                    COUNT(*) / COUNT(DISTINCT DATE(time_start)) AS daily,
                    COUNT(*) / COUNT(DISTINCT YEARWEEK(time_start, 1)) AS weekly,
                    COUNT(*) / COUNT(DISTINCT DATE_FORMAT(time_start, '%Y-%m')) AS monthly
             FROM calls_per_report 
             WHERE from_dn = :user",
            [':user' => $user]
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'Total'     => $result['total'] ?? 0,
            'Pro Tag'   => round((float)($result['daily'] ?? 0), 2),
            'Pro Woche' => round((float)($result['weekly'] ?? 0), 2),
            'Pro Monat' => round((float)($result['monthly'] ?? 0), 2),
        ];
    }

    public function getConnection(): Medoo
    {
        return $this->db;
    }

    public function getPdo(): PDO
    {
        return $this->db->pdo;
    }
}

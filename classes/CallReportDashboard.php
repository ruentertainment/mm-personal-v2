<?php

class CallReportDashboard
{
    private $db;

    public function __construct(DBHandler $db)
    {
        $this->db = $db;
    }

    public function getAllUsers(): array
    {
        return $this->db->getConnection()->select('calls_per_report', 'from_dn', ['GROUP' => 'from_dn']);
    }

    private function contactsMap(): array
    {
        $db = $this->db->getConnection();
        $contacts = $db->select('contact', '*');

        $map = [];
        foreach ($contacts as $contact) {
            foreach (['mobile', 'mobile2', 'company_number', 'company_number2', 'private'] as $field) {
                if (!empty($contact[$field])) {
                    $norm = $this->normalizePhoneNumber($contact[$field]);
                    if (!isset($map[$norm])) {
                        $map[$norm] = $contact;
                    }
                }
            }
        }
        return $map;
    }

    private function queryCalls(string $user, string $start, ?string $end = null): array
    {
        $db = $this->db->getConnection();
        $where = [
            'AND' => [
                'OR' => ['from_dn' => $user, 'to_dn' => $user],
                'time_start[>=]' => $start,
            ]
        ];
        if ($end) {
            $where['AND']['time_start[<=]'] = $end;
        }
        return $db->select('calls_per_report', '*', $where);
        $pdo = $this->db->getConnection();
        $sql = "SELECT * FROM calls_per_report WHERE (from_dn = :user OR to_dn = :user) AND time_start >= :start";
        $params = ['user' => $user, 'start' => $start];
        if ($end) {
            $sql .= " AND time_start <= :end";
            $params['end'] = $end;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function durationFromRow(array $row): int
    {
        $duration = isset($row['duration']) ? (int) $row['duration'] : 0;
        if ($duration > 0) {
            return $duration;
        }
        $start = isset($row['time_start']) ? strtotime($row['time_start']) : false;
        $end   = isset($row['time_end']) ? strtotime($row['time_end']) : false;
        if ($start === false || $end === false) {
            return 0;
        }
        $diff = $end - $start;
        return $diff > 0 ? $diff : 0;
    }

    public static function emptyStats(): array
    {
        return [
            'kunden_total' => 0,
            'kunden_in' => 0,
            'kunden_out' => 0,
            'kunden_in_time' => 0,
            'kunden_out_time' => 0,
            'kandidaten_total' => 0,
            'kandidaten_in' => 0,
            'kandidaten_out' => 0,
            'kandidaten_in_time' => 0,
            'kandidaten_out_time' => 0,
            'sonst_total' => 0,
            'sonst_in' => 0,
            'sonst_out' => 0,
            'sonst_in_time' => 0,
            'sonst_out_time' => 0,
            'total_in_time' => 0,
            'total_out_time' => 0,
            'total_in_count' => 0,
            'total_out_count' => 0,
        ];
    }

    public static function updateStats(array &$stats, array $row, string $user, string $type): void
    {
        $isOutgoing = $row['from_dn'] === $user;
        $dir = $isOutgoing ? 'out' : 'in';
        $stats[$type . '_total']++;
        $stats[$type . '_' . $dir]++;
        $stats['total_' . $dir . '_count']++;
        $duration = self::durationFromRow($row);
        $stats['total_' . $dir . '_time'] += $duration;
        $stats[$type . '_' . $dir . '_time'] += $duration;
    }

    public static function aggregateDurations(array $rows, string $user): array
    {
        $totals = ['total_in_time' => 0, 'total_out_time' => 0];
        foreach ($rows as $row) {
            $d = self::durationFromRow($row);
            if (($row['from_dn'] ?? '') === $user) {
                $totals['total_out_time'] += $d;
            } else {
                $totals['total_in_time'] += $d;
            }
        }
        return $totals;
    }

    private function aggregate(array $rows, array $map, string $user): array
    {
        $stats = ['kunden' => 0, 'kandidaten' => 0, 'incoming' => 0, 'outgoing' => 0];

        foreach ($rows as $row) {
            $isOutgoing = $row['from_dn'] === $user;
            $other = $isOutgoing ? $row['to_no'] : $row['from_no'];
            $norm = $this->normalizePhoneNumber($other);
            $contact = $map[$norm] ?? null;

            $type = $contact ? ($contact['company_name'] !== '' ? 'kunden' : 'kandidaten') : 'kandidaten';
            $dir = $isOutgoing ? 'outgoing' : 'incoming';

            $stats[$type]++;
            $stats[$dir]++;
        }

        return $stats;
    }

    public function getStatsFromWeek(string $user, int $year, int $week): array
    {
        $start = new DateTime();
        $start->setISODate($year, $week);

        $map = $this->contactsMap();
        $rows = $this->queryCalls($user, $start->format('Y-m-d H:i:s'), null);
        return $this->aggregate($rows, $map, $user);
    }

    public function getWeeklyStats(string $user, int $year, int $week): array
    {
        $start = new DateTime();
        $start->setISODate($year, $week);
        $end = (clone $start)->modify('+6 days 23 hours 59 minutes 59 seconds');

        $map = $this->contactsMap();
        $rows = $this->queryCalls($user, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));

        $stats = [
            'kunden_total'     => 0,
            'kunden_in'        => 0,
            'kunden_out'       => 0,
            'kandidaten_total' => 0,
            'kandidaten_in'    => 0,
            'kandidaten_out'   => 0,
            'sonst_total'      => 0,
            'sonst_in'         => 0,
            'sonst_out'        => 0,
            'total_in_time'    => 0,
            'total_out_time'   => 0,
        ];

        foreach ($rows as $row) {
            $isOutgoing = $row['from_dn'] === $user;
            $other = $isOutgoing ? $row['to_no'] : $row['from_no'];
            $norm = $this->normalizePhoneNumber($other);
            $contact = $map[$norm] ?? null;
            $type = $contact ? ($contact['company_name'] !== '' ? 'kunden' : 'kandidaten') : 'sonst';
            self::updateStats($stats, $row, $user, $type);

            $type = $contact ? ($contact['company_name'] !== '' ? 'kunden' : 'kandidaten') : 'sonst';
            $dir  = $isOutgoing ? 'out' : 'in';

            $stats[$type . '_total']++;
            $stats[$type . '_' . $dir]++;

            $duration = self::durationFromRow($row);
            if ($isOutgoing) {
                $stats['total_out_time'] += $duration;
            } else {
                $stats['total_in_time'] += $duration;
            }
        }

        return $stats;
    }

    public function getCallStatisticsForWeek(string $user, int $year, int $week, bool $fourWeekPeriod = false): array
    {
        $db = $this->db->getConnection();
        $contacts = $db->select('contact', '*');
        $map = [];
        foreach ($contacts as $contact) {
            foreach (['mobile', 'mobile2', 'company_number', 'company_number2', 'private'] as $field) {
                if (!empty($contact[$field])) {
                    $norm = $this->normalizePhoneNumber($contact[$field]);
                    if (!isset($map[$norm])) {
                        $map[$norm] = $contact;
                    }
                }
            }
        }

        $weekStart = new DateTime();
        $weekStart->setISODate($year, $week);
        $currentDow = (int) date('N') - 1;
        $todayDate = (clone $weekStart)->modify('+' . $currentDow . ' days');

        $weekEnd   = (clone $weekStart)->modify('+6 days 23 hours 59 minutes 59 seconds');
        $todayStart = (clone $todayDate)->setTime(0, 0, 0);
        $todayEnd   = (clone $todayDate)->setTime(23, 59, 59);

        if ($fourWeekPeriod) {
            $monthStart = (clone $weekStart)->modify('-4 weeks')->setTime(0, 0, 0);
            $monthEnd   = (clone $weekStart)->modify('-1 day')->setTime(23, 59, 59);
        } else {
            $monthStart = (clone $weekStart)->modify('first day of this month')->setTime(0, 0, 0);
            $monthEnd   = (clone $weekStart)->modify('last day of this month')->setTime(23, 59, 59);
        }

        $rows = $this->queryCalls(
            $user,
            $monthStart->format('Y-m-d H:i:s'),
            $monthEnd->format('Y-m-d H:i:s')
        );

        $init = fn() => [
            'kunden_total'      => 0,
            'kunden_in'         => 0,
            'kunden_out'        => 0,
            'kandidaten_total'  => 0,
            'kandidaten_in'     => 0,
            'kandidaten_out'    => 0,
            'sonst_total'       => 0,
            'sonst_in'          => 0,
            'sonst_out'         => 0,
            'total_in_time'     => 0,
            'total_out_time'    => 0,
        ];
        $stats = [
            'today' => $init(),
            'week'  => $init(),
            'month' => $init(),
        ];

        $weekStartTs = $weekStart->getTimestamp();
        $weekEndTs   = $weekEnd->getTimestamp();
        $todayStartTs = $todayStart->getTimestamp();
        $todayEndTs   = $todayEnd->getTimestamp();

        foreach ($rows as $row) {
            $isOutgoing = $row['from_dn'] === $user;
            $other = $isOutgoing ? $row['to_no'] : $row['from_no'];
            $norm = $this->normalizePhoneNumber($other);
            $contact = $map[$norm] ?? null;

            $type = $contact ? ($contact['company_name'] !== '' ? 'kunden' : 'kandidaten') : 'sonst';
            $ts = strtotime($row['time_start']);
            self::updateStats($stats['month'], $row, $user, $type);
            if ($ts >= $weekStartTs && $ts <= $weekEndTs) {
                self::updateStats($stats['week'], $row, $user, $type);
                if ($ts >= $todayStartTs && $ts <= $todayEndTs) {
                    self::updateStats($stats['today'], $row, $user, $type);
                }
            }
        }

        return $stats;
    }

    public function getAverageCalls(string $user): array
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                DATE(time_start) as tag, 
                COUNT(*) as anzahl
            FROM calls_per_report
            WHERE from_dn = ?
            GROUP BY DATE(time_start)
        ");
        $stmt->execute([$user]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tage = count($rows);
        $gesamt = array_sum(array_column($rows, 'anzahl'));

        $daily = $tage > 0 ? round($gesamt / $tage, 2) : 0;
        $weekly = $tage > 0 ? round($gesamt / ($tage / 7), 2) : 0;
        $monthly = $tage > 0 ? round($gesamt / ($tage / 30), 2) : 0;
        return [
            'daily' => $daily,
            'weekly'  => $weekly,
            'monthly' => $monthly,
        ];
    }

    public function getCallStatistics(string $user, int $year, int $week): array
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->query("SELECT * FROM contact");
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $nummernZuKontakt = [];
        foreach ($contacts as $contact) {
            foreach (['mobile', 'mobile2', 'company_number', 'company_number2', 'private'] as $feld) {
                if (!empty($contact[$feld])) {
                    $norm = $this->normalizePhoneNumber($contact[$feld]);
                    if (!isset($nummernZuKontakt[$norm])) {
                        $nummernZuKontakt[$norm] = $contact;
                    }
                }
            }
        }

        $startWeek = new DateTime();
        $startWeek->setISODate($year, $week, 1)->modify('-3 weeks')->setTime(0,0,0);
        $endWeek = new DateTime();
        $endWeek->setISODate($year, $week, 7)->setTime(23,59,59);
        $today = $endWeek->format('Y-m-d');
        $weekStr = sprintf('%04d-%02d', $year, $week);

        $stmt = $pdo->prepare("SELECT * FROM calls_per_report WHERE (from_dn = :user OR to_dn = :user) AND time_start BETWEEN :start AND :end");
        $stmt->execute([
            'user' => $user,
            'start' => $startWeek->format('Y-m-d H:i:s'),
            'end'   => $endWeek->format('Y-m-d H:i:s')
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $init = fn() => [
            'kunde_in' => 0,
            'kunde_out' => 0,
            'kand_in' => 0,
            'kand_out' => 0,
            'sonst_in' => 0,
            'sonst_out' => 0
        ];

        $stats = [
            'heute' => $init(),
            'woche' => $init(),
            'monat' => $init()
        ];

        foreach ($rows as $row) {
            $isOutgoing = $row['from_dn'] === $user;
            $gegner = $isOutgoing ? $row['to_no'] : $row['from_no'];
            $nummer = $this->normalizePhoneNumber($gegner);
            $contact = $nummernZuKontakt[$nummer] ?? null;

            if ($contact) {
                $base = $contact['company_name'] !== '' ? 'kunde' : 'kand';
            } else {
                $base = 'sonst';
            }
            $key = $base . '_' . ($isOutgoing ? 'out' : 'in');

            $zeit = strtotime($row['time_start']);
            $datum = date('Y-m-d', $zeit);
            $wostr = date('o-W', $zeit);

            if ($datum === $today) {
                $stats['heute'][$key]++;
            }
            if ($wostr === $weekStr) {
                $stats['woche'][$key]++;
            }
            if ($zeit >= $startWeek->getTimestamp() && $zeit <= $endWeek->getTimestamp()) {
                $stats['monat'][$key]++;
            }
        }

        return $stats;
    }

    private function normalizePhoneNumber(string $number): string
    {
        $number = preg_replace('/[\s\-\(\)]+/', '', $number);
        $number = preg_replace('/^\+41/', '0', $number);
        $number = preg_replace('/^0041/', '0', $number);
        return $number;
    }
}

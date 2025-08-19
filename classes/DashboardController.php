<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/DBHandler.php';
require_once __DIR__ . '/../classes/CallReportDashboard.php';

class DashboardController
{
    public function run()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $config = require __DIR__ . '/../config/config.php';
        $dbConfig = $config['db'];

        $dbHandler = new DBHandler(
            $dbConfig['host'],
            $dbConfig['dbname'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['port'] ?? 3306,
            $dbConfig['charset'] ?? 'utf8mb4'
        );

        $dashboard = new CallReportDashboard($dbHandler);
        $users = $dashboard->getAllUsers();
        $selectedUser = $_GET['user'] ?? null;
        $weekOffset = isset($_GET['week_offset']) ? (int) $_GET['week_offset'] : 1;

        $currentStats = null;
        $currentLabels = [];
        $weeklyStats = [];
        $selectedYear = null;
        $selectedWeek = null;
        $currentYear = null;
        $currentWeek = null;

        if ($selectedUser) {
            $currentYear = (int) date('o');
            $currentWeek = (int) date('W');
            $currentStats = $dashboard->getCallStatisticsForWeek($selectedUser, $currentYear, $currentWeek);

            $currentWeekStart = new DateTime();
            $currentWeekStart->setISODate($currentYear, $currentWeek);
            $currentWeekEnd = (clone $currentWeekStart)->modify('+6 days');
            $currentDow = (int) date('N') - 1;
            $currentToday = (clone $currentWeekStart)->modify("+{$currentDow} days");
            $currentMonthStart = (clone $currentWeekStart)->modify('first day of this month');
            $currentMonthEnd = (clone $currentWeekStart)->modify('last day of this month');
            $currentLabels = [
                'today' => 'Heute (' . $currentToday->format('d.m.Y') . ')',
                'week' => 'Woche KW ' . $currentWeek . ' (' . $currentWeekStart->format('d.m.Y') . ' - ' . $currentWeekEnd->format('d.m.Y') . ')',
                'month' => 'Monat ' . $currentWeekStart->format('m.Y') . ' (' . $currentMonthStart->format('d.m.Y') . ' - ' . $currentMonthEnd->format('d.m.Y') . ')',
            ];

            if ($weekOffset >= 1) {
                $baseDate = new DateTime();
                $baseDate->modify("-{$weekOffset} week");
                $selectedYear = (int) $baseDate->format('o');
                $selectedWeek = (int) $baseDate->format('W');

                for ($i = 0; $i < 5; $i++) {
                    $weekDate = (clone $baseDate)->modify("-{$i} week");
                    $year = (int) $weekDate->format('o');
                    $week = (int) $weekDate->format('W');
                    $stats = $dashboard->getWeeklyStats($selectedUser, $year, $week);
                    $weeklyStats[] = [
                        'year'  => $year,
                        'week'  => $week,
                        'stats' => $stats,
                    ];
                }
            }
        }

        require __DIR__ . '/../index.php';
    }
}
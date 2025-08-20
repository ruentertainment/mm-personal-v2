<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php'; // wichtiger absoluter Pfad

require __DIR__ . '/classes/DBHandler.php';
// ... restlicher Code

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes/DBHandler.php';
require_once __DIR__ . '/classes/CallReportDashboard.php';
require_once __DIR__ . '/helpers.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = require __DIR__ . '/config/config.php';
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

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Call Report Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="print.css" rel="stylesheet" media="print">
</head>
<body class="bg-light">
<div class="container py-5">
    <h1 class="display-5 fw-bold print-header">
        <i class="bi bi-telephone-inbound me-2"></i> Anruf-Statistik Dashboard
    </h1>
    <div class="container mt-5 no-print">
        <div class="bg-light p-5 rounded shadow-sm">
            <p class="lead text-muted mb-0">Datum: <strong><?= date('d.m.Y'); ?></strong></p><br>
            <p class="lead text-muted mb-0">
                Übersicht aller Anrufe. Wählen Sie eine Mitarbeiternummer aus und eine Kalenderwoche, um die aktuellen Statistiken einzusehen.
            </p>
        </div>
        <br>
    </div>
    <form method="GET" class="mb-4 no-print">
        <div class="row g-3 align-items-center">
            <div class="col-auto">
                <label for="user" class="col-form-label">Mitarbeiter:</label>
            </div>
            <div class="col-auto">
                <select name="user" id="user" class="form-select" onchange="this.form.submit()">
                    <option value="">-- auswählen --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user) ?>" <?= $selectedUser === $user ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
    <?php if ($selectedUser): ?>
        <?php if ($currentStats): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="card-title">Anrufstatistik der aktuellen Kalenderwoche: <?= $currentWeek ?>, <?= $currentYear ?></h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mt-3">
                        <thead class="table-light">
                        <tr>
                            <th>Zeitraum</th>
                            <th>Kunden Gesamt</th>
                            <th>Kandidaten Gesamt</th>
                            <th>Sonstiges Gesamt</th>
                            <th>Total Eingehend</th>
                            <th>Total Ausgehend</th>
                            <th>Total Eingehend (Zeit)</th>
                            <th>Total Ausgehend (Zeit)</th>
                            <th>Kunden Eingehend</th>
                            <th>Kunden Ausgehend</th>
                            <th>Kunden Eingehend (Zeit)</th>
                            <th>Kunden Ausgehend (Zeit)</th>
                            <th>Kandidaten Eingehend</th>
                            <th>Kandidaten Ausgehend</th>
                            <th>Kandidaten Eingehend (Zeit)</th>
                            <th>Kandidaten Ausgehend (Zeit)</th>
                            <th>Sonstiges Eingehend</th>
                            <th>Sonstiges Ausgehend</th>
                            <th>Sonstiges Eingehend (Zeit)</th>
                            <th>Sonstiges Ausgehend (Zeit)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (['today', 'week', 'month'] as $key): ?>
                            <tr>
                                <td><?= $currentLabels[$key] ?></td>
                                <td><?= $currentStats[$key]['kunden_total'] ?></td>
                                <td><?= $currentStats[$key]['kandidaten_total'] ?></td>
                                <td><?= $currentStats[$key]['sonst_total'] ?></td>
                                <td><?= $currentStats[$key]['total_in_count'] ?></td>
                                <td><?= $currentStats[$key]['total_out_count'] ?></td>
                                <td><?= fmt_duration($currentStats[$key]['total_in_time']) ?></td>
                                <td><?= fmt_duration($currentStats[$key]['total_out_time']) ?></td>
                                <td><?= $currentStats[$key]['kunden_in'] ?></td>
                                <td><?= $currentStats[$key]['kunden_out'] ?></td>
                                <td><?= fmt_duration($currentStats[$key]['kunden_in_time']) ?></td>
                                <td><?= fmt_duration($currentStats[$key]['kunden_out_time']) ?></td>
                                <td><?= $currentStats[$key]['kandidaten_in'] ?></td>
                                <td><?= $currentStats[$key]['kandidaten_out'] ?></td>
                                <td><?= fmt_duration($currentStats[$key]['kandidaten_in_time']) ?></td>
                                <td><?= fmt_duration($currentStats[$key]['kandidaten_out_time']) ?></td>
                                <td><?= $currentStats[$key]['sonst_in'] ?></td>
                                <td><?= $currentStats[$key]['sonst_out'] ?></td>
                                <td><?= fmt_duration($currentStats[$key]['sonst_in_time']) ?></td>
                                <td><?= fmt_duration($currentStats[$key]['sonst_out_time']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="GET" class="mb-4 no-print">
            <input type="hidden" name="user" value="<?= htmlspecialchars($selectedUser) ?>">
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="week_offset" class="col-form-label">Kalenderwoche:</label>
                </div>
                <div class="col-auto">
                    <select name="week_offset" id="week_offset" class="form-select" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <?php
                            $dt = new DateTime();
                            $dt->modify("-{$i} week");
                            ?>
                            <option value="<?= $i ?>" <?= $i === $weekOffset ? 'selected' : '' ?>>
                                KW <?= $dt->format('W') ?> (<?= $dt->format('o') ?>)
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </form>

        <?php if (!empty($weeklyStats)): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="card-title">Anrufstatistik ab KW <?= $selectedWeek ?>, <?= $selectedYear ?> für <strong><?= htmlspecialchars($selectedUser) ?></strong></h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mt-3">
                        <thead class="table-light">
                        <tr>
                            <th>Kalenderwoche</th>
                            <th>Kunden Gesamt</th>
                            <th>Kandidaten Gesamt</th>
                            <th>Sonstiges Gesamt</th>
                            <th>Total Eingehend</th>
                            <th>Total Ausgehend</th>
                            <th>Total Eingehend (Zeit)</th>
                            <th>Total Ausgehend (Zeit)</th>
                            <th>Kunden Eingehend</th>
                            <th>Kunden Ausgehend</th>
                            <th>Kunden Eingehend (Zeit)</th>
                            <th>Kunden Ausgehend (Zeit)</th>
                            <th>Kandidaten Eingehend</th>
                            <th>Kandidaten Ausgehend</th>
                            <th>Kandidaten Eingehend (Zeit)</th>
                            <th>Kandidaten Ausgehend (Zeit)</th>
                            <th>Sonstiges Eingehend</th>
                            <th>Sonstiges Ausgehend</th>
                            <th>Sonstiges Eingehend (Zeit)</th>
                            <th>Sonstiges Ausgehend (Zeit)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($weeklyStats as $row): ?>
                            <tr>
                                <td>KW <?= $row['week'] ?> (<?= $row['year'] ?>)</td>
                                <td><?= $row['stats']['kunden_total'] ?></td>
                                <td><?= $row['stats']['kandidaten_total'] ?></td>
                                <td><?= $row['stats']['sonst_total'] ?></td>
                                <td><?= $row['stats']['total_in_count'] ?></td>
                                <td><?= $row['stats']['total_out_count'] ?></td>
                                <td><?= fmt_duration($row['stats']['total_in_time']) ?></td>
                                <td><?= fmt_duration($row['stats']['total_out_time']) ?></td>
                                <td><?= $row['stats']['kunden_in'] ?></td>
                                <td><?= $row['stats']['kunden_out'] ?></td>
                                <td><?= fmt_duration($row['stats']['kunden_in_time']) ?></td>
                                <td><?= fmt_duration($row['stats']['kunden_out_time']) ?></td>
                                <td><?= $row['stats']['kandidaten_in'] ?></td>
                                <td><?= $row['stats']['kandidaten_out'] ?></td>
                                <td><?= fmt_duration($row['stats']['kandidaten_in_time']) ?></td>
                                <td><?= fmt_duration($row['stats']['kandidaten_out_time']) ?></td>
                                <td><?= $row['stats']['sonst_in'] ?></td>
                                <td><?= $row['stats']['sonst_out'] ?></td>
                                <td><?= fmt_duration($row['stats']['sonst_in_time']) ?></td>
                                <td><?= fmt_duration($row['stats']['sonst_out_time']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

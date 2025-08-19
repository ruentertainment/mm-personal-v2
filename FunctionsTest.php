<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/CallReportDashboard.php';


// fmt_duration tests
assert(fmt_duration(0) === '00:00:00');
assert(fmt_duration(65) === '00:01:05');
assert(fmt_duration(90061) === '25:01:01');


// stats aggregation tests
$stats = CallReportDashboard::emptyStats();
CallReportDashboard::updateStats($stats, ['from_dn' => '100', 'duration' => 30, 'time_start' => '2024-01-01 10:00:00', 'time_end' => '2024-01-01 10:00:30'], '100', 'kunden');
CallReportDashboard::updateStats($stats, ['from_dn' => '200', 'duration' => 0, 'time_start' => '2024-01-01 10:01:00', 'time_end' => '2024-01-01 10:03:00'], '100', 'kandidaten');
assert($stats['total_out_count'] === 1);
assert($stats['total_in_count'] === 1);
assert($stats['kunden_out_time'] === 30);
assert($stats['kandidaten_in_time'] === 120);
assert($stats['total_out_time'] === 30);
assert($stats['total_in_time'] === 120);


echo "All tests passed\n";
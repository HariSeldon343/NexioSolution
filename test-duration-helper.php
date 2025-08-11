<?php
/**
 * Test CalendarHelper duration formatting
 */

require_once 'backend/utils/CalendarHelper.php';

echo "<h2>Test Duration Formatting</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'><th>Minutes</th><th>Expected</th><th>Actual Result</th><th>Status</th></tr>";

$testCases = [
    [30, '30 min'],
    [60, '1 ora'],
    [90, '1 ora 30 min'],
    [120, '2 ore'],
    [180, '3 ore'],
    [240, '4 ore'],
    [360, '6 ore'],
    [480, '8 ore'],
    [1440, '1 giorno'],
    [1500, '1 giorno 1 ora'],
    [2880, '2 giorni'],
    [4320, '3 giorni'],
    [5760, '4 giorni'],
    [7200, '5 giorni'],
    [10080, '1 settimana'],
    [10800, '1 settimana 12 ore'],
    [20160, '2 settimane'],
];

foreach ($testCases as $test) {
    $minutes = $test[0];
    $expected = $test[1];
    $actual = CalendarHelper::formatDuration($minutes);
    $status = ($actual === $expected) ? '✓' : '✗';
    $statusColor = ($actual === $expected) ? 'green' : 'red';
    
    echo "<tr>";
    echo "<td>$minutes</td>";
    echo "<td>$expected</td>";
    echo "<td>$actual</td>";
    echo "<td style='color: $statusColor; font-weight: bold; text-align: center;'>$status</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Test Task Duration Formatting</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'><th>Days</th><th>Expected</th><th>Actual Result</th><th>Status</th></tr>";

$taskTestCases = [
    [1, '1 giorno'],
    [2, '2 giorni'],
    [1.5, '1,5 giorni'],
    [3.5, '3,5 giorni'],
    [10, '10 giorni'],
    [0.5, '0,5 giorni'],
];

foreach ($taskTestCases as $test) {
    $days = $test[0];
    $expected = $test[1];
    $actual = CalendarHelper::formatTaskDuration($days);
    $status = ($actual === $expected) ? '✓' : '✗';
    $statusColor = ($actual === $expected) ? 'green' : 'red';
    
    echo "<tr>";
    echo "<td>$days</td>";
    echo "<td>$expected</td>";
    echo "<td>$actual</td>";
    echo "<td style='color: $statusColor; font-weight: bold; text-align: center;'>$status</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Test Time Range Formatting</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'><th>Start</th><th>End</th><th>Include Date</th><th>Result</th></tr>";

$timeRangeTests = [
    ['2025-08-10 09:00:00', '2025-08-10 10:00:00', false],
    ['2025-08-10 09:00:00', '2025-08-10 17:00:00', false],
    ['2025-08-10 09:00:00', '2025-08-11 10:00:00', false],
    ['2025-08-10 09:00:00', '2025-08-10 10:00:00', true],
    ['2025-08-10 09:00:00', '2025-08-11 17:00:00', true],
];

foreach ($timeRangeTests as $test) {
    $start = $test[0];
    $end = $test[1];
    $includeDate = $test[2];
    $result = CalendarHelper::formatTimeRange($start, $end, $includeDate);
    
    echo "<tr>";
    echo "<td>$start</td>";
    echo "<td>$end</td>";
    echo "<td>" . ($includeDate ? 'Yes' : 'No') . "</td>";
    echo "<td>$result</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Test Event Type Classes and Labels</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'><th>Type</th><th>CSS Class</th><th>Label</th></tr>";

$eventTypes = ['meeting', 'riunione', 'presentation', 'training', 'workshop', 'conference', 'task', 'altro', 'custom'];

foreach ($eventTypes as $type) {
    $cssClass = CalendarHelper::getEventTypeClass($type);
    $label = CalendarHelper::getEventTypeLabel($type);
    
    echo "<tr>";
    echo "<td>$type</td>";
    echo "<td><span style='padding: 3px 8px; border-radius: 4px; background: #e0e0e0;'>$cssClass</span></td>";
    echo "<td>$label</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><a href='calendario-eventi.php'>Back to Calendar</a></p>";
?>
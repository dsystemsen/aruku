<?php
require_once __DIR__ . '/../inc/member.php';

$prefix = '../';
$me = member_require_login($prefix);

$st = aruku_db()->prepare('SELECT log_date, activity, minutes, weight, kcal FROM activity_logs WHERE member_id = ? ORDER BY log_date ASC, id ASC');
$st->execute([(int) $me['id']]);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="aruku_records.csv"');
header('Cache-Control: no-store');

echo "\xEF\xBB\xBF"; // UTF-8 BOM（Excel対策）
echo "日付,種目,時間(分),体重(kg),消費kcal\n";

$acts = aruku_activities();
foreach ($st as $r) {
    $label = $acts[$r['activity']]['label'] ?? $r['activity'];
    $label = '"' . str_replace('"', '""', $label) . '"';
    echo implode(',', [
        $r['log_date'],
        $label,
        (int) $r['minutes'],
        rtrim(rtrim(number_format((float) $r['weight'], 1, '.', ''), '0'), '.'),
        (int) round((float) $r['kcal']),
    ]) . "\n";
}
exit;

<?php
/**
 * ページビュー（PV）の軽量計測（自前・Cookie不要・外部送信なし）。
 * フロントの app.js が各ページ読み込み時に /track.php へビーコン送信する。
 * 1PV1行を page_views に記録し、集計は COUNT / GROUP BY で行う。
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** 1PVを記録（path はサイト内の相対パス）。 */
function pv_log(string $path): bool
{
    $path = preg_replace('/[^\x21-\x7e]/', '', trim($path)); // 印字可能ASCIIのみ
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim((string) $path, '/');
    }
    // クエリは集計を太らせるので除去（パスのみで集計）
    $qpos = strpos($path, '?');
    if ($qpos !== false) {
        $path = substr($path, 0, $qpos);
    }
    $path = substr($path, 0, 190);
    try {
        aruku_db()->prepare('INSERT INTO page_views (path) VALUES (?)')->execute([$path]);
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/** 今日・今月・累計のPVと、今月の人気ページ上位を返す。 */
function pv_stats(int $topLimit = 20): array
{
    $pdo = aruku_db();
    $topLimit = max(1, min(100, $topLimit));
    $todayStart = date('Y-m-d') . ' 00:00:00';
    $monthStart = date('Y-m-01') . ' 00:00:00';

    $count = static function (string $sql, array $args) use ($pdo): int {
        $st = $pdo->prepare($sql);
        $st->execute($args);
        return (int) $st->fetchColumn();
    };

    $total = (int) $pdo->query('SELECT COUNT(*) FROM page_views')->fetchColumn();
    $today = $count('SELECT COUNT(*) FROM page_views WHERE created_at >= ?', [$todayStart]);
    $month = $count('SELECT COUNT(*) FROM page_views WHERE created_at >= ?', [$monthStart]);

    $st = $pdo->prepare('SELECT path, COUNT(*) AS c FROM page_views WHERE created_at >= ? GROUP BY path ORDER BY c DESC LIMIT ' . $topLimit);
    $st->execute([$monthStart]);
    $topPaths = $st->fetchAll();

    return ['total' => $total, 'today' => $today, 'month' => $month, 'topPaths' => $topPaths];
}

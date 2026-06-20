<?php
/**
 * 計測の受信エンドポイント（CTAクリック／ページビュー）。
 * フロントの navigator.sendBeacon('/track.php', ...) から POST される。
 * 返却は 204（本文なし）。許可キー以外のCTAは inc/cta.php 側で無視される。
 */
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit;
}

// $_POST が空（sendBeacon が text/plain 等で送った）場合のフォールバック
$in = $_POST;
if (!$in) {
    parse_str((string) file_get_contents('php://input'), $in);
}

$pv  = (string) ($in['pv'] ?? '');
$cta = (string) ($in['cta'] ?? '');

if ($pv !== '') {
    require __DIR__ . '/inc/pv.php';
    pv_log($pv);
} elseif ($cta !== '') {
    require __DIR__ . '/inc/cta.php';
    cta_log($cta, (string) ($in['page'] ?? ''));
}

http_response_code(204);

<?php
/**
 * CTAクリック計測の受信エンドポイント。
 * フロントの navigator.sendBeacon('/track.php', ...) から POST される。
 * 返却は 204（本文なし）。許可キー以外は inc/cta.php 側で無視される。
 */
declare(strict_types=1);

require __DIR__ . '/inc/cta.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit;
}

$key  = (string)($_POST['cta'] ?? '');
$page = (string)($_POST['page'] ?? '');

// sendBeacon が text/plain 等で送ってきて $_POST が空の場合のフォールバック
if ($key === '') {
    parse_str((string)file_get_contents('php://input'), $in);
    $key  = (string)($in['cta'] ?? '');
    $page = (string)($in['page'] ?? $page);
}

cta_log($key, $page);

http_response_code(204);

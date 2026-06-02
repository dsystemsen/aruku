<?php
/**
 * コラムエディタ：本文への画像インライン挿入用 非同期アップロード。
 * 認可: 会員ログイン必須＋CSRF（X-CSRF ヘッダ or フォーム値）。
 * 入力: multipart/form-data, file=<画像1枚>
 * 出力: JSON { ok, url, markdown } / { ok:false, error }
 */
require_once __DIR__ . '/../inc/member.php';
require_once __DIR__ . '/../inc/posts.php';

header('Content-Type: application/json; charset=utf-8');

$fail = static function (string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $fail('不正なリクエストです。', 405);
}

$me = member_current();
if (!$me) {
    $fail('ログインが必要です。', 401);
}

$token = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? null);
if (!member_csrf_check($token)) {
    $fail('セッションの有効期限が切れました。ページを再読み込みしてください。', 419);
}

if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    $fail('画像が見つかりません。');
}

$res = post_save_image($_FILES['file']);
if (!$res['ok'] || empty($res['name'])) {
    $fail($res['error'] ?? '画像の保存に失敗しました。');
}

$url = 'uploads/' . $res['name'];           // 本文（相対）用
echo json_encode([
    'ok'       => true,
    'url'      => $url,
    'markdown' => '![](' . $url . ')',
], JSON_UNESCAPED_UNICODE);

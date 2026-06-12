<?php
/**
 * 誰でも掲示板の投稿受け口（POST専用）。
 * 投稿後はトップの掲示板セクション（/index.html#board）へ戻す。
 */
require_once __DIR__ . '/inc/member.php'; // セッション・CSRF を流用
require_once __DIR__ . '/inc/board.php';

member_session_start();

$back = '/index.html#board';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $back);
    exit;
}

// CSRF
if (!member_csrf_check($_POST['csrf'] ?? null)) {
    $_SESSION['board_flash'] = ['ok' => false, 'msg' => 'セッションの有効期限が切れました。もう一度お試しください。'];
    header('Location: ' . $back);
    exit;
}

// ハニーポット（人間は空のはず）
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    // ボットとみなして黙って成功扱い（攻撃者にヒントを与えない）
    $_SESSION['board_flash'] = ['ok' => true, 'msg' => '投稿しました。'];
    header('Location: ' . $back);
    exit;
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$r  = board_create((string) ($_POST['nickname'] ?? ''), (string) ($_POST['body'] ?? ''), $ip);

$_SESSION['board_flash'] = $r['ok']
    ? ['ok' => true, 'msg' => '投稿しました。ありがとうございます！']
    : ['ok' => false, 'msg' => $r['error'] ?? '投稿できませんでした。'];

header('Location: ' . $back);
exit;

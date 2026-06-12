<?php
/**
 * つぶやき掲示板 /board.html
 *  - GET : 掲示板ページを表示（render_board）
 *  - POST: 投稿を受け付け、/board.html へ戻す
 * .htaccess で ^board\.html$ → board.php にルーティング。
 */
require __DIR__ . '/render.php';
require_once __DIR__ . '/inc/member.php'; // セッション・CSRF を流用
require_once __DIR__ . '/inc/board.php';

member_session_start();

$back = '/board.html';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // CSRF
    if (!member_csrf_check($_POST['csrf'] ?? null)) {
        $_SESSION['board_flash'] = ['ok' => false, 'msg' => 'セッションの有効期限が切れました。もう一度お試しください。'];
        header('Location: ' . $back);
        exit;
    }
    // ハニーポット（人間は空のはず）：ボットは黙って成功扱いにしてヒントを与えない
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
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
}

header('Content-Type: text/html; charset=UTF-8');
echo render_board();

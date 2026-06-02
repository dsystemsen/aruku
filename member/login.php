<?php
require_once __DIR__ . '/../render.php';
require_once __DIR__ . '/../inc/member.php';

$prefix = '../';
member_session_start();
if (member_current()) {
    header('Location: mypage.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!member_csrf_check($_POST['csrf'] ?? null)) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (aruku_honeypot_filled()) {
        $error = '送信を確認できませんでした。';
    } elseif (!aruku_ratelimit('login:' . member_client_ip(), 10, 900)) {
        $error = 'ログインの試行が多すぎます。しばらく時間をおいてからお試しください。';
    } else {
        $r = member_login($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($r['ok']) {
            header('Location: mypage.php');
            exit;
        }
        $error = $r['error'];
    }
}

$token = member_csrf_token();
$hp    = aruku_honeypot_field();
$email = h($_POST['email'] ?? '');
$err   = $error ? '<p class="auth-error">' . h($error) . '</p>' : '';

$body = <<<HTML
<div class="auth-card">
  <h1 class="auth-title">ログイン</h1>
  <p class="auth-sub">記録の続きを始めましょう。</p>
  $err
  <form method="post" class="auth-form" autocomplete="on">
    <input type="hidden" name="csrf" value="$token">
    $hp
    <label class="field"><span>メールアドレス</span><input type="email" name="email" value="$email" autocomplete="email" required></label>
    <label class="field"><span>パスワード</span><input type="password" name="password" autocomplete="current-password" required></label>
    <button type="submit" class="lp-btn lp-btn-primary auth-btn">ログイン</button>
  </form>
  <p class="auth-alt">アカウントをお持ちでない方は <a href="register.php">会員登録</a></p>
</div>
HTML;

member_render_page($prefix, 'ログイン', $body);

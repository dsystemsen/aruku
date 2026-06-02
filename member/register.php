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
    } elseif (!aruku_ratelimit('reg:' . member_client_ip(), 5, 3600)) {
        $error = '登録の試行が多すぎます。しばらく時間をおいてからお試しください。';
    } else {
        $r = member_register(
            $_POST['email'] ?? '',
            $_POST['password'] ?? '',
            $_POST['nickname'] ?? '',
            $_POST['sex'] ?? 'male'
        );
        if ($r['ok']) {
            header('Location: mypage.php');
            exit;
        }
        $error = $r['error'];
    }
}

$token = member_csrf_token();
$hp    = aruku_honeypot_field();
$nick  = h($_POST['nickname'] ?? '');
$email = h($_POST['email'] ?? '');
$err   = $error ? '<p class="auth-error">' . h($error) . '</p>' : '';

$body = <<<HTML
<div class="auth-card">
  <h1 class="auth-title">会員登録</h1>
  <p class="auth-sub">登録すると、毎日の体重・運動を記録して消費カロリーを自動で積み上げられます。</p>
  $err
  <form method="post" class="auth-form" autocomplete="on">
    <input type="hidden" name="csrf" value="$token">
    $hp
    <label class="field"><span>ニックネーム</span><input type="text" name="nickname" value="$nick" maxlength="30" required></label>
    <label class="field"><span>メールアドレス</span><input type="email" name="email" value="$email" autocomplete="email" required></label>
    <label class="field"><span>パスワード（8文字以上）</span><input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
    <label class="field"><span>性別（消費カロリー計算に使用）</span>
      <select name="sex"><option value="male">男性</option><option value="female">女性</option></select>
    </label>
    <button type="submit" class="lp-btn lp-btn-primary auth-btn">登録する</button>
  </form>
  <p class="auth-alt">すでに登録済みの方は <a href="login.php">ログイン</a></p>
</div>
HTML;

member_render_page($prefix, '会員登録', $body);

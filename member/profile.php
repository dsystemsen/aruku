<?php
require_once __DIR__ . '/../render.php';
require_once __DIR__ . '/../inc/member.php';

$prefix = '../';
$me = member_require_login($prefix);

$msg = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!member_csrf_check($_POST['csrf'] ?? null)) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } else {
        $do = (string) ($_POST['do'] ?? '');
        if ($do === 'profile') {
            $r = member_update_profile((int) $me['id'], $_POST['nickname'] ?? '', $_POST['sex'] ?? 'male');
            $r['ok'] ? ($msg = 'プロフィールを更新しました。') : ($error = $r['error']);
        } elseif ($do === 'password') {
            $r = member_change_password((int) $me['id'], $_POST['current'] ?? '', $_POST['new'] ?? '');
            $r['ok'] ? ($msg = 'パスワードを変更しました。') : ($error = $r['error']);
        }
        // 表示を最新化（キャッシュを無視して再取得）
        $me = member_current(true);
    }
}

$token = member_csrf_token();
$nick = h($me['nickname']);
$email = h($me['email']);
$maleSel = $me['sex'] !== 'female' ? ' selected' : '';
$femaleSel = $me['sex'] === 'female' ? ' selected' : '';
$msgHtml = $msg ? '<p class="auth-ok">' . h($msg) . '</p>' : '';
$errHtml = $error ? '<p class="auth-error">' . h($error) . '</p>' : '';

$body = <<<HTML
<div class="member-head">
  <h1>プロフィール編集</h1>
  <p>{$email}　<a href="mypage.php" class="member-logout">マイページへ戻る</a></p>
</div>
$msgHtml
$errHtml
<div class="calc-tool post-editor">
  <h2 class="record-title">基本情報</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="$token">
    <input type="hidden" name="do" value="profile">
    <label class="field"><span>ニックネーム</span><input type="text" name="nickname" value="$nick" maxlength="30" required></label>
    <label class="field"><span>性別（消費カロリー計算に使用）</span>
      <select name="sex"><option value="male"$maleSel>男性</option><option value="female"$femaleSel>女性</option></select>
    </label>
    <button type="submit" class="lp-btn lp-btn-primary">プロフィールを保存</button>
  </form>
</div>
<div class="calc-tool post-editor" style="margin-top:24px;">
  <h2 class="record-title">パスワード変更</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="$token">
    <input type="hidden" name="do" value="password">
    <label class="field"><span>現在のパスワード</span><input type="password" name="current" autocomplete="current-password" required></label>
    <label class="field"><span>新しいパスワード（8文字以上）</span><input type="password" name="new" minlength="8" autocomplete="new-password" required></label>
    <button type="submit" class="lp-btn lp-btn-primary">パスワードを変更</button>
  </form>
</div>
HTML;

member_render_page($prefix, 'プロフィール編集', $body);

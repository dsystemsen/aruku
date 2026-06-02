<?php
// 解約（退会）ページ — 会員ログイン必須。パスワード確認のうえアカウントと関連データを削除。
require_once __DIR__ . '/../render.php';
require_once __DIR__ . '/../inc/member.php';
require_once __DIR__ . '/../inc/posts.php';

$prefix = '../';
$me = member_require_login($prefix);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!member_csrf_check($_POST['csrf'] ?? null)) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } else {
        $pw = (string) ($_POST['password'] ?? '');
        $st = aruku_db()->prepare('SELECT password_hash FROM members WHERE id = ?');
        $st->execute([(int) $me['id']]);
        $row = $st->fetch();
        if (!$row || !password_verify($pw, $row['password_hash'])) {
            $error = 'パスワードが正しくありません。';
        } else {
            member_delete((int) $me['id']);
            member_logout();
            header('Location: ../index.html');
            exit;
        }
    }
}

$token = member_csrf_token();
$nick = h($me['nickname']);
$errHtml = $error ? '<p class="auth-error">' . h($error) . '</p>' : '';

$body = <<<HTML
<div class="member-head">
  <h1>解約（退会）</h1>
  <p>退会すると元に戻せません。<a href="mypage.php" class="member-logout">マイページへ戻る</a></p>
</div>
<div class="calc-tool">
  <p class="cancel-note">次のデータが<strong>すべて削除</strong>されます。削除後は復元できません。</p>
  <ul class="cancel-list">
    <li>会員アカウント（{$nick}）</li>
    <li>消費カロリーの記録・週間目標</li>
    <li>投稿したコラム・コメント・いいね・保存（ブックマーク）</li>
  </ul>
  {$errHtml}
  <form method="post">
    <input type="hidden" name="csrf" value="{$token}">
    <label class="field"><span>確認のため、現在のパスワードを入力してください</span><input type="password" name="password" autocomplete="current-password" required></label>
    <label class="cancel-confirm"><input type="checkbox" required> 上記に同意し、退会します（元に戻せません）</label>
    <div class="editor-actions">
      <button type="submit" class="lp-btn cancel-btn" onclick="return confirm('本当に退会しますか？この操作は取り消せません。');">退会する</button>
      <a href="mypage.php" class="lp-btn lp-btn-secondary">やめる</a>
    </div>
  </form>
</div>
HTML;

member_render_page($prefix, '解約（退会）', $body, ['robots' => 'noindex, nofollow']);

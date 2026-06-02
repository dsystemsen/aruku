<?php
// 運営用：保留中（自動保留）の投稿の確認・公開・削除 ＋ ユーザー一覧。管理者メールのみ。
require_once __DIR__ . '/../render.php';
require_once __DIR__ . '/../inc/member.php';
require_once __DIR__ . '/../inc/posts.php';

$prefix = '../';
$me = member_require_login($prefix);
if (!member_is_admin($me)) {
    header('Location: mypage.php');
    exit;
}

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (member_csrf_check($_POST['csrf'] ?? null)) {
        $pid = (int) ($_POST['pid'] ?? 0);
        $do  = (string) ($_POST['do'] ?? '');
        if ($pid > 0 && $do === 'publish') {
            post_set_status($pid, 'published');
            $notice = '投稿を公開しました。';
        } elseif ($pid > 0 && $do === 'delpost') {
            post_delete_admin($pid);
            $notice = '投稿を削除しました。';
        }
    }
}
$token = member_csrf_token();
$noticeHtml = $notice ? '<p class="auth-ok">' . h($notice) . '</p>' : '';

// 保留中（自動保留）の投稿
$pending = posts_by_status('pending');
$pendCount = count($pending);
$pendRows = '';
foreach ($pending as $p) {
    $pid = (int) $p['id'];
    $pendRows .= '<tr>'
        . '<td>' . h(substr((string) $p['created_at'], 0, 10)) . '</td>'
        . '<td><div class="hold-title">' . h($p['title']) . '</div><div class="hold-excerpt">' . h(post_excerpt($p['body'], 120)) . '</div></td>'
        . '<td>' . h($p['nickname']) . '</td>'
        . '<td class="hold-act">'
        . '<form method="post"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="pid" value="' . $pid . '"><input type="hidden" name="do" value="publish"><button type="submit" class="lp-btn lp-btn-primary hold-btn">公開</button></form>'
        . '<form method="post" onsubmit="return confirm(\'この投稿を削除しますか？元に戻せません。\');"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="pid" value="' . $pid . '"><input type="hidden" name="do" value="delpost"><button type="submit" class="lp-btn cancel-btn hold-btn">削除</button></form>'
        . '</td></tr>';
}
$pendTable = $pendRows !== ''
    ? '<div class="user-table-wrap"><table class="user-table"><thead><tr><th>日付</th><th>内容</th><th>投稿者</th><th>操作</th></tr></thead><tbody>' . $pendRows . '</tbody></table></div>'
    : '<p class="log-empty" style="text-align:left;padding:14px 0;">保留中の投稿はありません。</p>';

// ユーザー一覧（ID昇順）
$members = members_list(500);
usort($members, static fn($a, $b) => (int) $a['id'] <=> (int) $b['id']);
$count = count($members);
$rows = '';
foreach ($members as $m) {
    $sex = ['male' => '男性', 'female' => '女性'][$m['sex'] ?? ''] ?? h((string) ($m['sex'] ?? '－'));
    $rows .= '<tr>'
        . '<td>' . (int) $m['id'] . '</td>'
        . '<td><a href="' . $prefix . 'u/' . (int) $m['id'] . '">' . h($m['nickname']) . '</a></td>'
        . '<td>' . h($m['email']) . '</td>'
        . '<td>' . $sex . '</td>'
        . '<td>' . h(substr((string) $m['created_at'], 0, 10)) . '</td>'
        . '<td style="text-align:right;">' . (int) $m['posts'] . '</td>'
        . '</tr>';
}

$body = <<<HTML
{$noticeHtml}
<h2 class="record-title record-tool-title">保留中の投稿（自動保留・要確認）<small class="md-hint">{$pendCount}件</small></h2>
<p class="cancel-note">不適切な表現の可能性で自動保留された投稿です。内容を確認し、問題なければ「公開」、不適切なら「削除」してください。</p>
{$pendTable}

<h2 class="record-title record-tool-title" style="margin-top:48px;">ユーザー一覧<small class="md-hint">{$count}名</small></h2>
<div class="user-table-wrap">
  <table class="user-table">
    <thead><tr><th>ID</th><th>ニックネーム</th><th>メール</th><th>性別</th><th>登録日</th><th>投稿数</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
HTML;

member_render_page($prefix, '運営用', $body, ['robots' => 'noindex, nofollow']);

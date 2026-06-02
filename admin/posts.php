<?php
/**
 * aruku 管理画面 — 会員投稿の承認（モデレーション）
 * pending を確認して 公開(published) / 却下(rejected) / 削除。
 */
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/../inc/posts.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_verify()) {
        $id = (int) ($_POST['id'] ?? 0);
        $do = (string) ($_POST['do'] ?? '');
        if ($id > 0) {
            if ($do === 'approve') {
                post_set_status($id, 'published');
                flash_set('ok', '投稿を公開しました。');
            } elseif ($do === 'reject') {
                post_set_status($id, 'rejected');
                flash_set('ok', '投稿を却下しました。');
            } elseif ($do === 'unpublish') {
                post_set_status($id, 'pending');
                flash_set('ok', '投稿を非公開（承認待ち）に戻しました。');
            } elseif ($do === 'delete') {
                post_delete($id);
                flash_set('ok', '投稿を削除しました。');
            } elseif ($do === 'delcomment') {
                comment_delete_admin($id);
                flash_set('ok', 'コメントを削除しました。');
            } elseif ($do === 'handlereport') {
                report_handle($id);
                flash_set('ok', '通報を対応済みにしました。');
            }
        }
    }
    header('Location: posts.php');
    exit;
}

$pending   = posts_by_status('pending');
$published = posts_by_status('published');
$comments  = comments_recent(80);
$reports   = reports_open(80);
$base      = admin_base();

$reportsHtml = '';
foreach ($reports as $r) {
    if ($r['target_type'] === 'post') {
        $link = '../posts/' . (int) $r['target_id'];
        $tlabel = '投稿 #' . (int) $r['target_id'];
    } else {
        $c = comment_get((int) $r['target_id']);
        $link = $c ? '../posts/' . (int) $c['post_id'] . '#comments' : '#';
        $tlabel = 'コメント #' . (int) $r['target_id'];
    }
    $reason = (($r['reason'] ?? '') !== '') ? h($r['reason']) : '（なし）';
    $reportsHtml .= '<div class="post-mod-card"><div class="post-mod-head"><strong>' . h($tlabel) . '</strong>'
        . '<span class="post-mod-meta">通報者: ' . h($r['reporter']) . ' ／ ' . h(substr((string) $r['created_at'], 0, 16)) . '</span></div>'
        . '<p class="post-mod-body">理由: ' . $reason . ' ／ <a href="' . $link . '" target="_blank" rel="noopener">対象を開く</a></p>'
        . '<form method="post" class="post-mod-actions">' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $r['id'] . '">'
        . '<button name="do" value="handlereport" class="ab ab-ok">対応済みにする</button></form></div>';
}
if ($reportsHtml === '') {
    $reportsHtml = '<p class="post-mod-empty">未対応の通報はありません。</p>';
}

function admin_post_card(array $p, string $kind): string
{
    $date = h(substr((string) ($p['published_at'] ?: $p['created_at']), 0, 16));
    $excerpt = h(post_excerpt($p['body'], 140));
    $btns = '';
    if ($kind === 'pending') {
        $btns = '<button name="do" value="approve" class="ab ab-ok">公開する</button>'
            . '<button name="do" value="reject" class="ab ab-warn">却下</button>';
    } else {
        $btns = '<button name="do" value="unpublish" class="ab ab-warn">非公開に戻す</button>';
    }
    return '<div class="post-mod-card">'
        . '<div class="post-mod-head"><strong>' . h($p['title']) . '</strong>'
        . '<span class="post-mod-meta">' . h($p['nickname']) . '（' . h($p['email']) . '）／ ' . $date . '</span></div>'
        . '<p class="post-mod-body">' . $excerpt . '</p>'
        . '<form method="post" class="post-mod-actions">'
        . csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $p['id'] . '">'
        . $btns
        . '<button name="do" value="delete" class="ab ab-del" onclick="return confirm(\'削除しますか？\')">削除</button>'
        . '</form></div>';
}

$pendingHtml = $pending ? implode('', array_map(fn($p) => admin_post_card($p, 'pending'), $pending))
    : '<p class="post-mod-empty">承認待ちの投稿はありません。</p>';
$publishedHtml = $published ? implode('', array_map(fn($p) => admin_post_card($p, 'published'), $published))
    : '<p class="post-mod-empty">公開中の会員投稿はありません。</p>';

$commentsHtml = '';
foreach ($comments as $c) {
    $commentsHtml .= '<div class="cmod">'
        . '<div class="cmod-head"><strong>' . h($c['nickname']) . '</strong>'
        . '<span class="cmod-meta">' . h($c['email']) . ' ／ ' . h(substr((string) $c['created_at'], 0, 16))
        . ' ／ 投稿: <a href="../posts/' . (int) $c['post_id'] . '" target="_blank" rel="noopener">' . h($c['post_title']) . '</a></span></div>'
        . '<p class="cmod-body">' . nl2br(h($c['body'])) . '</p>'
        . '<form method="post" class="cmod-actions">' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $c['id'] . '">'
        . '<button name="do" value="delcomment" class="ab ab-del" onclick="return confirm(\'コメントを削除しますか？\')">削除</button>'
        . '</form></div>';
}
if ($commentsHtml === '') {
    $commentsHtml = '<p class="post-mod-empty">コメントはまだありません。</p>';
}

$flash = '';
foreach (flash_take() as $f) {
    $flash .= '<div class="flash flash-' . h($f['type']) . '">' . h($f['msg']) . '</div>';
}

?><!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>会員投稿の承認｜aruku 管理</title>
<link rel="stylesheet" href="admin.css">
<style>
.post-mod-card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px 20px;margin-bottom:14px;}
.post-mod-head{display:flex;flex-wrap:wrap;gap:6px 14px;align-items:baseline;margin-bottom:8px;}
.post-mod-meta{font-size:.82rem;color:var(--muted);}
.post-mod-body{font-size:.9rem;color:var(--ink-2);margin:0 0 14px;line-height:1.7;}
.post-mod-actions{display:flex;gap:10px;flex-wrap:wrap;}
.ab{border:0;border-radius:999px;padding:8px 18px;font-weight:700;font-size:.86rem;cursor:pointer;color:#fff;}
.ab-ok{background:var(--forest);}.ab-warn{background:#c98a1e;}.ab-del{background:var(--danger);}
.post-mod-empty{color:var(--muted);padding:14px 0;}
.mod-section-title{margin:26px 0 12px;font-size:1.1rem;}
.cmod{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:10px;}
.cmod-head{display:flex;flex-wrap:wrap;gap:4px 12px;align-items:baseline;margin-bottom:6px;}
.cmod-meta{font-size:.8rem;color:var(--muted);}
.cmod-body{font-size:.9rem;color:var(--ink-2);margin:0 0 10px;line-height:1.7;}
.cmod-actions{display:inline;}
</style>
</head>
<body>
<div class="topbar"><div class="topbar-in">
  <a href="<?= h($base) ?>" class="brand">aruku <span>管理</span></a>
  <nav class="topnav">
    <a href="<?= h($base) ?>">ダッシュボード</a>
    <a href="posts.php" class="active">会員投稿の承認</a>
    <a href="members.php">会員管理</a>
  </nav>
  <div class="topbar-right"><a href="<?= h($base) ?>?p=logout">ログアウト</a></div>
</div></div>
<main class="wrap" style="max-width:900px;margin:0 auto;padding:28px 20px 80px;">
  <h1>会員投稿の承認</h1>
  <?= $flash ?>
  <h2 class="mod-section-title">通報（<?= count($reports) ?>）</h2>
  <?= $reportsHtml ?>
  <h2 class="mod-section-title">承認待ち（<?= count($pending) ?>）</h2>
  <?= $pendingHtml ?>
  <h2 class="mod-section-title">公開中（<?= count($published) ?>）</h2>
  <?= $publishedHtml ?>
  <h2 class="mod-section-title">コメント（<?= count($comments) ?>）</h2>
  <?= $commentsHtml ?>
</main>
</body>
</html>

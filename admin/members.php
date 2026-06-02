<?php
/**
 * aruku 管理画面 — 会員管理＋簡易統計
 */
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/../inc/posts.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_verify()) {
        $id = (int) ($_POST['id'] ?? 0);
        if (($_POST['do'] ?? '') === 'delmember' && $id > 0) {
            member_delete($id);
            flash_set('ok', '会員と関連データを削除しました。');
        }
    }
    header('Location: members.php');
    exit;
}

$stats = site_stats();
$members = members_list(300);
$base = admin_base();

$rows = '';
foreach ($members as $m) {
    $rows .= '<tr>'
        . '<td>' . (int) $m['id'] . '</td>'
        . '<td><a href="../u/' . (int) $m['id'] . '" target="_blank" rel="noopener">' . h($m['nickname']) . '</a></td>'
        . '<td>' . h($m['email']) . '</td>'
        . '<td>' . ($m['sex'] === 'female' ? '女性' : '男性') . '</td>'
        . '<td>' . (int) $m['posts'] . '</td>'
        . '<td>' . h(substr((string) $m['created_at'], 0, 10)) . '</td>'
        . '<td><form method="post" onsubmit="return confirm(\'この会員と投稿・コメント等をすべて削除します。よろしいですか？\')">' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $m['id'] . '"><button name="do" value="delmember" class="ab ab-del">削除</button></form></td>'
        . '</tr>';
}
if ($rows === '') {
    $rows = '<tr><td colspan="7" style="color:var(--muted);padding:18px;">会員はまだいません。</td></tr>';
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
<title>会員管理｜aruku 管理</title>
<link rel="stylesheet" href="admin.css">
<style>
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin:18px 0 28px;}
.stat-card{background:var(--card);border:1px solid var(--line);border-top:3px solid var(--forest);border-radius:14px;padding:18px 20px;}
.stat-num{font-size:2rem;font-weight:800;color:var(--forest);}
.stat-label{color:var(--muted);font-size:.85rem;}
.mtable{width:100%;border-collapse:collapse;font-size:.9rem;background:var(--card);}
.mtable th,.mtable td{border:1px solid var(--line);padding:9px 12px;text-align:left;}
.mtable thead th{background:var(--forest-100);}
.ab{border:0;border-radius:999px;padding:6px 14px;font-weight:700;font-size:.82rem;cursor:pointer;color:#fff;}
.ab-del{background:var(--danger);}
</style>
</head>
<body>
<div class="topbar"><div class="topbar-in">
  <a href="<?= h($base) ?>" class="brand">aruku <span>管理</span></a>
  <nav class="topnav">
    <a href="<?= h($base) ?>">ダッシュボード</a>
    <a href="posts.php">会員投稿の承認</a>
    <a href="members.php" class="active">会員管理</a>
  </nav>
  <div class="topbar-right"><a href="<?= h($base) ?>?p=logout">ログアウト</a></div>
</div></div>
<main class="wrap" style="max-width:1000px;margin:0 auto;padding:28px 20px 80px;">
  <h1>会員管理</h1>
  <?= $flash ?>
  <div class="stat-grid">
    <div class="stat-card"><div class="stat-num"><?= (int) $stats['members'] ?></div><div class="stat-label">会員数</div></div>
    <div class="stat-card"><div class="stat-num"><?= (int) $stats['posts'] ?></div><div class="stat-label">公開コラム</div></div>
    <div class="stat-card"><div class="stat-num"><?= (int) $stats['pending'] ?></div><div class="stat-label">承認待ち</div></div>
    <div class="stat-card"><div class="stat-num"><?= (int) $stats['comments'] ?></div><div class="stat-label">コメント</div></div>
  </div>
  <h2 style="font-size:1.1rem;margin:0 0 12px;">会員一覧（<?= count($members) ?>）</h2>
  <div style="overflow-x:auto;">
  <table class="mtable">
    <thead><tr><th>ID</th><th>ニックネーム</th><th>メール</th><th>性別</th><th>投稿</th><th>登録日</th><th>操作</th></tr></thead>
    <tbody><?= $rows ?></tbody>
  </table>
  </div>
</main>
</body>
</html>

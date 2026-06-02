<?php
require_once __DIR__ . '/../render.php';
require_once __DIR__ . '/../inc/member.php';
require_once __DIR__ . '/../inc/posts.php';

$prefix = '../'; // 表示は /u/<id> 経由（ベースは /u/）。アセット・記事は ../ で /へ。
member_session_start();
$me = member_current();
$id = (int) ($_GET['id'] ?? 0);
$mem = $id > 0 ? member_public($id) : null;

if (!$mem) {
    http_response_code(404);
    $body = '<div class="post-article"><h1>ユーザーが見つかりません</h1>'
        . '<p><a href="../" class="lp-btn lp-btn-secondary">トップへ戻る</a></p></div>';
    member_render_page($prefix, 'ユーザーが見つかりません', $body);
    exit;
}

$followers  = follower_count($id);
$followingN = following_count($id);
$posts = posts_by_member_published($id, 50);
$cards = aruku_post_cards($posts, $prefix);
$av = h(post_avatar_char($mem['nickname']));
$nick = h($mem['nickname']);

if ($me && (int) $me['id'] !== $id) {
    $following = is_following((int) $me['id'], $id);
    $token = member_csrf_token();
    $followUi = '<form method="post" action="/member/follow.php" class="follow-form">'
        . '<input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="follow-btn' . ($following ? ' following' : '') . '" type="submit">' . ($following ? 'フォロー中' : '＋ フォロー') . '</button></form>';
} elseif (!$me) {
    $followUi = '<a class="follow-btn" href="/member/login.php">＋ フォロー</a>';
} else {
    $followUi = '<a class="follow-btn ghost" href="/member/profile.php">プロフィール編集</a>';
}

$feed = $cards ? '<div class="note-feed">' . $cards . '</div>' : '<p class="column-cat-desc">まだ投稿がありません。</p>';
$count = count($posts);

$body = <<<HTML
<div class="profile-head">
  <span class="profile-avatar">{$av}</span>
  <div class="profile-info">
    <h1 class="profile-name">{$nick}</h1>
    <div class="profile-stats"><span><b>{$count}</b> 投稿</span><span><b>{$followers}</b> フォロワー</span><span><b>{$followingN}</b> フォロー</span></div>
    <div class="profile-actions">{$followUi}</div>
  </div>
</div>
<h2 class="record-title">{$nick} さんのコラム</h2>
{$feed}
HTML;

$pubUrl = site()['url'] . '/u/' . $id;
$jsonld = [
    [
        '@context'   => 'https://schema.org',
        '@type'      => 'ProfilePage',
        'name'       => $mem['nickname'] . ' さんのプロフィール',
        'url'        => $pubUrl,
        'inLanguage' => 'ja',
        'isPartOf'   => ['@type' => 'WebSite', 'name' => 'あるく', 'url' => site()['url'] . '/'],
        'mainEntity' => [
            '@type'       => 'Person',
            'name'        => $mem['nickname'],
            'url'         => $pubUrl,
            'description' => $mem['nickname'] . 'さんは、歩くことの総合メディア「あるく」のコラム投稿者です。',
            'memberOf'    => ['@type' => 'Organization', 'name' => 'あるく', 'url' => site()['url'] . '/'],
        ],
    ],
    [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'トップ', 'item' => site()['url'] . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $mem['nickname'] . ' さん', 'item' => $pubUrl],
        ],
    ],
];
member_render_page($prefix, $nick . ' さんのプロフィール', $body, [
    'desc'      => $nick . ' さんのプロフィールと投稿コラム（' . $count . '本）。歩くことの総合メディア「あるく」。',
    'robots'    => 'index, follow',
    'ogType'    => 'profile',
    'canonical' => $pubUrl,
    'jsonld'    => $jsonld,
]);

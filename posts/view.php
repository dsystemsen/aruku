<?php
require_once __DIR__ . '/../render.php';
require_once __DIR__ . '/../inc/member.php';
require_once __DIR__ . '/../inc/posts.php';

$prefix = '../';
member_session_start();
$me = member_current();
$id = (int) ($_GET['id'] ?? 0);

// コメント追加・削除（会員のみ・PRG）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    if ($me && member_csrf_check($_POST['csrf'] ?? null)) {
        $do = (string) ($_POST['do'] ?? '');
        if ($do === 'comment' && !aruku_honeypot_filled() && aruku_ratelimit('comment:' . (int) $me['id'], 10, 600)) {
            $cr = comment_add($id, (int) $me['id'], $_POST['comment'] ?? '');
            if (!empty($cr['ok'])) {
                notify_comment($id, (int) $me['id']); // 投稿者へメール通知（送信できる環境のみ）
                notif_create(post_author_id($id), 'comment', (int) $me['id'], $id); // サイト内通知
            }
        } elseif ($do === 'delcomment') {
            comment_delete_owned((int) ($_POST['cid'] ?? 0), (int) $me['id']);
        } elseif ($do === 'report') {
            $rt = (($_POST['rtype'] ?? 'post') === 'comment') ? 'comment' : 'post';
            $rtid = (int) ($_POST['rtid'] ?? 0);
            if ($rtid > 0) {
                report_create($rt, $rtid, (int) $me['id'], (string) ($_POST['reason'] ?? ''));
            }
            header('Location: ' . $prefix . 'posts/' . $id . '?reported=1');
            exit;
        }
    }
    header('Location: ' . $prefix . 'posts/' . $id . '#comments');
    exit;
}

$post = $id > 0 ? post_get_published($id) : null;
if (!$post) {
    http_response_code(404);
    $body = '<div class="post-article"><h1>記事が見つかりません</h1>'
        . '<p>この投稿は存在しないか、まだ公開されていません。</p>'
        . '<p><a href="../" class="lp-btn lp-btn-secondary">トップへ戻る</a></p></div>';
    member_render_page($prefix, '記事が見つかりません', $body);
    exit;
}

$date   = substr((string) ($post['published_at'] ?: $post['created_at']), 0, 10);
$avatar = h(post_avatar_char($post['nickname']));
$nick   = h($post['nickname']);
$title  = h($post['title']);
$bodyHtml = post_body_html($post['body'], $prefix);
$files = post_all_image_files($post);
// 画像の実寸を width/height に出力（CLS対策）
$imgDim = static function (string $file): string {
    $p = dirname(__DIR__) . '/uploads/' . $file;
    $d = @getimagesize($p);
    return ($d && (int) $d[0] > 0) ? ' width="' . (int) $d[0] . '" height="' . (int) $d[1] . '"' : '';
};
$cover = '';
$gallery = '';
if ($files) {
    // カバーはLCP候補のため lazy にせず fetchpriority=high
    $cover = '<div class="post-cover"><img src="' . $prefix . 'uploads/' . h($files[0]) . '" alt="' . $title . '"' . $imgDim($files[0]) . ' fetchpriority="high" decoding="async"></div>';
    $rest = array_slice($files, 1);
    if ($rest) {
        $g = '';
        foreach ($rest as $f) {
            $g .= '<img src="' . $prefix . 'uploads/' . h($f) . '" alt="' . $title . 'の画像"' . $imgDim($f) . ' loading="lazy" decoding="async">';
        }
        $gallery = '<div class="post-gallery">' . $g . '</div>';
    }
}

// 想定Q&A（FAQ）：可視アコーディオン＋FAQPage 構造化データ
$faqArr = [];
$rawFaq = trim((string) ($post['faq'] ?? ''));
if ($rawFaq !== '') {
    $decoded = json_decode($rawFaq, true);
    if (is_array($decoded)) {
        foreach ($decoded as $qa) {
            $q = trim((string) ($qa['q'] ?? ''));
            $a = trim((string) ($qa['a'] ?? ''));
            if ($q !== '' && $a !== '') { $faqArr[] = ['q' => $q, 'a' => $a]; }
        }
    }
}
$faqHtml = '';
if ($faqArr) {
    $items = '';
    foreach ($faqArr as $qa) {
        $items .= '<details class="column-faq-item"><summary>' . h($qa['q']) . '</summary>'
            . '<div class="column-faq-a">' . h($qa['a']) . '</div></details>';
    }
    $faqHtml = '<section class="post-faq"><h2>よくある質問</h2>' . $items . '</section>';
}

$cats   = aruku_post_categories();
$catKey  = (string) ($post['category'] ?? '');
$catName = $cats[$catKey] ?? '';
$catRel  = $catName !== '' ? $prefix . 'category/' . $catKey . '.html' : '';
$catBadge = $catName !== ''
    ? '<a class="post-cat" href="' . h($catRel) . '">' . h($catName) . '</a>'
    : '';

$authorLink = $prefix . 'u/' . (int) $post['member_id'];

// タグはサイト内検索（/search.html?q=タグ）へ
$tagChips = '';
foreach (post_tags($id) as $tg) {
    $tagChips .= '<a class="post-tag" href="' . $prefix . 'search.html?q=' . rawurlencode($tg) . '">#' . h($tg) . '</a>';
}
$tagsHtml = $tagChips ? '<div class="post-tags">' . $tagChips . '</div>' : '';

$token = member_csrf_token();
$hp = aruku_honeypot_field();

$likeCount = like_count($id);
$liked = $me ? member_liked($id, (int) $me['id']) : false;
$likeUi = $me
    ? '<form method="post" action="like.php" class="like-form"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="id" value="' . $id . '"><button class="like-btn' . ($liked ? ' liked' : '') . '" type="submit">♥ <span>' . $likeCount . '</span></button></form>'
    : '<a class="like-btn" href="' . $prefix . 'member/login.php">♥ <span>' . $likeCount . '</span></a>';

$bookmarked = $me ? is_bookmarked((int) $me['id'], $id) : false;
$bmUi = $me
    ? '<form method="post" action="bookmark.php" class="like-form"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="id" value="' . $id . '"><button class="bm-btn' . ($bookmarked ? ' marked' : '') . '" type="submit">🔖 ' . ($bookmarked ? '保存済み' : '保存') . '</button></form>'
    : '';

// SNSシェア・OGP・通報・関連記事
$siteUrl = site()['url'];
$postUrl = $siteUrl . '/posts/' . $id;
$enc = rawurlencode($postUrl);
$encTitle = rawurlencode($post['title']);
$ogImage = $files ? $siteUrl . '/uploads/' . $files[0] : $siteUrl . '/assets/ogp.png';
$shareUi = '<div class="share-row">'
    . '<a class="share-btn x" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=' . $encTitle . '&url=' . $enc . '">𝕏 シェア</a>'
    . '<a class="share-btn line" target="_blank" rel="noopener" href="https://social-plugins.line.me/lineit/share?url=' . $enc . '">LINE</a>'
    . '<a class="share-btn fb" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=' . $enc . '">Facebook</a>'
    . '<button class="share-btn copy" type="button" data-url="' . h($postUrl) . '" onclick="if(navigator.clipboard){navigator.clipboard.writeText(this.dataset.url);this.textContent=\'コピーしました\';}">リンクをコピー</button>'
    . '</div>';
$reportedMsg = isset($_GET['reported']) ? '<p class="auth-ok">通報を受け付けました。ご協力ありがとうございます。</p>' : '';
$reportUi = $me
    ? '<details class="report-box"><summary>⚠ この投稿を通報</summary>'
        . '<form method="post" class="report-form"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="do" value="report"><input type="hidden" name="rtype" value="post"><input type="hidden" name="rtid" value="' . $id . '">'
        . '<input type="text" name="reason" maxlength="200" placeholder="理由（任意）"><button type="submit">通報する</button></form></details>'
    : '';
$related = posts_related($id, $post['category'] ?? null, 4);
$relatedHtml = $related
    ? '<section class="related"><h2 class="comments-title">関連するコラム</h2><div class="note-feed">' . aruku_post_cards($related, $prefix) . '</div></section>'
    : '';

$comments = comments_for($id);
$cCount = count($comments);
$cList = '';
foreach ($comments as $c) {
    $del = ($me && (int) $c['member_id'] === (int) $me['id'])
        ? '<form method="post" class="cmt-del"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="do" value="delcomment"><input type="hidden" name="cid" value="' . (int) $c['id'] . '"><button type="submit">削除</button></form>'
        : '';
    $rep = ($me && (int) $c['member_id'] !== (int) $me['id'])
        ? '<form method="post" class="cmt-del"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="do" value="report"><input type="hidden" name="rtype" value="comment"><input type="hidden" name="rtid" value="' . (int) $c['id'] . '"><button type="submit">通報</button></form>'
        : '';
    $cList .= '<div class="cmt"><div class="cmt-head"><span class="cmt-avatar">' . h(post_avatar_char($c['nickname'])) . '</span>'
        . '<span class="cmt-name">' . h($c['nickname']) . '</span>'
        . '<span class="cmt-date">' . h(substr((string) $c['created_at'], 0, 10)) . '</span>' . $del . $rep
        . '</div><div class="cmt-body">' . post_body_html($c['body']) . '</div></div>';
}
if ($cList === '') {
    $cList = '<p class="cmt-empty">まだコメントはありません。</p>';
}
$cForm = $me
    ? '<form method="post" class="cmt-form"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="do" value="comment">' . $hp . '<textarea name="comment" rows="3" maxlength="2000" placeholder="コメントを書く（会員のみ）" required></textarea><button type="submit" class="lp-btn lp-btn-primary">コメントする</button></form>'
    : '<p class="cmt-login"><a href="' . $prefix . 'member/login.php">ログイン</a>するとコメントできます。</p>';

$crumb = '<nav class="post-breadcrumb"><a href="' . $prefix . '">トップ</a>'
    . ($catName !== '' ? ' ／ <a href="' . h($catRel) . '">' . h($catName) . '</a>' : '')
    . '</nav>';
$modDate = substr((string) ($post['updated_at'] ?? ''), 0, 10);
$reviewed = ($modDate !== '' && $modDate !== $date) ? '<span class="post-updated">最終更新 ' . h(str_replace('-', '/', $modDate)) . '</span>' : '';

$body = <<<HTML
<article class="post-article">
  {$crumb}
  {$reportedMsg}
  $catBadge
  <h1 class="post-title">{$title}</h1>
  <div class="post-byline">
    <a class="post-avatar" href="{$authorLink}">{$avatar}</a>
    <span class="post-byline-text"><a class="post-author" href="{$authorLink}">{$nick}</a><span class="post-date">{$date}</span>{$reviewed}</span>
    <span class="post-badge">会員投稿</span>
  </div>
  {$cover}
  <div class="post-body">{$bodyHtml}</div>
  {$gallery}
  {$faqHtml}
  {$tagsHtml}
  <div class="post-actions">{$likeUi}{$bmUi}</div>
  {$shareUi}
  {$reportUi}
  {$relatedHtml}
  <aside class="post-disclaimer">本記事は一般的な健康情報の提供を目的としたもので、医療上の診断・治療・助言に代わるものではありません。持病や体調に不安のある方は、運動を始める前に医師等の専門家にご相談ください。詳しくは<a href="{$prefix}editorial-policy.html">編集・監修ポリシー</a>をご覧ください。</aside>
  <section class="comments" id="comments">
    <h2 class="comments-title">コメント（{$cCount}）</h2>
    <div class="cmt-list">{$cList}</div>
    {$cForm}
  </section>
</article>
HTML;

$pubIso = date('c', strtotime((string) ($post['published_at'] ?: $post['created_at'])) ?: time());
$modIso = date('c', strtotime((string) ($post['updated_at'] ?: ($post['published_at'] ?: $post['created_at']))) ?: time());
// article:* OGタグ
$artMeta = '<meta property="article:published_time" content="' . $pubIso . '">' . "\n"
    . '<meta property="article:modified_time" content="' . $modIso . '">' . "\n"
    . '<meta property="article:author" content="' . $siteUrl . '/u/' . (int) $post['member_id'] . '">' . "\n"
    . ($catName !== '' ? '<meta property="article:section" content="' . h($catName) . '">' . "\n" : '');
foreach (post_tags($id) as $tg) {
    $artMeta .= '<meta property="article:tag" content="' . h($tg) . '">' . "\n";
}
$metaDesc = trim((string) ($post['seo_desc'] ?? '')) !== '' ? (string) $post['seo_desc'] : post_excerpt($post['body'], 110);
$crumbLd = [['@type' => 'ListItem', 'position' => 1, 'name' => 'トップ', 'item' => $siteUrl . '/']];
$pos = 2;
if ($catName !== '') {
    $crumbLd[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $catName, 'item' => $siteUrl . '/category/' . $catKey . '.html'];
}
$crumbLd[] = ['@type' => 'ListItem', 'position' => $pos, 'name' => $post['title'], 'item' => $postUrl];
$jsonld = [
    array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'description' => $metaDesc,
        'datePublished' => $pubIso,
        'dateModified' => $modIso,
        'inLanguage' => 'ja',
        'articleSection' => $catName !== '' ? $catName : null,
        'author' => ['@type' => 'Person', 'name' => $post['nickname'], 'url' => $siteUrl . '/u/' . (int) $post['member_id']],
        'image' => [$ogImage],
        'mainEntityOfPage' => $postUrl,
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'あるく',
            'url' => $siteUrl . '/',
            'logo' => ['@type' => 'ImageObject', 'url' => $siteUrl . '/assets/ogp.png', 'width' => 1200, 'height' => 630],
        ],
    ], static fn($v) => $v !== null),
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $crumbLd,
    ],
];
if ($faqArr) {
    $main = [];
    foreach ($faqArr as $qa) {
        $main[] = ['@type' => 'Question', 'name' => $qa['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa['a']]];
    }
    $jsonld[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $main];
}
member_render_page($prefix, $post['title'], $body, [
    'desc'      => $metaDesc,
    'robots'    => '', // 公開投稿はインデックス可
    'ogType'    => 'article',
    'canonical' => $postUrl,
    'ogImage'   => $ogImage,
    'jsonld'    => $jsonld,
    'headExtra' => $artMeta,
]);

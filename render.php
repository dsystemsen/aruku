<?php
/**
 * あるく 動的レンダラ（PHP版・build.py の移植）
 * ----------------------------------------------------------------
 * 記事データ（articles.php）を読み込み、各ページのHTMLを生成します。
 *   render_top()          … トップページ（LP）
 *   render_column_index() … コラム一覧（カテゴリ別ハブ）
 *   render_article($slug) … 各コラム記事（無ければ null を返す）
 *   render_sitemap()      … sitemap.xml
 *
 * 記事の追加・編集は管理画面（/admin/）から行えます（データは data/content.json）。
 * articles.php は初回シード用の元データです。
 */

require_once __DIR__ . '/cms.php';

// ============================================================
// サイト設定（編集可能な項目は data/content.json 由来）
// ============================================================
function site(): array
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $c = cms_load()['site'];
    $s = [
        'url'         => 'https://aruku.ne.jp',
        'brand'       => 'aruku',
        'brand_ja'    => 'アルク',
        'tagline'     => $c['tagline'],
        'description' => $c['description'],
        'author'      => $c['author'],
        'author_role' => $c['author_role'],
        'org'         => $c['org'],
        'org_url'     => $c['org_url'],
        'x_url'       => $c['x_url'],
        'year'        => 2026,
    ];
    return $s;
}

// ============================================================
// データ読み込み & カテゴリ内インデックス（連番・前後記事）
// build.py の index_articles() 相当。1度だけ計算してキャッシュ。
// ============================================================
function aruku_data(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $content        = cms_load();
    $CATEGORIES     = $content['categories'];
    $CATEGORY_ORDER = $content['category_order'];
    $ARTICLES       = $content['articles'];

    $by_slug = [];
    foreach ($ARTICLES as $a) {
        $by_slug[$a['slug']] = $a;
    }

    // カテゴリごとの記事リスト（CATEGORIES の定義順で初期化）
    $cat_lists = [];
    foreach ($CATEGORIES as $c => $_) {
        $cat_lists[$c] = [];
    }
    foreach ($ARTICLES as $a) {
        $cat_lists[$a['cat']][] = $a;
    }

    // 連番・前後ナビ
    $meta = [];
    foreach ($cat_lists as $cat => $lst) {
        $n = count($lst);
        foreach ($lst as $i => $a) {
            $meta[$a['slug']] = [
                'num'  => $i + 1,
                'prev' => $i > 0 ? $lst[$i - 1] : null,
                'next' => $i < $n - 1 ? $lst[$i + 1] : null,
            ];
        }
    }

    $cache = [
        'cats'      => $CATEGORIES,
        'order'     => $CATEGORY_ORDER,
        'articles'  => $ARTICLES,
        'by_slug'   => $by_slug,
        'cat_lists' => $cat_lists,
        'meta'      => $meta,
    ];
    return $cache;
}

// ============================================================
// 共通パーツ
// ============================================================
function thumb_svg(array $article, string $gid): string
{
    $d   = aruku_data();
    $cat = $d['cats'][$article['cat']];
    [$g0, $g1] = $cat['grad'];
    $num = $d['meta'][$article['slug']]['num'];
    $num2 = sprintf('%02d', $num);
    return '<svg viewBox="0 0 600 240" preserveAspectRatio="xMidYMid slice">'
        . '<defs><linearGradient id="' . $gid . '" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="' . $g0 . '"/><stop offset="1" stop-color="' . $g1 . '"/>'
        . '</linearGradient></defs>'
        . '<rect width="600" height="240" fill="url(#' . $gid . ')"/>'
        . '<text x="44" y="200" font-size="150" font-weight="900" fill="' . $cat['fg'] . '" '
        . 'opacity="0.22" font-family="-apple-system,sans-serif">' . $cat['emoji'] . '</text>'
        . '<text x="560" y="170" font-size="130" font-weight="900" fill="' . $cat['fg'] . '" '
        . 'text-anchor="end" opacity="0.30" font-family="-apple-system,sans-serif">' . $num2 . '</text>'
        . '</svg>';
}

function nav_html(string $prefix): string
{
    require_once __DIR__ . '/inc/member.php';
    member_session_start();
    $me = member_current();
    $adminLink = member_is_admin($me)
        ? '<a href="' . $prefix . 'member/users.php" class="lp-nav-cta lp-nav-admin">運営用</a>' . "\n      "
        : '';
    $authAttr = $me ? 'in' : 'out';
    return <<<HTML
<nav class="lp-nav" data-auth="{$authAttr}">
  <div class="lp-nav-inner">
    <a href="{$prefix}index.html" class="lp-brand"><img src="{$prefix}assets/logo.svg?v=20260612f" alt="あるくロゴ"><span class="lp-brand-text"><span class="lp-brand-tagline">-歩くことで健康に-</span><span class="lp-brand-name">あるく</span></span></a>
    <div class="lp-nav-links">
      {$adminLink}<a href="{$prefix}member/mypage.php" class="lp-nav-cta">マイページ</a>
      <a href="{$prefix}member/register.php" class="lp-nav-cta">会員登録</a>
      <a href="{$prefix}member/login.php" class="lp-nav-cta">ログイン</a>
      <a href="{$prefix}member/logout.php" class="lp-nav-logout">ログアウト</a>
    </div>
  </div>
</nav>
HTML;
}

function footer_html(string $prefix): string
{
    $s = site();
    return <<<HTML
<footer class="lp-footer">
  <div class="lp-footer-inner">
    <div>
      <div class="lp-footer-brand"><img src="{$prefix}assets/logo.svg?v=20260612f" alt="あるく ロゴ">あるく</div>
      <p class="lp-footer-tagline">{$s['tagline']}</p>
    </div>
    <nav class="lp-footer-links">
      <a href="{$prefix}index.html">トップ</a>
      <a href="{$prefix}calorie-table.html">消費カロリー</a>
      <a href="{$prefix}about.html">運営者情報</a>
      <a href="{$prefix}privacy.html">プライバシーポリシー</a>
      <a href="{$prefix}editorial-policy.html">編集・監修ポリシー</a>
      <a href="{$s['org_url']}" target="_blank" rel="noopener">🏢 運営会社</a>
      <a href="{$s['x_url']}" target="_blank" rel="noopener">公式𝕏</a>
    </nav>
  </div>
  <div class="lp-footer-copy">&copy; {$s['year']} {$s['org']}. All rights reserved.</div>
</footer>
HTML;
}

/** パンくず（トップ → 現在ページ）。$wrap=true で中央寄せのバーで囲む（フルブリードページ用）。 */
function breadcrumb_nav(string $prefix, string $label, bool $wrap = false): string
{
    $nav = '<nav class="column-breadcrumb" aria-label="パンくず"><a href="' . $prefix . 'index.html">トップ</a> ／ <span>' . $label . '</span></nav>';
    return $wrap ? '<div class="breadcrumb-bar">' . $nav . '</div>' : $nav;
}

/**
 * @param array|null $jsonld  JSON-LD ブロックの配列（各要素が1つの構造化データ）
 */
function head_html(string $prefix, string $title, string $desc, string $canonical, string $keywords = '', ?array $jsonld = null, string $og_type = 'article', string $robots = '', string $ogImage = '', string $headExtra = ''): string
{
    $s = site();
    // 動的HTMLは常に最新を返す（CMS編集・コンテンツ更新を即時反映）。
    // サーバ既定の max-age=600 を上書き。アセット(CSS/JS/画像)のキャッシュは別途維持。
    if (!headers_sent()) {
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
        header('Expires: 0');
    }
    // 既定（index系）は rich-result 拡張を付与。noindex系は渡された値をそのまま使う。
    $robotsContent = $robots !== '' ? $robots : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    $rb = '<meta name="robots" content="' . $robotsContent . '">' . "\n";
    // フォントはメイリオ（端末ローカル）を使用するため Web フォントの読込は不要。
    $css = '<link rel="stylesheet" href="' . $prefix . 'assets/style.css?v=20260612f">' . "\n"
        . '<link rel="stylesheet" href="' . $prefix . 'assets/column.css?v=20260612f">' . "\n"
        . '<noscript><style>.reveal,.reveal-stagger>*,.hero-anim,.hero-art-anim{opacity:1!important;transform:none!important;animation:none!important}</style></noscript>';
    // meta keywords は Google・各AIともに無視するため出力しない（引数は後方互換で受けるだけ）。
    $kw = '';
    $ld = '';
    if ($jsonld) {
        foreach ($jsonld as $block) {
            $ld .= '<script type="application/ld+json">' . "\n"
                . json_encode($block, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                . "\n</script>\n";
        }
    }
    $nav = nav_html($prefix);
    $ogp = $ogImage !== '' ? $ogImage : $s['url'] . '/assets/ogp.png';
    // 既定OG画像(ogp.png=1200×630)のときだけ寸法を明示（記事固有画像は実寸不明のため出さない）
    $ogpDims = substr($ogp, -15) === '/assets/ogp.png'
        ? "<meta property=\"og:image:width\" content=\"1200\">\n<meta property=\"og:image:height\" content=\"630\">\n"
        : '';
    // 公式Xアカウントから twitter:site を導出
    $twSite = (!empty($s['x_url']) && preg_match('#(?:x\.com|twitter\.com)/@?([A-Za-z0-9_]+)#', $s['x_url'], $xm))
        ? '<meta name="twitter:site" content="@' . $xm[1] . '">' . "\n"
        : '';
    return <<<HTML
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://pagead2.googlesyndication.com" crossorigin>
<link rel="preconnect" href="https://googleads.g.doubleclick.net" crossorigin>
<link rel="dns-prefetch" href="https://googlesyndication.com">
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7434781072719018" crossorigin="anonymous"></script>
<title>{$title}</title>
<meta name="description" content="{$desc}">
{$rb}{$kw}<link rel="canonical" href="{$canonical}">
<link rel="alternate" hreflang="ja" href="{$canonical}">
<link rel="alternate" hreflang="x-default" href="{$canonical}">
<meta property="og:type" content="{$og_type}">
<meta property="og:site_name" content="あるく">
<meta property="og:title" content="{$title}">
<meta property="og:description" content="{$desc}">
<meta property="og:url" content="{$canonical}">
<meta property="og:image" content="{$ogp}">
{$ogpDims}<meta property="og:image:alt" content="あるく — 歩くことの総合メディア">
<meta property="og:locale" content="ja_JP">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{$title}">
<meta name="twitter:description" content="{$desc}">
<meta name="twitter:image" content="{$ogp}">
{$twSite}<meta name="theme-color" content="#29b183">
{$headExtra}<link rel="icon" type="image/svg+xml" href="{$prefix}assets/logo.svg?v=20260612f">
{$css}
{$ld}</head>
<body>
{$nav}

HTML;
}

// ============================================================
// 記事ページの部品
// ============================================================
function render_related(array $article): string
{
    $d = aruku_data();
    $slugs = $article['related'] ?? [];
    if (!$slugs) {
        $slugs = [];
        foreach ($d['cat_lists'][$article['cat']] as $a) {
            if ($a['slug'] !== $article['slug']) {
                $slugs[] = $a['slug'];
            }
        }
    }
    $cards = [];
    $i = 0;
    foreach (array_slice($slugs, 0, 4) as $s) {
        $a = $d['by_slug'][$s] ?? null;
        if (!$a) {
            $i++;
            continue;
        }
        $num = $d['meta'][$a['slug']]['num'];
        $cat = $d['cats'][$a['cat']];
        $num2 = sprintf('%02d', $num);
        $sub = $a['subtitle'] ?? '';
        $cards[] = '<a href="./' . $a['slug'] . '.html" class="column-card">'
            . '<div class="column-thumb">' . thumb_svg($a, 'rel' . $i) . '</div>'
            . '<div class="column-card-body">'
            . '<span class="column-card-num">' . $cat['name'] . ' #' . $num2 . '</span>'
            . '<h3>' . $a['title'] . '</h3>'
            . '<p>' . $sub . '</p>'
            . '</div></a>';
        $i++;
    }
    if (!$cards) {
        return '';
    }
    return '<section class="column-section column-related-block">'
        . '<h2>関連記事</h2>'
        . '<div class="column-cards-grid reveal-stagger">' . implode('', $cards) . '</div>'
        . '</section>';
}

function render_affiliate(?array $aff): string
{
    if (!$aff) {
        return '';
    }
    return '<div class="affiliate-box">'
        . '<span class="aff-label">' . $aff['label'] . '</span>'
        . '<h4>' . $aff['title'] . '</h4>'
        . '<p>' . $aff['desc'] . '</p>'
        . '<div class="affiliate-slot"><!-- ▼ ここにASP（A8.net/もしも/Amazon/楽天）の広告タグを貼り付け ▼ -->'
        . '<br><a href="#" class="aff-btn" rel="nofollow sponsored">' . $aff['cta'] . '</a></div>'
        . '</div>';
}

function render_faq(?array $faq): string
{
    if (!$faq) {
        return '';
    }
    $items = '';
    foreach ($faq as $qa) {
        [$q, $a] = $qa;
        $items .= '<details class="column-faq-item"><summary>' . $q . '</summary>'
            . '<div class="column-faq-a">' . $a . '</div></details>';
    }
    return '<section class="column-section column-faq-block" id="faq">'
        . '<h2>よくある質問</h2>' . $items . '</section>';
}

// 公開日 "2026-05-25" → "2026年05月25日"
function format_date_ja(string $date): string
{
    $p = explode('-', $date);
    return $p[0] . '年' . $p[1] . '月' . $p[2] . '日';
}

// ============================================================
// 記事ページ全体
// ============================================================
function render_article(string $slug): ?string
{
    $d = aruku_data();
    $article = $d['by_slug'][$slug] ?? null;
    if (!$article) {
        return null;
    }
    $s = site();
    $prefix = '../';
    $cat = $d['cats'][$article['cat']];
    $url = $s['url'] . '/column/' . $article['slug'] . '.html';
    $full_title = $article['title'] . '｜aruku';
    $sub = !empty($article['subtitle']) ? '<br><small>' . $article['subtitle'] . '</small>' : '';

    // 目次
    $toc = '';
    foreach ($article['sections'] as $sec) {
        $toc .= '<li><a href="#' . $sec['id'] . '">' . $sec['h2'] . '</a></li>';
    }
    if (!empty($article['faq'])) {
        $toc .= '<li><a href="#faq">よくある質問</a></li>';
    }

    // 本文セクション
    $sections = '';
    foreach ($article['sections'] as $sec) {
        $sections .= '<section class="column-section" id="' . $sec['id'] . '"><h2>' . $sec['h2'] . '</h2>' . $sec['body'] . '</section>';
    }

    // 前後ナビ
    $m = $d['meta'][$article['slug']];
    $prev_a = $m['prev'];
    $next_a = $m['next'];
    $prev_html = $prev_a
        ? '<a href="./' . $prev_a['slug'] . '.html" class="column-nav-prev">← ' . $prev_a['title'] . '</a>'
        : '<span class="disabled">← 最初の記事です</span>';
    $next_html = $next_a
        ? '<a href="./' . $next_a['slug'] . '.html" class="column-nav-next">' . $next_a['title'] . ' →</a>'
        : '<span class="disabled">最新の記事です →</span>';

    // 本文の文字数（日本語は文字数で近似）— wordCount 用
    $plain = '';
    foreach ($article['sections'] as $sec) {
        $plain .= strip_tags($sec['body']);
    }
    $wordCount = mb_strlen(preg_replace('/\s+/u', '', $plain));

    // JSON-LD
    $jsonld = [
        [
            '@context'    => 'https://schema.org',
            '@type'       => 'Article',
            'headline'    => $article['title'],
            'description' => $article['desc'],
            // ※ Article の image はラスター画像（PNG/JPG）が必須。SVG はリッチリザルト非対応。
            'image'       => [$s['url'] . '/assets/ogp.png'],
            'inLanguage'  => 'ja',
            'articleSection' => $cat['name'],
            'isAccessibleForFree' => true,
            'wordCount'   => $wordCount,
            'author'      => [
                '@type'    => 'Person',
                'name'     => $s['author'],
                'jobTitle' => '代表取締役',
                'worksFor' => ['@type' => 'Organization', 'name' => $s['org'], 'url' => $s['org_url']],
                'url'      => $s['url'] . '/about.html',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => 'あるく',
                'url'   => $s['url'] . '/',
                'logo'  => ['@type' => 'ImageObject', 'url' => $s['url'] . '/assets/ogp.png', 'width' => 1200, 'height' => 630],
            ],
            'datePublished'    => $article['date'],
            'dateModified'     => $article['date'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'speakable'        => ['@type' => 'SpeakableSpecification', 'cssSelector' => ['.column-header h1', '.column-lead']],
        ],
        [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'あるく', 'item' => $s['url'] . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'コラム', 'item' => $s['url'] . '/column/'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title']],
            ],
        ],
    ];
    if (!empty($article['faq'])) {
        $main = [];
        foreach ($article['faq'] as $qa) {
            [$q, $a] = $qa;
            $main[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
        }
        $jsonld[] = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $main,
        ];
    }

    $head = head_html($prefix, $full_title, $article['desc'], $url, $article['keywords'] ?? '', $jsonld);

    $date_ja   = format_date_ja($article['date']);
    $hero_thumb = thumb_svg($article, 'hero');
    $lead      = $article['lead'];
    $read      = $article['read'];
    $title     = $article['title'];
    $catemoji  = $cat['emoji'];
    $catname   = $cat['name'];
    $affiliate = render_affiliate($article['affiliate'] ?? null);
    $faq       = render_faq($article['faq'] ?? null);
    $related   = render_related($article);
    $footer    = footer_html($prefix);
    $author      = $s['author'];
    $author_role = $s['author_role'];
    $cat_back    = $article['cat']; // 「コラム一覧」戻り先＝同カテゴリのページ

    $body = <<<HTML
<article class="column-article">
  <header class="column-header">
    <nav class="column-breadcrumb" aria-label="パンくず">
      <a href="../index.html">トップ</a> ／ <a href="./">コラム</a> ／ <span>{$title}</span>
    </nav>
    <div class="column-thumb" aria-hidden="true">{$hero_thumb}</div>
    <h1>{$title}{$sub}</h1>
    <div class="column-meta">
      <span class="column-cat-tag">{$catemoji} {$catname}</span>
      <span>公開日: {$date_ja}</span>
      <span>所要時間: 約{$read}分</span>
    </div>
    <p class="column-lead">{$lead}</p>
  </header>

  <nav class="column-toc" aria-label="目次">
    <h2>目次</h2>
    <ol>{$toc}</ol>
  </nav>

  {$sections}

  {$affiliate}

  <nav class="column-nav-prevnext">
    {$prev_html}
    <a href="../category/{$cat_back}.html" class="column-nav-back">コラム一覧</a>
    {$next_html}
  </nav>

  <aside class="column-author" aria-label="執筆者プロフィール">
    <div class="column-author-name">執筆: <strong>{$author}</strong></div>
    <div class="column-author-role">{$author_role}</div>
    <p class="column-author-bio">健康・ITに関する情報を分かりやすく届けることを目指し、aruku を企画・運営しています。</p>
    <a href="../about.html" class="column-author-link">運営者情報を見る →</a>
  </aside>

  {$faq}

  {$related}

  <section class="column-section column-conclusion">
    <h2>歩く習慣を、もっと深く知る</h2>
    <p>aruku では、ウォーキングの効果・正しい歩き方・カロリー・ポイ活・マシンまで、歩くことのすべてをコラムで発信しています。気になるテーマから読み進めてみてください。</p>
    <div class="column-cta">
      <a href="./" class="lp-btn lp-btn-primary">コラム一覧を見る</a>
      <a href="./calorie-table.html" class="lp-btn lp-btn-secondary">歩数別カロリー表を見る</a>
    </div>
    <p class="column-cta-note">※ 本サイトは一般的な健康情報を提供するものであり、医療行為・診断ではありません。</p>
  </section>
</article>

{$footer}
<script src="../assets/app.js?v=20260612f" defer></script>
</body>
</html>
HTML;

    return $head . $body;
}

// ============================================================
// コラム一覧ページ
// ============================================================
/** 公開投稿の配列を note.com 風カード群のHTMLにする（集計・タグはまとめて取得）。 */
function aruku_post_cards(array $pp, string $prefix): string
{
    if (!$pp) {
        return '';
    }
    require_once __DIR__ . '/inc/posts.php';
    $pcats = aruku_post_categories();
    $ids = array_map(static fn($x) => (int) $x['id'], $pp);
    $likeMap = like_counts_map($ids);
    $cmtMap  = comment_counts_map($ids);
    $imgMap  = post_first_images_map($ids);
    $tagMap  = post_tags_map($ids);
    $cards = '';
    foreach ($pp as $p) {
        $pid = (int) $p['id'];
        $av = h(post_avatar_char($p['nickname']));
        $dt = h(substr((string) ($p['published_at'] ?: $p['created_at']), 0, 10));
        // カード用サムネイルは image 列（専用デザイン）を優先、なければ最初の投稿画像
        $coverFile = ($p['image'] ?? '') !== '' ? $p['image'] : ($imgMap[$pid] ?? '');
        $cover = $coverFile
            ? '<div class="note-card-cover"><img src="' . $prefix . 'uploads/' . h($coverFile) . '" alt="' . h($p['title']) . '" loading="lazy" decoding="async"></div>'
            : '';
        $catTag = (!empty($p['category']) && isset($pcats[$p['category']]))
            ? '<span class="note-cat">' . h($pcats[$p['category']]) . '</span>' : '';
        $tagHtml = '';
        foreach (($tagMap[$pid] ?? []) as $tg) {
            $tagHtml .= '<span class="note-tag">#' . h($tg) . '</span>';
        }
        $lk = $likeMap[$pid] ?? 0;
        $cm = $cmtMap[$pid] ?? 0;
        $cards .= '<a class="note-card' . ($cover ? ' has-cover' : '') . '" href="' . $prefix . 'posts/' . $pid . '">'
            . $cover
            . '<div class="note-card-meta">' . $catTag . $tagHtml . '</div>'
            . '<h3 class="note-card-title">' . h($p['title']) . '</h3>'
            . '<p class="note-card-excerpt">' . h(post_excerpt($p['body'])) . '</p>'
            . '<div class="note-card-foot"><span class="note-avatar">' . $av . '</span>'
            . '<span class="note-author">' . h($p['nickname']) . '</span>'
            . '<span class="note-dot">·</span><span class="note-date">' . $dt . '</span>'
            . '<span class="note-stats">♥ ' . $lk . '　💬 ' . $cm . '</span></div>'
            . '</a>';
    }
    return $cards;
}

/** カテゴリ左ナビ（note.com 風）。$base はコラム一覧へのリンク先（例 'index.html' / 'column/index.html'）、$active は現在のカテゴリキー。 */
function aruku_category_nav(string $prefix, string $active): string
{
    require_once __DIR__ . '/inc/posts.php';
    $em = aruku_post_category_emoji();
    // 各カテゴリは専用の一覧ページ /category/<key>.html へ（「すべて」カテゴリは廃止）
    $items = '';
    foreach (aruku_post_categories() as $k => $lbl) {
        $items .= '<a class="cat-nav-item' . ($active === $k ? ' active' : '') . '" href="' . $prefix . 'category/' . h($k) . '.html">'
            . ($em[$k] ?? '') . ' ' . h($lbl) . '</a>';
    }
    return '<nav class="cat-nav"><div class="cat-nav-head">カテゴリ</div>' . $items . '</nav>';
}

function render_column_index(): string
{
    $d = aruku_data();
    $s = site();
    $prefix = '../';
    $url = $s['url'] . '/column/';
    $title = 'コラム一覧｜あるく';
    $desc = 'ウォーキングの効果・正しい歩き方・歩数別カロリー・歩いてポイ活・ウォーキングマシン。歩くことに関するすべてのコラムをカテゴリ別にまとめました。';

    // カテゴリ絞り込み（?cat=koka など）。指定時はそのカテゴリのコラムのみ表示。
    require_once __DIR__ . '/inc/posts.php';
    $pcatsAll = aruku_post_categories();
    $filterCat = isset($_GET['cat']) ? preg_replace('/[^a-z0-9_-]/', '', (string) $_GET['cat']) : '';
    if ($filterCat !== '' && !isset($d['cats'][$filterCat]) && !isset($pcatsAll[$filterCat])) {
        $filterCat = '';
    }
    if ($filterCat !== '') {
        $catName = $d['cats'][$filterCat]['name'] ?? ($pcatsAll[$filterCat] ?? '');
        $title = $catName . '｜あるく コラム';
        $desc  = $d['cats'][$filterCat]['desc'] ?? ('会員が投稿した「' . $catName . '」のコラム。');
    }

    // 全記事インデックス（開閉式）
    $all_links = '';
    foreach ($d['articles'] as $a) {
        $all_links .= '<a href="./' . $a['slug'] . '.html">' . $a['title'] . '</a>';
    }
    $toc_index = '<details class="column-toc-index"><summary>全記事インデックス（'
        . count($d['articles']) . '記事）</summary>'
        . '<div class="column-toc-index-list">' . $all_links . '</div></details>';
    if ($filterCat !== '') {
        $toc_index = '';
    }

    $sections = '';
    foreach ($d['order'] as $cat_key) {
        if ($filterCat !== '' && $cat_key !== $filterCat) {
            continue;
        }
        $cat = $d['cats'][$cat_key];
        $arts = $d['cat_lists'][$cat_key] ?? [];
        if (!$arts) {
            continue;
        }
        $cards = '';
        foreach ($arts as $i => $a) {
            $num = $d['meta'][$a['slug']]['num'];
            $num2 = sprintf('%02d', $num);
            $sub = $a['subtitle'] ?? '';
            $cards .= '<a href="./' . $a['slug'] . '.html" class="column-card">'
                . '<div class="column-thumb">' . thumb_svg($a, $cat_key . $i) . '</div>'
                . '<div class="column-card-body">'
                . '<span class="column-card-num">#' . $num2 . '</span>'
                . '<h3>' . $a['title'] . '</h3>'
                . '<p>' . $sub . '</p>'
                . '</div></a>';
        }
        $sections .= '<section class="column-cat-section reveal" id="' . $cat_key . '">'
            . '<h2 class="column-cat-title"><span class="cat-emoji">' . $cat['emoji'] . '</span>' . $cat['name'] . '</h2>'
            . '<p class="column-cat-desc">' . $cat['desc'] . '</p>'
            . '<div class="column-cards-grid reveal-stagger">' . $cards . '</div>'
            . '</section>';
    }

    $jsonld = [[
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        'name'        => 'あるく コラム一覧',
        'description' => $desc,
        'url'         => $url,
    ]];
    $head = head_html($prefix, $title, $desc, $url, 'ウォーキング コラム,歩く 健康 記事', $jsonld);
    $footer = footer_html($prefix);

    if ($filterCat !== '') {
        $cName = $d['cats'][$filterCat]['name'] ?? ($pcatsAll[$filterCat] ?? '');
        $heroH1 = $cName;
        $heroP  = ($d['cats'][$filterCat]['desc'] ?? ('「' . $cName . '」のコラム。'))
            . '<br><a class="column-back-link" href="./index.html">← コラム一覧へ戻る</a>';
    } else {
        $heroH1 = 'あるく コラム';
        $heroP  = '歩くことの効果から、正しい歩き方・カロリー・ポイ活・マシンまで。<br>気になるカテゴリから読み進めてください。';
    }

    // 会員投稿（公開済み）を note.com 風フィードで先頭に表示（カテゴリ絞り込みにも連動）
    require_once __DIR__ . '/inc/posts.php';
    $userFeed = '';
    $pcats = aruku_post_categories();
    $sort = (($_GET['sort'] ?? 'new') === 'popular') ? 'popular' : 'new';
    $perPage = 12;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $catParam = $filterCat !== '' ? $filterCat : null;
    $pp = posts_published($perPage, $catParam, $sort, ($page - 1) * $perPage);
    $total = posts_published_count($catParam);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $qs = function (array $over) use ($filterCat, $sort, $page) {
        $p = [];
        if ($filterCat !== '') {
            $p['cat'] = $filterCat;
        }
        if ($sort !== 'new') {
            $p['sort'] = $sort;
        }
        if ($page > 1) {
            $p['page'] = $page;
        }
        $p = array_merge($p, $over);
        if (($p['sort'] ?? 'new') === 'new') {
            unset($p['sort']);
        }
        if ((int) ($p['page'] ?? 1) === 1) {
            unset($p['page']);
        }
        return 'index.html' . ($p ? '?' . http_build_query($p) : '');
    };
    $userFeed = '';
    if ($total > 0) {
        $sortTabs = '<div class="sort-tabs">'
            . '<a class="sort-tab' . ($sort === 'new' ? ' active' : '') . '" href="' . $qs(['sort' => 'new', 'page' => 1]) . '">新着</a>'
            . '<a class="sort-tab' . ($sort === 'popular' ? ' active' : '') . '" href="' . $qs(['sort' => 'popular', 'page' => 1]) . '">人気</a></div>';
        $pager = '';
        if ($totalPages > 1) {
            $prev = $page > 1 ? '<a class="pager-link" href="' . $qs(['page' => $page - 1]) . '">← 前</a>' : '<span class="pager-link disabled">← 前</span>';
            $next = $page < $totalPages ? '<a class="pager-link" href="' . $qs(['page' => $page + 1]) . '">次 →</a>' : '<span class="pager-link disabled">次 →</span>';
            $pager = '<div class="pager">' . $prev . '<span class="pager-info">' . $page . ' / ' . $totalPages . '</span>' . $next . '</div>';
        }
        // カテゴリ絞り込み時はカテゴリ名を見出しに表示
        $catEmojiMap = aruku_post_category_emoji();
        if ($filterCat !== '') {
            $feedEmoji = $catEmojiMap[$filterCat] ?? '📝';
            $feedName  = $d['cats'][$filterCat]['name'] ?? ($pcatsAll[$filterCat] ?? 'コラム');
            $feedDesc  = '「' . h($feedName) . '」に関する会員のコラムです。';
        } else {
            $feedEmoji = '📝';
            $feedName  = 'みんなのコラム';
            $feedDesc  = '会員のみなさんが投稿したコラムです。';
        }
        $userFeed = '<section class="column-cat-section reveal" id="minna">'
            . '<h2 class="column-cat-title"><span class="cat-emoji">' . $feedEmoji . '</span>' . h($feedName) . '</h2>'
            . '<p class="column-cat-desc">' . $feedDesc
            . '<a href="' . $prefix . 'member/post.php">あなたも書いてみる →</a></p>'
            . $sortTabs
            . '<div class="note-feed">' . aruku_post_cards($pp, $prefix) . '</div>'
            . $pager . '</section>';
    }

    // 検索ボックス＋人気タグ
    $q   = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $tag = isset($_GET['tag']) ? mb_strtolower(trim((string) $_GET['tag'])) : '';
    $searchBox = '<form class="column-search" method="get" action="index.html" role="search">'
        . '<input type="search" name="q" value="' . h($q) . '" placeholder="コラムを検索…">'
        . '<button type="submit">検索</button></form>';
    $tagCloud = '';
    $pop = tags_popular(20);
    if ($pop) {
        $tc = '';
        foreach ($pop as $t) {
            $tc .= '<a href="index.html?tag=' . rawurlencode($t['tag']) . '">#' . h($t['tag']) . '<small>' . (int) $t['c'] . '</small></a>';
        }
        $tagCloud = '<div class="tag-cloud"><span class="tag-cloud-label">人気タグ</span>' . $tc . '</div>';
    }

    // 検索・タグ表示モードでは一覧をその結果に差し替え
    if ($q !== '') {
        $res = posts_search($q, 50);
        $cards = aruku_post_cards($res, $prefix);
        $userFeed = '<section class="column-cat-section reveal"><h2 class="column-cat-title">「' . h($q) . '」の検索結果（' . count($res) . '）</h2>'
            . ($cards ? '<div class="note-feed">' . $cards . '</div>' : '<p class="column-cat-desc">該当する投稿が見つかりませんでした。</p>') . '</section>';
        $toc_index = '';
        $ranking = '';
        $sections = '';
        $heroH1 = 'コラムを検索';
        $heroP = 'キーワードで会員コラムを探せます。';
    } elseif ($tag !== '') {
        $res = posts_by_tag($tag, 50);
        $cards = aruku_post_cards($res, $prefix);
        $userFeed = '<section class="column-cat-section reveal"><h2 class="column-cat-title">#' . h($tag) . '（' . count($res) . '）</h2>'
            . ($cards ? '<div class="note-feed">' . $cards . '</div>' : '<p class="column-cat-desc">このタグの投稿はまだありません。</p>') . '</section>';
        $toc_index = '';
        $ranking = '';
        $sections = '';
        $heroH1 = '#' . $tag;
        $heroP = 'タグ「' . $tag . '」のコラム。';
    }

    // いいね数ランキング（期間別タブ。絞り込み・検索なしのとき）
    $ranking = '';
    if ($filterCat === '' && $q === '' && $tag === '') {
        $rank = (string) ($_GET['rank'] ?? 'all');
        $daysMap = ['week' => 7, 'month' => 30, 'all' => null];
        $days = array_key_exists($rank, $daysMap) ? $daysMap[$rank] : null;
        if (!array_key_exists($rank, $daysMap)) {
            $rank = 'all';
        }
        $top = posts_top_liked(5, $days);
        $tabs = '';
        foreach (['all' => '全期間', 'month' => '今月', 'week' => '今週'] as $k => $lbl) {
            $tabs .= '<a class="rank-tab' . ($rank === $k ? ' active' : '') . '" href="index.html?rank=' . $k . '#ranking">' . $lbl . '</a>';
        }
        $items = '';
        $rk = 0;
        foreach ($top as $tp) {
            $rk++;
            $items .= '<a class="rank-item" href="' . $prefix . 'posts/' . (int) $tp['id'] . '">'
                . '<span class="rank-num">' . $rk . '</span>'
                . '<span class="rank-title">' . h($tp['title']) . '</span>'
                . '<span class="rank-likes">♥ ' . (int) $tp['likes'] . '</span></a>';
        }
        $listHtml = $items !== '' ? '<div class="rank-list">' . $items . '</div>' : '<p class="column-cat-desc">この期間でいいねされたコラムはまだありません。</p>';
        $ranking = '<section class="column-cat-section reveal" id="ranking">'
            . '<h2 class="column-cat-title"><span class="cat-emoji">🏆</span>人気のコラム</h2>'
            . '<div class="rank-tabs">' . $tabs . '</div>'
            . $listHtml . '</section>';
    }

    $catNav = aruku_category_nav('../', $filterCat);
    // デフォルトの一覧ページでは冒頭の見出し枠を出さない（絞り込み・検索・タグ時のみ表示）
    $isDefaultList = ($filterCat === '' && $q === '' && $tag === '');
    $heroBlock = $isDefaultList ? '' : '<div class="column-list-hero"><h1>' . $heroH1 . '</h1><p>' . $heroP . '</p></div>';
    $body = <<<HTML
{$heroBlock}
<div class="column-layout">
  <aside class="column-side">{$catNav}</aside>
  <div class="column-main column-article">
    {$searchBox}
    {$tagCloud}
    {$toc_index}
    {$ranking}
    {$userFeed}
    {$sections}
    <section class="column-section column-conclusion">
      <h2>まずはここから</h2>
      <p>「何歩でどれくらい消費するの？」が気になる方は、まず歩数別カロリー表をチェック。歩くことの全体像は効果・効能ガイドからどうぞ。</p>
      <div class="column-cta">
        <a href="../calorie-table.html" class="lp-btn lp-btn-primary">歩数別カロリー表</a>
        <a href="../category/koka.html" class="lp-btn lp-btn-secondary">ウォーキングの効果・効能</a>
      </div>
    </section>
  </div>
</div>

{$footer}
<script src="../assets/app.js?v=20260612f" defer></script>
</body>
</html>
HTML;
    return $head . $body;
}

// ============================================================
// カテゴリ別コラム一覧（note風）： /category/<cat>.html
// 「すべて(index)」ページは廃止＝無効/index/未知カテゴリは404。
// ============================================================
function render_category_columns(string $cat): string
{
    $s = site();
    $prefix = '../';
    require_once __DIR__ . '/inc/posts.php';
    $pcats = aruku_post_categories();
    $em = aruku_post_category_emoji();
    $cat = preg_replace('/[^a-z0-9_-]/', '', (string) $cat);
    // 「すべて」ページは削除済み。特定カテゴリ以外は404。
    if ($cat === '' || $cat === 'index' || !isset($pcats[$cat])) {
        http_response_code(404);
        return '<!doctype html><html lang="ja"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex, nofollow">'
            . '<title>ページが見つかりません｜あるく</title>'
            . '<link rel="stylesheet" href="' . $prefix . 'assets/style.css?v=20260612f"></head><body>'
            . '<main style="max-width:640px;margin:14vh auto;padding:0 24px;text-align:center;">'
            . '<h1 style="font-size:1.6rem;margin-bottom:12px;">ページが見つかりません</h1>'
            . '<p style="color:#5d6362;margin-bottom:28px;">お探しのページは削除されました。</p>'
            . '<p><a class="lp-btn lp-btn-primary" href="' . $prefix . 'index.html">トップへ戻る</a></p>'
            . '</main></body></html>';
    }
    $name = $pcats[$cat];
    $emoji = $em[$cat] ?? '📝';
    $pp = posts_published(300, $cat, 'new', 0);
    $url = $s['url'] . '/category/' . $cat . '.html';
    $count = count($pp);
    $cards = aruku_post_cards($pp, $prefix);
    $feed = $cards
        ? '<div class="note-feed">' . $cards . '</div>'
        : '<p class="rail-empty">このカテゴリのコラムはまだありません。<a href="' . $prefix . 'member/post.php">最初のコラムを書いてみませんか？ →</a></p>';

    // 編集部の特集記事（CMS記事＝5本柱）。会員投稿の上に表示し、記事ページへ内部リンク。
    $d = aruku_data();
    $editArts = $d['cat_lists'][$cat] ?? [];
    $editSection = '';
    $editItems = [];
    if ($editArts) {
        $ecards = '';
        foreach ($editArts as $i => $a) {
            $sub = $a['subtitle'] ?? '';
            $ecards .= '<a href="' . $prefix . 'column/' . $a['slug'] . '.html" class="column-card">'
                . '<div class="column-thumb">' . thumb_svg($a, 'edit' . $cat . $i) . '</div>'
                . '<div class="column-card-body">'
                . '<span class="column-card-num">' . $emoji . ' 編集部</span>'
                . '<h3>' . h($a['title']) . '</h3>'
                . '<p>' . h($sub) . '</p>'
                . '</div></a>';
            $editItems[] = $s['url'] . '/column/' . $a['slug'] . '.html';
        }
        $editSection = '<section class="column-cat-section reveal"><h2 class="column-cat-title"><span class="cat-emoji">📚</span>編集部の特集記事</h2>'
            . '<p class="column-cat-desc">「' . h($name) . '」をくわしく掘り下げた、編集部のコラムです。</p>'
            . '<div class="column-cards-grid">' . $ecards . '</div></section>';
    }
    $feedHeader = $cards
        ? '<h2 class="column-cat-title"><span class="cat-emoji">📝</span>みんなのコラム</h2>'
        : '';

    $catNav = aruku_category_nav($prefix, $cat);
    $title = $name . '｜あるく コラム';
    $desc = '「' . $name . '」に関するコラム一覧（' . $count . '本）。';
    $listItems = [];
    $i = 1;
    foreach ($editArts as $a) {
        $listItems[] = ['@type' => 'ListItem', 'position' => $i++, 'url' => $s['url'] . '/column/' . $a['slug'] . '.html', 'name' => $a['title']];
    }
    foreach ($pp as $p) {
        $listItems[] = ['@type' => 'ListItem', 'position' => $i++, 'url' => $s['url'] . '/posts/' . (int) $p['id'], 'name' => $p['title']];
    }
    $jsonld = [
        [
            '@context' => 'https://schema.org',
            '@type'    => 'CollectionPage',
            'name'     => $title,
            'description' => $desc,
            'url'      => $url,
            'inLanguage' => 'ja',
            'isPartOf' => ['@type' => 'WebSite', 'name' => 'あるく', 'url' => $s['url'] . '/'],
            'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $count, 'itemListElement' => $listItems],
        ],
        [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'トップ', 'item' => $s['url'] . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $name, 'item' => $url],
            ],
        ],
    ];
    $head = head_html($prefix, $title, $desc, $url, '', $jsonld, 'website', 'index, follow', '');
    $footer = footer_html($prefix);
    $heroDesc = h($desc);
    $heroName = h($name);
    $crumb = breadcrumb_nav($prefix, h($name), true);
    $body = <<<HTML
{$crumb}
<div class="column-list-hero"><h1>{$emoji} {$heroName}</h1><p>{$heroDesc}</p></div>
<div class="column-layout">
  <aside class="column-side">{$catNav}</aside>
  <div class="column-main column-article">
    {$editSection}
    {$feedHeader}
    {$feed}
  </div>
</div>
{$footer}
<script src="{$prefix}assets/app.js?v=20260612f" defer></script>
</body>
</html>
HTML;
    return $head . $body;
}

// ============================================================
// 編集・監修ポリシー（YMYL対応）/editorial-policy.html
// ============================================================
function render_editorial_policy(): string
{
    $s = site();
    $prefix = '';
    $url = $s['url'] . '/editorial-policy.html';
    $title = '編集・監修ポリシー｜あるく';
    $desc = '歩くことの総合メディア「あるく」の編集方針・情報の根拠・健康情報の取り扱い（免責）・更新方針について。';
    $jsonld = [[
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'トップ', 'item' => $s['url'] . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => '編集・監修ポリシー', 'item' => $url],
        ],
    ]];
    $head = head_html($prefix, $title, $desc, $url, '', $jsonld, 'website', 'index, follow');
    $footer = footer_html($prefix);
    $crumb = breadcrumb_nav($prefix, '編集・監修ポリシー', true);
    $org = h($s['org']);
    $orgUrl = h($s['org_url']);
    $body = <<<HTML
{$crumb}
<section class="about-section reveal">
  <div class="about-inner column-article">
    <h1 class="about-title">編集・監修ポリシー</h1>
    <p class="about-lead">「あるく」は、歩くことに関する情報を、できるだけ分かりやすく・誠実にお届けするための編集方針を定めています。</p>

    <h2>運営と編集体制</h2>
    <p>本メディアは <a href="{$orgUrl}" target="_blank" rel="noopener">{$org}</a> が運営しています。記事は編集部および会員のみなさんが執筆し、公開前に内容を確認しています。表現が不適切な投稿は、キーワード判定および自動モデレーションにより公開前に保留する体制をとっています。</p>

    <h2>情報の根拠について</h2>
    <p>健康・運動・カロリーに関する記述は、一般に広く知られている知見や、公的機関が公開している情報（例：厚生労働省「e-ヘルスネット」、スポーツ庁の啓発資料など）を参考に、編集部が分かりやすく整理したものです。消費カロリーは「消費kcal ≒ 歩数 × 体重kg × 0.0005」などの簡易式による<strong>目安</strong>であり、体質・歩き方・環境により前後します。</p>

    <h2>健康情報の取り扱い（重要）</h2>
    <p>本サイトの情報は<strong>一般的な健康情報の提供を目的としたものであり、医療上の診断・治療・助言に代わるものではありません</strong>。持病のある方、体調に不安のある方、妊娠中の方などは、運動の開始・変更にあたって必ず医師等の専門家にご相談ください。本サイトの情報の利用によって生じたいかなる結果についても、運営者は責任を負いかねます。</p>

    <h2>更新と訂正</h2>
    <p>各コラムには公開日・最終更新日を表示しています。内容に誤りや古くなった情報を見つけた場合は、できる限り速やかに訂正・更新します。ご指摘は運営会社のお問い合わせ窓口までお寄せください。</p>

    <h2>お問い合わせ</h2>
    <p>ご意見・訂正のご依頼は <a href="{$orgUrl}" target="_blank" rel="noopener">運営会社のお問い合わせ窓口</a> までお願いいたします。退会（解約）はログイン後のマイページ下部の「解約手続き」から行えます。</p>

    <div class="about-actions"><a class="lp-btn lp-btn-secondary" href="faq.html">よくある質問→</a><a class="lp-btn lp-btn-primary" href="index.html">トップへ戻る</a></div>
  </div>
</section>
{$footer}
<script src="{$prefix}assets/app.js?v=20260612f" defer></script>
</body>
</html>
HTML;
    return $head . $body;
}

// ============================================================
// コラム内検索 /search.html?q=...
// ============================================================
function render_search_page(string $q): string
{
    $s = site();
    $prefix = '';
    require_once __DIR__ . '/inc/posts.php';
    $q = trim($q);
    $qh = h($q);
    $results = $q !== '' ? posts_search($q, 60) : [];
    $count = count($results);
    $url = $s['url'] . '/search.html' . ($q !== '' ? '?q=' . rawurlencode($q) : '');
    $title = ($q !== '' ? '「' . $q . '」の検索結果' : 'コラムを検索') . '｜あるく';
    $desc = $q !== '' ? '「' . $q . '」に関するコラムの検索結果（' . $count . '本）。' : 'あるくのコラムをキーワードで検索できます。';
    // 検索結果ページはインデックスさせない（薄い・重複回避）。リンクは追う。
    $head = head_html($prefix, $title, $desc, $url, '', null, 'website', 'noindex, follow', '');
    $footer = footer_html($prefix);
    $crumb = breadcrumb_nav($prefix, 'コラムを検索', true);
    $catNav = aruku_category_nav($prefix, '');
    if ($q === '') {
        $feed = '<p class="rail-empty">キーワードを入力してコラムを検索してください。</p>';
    } elseif ($results) {
        $feed = '<p class="search-count">「<b>' . $qh . '</b>」の検索結果：' . $count . '本</p>'
            . '<div class="note-feed">' . aruku_post_cards($results, $prefix) . '</div>';
    } else {
        $feed = '<p class="rail-empty">「' . $qh . '」に一致するコラムは見つかりませんでした。別のキーワードでお試しください。</p>';
    }
    $body = <<<HTML
{$crumb}
<div class="column-list-hero"><h1>🔍 コラムを検索</h1>
  <form class="site-search" method="get" action="search.html" role="search">
    <input type="search" name="q" value="{$qh}" placeholder="例：早歩き、膝、消費カロリー、ダイエット" aria-label="サイト内検索">
    <button type="submit" class="lp-btn lp-btn-primary">検索</button>
  </form>
</div>
<div class="column-layout">
  <aside class="column-side">{$catNav}</aside>
  <div class="column-main column-article">
    {$feed}
  </div>
</div>
{$footer}
<script src="{$prefix}assets/app.js?v=20260612f" defer></script>
</body>
</html>
HTML;
    return $head . $body;
}

// ============================================================
// 「あるくとは？」ページ /about-aruku.html
// ============================================================
function render_aboutaruku(): string
{
    $s = site();
    $prefix = '';
    $title = 'あるくとは？｜あるく';
    $desc = '「あるく」は歩くことの総合メディア。歩数別カロリー・コラム・記録機能など、サービスの特長をご紹介します。';
    $head = head_html($prefix, $title, $desc, $s['url'] . '/about-aruku.html', '', null, 'website', 'index, follow');
    $footer = footer_html($prefix);
    $crumb = breadcrumb_nav($prefix, 'あるくとは？', true);
    $body = <<<HTML
{$crumb}
<section class="aboutpage-il">
  <div class="aboutpage-il-inner">
    <div class="aboutpage-il-img"><img src="uploads/aruku_runner.png" alt="ランニングする女性のイラスト" loading="lazy"></div>
    <div class="aboutpage-il-text">
      <h1 class="aboutpage-title aboutpage-title--left">あるくとは？</h1>
      <p class="aboutpage-sub">「歩くだけでやせるって、ホント？」——ホントです。「あるく」がダイエットと健康習慣の相棒になる、5つのうれしいポイント。</p>
      <div class="aboutpage-point"><span class="pt-no">1</span><div class="pt-tx"><b>サイフにやさしい。タダで始められる</b><span>ジムの月会費も、高い器具もいりません。必要なのは靴1足だけ。コラムも歩数別カロリーツールも会員登録もぜんぶ無料だから、お金の心配なしで今日からスタートできます。</span></div></div>
      <div class="aboutpage-point"><span class="pt-no">2</span><div class="pt-tx"><b>「何歩で何kcal？」がひと目でわかる</b><span>早見表と計算ツールで、目標までに歩く歩数と消費カロリーがパッと丸わかり。なんとなく歩くより、ぐっと効率よく、ムダなくやせられます。</span></div></div>
      <div class="aboutpage-point"><span class="pt-no">3</span><div class="pt-tx"><b>がんばりが数字で見えるから、続く＆リバウンドしにくい</b><span>体重と歩数を記録すれば、消費カロリーが自動でどんどん積み上がる。減っていく数字を見るのが楽しくなって、気づけば「やせ習慣」が身についています。</span></div></div>
      <div class="aboutpage-point"><span class="pt-no">4</span><div class="pt-tx"><b>やせるだけじゃない、カラダの中から元気に</b><span>歩くことは、脂肪燃焼だけじゃなく血圧・血糖値ケアや睡眠の質アップにもうれしい運動。見た目スッキリ＆中身も健康、一石二鳥をまるごと狙えます。</span></div></div>
      <div class="aboutpage-point"><span class="pt-no">5</span><div class="pt-tx"><b>ひとりじゃないから、心が折れない</b><span>同じように歩いてがんばる仲間の投稿が、毎日の励みに。続け方のコツが詰まったコラムも読み放題。「今日はサボりたいな」って日も、そっと背中を押してくれます。</span></div></div>
      <div class="about-actions"><a class="lp-btn lp-btn-secondary" href="faq.html">よくある質問（FAQ）→</a><a class="lp-btn lp-btn-primary" href="index.html">トップへ戻る</a></div>
    </div>
  </div>
</section>
{$footer}
<script src="assets/app.js?v=20260612f" defer></script>
</body>
</html>
HTML;
    return $head . $body;
}

// ============================================================
// 「よくある質問（FAQ）」ページ /faq.html
// ============================================================
function render_faq_page(): string
{
    $s = site();
    $prefix = '';
    $title = '歩く・健康のよくある質問（FAQ）｜歩くとふらつく原因・高齢者の歩数・ウォーキングとの違いも｜あるく';
    $desc = '歩くことと健康についてのよくある質問。ウォーキングの健康効果や1日の目標歩数に加え、歩くとふらつく原因、70歳・75歳は1日何歩歩くべきか、赤ちゃんが歩く時期、「歩く」と「ウォーキング」の違い、42.195kmを歩くと何時間か、歩く哲学、サルコペニア対策、Walk in Her Shoes（歩く国際協力）まで、歩くにまつわる素朴な疑問にお答えします。';
    $faqLd = [[
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            ['@type' => 'Question', 'name' => '歩くと健康にどんな効果がありますか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'ウォーキングには、脂肪燃焼によるダイエット効果、血圧・血糖値の改善、心肺機能の向上、骨や筋力の維持、ストレス軽減や睡眠の質アップなど、心と体の幅広い健康効果が期待できます。特別な道具がいらず、誰でも今日から始められるのが最大の魅力です。']],
            ['@type' => 'Question', 'name' => '1日何歩あるけば健康にいいですか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '一般的な目安は1日8,000歩・週合計150分の活動です。1万歩を目標にする方も多いですが、もともと歩数が少ない人ほど、少し増やすだけで健康効果が大きいとされています。まずは今より1,000歩多く歩くことから始めてみましょう。']],
            ['@type' => 'Question', 'name' => '毎日歩かないと健康効果はありませんか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '毎日でなくても、週の合計時間が確保できれば効果は期待できます。10分×3回のこま切れウォーキングでも、まとめて30分歩くのとほぼ同じ効果があるとされています。続けやすい形で習慣にすることが一番大切です。']],
            ['@type' => 'Question', 'name' => '運動が苦手でも、歩くだけで健康になれますか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'はい。ウォーキングは強度を自分で調整できる有酸素運動で、運動が苦手な方にもおすすめです。「少し息が弾む」程度の早歩きを取り入れると、無理なく健康効果を高められます。'] ],
            ['@type' => 'Question', 'name' => '朝と夜、健康のために歩くならどちらがいいですか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'どちらにも利点があります。朝の歩行は生活リズムが整い目覚めが良くなり、夜（食後30〜60分）の歩行は血糖値対策に向いています。大切なのは時間帯よりも継続です。自分の生活に合うタイミングを選びましょう。']],
            ['@type' => 'Question', 'name' => '高齢者や運動不足の人が歩いても大丈夫ですか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'ウォーキングは年齢を問わず取り組みやすい運動ですが、持病のある方・治療中の方・長く運動していなかった方は、始める前に医師にご相談ください。本サイトの情報は一般的な健康情報であり、医療行為・診断ではありません。']],
            ['@type' => 'Question', 'name' => 'あるくは無料で使えますか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'はい。コラムの閲覧も、歩数別カロリー早見表・計算ツールも無料でご利用いただけます。会員登録も無料です。']],
            ['@type' => 'Question', 'name' => '会員登録すると何ができますか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '体重・運動の記録から消費カロリーを自動で計算・累計できます。コラムの投稿、いいね、コメント、保存などのコミュニティ機能もご利用いただけます。']],
            ['@type' => 'Question', 'name' => 'コラムは誰が書いていますか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '編集部のほか、会員のみなさんも投稿しています。投稿されたコラムは、公開前に内容を確認しています。']],
            ['@type' => 'Question', 'name' => 'スマートフォンでも使えますか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'はい。スマホ・タブレット・PCのどの画面にも対応しています。']],
            ['@type' => 'Question', 'name' => '表示される消費カロリーは正確ですか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '体重や歩数から算出した目安です（消費kcal ≒ 歩数 × 体重 × 0.0005）。体質や歩き方で前後します。']],
            ['@type' => 'Question', 'name' => '退会したいときはどうすればいいですか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'ログイン後のマイページ下部にある「解約手続き」ボタンからお手続きいただけます。解約すると登録情報・記録はすべて削除され、元に戻せませんのでご注意ください。']],
            ['@type' => 'Question', 'name' => '歩くとふらつくのは何が原因ですか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '歩くときのふらつきは、加齢による足腰の筋力低下やバランス感覚の衰え、めまい（内耳の異常）、起立性低血圧、貧血、脱水、薬の副作用、神経の病気など、さまざまな原因が考えられます。一時的でなく繰り返す・転倒しそうになる場合は、自己判断せず早めに医療機関を受診してください。本サイトの情報は一般的な健康情報であり、診断ではありません。']],
            ['@type' => 'Question', 'name' => '70歳は1日何歩歩くべきですか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '厚生労働省「健康日本21（第三次）」では、65歳以上の歩数目標を1日約6,000歩としています。70歳の方も6,000歩前後が一つの目安ですが、無理は禁物です。これまで歩いていなかった方は5,000歩程度からでも十分に健康効果が期待できます。体調や持病に合わせ、医師と相談しながら少しずつ増やしましょう。']],
            ['@type' => 'Question', 'name' => '75歳は1日何歩歩くべきですか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '75歳の方も、65歳以上の目安である1日約6,000歩を基準に、ご自身の体力に合わせて調整するとよいでしょう。大切なのは歩数そのものより継続です。5,000歩前後でも、座りっぱなしを減らしてこまめに歩くだけで、フレイル（虚弱）予防に役立ちます。持病のある方は事前に医師へご相談ください。']],
            ['@type' => 'Question', 'name' => '「歩く国際協力 Walk in Her Shoes」とは？2024年はどんな活動でしたか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Walk in Her Shoes（ウォーク・イン・ハー・シューズ）は、国際協力NGOのCARE（ケア・インターナショナル ジャパン）が主催する「歩く国際協力」キャンペーンです。途上国で毎日水くみなどに長い距離を歩く女性や女の子の現実に思いをはせながら、参加者が歩いた歩数を寄付につなげます。2024年は3月8日〜5月31日に開催され、多くの参加者の歩数が支援に役立てられました。']],
            ['@type' => 'Question', 'name' => '赤ちゃんはいつから歩くようになりますか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '個人差が大きいですが、多くの赤ちゃんは1歳前後（おおむね生後11〜15か月ごろ）に最初の一歩を踏み出します。早い・遅いは発達のリズムによるもので、1歳半を過ぎても歩かないなど気になる場合は、かかりつけの小児科や乳幼児健診で相談すると安心です。']],
            ['@type' => 'Question', 'name' => '高齢者はどれくらいの時間歩けばよいですか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '目安は1日合計20〜30分程度のウォーキングです。一度にまとめてでも、10分×2〜3回に分けても効果は期待できます。「少し汗ばむ・会話できる程度」の速さが目安です。体力に不安がある方は5〜10分から始め、徐々に時間を延ばしましょう。持病のある方は医師にご相談ください。']],
            ['@type' => 'Question', 'name' => '「歩く」と「ウォーキング」の違いは何ですか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '「歩く」は移動や日常動作を含めた歩行全般を指すのに対し、「ウォーキング」は健康・運動を目的に、姿勢や歩幅・ペースを意識して行う歩行を指すのが一般的です。同じ歩く動作でも、背すじを伸ばし腕を振って少し速めに歩くと、運動効果が高まります。']],
            ['@type' => 'Question', 'name' => 'フルマラソンの42.195kmを歩くと何時間かかりますか？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '歩く速さにもよりますが、時速5km（ふつうの速さ）なら約8時間半、時速4km（ゆっくり）なら約10時間半が目安です。早歩き（時速6km前後）なら約7時間。実際は休憩や信号待ちが加わるため、これより長めに見ておくとよいでしょう。']],
            ['@type' => 'Question', 'name' => '「歩く哲学」とは何ですか？簡単に教えてください。', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '「歩く哲学」とは、歩きながら考えることで思索が深まるという、古くからの考え方を指します。古代ギリシャのアリストテレスは歩きながら弟子に教え（逍遥学派）、カントやニーチェ、ルソーといった哲学者も日々の散歩を思考の時間にしていました。歩くと血流が促されて頭がすっきりし、新しい発想が生まれやすくなるといわれます。']],
            ['@type' => 'Question', 'name' => '歩くのが遅い・握力がないのはサルコペニアですか？やり方（対策）は？', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '歩く速度の低下や握力の低下は、加齢で筋肉量が減る「サルコペニア」のサインの一つです。目安として、握力が男性28kg・女性18kg未満、歩く速さが秒速1.0m（横断歩道を青信号で渡り切れない程度）未満だと注意が必要とされます。対策のやり方は、スクワットなどの筋トレ＋たんぱく質をしっかりとる食事＋ウォーキングの習慣化です。気になる場合は医療機関でご相談ください。本サイトの情報は一般的な健康情報であり、診断ではありません。']],
        ],
    ]];
    $head = head_html($prefix, $title, $desc, $s['url'] . '/faq.html', '', $faqLd, 'website', 'index, follow');
    $footer = footer_html($prefix);
    $crumb = breadcrumb_nav($prefix, 'よくある質問（FAQ）', true);
    $body = <<<HTML
{$crumb}
<section class="about-section about-faq reveal" id="faq">
  <div class="about-inner">
    <h1 class="about-title">歩く・健康のよくある質問（FAQ）</h1>
    <p class="about-lead">歩くことと健康、そして「あるく」の使い方について、よくいただくご質問をまとめました。ウォーキングの健康効果や1日の目標歩数など、歩く健康習慣のギモン解消にお役立てください。</p>
    <details class="column-faq-item"><summary>歩くと健康にどんな効果がありますか？</summary><div class="column-faq-a">ウォーキングには、脂肪燃焼によるダイエット効果、血圧・血糖値の改善、心肺機能の向上、骨や筋力の維持、ストレス軽減や睡眠の質アップなど、心と体の幅広い健康効果が期待できます。特別な道具がいらず、誰でも今日から始められるのが最大の魅力です。</div></details>
    <details class="column-faq-item"><summary>1日何歩あるけば健康にいいですか？</summary><div class="column-faq-a">一般的な目安は1日8,000歩・週合計150分の活動です。1万歩を目標にする方も多いですが、もともと歩数が少ない人ほど、少し増やすだけで健康効果が大きいとされています。まずは今より1,000歩多く歩くことから始めてみましょう。歩数ごとの目安は<a href="calorie-table.html">歩数別カロリー早見表</a>で確認できます。</div></details>
    <details class="column-faq-item"><summary>毎日歩かないと健康効果はありませんか？</summary><div class="column-faq-a">毎日でなくても、週の合計時間が確保できれば効果は期待できます。10分×3回のこま切れウォーキングでも、まとめて30分歩くのとほぼ同じ効果があるとされています。続けやすい形で習慣にすることが一番大切です。</div></details>
    <details class="column-faq-item"><summary>運動が苦手でも、歩くだけで健康になれますか？</summary><div class="column-faq-a">はい。ウォーキングは強度を自分で調整できる有酸素運動で、運動が苦手な方にもおすすめです。「少し息が弾む」程度の早歩きを取り入れると、無理なく健康効果を高められます。</div></details>
    <details class="column-faq-item"><summary>朝と夜、健康のために歩くならどちらがいいですか？</summary><div class="column-faq-a">どちらにも利点があります。朝の歩行は生活リズムが整い目覚めが良くなり、夜（食後30〜60分）の歩行は血糖値対策に向いています。大切なのは時間帯よりも継続です。自分の生活に合うタイミングを選びましょう。</div></details>
    <details class="column-faq-item"><summary>高齢者や運動不足の人が歩いても大丈夫ですか？</summary><div class="column-faq-a">ウォーキングは年齢を問わず取り組みやすい運動ですが、持病のある方・治療中の方・長く運動していなかった方は、始める前に医師にご相談ください。本サイトの情報は一般的な健康情報であり、医療行為・診断ではありません。詳しくは<a href="editorial-policy.html">編集・監修ポリシー</a>をご覧ください。</div></details>
    <details class="column-faq-item"><summary>あるくは無料で使えますか？</summary><div class="column-faq-a">はい。コラムの閲覧も、歩数別カロリー早見表・計算ツールも無料でご利用いただけます。会員登録も無料です。</div></details>
    <details class="column-faq-item"><summary>会員登録すると何ができますか？</summary><div class="column-faq-a">体重・運動の記録から消費カロリーを自動で計算・累計できます。さらにコラムの投稿、いいね、コメント、保存（ブックマーク）などのコミュニティ機能もご利用いただけます。</div></details>
    <details class="column-faq-item"><summary>コラムは誰が書いていますか？</summary><div class="column-faq-a">編集部のほか、会員のみなさんも投稿しています。投稿されたコラムは、公開前に内容を確認しています。</div></details>
    <details class="column-faq-item"><summary>スマートフォンでも使えますか？</summary><div class="column-faq-a">はい。スマホ・タブレット・PCのどの画面にも対応しています。通勤や外出先でも気軽にご覧いただけます。</div></details>
    <details class="column-faq-item"><summary>表示される消費カロリーは正確ですか？</summary><div class="column-faq-a">表示される数値は、歩数や体重などから算出した目安です（消費kcal ≒ 歩数 × 体重 × 0.0005）。体質や歩き方で前後するため、参考値としてご活用ください。</div></details>
    <details class="column-faq-item"><summary>退会したいときはどうすればいいですか？</summary><div class="column-faq-a">ログイン後の<a href="member/mypage.php">マイページ</a>下部にある「解約手続き」ボタンからお手続きいただけます。解約すると登録情報・記録はすべて削除され、元に戻せませんのでご注意ください。</div></details>

    <h2 class="about-subtitle" style="margin-top:48px;">歩くにまつわる素朴な疑問Q&amp;A</h2>
    <p class="about-lead">「歩くとふらつく原因は？」「高齢者は1日何歩？」「歩くとウォーキングの違いは？」など、歩くことにまつわるよくある疑問をまとめました。</p>
    <details class="column-faq-item"><summary>歩くとふらつくのは何が原因ですか？</summary><div class="column-faq-a">歩くときのふらつきは、加齢による足腰の筋力低下やバランス感覚の衰え、めまい（内耳の異常）、起立性低血圧、貧血、脱水、薬の副作用、神経の病気など、さまざまな原因が考えられます。一時的でなく繰り返す・転倒しそうになる場合は、自己判断せず早めに医療機関を受診してください。原因と受診の目安は<a href="column/walking-dizzy-causes.html">歩くとふらつく原因の記事</a>でくわしく解説しています。本サイトの情報は一般的な健康情報であり、診断ではありません。</div></details>
    <details class="column-faq-item"><summary>70歳は1日何歩歩くべきですか？</summary><div class="column-faq-a">厚生労働省「健康日本21（第三次）」では、65歳以上の歩数目標を1日約6,000歩としています。70歳の方も6,000歩前後が一つの目安ですが、無理は禁物です。これまで歩いていなかった方は5,000歩程度からでも十分に健康効果が期待できます。体調や持病に合わせ、医師と相談しながら少しずつ増やしましょう。歩数ごとの消費カロリーは<a href="calorie-table.html">歩数別カロリー早見表</a>で確認できます。</div></details>
    <details class="column-faq-item"><summary>75歳は1日何歩歩くべきですか？</summary><div class="column-faq-a">75歳の方も、65歳以上の目安である1日約6,000歩を基準に、ご自身の体力に合わせて調整するとよいでしょう。大切なのは歩数そのものより継続です。5,000歩前後でも、座りっぱなしを減らしてこまめに歩くだけで、フレイル（虚弱）予防に役立ちます。持病のある方は事前に医師へご相談ください。</div></details>
    <details class="column-faq-item"><summary>「歩く国際協力 Walk in Her Shoes」とは？2024年はどんな活動でしたか？</summary><div class="column-faq-a">Walk in Her Shoes（ウォーク・イン・ハー・シューズ）は、国際協力NGOのCARE（ケア・インターナショナル ジャパン）が主催する「歩く国際協力」キャンペーンです。途上国で毎日水くみなどに長い距離を歩く女性や女の子の現実に思いをはせながら、参加者が歩いた歩数を寄付につなげます。2024年は3月8日〜5月31日に開催され、多くの参加者の歩数が支援に役立てられました。</div></details>
    <details class="column-faq-item"><summary>赤ちゃんはいつから歩くようになりますか？</summary><div class="column-faq-a">個人差が大きいですが、多くの赤ちゃんは1歳前後（おおむね生後11〜15か月ごろ）に最初の一歩を踏み出します。早い・遅いは発達のリズムによるもので、1歳半を過ぎても歩かないなど気になる場合は、かかりつけの小児科や乳幼児健診で相談すると安心です。</div></details>
    <details class="column-faq-item"><summary>高齢者はどれくらいの時間歩けばよいですか？</summary><div class="column-faq-a">目安は1日合計20〜30分程度のウォーキングです。一度にまとめてでも、10分×2〜3回に分けても効果は期待できます。「少し汗ばむ・会話できる程度」の速さが目安です。体力に不安がある方は5〜10分から始め、徐々に時間を延ばしましょう。持病のある方は医師にご相談ください。</div></details>
    <details class="column-faq-item"><summary>「歩く」と「ウォーキング」の違いは何ですか？</summary><div class="column-faq-a">「歩く」は移動や日常動作を含めた歩行全般を指すのに対し、「ウォーキング」は健康・運動を目的に、姿勢や歩幅・ペースを意識して行う歩行を指すのが一般的です。同じ歩く動作でも、背すじを伸ばし腕を振って少し速めに歩くと、運動効果が高まります。</div></details>
    <details class="column-faq-item"><summary>フルマラソンの42.195kmを歩くと何時間かかりますか？</summary><div class="column-faq-a">歩く速さにもよりますが、時速5km（ふつうの速さ）なら約8時間半、時速4km（ゆっくり）なら約10時間半が目安です。早歩き（時速6km前後）なら約7時間。実際は休憩や信号待ちが加わるため、これより長めに見ておくとよいでしょう。歩く時間と消費カロリーの目安は<a href="calorie-table.html">歩数別カロリー早見表</a>も参考にしてください。</div></details>
    <details class="column-faq-item"><summary>「歩く哲学」とは何ですか？簡単に教えてください。</summary><div class="column-faq-a">「歩く哲学」とは、歩きながら考えることで思索が深まるという、古くからの考え方を指します。古代ギリシャのアリストテレスは歩きながら弟子に教え（逍遥学派）、カントやニーチェ、ルソーといった哲学者も日々の散歩を思考の時間にしていました。歩くと血流が促されて頭がすっきりし、新しい発想が生まれやすくなるといわれます。哲学や赤ちゃんの初歩、フルマラソンを歩く話などは<a href="column/walking-fun-facts.html">歩くのがちょっと楽しくなる小話</a>でやさしくご紹介しています。</div></details>
    <details class="column-faq-item"><summary>歩くのが遅い・握力がないのはサルコペニアですか？やり方（対策）は？</summary><div class="column-faq-a">歩く速度の低下や握力の低下は、加齢で筋肉量が減る「サルコペニア」のサインの一つです。目安として、握力が男性28kg・女性18kg未満、歩く速さが秒速1.0m（横断歩道を青信号で渡り切れない程度）未満だと注意が必要とされます。対策のやり方は、スクワットなどの筋トレ＋たんぱく質をしっかりとる食事＋ウォーキングの習慣化です。セルフチェックと予防の歩き方は<a href="column/sarcopenia-walking.html">サルコペニアと歩く対策の記事</a>でくわしく解説しています。気になる場合は医療機関でご相談ください。本サイトの情報は一般的な健康情報であり、診断ではありません。</div></details>
    <div class="about-actions"><a class="lp-btn lp-btn-secondary" href="about-aruku.html">あるくとは？→</a><a class="lp-btn lp-btn-primary" href="index.html">トップへ戻る</a></div>
  </div>
</section>
{$footer}
<script src="assets/app.js?v=20260612f" defer></script>
</body>
</html>
HTML;
    return $head . $body;
}

// ============================================================
// トップページ（LP）
// ============================================================
function render_top(): string
{
    $d = aruku_data();
    $s = site();
    $prefix = '';
    $url = $s['url'] . '/';
    $title = 'あるく｜歩くことを、もっと楽しく健康に';
    $desc = $s['description'];

    // 5本柱カード
    $pillars = '';
    foreach ($d['order'] as $c) {
        $cat = $d['cats'][$c];
        $pillars .= '<a href="category/' . $c . '.html" class="pillar-card">'
            . '<div class="pillar-icon">' . $cat['emoji'] . '</div>'
            . '<h3>' . $cat['name'] . '</h3>'
            . '<p>' . $cat['desc'] . '</p>'
            . '<span class="pillar-more">記事を読む →</span></a>';
    }

    // 注目記事（各カテゴリの先頭＝ハブ記事）
    $fcards = '';
    $i = 0;
    foreach ($d['order'] as $c) {
        if (empty($d['cat_lists'][$c])) {
            continue;
        }
        $a = $d['cat_lists'][$c][0];
        $cat = $d['cats'][$a['cat']];
        $sub = $a['subtitle'] ?? '';
        $fcards .= '<a href="column/' . $a['slug'] . '.html" class="column-card">'
            . '<div class="column-thumb">' . thumb_svg($a, 'feat' . $i) . '</div>'
            . '<div class="column-card-body">'
            . '<span class="column-card-num">' . $cat['emoji'] . ' ' . $cat['name'] . '</span>'
            . '<h3>' . $a['title'] . '</h3>'
            . '<p>' . $sub . '</p>'
            . '</div></a>';
        $i++;
    }

    // エンティティグラフ（@id で WebSite ⇄ Organization ⇄ Person を相互参照）。
    // 検索エンジン・生成AIにサイト主体と専門領域（knowsAbout）を明確に伝える＝AIO/LLMO 強化。
    $orgId    = $url . '#organization';
    $siteId   = $url . '#website';
    $authorId = $url . 'about.html#author';
    $jsonld = [
        [
            '@context'    => 'https://schema.org',
            '@type'       => 'WebSite',
            '@id'         => $siteId,
            'name'        => 'あるく',
            'alternateName' => 'aruku',
            'url'         => $url,
            'description' => $desc,
            'inLanguage'  => 'ja',
            'publisher'   => ['@id' => $orgId],
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => $url . 'search.html?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            '@id'      => $orgId,
            'name'     => 'あるく',
            'alternateName' => '歩くことの総合メディア あるく',
            'url'      => $url,
            'logo'     => ['@type' => 'ImageObject', 'url' => $url . 'assets/ogp.png', 'width' => 1200, 'height' => 630],
            'image'    => $url . 'assets/ogp.png',
            'description' => $s['description'],
            'slogan'   => $s['tagline'],
            'foundingDate' => '2026',
            'areaServed'   => 'JP',
            // 専門領域（トピック権威性）— 生成AIが「歩く・健康」分野の情報源として参照しやすくする
            'knowsAbout' => [
                'ウォーキング', '歩数', '消費カロリー', '正しい歩き方', 'ウォーキングダイエット',
                '歩いてポイ活', 'ウォーキングマシン', '有酸素運動', '歩数計', '健康習慣',
            ],
            'sameAs'   => array_values(array_filter([$s['x_url'], $s['org_url']])),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'url' => $url . 'about.html',
                'availableLanguage' => ['Japanese'],
            ],
            'parentOrganization' => ['@type' => 'Organization', 'name' => $s['org'], 'url' => $s['org_url']],
        ],
        [
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            '@id'      => $authorId,
            'name'     => $s['author'],
            'jobTitle' => '代表取締役',
            'worksFor' => ['@id' => $orgId],
            'url'      => $url . 'about.html',
        ],
    ];

    $head = head_html($prefix, $title, $desc, $url, '歩く,ウォーキング,ポイ活,カロリー,健康', $jsonld, 'website');
    $footer = footer_html($prefix);
    $top = cms_load()['top'];

    // トップに差し込む「歩数別カロリー早見表」
    // calorie-table 記事の最初のセクション（表）を流用＝CMS編集に自動追従。
    $calorie_section = '';
    $ct = $d['by_slug']['calorie-table'] ?? null;
    if ($ct && !empty($ct['sections'][0]['body'])) {
        $ctable = $ct['sections'][0]['body'];
        $calorie_section = <<<HTML
    <div class="calorie-panel reveal">
      <div class="calorie-panel-top">
        <p class="calorie-formula">消費kcal <b>≒</b> 歩数 <b>×</b> 体重<small>kg</small> <b>×</b> 0.0005</p>
      </div>
      {$ctable}
    </div>
HTML;
    }

    $badge = $top['hero_badge'] !== '' ? '<span class="hero-badge hero-anim hero-anim-1">' . $top['hero_badge'] . '</span>' : '';
    $pillarsEyebrow = $top['pillars_eyebrow'] !== '' ? '<span class="section-eyebrow">' . $top['pillars_eyebrow'] . '</span>' : '';
    // 消費カロリー計算ツール（ジョギング等）の体重・時間プルダウン
    $wOpts = '';
    for ($kg = 40; $kg <= 150; $kg += 5) {
        $sel = $kg === 60 ? ' selected' : '';
        $wOpts .= '<option value="' . $kg . '"' . $sel . '>' . $kg . 'kg</option>';
    }
    $tOpts = '';
    foreach ([10, 20, 30, 40, 50, 60, 90, 120] as $m) {
        $sel = $m === 30 ? ' selected' : '';
        $tOpts .= '<option value="' . $m . '"' . $sel . '>' . $m . '分</option>';
    }
    // ３．コラム：note.com 風にカテゴリ別の横スクロール（レール）で表示
    require_once __DIR__ . '/inc/posts.php';
    $catEmoji = aruku_post_category_emoji();
    $catNavTop = aruku_category_nav('', '');
    // タイトル＋（moreHref があれば）「すべて見る」＋左右矢印付きの横スクロールレール
    $railOf = static function (string $titleHtml, string $moreHref, string $cardsHtml): string {
        $more = $moreHref !== '' ? '<a class="cat-rail-more" href="' . $moreHref . '">すべて見る</a>' : '';
        return '<div class="cat-rail-block"><div class="cat-rail-head"><h3 class="cat-rail-title">' . $titleHtml . '</h3>'
            . $more . '</div>'
            . '<div class="cat-rail-scroller">'
            . '<button class="rail-arrow prev is-hidden" type="button" aria-label="前へ">‹</button>'
            . '<div class="note-rail">' . $cardsHtml . '</div>'
            . '<button class="rail-arrow next" type="button" aria-label="次へ">›</button>'
            . '</div></div>';
    };
    $rails = '';
    $latest = posts_published(12, null, 'new', 0);
    if ($latest) {
        // 「新着」は全カテゴリ横断のため、廃止した「すべて」ページへはリンクしない
        $rails .= $railOf('🆕 新着のコラム', '', aruku_post_cards($latest, ''));
    }
    // 全カテゴリをレール表示。該当コラムが無いカテゴリは枠だけ出す
    foreach (aruku_post_categories() as $ckey => $clabel) {
        $cp = posts_published(12, $ckey);
        $titleHtml = '<a href="category/' . h($ckey) . '.html">' . ($catEmoji[$ckey] ?? '') . ' ' . h($clabel) . '</a>';
        $moreHref = 'category/' . h($ckey) . '.html';
        if ($cp) {
            $rails .= $railOf($titleHtml, $moreHref, aruku_post_cards($cp, ''));
        } else {
            $rails .= '<div class="cat-rail-block is-empty"><div class="cat-rail-head"><h3 class="cat-rail-title">' . $titleHtml
                . '</h3><a class="cat-rail-more" href="' . $moreHref . '">すべて見る</a></div>'
                . '<div class="cat-rail-empty">このカテゴリのコラムはまだありません。<a href="member/post.php">最初のコラムを書いてみませんか？ →</a></div></div>';
        }
    }
    $mainContent = $rails !== '' ? $rails
        : '<p class="rail-empty">まだコラムがありません。<a href="member/post.php">最初のコラムを書いてみませんか？ →</a></p>';
    $columnFeed = '<div class="column-layout col-layout--flush"><aside class="column-side">' . $catNavTop . '</aside>'
        . '<div class="column-main col-box">' . $mainContent . '</div></div>';

    $body = <<<HTML
<header class="hero">
  <div class="hero-inner">
    <div>
      {$badge}
      <h1 class="hero-anim hero-anim-2">{$top['hero_title_1']}<span class="hero-keep"><span class="accent">{$top['hero_accent']}</span>{$top['hero_title_2']}</span></h1>
      <p class="hero-lead hero-anim hero-anim-3">{$top['hero_lead']}</p>
      <p class="hero-free hero-anim hero-anim-4"><span>全機能無料で利用できます。</span></p>
    </div>
  </div>
</header>

<section class="info-nav-section">
  <div class="info-nav">
    <a class="info-nav-card" href="about-aruku.html"><b>あるくとは？</b></a>
    <a class="info-nav-card" href="faq.html"><b>よくある質問（FAQ）</b></a>
  </div>
</section>

<section class="section calorie-feature">
  <div class="section-inner">
    <div class="section-head section-head--left reveal">
      {$pillarsEyebrow}
      <h2>{$top['pillars_title']}</h2>
    </div>
    {$calorie_section}
    <div class="section-head section-head--left reveal" style="margin-top:56px;">
      <h2>２．歩数別・消費カロリー測定<br>（早歩き・ジョギング・ランニング）</h2>
    </div>
    <div class="calc-tool reveal">
      <div class="calc-grid">
        <label class="calc-field">
          <span>運動の種類</span>
          <select id="calc-activity">
            <option value="5.0|6.5">早歩き（時速約6.5km）</option>
            <option value="8.3|8" selected>ジョギング（時速約8km）</option>
            <option value="10.0|10">ランニング（時速約10km）</option>
          </select>
        </label>
        <label class="calc-field">
          <span>性別</span>
          <select id="calc-sex">
            <option value="1.0">男性</option>
            <option value="0.95">女性</option>
          </select>
        </label>
        <label class="calc-field">
          <span>体重</span>
          <select id="calc-weight">{$wOpts}</select>
        </label>
        <label class="calc-field">
          <span>運動時間</span>
          <select id="calc-time">{$tOpts}</select>
        </label>
      </div>
      <div class="calc-result">
        <span class="calc-result-label">推定消費カロリー</span>
        <span class="calc-result-value"><b id="calc-kcal">—</b> kcal</span>
        <span class="calc-result-sub" id="calc-distance"></span>
      </div>
      <p class="calc-note">※ 計算式：METs × 体重(kg) × 時間(h) × 1.05 ×（性別係数）。<br>※一般的な時速・METs（早歩き5.0／ジョギング8.3／ランニング10.0）を用いた目安です。<br>性別係数：男性1.00／女性0.95。</p>
    </div>
    <p class="calc-more"><a href="calorie-table.html" class="lp-btn lp-btn-secondary">歩数別カロリー早見表＆計算ツールの詳細を見る →</a></p>
    <script>
    (function(){
      var a=document.getElementById('calc-activity'),s=document.getElementById('calc-sex'),
          w=document.getElementById('calc-weight'),t=document.getElementById('calc-time'),
          out=document.getElementById('calc-kcal'),dist=document.getElementById('calc-distance');
      if(!a){return;}
      function calc(){
        var p=a.value.split('|'),met=parseFloat(p[0]),speed=parseFloat(p[1]);
        var sex=parseFloat(s.value),wt=parseFloat(w.value),min=parseFloat(t.value),h=min/60;
        var kcal=met*wt*h*1.05*sex;
        out.textContent=Math.round(kcal);
        dist.textContent='（走る距離の目安：約'+(speed*h).toFixed(1)+'km）';
      }
      [a,s,w,t].forEach(function(el){el.addEventListener('change',calc);});
      calc();
    })();
    </script>
    <div class="section-head section-head--left reveal" id="apps" style="margin-top:56px; scroll-margin-top:90px;">
      <h2>３．健康管理アプリ比較ランキング<br>（カロリー計算・体重管理）</h2>
    </div>
    <div class="reveal">
      <p class="app-rank-intro">「歩いて消費」とあわせて使いたい、<b>カロリー計算・体重管理アプリ</b>を、料金とサービス内容で比べてランキングにしました。あるくで歩数と消費カロリーを管理し、食事はアプリで記録——と組み合わせれば、ダイエットも健康づくりもぐっと続けやすくなります。</p>
      <div class="app-rank-wrap">
        <table class="app-rank">
          <thead>
            <tr><th>順位</th><th>アプリ名</th><th>特徴・主なサービス</th><th>料金（税込）</th><th>公式サイト</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="app-rank-no">1</span></td>
              <td><span class="app-rank-name">あすけん</span></td>
              <td class="app-rank-feat">AI栄養士が毎食アドバイス。栄養素を自動採点してくれる定番アプリ。栄養バランスごと整えたい人に。</td>
              <td class="app-rank-price">無料<small>プレミアム 月480円〜</small></td>
              <td><a class="app-rank-btn" href="https://www.asken.jp/" target="_blank" rel="noopener">公式ページ →</a></td>
            </tr>
            <tr>
              <td><span class="app-rank-no">2</span></td>
              <td><span class="app-rank-name">カロミル</span></td>
              <td class="app-rank-feat">食事を写真でAIが自動記録。PFC・血糖・血圧もまとめて管理。ラクに続けたい人にぴったり。</td>
              <td class="app-rank-price">無料<small>プレミアム 月480円〜</small></td>
              <td><a class="app-rank-btn" href="https://www.calomeal.com/" target="_blank" rel="noopener">公式ページ →</a></td>
            </tr>
            <tr>
              <td><span class="app-rank-no">3</span></td>
              <td><span class="app-rank-name">MyFitnessPal</span></td>
              <td class="app-rank-feat">世界最大級の食品データベースとバーコード読み取りが強み。海外食品も記録したい本格派に。</td>
              <td class="app-rank-price">無料<small>プレミアム 月約3,100円〜</small></td>
              <td><a class="app-rank-btn" href="https://www.myfitnesspal.com/ja" target="_blank" rel="noopener">公式ページ →</a></td>
            </tr>
            <tr>
              <td><span class="app-rank-no">4</span></td>
              <td><span class="app-rank-name">RecStyle（レックスタイル）</span></td>
              <td class="app-rank-feat">体重・体脂肪の変化をグラフで見える化。完全無料でシンプル。記録を習慣にしたい人に。</td>
              <td class="app-rank-price">完全無料<small>追加課金なし</small></td>
              <td><a class="app-rank-btn" href="https://apps.apple.com/jp/app/id709213946" target="_blank" rel="noopener">公式ページ →</a></td>
            </tr>
            <tr>
              <td><span class="app-rank-no">5</span></td>
              <td><span class="app-rank-name">シンプルダイエット</span></td>
              <td class="app-rank-feat">体重記録に特化した迷わず使える超シンプル設計。記録が続かなかった人の最後の1つに。</td>
              <td class="app-rank-price">無料<small>一部機能のみ課金</small></td>
              <td><a class="app-rank-btn" href="https://simpleweight.net/" target="_blank" rel="noopener">公式ページ →</a></td>
            </tr>
            <tr>
              <td><span class="app-rank-no">6</span></td>
              <td><span class="app-rank-name">FiNC（フィンク）</span></td>
              <td class="app-rank-feat">体重・食事・歩数・睡眠をまるごと管理。記録でポイントも貯まり、ごほうび感覚で続けられる。</td>
              <td class="app-rank-price">無料<small>FiNC Plus 月480円〜</small></td>
              <td><a class="app-rank-btn" href="https://finc.com/" target="_blank" rel="noopener">公式ページ →</a></td>
            </tr>
            <tr>
              <td><span class="app-rank-no">7</span></td>
              <td><span class="app-rank-name">ヘルスプラネット（タニタ）</span></td>
              <td class="app-rank-feat">タニタの体組成計と連携し、体重・体脂肪・筋肉量を自動でグラフ記録。数値で管理したい人に。</td>
              <td class="app-rank-price">完全無料<small>体組成計連携に対応</small></td>
              <td><a class="app-rank-btn" href="https://www.healthplanet.jp/" target="_blank" rel="noopener">公式ページ →</a></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="app-rank-note">※ ランキングは編集部の総合評価による目安です。※ 料金・サービス内容は2026年6月時点のもので、変更される場合があります。最新の情報は各公式サイトをご確認ください。</p>
    </div>
    <div class="section-head section-head--left reveal" id="columns" style="margin-top:56px; scroll-margin-top:90px;">
      <h2>４．コラム</h2>
    </div>
    {$columnFeed}
  </div>
</section>

{$footer}
<script src="assets/app.js?v=20260612f" defer></script>
</body>
</html>
HTML;
    return $head . $body;
}

// ============================================================
// 消費カロリー専用ハブページ — /calorie-table.html
//   「歩く カロリー / ウォーキング 消費カロリー / 1万歩 カロリー」の着地ページ。
//   早見表（CMS calorie-table を流用）＋計算ツール＋計算式＋FAQ＋構造化データ。
// ============================================================
function render_calorie(): string
{
    require_once __DIR__ . '/inc/member.php'; // h()
    $s = site();
    $prefix = '';
    $url = $s['url'] . '/calorie-table.html';
    $title = '歩く・ウォーキングの消費カロリー｜歩数別カロリー早見表＆計算ツール｜あるく';
    $desc = '歩く・ウォーキングの消費カロリーを歩数別・体重別の早見表と無料の計算ツールで確認。1,000歩〜2万歩、体重40〜90kgの目安、「1万歩＝約300kcal」の計算式、早歩き・ジョギング・ランニング別の消費kcalまで分かりやすく解説します。';

    $d = aruku_data();
    $ct = $d['by_slug']['calorie-table'] ?? null;

    // 早見表（CMS calorie-table の各セクションを流用＝管理画面の編集に自動追従）
    $tableBody = $ct['sections'][0]['body'] ?? '';
    $formulaBody = $ct['sections'][1]['body'] ?? '';
    $tipsBody = $ct['sections'][2]['body'] ?? '';

    $calorie_panel = '';
    if ($tableBody !== '') {
        $calorie_panel = <<<HTML
    <div class="calorie-panel reveal">
      <div class="calorie-panel-top">
        <p class="calorie-formula">消費kcal <b>≒</b> 歩数 <b>×</b> 体重<small>kg</small> <b>×</b> 0.0005</p>
      </div>
      {$tableBody}
    </div>
HTML;
    }

    // 消費カロリー計算ツール（体重・時間プルダウン）
    $wOpts = '';
    for ($kg = 40; $kg <= 150; $kg += 5) {
        $sel = $kg === 60 ? ' selected' : '';
        $wOpts .= '<option value="' . $kg . '"' . $sel . '>' . $kg . 'kg</option>';
    }
    $tOpts = '';
    foreach ([10, 20, 30, 40, 50, 60, 90, 120] as $m) {
        $sel = $m === 30 ? ' selected' : '';
        $tOpts .= '<option value="' . $m . '"' . $sel . '>' . $m . '分</option>';
    }

    // FAQ（CMS calorie-table の Q&A を流用）
    // FAQ（計10問）：環境非依存で必ず10問。健康・あるく・ウォーキングのSEOキーワードを織り込み。
    $faqs = [];
    $faqs[] = ['1万歩で本当に300kcal消費しますか？', '体重60kg・普通歩行の場合の目安です。体重が軽い人は少なく、重い人は多くなります。早歩きならさらに増えます。'];
    $faqs[] = ['1kg痩せるには何歩あるけば必要ですか？', '脂肪1kg＝約7,200kcal。体重60kgなら計算上は約24万歩ですが、実際は食事管理との組み合わせが現実的です。'];
    $faqs[] = ['歩数計とスマホ、どちらの数値が正確ですか？', 'どちらも誤差はありますが、傾向を把握する分には十分です。同じ機器で毎日測り、変化を見るのがおすすめです。'];
    $faqs[] = ['1日の消費カロリーは何kcalを目標にすればいいですか？', 'ウォーキングなど運動でプラスする消費は、まずは1日200〜300kcal（体重60kgでおよそ7,000〜10,000歩に相当）を目安にすると、無理なく続けやすいです。ダイエットが目的の場合は、消費を増やすこと以上に食事とのカロリー収支を整えるのが近道です。上の早見表・計算ツールで、自分の体重・歩数・運動時間での消費kcalを確認してみましょう。'];
    $faqs[] = ['ウォーキングで消費カロリーを増やすコツはありますか？', '①速度を上げる（早歩きで2〜4割アップ）②坂道・階段を使う（上り坂は平地の約1.5〜2倍）③大股＋腕振りで全身を使う、の3つが効果的です。同じ歩数でも歩き方しだいで消費カロリーは大きく変わります。'];
    $faqs[] = ['ダイエット目的なら、ウォーキングは何分くらい歩けばいいですか？', '1回20〜40分・週合計150分が目安です。10分×3回のこま切れでも、合計時間が確保できれば効果は期待できます。ただし体重を落とすには、消費を増やすより食事とのカロリー収支を整えるほうが結果につながりやすいです。'];
    $faqs[] = ['早歩きとふつうの歩行では、消費カロリーはどのくらい違いますか？', '早歩きは運動強度（METs）が上がるため、ふつうの歩行に比べて消費カロリーが約1.2〜1.4倍になります。上の計算ツールで「早歩き」を選ぶと、ふつうの歩行との違いを数字で確認できます。'];
    $faqs[] = ['「あるく」では消費カロリーをどうやって計算していますか？', '早見表は「消費kcal ≒ 歩数 × 体重kg × 0.0005」という簡易式、計算ツールは「METs × 体重 × 時間 × 1.05 ×（性別係数）」で算出しています。いずれも一般的な目安であり、体質・歩き方・環境により前後します。'];
    $faqs[] = ['食後にウォーキングをすると健康やダイエットに効果的ですか？', '食後30〜60分のウォーキングは、血糖値の急な上昇をやわらげ、脂肪対策にも役立つとされています。無理のない範囲で、食後の軽い早歩きを習慣にするのがおすすめです。'];
    $faqs[] = ['雨の日や室内でも、ウォーキングの健康効果や消費カロリーは得られますか？', 'はい。ウォーキングマシンや室内での足踏み・歩行でも、屋外と同様に脂肪燃焼や心肺機能の向上といった健康効果が期待できます。天候に左右されず続けられるのが室内ウォーキングの利点です。'];

    $faqHtml = '';
    $faqLd = [];
    if ($faqs) {
        $items = '';
        foreach ($faqs as $qa) {
            [$q, $a] = $qa;
            $items .= '<details class="column-faq-item"><summary>' . h($q) . '</summary><div class="column-faq-a">' . h($a) . '</div></details>';
            $faqLd[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
        }
        $faqHtml = '<section class="column-section" id="faq"><h2>歩く・ウォーキングと消費カロリーのよくある質問</h2><div class="column-faq">' . $items . '</div></section>';
    }

    // 構造化データ：計算ツール(WebApplication)＋FAQ＋パンくず
    $jsonld = [
        [
            '@context'             => 'https://schema.org',
            '@type'                => 'WebApplication',
            'name'                 => '消費カロリー計算ツール（ウォーキング）',
            'description'          => '歩く速度・性別・体重・運動時間から、ウォーキング／早歩き／ジョギング／ランニングの推定消費カロリーを計算する無料ツールです。',
            'url'                  => $url . '#calc',
            'applicationCategory'  => 'HealthApplication',
            'operatingSystem'      => 'All',
            'browserRequirements'  => 'Requires JavaScript',
            'inLanguage'           => 'ja',
            'isAccessibleForFree'  => true,
            'offers'               => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'JPY'],
            'publisher'            => ['@type' => 'Organization', 'name' => 'あるく', 'url' => $s['url'] . '/'],
        ],
        [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'あるく', 'item' => $s['url'] . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => '歩く・ウォーキングの消費カロリー'],
            ],
        ],
    ];
    if ($faqLd) {
        $jsonld[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqLd];
    }

    $ogImg = $s['url'] . '/assets/running-woman-calorie.jpg';
    $head = head_html($prefix, $title, $desc, $url, '歩く カロリー,ウォーキング 消費カロリー,1万歩 カロリー,歩数 カロリー,消費カロリー 計算', $jsonld, 'website', 'index, follow', $ogImg);
    $footer = footer_html($prefix);
    $crumb = breadcrumb_nav($prefix, '消費カロリー', true);

    // 計算式・コツのセクション（CMS本文があれば差し込む）
    $formulaSection = $formulaBody !== '' ? '<section class="column-section" id="formula"><h2>消費カロリーの計算式</h2>' . $formulaBody . '</section>' : '';
    $tipsSection = $tipsBody !== '' ? '<section class="column-section" id="tips"><h2>消費カロリーを効率よく増やすコツ</h2>' . $tipsBody . '</section>' : '';

    $body = <<<HTML
{$crumb}
<article class="column-article calorie-page">
  <header class="column-header">
    <span class="column-cat-badge">🔥 消費カロリー</span>
    <h1>歩く・ウォーキングの消費カロリー<br><small>歩数別カロリー早見表＆無料計算ツール</small></h1>
    <p class="column-lead">「結局、何歩あるけば何kcal消費できるの？」——体重別の<strong>歩数別カロリー早見表</strong>と、早歩き・ジョギング・ランニングにも対応した<strong>消費カロリー計算ツール</strong>で、ウォーキングの消費カロリーがひと目で分かります。すべて無料・登録不要です。</p>
  </header>

  <figure class="post-cover calorie-cover">
    <img src="assets/running-woman-calorie.jpg" width="1440" height="756" alt="野原を笑顔でランニングする女性。歩く・走ることで楽しく消費カロリーを増やせる" fetchpriority="high" decoding="async">
  </figure>

  <section class="section calorie-feature calorie-feature--page">
    <div class="section-inner">
      <div class="section-head section-head--left reveal">
        <span class="section-eyebrow">STEP 1</span>
        <h2>歩数別・消費カロリー早見表（ウォーキング）</h2>
      </div>
      {$calorie_panel}

      <div class="section-head section-head--left reveal" id="calc" style="margin-top:56px; scroll-margin-top:90px;">
        <span class="section-eyebrow">STEP 2</span>
        <h2>消費カロリー計算ツール<br>（早歩き・ジョギング・ランニング）</h2>
      </div>
      <div class="calc-tool reveal">
        <div class="calc-grid">
          <label class="calc-field">
            <span>運動の種類</span>
            <select id="calc-activity">
              <option value="3.5|4.0">ふつうの歩行（時速約4km）</option>
              <option value="5.0|6.5" selected>早歩き（時速約6.5km）</option>
              <option value="8.3|8">ジョギング（時速約8km）</option>
              <option value="10.0|10">ランニング（時速約10km）</option>
            </select>
          </label>
          <label class="calc-field">
            <span>性別</span>
            <select id="calc-sex">
              <option value="1.0">男性</option>
              <option value="0.95">女性</option>
            </select>
          </label>
          <label class="calc-field">
            <span>体重</span>
            <select id="calc-weight">{$wOpts}</select>
          </label>
          <label class="calc-field">
            <span>運動時間</span>
            <select id="calc-time">{$tOpts}</select>
          </label>
        </div>
        <div class="calc-result">
          <span class="calc-result-label">推定消費カロリー</span>
          <span class="calc-result-value"><b id="calc-kcal">—</b> kcal</span>
          <span class="calc-result-sub" id="calc-distance"></span>
        </div>
        <p class="calc-note">※ 計算式：METs × 体重(kg) × 時間(h) × 1.05 ×（性別係数）。<br>※一般的な時速・METs（歩行3.5／早歩き5.0／ジョギング8.3／ランニング10.0）を用いた目安です。<br>性別係数：男性1.00／女性0.95。</p>
      </div>
      <script>
      (function(){
        var a=document.getElementById('calc-activity'),s=document.getElementById('calc-sex'),
            w=document.getElementById('calc-weight'),t=document.getElementById('calc-time'),
            out=document.getElementById('calc-kcal'),dist=document.getElementById('calc-distance');
        if(!a){return;}
        function calc(){
          var p=a.value.split('|'),met=parseFloat(p[0]),speed=parseFloat(p[1]);
          var sex=parseFloat(s.value),wt=parseFloat(w.value),min=parseFloat(t.value),h=min/60;
          var kcal=met*wt*h*1.05*sex;
          out.textContent=Math.round(kcal);
          dist.textContent='（歩く・走る距離の目安：約'+(speed*h).toFixed(1)+'km）';
        }
        [a,s,w,t].forEach(function(el){el.addEventListener('change',calc);});
        calc();
      })();
      </script>
    </div>
  </section>

  {$formulaSection}

  {$tipsSection}

  {$faqHtml}

  <p class="column-cta-note">※ 本ページの数値は一般的な簡易式・METsに基づく<strong>目安</strong>であり、医療行為・診断ではありません。体質・歩き方・環境により前後します。</p>
</article>

{$footer}
<script src="assets/app.js?v=20260612f" defer></script>
</body>
</html>
HTML;
    return $head . $body;
}

// ============================================================
// 固定ページ（運営者情報 / プライバシーポリシー）
//   data/content.json の pages.<key> から描画。about には管理者ログイン導線。
// ============================================================
function render_page(string $key): ?string
{
    $content = cms_load();
    $page = $content['pages'][$key] ?? null;
    if (!$page) {
        return null;
    }
    $s = site();
    $prefix  = '';
    $url     = $s['url'] . '/' . $key . '.html';
    $title_pg = $page['title'] ?? '';
    $title   = $title_pg . '｜あるく';
    $desc    = $page['desc'] ?? $s['description'];
    $robots  = !empty($page['noindex']) ? 'noindex, follow' : '';

    $sections = '';
    foreach (($page['sections'] ?? []) as $sec) {
        $sections .= '<section class="column-section"><h2>' . ($sec['h2'] ?? '') . '</h2>'
            . ($sec['body'] ?? '') . '</section>';
    }

    // 運営者ページには管理者ログイン導線を常設
    $admin_cta = '';
    if ($key === 'about') {
        $admin_cta = '<section class="column-section column-conclusion">'
            . '<h2>サイト管理</h2>'
            . '<p>運営者向けの入口です。記事・カテゴリ・各ページ・トップの文言は管理画面から編集できます。</p>'
            . '<div class="column-cta"><a href="admin/" class="lp-btn lp-btn-primary">🔒 管理者ログイン</a></div>'
            . '</section>';
    }

    $head = head_html($prefix, $title, $desc, $url, '', null, 'website', $robots);
    $footer = footer_html($prefix);

    $body = <<<HTML
<article class="column-article">
  <nav class="column-breadcrumb" aria-label="パンくず">
    <a href="index.html">トップ</a> ／ <span>{$title_pg}</span>
  </nav>
  <h1>{$title_pg}</h1>
  {$sections}
  {$admin_cta}
</article>

{$footer}
<script src="assets/app.js?v=20260612f" defer></script>
</body>
</html>
HTML;
    return $head . $body;
}

// ============================================================
// サイトマップ
// ============================================================
function render_sitemap(): string
{
    $d = aruku_data();
    $s = site();
    $today = date('Y-m-d');
    $urls = [
        [$s['url'] . '/', $today, '1.0'],
        [$s['url'] . '/calorie-table.html', $today, '0.9'],
        [$s['url'] . '/about-aruku.html', $today, '0.6'],
        [$s['url'] . '/faq.html', $today, '0.5'],
        [$s['url'] . '/editorial-policy.html', $today, '0.4'],
        [$s['url'] . '/about.html', $today, '0.4'],
        [$s['url'] . '/privacy.html', $today, '0.3'],
    ];
    require_once __DIR__ . '/inc/posts.php';
    // カテゴリ一覧ページ
    foreach (array_keys(aruku_post_categories()) as $ck) {
        $urls[] = [$s['url'] . '/category/' . $ck . '.html', $today, '0.7'];
    }
    // 編集部コラム（CMS記事＝5本柱の評価記事）
    foreach ($d['articles'] as $a) {
        $lm = substr((string) ($a['date'] ?? ''), 0, 10);
        $urls[] = [$s['url'] . '/column/' . $a['slug'] . '.html', ($lm !== '' ? $lm : $today), '0.7'];
    }
    // 公開済みの会員投稿
    foreach (posts_published(1000) as $pp) {
        $lm = substr((string) (($pp['updated_at'] ?? '') ?: ($pp['published_at'] ?: $pp['created_at'])), 0, 10);
        $urls[] = [$s['url'] . '/posts/' . (int) $pp['id'], ($lm !== '' ? $lm : $today), '0.6'];
    }
    $items = [];
    foreach ($urls as [$loc, $lm, $p]) {
        $items[] = "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lm}</lastmod>\n    <priority>{$p}</priority>\n  </url>";
    }
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
        . implode("\n", $items) . "\n</urlset>\n";
}

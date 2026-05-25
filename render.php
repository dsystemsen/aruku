<?php
/**
 * aruku（アルク） 動的レンダラ（PHP版・build.py の移植）
 * ----------------------------------------------------------------
 * 記事データ（articles.php）を読み込み、各ページのHTMLを生成します。
 *   render_top()          … トップページ（LP）
 *   render_column_index() … コラム一覧（カテゴリ別ハブ）
 *   render_article($slug) … 各コラム記事（無ければ null を返す）
 *   render_sitemap()      … sitemap.xml
 *
 * 記事を追加するときは articles.php の $ARTICLES に1件足すだけ。
 * 関連記事・前後ナビ・サイトマップ・一覧は自動で追従します。
 */

// ============================================================
// サイト設定
// ============================================================
function site(): array
{
    static $s = [
        'url'         => 'https://aruku.dsystemsen.com',
        'brand'       => 'aruku',
        'brand_ja'    => 'アルク',
        'tagline'     => '歩くことを、もっと楽しく健康に。',
        'description' => 'ウォーキングの効果・正しい歩き方・歩数別カロリー・歩いてポイ活・ウォーキングマシンまで。歩くことのすべてが分かる健康情報メディア。',
        'author'      => '斎藤 雄義',
        'author_role' => '株式会社D-SYSTEMS-EN 代表取締役',
        'org'         => '株式会社D-SYSTEMS-EN',
        'org_url'     => 'https://www.dsystemsen.com/',
        'x_url'       => 'https://x.com/DsystemsEn',
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
    require __DIR__ . '/articles.php'; // $CATEGORIES, $CATEGORY_ORDER, $ARTICLES

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
    return <<<HTML
<nav class="lp-nav">
  <div class="lp-nav-inner">
    <a href="{$prefix}index.html" class="lp-brand"><img src="{$prefix}assets/logo.svg" alt="aruku（アルク）ロゴ"><span class="lp-brand-name">aruku</span></a>
    <div class="lp-nav-links">
      <a href="{$prefix}column/index.html">コラム</a>
      <a href="{$prefix}column/calorie-table.html" class="lp-nav-hide-sp">カロリー表</a>
      <a href="{$prefix}about.html" class="lp-nav-hide-sp">運営者</a>
      <a href="{$prefix}column/index.html" class="lp-nav-cta">記事を読む</a>
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
      <div class="lp-footer-brand"><img src="{$prefix}assets/logo.svg" alt="aruku ロゴ">aruku</div>
      <p class="lp-footer-tagline">{$s['tagline']}</p>
    </div>
    <nav class="lp-footer-links">
      <a href="{$prefix}index.html">トップ</a>
      <a href="{$prefix}column/index.html">コラム一覧</a>
      <a href="{$prefix}about.html">運営者情報</a>
      <a href="{$prefix}privacy.html">プライバシーポリシー</a>
      <a href="{$s['org_url']}" target="_blank" rel="noopener">🏢 運営会社</a>
      <a href="{$s['x_url']}" target="_blank" rel="noopener">𝕏 公式X</a>
    </nav>
  </div>
  <div class="lp-footer-copy">&copy; {$s['year']} {$s['org']}. All rights reserved.</div>
</footer>
HTML;
}

/**
 * @param array|null $jsonld  JSON-LD ブロックの配列（各要素が1つの構造化データ）
 */
function head_html(string $prefix, string $title, string $desc, string $canonical, string $keywords = '', ?array $jsonld = null, string $og_type = 'article'): string
{
    $s = site();
    $css = '<link rel="stylesheet" href="' . $prefix . 'assets/style.css">' . "\n"
        . '<link rel="stylesheet" href="' . $prefix . 'assets/column.css">';
    $kw = $keywords !== '' ? '<meta name="keywords" content="' . $keywords . '">' . "\n" : '';
    $ld = '';
    if ($jsonld) {
        foreach ($jsonld as $block) {
            $ld .= '<script type="application/ld+json">' . "\n"
                . json_encode($block, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                . "\n</script>\n";
        }
    }
    $nav = nav_html($prefix);
    $ogp = $s['url'] . '/assets/ogp.svg';
    return <<<HTML
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<meta name="description" content="{$desc}">
{$kw}<link rel="canonical" href="{$canonical}">
<meta property="og:type" content="{$og_type}">
<meta property="og:site_name" content="aruku（アルク）">
<meta property="og:title" content="{$title}">
<meta property="og:description" content="{$desc}">
<meta property="og:url" content="{$canonical}">
<meta property="og:image" content="{$ogp}">
<meta property="og:locale" content="ja_JP">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="{$prefix}assets/logo.svg">
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
        . '<div class="column-cards-grid">' . implode('', $cards) . '</div>'
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

    // JSON-LD
    $jsonld = [
        [
            '@context'    => 'https://schema.org',
            '@type'       => 'Article',
            'headline'    => $article['title'],
            'description' => $article['desc'],
            'image'       => $s['url'] . '/assets/ogp.svg',
            'author'      => [
                '@type'    => 'Person',
                'name'     => $s['author'],
                'jobTitle' => '代表取締役',
                'worksFor' => ['@type' => 'Organization', 'name' => $s['org'], 'url' => $s['org_url']],
                'url'      => $s['url'] . '/about.html',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => $s['org'],
                'logo'  => ['@type' => 'ImageObject', 'url' => $s['url'] . '/assets/logo.svg'],
            ],
            'datePublished'    => $article['date'],
            'dateModified'     => $article['date'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        ],
        [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'aruku', 'item' => $s['url'] . '/'],
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
    <a href="./" class="column-nav-back">コラム一覧</a>
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
<script src="../assets/app.js" defer></script>
</body>
</html>
HTML;

    return $head . $body;
}

// ============================================================
// コラム一覧ページ
// ============================================================
function render_column_index(): string
{
    $d = aruku_data();
    $s = site();
    $prefix = '../';
    $url = $s['url'] . '/column/';
    $title = 'コラム一覧｜aruku（アルク）';
    $desc = 'ウォーキングの効果・正しい歩き方・歩数別カロリー・歩いてポイ活・ウォーキングマシン。歩くことに関するすべてのコラムをカテゴリ別にまとめました。';

    // 全記事インデックス（開閉式）
    $all_links = '';
    foreach ($d['articles'] as $a) {
        $all_links .= '<a href="./' . $a['slug'] . '.html">' . $a['title'] . '</a>';
    }
    $toc_index = '<details class="column-toc-index"><summary>全記事インデックス（'
        . count($d['articles']) . '記事）</summary>'
        . '<div class="column-toc-index-list">' . $all_links . '</div></details>';

    $sections = '';
    foreach ($d['order'] as $cat_key) {
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
        $sections .= '<section class="column-cat-section" id="' . $cat_key . '">'
            . '<h2 class="column-cat-title"><span class="cat-emoji">' . $cat['emoji'] . '</span>' . $cat['name'] . '</h2>'
            . '<p class="column-cat-desc">' . $cat['desc'] . '</p>'
            . '<div class="column-cards-grid">' . $cards . '</div>'
            . '</section>';
    }

    $jsonld = [[
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        'name'        => 'aruku コラム一覧',
        'description' => $desc,
        'url'         => $url,
    ]];
    $head = head_html($prefix, $title, $desc, $url, 'ウォーキング コラム,歩く 健康 記事', $jsonld);
    $footer = footer_html($prefix);

    $body = <<<HTML
<div class="column-list-hero">
  <h1>aruku コラム</h1>
  <p>歩くことの効果から、正しい歩き方・カロリー・ポイ活・マシンまで。<br>気になるカテゴリから読み進めてください。</p>
</div>
<div class="column-article">
  {$toc_index}
  {$sections}
  <section class="column-section column-conclusion">
    <h2>まずはここから</h2>
    <p>「何歩でどれくらい消費するの？」が気になる方は、まず歩数別カロリー表をチェック。歩くことの全体像は効果・効能ガイドからどうぞ。</p>
    <div class="column-cta">
      <a href="./calorie-table.html" class="lp-btn lp-btn-primary">歩数別カロリー表</a>
      <a href="./walking-effects.html" class="lp-btn lp-btn-secondary">ウォーキングの効果・効能</a>
    </div>
  </section>
</div>

{$footer}
<script src="../assets/app.js" defer></script>
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
    $title = 'aruku（アルク）｜歩くことを、もっと楽しく健康に';
    $desc = $s['description'];

    // 5本柱カード
    $pillars = '';
    foreach ($d['order'] as $c) {
        $cat = $d['cats'][$c];
        $pillars .= '<a href="column/index.html#' . $c . '" class="pillar-card">'
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

    $jsonld = [[
        '@context'    => 'https://schema.org',
        '@type'       => 'WebSite',
        'name'        => 'aruku（アルク）',
        'url'         => $url,
        'description' => $desc,
        'publisher'   => ['@type' => 'Organization', 'name' => $s['org'], 'url' => $s['org_url']],
    ]];

    $head = head_html($prefix, $title, $desc, $url, '歩く,ウォーキング,ポイ活,カロリー,健康', $jsonld, 'website');
    $footer = footer_html($prefix);

    $body = <<<HTML
<header class="hero">
  <div class="hero-inner">
    <div>
      <span class="hero-badge">🚶 歩くことの総合メディア</span>
      <h1>歩くことを、<span class="accent">もっと楽しく</span>健康に。</h1>
      <p class="hero-lead">ウォーキングの効果から正しい歩き方、歩数別の消費カロリー、歩いてポイ活、ウォーキングマシンまで。「歩く」のすべてを、わかりやすいコラムでお届けします。</p>
      <div class="hero-actions">
        <a href="column/index.html" class="lp-btn lp-btn-primary">コラムを読む</a>
        <a href="column/calorie-table.html" class="lp-btn lp-btn-secondary">🔥 カロリー表を見る</a>
      </div>
    </div>
    <div class="hero-art"><img src="assets/ogp.svg" alt="aruku イメージ" width="600" height="315" loading="eager"></div>
  </div>
</header>

<section class="section">
  <div class="section-inner">
    <div class="section-head">
      <span class="section-eyebrow">5つのテーマ</span>
      <h2>歩くことを、5つの視点で深掘り</h2>
      <p>知りたいテーマから、専門コラムへ。</p>
    </div>
    <div class="pillar-grid">{$pillars}</div>
  </div>
</section>

<section class="section section-soft">
  <div class="section-inner">
    <div class="section-head">
      <span class="section-eyebrow">PICK UP</span>
      <h2>注目のコラム</h2>
      <p>まず読んでほしい、各テーマの基本ガイド。</p>
    </div>
    <div class="post-grid">{$fcards}</div>
    <div class="text-center mt-32"><a href="column/index.html" class="lp-btn lp-btn-secondary">すべての記事を見る →</a></div>
  </div>
</section>

<section class="cta-band">
  <h2>今日から、1日あと2,000歩。</h2>
  <p>小さな一歩の積み重ねが、心と体を変えていきます。まずは歩数とカロリーの関係から。</p>
  <a href="column/calorie-table.html" class="lp-btn lp-btn-primary">歩数別カロリー表を見る</a>
</section>

{$footer}
<script src="assets/app.js" defer></script>
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
        [$s['url'] . '/column/', $today, '0.9'],
        [$s['url'] . '/about.html', $today, '0.4'],
        [$s['url'] . '/privacy.html', $today, '0.3'],
    ];
    foreach ($d['articles'] as $a) {
        $prio = $d['meta'][$a['slug']]['num'] == 1 ? '0.8' : '0.7';
        $urls[] = [$s['url'] . '/column/' . $a['slug'] . '.html', $a['date'], $prio];
    }
    $items = [];
    foreach ($urls as [$loc, $lm, $p]) {
        $items[] = "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lm}</lastmod>\n    <priority>{$p}</priority>\n  </url>";
    }
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
        . implode("\n", $items) . "\n</urlset>\n";
}

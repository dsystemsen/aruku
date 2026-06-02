# -*- coding: utf-8 -*-
"""
あるく 静的サイトジェネレータ
========================================
articles.py の ARTICLES / CATEGORIES を読み込み、以下を生成します。

  index.html              … トップページ（LP）
  column/index.html       … コラム一覧（カテゴリ別ハブ）
  column/<slug>.html      … 各コラム記事
  sitemap.xml             … サイトマップ
  robots.txt              … クローラ向け設定

使い方:
  python build.py

新しい記事を増やすときは articles.py に追記して再実行するだけ。
関連記事・前後ナビ・サイトマップ・一覧は自動で追従します。
"""

import json
import os
from datetime import date

from articles import ARTICLES, CATEGORIES, CATEGORY_ORDER

# ============================================================
# サイト設定
# ============================================================
SITE = {
    "url": "https://aruku.dsystemsen.com",
    "brand": "aruku",
    "brand_ja": "アルク",
    "tagline": "歩くことを、もっと楽しく健康に。",
    "description": "ウォーキングの効果・正しい歩き方・歩数別カロリー・歩いてポイ活・ウォーキングマシンまで。歩くことのすべてが分かる健康情報メディア。",
    "author": "齋藤 雄吾",
    "author_role": "株式会社D-SYSTEMS-EN 代表取締役",
    "org": "株式会社D-SYSTEMS-EN",
    "org_url": "https://www.dsystemsen.com/",
    "x_url": "https://x.com/DsystemsEn",
    "year": 2026,
}

ROOT = os.path.dirname(os.path.abspath(__file__))
COLUMN_DIR = os.path.join(ROOT, "column")


# ============================================================
# 補助：カテゴリ内インデックス & ルックアップ
# ============================================================
def index_articles():
    """各記事に、所属カテゴリ内の連番・前後記事を付与した辞書を返す。"""
    by_slug = {a["slug"]: a for a in ARTICLES}
    cat_lists = {c: [] for c in CATEGORIES}
    for a in ARTICLES:
        cat_lists.setdefault(a["cat"], []).append(a)
    meta = {}
    for cat, lst in cat_lists.items():
        for i, a in enumerate(lst):
            meta[a["slug"]] = {
                "num": i + 1,
                "prev": lst[i - 1] if i > 0 else None,
                "next": lst[i + 1] if i < len(lst) - 1 else None,
            }
    return by_slug, cat_lists, meta


BY_SLUG, CAT_LISTS, META = index_articles()


# ============================================================
# 共通パーツ
# ============================================================
def thumb_svg(article, gid):
    """カテゴリ色のグラデーション＋大きな連番のサムネイルSVG。"""
    cat = CATEGORIES[article["cat"]]
    g0, g1 = cat["grad"]
    num = META[article["slug"]]["num"]
    return (
        f'<svg viewBox="0 0 600 240" preserveAspectRatio="xMidYMid slice">'
        f'<defs><linearGradient id="{gid}" x1="0" y1="0" x2="1" y2="1">'
        f'<stop offset="0" stop-color="{g0}"/><stop offset="1" stop-color="{g1}"/>'
        f"</linearGradient></defs>"
        f'<rect width="600" height="240" fill="url(#{gid})"/>'
        f'<text x="44" y="200" font-size="150" font-weight="900" fill="{cat["fg"]}" '
        f'opacity="0.22" font-family="-apple-system,sans-serif">{cat["emoji"]}</text>'
        f'<text x="560" y="170" font-size="130" font-weight="900" fill="{cat["fg"]}" '
        f'text-anchor="end" opacity="0.30" font-family="-apple-system,sans-serif">{num:02d}</text>'
        f"</svg>"
    )


def nav_html(prefix):
    return f"""<nav class="lp-nav">
  <div class="lp-nav-inner">
    <a href="{prefix}index.html" class="lp-brand"><img src="{prefix}assets/logo.svg" alt="あるくロゴ"><span class="lp-brand-name">あるく</span></a>
    <div class="lp-nav-links">
      <a href="{prefix}column/index.html">コラム</a>
      <a href="{prefix}column/calorie-table.html" class="lp-nav-hide-sp">カロリー表</a>
      <a href="{prefix}about.html" class="lp-nav-hide-sp">運営者</a>
      <a href="{prefix}column/index.html" class="lp-nav-cta">記事を読む</a>
    </div>
  </div>
</nav>"""


def footer_html(prefix):
    return f"""<footer class="lp-footer">
  <div class="lp-footer-inner">
    <div>
      <div class="lp-footer-brand"><img src="{prefix}assets/logo.svg" alt="あるく ロゴ">あるく</div>
      <p class="lp-footer-tagline">{SITE['tagline']}</p>
    </div>
    <nav class="lp-footer-links">
      <a href="{prefix}index.html">トップ</a>
      <a href="{prefix}column/index.html">コラム一覧</a>
      <a href="{prefix}about.html">運営者情報</a>
      <a href="{prefix}privacy.html">プライバシーポリシー</a>
      <a href="{SITE['org_url']}" target="_blank" rel="noopener">🏢 運営会社</a>
      <a href="{SITE['x_url']}" target="_blank" rel="noopener">公式𝕏</a>
    </nav>
  </div>
  <div class="lp-footer-copy">&copy; {SITE['year']} {SITE['org']}. All rights reserved.</div>
</footer>"""


def head_html(prefix, title, desc, canonical, keywords="", jsonld=None, body_css="column"):
    css = (
        f'<link rel="stylesheet" href="{prefix}assets/style.css">\n'
        f'<link rel="stylesheet" href="{prefix}assets/column.css">'
    )
    kw = f'<meta name="keywords" content="{keywords}">\n' if keywords else ""
    ld = ""
    if jsonld:
        for block in jsonld:
            ld += (
                '<script type="application/ld+json">\n'
                + json.dumps(block, ensure_ascii=False, indent=2)
                + "\n</script>\n"
            )
    return f"""<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<meta name="description" content="{desc}">
{kw}<link rel="canonical" href="{canonical}">
<meta property="og:type" content="article">
<meta property="og:site_name" content="あるく">
<meta property="og:title" content="{title}">
<meta property="og:description" content="{desc}">
<meta property="og:url" content="{canonical}">
<meta property="og:image" content="{SITE['url']}/assets/ogp.svg">
<meta property="og:locale" content="ja_JP">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="{prefix}assets/logo.svg">
{css}
{ld}</head>
<body>
{nav_html(prefix)}
"""


# ============================================================
# 記事ページの生成
# ============================================================
def render_related(article):
    slugs = article.get("related") or []
    if not slugs:
        slugs = [a["slug"] for a in CAT_LISTS[article["cat"]] if a["slug"] != article["slug"]]
    cards = []
    for i, s in enumerate(slugs[:4]):
        a = BY_SLUG.get(s)
        if not a:
            continue
        num = META[a["slug"]]["num"]
        cat = CATEGORIES[a["cat"]]
        cards.append(
            f'<a href="./{a["slug"]}.html" class="column-card">'
            f'<div class="column-thumb">{thumb_svg(a, "rel" + str(i))}</div>'
            f'<div class="column-card-body">'
            f'<span class="column-card-num">{cat["name"]} #{num:02d}</span>'
            f'<h3>{a["title"]}</h3>'
            f'<p>{a.get("subtitle", "")}</p>'
            f"</div></a>"
        )
    if not cards:
        return ""
    return (
        '<section class="column-section column-related-block">'
        "<h2>関連記事</h2>"
        f'<div class="column-cards-grid">{"".join(cards)}</div>'
        "</section>"
    )


def render_affiliate(aff):
    if not aff:
        return ""
    return (
        '<div class="affiliate-box">'
        f'<span class="aff-label">{aff["label"]}</span>'
        f'<h4>{aff["title"]}</h4>'
        f'<p>{aff["desc"]}</p>'
        '<div class="affiliate-slot"><!-- ▼ ここにASP（A8.net/もしも/Amazon/楽天）の広告タグを貼り付け ▼ -->'
        f'<br><a href="#" class="aff-btn" rel="nofollow sponsored">{aff["cta"]}</a></div>'
        "</div>"
    )


def render_faq(faq):
    if not faq:
        return ""
    items = "".join(
        f'<details class="column-faq-item"><summary>{q}</summary>'
        f'<div class="column-faq-a">{a}</div></details>'
        for q, a in faq
    )
    return (
        '<section class="column-section column-faq-block" id="faq">'
        "<h2>よくある質問</h2>" + items + "</section>"
    )


def build_article(article):
    prefix = "../"
    cat = CATEGORIES[article["cat"]]
    url = f"{SITE['url']}/column/{article['slug']}.html"
    full_title = f"{article['title']}｜aruku"
    sub = f"<br><small>{article['subtitle']}</small>" if article.get("subtitle") else ""

    # 目次
    toc = "".join(
        f'<li><a href="#{s["id"]}">{s["h2"]}</a></li>' for s in article["sections"]
    )
    if article.get("faq"):
        toc += '<li><a href="#faq">よくある質問</a></li>'

    # 本文セクション
    sections = "".join(
        f'<section class="column-section" id="{s["id"]}"><h2>{s["h2"]}</h2>{s["body"]}</section>'
        for s in article["sections"]
    )

    # 前後ナビ
    m = META[article["slug"]]
    prev_a, next_a = m["prev"], m["next"]
    prev_html = (
        f'<a href="./{prev_a["slug"]}.html" class="column-nav-prev">← {prev_a["title"]}</a>'
        if prev_a else '<span class="disabled">← 最初の記事です</span>'
    )
    next_html = (
        f'<a href="./{next_a["slug"]}.html" class="column-nav-next">{next_a["title"]} →</a>'
        if next_a else '<span class="disabled">最新の記事です →</span>'
    )

    # JSON-LD
    jsonld = [
        {
            "@context": "https://schema.org",
            "@type": "Article",
            "headline": article["title"],
            "description": article["desc"],
            "image": f"{SITE['url']}/assets/ogp.svg",
            "author": {
                "@type": "Person",
                "name": SITE["author"],
                "jobTitle": "代表取締役",
                "worksFor": {"@type": "Organization", "name": SITE["org"], "url": SITE["org_url"]},
                "url": f"{SITE['url']}/about.html",
            },
            "publisher": {
                "@type": "Organization",
                "name": SITE["org"],
                "logo": {"@type": "ImageObject", "url": f"{SITE['url']}/assets/logo.svg"},
            },
            "datePublished": article["date"],
            "dateModified": article["date"],
            "mainEntityOfPage": {"@type": "WebPage", "@id": url},
        },
        {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "itemListElement": [
                {"@type": "ListItem", "position": 1, "name": "aruku", "item": f"{SITE['url']}/"},
                {"@type": "ListItem", "position": 2, "name": "コラム", "item": f"{SITE['url']}/column/"},
                {"@type": "ListItem", "position": 3, "name": article["title"]},
            ],
        },
    ]
    if article.get("faq"):
        jsonld.append(
            {
                "@context": "https://schema.org",
                "@type": "FAQPage",
                "mainEntity": [
                    {
                        "@type": "Question",
                        "name": q,
                        "acceptedAnswer": {"@type": "Answer", "text": a},
                    }
                    for q, a in article["faq"]
                ],
            }
        )

    head = head_html(prefix, full_title, article["desc"], url, article.get("keywords", ""), jsonld)

    body = f"""<article class="column-article">
  <header class="column-header">
    <nav class="column-breadcrumb" aria-label="パンくず">
      <a href="../index.html">トップ</a> ／ <a href="./">コラム</a> ／ <span>{article['title']}</span>
    </nav>
    <div class="column-thumb" aria-hidden="true">{thumb_svg(article, 'hero')}</div>
    <h1>{article['title']}{sub}</h1>
    <div class="column-meta">
      <span class="column-cat-tag">{cat['emoji']} {cat['name']}</span>
      <span>公開日: {article['date'].replace('-', '年', 1).replace('-', '月', 1)}日</span>
      <span>所要時間: 約{article['read']}分</span>
    </div>
    <p class="column-lead">{article['lead']}</p>
  </header>

  <nav class="column-toc" aria-label="目次">
    <h2>目次</h2>
    <ol>{toc}</ol>
  </nav>

  {sections}

  {render_affiliate(article.get('affiliate'))}

  <nav class="column-nav-prevnext">
    {prev_html}
    <a href="./" class="column-nav-back">コラム一覧</a>
    {next_html}
  </nav>

  <aside class="column-author" aria-label="執筆者プロフィール">
    <div class="column-author-name">執筆: <strong>{SITE['author']}</strong></div>
    <div class="column-author-role">{SITE['author_role']}</div>
    <p class="column-author-bio">健康・ITに関する情報を分かりやすく届けることを目指し、aruku を企画・運営しています。</p>
    <a href="../about.html" class="column-author-link">運営者情報を見る →</a>
  </aside>

  {render_faq(article.get('faq'))}

  {render_related(article)}

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

{footer_html(prefix)}
<script src="../assets/app.js" defer></script>
</body>
</html>"""

    return head + body


# ============================================================
# コラム一覧ページ
# ============================================================
def build_column_index():
    prefix = "../"
    url = f"{SITE['url']}/column/"
    title = "コラム一覧｜あるく"
    desc = "ウォーキングの効果・正しい歩き方・歩数別カロリー・歩いてポイ活・ウォーキングマシン。歩くことに関するすべてのコラムをカテゴリ別にまとめました。"

    # 全記事インデックス（開閉式）
    all_links = "".join(
        f'<a href="./{a["slug"]}.html">{a["title"]}</a>' for a in ARTICLES
    )
    toc_index = (
        '<details class="column-toc-index"><summary>全記事インデックス（'
        f'{len(ARTICLES)}記事）</summary>'
        f'<div class="column-toc-index-list">{all_links}</div></details>'
    )

    sections = []
    for cat_key in CATEGORY_ORDER:
        cat = CATEGORIES[cat_key]
        arts = CAT_LISTS.get(cat_key, [])
        if not arts:
            continue
        cards = []
        for i, a in enumerate(arts):
            num = META[a["slug"]]["num"]
            cards.append(
                f'<a href="./{a["slug"]}.html" class="column-card">'
                f'<div class="column-thumb">{thumb_svg(a, cat_key + str(i))}</div>'
                f'<div class="column-card-body">'
                f'<span class="column-card-num">#{num:02d}</span>'
                f'<h3>{a["title"]}</h3>'
                f'<p>{a.get("subtitle", "")}</p>'
                f"</div></a>"
            )
        sections.append(
            f'<section class="column-cat-section" id="{cat_key}">'
            f'<h2 class="column-cat-title"><span class="cat-emoji">{cat["emoji"]}</span>{cat["name"]}</h2>'
            f'<p class="column-cat-desc">{cat["desc"]}</p>'
            f'<div class="column-cards-grid">{"".join(cards)}</div>'
            "</section>"
        )

    jsonld = [{
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        "name": "あるく コラム一覧",
        "description": desc,
        "url": url,
    }]
    head = head_html(prefix, title, desc, url, "ウォーキング コラム,歩く 健康 記事", jsonld)

    body = f"""<div class="column-list-hero">
  <h1>あるく コラム</h1>
  <p>歩くことの効果から、正しい歩き方・カロリー・ポイ活・マシンまで。<br>気になるカテゴリから読み進めてください。</p>
</div>
<div class="column-article">
  {toc_index}
  {''.join(sections)}
  <section class="column-section column-conclusion">
    <h2>まずはここから</h2>
    <p>「何歩でどれくらい消費するの？」が気になる方は、まず歩数別カロリー表をチェック。歩くことの全体像は効果・効能ガイドからどうぞ。</p>
    <div class="column-cta">
      <a href="./calorie-table.html" class="lp-btn lp-btn-primary">歩数別カロリー表</a>
      <a href="./walking-effects.html" class="lp-btn lp-btn-secondary">ウォーキングの効果・効能</a>
    </div>
  </section>
</div>

{footer_html(prefix)}
<script src="../assets/app.js" defer></script>
</body>
</html>"""
    return head + body


# ============================================================
# トップページ（LP）
# ============================================================
def build_top():
    prefix = ""
    url = f"{SITE['url']}/"
    title = "あるく｜歩くことを、もっと楽しく健康に"
    desc = SITE["description"]

    # 5本柱カード
    pillars = "".join(
        f'<a href="column/index.html#{c}" class="pillar-card">'
        f'<div class="pillar-icon">{CATEGORIES[c]["emoji"]}</div>'
        f'<h3>{CATEGORIES[c]["name"]}</h3>'
        f'<p>{CATEGORIES[c]["desc"]}</p>'
        f'<span class="pillar-more">記事を読む →</span></a>'
        for c in CATEGORY_ORDER
    )

    # 注目記事（各カテゴリの先頭＝ハブ記事を最大6件）
    featured = [CAT_LISTS[c][0] for c in CATEGORY_ORDER if CAT_LISTS.get(c)]
    fcards = []
    for i, a in enumerate(featured):
        cat = CATEGORIES[a["cat"]]
        fcards.append(
            f'<a href="column/{a["slug"]}.html" class="column-card">'
            f'<div class="column-thumb">{thumb_svg(a, "feat" + str(i))}</div>'
            f'<div class="column-card-body">'
            f'<span class="column-card-num">{cat["emoji"]} {cat["name"]}</span>'
            f'<h3>{a["title"]}</h3>'
            f'<p>{a.get("subtitle", "")}</p>'
            f"</div></a>"
        )

    jsonld = [{
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "あるく",
        "url": url,
        "description": desc,
        "publisher": {"@type": "Organization", "name": SITE["org"], "url": SITE["org_url"]},
    }]

    # head（トップは og:type を website に）
    head = head_html(prefix, title, desc, url, "歩く,ウォーキング,ポイ活,カロリー,健康", jsonld)
    head = head.replace('<meta property="og:type" content="article">',
                        '<meta property="og:type" content="website">')

    body = f"""<header class="hero">
  <div class="hero-inner">
    <div>
      <span class="hero-badge">🚶 歩くことの総合メディア</span>
      <h1>歩くことを、<span class="accent">もっと楽しく</span>健康に。</h1>
      <p class="hero-lead">ウォーキングの効果から正しい歩き方、歩数別の消費カロリー、歩いてポイ活まで。「歩く」のすべてを、みんなでコラムをつくって、役立つ情報を共有。</p>
      <div class="hero-actions">
        <a href="column/index.html" class="lp-btn lp-btn-primary">コラムを読む</a>
        <a href="column/calorie-table.html" class="lp-btn lp-btn-secondary">🔥 カロリー表を見る</a>
      </div>
    </div>
    <div class="hero-art"><img src="assets/ogp.svg" alt="あるく イメージ" width="600" height="315" loading="eager"></div>
  </div>
</header>

<section class="section">
  <div class="section-inner">
    <div class="section-head">
      <span class="section-eyebrow">5つのテーマ</span>
      <h2>歩くことを、5つの視点で深掘り</h2>
      <p>知りたいテーマから、専門コラムへ。</p>
    </div>
    <div class="pillar-grid">{pillars}</div>
  </div>
</section>

<section class="section section-soft">
  <div class="section-inner">
    <div class="section-head">
      <span class="section-eyebrow">PICK UP</span>
      <h2>注目のコラム</h2>
      <p>まず読んでほしい、各テーマの基本ガイド。</p>
    </div>
    <div class="post-grid">{''.join(fcards)}</div>
    <div class="text-center mt-32"><a href="column/index.html" class="lp-btn lp-btn-secondary">すべての記事を見る →</a></div>
  </div>
</section>

<section class="cta-band">
  <h2>今日から、1日あと2,000歩。</h2>
  <p>小さな一歩の積み重ねが、心と体を変えていきます。まずは歩数とカロリーの関係から。</p>
  <a href="column/calorie-table.html" class="lp-btn lp-btn-primary">歩数別カロリー表を見る</a>
</section>

{footer_html(prefix)}
<script src="assets/app.js" defer></script>
</body>
</html>"""
    return head + body


# ============================================================
# サイトマップ・robots
# ============================================================
def build_sitemap():
    today = date.today().isoformat()
    urls = [
        (f"{SITE['url']}/", today, "1.0"),
        (f"{SITE['url']}/column/", today, "0.9"),
        (f"{SITE['url']}/about.html", today, "0.4"),
        (f"{SITE['url']}/privacy.html", today, "0.3"),
    ]
    for a in ARTICLES:
        prio = "0.8" if META[a["slug"]]["num"] == 1 else "0.7"
        urls.append((f"{SITE['url']}/column/{a['slug']}.html", a["date"], prio))
    items = "\n".join(
        f"  <url>\n    <loc>{loc}</loc>\n    <lastmod>{lm}</lastmod>\n"
        f"    <priority>{p}</priority>\n  </url>"
        for loc, lm, p in urls
    )
    return (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        f"{items}\n</urlset>\n"
    )


def build_robots():
    return (
        "# あるく robots.txt\n"
        "User-agent: *\n"
        "Allow: /\n\n"
        f"Sitemap: {SITE['url']}/sitemap.xml\n"
    )


# ============================================================
# 実行
# ============================================================
def write(path, content):
    with open(path, "w", encoding="utf-8") as f:
        f.write(content)
    rel = os.path.relpath(path, ROOT)
    print(f"  generated: {rel}")


def main():
    os.makedirs(COLUMN_DIR, exist_ok=True)
    print(f"Building aruku ({len(ARTICLES)} articles)...")

    write(os.path.join(ROOT, "index.html"), build_top())
    write(os.path.join(COLUMN_DIR, "index.html"), build_column_index())
    for a in ARTICLES:
        write(os.path.join(COLUMN_DIR, f"{a['slug']}.html"), build_article(a))
    write(os.path.join(ROOT, "sitemap.xml"), build_sitemap())
    write(os.path.join(ROOT, "robots.txt"), build_robots())

    print(f"Done. {len(ARTICLES) + 2} HTML pages + sitemap + robots.")


if __name__ == "__main__":
    main()

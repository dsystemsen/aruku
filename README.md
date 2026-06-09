# aruku（アルク）

> 歩くことを、もっと楽しく健康に。

ウォーキングの**効果・効能**／**歩数別カロリー**／**正しい歩き方**／**歩いてポイ活**／**ウォーキングマシン**を扱う、コラム形式のSEO健康情報メディア。

- 本番ドメイン: `https://aruku.ne.jp`
- 配信: **動的PHP**（Xserver / PHPで稼働）
- 運営: 株式会社D-SYSTEMS-EN

---

## 仕組み（動的PHPサイト）

100ページ超を**保守しやすく量産する**ため、記事は手書きせず「データ＋テンプレート」で管理します。
ビルド工程はありません。**データを編集してアップロードすれば即反映**されます。

```
articles.php   ← 記事データ（ここを編集する）
render.php     ← 共通テンプレート関数（HTMLを生成）
index.php      ← トップページ（/）
column/index.php   ← コラム一覧（/column/）
column/article.php ← 各記事（/column/<slug>.html を内部処理）
sitemap.php    ← サイトマップ（/sitemap.xml）
.htaccess      ← URLルーティング／HTTPS／キャッシュ等（Xserver用）
assets/        ← CSS・ロゴ・JS（共通デザイン）
   ├ style.css     ナビ/フッター/ボタン/トップLP
   ├ column.css    コラム記事・一覧
   ├ logo.svg / ogp.svg
   └ app.js
─────────────── 手書きの固定ページ ───────────────
about.html / privacy.html / 404.html
robots.txt
```

`render.php` が **目次・JSON-LD（Article/パンくず/FAQ）・関連記事・前後ナビ・サムネイル・サイトマップ・一覧ページ**をすべて自動生成します。

### URLについて

URL構造は従来の静的版と**完全に同一**です（SEO・被リンク・既存インデックスを維持）。

| URL | 実体 |
|---|---|
| `/` | `index.php` |
| `/column/` | `column/index.php` |
| `/column/<slug>.html` | `column/article.php?slug=<slug>`（`.htaccess` が内部転送）|
| `/sitemap.xml` | `sitemap.php` |

---

## ビルド方法

**不要です。** `articles.php` を編集してアップロードするだけで反映されます。

ローカルで表示確認したい場合は、PHPの組み込みサーバが使えます（任意）。

```powershell
php -S localhost:8000
```

> ※ 組み込みサーバは `.htaccess` を読まないため、ルーティング確認には簡易ルータが別途必要です。
> URL確認まで含めて検証したい場合は本番（Xserver）にアップして確認するのが確実です。

---

## 記事の追加方法（最重要）

1. `articles.php` の `$ARTICLES` 配列に連想配列を1件追加する。
2. ファイルをアップロードする。
3. これだけで一覧・関連記事・前後ナビ・サイトマップに自動反映されます。

### 記事データのテンプレート

```php
[
    'slug'     => 'kettoichi',                 // → /column/kettoichi.html
    'cat'      => 'koka',                       // koka/calorie/howto/poikatsu/machine
    'title'    => '血糖値・糖尿病予防に歩く',
    'subtitle' => '食後の15分ウォークが効く理由',   // 任意（h1の補足）
    'desc'     => '（120字程度のメタディスクリプション）',
    'keywords' => '血糖値 ウォーキング,食後 歩く',
    'date'     => '2026-05-26',
    'read'     => 5,                            // 所要時間（分）
    'lead'     => 'リード文。<strong>HTML可</strong>。',
    'sections' => [
        ['id' => 'sec-1', 'h2' => '見出し', 'body' => '<p>本文HTML…</p>'],
        ['id' => 'sec-2', 'h2' => '見出し', 'body' => '<ul><li>…</li></ul>'],
    ],
    'faq'      => [['質問？', '回答。'], ['質問？', '回答。']],  // 任意（FAQ構造化データになる）
    'related'  => ['walking-effects', 'naizou-shibou'],          // 任意（未指定なら同カテゴリから自動）
    'affiliate' => null,   // または下記の広告枠
],
```

### アフィリエイト枠を入れる場合

```php
'affiliate' => [
    'label' => 'PR・おすすめ商品',
    'title' => '見出し（例：人気のウォーキングシューズ）',
    'desc'  => '説明文',
    'cta'   => 'ボタン文言（例：商品を見る）',
],
```

→ 記事内に広告ボックスが出力されます。`<div class="affiliate-slot">` のコメント位置（`render.php` の `render_affiliate()`）に、
各ASP（A8.net / もしも / Amazon / 楽天）から発行した**広告タグを貼り付け**てください。
`assets/column.css` の `.affiliate-box` 周辺でデザイン調整できます。

### 本文で使える便利クラス
- `<p class="column-callout">💡 …</p>` … 強調ボックス（結論・ポイント）
- `<p class="column-note">※ …</p>` … 注意書き（黄色）
- `<table class="column-table">…</table>` … 表（`<div class="column-table-wrap">` で囲むと横スクロール対応）
- `<h3>…</h3>` … セクション内の小見出し

---

## デプロイ（Xserver）

ローカルでの**ビルドは不要**です。以下を `aruku.ne.jp` の公開ディレクトリへアップロードします。

- `index.php` / `render.php` / `articles.php` / `sitemap.php`
- `column/`（`index.php` と `article.php`）
- `about.html` / `privacy.html` / `404.html`
- `assets/`（フォルダごと）
- `robots.txt`
- `.htaccess`

手順:

1. 上記ファイルをFTP等でアップロード。
2. ブラウザで `https://aruku.ne.jp/` を確認。
3. `https://aruku.ne.jp/column/` と任意の記事 `…/column/walking-effects.html` を確認。
4. `https://aruku.ne.jp/sitemap.xml` が表示されることを確認し、Google Search Console に登録。

> PHPバージョンは 7.4 以上（推奨 8.x）。Xserver のサーバーパネルで確認できます。
> `.htaccess` の `<FilesMatch>` により `render.php` / `articles.php` への直接アクセスは禁止されます。

### 旧Python版について（参考）

`articles.py` / `build.py` は旧・静的サイトジェネレータ（Python）です。
**現在は使用しません**（Xserver で稼働させるため PHP 版に移行）。
`articles.php` は `articles.py` の内容をそのまま移植したものです（カロリー表・医療免責文は展開済み）。
不要であれば `articles.py` と `build.py` は削除して構いません（**アップロード対象外**）。

---

## 記事ロードマップ

100記事の全体計画は **[ARTICLE_PLAN.md](ARTICLE_PLAN.md)** を参照。
**全102記事を作成完了**（5本柱すべて目標達成）。総ページ数107（記事102＋トップ・コラム一覧・運営者・プライバシー・404）。

### 狙う主要SEOキーワード（すべて第1弾で着地）
| キーワード | 主な着地ページ |
|---|---|
| 歩く ポイ活 | `/column/poikatsu-matome.html` |
| ウォーキング 普通に 歩く | `/column/futsuu-to-no-chigai.html` |
| 歩く / ウォーキング | `/column/walking-effects.html` ほか |

---

## デザインテーマ
健康系グリーン（`--green-600: #16a34a` ／ アクセント `--teal-600: #0d9488`）。
配色は `assets/style.css` 冒頭の CSS 変数で一括変更できます。

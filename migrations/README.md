# 本番（MySQL）へのコンテンツ移行手順

ローカルの全コンテンツ（会員＝著者・全公開コラム・タグ・追加画像メタ）は `data/aruku.sqlite` にあり、**`data/` は本番へデプロイしない**ため、本番MySQLには別途このSQLで投入します。今回のSEO改修（各記事の `seo_desc` ＋ 導入文、早歩きの柱記事）もこのSQLに含まれています。

- 移行SQL: `migrations/aruku_content_YYYYMMDD.sql`（utf8mb4 / `INSERT … ON DUPLICATE KEY UPDATE` ＝**何度流しても安全**）
- 内容: 会員34・公開コラム223（`seo_desc` 込）・タグ・画像メタ
- 注意: **会員の bcrypt パスワードハッシュを含む**ため `.gitignore` 済み（gitに乗せない）。衝突時、会員は `nickname` のみ更新＝本番の既存パスワードは壊しません。

## 事前に本番へ反映が必要なコード（今回のSEO機能の動作に必須）
- `inc/db.php` … `posts.seo_desc` 列のマイグレーション
- `inc/posts.php` … `post_get_published` が `seo_desc` を取得／`aruku_md_inline` が内部相対リンク（`.html`/`.php`）に対応
- `posts/view.php` … meta description に `seo_desc` を優先使用

## 手順
1. **コードをデプロイ**（[[../DEPLOY.md]] / メモリ aruku-deploy の scp 手順。`data/` は送らない）。
2. **サイトへ1度アクセス**して `aruku_db_init()` にテーブル＋`seo_desc`列を自動作成させる
   （SQLを先に流す場合は、SQL冒頭の `ALTER TABLE posts ADD COLUMN seo_desc VARCHAR(180);` のコメントを外して先に実行）。
3. **SQLを取り込む**（いずれか）
   - SSH/CLI: `mysql -u <USER> -p <DBNAME> < migrations/aruku_content_YYYYMMDD.sql`
   - Xserver: サーバーパネル → phpMyAdmin →「インポート」で同SQLをアップロード
4. **画像を転送**（コラムのサムネは `uploads/` の実ファイル。223件）
   - `scp -P 10022 -i <key> uploads/*.jpg uploads/*.png xs186588@xs186588.xsrv.jp:<docroot>/uploads/`
   - 転送後に権限修正: `find uploads -type d -exec chmod 755 {} \; && find uploads -type f -exec chmod 644 {} \;`
5. **確認**: `/`（トップにコラム露出）・`/posts/301`（早歩きの柱記事）・任意記事の `<meta name="description">` に専用 `seo_desc` が出ること。

## 再生成（ローカルで内容を更新したとき）
ローカルSQLiteを正としてSQLを作り直す場合は、リポジトリ直下で生成スクリプトを再実行してください（本リポジトリには生成スクリプトは置かず、必要時に作成）。`ON DUPLICATE KEY UPDATE` なので、本番へは再度流すだけで差分が反映されます。

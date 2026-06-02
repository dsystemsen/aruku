# デプロイ手順（GitHub Actions → Xserver）

`main` ブランチに push すると、GitHub Actions が本番 `aruku.dsystemsen.com` へ
自動アップロードします（`.github/workflows/deploy.yml`）。
**初回だけ** 下記の GitHub Secrets 登録が必要です。

---

## 1. 必要な GitHub Secrets

リポジトリ → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**
で以下を登録します。

| Secret名 | 値 | 備考 |
|---|---|---|
| `XSERVER_HOST` | `xs186588.xsrv.jp` | Xサーバのホスト名（IP `183.181.81.59` でも可）|
| `XSERVER_USER` | `xs186588` | XサーバのサーバーID |
| `XSERVER_PORT` | `10022` | Xサーバの SSH ポート |
| `XSERVER_TARGET` | `/home/xs186588/dsystemsen.com/public_html/aruku.dsystemsen.com` | **配置先（要確認・下記2）** |
| `XSERVER_SSH_KEY` | OpenSSH 秘密鍵の全文 | 下記3 |
| `XSERVER_SSH_PASSPHRASE` | 鍵のパスフレーズ | 鍵にパスフレーズが**無ければ登録不要**（空でOK）|

---

## 2. 配置先（XSERVER_TARGET）の確認

`saguru.dsystemsen.com` が
`/home/xs186588/dsystemsen.com/public_html/saguru.dsystemsen.com/`
に置かれているのと同じ構成なら、aruku は次のはずです：

```
/home/xs186588/dsystemsen.com/public_html/aruku.dsystemsen.com
```

念のため、SSH接続して実際のディレクトリを確認してください（普段のPCの鍵で）:

```bash
ssh -p 10022 xs186588@xs186588.xsrv.jp "ls -la ~/dsystemsen.com/public_html/"
```

`aruku.dsystemsen.com` ディレクトリが見えれば、そのフルパスを `XSERVER_TARGET` に設定します。
（サブドメインのドキュメントルートが別構成の場合は、その実パスを設定）

---

## 3. SSH鍵の用意（2通り・推奨はA）

GitHub Actions は**パスフレーズ入力ができない**ため、原則 **パスフレーズ無しの専用デプロイ鍵** を使います。

### A. 専用デプロイ鍵を新規作成（推奨・安全に失効できる）

手元（普段のPC）で：

```bash
ssh-keygen -t ed25519 -f aruku_deploy -N "" -C "github-actions-aruku"
```

- 生成された **公開鍵 `aruku_deploy.pub`** を Xサーバに登録
  サーバーパネル → **SSH設定** → **公開鍵認証用鍵の登録**（既存の鍵に**追記**する形で登録。既存のSSH接続はそのまま使えます）
- 生成された **秘密鍵 `aruku_deploy`（全文）** を `XSERVER_SSH_KEY` に貼り付け
- `XSERVER_SSH_PASSPHRASE` は**登録不要**

> 秘密鍵の全文とは `-----BEGIN OPENSSH PRIVATE KEY-----` から
> `-----END OPENSSH PRIVATE KEY-----` までを丸ごと、です。

### B. 既存の鍵を流用（Xサーバ側の変更不要）

普段使っている **動作実績のある秘密鍵** を `XSERVER_SSH_KEY` に貼り付け、
その **パスフレーズ** を `XSERVER_SSH_PASSPHRASE` に登録します。
（既に公開鍵がサーバ登録済みなので、サーバ側の作業は不要）

---

## 4. 初回デプロイ

1. 上記 Secrets を登録。
2. Actions タブ → **Deploy to Xserver** → **Run workflow**（手動実行）。
   または、何かを変更して `git push` すれば自動で走ります。
3. ジョブが緑（成功）になったらブラウザで確認：
   - `https://aruku.dsystemsen.com/`
   - `https://aruku.dsystemsen.com/column/`
   - `https://aruku.dsystemsen.com/column/walking-effects.html`
   - `https://aruku.dsystemsen.com/sitemap.xml`

> ※ Secrets 登録前に push すると、最初のジョブは認証エラーで**失敗します**（正常）。
> Secrets を入れてから再実行してください。

---

## 5. 以後の更新フロー

```bash
# articles.php を編集して記事追加・修正
git add -A
git commit -m "記事を更新"
git push        # → 自動で本番反映
```

アップロードされるのは本番に必要なファイルだけです
（`articles.py` / `build.py` / `README.md` / `.github/` などは送られません）。

---

## トラブルシューティング

| 症状 | 対処 |
|---|---|
| ジョブが `Permission denied (publickey)` | 公開鍵がサーバ未登録、または `XSERVER_SSH_KEY` が別の鍵。A の手順を再確認 |
| `passphrase` 関連で失敗 | 鍵がパスフレーズ付きなら `XSERVER_SSH_PASSPHRASE` を登録（無し鍵なら空に）|
| アップロードは成功するがページが404/表示崩れ | `XSERVER_TARGET` のパス違い。手順2で実パスを再確認 |
| 反映が遅い | ブラウザ/サーバキャッシュ。ハードリフレッシュ（Ctrl+F5）|

---

## 会員機能（マイページ・記録・会員投稿）の本番反映 — MySQL

会員機能は **本番=MySQL / ローカル=SQLite** を自動切替します（`inc/db.php`）。
`inc/db_config.php` が存在すれば MySQL、無ければ SQLite を使用します。

### 1. XserverでMySQLデータベースを作成
サーバーパネル →「MySQL設定」で、データベース・ユーザーを作成し、ユーザーをDBに追加。
- DB名 / ユーザー名 / パスワード / **MySQLホスト名**（例 `mysqlXXXX.xserver.jp`）を控える。
- 文字コードは utf8mb4 を推奨。

### 2. inc/db_config.php をサーバに設置（秘密情報・gitに入れない）
`inc/db_config.sample.php` をコピーして `inc/db_config.php` を作り、上記の情報を記入してサーバの `inc/` にアップロード。
テーブル（members / activity_logs / posts）は**初回アクセス時に自動作成**されます（`aruku_db_init()`）。

### 3. デプロイ対象に新規ファイルを追加
手動 scp の場合、従来一式に加えて次を**必ず**アップロード：
- `inc/`（`db.php` / `member.php` / `posts.php`。※`db_config.php`は別途サーバ設置、`db_config.sample.php`は任意）
- `member/`（`register.php` / `login.php` / `logout.php` / `mypage.php` / `post.php`）
- `posts/`（`view.php`）
- `admin/posts.php`（会員投稿の承認画面）
- `uploads/`（`.htaccess` を含む。**書き込み権限が必要**＝ディレクトリを 755、Webサーバが書ける状態に）
- 更新済み：`render.php` / `.htaccess`（/posts/123 のリライト追加）/ `assets/style.css` / `assets/column.css`

**送らない**：`data/`（ローカルSQLite `aruku.sqlite` 含む）/ `inc/db_config.php` は手動で安全に設置 / `dev-router.php`（ローカル専用）/ `uploads/` 内のローカル画像（実体はユーザー投稿）。

会員投稿は **きれいなURL `/posts/123`**（`.htaccess` のリライト）。画像は `uploads/` に検証のうえ保存（JPEG/PNG/WebP/GIF・4MBまで・スクリプト実行は `.htaccess` で禁止）。スパム対策：ハニーポット＋レート制限（`data/.ratelimit.json`）＋承認待ち上限。

### 4. 確認
- `/member/register.php` で会員登録 → `/member/mypage.php` で記録 → 総消費カロリー表示。
- 会員が `/member/post.php` で投稿 → `/admin/posts.php`（管理ログイン）で承認 → `/column/` の「みんなのコラム」に表示。
- セッションCookieのため **HTTPS必須**（`.htaccess` でHTTPS強制済み）。

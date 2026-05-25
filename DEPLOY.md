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

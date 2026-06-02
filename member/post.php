<?php
require_once __DIR__ . '/../render.php';
require_once __DIR__ . '/../inc/member.php';
require_once __DIR__ . '/../inc/posts.php';

$prefix = '../';
$me = member_require_login($prefix);
$cats = aruku_post_categories();

// 編集モード（自分の投稿のみ）
$editId = (int) ($_GET['id'] ?? ($_POST['edit_id'] ?? 0));
$existing = $editId > 0 ? post_get_owned($editId, (int) $me['id']) : null;
$editing = (bool) $existing;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!member_csrf_check($_POST['csrf'] ?? null)) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (aruku_honeypot_filled()) {
        $error = '送信を確認できませんでした。';
    } else {
        if (($_POST['save_draft'] ?? '') !== '') {
            $status = 'draft';
        } else {
            // ①キーワード/スパム簡易判定 → ②OpenAI Moderation（暴力・ヘイト・性的・自傷等）。
            // どちらかが不適切と判定したら自動で保留（管理者キューへ）。それ以外は承認なしで即時公開。
            $flag = aruku_post_flagged($_POST['title'] ?? '', $_POST['body'] ?? '');
            if (!$flag) {
                $mod = aruku_openai_moderation(($_POST['title'] ?? '') . "\n" . ($_POST['body'] ?? ''));
                if ($mod === true) {
                    $flag = true;
                }
            }
            $status = $flag ? 'pending' : 'published';
        }
        $category = (string) ($_POST['category'] ?? '');
        $imgs = aruku_save_images($_FILES['image'] ?? [], 5);
        if (!isset(aruku_post_categories()[$category])) {
            $error = 'カテゴリを選択してください。';
            foreach ($imgs['names'] ?? [] as $n) {
                @unlink(dirname(__DIR__) . '/uploads/' . $n);
            }
        } elseif (!$imgs['ok']) {
            $error = $imgs['error'];
        } elseif ($editing) {
            // 既存画像の削除（チェックされたもの）
            foreach ((array) ($_POST['delimg'] ?? []) as $delId) {
                post_image_delete((int) $delId, (int) $existing['id']);
            }
            $r = post_update((int) $existing['id'], (int) $me['id'], $_POST['title'] ?? '', $_POST['body'] ?? '', null, $category, $status);
            if ($r['ok']) {
                foreach ((array) ($_POST['imgorder'] ?? []) as $iid => $sv) {
                    post_image_set_sort((int) $iid, (int) $existing['id'], (int) $sv);
                }
                $sort = count(post_images((int) $existing['id']));
                foreach ($imgs['names'] as $n) {
                    post_add_image((int) $existing['id'], $n, $sort++);
                }
                post_set_tags((int) $existing['id'], $_POST['tags'] ?? '');
                header('Location: mypage.php?' . ($status === 'draft' ? 'draft=1' : ($status === 'published' ? 'published=1' : 'held=1')));
                exit;
            }
            foreach ($imgs['names'] as $n) {
                @unlink(dirname(__DIR__) . '/uploads/' . $n);
            }
            $error = $r['error'];
        } else {
            if ($status !== 'draft' && !aruku_ratelimit('post:' . (int) $me['id'], 5, 600)) {
                $error = '投稿が続けて行われました。少し時間をおいてからお試しください。';
            } else {
                $r = post_create((int) $me['id'], $_POST['title'] ?? '', $_POST['body'] ?? '', null, $category, $status);
                if ($r['ok']) {
                    $sort = 0;
                    foreach ($imgs['names'] as $n) {
                        post_add_image((int) $r['id'], $n, $sort++);
                    }
                    post_set_tags((int) $r['id'], $_POST['tags'] ?? '');
                    header('Location: mypage.php?' . ($status === 'draft' ? 'draft=1' : ($status === 'published' ? 'published=1' : 'held=1')));
                    exit;
                }
                foreach ($imgs['names'] as $n) {
                    @unlink(dirname(__DIR__) . '/uploads/' . $n);
                }
                $error = $r['error'];
            }
        }
    }
}

$token = member_csrf_token();
$hp    = aruku_honeypot_field();
$curTitle = h($_POST['title'] ?? ($existing['title'] ?? ''));
$curBody  = h($_POST['body'] ?? ($existing['body'] ?? ''));
$curCat   = (string) ($_POST['category'] ?? ($existing['category'] ?? ''));
$curTags  = h($_POST['tags'] ?? ($editing ? implode(', ', post_tags((int) $existing['id'])) : ''));
$err   = $error ? '<p class="auth-error">' . h($error) . '</p>' : '';
$heading = $editing ? 'コラムを編集' : 'コラムを書く';
$editField = $editing ? '<input type="hidden" name="edit_id" value="' . (int) $existing['id'] . '">' : '';

$catOpts = '<option value="" disabled' . ($curCat === '' ? ' selected' : '') . '>カテゴリを選択（必須）</option>';
foreach ($cats as $k => $label) {
    $sel = ($curCat === $k) ? ' selected' : '';
    $catOpts .= '<option value="' . h($k) . '"' . $sel . '>' . h($label) . '</option>';
}

// 編集時：既存画像（削除チェック付き）
$existImgs = '';
if ($editing) {
    $imgsList = post_images((int) $existing['id']);
    if ($imgsList) {
        $thumbs = '';
        $od = 0;
        foreach ($imgsList as $im) {
            $thumbs .= '<label class="img-manage-item"><img src="' . $prefix . 'uploads/' . h($im['filename']) . '" alt="">'
                . '<span class="img-order">順番 <input type="number" name="imgorder[' . (int) $im['id'] . ']" value="' . $od++ . '" min="0" max="99"></span>'
                . '<span><input type="checkbox" name="delimg[]" value="' . (int) $im['id'] . '"> 削除</span></label>';
        }
        $existImgs = '<div class="field"><span>現在の画像（チェックで削除）</span><div class="img-manage">' . $thumbs . '</div></div>';
    }
}

$draftKey = $editing ? ('post' . (int) $existing['id']) : 'new';

$body = <<<HTML
<div class="member-head">
  <h1>{$heading}</h1>
  <p><a href="mypage.php" class="member-logout">マイページへ戻る</a></p>
</div>
<div class="calc-tool post-editor" id="post-editor" data-draft-key="$draftKey" data-upload="upload_image.php" data-csrf="$token">
  $err
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="$token">
    $editField
    $hp
    $existImgs
    <div class="field"><span>サムネイル画像（1枚）<small class="md-hint">推奨サイズ：横長 1200×800px 前後（3:2）／JPEG・PNG・WebP・GIF・各4MBまで</small></span>
      <div class="file-drop" id="cover-drop">
        <input type="file" name="image[]" accept="image/jpeg,image/png,image/webp,image/gif">
        <div class="file-drop-empty">📷 ここにドラッグ&ドロップ、またはクリックで選択</div>
        <div class="file-drop-filled" hidden>
          <img class="file-drop-preview" alt="サムネイルプレビュー">
          <div class="file-size-ctrl">
            <label>表示サイズ <input type="range" class="file-size-range" min="120" max="520" value="320"></label>
            <button type="button" class="file-clear">× 画像を削除</button>
          </div>
        </div>
      </div>
    </div>
    <label class="field"><span>タイトル</span><input type="text" id="post-title" name="title" value="$curTitle" maxlength="120" placeholder="例：ウォーキングを1ヶ月続けて変わったこと" required></label>
    <label class="field"><span>カテゴリ（必須）</span><select name="category" required>$catOpts</select></label>
    <label class="field"><span>タグ（カンマ区切り・最大5個）</span><input type="text" name="tags" value="$curTags" placeholder="例：初心者, ダイエット, 朝活"></label>
    <div class="field md-field">
      <span>本文<small class="md-hint">Markdown対応：## 見出し / **太字** / - リスト / &gt; 引用 / 画像はドラッグ&amp;ドロップ・貼り付け・🖼ボタンで挿入</small></span>
      <div class="md-toolbar" data-md-target="post-body" role="toolbar" aria-label="書式">
        <button type="button" class="md-btn" data-md="h2" title="見出し">見出し</button>
        <button type="button" class="md-btn" data-md="h3" title="小見出し">小見出し</button>
        <button type="button" class="md-btn" data-md="bold" title="太字 (Ctrl+B)"><b>B</b></button>
        <button type="button" class="md-btn" data-md="italic" title="斜体 (Ctrl+I)"><i>I</i></button>
        <button type="button" class="md-btn" data-md="ul" title="箇条書き">・リスト</button>
        <button type="button" class="md-btn" data-md="ol" title="番号リスト">1.リスト</button>
        <button type="button" class="md-btn" data-md="quote" title="引用">&gt; 引用</button>
        <button type="button" class="md-btn" data-md="link" title="リンク">🔗 リンク</button>
        <button type="button" class="md-btn" data-md="image" title="画像を挿入">🖼 画像</button>
        <button type="button" class="md-btn md-toggle-preview" data-md="preview" aria-pressed="false">プレビュー</button>
        <button type="button" class="md-btn md-fullscreen" data-md="fullscreen" title="集中モード">⛶</button>
      </div>
      <div class="md-editzone">
        <textarea id="post-body" name="body" rows="16" maxlength="20000" placeholder="あなたの体験やコツを自由に書いてください。&#10;&#10;## 見出しから書き始めると読みやすくなります&#10;画像はここにドラッグ&ドロップでも入ります" required>$curBody</textarea>
        <div class="md-dropmsg">ここに画像をドロップ</div>
      </div>
      <div class="md-preview" hidden aria-hidden="true"></div>
      <div class="md-statusbar">
        <span class="md-count" id="md-count"></span>
      </div>
      <input type="file" id="md-image-input" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
    </div>
    <div class="editor-actions">
      <button type="submit" name="submit_post" value="1" class="lp-btn lp-btn-primary">投稿する</button>
      <button type="submit" name="save_draft" value="1" class="lp-btn lp-btn-secondary">下書き保存</button>
    </div>
  </form>
</div>
HTML;

member_render_page($prefix, $heading, $body);

<?php
/**
 * aruku 管理画面（CMS） — フロントコントローラ
 * 記事 / カテゴリ / 固定ページ(運営者・プライバシー) / トップ文言 を編集。
 * データは data/content.json（cms.php 経由）。
 */
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/auth.php';

$BASE   = admin_base();
$method = $_SERVER['REQUEST_METHOD'];
$action = (string)($_POST['action'] ?? '');
$p      = (string)($_GET['p'] ?? 'dashboard');

// ---- ログイン処理（POST） ----
if ($method === 'POST' && $action === 'login') {
    $res = attempt_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''), $CONFIG);
    if (!empty($res['ok'])) {
        redirect($BASE);
    }
    if (!empty($res['locked'])) {
        $min = (int)ceil($res['locked'] / 60);
        echo view_login('試行回数が上限に達しました。約' . $min . '分後に再度お試しください。');
    } else {
        echo view_login('メールアドレスまたはパスワードが違います。');
    }
    exit;
}

// ---- ログアウト ----
if ($p === 'logout') {
    do_logout();
    redirect($BASE . '?p=login');
}

// ---- ログイン画面 ----
if ($p === 'login') {
    if (is_logged_in()) {
        redirect($BASE);
    }
    echo view_login();
    exit;
}

// ---- ここから要ログイン ----
require_login();

// ---- 変更系POST（CSRF必須） ----
if ($method === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'セキュリティトークンが無効です（セッション切れの可能性）。もう一度お試しください。');
        redirect($BASE);
    }
    switch ($action) {
        case 'save_article':   handle_save_article($CONFIG, $BASE);   break;
        case 'delete_article': handle_delete_article($BASE);          break;
        case 'save_categories':handle_save_categories($BASE);         break;
        case 'save_page':      handle_save_page($BASE);               break;
        case 'save_site':      handle_save_site($BASE);               break;
        default:               redirect($BASE);
    }
    exit;
}

// ---- 画面表示（GET） ----
$content = cms_load();
switch ($p) {
    case 'article':
        $slug = (string)($_GET['slug'] ?? '');
        if ($slug !== '') {
            $art = null;
            foreach ($content['articles'] as $a) {
                if (($a['slug'] ?? '') === $slug) { $art = $a; break; }
            }
            if (!$art) { flash_set('error', '記事が見つかりません: ' . $slug); redirect($BASE); }
            echo view_article($content, $art, false);
        } else {
            echo view_article($content, new_article_template($content), true);
        }
        break;
    case 'categories':
        echo view_categories($content);
        break;
    case 'page':
        $key = (string)($_GET['key'] ?? 'about');
        if (!isset($content['pages'][$key])) { flash_set('error', 'ページが見つかりません。'); redirect($BASE); }
        echo view_page($content, $key);
        break;
    case 'site':
        echo view_site($content);
        break;
    case 'cta':
        echo view_cta();
        break;
    case 'dashboard':
    default:
        echo view_dashboard($content);
}

/** CTAクリック計測の表示。 */
function view_cta(): string
{
    require_once __DIR__ . '/../inc/cta.php';
    $labels = cta_labels();
    try {
        $c = cta_counts();
    } catch (\Throwable $e) {
        $c = ['total' => [], 'd30' => []];
    }
    $total = $c['total'];
    $d30   = $c['d30'];
    $sumTotal = array_sum($total);
    $sum30    = array_sum($d30);

    // 合計の多い順に並べる（ラベル定義順をベースに件数で安定ソート）
    $keys = array_keys($labels);
    usort($keys, function ($a, $b) use ($total) {
        return ($total[$b] ?? 0) <=> ($total[$a] ?? 0);
    });

    $rows = '';
    foreach ($keys as $k) {
        $t = (int)($total[$k] ?? 0);
        $d = (int)($d30[$k] ?? 0);
        $share = $sumTotal > 0 ? round($t / $sumTotal * 100) : 0;
        $rows .= '<tr>'
            . '<td>' . h($labels[$k]) . '<br><small style="color:#8a8f8d;">' . h($k) . '</small></td>'
            . '<td style="text-align:right;font-weight:700;">' . number_format($t) . '</td>'
            . '<td style="text-align:right;">' . number_format($d) . '</td>'
            . '<td style="text-align:right;color:#5d6362;">' . $share . '%</td>'
            . '</tr>';
    }

    $inner = '<section class="card">'
        . '<h1 class="card-title">CTA計測</h1>'
        . '<p style="color:#5d6362;line-height:1.8;margin:0 0 18px;">サイト各所のCTA（行動ボタン）のクリック数です。Cookieや外部送信を使わず、自前で集計しています。'
        . '<br>合計クリック: <b>' . number_format($sumTotal) . '</b>　／　直近30日: <b>' . number_format($sum30) . '</b></p>'
        . '<table class="data-table" style="width:100%;border-collapse:collapse;">'
        . '<thead><tr>'
        . '<th style="text-align:left;">CTA</th>'
        . '<th style="text-align:right;">合計</th>'
        . '<th style="text-align:right;">直近30日</th>'
        . '<th style="text-align:right;">構成比</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . ($sumTotal === 0 ? '<p style="color:#8a8f8d;margin-top:16px;">まだクリックデータがありません。サイト公開後、CTAが押されると数値が記録されます。</p>' : '')
        . '</section>';
    return layout('CTA計測', 'cta', $inner);
}

// ============================================================
// 保存ハンドラ
// ============================================================
function build_article_from_post(): array
{
    $a = [];
    $a['slug']  = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string)($_POST['slug'] ?? ''))));
    $a['cat']   = trim((string)($_POST['cat'] ?? ''));
    $a['title'] = trim((string)($_POST['title'] ?? ''));
    $sub = trim((string)($_POST['subtitle'] ?? ''));
    if ($sub !== '') { $a['subtitle'] = $sub; }
    $a['desc'] = trim((string)($_POST['desc'] ?? ''));
    $kw = trim((string)($_POST['keywords'] ?? ''));
    if ($kw !== '') { $a['keywords'] = $kw; }
    $a['date'] = trim((string)($_POST['date'] ?? '')) ?: date('Y-m-d');
    $a['read'] = max(1, (int)($_POST['read'] ?? 5));
    $a['lead'] = trim((string)($_POST['lead'] ?? ''));

    $secs = [];
    foreach ((array)($_POST['sections'] ?? []) as $row) {
        $h2 = trim((string)($row['h2'] ?? ''));
        $body = trim((string)($row['body'] ?? ''));
        if ($h2 === '' && $body === '') { continue; }
        $id = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string)($row['id'] ?? ''))));
        if ($id === '') { $id = 'sec-' . (count($secs) + 1); }
        $secs[] = ['id' => $id, 'h2' => $h2, 'body' => $body];
    }
    $a['sections'] = $secs;

    $faq = [];
    foreach ((array)($_POST['faq'] ?? []) as $row) {
        $q = trim((string)($row['q'] ?? ''));
        $ans = trim((string)($row['a'] ?? ''));
        if ($q === '' && $ans === '') { continue; }
        $faq[] = [$q, $ans];
    }
    $a['faq'] = $faq;

    $rel = [];
    foreach (preg_split('/[,\s]+/', (string)($_POST['related'] ?? '')) as $x) {
        $x = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($x)));
        if ($x !== '') { $rel[] = $x; }
    }
    $a['related'] = $rel;

    if (!empty($_POST['aff_enabled'])) {
        $a['affiliate'] = [
            'label' => trim((string)($_POST['aff_label'] ?? '')),
            'title' => trim((string)($_POST['aff_title'] ?? '')),
            'desc'  => trim((string)($_POST['aff_desc'] ?? '')),
            'cta'   => trim((string)($_POST['aff_cta'] ?? '')),
        ];
    } else {
        $a['affiliate'] = null;
    }
    return $a;
}

function handle_save_article(array $config, string $base): void
{
    $content = cms_load();
    $orig = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string)($_POST['orig_slug'] ?? ''))));
    $a = build_article_from_post();

    // 検証
    $errors = [];
    if ($a['slug'] === '')  { $errors[] = 'スラッグ（URL）は必須です（半角英数字とハイフン）。'; }
    if ($a['title'] === '') { $errors[] = 'タイトルは必須です。'; }
    if (!isset($content['categories'][$a['cat']])) { $errors[] = 'カテゴリを選択してください。'; }
    // スラッグ重複
    foreach ($content['articles'] as $x) {
        if (($x['slug'] ?? '') === $a['slug'] && $a['slug'] !== $orig) {
            $errors[] = 'このスラッグは既に使われています: ' . $a['slug'];
            break;
        }
    }
    if ($errors) {
        echo view_article($content, $a, $orig === '', $errors);
        exit;
    }

    // 反映（既存を置換 or 追加）
    $found = false;
    if ($orig !== '') {
        foreach ($content['articles'] as $i => $x) {
            if (($x['slug'] ?? '') === $orig) { $content['articles'][$i] = $a; $found = true; break; }
        }
    }
    if (!$found) { $content['articles'][] = $a; }

    $res = cms_save($content);
    if (!$res['ok']) { flash_set('error', $res['error']); redirect($base . '?p=article&slug=' . urlencode($a['slug'])); }
    flash_set('success', '記事「' . $a['title'] . '」を保存しました。');
    redirect($base);
}

function handle_delete_article(string $base): void
{
    $content = cms_load();
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string)($_POST['slug'] ?? ''))));
    $title = $slug;
    $new = [];
    foreach ($content['articles'] as $x) {
        if (($x['slug'] ?? '') === $slug) { $title = $x['title'] ?? $slug; continue; }
        $new[] = $x;
    }
    $content['articles'] = $new;
    $res = cms_save($content);
    if (!$res['ok']) { flash_set('error', $res['error']); redirect($base); }
    flash_set('success', '記事「' . $title . '」を削除しました。');
    redirect($base);
}

function handle_save_categories(string $base): void
{
    $content = cms_load();
    $articles = $content['articles'];
    $usage = [];
    foreach ($articles as $x) { $usage[$x['cat'] ?? ''] = ($usage[$x['cat'] ?? ''] ?? 0) + 1; }

    $newCats = [];
    $orderList = [];
    foreach ((array)($_POST['cats'] ?? []) as $key => $row) {
        $key = (string)$key;
        if (!isset($content['categories'][$key])) { continue; }
        if (!empty($row['delete'])) {
            if (($usage[$key] ?? 0) > 0) {
                flash_set('error', 'カテゴリ「' . $key . '」は記事が紐づくため削除できません。');
                continue; // 削除せず残す
            }
            continue; // 未使用なら削除（追加しない）
        }
        $newCats[$key] = [
            'name'  => trim((string)($row['name'] ?? '')),
            'emoji' => trim((string)($row['emoji'] ?? '')),
            'desc'  => trim((string)($row['desc'] ?? '')),
            'grad'  => [trim((string)($row['grad0'] ?? '#e3efe2')), trim((string)($row['grad1'] ?? '#4f9e6b'))],
            'fg'    => trim((string)($row['fg'] ?? '#1b5e3f')),
        ];
        $orderList[$key] = (int)($row['order'] ?? 999);
    }

    // 新規カテゴリ
    $nk = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string)($_POST['newkey'] ?? ''))));
    $nn = trim((string)($_POST['newname'] ?? ''));
    if ($nk !== '' && $nn !== '') {
        if (isset($newCats[$nk]) || isset($content['categories'][$nk])) {
            flash_set('error', 'カテゴリキー「' . $nk . '」は既に存在します。');
        } else {
            $newCats[$nk] = [
                'name'  => $nn,
                'emoji' => trim((string)($_POST['newemoji'] ?? '🏷️')),
                'desc'  => trim((string)($_POST['newdesc'] ?? '')),
                'grad'  => ['#e3efe2', '#4f9e6b'],
                'fg'    => '#1b5e3f',
            ];
            $orderList[$nk] = (int)($_POST['neworder'] ?? 999);
        }
    }

    if (!$newCats) { flash_set('error', 'カテゴリは1つ以上必要です。'); redirect($base . '?p=categories'); }

    asort($orderList);
    $order = array_keys($orderList);
    // 並び順に合わせてカテゴリ配列も並べ替え
    $sorted = [];
    foreach ($order as $k) { if (isset($newCats[$k])) { $sorted[$k] = $newCats[$k]; } }

    $content['categories'] = $sorted;
    $content['category_order'] = $order;
    $res = cms_save($content);
    if (!$res['ok']) { flash_set('error', $res['error']); redirect($base . '?p=categories'); }
    flash_set('success', 'カテゴリを保存しました。');
    redirect($base . '?p=categories');
}

function handle_save_page(string $base): void
{
    $content = cms_load();
    $key = (string)($_POST['key'] ?? '');
    if (!isset($content['pages'][$key])) { flash_set('error', 'ページが見つかりません。'); redirect($base); }

    $secs = [];
    foreach ((array)($_POST['sections'] ?? []) as $row) {
        $h2 = trim((string)($row['h2'] ?? ''));
        $body = trim((string)($row['body'] ?? ''));
        if ($h2 === '' && $body === '') { continue; }
        $secs[] = ['h2' => $h2, 'body' => $body];
    }
    $content['pages'][$key]['title']    = trim((string)($_POST['title'] ?? $content['pages'][$key]['title']));
    $content['pages'][$key]['desc']     = trim((string)($_POST['desc'] ?? ''));
    $content['pages'][$key]['noindex']  = !empty($_POST['noindex']);
    $content['pages'][$key]['sections'] = $secs;

    $res = cms_save($content);
    if (!$res['ok']) { flash_set('error', $res['error']); redirect($base . '?p=page&key=' . urlencode($key)); }
    flash_set('success', 'ページ「' . $content['pages'][$key]['title'] . '」を保存しました。');
    redirect($base . '?p=page&key=' . urlencode($key));
}

function handle_save_site(string $base): void
{
    $content = cms_load();
    foreach (array_keys($content['top']) as $k) {
        if (isset($_POST['top'][$k])) { $content['top'][$k] = trim((string)$_POST['top'][$k]); }
    }
    foreach (array_keys($content['site']) as $k) {
        if (isset($_POST['site'][$k])) { $content['site'][$k] = trim((string)$_POST['site'][$k]); }
    }
    $res = cms_save($content);
    if (!$res['ok']) { flash_set('error', $res['error']); redirect($base . '?p=site'); }
    flash_set('success', 'トップ／サイト文言を保存しました。');
    redirect($base . '?p=site');
}

function new_article_template(array $content): array
{
    $firstCat = $content['category_order'][0] ?? array_key_first($content['categories']);
    return [
        'slug' => '', 'cat' => $firstCat, 'title' => '', 'subtitle' => '', 'desc' => '',
        'keywords' => '', 'date' => date('Y-m-d'), 'read' => 5, 'lead' => '',
        'sections' => [['id' => 'sec-1', 'h2' => '', 'body' => '']],
        'faq' => [], 'related' => [], 'affiliate' => null,
    ];
}

// ============================================================
// ビュー
// ============================================================
function view_login(?string $error = null): string
{
    $csrf = csrf_field();
    $err = $error ? '<div class="msg msg-error">' . h($error) . '</div>' : '';
    return <<<HTML
<!doctype html><html lang="ja"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>管理者ログイン｜aruku CMS</title>
<link rel="stylesheet" href="admin.css">
</head><body class="login-body">
<div class="login-card">
  <div class="login-brand">aruku <span>CMS</span></div>
  <p class="login-sub">管理者ログイン</p>
  {$err}
  <form method="post" action="?p=login" autocomplete="off">
    {$csrf}
    <input type="hidden" name="action" value="login">
    <label>メールアドレス
      <input type="email" name="email" required autofocus>
    </label>
    <label>パスワード
      <input type="password" name="password" required>
    </label>
    <button type="submit" class="btn btn-primary btn-block">ログイン</button>
  </form>
  <a class="login-back" href="../index.html">← サイトに戻る</a>
</div>
</body></html>
HTML;
}

function layout(string $title, string $active, string $inner): string
{
    $base = admin_base();
    $flash = '';
    foreach (flash_take() as $f) {
        $cls = $f['type'] === 'error' ? 'msg-error' : 'msg-success';
        $flash .= '<div class="msg ' . $cls . '">' . h($f['msg']) . '</div>';
    }
    $nav = function (string $p, string $label, string $href) use ($active) {
        $cls = $active === $p ? ' class="active"' : '';
        return '<a href="' . h($href) . '"' . $cls . '>' . h($label) . '</a>';
    };
    $links = $nav('dashboard', '記事一覧', $base)
        . $nav('article', '新規記事', $base . '?p=article')
        . $nav('categories', 'カテゴリ', $base . '?p=categories')
        . $nav('page_about', '運営者ページ', $base . '?p=page&key=about')
        . $nav('page_privacy', 'プライバシー', $base . '?p=page&key=privacy')
        . $nav('site', 'トップ／サイト文言', $base . '?p=site')
        . $nav('cta', 'CTA計測', $base . '?p=cta');
    $email = h($_SESSION['admin_email'] ?? '');
    return <<<HTML
<!doctype html><html lang="ja"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{$title}｜aruku CMS</title>
<link rel="stylesheet" href="admin.css">
</head><body>
<header class="topbar">
  <div class="topbar-in">
    <a class="brand" href="{$base}">aruku <span>CMS</span></a>
    <nav class="topnav">{$links}</nav>
    <div class="topbar-right">
      <a class="view-site" href="../index.html" target="_blank" rel="noopener">サイトを見る ↗</a>
      <span class="who">{$email}</span>
      <a class="logout" href="{$base}?p=logout">ログアウト</a>
    </div>
  </div>
</header>
<main class="wrap">
{$flash}
{$inner}
</main>
<script src="admin.js" defer></script>
</body></html>
HTML;
}

function cat_options(array $content, string $selected): string
{
    $out = '';
    foreach ($content['category_order'] as $key) {
        $c = $content['categories'][$key] ?? null;
        if (!$c) { continue; }
        $sel = $key === $selected ? ' selected' : '';
        $out .= '<option value="' . h($key) . '"' . $sel . '>' . h(($c['emoji'] ?? '') . ' ' . ($c['name'] ?? $key)) . '</option>';
    }
    return $out;
}

function view_dashboard(array $content): string
{
    $base = admin_base();
    $total = count($content['articles']);
    $warn = cms_can_write() ? '' : '<div class="msg msg-error">⚠ data ディレクトリに書き込めません。保存できない可能性があります（パーミッション755・所有者を確認）。</div>';

    // カテゴリ別に一覧
    $byCat = [];
    foreach ($content['articles'] as $a) { $byCat[$a['cat'] ?? ''][] = $a; }

    $sections = '';
    foreach ($content['category_order'] as $key) {
        $c = $content['categories'][$key] ?? null;
        if (!$c) { continue; }
        $arts = $byCat[$key] ?? [];
        $rows = '';
        foreach ($arts as $a) {
            $slug = h($a['slug'] ?? '');
            $rows .= '<tr>'
                . '<td class="c-title"><a href="' . $base . '?p=article&slug=' . urlencode($a['slug'] ?? '') . '">' . h($a['title'] ?? '(無題)') . '</a>'
                . '<div class="c-slug">/column/' . $slug . '.html</div></td>'
                . '<td class="c-date">' . h($a['date'] ?? '') . '</td>'
                . '<td class="c-actions">'
                . '<a class="mini" href="../column/' . $slug . '.html" target="_blank" rel="noopener">表示</a>'
                . '<a class="mini" href="' . $base . '?p=article&slug=' . urlencode($a['slug'] ?? '') . '">編集</a>'
                . '</td></tr>';
        }
        if ($rows === '') { $rows = '<tr><td colspan="3" class="empty">記事がありません</td></tr>'; }
        $sections .= '<section class="cat-block">'
            . '<h2>' . h(($c['emoji'] ?? '') . ' ' . ($c['name'] ?? $key)) . ' <span class="count">' . count($arts) . '</span></h2>'
            . '<table class="list"><tbody>' . $rows . '</tbody></table></section>';
    }

    $inner = <<<HTML
{$warn}
<div class="page-head">
  <div>
    <h1>記事一覧</h1>
    <p class="muted">全 {$total} 記事。タイトルをクリックで編集できます。</p>
  </div>
  <div class="page-head-actions">
    <input type="search" id="filter" class="search" placeholder="記事を絞り込み…">
    <a class="btn btn-primary" href="{$base}?p=article">＋ 新規記事</a>
  </div>
</div>
<div id="list-root">
{$sections}
</div>
HTML;
    return layout('記事一覧', 'dashboard', $inner);
}

function section_rows_html(array $sections, bool $withId): string
{
    $out = '';
    $i = 0;
    foreach ($sections as $sec) {
        $out .= section_row_html($i, $sec, $withId);
        $i++;
    }
    if ($out === '') {
        $out = section_row_html(0, ['id' => 'sec-1', 'h2' => '', 'body' => ''], $withId);
    }
    return $out;
}

function section_row_html(int $i, array $sec, bool $withId): string
{
    $idField = $withId
        ? '<input type="text" name="sections[' . $i . '][id]" value="' . h($sec['id'] ?? '') . '" class="sec-id" placeholder="ID(任意)">'
        : '';
    $h2 = h($sec['h2'] ?? '');
    $body = h($sec['body'] ?? '');
    return <<<HTML
<div class="sec-row" data-row>
  <div class="sec-row-head">
    <span class="drag">≡ 見出し</span>
    <input type="text" name="sections[{$i}][h2]" value="{$h2}" class="sec-h2" placeholder="見出し（h2）">
    {$idField}
    <button type="button" class="mini danger" data-remove-row>削除</button>
  </div>
  <textarea name="sections[{$i}][body]" class="richtext" rows="6" placeholder="本文（HTML可）">{$body}</textarea>
</div>
HTML;
}

function faq_rows_html(array $faq): string
{
    $out = '';
    $i = 0;
    foreach ($faq as $qa) {
        $q = h($qa[0] ?? '');
        $a = h($qa[1] ?? '');
        $out .= <<<HTML
<div class="faq-row" data-row>
  <div class="faq-row-head"><span>Q&amp;A</span><button type="button" class="mini danger" data-remove-row>削除</button></div>
  <input type="text" name="faq[{$i}][q]" value="{$q}" placeholder="質問">
  <textarea name="faq[{$i}][a]" rows="2" placeholder="回答">{$a}</textarea>
</div>
HTML;
        $i++;
    }
    return $out;
}

function view_article(array $content, array $a, bool $isNew, array $errors = []): string
{
    $base = admin_base();
    $errHtml = '';
    if ($errors) {
        $errHtml = '<div class="msg msg-error"><ul><li>' . implode('</li><li>', array_map('h', $errors)) . '</li></ul></div>';
    }
    $csrf = csrf_field();
    $opts = cat_options($content, $a['cat'] ?? '');
    $secRows = section_rows_html($a['sections'] ?? [], true);
    $faqRows = faq_rows_html($a['faq'] ?? []);
    $related = h(implode(', ', $a['related'] ?? []));
    $aff = $a['affiliate'] ?? null;
    $affChecked = $aff ? ' checked' : '';
    $affLabel = h($aff['label'] ?? 'PR・おすすめ商品');
    $affTitle = h($aff['title'] ?? '');
    $affDesc  = h($aff['desc'] ?? '');
    $affCta   = h($aff['cta'] ?? '商品を見る');
    $slug = h($a['slug'] ?? '');
    $title = h($a['title'] ?? '');
    $subtitle = h($a['subtitle'] ?? '');
    $desc = h($a['desc'] ?? '');
    $keywords = h($a['keywords'] ?? '');
    $date = h($a['date'] ?? date('Y-m-d'));
    $read = (int)($a['read'] ?? 5);
    $lead = h($a['lead'] ?? '');
    $origSlug = $isNew ? '' : $slug;
    $heading = $isNew ? '新規記事' : '記事を編集';
    $viewLink = $isNew ? '' : '<a class="btn btn-ghost" href="../column/' . $slug . '.html" target="_blank" rel="noopener">表示 ↗</a>';
    $deleteBtn = $isNew ? '' : <<<HTML
<form method="post" action="{$base}" class="delete-form" onsubmit="return confirm('この記事を削除します。よろしいですか？');">
  {$csrf}<input type="hidden" name="action" value="delete_article"><input type="hidden" name="slug" value="{$slug}">
  <button type="submit" class="btn btn-danger">記事を削除</button>
</form>
HTML;

    $inner = <<<HTML
{$errHtml}
<div class="page-head">
  <div><h1>{$heading}</h1></div>
  <div class="page-head-actions">{$viewLink}<a class="btn btn-ghost" href="{$base}">一覧へ戻る</a></div>
</div>
<form method="post" action="{$base}" class="form" id="article-form">
  {$csrf}
  <input type="hidden" name="action" value="save_article">
  <input type="hidden" name="orig_slug" value="{$origSlug}">

  <div class="grid2">
    <label>タイトル <span class="req">*</span>
      <input type="text" name="title" value="{$title}" required>
    </label>
    <label>サブタイトル（任意）
      <input type="text" name="subtitle" value="{$subtitle}">
    </label>
  </div>

  <div class="grid3">
    <label>スラッグ（URL） <span class="req">*</span>
      <input type="text" name="slug" value="{$slug}" pattern="[a-z0-9\\-]+" placeholder="例: kettoichi" required>
      <small>/column/<b>スラッグ</b>.html</small>
    </label>
    <label>カテゴリ <span class="req">*</span>
      <select name="cat">{$opts}</select>
    </label>
    <label>公開日
      <input type="date" name="date" value="{$date}">
    </label>
  </div>

  <div class="grid3">
    <label>所要時間（分）
      <input type="number" name="read" value="{$read}" min="1" max="60">
    </label>
    <label class="span2">メタディスクリプション（説明文・120字程度）
      <input type="text" name="desc" value="{$desc}">
    </label>
  </div>

  <label>キーワード（カンマ区切り）
    <input type="text" name="keywords" value="{$keywords}">
  </label>

  <label>リード文（導入）
    <textarea name="lead" class="richtext" rows="3" placeholder="リード文（HTML可）">{$lead}</textarea>
  </label>

  <fieldset>
    <legend>本文セクション</legend>
    <div id="sections" data-rows>{$secRows}</div>
    <button type="button" class="btn btn-ghost" data-add="section">＋ セクションを追加</button>
  </fieldset>

  <fieldset>
    <legend>よくある質問（FAQ・任意）</legend>
    <div id="faq" data-rows>{$faqRows}</div>
    <button type="button" class="btn btn-ghost" data-add="faq">＋ Q&amp;Aを追加</button>
  </fieldset>

  <label>関連記事スラッグ（カンマ区切り・任意。未指定なら同カテゴリから自動）
    <input type="text" name="related" value="{$related}" placeholder="例: walking-effects, naizou-shibou">
  </label>

  <fieldset>
    <legend>広告枠（任意）</legend>
    <label class="checkbox"><input type="checkbox" name="aff_enabled" id="aff_enabled"{$affChecked}> 広告枠を表示する</label>
    <div id="aff-fields" class="aff-fields">
      <div class="grid2">
        <label>ラベル<input type="text" name="aff_label" value="{$affLabel}"></label>
        <label>CTAボタン文言<input type="text" name="aff_cta" value="{$affCta}"></label>
      </div>
      <label>見出し<input type="text" name="aff_title" value="{$affTitle}"></label>
      <label>説明<input type="text" name="aff_desc" value="{$affDesc}"></label>
    </div>
  </fieldset>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">保存する</button>
    <a class="btn btn-ghost" href="{$base}">キャンセル</a>
    {$deleteBtn}
  </div>
</form>
HTML;
    // セクション/FAQ 追加用テンプレート（JSが複製）
    $inner .= section_template_html() . faq_template_html();
    return layout($heading, $isNew ? 'article' : 'dashboard', $inner);
}

function section_template_html(): string
{
    return <<<HTML
<template id="tpl-section">
<div class="sec-row" data-row>
  <div class="sec-row-head">
    <span class="drag">≡ 見出し</span>
    <input type="text" name="sections[__I__][h2]" class="sec-h2" placeholder="見出し（h2）">
    <input type="text" name="sections[__I__][id]" class="sec-id" placeholder="ID(任意)">
    <button type="button" class="mini danger" data-remove-row>削除</button>
  </div>
  <textarea name="sections[__I__][body]" class="richtext" rows="6" placeholder="本文（HTML可）"></textarea>
</div>
</template>
HTML;
}

function faq_template_html(): string
{
    return <<<HTML
<template id="tpl-faq">
<div class="faq-row" data-row>
  <div class="faq-row-head"><span>Q&amp;A</span><button type="button" class="mini danger" data-remove-row>削除</button></div>
  <input type="text" name="faq[__I__][q]" placeholder="質問">
  <textarea name="faq[__I__][a]" rows="2" placeholder="回答"></textarea>
</div>
</template>
<template id="tpl-page-section">
<div class="sec-row" data-row>
  <div class="sec-row-head">
    <span class="drag">≡ 見出し</span>
    <input type="text" name="sections[__I__][h2]" class="sec-h2" placeholder="見出し（h2）">
    <button type="button" class="mini danger" data-remove-row>削除</button>
  </div>
  <textarea name="sections[__I__][body]" class="richtext" rows="6" placeholder="本文（HTML可）"></textarea>
</div>
</template>
HTML;
}

function view_categories(array $content): string
{
    $base = admin_base();
    $csrf = csrf_field();
    $usage = [];
    foreach ($content['articles'] as $x) { $usage[$x['cat'] ?? ''] = ($usage[$x['cat'] ?? ''] ?? 0) + 1; }
    $rows = '';
    $ord = 0;
    foreach ($content['category_order'] as $key) {
        $c = $content['categories'][$key] ?? null;
        if (!$c) { continue; }
        $ord += 10;
        $n = $usage[$key] ?? 0;
        $delCell = $n > 0
            ? '<span class="muted">記事' . $n . '件</span>'
            : '<label class="checkbox small"><input type="checkbox" name="cats[' . h($key) . '][delete]" value="1"> 削除</label>';
        $rows .= '<tr>'
            . '<td class="key">' . h($key) . '</td>'
            . '<td><input type="text" name="cats[' . h($key) . '][emoji]" value="' . h($c['emoji'] ?? '') . '" class="emoji"></td>'
            . '<td><input type="text" name="cats[' . h($key) . '][name]" value="' . h($c['name'] ?? '') . '"></td>'
            . '<td><input type="text" name="cats[' . h($key) . '][desc]" value="' . h($c['desc'] ?? '') . '" class="wide"></td>'
            . '<td class="colors">'
              . '<input type="color" name="cats[' . h($key) . '][grad0]" value="' . h($c['grad'][0] ?? '#e3efe2') . '">'
              . '<input type="color" name="cats[' . h($key) . '][grad1]" value="' . h($c['grad'][1] ?? '#4f9e6b') . '">'
              . '<input type="color" name="cats[' . h($key) . '][fg]" value="' . h($c['fg'] ?? '#1b5e3f') . '">'
            . '</td>'
            . '<td><input type="number" name="cats[' . h($key) . '][order]" value="' . $ord . '" class="ord"></td>'
            . '<td>' . $delCell . '</td>'
            . '</tr>';
    }
    $inner = <<<HTML
<div class="page-head"><div><h1>カテゴリ</h1><p class="muted">並び順（小さいほど先）・名称・絵文字・説明・サムネ配色を編集できます。記事が紐づくカテゴリは削除できません。</p></div></div>
<form method="post" action="{$base}" class="form">
  {$csrf}<input type="hidden" name="action" value="save_categories">
  <table class="cat-table">
    <thead><tr><th>キー</th><th>絵文字</th><th>名称</th><th>説明</th><th>配色(背景2色/文字)</th><th>順</th><th></th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
  <fieldset>
    <legend>カテゴリを追加</legend>
    <div class="grid3">
      <label>キー（半角英数_）<input type="text" name="newkey" pattern="[a-z0-9_]*" placeholder="例: gear"></label>
      <label>絵文字<input type="text" name="newemoji" placeholder="🏷️"></label>
      <label>並び順<input type="number" name="neworder" value="999"></label>
    </div>
    <label>名称<input type="text" name="newname" placeholder="例: ウォーキング用品"></label>
    <label>説明<input type="text" name="newdesc"></label>
  </fieldset>
  <div class="form-actions"><button class="btn btn-primary" type="submit">保存する</button></div>
</form>
HTML;
    return layout('カテゴリ', 'categories', $inner);
}

function view_page(array $content, string $key): string
{
    $base = admin_base();
    $csrf = csrf_field();
    $page = $content['pages'][$key];
    $title = h($page['title'] ?? '');
    $desc = h($page['desc'] ?? '');
    $noidx = !empty($page['noindex']) ? ' checked' : '';
    $secRows = section_rows_html($page['sections'] ?? [], false);
    $label = $key === 'about' ? '運営者ページ' : 'プライバシーポリシー';
    $tpl = faq_template_html(); // includes tpl-page-section
    $inner = <<<HTML
<div class="page-head">
  <div><h1>{$label}を編集</h1><p class="muted">URL: /{$key}.html</p></div>
  <div class="page-head-actions"><a class="btn btn-ghost" href="../{$key}.html" target="_blank" rel="noopener">表示 ↗</a></div>
</div>
<form method="post" action="{$base}" class="form">
  {$csrf}<input type="hidden" name="action" value="save_page"><input type="hidden" name="key" value="{$key}">
  <div class="grid2">
    <label>ページタイトル<input type="text" name="title" value="{$title}"></label>
    <label class="checkbox" style="align-self:end"><input type="checkbox" name="noindex"{$noidx}> 検索エンジンに登録しない（noindex）</label>
  </div>
  <label>メタディスクリプション<input type="text" name="desc" value="{$desc}"></label>
  <fieldset>
    <legend>本文セクション</legend>
    <div id="sections" data-rows data-tpl="tpl-page-section">{$secRows}</div>
    <button type="button" class="btn btn-ghost" data-add="page-section">＋ セクションを追加</button>
  </fieldset>
  <div class="form-actions"><button class="btn btn-primary" type="submit">保存する</button><a class="btn btn-ghost" href="{$base}">戻る</a></div>
</form>
{$tpl}
HTML;
    return layout($label, $key === 'about' ? 'page_about' : 'page_privacy', $inner);
}

function view_site(array $content): string
{
    $base = admin_base();
    $csrf = csrf_field();
    $t = $content['top'];
    $s = $content['site'];
    $f = function (string $group, string $k, string $label, string $val, bool $area = false): string {
        $name = $group . '[' . $k . ']';
        if ($area) {
            return '<label>' . h($label) . '<textarea name="' . h($name) . '" rows="3">' . h($val) . '</textarea></label>';
        }
        return '<label>' . h($label) . '<input type="text" name="' . h($name) . '" value="' . h($val) . '"></label>';
    };
    $top = $f('top', 'hero_badge', 'ヒーロー：バッジ', $t['hero_badge'])
        . '<div class="grid3">'
        . $f('top', 'hero_title_1', 'ヒーロー見出し（前半）', $t['hero_title_1'])
        . $f('top', 'hero_accent', 'ヒーロー見出し（強調・クレイ色）', $t['hero_accent'])
        . $f('top', 'hero_title_2', 'ヒーロー見出し（後半）', $t['hero_title_2'])
        . '</div>'
        . $f('top', 'hero_lead', 'ヒーロー：リード文', $t['hero_lead'], true)
        . '<div class="grid2">' . $f('top', 'hero_btn1', 'ヒーロー：ボタン1', $t['hero_btn1']) . $f('top', 'hero_btn2', 'ヒーロー：ボタン2', $t['hero_btn2']) . '</div>'
        . '<div class="grid3">' . $f('top', 'pillars_eyebrow', '5本柱：見出し上', $t['pillars_eyebrow']) . $f('top', 'pillars_title', '5本柱：見出し', $t['pillars_title']) . $f('top', 'pillars_sub', '5本柱：サブ', $t['pillars_sub']) . '</div>'
        . '<div class="grid3">' . $f('top', 'pickup_eyebrow', '注目：見出し上', $t['pickup_eyebrow']) . $f('top', 'pickup_title', '注目：見出し', $t['pickup_title']) . $f('top', 'pickup_sub', '注目：サブ', $t['pickup_sub']) . '</div>'
        . $f('top', 'cta_title', 'CTA帯：見出し', $t['cta_title'])
        . $f('top', 'cta_sub', 'CTA帯：本文', $t['cta_sub'], true)
        . $f('top', 'cta_btn', 'CTA帯：ボタン', $t['cta_btn']);
    $site = $f('site', 'tagline', 'タグライン', $s['tagline'])
        . $f('site', 'description', 'サイト説明（メタ）', $s['description'], true)
        . '<div class="grid2">' . $f('site', 'author', '著者名', $s['author']) . $f('site', 'author_role', '著者の肩書', $s['author_role']) . '</div>'
        . '<div class="grid2">' . $f('site', 'org', '運営組織', $s['org']) . $f('site', 'org_url', '組織URL', $s['org_url']) . '</div>'
        . $f('site', 'x_url', 'X（旧Twitter）URL', $s['x_url']);
    $inner = <<<HTML
<div class="page-head"><div><h1>トップ／サイト文言</h1><p class="muted">トップページの見出し・リード・各セクション文言とサイト共通情報を編集できます。</p></div>
<div class="page-head-actions"><a class="btn btn-ghost" href="../index.html" target="_blank" rel="noopener">トップを見る ↗</a></div></div>
<form method="post" action="{$base}" class="form">
  {$csrf}<input type="hidden" name="action" value="save_site">
  <fieldset><legend>トップページ</legend>{$top}</fieldset>
  <fieldset><legend>サイト共通</legend>{$site}</fieldset>
  <div class="form-actions"><button class="btn btn-primary" type="submit">保存する</button></div>
</form>
HTML;
    return layout('トップ／サイト文言', 'site', $inner);
}

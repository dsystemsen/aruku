<?php
require_once __DIR__ . '/../render.php';
require_once __DIR__ . '/../inc/member.php';
require_once __DIR__ . '/../inc/posts.php';

$prefix = '../';
$me   = member_require_login($prefix);
$acts = aruku_activities();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!member_csrf_check($_POST['csrf'] ?? null)) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (($_POST['do'] ?? '') === 'delpost') {
        post_delete_owned((int) ($_POST['pid'] ?? 0), (int) $me['id']);
        header('Location: mypage.php?deleted=1');
        exit;
    } elseif (($_POST['do'] ?? '') === 'setgoal') {
        member_set_weekly_goal((int) $me['id'], (int) ($_POST['goal'] ?? 0));
        header('Location: mypage.php?goal=1');
        exit;
    } else {
        $date     = (string) ($_POST['log_date'] ?? '');
        $activity = (string) ($_POST['activity'] ?? '');
        $minutes  = (int) ($_POST['minutes'] ?? 0);
        $weight   = (float) ($_POST['weight'] ?? 0);
        $dateOk   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
        if (!$dateOk) {
            $error = '日付を正しく入力してください。';
        } elseif (!isset($acts[$activity])) {
            $error = '運動の種類を選択してください。';
        } elseif ($minutes < 1 || $minutes > 600) {
            $error = '運動時間は1〜600分で入力してください。';
        } elseif ($weight < 20 || $weight > 300) {
            $error = '体重は20〜300kgで入力してください。';
        } else {
            $kcal = aruku_calc_kcal($activity, $weight, $minutes, $me['sex']);
            $st = aruku_db()->prepare(
                'INSERT INTO activity_logs (member_id, log_date, activity, minutes, weight, kcal) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $st->execute([$me['id'], $date, $activity, $minutes, $weight, $kcal]);
            header('Location: mypage.php?added=1');
            exit;
        }
    }
}

$msg = isset($_GET['added']) ? '記録を追加しました。'
    : (isset($_GET['published']) ? 'コラムを公開しました。'
    : (isset($_GET['held']) ? '不適切な表現の可能性があるため、確認のため一時保留しました。運営の確認後に公開されます。'
    : (isset($_GET['draft']) ? '下書きを保存しました。下の「あなたのコラム」から続きを編集できます。'
    : (isset($_GET['posted']) ? 'コラムを公開しました。'
    : (isset($_GET['deleted']) ? '投稿を削除しました。'
    : (isset($_GET['goal']) ? '週間目標を保存しました。' : ''))))));

$db = aruku_db();
$st = $db->prepare('SELECT log_date, activity, minutes, weight, kcal FROM activity_logs WHERE member_id = ? ORDER BY log_date DESC, id DESC');
$st->execute([$me['id']]);
$logs = $st->fetchAll();

$st = $db->prepare('SELECT COALESCE(SUM(kcal),0) AS total, COUNT(DISTINCT log_date) AS days, COUNT(*) AS cnt FROM activity_logs WHERE member_id = ?');
$st->execute([$me['id']]);
$agg   = $st->fetch();
$total = number_format((float) $agg['total']);
$days  = (int) $agg['days'];
$cnt   = (int) $agg['cnt'];

$today   = date('Y-m-d');
$actOpts = '';
foreach ($acts as $k => $a) {
    $actOpts .= '<option value="' . h($k) . '">' . h($a['label']) . '</option>';
}

$rows = '';
foreach ($logs as $l) {
    $label = $acts[$l['activity']]['label'] ?? $l['activity'];
    $rows .= '<tr>'
        . '<td>' . h($l['log_date']) . '</td>'
        . '<td>' . h($label) . '</td>'
        . '<td>' . h($l['minutes']) . '分</td>'
        . '<td>' . h(number_format((float) $l['weight'], 1)) . 'kg</td>'
        . '<td><b>' . h(number_format(round((float) $l['kcal']))) . '</b> kcal</td>'
        . '</tr>';
}
if ($rows === '') {
    $rows = '<tr><td colspan="5" class="log-empty">まだ記録がありません。上のフォームから追加してください。</td></tr>';
}

// 自分の投稿一覧
$myPosts = posts_by_member((int) $me['id']);
$statusLabel = ['pending' => '承認待ち', 'published' => '公開中', 'rejected' => '非公開', 'draft' => '下書き'];
$postRows = '';
foreach ($myPosts as $p) {
    $stt = (string) $p['status'];
    $badge = '<span class="post-status post-status-' . h($stt) . '">' . h($statusLabel[$stt] ?? $stt) . '</span>';
    $titleCell = $stt === 'published'
        ? '<a href="../posts/' . (int) $p['id'] . '">' . h($p['title']) . '</a>'
        : '<a href="post.php?id=' . (int) $p['id'] . '">' . h($p['title']) . '</a>';
    $actions = '<a href="post.php?id=' . (int) $p['id'] . '" class="post-act-edit">編集</a>'
        . '<form method="post" class="post-act-del" onsubmit="return confirm(\'この投稿を削除しますか？\');">'
        . '<input type="hidden" name="csrf" value="' . member_csrf_token() . '">'
        . '<input type="hidden" name="do" value="delpost"><input type="hidden" name="pid" value="' . (int) $p['id'] . '">'
        . '<button type="submit">削除</button></form>';
    $postRows .= '<tr><td>' . h(substr((string) $p['created_at'], 0, 10)) . '</td><td>' . $titleCell . '</td><td>' . $badge . '</td><td class="post-act">' . $actions . '</td></tr>';
}
$postsTable = $postRows !== ''
    ? '<div class="column-table-wrap"><table class="column-table log-table"><thead><tr><th>日付</th><th>タイトル</th><th>状態</th><th>操作</th></tr></thead><tbody>' . $postRows . '</tbody></table></div>'
    : '<p class="log-empty" style="text-align:left;padding:14px 0;">まだ投稿がありません。「コラムを書く」から投稿できます。</p>';


// 期間別サマリー＆週間目標
$kToday = round(logs_kcal_since((int) $me['id'], date('Y-m-d')));
$kWeek  = round(logs_kcal_since((int) $me['id'], date('Y-m-d', strtotime('monday this week'))));
$kMonth = round(logs_kcal_since((int) $me['id'], date('Y-m-01')));
$goal = (int) ($me['weekly_goal'] ?? 0);
$pct = $goal > 0 ? min(100, (int) round($kWeek / $goal * 100)) : 0;

$token   = member_csrf_token();
$summary = '<div class="summary-grid">'
    . '<div class="sum-card"><span class="sum-num">' . number_format($kToday) . '</span><span class="sum-label">今日 kcal</span></div>'
    . '<div class="sum-card"><span class="sum-num">' . number_format($kWeek) . '</span><span class="sum-label">今週 kcal</span></div>'
    . '<div class="sum-card"><span class="sum-num">' . number_format($kMonth) . '</span><span class="sum-label">今月 kcal</span></div>'
    . '</div>';
$summary .= '<div class="record-title" style="margin-top:6px;font-weight:bold;">目標設定</div>';
$summary .= $goal > 0
    ? '<div class="goal-wrap"><div class="goal-label">週間目標 ' . number_format($goal) . ' kcal に対して <b>' . $pct . '%</b>（' . number_format($kWeek) . ' kcal）</div><div class="goal-bar"><span style="width:' . $pct . '%"></span></div></div>'
    : '<p class="goal-none">週間目標を設定すると、今週の達成率を表示します。</p>';
$summary .= '<form method="post" class="goal-form"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="do" value="setgoal">'
    . '<input type="number" name="goal" min="0" max="100000" value="' . $goal . '" placeholder="週間目標 kcal"><button type="submit" class="lp-btn lp-btn-secondary">目標を設定</button></form>';

// 直近15日の消費カロリー棒グラフ
$span = [];
for ($i = 14; $i >= 0; $i--) {
    $span[date('Y-m-d', strtotime("-$i days"))] = 0;
}
$daily = logs_daily_since((int) $me['id'], (string) array_key_first($span));
foreach ($daily as $d => $k) {
    if (isset($span[$d])) {
        $span[$d] = (int) round($k);
    }
}
$maxK = max(1, max($span));
$bars = '';
foreach ($span as $d => $k) {
    $hpct = (int) round($k / $maxK * 100);
    $bars .= '<div class="chart-col" title="' . h($d) . '：' . number_format($k) . ' kcal">'
        . '<div class="chart-bar" style="height:' . $hpct . '%"></div>'
        . '<span class="chart-x">' . (int) substr($d, 8, 2) . '</span></div>';
}
$chart = '<div class="record-title" style="margin-top:6px;font-weight:bold;">直近15日の消費カロリー</div><div class="kcal-chart">' . $bars . '</div>';
$summary .= $chart;

// 月間カレンダー（当月・記録のある日をハイライト）
$monthStart = date('Y-m-01');
$daysInMonth = (int) date('t');
$firstDow = (int) date('w', strtotime($monthStart)); // 0=日
$monthKcalMap = logs_daily_since((int) $me['id'], $monthStart);
$dowHtml = '';
foreach (['日', '月', '火', '水', '木', '金', '土'] as $w) {
    $dowHtml .= '<div class="cal-dow">' . $w . '</div>';
}
$calCells = '';
for ($i = 0; $i < $firstDow; $i++) {
    $calCells .= '<div class="cal-cell empty"></div>';
}
for ($dnum = 1; $dnum <= $daysInMonth; $dnum++) {
    $ds = date('Y-m', strtotime($monthStart)) . sprintf('-%02d', $dnum);
    $k = isset($monthKcalMap[$ds]) ? (int) round($monthKcalMap[$ds]) : 0;
    $cls = 'cal-cell' . ($k > 0 ? ' has' : '') . ($ds === date('Y-m-d') ? ' today' : '');
    $calCells .= '<div class="' . $cls . '" title="' . $ds . ($k > 0 ? '：' . number_format($k) . 'kcal' : '') . '">'
        . '<span class="cal-d">' . $dnum . '</span>' . ($k > 0 ? '<span class="cal-k">' . number_format($k) . '</span>' : '') . '</div>';
}
$summary .= '<div class="record-title" style="margin-top:6px;font-weight:bold;">' . date('Y年n月') . ' の記録カレンダー</div>'
    . '<div class="calendar">' . $dowHtml . $calCells . '</div>';

// 体重の推移（直近15日・折れ線グラフ）
$wmap = logs_weight_series((int) $me['id'], date('Y-m-d', strtotime('-14 days')));
if ($wmap) {
    $wvals = array_values($wmap);
    $wmin = min($wvals);
    $wmax = max($wvals);
    $range = max(0.1, $wmax - $wmin);
    $pts = [];
    for ($j = 0; $j <= 14; $j++) {
        $d = date('Y-m-d', strtotime('-' . (14 - $j) . ' days'));
        if (isset($wmap[$d])) {
            $x = 10 + ($j / 14) * 300;
            $y = 80 - (($wmap[$d] - $wmin) / $range) * 65;
            $pts[] = [$x, $y, $wmap[$d], $d];
        }
    }
    $poly = implode(' ', array_map(fn($p) => round($p[0], 1) . ',' . round($p[1], 1), $pts));
    $dots = '';
    foreach ($pts as $p) {
        $dots .= '<circle cx="' . round($p[0], 1) . '" cy="' . round($p[1], 1) . '" r="3.2" fill="#ff6333"><title>' . h($p[3]) . '：' . $p[2] . 'kg</title></circle>';
    }
    $svg = '<svg viewBox="0 0 320 90" class="weight-svg" role="img" aria-label="体重の推移">'
        . ($poly ? '<polyline points="' . $poly . '" fill="none" stroke="#ff6333" stroke-width="2" stroke-linejoin="round"/>' : '')
        . $dots . '</svg>';
    $summary .= '<div class="record-title" style="margin-top:6px;">体重の推移（直近15日）</div>' . $svg
        . '<p class="goal-none">範囲 ' . $wmin . '〜' . $wmax . ' kg</p>';
}

$msgClass = isset($_GET['held']) ? 'hold-msg' : 'auth-ok';
$msgHtml = $msg ? '<p class="' . $msgClass . '">' . h($msg) . '</p>' : '';
$errHtml = $error ? '<p class="auth-error">' . h($error) . '</p>' : '';
$nick    = h($me['nickname']);

$body = <<<HTML
<div class="member-head">
  <h1>マイページ</h1>
  <p>{$nick} さん</p>
</div>

<h2 class="record-title record-tool-title">これまでの総消費カロリー</h2>
<div class="total-banner">
  <span class="total-value"><b>{$total}</b> kcal</span>
  <span class="total-sub">記録 {$days} 日 ／ {$cnt} 件</span>
</div>

<div class="calc-cta" style="margin-top:20px;">
  <p class="calc-cta-lead">🏆 <b>月間あるくチャレンジ</b>：今月の消費カロリーで、みんなとランキング！目標達成でバッジも獲得できます。</p>
  <a href="ranking.php" class="lp-btn lp-btn-primary">ランキングを見る →</a>
</div>

$msgHtml
$errHtml

<h2 class="record-title record-tool-title">記録を追加</h2>
<div class="calc-tool record-tool">
  <form method="post">
    <input type="hidden" name="csrf" value="$token">
    <div class="calc-grid calc-grid--2x2">
      <label class="calc-field"><span>日付</span><input type="date" name="log_date" value="$today" required></label>
      <label class="calc-field"><span>運動の種類</span><select name="activity">$actOpts</select></label>
      <label class="calc-field"><span>運動時間（分）</span><input type="number" name="minutes" min="1" max="600" value="30" required></label>
      <label class="calc-field"><span>体重（kg）</span><input type="number" name="weight" min="20" max="300" step="0.1" value="60" required></label>
    </div>
    <div class="record-actions"><button type="submit" class="lp-btn lp-btn-primary">この内容で記録する</button></div>
  </form>
</div>

{$summary}

<h2 class="record-title record-tool-title" style="margin-top:48px;">記録の履歴</h2>
<div class="column-table-wrap log-table-wrap">
  <table class="column-table log-table">
    <thead><tr><th>日付</th><th>種類</th><th>時間</th><th>体重</th><th>消費</th></tr></thead>
    <tbody>$rows</tbody>
  </table>
</div>

<h2 class="record-title record-tool-title" style="margin-top:48px;">あなたのコラム</h2>
$postsTable
<div class="record-actions record-actions--left"><a href="post.php" class="lp-btn lp-btn-primary mypage-wide-btn">＋ コラムを書く</a></div>

<h2 class="record-title record-tool-title" style="margin-top:48px;">CSVダウンロード</h2>
<div class="record-actions record-actions--left"><a href="export.php" class="lp-btn lp-btn-primary mypage-wide-btn">ダウンロード</a></div>

<h2 class="record-title record-tool-title" style="margin-top:48px;">プロフィール編集</h2>
<p class="cancel-note">ニックネーム・性別・パスワードの変更ができます。</p>
<div class="record-actions record-actions--left"><a href="profile.php" class="lp-btn lp-btn-primary mypage-wide-btn">プロフィールを編集</a></div>

<h2 class="record-title record-tool-title" style="margin-top:48px;">解約</h2>
<div class="calc-tool">
  <p class="cancel-note">あるくを退会（解約）します。退会すると、これまでの記録・投稿などのデータが削除され、元に戻せませんのでご注意ください。</p>
  <div style="display:flex;justify-content:flex-end;"><a href="cancel.php" class="lp-btn cancel-btn">解約手続きへ進む</a></div>
</div>
HTML;

member_render_page($prefix, 'マイページ', $body);

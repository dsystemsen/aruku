<?php
// 月間あるくチャレンジ（消費カロリーのランキング）— 会員ページ
require_once __DIR__ . '/../render.php';
require_once __DIR__ . '/../inc/member.php';

$prefix = '../';
$me = member_require_login($prefix);

$msg = '';
$error = '';

// オプトイン参加の ON/OFF 切替（CSRF必須）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!member_csrf_check($_POST['csrf'] ?? null)) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (($_POST['do'] ?? '') === 'optin') {
        $on = ((string) ($_POST['value'] ?? '')) === '1';
        ranking_optin_set((int) $me['id'], $on);
        header('Location: ranking.php?' . ($on ? 'joined=1' : 'left=1'));
        exit;
    }
}

if (isset($_GET['joined'])) { $msg = 'ランキングに参加しました。今月の記録で順位が表示されます。'; }
if (isset($_GET['left']))   { $msg = 'ランキングから外れました。あなたの記録は一覧に表示されません。'; }

// 最新の参加状態を取得（POST直後の反映のため再読込）
$st = aruku_db()->prepare('SELECT ranking_optin FROM members WHERE id = ?');
$st->execute([(int) $me['id']]);
$optin = ((int) $st->fetchColumn()) === 1;

[$start, $next, $ym, $monthLabel] = ranking_month_bounds();
$target = ranking_monthly_target();
$my = ranking_my_stats((int) $me['id'], $start, $next);

// 目標達成ならバッジ付与（オプトイン時のみ）
$achieved = false;
if ($optin) {
    $achieved = ranking_award_badge_if_earned((int) $me['id'], $ym, (float) $my['kcal']);
}

$top = ranking_top($start, $next, 100);

$token = h(member_csrf_token());
$nick  = h($me['nickname']);
$msgHtml = $msg ? '<p class="auth-ok">' . h($msg) . '</p>' : '';
$errHtml = $error ? '<p class="auth-error">' . h($error) . '</p>' : '';

// 参加トグル
if ($optin) {
    $toggle = '<form method="post" style="margin:0;">' . '<input type="hidden" name="csrf" value="' . $token . '">'
        . '<input type="hidden" name="do" value="optin"><input type="hidden" name="value" value="0">'
        . '<button type="submit" class="lp-btn lp-btn-secondary">ランキングから外れる</button></form>';
    $statusLine = '<span class="rank-status rank-status--on">✓ 参加中</span>';
} else {
    $toggle = '<form method="post" style="margin:0;">' . '<input type="hidden" name="csrf" value="' . $token . '">'
        . '<input type="hidden" name="do" value="optin"><input type="hidden" name="value" value="1">'
        . '<button type="submit" class="lp-btn lp-btn-primary">ランキングに参加する</button></form>';
    $statusLine = '<span class="rank-status rank-status--off">未参加</span>';
}

// 自分の状況カード
$myCard = '';
if ($optin) {
    $kc = (int) round($my['kcal']);
    $pct = $target > 0 ? min(100, (int) round($kc / $target * 100)) : 0;
    $rankText = $my['rank'] !== null
        ? '<b>' . $my['rank'] . '</b> 位　<small>（参加 ' . $my['participants'] . ' 人中）</small>'
        : 'まだ今月の記録がありません';
    $badgeText = $achieved
        ? '<span class="rank-badge-won">🏅 ' . h($monthLabel) . 'チャレンジ達成！</span>'
        : 'あと <b>' . max(0, $target - $kc) . '</b> kcal で達成';
    $myCard = <<<HTML
<div class="rank-mycard">
  <div class="rank-mycard-row"><span>今月の消費カロリー</span><span><b>{$kc}</b> kcal</span></div>
  <div class="rank-mycard-row"><span>あなたの順位</span><span>{$rankText}</span></div>
  <div class="rank-progress"><div class="rank-progress-bar" style="width:{$pct}%"></div></div>
  <div class="rank-mycard-foot"><span>チャレンジ目標 {$target} kcal</span><span>{$badgeText}</span></div>
</div>
HTML;
} else {
    $myCard = '<div class="rank-mycard rank-mycard--off"><p>「ランキングに参加する」をオンにすると、今月の消費カロリーで順位がつき、目標達成でバッジがもらえます。あなたのニックネームと消費カロリーが、ログイン会員に表示されます。</p></div>';
}

// リーダーボード
$rows = '';
if ($top) {
    $i = 0;
    foreach ($top as $r) {
        $i++;
        $medal = $i === 1 ? '🥇' : ($i === 2 ? '🥈' : ($i === 3 ? '🥉' : (string) $i));
        $isMe = ((int) $r['id'] === (int) $me['id']);
        $cls = $isMe ? ' class="rank-me"' : '';
        $meTag = $isMe ? ' <span class="rank-you">あなた</span>' : '';
        $rows .= '<tr' . $cls . '><td class="rank-no">' . $medal . '</td><td>' . h($r['nickname']) . $meTag . '</td><td class="rank-kcal">' . number_format((int) round($r['kcal'])) . ' kcal</td></tr>';
    }
} else {
    $rows = '<tr><td colspan="3" class="rank-empty">今月はまだ参加者の記録がありません。最初の記録で1位を目指しましょう！</td></tr>';
}

$body = <<<HTML
<div class="member-head">
  <h1>🏆 月間あるくチャレンジ</h1>
  <p>{$monthLabel}の消費カロリーで競う、みんなのランキング</p>
</div>
{$msgHtml}{$errHtml}

<div class="rank-optin">
  <div class="rank-optin-left">
    <div class="rank-optin-title">ランキングへの参加 {$statusLine}</div>
    <p class="rank-optin-note">参加は任意です。参加すると、あなたの<strong>ニックネームと当月の消費カロリー</strong>がログイン会員向けのランキングに表示されます。いつでも外せます。</p>
  </div>
  <div class="rank-optin-right">{$toggle}</div>
</div>

{$myCard}

<h2 class="record-title record-tool-title" style="margin-top:40px;">今月のランキング（{$monthLabel}）</h2>
<div class="record-table-wrap">
  <table class="column-table rank-table">
    <thead><tr><th>順位</th><th>ニックネーム</th><th>今月の消費</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
<p class="cancel-note" style="margin-top:14px;">※ 集計は毎月1日にリセットされます。消費カロリーはマイページの記録（運動の種類・時間・体重）から自動集計します。数値は一般的な式に基づく目安です。</p>

<div class="record-actions record-actions--left" style="margin-top:24px;">
  <a href="mypage.php" class="lp-btn lp-btn-primary mypage-wide-btn">記録する（マイページ）</a>
</div>
HTML;

member_render_page($prefix, '月間あるくチャレンジ', $body);

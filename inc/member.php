<?php
/**
 * aruku 会員機能（一般ユーザー）
 * - 会員登録 / ログイン / ログアウト（セッション・CSRF・bcrypt）
 * - 消費カロリー計算（トップの計算ツールと同一ロジック）
 * ※ 管理者(admin)のセッションとは別クッキー名で分離。
 */
require_once __DIR__ . '/db.php';

function member_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('aruku_member');
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function member_csrf_token(): string
{
    member_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function member_csrf_check(?string $t): bool
{
    member_session_start();
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

function member_current(bool $fresh = false): ?array
{
    member_session_start();
    if (empty($_SESSION['member_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $st = aruku_db()->prepare('SELECT id, email, nickname, sex, weekly_goal FROM members WHERE id = ?');
    $st->execute([$_SESSION['member_id']]);
    $cache = $st->fetch() ?: null;
    if (!$cache) {
        unset($_SESSION['member_id']);
    }
    return $cache;
}

/** 管理者メール（この一覧の会員＝管理者）。 */
function aruku_admin_emails(): array
{
    return ['yugo_saitou_g@dsystemsen.com'];
}
/** 指定会員（または現在の会員）が管理者か。 */
function member_is_admin(?array $m): bool
{
    if (!$m || empty($m['email'])) {
        return false;
    }
    return in_array(strtolower(trim((string) $m['email'])), aruku_admin_emails(), true);
}

function member_require_login(string $prefix): array
{
    $me = member_current();
    if (!$me) {
        header('Location: ' . $prefix . 'member/login.php');
        exit;
    }
    return $me;
}

function member_register(string $email, string $password, string $nickname, string $sex): array
{
    $email = trim($email);
    $nickname = trim($nickname);
    $sex = ($sex === 'female') ? 'female' : 'male';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'メールアドレスの形式が正しくありません。'];
    }
    if (mb_strlen($password) < 8) {
        return ['ok' => false, 'error' => 'パスワードは8文字以上にしてください。'];
    }
    if ($nickname === '' || mb_strlen($nickname) > 30) {
        return ['ok' => false, 'error' => 'ニックネームは1〜30文字で入力してください。'];
    }
    $db = aruku_db();
    $st = $db->prepare('SELECT id FROM members WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetch()) {
        return ['ok' => false, 'error' => 'このメールアドレスは既に登録されています。'];
    }
    $st = $db->prepare('INSERT INTO members (email, password_hash, nickname, sex) VALUES (?, ?, ?, ?)');
    $st->execute([$email, password_hash($password, PASSWORD_BCRYPT), $nickname, $sex]);
    member_session_start();
    session_regenerate_id(true);
    $_SESSION['member_id'] = (int) $db->lastInsertId();
    return ['ok' => true];
}

function member_login(string $email, string $password): array
{
    $email = trim($email);
    $st = aruku_db()->prepare('SELECT id, password_hash FROM members WHERE email = ?');
    $st->execute([$email]);
    $m = $st->fetch();
    if (!$m || !password_verify($password, $m['password_hash'])) {
        return ['ok' => false, 'error' => 'メールアドレスまたはパスワードが正しくありません。'];
    }
    member_session_start();
    session_regenerate_id(true);
    $_SESSION['member_id'] = (int) $m['id'];
    return ['ok' => true];
}

function member_logout(): void
{
    member_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ===== プロフィール編集 ===== */
function member_update_profile(int $memberId, string $nickname, string $sex): array
{
    $nickname = trim($nickname);
    $sex = ($sex === 'female') ? 'female' : 'male';
    if ($nickname === '' || mb_strlen($nickname) > 30) {
        return ['ok' => false, 'error' => 'ニックネームは1〜30文字で入力してください。'];
    }
    aruku_db()->prepare('UPDATE members SET nickname = ?, sex = ? WHERE id = ?')
        ->execute([$nickname, $sex, $memberId]);
    return ['ok' => true];
}

function member_change_password(int $memberId, string $current, string $new): array
{
    if (mb_strlen($new) < 8) {
        return ['ok' => false, 'error' => '新しいパスワードは8文字以上にしてください。'];
    }
    $db = aruku_db();
    $st = $db->prepare('SELECT password_hash FROM members WHERE id = ?');
    $st->execute([$memberId]);
    $row = $st->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) {
        return ['ok' => false, 'error' => '現在のパスワードが正しくありません。'];
    }
    $db->prepare('UPDATE members SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_BCRYPT), $memberId]);
    return ['ok' => true];
}

/* ===== 記録の集計・週間目標 ===== */
function logs_kcal_since(int $memberId, string $sinceDate): float
{
    $st = aruku_db()->prepare('SELECT COALESCE(SUM(kcal),0) FROM activity_logs WHERE member_id = ? AND log_date >= ?');
    $st->execute([$memberId, $sinceDate]);
    return (float) $st->fetchColumn();
}
function member_set_weekly_goal(int $memberId, int $kcal): void
{
    $kcal = max(0, min(100000, $kcal));
    aruku_db()->prepare('UPDATE members SET weekly_goal = ? WHERE id = ?')->execute([$kcal, $memberId]);
}
/** 日付→消費kcal合計のマップ（$sinceDate 以降）。 */
function logs_daily_since(int $memberId, string $sinceDate): array
{
    $st = aruku_db()->prepare('SELECT log_date, SUM(kcal) k FROM activity_logs WHERE member_id = ? AND log_date >= ? GROUP BY log_date');
    $st->execute([$memberId, $sinceDate]);
    $m = [];
    foreach ($st as $r) {
        $m[(string) $r['log_date']] = (float) $r['k'];
    }
    return $m;
}
/** 日付→体重のマップ（同日複数なら後の記録で上書き）。 */
function logs_weight_series(int $memberId, string $sinceDate): array
{
    $st = aruku_db()->prepare('SELECT log_date, weight FROM activity_logs WHERE member_id = ? AND log_date >= ? AND weight IS NOT NULL ORDER BY log_date ASC, id ASC');
    $st->execute([$memberId, $sinceDate]);
    $m = [];
    foreach ($st as $r) {
        $m[(string) $r['log_date']] = (float) $r['weight'];
    }
    return $m;
}
/** 直近の連続記録日数（最新の記録日から遡って連続している日数）。 */
function member_streak(int $memberId): int
{
    $st = aruku_db()->prepare('SELECT DISTINCT log_date FROM activity_logs WHERE member_id = ? ORDER BY log_date DESC');
    $st->execute([$memberId]);
    $dates = array_column($st->fetchAll(), 'log_date');
    if (!$dates) {
        return 0;
    }
    $streak = 0;
    $expected = (string) $dates[0];
    foreach ($dates as $d) {
        if ((string) $d === $expected) {
            $streak++;
            $expected = date('Y-m-d', strtotime($d . ' -1 day'));
        } else {
            break;
        }
    }
    return $streak;
}

/* ===== スパム・不正対策 ===== */

function member_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0');
}

/**
 * 簡易レートリミッタ（data/.ratelimit.json、ファイルロック）。
 * $key の操作が直近 $windowSec 秒で $max 回未満なら true（許可・記録）、超過なら false。
 * 障害時は fail-open（正規ユーザーを巻き込まない）。
 */
function aruku_ratelimit(string $key, int $max, int $windowSec): bool
{
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/.ratelimit.json';
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return true;
    }
    flock($fp, LOCK_EX);
    $raw  = stream_get_contents($fp) ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
    }
    $now = time();
    // 古いキーを掃除（ファイル肥大化防止）
    foreach ($data as $k => $times) {
        $data[$k] = array_values(array_filter((array) $times, fn($t) => $t > $now - max($windowSec, 86400)));
        if (!$data[$k]) {
            unset($data[$k]);
        }
    }
    $arr = array_values(array_filter($data[$key] ?? [], fn($t) => $t > $now - $windowSec));
    $allowed = count($arr) < $max;
    if ($allowed) {
        $arr[] = $now;
    }
    $data[$key] = $arr;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
    return $allowed;
}

/** ハニーポット（hp_url）に入力があればボットとみなす。 */
function aruku_honeypot_filled(): bool
{
    return isset($_POST['hp_url']) && trim((string) $_POST['hp_url']) !== '';
}

/** フォームに差し込むハニーポット入力（CSSで画面外に隠す）。 */
function aruku_honeypot_field(): string
{
    return '<div class="hp-field" aria-hidden="true">'
        . '<label>このフィールドは入力しないでください'
        . '<input type="text" name="hp_url" tabindex="-1" autocomplete="off"></label></div>';
}

/* ===== 消費カロリー計算（トップの計算ツールと統一） ===== */

function aruku_activities(): array
{
    return [
        'walking'   => ['label' => 'ウォーキング（時速約4km）', 'met' => 3.5],
        'hayaaruki' => ['label' => '早歩き（時速約6.5km）',   'met' => 5.0],
        'jogging'   => ['label' => 'ジョギング（時速約8km）',  'met' => 8.3],
        'running'   => ['label' => 'ランニング（時速約10km）', 'met' => 10.0],
    ];
}

function aruku_calc_kcal(string $activity, float $weight, int $minutes, string $sex): float
{
    $acts = aruku_activities();
    $met  = $acts[$activity]['met'] ?? 0.0;
    $sexF = ($sex === 'female') ? 0.95 : 1.0;
    return $met * $weight * ($minutes / 60) * 1.05 * $sexF;
}

/* ===== 会員ページの共通レイアウト（render.php の head/footer を流用） ===== */

function member_render_page(string $prefix, string $title, string $bodyHtml, array $opts = []): void
{
    $s = site();
    $desc      = $opts['desc'] ?? $title;
    $robots    = $opts['robots'] ?? 'noindex, nofollow';
    $ogType    = $opts['ogType'] ?? 'website';
    $canonical = $opts['canonical'] ?? $s['url'];
    $ogImage   = $opts['ogImage'] ?? '';
    $jsonld    = $opts['jsonld'] ?? null;
    $headExtra = $opts['headExtra'] ?? '';
    echo head_html($prefix, $title . '｜あるく', $desc, $canonical, '', $jsonld, $ogType, $robots, $ogImage, $headExtra);
    // 独自パンくずを持つページ（記事ページ等）は noCrumb で自動パンくずを抑止
    $crumb = ($opts['noCrumb'] ?? false)
        ? ''
        : '<nav class="column-breadcrumb" aria-label="パンくず"><a href="' . $prefix . 'index.html">トップ</a> ／ <span>' . h($title) . '</span></nav>';
    echo '<main class="member-wrap">' . $crumb . $bodyHtml . '</main>';
    echo footer_html($prefix);
    echo '<script src="' . $prefix . 'assets/app.js?v=20260605" defer></script></body></html>';
}

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

<?php
/**
 * aruku 管理画面 — 認証・ログイン試行制限
 */
declare(strict_types=1);

function throttle_path(): string
{
    return cms_dir() . '/.throttle.json';
}

function throttle_load(): array
{
    $p = throttle_path();
    if (is_file($p)) {
        $a = json_decode((string)@file_get_contents($p), true);
        if (is_array($a)) {
            return $a;
        }
    }
    return [];
}

function throttle_save(array $a): void
{
    cms_prepare_dir();
    @file_put_contents(throttle_path(), json_encode($a), LOCK_EX);
}

function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/** ロック中なら残り秒数、そうでなければ 0 を返す。 */
function login_locked_seconds(array $config): int
{
    $a   = throttle_load();
    $ip  = client_ip();
    $rec = $a[$ip] ?? null;
    if (!$rec) {
        return 0;
    }
    $max    = (int)$config['login_max_attempts'];
    $window = (int)$config['login_lockout_min'] * 60;
    if (($rec['count'] ?? 0) >= $max) {
        $elapsed = time() - (int)($rec['last'] ?? 0);
        if ($elapsed < $window) {
            return $window - $elapsed;
        }
    }
    return 0;
}

function login_record_fail(): void
{
    $a  = throttle_load();
    $ip = client_ip();
    $rec = $a[$ip] ?? ['count' => 0, 'last' => 0];
    // ウィンドウ外なら数字をリセット（古い失敗は流す）
    $rec['count'] = (int)($rec['count'] ?? 0) + 1;
    $rec['last']  = time();
    $a[$ip] = $rec;
    // エントリが増えすぎないよう古いものを掃除
    if (count($a) > 200) {
        $a = array_slice($a, -100, null, true);
    }
    throttle_save($a);
}

function login_clear(): void
{
    $a  = throttle_load();
    $ip = client_ip();
    if (isset($a[$ip])) {
        unset($a[$ip]);
        throttle_save($a);
    }
}

/**
 * ログイン試行。
 * @return array{ok:bool, locked?:int}
 */
function attempt_login(string $email, string $password, array $config): array
{
    $locked = login_locked_seconds($config);
    if ($locked > 0) {
        return ['ok' => false, 'locked' => $locked];
    }
    $emailOk = hash_equals(
        strtolower(trim($config['email'])),
        strtolower(trim($email))
    );
    $passOk = password_verify($password, $config['password_hash']);
    if ($emailOk && $passOk) {
        session_regenerate_id(true);
        $_SESSION['admin_ok']    = true;
        $_SESSION['admin_email'] = $config['email'];
        $_SESSION['admin_login'] = time();
        login_clear();
        return ['ok' => true];
    }
    login_record_fail();
    return ['ok' => false];
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect(admin_base() . '?p=login');
    }
}

function do_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
    }
    session_destroy();
}

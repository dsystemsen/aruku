<?php
/**
 * aruku 管理画面 — ブートストラップ
 * セッション開始・設定読込・コンテンツ層(render.php/cms.php)読込・共通ヘルパー
 */
declare(strict_types=1);

$CONFIG = require __DIR__ . '/config.php';

// コンテンツ層（cms_load / cms_save / aruku_data / site など）
require_once __DIR__ . '/../../render.php';

// ---- セッション（HTTPSではsecure付き） ----
$IS_HTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');

session_name($CONFIG['session_name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/admin',
    'secure'   => $IS_HTTPS,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ---- ヘルパー ----
function h($s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && is_string($_POST['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function admin_base(): string
{
    // 例: /admin/  （末尾スラッシュ付き）
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php')), '/');
    return $dir . '/';
}

function redirect(string $path): void
{
    header('Location: ' . $path, true, 302);
    exit;
}

function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_ok']) && $_SESSION['admin_ok'] === true;
}

<?php
/**
 * CTAクリックの軽量計測（自前・Cookie不要・外部送信なし）。
 * 1クリック1行を cta_clicks に記録し、集計は GROUP BY で行う。
 * 不正キーの混入・テーブル肥大を防ぐため、許可キーのホワイトリストで弾く。
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** 計測対象として認める CTA キー（ラベルは管理画面の表示にも使う）。 */
function cta_labels(): array
{
    return [
        'hero_register'   => 'ヒーロー：無料で記録を始める',
        'hero_columns'    => 'ヒーロー：コラムを読む',
        'calc_register'   => 'カロリー計算結果：無料で記録を始める',
        'bottom_register' => '最下部CTA帯：無料で記録を始める',
        'bottom_calorie'  => '最下部CTA帯：歩数別カロリー表',
        'mobile_register' => 'モバイル追従バー：無料で始める',
        'nav_register'    => 'ヘッダー：無料ではじめる',
        'nav_login'       => 'ヘッダー：ログイン',
    ];
}

/** 1クリックを記録。許可キー以外・失敗時は false（呼び出し側は無視してよい）。 */
function cta_log(string $key, string $page = ''): bool
{
    $key = strtolower(trim($key));
    if (!array_key_exists($key, cta_labels())) {
        return false;
    }
    // page は内部表示用の軽い参考値。制御文字や長すぎる値を除去。
    $page = preg_replace('/[^\x21-\x7e]/', '', $page);
    $page = substr((string)$page, 0, 120);
    try {
        $st = aruku_db()->prepare('INSERT INTO cta_clicks (cta_key, page) VALUES (?, ?)');
        $st->execute([$key, $page]);
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/** キー別の合計／直近30日のクリック数を返す。 */
function cta_counts(): array
{
    $pdo = aruku_db();
    $total = [];
    foreach ($pdo->query('SELECT cta_key, COUNT(*) AS c FROM cta_clicks GROUP BY cta_key') as $r) {
        $total[$r['cta_key']] = (int)$r['c'];
    }
    $d30 = [];
    $since = date('Y-m-d H:i:s', time() - 30 * 86400);
    $st = $pdo->prepare('SELECT cta_key, COUNT(*) AS c FROM cta_clicks WHERE created_at >= ? GROUP BY cta_key');
    $st->execute([$since]);
    foreach ($st->fetchAll() as $r) {
        $d30[$r['cta_key']] = (int)$r['c'];
    }
    return ['total' => $total, 'd30' => $d30];
}

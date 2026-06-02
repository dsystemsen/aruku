<?php
/**
 * あるく — コンテンツストア（CMSデータ層）
 * ----------------------------------------------------------------
 * サイトの編集可能データを data/content.json で一元管理する。
 *   - 初回アクセス時、articles.php（＋既定文言）から自動シードして
 *     data/content.json を生成する（サーバ側がデータの所有者）。
 *   - 管理画面（/admin/）がこのファイルの関数で読み書きする。
 *   - data/content.json が壊れた/無い場合は articles.php と既定値へ
 *     フォールバックするため、サイトが落ちない。
 *
 * ※ このファイルは .htaccess により直接アクセス禁止。
 */

function cms_dir(): string          { return __DIR__ . '/data'; }
function cms_content_path(): string { return cms_dir() . '/content.json'; }
function cms_backup_dir(): string   { return cms_dir() . '/backups'; }

/**
 * 編集可能な既定文言（site / top / pages）。
 * 初回シードと、JSONに不足キーがある場合の補完に使う。
 */
function cms_defaults(): array
{
    return [
        'site' => [
            'tagline'     => '歩くことを、もっと楽しく健康に。',
            'description' => 'ウォーキングの効果・正しい歩き方・歩数別カロリー・歩いてポイ活・ウォーキングマシンまで。歩くことのすべてが分かる健康情報メディア。',
            'author'      => '齋藤 雄吾',
            'author_role' => '株式会社D-SYSTEMS-EN 代表取締役',
            'org'         => '株式会社D-SYSTEMS-EN',
            'org_url'     => 'https://www.dsystemsen.com/',
            'x_url'       => 'https://x.com/DsystemsEn',
        ],
        'top' => [
            'hero_badge'      => '',
            'hero_title_1'    => '歩くことを、',
            'hero_accent'     => 'もっと楽しく',
            'hero_title_2'    => '健康に。',
            'hero_lead'       => 'ウォーキングの効果から正しい歩き方、歩数別の消費カロリー、歩いてポイ活まで。<br>「歩く」のすべてを、みんなでコラムをつくって、役立つ情報を共有。',
            'hero_btn1'       => 'コラムを読む →',
            'hero_btn2'       => '歩数別カロリー表',
            'pillars_eyebrow' => '',
            'pillars_title'   => '１．歩数別・消費カロリー早見表（ウォーキング）',
            'pillars_sub'     => '知りたいテーマから、専門コラムへ。',
            'pickup_eyebrow'  => 'Pick Up',
            'pickup_title'    => '注目のコラム',
            'pickup_sub'      => 'まず読んでほしい、各テーマの基本ガイド。',
            'cta_title'       => '今日から、1日あと2,000歩。',
            'cta_sub'         => '小さな一歩の積み重ねが、心と体を変えていきます。まずは歩数とカロリーの関係から。',
            'cta_btn'         => '歩数別カロリー表を見る →',
        ],
        'pages' => [
            'about' => [
                'title' => '運営者情報',
                'desc' => '歩くことの総合メディア「あるく」の運営者情報。株式会社D-SYSTEMS-EN が運営しています。',
                'noindex' => false,
                'sections' => [
                    [
                        'h2' => 'あるくについて',
                        'body' => '<p>「あるく」は、<strong>歩くことの効果・正しい歩き方・歩数別カロリー・歩いてポイ活・ウォーキングマシン</strong>まで、歩くことに関する情報を分かりやすくお届けする健康情報メディアです。</p><p>「今日からあと少し歩いてみよう」と思えるきっかけを、信頼できる情報とともに提供することを目指しています。</p>',
                    ],
                    [
                        'h2' => '運営会社',
                        'body' => '<div class="column-table-wrap"><table class="column-table"><tbody>'
                            . '<tr><td>運営者</td><td>株式会社D-SYSTEMS-EN</td></tr>'
                            . '<tr><td>代表者</td><td>齋藤 雄吾</td></tr>'
                            . '<tr><td>事業内容</td><td>システム開発・Webメディア運営</td></tr>'
                            . '<tr><td>会社サイト</td><td><a href="https://www.dsystemsen.com/" target="_blank" rel="noopener">https://www.dsystemsen.com/</a></td></tr>'
                            . '<tr><td>お問い合わせ</td><td><a href="https://www.dsystemsen.com/" target="_blank" rel="noopener">運営会社サイトの問い合わせ窓口</a></td></tr>'
                            . '</tbody></table></div>',
                    ],
                    [
                        'h2' => '免責事項',
                        'body' => '<p>本サイトに掲載する健康・運動に関する情報は、一般的な情報提供を目的としたものであり、医療上の診断・治療・助言を行うものではありません。健康状態に不安のある方、治療中の方は、運動を始める前に必ず医師等の専門家にご相談ください。</p>'
                            . '<p>掲載情報の正確性には努めていますが、内容を保証するものではなく、本サイトの利用により生じたいかなる損害についても責任を負いかねます。アプリ・商品・サービスの仕様や価格は変更される場合があるため、最新情報は各公式サイトでご確認ください。</p>',
                    ],
                ],
            ],
            'privacy' => [
                'title' => 'プライバシーポリシー',
                'desc' => 'あるくのプライバシーポリシー。会員情報・健康記録（体重・運動）・投稿コンテンツ、Cookie・アクセス解析・広告配信における情報の取り扱いについて定めます。',
                'noindex' => true,
                'sections' => [
                    [
                        'h2' => '基本方針',
                        'body' => '<p>あるく（以下「当サイト」）は、利用者のプライバシーを尊重し、個人情報の保護に努めます。本ポリシーは、当サイトにおける情報の取り扱いについて定めるものです。</p>',
                    ],
                    [
                        'h2' => '取得する情報',
                        'body' => '<p>当サイトでは、会員機能・健康記録機能の提供にあたり、次の情報を取得・保存します。</p>'
                            . '<ul>'
                            . '<li><strong>会員情報</strong>：メールアドレス、ニックネーム、性別。パスワードは暗号化（ハッシュ化）して保存し、当サイトでも元のパスワードは分かりません。</li>'
                            . '<li><strong>健康記録</strong>：あなたが入力した体重、運動の種類・時間、および計算された消費カロリー・累計。</li>'
                            . '<li><strong>投稿コンテンツ</strong>：会員が投稿したコラムのタイトル・本文。</li>'
                            . '<li><strong>アクセス情報</strong>：Cookie（セッション）、アクセス日時、IPアドレス等。</li>'
                            . '</ul>',
                    ],
                    [
                        'h2' => '利用目的',
                        'body' => '<p>取得した情報は、次の目的の範囲でのみ利用します。</p>'
                            . '<ul>'
                            . '<li>会員ログイン・本人確認、記録の保存と表示</li>'
                            . '<li>消費カロリーの計算および累計の表示</li>'
                            . '<li>会員が投稿したコラムの公開（管理者の承認後）</li>'
                            . '<li>不正利用・スパムの防止、サービスの維持・改善</li>'
                            . '</ul>',
                    ],
                    [
                        'h2' => '健康データの取り扱い',
                        'body' => '<p>体重・運動記録などの健康に関する情報は、<strong>記録・表示というご本人のための目的のみ</strong>に利用し、ご本人のマイページ以外には表示しません。ご本人の同意なく第三者へ提供・販売することはありません。</p>',
                    ],
                    [
                        'h2' => 'データの保管とセキュリティ',
                        'body' => '<p>会員情報・記録はデータベースに保管します。パスワードは bcrypt によりハッシュ化し、通信は HTTPS で暗号化、ログインセッションの Cookie は httponly・secure・SameSite の設定で保護しています。</p>',
                    ],
                    [
                        'h2' => 'Cookie・セッションの利用',
                        'body' => '<p>ログイン状態の維持や安全性確保のため、セッション Cookie を使用します。ブラウザの設定で Cookie を無効にした場合、ログインや記録機能をご利用いただけないことがあります。</p>',
                    ],
                    [
                        'h2' => '会員投稿コンテンツについて',
                        'body' => '<p>会員が投稿し、管理者が承認したコラムは一般に公開され、どなたでも閲覧できます。投稿内容の権利と責任は投稿者に帰属します。個人情報や第三者の権利を侵害する内容は投稿しないでください。不適切と判断した投稿は、予告なく非公開・削除する場合があります。</p>',
                    ],
                    [
                        'h2' => '退会・データの削除',
                        'body' => '<p>アカウントの退会、または登録情報・記録・投稿の削除をご希望の場合は、運営会社の窓口までご連絡ください。ご本人確認のうえ対応いたします。</p>',
                    ],
                    [
                        'h2' => 'アクセス解析ツールについて',
                        'body' => '<p>当サイトでは、サイト改善のためにアクセス解析ツール（Google アナリティクス等）を利用する場合があります。これらのツールはトラフィックデータの収集のために Cookie を使用することがありますが、このデータは匿名で収集されており、個人を特定するものではありません。ブラウザの設定により Cookie を無効にすることで収集を拒否できます。</p>',
                    ],
                    [
                        'h2' => '広告配信について',
                        'body' => '<p>当サイトでは、第三者配信の広告サービス（Google アドセンス、各種アフィリエイトプログラム等）を利用する場合があります。広告配信事業者は、利用者の興味に応じた広告を表示するために Cookie を使用することがあります。Cookie を無効にする方法や広告配信事業者のプライバシーポリシーについては、各事業者の案内をご確認ください。</p>'
                            . '<p>当サイトはアフィリエイトプログラムに参加しており、紹介する商品・サービスを通じて収益を得る場合があります。紹介する内容は、利用者に有益と判断したものを掲載しています。</p>',
                    ],
                    [
                        'h2' => '免責事項',
                        'body' => '<p>当サイトのコンテンツは情報提供を目的としており、その正確性・完全性を保証するものではありません。当サイトの情報を用いて行う一切の行為について、利用者ご自身の責任と判断において行ってください。</p>',
                    ],
                    [
                        'h2' => 'お問い合わせ・改定',
                        'body' => '<p>本ポリシーに関するお問い合わせは、運営会社（株式会社D-SYSTEMS-EN）の窓口までお願いいたします。本ポリシーは、必要に応じて改定することがあります。</p>',
                    ],
                ],
            ],
        ],
    ];
}

/**
 * articles.php（＋既定文言）から完全なコンテンツ配列を構築。
 */
function cms_build_seed(): array
{
    $CATEGORIES = $CATEGORY_ORDER = $ARTICLES = null;
    require __DIR__ . '/articles.php'; // $CATEGORIES, $CATEGORY_ORDER, $ARTICLES を定義
    $data = cms_defaults();
    $data['categories']     = $CATEGORIES;
    $data['category_order'] = $CATEGORY_ORDER;
    $data['articles']       = $ARTICLES;
    return $data;
}

/**
 * JSONに不足している site/top/pages のキーを既定値で補完する（浅いマージ）。
 */
function cms_ensure_defaults(array $data): array
{
    $def = cms_defaults();
    foreach (['site', 'top'] as $k) {
        $data[$k] = array_merge($def[$k], isset($data[$k]) && is_array($data[$k]) ? $data[$k] : []);
    }
    if (!isset($data['pages']) || !is_array($data['pages'])) {
        $data['pages'] = $def['pages'];
    } else {
        foreach ($def['pages'] as $pk => $pv) {
            if (!isset($data['pages'][$pk])) {
                $data['pages'][$pk] = $pv;
            }
        }
    }
    return $data;
}

/**
 * コンテンツを読み込む（プロセス内キャッシュ）。
 * 無効/不在なら articles.php からシードして書き出す。
 */
function cms_load(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $path = cms_content_path();
    if (is_file($path)) {
        $raw  = @file_get_contents($path);
        $data = json_decode((string)$raw, true);
        if (is_array($data) && !empty($data['articles']) && !empty($data['categories'])) {
            $cache = cms_ensure_defaults($data);
            return $cache;
        }
    }
    // フォールバック：シード生成（書き込みはベストエフォート）
    $data = cms_build_seed();
    cms_write_atomic($data); // 失敗してもサイトは動く
    $cache = $data;
    return $cache;
}

/** キャッシュを破棄（保存後に使用）。 */
function cms_reset_cache(): void
{
    // 次の cms_load() で再読込させるため、静的キャッシュを無効化する手段として
    // 別プロセス（管理画面の保存→リダイレクト）では問題にならない。
    // 同一プロセス内で必要な場合のために提供。
}

/** data ディレクトリを用意し、直アクセス遮断の .htaccess を置く。 */
function cms_prepare_dir(): bool
{
    $dir = cms_dir();
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n");
    }
    // is_writable() は Windows/一部環境で誤判定するため、ディレクトリの存在のみ確認。
    // 実際の書込可否は cms_write_atomic の書込結果で判定する。
    return is_dir($dir);
}

/** 実際にプローブ書込して data ディレクトリの書込可否を判定（is_writableの誤判定回避）。 */
function cms_can_write(): bool
{
    if (!cms_prepare_dir()) {
        return false;
    }
    $probe = cms_dir() . '/.probe';
    $ok = @file_put_contents($probe, '1') !== false;
    if ($ok) {
        @unlink($probe);
    }
    return $ok;
}

/** アトミックに content.json を書き出す。成功で true。 */
function cms_write_atomic(array $data): bool
{
    if (!cms_prepare_dir()) {
        return false;
    }
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if ($json === false) {
        return false;
    }
    $path = cms_content_path();
    $tmp  = $path . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    // POSIX では rename が既存を上書きしてアトミック。
    // Windows 等では既存ファイルがあると rename が失敗するため退避して再試行。
    if (@rename($tmp, $path)) {
        return true;
    }
    if (is_file($path) && @unlink($path) && @rename($tmp, $path)) {
        return true;
    }
    // 最終手段：直接書き込み
    if (@file_put_contents($path, $json, LOCK_EX) !== false) {
        @unlink($tmp);
        return true;
    }
    @unlink($tmp);
    return false;
}

/**
 * 編集内容を保存。直前の状態をバックアップしてからアトミック書き込み。
 * @return array{ok:bool,error?:string}
 */
function cms_save(array $data): array
{
    // バックアップ（既存があれば）
    if (is_file(cms_content_path())) {
        $bdir = cms_backup_dir();
        if (!is_dir($bdir)) {
            @mkdir($bdir, 0755, true);
        }
        if (is_dir($bdir) && is_writable($bdir)) {
            @copy(cms_content_path(), $bdir . '/content-' . date('Ymd-His') . '.json');
            // 古いバックアップを30件まで保持
            $files = glob($bdir . '/content-*.json') ?: [];
            if (count($files) > 30) {
                sort($files);
                foreach (array_slice($files, 0, count($files) - 30) as $old) {
                    @unlink($old);
                }
            }
        }
    }
    if (!cms_write_atomic($data)) {
        return ['ok' => false, 'error' => 'data/content.json に書き込めませんでした（パーミッションを確認してください）。'];
    }
    return ['ok' => true];
}

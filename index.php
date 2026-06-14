<?php
$envPath = file_exists(__DIR__ . '/.env')
    ? __DIR__ . '/.env'
    : __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
} else {
    die('<div class="alert alert-danger">設定ファイルが見つかりません</div>');
}

$cacheFile = __DIR__ . '/cache.json';
// URLパラメータに ?refresh=1 があるか確認
$forceRefresh = (isset($_GET['refresh']) && $_GET['refresh'] === '1');

$rows = [];

// キャッシュが存在し、かつ強制更新ではない場合はキャッシュを読み込む
if (file_exists($cacheFile) && !$forceRefresh) {
    $cacheData = file_get_contents($cacheFile);
    $rows = json_decode($cacheData, true) ?? [];
}

// キャッシュがない、または強制更新の場合は Google Sheets API から取得
if (empty($rows) || $forceRefresh) {
    $apiKey        = getenv('SPREADSHEET_API_KEY');
    $spreadsheetId = getenv('SPREADSHEET_ID');
    $range         = getenv('SPREADSHEET_RANGE');

    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}?key={$apiKey}";

    $response = @file_get_contents($url);
    if ($response !== FALSE) {
        $data = json_decode($response, true);
        $rows = $data['values'] ?? [];

        // 取得したデータをキャッシュファイルとして保存
        file_put_contents($cacheFile, json_encode($rows, JSON_UNESCAPED_UNICODE));
    } else {
        // API取得失敗時、古いキャッシュがあればそれを代用
        if (file_exists($cacheFile)) {
            $rows = json_decode(file_get_contents($cacheFile), true) ?? [];
        } else {
            // エラー表示を美しくするために、ここではdieせずエラーメッセージを設定する
            $rows = [];
            $errorMessage = 'Google API への接続に失敗しました。';
        }
    }
}

// HTMLテンプレートの出力
$productsHtml = '';
if (isset($errorMessage)) {
    $productsHtml = '<div class="col-12"><div class="alert alert-danger">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div></div>';
} elseif (empty($rows)) {
    $productsHtml = '<div class="col-12"><p class="text-center text-muted py-5">商品データがありません。</p></div>';
} else {
    foreach ($rows as $row) {
        $imgUrl  = !empty($row[0]) ? htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') : 'https://placehold.co/600x600/png?font=montserrat&text=No+Image';
        $linkUrl = !empty($row[1]) ? htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8') : '#';
        $title   = !empty($row[2]) ? htmlspecialchars($row[2], ENT_QUOTES, 'UTF-8') : 'サンプルの商品名';
        $price   = !empty($row[3]) ? htmlspecialchars($row[3], ENT_QUOTES, 'UTF-8') : '0';

        $productsHtml .= '
        <div class="col-lg-4 col-md-6 col-sm-6 mb-4 reveal">
            <div class="card h-100 border-light-subtle rounded-4 shadow-sm overflow-hidden hover-animate bg-white">
                <div class="ratio ratio-1x1 bg-light">
                    <a href="' . $linkUrl . '" class="overflow-hidden" target="_blank">
                        <img src="' . $imgUrl . '" class="w-100 h-100 object-fit-cover zoom-img" style="object-position: bottom;" alt="商品画像">
                    </a>
                </div>
                <div class="card-body d-flex flex-column p-4">
                    <h5 class="card-title fs-6 fw-bold line-clamp-2 mb-3 text-body">' . $title . '</h5>
                    <div class="d-flex align-items-baseline gap-1 mt-auto mb-3">
                        <span class="text-body-secondary fs-6 fw-bold">PRICE</span>
                        <span class="text-danger fs-4 fw-bold" style="font-family: \'Plus Jakarta Sans\', sans-serif;">¥' . $price . '</span>
                        <span class="text-body-secondary fs-6 fw-lighter" style="transform: translateY(-2px);">[tax in.]</span>
                    </div>
                    <a href="' . $linkUrl . '"
                        class="btn btn-dark w-100 py-2 rounded-3 d-inline-flex align-items-center justify-content-center gap-2 fw-semibold btn-product-detail"
                        target="_blank"
                    >
                        <span>商品を見る</span>
                        <i class="bi bi-arrow-up-right fs-6"></i>
                    </a>
                </div>
            </div>
        </div>';
    }
}

$pageTitle = '商品一覧';
$pageSubtitle = 'Curated Collection';

$template = '
<div class="container pt-5 pb-4">
    <div class="d-flex justify-content-between align-items-end mb-5 pb-3 border-bottom border-light-subtle">
        <div>
            <span class="text-body-secondary text-uppercase small fw-semibold d-block mb-1" style="letter-spacing: 0.15em;">' . $pageSubtitle . '</span>
            <h1 class="brand-title fw-bold fs-2 mb-0">' . $pageTitle . '</h1>
        </div>
        <a href="?refresh=1" class="btn btn-outline-dark rounded-pill btn-sm px-3 py-2 btn-modern-reload d-inline-flex align-items-center gap-2">
            <i class="bi bi-arrow-clockwise fs-6"></i>
            <span class="fw-medium">再読込</span>
        </a>
    </div>
    
    <div id="product-list" class="row g-4">
        ' . $productsHtml . '
    </div>
</div>
';

echo $template;

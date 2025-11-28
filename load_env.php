<?php
/**
 * .envファイルを読み込む簡易ローダー
 */

function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path)) {
        throw new Exception('.envファイルが見つかりません: ' . $path);
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // コメント行をスキップ
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // KEY=VALUE形式をパース
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // クォートを削除
            $value = trim($value, '"\'');
            
            // 環境変数として設定
            if (!array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

/**
 * 環境変数を取得
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env($key, $default = null) {
    $value = getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // 真偽値の変換
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }
    
    return $value;
}

// .envファイルを読み込み
try {
    loadEnv();
} catch (Exception $e) {
    // .envが存在しない場合は警告を出す
    error_log('Warning: ' . $e->getMessage());
}


<?php
session_start();

// ========================== ЗАГРУЗКА ПЕРЕМЕННЫХ ОКРУЖЕНИЯ ==========================
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        die("
            <!DOCTYPE html>
            <html>
            <head>
                <title>Ошибка конфигурации</title>
                <style>
                    body { font-family: Arial; padding: 40px; background: #f8f9fa; }
                    .error-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
                    h1 { color: #dc3545; }
                    code { background: #f8f9fa; padding: 10px; border-radius: 5px; display: block; margin: 10px 0; }
                </style>
            </head>
            <body>
                <div class='error-box'>
                    <h1>❌ Файл .env не найден</h1>
                    <p>Создайте файл <strong>.env</strong> в корневой папке проекта</p>
                    <p>Скопируйте шаблон:</p>
                    <code>cp .env.example .env</code>
                    <p>Заполните файл .env своими данными:</p>
                    <ol>
                        <li>ADMIN_USERNAME и ADMIN_PASSWORD</li>
                        <li>Данные PostgreSQL с Render</li>
                    </ol>
                    <p><strong>ВНИМАНИЕ:</strong> Не загружайте файл .env на GitHub!</p>
                </div>
            </body>
            </html>
        ");
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Пропускаем комментарии
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Удаляем кавычки если есть
        if (($value[0] === '"' && $value[strlen($value)-1] === '"') || 
            ($value[0] === "'" && $value[strlen($value)-1] === "'")) {
            $value = substr($value, 1, -1);
        }
        
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Загружаем .env файл
loadEnv(__DIR__ . '/.env');

// ========================== КОНСТАНТЫ ИЗ .env ==========================
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME'));
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD'));
define('RENDER_DB_HOST', getenv('RENDER_DB_HOST'));
define('RENDER_DB_PORT', getenv('RENDER_DB_PORT'));
define('RENDER_DB_NAME', getenv('RENDER_DB_NAME'));
define('RENDER_DB_USER', getenv('RENDER_DB_USER'));
define('RENDER_DB_PASS', getenv('RENDER_DB_PASS'));

// ========================== ФУНКЦИИ ==========================
function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function getDBConnection() {
    try {
        $dsn = "pgsql:host=" . RENDER_DB_HOST . 
               ";port=" . RENDER_DB_PORT . 
               ";dbname=" . RENDER_DB_NAME;
        
        $pdo = new PDO($dsn, RENDER_DB_USER, RENDER_DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        createTablesIfNotExists($pdo);
        
        // Автоматически удаляем истекшие ключи (по желанию, можно закомментировать)
        deleteExpiredKeys($pdo);
        
        return $pdo;
    } catch (PDOException $e) {
        die("
            <!DOCTYPE html>
            <html>
            <head>
                <title>Ошибка подключения</title>
                <style>body { font-family: Arial; padding: 40px; text-align: center; } .error { color: #dc3545; }</style>
            </head>
            <body>
                <div class='error'>
                    <h1>❌ Ошибка подключения к базе данных</h1>
                    <p>" . htmlspecialchars($e->getMessage()) . "</p>
                </div>
            </body>
            </html>
        ");
    }
}

function createTablesIfNotExists($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS keys (
        id SERIAL PRIMARY KEY,
        key_name VARCHAR(255) NOT NULL,
        key_value TEXT NOT NULL,
        description TEXT,
        created_by VARCHAR(100) DEFAULT 'admin',
        valid_hours INTEGER DEFAULT 0,
        expires_at TIMESTAMP,
        is_active BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
}

// УДАЛЕНИЕ истекших ключей (вместо деактивации)
function deleteExpiredKeys($pdo) {
    // Удаляем ключи, у которых срок истек более 24 часов назад
    $sql = "DELETE FROM keys 
            WHERE expires_at IS NOT NULL 
            AND expires_at < CURRENT_TIMESTAMP - INTERVAL '24 hours'";
    $pdo->exec($sql);
}

// Деактивация истекших ключей (альтернатива удалению)
function deactivateExpiredKeys($pdo) {
    $sql = "UPDATE keys 
            SET is_active = false 
            WHERE expires_at IS NOT NULL 
            AND expires_at < CURRENT_TIMESTAMP 
            AND is_active = true";
    $pdo->exec($sql);
}

function calculateExpiryDate($validHours) {
    if ($validHours <= 0) {
        return null; // Бессрочный ключ
    }
    $date = new DateTime();
    $date->add(new DateInterval('PT' . $validHours . 'H'));
    return $date->format('Y-m-d H:i:s');
}

function formatTimeRemaining($expiresAt) {
    if (!$expiresAt) return '∞ (бессрочно)';
    
    $now = new DateTime();
    $expiry = new DateTime($expiresAt);
    
    if ($expiry < $now) {
        return '⌛ Истек ' . $now->diff($expiry)->h . ' ч. назад';
    }
    
    $interval = $now->diff($expiry);
    
    if ($interval->days > 0) {
        return $interval->days . ' дн. ' . $interval->h . ' ч.';
    } elseif ($interval->h > 0) {
        return $interval->h . ' ч. ' . $interval->i . ' мин.';
    } else {
        return $interval->i . ' мин.';
    }
}

function getKeyStatus($key) {
    $now = new DateTime();
    $isExpired = $key['expires_at'] && new DateTime($key['expires_at']) < $now;
    
    if (!$key['is_active']) {
        return ['text' => '❌ Неактивен', 'class' => 'inactive', 'expired' => false];
    } elseif ($isExpired) {
        return ['text' => '⌛ Истек', 'class' => 'expired', 'expired' => true];
    } else {
        return ['text' => '✅ Активен', 'class' => 'active', 'expired' => false];
    }
}
?>
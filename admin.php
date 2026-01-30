<?php
require_once 'config.php';

if (isAdmin()) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit();
    } else {
        $error = 'Неверный логин или пароль';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>🔐 Авторизация</h1>
                <p>Система управления ключами со сроком действия</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    ❌ <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">👤 Логин:</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Введите логин" autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">🔒 Пароль:</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Введите пароль" autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn btn-login">🚀 Войти</button>
            </form>
            
            <div class="login-footer">
                <p>🔑 Ключи автоматически удаляются после истечения срока</p>
                <p class="small">Используйте файл .env для безопасного хранения паролей</p>
            </div>
        </div>
    </div>
</body>
</html>
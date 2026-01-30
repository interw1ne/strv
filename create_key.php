<?php
require_once 'config.php';

if (!isAdmin()) {
    header('Location: admin.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key_name = trim($_POST['key_name'] ?? '');
    $key_value = trim($_POST['key_value'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Определяем срок действия
    $valid_hours = (int)($_POST['valid_hours'] ?? 0);
    if (isset($_POST['custom_hours']) && $_POST['custom_hours'] > 0) {
        $valid_hours = (int)$_POST['custom_hours'];
    }
    
    if (!empty($key_name) && !empty($key_value) && $valid_hours >= 0) {
        try {
            $pdo = getDBConnection();
            
            $expires_at = calculateExpiryDate($valid_hours);
            
            $sql = "INSERT INTO keys (key_name, key_value, description, valid_hours, expires_at, created_by) 
                    VALUES (:key_name, :key_value, :description, :valid_hours, :expires_at, :created_by)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':key_name' => $key_name,
                ':key_value' => $key_value,
                ':description' => $description,
                ':valid_hours' => $valid_hours,
                ':expires_at' => $expires_at,
                ':created_by' => 'admin'
            ]);
            
            header('Location: index.php?success=1');
            exit();
            
        } catch (PDOException $e) {
            $error = "Ошибка: " . $e->getMessage();
        }
    } else {
        $error = "Заполните все обязательные поля правильно";
    }
}

// Если есть ошибка
if (isset($error)): ?>
<!DOCTYPE html>
<html>
<head>
    <title>Ошибка</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <h2>❌ Ошибка</h2>
            <p><?php echo $error; ?></p>
            <a href="index.php" class="btn">← Вернуться</a>
        </div>
    </div>
</body>
</html>
<?php exit(); endif; ?>
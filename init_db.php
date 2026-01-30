<?php
require_once 'config.php';

echo "<h1>Тест подключения к базе данных</h1>";

try {
    $pdo = getDBConnection();
    echo "<p style='color: green;'>✅ Подключение к PostgreSQL успешно!</p>";
    
    // Проверяем таблицу
    $stmt = $pdo->query("SELECT COUNT(*) FROM keys");
    $count = $stmt->fetchColumn();
    echo "<p>Записей в таблице 'keys': $count</p>";
    
    // Показываем структуру
    echo "<h3>Структура таблицы 'keys':</h3>";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns 
                         WHERE table_name = 'keys' ORDER BY ordinal_position");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'><tr><th>Колонка</th><th>Тип данных</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['column_name']}</td><td>{$col['data_type']}</td></tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Ошибка: " . $e->getMessage() . "</p>";
    echo "<p>Проверьте настройки подключения в config.php</p>";
}
?>
<?php
require_once 'config.php';

if (!isAdmin()) {
    header('Location: admin.php');
    exit();
}

$pdo = getDBConnection();

// Обработка действий
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    switch ($_GET['action']) {
        case 'activate':
            $pdo->prepare("UPDATE keys SET is_active = true WHERE id = ?")->execute([$id]);
            break;
        case 'deactivate':
            $pdo->prepare("UPDATE keys SET is_active = false WHERE id = ?")->execute([$id]);
            break;
        case 'delete':
            $pdo->prepare("DELETE FROM keys WHERE id = ?")->execute([$id]);
            break;
        case 'delete_expired':
            $pdo->exec("DELETE FROM keys WHERE expires_at < CURRENT_TIMESTAMP");
            break;
    }
    
    header('Location: index.php');
    exit();
}

// Получение ключей
$stmt = $pdo->query("
    SELECT *, 
    CASE 
        WHEN expires_at IS NULL THEN 'Бессрочный'
        WHEN expires_at < CURRENT_TIMESTAMP THEN 'Истек'
        ELSE 'Активен'
    END as status_text
    FROM keys 
    ORDER BY 
        CASE 
            WHEN expires_at IS NULL THEN 1
            WHEN expires_at < CURRENT_TIMESTAMP THEN 3
            ELSE 2
        END,
        created_at DESC
");
$keys = $stmt->fetchAll();

// Статистика
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN is_active = true AND (expires_at IS NULL OR expires_at >= CURRENT_TIMESTAMP) THEN 1 END) as active,
        COUNT(CASE WHEN expires_at < CURRENT_TIMESTAMP THEN 1 END) as expired,
        COUNT(CASE WHEN is_active = false THEN 1 END) as inactive
    FROM keys
")->fetch();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление ключами со сроком</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 Управление ключами</h1>
            <div class="header-actions">
                <a href="?action=delete_expired" class="btn btn-danger" onclick="return confirm('Удалить ВСЕ истекшие ключи?')">
                    🗑️ Очистить истекшие
                </a>
                <a href="logout.php" class="btn btn-logout">🚪 Выйти</a>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Всего ключей</div>
            </div>
            <div class="stat-card stat-active">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Активных</div>
            </div>
            <div class="stat-card stat-expired">
                <div class="stat-number"><?php echo $stats['expired']; ?></div>
                <div class="stat-label">Истекших</div>
            </div>
            <div class="stat-card stat-inactive">
                <div class="stat-number"><?php echo $stats['inactive']; ?></div>
                <div class="stat-label">Неактивных</div>
            </div>
        </div>

        <div class="create-key-section">
            <h2>➕ Создать новый ключ</h2>
            <form action="create_key.php" method="POST" class="key-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="key_name">📝 Название:</label>
                        <input type="text" id="key_name" name="key_name" required placeholder="API ключ для клиента">
                    </div>
                    <div class="form-group">
                        <label for="valid_hours">⏳ Срок действия:</label>
                        <div class="time-options">
                            <select id="valid_hours" name="valid_hours" class="time-select">
                                <option value="0">∞ Бессрочный</option>
                                <option value="1">1 час</option>
                                <option value="6">6 часов</option>
                                <option value="12">12 часов</option>
                                <option value="24">24 часа (1 день)</option>
                                <option value="168">7 дней</option>
                                <option value="720">30 дней</option>
                                <option value="2160">90 дней</option>
                                <option value="8760">365 дней</option>
                            </select>
                            <div class="custom-time">
                                <input type="number" id="custom_hours" name="custom_hours" min="1" max="87600" placeholder="Или введите часы">
                                <span>часов</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="key_value">🔑 Значение ключа:</label>
                    <div class="key-input-group">
                        <textarea id="key_value" name="key_value" rows="3" required 
                                  placeholder="Вставьте или введите значение ключа"></textarea>
                        <button type="button" class="btn-sm" onclick="generateKey()">🎲 Сгенерировать</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">📋 Описание:</label>
                    <textarea id="description" name="description" rows="2" 
                              placeholder="Дополнительная информация о ключе"></textarea>
                </div>

                <button type="submit" class="btn btn-create">🎯 Создать ключ</button>
            </form>
        </div>

        <div class="keys-section">
            <h2>📋 Список ключей</h2>
            
            <?php if (empty($keys)): ?>
                <div class="empty-state">
                    <p>📭 Нет созданных ключей</p>
                    <p>Создайте первый ключ используя форму выше</p>
                </div>
            <?php else: ?>
                <div class="filters">
                    <div class="filter-group">
                        <button class="filter-btn active" data-filter="all">Все (<?php echo $stats['total']; ?>)</button>
                        <button class="filter-btn" data-filter="active">Активные (<?php echo $stats['active']; ?>)</button>
                        <button class="filter-btn" data-filter="expired">Истекшие (<?php echo $stats['expired']; ?>)</button>
                        <button class="filter-btn" data-filter="inactive">Неактивные (<?php echo $stats['inactive']; ?>)</button>
                    </div>
                </div>

                <div class="keys-grid">
                    <?php foreach ($keys as $key): 
                        $status = getKeyStatus($key);
                        $timeRemaining = formatTimeRemaining($key['expires_at']);
                    ?>
                    <div class="key-card <?php echo $status['class']; ?>" data-status="<?php echo $status['class']; ?>">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($key['key_name']); ?></h3>
                            <span class="key-id">#<?php echo $key['id']; ?></span>
                        </div>
                        
                        <?php if ($key['description']): ?>
                            <p class="key-description"><?php echo htmlspecialchars($key['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="key-value" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($key['key_value']); ?>')">
                            <label>Ключ:</label>
                            <div class="value-display">
                                <?php echo htmlspecialchars($key['key_value']); ?>
                                <span class="copy-hint">📋 Клик для копирования</span>
                            </div>
                        </div>
                        
                        <div class="key-info">
                            <div class="info-row">
                                <span class="info-label">Срок:</span>
                                <span class="info-value">
                                    <?php if ($key['valid_hours'] == 0): ?>
                                        <span class="badge permanent">∞ Бессрочно</span>
                                    <?php else: ?>
                                        <span class="badge time"><?php echo $key['valid_hours']; ?> ч.</span>
                                        <span class="time-remaining"><?php echo $timeRemaining; ?></span>
                                        <small>до <?php echo date('d.m.Y H:i', strtotime($key['expires_at'])); ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Статус:</span>
                                <span class="status-badge <?php echo $status['class']; ?>">
                                    <?php echo $status['text']; ?>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Создан:</span>
                                <span class="info-value">
                                    <?php echo date('d.m.Y', strtotime($key['created_at'])); ?>
                                    в <?php echo date('H:i', strtotime($key['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <?php if ($key['is_active'] && !$status['expired']): ?>
                                <a href="?action=deactivate&id=<?php echo $key['id']; ?>" 
                                   class="btn-action btn-warning" 
                                   onclick="return confirm('Деактивировать ключ?')">
                                    ⛔ Деактивировать
                                </a>
                            <?php elseif (!$key['is_active']): ?>
                                <a href="?action=activate&id=<?php echo $key['id']; ?>" 
                                   class="btn-action btn-success">
                                    ✅ Активировать
                                </a>
                            <?php endif; ?>
                            
                            <a href="?action=delete&id=<?php echo $key['id']; ?>" 
                               class="btn-action btn-danger" 
                               onclick="return confirm('Удалить ключ навсегда?')">
                                🗑️ Удалить
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function copyToClipboard(element, text) {
        navigator.clipboard.writeText(text).then(() => {
            const valueDisplay = element.querySelector('.value-display');
            const original = valueDisplay.innerHTML;
            valueDisplay.innerHTML = '✅ Скопировано!';
            valueDisplay.style.background = '#e8f5e9';
            setTimeout(() => {
                valueDisplay.innerHTML = original;
                valueDisplay.style.background = '';
            }, 1500);
        });
    }

    function generateKey() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let key = '';
        for (let i = 0; i < 32; i++) {
            key += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('key_value').value = key;
    }

    // Фильтрация ключей
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            document.querySelectorAll('.key-card').forEach(card => {
                if (filter === 'all' || card.dataset.status === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // Кастомное время
    document.getElementById('valid_hours').addEventListener('change', function() {
        if (this.value === 'custom') {
            document.querySelector('.custom-time').style.display = 'flex';
        } else {
            document.querySelector('.custom-time').style.display = 'none';
        }
    });

    // Автообновление каждые 5 минут
    setInterval(() => {
        const expiredCount = document.querySelectorAll('.key-card.expired').length;
        if (expiredCount > 0) {
            if (confirm('Обнаружены истекшие ключи. Обновить страницу?')) {
                location.reload();
            }
        }
    }, 300000); // 5 минут
    </script>
</body>
</html>
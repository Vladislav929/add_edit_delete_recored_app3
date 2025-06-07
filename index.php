<?php
// Включим отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключение к БД выносим в начало файла
$servername = "localhost";
$username = "root"; // ваше имя пользователя
$password = ""; // ваш пароль
$dbname = "shops";

// Создаём подключение
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Проверяем соединение
if (!$conn) {
    die("Не удалось подключиться к базе данных");
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление базой данных</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php
        // Вывод сообщений об успехе/ошибке
        if (isset($_GET['success'])) {
            echo '<div class="success">'.htmlspecialchars($_GET['success']).'</div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="error">'.htmlspecialchars($_GET['error']).'</div>';
        }
        ?>
        
        <h1>Управление базой данных</h1>
        
        <!-- Форма выбора таблицы -->
        <form method="get" action="">
            <div class="form-group">
                <label for="table">Выберите таблицу:</label>
                <select name="table" id="table" required>
                    <option value="">-- Выберите таблицу --</option>
                    <?php
                    // Получаем список таблиц
                    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($tables as $table) {
                        $selected = ($_GET['table'] ?? '') == $table ? 'selected' : '';
                        echo "<option value=\"$table\" $selected>$table</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit" class="show-btn">Показать</button>
        </form>

        <?php
        // Отображение содержимого таблицы
        if (isset($_GET['table'])) {
            $table_name = $_GET['table'];
            
            try {
                // Получаем данные таблицы
                $stmt = $conn->query("SELECT * FROM $table_name LIMIT 50");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Форма удаления
                echo '<div class="action-form">';
                echo '<h2>Удаление записи</h2>';
                echo '<form action="delete.php" method="post">';
                echo '<input type="hidden" name="table_name" value="'.$table_name.'">';
                echo '<div class="form-group">';
                echo '<label for="record_id">ID записи:</label>';
                echo '<input type="number" id="record_id" name="record_id" required>';
                echo '</div>';
                echo '<button type="submit" class="delete-btn">Удалить</button>';
                echo '</form>';
                echo '</div>';
                
                // Вывод таблицы
                if (count($results) > 0) {
                    echo '<h2>Содержимое таблицы: '.htmlspecialchars($table_name).'</h2>';
                    echo '<div class="table-container">';
                    echo '<table>';
                    echo '<thead><tr>';
                    foreach (array_keys($results[0]) as $column) {
                        echo '<th>'.$column.'</th>';
                    }
                    echo '</tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($results as $row) {
                        echo '<tr>';
                        foreach ($row as $value) {
                            echo '<td>'.htmlspecialchars($value).'</td>';
                        }
                        echo '<td class="actions">';
                        echo '<a href="index.php?table='.htmlspecialchars($table_name).'&edit='.htmlspecialchars($row['id']).'" class="edit-btn">✏️</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                } else {
                    echo '<p>Таблица пуста</p>';
                }
                
            } catch (PDOException $e) {
                echo '<div class="error">Ошибка при получении данных: '.$e->getMessage().'</div>';
            }
        }
        ?>
        <!-- После таблицы с данными добавьте: -->
        <?php if (isset($_GET['table']) && isset($_GET['edit'])): ?>
        <div class="edit-form">
            <h2>Редактирование записи #<?= htmlspecialchars($_GET['edit']) ?></h2>
            
            <?php
            $table_name = $_GET['table'];
            $record_id = $_GET['edit'];
            
            // Получаем данные записи
            $stmt = $conn->prepare("SELECT * FROM $table_name WHERE id = ?");
            $stmt->execute([$record_id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record):
                // Получаем информацию о внешних ключах
                $fk_query = $conn->query("
                    SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = '$dbname' 
                    AND TABLE_NAME = '$table_name' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                $foreign_keys = $fk_query->fetchAll(PDO::FETCH_ASSOC);
                
                // Получаем структуру таблицы
                $stmt = $conn->query("DESCRIBE $table_name");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <form action="edit.php" method="post">
                <input type="hidden" name="table_name" value="<?= htmlspecialchars($table_name) ?>">
                <input type="hidden" name="record_id" value="<?= htmlspecialchars($record_id) ?>">
                
                <?php foreach ($columns as $column): ?>
                    <?php if ($column['Field'] == 'id') continue; ?>
                    
                    <div class="form-group">
                        <label for="edit_<?= htmlspecialchars($column['Field']) ?>">
                            <?= htmlspecialchars($column['Field']) ?>
                            <?php if ($column['Null'] == 'NO'): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php
                        // Проверяем, является ли поле внешним ключом
                        $is_foreign = false;
                        foreach ($foreign_keys as $fk) {
                            if ($fk['COLUMN_NAME'] == $column['Field']) {
                                $is_foreign = true;
                                $ref_table = $fk['REFERENCED_TABLE_NAME'];
                                $ref_column = $fk['REFERENCED_COLUMN_NAME'];
                                break;
                            }
                        }
                        
                        if ($is_foreign) {
                            // Для внешнего ключа делаем выпадающий список
                            $options = $conn->query("SELECT * FROM $ref_table")->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Пытаемся найти поле для отображения
                            $display_field = 'name';
                            if (!isset($options[0]['name'])) {
                                $display_field = isset($options[0]['title']) ? 'title' : $ref_column;
                            }
                            ?>
                            <select name="<?= htmlspecialchars($column['Field']) ?>" 
                                    id="edit_<?= htmlspecialchars($column['Field']) ?>"
                                    <?= $column['Null'] == 'NO' ? 'required' : '' ?>>
                                <option value="">-- Выберите --</option>
                                <?php foreach ($options as $option): ?>
                                    <option value="<?= htmlspecialchars($option[$ref_column]) ?>"
                                        <?= $option[$ref_column] == $record[$column['Field']] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($option[$display_field] ?? $option[$ref_column]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php } else { 
                            // Определяем тип поля
                            $type = 'text';
                            if (strpos($column['Type'], 'int') !== false) $type = 'number';
                            if (strpos($column['Type'], 'date') !== false) $type = 'date';
                            if (strpos($column['Type'], 'text') !== false) $type = 'textarea';
                            ?>
                            
                            <?php if ($type == 'textarea'): ?>
                                <textarea name="<?= htmlspecialchars($column['Field']) ?>" 
                                          id="edit_<?= htmlspecialchars($column['Field']) ?>"
                                          <?= $column['Null'] == 'NO' ? 'required' : '' ?>><?= 
                                          htmlspecialchars($record[$column['Field']] ?? '') ?></textarea>
                            <?php else: ?>
                                <input type="<?= $type ?>" 
                                       name="<?= htmlspecialchars($column['Field']) ?>" 
                                       id="edit_<?= htmlspecialchars($column['Field']) ?>"
                                       value="<?= htmlspecialchars($record[$column['Field']] ?? '') ?>"
                                       <?= $column['Null'] == 'NO' ? 'required' : '' ?>
                                       <?php if ($type == 'number'): ?>
                                           min="0" step="<?= strpos($column['Type'], 'decimal') !== false ? '0.01' : '1' ?>"
                                       <?php endif; ?>>
                            <?php endif; ?>
                        <?php } ?>
                        
                        <div class="field-info">
                            Тип: <?= htmlspecialchars($column['Type']) ?>
                            <?php if ($is_foreign): ?>
                                | Ссылается на: <?= htmlspecialchars($ref_table) ?>.<?= htmlspecialchars($ref_column) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" class="save-btn">Сохранить изменения</button>
                <a href="index.php?table=<?= htmlspecialchars($table_name) ?>" class="cancel-btn">Отмена</a>
            </form>
            <?php else: ?>
                <div class="error">Запись не найдена</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['table'])): ?>
        <div class="add-form">
            <h2>Добавить новую запись</h2>
            
            <?php
            $table_name = $_GET['table'];
            $stmt = $conn->query("DESCRIBE $table_name");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Получаем информацию о внешних ключах
            $fk_query = $conn->query("
                SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = '$dbname' 
                AND TABLE_NAME = '$table_name' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            $foreign_keys = $fk_query->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <form action="add.php" method="post">
                <input type="hidden" name="table_name" value="<?= htmlspecialchars($table_name) ?>">
                
                <?php foreach ($columns as $column): ?>
                    <?php if ($column['Field'] == 'id') continue; ?>
                    
                    <div class="form-group">
                        <label for="add_<?= $column['Field'] ?>">
                            <?= htmlspecialchars($column['Field']) ?>
                            <?php if ($column['Null'] == 'NO'): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php
                        // Проверяем, является ли поле внешним ключом
                        $is_foreign = false;
                        foreach ($foreign_keys as $fk) {
                            if ($fk['COLUMN_NAME'] == $column['Field']) {
                                $is_foreign = true;
                                $ref_table = $fk['REFERENCED_TABLE_NAME'];
                                $ref_column = $fk['REFERENCED_COLUMN_NAME'];
                                break;
                            }
                        }
                        
                        if ($is_foreign) {
                            // Для внешнего ключа делаем выпадающий список
                            $options = $conn->query("SELECT * FROM $ref_table")->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Пытаемся найти поле для отображения
                            $display_field = 'name';
                            if (!isset($options[0]['name'])) {
                                $display_field = isset($options[0]['title']) ? 'title' : $ref_column;
                            }
                            ?>
                            <select name="<?= $column['Field'] ?>" id="add_<?= $column['Field'] ?>"
                                    <?= $column['Null'] == 'NO' ? 'required' : '' ?>>
                                <option value="">-- Выберите --</option>
                                <?php foreach ($options as $option): ?>
                                    <option value="<?= htmlspecialchars($option[$ref_column]) ?>">
                                        <?= htmlspecialchars($option[$display_field] ?? $option[$ref_column]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php } else { 
                            // Определяем тип поля
                            $type = 'text';
                            if (strpos($column['Type'], 'int') !== false) $type = 'number';
                            if (strpos($column['Type'], 'date') !== false) $type = 'date';
                            if (strpos($column['Type'], 'text') !== false) $type = 'textarea';
                            ?>
                            
                            <?php if ($type == 'textarea'): ?>
                                <textarea name="<?= $column['Field'] ?>" id="add_<?= $column['Field'] ?>"
                                          <?= $column['Null'] == 'NO' ? 'required' : '' ?>></textarea>
                            <?php else: ?>
                                <input type="<?= $type ?>" 
                                       name="<?= $column['Field'] ?>" 
                                       id="add_<?= $column['Field'] ?>"
                                       <?= $column['Null'] == 'NO' ? 'required' : '' ?>
                                       <?php if ($type == 'number'): ?>
                                           min="0" step="<?= strpos($column['Type'], 'decimal') !== false ? '0.01' : '1' ?>"
                                       <?php endif; ?>>
                            <?php endif; ?>
                        <?php } ?>
                        
                        <div class="field-info">
                            Тип: <?= htmlspecialchars($column['Type']) ?>
                            <?php if ($is_foreign): ?>
                                | Ссылается на: <?= htmlspecialchars($ref_table) ?>.<?= htmlspecialchars($ref_column) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" class="add-btn">Добавить запись</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
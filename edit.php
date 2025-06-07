<?php
require 'db_connect.php';

// Включение отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

function sanitize($data) {
    return htmlspecialchars(trim($data));
}

// Получаем данные из формы
$table_name = sanitize($_POST['table_name']);
$record_id = sanitize($_POST['record_id']);

try {
    // 1. Получаем текущие данные записи
    $stmt = $conn->prepare("SELECT * FROM $table_name WHERE id = ?");
    $stmt->execute([$record_id]);
    $current_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_data) {
        throw new Exception("Запись не найдена");
    }
    
    // 2. Получаем структуру таблицы
    $stmt = $conn->query("DESCRIBE $table_name");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Подготовка данных для обновления
    $update_data = [];
    foreach ($columns as $column) {
        $field = $column['Field'];
        if ($field == 'id') continue;
        
        if (isset($_POST[$field])) {
            $update_data[$field] = $_POST[$field] === '' ? null : sanitize($_POST[$field]);
        }
    }
    
    // 4. Формируем SQL запрос
    $set_parts = [];
    foreach ($update_data as $field => $value) {
        $set_parts[] = "$field = :$field";
    }
    
    $sql = "UPDATE $table_name SET " . implode(', ', $set_parts) . " WHERE id = :id";
    $stmt = $conn->prepare($sql);
    
    // Привязываем параметры
    foreach ($update_data as $field => &$value) {
        $stmt->bindParam(":$field", $value);
    }
    $stmt->bindParam(':id', $record_id);
    
    // 5. Выполняем обновление
    $stmt->execute();
    
    header("Location: index.php?table=$table_name&success=Запись успешно обновлена");
    
} catch(PDOException $e) {
    header("Location: index.php?table=$table_name&error=" . urlencode("Ошибка БД: " . $e->getMessage()));
} catch(Exception $e) {
    header("Location: index.php?table=$table_name&error=" . urlencode($e->getMessage()));
}
?>
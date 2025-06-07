<?php
// Включение отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключение к БД
require 'db_connect.php';

// Получаем данные из формы
$table_name = $_POST['table_name'];
$post_data = $_POST;

try {
    // Получаем структуру таблицы
    $stmt = $conn->query("DESCRIBE $table_name");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Подготовка данных для вставки
    $data = [];
    foreach ($columns as $column) {
        $field = $column['Field'];
        if ($field == 'id') continue; // Пропускаем автоинкремент
        
        if (isset($post_data[$field])) {
            $data[$field] = $post_data[$field];
        } elseif ($column['Null'] == 'NO' && $column['Default'] === null) {
            throw new Exception("Обязательное поле $field не заполнено");
        }
    }
    
    // Формируем SQL запрос
    $fields = implode(", ", array_keys($data));
    $placeholders = ":" . implode(", :", array_keys($data));
    
    $sql = "INSERT INTO $table_name ($fields) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->execute($data);
    
    $new_id = $conn->lastInsertId();
    header("Location: index.php?table=$table_name&success=Запись #$new_id успешно добавлена");
    
} catch(PDOException $e) {
    header("Location: index.php?table=$table_name&error=" . urlencode("Ошибка БД: " . $e->getMessage()));
} catch(Exception $e) {
    header("Location: index.php?table=$table_name&error=" . urlencode($e->getMessage()));
}
?>
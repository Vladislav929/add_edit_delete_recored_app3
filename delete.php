<?php
require 'db_connect.php';
// Настройки подключения к базе данных
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "shops";

// Получаем данные из формы
$record_id = $_POST['record_id'];
$table_name = $_POST['table_name'];

try {
    // Подключаемся к базе данных
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL запрос для удаления записи
    $sql = "DELETE FROM $table_name WHERE id = :id";
    
    // Подготавливаем запрос
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $record_id);
    
    // Выполняем запрос
    $stmt->execute();
    
    // Перенаправляем обратно с сообщением об успехе
    header("Location: index.php?table=$table_name&success=1");
    
} catch(PDOException $e) {
    // Перенаправляем с сообщением об ошибке
    header("Location: index.php?table=$table_name&error=" . urlencode($e->getMessage()));
}
?>
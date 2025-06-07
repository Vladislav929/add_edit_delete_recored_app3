<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "shops";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8");
} catch(PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Функция для получения "имени" записи
function get_display_field($conn, $table) {
    $common_names = ['name', 'title', 'username', 'product_name'];
    
    $columns = $conn->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($common_names as $name) {
        if (in_array($name, $columns)) {
            return $name;
        }
    }
    
    return $columns[1] ?? 'id'; // Возвращаем второй столбец или id
}
?>
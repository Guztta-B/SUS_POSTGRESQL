<?php

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$db_name = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

try {
    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$db_name;sslmode=require",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $exception) {
    error_log("Erro de conexão: " . $exception->getMessage());
    http_response_code(500);
    exit("Erro ao conectar ao banco de dados.");
}
?>

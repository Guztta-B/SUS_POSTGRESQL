
<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'conexao.php';

try {
    $stmt = $conn->query("
        SELECT
            current_database() AS banco,
            current_timestamp AS horario
    ");

    $resultado = $stmt->fetch();

    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "PHP conectado ao PostgreSQL!",
        "banco" => $resultado["banco"]
    ]);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        "status" => "erro",
        "mensagem" => "Falha na consulta ao PostgreSQL."
    ]);
}

<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once 'conexao.php';

$dados = json_decode(file_get_contents("php://input"));

if (empty($dados->id)) {
    http_response_code(400);

    echo json_encode([
        "mensagem" => "ID do agendamento não informado."
    ]);

    exit();
}

try {

    // Verifica se o agendamento existe
    $queryVerificar = "
        SELECT id
        FROM agendamentos
        WHERE id = :id
        LIMIT 1
    ";

    $stmtVerificar = $conn->prepare($queryVerificar);

    $stmtVerificar->bindParam(
        ':id',
        $dados->id,
        PDO::PARAM_INT
    );

    $stmtVerificar->execute();

    if (!$stmtVerificar->fetch()) {

        http_response_code(404);

        echo json_encode([
            "mensagem" => "Agendamento não encontrado."
        ]);

        exit();
    }

    // Exclui o agendamento
    $queryDelete = "
        DELETE FROM agendamentos
        WHERE id = :id
    ";

    $stmtDelete = $conn->prepare($queryDelete);

    $stmtDelete->bindParam(
        ':id',
        $dados->id,
        PDO::PARAM_INT
    );

    if ($stmtDelete->execute()) {

        http_response_code(200);

        echo json_encode([
            "mensagem" => "Consulta cancelada com sucesso."
        ]);

    } else {

        http_response_code(500);

        echo json_encode([
            "mensagem" => "Não foi possível cancelar a consulta."
        ]);
    }

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "mensagem" => "Erro no banco de dados."
    ]);
}
?>
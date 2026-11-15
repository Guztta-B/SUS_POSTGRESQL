<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set('America/Sao_Paulo');

include_once 'conexao.php';

$dados = json_decode(file_get_contents("php://input"));

if (empty($dados->id)) {
    http_response_code(400);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "ID do agendamento não informado."
    ]);

    exit();
}

try {

    // Busca o agendamento
    $stmt = $conn->prepare("
        SELECT id, unidade, status, data_hora, ticket
        FROM agendamentos
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->bindParam(':id', $dados->id, PDO::PARAM_INT);
    $stmt->execute();

    $ag = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ag) {
        http_response_code(404);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Agendamento não encontrado."
        ]);

        exit();
    }

    if (strtolower(trim($ag['status'])) === 'cancelado') {
        http_response_code(400);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Este agendamento foi cancelado."
        ]);

        exit();
    }

    $hoje = date('Y-m-d');

    // Só permite check-in no dia do atendimento
    if (substr($ag['data_hora'], 0, 10) !== $hoje) {
        http_response_code(400);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "O check-in só pode ser feito no dia do atendimento."
        ]);

        exit();
    }

    // Já fez check-in: devolve o mesmo ticket
    if (!empty($ag['ticket'])) {
        http_response_code(200);

        echo json_encode([
            "sucesso" => true,
            "ticket" => $ag['ticket']
        ]);

        exit();
    }

    // Próximo número da fila daquela unidade hoje
    $stmtContar = $conn->prepare("
        SELECT COUNT(*)
        FROM agendamentos
        WHERE unidade = :unidade
          AND ticket IS NOT NULL
          AND DATE(checkin_em) = :hoje
    ");

    $stmtContar->bindParam(':unidade', $ag['unidade']);
    $stmtContar->bindParam(':hoje', $hoje);
    $stmtContar->execute();

    $numero = (int) $stmtContar->fetchColumn() + 1;

    $ticket = 'A' . str_pad($numero, 3, '0', STR_PAD_LEFT);

    // Salva ticket e horário da chegada
    $agora = date('Y-m-d H:i:s');

    $stmtUpdate = $conn->prepare("
        UPDATE agendamentos
        SET ticket = :ticket,
            checkin_em = :checkin_em
        WHERE id = :id
    ");

    $stmtUpdate->bindParam(':ticket', $ticket);
    $stmtUpdate->bindParam(':checkin_em', $agora);
    $stmtUpdate->bindParam(':id', $dados->id, PDO::PARAM_INT);
    $stmtUpdate->execute();

    http_response_code(200);

    echo json_encode([
        "sucesso" => true,
        "ticket" => $ticket
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro no banco de dados."
    ]);
}
?>

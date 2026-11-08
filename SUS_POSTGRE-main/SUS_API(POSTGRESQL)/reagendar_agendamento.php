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

if (
    empty($dados->id) ||
    empty($dados->data_hora) ||
    empty($dados->tipo) ||
    empty($dados->unidade) ||
    empty($dados->motivo_reagendamento)
) {
    http_response_code(400);

    echo json_encode([
        "mensagem" => "Dados incompletos."
    ]);

    exit();
}

try {

    // ---------------------------------------------------------
    // 1. Verifica se o agendamento realmente existe
    // ---------------------------------------------------------

    $queryAgendamento = "
        SELECT *
        FROM agendamentos
        WHERE id = :id
        LIMIT 1
    ";

    $stmtAgendamento = $conn->prepare($queryAgendamento);
    $stmtAgendamento->bindParam(':id', $dados->id);
    $stmtAgendamento->execute();

    $agendamento = $stmtAgendamento->fetch(PDO::FETCH_ASSOC);

    if (!$agendamento) {

        http_response_code(404);

        echo json_encode([
            "mensagem" => "Agendamento não encontrado."
        ]);

        exit();
    }


    // ---------------------------------------------------------
    // 2. Verifica quais médicos já estão ocupados
    //    no NOVO horário
    //
    //    O próprio agendamento que está sendo reagendado
    //    é ignorado.
    // ---------------------------------------------------------

    $queryOcupados = "
        SELECT funcionario_id
        FROM agendamentos
        WHERE data_hora = :data_hora
          AND funcionario_id IS NOT NULL
          AND id <> :id
    ";

    $stmtOcupados = $conn->prepare($queryOcupados);

    $stmtOcupados->bindParam(
        ':data_hora',
        $dados->data_hora
    );

    $stmtOcupados->bindParam(
        ':id',
        $dados->id
    );

    $stmtOcupados->execute();

    $ocupados = $stmtOcupados->fetchAll(PDO::FETCH_COLUMN);


    // ---------------------------------------------------------
    // 3. Procura médicos disponíveis
    // ---------------------------------------------------------

    if (count($ocupados) > 0) {

        $placeholders = implode(
            ',',
            array_fill(0, count($ocupados), '?')
        );

        $queryDisponiveis = "
            SELECT id
            FROM funcionarios
            WHERE id NOT IN ($placeholders)
        ";

        $stmtDisponiveis = $conn->prepare($queryDisponiveis);

        $stmtDisponiveis->execute($ocupados);

    } else {

        $queryDisponiveis = "
            SELECT id
            FROM funcionarios
        ";

        $stmtDisponiveis = $conn->prepare($queryDisponiveis);

        $stmtDisponiveis->execute();
    }

    $disponiveis = $stmtDisponiveis->fetchAll(PDO::FETCH_COLUMN);


    // ---------------------------------------------------------
    // 4. Nenhum médico disponível
    // ---------------------------------------------------------

    if (empty($disponiveis)) {

        http_response_code(409);

        echo json_encode([
            "mensagem" =>
                "Não há médicos disponíveis nesse horário. Escolha outro horário."
        ]);

        exit();
    }


    // ---------------------------------------------------------
    // 5. Escolhe um médico disponível
    // ---------------------------------------------------------

    $funcionario_id =
        $disponiveis[array_rand($disponiveis)];


    // ---------------------------------------------------------
    // 6. Atualiza o próprio agendamento
    // ---------------------------------------------------------

    $queryUpdate = "
        UPDATE agendamentos
        SET
            funcionario_id = :funcionario_id,
            data_hora = :data_hora,
            tipo = :tipo,
            unidade = :unidade,
            motivo_reagendamento = :motivo_reagendamento,
            status = 'pendente'
        WHERE id = :id
    ";

    $stmtUpdate = $conn->prepare($queryUpdate);

    $stmtUpdate->bindParam(
        ':funcionario_id',
        $funcionario_id
    );

    $stmtUpdate->bindParam(
        ':data_hora',
        $dados->data_hora
    );

    $stmtUpdate->bindParam(
        ':tipo',
        $dados->tipo
    );

    $stmtUpdate->bindParam(
        ':unidade',
        $dados->unidade
    );

    $stmtUpdate->bindParam(
        ':motivo_reagendamento',
        $dados->motivo_reagendamento
    );

    $stmtUpdate->bindParam(
        ':id',
        $dados->id
    );


    if ($stmtUpdate->execute()) {

        http_response_code(200);

        echo json_encode([
            "mensagem" => "Agendamento reagendado com sucesso.",
            "funcionario_id" => $funcionario_id
        ]);

    } else {

        http_response_code(500);

        echo json_encode([
            "mensagem" => "Erro ao reagendar agendamento."
        ]);
    }

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "mensagem" => "Erro no banco de dados.",
        "erro" => $e->getMessage()
    ]);
}

?>

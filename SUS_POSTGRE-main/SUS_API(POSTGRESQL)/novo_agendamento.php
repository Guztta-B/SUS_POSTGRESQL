<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// O Chrome manda uma requisição OPTIONS antes do POST (preflight de CORS).
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once 'conexao.php';

$dados = json_decode(file_get_contents("php://input"));

if (
    !empty($dados->paciente_id) &&
    !empty($dados->data_hora) &&
    !empty($dados->nome_completo) &&
    !empty($dados->rg) &&
    !empty($dados->telefone) &&
    !empty($dados->unidade)
) {
    // 1) Busca, entre TODOS os médicos cadastrados, quais já têm um
    //    agendamento marcado exatamente nesse data_hora.
    $queryOcupados = "SELECT funcionario_id FROM agendamentos
                       WHERE data_hora = :data_hora AND funcionario_id IS NOT NULL";
    $stmtOcupados = $conn->prepare($queryOcupados);
    $stmtOcupados->bindParam(':data_hora', $dados->data_hora);
    $stmtOcupados->execute();
    $ocupados = $stmtOcupados->fetchAll(PDO::FETCH_COLUMN);

    // 2) Pega os médicos que NÃO estão nessa lista de ocupados.
    if (count($ocupados) > 0) {
        $placeholders = implode(',', array_fill(0, count($ocupados), '?'));
        $queryDisponiveis = "SELECT id FROM funcionarios WHERE id NOT IN ($placeholders)";
        $stmtDisponiveis = $conn->prepare($queryDisponiveis);
        $stmtDisponiveis->execute($ocupados);
    } else {
        $queryDisponiveis = "SELECT id FROM funcionarios";
        $stmtDisponiveis = $conn->prepare($queryDisponiveis);
        $stmtDisponiveis->execute();
    }
    $disponiveis = $stmtDisponiveis->fetchAll(PDO::FETCH_COLUMN);

    // 3) Se não sobrou nenhum médico livre nesse horário, avisa o paciente
    //    em vez de marcar dois atendimentos com o mesmo médico.
    if (empty($disponiveis)) {
        http_response_code(409);
        echo json_encode(array("mensagem" => "Não há médicos disponíveis nesse horário. Escolha outro horário."));
        exit();
    }

    // 4) Sorteia um médico livre.
    $funcionario_id = $disponiveis[array_rand($disponiveis)];

    $query = "INSERT INTO agendamentos
                (paciente_id, funcionario_id, data_hora, tipo, status, nome_completo, telefone, rg, unidade)
              VALUES
                (:paciente_id, :funcionario_id, :data_hora, :tipo, 'pendente', :nome_completo, :telefone, :rg, :unidade)";

    $stmt = $conn->prepare($query);

    $stmt->bindParam(':paciente_id', $dados->paciente_id);
    $stmt->bindParam(':funcionario_id', $funcionario_id);
    $stmt->bindParam(':data_hora', $dados->data_hora);
    $stmt->bindParam(':tipo', $dados->tipo);
    $stmt->bindParam(':nome_completo', $dados->nome_completo);
    $stmt->bindParam(':telefone', $dados->telefone);
    $stmt->bindParam(':rg', $dados->rg);
    $stmt->bindParam(':unidade', $dados->unidade);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("mensagem" => "Agendamento criado com sucesso."));
    } else {
        http_response_code(500);
        echo json_encode(array("mensagem" => "Erro ao criar agendamento."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("mensagem" => "Dados incompletos."));
}
?>

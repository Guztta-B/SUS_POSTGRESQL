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

if (!$dados) {
    http_response_code(400);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Nenhum dado recebido."
    ]);

    exit();
}

if (
    empty($dados->cpf) ||
    empty($dados->senha)
) {
    http_response_code(400);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Informe o CPF e a senha."
    ]);

    exit();
}

$cpf = trim($dados->cpf);
$senha = $dados->senha;

try {

    $sql = "
        SELECT
            id,
            nome,
            cpf,
            telefone,
            senha_hash
        FROM pacientes
        WHERE cpf = :cpf
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bindParam(
        ':cpf',
        $cpf,
        PDO::PARAM_STR
    );

    $stmt->execute();

    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        http_response_code(401);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "CPF ou senha incorretos."
        ]);

        exit();
    }

    if (
        !password_verify(
            $senha,
            $paciente['senha_hash']
        )
    ) {
        http_response_code(401);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "CPF ou senha incorretos."
        ]);

        exit();
    }

    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Login realizado com sucesso.",
        "usuario" => [
            "id" => (int)$paciente['id'],
            "nome" => $paciente['nome'],
            "cpf" => $paciente['cpf'],
            "telefone" => $paciente['telefone']
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro no banco: " . $e->getMessage()
    ]);
}
?>
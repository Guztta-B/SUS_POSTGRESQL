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

$dados = json_decode(
    file_get_contents("php://input")
);

if (!$dados) {
    http_response_code(400);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Nenhum dado foi recebido."
    ]);

    exit();
}

if (
    empty($dados->nome) ||
    empty($dados->cpf) ||
    empty($dados->telefone) ||
    empty($dados->senha)
) {
    http_response_code(400);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Preencha todos os campos."
    ]);

    exit();
}

$nome = trim($dados->nome);
$cpf = trim($dados->cpf);
$telefone = trim($dados->telefone);
$senha = $dados->senha;

try {

    // Verifica se o CPF já existe
    $queryVerificar = "
        SELECT id
        FROM pacientes
        WHERE cpf = :cpf
        LIMIT 1
    ";

    $stmtVerificar = $conn->prepare(
        $queryVerificar
    );

    $stmtVerificar->bindParam(
        ':cpf',
        $cpf,
        PDO::PARAM_STR
    );

    $stmtVerificar->execute();

    if ($stmtVerificar->fetch()) {

        http_response_code(409);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Este CPF já está cadastrado."
        ]);

        exit();
    }

    // Cria o hash da senha
    $senhaHash = password_hash(
        $senha,
        PASSWORD_DEFAULT
    );

    // Insere o paciente UMA ÚNICA VEZ
    $query = "
        INSERT INTO pacientes
        (
            nome,
            cpf,
            telefone,
            senha_hash
        )
        VALUES
        (
            :nome,
            :cpf,
            :telefone,
            :senha_hash
        )
    ";

    $stmt = $conn->prepare($query);

    $stmt->bindParam(
        ':nome',
        $nome,
        PDO::PARAM_STR
    );

    $stmt->bindParam(
        ':cpf',
        $cpf,
        PDO::PARAM_STR
    );

    $stmt->bindParam(
        ':telefone',
        $telefone,
        PDO::PARAM_STR
    );

    $stmt->bindParam(
        ':senha_hash',
        $senhaHash,
        PDO::PARAM_STR
    );

    $stmt->execute();

    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Cadastro realizado com sucesso."
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro ao realizar cadastro."
    ]);
}

?>
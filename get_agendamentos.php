<?php
// get_agendamentos.php
header("Access-Control-Allow-Origin: *"); // Allows Flutter to request data
header("Content-Type: application/json; charset=UTF-8");

include_once 'conexao.php';

// Usamos o nome_completo/telefone/rg/unidade que ficaram salvos na própria
// linha do agendamento (o que o paciente digitou no formulário), em vez do
// nome fixo da tabela "pacientes" — assim cada agendamento mostra o nome
// de quem realmente marcou aquele horário, não sempre o mesmo paciente_id.
$query = "SELECT a.id, a.data_hora, a.tipo, a.status,
                 a.nome_completo AS paciente,
                 a.telefone,
                 a.rg,
                 a.unidade,
                 f.nome AS medico
          FROM agendamentos a
          LEFT JOIN funcionarios f ON a.funcionario_id = f.id
          ORDER BY a.data_hora ASC";

$stmt = $conn->prepare($query);
$stmt->execute();

$agendamentos = array();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    array_push($agendamentos, $row);
}

// Convert the PHP array into JSON for Flutter
echo json_encode($agendamentos);
?>

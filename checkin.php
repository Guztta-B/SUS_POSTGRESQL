<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

require 'conexao.php'; // deve fornecer $pdo

function responder($codigo, $sucesso, $extra = []) {
    http_response_code($codigo);
    echo json_encode(array_merge(['sucesso' => $sucesso], $extra));
    exit;
}

$entrada = json_decode(file_get_contents('php://input'), true);
$id = (int)($entrada['id'] ?? 0);

$st = $pdo->prepare(
    'SELECT id, unidade, status, data_hora, ticket
       FROM agendamentos WHERE id = ?'
);
$st->execute([$id]);
$ag = $st->fetch(PDO::FETCH_ASSOC);

if (!$ag) {
    responder(404, false, ['mensagem' => 'Agendamento não encontrado.']);
}

if (strtolower(trim($ag['status'])) === 'cancelado') {
    responder(400, false, ['mensagem' => 'Este agendamento foi cancelado.']);
}

$hoje = date('Y-m-d');

if (substr($ag['data_hora'], 0, 10) !== $hoje) {
    responder(400, false, [
        'mensagem' => 'O check-in só pode ser feito no dia do atendimento.'
    ]);
}

// Já fez check-in: devolve o mesmo ticket
if (!empty($ag['ticket'])) {
    responder(200, true, ['ticket' => $ag['ticket']]);
}

// Próximo número da fila daquela unidade hoje
$c = $pdo->prepare(
    'SELECT COUNT(*) FROM agendamentos
      WHERE unidade = ? AND ticket IS NOT NULL
        AND DATE(checkin_em) = ?'
);
$c->execute([$ag['unidade'], $hoje]);
$numero = (int)$c->fetchColumn() + 1;

$ticket = 'A' . str_pad($numero, 3, '0', STR_PAD_LEFT);

$up = $pdo->prepare(
    'UPDATE agendamentos SET ticket = ?, checkin_em = ? WHERE id = ?'
);
$up->execute([$ticket, date('Y-m-d H:i:s'), $id]);

responder(200, true, ['ticket' => $ticket]);
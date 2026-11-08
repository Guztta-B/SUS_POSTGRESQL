<?php
// api/processar_agendamento.php - Processar ações em agendamentos

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../conexao.php';
include_once '../auth.php';

// Verificar se está logado
session_start();
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']);
    exit();
}

$dados = json_decode(file_get_contents("php://input"), true);

try {
    // =====================================================
    // REAGENDAR AGENDAMENTO
    // =====================================================
    if (!empty($dados['id']) && !empty($dados['data_hora']) && 
        !empty($dados['tipo']) && !empty($dados['unidade'])) {
        
        // 1) Verifica se agendamento existe
        $queryCheck = "SELECT * FROM agendamentos WHERE id = :id";
        $stmtCheck = $conn->prepare($queryCheck);
        $stmtCheck->bindParam(':id', $dados['id']);
        $stmtCheck->execute();
        
        if (!$stmtCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Agendamento não encontrado']);
            exit();
        }
        
        // 2) Procura médicos disponíveis no novo horário
        $queryOcupados = "SELECT funcionario_id FROM agendamentos 
                          WHERE data_hora = :data_hora 
                          AND funcionario_id IS NOT NULL 
                          AND id <> :id";
        
        $stmtOcupados = $conn->prepare($queryOcupados);
        $stmtOcupados->bindParam(':data_hora', $dados['data_hora']);
        $stmtOcupados->bindParam(':id', $dados['id']);
        $stmtOcupados->execute();
        
        $ocupados = $stmtOcupados->fetchAll(PDO::FETCH_COLUMN);
        
        // 3) Busca médicos disponíveis
        if (!empty($ocupados)) {
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
        
        if (empty($disponiveis)) {
            http_response_code(409);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Nenhum médico disponível neste horário'
            ]);
            exit();
        }
        
        // 4) Sorteia um médico
        $funcionario_id = $disponiveis[array_rand($disponiveis)];
        
        // 5) Atualiza agendamento
        $queryUpdate = "UPDATE agendamentos 
                        SET funcionario_id = :funcionario_id,
                            data_hora = :data_hora,
                            tipo = :tipo,
                            unidade = :unidade,
                            motivo_reagendamento = :motivo,
                            status = 'pendente'
                        WHERE id = :id";
        
        $motivo = $dados['motivo_reagendamento'] ?? null;
        $stmtUpdate = $conn->prepare($queryUpdate);
        $stmtUpdate->bindParam(':funcionario_id', $funcionario_id);
        $stmtUpdate->bindParam(':data_hora', $dados['data_hora']);
        $stmtUpdate->bindParam(':tipo', $dados['tipo']);
        $stmtUpdate->bindParam(':unidade', $dados['unidade']);
        $stmtUpdate->bindParam(':motivo', $motivo);
        $stmtUpdate->bindParam(':id', $dados['id']);
        
        if ($stmtUpdate->execute()) {
            // Log de auditoria
            registrar_auditoria(
                $conn,
                'REAGENDAMENTO',
                'agendamentos',
                $dados['id'],
                'Reagendado para ' . $dados['data_hora']
            );
            
            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Agendamento reagendado com sucesso'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Erro ao reagendar'
            ]);
        }
    }
    
    // =====================================================
    // MARCAR COMO CONCLUÍDO
    // =====================================================
    else if (isset($dados['acao']) && $dados['acao'] === 'marcar_concluido' && !empty($dados['id'])) {
        $queryUpdate = "UPDATE agendamentos SET status = 'concluido' WHERE id = :id";
        $stmtUpdate = $conn->prepare($queryUpdate);
        $stmtUpdate->bindParam(':id', $dados['id']);
        
        if ($stmtUpdate->execute()) {
            registrar_auditoria($conn, 'MARCAR_CONCLUIDO', 'agendamentos', $dados['id'], '');
            
            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Agendamento marcado como concluído'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Erro ao atualizar status'
            ]);
        }
    }
    
    // =====================================================
    // CANCELAR AGENDAMENTO
    // =====================================================
    else if (isset($dados['acao']) && $dados['acao'] === 'cancelar' && !empty($dados['id'])) {
        $queryDelete = "DELETE FROM agendamentos WHERE id = :id";
        $stmtDelete = $conn->prepare($queryDelete);
        $stmtDelete->bindParam(':id', $dados['id']);
        
        if ($stmtDelete->execute()) {
            registrar_auditoria($conn, 'CANCELAMENTO', 'agendamentos', $dados['id'], '');
            
            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Agendamento cancelado'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Erro ao cancelar'
            ]);
        }
    }
    
    else {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Dados incompletos'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro no banco de dados'
    ]);
}
?>
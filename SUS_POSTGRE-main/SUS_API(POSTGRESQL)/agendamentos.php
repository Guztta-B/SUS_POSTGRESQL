<?php
// agendamentos.php - Gerenciamento de Agendamentos

include_once 'conexao.php';
include_once 'auth.php';

session_start();

// Verifica se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$agendamento_edicao = null;
$acao = $_GET['acao'] ?? '';
$id_edicao = $_GET['id'] ?? '';

// Se está editando, pega o agendamento
if ($acao === 'editar' && $id_edicao) {
    $query = "SELECT a.*, f.nome AS medico
              FROM agendamentos a
              LEFT JOIN funcionarios f ON a.funcionario_id = f.id
              WHERE a.id = :id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id_edicao);
    $stmt->execute();
    $agendamento_edicao = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Puxar todos os agendamentos
$query = "SELECT a.id, a.nome_completo, a.telefone, a.rg, a.unidade, 
                 a.data_hora, a.tipo, a.status, 
                 f.nome AS medico
          FROM agendamentos a
          LEFT JOIN funcionarios f ON a.funcionario_id = f.id
          ORDER BY a.data_hora DESC";

$stmt = $conn->prepare($query);
$stmt->execute();
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Puxar médicos para select
$queryMedicos = "SELECT id, nome FROM funcionarios ORDER BY nome";
$stmtMedicos = $conn->prepare($queryMedicos);
$stmtMedicos->execute();
$medicos = $stmtMedicos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamentos - Sistema SUS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .pendente {
            color: #f59e0b; /* Amarelo */
        }
        :root {
            --primary-color: #1e40af;
            --secondary-color: #16a34a;
            --danger-color: #dc2626;
            --warning-color: #f59e0b;
            --info-color: #0891b2;
        }
        .badge-pendente {
    background-color: #ffc107;
    color: #000;
}
        .sidebar {
            background: white;
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            padding-top: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: #666;
            text-decoration: none;
            border-left: 4px solid transparent;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: #f3f4f6;
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }
        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f3f4f6;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-size: 20px;
            font-weight: 700;
            color: white !important;
        }
        
        .container {
            max-width: 1200px;
            margin-top: 30px;
        }
        
        .page-header {
            color: var(--primary-color);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 30px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
        }
        
        .form-label {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.15);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: #1e3a8a;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        table {
            margin: 0;
        }
        
        table thead {
            background-color: var(--primary-color);
            color: white;
        }
        
        table td {
            vertical-align: middle;
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        table tbody tr:hover {
            background-color: #f9fafb;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-pending {
            background-color: #fef3c7;
            color: #b45309;

        }
        
        .badge-completed {
            background-color: #dcfce7;
            color: #15803d;
        }
        
        .badge-canceled {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .btn-action {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            margin-right: 5px;
            border: none;
            cursor: pointer;
        }
        
        .btn-edit {
            background-color: var(--info-color);
            color: white;
        }
        
        .btn-reagendar {
            background-color: var(--warning-color);
            color: white;
        }
        
        .btn-cancel {
            background-color: var(--danger-color);
            color: white;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand">🏥 Sistema SUS Guarulhos</span>
            <div class="ms-auto" style="color: white;">
                <a href="logout.php" class="btn btn-light btn-sm">Sair</a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid"></div>
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <ul class="sidebar-menu">
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="agendamentos.php" class="active"><i class="fas fa-calendar"></i> Agendamentos</a></li>
                    <li><a href="vacinacao.php" style="opacity: 0.5;"><i class="fas fa-syringe"></i> Vacinação</a></li>
                    <li><a href="estoque.php" style="opacity: 0.5;"><i class="fas fa-pills"></i> Estoque</a></li>
                    <li><a href="equipe.php" style="opacity: 0.5;"><i class="fas fa-users"></i> Equipe</a></li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
    <div class="container">
        <h1 class="page-header">📅 Gerenciamento de Agendamentos</h1>
        
        <!-- Tabela -->
        <div class="table-container">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Paciente</th>
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>Médico</th>
                        <th>Unidade</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agendamentos)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Nenhum agendamento encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($agendamentos as $agendamento): ?>
                            <tr>
                                <td>#<?php echo $agendamento['id']; ?></td>
                                <td>
                                    <strong><?php echo $agendamento['nome_completo']; ?></strong><br>
                                    <small class="text-muted"><?php echo $agendamento['telefone']; ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $data = new DateTime($agendamento['data_hora']);
                                    echo $data->format('d/m/Y \à\s H:i');
                                    ?>
                                </td>
                                <td><?php echo ucfirst($agendamento['tipo']); ?></td>
                                <td><?php echo $agendamento['medico'] ?? 'Não atribuído'; ?></td>
                                <td><?php echo $agendamento['unidade']; ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'badge-' . $agendamento['status'];
                                    $status_texto = [
                                        'pendente' => '⏳ Pendente' ,
                                        'concluido' => '✅ Concluído',
                                        'cancelado' => '❌ Cancelado'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $status_texto[$agendamento['status']] ?? $agendamento['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#modalReagendar" onclick="preparaReagendamento(<?php echo $agendamento['id']; ?>, '<?php echo $agendamento['data_hora']; ?>')">
                                        📅 Reagendar
                                    </button>
                                    <br>
                                    <br>
                                    <?php if ($agendamento['status'] === 'pendente'): ?>
                                        <button class="btn-action" style="background-color: #16a34a; color: white;" onclick="marcarConcluido(<?php echo $agendamento['id']; ?>)">
                                            ✅ Concluir
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal de Reagendamento -->
    <div class="modal fade" id="modalReagendar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📅 Reagendar Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formReagendamento">
                        <input type="hidden" id="agendamento_id" name="id">
                        
                        <div class="mb-3">
                            <label class="form-label">Nova Data e Hora</label>
                            <input type="datetime-local" class="form-control" id="nova_data_hora" name="data_hora" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Atendimento</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="">Selecione...</option>
                                <option value="consulta">Consulta</option>
                                <option value="triagem">Triagem</option>
                                <option value="vacina">Vacina</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Unidade do SUS</label>
                            <select class="form-select" id="unidade" name="unidade" required>
                                <option value="">Selecione...</option>
                                <option value="UBS Central">UBS Central</option>
                                <option value="UBS Vila Formosa">UBS Vila Formosa</option>
                                <option value="UBS Cocaia">UBS Cocaia</option>
                                <option value="UBS Vila Augusta">UBS Vila Augusta</option>
                                <option value="UBS Cambuci">UBS Cambuci</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Motivo do Reagendamento</label>
                            <textarea class="form-control" id="motivo" name="motivo_reagendamento" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="enviarReagendamento()">Reagendar</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function preparaReagendamento(id, dataAtual) {
            document.getElementById('agendamento_id').value = id;
            
            // Formata a data para datetime-local
            const dataObj = new Date(dataAtual);
            const dataFormatada = dataObj.toISOString().slice(0, 16);
            document.getElementById('nova_data_hora').value = dataFormatada;
        }
        
        function enviarReagendamento() {
            const formData = new FormData(document.getElementById('formReagendamento'));
            const dados = Object.fromEntries(formData);
            
            fetch('api/processar_agendamento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dados)
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    alert('✅ Agendamento reagendado com sucesso!');
                    location.reload();
                } else {
                    alert('❌ Erro: ' + data.mensagem);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao processar reagendamento');
            });
        }
        
        function marcarConcluido(id) {
            if (confirm('Marcar este agendamento como concluído?')) {
                fetch('api/processar_agendamento.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        acao: 'marcar_concluido',
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.sucesso) {
                        alert('✅ Agendamento marcado como concluído!');
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + data.mensagem);
                    }
                });
            }
        }
    </script>
</body>
</html>
<?php
// dashboard.php - Painel Principal

include_once 'conexao.php';
include_once 'auth.php';

session_start();

// Verifica se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_data = $_GET['data'] ?? '';

// Puxar agendamentos
$query = "SELECT a.id, a.nome_completo, a.telefone, a.rg, a.unidade,
                 a.data_hora, a.tipo, a.status,
                 f.nome AS medico
          FROM agendamentos a
          LEFT JOIN funcionarios f ON a.funcionario_id = f.id
          WHERE 1=1";

if ($filtro_status) {
    $query .= " AND a.status = :status";
}

if ($filtro_data) {
    $query .= " AND CAST(a.data_hora AS DATE) = :data";
}

$query .= " ORDER BY a.data_hora DESC";

$stmt = $conn->prepare($query);

if ($filtro_status) {
    $stmt->bindParam(':status', $filtro_status);
}

if ($filtro_data) {
    $stmt->bindParam(':data', $filtro_data);
}

$stmt->execute();
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas gerais
$queryStats = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados
FROM agendamentos";

$stmtStats = $conn->prepare($queryStats);
$stmtStats->execute();
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Estatísticas de hoje
$queryHoje = "SELECT
    COUNT(*) as total_hoje,
    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes_hoje,
    SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos_hoje,
    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados_hoje
FROM agendamentos
WHERE CAST(data_hora AS DATE) = CURRENT_DATE";

$stmtHoje = $conn->prepare($queryHoje);
$stmtHoje->execute();
$hoje = $stmtHoje->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUS-UBS Guarulhos | Dashboard</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --blue: #0757a9;
            --blue-dark: #064b91;
            --bg: #f1f3f5;
            --border: #e2e5e8;
            --text: #20252a;
            --muted: #777f86;
        }

        html, body {
            margin: 0;
            min-height: 100%;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 12px;
        }

        body {
            overflow-x: hidden;
        }

        /* SIDEBAR - igual à referência */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 225px;
            background: #0757a9;
            color: #fff;
            z-index: 10;
        }

        .brand {
            height: 82px;
            padding: 17px 14px 10px;
            border-bottom: 1px solid rgba(255,255,255,.14);
        }

        .brand-title {
            font-size: 25px;
            font-weight: 700;
            line-height: 21px;
        }

        .brand-subtitle {
            font-size: 15px;
            margin-top: 5px;
            opacity: .95;
        }

        .menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .menu li {
            border-bottom: 1px solid rgba(255,255,255,.11);
        }

        .menu a {
            height: 48px;
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 0 14px;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            white-space: nowrap;
        }

        .menu a:hover,
        .menu a.active {
            background: #0b62b9;
        }

        .menu a.active {
            border-left: 4px solid #fff;
            padding-left: 10px;
        }

        .menu-icon {
            width: 18px;
            text-align: center;
            font-size: 13px;
            flex: 0 0 10px;
        }

        /* ÁREA PRINCIPAL */
        .main {
            margin-left: 225px;
            min-height: 100vh;
        }

        .topbar {
            height: 50px;
            background: #fff;
            border-top: 1px solid #0b5db1;
            border-bottom: 1px solid #dfe2e5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }

        .topbar-info {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
            white-space: nowrap;
        }

        .user-pill {
            background: #dce1e6;
            color: #333;
            padding: 5px 13px;
            border-radius: 13px;
            margin-left: 4px;
        }

        .logout {
            color: #0757a9;
            text-decoration: none;
            margin-left: 4px;
        }

        .content {
            padding: 22px 24px 28px;
        }

        /* CARDS */
        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 8px;
        }

        .card-metric {
            position: relative;
            height: 100px;
            background: #fff;
            border: 1px solid #e6e8ea;
            border-radius: 8px;
            padding: 16px 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }

        .card-metric::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            border-radius: 5px 0 0 5px;
            background: #0757a9;
        }

        .card-metric.green::before { background: #25a84a; }
        .card-metric.yellow::before { background: #ffb800; }
        .card-metric.purple::before { background: #7040c7; }

        .metric-title {
            color: #70777d;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .metric-value {
            font-size: 30px;
            line-height: 30px;
            font-weight: 700;
        }

        .metric-description {
            color: #8b9196;
            font-size: 15px;
            margin-top: 5px;
        }

        /* PAINÉIS CENTRAIS */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 56px;
            margin-bottom: 22px;
        }

        .panel {
            background: #fff;
            border: 1px solid #e4e7e9;
            border-radius: 8px;
            padding: 20px;
            min-height: 440px;
            box-shadow: 0 1px 3px rgba(0,0,0,.035);
        }

        .panel-title {
            color: #0757a9;
            font-size: 19px;
            font-weight: 700;
            margin: 0 0 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e1e4e7;
        }

        .summary-main {
            font-size: 14px;
            font-weight: 600;
            line-height: 38px;
            margin-bottom: 22px;
        }

        .summary-main strong {
            font-weight: 700;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            margin: 0 0 6px;
        }

        .mini-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .mini-table th {
            background: #e8ebed;
            font-weight: 700;
            text-align: left;
            padding: 11px 9px;
        }

        .mini-table td {
            padding: 10px 9px;
            border-bottom: 1px solid #e5e7e9;
        }

        .mini-table th:not(:first-child),
        .mini-table td:not(:first-child) {
            text-align: center;
        }

        .staff-top {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 22px;
        }

        .staff-badge {
            display: inline-block;
            background: #27a64a;
            color: white;
            border-radius: 3px;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 4px;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-yellow {
            background: #ffbd00;
            color: #222;
        }

        .status-red {
            background: #e73549;
            color: white;
        }

        .status-blue {
            background: #075fbb;
            color: white;
        }

        /* FILTROS */
        .filter-panel {
            background: #fff;
            border: 1px solid #e4e7e9;
            border-radius: 8px;
            padding: 15px 18px 17px;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,.035);
        }

        .filter-title {
            color: #0757a9;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 22px;
        }

        .filter-panel form {
            display: grid;
            grid-template-columns: 1fr 1fr 76px;
            gap: 8px;
            align-items: end;
        }

        .filter-label {
            display: block;
            font-size: 19px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .filter-panel .form-control,
        .filter-panel .form-select {
            width: 100%;
            height: 38px;
            min-height: 38px;
            padding: 5px 9px;
            border: 1px solid #cfd4d8;
            border-radius: 3px;
            font-size: 12px;
            color: #555;
        }

        .btn-clear {
            height: 38px;
            border: 1px solid #d4d8db;
            border-radius: 3px;
            background: #f0f2f4;
            color: #555;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        /* TABELA */
        .table-panel {
            background: #fff;
            border: 1px solid #e4e7e9;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,.035);
        }

        .table-header {
            color: #0757a9;
            font-size: 14px;
            font-weight: 700;
            padding: 15px 18px 12px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .dashboard-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .dashboard-table th {
            background: #e8ebed;
            color: #333;
            font-size: 19px;
            font-weight: 700;
            text-align: left;
            padding: 6px 7px;
        }

        .dashboard-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e6e8ea;
            font-size: 19px;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dashboard-table th:nth-child(1) { width: 5%; }
        .dashboard-table th:nth-child(2) { width: 18%; }
        .dashboard-table th:nth-child(3) { width: 12%; }
        .dashboard-table th:nth-child(4) { width: 10%; }
        .dashboard-table th:nth-child(5) { width: 17%; }
        .dashboard-table th:nth-child(6) { width: 19%; }
        .dashboard-table th:nth-child(7) { width: 10%; }
        .dashboard-table th:nth-child(8) { width: 9%; }

        .patient-name {
            font-weight: 600;
        }

        .patient-phone {
            color: #8b9196;
            font-size: 9px;
        }

        .action-btn {
            width: 31px;
            height: 28px;
            border: 0;
            border-radius: 3px;
            padding: 0;
            font-size: 13px;
            cursor: pointer;
            margin-right: 2px;
        }

        .edit-btn {
            background: #0874d1;
            color: #fff;
        }

        .complete-btn {
            background: #25a84a;
            color: #fff;
        }

        .empty {
            text-align: center;
            color: #888;
            padding: 20px !important;
        }

        /* Em telas menores, mantém a estrutura sem destruir o layout */
        @media (max-width: 900px) {
            .sidebar {
                width: 180px;
            }

            .main {
                margin-left: 180px;
            }

            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .topbar-info {
                display: none;
            }
        }

        @media (max-width: 600px) {
            .sidebar {
                position: relative;
                width: 100%;
            }

            .main {
                margin-left: 0;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .filter-panel form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-title">SUS-UBS Guarulhos</div>
    </div>

    <ul class="menu">
        <li>
            <a href="dashboard.php" class="active">
                <span class="menu-icon"></span>
                <span>Métricas &amp; Dashboard</span>
            </a>
        </li>
        <li>
            <a href="triagem.php">
                <span class="menu-icon"></span>
                <span>Triagem &amp; Filas (Pacientes)</span>
            </a>
        </li>
        <li>
            <a href="#">
                <span class="menu-icon"></span>
                <span>Prontuário (PEP)</span>
            </a>
        </li>
        <li>
            <a href="vacinacao.php">
                <span class="menu-icon"></span>
                <span>Estoque &amp; Sala de Vacinas</span>
            </a>
        </li>
        <li>
            <a href="estoque.php">
                <span class="menu-icon"></span>
                <span>Estoque de Medicamentos</span>
            </a>
        </li>
    </ul>
</aside>

<main class="main">

    <header class="topbar">
        <h1>Métricas Operacionais da Unidade</h1>

        <div class="topbar-info">
            <strong>Unidade:</strong> UBS Centro Guarulhos
            <span style="color:#b8bec3;">|</span>

            <span class="user-pill">
                <?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'nome usuario'); ?>
            </span>

            <a class="logout" href="logout.php">Sair</a>
        </div>
    </header>

    <section class="content">

        <!-- CARDS -->
        <div class="cards">

            <div class="card-metric">
                <div class="metric-title">📊 AGENDAMENTOS HOJE</div>
                <div class="metric-value">
                    <?php echo (int)($hoje['total_hoje'] ?? 0); ?>
                </div>
                <div class="metric-description">
                    <?php echo (int)($hoje['concluidos_hoje'] ?? 0); ?> concluídos hoje
                </div>
            </div>

            <div class="card-metric green">
                <div class="metric-title">✅ CONCLUÍDOS</div>
                <div class="metric-value">
                    <?php echo (int)($stats['concluidos'] ?? 0); ?>
                </div>
                <div class="metric-description">Total de atendimentos concluídos</div>
            </div>

            <div class="card-metric yellow">
                <div class="metric-title">⏳ PENDENTES</div>
                <div class="metric-value">
                    <?php echo (int)($stats['pendentes'] ?? 0); ?>
                </div>
                <div class="metric-description">Aguardando atendimento</div>
            </div>

            <div class="card-metric purple">
                <div class="metric-title">❌ CANCELADOS</div>
                <div class="metric-value">
                    <?php echo (int)($stats['cancelados'] ?? 0); ?>
                </div>
                <div class="metric-description">Total de agendamentos cancelados</div>
            </div>

        </div>

        <!-- PAINÉIS CENTRAIS -->
        <div class="dashboard-grid">

            <!-- ESQUERDA -->
            <section class="panel">
                <h2 class="panel-title">Resumo de Tempo &amp; Espera Diária</h2>

                <div class="summary-main">
                    <strong>Tempo Médio de Espera Geral:</strong> 16 minutos<br>
                    <strong>Pico de Atendimento:</strong> 08:00h - 10:30h
                </div>

                <div class="section-title">
                    Distribuição de Atendimentos Concluídos Hoje
                </div>

                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Setor</th>
                            <th>Concluídos</th>
                            <th>Aguardando</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Consultas Médicas (Clínico/Pediatria)</td>
                            <td>78 pacientes</td>
                            <td>12 pacientes</td>
                        </tr>
                        <tr>
                            <td>Sala de Vacinação</td>
                            <td>64 pacientes</td>
                            <td>5 pacientes</td>
                        </tr>
                        <tr>
                            <td>Farmácia (Dispensação)</td>
                            <td>89 pacientes</td>
                            <td>8 pacientes</td>
                        </tr>
                        <tr>
                            <td>Procedimentos e Exames</td>
                            <td>42 pacientes</td>
                            <td>3 pacientes</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- DIREITA -->
            <section class="panel">
                <h2 class="panel-title">Gestão de Pessoal Hoje (45 Funcionários)</h2>

                <div class="staff-top">
                    De Plantão / Trabalhando Hoje:
                    <span class="staff-badge">35 Ativos</span>
                </div>

                <div class="section-title">
                    Escala de Ausências / Licenças
                </div>

                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Profissional</th>
                            <th>Cargo / Função</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>Dr. Fernando Souza</td>
                            <td>Médico Clínico</td>
                            <td><span class="status-badge status-yellow">Férias</span></td>
                        </tr>
                        <tr>
                            <td>Enf. Patricia Lima</td>
                            <td>Enfermeira Chefe</td>
                            <td><span class="status-badge status-yellow">Férias</span></td>
                        </tr>
                        <tr>
                            <td>Dra. Beatriz Mendes</td>
                            <td>Pediatra</td>
                            <td><span class="status-badge status-red">Folga Escala</span></td>
                        </tr>
                        <tr>
                            <td>Lucas Andrade</td>
                            <td>Recepcionista</td>
                            <td><span class="status-badge status-red">Folga Escala</span></td>
                        </tr>
                        <tr>
                            <td>Carla Silveira</td>
                            <td>Técnica de Enfermagem</td>
                            <td><span class="status-badge status-blue">Atestado Médico</span></td>
                        </tr>
                    </tbody>
                </table>
            </section>

        </div>

        <!-- FILTROS -->
        <section class="filter-panel">

            <div class="filter-title">Filtros de Agendamentos</div>

            <form method="GET">

                <div>
                    <label class="filter-label">Filtrar por Status</label>

                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="">Todos</option>

                        <option value="pendente"
                            <?php echo $filtro_status === 'pendente' ? 'selected' : ''; ?>>
                            Pendente
                        </option>

                        <option value="concluido"
                            <?php echo $filtro_status === 'concluido' ? 'selected' : ''; ?>>
                            Concluído
                        </option>

                        <option value="cancelado"
                            <?php echo $filtro_status === 'cancelado' ? 'selected' : ''; ?>>
                            Cancelado
                        </option>
                    </select>
                </div>

                <div>
                    <label class="filter-label">Filtrar por Data</label>

                    <input
                        type="date"
                        class="form-control"
                        name="data"
                        value="<?php echo htmlspecialchars($filtro_data); ?>"
                        onchange="this.form.submit()"
                    >
                </div>

                <a href="dashboard.php" class="btn-clear">Limpar filtros</a>

            </form>
        </section>

        <!-- TABELA -->
        <section class="table-panel">

            <div class="table-header">
                Agendamentos Recentes
            </div>

            <div class="table-responsive">

                <table class="dashboard-table">

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
                            <td colspan="8" class="empty">
                                Nenhum agendamento encontrado.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($agendamentos as $agendamento): ?>

                            <tr>

                                <td>
                                    #<?php echo (int)$agendamento['id']; ?>
                                </td>

                                <td>
                                    <div class="patient-name">
                                        <?php echo htmlspecialchars($agendamento['nome_completo']); ?>
                                    </div>

                                    <div class="patient-phone">
                                        <?php echo htmlspecialchars($agendamento['telefone']); ?>
                                    </div>
                                </td>

                                <td>
                                    <?php
                                        $data = new DateTime($agendamento['data_hora']);
                                        echo $data->format('d/m/Y H:i');
                                    ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars(ucfirst($agendamento['tipo'])); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($agendamento['medico'] ?? 'Não atribuído'); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($agendamento['unidade']); ?>
                                </td>

                                <td>

                                    <?php
                                        $status = $agendamento['status'];

                                        $status_texto = [
                                            'pendente' => '⏳ Pendente',
                                            'concluido' => '✅ Concluído',
                                            'cancelado' => '❌ Cancelado'
                                        ];

                                        $status_class = [
                                            'pendente' => 'status-yellow',
                                            'concluido' => 'status-blue',
                                            'cancelado' => 'status-red'
                                        ];
                                    ?>

                                    <span class="status-badge <?php echo $status_class[$status] ?? 'status-blue'; ?>">
                                        <?php echo htmlspecialchars($status_texto[$status] ?? $status); ?>
                                    </span>

                                </td>

                                <td>

                                    <button
                                        class="action-btn edit-btn"
                                        title="Editar"
                                        onclick="editarAgendamento(<?php echo (int)$agendamento['id']; ?>)"
                                    >
                                        ✎
                                    </button>

                                    <?php if ($agendamento['status'] === 'pendente'): ?>

                                        <button
                                            class="action-btn complete-btn"
                                            title="Concluir"
                                            onclick="marcarConcluido(<?php echo (int)$agendamento['id']; ?>)"
                                        >
                                            ✓
                                        </button>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </section>

    </section>
</main>

<script>
function editarAgendamento(id) {
    window.location.href = 'agendamentos.php?acao=editar&id=' + id;
}

function marcarConcluido(id) {

    if (!confirm('Marcar este agendamento como concluído?')) {
        return;
    }

    fetch('api/processar_agendamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
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

    })
    .catch(error => {
        alert('❌ Erro ao processar a solicitação.');
        console.error(error);
    });
}
</script>

</body>
</html>

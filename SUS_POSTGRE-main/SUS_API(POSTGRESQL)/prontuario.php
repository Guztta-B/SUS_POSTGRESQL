<?php
// prontuario.php - Prontuário Eletrônico do Paciente (PEP): busca + histórico

include_once 'conexao.php';
include_once 'auth.php';

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$busca = trim($_GET['busca'] ?? '');
$pacienteRg = $_GET['paciente'] ?? '';

$queryPacientes = "SELECT nome_completo, rg, telefone,
                          COUNT(*) as total_agendamentos,
                          MAX(data_hora) as ultimo_atendimento
                   FROM agendamentos
                   WHERE 1=1";
if ($busca !== '') {
    $queryPacientes .= " AND (nome_completo ILIKE :busca1 OR rg ILIKE :busca2 OR telefone ILIKE :busca3)";
}
$queryPacientes .= " GROUP BY nome_completo, rg, telefone ORDER BY ultimo_atendimento DESC";

$stmtPacientes = $conn->prepare($queryPacientes);
if ($busca !== '') {
    $termo = '%' . $busca . '%';
    $stmtPacientes->bindParam(':busca1', $termo);
    $stmtPacientes->bindParam(':busca2', $termo);
    $stmtPacientes->bindParam(':busca3', $termo);
}
$stmtPacientes->execute();
$pacientes = $stmtPacientes->fetchAll(PDO::FETCH_ASSOC);

$historico = [];
$nomePacienteSelecionado = '';
if ($pacienteRg !== '') {
    $stmtHist = $conn->prepare("SELECT a.data_hora, a.tipo, a.status, a.unidade, a.nome_completo, f.nome AS medico
                                 FROM agendamentos a
                                 LEFT JOIN funcionarios f ON a.funcionario_id = f.id
                                 WHERE a.rg = :rg
                                 ORDER BY a.data_hora DESC");
    $stmtHist->bindParam(':rg', $pacienteRg);
    $stmtHist->execute();
    $historico = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($historico)) {
        $nomePacienteSelecionado = $historico[0]['nome_completo'];
    }
}

$status_texto = ['pendente' => 'Pendente', 'concluido' => 'Concluído', 'cancelado' => 'Cancelado'];
$status_class = ['pendente' => 'status-yellow', 'concluido' => 'status-green', 'cancelado' => 'status-red'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUS-UBS Guarulhos | Prontuário</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }
        :root { --blue: #0757a9; --bg: #f1f3f5; --border: #e2e5e8; --text: #20252a; --muted: #777f86; }
        html, body { margin: 0; min-height: 100%; font-family: "Segoe UI", Tahoma, Arial, sans-serif; background: var(--bg); color: var(--text); font-size: 12px; }
        body { overflow-x: hidden; }
        .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 225px; background: #0757a9; color: #fff; z-index: 10; }
        .brand { height: 82px; padding: 17px 14px 10px; border-bottom: 1px solid rgba(255,255,255,.14); }
        .brand-title { font-size: 25px; font-weight: 700; line-height: 21px; }
        .menu { list-style: none; padding: 0; margin: 0; }
        .menu li { border-bottom: 1px solid rgba(255,255,255,.11); }
        .menu a { height: 48px; display: flex; align-items: center; gap: 7px; padding: 0 14px; color: #fff; text-decoration: none; font-size: 14px; white-space: nowrap; }
        .menu a:hover, .menu a.active { background: #0b62b9; }
        .menu a.active { border-left: 4px solid #fff; padding-left: 10px; }
        .menu-icon { width: 18px; text-align: center; font-size: 13px; flex: 0 0 10px; }
        .main { margin-left: 225px; min-height: 100vh; }
        .topbar { height: 50px; background: #fff; border-top: 1px solid #0b5db1; border-bottom: 1px solid #dfe2e5; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; }
        .topbar h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .topbar-info { display: flex; align-items: center; gap: 4px; font-size: 13px; white-space: nowrap; }
        .user-pill { background: #dce1e6; color: #333; padding: 5px 13px; border-radius: 13px; margin-left: 4px; }
        .logout { color: #0757a9; text-decoration: none; margin-left: 4px; }
        .content { padding: 22px 24px 28px; }
        .panel { background: #fff; border: 1px solid #e4e7e9; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.035); margin-bottom: 22px; }
        .panel-title { color: #0757a9; font-size: 19px; font-weight: 700; margin: 0 0 14px; padding-bottom: 10px; border-bottom: 1px solid #e1e4e7; }
        .panel form { display: grid; grid-template-columns: 1fr 120px; gap: 10px; align-items: end; }
        .filter-label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 4px; }
        .panel .form-control { width: 100%; height: 38px; padding: 5px 9px; border: 1px solid #cfd4d8; border-radius: 3px; font-size: 12px; color: #555; }
        .table-panel { background: #fff; border: 1px solid #e4e7e9; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.035); margin-bottom: 22px; }
        .table-header { color: #0757a9; font-size: 14px; font-weight: 700; padding: 15px 18px 12px; }
        .table-responsive { overflow-x: auto; }
        .dashboard-table { width: 100%; border-collapse: collapse; }
        .dashboard-table th { background: #e8ebed; color: #333; font-size: 12px; font-weight: 700; text-align: left; padding: 10px 9px; }
        .dashboard-table td { padding: 10px 9px; border-bottom: 1px solid #e6e8ea; font-size: 13px; vertical-align: middle; }
        .status-badge { display: inline-block; padding: 3px 9px; border-radius: 3px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .status-yellow { background: #ffbd00; color: #222; }
        .status-red { background: #e73549; color: #fff; }
        .status-green { background: #25a84a; color: #fff; }
        .btn-acao { border: none; border-radius: 4px; padding: 6px 12px; font-size: 12px; font-weight: 600; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-azul { background: #0874d1; }
        .patient-name { font-weight: 600; }
        .patient-phone { color: #8b9196; font-size: 11px; }
        .empty { text-align: center; color: #888; padding: 20px !important; }
        @media (max-width: 900px) { .sidebar { width: 180px; } .main { margin-left: 180px; } .topbar-info { display: none; } }
        @media (max-width: 600px) { .sidebar { position: relative; width: 100%; } .main { margin-left: 0; } .panel form { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand"><div class="brand-title">SUS-UBS Guarulhos</div></div>
    <ul class="menu">
        <li><a href="dashboard.php"><span class="menu-icon"></span><span>Métricas &amp; Dashboard</span></a></li>
        <li><a href="triagem.php"><span class="menu-icon"></span><span>Triagem &amp; Filas (Pacientes)</span></a></li>
        <li><a href="prontuario.php" class="active"><span class="menu-icon"></span><span>Prontuário (PEP)</span></a></li>
        <li><a href="vacinacao.php"><span class="menu-icon"></span><span>Estoque &amp; Sala de Vacinas</span></a></li>
        <li><a href="estoque.php"><span class="menu-icon"></span><span>Estoque de Medicamentos</span></a></li>
    </ul>
</aside>

<main class="main">
    <header class="topbar">
        <h1>Prontuário Eletrônico do Paciente</h1>
        <div class="topbar-info">
            <strong>Unidade:</strong> UBS Centro Guarulhos
            <span style="color:#b8bec3;">|</span>
            <span class="user-pill"><?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'nome usuario'); ?></span>
            <a class="logout" href="logout.php">Sair</a>
        </div>
    </header>

    <section class="content">

        <section class="panel">
            <h2 class="panel-title">Buscar Paciente</h2>
            <form method="GET" action="prontuario.php">
                <div>
                    <label class="filter-label">Nome, RG ou telefone</label>
                    <input type="text" class="form-control" name="busca" value="<?php echo htmlspecialchars($busca); ?>">
                </div>
                <button type="submit" class="btn-acao btn-azul" style="height:38px;">Buscar</button>
            </form>
        </section>

        <section class="table-panel">
            <div class="table-header">Pacientes</div>
            <div class="table-responsive">
                <table class="dashboard-table">
                    <thead>
                        <tr><th>Paciente</th><th>RG</th><th>Telefone</th><th>Último Atendimento</th><th>Total de Agendamentos</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pacientes)): ?>
                            <tr><td colspan="6" class="empty">Nenhum paciente encontrado.</td></tr>
                        <?php else: foreach ($pacientes as $p): ?>
                            <tr>
                                <td class="patient-name"><?php echo htmlspecialchars($p['nome_completo']); ?></td>
                                <td><?php echo htmlspecialchars($p['rg']); ?></td>
                                <td><?php echo htmlspecialchars($p['telefone']); ?></td>
                                <td><?php echo (new DateTime($p['ultimo_atendimento']))->format('d/m/Y'); ?></td>
                                <td><?php echo (int)$p['total_agendamentos']; ?></td>
                                <td>
                                    <a class="btn-acao btn-azul" href="prontuario.php?paciente=<?php echo urlencode($p['rg']); ?>">Ver Histórico</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($pacienteRg !== ''): ?>
        <section class="table-panel">
            <div class="table-header">Histórico<?php echo $nomePacienteSelecionado !== '' ? ' — ' . htmlspecialchars($nomePacienteSelecionado) : ''; ?></div>
            <div class="table-responsive">
                <table class="dashboard-table">
                    <thead>
                        <tr><th>Data</th><th>Tipo</th><th>Médico</th><th>Unidade</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historico)): ?>
                            <tr><td colspan="5" class="empty">Nenhum agendamento encontrado para esse paciente.</td></tr>
                        <?php else: foreach ($historico as $h): ?>
                            <tr>
                                <td><?php echo (new DateTime($h['data_hora']))->format('d/m/Y H:i'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($h['tipo'])); ?></td>
                                <td><?php echo htmlspecialchars($h['medico'] ?? 'Não atribuído'); ?></td>
                                <td><?php echo htmlspecialchars($h['unidade']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class[$h['status']] ?? 'status-yellow'; ?>">
                                        <?php echo $status_texto[$h['status']] ?? $h['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

    </section>
</main>
</body>
</html>

<?php
// triagem.php - Fila de Triagem / Atendimento do dia

include_once 'conexao.php';
include_once 'auth.php';

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Ação: chamar paciente (aguardando -> em atendimento)
if (($_GET['acao'] ?? '') === 'chamar' && !empty($_GET['id'])) {
    $stmt = $conn->prepare("UPDATE fila_triagem SET status_fila = 'em_atendimento' WHERE id = :id");
    $idFila = (int) $_GET['id'];
    $stmt->bindParam(':id', $idFila, PDO::PARAM_INT);
    $stmt->execute();
    header('Location: triagem.php');
    exit();
}

// Ação: concluir atendimento
if (($_GET['acao'] ?? '') === 'concluir' && !empty($_GET['id'])) {
    $stmt = $conn->prepare("UPDATE fila_triagem SET status_fila = 'concluido' WHERE id = :id");
    $idFila = (int) $_GET['id'];
    $stmt->bindParam(':id', $idFila, PDO::PARAM_INT);
    $stmt->execute();
    header('Location: triagem.php');
    exit();
}

// Ação: adicionar um agendamento de hoje na fila
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'adicionar') {
    $agendamentoId = (int) $_POST['agendamento_id'];
    $prioridade = $_POST['prioridade'] === 'urgente' ? 'urgente' : 'normal';
    $senha = 'S' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO fila_triagem (agendamento_id, senha, prioridade, hora_chegada, status_fila)
                             VALUES (:agendamento_id, :senha, :prioridade, NOW(), 'aguardando')");
    $stmt->bindParam(':agendamento_id', $agendamentoId, PDO::PARAM_INT);
    $stmt->bindParam(':senha', $senha);
    $stmt->bindParam(':prioridade', $prioridade);
    $stmt->execute();
    header('Location: triagem.php');
    exit();
}

// Fila de hoje, urgentes primeiro
$queryFila = "SELECT ft.id, ft.senha, ft.prioridade, ft.hora_chegada, ft.status_fila,
                     a.nome_completo, a.tipo
              FROM fila_triagem ft
              JOIN agendamentos a ON ft.agendamento_id = a.id
              WHERE CAST(ft.hora_chegada AS DATE) = CURRENT_DATE
              ORDER BY CASE ft.prioridade WHEN 'urgente' THEN 0 ELSE 1 END, ft.hora_chegada ASC";
$stmtFila = $conn->prepare($queryFila);
$stmtFila->execute();
$fila = $stmtFila->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas do dia
$queryStatsFila = "SELECT
    SUM(CASE WHEN status_fila = 'aguardando' THEN 1 ELSE 0 END) as aguardando,
    SUM(CASE WHEN status_fila = 'em_atendimento' THEN 1 ELSE 0 END) as em_atendimento,
    SUM(CASE WHEN status_fila = 'concluido' THEN 1 ELSE 0 END) as concluidos
FROM fila_triagem WHERE CAST(hora_chegada AS DATE) = CURRENT_DATE";
$stmtStatsFila = $conn->prepare($queryStatsFila);
$stmtStatsFila->execute();
$statsFila = $stmtStatsFila->fetch(PDO::FETCH_ASSOC);

// Agendamentos de hoje que ainda não entraram na fila (para o formulário abaixo)
$queryDisponiveis = "SELECT a.id, a.nome_completo, a.tipo, a.data_hora
                      FROM agendamentos a
                      WHERE CAST(a.data_hora AS DATE) = CURRENT_DATE
                        AND a.status = 'pendente'
                        AND a.id NOT IN (SELECT agendamento_id FROM fila_triagem)
                      ORDER BY a.data_hora ASC";
$stmtDisponiveis = $conn->prepare($queryDisponiveis);
$stmtDisponiveis->execute();
$disponiveis = $stmtDisponiveis->fetchAll(PDO::FETCH_ASSOC);

$status_texto_fila = ['aguardando' => 'Aguardando', 'em_atendimento' => 'Em atendimento', 'concluido' => 'Concluído'];
$status_class_fila = ['aguardando' => 'status-blue', 'em_atendimento' => 'status-yellow', 'concluido' => 'status-green'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUS-UBS Guarulhos | Triagem e Filas</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }
        :root {
            --blue: #0757a9; --blue-dark: #064b91; --bg: #f1f3f5;
            --border: #e2e5e8; --text: #20252a; --muted: #777f86;
        }
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
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 22px; }
        .card-metric { position: relative; height: 100px; background: #fff; border: 1px solid #e6e8ea; border-radius: 8px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .card-metric::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; border-radius: 5px 0 0 5px; background: #0757a9; }
        .card-metric.green::before { background: #25a84a; }
        .card-metric.yellow::before { background: #ffb800; }
        .metric-title { color: #70777d; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; }
        .metric-value { font-size: 30px; line-height: 30px; font-weight: 700; }
        .metric-description { color: #8b9196; font-size: 15px; margin-top: 5px; }
        .panel { background: #fff; border: 1px solid #e4e7e9; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.035); margin-bottom: 22px; }
        .panel-title { color: #0757a9; font-size: 19px; font-weight: 700; margin: 0 0 14px; padding-bottom: 10px; border-bottom: 1px solid #e1e4e7; }
        .panel form { display: grid; grid-template-columns: 2fr 1fr 120px; gap: 10px; align-items: end; }
        .filter-label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 4px; }
        .panel .form-control, .panel .form-select { width: 100%; height: 38px; padding: 5px 9px; border: 1px solid #cfd4d8; border-radius: 3px; font-size: 12px; color: #555; }
        .table-panel { background: #fff; border: 1px solid #e4e7e9; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.035); }
        .table-header { color: #0757a9; font-size: 14px; font-weight: 700; padding: 15px 18px 12px; }
        .table-responsive { overflow-x: auto; }
        .dashboard-table { width: 100%; border-collapse: collapse; }
        .dashboard-table th { background: #e8ebed; color: #333; font-size: 12px; font-weight: 700; text-align: left; padding: 10px 9px; }
        .dashboard-table td { padding: 10px 9px; border-bottom: 1px solid #e6e8ea; font-size: 13px; vertical-align: middle; }
        .status-badge { display: inline-block; padding: 3px 9px; border-radius: 3px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .status-yellow { background: #ffbd00; color: #222; }
        .status-red { background: #e73549; color: #fff; }
        .status-blue { background: #075fbb; color: #fff; }
        .status-green { background: #25a84a; color: #fff; }
        .btn-acao { border: none; border-radius: 4px; padding: 6px 12px; font-size: 12px; font-weight: 600; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-azul { background: #0874d1; }
        .btn-verde { background: #25a84a; }
        .empty { text-align: center; color: #888; padding: 20px !important; }
        @media (max-width: 900px) {
            .sidebar { width: 180px; } .main { margin-left: 180px; }
            .cards { grid-template-columns: repeat(2, 1fr); } .topbar-info { display: none; }
        }
        @media (max-width: 600px) {
            .sidebar { position: relative; width: 100%; } .main { margin-left: 0; }
            .cards { grid-template-columns: 1fr; } .panel form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand"><div class="brand-title">SUS-UBS Guarulhos</div></div>
    <ul class="menu">
        <li><a href="dashboard.php"><span class="menu-icon"></span><span>Métricas &amp; Dashboard</span></a></li>
        <li><a href="triagem.php" class="active"><span class="menu-icon"></span><span>Triagem &amp; Filas (Pacientes)</span></a></li>
        <li><a href="prontuario.php"><span class="menu-icon"></span><span>Prontuário (PEP)</span></a></li>
        <li><a href="vacinacao.php"><span class="menu-icon"></span><span>Estoque &amp; Sala de Vacinas</span></a></li>
        <li><a href="estoque.php"><span class="menu-icon"></span><span>Estoque de Medicamentos</span></a></li>
    </ul>
</aside>

<main class="main">
    <header class="topbar">
        <h1>Triagem e Filas de Pacientes</h1>
        <div class="topbar-info">
            <strong>Unidade:</strong> UBS Centro Guarulhos
            <span style="color:#b8bec3;">|</span>
            <span class="user-pill"><?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'nome usuario'); ?></span>
            <a class="logout" href="logout.php">Sair</a>
        </div>
    </header>

    <section class="content">

        <div class="cards">
            <div class="card-metric">
                <div class="metric-title">NA FILA</div>
                <div class="metric-value"><?php echo (int)($statsFila['aguardando'] ?? 0); ?></div>
                <div class="metric-description">Aguardando chamada</div>
            </div>
            <div class="card-metric yellow">
                <div class="metric-title">EM ATENDIMENTO</div>
                <div class="metric-value"><?php echo (int)($statsFila['em_atendimento'] ?? 0); ?></div>
                <div class="metric-description">Sendo atendidos agora</div>
            </div>
            <div class="card-metric green">
                <div class="metric-title">CONCLUÍDOS HOJE</div>
                <div class="metric-value"><?php echo (int)($statsFila['concluidos'] ?? 0); ?></div>
                <div class="metric-description">Senhas já chamadas e finalizadas</div>
            </div>
        </div>

        <section class="panel">
            <h2 class="panel-title">Adicionar Agendamento de Hoje à Fila</h2>
            <form method="POST" action="triagem.php">
                <input type="hidden" name="acao" value="adicionar">
                <div>
                    <label class="filter-label">Agendamento de hoje</label>
                    <select class="form-select" name="agendamento_id" required>
                        <option value="">Selecione um paciente</option>
                        <?php foreach ($disponiveis as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>">
                                <?php echo htmlspecialchars($d['nome_completo']) . ' — ' . htmlspecialchars(ucfirst($d['tipo'])) . ' — ' . (new DateTime($d['data_hora']))->format('H:i'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="filter-label">Prioridade</label>
                    <select class="form-select" name="prioridade">
                        <option value="normal">Normal</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
                <button type="submit" class="btn-acao btn-azul" style="height:38px;">Adicionar</button>
            </form>
        </section>

        <section class="table-panel">
            <div class="table-header">Fila de Espera de Hoje</div>
            <div class="table-responsive">
                <table class="dashboard-table">
                    <thead>
                        <tr><th>Senha</th><th>Paciente</th><th>Tipo</th><th>Prioridade</th><th>Chegada</th><th>Status</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fila)): ?>
                            <tr><td colspan="7" class="empty">Nenhum paciente na fila hoje.</td></tr>
                        <?php else: foreach ($fila as $f): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($f['senha']); ?></td>
                                <td><?php echo htmlspecialchars($f['nome_completo']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($f['tipo'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $f['prioridade'] === 'urgente' ? 'status-red' : 'status-green'; ?>">
                                        <?php echo $f['prioridade'] === 'urgente' ? 'Urgente' : 'Normal'; ?>
                                    </span>
                                </td>
                                <td><?php echo (new DateTime($f['hora_chegada']))->format('H:i'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class_fila[$f['status_fila']]; ?>">
                                        <?php echo $status_texto_fila[$f['status_fila']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($f['status_fila'] === 'aguardando'): ?>
                                        <a class="btn-acao btn-azul" href="triagem.php?acao=chamar&id=<?php echo (int)$f['id']; ?>" onclick="return confirm('Chamar este paciente?');">Chamar</a>
                                    <?php elseif ($f['status_fila'] === 'em_atendimento'): ?>
                                        <a class="btn-acao btn-verde" href="triagem.php?acao=concluir&id=<?php echo (int)$f['id']; ?>" onclick="return confirm('Concluir este atendimento?');">Concluir</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </section>
</main>
</body>
</html>

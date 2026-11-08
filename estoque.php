<?php
// estoque.php - Estoque de Medicamentos

include_once 'conexao.php';
include_once 'auth.php';

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'repor') {
    $id = (int) $_POST['id'];
    $qtd = (int) $_POST['quantidade'];
    $stmt = $conn->prepare("UPDATE estoque_medicamentos SET quantidade = quantidade + :qtd WHERE id = :id");
    $stmt->bindParam(':qtd', $qtd);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    header('Location: estoque.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    $stmt = $conn->prepare("INSERT INTO estoque_medicamentos (nome, lote, validade, quantidade, estoque_minimo)
                             VALUES (:nome, :lote, :validade, :quantidade, :estoque_minimo)");
    $stmt->bindParam(':nome', $_POST['nome']);
    $stmt->bindParam(':lote', $_POST['lote']);
    $stmt->bindParam(':validade', $_POST['validade']);
    $quantidade = (int) $_POST['quantidade'];
    $stmt->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
    $estoqueMinimo = (int) $_POST['estoque_minimo'];
    $stmt->bindParam(':estoque_minimo', $estoqueMinimo, PDO::PARAM_INT);
    $stmt->execute();
    header('Location: estoque.php');
    exit();
}

$stmtVacinas = $conn->prepare("SELECT * FROM estoque_medicamentos ORDER BY nome ASC");
$stmtVacinas->execute();
$vacinas = $stmtVacinas->fetchAll(PDO::FETCH_ASSOC);

$totalTipos = count($vacinas);
$abaixoMinimo = 0;
foreach ($vacinas as $v) {
    if ((int)$v['quantidade'] < (int)$v['estoque_minimo']) $abaixoMinimo++;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUS-UBS Guarulhos | Estoque de Medicamentos</title>

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
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 22px; }
        .card-metric { position: relative; height: 100px; background: #fff; border: 1px solid #e6e8ea; border-radius: 8px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .card-metric::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; border-radius: 5px 0 0 5px; background: #0757a9; }
        .card-metric.green::before { background: #25a84a; }
        .card-metric.red::before { background: #e73549; }
        .metric-title { color: #70777d; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; }
        .metric-value { font-size: 30px; line-height: 30px; font-weight: 700; }
        .metric-description { color: #8b9196; font-size: 15px; margin-top: 5px; }
        .panel { background: #fff; border: 1px solid #e4e7e9; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.035); margin-bottom: 22px; }
        .panel-title { color: #0757a9; font-size: 19px; font-weight: 700; margin: 0 0 14px; padding-bottom: 10px; border-bottom: 1px solid #e1e4e7; }
        .panel form { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr 120px; gap: 10px; align-items: end; }
        .filter-label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 4px; }
        .panel .form-control { width: 100%; height: 38px; padding: 5px 9px; border: 1px solid #cfd4d8; border-radius: 3px; font-size: 12px; color: #555; }
        .table-panel { background: #fff; border: 1px solid #e4e7e9; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.035); }
        .table-header { color: #0757a9; font-size: 14px; font-weight: 700; padding: 15px 18px 12px; }
        .table-responsive { overflow-x: auto; }
        .dashboard-table { width: 100%; border-collapse: collapse; }
        .dashboard-table th { background: #e8ebed; color: #333; font-size: 12px; font-weight: 700; text-align: left; padding: 10px 9px; }
        .dashboard-table td { padding: 10px 9px; border-bottom: 1px solid #e6e8ea; font-size: 13px; vertical-align: middle; }
        .status-badge { display: inline-block; padding: 3px 9px; border-radius: 3px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .status-red { background: #e73549; color: #fff; }
        .status-green { background: #25a84a; color: #fff; }
        .repor-form { display: flex; gap: 6px; align-items: center; }
        .repor-form input { width: 60px; height: 30px; border: 1px solid #cfd4d8; border-radius: 3px; padding: 2px 6px; font-size: 12px; }
        .btn-acao { border: none; border-radius: 4px; padding: 6px 12px; font-size: 12px; font-weight: 600; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-azul { background: #0874d1; }
        .empty { text-align: center; color: #888; padding: 20px !important; }
        @media (max-width: 900px) { .sidebar { width: 180px; } .main { margin-left: 180px; } .cards { grid-template-columns: repeat(2, 1fr); } .topbar-info { display: none; } }
        @media (max-width: 600px) { .sidebar { position: relative; width: 100%; } .main { margin-left: 0; } .cards { grid-template-columns: 1fr; } .panel form { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand"><div class="brand-title">SUS-UBS Guarulhos</div></div>
    <ul class="menu">
        <li><a href="dashboard.php"><span class="menu-icon"></span><span>Métricas &amp; Dashboard</span></a></li>
        <li><a href="triagem.php"><span class="menu-icon"></span><span>Triagem &amp; Filas (Pacientes)</span></a></li>
        <li><a href="prontuario.php"><span class="menu-icon"></span><span>Prontuário (PEP)</span></a></li>
        <li><a href="vacinacao.php"><span class="menu-icon"></span><span>Estoque &amp; Sala de Vacinas</span></a></li>
        <li><a href="estoque.php" class="active"><span class="menu-icon"></span><span>Estoque de Medicamentos</span></a></li>
    </ul>
</aside>

<main class="main">
    <header class="topbar">
        <h1>Estoque de Medicamentos</h1>
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
                <div class="metric-title">MEDICAMENTOS CADASTRADOS</div>
                <div class="metric-value"><?php echo $totalTipos; ?></div>
                <div class="metric-description">Itens diferentes em estoque</div>
            </div>
            <div class="card-metric red">
                <div class="metric-title">ABAIXO DO MÍNIMO</div>
                <div class="metric-value"><?php echo $abaixoMinimo; ?></div>
                <div class="metric-description">Precisam de reposição</div>
            </div>
        </div>

        <section class="panel">
            <h2 class="panel-title">Cadastrar Novo Medicamento</h2>
            <form method="POST" action="estoque.php" style="grid-template-columns: 1.3fr 1fr 1fr .8fr .8fr 110px;">
                <input type="hidden" name="acao" value="cadastrar">
                <div><label class="filter-label">Nome</label><input class="form-control" name="nome" required></div>
                <div><label class="filter-label">Lote</label><input class="form-control" name="lote" required></div>
                <div><label class="filter-label">Validade</label><input class="form-control" type="date" name="validade" required></div>
                <div><label class="filter-label">Qtd. inicial</label><input class="form-control" type="number" name="quantidade" min="0" value="0" required></div>
                <div><label class="filter-label">Estoque mínimo</label><input class="form-control" type="number" name="estoque_minimo" min="0" value="0" required></div>
                <button type="submit" class="btn-acao btn-azul" style="height:38px;">Cadastrar</button>
            </form>
        </section>

        <section class="table-panel">
            <div class="table-header">Estoque de Medicamentos</div>
            <div class="table-responsive">
                <table class="dashboard-table">
                    <thead>
                        <tr><th>Medicamento</th><th>Lote</th><th>Validade</th><th>Quantidade</th><th>Estoque Mínimo</th><th>Status</th><th>Repor</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vacinas)): ?>
                            <tr><td colspan="7" class="empty">Nenhum medicamento cadastrado.</td></tr>
                        <?php else: foreach ($vacinas as $v): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($v['nome']); ?></td>
                                <td><?php echo htmlspecialchars($v['lote']); ?></td>
                                <td><?php echo (new DateTime($v['validade']))->format('m/Y'); ?></td>
                                <td><?php echo (int)$v['quantidade']; ?></td>
                                <td><?php echo (int)$v['estoque_minimo']; ?></td>
                                <td>
                                    <?php if ((int)$v['quantidade'] < (int)$v['estoque_minimo']): ?>
                                        <span class="status-badge status-red">Baixo</span>
                                    <?php else: ?>
                                        <span class="status-badge status-green">OK</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="estoque.php" class="repor-form">
                                        <input type="hidden" name="acao" value="repor">
                                        <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                                        <input type="number" name="quantidade" min="1" value="10" required>
                                        <button type="submit" class="btn-acao btn-azul">Repor</button>
                                    </form>
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

<?php
// gerar_senha.php - Gera hash bcrypt para qualquer senha

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha = $_POST['senha'] ?? '';
    
    if (!empty($senha)) {
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10]);
        ?>
        <div style="background: #dcfce7; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h3>✅ Hash gerado com sucesso!</h3>
            <p><strong>Senha original:</strong> <?php echo htmlspecialchars($senha); ?></p>
            <p><strong>Hash bcrypt:</strong></p>
            <code style="display: block; background: #f3f4f6; padding: 10px; border-radius: 4px; word-break: break-all;">
                <?php echo $hash; ?>
            </code>
            <p style="margin-top: 15px; color: #666;">
                💡 Copie esse hash e coloque no banco de dados na tabela <code>usuarios_admin</code>
            </p>
        </div>
        <?php
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerar Hash de Senha</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background: #f3f4f6;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #1e40af;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
        }
        
        input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        input:focus {
            border-color: #1e40af;
            outline: none;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #1e40af 0%, #16a34a 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
        }
        
        button:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Gerar Hash de Senha</h1>
        <p>Digite a senha que você quer usar e ela será convertida em um hash seguro bcrypt.</p>
        
        <form method="POST">
            <div class="form-group">
                <label for="senha">Digite a nova senha:</label>
                <input 
                    type="password" 
                    id="senha" 
                    name="senha" 
                    placeholder="Ex: MinhaSenh@123"
                    required
                    autofocus
                >
            </div>
            
            <button type="submit">🔒 Gerar Hash</button>
        </form>
    </div>
</body>
</html>
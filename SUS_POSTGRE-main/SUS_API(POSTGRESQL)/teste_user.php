<?php
try {
    $conn = new PDO("pgsql:host=localhost;port=5432;dbname=sus_guarulhos_db", "postgres", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $email = 'gerente@sus.local';
    $senha_teste = 'teste';
    
    $query = "SELECT id, email, senha FROM usuarios_admin WHERE email = :email";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario) {
        echo "<h2>✅ Usuário encontrado!</h2>";
        echo "<p><strong>Email:</strong> " . $usuario['email'] . "</p>";
        echo "<p><strong>Senha no banco (hash):</strong> " . substr($usuario['senha'], 0, 20) . "...</p>";
        
        // Testa se a senha bate
        if (password_verify($senha_teste, $usuario['senha'])) {
            echo "<p style='color: green;'><strong>✅ Senha CORRETA!</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>❌ Senha ERRADA!</strong></p>";
            echo "<p>A senha '12345' não bate com o hash no banco.</p>";
        }
    } else {
        echo "<p style='color: red;'><strong>❌ Usuário não encontrado!</strong></p>";
    }
    
} catch(PDOException $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
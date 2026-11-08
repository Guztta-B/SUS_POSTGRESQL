<?php
// auth.php - Autenticação e Segurança

// session_start() já é chamado em login.php

// =====================================================
function fazer_login($email, $senha, $conn) {
    $query = "SELECT id, nome, email, perfil, senha FROM usuarios_admin WHERE email = :email AND ativo = TRUE";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Atualizar último acesso
        $update = "UPDATE usuarios_admin SET ultimo_acesso = NOW() WHERE id = :id";
        $stmtUpdate = $conn->prepare($update);
        $stmtUpdate->bindParam(':id', $usuario['id']);
        $stmtUpdate->execute();
        
        // Setar sessão
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_perfil'] = $usuario['perfil'];
        
        return true;
    }
    
    return false;
}

// =====================================================
// VERIFICAR SE ESTÁ LOGADO
// =====================================================
function verificar_sessao() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit();
    }
}

// =====================================================
// FAZER LOGOUT
// =====================================================
function fazer_logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

// =====================================================
// VERIFICAR PERMISSÃO (ROLE-BASED ACCESS)
// =====================================================
function verificar_permissao($perfil_necessario) {
    if (!isset($_SESSION['usuario_perfil'])) {
        return false;
    }
    
    $perfis_hierarquia = [
        'admin' => 3,
        'gerente' => 2,
        'funcionario' => 1
    ];
    
    $nivel_usuario = $perfis_hierarquia[$_SESSION['usuario_perfil']] ?? 0;
    $nivel_necessario = $perfis_hierarquia[$perfil_necessario] ?? 0;
    
    return $nivel_usuario >= $nivel_necessario;
}

// =====================================================
// HASH DE SENHA (BCRYPT)
// =====================================================
function hash_senha($senha) {
    return password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10]);
}

// =====================================================
// GERAR TOKEN CSRF
// =====================================================
function gerar_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// =====================================================
// VERIFICAR TOKEN CSRF
// =====================================================
function verificar_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// =====================================================
// SANITIZAR INPUT
// =====================================================
function sanitizar($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// =====================================================
// LOG DE AUDITORIA
// =====================================================
function registrar_auditoria($conn, $acao, $tabela, $id_registro, $detalhes = '') {
    $query = "INSERT INTO auditoria_log (usuario_id, acao, tabela, id_registro, detalhes, ip, user_agent)
              VALUES (:usuario_id, :acao, :tabela, :id_registro, :detalhes, :ip, :user_agent)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':usuario_id', $_SESSION['usuario_id']);
    $stmt->bindParam(':acao', $acao);
    $stmt->bindParam(':tabela', $tabela);
    $stmt->bindParam(':id_registro', $id_registro);
    $stmt->bindParam(':detalhes', $detalhes);
    $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
    $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    
    return $stmt->execute();
}
?>
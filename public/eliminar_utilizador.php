<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

// Apenas administradores podem eliminar
if ($utilizador_logado['role_id'] != ROLE_ADMIN) {
    header("Location: gerir_utilizadores.php?erro=permissao");
    exit;
}

// Só permite POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: gerir_utilizadores.php?erro=metodo_invalido");
    exit;
}

// Validar ID enviado
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    header("Location: gerir_utilizadores.php?erro=id_invalido");
    exit;
}

$id = (int)$_POST['id'];

// Impedir apagar-se a si próprio
if ($id === (int)$utilizador_logado['id']) {
    header("Location: gerir_utilizadores.php?erro=nao_pode_apagar_se");
    exit;
}

// Verificar se existe
$stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE id = ?");
$stmt->execute([$id]);
$utilizador = $stmt->fetch();

if (!$utilizador) {
    header("Location: gerir_utilizadores.php?erro=nao_existe");
    exit;
}

try {
    // Apagar utilizador
    $del = $pdo->prepare("DELETE FROM utilizadores WHERE id = ?");
    adicionarLog(
        $pdo,
        "Eliminou utilizador",
        "Utilizador ID $id eliminado pelo admin ID ".$utilizador_logado['id']
    );
    $del->execute([$id]);

    

    header("Location: gerir_utilizadores.php?sucesso=1");
    exit;

} catch (Exception $e) {
    header("Location: gerir_utilizadores.php?erro=erro_sistema");
    exit;
}
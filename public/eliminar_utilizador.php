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

/// Verificar se existe
$stmt = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ?");
$stmt->execute([$id]);
$utilizador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$utilizador) {
    header("Location: gerir_utilizadores.php?erro=nao_existe");
    exit;
}

$nomeEliminado = $utilizador['nome'];

try {

    // Registar log ANTES de apagar
    adicionarLog(
        $pdo,
        "Eliminou utilizador",
        "Utilizador '{$nomeEliminado}' eliminado pelo admin ".$utilizador_logado['nome']
    );

    // Apagar utilizador
    $del = $pdo->prepare("DELETE FROM utilizadores WHERE id = ?");
    $del->execute([$id]);

    header("Location: gerir_utilizadores.php?sucesso=eliminado");
    exit;

} catch (Exception $e) {
    header("Location: gerir_utilizadores.php?erro=erro_bd");
    exit;
}


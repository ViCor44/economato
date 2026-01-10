<?php
require_once 'config/db.php';
require_once 'src/session_bootstrap.php';

/* BD SUPER LOGIN */
$pdoSuper = new PDO(
    "mysql:host=localhost;dbname=super_login;charset=utf8mb4",
    "root",
    "",
    [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
);

/* BD LOCAL DO SISTEMA */
$pdoLocal = $pdo; // Usar a ligação já existente em config/db.php

$token = $_GET['token'] ?? '';
if (!$token) {
    header("Location: login.php"); exit;
}

/* validar token */
$stmt = $pdoSuper->prepare("
    SELECT t.admin_id, t.system_key
    FROM admin_tokens t
    WHERE t.token = ?
      AND t.used = 0
      AND t.expires_at > NOW()
");
$stmt->execute([$token]);
$t = $stmt->fetch();

if (!$t) {
    header("Location: login.php"); exit;
}

/* marcar token como usado */
$pdoSuper->prepare("
    UPDATE admin_tokens SET used = 1 WHERE token = ?
")->execute([$token]);

/* 🔑 OBTER USER LOCAL MAPEADO */
$stmt = $pdoSuper->prepare("
    SELECT user_id
    FROM admin_user_map
    WHERE admin_id = ? AND system_key = ?
");
$stmt->execute([$t['admin_id'], $t['system_key']]);
$map = $stmt->fetch();

if (!$map) {
    exit('Admin não tem utilizador associado neste sistema.');
}

$userId = $map['user_id'];

/* buscar dados do user local */
$stmt = $pdoLocal->prepare("
    SELECT id, email, role_id, nome
    FROM utilizadores
    WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    exit('Utilizador local não existe.');
}

/* criar sessão EXACTAMENTE como login normal */
session_regenerate_id(true);
$_SESSION['user_id']      = $user['id'];
$_SESSION['user_email']   = $user['email'];
$_SESSION['user_role_id'] = $user['role_id'];
$_SESSION['user_name']    = $user['nome'];

/* FLAGS EXTRA (CRÍTICAS) */
$_SESSION['logged']        = true;
$_SESSION['is_admin']      = true;

header("Location: public/index.php");
exit;
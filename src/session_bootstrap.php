<?php
// src/session_bootstrap.php

// Dar um nome único à sessão deste sistema
session_name('CREWGEST_SESSID');

// Opcional: definir parâmetros do cookie
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',        // ou '/economato' se quiseres restringir
    'secure'   => false,      // true se usares https
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

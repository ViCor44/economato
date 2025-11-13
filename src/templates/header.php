<?php
// /src/templates/header.php - Novo Design
?>

<div class="bg-stone-800 h-2  mt-4"></div>

<header class="bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-8">
                <a href="index.php" class="text-2xl font-bold text-gray-800 hover:text-blue-600">CrewGest</a>

                <?php
                // Teste final com lógica ultra-simplificada
                $mostrar_link_admin = false; // Começa como falso
                if (isset($_SESSION['user_role_id']) && (int)$_SESSION['user_role_id'] === 1) {
                    // Se a role_id da sessão for estritamente igual ao número 1, mostra o link.
                    $mostrar_link_admin = true;
                }
                if ($mostrar_link_admin):
                ?>
                    <nav class="flex border-l border-gray-200 pl-8">
                        <a href="gerir_utilizadores.php" class="text-sm font-medium text-gray-600 hover:text-gray-900">Gerir Utilizadores</a>
                    </nav>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-4">
                <span class="text-gray-600 text-sm">
                    Bem-vindo(a), 
                    <a href="perfil.php" class="font-medium text-blue-600 hover:underline">
                        <?= htmlspecialchars($_SESSION['user_name'] ?? 'Utilizador') ?>
                    </a>
                </span>
                <a href="logout.php" class="text-red-600 hover:text-red-900 font-medium text-sm transition-colors">
                    Sair
                </a>
            </div>
        </div>
    </div>
</header>
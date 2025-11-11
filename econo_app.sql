-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 12-Nov-2025 às 00:32
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `econo_app`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `cacifos`
--

CREATE TABLE `cacifos` (
  `id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `colaborador_id` int(11) DEFAULT NULL,
  `avariado` tinyint(1) DEFAULT 0,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `cacifos`
--

INSERT INTO `cacifos` (`id`, `numero`, `colaborador_id`, `avariado`, `atualizado_em`) VALUES
(1, 2, 1, 0, '2025-11-11 18:50:00'),
(2, 3, 1, 0, '2025-11-11 19:43:00');

-- --------------------------------------------------------

--
-- Estrutura da tabela `colaboradores`
--

CREATE TABLE `colaboradores` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cartao` varchar(50) NOT NULL,
  `departamento_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `colaboradores`
--

INSERT INTO `colaboradores` (`id`, `nome`, `cartao`, `departamento_id`, `ativo`, `criado_em`) VALUES
(1, 'Joaquim', '34215487551', 2, 1, '2025-11-10 23:28:31');

-- --------------------------------------------------------

--
-- Estrutura da tabela `cores`
--

CREATE TABLE `cores` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `cores`
--

INSERT INTO `cores` (`id`, `nome`) VALUES
(1, 'Azul');

-- --------------------------------------------------------

--
-- Estrutura da tabela `departamentos`
--

CREATE TABLE `departamentos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `departamentos`
--

INSERT INTO `departamentos` (`id`, `nome`, `criado_em`) VALUES
(1, 'Piscinas', '2025-11-11 19:45:37'),
(2, 'Técnicos', '2025-11-11 21:01:16');

-- --------------------------------------------------------

--
-- Estrutura da tabela `fardas`
--

CREATE TABLE `fardas` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cor_id` int(11) NOT NULL,
  `tamanho_id` int(11) NOT NULL,
  `departamento_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 0,
  `preco_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `fardas`
--

INSERT INTO `fardas` (`id`, `nome`, `cor_id`, `tamanho_id`, `departamento_id`, `quantidade`, `preco_unitario`, `criado_em`, `atualizado_em`) VALUES
(1, 'Polo', 1, 1, 2, 28, 20.00, '2025-11-11 21:01:42', '2025-11-11 21:31:36'),
(2, 'Calção', 1, 1, 2, 28, 15.00, '2025-11-11 21:02:23', '2025-11-11 21:31:20');

-- --------------------------------------------------------

--
-- Estrutura da tabela `farda_atribuicoes`
--

CREATE TABLE `farda_atribuicoes` (
  `id` int(11) NOT NULL,
  `colaborador_id` int(11) NOT NULL,
  `farda_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `data_atribuicao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `farda_atribuicoes`
--

INSERT INTO `farda_atribuicoes` (`id`, `colaborador_id`, `farda_id`, `quantidade`, `data_atribuicao`) VALUES
(1, 1, 2, 2, '2025-11-11 21:12:34'),
(2, 1, 1, 2, '2025-11-11 21:12:44');

-- --------------------------------------------------------

--
-- Estrutura da tabela `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome_role` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `roles`
--

INSERT INTO `roles` (`id`, `nome_role`) VALUES
(1, 'Admin'),
(2, 'Gestor'),
(3, 'User');

-- --------------------------------------------------------

--
-- Estrutura da tabela `tamanhos`
--

CREATE TABLE `tamanhos` (
  `id` int(11) NOT NULL,
  `nome` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `tamanhos`
--

INSERT INTO `tamanhos` (`id`, `nome`) VALUES
(1, 'L');

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizadores`
--

CREATE TABLE `utilizadores` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `google_authenticator_secret` varchar(255) DEFAULT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `utilizadores`
--

INSERT INTO `utilizadores` (`id`, `nome`, `email`, `password_hash`, `google_authenticator_secret`, `role_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$lTDNseVm5FFBBC54aXOeUer6tEosMRkJVoSS81I.PExD67LjUqtWC', NULL, 1, 1, '2025-11-10 22:39:45', '2025-11-10 22:39:45'),
(8, 'Victor Correia', 'victor.a.correia@gmail.com', '$2y$10$JA0ylTNl5syI5E92OagyZO8DM6L7VA1l65WwwVUJKVxgfivPnrZzq', NULL, 2, 1, '2025-11-11 22:07:38', '2025-11-11 23:26:05');

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `cacifos`
--
ALTER TABLE `cacifos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `colaborador_id` (`colaborador_id`);

--
-- Índices para tabela `colaboradores`
--
ALTER TABLE `colaboradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cartao` (`cartao`),
  ADD KEY `fk_colaboradores_departamento` (`departamento_id`);

--
-- Índices para tabela `cores`
--
ALTER TABLE `cores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices para tabela `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices para tabela `fardas`
--
ALTER TABLE `fardas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cor_id` (`cor_id`),
  ADD KEY `tamanho_id` (`tamanho_id`),
  ADD KEY `departamento_id` (`departamento_id`);

--
-- Índices para tabela `farda_atribuicoes`
--
ALTER TABLE `farda_atribuicoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `colaborador_id` (`colaborador_id`),
  ADD KEY `farda_id` (`farda_id`);

--
-- Índices para tabela `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome_role` (`nome_role`);

--
-- Índices para tabela `tamanhos`
--
ALTER TABLE `tamanhos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices para tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_utilizador_role_final` (`role_id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `cacifos`
--
ALTER TABLE `cacifos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `colaboradores`
--
ALTER TABLE `colaboradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `cores`
--
ALTER TABLE `cores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `fardas`
--
ALTER TABLE `fardas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `farda_atribuicoes`
--
ALTER TABLE `farda_atribuicoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `tamanhos`
--
ALTER TABLE `tamanhos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `cacifos`
--
ALTER TABLE `cacifos`
  ADD CONSTRAINT `cacifos_ibfk_1` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `colaboradores`
--
ALTER TABLE `colaboradores`
  ADD CONSTRAINT `fk_colaboradores_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `fardas`
--
ALTER TABLE `fardas`
  ADD CONSTRAINT `fardas_ibfk_1` FOREIGN KEY (`cor_id`) REFERENCES `cores` (`id`),
  ADD CONSTRAINT `fardas_ibfk_2` FOREIGN KEY (`tamanho_id`) REFERENCES `tamanhos` (`id`),
  ADD CONSTRAINT `fardas_ibfk_3` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`);

--
-- Limitadores para a tabela `farda_atribuicoes`
--
ALTER TABLE `farda_atribuicoes`
  ADD CONSTRAINT `farda_atribuicoes_ibfk_1` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `farda_atribuicoes_ibfk_2` FOREIGN KEY (`farda_id`) REFERENCES `fardas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

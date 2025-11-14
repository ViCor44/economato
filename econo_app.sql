-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 14-Nov-2025 às 13:11
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
(2, 3, 1, 0, '2025-11-11 19:43:00'),
(3, 320, 2, 0, '2025-11-14 09:16:47'),
(4, 321, 2, 0, '2025-11-14 09:17:05');

-- --------------------------------------------------------

--
-- Estrutura da tabela `colaboradores`
--

CREATE TABLE `colaboradores` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `cartao` varchar(50) NOT NULL,
  `departamento_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `colaboradores`
--

INSERT INTO `colaboradores` (`id`, `nome`, `telefone`, `email`, `cartao`, `departamento_id`, `ativo`, `criado_em`) VALUES
(1, 'Joaquim', '', '', '34215487551', 2, 0, '2025-11-10 23:28:31'),
(2, 'Liliana Vaz', '967654321', 'lili@gmail.com', '0000663109', 4, 1, '2025-11-14 09:11:05');

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
(2, 'Amarela'),
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
(2, 'Manutenção', '2025-11-11 21:01:16'),
(3, 'Limpeza', '2025-11-12 22:03:43'),
(4, 'Restauração', '2025-11-14 09:06:29'),
(5, 'Receção/Loja', '2025-11-14 09:12:39'),
(6, 'Jardinagem', '2025-11-14 09:13:03');

-- --------------------------------------------------------

--
-- Estrutura da tabela `fardas`
--

CREATE TABLE `fardas` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cor_id` int(11) NOT NULL,
  `tamanho_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 0,
  `preco_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `fardas`
--

INSERT INTO `fardas` (`id`, `nome`, `cor_id`, `tamanho_id`, `quantidade`, `preco_unitario`, `criado_em`, `atualizado_em`) VALUES
(1, 'Polo', 1, 1, 28, 20.00, '2025-11-11 21:01:42', '2025-11-13 22:23:36'),
(2, 'Calção', 1, 1, 30, 15.00, '2025-11-11 21:02:23', '2025-11-14 09:16:21'),
(3, 'T-shirt', 2, 2, 50, 10.00, '2025-11-12 19:43:06', '2025-11-12 19:44:57');

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
(2, 1, 1, 1, '2025-11-11 21:12:44'),
(3, 1, 2, 1, '2025-11-12 21:44:48');

-- --------------------------------------------------------

--
-- Estrutura da tabela `farda_baixas`
--

CREATE TABLE `farda_baixas` (
  `id` int(11) NOT NULL,
  `farda_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `data_baixa` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `farda_compras`
--

CREATE TABLE `farda_compras` (
  `id` int(11) NOT NULL,
  `farda_id` int(11) NOT NULL,
  `quantidade_adicionada` int(11) NOT NULL,
  `preco_compra` decimal(10,2) DEFAULT NULL,
  `data_compra` datetime DEFAULT current_timestamp(),
  `criado_por` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `farda_departamentos`
--

CREATE TABLE `farda_departamentos` (
  `id` int(11) NOT NULL,
  `farda_id` int(11) NOT NULL,
  `departamento_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `farda_departamentos`
--

INSERT INTO `farda_departamentos` (`id`, `farda_id`, `departamento_id`) VALUES
(2, 1, 2),
(5, 2, 2),
(4, 2, 3),
(3, 3, 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `farda_devolucoes`
--

CREATE TABLE `farda_devolucoes` (
  `id` int(11) NOT NULL,
  `atribuicao_id` int(11) NOT NULL,
  `colaborador_id` int(11) NOT NULL,
  `farda_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `estado` enum('boas_condicoes','reciclagem') NOT NULL,
  `data_devolucao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `farda_devolucoes`
--

INSERT INTO `farda_devolucoes` (`id`, `atribuicao_id`, `colaborador_id`, `farda_id`, `quantidade`, `estado`, `data_devolucao`) VALUES
(4, 2, 1, 1, 1, 'reciclagem', '2025-11-13 22:20:55');

-- --------------------------------------------------------

--
-- Estrutura da tabela `farda_emprestimos`
--

CREATE TABLE `farda_emprestimos` (
  `id` int(11) NOT NULL,
  `colaborador_id` int(11) NOT NULL,
  `farda_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `data_emprestimo` datetime DEFAULT current_timestamp(),
  `data_devolucao` datetime DEFAULT NULL,
  `devolvido` tinyint(1) DEFAULT 0,
  `condicao_devolucao` enum('bom_estado','danificado','perdido') DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_por` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `farda_emprestimos`
--

INSERT INTO `farda_emprestimos` (`id`, `colaborador_id`, `farda_id`, `quantidade`, `data_emprestimo`, `data_devolucao`, `devolvido`, `condicao_devolucao`, `observacoes`, `criado_por`) VALUES
(1, 1, 1, 1, '2025-11-12 22:53:18', '2025-11-13 22:23:36', 1, 'bom_estado', '', 8),
(2, 1, 2, 1, '2025-11-14 09:15:55', '2025-11-14 09:16:21', 1, 'bom_estado', '', 1);

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
(1, 'L'),
(2, 'XL');

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
(8, 'Victor Correia', 'victor.a.correia@gmail.com', '$2y$10$lTDNseVm5FFBBC54aXOeUer6tEosMRkJVoSS81I.PExD67LjUqtWC', NULL, 2, 1, '2025-11-11 22:07:38', '2025-11-13 21:33:59'),
(9, 'Elisabete Viana', 'elisabeteviana@slidesplash.com', '$2y$10$ncAMB7hcvCmtIi5HwS7sCuTEww/EjCdHogBIvCe7bVBFCEBkTGRte', 'F3WZWXAXNW4FZWE42JEOHGUIMCDLISUZ', 1, 1, '2025-11-14 09:40:03', '2025-11-14 09:41:45'),
(10, 'Alberto Viana', 'ab@gmail.com', '$2y$10$K4heco9VyuL0vuk5s59qo.OLMLns0C4NpYOM0uRC75zx1U6slOEs2', 'X2EQJFQYASFRJTRLH6WJT7SYGBU3QYQQ', 3, 1, '2025-11-14 11:49:39', '2025-11-14 11:50:40');

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
  ADD KEY `tamanho_id` (`tamanho_id`);

--
-- Índices para tabela `farda_atribuicoes`
--
ALTER TABLE `farda_atribuicoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `colaborador_id` (`colaborador_id`),
  ADD KEY `farda_id` (`farda_id`);

--
-- Índices para tabela `farda_baixas`
--
ALTER TABLE `farda_baixas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `farda_id` (`farda_id`);

--
-- Índices para tabela `farda_compras`
--
ALTER TABLE `farda_compras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_farda_compras_farda` (`farda_id`),
  ADD KEY `fk_farda_compras_user` (`criado_por`);

--
-- Índices para tabela `farda_departamentos`
--
ALTER TABLE `farda_departamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `farda_id` (`farda_id`,`departamento_id`),
  ADD KEY `departamento_id` (`departamento_id`);

--
-- Índices para tabela `farda_devolucoes`
--
ALTER TABLE `farda_devolucoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `atribuicao_id` (`atribuicao_id`),
  ADD KEY `colaborador_id` (`colaborador_id`),
  ADD KEY `farda_id` (`farda_id`);

--
-- Índices para tabela `farda_emprestimos`
--
ALTER TABLE `farda_emprestimos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_emprestimo_colaborador` (`colaborador_id`),
  ADD KEY `fk_emprestimo_farda` (`farda_id`),
  ADD KEY `fk_emprestimo_user` (`criado_por`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `colaboradores`
--
ALTER TABLE `colaboradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `cores`
--
ALTER TABLE `cores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `fardas`
--
ALTER TABLE `fardas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `farda_atribuicoes`
--
ALTER TABLE `farda_atribuicoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `farda_baixas`
--
ALTER TABLE `farda_baixas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `farda_compras`
--
ALTER TABLE `farda_compras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `farda_departamentos`
--
ALTER TABLE `farda_departamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `farda_devolucoes`
--
ALTER TABLE `farda_devolucoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `farda_emprestimos`
--
ALTER TABLE `farda_emprestimos`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
  ADD CONSTRAINT `fardas_ibfk_2` FOREIGN KEY (`tamanho_id`) REFERENCES `tamanhos` (`id`);

--
-- Limitadores para a tabela `farda_atribuicoes`
--
ALTER TABLE `farda_atribuicoes`
  ADD CONSTRAINT `farda_atribuicoes_ibfk_1` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `farda_atribuicoes_ibfk_2` FOREIGN KEY (`farda_id`) REFERENCES `fardas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `farda_baixas`
--
ALTER TABLE `farda_baixas`
  ADD CONSTRAINT `farda_baixas_ibfk_1` FOREIGN KEY (`farda_id`) REFERENCES `fardas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `farda_compras`
--
ALTER TABLE `farda_compras`
  ADD CONSTRAINT `fk_farda_compras_farda` FOREIGN KEY (`farda_id`) REFERENCES `fardas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_farda_compras_user` FOREIGN KEY (`criado_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `farda_departamentos`
--
ALTER TABLE `farda_departamentos`
  ADD CONSTRAINT `farda_departamentos_ibfk_1` FOREIGN KEY (`farda_id`) REFERENCES `fardas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `farda_departamentos_ibfk_2` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `farda_devolucoes`
--
ALTER TABLE `farda_devolucoes`
  ADD CONSTRAINT `farda_devolucoes_ibfk_1` FOREIGN KEY (`atribuicao_id`) REFERENCES `farda_atribuicoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `farda_devolucoes_ibfk_2` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `farda_devolucoes_ibfk_3` FOREIGN KEY (`farda_id`) REFERENCES `fardas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `farda_emprestimos`
--
ALTER TABLE `farda_emprestimos`
  ADD CONSTRAINT `fk_emprestimo_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_emprestimo_farda` FOREIGN KEY (`farda_id`) REFERENCES `fardas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_emprestimo_user` FOREIGN KEY (`criado_por`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

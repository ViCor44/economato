-- Guia rápido para reverter termos de devolução no CrewGest
-- ===========================================================

-- 1. VERIFICAR SE EXISTEM TERMOS PARA REVERTER
-- Mostra colaboradores inativos que têm termos de devolução
SELECT DISTINCT
    fa.termo_id,
    c.id,
    c.nome,
    c.numero_funcionario,
    c.ativo,
    COUNT(*) AS total_atribuicoes,
    SUM(fa.quantidade) AS total_quantidade,
    MAX(fa.data_devolucao) AS data_termo
FROM farda_atribuicoes fa
JOIN colaboradores c ON fa.colaborador_id = c.id
WHERE fa.termo_id IS NOT NULL
  AND c.ativo = 0
GROUP BY fa.termo_id
ORDER BY MAX(fa.data_devolucao) DESC;


-- 2. VERIFICAR ESTADO DE UM COLABORADOR (ANTES DE REVERTER)
-- Substitua :colaborador_id pelo ID desejado
SELECT 
    id,
    nome,
    ativo,
    numero_funcionario
FROM colaboradores
WHERE id = :colaborador_id;

-- Ver as atribuições associadas
SELECT 
    fa.id,
    f.nome AS farda_nome,
    fa.quantidade,
    fa.estado,
    fa.estado_devolucao,
    fa.termo_id,
    f.preco_unitario,
    (fa.quantidade * f.preco_unitario) AS valor_total
FROM farda_atribuicoes fa
JOIN fardas f ON fa.farda_id = f.id
WHERE fa.colaborador_id = :colaborador_id
ORDER BY fa.estado, f.nome;


-- 3. VERIFICAR STOCK ANTES DA REVERSÃO
SELECT 
    f.id,
    f.nome,
    f.quantidade AS quantidade_atual,
    c.nome AS cor,
    t.nome AS tamanho
FROM fardas f
JOIN cores c ON f.cor_id = c.id
JOIN tamanhos t ON f.tamanho_id = t.id
ORDER BY f.nome;


-- 4. VER UM TERMO ESPECÍFICO
-- Substitua :termo_id pelo ID do termo a reverter
SELECT 
    fa.id,
    fa.estado,
    fa.estado_devolucao,
    f.nome,
    fa.quantidade,
    f.preco_unitario
FROM farda_atribuicoes fa
JOIN fardas f ON fa.farda_id = f.id
WHERE fa.termo_id = :termo_id
ORDER BY f.nome;


-- 5. REVERTER MANUALMENTE (SE NECESSÁRIO)
-- ⚠️ Cuidado: Apenas execute isto se tiver a certeza!
-- Substitua :termo_id e :colaborador_id

-- 5a. Reverter atribuições devolvidas para 'atribuida'
UPDATE farda_atribuicoes
SET 
    estado = 'atribuida',
    estado_devolucao = NULL,
    data_devolucao = NULL,
    termo_id = NULL
WHERE termo_id = :termo_id
  AND estado IN ('devolvida_confirmada', 'em_divida');

-- 5b. Reduzir o stock das fardas que tinham sido devolvidas ao stock
-- (Esta operação é complexa e recomenda-se usar a interface)

-- 5c. Reativar o colaborador
UPDATE colaboradores
SET ativo = 1
WHERE id = :colaborador_id;


-- 6. VERIFICAR SE A REVERSÃO FUNCIONOU
-- Executa isto APÓS reverter

-- Status do colaborador
SELECT 
    id,
    nome,
    ativo,
    numero_funcionario
FROM colaboradores
WHERE id = :colaborador_id;

-- Atribuições restauradas
SELECT 
    fa.id,
    f.nome,
    fa.quantidade,
    fa.estado,
    fa.termo_id
FROM farda_atribuicoes fa
JOIN fardas f ON fa.farda_id = f.id
WHERE fa.colaborador_id = :colaborador_id
ORDER BY f.nome;

-- Dívida (deve ser 0 ou NULL)
SELECT 
    COUNT(*) AS total_itens_divida,
    SUM(fa.quantidade * f.preco_unitario) AS total_divida
FROM farda_atribuicoes fa
JOIN fardas f ON fa.farda_id = f.id
WHERE fa.colaborador_id = :colaborador_id
  AND fa.estado = 'em_divida';


-- 7. AUDITAR A REVERSÃO
-- Ver o log da operação
SELECT 
    id,
    user_id,
    acao,
    detalhes,
    criado_em
FROM logs
WHERE acao = 'Reverter termo de devolução'
ORDER BY criado_em DESC
LIMIT 10;

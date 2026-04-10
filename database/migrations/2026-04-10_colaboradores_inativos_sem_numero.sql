-- Garantir que colaboradores inativos nao mantem numero de funcionario
-- Executar na base de dados econo_app

UPDATE colaboradores
SET numero_funcionario = ''
WHERE ativo = 0
  AND TRIM(COALESCE(numero_funcionario, '')) <> '';
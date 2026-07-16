-- Performance da listagem de colaboradores
-- Executar na base de dados econo_app

-- Filtros por estado/departamento + ordenacao por nome
CREATE INDEX idx_colaboradores_ativo_nome
    ON colaboradores (ativo, nome);

CREATE INDEX idx_colaboradores_departamento_nome
    ON colaboradores (departamento_id, nome);

-- Pesquisa exata por numero de funcionario
CREATE INDEX idx_colaboradores_numero_funcionario
    ON colaboradores (numero_funcionario);

-- Agregacao de dividas por colaborador/estado
CREATE INDEX idx_farda_atribuicoes_estado_colaborador
    ON farda_atribuicoes (estado, colaborador_id);

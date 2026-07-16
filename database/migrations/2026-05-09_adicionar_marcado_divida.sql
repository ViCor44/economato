-- Adicionar suporte para marcar fardas como dívida
-- Executar na base de dados econo_app

ALTER TABLE farda_atribuicoes
    ADD COLUMN IF NOT EXISTS marcado_como_divida TINYINT(1) NOT NULL DEFAULT 0 AFTER estado_devolucao,
    ADD COLUMN IF NOT EXISTS data_marcacao_divida DATETIME NULL AFTER marcado_como_divida;

CREATE INDEX idx_farda_atribuicoes_marcado_divida
    ON farda_atribuicoes (colaborador_id, marcado_como_divida);

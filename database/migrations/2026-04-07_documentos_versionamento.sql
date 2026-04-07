-- Versionamento de documentos e rastreamento de alteracoes de atribuicao
-- Executar na base de dados econo_app

-- 1. Rastreamento de edicoes nas atribuicoes de farda
ALTER TABLE farda_atribuicoes
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER data_atribuicao;

-- 2. Versionamento de documentos gerados

ALTER TABLE documentos
    ADD COLUMN IF NOT EXISTS estado ENUM('valido','invalidado') NOT NULL DEFAULT 'valido' AFTER criado_em,
    ADD COLUMN IF NOT EXISTS invalidado_em DATETIME NULL AFTER estado,
    ADD COLUMN IF NOT EXISTS motivo_invalidacao VARCHAR(255) NULL AFTER invalidado_em,
    ADD COLUMN IF NOT EXISTS invalida_documento_id INT NULL AFTER motivo_invalidacao,
    ADD COLUMN IF NOT EXISTS invalidado_por_documento_id INT NULL AFTER invalida_documento_id;

CREATE INDEX idx_documentos_colaborador_tipo_estado
    ON documentos (colaborador_id, tipo, estado);

CREATE INDEX idx_documentos_invalidado_por
    ON documentos (invalidado_por_documento_id);

CREATE INDEX idx_documentos_invalida
    ON documentos (invalida_documento_id);

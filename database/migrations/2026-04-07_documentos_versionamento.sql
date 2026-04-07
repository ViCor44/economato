-- Versionamento de documentos para reemissao de termos
-- Executar na base de dados econo_app

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

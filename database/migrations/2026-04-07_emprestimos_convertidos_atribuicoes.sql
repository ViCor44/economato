ALTER TABLE farda_emprestimos
    ADD COLUMN IF NOT EXISTS convertido_em_atribuicao TINYINT(1) NOT NULL DEFAULT 0 AFTER devolvido,
    ADD COLUMN IF NOT EXISTS atribuicao_id INT NULL AFTER convertido_em_atribuicao;

CREATE INDEX idx_farda_emprestimos_atribuicao_id
    ON farda_emprestimos (atribuicao_id);
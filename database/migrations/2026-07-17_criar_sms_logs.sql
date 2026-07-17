-- Log de SMS enviados (ou tentativas de envio) pelo sistema.
-- Executar na base de dados econo_app.

CREATE TABLE IF NOT EXISTS sms_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    emissor     VARCHAR(190) NOT NULL COMMENT 'Nome do utilizador que enviou ou "Sistema (Cron)"',
    receptor    VARCHAR(190) NOT NULL COMMENT 'Número (E.164) e, quando disponível, nome do destinatário',
    mensagem    TEXT NOT NULL,
    estado      ENUM('enviado','erro') NOT NULL DEFAULT 'enviado',
    erro        VARCHAR(500) NULL,
    data_hora   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_logs_data (data_hora),
    INDEX idx_sms_logs_receptor (receptor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

<?php
// src/templates/assistant_widget.php

$aiContext = $aiContext ?? 'default';
?>
<div id="ai-assistant-root">
    <!-- Botão flutuante -->
    <button id="ai-assistant-toggle" class="ai-assistant-button" title="Abrir assistente IA">
        🤖
    </button>

    <!-- Modal -->
    <div id="ai-assistant-overlay" class="ai-assistant-overlay ai-hidden">
        <div class="ai-assistant-modal" role="dialog" aria-modal="true">
            <div class="ai-assistant-header">
                <strong>Assistente IA</strong>
                <button id="ai-assistant-close" class="ai-assistant-close">&times;</button>
            </div>

            <div class="ai-assistant-body" data-context="<?= htmlspecialchars($aiContext) ?>">
                <div class="ai-assistant-message ai-assistant-message-system">
                    <?php if ($aiContext === 'fardas'): ?>
                        Posso ajudar com criação e gestão de fardas, stock e departamentos.
                    <?php elseif ($aiContext === 'etiquetas'): ?>
                        Posso ajudar a perceber como funcionam as etiquetas e os relatórios de códigos de barras.
                    <?php else: ?>
                        Olá! Sou o assistente inteligente do CrewGest.
                        Posso ajudar a explicar erros, relatórios e tarefas do dia-a-dia.
                    <?php endif; ?>
                </div>

                <div class="ai-assistant-suggestions">
                    <span class="ai-suggestion" data-question="Explica-me esta página.">
                        Explica-me esta página
                    </span>
                    <span class="ai-suggestion" data-question="Mostra-me possíveis problemas aqui.">
                        Possíveis problemas
                    </span>
                    <span class="ai-suggestion" data-question="Como posso usar esta área do sistema?">
                        Como utilizar esta área
                    </span>
                </div>

                <div id="ai-assistant-conversation"></div>
            </div>

            <div class="ai-assistant-footer">
                <input type="text" id="ai-assistant-input" placeholder="Escreve a tua questão..." />
                <button id="ai-assistant-send">Enviar</button>
            </div>
        </div>
    </div>
</div>

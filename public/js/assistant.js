document.addEventListener('DOMContentLoaded', function () {
    const btnToggle = document.getElementById('ai-assistant-toggle');
    const overlay = document.getElementById('ai-assistant-overlay');
    const btnClose = document.getElementById('ai-assistant-close');
    const input = document.getElementById('ai-assistant-input');
    const btnSend = document.getElementById('ai-assistant-send');
    const conversation = document.getElementById('ai-assistant-conversation');
    const suggestions = document.querySelectorAll('.ai-suggestion');
    const body = document.querySelector('.ai-assistant-body');
    const context = body ? body.getAttribute('data-context') : 'default';

    if (!btnToggle || !overlay) return;

    function openModal() {
        overlay.classList.remove('ai-hidden');
        setTimeout(() => input && input.focus(), 100);
    }

    function closeModal() {
        overlay.classList.add('ai-hidden');
    }

    btnToggle.addEventListener('click', openModal);
    if (btnClose) btnClose.addEventListener('click', closeModal);

    // Fechar ao clicar fora da caixa
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            closeModal();
        }
    });

    function appendMessage(text, type) {
        const div = document.createElement('div');
        div.className = 'ai-assistant-message ' +
            (type === 'user' ? 'ai-assistant-message-user' : 'ai-assistant-message-system');
        div.textContent = text;
        conversation.appendChild(div);
        conversation.scrollTop = conversation.scrollHeight;
    }

    function fakeAssistantReply(question) {
        let answer = "Ainda estou em modo beta 😄 Mas posso ajudar a explicar esta área do sistema.";
        const q = question.toLowerCase();

        if (context === 'fardas') {
            if (q.includes('adicionar') || q.includes('criar')) {
                answer = "Para adicionar uma farda, utiliza a opção 'Gerir Stock de Farda' e escolhe 'Nova farda'. " +
                         "Preenche nome, cor, tamanho, departamentos e, se tiveres, o EAN para etiquetas.";
            } else if (q.includes('stock')) {
                answer = "Na página de 'Gerir Stock de Farda', podes ver todas as fardas disponíveis, " +
                         "filtrar por departamento e verificar o stock atual de cada peça.";
            } else if (q.includes('departamento')) {
                answer = "Ao adicionar ou editar uma farda, podes associá-la a um ou mais departamentos " +
                         "para facilitar a gestão e filtragem no sistema.";
            } else if (q.includes('editar')) {
                answer = "Para editar uma farda existente, vai à página de 'Gerir Stock de Farda', " +
                         "clica na farda que queres modificar e ajusta os detalhes conforme necessário.";
            } else if (q.includes('explica')) {
                answer = "Esta página permite gerir o stock de fardas, " +
                         "adicionar novas peças, editar existentes e ver o stock atual por departamento.";
            } else if (q.includes('problemas')) {
                answer = "Se encontrares problemas ao gerir fardas, verifica se tens as permissões necessárias " +
                         "ou contacta o suporte técnico para assistência.";
            }
        } else if (context === 'etiquetas') {
            if (q.includes('explica')) {
                answer = "Esta página mostra etiquetas de códigos de barras associadas às fardas, " +
                         "agrupadas por departamento. Cada cartão mostra peça, cor, tamanho, EAN e stock.";
            }
        } else if (context === 'colaboradores') {
            if (q.includes('explica')) {
                answer = "Esta página permite gerir os colaboradores, " +
                         "adicionar novos, editar informações e ver o histórico de fardas atribuídas.";
            } else if (q.includes('adicionar')) {
                answer = "Para adicionar um colaborador, clica em 'Novo Colaborador', " +
                         "preenche os detalhes necessários e guarda as informações.";
            } else if (q.includes('detalhes')) {
                answer = "Na página de colaboradores, clica no nome do colaborador par poderes ver os detalhes como nome, cargo, departamento " +
                         "e fardas atribuídas, cacifos desse colaborador.";
            }
        } else if (context === 'dashboard') {
            if (q.includes('explica')) {
                answer = "O dashboard oferece uma visão geral do sistema, " +
                         "com estatísticas rápidas sobre fardas, colaboradores e alertas importantes.";
            }
        }

        appendMessage(answer, 'assistant');
    }

    function handleSend(text) {
        const trimmed = text.trim();
        if (!trimmed) return;
        appendMessage(trimmed, 'user');
        input.value = '';
        fakeAssistantReply(trimmed);
    }

    btnSend.addEventListener('click', () => handleSend(input.value));

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSend(input.value);
        }
    });

    suggestions.forEach(s => {
        s.addEventListener('click', () => {
            const q = s.getAttribute('data-question');
            handleSend(q);
        });
    });
});

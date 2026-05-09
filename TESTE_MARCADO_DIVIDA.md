<!-- DOCUMENTO DE TESTE: Sistema de Marcar Farda como Dívida -->

## 🧪 Guia de Teste - Sistema de Marcar Farda como Dívida

### ✅ Pré-requisitos
- Base de dados atualizada com a migration: `2026-05-09_adicionar_marcado_divida.sql`
- Colaborador com pelo menos uma farda atribuída

### 📋 Casos de Teste

#### Teste 1: Visualizar Botão "Marcar como Dívida"
**Objetivo:** Verificar se o botão aparece para cada farda

**Passos:**
1. Aceda a `public/devolucao_farda.php?colaborador_id=X` (X = ID do colaborador)
2. Procure por um colaborador com fardas atribuídas

**Resultado Esperado:**
- Para cada farda em estado "atribuida", devem aparecer 3 botões:
  - ♻️ 1 peça (azul)
  - ♻️ Todas (púrpura)
  - 💳 Marcar como dívida (vermelho)

---

#### Teste 2: Marcar Farda como Dívida
**Objetivo:** Verificar se a farda é marcada corretamente

**Passos:**
1. Clique no botão "💳 Marcar como dívida" para uma farda
2. Confirme a mensagem de confirmação: "Tem a certeza que quer marcar esta farda como dívida?"
3. Verifique o resultado

**Resultado Esperado:**
- Página recarrega
- Mensagem de sucesso: "Farda marcada como dívida com sucesso."
- Farda agora mostra badge amarelo "💳 Marcada como dívida"
- Botão "📄 Gerar Termo de Devolução" ainda permanece desabilitado (se houver outras fardas não tratadas)

---

#### Teste 3: Habilitar Botão "Gerar Termo"
**Objetivo:** Verificar se o botão fica habilitado quando todas as fardas são marcadas ou devolvidas

**Passos:**
1. Colaborador com 2 fardas atribuídas:
   - Farda A: clique "1 peça" e selecione estado "Boas condições"
   - Farda B: clique "💳 Marcar como dívida"
2. Verifique o estado do botão "Gerar Termo"

**Resultado Esperado:**
- Botão "📄 Gerar Termo de Devolução" fica **habilitado** (verde)
- Mensagem anterior desaparece

---

#### Teste 4: Erro - Farda Não Tratada
**Objetivo:** Verificar que o botão permanece desabilitado

**Passos:**
1. Colaborador com 2 fardas atribuídas
2. Marque apenas 1 como dívida
3. Verifique o botão "Gerar Termo"

**Resultado Esperado:**
- Botão "📄 Gerar Termo de Devolução" permanece **desabilitado** (cinzento)
- Tooltip mostra: "Marque todas as fardas para devolução ou como dívida antes de gerar o termo"
- Mensagem: "⚠ Existem fardas ainda não marcadas para devolução ou como dívida."

---

#### Teste 5: Gerar Termo com Dívida
**Objetivo:** Verificar se o PDF inclui corretamente as fardas como dívida

**Passos:**
1. Colaborador com fardas marcadas como dívida
2. Clique em "📄 Gerar Termo de Devolução"
3. Analise o PDF gerado

**Resultado Esperado:**
- Seção "Peças Não Devolvidas (Em Dívida)" inclui as fardas marcadas como dívida
- `total_divida` é calculado corretamente (quantidade × preço_unitário)
- Colaborador fica inativo após gerar termo

---

#### Teste 6: Verificar Base de Dados
**Objetivo:** Validar que os dados são armazenados corretamente

**Query SQL:**
```sql
SELECT id, colaborador_id, farda_id, estado, marcado_como_divida, data_marcacao_divida 
FROM farda_atribuicoes 
WHERE colaborador_id = X;
```

**Resultado Esperado:**
- Colunas `marcado_como_divida` e `data_marcacao_divida` contêm dados corretos
- Registos marcados como dívida têm `marcado_como_divida = 1`

---

#### Teste 7: Logging
**Objetivo:** Verificar se as ações são registadas

**Verificar:**
- Base de dados `logs` deve conter entrada: "Marcar farda como dívida"
- Log deve incluir: "Colaborador ID X marcou atribuição ID Y como dívida"

---

### 🐛 Possíveis Problemas e Soluções

| Problema | Solução |
|----------|---------|
| Botão "Marcar como dívida" não aparece | Verifique migration foi executada e página foi recarregada |
| Erro ao marcar como dívida | Verifique logs de erro do PHP/MySQL |
| Botão "Gerar Termo" não fica habilitado | Verifique se todas as fardas foram marcadas ou devolvidas |
| PDF não inclui dívidas corretamente | Verifique se campo `marcado_como_divida` está na query |

---

### 📊 Resumo das Alterações de Comportamento

| Situação | Antes | Depois |
|----------|-------|--------|
| Farda não marcada para devolução | Bloqueia "Gerar Termo" | Bloqueia se não marcada como dívida também |
| Farda marcada como dívida | Não existia | Permite prosseguir para "Gerar Termo" |
| PDF com divida | Não incluía | Inclui na seção "Peças Não Devolvidas" |


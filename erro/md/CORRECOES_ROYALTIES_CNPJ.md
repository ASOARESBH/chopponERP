# Correções - Royalties e CNPJ

**Data:** 2025-12-04
**Versão:** 2.1

## Problemas Corrigidos

### 1. Erro de CNPJ na Query de Royalties

**Problema:** Ao listar royalties, o sistema apresentava o erro `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'cnpj' in 'field list'`.

**Causa:** A query principal que lista os royalties não estava incluindo o campo `cnpj` da tabela `estabelecimentos`, mas outras partes do código (como a geração de boletos) esperavam esse campo.

**Solução:** Adicionado o campo `e.cnpj` nas queries SELECT de royalties:

```sql
SELECT r.*, e.name as estabelecimento_nome, e.cnpj,
       u.name as criado_por_nome
FROM royalties r
INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
LEFT JOIN users u ON r.created_by = u.id
```

**Arquivo modificado:** `/admin/financeiro_royalties.php` (linhas 497 e 507)

---

### 2. Cálculo Automático de Royalties Não Funcionando

**Problema:** Ao digitar o valor do faturamento bruto, o sistema não calculava automaticamente os 7% de royalties.

**Causa:** O evento `input` não estava sendo disparado corretamente em todos os navegadores, e a formatação de moeda interferia no cálculo.

**Solução:** 
- Criada função dedicada `calcularRoyalties()` para centralizar o cálculo.
- Adicionados múltiplos event listeners (`input`, `keyup`, `change`) para garantir compatibilidade.
- Adicionado recálculo após formatação automática do campo.

**Código implementado:**

```javascript
function calcularRoyalties() {
    let input = document.getElementById('valor_faturamento_bruto');
    let valor = input.value.replace(/[^\d,]/g, '');
    valor = valor.replace(',', '.');
    let valorFloat = parseFloat(valor) || 0;
    let royalties = valorFloat * 0.07;
    
    document.getElementById('valor_royalties_display').textContent = 
        'R$ ' + royalties.toFixed(2).replace('.', ',');
}

const faturamentoInput = document.getElementById('valor_faturamento_bruto');
faturamentoInput.addEventListener('input', calcularRoyalties);
faturamentoInput.addEventListener('keyup', calcularRoyalties);
faturamentoInput.addEventListener('change', calcularRoyalties);
```

**Arquivo modificado:** `/admin/financeiro_royalties.php` (linhas 948-985)

---

### 3. Máscara e Validação de CNPJ/CPF em Estabelecimentos

**Problema:** O campo de CNPJ/CPF não possuía máscara automática nem validação, permitindo dados inválidos.

**Solução:** Implementadas funções JavaScript para:

1. **Máscara Automática (`maskCNPJCPF`):**
   - Detecta automaticamente se é CPF (11 dígitos) ou CNPJ (14 dígitos).
   - Aplica formatação: `000.000.000-00` (CPF) ou `00.000.000/0000-00` (CNPJ).

2. **Validação Matemática (`validateCNPJCPF`):**
   - Valida CPF usando algoritmo de dígitos verificadores.
   - Valida CNPJ usando algoritmo de dígitos verificadores.
   - Rejeita sequências repetidas (ex: 111.111.111-11).

**Código implementado:**

```javascript
function maskCNPJCPF(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length <= 11) {
        // Máscara CPF
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
        // Máscara CNPJ
        value = value.replace(/(\d{2})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1/$2');
        value = value.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }
    
    input.value = value;
}
```

**Arquivo modificado:** `/admin/estabelecimentos.php` (linhas 147-150 e 203-310)

---

## Arquivos Modificados

1. `/admin/financeiro_royalties.php`
   - Correção da query de listagem de royalties
   - Melhoria no cálculo automático de royalties

2. `/admin/estabelecimentos.php`
   - Adição de máscara automática de CNPJ/CPF
   - Adição de validação matemática de CNPJ/CPF

---

## Como Testar

### Teste 1: Royalties
1. Acesse **Financeiro > Royalties**
2. Clique em **+ Novo Lançamento**
3. Digite um valor no campo "Valor do Faturamento Bruto" (ex: 10000)
4. Verifique se o valor dos royalties (7%) é calculado automaticamente (R$ 700,00)

### Teste 2: CNPJ/CPF
1. Acesse **Estabelecimentos**
2. Clique em **+ Novo Estabelecimento**
3. No campo CNPJ/CPF, digite apenas números
4. Verifique se a máscara é aplicada automaticamente
5. Tente salvar com um CNPJ/CPF inválido e verifique se a validação funciona

---

## Notas Importantes

- O cálculo de royalties agora funciona em tempo real enquanto o usuário digita.
- A validação de CNPJ/CPF impede o cadastro de documentos inválidos.
- A máscara é aplicada automaticamente, o usuário não precisa digitar pontos, barras ou hífens.

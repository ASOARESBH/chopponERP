# 🔐 Permissões dos Novos Módulos

**Autor**: Manus AI  
**Data**: 2025-12-05

---

## 1. Visão Geral

Este documento descreve como adicionar permissões para os novos módulos implementados no sistema **Chopp On Tap**:

- **Módulo de Estoque** (4 páginas)
- **Módulo de Fornecedores** (1 página)
- **Módulos Financeiros** (Royalties e Faturamento)
- **Outros módulos** (Promoções, Permissões, Integrações)

---

## 2. Como Aplicar as Permissões

### Passo 1: Acessar o phpMyAdmin

1. Faça login no phpMyAdmin
2. Selecione o banco de dados do sistema

### Passo 2: Executar o Script SQL

1. Vá na aba **SQL**
2. Copie e cole o conteúdo do arquivo `sql/add_estoque_permissions.sql`
3. Clique em **Executar**

### Passo 3: Verificar

Execute a seguinte consulta para verificar se as páginas foram adicionadas:

```sql
SELECT page_key, page_name, page_category 
FROM system_pages 
WHERE page_category IN ('Estoque', 'Marketing')
ORDER BY page_category, page_name;
```

---

## 3. Páginas Adicionadas

| Página | Nome | Categoria | Admin Only |
| :--- | :--- | :--- | :---: |
| `estoque_produtos` | Estoque - Produtos | Estoque | Não |
| `estoque_visao` | Estoque - Visão Geral | Estoque | Não |
| `estoque_movimentacoes` | Estoque - Movimentações | Estoque | Não |
| `estoque_relatorios` | Estoque - Relatórios | Estoque | Não |
| `fornecedores` | Fornecedores | Estoque | Não |
| `financeiro_royalties` | Royalties | Financeiro | Não |
| `financeiro_faturamento` | Faturamento | Financeiro | Não |
| `promocoes` | Promoções | Marketing | Não |
| `permissoes` | Permissões | Administração | **Sim** |
| `stripe_config` | Stripe Pagamentos | Administração | **Sim** |
| `cora_config` | Banco Cora | Administração | **Sim** |

---

## 4. Permissões por Tipo de Usuário

### 4.1. Admin Geral (type = 1)
✅ **Acesso total** a todos os módulos  
✅ Pode visualizar, criar, editar e excluir

### 4.2. Gerente (type = 2)
✅ Acesso total ao módulo de **Estoque**  
✅ Acesso total ao módulo **Financeiro**  
✅ Acesso ao módulo de **Promoções**  
❌ Sem acesso a páginas exclusivas de Admin (Permissões, Integrações)

### 4.3. Operador (type = 3)
✅ Pode visualizar, criar e editar no módulo de **Estoque**  
✅ Pode visualizar e editar **Fornecedores**  
❌ Não pode excluir produtos ou fornecedores  
❌ Sem acesso a módulos Financeiros

### 4.4. Visualizador (type = 4)
✅ Pode apenas **visualizar** Estoque e Relatórios  
❌ Não pode criar, editar ou excluir nada

---

## 5. Gerenciar Permissões Personalizadas

Após aplicar o script, você pode personalizar as permissões de cada usuário através da página **Permissões** no painel administrativo:

1. Acesse **Administração > Permissões**
2. Selecione um usuário
3. Marque/desmarque as permissões desejadas:
   - **Visualizar** (can_view)
   - **Criar** (can_create)
   - **Editar** (can_edit)
   - **Excluir** (can_delete)

---

## 6. Verificar Permissões de um Usuário

Para verificar as permissões de um usuário específico, execute:

```sql
SELECT 
    u.name as usuario,
    u.type as tipo_usuario,
    sp.page_name as pagina,
    sp.page_category as categoria,
    up.can_view as visualizar,
    up.can_create as criar,
    up.can_edit as editar,
    up.can_delete as excluir
FROM user_permissions up
INNER JOIN users u ON up.user_id = u.id
INNER JOIN system_pages sp ON up.page_id = sp.id
WHERE u.id = 1  -- Altere para o ID do usuário desejado
ORDER BY sp.page_category, sp.page_name;
```

---

**Fim da Documentação**

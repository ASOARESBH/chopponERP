# Correções Aplicadas no Sistema

**Data:** 2025-12-04  
**Versão:** 2.2

## Problema Resolvido: Página em Branco

A página `financeiro_royalties.php` estava exibindo uma tela em branco devido a um erro fatal no PHP causado pela classe `StripeAPI` que tentava buscar dados de uma tabela `stripe_config` que não existia no banco de dados.

---

## Correções Implementadas

### 1. Modificação da Classe StripeAPI

**Arquivo:** `/includes/stripe_api.php`

**Problema:** O construtor da classe tentava obrigatoriamente buscar configurações na tabela `stripe_config`, causando erro fatal quando a tabela não existia.

**Solução:** Modificado o construtor para:
- Tentar buscar configurações da tabela `stripe_config` (se existir)
- Em caso de erro (tabela não existe), usar fallback para variáveis de ambiente ou constantes
- Suportar configuração global via `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` e `STRIPE_MODE`

**Código modificado:**
```php
public function __construct($estabelecimento_id = null) {
    // Tentar buscar da tabela de configuração
    if ($estabelecimento_id) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("
                SELECT stripe_secret_key, stripe_webhook_secret, modo, ativo
                FROM stripe_config
                WHERE estabelecimento_id = ? AND ativo = 1
            ");
            $stmt->execute([$estabelecimento_id]);
            $config = $stmt->fetch();
            
            if ($config) {
                $this->secret_key = $config['stripe_secret_key'];
                $this->webhook_secret = $config['stripe_webhook_secret'];
                $this->modo = $config['modo'];
                return;
            }
        } catch (Exception $e) {
            // Tabela não existe ainda, usar configuração global
        }
    }
    
    // Fallback: usar variáveis de ambiente ou constantes
    $this->secret_key = getenv('STRIPE_SECRET_KEY') ?: (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '');
    $this->webhook_secret = getenv('STRIPE_WEBHOOK_SECRET') ?: (defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '');
    $this->modo = getenv('STRIPE_MODE') ?: (defined('STRIPE_MODE') ? STRIPE_MODE : 'test');
    
    if (empty($this->secret_key)) {
        throw new Exception('Configuração do Stripe não encontrada. Configure STRIPE_SECRET_KEY.');
    }
}
```

---

### 2. Script SQL para Criar Tabelas de Integrações

**Arquivo:** `/database_integracoes.sql`

Criado script SQL para criar as tabelas necessárias:
- `stripe_config` - Configurações do Stripe por estabelecimento
- `cora_config` - Configurações do Banco Cora por estabelecimento

**Para aplicar:**
```sql
-- Execute este script no seu banco de dados MySQL
source /caminho/para/database_integracoes.sql;
```

---

### 3. Funcionalidades Já Implementadas (Verificadas)

✅ **Campo CNPJ nas Queries de Royalties**  
As queries já incluem o campo `e.cnpj` (linhas 497 e 507 de `financeiro_royalties.php`)

✅ **Cálculo Automático de Royalties (7%)**  
Função `calcularRoyalties()` já implementada com múltiplos event listeners (linhas 949-977 de `financeiro_royalties.php`)

✅ **Máscara e Validação de CNPJ/CPF**  
Funções `maskCNPJCPF()` e `validateCNPJCPF()` já implementadas em `estabelecimentos.php` (linhas 204-310)

---

## Como Usar

### Opção 1: Configuração via Banco de Dados (Recomendado)

1. Execute o script `database_integracoes.sql` no seu banco de dados
2. Acesse o menu **Integrações > Stripe Pagamentos** no sistema
3. Configure suas credenciais do Stripe

### Opção 2: Configuração via Variáveis de Ambiente

Adicione no seu arquivo `.env` ou configuração do servidor:

```bash
STRIPE_SECRET_KEY=sk_test_seu_secret_key_aqui
STRIPE_WEBHOOK_SECRET=whsec_seu_webhook_secret_aqui
STRIPE_MODE=test
```

Ou defina constantes no arquivo `config.php`:

```php
define('STRIPE_SECRET_KEY', 'sk_test_seu_secret_key_aqui');
define('STRIPE_WEBHOOK_SECRET', 'whsec_seu_webhook_secret_aqui');
define('STRIPE_MODE', 'test');
```

---

## Arquivos Modificados

1. `/includes/stripe_api.php` - Construtor modificado para suportar fallback
2. `/database_integracoes.sql` - Novo arquivo criado

---

## Próximos Passos

1. **Execute o script SQL** `database_integracoes.sql` no seu banco de dados
2. **Configure suas credenciais** do Stripe (via banco ou variáveis de ambiente)
3. **Teste a página de Royalties** - ela deve carregar normalmente agora
4. **Teste o cálculo automático** - digite um valor de faturamento e veja os 7% sendo calculados
5. **Teste a máscara de CNPJ** - cadastre um novo estabelecimento e veja a máscara funcionando

---

## Suporte

Se a página ainda não carregar:
1. Verifique os logs de erro do PHP (`error_log`)
2. Verifique se todas as tabelas necessárias existem no banco
3. Verifique se os arquivos `includes/config.php` e `includes/auth.php` estão corretos

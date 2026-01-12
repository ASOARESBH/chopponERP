# Changelog - Sistema Chopp On Tap

## Versão 2.0.0 - Migração para PHP Procedural (2025-11-25)

### 🎯 Mudanças Principais

**Arquitetura:**
- ✅ Migrado de Laravel para PHP procedural puro
- ✅ Removida dependência de frameworks
- ✅ HTML estático com CSS responsivo
- ✅ Sistema mais leve e fácil de manter
- ✅ Compatível com hospedagem compartilhada (HostGator)

**Funcionalidades Mantidas:**
- ✅ Sistema de autenticação com 4 níveis de usuário
- ✅ Gestão de estabelecimentos, bebidas e TAPs
- ✅ Integração completa com SumUp (PIX, Crédito, Débito)
- ✅ API REST para comunicação com app Android
- ✅ Webhooks para atualização de status de pagamento
- ✅ Dashboard com métricas e gráficos
- ✅ Controle de volume consumido e crítico
- ✅ Relatórios de vendas e consumo
- ✅ Upload de imagens de bebidas
- ✅ Sistema multi-estabelecimento

**Melhorias:**
- ✅ Interface mais limpa e moderna
- ✅ Layout 100% responsivo
- ✅ Instalador automático
- ✅ Documentação completa da API
- ✅ Logs de webhook para debug
- ✅ Configuração simplificada
- ✅ Melhor organização de arquivos

**Segurança:**
- ✅ Proteção de diretórios via .htaccess
- ✅ Senhas com hash bcrypt
- ✅ JWT para autenticação da API
- ✅ Sanitização de inputs
- ✅ Prepared statements (PDO)

---

## Estrutura do Sistema

### Módulos Administrativos

1. **Dashboard**
   - Vendas totais e mensais
   - Consumo em litros
   - Total de TAPs ativas
   - Gráfico de bebidas mais vendidas
   - TAPs com vencimento próximo
   - Gráfico de vendas mensais comparativo

2. **Bebidas**
   - CRUD completo
   - Upload de imagens
   - Preço normal e promocional
   - Informações técnicas (IBU, álcool)

3. **TAPs**
   - CRUD completo
   - Controle de volume
   - Pareamento com leitora SumUp
   - Status ativo/inativo
   - Vencimento de barril

4. **Pagamentos**
   - Configuração de token SumUp
   - Habilitação de métodos
   - Informações de webhook

5. **Pedidos**
   - Relatório completo
   - Filtros por data, status e método
   - Estatísticas de vendas

6. **Usuários** (Admin Geral)
   - CRUD completo
   - Associação com estabelecimentos
   - 4 níveis de acesso

7. **Estabelecimentos** (Admin Geral)
   - CRUD completo
   - Gestão multi-estabelecimento

### API REST

12 endpoints implementados:
- Login e validação de token
- Verificação de TAP
- Criação e cancelamento de pedidos
- Controle de liberação de líquido
- Listagem de bebidas e TAPs
- Webhook SumUp

---

## Banco de Dados

**Tabelas:**
- `users` - Usuários do sistema
- `estabelecimentos` - Estabelecimentos/choperias
- `user_estabelecimento` - Relação usuário-estabelecimento
- `bebidas` - Catálogo de bebidas
- `tap` - Torneiras automáticas
- `order` - Pedidos/vendas
- `payment` - Configurações de pagamento

**Dados Iniciais:**
- 1 usuário admin: choppon24h@gmail.com
- Configuração de pagamento padrão

---

## Compatibilidade

**Servidor:**
- PHP 7.4+
- MySQL 5.7+
- Apache 2.4+ ou Nginx

**Extensões PHP Necessárias:**
- pdo_mysql
- curl
- json
- gd (opcional, para manipulação de imagens)

**Navegadores:**
- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS/Android)

---

## Migração do Sistema Anterior

### Dados Preservados

Se você tinha dados no sistema Laravel anterior:

1. Exporte os dados das tabelas
2. Importe no novo banco seguindo a mesma estrutura
3. As senhas precisarão ser recadastradas (hash diferente)

### Integração com App Android

**URLs que mudaram:**
- Antes: `https://server.choppon24h.com.br/api/v1/...`
- Agora: `https://seudominio.com.br/api/...`

**Atualize no app Android:**
- Base URL da API
- Endpoints (removido `/v1/`)
- Header de autenticação (agora é `Token` ao invés de `Authorization: Bearer`)

---

## Roadmap Futuro

**Possíveis Melhorias:**
- [ ] Relatórios em PDF
- [ ] Exportação de dados para Excel
- [ ] Notificações por email
- [ ] Sistema de alertas de volume crítico
- [ ] Dashboard em tempo real
- [ ] App mobile nativo
- [ ] Integração com outros gateways de pagamento

---

**Versão:** 2.0.0  
**Data:** 25/11/2025  
**Desenvolvido para:** HostGator  
**Licença:** Proprietária

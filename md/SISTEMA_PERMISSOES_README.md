# 🔐 Sistema de Permissões por Página

## Chopp On Tap - v3.1

Sistema completo de controle de acesso baseado em permissões por página, permitindo que o Administrador Geral defina exatamente quais páginas cada usuário pode acessar e quais ações pode realizar.

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Funcionalidades](#funcionalidades)
3. [Instalação](#instalação)
4. [Estrutura do Banco de Dados](#estrutura-do-banco-de-dados)
5. [Como Usar](#como-usar)
6. [Tipos de Permissões](#tipos-de-permissões)
7. [Páginas Exclusivas](#páginas-exclusivas)
8. [Desenvolvimento](#desenvolvimento)

---

## 🎯 Visão Geral

O Sistema de Permissões permite controlar o acesso de cada usuário às diferentes páginas do sistema, com quatro níveis de ação:

- **Ver** (View): Visualizar a página
- **Criar** (Create): Criar novos registros
- **Editar** (Edit): Modificar registros existentes
- **Excluir** (Delete): Remover registros

### Benefícios

✅ **Segurança aprimorada** - Controle granular de acesso
✅ **Flexibilidade** - Personalize permissões por usuário
✅ **Páginas exclusivas** - Logs, E-mail e Telegram apenas para Admin
✅ **Menu dinâmico** - Usuários veem apenas o que podem acessar
✅ **Fácil gerenciamento** - Interface visual intuitiva

---

## 🚀 Funcionalidades

### 1. Controle Granular de Acesso
- Defina permissões individuais para cada usuário
- 4 níveis de ação por página (Ver, Criar, Editar, Excluir)
- Permissões padrão baseadas no tipo de usuário

### 2. Menu Lateral Dinâmico
- Exibe apenas páginas que o usuário tem permissão
- Oculta automaticamente itens sem acesso
- Submenus inteligentes (aparecem apenas se houver acesso a algum item)

### 3. Páginas Exclusivas do Admin
- **Logs do Sistema**: Apenas Admin Geral
- **Configuração de E-mail**: Apenas Admin Geral
- **Telegram**: Apenas Admin Geral

### 4. Interface de Gerenciamento
- Página dedicada para gerenciar permissões
- Seleção visual de usuários
- Checkboxes para cada tipo de permissão
- Agrupamento por categoria

### 5. Vínculo com Estabelecimentos
- Usuários não-admin vinculados a estabelecimentos específicos
- Visualizam apenas dados do seu estabelecimento
- Admin Geral vê todos os estabelecimentos

---

## 📦 Instalação

### Pré-requisitos

- ✅ Sistema Chopp On Tap v3.0 ou superior instalado
- ✅ Backup do banco de dados realizado
- ✅ Acesso ao servidor/hospedagem

### Passo a Passo

#### 1. Fazer Backup

```bash
# Backup do banco de dados
mysqldump -u usuario -p nome_banco > backup_antes_permissoes.sql

# Backup dos arquivos
cp -r /caminho/do/sistema /caminho/do/backup
```

#### 2. Extrair Arquivos

Extraia o conteúdo do ZIP e substitua os seguintes arquivos:

```
PHP/
├── includes/
│   ├── permissions.php          (NOVO)
│   └── header.php               (ATUALIZADO)
├── admin/
│   ├── permissoes.php           (NOVO)
│   ├── usuarios.php             (ATUALIZADO)
│   ├── bebidas.php              (ATUALIZADO)
│   └── ajax/
│       └── get_user_permissions.php  (NOVO)
├── install_permissions.sql      (NOVO)
└── install_permissions.php      (NOVO)
```

#### 3. Configurar Senha de Instalação

Edite o arquivo `install_permissions.php` e altere a senha:

```php
define('INSTALL_PASSWORD', 'SuaSenhaSegura123!');
```

#### 4. Executar Instalação

1. Acesse: `https://seusite.com/install_permissions.php`
2. Digite a senha configurada
3. Clique em "Iniciar Instalação"
4. Aguarde a conclusão

#### 5. Verificar Instalação

Após a instalação bem-sucedida:

1. Faça login como Administrador Geral
2. Verifique se o menu "Permissões" aparece
3. Acesse "Permissões" e verifique os usuários listados

#### 6. Segurança Pós-Instalação

**IMPORTANTE:** Delete ou renomeie os arquivos de instalação:

```bash
rm install_permissions.php
rm install_permissions.sql
# OU
mv install_permissions.php install_permissions.php.bak
mv install_permissions.sql install_permissions.sql.bak
```

---

## 🗄️ Estrutura do Banco de Dados

### Tabela: `system_pages`

Armazena todas as páginas do sistema.

```sql
CREATE TABLE `system_pages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_key` VARCHAR(100) NOT NULL,
  `page_name` VARCHAR(255) NOT NULL,
  `page_url` VARCHAR(255) NOT NULL,
  `page_icon` VARCHAR(100) NULL,
  `page_category` VARCHAR(100) NULL,
  `admin_only` TINYINT(1) NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`page_key`)
);
```

### Tabela: `user_permissions`

Armazena as permissões de cada usuário.

```sql
CREATE TABLE `user_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `page_id` BIGINT UNSIGNED NOT NULL,
  `can_view` TINYINT(1) NOT NULL DEFAULT 1,
  `can_create` TINYINT(1) NOT NULL DEFAULT 0,
  `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
  `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`user_id`, `page_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`page_id`) REFERENCES `system_pages` (`id`) ON DELETE CASCADE
);
```

---

## 📖 Como Usar

### Para Administradores

#### 1. Acessar Gerenciamento de Permissões

1. Faça login como **Administrador Geral**
2. No menu lateral, clique em **"Permissões"**
3. Você verá a lista de todos os usuários

#### 2. Configurar Permissões de um Usuário

1. Clique no usuário desejado na lista
2. As permissões atuais serão exibidas
3. Marque/desmarque os checkboxes conforme necessário:
   - **Ver**: Usuário pode acessar a página
   - **Criar**: Usuário pode criar novos registros
   - **Editar**: Usuário pode modificar registros
   - **Excluir**: Usuário pode deletar registros
4. Clique em **"Salvar Permissões"**

#### 3. Criar Novo Usuário

Ao criar um novo usuário em **"Usuários"**:

1. Preencha os dados básicos (nome, email, senha)
2. Selecione o **tipo de usuário**
3. Vincule a um ou mais **estabelecimentos** (se não for Admin Geral)
4. As **permissões padrão** serão criadas automaticamente baseadas no tipo

### Para Usuários

Os usuários verão automaticamente:

- ✅ Apenas páginas que têm permissão no menu
- ✅ Apenas botões de ação permitidos (ex: "+ Nova Bebida")
- ❌ Páginas sem permissão retornarão erro de acesso

---

## 🔑 Tipos de Permissões

### Permissões Padrão por Tipo de Usuário

#### 1. Administrador Geral (type = 1)

```
✅ Acesso TOTAL a todas as páginas
✅ Todas as ações: Ver, Criar, Editar, Excluir
✅ Acesso a páginas exclusivas (Logs, E-mail, Telegram)
✅ Gerenciar permissões de outros usuários
```

#### 2. Gerente (type = 2)

```
✅ Páginas operacionais e financeiras
✅ Ações: Ver, Criar, Editar
❌ Não pode excluir registros
❌ Sem acesso a páginas exclusivas do Admin
```

**Páginas com acesso:**
- Dashboard
- Bebidas
- TAPs
- Pagamentos
- Pedidos
- Usuários
- Estabelecimentos
- Taxas de Juros
- Contas a Pagar

#### 3. Operador (type = 3)

```
✅ Páginas operacionais básicas
✅ Ações: Ver, Editar
❌ Não pode criar novos registros
❌ Não pode excluir registros
❌ Sem acesso a páginas financeiras
```

**Páginas com acesso:**
- Dashboard
- Bebidas
- TAPs
- Pedidos

#### 4. Visualizador (type = 4)

```
✅ Apenas visualização
✅ Ação: Ver
❌ Não pode criar, editar ou excluir
❌ Acesso limitado a páginas básicas
```

**Páginas com acesso:**
- Dashboard
- Bebidas
- TAPs
- Pedidos

---

## 🔒 Páginas Exclusivas

Estas páginas são **exclusivas do Administrador Geral** e não podem ser atribuídas a outros usuários:

### 1. Logs do Sistema
- **URL**: `admin/logs.php`
- **Motivo**: Contém informações sensíveis do sistema
- **Acesso**: Apenas Admin Geral

### 2. Configuração de E-mail
- **URL**: `admin/email_config.php`
- **Motivo**: Configurações críticas de envio de e-mail
- **Acesso**: Apenas Admin Geral

### 3. Telegram
- **URL**: `admin/telegram.php`
- **Motivo**: Configurações de integração e bot
- **Acesso**: Apenas Admin Geral

---

## 👨‍💻 Desenvolvimento

### Adicionar Nova Página ao Sistema

#### 1. Cadastrar a Página no Banco

```sql
INSERT INTO `system_pages` 
(`page_key`, `page_name`, `page_url`, `page_icon`, `page_category`, `admin_only`) 
VALUES 
('minha_pagina', 'Minha Página', 'admin/minha_pagina.php', 'fas fa-star', 'Operacional', 0);
```

#### 2. Adicionar Verificação na Página

```php
<?php
$page_title = 'Minha Página';
$current_page = 'minha_pagina';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

// Verificar permissão de acesso
requirePagePermission('minha_pagina', 'view');

// Resto do código...
?>
```

#### 3. Proteger Botões de Ação

```php
<!-- Botão de criar -->
<?php if (hasPagePermission('minha_pagina', 'create')): ?>
<button onclick="openModal()">+ Novo Item</button>
<?php endif; ?>

<!-- Botão de editar -->
<?php if (hasPagePermission('minha_pagina', 'edit')): ?>
<button onclick="editItem()">Editar</button>
<?php endif; ?>

<!-- Botão de excluir -->
<?php if (hasPagePermission('minha_pagina', 'delete')): ?>
<button onclick="deleteItem()">Excluir</button>
<?php endif; ?>
```

#### 4. Adicionar ao Menu (header.php)

```php
$menu_structure = [
    // ... outras páginas
    'minha_pagina' => [
        'title' => 'Minha Página',
        'icon' => 'fas fa-star',
        'url' => 'admin/minha_pagina.php',
        'page_key' => 'minha_pagina'
    ],
];
```

### Funções Disponíveis

#### `hasPagePermission($page_key, $action)`
Verifica se o usuário tem permissão para uma ação específica.

```php
if (hasPagePermission('bebidas', 'create')) {
    // Usuário pode criar bebidas
}
```

#### `requirePagePermission($page_key, $action)`
Requer permissão ou redireciona para dashboard.

```php
requirePagePermission('bebidas', 'view');
```

#### `getUserPermissions($user_id)`
Obtém todas as permissões de um usuário.

```php
$permissions = getUserPermissions($user_id);
```

#### `saveUserPermissions($user_id, $permissions)`
Salva permissões de um usuário.

```php
$permissions = [
    1 => ['view' => 1, 'create' => 1],
    2 => ['view' => 1, 'edit' => 1]
];
saveUserPermissions($user_id, $permissions);
```

#### `createDefaultPermissions($user_id, $user_type)`
Cria permissões padrão para novo usuário.

```php
createDefaultPermissions($user_id, 2); // Gerente
```

---

## 🔧 Troubleshooting

### Problema: Usuário não vê nenhuma página no menu

**Solução:**
1. Verifique se o usuário tem permissões cadastradas
2. Acesse "Permissões" e configure as permissões do usuário
3. Ou execute: `createDefaultPermissions($user_id, $user_type);`

### Problema: Erro "Você não tem permissão para acessar esta página"

**Solução:**
1. Faça login como Admin Geral
2. Acesse "Permissões"
3. Selecione o usuário
4. Marque a permissão "Ver" para a página desejada

### Problema: Botões de ação não aparecem

**Solução:**
1. Verifique se o usuário tem a permissão específica (create, edit, delete)
2. Configure as permissões em "Permissões"

### Problema: Páginas exclusivas aparecem para outros usuários

**Solução:**
1. Verifique se `admin_only = 1` na tabela `system_pages`
2. Certifique-se de que o header.php foi atualizado corretamente

---

## 📞 Suporte

Para dúvidas ou problemas:

1. Verifique este README completo
2. Consulte os logs do sistema em `logs/errors.log`
3. Entre em contato com o suporte técnico

---

## 📝 Changelog

### v3.1 - Sistema de Permissões
- ✅ Implementado sistema completo de permissões por página
- ✅ Criadas tabelas `system_pages` e `user_permissions`
- ✅ Menu lateral dinâmico baseado em permissões
- ✅ Interface de gerenciamento de permissões
- ✅ Páginas exclusivas para Admin Geral
- ✅ Permissões padrão por tipo de usuário
- ✅ Vínculo de usuários a estabelecimentos

---

**Desenvolvido por**: Manus AI  
**Sistema**: Chopp On Tap  
**Versão**: 3.1  
**Data**: Dezembro 2025

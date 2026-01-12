# Guia de Instalação - Sistema Chopp On Tap

## Passo a Passo para HostGator

### 1️⃣ Preparação

Antes de começar, tenha em mãos:
- ✅ Acesso ao painel cPanel do HostGator
- ✅ Credenciais do banco de dados MySQL
- ✅ Token da API SumUp
- ✅ Cliente FTP (FileZilla) ou use o Gerenciador de Arquivos do cPanel

---

### 2️⃣ Upload dos Arquivos

**Opção A: Via FTP (FileZilla)**

1. Conecte-se ao servidor FTP do HostGator
2. Navegue até a pasta `public_html`
3. Faça upload de **todos os arquivos** do sistema
4. Aguarde a conclusão do upload

**Opção B: Via cPanel**

1. Acesse o **Gerenciador de Arquivos** no cPanel
2. Navegue até `public_html`
3. Clique em **Upload**
4. Faça upload do arquivo `choppon_new.zip`
5. Clique com botão direito no arquivo e selecione **Extrair**
6. Delete o arquivo ZIP após extração

---

### 3️⃣ Configurar Banco de Dados

**No cPanel:**

1. Acesse **MySQL Databases**
2. O banco já deve existir: `inlaud99_choppontap`
3. Verifique se o usuário `inlaud99_admin` tem permissões completas
4. Anote as credenciais:
   - Host: `localhost`
   - Banco: `inlaud99_choppontap`
   - Usuário: `inlaud99_admin`
   - Senha: `Admin259087@`

---

### 4️⃣ Importar Estrutura do Banco

**Via phpMyAdmin:**

1. Acesse **phpMyAdmin** no cPanel
2. Selecione o banco `inlaud99_choppontap` na barra lateral
3. Clique na aba **Importar**
4. Clique em **Escolher arquivo**
5. Selecione o arquivo `database.sql`
6. Clique em **Executar**
7. Aguarde mensagem de sucesso

---

### 5️⃣ Configurar Permissões de Pastas

**Via Gerenciador de Arquivos:**

1. Navegue até a pasta do sistema
2. Clique com botão direito em `uploads` → **Alterar Permissões**
3. Defina como **755** (rwxr-xr-x)
4. Marque **Recursivo** e aplique
5. Repita para a pasta `logs`

**Via SSH (se disponível):**

```bash
chmod -R 755 uploads/
chmod -R 755 logs/
```

---

### 6️⃣ Executar Instalador

1. Acesse no navegador: `https://seudominio.com.br/install.php`
2. **Passo 1:** Confirme as credenciais do banco de dados
3. **Passo 2:** Clique em "Importar Banco de Dados"
4. **Passo 3:** Confirme a URL do site
5. **Passo 4:** Instalação concluída!

---

### 7️⃣ Primeiro Acesso

1. Acesse: `https://seudominio.com.br/`
2. Faça login com:
   - **Email:** `choppon24h@gmail.com`
   - **Senha:** `Admin259087@`

---

### 8️⃣ Configurar Integração SumUp

**No painel administrativo:**

1. Acesse **Pagamentos**
2. Insira o **Token SumUp**
3. Marque os métodos de pagamento habilitados:
   - ☑ PIX
   - ☑ Cartão de Crédito
   - ☑ Cartão de Débito
4. Clique em **Salvar Configurações**

**No painel SumUp:**

1. Acesse o painel de desenvolvedor da SumUp
2. Configure o webhook:
   ```
   https://seudominio.com.br/api/webhook.php
   ```
3. Salve as configurações

---

### 9️⃣ Configurar TAPs com Leitora de Cartão

Para cada TAP que terá leitora de cartão:

1. Acesse **TAPs** no menu
2. Clique em **+ Nova TAP** ou edite uma existente
3. Preencha o campo **Código de Pareamento SumUp**
4. O sistema automaticamente registrará a leitora

---

### 🔟 Segurança Pós-Instalação

**IMPORTANTE - Execute após instalação:**

1. **Delete o arquivo `install.php`:**
   ```bash
   rm install.php
   ```

2. **Altere o JWT Secret:**
   - Edite `includes/config.php`
   - Altere a linha: `define('JWT_SECRET', 'seu-segredo-aqui');`
   - Use uma string aleatória longa

3. **Configure SSL/HTTPS:**
   - No cPanel, acesse **SSL/TLS**
   - Instale certificado SSL gratuito (Let's Encrypt)
   - Force redirecionamento HTTPS no `.htaccess`

---

## Verificação de Instalação

### ✅ Checklist

- [ ] Arquivos enviados para o servidor
- [ ] Banco de dados importado
- [ ] Permissões de pastas configuradas
- [ ] Login funcionando
- [ ] Token SumUp configurado
- [ ] Webhook SumUp configurado
- [ ] Arquivo install.php deletado
- [ ] JWT Secret alterado
- [ ] SSL/HTTPS configurado

---

## Estrutura de URLs

- **Painel Admin:** `https://seudominio.com.br/admin/dashboard.php`
- **API REST:** `https://seudominio.com.br/api/`
- **Webhook:** `https://seudominio.com.br/api/webhook.php`

---

## Solução de Problemas

### Erro 500 - Internal Server Error

1. Verifique permissões das pastas
2. Verifique logs em `logs/webhook.log`
3. Ative display_errors temporariamente em `includes/config.php`

### Não consegue fazer login

1. Verifique se o banco foi importado corretamente
2. Verifique credenciais do banco em `includes/config.php`
3. Verifique se a tabela `users` existe

### Webhook não funciona

1. Verifique se a URL está acessível publicamente
2. Verifique logs em `logs/webhook.log`
3. Teste manualmente com Postman ou curl

### Upload de imagens não funciona

1. Verifique permissões da pasta `uploads/bebidas/`
2. Deve ser 755 ou 777
3. Verifique `upload_max_filesize` no PHP

---

## Suporte Técnico

**Email:** choppon24h@gmail.com

**Logs do Sistema:**
- Webhook: `/logs/webhook.log`
- Erros PHP: Verifique error_log do servidor

---

## Próximos Passos

Após instalação:

1. **Cadastre estabelecimentos** (se for multi-estabelecimento)
2. **Cadastre bebidas** com imagens
3. **Configure TAPs** com Android ID e pareamento
4. **Crie usuários** para cada estabelecimento
5. **Teste a API** com o app Android
6. **Monitore logs** de webhook para verificar integração

---

## Backup

Recomendamos fazer backup regular de:
- Banco de dados MySQL (via phpMyAdmin)
- Pasta `uploads/` (imagens das bebidas)
- Arquivo `includes/config.php` (configurações)

---

**Desenvolvido para HostGator com PHP 7.4+ e MySQL 5.7+**

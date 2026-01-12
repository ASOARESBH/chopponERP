# Solução de Problemas - Chopp On Tap

## Problemas Comuns e Soluções

### ❌ Erro: "Duplicate entry 'choppon24h@gmail.com' for key 'users_email_unique'"

**Causa:** O banco de dados já contém o usuário admin e você tentou importar novamente.

**Solução 1 - Limpar e Reimportar (APAGA TODOS OS DADOS):**

1. Acesse **phpMyAdmin** no cPanel
2. Selecione o banco `inlaud99_choppontap`
3. Clique na aba **SQL**
4. Execute este comando:
```sql
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `order`;
DROP TABLE IF EXISTS `tap`;
DROP TABLE IF EXISTS `user_estabelecimento`;
DROP TABLE IF EXISTS `bebidas`;
DROP TABLE IF EXISTS `payment`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `estabelecimentos`;
SET FOREIGN_KEY_CHECKS = 1;
```
5. Volte ao instalador e importe novamente

**Solução 2 - Pular Instalador (se já tem dados):**

Se você já importou o banco e só quer atualizar arquivos:

1. **NÃO** execute o instalador novamente
2. Delete o arquivo `install.php`
3. Edite `includes/config.php` e confirme as credenciais
4. Acesse diretamente: `https://seudominio.com.br/`

---

### ❌ Erro 500 - Internal Server Error

**Causa:** Permissões de arquivo incorretas ou erro de PHP.

**Solução:**

1. Verifique permissões das pastas:
```bash
chmod 755 uploads/
chmod 755 uploads/bebidas/
chmod 755 logs/
```

2. Verifique se o arquivo `.htaccess` está presente

3. Ative display de erros temporariamente em `includes/config.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

4. Verifique logs de erro do PHP no cPanel

---

### ❌ Não Consegue Fazer Login

**Causa:** Senha incorreta ou banco não importado.

**Solução:**

1. Verifique se o banco foi importado corretamente
2. Use as credenciais corretas:
   - Email: `choppon24h@gmail.com`
   - Senha: `Admin259087@`

3. Se esqueceu a senha, execute no phpMyAdmin:
```sql
UPDATE users 
SET password = '$2y$12$LqGH7xZ5J3Y9K8vN2mP4xOZQw8F6R3T5Y7U9W1E4S6D8F0G2H4J6K8' 
WHERE email = 'choppon24h@gmail.com';
```

---

### ❌ Erro de Conexão com Banco de Dados

**Causa:** Credenciais incorretas em `includes/config.php`.

**Solução:**

1. Edite `includes/config.php`
2. Verifique as credenciais:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inlaud99_choppontap');
define('DB_USER', 'inlaud99_admin');
define('DB_PASS', 'Admin259087@');
```

3. Teste a conexão no phpMyAdmin com as mesmas credenciais

---

### ❌ Upload de Imagens Não Funciona

**Causa:** Permissões da pasta `uploads/` incorretas.

**Solução:**

1. Via SSH:
```bash
chmod -R 755 uploads/
chown -R usuario:usuario uploads/
```

2. Via cPanel Gerenciador de Arquivos:
   - Clique com botão direito em `uploads`
   - Alterar Permissões → 755
   - Marcar "Recursivo"

3. Verifique `upload_max_filesize` no PHP:
   - cPanel → Select PHP Version → Options
   - Aumente `upload_max_filesize` para 10M

---

### ❌ Webhook SumUp Não Funciona

**Causa:** URL não acessível ou logs não sendo gravados.

**Solução:**

1. Teste se a URL está acessível:
```bash
curl https://seudominio.com.br/api/webhook.php
```

2. Verifique permissões da pasta `logs/`:
```bash
chmod 755 logs/
```

3. Teste manualmente com Postman:
   - POST `https://seudominio.com.br/api/webhook.php`
   - Body (JSON):
```json
{
  "id": "test-123",
  "status": "SUCCESSFUL"
}
```

4. Verifique o log em `logs/webhook.log`

---

### ❌ API Retorna "Token inválido"

**Causa:** Token JWT expirado ou incorreto.

**Solução:**

1. Faça login novamente para obter novo token:
```bash
curl -X POST https://seudominio.com.br/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"choppon24h@gmail.com","password":"Admin259087@"}'
```

2. Use o token retornado no header:
```
Token: eyJ0eXAiOiJKV1QiLCJhbGc...
```

3. Se o problema persistir, altere o `JWT_SECRET` em `includes/config.php`

---

### ❌ Gráficos Não Aparecem no Dashboard

**Causa:** Chart.js não carregou ou erro de JavaScript.

**Solução:**

1. Verifique se há conexão com internet (Chart.js é CDN)
2. Abra Console do navegador (F12) e veja erros
3. Verifique se jQuery está carregando
4. Limpe cache do navegador (Ctrl+Shift+Del)

---

### ❌ Layout Quebrado / CSS Não Carrega

**Causa:** Caminho incorreto do CSS ou arquivo faltando.

**Solução:**

1. Verifique se o arquivo existe:
```
assets/css/style.css
```

2. Verifique a URL do site em `includes/config.php`:
```php
define('SITE_URL', 'https://seudominio.com.br');
```

3. Limpe cache do navegador

---

### ❌ Erro "Call to undefined function password_verify()"

**Causa:** Versão do PHP muito antiga.

**Solução:**

1. No cPanel → Select PHP Version
2. Escolha PHP 7.4 ou superior
3. Reinicie o servidor

---

### ❌ Tabelas Não Aparecem no phpMyAdmin

**Causa:** Banco não foi importado ou importação falhou.

**Solução:**

1. Verifique se o banco `inlaud99_choppontap` existe
2. Selecione o banco no phpMyAdmin
3. Se estiver vazio, importe `database.sql` manualmente:
   - Clique em **Importar**
   - Selecione `database.sql`
   - Clique em **Executar**

---

### ❌ Sistema Lento

**Causa:** Muitos dados ou servidor sobrecarregado.

**Solução:**

1. Ative cache no `.htaccess` (já configurado)
2. Otimize banco de dados no phpMyAdmin:
   - Selecione todas as tabelas
   - "Com selecionados" → Otimizar tabela
3. Considere upgrade do plano de hospedagem

---

## Comandos Úteis

### Resetar Senha do Admin

```sql
UPDATE users 
SET password = '$2y$12$LqGH7xZ5J3Y9K8vN2mP4xOZQw8F6R3T5Y7U9W1E4S6D8F0G2H4J6K8' 
WHERE email = 'choppon24h@gmail.com';
```

### Limpar Todos os Pedidos

```sql
TRUNCATE TABLE `order`;
```

### Resetar Volume Consumido das TAPs

```sql
UPDATE tap SET volume_consumido = 0;
```

### Ver Últimos Webhooks Recebidos

```bash
tail -n 50 logs/webhook.log
```

### Testar Conexão com Banco

```php
<?php
$conn = new PDO("mysql:host=localhost;dbname=inlaud99_choppontap", "inlaud99_admin", "Admin259087@");
echo "Conexão OK!";
?>
```

---

## Logs do Sistema

### Webhook Log

**Localização:** `/logs/webhook.log`

**Conteúdo:** Todas as requisições recebidas no webhook SumUp

**Como ver:**
```bash
tail -f logs/webhook.log
```

### Error Log PHP

**Localização:** Varia por servidor, geralmente:
- `/home/usuario/public_html/error_log`
- `/var/log/apache2/error.log`

**Como ver no cPanel:**
- Metrics → Errors

---

## Backup e Restauração

### Fazer Backup

**Banco de Dados:**
1. phpMyAdmin → Selecione banco
2. Exportar → Executar
3. Salve o arquivo `.sql`

**Arquivos:**
1. Faça backup da pasta `uploads/`
2. Faça backup de `includes/config.php`

### Restaurar Backup

**Banco de Dados:**
1. phpMyAdmin → Selecione banco
2. Importar → Selecione arquivo `.sql`
3. Executar

**Arquivos:**
1. Restaure pasta `uploads/`
2. Restaure `includes/config.php`

---

## Contato para Suporte

**Email:** choppon24h@gmail.com

**Ao solicitar suporte, informe:**
- Mensagem de erro completa
- Passo a passo do que fez antes do erro
- Versão do PHP (cPanel → Select PHP Version)
- Conteúdo do `logs/webhook.log` (se relevante)
- Screenshot do erro (se possível)

---

**Última atualização:** 25/11/2025

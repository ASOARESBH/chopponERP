# 🚀 Instalação Rápida - Chopp On Tap v2.0.3

## Problema Identificado e Corrigido

**Problema:** CSS não carregava porque o `SITE_URL` estava configurado como `http://localhost` ao invés do domínio real.

**Solução:** Implementada detecção automática de URL. O sistema agora detecta automaticamente o domínio e protocolo (HTTP/HTTPS).

---

## 📦 Passo a Passo de Instalação

### 1. Upload dos Arquivos

**Via FTP (FileZilla):**
1. Conecte-se ao servidor
2. Navegue até `public_html`
3. Faça upload de **todos os arquivos** do ZIP
4. Aguarde conclusão

**Via cPanel:**
1. Gerenciador de Arquivos
2. Navegue até `public_html`
3. Upload do ZIP
4. Extrair arquivo
5. Mover arquivos da pasta `choppon_new` para `public_html` (ou deixar em subpasta)

---

### 2. Importar Banco de Dados

**phpMyAdmin:**
1. Acesse phpMyAdmin no cPanel
2. Selecione o banco `inlaud99_choppontap`
3. Clique em **SQL**
4. Execute este comando para limpar (se já tentou antes):

```sql
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `order`, `tap`, `user_estabelecimento`, 
                     `bebidas`, `payment`, `users`, `estabelecimentos`;
SET FOREIGN_KEY_CHECKS = 1;
```

5. Clique em **Importar**
6. Selecione `database.sql`
7. Clique em **Executar**

---

### 3. Atualizar Senha do Admin

**Opção A - Via Script (Recomendado):**
1. Acesse: `https://seudominio.com.br/update_password.php`
2. Clique em **Atualizar Senha**
3. Delete o arquivo: `rm update_password.php`

**Opção B - Via phpMyAdmin:**
1. Acesse phpMyAdmin
2. Selecione banco `inlaud99_choppontap`
3. Clique em **SQL**
4. Execute:

```sql
UPDATE users 
SET password = '$2y$12$0WtTRckkCnL3IiFtG8qKH.h7wqCPYQkfktIlJC6Ry2iYNKz1K7Lty' 
WHERE email = 'choppon24h@gmail.com';
```

---

### 4. Testar Detecção de URL

1. Acesse: `https://seudominio.com.br/test_url.php`
2. Verifique se o **SITE_URL** está correto
3. Verifique se todos os arquivos foram encontrados
4. Teste os links de CSS e JS
5. Delete o arquivo: `rm test_url.php`

---

### 5. Fazer Login

1. Acesse: `https://seudominio.com.br/`
2. Login:
   - **Email:** `choppon24h@gmail.com`
   - **Senha:** `Admin259087@`
3. Verifique se o CSS está carregando corretamente
4. Navegue pelas páginas do menu

---

## ✅ Checklist de Verificação

Após instalação, verifique:

- [ ] CSS está carregando (página com cores e layout bonito)
- [ ] Menu lateral funciona
- [ ] Dashboard mostra cards coloridos
- [ ] Consegue navegar entre as páginas
- [ ] Logo aparece no topo
- [ ] Gráficos aparecem no dashboard
- [ ] Não há erros 404 no console do navegador (F12)

---

## 🔧 Se o CSS Ainda Não Carregar

### 1. Verificar Permissões

```bash
chmod 755 assets/
chmod 755 assets/css/
chmod 644 assets/css/style.css
chmod 755 assets/js/
chmod 644 assets/js/main.js
chmod 755 assets/images/
chmod 644 assets/images/*
```

### 2. Verificar .htaccess

Certifique-se de que o arquivo `.htaccess` existe na raiz e contém:

```apache
# Habilitar RewriteEngine
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
</IfModule>

# Permitir acesso aos assets
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico)$">
    Allow from all
</FilesMatch>
```

### 3. Verificar Console do Navegador

1. Abra a página
2. Pressione F12
3. Vá na aba **Console**
4. Procure por erros 404
5. Se houver, anote a URL que está dando erro

### 4. Verificar Estrutura de Pastas

Certifique-se de que a estrutura está assim:

```
public_html/  (ou subpasta)
├── index.php
├── .htaccess
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── images/
│       ├── logo.png
│       └── logo.jpeg
├── admin/
│   ├── dashboard.php
│   ├── bebidas.php
│   └── ...
├── includes/
│   ├── config.php
│   ├── auth.php
│   └── ...
└── api/
    ├── login.php
    └── ...
```

---

## 🆘 Solução de Problemas

### Erro: "Not Found" ao clicar no menu

**Causa:** SITE_URL ainda está incorreto ou arquivos não foram enviados.

**Solução:**
1. Acesse `test_url.php` e veja o SITE_URL detectado
2. Se estiver errado, edite manualmente `includes/config.php`
3. Procure pela função `detectSiteURL()` e ajuste

### Erro: CSS carrega mas está "quebrado"

**Causa:** Arquivo CSS corrompido ou incompleto.

**Solução:**
1. Verifique tamanho do arquivo: deve ter ~17KB
2. Abra o arquivo CSS no navegador
3. Se estiver vazio ou incompleto, faça upload novamente

### Erro: "Call to undefined function Logger"

**Causa:** Arquivo `includes/logger.php` não foi enviado.

**Solução:**
1. Verifique se o arquivo existe
2. Se não, faça upload novamente de todos os arquivos da pasta `includes/`

---

## 🔐 Segurança Pós-Instalação

Após confirmar que tudo funciona:

```bash
# Delete arquivos de teste
rm test_url.php
rm update_password.php
rm install.php

# Desative modo debug
# Edite includes/config.php e mude:
# define('DEBUG_MODE', false);

# Altere JWT_SECRET
# Edite includes/config.php e mude:
# define('JWT_SECRET', 'sua-chave-aleatoria-longa-aqui');
```

---

## 📊 Verificar Logs

Se houver problemas, verifique os logs:

**Via Painel Admin:**
1. Faça login
2. Menu → **Logs**
3. Selecione **Autenticação** ou **Erros**

**Via SSH:**
```bash
tail -50 logs/auth.log
tail -50 logs/errors.log
tail -50 logs/debug.log
```

---

## 📞 Suporte

**Email:** choppon24h@gmail.com

**Ao solicitar suporte, envie:**
1. URL do site
2. Screenshot do erro
3. Resultado do `test_url.php`
4. Conteúdo de `logs/errors.log` (últimas 50 linhas)

---

## 🎉 Resultado Esperado

Após instalação correta:

✅ Login funcionando  
✅ Dashboard com layout bonito e colorido  
✅ Menu lateral estilizado  
✅ Cards com cores azul e laranja  
✅ Gráficos aparecendo  
✅ Navegação entre páginas funcionando  
✅ Logo aparecendo no topo  

---

**Versão:** 2.0.3  
**Data:** 25/11/2025  
**Status:** ✅ Pronto para Produção

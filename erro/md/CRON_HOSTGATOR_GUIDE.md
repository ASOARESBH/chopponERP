# Guia de Configuração do CRON no HostGator

Este guia detalha a configuração correta da tarefa CRON para o script de notificação de contas a pagar (`notificar_contas_vencer.php`) no ambiente de hospedagem HostGator.

## 1. Análise da Configuração Atual

A imagem fornecida mostra a seguinte configuração de CRON:

| Minuto | Hora | Dia | Mês | Dia da Semana | Comando |
| :---: | :---: | :---: | :---: | :---: | :--- |
| 0 | 8 | * | * | 7 | `/usr/bin/php /caminho/completo/cron/notificar_contas_vencer.php` |

**Problemas Identificados:**

1.  **Dia da Semana (7):** Na maioria dos sistemas CRON (incluindo o cPanel da HostGator), o dia da semana é representado de 0 a 6, onde 0 (ou 7) é Domingo. O valor `7` pode ser interpretado como um erro ou como Domingo, dependendo da implementação. O valor mais seguro para **todos os dias** é `*` (asterisco). Se a intenção era rodar apenas no Domingo, o correto seria `0`.
2.  **Caminho Incorreto:** O comando usa `/caminho/completo/cron/notificar_contas_vencer.php`. Este é um **caminho de exemplo** e deve ser substituído pelo caminho absoluto real do arquivo no seu servidor HostGator.

## 2. Configuração Correta do CRON

Para garantir que o script seja executado **todos os dias às 08:00**, a configuração deve ser:

| Campo | Valor | Descrição |
| :---: | :---: | :--- |
| Minuto | `0` | Executar no minuto 0 da hora (início da hora) |
| Hora | `8` | Executar às 8 da manhã |
| Dia | `*` | Executar todos os dias do mês |
| Mês | `*` | Executar todos os meses |
| Dia da Semana | `*` | Executar todos os dias da semana |

### Comando CRON

O comando deve usar o interpretador PHP e o caminho absoluto para o script.

1.  **Localize o Caminho Absoluto:**
    O caminho absoluto do seu diretório principal (home) na HostGator geralmente é algo como `/home/seuusuario/`.
    Se o seu sistema PHP estiver no diretório `public_html`, o caminho absoluto para o script CRON será:
    
    ```
    /home/seuusuario/public_html/PHP/cron/notificar_contas_vencer.php
    ```
    
    **Substitua `seuusuario` pelo seu nome de usuário real na HostGator.**

2.  **Comando Completo:**
    O comando completo a ser inserido no campo "Comando" do cPanel será:

    ```bash
    /usr/bin/php /home/seuusuario/public_html/PHP/cron/notificar_contas_vencer.php
    ```

    **Alternativa (Recomendada):** Para evitar problemas com o caminho do PHP, use o comando `php` e redirecione a saída para um log:

    ```bash
    /usr/bin/php -q /home/seuusuario/public_html/PHP/cron/notificar_contas_vencer.php >/dev/null 2>&1
    ```
    
    *   `>/dev/null`: Redireciona a saída padrão (echo/print) para o "nada".
    *   `2>&1`: Redireciona a saída de erro para a saída padrão.
    *   **Isso evita que o CRON envie e-mails a cada execução.**

## 3. Passo a Passo no cPanel (HostGator)

1.  Acesse o **cPanel** da sua conta HostGator.
2.  Na seção **Avançado**, clique em **Tarefas Cron** (ou **Cron Jobs**).
3.  Na seção **Adicionar Nova Tarefa Cron**, configure os campos:
    *   **Configurações Comuns:** Selecione `Once per day (0 8 * * *)` ou `Uma vez por dia (0 8 * * *)`.
    *   **Minuto:** `0`
    *   **Hora:** `8`
    *   **Dia:** `*`
    *   **Mês:** `*`
    *   **Dia da Semana:** `*`
4.  No campo **Comando**, insira o comando completo, substituindo o caminho de exemplo pelo seu caminho real:
    
    ```bash
    /usr/bin/php -q /home/seuusuario/public_html/PHP/cron/notificar_contas_vencer.php >/dev/null 2>&1
    ```
    
5.  Clique em **Adicionar Nova Tarefa Cron**.

**Observação:** O script `notificar_contas_vencer.php` foi corrigido para usar a classe `TelegramBot` de forma correta, o que deve resolver o problema de notificação, desde que o CRON esteja configurado corretamente e as credenciais do Telegram estejam válidas no sistema.

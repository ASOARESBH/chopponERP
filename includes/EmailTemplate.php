<?php
/**
 * EmailTemplate - Templates de E-mail Profissionais
 * Responsável por gerar HTML dos e-mails enviados pelo sistema
 */

class EmailTemplate {
    
    /**
     * Assunto do e-mail de cobrança
     */
    public function getAssuntoCobranca($royalty, $estabelecimento) {
        return sprintf(
            "💰 Cobrança de Royalties - %s - Período %s a %s",
            $estabelecimento['name'],
            date('d/m/Y', strtotime($royalty['periodo_inicial'])),
            date('d/m/Y', strtotime($royalty['periodo_final']))
        );
    }
    
    /**
     * Template de Cobrança de Royalties
     */
    public function getTemplateCobranca($royalty, $estabelecimento) {
        $periodo_inicial = date('d/m/Y', strtotime($royalty['periodo_inicial']));
        $periodo_final = date('d/m/Y', strtotime($royalty['periodo_final']));
        $faturamento = number_format($royalty['valor_faturamento_bruto'], 2, ',', '.');
        $royalties = number_format($royalty['valor_royalties'], 2, ',', '.');
        $vencimento = date('d/m/Y', strtotime($royalty['data_vencimento']));
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px; }
                .info-box { background: #f8f9fa; padding: 20px; margin: 20px 0; border-left: 4px solid #667eea; border-radius: 4px; }
                .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #dee2e6; }
                .info-row:last-child { border-bottom: none; }
                .label { font-weight: bold; color: #6c757d; }
                .value { color: #212529; }
                .highlight { font-size: 24px; font-weight: bold; color: #667eea; }
                .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
                .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🍺 Chopp On Tap</h1>
                    <p style='margin: 10px 0 0 0; font-size: 16px;'>Cobrança de Royalties</p>
                </div>
                <div class='content'>
                    <p>Prezado(a) <strong>" . htmlspecialchars($estabelecimento['name']) . "</strong>,</p>
                    
                    <p>Segue link para pagamento dos royalties referente ao período abaixo:</p>
                    
                    <div class='info-box'>
                        <div class='info-row'>
                            <span class='label'>📅 Período:</span>
                            <span class='value'>{$periodo_inicial} a {$periodo_final}</span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>📊 Faturamento Bruto:</span>
                            <span class='value'>R$ {$faturamento}</span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>💰 Royalties (7%):</span>
                            <span class='value highlight'>R$ {$royalties}</span>
                        </div>
                        <div class='info-row'>
                            <span class='label'>📆 Vencimento:</span>
                            <span class='value'>{$vencimento}</span>
                        </div>
                    </div>
                    
                    <div class='alert'>
                        <strong>📝 Descrição:</strong><br>
                        " . nl2br(htmlspecialchars($royalty['descricao'])) . "
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . htmlspecialchars($royalty['payment_link_url']) . "' class='button'>
                            🔗 PAGAR AGORA
                        </a>
                    </div>
                    
                    <p style='font-size: 14px; color: #6c757d;'>
                        <strong>Importante:</strong> Este link de pagamento é seguro e processado pela Stripe. 
                        Você pode pagar com cartão de crédito, PIX ou boleto bancário.
                    </p>
                    
                    <p>Em caso de dúvidas, entre em contato conosco.</p>
                    
                    <p>Atenciosamente,<br>
                    <strong>Equipe Chopp On Tap</strong></p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático. Não responda.</p>
                    <p>&copy; " . date('Y') . " Chopp On Tap. Todos os direitos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $html;
    }
    
    /**
     * Assunto do e-mail de confirmação
     */
    public function getAssuntoConfirmacao($royalty, $estabelecimento) {
        return sprintf(
            "✅ Pagamento Confirmado - Royalties %s",
            $estabelecimento['name']
        );
    }
    
    /**
     * Template de Confirmação de Pagamento
     */
    public function getTemplateConfirmacao($royalty, $estabelecimento) {
        $periodo_inicial = date('d/m/Y', strtotime($royalty['periodo_inicial']));
        $periodo_final = date('d/m/Y', strtotime($royalty['periodo_final']));
        $royalties = number_format($royalty['valor_royalties'], 2, ',', '.');
        $data_pagamento = date('d/m/Y H:i:s');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px; }
                .success-icon { font-size: 64px; text-align: center; margin: 20px 0; }
                .info-box { background: #d4edda; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745; border-radius: 4px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Pagamento Confirmado!</h1>
                </div>
                <div class='content'>
                    <div class='success-icon'>🎉</div>
                    
                    <p>Olá <strong>" . htmlspecialchars($estabelecimento['name']) . "</strong>,</p>
                    
                    <p>Confirmamos o recebimento do pagamento dos royalties!</p>
                    
                    <div class='info-box'>
                        <p><strong>💰 Valor Pago:</strong> R$ {$royalties}</p>
                        <p><strong>📅 Data do Pagamento:</strong> {$data_pagamento}</p>
                        <p><strong>📊 Período:</strong> {$periodo_inicial} a {$periodo_final}</p>
                    </div>
                    
                    <p>Obrigado pela parceria!</p>
                    
                    <p>Atenciosamente,<br>
                    <strong>Equipe Chopp On Tap</strong></p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático. Não responda.</p>
                    <p>&copy; " . date('Y') . " Chopp On Tap. Todos os direitos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $html;
    }
    
    /**
     * Template de Alerta Genérico
     * Tipos: info, warning, error, success
     */
    public function getTemplateAlerta($tipo, $titulo, $mensagem, $dados = []) {
        $cores = [
            'info' => '#17a2b8',
            'warning' => '#ffc107',
            'error' => '#dc3545',
            'success' => '#28a745'
        ];
        
        $icones = [
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'error' => '❌',
            'success' => '✅'
        ];
        
        $cor = $cores[$tipo] ?? $cores['info'];
        $icone = $icones[$tipo] ?? $icones['info'];
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { background: {$cor}; color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .message-box { background: #f8f9fa; padding: 20px; border-left: 4px solid {$cor}; margin: 20px 0; border-radius: 4px; }
                .dados-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .dados-table th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6; }
                .dados-table td { padding: 10px; border-bottom: 1px solid #dee2e6; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$icone} {$titulo}</h1>
                </div>
                <div class='content'>
                    <div class='message-box'>
                        {$mensagem}
                    </div>
                    ";
        
        if (!empty($dados)) {
            $html .= "<table class='dados-table'>";
            $html .= "<thead><tr><th>Campo</th><th>Valor</th></tr></thead>";
            $html .= "<tbody>";
            foreach ($dados as $campo => $valor) {
                $html .= "<tr><td><strong>" . htmlspecialchars($campo) . "</strong></td><td>" . htmlspecialchars($valor) . "</td></tr>";
            }
            $html .= "</tbody></table>";
        }
        
        $html .= "
                    <p style='margin-top: 30px; color: #6c757d;'><small>Data/Hora: " . date('d/m/Y H:i:s') . "</small></p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático do sistema. Não responda.</p>
                    <p>&copy; " . date('Y') . " Chopp ON. Todos os direitos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $html;
    }
    
    // Templates específicos de alertas abaixo...
    
    public function getTemplateEstoqueBaixo($produto, $quantidade_atual, $quantidade_minima, $estabelecimento) {
        $mensagem = sprintf(
            "<p>O produto <strong>%s</strong> está com estoque baixo no estabelecimento <strong>%s</strong>.</p>
            <p><strong>Quantidade Atual:</strong> %d unidades<br>
            <strong>Quantidade Mínima:</strong> %d unidades</p>
            <p>Por favor, providencie a reposição o mais breve possível.</p>",
            htmlspecialchars($produto['nome']),
            htmlspecialchars($estabelecimento['name']),
            $quantidade_atual,
            $quantidade_minima
        );
        
        return $this->getTemplateAlerta('warning', 'Alerta de Estoque Baixo', $mensagem, [
            'Produto' => $produto['nome'],
            'Estabelecimento' => $estabelecimento['name'],
            'Quantidade Atual' => $quantidade_atual,
            'Quantidade Mínima' => $quantidade_minima
        ]);
    }
    
    public function getTemplateVencimentoConta($conta, $dias_restantes, $estabelecimento) {
        $urgencia = $dias_restantes <= 3 ? 'error' : 'warning';
        
        $mensagem = sprintf(
            "<p>A conta <strong>%s</strong> vence em <strong>%d dias</strong> (%s).</p>
            <p><strong>Valor:</strong> R$ %s</p>
            <p>Por favor, providencie o pagamento para evitar juros e multas.</p>",
            htmlspecialchars($conta['descricao']),
            $dias_restantes,
            date('d/m/Y', strtotime($conta['data_vencimento'])),
            number_format($conta['valor'], 2, ',', '.')
        );
        
        return $this->getTemplateAlerta($urgencia, 'Alerta de Vencimento de Conta', $mensagem, [
            'Descrição' => $conta['descricao'],
            'Valor' => 'R$ ' . number_format($conta['valor'], 2, ',', '.'),
            'Vencimento' => date('d/m/Y', strtotime($conta['data_vencimento'])),
            'Dias Restantes' => $dias_restantes,
            'Estabelecimento' => $estabelecimento['name']
        ]);
    }
}

<?php
/**
 * Classe de Validação de Dados
 * Versão 1.0
 */

class Validator {
    
    /**
     * Valida CPF brasileiro
     * @param string $cpf
     * @return bool
     */
    public static function validateCPF($cpf) {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        // Valida primeiro dígito verificador
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Valida CNPJ brasileiro
     * @param string $cnpj
     * @return bool
     */
    public static function validateCNPJ($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        // Valida primeiro dígito verificador
        for ($i = 0, $j = 5, $sum = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $remainder = $sum % 11;
        if ($cnpj[12] != ($remainder < 2 ? 0 : 11 - $remainder)) {
            return false;
        }
        
        // Valida segundo dígito verificador
        for ($i = 0, $j = 6, $sum = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $remainder = $sum % 11;
        return $cnpj[13] == ($remainder < 2 ? 0 : 11 - $remainder);
    }
    
    /**
     * Valida email
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valida valor monetário
     * @param mixed $value
     * @param float $min
     * @param float $max
     * @return bool
     */
    public static function validateMoney($value, $min = 0.01, $max = 999999.99) {
        $value = floatval($value);
        return $value >= $min && $value <= $max;
    }
    
    /**
     * Valida quantidade em mililitros
     * @param int $ml
     * @param int $min
     * @param int $max
     * @return bool
     */
    public static function validateML($ml, $min = 50, $max = 10000) {
        $ml = intval($ml);
        return $ml >= $min && $ml <= $max;
    }
    
    /**
     * Valida método de pagamento
     * @param string $method
     * @return bool
     */
    public static function validatePaymentMethod($method) {
        return in_array($method, ['pix', 'credit', 'debit']);
    }
    
    /**
     * Sanitiza string
     * @param string $data
     * @return string
     */
    public static function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Valida Android ID
     * @param string $android_id
     * @return bool
     */
    public static function validateAndroidID($android_id) {
        // Android ID deve ter entre 8 e 64 caracteres alfanuméricos
        return preg_match('/^[a-zA-Z0-9]{8,64}$/', $android_id);
    }
}

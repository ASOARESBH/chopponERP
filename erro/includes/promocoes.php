<?php
/**
 * Funções para gerenciamento de Promoções
 * Chopp On Tap - v3.2
 */

/**
 * Verificar se uma promoção está ativa no momento
 */
function isPromocaoAtiva($promocao_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT * FROM promocoes 
        WHERE id = ? 
        AND status = 1 
        AND NOW() BETWEEN data_inicio AND data_fim
    ");
    $stmt->execute([$promocao_id]);
    return $stmt->fetch() !== false;
}

/**
 * Obter promoções ativas para um estabelecimento
 */
function getPromocoesAtivas($estabelecimento_id = null) {
    $conn = getDBConnection();
    
    if ($estabelecimento_id) {
        $stmt = $conn->prepare("
            SELECT * FROM promocoes 
            WHERE estabelecimento_id = ? 
            AND status = 1 
            AND NOW() BETWEEN data_inicio AND data_fim
            ORDER BY data_inicio DESC
        ");
        $stmt->execute([$estabelecimento_id]);
    } else {
        $stmt = $conn->query("
            SELECT p.*, e.name as estabelecimento_nome
            FROM promocoes p
            LEFT JOIN estabelecimentos e ON p.estabelecimento_id = e.id
            WHERE p.status = 1 
            AND NOW() BETWEEN p.data_inicio AND p.data_fim
            ORDER BY p.data_inicio DESC
        ");
    }
    
    return $stmt->fetchAll();
}

/**
 * Obter bebidas de uma promoção
 */
function getPromocaoBebidas($promocao_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT b.* 
        FROM bebidas b
        INNER JOIN promocao_bebidas pb ON b.id = pb.bebida_id
        WHERE pb.promocao_id = ?
    ");
    $stmt->execute([$promocao_id]);
    return $stmt->fetchAll();
}

/**
 * Verificar se uma bebida está em promoção
 */
function getBebidaPromocao($bebida_id, $estabelecimento_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT p.* 
        FROM promocoes p
        INNER JOIN promocao_bebidas pb ON p.id = pb.promocao_id
        WHERE pb.bebida_id = ? 
        AND p.estabelecimento_id = ?
        AND p.status = 1 
        AND NOW() BETWEEN p.data_inicio AND p.data_fim
        LIMIT 1
    ");
    $stmt->execute([$bebida_id, $estabelecimento_id]);
    return $stmt->fetch();
}

/**
 * Validar cupom de promoção
 */
function validarCupomPromocao($cupom, $promocao_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT cupons FROM promocoes WHERE id = ?");
    $stmt->execute([$promocao_id]);
    $promocao = $stmt->fetch();
    
    if (!$promocao || !$promocao['cupons']) {
        return false;
    }
    
    // Limpar e normalizar o cupom
    $cupom = strtolower(trim($cupom));
    if (substr($cupom, 0, 1) !== '#') {
        $cupom = '#' . $cupom;
    }
    
    // Verificar se o cupom está na lista
    $cupons_validos = array_map('trim', explode(',', strtolower($promocao['cupons'])));
    return in_array($cupom, $cupons_validos);
}

/**
 * Calcular desconto de promoção
 */
function calcularDescontoPromocao($bebida_id, $estabelecimento_id, $cupom = null, $cashback = null) {
    $promocao = getBebidaPromocao($bebida_id, $estabelecimento_id);
    
    if (!$promocao) {
        return [
            'tem_promocao' => false,
            'valor_original' => 0,
            'valor_promocional' => 0,
            'desconto' => 0
        ];
    }
    
    // Obter valor da bebida
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT valor, valor_promo FROM bebidas WHERE id = ?");
    $stmt->execute([$bebida_id]);
    $bebida = $stmt->fetch();
    
    $valor_original = $bebida['valor'];
    $valor_promocional = $bebida['valor_promo'] ?? $valor_original;
    
    // Verificar regra da promoção
    $pode_usar = false;
    
    switch ($promocao['tipo_regra']) {
        case 'todos':
            $pode_usar = true;
            break;
            
        case 'cupom':
            if ($cupom && validarCupomPromocao($cupom, $promocao['id'])) {
                $pode_usar = true;
            }
            break;
            
        case 'cashback':
            if ($cashback && $cashback >= $promocao['cashback_valor']) {
                $pode_usar = true;
                // Para cashback, libera ML ao invés de desconto
                $ml_liberado = floor($cashback / $promocao['cashback_valor']) * $promocao['cashback_ml'];
                return [
                    'tem_promocao' => true,
                    'tipo' => 'cashback',
                    'cashback_necessario' => $promocao['cashback_valor'],
                    'ml_liberado' => $ml_liberado,
                    'promocao_id' => $promocao['id']
                ];
            }
            break;
    }
    
    if (!$pode_usar) {
        return [
            'tem_promocao' => false,
            'valor_original' => $valor_original,
            'valor_promocional' => $valor_original,
            'desconto' => 0
        ];
    }
    
    $desconto = $valor_original - $valor_promocional;
    
    return [
        'tem_promocao' => true,
        'tipo' => $promocao['tipo_regra'],
        'promocao_id' => $promocao['id'],
        'promocao_nome' => $promocao['nome'],
        'valor_original' => $valor_original,
        'valor_promocional' => $valor_promocional,
        'desconto' => $desconto,
        'percentual' => ($desconto / $valor_original) * 100
    ];
}

/**
 * Registrar uso de promoção
 */
function registrarUsoPromocao($promocao_id, $bebida_id, $pedido_id = null, $cupom = null, $cashback = null, $ml_liberado = null) {
    $conn = getDBConnection();
    
    // Obter valores
    $stmt = $conn->prepare("SELECT valor, valor_promo FROM bebidas WHERE id = ?");
    $stmt->execute([$bebida_id]);
    $bebida = $stmt->fetch();
    
    $valor_original = $bebida['valor'];
    $valor_promocional = $bebida['valor_promo'] ?? $valor_original;
    $desconto = $valor_original - $valor_promocional;
    
    $stmt = $conn->prepare("
        INSERT INTO promocao_uso 
        (promocao_id, bebida_id, pedido_id, cupom_usado, cashback_usado, ml_liberado, 
         valor_original, valor_promocional, desconto_aplicado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $promocao_id,
        $bebida_id,
        $pedido_id,
        $cupom,
        $cashback,
        $ml_liberado,
        $valor_original,
        $valor_promocional,
        $desconto
    ]);
}

/**
 * Obter estatísticas de uso de promoção
 */
function getEstatisticasPromocao($promocao_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_usos,
            SUM(desconto_aplicado) as total_desconto,
            SUM(ml_liberado) as total_ml_liberado,
            COUNT(DISTINCT bebida_id) as bebidas_diferentes,
            AVG(desconto_aplicado) as desconto_medio
        FROM promocao_uso
        WHERE promocao_id = ?
    ");
    $stmt->execute([$promocao_id]);
    return $stmt->fetch();
}

/**
 * Obter todas as promoções (com filtros)
 */
function getAllPromocoes($estabelecimento_id = null, $status = null) {
    $conn = getDBConnection();
    
    $where = [];
    $params = [];
    
    if ($estabelecimento_id) {
        $where[] = "p.estabelecimento_id = ?";
        $params[] = $estabelecimento_id;
    }
    
    if ($status !== null) {
        $where[] = "p.status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "
        SELECT p.*, 
               e.name as estabelecimento_nome,
               COUNT(DISTINCT pb.bebida_id) as total_bebidas,
               CASE 
                   WHEN NOW() < p.data_inicio THEN 'agendada'
                   WHEN NOW() BETWEEN p.data_inicio AND p.data_fim THEN 'ativa'
                   ELSE 'expirada'
               END as situacao
        FROM promocoes p
        LEFT JOIN estabelecimentos e ON p.estabelecimento_id = e.id
        LEFT JOIN promocao_bebidas pb ON p.id = pb.promocao_id
        $whereClause
        GROUP BY p.id
        ORDER BY p.data_inicio DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Criar nova promoção
 */
function createPromocao($data) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO promocoes 
        (estabelecimento_id, nome, descricao, data_inicio, data_fim, tipo_regra, 
         cupons, cashback_valor, cashback_ml, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $data['estabelecimento_id'],
        $data['nome'],
        $data['descricao'],
        $data['data_inicio'],
        $data['data_fim'],
        $data['tipo_regra'],
        $data['cupons'] ?? null,
        $data['cashback_valor'] ?? null,
        $data['cashback_ml'] ?? null,
        $data['status'] ?? 1
    ]);
    
    if ($result) {
        return $conn->lastInsertId();
    }
    
    return false;
}

/**
 * Atualizar promoção
 */
function updatePromocao($id, $data) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        UPDATE promocoes SET
            estabelecimento_id = ?,
            nome = ?,
            descricao = ?,
            data_inicio = ?,
            data_fim = ?,
            tipo_regra = ?,
            cupons = ?,
            cashback_valor = ?,
            cashback_ml = ?,
            status = ?
        WHERE id = ?
    ");
    
    return $stmt->execute([
        $data['estabelecimento_id'],
        $data['nome'],
        $data['descricao'],
        $data['data_inicio'],
        $data['data_fim'],
        $data['tipo_regra'],
        $data['cupons'] ?? null,
        $data['cashback_valor'] ?? null,
        $data['cashback_ml'] ?? null,
        $data['status'] ?? 1,
        $id
    ]);
}

/**
 * Deletar promoção
 */
function deletePromocao($id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM promocoes WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Vincular bebidas à promoção
 */
function vincularBebidasPromocao($promocao_id, $bebidas_ids) {
    $conn = getDBConnection();
    
    // Remover vínculos antigos
    $stmt = $conn->prepare("DELETE FROM promocao_bebidas WHERE promocao_id = ?");
    $stmt->execute([$promocao_id]);
    
    // Adicionar novos vínculos
    $stmt = $conn->prepare("INSERT INTO promocao_bebidas (promocao_id, bebida_id) VALUES (?, ?)");
    
    foreach ($bebidas_ids as $bebida_id) {
        $stmt->execute([$promocao_id, $bebida_id]);
    }
    
    return true;
}

/**
 * Obter IDs das bebidas vinculadas
 */
function getPromocaoBebidasIds($promocao_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT bebida_id FROM promocao_bebidas WHERE promocao_id = ?");
    $stmt->execute([$promocao_id]);
    return array_column($stmt->fetchAll(), 'bebida_id');
}

<?php
/**
 * Classe FornecedoresManager
 * Gerencia todas as operações relacionadas a fornecedores
 */

class FornecedoresManager {
    private $conn;
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }
    
    /**
     * Criar novo fornecedor
     */
    public function criar($dados) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO fornecedores 
                (nome, razao_social, cnpj, email, telefone, whatsapp, endereco, 
                 cidade, estado, cep, contato_nome, observacoes, ativo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $result = $stmt->execute([
                $dados['nome'],
                $dados['razao_social'] ?? null,
                $dados['cnpj'] ?? null,
                $dados['email'] ?? null,
                $dados['telefone'] ?? null,
                $dados['whatsapp'] ?? null,
                $dados['endereco'] ?? null,
                $dados['cidade'] ?? null,
                $dados['estado'] ?? null,
                $dados['cep'] ?? null,
                $dados['contato_nome'] ?? null,
                $dados['observacoes'] ?? null
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Fornecedor cadastrado com sucesso!',
                    'id' => $this->conn->lastInsertId()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao cadastrar fornecedor.'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erro ao cadastrar fornecedor: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Atualizar fornecedor existente
     */
    public function atualizar($id, $dados) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE fornecedores 
                SET nome = ?, razao_social = ?, cnpj = ?, email = ?, telefone = ?,
                    whatsapp = ?, endereco = ?, cidade = ?, estado = ?, cep = ?,
                    contato_nome = ?, observacoes = ?, ativo = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $dados['nome'],
                $dados['razao_social'] ?? null,
                $dados['cnpj'] ?? null,
                $dados['email'] ?? null,
                $dados['telefone'] ?? null,
                $dados['whatsapp'] ?? null,
                $dados['endereco'] ?? null,
                $dados['cidade'] ?? null,
                $dados['estado'] ?? null,
                $dados['cep'] ?? null,
                $dados['contato_nome'] ?? null,
                $dados['observacoes'] ?? null,
                isset($dados['ativo']) ? 1 : 0,
                $id
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Fornecedor atualizado com sucesso!'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao atualizar fornecedor.'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erro ao atualizar fornecedor: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Excluir fornecedor
     */
    public function excluir($id) {
        try {
            // Verificar se há produtos vinculados
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as total 
                FROM estoque_produtos 
                WHERE fornecedor_id = ?
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                return [
                    'success' => false,
                    'message' => 'Não é possível excluir este fornecedor pois há ' . 
                                $result['total'] . ' produto(s) vinculado(s).'
                ];
            }
            
            // Excluir fornecedor
            $stmt = $this->conn->prepare("DELETE FROM fornecedores WHERE id = ?");
            $stmt->execute([$id]);
            
            return [
                'success' => true,
                'message' => 'Fornecedor excluído com sucesso!'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erro ao excluir fornecedor: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Buscar fornecedor por ID
     */
    public function buscarPorId($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM fornecedores WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Listar todos os fornecedores com filtros
     */
    public function listar($filtros = []) {
        try {
            $where = [];
            $params = [];
            
            // Filtro de busca
            if (!empty($filtros['busca'])) {
                $where[] = "(nome LIKE ? OR razao_social LIKE ? OR cnpj LIKE ?)";
                $busca_param = '%' . $filtros['busca'] . '%';
                $params[] = $busca_param;
                $params[] = $busca_param;
                $params[] = $busca_param;
            }
            
            // Filtro de status
            if (isset($filtros['ativo'])) {
                $where[] = "ativo = ?";
                $params[] = $filtros['ativo'];
            }
            
            // Filtro de cidade
            if (!empty($filtros['cidade'])) {
                $where[] = "cidade = ?";
                $params[] = $filtros['cidade'];
            }
            
            // Filtro de estado
            if (!empty($filtros['estado'])) {
                $where[] = "estado = ?";
                $params[] = $filtros['estado'];
            }
            
            $sql = "SELECT * FROM fornecedores";
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            $sql .= " ORDER BY nome";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Listar apenas fornecedores ativos
     */
    public function listarAtivos() {
        return $this->listar(['ativo' => 1]);
    }
    
    /**
     * Obter estatísticas de fornecedores
     */
    public function obterEstatisticas() {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    COUNT(*) as total_fornecedores,
                    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativos,
                    SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) as inativos
                FROM fornecedores
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Fornecedores com mais produtos
            $stmt = $this->conn->query("
                SELECT f.nome, COUNT(p.id) as total_produtos
                FROM fornecedores f
                LEFT JOIN estoque_produtos p ON f.id = p.fornecedor_id
                WHERE f.ativo = 1
                GROUP BY f.id
                ORDER BY total_produtos DESC
                LIMIT 5
            ");
            $stats['top_fornecedores'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
            
        } catch (PDOException $e) {
            return [
                'total_fornecedores' => 0,
                'ativos' => 0,
                'inativos' => 0,
                'top_fornecedores' => []
            ];
        }
    }
    
    /**
     * Validar CNPJ
     */
    public function validarCNPJ($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        // Validação básica de CNPJ
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar se CNPJ já existe
     */
    public function cnpjExiste($cnpj, $excluirId = null) {
        try {
            $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
            
            $sql = "SELECT COUNT(*) as total FROM fornecedores WHERE cnpj = ?";
            $params = [$cnpj];
            
            if ($excluirId) {
                $sql .= " AND id != ?";
                $params[] = $excluirId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result['total'] > 0;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Obter histórico de compras de um fornecedor
     */
    public function obterHistoricoCompras($fornecedorId, $limite = 10) {
        try {
            $stmt = $this->conn->prepare("
                SELECT m.*, p.nome as produto_nome, p.codigo as produto_codigo
                FROM estoque_movimentacoes m
                INNER JOIN estoque_produtos p ON m.produto_id = p.id
                WHERE m.fornecedor_id = ? AND m.tipo = 'entrada'
                ORDER BY m.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$fornecedorId, $limite]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Calcular valor total de compras de um fornecedor
     */
    public function calcularTotalCompras($fornecedorId, $dataInicio = null, $dataFim = null) {
        try {
            $where = ["m.fornecedor_id = ?", "m.tipo = 'entrada'"];
            $params = [$fornecedorId];
            
            if ($dataInicio) {
                $where[] = "DATE(m.created_at) >= ?";
                $params[] = $dataInicio;
            }
            
            if ($dataFim) {
                $where[] = "DATE(m.created_at) <= ?";
                $params[] = $dataFim;
            }
            
            $sql = "
                SELECT 
                    SUM(m.valor_total) as total_valor,
                    SUM(m.quantidade) as total_quantidade,
                    COUNT(*) as total_movimentacoes
                FROM estoque_movimentacoes m
                WHERE " . implode(' AND ', $where);
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [
                'total_valor' => 0,
                'total_quantidade' => 0,
                'total_movimentacoes' => 0
            ];
        }
    }
}
?>

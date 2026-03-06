<?php
require_once __DIR__ . '/BaseModel.php';

class PdfPersonalizado extends BaseModel {
    protected $table = 'pdf_personalizado';

    private $validStatuses = ['realizado', 'pagamento_confirmado', 'em_confeccao', 'entregue'];

    public function __construct($db) {
        parent::__construct($db);
    }

    public function criarPedido($data) {
        $now = date('Y-m-d H:i:s');

        $payload = [
            'module_id'          => (int)($data['module_id'] ?? 0),
            'user_id'            => isset($data['user_id']) ? (int)$data['user_id'] : null,
            'nome_solicitante'   => trim($data['nome_solicitante'] ?? ''),
            'descricao_alteracoes' => trim($data['descricao_alteracoes'] ?? ''),
            'anexo1_base64'      => $data['anexo1_base64'] ?? null,
            'anexo1_nome'        => $data['anexo1_nome'] ?? null,
            'anexo2_base64'      => $data['anexo2_base64'] ?? null,
            'anexo2_nome'        => $data['anexo2_nome'] ?? null,
            'anexo3_base64'      => $data['anexo3_base64'] ?? null,
            'anexo3_nome'        => $data['anexo3_nome'] ?? null,
            'status'             => 'pagamento_confirmado',
            'preco_pago'         => (float)($data['preco_pago'] ?? 0),
            'desconto_aplicado'  => (float)($data['desconto_aplicado'] ?? 0),
            'realizado_at'       => $now,
            'pagamento_confirmado_at' => $now,
            'em_confeccao_at'    => null,
            'entregue_at'        => null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ];

        if (empty($payload['nome_solicitante'])) {
            throw new Exception('Nome do solicitante é obrigatório');
        }
        if (empty($payload['descricao_alteracoes'])) {
            throw new Exception('Descrição das alterações é obrigatória');
        }

        foreach ($payload as $k => $v) {
            if ($v === '') $payload[$k] = null;
        }
        $payload['nome_solicitante'] = trim($data['nome_solicitante'] ?? '');
        $payload['descricao_alteracoes'] = trim($data['descricao_alteracoes'] ?? '');

        return parent::create($payload);
    }

    public function listarPedidos($userId = null, $status = null, $limit = 20, $offset = 0, $search = null) {
        $where = [];
        $params = [];

        if ($userId !== null) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        }
        if ($status !== null && in_array($status, $this->validStatuses)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($search) {
            $where[] = '(nome_solicitante LIKE ? OR descricao_alteracoes LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = "SELECT id, module_id, user_id, nome_solicitante, descricao_alteracoes, status,
                         preco_pago, desconto_aplicado,
                         anexo1_nome, anexo2_nome, anexo3_nome,
                         pdf_entrega_nome,
                         realizado_at, pagamento_confirmado_at, em_confeccao_at, entregue_at,
                         created_at, updated_at
                  FROM {$this->table} {$whereSql}
                  ORDER BY id DESC LIMIT ? OFFSET ?";

        $params[] = (int)$limit;
        $params[] = (int)$offset;

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarPedidos($userId = null, $status = null, $search = null) {
        $where = [];
        $params = [];

        if ($userId !== null) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        }
        if ($status !== null && in_array($status, $this->validStatuses)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($search) {
            $where[] = '(nome_solicitante LIKE ? OR descricao_alteracoes LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT COUNT(*) as count FROM {$this->table} {$whereSql}";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['count'] ?? 0);
    }

    public function obterPedido($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function atualizarStatus($id, $status, $extraData = []) {
        if (!in_array($status, $this->validStatuses)) {
            throw new Exception('Status inválido: ' . $status);
        }

        $now = date('Y-m-d H:i:s');
        $sets = ['status = ?', 'updated_at = ?'];
        $params = [$status, $now];

        $timestampCol = $status . '_at';
        $sets[] = "$timestampCol = ?";
        $params[] = $now;

        if (isset($extraData['pdf_entrega_base64'])) {
            $sets[] = 'pdf_entrega_base64 = ?';
            $params[] = $extraData['pdf_entrega_base64'];
        }
        if (isset($extraData['pdf_entrega_nome'])) {
            $sets[] = 'pdf_entrega_nome = ?';
            $params[] = $extraData['pdf_entrega_nome'];
        }

        $params[] = (int)$id;
        $query = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function deletarPedido($id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$id]);
    }

    public function getStats() {
        $query = "SELECT 
            SUM(CASE WHEN status = 'pagamento_confirmado' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'em_confeccao' THEN 1 ELSE 0 END) as aprovados,
            SUM(CASE WHEN status = 'entregue' THEN 1 ELSE 0 END) as finalizados,
            COUNT(*) as total,
            COALESCE(SUM(preco_pago), 0) as total_valor
            FROM {$this->table}";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

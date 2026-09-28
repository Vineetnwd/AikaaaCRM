<?php
namespace Core;

class Invoice {
    private $db;
    private $company_id;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->company_id = Auth::companyId();
    }

    public function all($filters = []) {
        $sql = "SELECT i.*, l.name as client_name 
                FROM invoices i 
                LEFT JOIN leads l ON i.lead_id = l.id 
                WHERE i.company_id = ?";
        $params = [$this->company_id];

        if (isset($filters['start']) && isset($filters['end'])) {
            $sql .= " AND i.invoice_date BETWEEN ? AND ?";
            $params[] = $filters['start'];
            $params[] = $filters['end'];
        }

        $sql .= " ORDER BY i.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data) {
        $allowedFields = [
            'company_id',
            'lead_id',
            'invoice_number',
            'description',
            'gst_number',
            'subtotal',
            'cgst',
            'sgst',
            'igst',
            'is_gst_enabled',
            'total_amount',
            'paid_amount',
            'due_amount',
            'payment_status',
            'invoice_date',
            'due_date',
            'discount',
        ];

        try {
            $tableFields = array_column($this->db->fetchAll("SHOW COLUMNS FROM invoices"), 'Field');
            if (!empty($tableFields)) {
                $allowedFields = array_values(array_intersect($allowedFields, $tableFields));
            }
        } catch (\Throwable $e) {
            // Keep the static allow-list if schema inspection is unavailable.
        }

        $data = array_intersect_key($data, array_flip($allowedFields));
        $hasDueAmount = isset($data['due_amount']);
        $data['company_id'] = $this->company_id;
        $data['subtotal'] = floatval($data['subtotal'] ?? 0);
        $data['cgst'] = floatval($data['cgst'] ?? 0);
        $data['sgst'] = floatval($data['sgst'] ?? 0);
        $data['igst'] = floatval($data['igst'] ?? 0);
        $data['discount'] = floatval($data['discount'] ?? 0);
        $data['is_gst_enabled'] = !empty($data['is_gst_enabled']) ? 1 : 0;
        $data['total_amount'] = floatval($data['total_amount'] ?? 0);
        $data['paid_amount'] = floatval($data['paid_amount'] ?? 0);
        $data['due_amount'] = $hasDueAmount ? floatval($data['due_amount']) : null;
        
        if (empty($data['due_date'])) {
            $data['due_date'] = $data['invoice_date'] ?? date('Y-m-d');
        }
        
        // Auto-calculate due amount if it equals total
        if (!$hasDueAmount) {
            $data['due_amount'] = $data['total_amount'] - $data['paid_amount'];
        }
        
        // Set payment status based on paid amount
        if ($data['paid_amount'] >= $data['total_amount']) {
            $data['payment_status'] = 'paid';
        } elseif ($data['paid_amount'] > 0) {
            $data['payment_status'] = 'partial';
        } else {
            $data['payment_status'] = 'due';
        }

        $data = array_intersect_key($data, array_flip($allowedFields));
        return $this->db->insert('invoices', $data);
    }

    public function getNextInvoiceNumber() {
        $last = $this->db->fetchOne("SELECT invoice_number FROM invoices WHERE company_id = ? ORDER BY id DESC LIMIT 1", [$this->company_id]);
        if (!$last) return "INV-1001";
        
        $clean = preg_replace('/[^0-9]/', '', $last['invoice_number']);
        $num = (int)$clean;
        
        if ($num > 0) {
            return "INV-" . ($num + 1);
        }
        return "INV-1001";
    }
}

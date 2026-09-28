<?php
namespace Core;

class Followup {
    private $db;
    private $company_id;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->company_id = Auth::companyId();
    }

    public function forLead($lead_id) {
        return $this->db->fetchAll("SELECT f.*, u.name as user_name FROM followups f JOIN users u ON f.user_id = u.id WHERE f.lead_id = ? AND f.company_id = ? ORDER BY f.created_at DESC", [$lead_id, $this->company_id]);
    }

    public function create($data) {
        $data['company_id'] = $this->company_id;
        $data['user_id'] = Auth::user()['id'] ?? 1; // Fallback for dev
        return $this->db->insert('followups', $data);
    }
}
?>

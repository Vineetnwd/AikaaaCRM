<?php
namespace Core;

class Requirement {
    private $db;
    private $company_id;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->company_id = Auth::companyId();
    }

    public function all($page = 1, $limit = 15, $search = '') {
        $offset = ($page - 1) * $limit;
        
        $where = "r.company_id = :comp";
        $params = [':comp' => $this->company_id];
        
        if (!empty($search)) {
            $where .= " AND (r.name LIKE :search1 OR r.description LIKE :search2 OR r.req_docs LIKE :search3)";
            $params[':search1'] = "%$search%";
            $params[':search2'] = "%$search%";
            $params[':search3'] = "%$search%";
        }

        $count = $this->db->fetchOne("SELECT COUNT(*) as c FROM requirements r WHERE $where", $params)['c'] ?? 0;
        
        $sql = "SELECT r.*, COUNT(DISTINCT l.mobile) as customer_count 
             FROM requirements r 
             LEFT JOIN lead_requirements lr ON r.id = lr.requirement_id 
             LEFT JOIN leads l ON lr.lead_id = l.id 
             WHERE $where 
             GROUP BY r.id 
             ORDER BY r.name ASC 
             LIMIT $limit OFFSET $offset";
             
        $data = $this->db->fetchAll($sql, $params);
        
        return [
            'data' => $data,
            'total_pages' => max(1, ceil($count / $limit)),
            'total_records' => $count
        ];
    }

    public function find($id) {
        return $this->db->fetchOne(
            "SELECT * FROM requirements WHERE id = ? AND company_id = ?",
            [$id, $this->company_id]
        );
    }

    public function create($data) {
        return $this->db->insert('requirements', [
            'company_id' => $this->company_id,
            'name' => $data['name'],
            'req_docs' => $data['req_docs'] ?? '',
            'description' => $data['description'] ?? '',
            'fee' => $data['fee'] ?? 0
        ]);
    }

    public function update($id, $data) {
        return $this->db->update('requirements', [
            'name' => $data['name'],
            'req_docs' => $data['req_docs'] ?? '',
            'description' => $data['description'] ?? '',
            'fee' => $data['fee'] ?? 0
        ], "id = ? AND company_id = ?", [$id, $this->company_id]);
    }

    public function delete($id) {
        return $this->db->query(
            "DELETE FROM requirements WHERE id = ? AND company_id = ?",
            [$id, $this->company_id]
        );
    }

    public function getLeadRequirements($lead_id) {
        return $this->db->fetchAll(
            "SELECT r.* FROM requirements r 
             JOIN lead_requirements lr ON r.id = lr.requirement_id 
             WHERE lr.lead_id = ?",
            [$lead_id]
        );
    }

    public function getRequirementCustomers($requirement_id) {
        return $this->db->fetchAll(
            "SELECT DISTINCT l.customer_id as id, l.name, l.mobile, l.email 
             FROM leads l 
             JOIN lead_requirements lr ON l.id = lr.lead_id 
             WHERE lr.requirement_id = ? AND l.company_id = ?",
            [$requirement_id, $this->company_id]
        );
    }

    public function syncLeadRequirements($lead_id, $requirement_ids) {
        // Remove existing
        $this->db->query("DELETE FROM lead_requirements WHERE lead_id = ?", [$lead_id]);
        
        // Add new
        if (!empty($requirement_ids)) {
            foreach ($requirement_ids as $req_id) {
                $this->db->insert('lead_requirements', [
                    'lead_id' => $lead_id,
                    'requirement_id' => $req_id
                ]);
            }
        }
    }
}

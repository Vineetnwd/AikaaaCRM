<?php
namespace Core;

class Company {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function all($filters = []) {
        $sql = "SELECT * FROM companies";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " WHERE name LIKE ? OR subdomain LIKE ? OR gst_number LIKE ?";
            $term = '%' . $filters['search'] . '%';
            $params = [$term, $term, $term];
        }

        $sql .= " ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function find($id) {
        return $this->db->fetchOne("SELECT * FROM companies WHERE id = ?", [$id]);
    }

    public function create($data) {
        $trialDays = 7;
        $trialEnds = date('Y-m-d', strtotime("+$trialDays days"));

        return $this->db->insert('companies', [
            'name' => $data['name'],
            'subdomain' => $data['subdomain'] ?? null,
            'gst_number' => $data['gst_number'] ?? null,
            'address' => $data['address'] ?? null,
            'plan' => $data['plan'] ?? 'trial',
            'status' => $data['status'] ?? 'active',
            'trial_ends_at' => $trialEnds,
            'subscription_ends_at' => ($data['plan'] === 'trial' || empty($data['plan'])) ? null : date('Y-m-d', strtotime(($data['plan'] === 'pro' ? '+1 year' : '+1 month')))
        ]);
    }

    public function update($id, $data) {
        $allowedFields = ['name', 'subdomain', 'gst_number', 'address', 'plan', 'status', 'logo_path', 'trial_ends_at', 'subscription_starts_at', 'subscription_ends_at', 'created_at'];
        
        $oldData = $this->find($id);
        
        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                // Formatting dates correctly if they are empty
                if (($field === 'created_at' || $field === 'subscription_starts_at' || $field === 'subscription_ends_at') && empty($data[$field])) {
                    $updateData[$field] = null;
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (empty($updateData)) return false;

        $keys = array_keys($updateData);
        $set = implode(', ', array_map(fn($k) => "$k = :$k", $keys));
        
        $sql = "UPDATE companies SET $set WHERE id = :id";
        $updateData['id'] = $id;
        
        $success = $this->db->query($sql, $updateData);
        
        if ($success && $oldData) {
            $changes = [];
            $trackedFields = ['plan', 'status', 'created_at', 'subscription_starts_at', 'subscription_ends_at'];
            foreach ($trackedFields as $tf) {
                if (array_key_exists($tf, $updateData)) {
                    // Compare values (treating null and empty string equivalently for dates)
                    $oldVal = $oldData[$tf];
                    $newVal = $updateData[$tf];
                    // Strip time component from dates if comparing with date-only input
                    if ($tf === 'created_at' || $tf === 'subscription_starts_at' || $tf === 'subscription_ends_at') {
                        $oldVal = $oldVal ? date('Y-m-d', strtotime($oldVal)) : null;
                        $newVal = $newVal ? date('Y-m-d', strtotime($newVal)) : null;
                    }
                    if ($oldVal != $newVal) {
                        $changes[$tf] = ['old' => $oldVal, 'new' => $newVal];
                    }
                }
            }
            if (!empty($changes)) {
                $this->db->insert('subscription_logs', [
                    'company_id' => $id,
                    'admin_id' => \Core\Auth::userId() ?? 1,
                    'action' => 'Subscription Updated',
                    'changes' => json_encode($changes)
                ]);
            }
        }
        return $success;
    }

    public function delete($id) {
        // Warning: Deleting a company should ideally involve deleting all its data, 
        // but for now we just delete the company record or mark it as suspended.
        return $this->db->query("DELETE FROM companies WHERE id = ?", [$id]);
    }
}

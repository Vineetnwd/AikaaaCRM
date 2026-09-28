<?php
namespace Core;

class Lead
{
    private $db;
    private $company_id;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->company_id = Auth::companyId();

        // Ensure created_by and transfer tracking columns exist
        static $columnChecked = false;
        if (!$columnChecked) {
            try {
                $this->db->query("ALTER TABLE leads ADD COLUMN created_by INT NULL");
            } catch (\Exception $e) {}
            try {
                $this->db->query("ALTER TABLE leads ADD COLUMN is_transferred TINYINT(1) DEFAULT 0");
            } catch (\Exception $e) {}
            try {
                $this->db->query("ALTER TABLE leads ADD COLUMN transferred_at DATETIME NULL");
            } catch (\Exception $e) {}
            try {
                $this->db->query("ALTER TABLE leads ADD COLUMN transferred_by INT NULL");
            } catch (\Exception $e) {}
            $columnChecked = true;
        }
    }

    public function all($filters = [])
    {
        $sql = "SELECT l.*, 
                u.name as assigned_to_name,
                e.name as assigned_employee_name,
                c.name as referral_customer_name,
                reqs.requirement_names,
                invs.invoice_descriptions,
                f.remark as latest_remark,
                f.call_status as latest_call_status,
                f.created_at as latest_interaction_at,
                f.follow_up_date as latest_next_followup_date,
                f.interaction_user_name,
                COALESCE(tf.transfer_count, 0) as transfer_count,
                tf.last_transferred_at,
                tf.last_transfer_remark,
                CASE 
                    WHEN COALESCE(l.is_transferred, 0) = 1 THEN 1 
                    WHEN COALESCE(tf.transfer_count, 0) > 0 THEN 1 
                    ELSE 0 
                END as is_transferred
                FROM leads l
                LEFT JOIN users u ON l.assigned_to = u.id
                LEFT JOIN employees e ON l.assigned_employee_id = e.id
                LEFT JOIN customers c ON l.referral_customer_id = c.id
                LEFT JOIN (
                    SELECT lr.lead_id, GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') as requirement_names
                    FROM lead_requirements lr
                    JOIN requirements r ON lr.requirement_id = r.id
                    GROUP BY lr.lead_id
                ) reqs ON l.id = reqs.lead_id
                LEFT JOIN (
                    SELECT lead_id, GROUP_CONCAT(description SEPARATOR '||') as invoice_descriptions
                    FROM invoices
                    WHERE description IS NOT NULL AND description != ''
                    GROUP BY lead_id
                ) invs ON l.id = invs.lead_id
                LEFT JOIN (
                    SELECT f1.lead_id, f1.remark, f1.call_status, f1.created_at, f1.follow_up_date, u2.name as interaction_user_name
                    FROM lead_followups f1
                    LEFT JOIN users u2 ON f1.user_id = u2.id
                    WHERE f1.id IN (
                        SELECT MAX(id) FROM lead_followups GROUP BY lead_id
                    )
                ) f ON l.id = f.lead_id
                LEFT JOIN (
                    SELECT lead_id, COUNT(*) as transfer_count, MAX(created_at) as last_transferred_at,
                           SUBSTRING_INDEX(GROUP_CONCAT(remark ORDER BY id DESC SEPARATOR '|||'), '|||', 1) as last_transfer_remark
                    FROM lead_followups
                    WHERE remark LIKE 'Lead Transferred%'
                    GROUP BY lead_id
                ) tf ON l.id = tf.lead_id
                WHERE l.company_id = ?";

        $params = [$this->company_id];
        $search = $filters['search'] ?? null;

        if ($search) {
            $sql .= " AND (l.name LIKE ? OR l.mobile LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        } elseif (isset($filters['all']) && $filters['all'] == 1) {
            // Skip date filtering to show all leads
        } else {
            if (!empty($filters['month'])) {
                $sql .= " AND l.month = ?";
                $params[] = (string) $filters['month'];
            }
            if (!empty($filters['year'])) {
                $sql .= " AND l.year = ?";
                $params[] = $filters['year'];
            }
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND l.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $sql .= " AND l.category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['source']) && $filters['source'] !== 'all') {
            $sql .= " AND l.source = ?";
            $params[] = $filters['source'];
        }
        if (!empty($filters['transfer'])) {
            if ($filters['transfer'] === 'transferred') {
                $sql .= " AND (COALESCE(l.is_transferred, 0) = 1 OR COALESCE(tf.transfer_count, 0) > 0)";
            } elseif ($filters['transfer'] === 'not_transferred') {
                $sql .= " AND (COALESCE(l.is_transferred, 0) = 0 AND COALESCE(tf.transfer_count, 0) = 0)";
            }
        }
        if (!empty($filters['user_id'])) {
            $sql .= " AND (l.assigned_to = ? OR l.assigned_employee_id = ?)";
            $params[] = $filters['user_id'];
            $params[] = $filters['employee_id'] ?? $filters['user_id'];
        }

        $sql .= " ORDER BY l.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function find($id)
    {
        return $this->db->fetchOne(
            "SELECT l.*,
                    u.name as assigned_to_name,
                    e.name as assigned_employee_name,
                    COALESCE(tf.transfer_count, 0) as transfer_count,
                    tf.last_transferred_at,
                    tf.last_transfer_remark,
                    CASE 
                        WHEN COALESCE(l.is_transferred, 0) = 1 THEN 1 
                        WHEN COALESCE(tf.transfer_count, 0) > 0 THEN 1 
                        ELSE 0 
                    END as is_transferred
             FROM leads l
             LEFT JOIN users u ON l.assigned_to = u.id
             LEFT JOIN employees e ON l.assigned_employee_id = e.id
             LEFT JOIN (
                 SELECT lead_id, COUNT(*) as transfer_count, MAX(created_at) as last_transferred_at,
                        SUBSTRING_INDEX(GROUP_CONCAT(remark ORDER BY id DESC SEPARATOR '|||'), '|||', 1) as last_transfer_remark
                 FROM lead_followups
                 WHERE remark LIKE 'Lead Transferred%'
                 GROUP BY lead_id
             ) tf ON l.id = tf.lead_id
             WHERE l.id = ? AND l.company_id = ?",
            [$id, $this->company_id]
        );
    }

    public function findByMobile($mobile, $excludeId = null, $requirement = null, $taskStatus = null)
    {
        $sql = "SELECT * FROM leads WHERE mobile = ? AND company_id = ?";
        $params = [$mobile, $this->company_id];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        if ($requirement !== null) {
            $sql .= " AND requirement = ?";
            $params[] = $requirement;
        }

        if ($taskStatus !== null) {
            $sql .= " AND task_status = ?";
            $params[] = $taskStatus;
        }

        return $this->db->fetchOne($sql, $params);
    }

    public function create($data)
    {
        $data['company_id'] = $this->company_id;
        $requirement_ids = $data['requirement_ids'] ?? [];
        
        // Allowed fields in the leads table
        $allowedFields = [
            'company_id', 'customer_id', 'name', 'mobile', 'email', 'requirement', 
            'category', 'source', 'assigned_to', 'assigned_employee_id', 'created_by', 
            'status', 'month', 'year', 'referral_person', 'referral_customer_id', 'referral_custom_name',
            'follow_up_date', 'commission_percent', 'commission_amount', 
            'other_service_name', 'custom_services', 'deal_value', 'closed_at', 
            'task_status', 'task_completed_at', 'expected_delivery_date', 'address', 
            'is_whatsapp', 'is_call', 'remark', 'is_transferred', 'transferred_at', 'transferred_by'
        ];

        // Filter data to only include allowed fields
        $filteredData = array_intersect_key($data, array_flip($allowedFields));

        // Default month/year if not set
        if (empty($filteredData['month']))
            $filteredData['month'] = date('n');
        if (empty($filteredData['year']))
            $filteredData['year'] = date('Y');

        $category = strtolower($filteredData['category'] ?? '');
        
        // Auto-update status based on category
        if ($category === 'red') {
            $filteredData['status'] = 'lost';
        } elseif ($category === 'green') {
            $filteredData['status'] = 'won';
        } elseif ($category === 'yellow') {
            $filteredData['status'] = 'in_progress';
        }

        $status = strtolower($filteredData['status'] ?? '');
        $shouldCreateCustomer = in_array($category, ['green', 'yellow']) || $status === 'won';

        // Automatically Create or Link Customer
        if (!empty($filteredData['mobile']) && $shouldCreateCustomer) {
            $customer = $this->db->fetchOne(
                "SELECT id FROM customers WHERE company_id = ? AND mobile = ?",
                [$this->company_id, $filteredData['mobile']]
            );

            if (!$customer) {
                $customerId = $this->db->insert('customers', [
                    'company_id' => $this->company_id,
                    'name' => $filteredData['name'] ?? 'NO NAME',
                    'mobile' => $filteredData['mobile'],
                    'email' => $filteredData['email'] ?? null,
                    'address' => $filteredData['address'] ?? null,
                ]);
            } else {
                $customerId = $customer['id'];
            }
            $filteredData['customer_id'] = $customerId;
        }

        $lead_id = $this->db->insert('leads', $filteredData);

        if (!empty($requirement_ids)) {
            foreach ($requirement_ids as $req_id) {
                $this->db->insert('lead_requirements', [
                    'lead_id' => $lead_id,
                    'requirement_id' => $req_id
                ]);
            }
        }

        return $lead_id;
    }

    public function update($id, $data)
    {
        $requirement_ids = $data['requirement_ids'] ?? null;
        
        // Allowed fields in the leads table
        $allowedFields = [
            'customer_id', 'name', 'mobile', 'email', 'requirement', 
            'category', 'source', 'assigned_to', 'assigned_employee_id', 'created_by', 
            'status', 'month', 'year', 'referral_person', 'referral_customer_id', 'referral_custom_name',
            'follow_up_date', 'commission_percent', 'commission_amount', 
            'other_service_name', 'custom_services', 'deal_value', 'closed_at', 
            'task_status', 'task_completed_at', 'expected_delivery_date', 'address', 
            'is_whatsapp', 'is_call', 'remark', 'is_transferred', 'transferred_at', 'transferred_by'
        ];

        // Filter data to only include allowed fields
        $filteredData = array_intersect_key($data, array_flip($allowedFields));

        $existingLead = $this->db->fetchOne("SELECT name, mobile, email, address, category, status FROM leads WHERE id = ?", [$id]);
        
        $category = strtolower($filteredData['category'] ?? $existingLead['category'] ?? '');
        
        // Auto-update status based on category change
        if (isset($filteredData['category']) && $filteredData['category'] !== $existingLead['category']) {
            if ($category === 'red') {
                $filteredData['status'] = 'lost';
            } elseif ($category === 'green') {
                $filteredData['status'] = 'won';
            } elseif ($category === 'yellow') {
                $filteredData['status'] = 'in_progress';
            }
        }

        $status = strtolower($filteredData['status'] ?? $existingLead['status'] ?? '');
        $shouldCreateCustomer = in_array($category, ['green', 'yellow']) || $status === 'won';
        
        $targetMobile = $filteredData['mobile'] ?? $existingLead['mobile'] ?? '';

        // Automatically Create or Link Customer on Update if qualified
        if (!empty($targetMobile) && $shouldCreateCustomer) {
            $customer = $this->db->fetchOne(
                "SELECT id FROM customers WHERE company_id = ? AND mobile = ?",
                [$this->company_id, $targetMobile]
            );

            if (!$customer) {
                $customerId = $this->db->insert('customers', [
                    'company_id' => $this->company_id,
                    'name' => $filteredData['name'] ?? ($existingLead['name'] ?? 'NO NAME'),
                    'mobile' => $targetMobile,
                    'email' => $filteredData['email'] ?? ($existingLead['email'] ?? null),
                    'address' => $filteredData['address'] ?? ($existingLead['address'] ?? null),
                ]);
            } else {
                $customerId = $customer['id'];
                
                // Optional: Update customer name/email if they are provided during lead update
                $updateData = [];
                if (!empty($filteredData['name'])) $updateData['name'] = $filteredData['name'];
                if (!empty($filteredData['email'])) $updateData['email'] = $filteredData['email'];
                if (!empty($filteredData['address'])) $updateData['address'] = $filteredData['address'];
                
                if (!empty($updateData)) {
                    $this->db->update('customers', $updateData, "id = ?", [$customerId]);
                }
            }
            $filteredData['customer_id'] = $customerId;
        }

        if (!empty($filteredData)) {
            $this->db->update('leads', $filteredData, "id = ? AND company_id = ?", [$id, $this->company_id]);
            
            // If a remark is provided, also create a follow-up entry for history
            if (!empty($filteredData['remark'])) {
                $this->db->insert('lead_followups', [
                    'lead_id' => $id,
                    'company_id' => $this->company_id,
                    'user_id' => Auth::userId(),
                    'remark' => $filteredData['remark'],
                    'follow_up_date' => $filteredData['follow_up_date'] ?? date('Y-m-d'),
                    'call_status' => 'connected',
                    'status' => 'completed'
                ]);
            }
        }

        if ($requirement_ids !== null) {
            $this->db->query("DELETE FROM lead_requirements WHERE lead_id = ?", [$id]);
            foreach ($requirement_ids as $req_id) {
                $this->db->insert('lead_requirements', [
                    'lead_id' => $id,
                    'requirement_id' => $req_id
                ]);
            }
        }

        return true;
    }

    public function delete($id)
    {
        // First delete lead requirements
        $this->db->query("DELETE FROM lead_requirements WHERE lead_id = ?", [$id]);
        return $this->db->query(
            "DELETE FROM leads WHERE id = ? AND company_id = ?",
            [$id, $this->company_id]
        );
    }

    public function canAccess($id)
    {
        $lead = $this->find($id);
        if (!$lead)
            return false;

        if (Auth::isAdmin() || Auth::isManager())
            return true;

        $employeeId = Auth::employeeId();
        return $lead['assigned_to'] == Auth::userId() || $lead['assigned_employee_id'] == $employeeId;
    }

    public function transfer($leadId, $targetEmployeeId, $remark = '')
    {
        $lead = $this->find($leadId);
        if (!$lead) {
            throw new \Exception("Lead not found");
        }

        if (!Auth::isAdmin() && !Auth::isManager()) {
            if (!$this->canAccess($leadId)) {
                throw new \Exception("Access denied: You can only transfer leads assigned to or created by you.");
            }
        }

        // Verify target employee belongs to same company
        $targetEmp = $this->db->fetchOne(
            "SELECT id, name, email FROM employees WHERE id = ? AND company_id = ?",
            [$targetEmployeeId, $this->company_id]
        );

        if (!$targetEmp) {
            throw new \Exception("Target employee not found in your organization.");
        }

        // Find linked user for target employee if any
        $targetUser = $this->db->fetchOne(
            "SELECT id FROM users WHERE company_id = ? AND (employee_id = ? OR emp_id = ? OR (email IS NOT NULL AND email != '' AND email = ?))",
            [$this->company_id, $targetEmployeeId, $targetEmployeeId, $targetEmp['email'] ?? '']
        );

        $updateData = [
            'assigned_employee_id' => $targetEmployeeId,
            'is_transferred' => 1,
            'transferred_at' => date('Y-m-d H:i:s'),
            'transferred_by' => Auth::userId(),
        ];
        if ($targetUser) {
            $updateData['assigned_to'] = $targetUser['id'];
        }

        $this->db->update('leads', $updateData, "id = ? AND company_id = ?", [$leadId, $this->company_id]);

        // Log transfer in lead_followups
        $actorName = Auth::userName();
        $transferRemark = "Lead Transferred to " . $targetEmp['name'] . " by " . $actorName;
        if (!empty($remark)) {
            $transferRemark .= " | Note: " . trim($remark);
        }

        $this->db->insert('lead_followups', [
            'lead_id' => $leadId,
            'company_id' => $this->company_id,
            'user_id' => Auth::userId(),
            'remark' => $transferRemark,
            'follow_up_date' => date('Y-m-d'),
            'follow_up_time' => date('H:i:s'),
            'call_status' => 'connected',
            'status' => 'completed'
        ]);

        return true;
    }
}
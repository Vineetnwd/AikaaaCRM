<?php
namespace Core;

class Company {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensureSMTPColumnsExist();
    }

    private function ensureSMTPColumnsExist() {
        try {
            $columns = $this->db->fetchAll("SHOW COLUMNS FROM companies");
            $columnNames = array_column($columns, 'Field');
            if (!in_array('smtp_host', $columnNames)) {
                $this->db->query("ALTER TABLE companies ADD COLUMN smtp_host VARCHAR(255) DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN smtp_port INT DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN smtp_email VARCHAR(255) DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN smtp_password VARCHAR(255) DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN smtp_secure VARCHAR(50) DEFAULT NULL");
            }
            if (!in_array('signature_path', $columnNames)) {
                $this->db->query("ALTER TABLE companies ADD COLUMN signature_path VARCHAR(255) DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN qr_code_path VARCHAR(255) DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN invoice_terms TEXT DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN quotation_terms TEXT DEFAULT NULL");
            }
            if (!in_array('bank_details', $columnNames)) {
                $this->db->query("ALTER TABLE companies ADD COLUMN bank_details TEXT DEFAULT NULL");
            }
            if (!in_array('whatsapp_access_token', $columnNames)) {
                $this->db->query("ALTER TABLE companies ADD COLUMN whatsapp_access_token TEXT DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN whatsapp_phone_number_id VARCHAR(255) DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN whatsapp_business_account_id VARCHAR(255) DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN whatsapp_number VARCHAR(255) DEFAULT NULL");
            }
            if (!in_array('whatsapp_default_template', $columnNames)) {
                $this->db->query("ALTER TABLE companies ADD COLUMN whatsapp_default_template VARCHAR(255) DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN whatsapp_default_mapping TEXT DEFAULT NULL");
                $this->db->query("ALTER TABLE companies ADD COLUMN whatsapp_header_image VARCHAR(512) DEFAULT NULL");
            }
        } catch (\Throwable $e) {
            // Silence error
        }
    }

    public function all($filters = []) {
        $today = date('Y-m-d');
        $todayStart = date('Y-m-d 00:00:00');
        $monthStart = date('Y-m-01 00:00:00');

        $sql = "SELECT c.*,
            CASE 
                WHEN c.plan = 'trial' THEN 
                    COALESCE(c.trial_ends_at, c.subscription_ends_at, DATE(DATE_ADD(c.created_at, INTERVAL 7 DAY)))
                WHEN c.plan != 'trial' AND c.subscription_ends_at IS NOT NULL AND c.subscription_ends_at > '2000-01-01' 
                     AND (c.trial_ends_at IS NULL OR c.subscription_ends_at > c.trial_ends_at) THEN 
                    c.subscription_ends_at
                WHEN c.plan = 'pro' THEN 
                    DATE(DATE_ADD(COALESCE(c.subscription_starts_at, c.created_at), INTERVAL 1 YEAR))
                WHEN c.plan = 'basic' THEN 
                    DATE(DATE_ADD(COALESCE(c.subscription_starts_at, c.created_at), INTERVAL 1 MONTH))
                ELSE 
                    COALESCE(c.subscription_ends_at, c.trial_ends_at)
            END AS effective_expiry_date,
            (SELECT COUNT(*) FROM leads WHERE company_id = c.id AND created_at >= ?) AS today_leads,
            (SELECT COUNT(*) FROM lead_followups WHERE company_id = c.id AND created_at >= ?) AS today_followups,
            (SELECT COUNT(*) FROM tasks WHERE company_id = c.id AND created_at >= ?) AS today_tasks,
            (SELECT COUNT(*) FROM quotations WHERE company_id = c.id AND created_at >= ?) AS today_quotations,
            (SELECT COUNT(*) FROM invoices WHERE company_id = c.id AND created_at >= ?) AS today_invoices,
            (SELECT COUNT(*) FROM leads WHERE company_id = c.id AND created_at >= ?) AS month_leads,
            (SELECT COUNT(*) FROM lead_followups WHERE company_id = c.id AND created_at >= ?) AS month_followups,
            (SELECT COUNT(*) FROM tasks WHERE company_id = c.id AND created_at >= ?) AS month_tasks,
            (SELECT COUNT(*) FROM quotations WHERE company_id = c.id AND created_at >= ?) AS month_quotations,
            (SELECT COUNT(*) FROM invoices WHERE company_id = c.id AND created_at >= ?) AS month_invoices
        FROM companies c";

        $params = [
            $todayStart, $todayStart, $todayStart, $todayStart, $todayStart,
            $monthStart, $monthStart, $monthStart, $monthStart, $monthStart
        ];

        if (!empty($filters['search'])) {
            $sql .= " WHERE c.name LIKE ? OR c.subdomain LIKE ? OR c.gst_number LIKE ?";
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY c.created_at DESC";
        $companies = $this->db->fetchAll($sql, $params);

        foreach ($companies as &$c) {
            $c['today_leads'] = (int)($c['today_leads'] ?? 0);
            $c['today_followups'] = (int)($c['today_followups'] ?? 0);
            $c['today_tasks'] = (int)($c['today_tasks'] ?? 0);
            $c['today_quotations'] = (int)($c['today_quotations'] ?? 0);
            $c['today_invoices'] = (int)($c['today_invoices'] ?? 0);

            $c['month_leads'] = (int)($c['month_leads'] ?? 0);
            $c['month_followups'] = (int)($c['month_followups'] ?? 0);
            $c['month_tasks'] = (int)($c['month_tasks'] ?? 0);
            $c['month_quotations'] = (int)($c['month_quotations'] ?? 0);
            $c['month_invoices'] = (int)($c['month_invoices'] ?? 0);

            $c['today_activities'] = $c['today_leads'] + $c['today_followups'] + $c['today_tasks'] + $c['today_quotations'] + $c['today_invoices'];
            $c['month_activities'] = $c['month_leads'] + $c['month_followups'] + $c['month_tasks'] + $c['month_quotations'] + $c['month_invoices'];

            $expiry = $c['effective_expiry_date'];
            $isExpired = false;
            $daysLeft = null;
            if (!empty($expiry)) {
                $diff = (int)floor((strtotime($expiry) - strtotime($today)) / 86400);
                $daysLeft = $diff;
                $isExpired = ($diff < 0);
            }

            $c['expiry_date'] = $expiry;
            $c['is_expired'] = $isExpired;
            $c['days_left'] = $daysLeft;
        }

        return $companies;
    }

    public function find($id) {
        $today = date('Y-m-d');
        $todayStart = date('Y-m-d 00:00:00');
        $monthStart = date('Y-m-01 00:00:00');

        $sql = "SELECT c.*,
            CASE 
                WHEN c.plan = 'trial' THEN 
                    COALESCE(c.trial_ends_at, c.subscription_ends_at, DATE(DATE_ADD(c.created_at, INTERVAL 7 DAY)))
                WHEN c.plan != 'trial' AND c.subscription_ends_at IS NOT NULL AND c.subscription_ends_at > '2000-01-01' 
                     AND (c.trial_ends_at IS NULL OR c.subscription_ends_at > c.trial_ends_at) THEN 
                    c.subscription_ends_at
                WHEN c.plan = 'pro' THEN 
                    DATE(DATE_ADD(COALESCE(c.subscription_starts_at, c.created_at), INTERVAL 1 YEAR))
                WHEN c.plan = 'basic' THEN 
                    DATE(DATE_ADD(COALESCE(c.subscription_starts_at, c.created_at), INTERVAL 1 MONTH))
                ELSE 
                    COALESCE(c.subscription_ends_at, c.trial_ends_at)
            END AS effective_expiry_date,
            (SELECT COUNT(*) FROM leads WHERE company_id = c.id AND created_at >= ?) AS today_leads,
            (SELECT COUNT(*) FROM lead_followups WHERE company_id = c.id AND created_at >= ?) AS today_followups,
            (SELECT COUNT(*) FROM tasks WHERE company_id = c.id AND created_at >= ?) AS today_tasks,
            (SELECT COUNT(*) FROM quotations WHERE company_id = c.id AND created_at >= ?) AS today_quotations,
            (SELECT COUNT(*) FROM invoices WHERE company_id = c.id AND created_at >= ?) AS today_invoices,
            (SELECT COUNT(*) FROM leads WHERE company_id = c.id AND created_at >= ?) AS month_leads,
            (SELECT COUNT(*) FROM lead_followups WHERE company_id = c.id AND created_at >= ?) AS month_followups,
            (SELECT COUNT(*) FROM tasks WHERE company_id = c.id AND created_at >= ?) AS month_tasks,
            (SELECT COUNT(*) FROM quotations WHERE company_id = c.id AND created_at >= ?) AS month_quotations,
            (SELECT COUNT(*) FROM invoices WHERE company_id = c.id AND created_at >= ?) AS month_invoices
        FROM companies c WHERE c.id = ?";

        $c = $this->db->fetchOne($sql, [
            $todayStart, $todayStart, $todayStart, $todayStart, $todayStart,
            $monthStart, $monthStart, $monthStart, $monthStart, $monthStart,
            $id
        ]);

        if ($c) {
            $c['today_leads'] = (int)($c['today_leads'] ?? 0);
            $c['today_followups'] = (int)($c['today_followups'] ?? 0);
            $c['today_tasks'] = (int)($c['today_tasks'] ?? 0);
            $c['today_quotations'] = (int)($c['today_quotations'] ?? 0);
            $c['today_invoices'] = (int)($c['today_invoices'] ?? 0);

            $c['month_leads'] = (int)($c['month_leads'] ?? 0);
            $c['month_followups'] = (int)($c['month_followups'] ?? 0);
            $c['month_tasks'] = (int)($c['month_tasks'] ?? 0);
            $c['month_quotations'] = (int)($c['month_quotations'] ?? 0);
            $c['month_invoices'] = (int)($c['month_invoices'] ?? 0);

            $c['today_activities'] = $c['today_leads'] + $c['today_followups'] + $c['today_tasks'] + $c['today_quotations'] + $c['today_invoices'];
            $c['month_activities'] = $c['month_leads'] + $c['month_followups'] + $c['month_tasks'] + $c['month_quotations'] + $c['month_invoices'];

            $expiry = $c['effective_expiry_date'];
            $isExpired = false;
            $daysLeft = null;
            if (!empty($expiry)) {
                $diff = (int)floor((strtotime($expiry) - strtotime($today)) / 86400);
                $daysLeft = $diff;
                $isExpired = ($diff < 0);
            }

            $c['expiry_date'] = $expiry;
            $c['is_expired'] = $isExpired;
            $c['days_left'] = $daysLeft;
        }

        return $c;
    }

    public function getLogs($companyId) {
        return $this->db->fetchAll("
            SELECT l.*, u.name as admin_name 
            FROM subscription_logs l 
            LEFT JOIN users u ON l.admin_id = u.id 
            WHERE l.company_id = ? 
            ORDER BY l.created_at DESC
        ", [$companyId]);
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
        $allowedFields = ['name', 'subdomain', 'gst_number', 'address', 'plan', 'status', 'logo_path', 'signature_path', 'qr_code_path', 'invoice_terms', 'quotation_terms', 'bank_details', 'trial_ends_at', 'subscription_starts_at', 'subscription_ends_at', 'created_at', 'smtp_host', 'smtp_port', 'smtp_email', 'smtp_password', 'smtp_secure', 'whatsapp_access_token', 'whatsapp_phone_number_id', 'whatsapp_business_account_id', 'whatsapp_number', 'whatsapp_default_template', 'whatsapp_default_mapping', 'whatsapp_header_image'];
        
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
        $plan = $data['plan'] ?? ($oldData['plan'] ?? 'trial');
        if ($plan === 'trial') {
            if (array_key_exists('subscription_ends_at', $updateData)) {
                $updateData['trial_ends_at'] = $updateData['subscription_ends_at'];
            }
        } else {
            $subEnd = $updateData['subscription_ends_at'] ?? $oldData['subscription_ends_at'] ?? null;
            $trialEnd = $updateData['trial_ends_at'] ?? $oldData['trial_ends_at'] ?? null;
            if (empty($subEnd) || ($trialEnd && $subEnd === $trialEnd)) {
                $start = $updateData['subscription_starts_at'] ?? $oldData['subscription_starts_at'] ?? $updateData['created_at'] ?? $oldData['created_at'] ?? date('Y-m-d');
                $interval = ($plan === 'pro') ? '+1 year' : '+1 month';
                $updateData['subscription_ends_at'] = date('Y-m-d', strtotime($interval, strtotime($start)));
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

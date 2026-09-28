<?php
namespace Core;

class Employee
{
    private $db;
    private $company_id;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->company_id = Auth::companyId();
        $this->ensureBankingColumnsExist();
        $this->ensureDocumentsTableExist();
    }

    private function ensureDocumentsTableExist()
    {
        try {
            $conn = $this->db->getConnection();
            $conn->exec("
                CREATE TABLE IF NOT EXISTS employee_documents (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    company_id  INT NOT NULL,
                    doc_name    VARCHAR(255) NOT NULL,
                    file_path   VARCHAR(500) NOT NULL,
                    file_type   VARCHAR(20) NOT NULL,
                    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (\Throwable $e) {
            // Silence error
        }
    }

    public function getDocuments($empId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM employee_documents WHERE employee_id = ? AND company_id = ? ORDER BY created_at DESC",
            [$empId, $this->company_id]
        );
    }

    public function deleteDocument($docId)
    {
        $doc = $this->db->fetchOne(
            "SELECT * FROM employee_documents WHERE id = ? AND company_id = ?",
            [$docId, $this->company_id]
        );
        if ($doc) {
            $fullPath = __DIR__ . '/../public/' . $doc['file_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            $this->db->query(
                "DELETE FROM employee_documents WHERE id = ? AND company_id = ?",
                [$docId, $this->company_id]
            );
        }
        return true;
    }

    private function ensureBankingColumnsExist()
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->query("SHOW COLUMNS FROM employees");
            $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (!in_array('bank_name', $columns)) {
                $conn->exec("ALTER TABLE employees ADD COLUMN bank_name VARCHAR(255) DEFAULT NULL");
                $conn->exec("ALTER TABLE employees ADD COLUMN account_holder_name VARCHAR(255) DEFAULT NULL");
                $conn->exec("ALTER TABLE employees ADD COLUMN account_number VARCHAR(100) DEFAULT NULL");
                $conn->exec("ALTER TABLE employees ADD COLUMN ifsc_code VARCHAR(50) DEFAULT NULL");
                $conn->exec("ALTER TABLE employees ADD COLUMN upi_id VARCHAR(100) DEFAULT NULL");
            }
        } catch (\Throwable $e) {
            // Silence error
        }
    }

    public function all($filters = [])
    {
        $today = date('Y-m-d');

        $baseSql = "FROM employees e WHERE e.company_id = ?";
        $params = [$this->company_id];

        if (!empty($filters['department'])) {
            $baseSql .= " AND e.department = ?";
            $params[] = $filters['department'];
        }
        if (!empty($filters['status'])) {
            $baseSql .= " AND e.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $baseSql .= " AND (e.name LIKE ? OR e.email LIKE ? OR e.mobile LIKE ? OR e.employee_id LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $selectCols = "SELECT e.*,
                    (SELECT COUNT(*) FROM leads l WHERE l.assigned_employee_id = e.id AND DATE(l.updated_at) = ?) AS today_leads_updated,
                    (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = e.id AND DATE(t.created_at) = ?) AS today_tasks_updated,
                    (SELECT COUNT(*) FROM leads l WHERE l.assigned_employee_id = e.id AND l.follow_up_date = ?) AS today_leads_target,
                    (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = e.id AND t.due_date = ?) AS today_tasks_target,
                    (SELECT GROUP_CONCAT(CONCAT(IFNULL(name, ''), '||', IFNULL(mobile, ''), '||', IFNULL((SELECT GROUP_CONCAT(r.name SEPARATOR ', ') FROM requirements r JOIN lead_requirements lr ON r.id = lr.requirement_id WHERE lr.lead_id = l.id), '')) SEPARATOR ';;') FROM leads l WHERE l.assigned_employee_id = e.id AND DATE(l.updated_at) = ?) AS today_lead_details,
                    (SELECT GROUP_CONCAT(title SEPARATOR ';;') FROM tasks t WHERE t.assigned_to = e.id AND DATE(t.created_at) = ?) AS today_task_details,
                    (SELECT GROUP_CONCAT(CONCAT(IFNULL(name, ''), '||', IFNULL(mobile, ''), '||', IFNULL((SELECT GROUP_CONCAT(r.name SEPARATOR ', ') FROM requirements r JOIN lead_requirements lr ON r.id = lr.requirement_id WHERE lr.lead_id = l.id), '')) SEPARATOR ';;') FROM leads l WHERE l.assigned_employee_id = e.id AND l.follow_up_date = ?) AS today_lead_target_details,
                    (SELECT GROUP_CONCAT(title SEPARATOR ';;') FROM tasks t WHERE t.assigned_to = e.id AND DATE(t.created_at) = ?) AS today_task_assigned_details,
                    (SELECT GROUP_CONCAT(title SEPARATOR ';;') FROM tasks t WHERE t.assigned_to = e.id AND t.due_date = ?) AS today_task_delivery_details ";

        $selectParams = [$today, $today, $today, $today, $today, $today, $today, $today, $today];
        $mergedParams = array_merge($selectParams, $params);

        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
            $limit_val = $filters['limit'] ?? '15';
            $limit = ($limit_val === 'all') ? 1000000 : max(1, (int)$limit_val);
            $offset = ($page - 1) * $limit;
            
            $countSql = "SELECT COUNT(*) as total " . $baseSql;
            $total = $this->db->fetchOne($countSql, $params)['total'] ?? 0;
            $total_pages = max(1, ceil($total / $limit));
            
            $sql = $selectCols . $baseSql . " ORDER BY e.created_at DESC LIMIT $limit OFFSET $offset";
            $data = $this->db->fetchAll($sql, $mergedParams);
            
            return [
                'data' => $data,
                'total_records' => $total,
                'total_pages' => $total_pages,
                'current_page' => $page
            ];
        }

        $sql = $selectCols . $baseSql . " ORDER BY e.created_at DESC";
        return $this->db->fetchAll($sql, $mergedParams);
    }

    public function find($id)
    {
        return $this->db->fetchOne(
            "SELECT e.*,
                    COUNT(l.id)                    AS total_leads,
                    SUM(l.status = 'won')           AS won_leads,
                    SUM(l.deal_value)               AS total_deal_value
             FROM employees e
             LEFT JOIN leads l ON l.assigned_employee_id = e.id AND l.company_id = e.company_id
             WHERE e.id = ? AND e.company_id = ?
             GROUP BY e.id",
            [$id, $this->company_id]
        );
    }

    public function create($data)
    {
        $userPassword = $data['user_password'] ?? '';
        $userRole = $data['user_role'] ?? 'executive';
        unset($data['user_password'], $data['user_role']);

        $validRoles = ['executive', 'manager', 'admin'];
        if (!in_array($userRole, $validRoles, true)) {
            $userRole = 'executive';
        }

        $data['company_id'] = $this->company_id;
        $data['created_at'] = date('Y-m-d H:i:s');

        $conn = $this->db->getConnection();

        try {
            $conn->beginTransaction();

            $empId = $this->db->insert('employees', $data);
            $userEmail = !empty($data['email']) ? $data['email'] : 'emp_' . $empId . '@aikaacrm.com';
            $userData = [
                'company_id' => $this->company_id,
                'name' => $data['name'],
                'email' => $userEmail,
                'mobile' => $data['mobile'] ?? null,
                'type' => $data['type'] ?? 'permanent',
                'password' => password_hash($userPassword, PASSWORD_DEFAULT),
                'role' => $userRole,
                'status' => 'active',
                'photo' => $data['photo'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $hasEmployeeId = $this->db->fetchOne("SHOW COLUMNS FROM users LIKE 'employee_id'");
            if ($hasEmployeeId) {
                $userData['employee_id'] = $empId;
            }

            $hasEmpId = $this->db->fetchOne("SHOW COLUMNS FROM users LIKE 'emp_id'");
            if ($hasEmpId) {
                $userData['emp_id'] = $empId;
            }

            $this->db->insert('users', $userData);

            $conn->commit();
            return $empId;
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            throw $e;
        }
    }

    public function update($id, $data)
    {
        unset($data['user_password'], $data['user_role']);

        $conn = $this->db->getConnection();
        try {
            $conn->beginTransaction();

            $keys = array_keys($data);
            $set = implode(', ', array_map(fn($k) => "$k = :$k", $keys));

            $sql = "UPDATE employees SET $set WHERE id = :id AND company_id = :company_id";
            $data['id'] = $id;
            $data['company_id'] = $this->company_id;

            $this->db->query($sql, $data);

            // Sync with User table
            $userData = [];
            if (isset($data['name']))
                $userData['name'] = $data['name'];
            if (isset($data['email']))
                $userData['email'] = $data['email'];
            if (isset($data['mobile']))
                $userData['mobile'] = $data['mobile'];
            if (isset($data['type']))
                $userData['type'] = $data['type'];
            if (isset($data['photo']))
                $userData['photo'] = $data['photo'];
            if (isset($data['status'])) {
                $userData['status'] = ($data['status'] === 'active' || $data['status'] === 'on_leave') ? 'active' : 'inactive';
            }

            if (!empty($userData)) {
                $userKeys = array_keys($userData);
                $userSet = implode(', ', array_map(fn($k) => "$k = :$k", $userKeys));
                $userSql = "UPDATE users SET $userSet WHERE (employee_id = :emp_id_sync1 OR emp_id = :emp_id_sync2) AND company_id = :company_id";
                $userData['emp_id_sync1'] = $id;
                $userData['emp_id_sync2'] = $id;
                $userData['company_id'] = $this->company_id;
                $this->db->query($userSql, $userData);
            }

            $conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($conn->inTransaction())
                $conn->rollBack();
            throw $e;
        }
    }

    public function delete($id)
    {
        $conn = $this->db->getConnection();
        try {
            $conn->beginTransaction();

            $user = $this->db->fetchOne("SELECT id FROM users WHERE (employee_id = ? OR emp_id = ?) AND company_id = ?", [$id, $id, $this->company_id]);
            $userId = $user ? $user['id'] : null;

            // Nullify assignments to avoid constraint violations
            $this->db->query("UPDATE leads SET assigned_employee_id = NULL WHERE assigned_employee_id = ? AND company_id = ?", [$id, $this->company_id]);
            if ($userId) {
                $this->db->query("UPDATE leads SET assigned_to = NULL WHERE assigned_to = ? AND company_id = ?", [$userId, $this->company_id]);
            }
            $this->db->query("UPDATE tasks SET assigned_to = NULL WHERE assigned_to = ? AND company_id = ?", [$id, $this->company_id]);

            // Clean up earnings history if they are being hard deleted
            $this->db->query("DELETE FROM employee_commission_earnings WHERE employee_id = ? AND company_id = ?", [$id, $this->company_id]);

            // Delete login account
            $this->db->query("DELETE FROM users WHERE (employee_id = ? OR emp_id = ?) AND company_id = ?", [$id, $id, $this->company_id]);

            // Finally, delete the employee
            $this->db->query("DELETE FROM employees WHERE id = ? AND company_id = ?", [$id, $this->company_id]);

            $conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw new \Exception("Could not delete employee: " . $e->getMessage());
        }
    }

    public function departments()
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT department FROM employees WHERE company_id = ? AND department IS NOT NULL AND department != '' ORDER BY department",
            [$this->company_id]
        );
    }

    public function stats()
    {
        return $this->db->fetchOne(
            "SELECT
                COUNT(*)                                          AS total,
                SUM(CASE WHEN status = 'active'   THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) AS on_leave,
                SUM(total_commission_earned)                      AS total_commission
             FROM employees WHERE company_id = ?",
            [$this->company_id]
        );
    }
}
?>
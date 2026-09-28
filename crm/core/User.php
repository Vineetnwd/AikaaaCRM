<?php
namespace Core;

class User
{
    private $db;
    private $company_id;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->company_id = Auth::companyId();
    }

    public function all($filters = [])
    {
        $baseSql = "FROM users u 
                LEFT JOIN employees e ON (u.employee_id = e.id OR u.emp_id = e.id)
                WHERE u.company_id = ?";
        $params = [$this->company_id];

        if (!empty($filters['search'])) {
            $baseSql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.role LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['role'])) {
            $baseSql .= " AND u.role = ?";
            $params[] = $filters['role'];
        }

        if (isset($filters['page'])) {
            $page = max(1, (int)$filters['page']);
            $limit_val = $filters['limit'] ?? '15';
            $limit = ($limit_val === 'all') ? 1000000 : max(1, (int)$limit_val);
            $offset = ($page - 1) * $limit;
            
            $countSql = "SELECT COUNT(*) as total " . $baseSql;
            $total = $this->db->fetchOne($countSql, $params)['total'] ?? 0;
            $total_pages = max(1, ceil($total / $limit));
            
            $sql = "SELECT u.*, e.name as employee_name, e.employee_id as emp_code " . $baseSql . " ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset";
            $data = $this->db->fetchAll($sql, $params);
            
            return [
                'data' => $data,
                'total_records' => $total,
                'total_pages' => $total_pages,
                'current_page' => $page
            ];
        }

        $sql = "SELECT u.*, e.name as employee_name, e.employee_id as emp_code " . $baseSql . " ORDER BY u.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function find($id)
    {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ? AND company_id = ?",
            [$id, $this->company_id]
        );
    }

    public function update($id, $data)
    {
        // If password is provided, hash it
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }

        $conn = $this->db->getConnection();
        try {
            $conn->beginTransaction();

            // Get original user to find employee link if not in data
            $originalUser = $this->find($id);
            $empId = $data['employee_id'] ?? $data['emp_id'] ?? $originalUser['employee_id'] ?? $originalUser['emp_id'] ?? null;

            $allowedUserKeys = ['name', 'email', 'mobile', 'password', 'role', 'type', 'status', 'photo', 'employee_id', 'emp_id', 'last_login'];
            $userData = array_intersect_key($data, array_flip($allowedUserKeys));

            if (!empty($userData)) {
                $keys = array_keys($userData);
                $set = implode(', ', array_map(fn($k) => "$k = :$k", $keys));

                $sql = "UPDATE users SET $set WHERE id = :id AND company_id = :company_id";
                $userData['id'] = $id;
                $userData['company_id'] = $this->company_id;

                $this->db->query($sql, $userData);
            }

            // Sync with Employee table
            if ($empId) {
                $empData = [];
                if (isset($data['name']))
                    $empData['name'] = $data['name'];
                if (isset($data['email']))
                    $empData['email'] = $data['email'];
                if (isset($data['mobile']))
                    $empData['mobile'] = $data['mobile'];
                if (isset($data['type']))
                    $empData['type'] = $data['type'];
                if (isset($data['photo']))
                    $empData['photo'] = $data['photo'];
                if (isset($data['designation']))
                    $empData['designation'] = $data['designation'];
                if (isset($data['department']))
                    $empData['department'] = $data['department'];
                if (isset($data['address']))
                    $empData['address'] = $data['address'];
                if (isset($data['date_of_birth']))
                    $empData['date_of_birth'] = $data['date_of_birth'];
                if (isset($data['gender']))
                    $empData['gender'] = $data['gender'];
                if (isset($data['status'])) {
                    $empData['status'] = ($data['status'] === 'active') ? 'active' : 'inactive';
                }

                if (!empty($empData)) {
                    $empKeys = array_keys($empData);
                    $empSet = implode(', ', array_map(fn($k) => "$k = :$k", $empKeys));
                    $empSql = "UPDATE employees SET $empSet WHERE id = :emp_id_sync AND company_id = :company_id";
                    $empData['emp_id_sync'] = $empId;
                    $empData['company_id'] = $this->company_id;
                    $this->db->query($empSql, $empData);
                }
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
        return $this->db->query(
            "DELETE FROM users WHERE id = ? AND company_id = ?",
            [$id, $this->company_id]
        );
    }
}

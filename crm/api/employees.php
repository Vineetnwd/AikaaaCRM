<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Employee.php';

use Core\Auth;
use Core\Employee;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (Auth::isExecutive()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$employeeModel = new Employee();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    $action = $_GET['action'] ?? null;

    if ($action === 'stats') {
        echo json_encode($employeeModel->stats());
    } elseif ($action === 'departments') {
        echo json_encode($employeeModel->departments());
    } elseif ($action === 'commissions' && $id) {
        $db = Core\Database::getInstance();
        $commissions = $db->fetchAll("
            SELECT id as lead_id, name as lead_name, requirement, commission_amount, updated_at 
            FROM leads 
            WHERE assigned_employee_id = ? AND company_id = ? AND commission_amount > 0 
            ORDER BY updated_at DESC
        ", [$id, Auth::companyId()]);
        echo json_encode($commissions);
    } elseif ($id) {
        $emp = $employeeModel->find($id);
        if ($emp) {
            echo json_encode($emp);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Employee not found']);
        }
    } else {
        $filters = [];
        if (!empty($_GET['department']))
            $filters['department'] = $_GET['department'];
        if (!empty($_GET['status']))
            $filters['status'] = $_GET['status'];
        if (!empty($_GET['search']))
            $filters['search'] = $_GET['search'];
        if (isset($_GET['page']))
            $filters['page'] = (int) $_GET['page'];
        echo json_encode($employeeModel->all($filters));
    }

} elseif ($method === 'POST') {
    if (isset($_FILES['employee_photo'])) {
        $file = $_FILES['employee_photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Upload error']);
            exit;
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = 'emp_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $uploadDir = __DIR__ . '/../public/uploads/profiles/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
            echo json_encode(['success' => true, 'path' => 'uploads/profiles/' . $newName]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move file']);
        }
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Employee name is required']);
        exit;
    }

    if (empty($input['user_password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Password is required']);
        exit;
    }

    if (strlen($input['user_password']) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters']);
        exit;
    }

    $validRoles = ['executive', 'manager', 'admin'];
    if (empty($input['user_role']) || !in_array($input['user_role'], $validRoles, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid role is required']);
        exit;
    }

    if (!isset($input['status']))
        $input['status'] = 'active';

    try {
        $id = $employeeModel->create($input);

        // Send Email and WhatsApp to the newly created employee
        $db = Core\Database::getInstance();
        $company_id = Auth::companyId();
        $company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$company_id]);

        $loginUrl = defined('APP_URL') ? APP_URL . "/public/index.php/login" : "https://" . $_SERVER['HTTP_HOST'] . "/public/index.php/login";
        $email = $input['email'] ?? '';
        $mobile = $input['mobile'] ?? '';
        $password = $input['user_password'] ?? '';
        $name = $input['name'] ?? 'Employee';

        // WhatsApp Notification
        if (!empty($mobile)) {
            $companyName = $company['name'] ?? 'Aikaaa CRM';
            $variables = [
                $name,
                "Welcome to $companyName! We're thrilled to have you on board. Your account has been successfully created.",
                "Your login credentials are: \nEmail: $email\nPassword: $password",
                $loginUrl
            ];
            require_once __DIR__ . '/../core/Whatsapp.php';
            \Core\WhatsApp::sendTemplate($mobile, $variables);
        }

        // Email Notification
        if (!empty($email)) {
            $companyName = $company['name'] ?? 'Aikaaa CRM';
            $smtp = [
                'smtp_host' => $company['smtp_host'] ?? '',
                'smtp_port' => intval($company['smtp_port'] ?? 587),
                'smtp_email' => $company['smtp_email'] ?? '',
                'smtp_password' => $company['smtp_password'] ?? '',
                'smtp_secure' => $company['smtp_secure'] ?? 'tls',
                'company_name' => $companyName
            ];
            $has_smtp = (!empty($smtp['smtp_host']) && !empty($smtp['smtp_email']) && !empty($smtp['smtp_password']));

            $baseAppUrl = defined('APP_URL') ? APP_URL : "https://" . $_SERVER['HTTP_HOST'];
            $logoHtml = '';
            if (!empty($company['logo_path'])) {
                $logoUrl = $baseAppUrl . '/public/' . $company['logo_path'];
                $logoHtml = '<img src="' . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($companyName) . ' Logo" style="max-height: 60px; margin-bottom: 15px;"><br>';
            }

            $subject = "Welcome to $companyName - Your Account Details";
            $htmlBody = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Welcome to ' . htmlspecialchars($companyName) . '</title>
            </head>
            <body style="margin: 0; padding: 0; font-family: \'Inter\', Helvetica, Arial, sans-serif; background-color: #f4f7fa; color: #333333;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f7fa; padding: 40px 20px;">
                    <tr>
                        <td align="center">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05); overflow: hidden;">
                                <!-- Header -->
                                <tr>
                                    <td align="center" style="padding: 40px 30px 20px 30px; background-color: #ffffff;">
                                        ' . $logoHtml . '
                                        <h1 style="margin: 0; color: #1e293b; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">Welcome to the Team!</h1>
                                    </td>
                                </tr>
                                
                                <!-- Body -->
                                <tr>
                                    <td style="padding: 20px 40px 30px 40px; background-color: #ffffff;">
                                        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #475569;">
                                            Dear <strong>' . htmlspecialchars($name) . '</strong>,
                                        </p>
                                        <p style="margin: 0 0 25px 0; font-size: 16px; line-height: 1.6; color: #475569;">
                                            We are thrilled to welcome you to <strong>' . htmlspecialchars($companyName) . '</strong>. Your employee account has been successfully created. You can now access your personalized dashboard and start collaborating.
                                        </p>
                                        
                                        <!-- Credentials Box -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 30px;">
                                            <tr>
                                                <td style="padding: 25px;">
                                                    <h3 style="margin: 0 0 15px 0; color: #0f172a; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Your Login Credentials</h3>
                                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                        <tr>
                                                            <td width="30%" style="padding-bottom: 10px; color: #64748b; font-size: 14px; font-weight: 500;">Login URL:</td>
                                                            <td style="padding-bottom: 10px; color: #1e293b; font-size: 15px; font-weight: 600;"><a href="' . htmlspecialchars($loginUrl) . '" style="color: #2563eb; text-decoration: none;">' . htmlspecialchars($loginUrl) . '</a></td>
                                                        </tr>
                                                        <tr>
                                                            <td width="30%" style="padding-bottom: 10px; color: #64748b; font-size: 14px; font-weight: 500;">Email:</td>
                                                            <td style="padding-bottom: 10px; color: #1e293b; font-size: 15px; font-weight: 600;">' . htmlspecialchars($email) . '</td>
                                                        </tr>
                                                        <tr>
                                                            <td width="30%" style="color: #64748b; font-size: 14px; font-weight: 500;">Password:</td>
                                                            <td style="color: #1e293b; font-size: 15px; font-weight: 600;">' . htmlspecialchars($password) . '</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- CTA Button -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td align="center">
                                                    <a href="' . htmlspecialchars($loginUrl) . '" style="display: inline-block; padding: 14px 32px; background-color: #2563eb; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 8px; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2); transition: background-color 0.3s ease;">Log In to Your Account</a>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <p style="margin: 30px 0 0 0; font-size: 14px; line-height: 1.6; color: #64748b; text-align: center;">
                                            For security purposes, we strongly recommend changing your password after your first login and keeping it confidential.
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style="padding: 30px 40px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center;">
                                        <p style="margin: 0 0 10px 0; font-size: 14px; color: #64748b; font-weight: 500;">
                                            Need help? Contact your administrator.
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #94a3b8;">
                                            &copy; ' . date('Y') . ' ' . htmlspecialchars($companyName) . '. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';

            if ($has_smtp) {
                try {
                    require_once __DIR__ . '/../core/Mailer.php';
                    \Core\Mailer::send($email, $subject, $htmlBody, $smtp);
                } catch (\Exception $me) {
                    // Ignore email error
                }
            } else {
                $fromName = !empty($smtp['company_name']) ? $smtp['company_name'] : $companyName;
                $fromEmail = !empty($smtp['smtp_email']) ? $smtp['smtp_email'] : 'noreply@aikocrm.com';
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: " . $fromName . " <" . $fromEmail . ">\r\n";
                $headers .= "Reply-To: " . $fromEmail . "\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                @mail($email, $subject, $htmlBody, $headers);
            }
        }

        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if ($id) {
        unset($input['id']);
        try {
            $employeeModel->update($id, $input);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Employee ID is required for update']);
    }

} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        try {
            $employeeModel->delete($id);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Employee ID is required']);
    }
}
?>
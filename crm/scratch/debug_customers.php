<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Database;

$db = Database::getInstance();
$company_id = 1; // Assuming company_id 1 for now, or I can try to find an active one.

echo "Searching for specific customers from screenshot:\n";
$names = ['SHYAM', 'RAJIV RANJAN', 'RANA FOUNDATION', 'SUMAN'];
$placeholders = implode(',', array_fill(0, count($names), '?'));
$customers = $db->fetchAll("SELECT id, name, mobile, company_id FROM customers WHERE name IN ($placeholders)", $names);
print_r($customers);

if (empty($customers)) {
    echo "No customers found with those names. Listing all customers for first company found:\n";
    $first_comp = $db->fetchOne("SELECT company_id FROM customers LIMIT 1")['company_id'];
    $customers = $db->fetchAll("SELECT id, name, mobile, company_id FROM customers WHERE company_id = ?", [$first_comp]);
    print_r($customers);
}


echo "\nLeads:\n";
$leads = $db->fetchAll("SELECT id, name, mobile, customer_id, company_id, created_at FROM leads");
print_r($leads);

echo "\nInvoices:\n";
$invoices = $db->fetchAll("SELECT id, lead_id, total_amount, company_id FROM invoices");
print_r($invoices);

echo "\nDebug Query for each customer:\n";
foreach ($customers as $c) {
    $cid = $c['id'];
    $cmobile = $c['mobile'];
    $comp = $c['company_id'];
    
    $lead_count = $db->fetchOne("SELECT COUNT(*) as count FROM leads l WHERE (l.customer_id = ? OR l.mobile = ?) AND l.company_id = ?", [$cid, $cmobile, $comp])['count'];
    echo "Customer {$c['name']} (ID: $cid, Mobile: $cmobile, Comp: $comp) -> Lead Count: $lead_count\n";
    
    $leads_found = $db->fetchAll("SELECT id, customer_id, mobile FROM leads l WHERE (l.customer_id = ? OR l.mobile = ?) AND l.company_id = ?", [$cid, $cmobile, $comp]);
    echo "Leads matched: " . json_encode($leads_found) . "\n";
}

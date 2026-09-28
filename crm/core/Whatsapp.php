<?php
namespace Core;

class WhatsApp
{
    /**
     * Send a WhatsApp Template Message via the configured AOC Portal API
     * 
     * @param string $mobile Target mobile number
     * @param array $customVariables Array of 4 text variables for info_update779
     */
    public static function sendTemplate($mobile, $customVariables = [])
    {
        $db = Database::getInstance();
        $companyId = Auth::companyId();

        $company = $db->fetchOne("SELECT whatsapp_access_token, whatsapp_number, whatsapp_default_template, whatsapp_header_image FROM companies WHERE id = ?", [$companyId]);

        if (empty($company['whatsapp_access_token']) || empty($company['whatsapp_number']) || empty($company['whatsapp_default_template'])) {
            return false; // Not configured properly
        }

        // Clean mobile
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        if (empty($mobile)) return false;
        
        if (strlen($mobile) == 10) {
            $mobile = '91' . $mobile; 
        }

        $parameters = [];
        foreach ($customVariables as $val) {
            $parameters[] = (string)$val;
        }

        $fromNumber = trim($company['whatsapp_number']);
        if (!empty($fromNumber) && $fromNumber[0] !== '+') {
            $fromNumber = '+' . ltrim($fromNumber, '+');
        }

        $payload = [
            "from" => $fromNumber,
            "campaignName" => "crm-api",
            "to" => "+" . ltrim($mobile, '+'),
            "templateName" => $company['whatsapp_default_template'],
            "type" => "template",
            "components" => [
                "body" => [
                    "params" => $parameters
                ]
            ]
        ];

        if (!empty($company['whatsapp_header_image'])) {
            $payload["components"]["header"] = [
                "type" => "image",
                "image" => [
                    "link" => $company['whatsapp_header_image']
                ]
            ];
        }

        $url = "https://api.aoc-portal.com/v1/whatsapp";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: " . $company['whatsapp_access_token'],
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpcode >= 200 && $httpcode < 300;
    }
    
    /**
     * Fetch Mobile and Name
     */
    private static function getClientDetails($lead_id) {
        $db = Database::getInstance();
        $lead = $db->fetchOne("SELECT l.name as lead_name, l.mobile as lead_mobile, c.name as customer_name, c.mobile as customer_mobile 
            FROM leads l 
            LEFT JOIN customers c ON l.customer_id = c.id 
            WHERE l.id = ?", [$lead_id]);
            
        if (!$lead) return null;
        
        $mobile = $lead['lead_mobile'] ?: $lead['customer_mobile'];
        $name = $lead['lead_name'] ?: $lead['customer_name'] ?: 'Customer';
        
        if (empty($mobile)) return null;
        
        return ['name' => $name, 'mobile' => $mobile];
    }

    public static function sendInvoiceAlert($invoice_id, $lead_id, $invoice_number, $total_amount) {
        $client = self::getClientDetails($lead_id);
        if (!$client) return false;

        $link = APP_URL . "/public/assets/views/invoice_print.php?id=" . $invoice_id;
        
        $variables = [
            $client['name'],
            "Your Invoice ($invoice_number) for ₹" . number_format($total_amount, 2) . " has been generated.",
            "Please complete the payment at your earliest convenience.",
            $link
        ];

        return self::sendTemplate($client['mobile'], $variables);
    }

    public static function sendQuotationAlert($quotation_id, $lead_id, $quotation_number, $total_amount) {
        $client = self::getClientDetails($lead_id);
        if (!$client) return false;

        $link = APP_URL . "/public/assets/views/quotation_print.php?id=" . $quotation_id;
        
        $variables = [
            $client['name'],
            "Your Quotation ($quotation_number) for ₹" . number_format($total_amount, 2) . " has been generated.",
            "Please review the document at the link below.",
            $link
        ];

        return self::sendTemplate($client['mobile'], $variables);
    }

    public static function sendPaymentAlert($payment_id, $invoice_id, $amountPaid) {
        $db = Database::getInstance();
        $inv = $db->fetchOne("SELECT lead_id, invoice_number FROM invoices WHERE id = ?", [$invoice_id]);
        if (!$inv) return false;
        
        $client = self::getClientDetails($inv['lead_id']);
        if (!$client) return false;

        $link = APP_URL . "/public/assets/views/invoice_receipt.php?payment_id=" . $payment_id;
        
        $variables = [
            $client['name'],
            "We have successfully received your payment of ₹" . number_format($amountPaid, 2) . " for Invoice " . $inv['invoice_number'] . ".",
            "Thank you for your business!",
            $link
        ];

        return self::sendTemplate($client['mobile'], $variables);
    }
}

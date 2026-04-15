<?php
require_once dirname(__FILE__) . '/../../lodin.php';

class LodinWebhookModuleFrontController extends ModuleFrontController
{
    public $ssl = true; // Force HTTPS for security
    
    /**
     * Disable display - webhooks are server-to-server
     */
    public function display()
    {
        // No template needed
    }
    
    public function postProcess()
    {
        error_log('=== LODIN WEBHOOK RECEIVED ===');
        error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('Headers: ' . json_encode(getallheaders()));
        
        // Only accept POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log('ERROR: Invalid request method');
            http_response_code(405);
            die('Method Not Allowed');
        }
        
        // Get raw POST data
        $payload = file_get_contents('php://input');
        error_log('Webhook payload: ' . $payload);
        
        if (empty($payload)) {
            error_log('ERROR: Empty payload');
            http_response_code(400);
            die('Bad Request');
        }
        
        // Verify webhook signature
        $signature = isset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) 
            ? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] 
            : null;
            
        if (!$this->verifySignature($payload, $signature)) {
            error_log('ERROR: Invalid signature');
            http_response_code(401);
            die('Unauthorized');
        }
        
        // Parse webhook data
        $data = json_decode($payload, true);
        
        if (!$data) {
            error_log('ERROR: Invalid JSON payload');
            http_response_code(400);
            die('Invalid JSON');
        }
        
        error_log('Parsed data: ' . print_r($data, true));
        
        try {
            $this->handleWebhook($data);
            
            // Return 200 OK to acknowledge receipt
            http_response_code(200);
            error_log('=== LODIN WEBHOOK SUCCESS ===');
            die('OK');
            
        } catch (Exception $e) {
            error_log('ERROR: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            http_response_code(500);
            die('Internal Server Error');
        }
    }
    
    /**
     * Verify webhook signature
     */
/**
 * Verify webhook signature
 */
private function verifySignature($payload, $receivedSignature)
{
    if (!$receivedSignature) {
        error_log('WARNING: No signature provided');
        return false;
    }
    
    $clientSecret = Configuration::get('LODIN_CLIENT_SECRET');
    
    if (!$clientSecret) {
        error_log('ERROR: Client secret not configured');
        return false;
    }
    
    // Calculate expected signature - same method as payment link
    $raw_hmac = hash_hmac('sha256', $payload, $clientSecret, true);
    $base64 = base64_encode($raw_hmac);
    $expectedSignature = rtrim(strtr($base64, ['+' => '-', '/' => '_']), '=');
    
    $isValid = hash_equals($expectedSignature, $receivedSignature);
    
    error_log('Signature verification: ' . ($isValid ? 'PASS' : 'FAIL'));
    error_log('Payload: ' . $payload);
    error_log('Client Secret length: ' . strlen($clientSecret));
    error_log('Expected: ' . $expectedSignature);
    error_log('Received: ' . $receivedSignature);
    
    return $isValid;
}

    
    /**
     * Handle webhook based on event type
     */
    private function handleWebhook($data)
    {
        $eventType = isset($data['eventType']) ? $data['eventType'] : null;
        $invoiceId = isset($data['invoiceId']) ? $data['invoiceId'] : null;
        $transactionId = isset($data['transactionId']) ? $data['transactionId'] : null;
        
        if (!$eventType || !$invoiceId) {
            throw new Exception('Missing required fields: eventType or invoiceId');
        }
        
        error_log('Event type: ' . $eventType);
        error_log('Invoice ID: ' . $invoiceId);
        error_log('Transaction ID: ' . $transactionId);
        
        // Find the order by invoice ID (from cart reference)
        $order = $this->findOrderByInvoiceId($invoiceId);
        
        if (!$order) {
            throw new Exception('Order not found for invoice: ' . $invoiceId);
        }
        
        error_log('Found order ID: ' . $order->id);
        error_log('Current order status: ' . $order->getCurrentState());
        
        // Handle different event types
        switch ($eventType) {
            case 'payment.succeeded':
            case 'payment.completed':
                $this->handlePaymentSuccess($order, $data);
                break;
                
            case 'payment.failed':
            case 'payment.declined':
                $this->handlePaymentFailure($order, $data);
                break;
                
            case 'payment.pending':
                $this->handlePaymentPending($order, $data);
                break;
                
            default:
                error_log('WARNING: Unknown event type: ' . $eventType);
        }
    }
    
    /**
     * Find order by invoice ID
     */
    private function findOrderByInvoiceId($invoiceId)
    {
        // Invoice ID format: CART-6-1768835164
        // Extract cart ID
        $parts = explode('-', $invoiceId);
        
        if (count($parts) < 2) {
            error_log('ERROR: Invalid invoice ID format: ' . $invoiceId);
            return null;
        }
        
        $cartId = (int) $parts[1];
        error_log('Searching for order with cart ID: ' . $cartId);

        for ($i = 0; $i < 3; $i++) {
        $orderId = Order::getIdByCartId($cartId);
        if ($orderId) {
            error_log('Found order ID: ' . $orderId . ' (attempt ' . ($i+1) . ')');
            return new Order($orderId);
        }
        if ($i < 2) sleep(1);
    }
        error_log('ERROR: No order found for cart ID: ' . $cartId);       
        return null;
    }
    
    /**
     * Handle successful payment
     */
    private function handlePaymentSuccess($order, $data)
    {
        error_log('Processing successful payment');
    
        // SÉCURITÉ 1 — Vérifier la signature (déjà fait en amont dans postProcess ?)
    
        // SÉCURITÉ 2 — Vérifier le montant si présent
        if (!empty($data['amount'])) {
            $amountPaid     = (float) $data['amount'];
            $amountExpected = (float) $order->total_paid;
    
            if (abs($amountPaid - $amountExpected) > 0.01) {
                error_log('ERROR: Amount mismatch! Expected: ' . $amountExpected . ' Got: ' . $amountPaid);
                throw new Exception('Amount mismatch: expected ' . $amountExpected . ' got ' . $amountPaid);
            }
            error_log('Amount verified: ' . $amountPaid);
        } else {
            // Lodin envoie parfois null ? on log mais on ne bloque pas
            error_log('WARNING: No amount in webhook, skipping amount check');
        }
    
        // SÉCURITÉ 3 — Vérifier le statut Lodin
        if (isset($data['status']) && $data['status'] !== 'COMPLETED') {
            throw new Exception('Unexpected status: ' . $data['status']);
        }
    
        // Check if already paid (évite le double traitement)
        if ($order->getCurrentState() == Configuration::get('PS_OS_PAYMENT')) {
            error_log('WARNING: Order already marked as paid');
            return;
        }
    
        // Update order status
        $newOrderState = Configuration::get('PS_OS_PAYMENT');
        $order->setCurrentState($newOrderState);
        $order->save();
    
        // Add payment details
        if (isset($data['transactionId'])) {
            $order->payment_transaction_id = $data['transactionId'];
            $order->update();
        }
    
        $this->addOrderPayment($order, $data);
    
        error_log('Order status updated to: Payment accepted');
    }
    
    /**
     * Handle failed payment
     */
    private function handlePaymentFailure($order, $data)
    {
        error_log('Processing failed payment');
        
        // Update order status to "Payment error"
        $newOrderState = Configuration::get('PS_OS_ERROR');
        
        $order->setCurrentState($newOrderState);
        $order->save();
        
        // Add failure reason to order message
        if (isset($data['errorMessage'])) {
            $message = new Message();
            $message->message = 'Payment failed: ' . $data['errorMessage'];
            $message->id_order = $order->id;
            $message->private = 1;
            $message->add();
        }
        
        error_log('Order status updated to: Payment error');
    }
    
    /**
     * Handle pending payment
     */
    private function handlePaymentPending($order, $data)
    {
        error_log('Processing pending payment');
        
        // Keep status as "Awaiting payment" or update to custom pending status
        // This is optional - depends on your business logic
        
        error_log('Order remains in pending status');
    }
    
    /**
     * Add payment record to order
     */
    private function addOrderPayment($order, $data)
    {
        $orderPayment = new OrderPayment();
        $orderPayment->order_reference = $order->reference;
        $orderPayment->id_currency = $order->id_currency;
        $orderPayment->amount = isset($data['amount']) ? (float)$data['amount'] : $order->total_paid;
        $orderPayment->payment_method = 'Lodin RTP';
        $orderPayment->conversion_rate = 1;
        $orderPayment->transaction_id = isset($data['transactionId']) ? $data['transactionId'] : null;
        $orderPayment->date_add = date('Y-m-d H:i:s');
        
        $orderPayment->add();
        
        error_log('Order payment record added');
    }
}

<?php
/**
 * Lodin RTP Payment Module
 * Generates payment links via Effyis API
 *
 * @author    Lodin < apps@lodinpay.com>
 * @copyright 2026 Lodin
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once dirname(__FILE__) . '/../../lodin.php';

class LodinWebhookModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function display()
    {
    }

    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        $payload = file_get_contents('php://input');

        if (empty($payload)) {
            http_response_code(400);
            exit('Bad Request');
        }

        $signature = isset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'])
            ? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']
            : null;

        if (!$this->verifySignature($payload, $signature)) {
            http_response_code(401);
            exit('Unauthorized');
        }

        $data = json_decode($payload, true);

        if (!$data) {
            http_response_code(400);
            exit('Invalid JSON');
        }

        try {
            $this->handleWebhook($data);
            http_response_code(200);
            exit('OK');
        } catch (Exception $e) {
            http_response_code(500);
            exit('Internal Server Error');
        }
    }

    private function verifySignature($payload, $receivedSignature)
    {
        if (!$receivedSignature) {
            return false;
        }

        $clientSecret = Configuration::get('LODIN_CLIENT_SECRET');

        if (!$clientSecret) {
            return false;
        }

        $raw_hmac = hash_hmac('sha256', $payload, $clientSecret, true);
        $base64 = base64_encode($raw_hmac);
        $expectedSignature = rtrim(strtr($base64, ['+' => '-', '/' => '_']), '=');

        return hash_equals($expectedSignature, $receivedSignature);
    }

    private function handleWebhook($data)
    {
        $eventType = isset($data['eventType']) ? $data['eventType'] : null;
        $invoiceId = isset($data['invoiceId']) ? $data['invoiceId'] : null;

        if (!$eventType || !$invoiceId) {
            throw new Exception('Missing required fields: eventType or invoiceId');
        }

        $order = $this->findOrderByInvoiceId($invoiceId);

        if (!$order) {
            throw new Exception('Order not found for invoice: ' . pSQL($invoiceId));
        }

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
                // statut inchangé
                break;
        }
    }

    private function findOrderByInvoiceId($invoiceId)
    {
        $parts = explode('-', $invoiceId);

        if (count($parts) < 2) {
            return null;
        }

        $cartId = (int) $parts[1];

        for ($i = 0; $i < 3; $i++) {
            $orderId = Order::getIdByCartId($cartId);
            if ($orderId) {
                return new Order($orderId);
            }
            if ($i < 2) {
                sleep(1);
            }
        }

        return null;
    }

    private function handlePaymentSuccess($order, $data)
    {
        if (!empty($data['amount'])) {
            $amountPaid = (float) $data['amount'];
            $amountExpected = (float) $order->total_paid;

            if (abs($amountPaid - $amountExpected) > 0.01) {
                throw new Exception('Amount mismatch: expected ' . $amountExpected . ' got ' . $amountPaid);
            }
        }

        if ($order->getCurrentState() == Configuration::get('PS_OS_PAYMENT')) {
            return;
        }

        $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
        $order->save();

        $this->addOrderPayment($order, $data);
    }

    private function handlePaymentFailure($order, $data)
    {
        $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
        $order->save();

        if (isset($data['errorMessage'])) {
            $message = new Message();
            $message->message = 'Payment failed: ' . pSQL($data['errorMessage']);
            $message->id_order = $order->id;
            $message->private = true;
            $message->add();
        }
    }

    private function addOrderPayment($order, $data)
    {
        $orderPayment = new OrderPayment();
        $orderPayment->order_reference = $order->reference;
        $orderPayment->id_currency = $order->id_currency;
        $orderPayment->amount = isset($data['amount']) ? (float) $data['amount'] : $order->total_paid;
        $orderPayment->payment_method = 'Lodin RTP';
        $orderPayment->conversion_rate = 1;
        $orderPayment->transaction_id = isset($data['transactionId']) ? pSQL($data['transactionId']) : null;
        $orderPayment->date_add = date('Y-m-d H:i:s');
        $orderPayment->add();
    }
}
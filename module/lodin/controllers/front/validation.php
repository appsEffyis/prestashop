<?php
require_once dirname(__FILE__) . '/../../lodin.php';

class LodinValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        error_log('=== LODIN VALIDATION CONTROLLER START ===');
        error_log('Request URI: ' . $_SERVER['REQUEST_URI']);
        error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
        
        $cart = $this->context->cart;
        error_log('Cart ID: ' . $cart->id);
        error_log('Customer ID: ' . $cart->id_customer);
        error_log('Delivery Address: ' . $cart->id_address_delivery);
        error_log('Invoice Address: ' . $cart->id_address_invoice);
        
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0) {
            error_log('ERROR: Cart validation failed - redirecting to checkout');
            Tools::redirect('index.php?controller=order&step=1');
        }

        try {
            error_log('Attempting to generate payment link...');
            $paymentLink = $this->module->generatePaymentLink($cart);
            
            if ($paymentLink) {
                error_log('Payment link received: ' . $paymentLink);
                
                $customer = new Customer($cart->id_customer);
                error_log('Customer secure key: ' . $customer->secure_key);
                error_log('Creating order...');
                
                $this->module->validateOrder(
                    (int)$cart->id,
                    (int)Configuration::get('PS_OS_BANKWIRE'),
                    $cart->getOrderTotal(true, Cart::BOTH),  // Fixed: Changed from TOTAL_PAYMENT to BOTH
                    $this->module->displayName,
                    null,
                    [],
                    (int)$cart->id_currency,
                    false,
                    $customer->secure_key
                );
                
                error_log('Order created successfully. Redirecting to: ' . $paymentLink);
                error_log('=== LODIN VALIDATION CONTROLLER SUCCESS ===');
                Tools::redirect($paymentLink);
            } else {
                throw new Exception('Payment link generation failed - received null');
            }
        } catch (Exception $e) {
            error_log('ERROR: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            error_log('=== LODIN VALIDATION CONTROLLER FAILED ===');
            
            $this->context->smarty->assign('error', $e->getMessage());
            $this->setTemplate('module:lodin/views/templates/front/error.tpl');
        }
    }
}

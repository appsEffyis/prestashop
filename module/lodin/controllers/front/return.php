<?php
class LodinReturnModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        error_log('=== LODIN RETURN CONTROLLER START ===');

        $id_cart   = (int) Tools::getValue('id_cart');
        $id_order  = (int) Tools::getValue('id_order');
        $id_module = (int) Tools::getValue('id_module');
        $key       = Tools::getValue('key');

        // Vérifier que la commande existe
        $order = new Order($id_order);
        
        if (!Validate::isLoadedObject($order)) {
            error_log('LODIN RETURN: Order not found');
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        // Vérifier la secure_key
        $customer = new Customer($order->id_customer);
        if ($key !== $customer->secure_key) {
            error_log('LODIN RETURN: Invalid key');
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        if ($order->getCurrentState() == Configuration::get('PS_OS_PAYMENT')) {
            error_log('LODIN RETURN: Redirecting to order-confirmation');
            Tools::redirect(
                'index.php?controller=order-confirmation' .
                '&id_cart='   . $id_cart .
                '&id_module=' . $id_module .
                '&id_order='  . $id_order .
                '&key='       . $key
            );
        }
        
        if ($order->getCurrentState() == Configuration::get('PS_OS_ERROR') || 
            $order->getCurrentState() == Configuration::get('PS_OS_CANCELED') ||
            $order->getCurrentState() == Configuration::get('PS_OS_BANKWIRE')) 
        { 
               
        
            $this->restoreCart($id_cart);
            
            $this->context->smarty->assign([
                'order_id' => $id_order,
                'checkout_url' => $this->context->link->getPageLink('order', true, null, ['step' => 1])
            ]);

            $this->setTemplate('module:lodin/views/templates/front/payment_error.tpl');
        }
    }
     protected function restoreCart($id_cart)
    {
        $old_cart = new Cart($id_cart);
        $duplication = $old_cart->duplicate();
        if ($duplication && Validate::isLoadedObject($duplication['cart'])) {
            $this->context->cookie->id_cart = $duplication['cart']->id;
            $this->context->cart = $duplication['cart'];
            CartRule::autoAddToCart($this->context);
            $this->context->cookie->write();
        }
    }
    
}
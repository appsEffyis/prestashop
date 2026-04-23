<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class LodinReturnModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $id_cart   = (int) Tools::getValue('id_cart');
        $id_module = (int) Tools::getValue('id_module');
        $token     = Tools::getValue('token');

        // Retrouver la commande via le cart
        $id_order = (int) Order::getIdByCartId($id_cart);
        $order    = new Order($id_order);

        if (!Validate::isLoadedObject($order)) {
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        // Vérifier le token
        $customer = new Customer($order->id_customer);
        $expected_token = hash_hmac(
            'sha256',
            $id_cart . $customer->secure_key,
            Configuration::get('LODIN_CLIENT_SECRET')
        );

        if (!hash_equals($expected_token, $token)) {
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        // Paiement accepté → page confirmation
        if ($order->getCurrentState() == Configuration::get('PS_OS_PAYMENT')) {
            Tools::redirect(
                'index.php?controller=order-confirmation' .
                '&id_cart='   . $id_cart .
                '&id_module=' . $id_module .
                '&id_order='  . $id_order .
                '&key='       . $customer->secure_key
            );
            return;
        }

        // Paiement échoué / annulé / en attente → restaurer le panier
        $this->restoreCart($id_cart);

        $this->context->smarty->assign([
            'order_id'     => $id_order,
            'checkout_url' => $this->context->link->getPageLink('order', true, null, ['step' => 1]),
        ]);

        $this->setTemplate('module:lodin/views/templates/front/payment_error.tpl');
    }

    protected function restoreCart($id_cart)
    {
        $old_cart   = new Cart($id_cart);
        $duplication = $old_cart->duplicate();

        if ($duplication && Validate::isLoadedObject($duplication['cart'])) {
            $this->context->cookie->id_cart = $duplication['cart']->id;
            $this->context->cart            = $duplication['cart'];
            CartRule::autoAddToCart($this->context);
            $this->context->cookie->write();
        }
    }
}
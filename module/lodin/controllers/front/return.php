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

        // ✅ Rediriger vers la page de confirmation PrestaShop
        error_log('LODIN RETURN: Redirecting to order-confirmation');
        Tools::redirect(
            'index.php?controller=order-confirmation' .
            '&id_cart='   . $id_cart .
            '&id_module=' . $id_module .
            '&id_order='  . $id_order .
            '&key='       . $key
        );
    }
}
<?php
require_once dirname(__FILE__) . '/../../lodin.php';

class LodinValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        error_log('=== LODIN VALIDATION CONTROLLER START ===');

        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0) {
            error_log('ERROR: Cart validation failed');
            Tools::redirect('index.php?controller=order&step=1');
        }

        try {
            $customer = new Customer($cart->id_customer);

            // ÉTAPE 1 — Créer la commande (statut: en attente)
            $this->module->validateOrder(
                (int)$cart->id,
                (int)Configuration::get('PS_OS_BANKWIRE'),
                $cart->getOrderTotal(true, Cart::BOTH),
                $this->module->displayName,
                null,
                [],
                (int)$cart->id_currency,
                false,
                $customer->secure_key
            );

            $order_id = (int)$this->module->currentOrder;
            error_log('Order created: ' . $order_id);

            // ÉTAPE 2 — Construire la returnUrl
            $return_url = $this->context->link->getModuleLink(
                'lodin',
                'return',
                [
                    'id_cart'   => (int)$cart->id,
                    'id_order'  => $order_id,
                    'id_module' => (int)$this->module->id,
                    'key'       => $customer->secure_key,
                ],
                true
            );
            error_log('Return URL: ' . $return_url);

            // ÉTAPE 3 — Générer le lien de paiement
            $result      = $this->module->generatePaymentLink($cart, $return_url);
            $paymentLink = $result['url'];
            $invoiceId   = $result['invoiceId'];
            error_log('Payment link: ' . $paymentLink);

            // ÉTAPE 4 — Sauvegarder le transaction_id
            $order = new Order($order_id);
            $order_payments = $order->getOrderPaymentCollection();
            if (isset($order_payments[0])) {
                $order_payments[0]->transaction_id = pSQL($invoiceId);
                $order_payments[0]->save();
            }

            // ÉTAPE 5 — Rediriger vers la gateway
            error_log('=== LODIN VALIDATION CONTROLLER SUCCESS ===');
            Tools::redirect($paymentLink);

        }  catch (Exception $e) {
            error_log('ERROR: ' . $e->getMessage());
            // On ajoute le message d'erreur à la session pour l'afficher sur la page commande
            $this->errors[] = $this->trans('Une erreur est survenue lors de la redirection vers Lodin : ', [], 'Modules.Lodin.Shop') . $e->getMessage();
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }
    }
}
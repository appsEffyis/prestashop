<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once dirname(__FILE__) . '/../../lodin.php';

class LodinValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        try {
            /** @var Lodin $module */
            $module   = $this->module;
            $customer = new Customer($cart->id_customer);

            // ÉTAPE 1 — Construire le token et la return URL
            $token = hash_hmac(
                'sha256',
                $cart->id . $customer->secure_key,
                Configuration::get('LODIN_CLIENT_SECRET')
            );

            $return_url = $this->context->link->getModuleLink(
                'lodin',
                'return',
                [
                    'id_cart'   => (int) $cart->id,
                    'id_module' => (int) $module->id,
                    'token'     => $token,
                ],
                true
            );

            // ÉTAPE 2 — Générer le lien AVANT de créer la commande
            $result      = $module->generatePaymentLink($cart, $return_url);
            $paymentLink = $result['url'];
            $invoiceId   = $result['invoiceId'];

            // ÉTAPE 3 — Créer la commande
            $module->validateOrder(
                (int) $cart->id,
                (int) Configuration::get('PS_OS_BANKWIRE'),
                $cart->getOrderTotal(true, Cart::BOTH),
                $module->displayName,
                null,
                ['transaction_id' => $invoiceId],
                (int) $cart->id_currency,
                false,
                $customer->secure_key
            );

            // ÉTAPE 4 — Rediriger vers la gateway
            Tools::redirect($paymentLink);

        } catch (Exception $e) {
            $this->errors[] = $this->trans(
                'Une erreur est survenue lors de la redirection vers Lodin : ',
                [],
                'Modules.Lodin.Shop'
            ) . $e->getMessage();
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }
    }
}
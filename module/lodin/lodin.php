<?php
/**
 * Lodin RTP Payment Module
 * Generates payment links via Effyis API
 *
 * @author    Lodin <apps@lodinpay.com>
 * @copyright 2026 Lodin
 * @license   AFL-3.0
 */
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
if (!defined('_PS_VERSION_')) {
    exit;
}

class Lodin extends PaymentModule
{
    const RTP_API_URL = 'https://api.lodinpay.com/merchant-service/extensions/pay/rtp';

    public function __construct()
    {
        $this->module_key = '07f6a598c35fa8a499aa75be3c8f7b60';
        $this->name = 'lodin';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Lodin';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->trans('Lodin RTP', [], 'Modules.Lodin.Admin');
        $this->description = $this->trans('Generate instant RTP payment links', [], 'Modules.Lodin.Admin');

        $this->confirmUninstall = $this->trans('Are you sure?', [], 'Modules.Lodin.Admin');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && Configuration::updateValue('LODIN_CLIENT_ID', '')
            && Configuration::updateValue('LODIN_CLIENT_SECRET', '')
            && $this->installTab();
    }

    public function getConfigFieldsValues()
    {
        return [
            'LODIN_CLIENT_ID' => Configuration::get('LODIN_CLIENT_ID'),
            'LODIN_CLIENT_SECRET' => Configuration::get('LODIN_CLIENT_SECRET'),
        ];
    }

    public function uninstall()
    {
        $tabUninstall = true;
        try {
            $tabUninstall = $this->uninstallTab();
        } catch (Exception $e) {
            $tabUninstall = false;
        }

        return parent::uninstall()
            && Configuration::deleteByName('LODIN_CLIENT_ID')
            && Configuration::deleteByName('LODIN_CLIENT_SECRET');
    }

    private function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if ($currency_order->iso_code !== 'EUR') {
            return false;
        }

        if (is_array($currencies_module) === false || empty($currencies_module)) {
            return false;
        }

        foreach ($currencies_module as $currency_module) {
            if ($currency_order->id == $currency_module['id_currency']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display Lodin option in checkout
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart'])) {
            return [];
        }

        $this->context->controller->registerStylesheet(
            'lodin-css',
            'modules/' . $this->name . '/views/css/lodin.css'
        );

        $validationUrl = $this->context->link->getModuleLink($this->name, 'validation', [], true);

        $this->context->smarty->assign([
            'logo_url' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/logo.png'),
            'banks_url' => $this->_path . 'views/img/Banks.png',
        ]);

        $paymentOption = new PaymentOption();
        $paymentOption
            ->setModuleName($this->name)
            ->setCallToActionText($this->trans('', [], 'Modules.Lodin.Shop'))
            ->setAction($validationUrl)
            ->setAdditionalInformation(
                $this->fetch('module:lodin/views/templates/hook/payment_option.tpl')
            );

        return [$paymentOption];
    }

    public function generatePaymentLink($cart, $return_url = '')
    {
        $client_id = Configuration::get('LODIN_CLIENT_ID');
        $client_secret = Configuration::get('LODIN_CLIENT_SECRET');

        if (!$client_id || !$client_secret) {
            throw new Exception('Lodin configuration missing');
        }

        $invoice_id = 'CART-' . $cart->id . '-' . time();
        $amount = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');

        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $payload = $client_id . $timestamp . $amount . $invoice_id;
        $signature = $this->generateSignature($payload, $client_secret);

        $headers = [
            'Content-Type: application/json',
            'X-Client-Id: ' . $client_id,
            'X-Timestamp: ' . $timestamp,
            'X-Signature: ' . $signature,
            'X-Extension-Code: PRESTASHOP',
        ];

        $body = [
            'amount' => round((float) $amount, 2),
            'invoiceId' => $invoice_id,
            'paymentType' => 'INST',
            'cardId' => $invoice_id,
            'description' => 'PrestaShop Order #' . $cart->id,
            'returnUrl' => $return_url,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::RTP_API_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $paymentLink = $data['url'] ?? null;

            if ($paymentLink) {
                return ['url' => $paymentLink, 'invoiceId' => $invoice_id];
            }

            throw new Exception('No payment URL in API response');
        }

        throw new Exception('API error: ' . $response);
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $client_id = (string) Tools::getValue('LODIN_CLIENT_ID');
            $client_secret = (string) Tools::getValue('LODIN_CLIENT_SECRET');

            if (empty($client_id) || empty($client_secret)) {
                $output .= $this->displayError('Invalid configuration');
            } else {
                Configuration::updateValue('LODIN_CLIENT_ID', $client_id);
                Configuration::updateValue('LODIN_CLIENT_SECRET', $client_secret);
                $output .= $this->displayConfirmation('Settings updated successfully.');
            }
        }

        return $this->displayForm() . $output;
    }

    public function displayForm()
    {
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->trans('Settings', [], 'Admin.Global'),
                'icon' => 'icon-cogs',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Client ID', [], 'Modules.Lodin.Admin'),
                    'name' => 'LODIN_CLIENT_ID',
                    'required' => true,
                ],
                [
                    'type' => 'password',
                    'label' => $this->trans('Client Secret', [], 'Modules.Lodin.Admin'),
                    'name' => 'LODIN_CLIENT_SECRET',
                    'required' => true,
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save', [], 'Admin.Actions'),
            ],
        ];

        $helper = $this->getFormHelper();

        return $helper->generateForm($fields_form);
    }

    private function getFormHelper()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper;
    }

    public function generateSignature($payload, $secret)
    {
        $raw_hmac = hash_hmac('sha256', $payload, $secret, true);
        $base64 = base64_encode($raw_hmac);
        $urlSafe = strtr($base64, ['+' => '-', '/' => '_']);

        return rtrim($urlSafe, '=');
    }

    public function hookPaymentReturn($params)
    {
        $order = $params['order'];
        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_PAYMENT')) {
            Tools::redirect(
                'index.php?controller=order-confirmation' .
                '&id_cart=' . (int) $order->id .
                '&id_module=' . (int) $this->id .
                '&id_order=' . (int) $order->id .
                '&key=' . $order->secure_key
            );
        }
    }

    public function installTab()
    {
        $tab = new Tab();
        $tab->active = true;
        $tab->class_name = 'AdminLodin';
        $tab->module = $this->name;
        $tab->id_parent = -1;

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Lodin';
        }

        return $tab->add();
    }

    public function uninstallTab()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminLodin');
        if ($id_tab) {
            $tab = new Tab($id_tab);

            return $tab->delete();
        }

        return true;
    }
}

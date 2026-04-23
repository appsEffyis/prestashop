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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Lodin extends PaymentModule
{
    const RTP_API_URL = 'https://api-preprod.lodinpay.com/merchant-service/extensions/pay/rtp';

    public function __construct()
    {
        $this->name = 'lodin';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Lodin';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => _PS_VERSION_];
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
   
    
    // Don't fail if tab doesn't exist
    $tabUninstall = true;
    try {
        $tabUninstall = $this->uninstallTab();
    } catch (Exception $e) {
        
    }
    
    $result = parent::uninstall()
        && Configuration::deleteByName('LODIN_CLIENT_ID')
        && Configuration::deleteByName('LODIN_CLIENT_SECRET');
    
    
    
    return $result;
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

$validationUrl = $this->context->link->getModuleLink($this->name, 'validation', [], true);


$paymentOption = new PaymentOption();
    $paymentOption
        ->setModuleName($this->name)
        // On laisse un texte pour le moteur mais on le cache totalement en CSS
        ->setCallToActionText($this->trans('', [], 'Modules.Lodin.Shop'))
        ->setAction($validationUrl)
        ->setLogo(null) 
        ->setAdditionalInformation(
            '<div class="lodin-custom-wrapper">' .
                '<div class="lodin-logo-column">' .
                    '<img src="' . Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/logo_lodin_rm.png') . '" class="lodin-img-logo" />' .
                '</div>' .
                '<div class="lodin-text-column">' .
                    '<span class="lodin-main-title">' . $this->trans('Pay by Bank', [], 'Modules.Lodin.Shop') . '</span>' .
                    '<p class="lodin-main-subtitle">' . $this->trans('Deposit securely from your bank app - no card needed.', [], 'Modules.Lodin.Shop') . '</p>' .
                '</div>' .
                '<div class="lodin-banks-column">' .
                    '<img src="' . $this->_path . 'views/img/Banks.png" style="height:35px;" />' .
                '</div>' .
            '</div>' .
            '<style>
                /* 1. ON CACHE LE TITRE ET L\'IMAGE PAR DÉFAUT DE PRESTASHOP */
                .payment-option:has(.lodin-custom-wrapper) label,
                .payment-option:has(.lodin-custom-wrapper) img:not(.lodin-img-logo):not([src*="Banks"]) {
                    display: none !important;
                }

                /* 2. ON FORCE L\'AFFICHAGE DU CONTENU SANS ATTENDRE LE CLIC */
                .additional-information {
                    display: block !important;
                }

                /* 3. DESIGN SANS CADRE ET ALIGNÉ À GAUCHE (Image 2) */
                .lodin-custom-wrapper {
                    display: flex;
                    align-items: center;
                    width: 100%;
                    background: transparent !important; /* Pas de fond */
                    border: none !important;           /* Pas de cadre */
                    padding: 0 !important;             /* Pas d\'espacement interne */
                    margin-top: -35px;                  /* Remonte le bloc au niveau du bouton radio */
                    margin-left: -20px;                  /* Décale à droite du bouton radio */
                }

                .lodin-logo-column {
                    margin-right: 12px;
                    flex-shrink: 0;
                }

                .lodin-img-logo {
                    width: 45px !important;
                    height: 45px !important;
                    object-fit: contain;
                    background: transparent !important;
                    mix-blend-mode: multiply;
                    display: block;
                }

                .lodin-text-column {
                    display: flex;
                    flex-direction: column;
                    flex-grow: 1;
                    padding-top: 0;
                }

                .lodin-main-title {
                    font-size: 18px;
                    font-weight: 700;
                    color: #232323;
                    line-height: 1.1;
                }

                .lodin-main-subtitle {
                    font-size: 15px;
                    color: #7a7a7a;
                    margin: 0;
                    line-height: 1.2;
                }

                .lodin-banks-column {
                    margin-left: auto;
                    padding-right: -40px;
                }

                /* Ajustement pour Mobile */
                @media (max-width: 767px) {
                    .lodin-custom-wrapper {
                        margin-top: -30px;
                        flex-wrap: wrap;
                    }
                    .lodin-banks-column {
                        display: none; /* Souvent masqué sur mobile pour gagner de la place */
                    }
                }
            </style>'
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
    
    

    // Fix: Use explicit ISO 8601 format with Z for UTC
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    $payload = $client_id . $timestamp . $amount . $invoice_id;
    $signature = $this->generateSignature($payload, $client_secret);
    
    

    $headers = [
        'Content-Type: application/json',
        'X-Client-Id: ' . $client_id,
        'X-Timestamp: ' . $timestamp,
        'X-Signature: ' . $signature,
        'X-Extension-Code: ' . "PRESTASHOP",
    ];

    $body = [
        'amount' => round((float)$amount, 2),
        'invoiceId' => $invoice_id,
        'paymentType' => 'INST',
        'cardId' => $invoice_id,
        'description' => 'PrestaShop Order #' . $cart->id,
        'returnUrl'   => $return_url,
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
    $curlError = curl_error($ch);
    curl_close($ch);
    
    
    if ($curlError) {
        
    }

    if ($httpCode === 200) {
    $data = json_decode($response, true);
   
    $paymentLink = $data['url'] ?? null;  // Changed from 'paymentLink' to 'url'
   
    
    if ($paymentLink) {
        
        return ['url' => $paymentLink, 'invoiceId' => $invoice_id];
    } else {
       
        throw new Exception('No payment URL in API response');
    }
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
                $output .= $this->displayError('Invalid: ID="' . $client_id . '" Secret="' . $client_secret . '"');
            } else {
                Configuration::updateValue('LODIN_CLIENT_ID', $client_id);
                Configuration::updateValue('LODIN_CLIENT_SECRET', $client_secret);
                $output .= $this->displayConfirmation('Settings updated! ID saved.');
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

    /**
     * Generate RTP payment link (validation controller target)
     */
   
    

 public function generateSignature($payload, $secret)
{
    
    
    $raw_hmac = hash_hmac('sha256', $payload, $secret, true);
    $base64 = base64_encode($raw_hmac);
    $urlSafe = strtr($base64, ['+' => '-', '/' => '_']);
    $signature = rtrim($urlSafe, '=');
    
   
    
    return $signature;
}


    public function hookPaymentReturn($params)
    {
        // Handle return from payment link
        $order = $params['order'];
        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_PAYMENT')) {
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$order->id . '&id_module=' . (int)$this->id . '&id_order=' . (int)$order->id . '&key=' . $order->secure_key);
        }
    }

        public function installTab()
    {
        $tab = new Tab();
        $tab->active = true;
        $tab->class_name = 'AdminLodin';
        $tab->module = $this->name;
        $tab->id_parent = -1; // invisible dans le menu

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Lodin';
        }

        return $tab->add();
    }

    public function uninstallTab()
    {
        $tabs = Tab::getIdFromClassName('AdminLodin');
        $id_tab = is_array($tabs) ? (int) reset($tabs) : (int) $tabs;
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }
}

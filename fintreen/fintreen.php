<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShop\PrestaShop\Core\Database\Db;

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once dirname(__FILE__) . '/class/actionFintreen.php';

class Fintreen extends PaymentModule
{
  public function __construct() 
  {
        $this->name = 'fintreen';
        $this->tab = 'payments_gateways';
        $this->version = '1.0';
        $this->author = 'Fintreen';
        $this->controllers = ['create', 'webhook'];
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        
        $config = Configuration::getMultiple(['API_TOKEN', 'EMAIL', 'MIN_SUM']);
		if (!empty($config['API_TOKEN'])) {
            $this->owner = $config['API_TOKEN'];
        }
		
		if (!empty($config['EMAIL'])) {
            $this->owner = $config['EMAIL'];
        }

        if (!empty($config['MIN_SUM'])) {
            $this->owner = $config['MIN_SUM'];
        }

        

        parent::__construct();

        $this->displayName = $this->l('Fintreen');
        $this->description = $this->l('Crypto Payments');
  }
  
  public function install()
  {
    return parent::install()
    && $this->registerHook('paymentOptions')
	&& $this->registerHook('createTable');
  }
  
  
  public function hookPaymentOptions(array $params)
  {
        if (empty($params['cart'])) {
            return [];
        }

        /** @var Cart $cart */
        $cart = $params['cart'];

        if ($cart->isVirtualCart()) {
            return [];
        }


        $cart = Context::getContext()->cart;
        $currency = new Currency($cart->id_currency);

        $source_currency_iso = $currency->iso_code;
        if($source_currency_iso !== 'EUR') {
            $target_currency_iso = 'EUR';

            $source_currency_id = Currency::getIdByIsoCode($source_currency_iso);
            $target_currency_id = Currency::getIdByIsoCode($target_currency_iso);

            $currency = new Currency($target_currency_id);
            $conversional_rate = $currency->getConversationRate();

            $converted_amount = $cart->getOrderTotal() * $conversional_rate;

            if($converted_amount >= Configuration::get('FINTREEN_MIN_SUM')) {
                $externalOption = new PaymentOption();
                $externalOption->setModuleName($this->name);
                $externalOption->setCallToActionText($this->displayName);
                $externalOption->setAction($this->context->link->getModuleLink($this->name, 'create', array(), true));
        
                $externalOption->setAdditionalInformation('Payment With Fintreen Crypto Gateway');
                //$externalOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/option/external.png'));

                return [$externalOption];
            } else {
                return [];
            }
        } else {
            if($cart->getOrderTotal() >= Configuration::get('FINTREEN_MIN_SUM')) {
                $externalOption = new PaymentOption();
                $externalOption->setModuleName($this->name);
                $externalOption->setCallToActionText($this->displayName);
                $externalOption->setAction($this->context->link->getModuleLink($this->name, 'create', array(), true));
        
                $externalOption->setAdditionalInformation('Payment With Fintreen Crypto Gateway');
                //$externalOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/option/external.png'));

                return [$externalOption];
            } else {
                return [];
            }
        }

        
  }
  
   public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $rest_to_paid = $params['order']->getOrdersTotalPaid() - $params['order']->getTotalPaid();

        $this->smarty->assign([
            'total_to_pay' => $this->context->getCurrentLocale()->formatPrice(
                $rest_to_paid,
                (new Currency($params['order']->id_currency))->iso_code
            ),
            'shop_name' => $this->context->shop->name,
            'checkName' => $this->checkName,
            'checkAddress' => Tools::nl2br($this->address),
            'status' => 'ok',
            'id_order' => $params['order']->id,
            'reference' => $params['order']->reference,
        ]);

        return $this->fetch('views/return.tpl');
    }
  
 
  
  public function hookCreateTable()
  {
     // Получаем объект базы данных
        $db = Db::getInstance();

        // SQL-запрос для создания таблицы
        $sql = "
            CREATE TABLE IF NOT EXISTS "._DB_PREFIX_."fintreen_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fintreen_id BIGINT UNSIGNED,
                user_id INT UNSIGNED NULL,
                fiat_amount DECIMAL(35, 2) UNSIGNED NOT NULL,
                fintreen_fiat_code VARCHAR(255) DEFAULT 'EUR',
                crypto_amount DECIMAL(35, 12) UNSIGNED NOT NULL,
                fintreen_crypto_code VARCHAR(255),
                fintreen_status_id SMALLINT UNSIGNED DEFAULT 1,
                is_test SMALLINT UNSIGNED DEFAULT 0,
                link TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY fintreen_id_is_test_unique (fintreen_id, is_test)
            )
            ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8;
        ";

        // Выполняем SQL-запрос
        if (!$db->execute($sql)) {
            return false;
        }

        return true;
  }
  
  public function getContent()
  {
	$output = '';

    // this part is executed only when the form is submitted
    if (Tools::isSubmit('submit' . $this->name)) {
        // retrieve the value set by the user
        $fintreen_auth = (string) Tools::getValue('FINTREEN_AUTH');
		$fintreen_email = (string) Tools::getValue('FINTREEN_EMAIL');
        $fintreen_min_sum = (string) Tools::getValue('FINTREEN_MIN_SUM');

        // check that the value is valid
        if (empty($fintreen_auth) || !Validate::isCleanHtml($fintreen_auth)) {
            // invalid value, show an error
            $output = $this->displayError($this->l('Invalid Fintreen Auth key'));
        } else {
            // value is ok, update it and display a confirmation message
            Configuration::updateValue('FINTREEN_AUTH', $fintreen_auth);
            $output = $this->displayConfirmation($this->l('Settings updated'));
        }
		
		if (empty($fintreen_email) || !Validate::isEmail($fintreen_email)) {
            // invalid value, show an error
            $output = $this->displayError($this->l('Invalid Fintreen Email'));
        } else {
            // value is ok, update it and display a confirmation message
            Configuration::updateValue('FINTREEN_EMAIL', $fintreen_email);
            $output = $this->displayConfirmation($this->l('Settings updated'));
        }

        if (empty($fintreen_min_sum)) {
            // invalid value, show an error
            $output = $this->displayError($this->l('Invalid Min Sum'));
        } else {
            // value is ok, update it and display a confirmation message
            Configuration::updateValue('FINTREEN_MIN_SUM', $fintreen_min_sum);
            $output = $this->displayConfirmation($this->l('Settings updated'));
        }

        

    }

    // display any message, then the form
    return $output . $this->displayForm();
  }
  
  public function displayForm()
{
    // Init Fields form array
    $form = [
        'form' => [
            'legend' => [
                'title' => $this->l('Settings Fintreen'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Fintreen Auth key'),
                    'name' => 'FINTREEN_AUTH',
                    'size' => 30,
                    'required' => true,
                ],
				[
                    'type' => 'text',
                    'label' => $this->l('Fintreen email'),
                    'name' => 'FINTREEN_EMAIL',
                    'size' => 30,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Minimal sum of order'),
                    'name' => 'FINTREEN_MIN_SUM',
                    'size' => 30,
                    'required' => true,
                ],

            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            ],
        ],
    ];

    $helper = new HelperForm();

    // Module, token and currentIndex
    $helper->table = $this->table;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
    $helper->submit_action = 'submit' . $this->name;

    // Default language
    $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

    // Load current value into the form
    $helper->fields_value['FINTREEN_AUTH'] = Tools::getValue('FINTREEN_AUTH', Configuration::get('FINTREEN_AUTH'));
	$helper->fields_value['FINTREEN_EMAIL'] = Tools::getValue('FINTREEN_EMAIL', Configuration::get('FINTREEN_EMAIL'));
    $helper->fields_value['FINTREEN_MIN_SUM'] = Tools::getValue('FINTREEN_MIN_SUM', Configuration::get('FINTREEN_MIN_SUM'));
    

    return $helper->generateForm([$form]);
}





}
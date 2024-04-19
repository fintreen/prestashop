<?php 

use PrestaShop\PrestaShop\Core\Database\Db;

class FintreenCreateModuleFrontController extends ModuleFrontController {
	
	 public const DEFAULT_FIAT_CODE = 'EUR';

    protected $baseUrl = 'https://fintreen.com/';

    protected $suffix = 'api/v1/';

    protected bool $ignoreSslVerif = false;

    static private $fintreenCurrencies = [];

	public function initContent() 
	{
		parent::initContent();
		if($id_cart = Tools::getValue('id_cart')) {
			$cart = new Cart($id_cart);
			if(!Validate::isLoadedObject($cart)) {
				$cart = $this->context->cart;
			}
		} else {
			$cart = $this->context->cart;
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

			$params = [];
        	$params['fiatAmount'] = $converted_amount;
        	$params['fiatCode'] = 'EUR';
        	$params['cryptoCode'] = 'USDT-TRC20';
    	} else {
    		$params = [];
        	$params['fiatAmount'] = $cart->getOrderTotal();
        	$params['fiatCode'] = 'EUR';
        	$params['cryptoCode'] = 'USDT-TRC20';
    	}
		
		$this->sendRequest('create', 'POST', $params);
	
		$orderId = (int)Tools::getValue('id_order');
	
		$db = \Db::getInstance();
		$data = json_decode($this->response, true);

		

// Данные для добавления
$fintreen_id = $data['data']['id'];
$user_id = $orderId;
$fiat_amount = $cart->getOrderTotal();
$fintreen_fiat_code = 'EUR';
$crypto_amount = $data['data']['amount'];
$fintreen_crypto_code = $data['data']['cryptoCode'];
$fintreen_status_id = $data['data']['statusId'];
$is_test = 0;
$link = $data['data']['link'];

// Подготовка SQL-запроса
$sql = 'INSERT INTO `' . _DB_PREFIX_ . 'fintreen_transactions` 
        (`fintreen_id`, `user_id`, `fiat_amount`, `fintreen_fiat_code`, `crypto_amount`, `fintreen_crypto_code`, `fintreen_status_id`, `is_test`, `link`) 
        VALUES 
        (' . (int)$fintreen_id . ', ' . (int)$user_id . ', ' . (float)$fiat_amount . ', "' . pSQL($fintreen_fiat_code) . '", ' . (float)$crypto_amount . ', "' . pSQL($fintreen_crypto_code) . '", ' . (int)$fintreen_status_id . ', ' . (int)$is_test . ', "' . pSQL($link) . '")';



    Tools::redirect($data['data']['link']);
	}

	public function sendRequest(string $endpoint, string $method = 'GET', array $params = []): string|null|bool {
        $urlToSend = $this->baseUrl . $this->suffix . $endpoint;
        if ($params) {
            ksort($params);
        }

        if ($params) {
            $buildedParams = http_build_query($params);
            $urlToSend .= '?' . $buildedParams;
        }

        $curl = curl_init($urlToSend);

        $headers = [
            'fintreen_auth: ' . Configuration::get('FINTREEN_AUTH'),
            'fintreen_signature: ' . sha1(Configuration::get('FINTREEN_AUTH') . Configuration::get('FINTREEN_EMAIL')),
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $curl_options = [
            CURLOPT_URL => $urlToSend,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers
        ];
        if ($this->ignoreSslVerif) {
            $curl_options[CURLOPT_SSL_VERIFYHOST] = 0;
            $curl_options[CURLOPT_SSL_VERIFYPEER] = false;
        } else {
            $curl_options[CURLOPT_SSL_VERIFYHOST] = 2;
            $curl_options[CURLOPT_SSL_VERIFYPEER] = true;
        }

        if ($method == 'GET') {
            $curl_options[CURLOPT_HTTPGET] = true;
        } else {
            $curl_options[CURLOPT_POSTFIELDS] = $buildedParams;
            $curl_options[CURLOPT_POST] = true;
        }

        curl_setopt_array($curl, $curl_options);
        $this->response = curl_exec($curl);
        $this->info = curl_getinfo($curl);
        $this->errno = curl_errno($curl);
        $this->error = curl_error($curl);

        return $this->response;
    }
}





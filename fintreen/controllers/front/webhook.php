<?php

use PrestaShop\PrestaShop\Core\Database\Db;

class FintreenWebhookModuleFrontController extends ModuleFrontController {
	public function initContent()
	{
		$input = file_get_contents('php://input');
		$data = json_decode($input);
		$id = $data['transaction_id'];
		
		$fintreen_id = $id; // Пример значения fintreen_id
		$sql = "SELECT user_id
        FROM `" . _DB_PREFIX_ . "fintreen_transactions`
        WHERE fintreen_id = $fintreen_id";
		
		$new_state = 2;

		// Выполнение запроса
		$result = \Db::getInstance()->executeS($sql);

 		$order = new Order($result);
          
        $history  = new OrderHistory();
        $history->id_order = (int)$order->id;
        $history->changeIdOrderState((int) $new_state, $order->id);
        $history->save();
	}
}
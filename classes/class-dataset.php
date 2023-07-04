<?php

namespace Woo_Posti_Warehouse;

defined('ABSPATH') || exit;

class Dataset {
	public static function getStoreTypes() {
		return array(
			'Store' => Text::type_store(),
			'Posti' => Text::type_warehouse(),
			'Not_in_stock' => Text::type_none(),
			'Catalog' => Text::type_dropshipping(),
		);
	}

	public static function getDeliveryTypes() {
		return array(
			'WAREHOUSE' => Text::type_warehouse(),
			'DROPSHIPPING' => Text::type_dropshipping(),
		);
	}
	
	public static function getServicesTypes() {
		return array(
			'_posti_lq' => Text::feature_lq(),
			'_posti_large' => Text::feature_large(),
			'_posti_fragile' => Text::feature_fragile(),
		);
	}
}

<?php

namespace Posti_Warehouse;

defined('ABSPATH') || exit;

class Posti_Warehouse_Dataset {
	public static function getStoreTypes() {
		return array(
			'Store' => Posti_Warehouse_Text::type_store(),
			'Posti' => Posti_Warehouse_Text::type_warehouse(),
			'Not_in_stock' => Posti_Warehouse_Text::type_none(),
			'Catalog' => Posti_Warehouse_Text::type_dropshipping(),
		);
	}

	public static function getDeliveryTypes() {
		return array(
			'WAREHOUSE' => Posti_Warehouse_Text::type_warehouse(),
			'DROPSHIPPING' => Posti_Warehouse_Text::type_dropshipping(),
		);
	}
	
	public static function getServicesTypes() {
		return array(
			'_posti_lq' => Posti_Warehouse_Text::feature_lq(),
			'_posti_large' => Posti_Warehouse_Text::feature_large(),
			'_posti_fragile' => Posti_Warehouse_Text::feature_fragile(),
		);
	}
}

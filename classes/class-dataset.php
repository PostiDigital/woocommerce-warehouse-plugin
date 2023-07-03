<?php


namespace Woo_Posti_Warehouse;

class Dataset {
    public static function getStoreTypes(){
        return array(
            'Store' => __('Store', 'posti-warehouse'),
            'Posti' => __('Posti Warehouse', 'posti-warehouse'),
            'Not_in_stock' => __('Not in stock', 'posti-warehouse'),
            'Catalog' => __('Dropshipping', 'posti-warehouse'),
        );
    }

    public static function getDeliveryTypes(){
        return array(
            'WAREHOUSE' => __('Posti Warehouse', 'posti-warehouse'),
            'DROPSHIPPING' => __('Dropshipping', 'posti-warehouse'),
        );
    }
    
    public static function getServicesTypes(){
        return array(
            '_posti_lq' => __('LQ Process permission', 'posti-warehouse'),
            '_posti_large' => __('Large', 'posti-warehouse'),
            '_posti_fragile' => __('Fragile', 'posti-warehouse'),
        );
    }
}

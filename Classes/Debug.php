<?php

namespace PostiWarehouse\Classes;

use PostiWarehouse\Classes\Logger;

class Debug {
    
    private $is_test = false;
    
    public function __construct(array &$options) {
        $this->is_test = Settings::is_test($options);
        add_action('admin_menu', array($this, 'posti_wh_debug_page'));
    }
    
    public function posti_wh_debug_page() {
        add_submenu_page(
                'options-general.php',
                __('Posti Warehouse Debug', 'posti-warehouse'),
                __('Posti Warehouse Debug', 'posti-warehouse'),
                'manage_options',
                'posti_wh_debug',
                array($this, 'posti_wh_debug_page_html')
        );
    }
    
    public function posti_wh_debug_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php $token_data = get_option('posti_wh_api_auth'); ?>
            <?php if (is_array($token_data)): ?>
                <div class="notice notice-info">
                    <p style = "word-break: break-all;"><strong><?php _e('Current token:', 'posti-warehouse'); ?><br/> </strong> <?= $token_data['token']; ?></p>
                    <p><strong><?php _e('Token expiration:', 'posti-warehouse'); ?> </strong> <?= date('Y-m-d H:i:s', $token_data['expires']); ?></p>
                </div>
            <?php endif; ?>
            <?php
            $logger = new Logger();
            $logs = $logger->getLogs();
            ?>
            <?php if (count($logs)): ?>
                <h3><?php _e('Logs', 'posti-warehouse'); ?></h3>
                <table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="manage-column column-columnname " style = "width: 150px" scope="col"><?php _e('Created', 'posti-warehouse'); ?></th> 
                            <th class="manage-column column-columnname" style = "width: 80px" scope="col"><?php _e('Type', 'posti-warehouse'); ?></th>
                            <th class="manage-column column-columnname " scope="col"><?php _e('Message', 'posti-warehouse'); ?></th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $key => $log): ?>
                            <tr class="<?= ($key % 2 == 0?'alternate':'');?>">
                                <td class="column-columnname"><?= $log->created_at; ?></td>
                                <td class="column-columnname"><?= $log->type; ?></td>
                                <td class="column-columnname"><?= nl2br(esc_html($log->message)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <h3><?php _e('No logs found', 'posti-warehouse'); ?></h3>
            <?php endif; ?>
        </div>
        <?php
    }
}

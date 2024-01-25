<?php
/****************************************************************
 * Pickup point select field in Checkout page
 *
 * Variables:
 *   (string) $nonce - Value for nonce field
 *   (array) $error - Error message
 *   (array) $pickup - Pickup point field
 *   (array) $custom - Custom pickup point address field
 ***************************************************************/
defined('ABSPATH') || exit;
?>

<tr class="shipping-pickup-point">
  <th><?php echo esc_html($pickup['title']); ?></th>
  <td data-title="<?php echo esc_attr($pickup['title']); ?>">
	<input type="hidden" name="posti_warehouse_nonce" value="<?php echo esc_attr($nonce); ?>" id="posti_warehouse_pickup_point_update_nonce"/>
	<?php if ( ! empty($error['msg']) ) : ?>
	  <p class="error-pickup"><?php echo esc_html($error['msg']); ?></p>
	  <input type='hidden' name='<?php echo esc_attr($error['name']); ?>' value='__NULL__'>
	<?php endif; ?>
	<?php if ( $pickup['show'] ) : ?>
	  <span><?php echo esc_html($pickup['desc']); ?></span>
	  <?php woocommerce_form_field($pickup['field']['name'], $pickup['field']['data'], $pickup['field']['value']); ?>
	<?php endif; ?>
  </td>
</tr>
<?php if ( $custom['show'] ) : ?>
  <tr class="shipping-custom-pickup-point">
	<th><?php echo esc_html($custom['title']); ?></th>
	<td data-title="<?php echo esc_attr($custom['title']); ?>">
	  <?php woocommerce_form_field($custom['field']['name'], $custom['field']['data'], $custom['field']['value']); ?>
	  <button type="button" onclick="warehouse_custom_pickup_point_change(posti_warehousecustom_pickup_point)" class="btn" id="posti_warehousecustom_pickup_point_btn"><i class="fa fa-search"></i><?php esc_html_e('Search', 'posti-warehouse'); ?></button>
	  <?php if ( ! empty($custom['desc']) ) : ?>
		<p><?php echo esc_html($custom['desc']); ?></p>
	  <?php endif; ?>
	</td>
  </tr>
<?php endif; ?>

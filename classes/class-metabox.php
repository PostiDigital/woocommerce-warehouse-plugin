<?php
namespace Posti_Warehouse;

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

class Posti_Warehouse_Metabox {

	private $postiOrder = false;
	
	private $error = '';

	public function __construct(Posti_Warehouse_Order $order) {
		$this->postiOrder = $order;
		add_action('add_meta_boxes', array($this, 'add_order_meta_box'), 10, 2);
//		add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
		add_action('wp_ajax_posti_order_meta_box', array($this, 'parse_ajax_meta_box'));
	}
	
	public function add_order_meta_box( $type, $post_or_order_object) {
		$screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')
				&& wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		$order = $post_or_order_object instanceof WP_Post ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
		if ($this->postiOrder->hasPostiProducts($order)) {
			add_meta_box(
				'posti_order_box_id',
				'Posti Order',
				array($this, 'add_order_meta_box_html'),
				$screen,
				'side',
				'high');
		}
	}

	public function add_order_meta_box_html( $post_or_order_object) {
		?>
		<div id ="posti-order-metabox">
			<input type="hidden" name="posti_order_metabox_nonce" value="<?php echo esc_attr(wp_create_nonce(str_replace('wc_', '', 'posti-order') . '-meta-box')); ?>" id="posti_order_metabox_nonce" />
			<img src ="<?php echo esc_attr(plugins_url('assets/img/posti-orange.png', dirname(__FILE__))); ?>"/>
			<label><?php echo esc_html(Posti_Warehouse_Text::order_status()); ?> </label>

			<?php
				$order = $post_or_order_object instanceof WP_Post ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
				$status = Posti_Warehouse_Text::order_not_placed();
				$warehouse_order = $this->postiOrder->getOrder($order);
				if ($warehouse_order) {
					$status = isset($warehouse_order['status']['value']) ? $warehouse_order['status']['value'] : '';
					$autoSubmit = isset($warehouse_order['preferences']['autoSubmit']) ? $warehouse_order['preferences']['autoSubmit'] : true;

					// Special review case, parallel to main order status
					if ($autoSubmit === false && in_array($status, ["Created", "Viewed"], true)) {
						$status = "Review";
					}
				}

				echo '<strong id = "posti-order-status">' . esc_html($status) . "</strong><br/>";
				if (!$warehouse_order || $status === 'Cancelled') {
					echo '<button type = "button" class="button button-posti" id = "posti-order-btn" name="posti_order_action"  onclick="posti_order_change(this);" value="place_order">' . esc_html(Posti_Warehouse_Text::order_place()) . "</button>";
				}
				elseif ($status === 'Review') {
					echo '<button type = "button" class="button button-posti" id = "posti-order-btn" name="posti_order_action"  onclick="posti_order_change(this);" value="submit_order">' . esc_html(Posti_Warehouse_Text::order_place()) . "</button>";
				}
			?>

			<?php if ($this->error) : ?>
			<div>
				<b style="color: red"><?php echo esc_html($this->error); ?></b>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function parse_ajax_meta_box() {
		
		check_ajax_referer(str_replace('wc_', '', 'posti-order') . '-meta-box', 'security');

		if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
			wp_die('', '', 501);
		}
		
		$post_id = sanitize_key($_POST['post_id']);
		$post_action = isset($_POST['order_action']) ? sanitize_key($_POST['order_action']) : '';
		$post = wc_get_order($post_id);
		if (!empty($post_action)) {
			$result = null;
			if ('place_order' === $post_action) {
				$result = $this->postiOrder->addOrder($post);
			}
			elseif ('submit_order' === $post_action) {
				$result = $this->postiOrder->submitOrder($post, true);
			}

			$this->error = isset($result['error']) ? $result['error'] : '';
			$this->add_order_meta_box_html($post);
			wp_die('', '', 200);
		}
		$this->error = Posti_Warehouse_Text::error_generic();
		$this->add_order_meta_box_html($post);
		wp_die('', '', 200);
	}

}

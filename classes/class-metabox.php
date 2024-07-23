<?php
namespace Posti_Warehouse;

defined('ABSPATH') || exit;

class Posti_Warehouse_Metabox {

	private $postiOrder = false;
	
	private $error = '';

	public function __construct(Posti_Warehouse_Order $order) {
		$this->postiOrder = $order;
		add_action('add_meta_boxes', array($this, 'add_order_meta_box'), 10, 2);
		add_action('wp_ajax_posti_order_meta_box', array($this, 'parse_ajax_meta_box'));
	}

	public function add_order_meta_box( $type, $post) {
		if ($this->postiOrder->hasPostiProducts($post->ID)) {
			foreach (wc_get_order_types('order-meta-boxes') as $type) {
				add_meta_box(
						'posti_order_box_id',
						'Posti Order',
						array($this, 'add_order_meta_box_html'),
						$type,
						'side',
						'default');
			}
		}
	}

	public function add_order_meta_box_html( $post) {
		?>
		<div id ="posti-order-metabox">
			<input type="hidden" name="posti_order_metabox_nonce" value="<?php echo esc_attr(wp_create_nonce(str_replace('wc_', '', 'posti-order') . '-meta-box')); ?>" id="posti_order_metabox_nonce" />
			<img src ="<?php echo esc_attr(plugins_url('assets/img/posti-orange.png', dirname(__FILE__))); ?>"/>
			<label><?php echo esc_html(Posti_Warehouse_Text::order_status()); ?> </label>

			<?php
				$status = Posti_Warehouse_Text::order_not_placed();
				$order = $this->postiOrder->getOrder($post);
				if ($order) {
					$status = isset($order['status']['value']) ? $order['status']['value'] : '';
					$autoSubmit = isset($order['preferences']['autoSubmit']) ? $order['preferences']['autoSubmit'] : true;

					// Special review case, parallel to main order status
					if ($autoSubmit === false && in_array($status, ["Created", "Viewed"], true)) {
						$status = "Review";
					}
				}

				echo '<strong id = "posti-order-status">' . esc_html($status) . "</strong><br/>";
				if (!$order || $status === 'Cancelled') {
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

<?php
namespace Woo_Posti_Warehouse;

defined('ABSPATH') || exit;

class Metabox {

	private $postiOrder = false;
	
	private $error = '';

	public function __construct( Order $order) {
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
			<label><?php echo esc_html(Text::order_status()); ?> </label>
			<strong id = "posti-order-status"><?php echo esc_html($this->postiOrder->getOrderStatus($post->ID)); ?></strong>
			<br/>
			<div id = "posti-order-action">
				<?php $this->postiOrder->getOrderActionButton($post->ID); ?>
			</div>
			<?php if ($this->error) : ?>
			<div>
				<?php echo esc_html($this->error); ?>
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
		$post = get_post($post_id);
		if (isset($_POST['order_action']) && 'place_order' == $_POST['order_action']) {
			$result = $this->postiOrder->addOrder($post_id); 
			$this->error = isset($result['error']) ? $result['error'] : '';
			$this->add_order_meta_box_html($post);
			wp_die('', '', 200);
		}

		$this->error = Text::error_generic();
		$this->add_order_meta_box_html($post);
		wp_die('', '', 200);
	}

}

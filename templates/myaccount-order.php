<?php
/****************************************************************
 * Selected pickup point value in My Account Order preview page
 *
 * Variables:
 *   (string) $pickup_point - Selected pickup point
 ***************************************************************/
defined('ABSPATH') || exit;
?>

<h2><?php esc_html($texts['title']); ?></h2>
<p><?php echo esc_html($pickup_point); ?></p>

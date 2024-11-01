<?php
/**
 * Plugin Name: Woo Sale Price
 * Plugin URI: http://ldav.it/shop/
 * Description: It records in WooCommerce orders the product discount, calculated by difference between regular price and the sale price. Discount is shown before and after checkout (cart, notices, invoices, ...).
 * Version: 0.3
 * Author: laboratorio d'Avanguardia
 * Author URI: http://ldav.it/
 * Requires at least: 4.4
 * Tested up to: 6.6.2
 *
 * Text Domain: woo-sale-price
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 7.7.2
 * License: GPLv2 or later
 * License URI: http://www.opensource.org/licenses/gpl-license.php
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( !class_exists( 'Woo_Sale_Price' ) ) :
define('Woo_SALEPRC_DOMAIN', 'woo-sale-price');
	
class Woo_Sale_Price {
	public $plugin_basename;
	public $plugin_url;
	public $plugin_path;
	public $version = '0.3';
	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) self::$instance = new self();
		return self::$instance;
	}

	public function __construct() {
		$this->plugin_basename = plugin_basename(__FILE__);
		$this->plugin_url = plugin_dir_url($this->plugin_basename);
		$this->plugin_path = trailingslashit(dirname(__FILE__));
		add_action( 'init', array( $this, 'init_hooks' ), 0 );
	}

	public function init_hooks() {
		if ($this->is_wc_active()) {
			add_filter('woocommerce_cart_item_price', array( $this, 'cart_item_price'), 30, 3 );
			add_action('woocommerce_checkout_create_order_line_item', array( $this, 'checkout_create_order_line_item'), 30, 4 );
			add_action('woocommerce_checkout_create_order', array( $this, 'checkout_create_order'), 30, 2 );
			add_action( 'before_woocommerce_init', function() {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}
			} );
		} else {
			add_action( 'admin_notices', array ( $this, 'check_wc' ) );
		}
	}

	public function is_wc_active() {
		$plugins = get_site_option( 'active_sitewide_plugins', array());
		if (in_array('woocommerce/woocommerce.php', get_option( 'active_plugins', array())) || isset($plugins['woocommerce/woocommerce.php'])) {
			return true;
		} else {
			return false;
		}
	}

	public function check_wc( $fields ) {
		$class = "error";
		$message = sprintf( __( 'Woo Sale Price requires %sWooCommerce%s 3.0+ to be installed and activated!' , Woo_SALEPRC_DOMAIN ), '<a href="https://wordpress.org/plugins/woocommerce/">', '</a>' );
		echo"<div class=\"$class\"> <p>$message</p></div>";
	}	

 	public function checkout_create_order($order, $data) {
		if(WC()->customer->is_vat_exempt()) return;
		$order->calculate_taxes();
		$order->calculate_totals();
	}

 	public function checkout_create_order_line_item($item, $cart_item_key, $values, $order) {
		if($item->get_type() == "line_item") {
			$_product = $item->get_product();
			$regular_price = $_product->get_regular_price();
			$sale_price = $_product->get_sale_price();
			$totaltax = $item->get_total_tax();
			if($regular_price != $sale_price && (float)$totaltax != 0) {
				$qty = $item->get_quantity();
				$tax = $totaltax / $item->get_total();
				$item->set_subtotal( $regular_price * $qty / (1 + $tax) );
			}
		}
	}
 
	public function cart_item_price( $price, $values, $cart_item_key ) {
		$slashed_price = $values['data']->get_price_html();
		$is_on_sale = $values['data']->is_on_sale();
		if ($is_on_sale) {
			$price = $slashed_price;
		}
		return $price;
	}

}
endif;

$Woo_Sale_Price = new Woo_Sale_Price();

?>
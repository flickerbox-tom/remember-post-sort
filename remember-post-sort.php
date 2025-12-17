<?php
/**
 * Plugin Name: Remember Post Sort Order
 * Plugin URI: https://github.com/flickerbox-tom/remember-post-sort
 * Description: Automatically remembers and applies the last used sort order for each post type in the WordPress admin for each user. 
 * Version: 1.0.0
 * Author: Tom Risse
 * Author URI: https://www.fuzzyraygun.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: remember-post-sort
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

class Remember_Post_Sort_Order {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Save the sort order when user sorts posts
		add_action('load-edit.php', array($this, 'handle_sort_order'));

		// Add reset button to admin
		add_action('restrict_manage_posts', array($this, 'add_reset_button'));

		// Handle reset action
		add_action('load-edit.php', array($this, 'handle_reset'));
	}

	/**
	 * Handle saving and applying sort order
	 */
	public function handle_sort_order() {
		global $typenow;

		// Get the post type
		$post_type = $typenow ? $typenow : (isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post');
		$current_user_id = get_current_user_id();

		// Check if user is actively sorting (orderby in URL)
		if (isset($_GET['orderby']) && !isset($_GET['action'])) {
			$orderby = sanitize_text_field($_GET['orderby']);
			$order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'asc';

			// Save to user meta
			update_user_meta($current_user_id, "post_sort_orderby_{$post_type}", $orderby);
			update_user_meta($current_user_id, "post_sort_order_{$post_type}", $order);

			// Don't redirect, let WordPress handle the sort
			return;
		}

		// Check if we should apply saved sort order
		// Only apply if there's no orderby in URL and no other actions
		if (!isset($_GET['orderby']) && !isset($_GET['action']) && !isset($_GET['_wp_http_referer'])) {
			$saved_orderby = get_user_meta($current_user_id, "post_sort_orderby_{$post_type}", true);
			$saved_order = get_user_meta($current_user_id, "post_sort_order_{$post_type}", true);

			// If we have a saved sort order, redirect with those parameters
			if (!empty($saved_orderby)) {
				$redirect_url = add_query_arg(array(
					'post_type' => $post_type !== 'post' ? $post_type : false,
					'orderby' => $saved_orderby,
					'order' => $saved_order
				), admin_url('edit.php'));

				wp_redirect($redirect_url);
				exit;
			}
		}
	}

	/**
	 * Add reset button to the post list screen
	 */
	public function add_reset_button($post_type) {
		$current_user_id = get_current_user_id();
		$saved_orderby = get_user_meta($current_user_id, "post_sort_orderby_{$post_type}", true);

		// Only show reset button if there's a saved sort order
		if (!empty($saved_orderby)) {
			$reset_url = add_query_arg(array(
				'action' => 'reset_post_sort',
				'post_type' => $post_type,
				'reset_nonce' => wp_create_nonce('reset_post_sort_' . $post_type)
			));

			echo '<a href="' . esc_url($reset_url) . '" class="button" style="margin-left: 5px;">';
			echo __('Reset Sort Order', 'remember-post-sort');
			echo '</a>';
		}
	}

	/**
	 * Handle reset action
	 */
	public function handle_reset() {
		// Check if reset action is triggered
		if (!isset($_GET['action']) || $_GET['action'] !== 'reset_post_sort') {
			return;
		}

		// Verify nonce
		$post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';
		if (!isset($_GET['reset_nonce']) || !wp_verify_nonce($_GET['reset_nonce'], 'reset_post_sort_' . $post_type)) {
			wp_die(__('Security check failed', 'remember-post-sort'));
		}

		// Delete saved sort order
		$current_user_id = get_current_user_id();
		delete_user_meta($current_user_id, "post_sort_orderby_{$post_type}");
		delete_user_meta($current_user_id, "post_sort_order_{$post_type}");

		// Redirect back to post list without the reset parameters
		$redirect_url = admin_url('edit.php');
		if ($post_type !== 'post') {
			$redirect_url = add_query_arg('post_type', $post_type, $redirect_url);
		}

		wp_redirect($redirect_url);
		exit;
	}
}

// Initialize the plugin
new Remember_Post_Sort_Order();

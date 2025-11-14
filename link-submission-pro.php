<?php
/**
 * Plugin Name:       Link Submission Pro
 * Plugin URI:        https://github.com/chrismccoy/link-submission-pro
 * Description:       This Provides an AJAX-powered front-end form for users to submit links, with an admin dashboard to approve or deny them.
 * Version:           1.0.0
 * Author:            Chris McCoy
 * Author URI:        https://github.com/chrismccoy
 * Text Domain:       link-submission-pro
 *
 * @package Link_Submission_Pro
 */

declare(strict_types = 1);

if ( ! defined('WPINC') ) {
	die;
}

// The WP_List_Table class is not loaded by default on all admin screens.
if ( ! class_exists('WP_List_Table') ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Creates a custom list table for displaying and managing link submissions.
 */
class LSP_List_Table extends WP_List_Table {

	/**
	 * Sets up the singular, plural, and ajax properties for the list table.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'link_submission',
				'plural'   => 'link_submissions',
				'ajax'     => false, // We are not using AJAX for table pagination/sorting.
			]
		);
	}

	/**
	 * Retrieves the submission data from the database for the table.
	 */
	private function get_table_data(): array {
		global $wpdb;
		$table_name    = $wpdb->prefix . 'lsp_link_submissions';
		$sql           = "SELECT * FROM {$table_name}";
		$where_clauses = [];

		// Dynamically build the WHERE clause based on URL filters.
		if (
			isset($_GET['status']) &&
			in_array($_GET['status'], ['pending', 'approved', 'denied', 'banned'], true)
		) {
			$where_clauses[] = $wpdb->prepare(
				'status = %s',
				sanitize_text_field(wp_unslash($_GET['status']))
			);
		}
		if ( ! empty($_GET['category_id']) ) {
			$where_clauses[] = $wpdb->prepare(
				'category_id = %d',
				absint(wp_unslash($_GET['category_id']))
			);
		}

		if ( ! empty($where_clauses) ) {
			$sql .= ' WHERE ' . implode(' AND ', $where_clauses);
		}

		// Handle sorting based on user clicks on column headers.
		$sortable_columns = array_keys($this->get_sortable_columns());
		$orderby = ( isset($_GET['orderby']) && in_array($_GET['orderby'], $sortable_columns, true) ) ? sanitize_key($_GET['orderby']) : 'submitted_at';
		$order = ( isset($_GET['order']) && in_array(strtolower($_GET['order']), ['asc', 'desc'], true) ) ? strtoupper($_GET['order']) : 'DESC';

		$sql .= $wpdb->prepare(' ORDER BY %s %s', $orderby, $order);

		return $wpdb->get_results($sql, ARRAY_A) ?? [];
	}

	/**
	 * Defines the table columns.
	 */
	public function get_columns(): array {
		return [
			'cb'           => '<input type="checkbox" />',
			'link_text'    => __('Link Text / URL', 'link-submission-pro'),
			'user_name'    => __('Submitted By', 'link-submission-pro'),
			'category'     => __('Category', 'link-submission-pro'),
			'submitted_at' => __('Date', 'link-submission-pro'),
			'status'       => __('Status', 'link-submission-pro'),
		];
	}

	/**
	 * Defines which columns are sortable.
	 */
	protected function get_sortable_columns(): array {
		return [
			'link_text'    => ['link_text', false],
			'user_name'    => ['user_name', false],
			'category'     => ['category_id', false],
			'submitted_at' => ['submitted_at', true], // Default sort column.
			'status'       => ['status', false],
		];
	}

	/**
	 * Defines the filter views (e.g., All, Pending, Banned).
	 */
	protected function get_views(): array {
		global $wpdb;
		$table_name     = $wpdb->prefix . 'lsp_link_submissions';
		$current_status = ! empty($_REQUEST['status']) ? sanitize_text_field(wp_unslash($_REQUEST['status'])) : 'all';
		$base_url       = admin_url('admin.php?page=link-submissions');

		// Optimized Query: Get all status counts in a single database call.
		$sql    = "SELECT status, COUNT(id) as count FROM {$table_name} GROUP BY status";
		$counts = $wpdb->get_results($sql, ARRAY_A);

		$status_counts = [
			'pending'  => 0,
			'approved' => 0,
			'denied'   => 0,
			'banned'   => 0,
		];

		foreach ( $counts as $row ) {
			if ( array_key_exists($row['status'], $status_counts) ) {
				$status_counts[ $row['status'] ] = (int) $row['count'];
			}
		}
		$total_count = array_sum($status_counts);

		$views = [
			'all'      => sprintf('<a href="%s" class="%s">%s (%d)</a>', $base_url, ($current_status === 'all') ? 'current' : '', __('All', 'link-submission-pro'), $total_count),
			'pending'  => sprintf('<a href="%s" class="%s">%s (%d)</a>', add_query_arg('status', 'pending', $base_url), ($current_status === 'pending') ? 'current' : '', __('Pending', 'link-submission-pro'), $status_counts['pending']),
			'approved' => sprintf('<a href="%s" class="%s">%s (%d)</a>', add_query_arg('status', 'approved', $base_url), ($current_status === 'approved') ? 'current' : '', __('Approved', 'link-submission-pro'), $status_counts['approved']),
			'denied'   => sprintf('<a href="%s" class="%s">%s (%d)</a>', add_query_arg('status', 'denied', $base_url), ($current_status === 'denied') ? 'current' : '', __('Denied', 'link-submission-pro'), $status_counts['denied']),
			'banned'   => sprintf('<a href="%s" class="%s">%s (%d)</a>', add_query_arg('status', 'banned', $base_url), ($current_status === 'banned') ? 'current' : '', __('Banned', 'link-submission-pro'), $status_counts['banned']),
		];

		return $views;
	}

	/**
	 * Defines the bulk actions available for the list table.
	 */
	protected function get_bulk_actions(): array {
		return [
			'approve'   => __('Approve', 'link-submission-pro'),
			'unapprove' => __('Unapprove', 'link-submission-pro'),
			'deny'      => __('Deny', 'link-submission-pro'),
			'ban'       => __('Ban', 'link-submission-pro'),
			'delete'    => __('Delete', 'link-submission-pro'),
		];
	}

	/**
	 * Prepares the items for the table display.
	 */
	public function prepare_items(): void {
		$this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
		$this->items           = $this->get_table_data();
	}

	/**
	 * Handles the default column output if no specific method exists.
	 */
	protected function column_default($item, $column_name): string {
		return isset($item[$column_name]) ? esc_html((string) $item[$column_name]) : '';
	}

	/**
	 * Renders the checkbox column for bulk actions.
	 */
	protected function column_cb($item): string {
		return sprintf(
			'<input type="checkbox" name="submission[]" value="%d" />',
			absint($item['id'])
		);
	}

	/**
	 * Renders the 'link_text' column, including row actions.
	 */
	protected function column_link_text($item): string {
		$content = sprintf(
			'<strong><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></strong><br><small>%s</small>',
			esc_url($item['url']),
			esc_html($item['link_text']),
			esc_html($item['url'])
		);

		// Only show row actions for items that are still pending moderation.
		if ( 'pending' === $item['status'] ) {
			$actions = [
				'lsp-approve' => sprintf('<a href="#" class="lsp-action-btn" data-id="%d" data-status="approved" style="color:#0071a1;">%s</a>', absint($item['id']), __('Approve', 'link-submission-pro')),
				'lsp-deny'    => sprintf('<a href="#" class="lsp-action-btn" data-id="%d" data-status="denied" style="color:#a00;">%s</a>', absint($item['id']), __('Deny', 'link-submission-pro')),
				'lsp-ban'     => sprintf('<a href="#" class="lsp-action-btn" data-id="%d" data-status="banned" style="color:#3c434a;">%s</a>', absint($item['id']), __('Ban', 'link-submission-pro')),
			];
			return $content . $this->row_actions($actions);
		}
		return $content;
	}

	/**
	 * Renders the 'category' column as a filterable link.
	 */
	protected function column_category($item): string {
		$category_id = absint($item['category_id']);
		if ( ! $category_id ) {
			return '—';
		}

		$category = get_term($category_id, 'link_category');
		if ( is_wp_error($category) || ! $category ) {
			return '—';
		}

		// Make the category clickable to filter the list table.
		$filter_url = add_query_arg(
			[
				'page'        => 'link-submissions',
				'category_id' => $category_id,
			],
			admin_url('admin.php')
		);
		return sprintf('<a href="%s">%s</a>', esc_url($filter_url), esc_html($category->name));
	}

	/**
	 * Renders the 'status' column with colored text for better visibility.
	 */
	protected function column_status($item): string {
		$status = esc_html(ucfirst($item['status']));
		$color = match ($item['status']) {
			'approved' => '#00a32a', // Green
			'denied' => '#d63638',   // Red
			'banned' => '#1d2327',   // Dark Gray
			default => '#e69a15',    // Orange (Pending)
		};
		return sprintf('<span class="lsp-status" style="font-weight:bold; color:%s;">%s</span>', esc_attr($color), $status);
	}
}

/**
 * Link Submission Pro.
 */
final class Link_Submission_Pro {

	private static ?Link_Submission_Pro $instance = null;

	/**
	 * Get the single instance of the class.
	 */
	public static function get_instance(): Link_Submission_Pro {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers all hooks and filters.
	 */
	private function __construct() {
		// Frontend hooks.
		add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
		add_shortcode('link_submission_form', [$this, 'render_form_shortcode']);
		add_action('wp_ajax_lsp_submit_link', [$this, 'handle_form_submission']);
		add_action('wp_ajax_nopriv_lsp_submit_link', [$this, 'handle_form_submission']);

		// Admin hooks.
		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_action('admin_notices', [$this, 'display_admin_notices']);
		add_action('wp_ajax_lsp_update_status', [$this, 'handle_status_update']);

		// This hook needs to fire early to process redirects before headers are sent.
		add_action('load-toplevel_page_link-submissions', [$this, 'process_bulk_actions']);
	}

	/**
	 * Handles plugin activation, primarily database setup.
	 */
	public static function activate(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'lsp_link_submissions';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			submitted_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			url varchar(255) NOT NULL,
			link_text varchar(255) NOT NULL,
			user_name varchar(100) NOT NULL,
			user_email varchar(100) NOT NULL,
			category_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			link_manager_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			banned_host varchar(255) DEFAULT '' NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY category_id (category_id),
			KEY banned_host (banned_host)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Enqueues front end scripts and styles.
	 */
	public function enqueue_frontend_assets(): void {
		wp_enqueue_style(
			'lsp-styles',
			plugin_dir_url(__FILE__) . 'assets/css/lsp-styles.css',
			[],
			'1.0.0'
		);
		wp_enqueue_script(
			'lsp-frontend-js',
			plugin_dir_url(__FILE__) . 'assets/js/lsp-frontend.js',
			['jquery'],
			'1.0.0',
			true
		);
		wp_localize_script(
			'lsp-frontend-js',
			'lsp_ajax',
			[
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('lsp_submit_nonce'),
			]
		);
	}

	/**
	 * Renders the submission form by loading a template file.
	 */
	public function render_form_shortcode(): string {
		ob_start();
		include $this->locate_template('submission-form.php');
		return ob_get_clean();
	}

	/**
	 * Locates a template file, allowing for theme overrides.
	 *
	 * Checks the theme directory first before falling back to the plugin's
	 * default template directory.
	 */
	private function locate_template(string $template_name): string {
		// Look for template in: /your-theme/link-submission-pro/
		$theme_template_path = get_stylesheet_directory() . '/link-submission-pro/' . $template_name;
		if ( file_exists($theme_template_path) ) {
			return $theme_template_path;
		}
		// Fallback to plugin's default template.
		return plugin_dir_path(__FILE__) . 'templates/' . $template_name;
	}

	/**
	 * Handles the AJAX form submission with robust validation.
	 */
	public function handle_form_submission(): void {
		// Security Checks.
		if ( ! check_ajax_referer('lsp_submit_nonce', '_wpnonce', false) ) {
			wp_send_json_error(['message' => __('Security check failed.', 'link-submission-pro')], 403);
		}
		// Honeypot check for bots.
		if ( ! empty($_POST['lsp_website']) ) {
			wp_send_json_error(['message' => __('Spam detected.', 'link-submission-pro')], 400);
		}

		// Sanitize and Validate Input.
		$errors = [];
		$post_data = wp_unslash($_POST);

		$url = isset($post_data['lsp_url']) ? esc_url_raw(trim($post_data['lsp_url'])) : '';
		if ( empty($url) || ! filter_var($url, FILTER_VALIDATE_URL) ) {
			$errors[] = __('Please enter a valid URL.', 'link-submission-pro');
		} elseif ( $this->is_domain_banned($url) ) {
			wp_send_json_error(['message' => __('This domain has been banned from submission.', 'link-submission-pro')], 403);
		}

		$link_text = isset($post_data['lsp_link_text']) ? sanitize_text_field(trim($post_data['lsp_link_text'])) : '';
		if ( empty($link_text) ) {
			$errors[] = __('Please enter link text.', 'link-submission-pro');
		}

		$user_name = isset($post_data['lsp_user_name']) ? sanitize_text_field(trim($post_data['lsp_user_name'])) : '';
		if ( empty($user_name) ) {
			$errors[] = __('Please enter your name.', 'link-submission-pro');
		}

		$user_email = isset($post_data['lsp_user_email']) ? sanitize_email(trim($post_data['lsp_user_email'])) : '';
		if ( ! is_email($user_email) ) {
			$errors[] = __('Please enter a valid email address.', 'link-submission-pro');
		}
		$category_id = isset($post_data['lsp_category']) ? absint($post_data['lsp_category']) : 0;

		// If there are any validation errors, send them back.
		if ( ! empty($errors) ) {
			wp_send_json_error(['message' => implode('<br>', $errors)], 400);
		}

		// Insert Data into the Database.
		global $wpdb;

		$table_name = $wpdb->prefix . 'lsp_link_submissions';
		$inserted = $wpdb->insert(
			$table_name,
			[
				'submitted_at' => current_time('mysql', 1), // Use GMT time.
				'url'          => $url,
				'link_text'    => $link_text,
				'user_name'    => $user_name,
				'user_email'   => $user_email,
				'category_id'  => $category_id,
				'status'       => 'pending',
			],
			['%s', '%s', '%s', '%s', '%s', '%d', '%s']
		);

		// Send Response.
		if ( ! $inserted ) {
			wp_send_json_error(['message' => __('Could not save submission. Please try again.', 'link-submission-pro')], 500);
		}
		$this->send_admin_notification($url, $link_text, $user_name);
		wp_send_json_success(['message' => __('Thank you! Your link has been submitted for review.', 'link-submission-pro')]);
	}

	/**
	 * Checks if a URL's host has a 'banned' status in the database.
	 */
	private function is_domain_banned(string $url): bool {
		global $wpdb;
		$host = self::get_host_from_url($url);
		if ( empty($host) ) {
			return false; // Cannot check an invalid URL.
		}
		$table_name = $wpdb->prefix . 'lsp_link_submissions';
		$count      = $wpdb->get_var(
			$wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE banned_host = %s", $host)
		);
		return $count > 0;
	}

	/**
	 * Parses a URL and returns its normalized host
	 */
	private static function get_host_from_url(string $url): string {
		$host = wp_parse_url($url, PHP_URL_HOST);
		if ( ! is_string($host) ) {
			return '';
		}
		// Normalize by removing 'www.' prefix for broader matching.
		if ( str_starts_with($host, 'www.') ) {
			$host = substr($host, 4);
		}
		return strtolower($host);
	}

	/**
	 * Sends a notification email to the site administrator.
	 */
	private function send_admin_notification(string $url, string $link_text, string $user_name): void {
		$to      = get_option('admin_email');
		$subject = sprintf(__('New Link Submission on %s', 'link-submission-pro'), get_bloginfo('name'));
		$body    = sprintf(
			"%s\n\nURL: %s\n%s: %s\n%s: %s\n\n%s:\n%s",
			__('A new link has been submitted for review.', 'link-submission-pro'),
			esc_url($url),
			__('Link Text', 'link-submission-pro'),
			esc_html($link_text),
			__('Submitted by', 'link-submission-pro'),
			esc_html($user_name),
			__('You can review this submission here', 'link-submission-pro'),
			admin_url('admin.php?page=link-submissions')
		);
		wp_mail($to, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
	}

	/**
	 * Enqueues admin scripts and styles.
	 */
	public function enqueue_admin_assets(string $hook): void {
		// Only load assets on our specific admin page.
		if ( 'toplevel_page_link-submissions' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'lsp-admin-js',
			plugin_dir_url(__FILE__) . 'assets/js/lsp-admin.js',
			['jquery'],
			'1.0.0',
			true
		);
		wp_localize_script(
			'lsp-admin-js',
			'lsp_admin_ajax',
			[
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('lsp_admin_nonce'),
			]
		);
	}

	/**
	 * Adds the options page to the admin menu.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__('Link Submissions', 'link-submission-pro'),
			__('Link Submissions', 'link-submission-pro'),
			'manage_options',
			'link-submissions',
			[$this, 'render_admin_page'],
			'dashicons-admin-links',
			25
		);
	}

	/**
	 * Renders the admin page content.
	 */
	public function render_admin_page(): void {
		$list_table = new LSP_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Link Submissions', 'link-submission-pro'); ?></h1>
			<p><?php esc_html_e('Review, approve, or deny user-submitted links.', 'link-submission-pro'); ?></p>

			<form method="post">
				<input type="hidden" name="page" value="link-submissions" />
				<?php
				// Security field for the bulk action forms.
				// This function is the proper way to add nonces to WP_List_Table forms.
				//$list_table->search_box(__('Search Submissions', 'link-submission-pro'), 'search_id');
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Processes bulk actions from the list table.
	 */
	public function process_bulk_actions(): void {
		// Determine which bulk action is being performed.
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		// Security checks.
		check_admin_referer('bulk-' . $this->_get_plural());
		if ( ! current_user_can('manage_options') ) {
			wp_die(__('Permission denied.', 'link-submission-pro'));
		}
		if ( empty($_POST['submission']) || ! is_array($_POST['submission']) ) {
			return;
		}
		$submission_ids = array_map('absint', $_POST['submission']);

		global $wpdb;
		$table_name      = $wpdb->prefix . 'lsp_link_submissions';
		$id_placeholders = implode(', ', array_fill(0, count($submission_ids), '%d'));
		$items_changed   = 0;

		switch ( $action ) {
			case 'approve':
				// Set status to 'approved' and clear any potential 'banned_host' value.
				$items_changed = $wpdb->query(
					$wpdb->prepare("UPDATE {$table_name} SET status = 'approved', banned_host = '' WHERE id IN ($id_placeholders)", $submission_ids)
				);
				if ( $items_changed > 0 ) {
					foreach ( $submission_ids as $id ) {
						$this->add_submission_to_link_manager($id);
						$this->send_user_status_notification($id, 'approved');
					}
				}
				break;

			case 'unapprove':
				// Find associated Link Manager IDs before changing the status.
				$link_ids_to_delete = $wpdb->get_col(
					$wpdb->prepare("SELECT link_manager_id FROM {$table_name} WHERE id IN ($id_placeholders) AND link_manager_id > 0", $submission_ids)
				);
				if ( ! empty($link_ids_to_delete) ) {
					foreach ( $link_ids_to_delete as $link_id ) {
						wp_delete_link($link_id);
					}
				}
				// Revert status to 'pending' and remove the link manager ID.
				$items_changed = $wpdb->query(
					$wpdb->prepare("UPDATE {$table_name} SET status = 'pending', link_manager_id = 0, banned_host = '' WHERE id IN ($id_placeholders)", $submission_ids)
				);
				break;

			case 'deny':
				$items_changed = $wpdb->query(
					$wpdb->prepare("UPDATE {$table_name} SET status = 'denied', banned_host = '' WHERE id IN ($id_placeholders)", $submission_ids)
				);
				if ( $items_changed > 0 ) {
					foreach ( $submission_ids as $id ) {
						$this->send_user_status_notification($id, 'denied');
					}
				}
				break;

			case 'ban':
				$submissions_to_ban = $wpdb->get_results(
					$wpdb->prepare("SELECT id, url FROM {$table_name} WHERE id IN ($id_placeholders)", $submission_ids)
				);
				if ( ! empty($submissions_to_ban) ) {
					foreach ( $submissions_to_ban as $submission ) {
						$host = self::get_host_from_url($submission->url);
						if ( ! empty($host) ) {
							$updated = $wpdb->update($table_name, ['status' => 'banned', 'banned_host' => $host], ['id' => $submission->id], ['%s', '%s'], ['%d']);
							if ( $updated ) {
								$items_changed++;
								$this->send_user_status_notification((int) $submission->id, 'banned');
							}
						}
					}
				}
				break;

			case 'delete':
				$items_changed = $wpdb->query(
					$wpdb->prepare("DELETE FROM {$table_name} WHERE id IN ($id_placeholders)", $submission_ids)
				);
				break;
		}

		// Redirect back to the list table with a confirmation message.
		if ( $items_changed > 0 ) {
			$redirect_url = add_query_arg(
				[
					'page'        => 'link-submissions',
					'bulk_action' => $action,
					'count'       => $items_changed,
					'status'      => $_GET['status'] ?? false,
					'category_id' => $_GET['category_id'] ?? false,
				],
				admin_url('admin.php')
			);
			wp_safe_redirect($redirect_url);
			exit;
		}
	}

	/**
	 * Displays admin notices for feedback after bulk actions.
	 */
	public function display_admin_notices(): void {
		if ( empty($_GET['bulk_action']) || empty($_GET['count']) ) {
			return;
		}
		$count   = absint($_GET['count']);
		$action  = sanitize_text_field(wp_unslash($_GET['bulk_action']));
		$message = '';

		switch ( $action ) {
			case 'approve':
				$message = sprintf(_n('%d submission approved.', '%d submissions approved.', $count, 'link-submission-pro'), $count);
				break;
			case 'unapprove':
				$message = sprintf(_n('%d submission unapproved and removed from Link Manager.', '%d submissions unapproved and removed from Link Manager.', $count, 'link-submission-pro'), $count);
				break;
			case 'deny':
				$message = sprintf(_n('%d submission denied.', '%d submissions denied.', $count, 'link-submission-pro'), $count);
				break;
			case 'ban':
				$message = sprintf(_n('%d submission banned. The associated domain is now blocked.', '%d submissions banned. The associated domains are now blocked.', $count, 'link-submission-pro'), $count);
				break;
			case 'delete':
				$message = sprintf(_n('%d submission deleted.', '%d submissions deleted.', $count, 'link-submission-pro'), $count);
				break;
		}
		if ( $message ) {
			printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message));
		}
	}

	/**
	 * Handles the AJAX request to approve, deny, or ban a single submission.
	 */
	public function handle_status_update(): void {
		// Security checks.
		if ( ! check_ajax_referer('lsp_admin_nonce', 'nonce') ) {
			wp_send_json_error(['message' => __('Security check failed.', 'link-submission-pro')], 403);
		}
		if ( ! current_user_can('manage_options') ) {
			wp_send_json_error(['message' => __('Permission denied.', 'link-submission-pro')], 403);
		}

		// Validate input.
		$submission_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$new_status    = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
		if ( ! $submission_id || ! in_array($new_status, ['approved', 'denied', 'banned'], true) ) {
			wp_send_json_error(['message' => __('Invalid data.', 'link-submission-pro')], 400);
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'lsp_link_submissions';

		// Prepare data for the database update.
		$data_to_update = ['status' => $new_status];
		if ( 'banned' === $new_status ) {
			$url = $wpdb->get_var($wpdb->prepare("SELECT url FROM {$table_name} WHERE id = %d", $submission_id));
			$data_to_update['banned_host'] = $url ? self::get_host_from_url($url) : '';
		} else {
			// Ensure banned_host is cleared if status is not 'banned'.
			$data_to_update['banned_host'] = '';
		}

		$updated = $wpdb->update($table_name, $data_to_update, ['id' => $submission_id]);

		if ( false === $updated ) {
			wp_send_json_error(['message' => __('Database update failed.', 'link-submission-pro')], 500);
		}
		// If a link is approved, add it to the Link Manager.
		if ( 'approved' === $new_status ) {
			$this->add_submission_to_link_manager($submission_id);
		}
		// Notify the user of the status change.
		$this->send_user_status_notification($submission_id, $new_status);
		wp_send_json_success(['message' => __('Status updated.', 'link-submission-pro')]);
	}

	/**
	 * Sends a notification to the user about their submission status.
	 */
	private function send_user_status_notification(int $submission_id, string $new_status): void {
		global $wpdb;
		$submission = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$wpdb->prefix}lsp_link_submissions WHERE id = %d", $submission_id)
		);
		if ( ! $submission || empty($submission->user_email) ) {
			return;
		}

		$blog_name = get_bloginfo('name');
		$user_name = $submission->user_name;
		$link_text = $submission->link_text;
		$url       = $submission->url;

		// Build the email subject and body based on the new status.
		switch ( $new_status ) {
			case 'approved':
				$subject = sprintf(__('Your link submission to %s has been approved!', 'link-submission-pro'), $blog_name);
				$body    = sprintf(__("Hi %s,\n\nGreat news! Your recent link submission has been approved and is now live:\n\nLink: %s\n\nThank you for your contribution to %s!", 'link-submission-pro'), $user_name, $url, $blog_name);
				break;
			case 'denied':
				$subject = sprintf(__('Regarding your link submission to %s', 'link-submission-pro'), $blog_name);
				$body    = sprintf(__("Hi %s,\n\nThank you for your recent link submission for \"%s\".\n\nUnfortunately, we were not able to approve it at this time. We appreciate your interest.\n\nRegards,\nThe %s Team", 'link-submission-pro'), $user_name, $link_text, $blog_name);
				break;
			case 'banned':
				$subject = sprintf(__('Regarding your link submission to %s', 'link-submission-pro'), $blog_name);
				$body    = sprintf(__("Hi %s,\n\nThis is a notification that your recent submission for the URL %s has been denied and its domain has been banned from future submissions.\n\nRegards,\nThe %s Team", 'link-submission-pro'), $user_name, $url, $blog_name);
				break;
			default:
				return; // Do not send email for other statuses.
		}
		wp_mail($submission->user_email, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
	}

	/**
	 * Adds an approved submission to the WordPress Link Manager (Bookmarks).
	 *
	 * This method inserts a new link into the `wp_links` table and associates
	 * it with the correct category. It also prevents duplicate entries.
	 */
	private function add_submission_to_link_manager(int $submission_id): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'lsp_link_submissions';
		$submission = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $submission_id)
		);
		if ( ! $submission ) {
			return;
		}

		// Check if this submission already has a Link Manager ID and if that link still exists.
		if ( ! empty($submission->link_manager_id) ) {
			$exists = $wpdb->get_var($wpdb->prepare("SELECT link_id FROM {$wpdb->links} WHERE link_id = %d", $submission->link_manager_id));
			if ( $exists ) {
				return; // Link already exists, nothing to do.
			}
		}

		// Prevent duplicates. Check if a link with the same URL and category already exists.
		$sql   = "SELECT COUNT(*) FROM {$wpdb->links} l
				JOIN {$wpdb->term_relationships} tr ON l.link_id = tr.object_id
				JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE l.link_url = %s AND tt.term_id = %d AND tt.taxonomy = 'link_category'";
		$query = $wpdb->prepare($sql, $submission->url, $submission->category_id);
		if ( $wpdb->get_var($query) > 0 ) {
			return; // A link with this URL in this category already exists.
		}

		// Insert the new link into the wp_links table.
		$link_data = [
			'link_url'     => $submission->url,
			'link_name'    => $submission->link_text,
			'link_visible' => 'Y',
		];
		$inserted = $wpdb->insert($wpdb->links, $link_data);
		if ( ! $inserted ) {
			return;
		}
		$new_link_id = $wpdb->insert_id;

		// If a category was selected, associate the new link with it.
		if ( $submission->category_id > 0 ) {
			wp_set_object_terms($new_link_id, (int) $submission->category_id, 'link_category');
		}

		// Update our submission record with the new Link Manager ID.
		$wpdb->update($table_name, ['link_manager_id' => $new_link_id], ['id' => $submission_id], ['%d'], ['%d']);
	}

	/**
	 * Wrapper for the current action used by WP_List_Table.
	 */
	public function current_action() {
		if ( isset($_REQUEST['action']) && -1 != $_REQUEST['action'] ) {
			return sanitize_text_field(wp_unslash($_REQUEST['action']));
		}

		if ( isset($_REQUEST['action2']) && -1 != $_REQUEST['action2'] ) {
			return sanitize_text_field(wp_unslash($_REQUEST['action2']));
		}

		return false;
	}

	/**
	 * Helper method to get the plural name for the WP_List_Table nonce.
	 */
	private function _get_plural(): string {
		return 'link_submissions';
	}
}

// Register the activation hook.
register_activation_hook(__FILE__, [Link_Submission_Pro::class, 'activate']);

// Run Forrest Run
Link_Submission_Pro::get_instance();

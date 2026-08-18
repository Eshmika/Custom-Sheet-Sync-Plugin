<?php
/**
 * Plugin Name: Dynamic Sheet to Post Type Sync
 * Description: Imports and syncs Google Sheet CSV rows into any custom post type using a custom field-mapping UI and scheduled WP-Cron.
 * Version: 1.0.0
 * Author: Expert Developer
 * Text Domain: dynamic-sheet-sync
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Dynamic_Sheet_Post_Sync
 * Main controller for the plugin.
 */
class Dynamic_Sheet_Post_Sync {

	/**
	 * Constructor to set up hooks and actions.
	 */
	public function __construct() {
		// Admin menu and settings initialization.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Custom Cron intervals hook.
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );

		// Scheduled sync action hook.
		add_action( 'dynamic_sheet_sync_cron_hook', array( $this, 'run_synchronization' ) );

		// Handle manual sync button post request.
		add_action( 'admin_post_dynamic_sheet_sync_now', array( $this, 'handle_manual_sync' ) );
	}

	/**
	 * Enqueue javascript/styles on the settings page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_sheet-sync' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'jquery' );

		// Inject custom inline style to make the interface look neat.
		wp_add_inline_style( 'wp-color-picker', '
			.sheet-sync-container { max-width: 900px; margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; padding: 20px 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px; }
			.sheet-sync-header { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
			.sheet-sync-header h1 { margin: 0; font-size: 24px; color: #23282d; }
			.sheet-sync-table th { width: 220px; font-weight: 600; padding: 15px 10px 15px 0; }
			.sheet-sync-table td { padding: 10px 0; }
			.sheet-sync-table input[type="text"], .sheet-sync-table select { width: 100%; max-width: 450px; }
			.repeater-row td { padding: 5px 0; }
			.repeater-row input { width: 100%; }
			.button-remove { color: #b52b2b; cursor: pointer; text-decoration: underline; font-weight: 500; }
			.button-remove:hover { color: #e63946; }
			.status-box { background: #f0f6fc; border-left: 4px solid #135e96; padding: 15px; margin-bottom: 20px; border-radius: 0 4px 4px 0; }
			.status-box p { margin: 0 0 8px 0; font-size: 14px; }
			.status-box p:last-child { margin-bottom: 0; }
			.status-box strong { color: #1d2327; }
		' );
	}

	/**
	 * Add admin submenu under Settings.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Dynamic Sheet Sync Settings', 'dynamic-sheet-sync' ),
			__( 'Sheet Sync', 'dynamic-sheet-sync' ),
			'manage_options',
			'sheet-sync',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'dynamic_sheet_sync_group', 'dynamic_sheet_sync_options', array( $this, 'sanitize_settings' ) );
	}

	/**
	 * Sanitization callback for all options.
	 */
	public function sanitize_settings( $input ) {
		$output = array();

		// Google Sheet URL.
		if ( isset( $input['csv_url'] ) ) {
			$output['csv_url'] = esc_url_raw( trim( $input['csv_url'] ) );
		}

		// Target Post Type.
		if ( isset( $input['post_type'] ) ) {
			$output['post_type'] = sanitize_key( trim( $input['post_type'] ) );
		}

		// Unique Identifier mappings.
		if ( isset( $input['uid_sheet_col'] ) ) {
			$output['uid_sheet_col'] = sanitize_text_field( trim( $input['uid_sheet_col'] ) );
		}
		if ( isset( $input['uid_meta_key'] ) ) {
			$output['uid_meta_key'] = sanitize_key( trim( $input['uid_meta_key'] ) );
		}

		// Standard Field mappings.
		if ( isset( $input['field_title'] ) ) {
			$output['field_title'] = sanitize_text_field( trim( $input['field_title'] ) );
		}
		if ( isset( $input['field_content'] ) ) {
			$output['field_content'] = sanitize_text_field( trim( $input['field_content'] ) );
		}
		if ( isset( $input['field_image'] ) ) {
			$output['field_image'] = sanitize_text_field( trim( $input['field_image'] ) );
		}
		if ( isset( $input['field_status'] ) ) {
			$output['field_status'] = sanitize_text_field( trim( $input['field_status'] ) );
		}

		// Custom Meta Mappings (Repeater).
		$output['custom_meta'] = array();
		if ( isset( $input['custom_meta'] ) && is_array( $input['custom_meta'] ) ) {
			foreach ( $input['custom_meta'] as $row ) {
				$sheet_col = isset( $row['sheet_col'] ) ? sanitize_text_field( trim( $row['sheet_col'] ) ) : '';
				$meta_key  = isset( $row['meta_key'] ) ? sanitize_key( trim( $row['meta_key'] ) ) : '';

				if ( ! empty( $sheet_col ) && ! empty( $meta_key ) ) {
					$output['custom_meta'][] = array(
						'sheet_col' => $sheet_col,
						'meta_key'  => $meta_key,
					);
				}
			}
		}

		// Sync Frequency.
		if ( isset( $input['frequency'] ) ) {
			$output['frequency'] = sanitize_text_field( $input['frequency'] );
		}

		// Keep existing log stats/results if not modified here.
		$old_options = get_option( 'dynamic_sheet_sync_options' );
		if ( is_array( $old_options ) ) {
			$output['last_sync_time']    = isset( $old_options['last_sync_time'] ) ? $old_options['last_sync_time'] : '';
			$output['last_sync_status']  = isset( $old_options['last_sync_status'] ) ? $old_options['last_sync_status'] : '';
			$output['last_sync_created'] = isset( $old_options['last_sync_created'] ) ? $old_options['last_sync_created'] : 0;
			$output['last_sync_updated'] = isset( $old_options['last_sync_updated'] ) ? $old_options['last_sync_updated'] : 0;
			$output['last_sync_total']   = isset( $old_options['last_sync_total'] ) ? $old_options['last_sync_total'] : 0;
		}

		// Update Cron schedules if frequency has changed.
		if ( ! is_array( $old_options ) || ! isset( $old_options['frequency'] ) || $old_options['frequency'] !== $output['frequency'] ) {
			wp_clear_scheduled_hook( 'dynamic_sheet_sync_cron_hook' );
			if ( 'manual' !== $output['frequency'] ) {
				wp_schedule_event( time() + 60, $output['frequency'], 'dynamic_sheet_sync_cron_hook' );
			}
		}

		return $output;
	}

	/**
	 * Append custom cron intervals.
	 */
	public function add_cron_intervals( $schedules ) {
		if ( ! isset( $schedules['every_15_minutes'] ) ) {
			$schedules['every_15_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 Minutes', 'dynamic-sheet-sync' ),
			);
		}
		return $schedules;
	}

	/**
	 * Retrieve list of registered public post types.
	 */
	private function get_registered_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$options = array();
		foreach ( $post_types as $slug => $object ) {
			$options[$slug] = $object->labels->singular_name . ' (' . $slug . ')';
		}
		return $options;
	}

	/**
	 * Render settings page template.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = get_option( 'dynamic_sheet_sync_options', array() );

		// Set default values.
		$csv_url        = isset( $options['csv_url'] ) ? $options['csv_url'] : '';
		$target_pt      = isset( $options['post_type'] ) ? $options['post_type'] : 'post';
		$uid_sheet_col  = isset( $options['uid_sheet_col'] ) ? $options['uid_sheet_col'] : '';
		$uid_meta_key   = isset( $options['uid_meta_key'] ) ? $options['uid_meta_key'] : '';
		$field_title    = isset( $options['field_title'] ) ? $options['field_title'] : '';
		$field_content  = isset( $options['field_content'] ) ? $options['field_content'] : '';
		$field_image    = isset( $options['field_image'] ) ? $options['field_image'] : '';
		$field_status   = isset( $options['field_status'] ) ? $options['field_status'] : '';
		$frequency      = isset( $options['frequency'] ) ? $options['frequency'] : 'hourly';
		$custom_meta    = isset( $options['custom_meta'] ) && is_array( $options['custom_meta'] ) ? $options['custom_meta'] : array();

		$registered_pts = $this->get_registered_post_types();
		?>
		<div class="wrap">
			<div class="sheet-sync-container">
				<div class="sheet-sync-header">
					<h1><?php esc_html_e( 'Dynamic Sheet to Post Type Sync Settings', 'dynamic-sheet-sync' ); ?></h1>
				</div>

				<?php settings_errors(); ?>

				<!-- Manual Actions & Sync Status Dashboard -->
				<div class="status-box">
					<p><strong><?php esc_html_e( 'Last Sync Status:', 'dynamic-sheet-sync' ); ?></strong> 
						<?php 
						if ( ! empty( $options['last_sync_time'] ) ) {
							$timestamp = get_date_from_gmt( $options['last_sync_time'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
							printf(
								/* translators: 1: formatted date/time, 2: total items, 3: created count, 4: updated count */
								esc_html__( 'Last run on %1$s. Processed %2$d rows (Created: %3$d, Updated: %4$d). Status: %5$s', 'dynamic-sheet-sync' ),
								esc_html( $timestamp ),
								intval( $options['last_sync_total'] ),
								intval( $options['last_sync_created'] ),
								intval( $options['last_sync_updated'] ),
								esc_html( $options['last_sync_status'] )
							);
						} else {
							esc_html_e( 'Never run', 'dynamic-sheet-sync' );
						}
						?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'manual_sheet_sync_action', 'manual_sheet_sync_nonce' ); ?>
						<input type="hidden" name="action" value="dynamic_sheet_sync_now" />
						<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Sync Now', 'dynamic-sheet-sync' ); ?>" />
					</form>
				</div>

				<!-- Settings Form -->
				<form method="post" action="options.php">
					<?php settings_fields( 'dynamic_sheet_sync_group' ); ?>

					<table class="form-table sheet-sync-table">
						<!-- CSV Url -->
						<tr>
							<th scope="row"><label for="csv_url"><?php esc_html_e( 'Google Sheet Published CSV URL', 'dynamic-sheet-sync' ); ?></label></th>
							<td>
								<input type="text" id="csv_url" name="dynamic_sheet_sync_options[csv_url]" value="<?php echo esc_url( $csv_url ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Make sure the Google Sheet is published to the web as a CSV, and paste the direct CSV URL here.', 'dynamic-sheet-sync' ); ?></p>
							</td>
						</tr>

						<!-- Target Post Type -->
						<tr>
							<th scope="row"><label for="post_type"><?php esc_html_e( 'Target Post Type', 'dynamic-sheet-sync' ); ?></label></th>
							<td>
								<select id="post_type" name="dynamic_sheet_sync_options[post_type]">
									<?php foreach ( $registered_pts as $slug => $name ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $target_pt, $slug ); ?>><?php echo esc_html( $name ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Select the post type to insert or update entries into.', 'dynamic-sheet-sync' ); ?></p>
							</td>
						</tr>

						<!-- Unique Identifier Section -->
						<tr style="border-top: 1px solid #f0f0.0; border-bottom: 1px solid #f0f0f0;">
							<th scope="row" style="padding-top: 20px;"><?php esc_html_e( 'Unique Identifier Mapping', 'dynamic-sheet-sync' ); ?></th>
							<td style="padding-top: 20px;">
								<div style="display: flex; gap: 15px; margin-bottom: 10px;">
									<div>
										<label style="display:block; font-weight:600; font-size:12px;" for="uid_sheet_col"><?php esc_html_e( 'Sheet Column Name', 'dynamic-sheet-sync' ); ?></label>
										<input type="text" id="uid_sheet_col" name="dynamic_sheet_sync_options[uid_sheet_col]" value="<?php echo esc_attr( $uid_sheet_col ); ?>" placeholder="e.g. VIN or ID" style="width: 215px;" />
									</div>
									<div>
										<label style="display:block; font-weight:600; font-size:12px;" for="uid_meta_key"><?php esc_html_e( 'WordPress Meta Key', 'dynamic-sheet-sync' ); ?></label>
										<input type="text" id="uid_meta_key" name="dynamic_sheet_sync_options[uid_meta_key]" value="<?php echo esc_attr( $uid_meta_key ); ?>" placeholder="e.g. _vehicle_vin or _sku" style="width: 215px;" />
									</div>
								</div>
								<p class="description"><?php esc_html_e( 'This unique sheet column value will map to the specified WordPress meta key to check if the post already exists and prevent duplicates.', 'dynamic-sheet-sync' ); ?></p>
							</td>
						</tr>

						<!-- Standard Post Field Mappings -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Standard Post Field Mappings', 'dynamic-sheet-sync' ); ?></th>
							<td>
								<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; max-width: 450px;">
									<div>
										<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 2px;" for="field_title"><?php esc_html_e( 'Post Title Column Header', 'dynamic-sheet-sync' ); ?></label>
										<input type="text" id="field_title" name="dynamic_sheet_sync_options[field_title]" value="<?php echo esc_attr( $field_title ); ?>" placeholder="e.g. Vehicle Name" style="width: 100%;" />
									</div>
									<div>
										<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 2px;" for="field_content"><?php esc_html_e( 'Post Content Column Header', 'dynamic-sheet-sync' ); ?></label>
										<input type="text" id="field_content" name="dynamic_sheet_sync_options[field_content]" value="<?php echo esc_attr( $field_content ); ?>" placeholder="e.g. Details" style="width: 100%;" />
									</div>
									<div>
										<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 2px;" for="field_image"><?php esc_html_e( 'Featured Image URL Column Header', 'dynamic-sheet-sync' ); ?></label>
										<input type="text" id="field_image" name="dynamic_sheet_sync_options[field_image]" value="<?php echo esc_attr( $field_image ); ?>" placeholder="e.g. Image URL" style="width: 100%;" />
									</div>
									<div>
										<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 2px;" for="field_status"><?php esc_html_e( 'Post Status Column Header (Optional)', 'dynamic-sheet-sync' ); ?></label>
										<input type="text" id="field_status" name="dynamic_sheet_sync_options[field_status]" value="<?php echo esc_attr( $field_status ); ?>" placeholder="e.g. Status" style="width: 100%;" />
									</div>
								</div>
								<p class="description"><?php esc_html_e( 'Enter the column header name from the CSV that corresponds to each core post detail.', 'dynamic-sheet-sync' ); ?></p>
							</td>
						</tr>

						<!-- Custom Meta Fields Mapping Table (Repeater UI) -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Custom Meta Fields Mapping', 'dynamic-sheet-sync' ); ?></th>
							<td>
								<table class="widefat striped" id="custom-meta-repeater-table" style="max-width: 500px; margin-bottom: 10px;">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Sheet Column Header', 'dynamic-sheet-sync' ); ?></th>
											<th><?php esc_html_e( 'WordPress Meta Key', 'dynamic-sheet-sync' ); ?></th>
											<th style="width: 80px; text-align: center;"><?php esc_html_e( 'Actions', 'dynamic-sheet-sync' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php
										$index = 0;
										if ( ! empty( $custom_meta ) ) :
											foreach ( $custom_meta as $row ) :
												?>
												<tr class="repeater-row">
													<td>
														<input type="text" name="dynamic_sheet_sync_options[custom_meta][<?php echo intval( $index ); ?>][sheet_col]" value="<?php echo esc_attr( $row['sheet_col'] ); ?>" placeholder="e.g. Price" />
													</td>
													<td>
														<input type="text" name="dynamic_sheet_sync_options[custom_meta][<?php echo intval( $index ); ?>][meta_key]" value="<?php echo esc_attr( $row['meta_key'] ); ?>" placeholder="e.g. _price" />
													</td>
													<td style="text-align: center; vertical-align: middle;">
														<span class="button-remove"><?php esc_html_e( 'Remove', 'dynamic-sheet-sync' ); ?></span>
													</td>
												</tr>
												<?php
												$index++;
											endforeach;
										endif;
										?>
									</tbody>
								</table>
								<button type="button" class="button button-secondary" id="btn-add-meta-row"><?php esc_html_e( 'Add Row', 'dynamic-sheet-sync' ); ?></button>
								<p class="description"><?php esc_html_e( 'Define extra custom attributes inside rows to be imported as post meta properties.', 'dynamic-sheet-sync' ); ?></p>
							</td>
						</tr>

						<!-- Sync Frequency Selection -->
						<tr>
							<th scope="row"><label for="frequency"><?php esc_html_e( 'Sync Frequency', 'dynamic-sheet-sync' ); ?></label></th>
							<td>
								<select id="frequency" name="dynamic_sheet_sync_options[frequency]">
									<option value="manual" <?php selected( $frequency, 'manual' ); ?>><?php esc_html_e( 'Manual Only (Disable Cron)', 'dynamic-sheet-sync' ); ?></option>
									<option value="every_15_minutes" <?php selected( $frequency, 'every_15_minutes' ); ?>><?php esc_html_e( 'Every 15 Minutes', 'dynamic-sheet-sync' ); ?></option>
									<option value="hourly" <?php selected( $frequency, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'dynamic-sheet-sync' ); ?></option>
									<option value="twicedaily" <?php selected( $frequency, 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'dynamic-sheet-sync' ); ?></option>
									<option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Daily', 'dynamic-sheet-sync' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Select the automation speed for recurring imports.', 'dynamic-sheet-sync' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button(); ?>
				</form>
			</div>
		</div>

		<script>
			jQuery(document).ready(function($) {
				var rowIndex = <?php echo intval( $index ); ?>;

				// Click handler to append a new metadata field mapping row
				$('#btn-add-meta-row').on('click', function() {
					var html = '<tr class="repeater-row">' +
						'<td><input type="text" name="dynamic_sheet_sync_options[custom_meta][' + rowIndex + '][sheet_col]" placeholder="e.g. Attribute" value="" /></td>' +
						'<td><input type="text" name="dynamic_sheet_sync_options[custom_meta][' + rowIndex + '][meta_key]" placeholder="e.g. _attribute_key" value="" /></td>' +
						'<td style="text-align: center; vertical-align: middle;"><span class="button-remove">Remove</span></td>' +
						'</tr>';
					$('#custom-meta-repeater-table tbody').append(html);
					rowIndex++;
				});

				// Event delegation for removal
				$('#custom-meta-repeater-table').on('click', '.button-remove', function() {
					$(this).closest('tr').remove();
				});
			});
		</script>
		<?php
	}

	/**
	 * Handle direct manual sync request.
	 */
	public function handle_manual_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user permissions.', 'dynamic-sheet-sync' ) );
		}

		check_admin_referer( 'manual_sheet_sync_action', 'manual_sheet_sync_nonce' );

		// Run direct sync function.
		$result = $this->run_synchronization();

		// Add status indicator error/success.
		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'dynamic_sheet_sync_options',
				'sync_failed',
				sprintf( esc_html__( 'Manual sync failed: %s', 'dynamic-sheet-sync' ), $result->get_error_message() ),
				'error'
			);
		} else {
			add_settings_error(
				'dynamic_sheet_sync_options',
				'sync_success',
				esc_html__( 'Manual synchronization finished successfully.', 'dynamic-sheet-sync' ),
				'success'
			);
		}

		// Redirect back to options dashboard.
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		$referrer = wp_get_referer();
		$redirect = $referrer ? $referrer : admin_url( 'options-general.php?page=sheet-sync' );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Perform the sheet synchronization task.
	 * Can be run manually or by WP-Cron.
	 */
	public function run_synchronization() {
		$options = get_option( 'dynamic_sheet_sync_options', array() );

		$csv_url       = isset( $options['csv_url'] ) ? $options['csv_url'] : '';
		$post_type     = isset( $options['post_type'] ) ? $options['post_type'] : 'post';
		$uid_sheet_col = isset( $options['uid_sheet_col'] ) ? $options['uid_sheet_col'] : '';
		$uid_meta_key  = isset( $options['uid_meta_key'] ) ? $options['uid_meta_key'] : '';

		// If critical configurations are missing, abort immediately.
		if ( empty( $csv_url ) || empty( $uid_sheet_col ) || empty( $uid_meta_key ) ) {
			return new WP_Error( 'missing_config', __( 'Sync aborted. Please configure the Google Sheet URL and Unique Identifier.', 'dynamic-sheet-sync' ) );
		}

		// Download the Google Sheet CSV.
		$response = wp_remote_get( $csv_url, array( 'timeout' => 45 ) );
		if ( is_wp_error( $response ) ) {
			$this->update_sync_stats( 'Failed to download CSV: ' . $response->get_error_message(), 0, 0, 0 );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			$this->update_sync_stats( 'Failed to download CSV: Empty response body.', 0, 0, 0 );
			return new WP_Error( 'empty_body', __( 'The CSV sheet returned empty content.', 'dynamic-sheet-sync' ) );
		}

		// Parse the CSV content.
		$lines = preg_split( '/\r\n|\r|\n/', trim( $body ) );
		if ( empty( $lines ) ) {
			$this->update_sync_stats( 'CSV contained no rows.', 0, 0, 0 );
			return new WP_Error( 'no_rows', __( 'CSV file has no data lines.', 'dynamic-sheet-sync' ) );
		}

		$headers = str_getcsv( array_shift( $lines ) );
		// Trim headers to avoid key mismatch issues.
		$headers = array_map( 'trim', $headers );

		$uid_col_index = array_search( $uid_sheet_col, $headers, true );
		if ( false === $uid_col_index ) {
			$this->update_sync_stats( 'Unique Identifier column not found in CSV.', 0, 0, 0 );
			return new WP_Error( 'uid_not_found', sprintf( __( 'The unique column "%s" was not found in CSV headers.', 'dynamic-sheet-sync' ), $uid_sheet_col ) );
		}

		// Core field headers mappings indexes.
		$title_col_header  = isset( $options['field_title'] ) ? $options['field_title'] : '';
		$content_col_header = isset( $options['field_content'] ) ? $options['field_content'] : '';
		$image_col_header  = isset( $options['field_image'] ) ? $options['field_image'] : '';
		$status_col_header = isset( $options['field_status'] ) ? $options['field_status'] : '';

		$title_index   = ! empty( $title_col_header ) ? array_search( $title_col_header, $headers, true ) : false;
		$content_index = ! empty( $content_col_header ) ? array_search( $content_col_header, $headers, true ) : false;
		$image_index   = ! empty( $image_col_header ) ? array_search( $image_col_header, $headers, true ) : false;
		$status_index  = ! empty( $status_col_header ) ? array_search( $status_col_header, $headers, true ) : false;

		// Custom Meta mapping lists.
		$custom_meta_mappings = isset( $options['custom_meta'] ) && is_array( $options['custom_meta'] ) ? $options['custom_meta'] : array();
		$custom_meta_resolved = array();
		foreach ( $custom_meta_mappings as $mapping ) {
			$idx = array_search( $mapping['sheet_col'], $headers, true );
			if ( false !== $idx ) {
				$custom_meta_resolved[] = array(
					'index'    => $idx,
					'meta_key' => $mapping['meta_key'],
				);
			}
		}

		$created_count = 0;
		$updated_count = 0;
		$total_rows    = 0;

		// Required libraries for image sideloading if we are in front-end context (like Cron).
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		foreach ( $lines as $line ) {
			if ( empty( trim( $line ) ) ) {
				continue;
			}
			$row_data = str_getcsv( $line );
			if ( count( $row_data ) < 1 || ! isset( $row_data[$uid_col_index] ) ) {
				continue;
			}

			$unique_id_value = trim( $row_data[$uid_col_index] );
			if ( empty( $unique_id_value ) ) {
				continue; // Skip lines missing unique identity keys.
			}

			$total_rows++;

			// Get standard fields if index exists.
			$post_title   = ( false !== $title_index && isset( $row_data[$title_index] ) ) ? sanitize_text_field( trim( $row_data[$title_index] ) ) : '';
			$post_content = ( false !== $content_index && isset( $row_data[$content_index] ) ) ? wp_kses_post( trim( $row_data[$content_index] ) ) : '';
			$post_status  = ( false !== $status_index && isset( $row_data[$status_index] ) ) ? sanitize_text_field( trim( strtolower( $row_data[$status_index] ) ) ) : 'publish';
			$image_url    = ( false !== $image_index && isset( $row_data[$image_index] ) ) ? esc_url_raw( trim( $row_data[$image_index] ) ) : '';

			// Validate post status.
			$valid_statuses = array( 'publish', 'pending', 'draft', 'private', 'future' );
			if ( ! in_array( $post_status, $valid_statuses, true ) ) {
				$post_status = 'publish';
			}

			// Query if post already exists.
			$query_args = array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'     => $uid_meta_key,
						'value'   => $unique_id_value,
						'compare' => '=',
					),
				),
			);

			$existing_posts = get_posts( $query_args );
			$post_id        = 0;

			if ( ! empty( $existing_posts ) ) {
				$existing_post = $existing_posts[0];
				$post_id       = $existing_post->ID;

				// Update post content & title.
				$update_args = array(
					'ID'           => $post_id,
					'post_type'    => $post_type,
				);
				if ( ! empty( $post_title ) ) {
					$update_args['post_title'] = $post_title;
				}
				if ( ! empty( $post_content ) ) {
					$update_args['post_content'] = $post_content;
				}
				if ( false !== $status_index ) {
					$update_args['post_status'] = $post_status;
				}

				wp_update_post( $update_args );
				$updated_count++;
			} else {
				// Fallback to title placeholder if title column is empty.
				$final_title = ! empty( $post_title ) ? $post_title : sprintf( __( 'Sheet Row Sync - %s', 'dynamic-sheet-sync' ), $unique_id_value );

				// Create new post.
				$insert_args = array(
					'post_title'   => $final_title,
					'post_content' => $post_content,
					'post_status'  => $post_status,
					'post_type'    => $post_type,
				);

				$post_id = wp_insert_post( $insert_args );
				if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
					// Save unique identifier.
					update_post_meta( $post_id, $uid_meta_key, $unique_id_value );
					$created_count++;
				}
			}

			// If successfully inserted/updated, process meta fields and images.
			if ( $post_id > 0 && ! is_wp_error( $post_id ) ) {
				// Process Custom Meta Fields.
				foreach ( $custom_meta_resolved as $mapping ) {
					$meta_value = isset( $row_data[$mapping['index']] ) ? sanitize_text_field( trim( $row_data[$mapping['index']] ) ) : '';
					update_post_meta( $post_id, $mapping['meta_key'], $meta_value );
				}

				// Process Featured Image.
				if ( ! empty( $image_url ) ) {
					$cached_img = get_post_meta( $post_id, '_sideloaded_img_url', true );
					if ( $cached_img !== $image_url ) {
						// Download and attach featured image.
						$img_id = media_sideload_image( $image_url, $post_id, null, 'id' );
						if ( ! is_wp_error( $img_id ) && $img_id > 0 ) {
							set_post_thumbnail( $post_id, $img_id );
							update_post_meta( $post_id, '_sideloaded_img_url', $image_url );
						}
					}
				}
			}
		}

		$this->update_sync_stats( 'Completed Successfully', $created_count, $updated_count, $total_rows );
		return true;
	}

	/**
	 * Record current run results back into dynamic sheet sync options.
	 */
	private function update_sync_stats( $status, $created, $updated, $total ) {
		$options = get_option( 'dynamic_sheet_sync_options', array() );
		$options['last_sync_time']    = gmdate( 'Y-m-d H:i:s' );
		$options['last_sync_status']  = $status;
		$options['last_sync_created'] = $created;
		$options['last_sync_updated'] = $updated;
		$options['last_sync_total']   = $total;
		update_option( 'dynamic_sheet_sync_options', $options );
	}
}

// Initialize the sync plugin instance.
$GLOBALS['dynamic_sheet_post_sync'] = new Dynamic_Sheet_Post_Sync();

/**
 * Register activation hook.
 */
register_activation_hook( __FILE__, 'dynamic_sheet_sync_activate' );
function dynamic_sheet_sync_activate() {
	// Set default schedules if options do not exist.
	$options = get_option( 'dynamic_sheet_sync_options' );
	if ( ! is_array( $options ) ) {
		$default_options = array(
			'csv_url'            => '',
			'post_type'          => 'post',
			'uid_sheet_col'      => '',
			'uid_meta_key'       => '',
			'field_title'        => '',
			'field_content'      => '',
			'field_image'        => '',
			'field_status'       => '',
			'frequency'          => 'hourly',
			'custom_meta'        => array(),
			'last_sync_time'     => '',
			'last_sync_status'   => '',
			'last_sync_created'  => 0,
			'last_sync_updated'  => 0,
			'last_sync_total'    => 0,
		);
		update_option( 'dynamic_sheet_sync_options', $default_options );
		wp_schedule_event( time() + 60, 'hourly', 'dynamic_sheet_sync_cron_hook' );
	} else {
		$freq = isset( $options['frequency'] ) ? $options['frequency'] : 'hourly';
		if ( 'manual' !== $freq && ! wp_next_scheduled( 'dynamic_sheet_sync_cron_hook' ) ) {
			wp_schedule_event( time() + 60, $freq, 'dynamic_sheet_sync_cron_hook' );
		}
	}
}

/**
 * Register deactivation hook.
 */
register_deactivation_hook( __FILE__, 'dynamic_sheet_sync_deactivate' );
function dynamic_sheet_sync_deactivate() {
	wp_clear_scheduled_hook( 'dynamic_sheet_sync_cron_hook' );
}

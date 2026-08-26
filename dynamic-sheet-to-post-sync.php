<?php
/**
 * Plugin Name: Dynamic Sheet to Post Type Sync & Vehicle Product Grid
 * Description: Automatically imports and syncs Google Sheet vehicle inventory into WordPress posts/products with multi-column titles, customizable specs, and a modern WooCommerce-style product grid shortcode.
 * Version: 2.0.0
 * Author: Eshmika Hettiarachchi
 * Text Domain: dynamic-sheet-sync
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Dynamic_Sheet_Post_Sync
 * Main controller for sheet sync, mapping, and product showcase.
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

		// Frontend assets and shortcodes.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_shortcode( 'vehicle_products', array( $this, 'render_vehicle_products_shortcode' ) );
		add_shortcode( 'vehicle_inventory', array( $this, 'render_vehicle_products_shortcode' ) );

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

		// Admin custom styles.
		wp_add_inline_style( 'wp-color-picker', '
			.sheet-sync-container { max-width: 1050px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
			.sheet-sync-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 24px 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 24px; }
			.sheet-sync-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f0f0f1; padding-bottom: 16px; margin-bottom: 20px; }
			.sheet-sync-header h1 { margin: 0; font-size: 22px; font-weight: 700; color: #1d2327; display: flex; align-items: center; gap: 10px; }
			.sheet-sync-badge { font-size: 12px; background: #e7f5ff; color: #0c85d0; font-weight: 600; padding: 4px 8px; border-radius: 4px; }
			.sheet-nav-tabs { display: flex; gap: 8px; border-bottom: 1px solid #c3c4c7; margin-bottom: 20px; }
			.sheet-nav-tab { padding: 10px 18px; font-size: 14px; font-weight: 600; color: #50575e; cursor: pointer; border: 1px solid transparent; border-bottom: none; border-radius: 6px 6px 0 0; background: #f6f7f7; text-decoration: none; }
			.sheet-nav-tab.active { background: #fff; color: #2271b1; border-color: #c3c4c7 #c3c4c7 #fff; margin-bottom: -1px; }
			.sheet-tab-panel { display: none; }
			.sheet-tab-panel.active { display: block; }
			.sheet-sync-table th { width: 240px; font-weight: 600; padding: 14px 10px 14px 0; color: #1d2327; font-size: 13.5px; }
			.sheet-sync-table td { padding: 10px 0; }
			.sheet-sync-table input[type="text"], .sheet-sync-table select, .sheet-sync-table textarea { width: 100%; max-width: 520px; border-radius: 4px; border: 1px solid #8c8f94; }
			.status-box { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 16px; border-radius: 0 6px 6px 0; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
			.status-box-content p { margin: 0 0 6px 0; font-size: 13.5px; }
			.status-box-content p:last-child { margin-bottom: 0; }
			.status-box-content strong { color: #1d2327; }
			.repeater-table { width: 100%; border-collapse: collapse; margin: 12px 0; background: #fff; border: 1px solid #dcdcde; border-radius: 6px; overflow: hidden; }
			.repeater-table th { background: #f6f7f7; padding: 10px 12px; font-size: 13px; text-align: left; border-bottom: 1px solid #dcdcde; }
			.repeater-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f1; vertical-align: middle; }
			.repeater-table input, .repeater-table select { width: 100% !important; margin: 0; }
			.btn-remove-row { color: #d63638; cursor: pointer; font-size: 18px; line-height: 1; transition: 0.2s; }
			.btn-remove-row:hover { color: #a00; transform: scale(1.15); }
			.shortcode-preview-box { background: #1e1e1e; color: #d4d4d4; padding: 14px 18px; border-radius: 6px; font-family: monospace; font-size: 13px; display: flex; align-items: center; justify-content: space-between; margin: 10px 0 20px 0; }
			.copy-btn { background: #2271b1; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 12px; font-family: sans-serif; font-weight: 600; }
			.copy-btn:hover { background: #135e96; }
			.helper-tag { display: inline-block; background: #f0f0f1; color: #3c434a; font-size: 11px; padding: 2px 6px; border-radius: 3px; font-family: monospace; cursor: pointer; margin-right: 4px; margin-top: 4px; }
			.helper-tag:hover { background: #e0e0e0; }
		' );
	}

	/**
	 * Register and enqueue frontend styling and interactive scripts for vehicle cards.
	 */
	public function enqueue_frontend_assets() {
		wp_register_style( 'dynamic-vehicle-grid-css', false );
		wp_enqueue_style( 'dynamic-vehicle-grid-css' );

		// WooCommerce-like Vehicle Product Grid Styles.
		wp_add_inline_style( 'dynamic-vehicle-grid-css', '
			:root {
				--vg-primary: #2563eb;
				--vg-primary-hover: #1d4ed8;
				--vg-accent: #10b981;
				--vg-text: #1f2937;
				--vg-text-muted: #6b7280;
				--vg-bg: #f9fafb;
				--vg-card-bg: #ffffff;
				--vg-border: #e5e7eb;
				--vg-radius: 12px;
				--vg-shadow: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
				--vg-shadow-hover: 0 12px 20px -3px rgba(0,0,0,0.12), 0 4px 6px -4px rgba(0,0,0,0.07);
			}
			.vehicle-grid-container {
				max-width: 1280px;
				margin: 30px auto;
				padding: 0 15px;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
				box-sizing: border-box;
			}
			.vehicle-grid-container * { box-sizing: border-box; }
			
			/* Filter & Search Bar */
			.vehicle-filter-bar {
				background: var(--vg-card-bg);
				border: 1px solid var(--vg-border);
				border-radius: var(--vg-radius);
				padding: 16px 20px;
				margin-bottom: 28px;
				display: flex;
				flex-wrap: wrap;
				gap: 12px;
				align-items: center;
				justify-content: space-between;
				box-shadow: var(--vg-shadow);
			}
			.vehicle-search-box {
				position: relative;
				flex: 1 1 260px;
			}
			.vehicle-search-box input {
				width: 100%;
				padding: 10px 14px 10px 38px;
				border: 1px solid var(--vg-border);
				border-radius: 8px;
				font-size: 14px;
				outline: none;
				transition: border-color 0.2s;
			}
			.vehicle-search-box input:focus { border-color: var(--vg-primary); }
			.vehicle-search-icon {
				position: absolute;
				left: 12px;
				top: 50%;
				transform: translateY(-50%);
				color: var(--vg-text-muted);
				pointer-events: none;
			}
			.vehicle-filter-controls {
				display: flex;
				flex-wrap: wrap;
				gap: 10px;
				align-items: center;
			}
			.vehicle-filter-select {
				padding: 9px 14px;
				border: 1px solid var(--vg-border);
				border-radius: 8px;
				font-size: 13.5px;
				color: var(--vg-text);
				background-color: #fff;
				cursor: pointer;
				outline: none;
			}
			.vehicle-filter-select:focus { border-color: var(--vg-primary); }
			.vehicle-reset-btn {
				background: #f3f4f6;
				color: var(--vg-text-muted);
				border: 1px solid var(--vg-border);
				padding: 9px 14px;
				border-radius: 8px;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				transition: 0.2s;
			}
			.vehicle-reset-btn:hover { background: #e5e7eb; color: var(--vg-text); }

			/* Product Grid Layout */
			.vehicle-grid {
				display: grid;
				grid-template-columns: repeat(var(--vg-cols, 3), 1fr);
				gap: 26px;
			}
			@media (max-width: 1024px) {
				.vehicle-grid { grid-template-columns: repeat(2, 1fr) !important; }
			}
			@media (max-width: 640px) {
				.vehicle-grid { grid-template-columns: 1fr !important; }
			}

			/* Product Card */
			.vehicle-card {
				background: var(--vg-card-bg);
				border: 1px solid var(--vg-border);
				border-radius: var(--vg-radius);
				overflow: hidden;
				display: flex;
				flex-direction: column;
				transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
				box-shadow: var(--vg-shadow);
				position: relative;
			}
			.vehicle-card:hover {
				transform: translateY(-5px);
				box-shadow: var(--vg-shadow-hover);
				border-color: #cbd5e1;
			}

			/* Card Image & Badges */
			.vehicle-card-img-wrap {
				position: relative;
				width: 100%;
				height: 220px;
				background: #f1f5f9;
				overflow: hidden;
			}
			.vehicle-card-img {
				width: 100%;
				height: 100%;
				object-fit: cover;
				transition: transform 0.4s ease;
			}
			.vehicle-card:hover .vehicle-card-img {
				transform: scale(1.05);
			}
			.vehicle-card-placeholder {
				width: 100%;
				height: 100%;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				color: #94a3b8;
				background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
			}
			.vehicle-card-placeholder svg { width: 56px; height: 56px; fill: currentColor; }
			.vehicle-badge-top {
				position: absolute;
				top: 12px;
				left: 12px;
				background: rgba(15, 23, 42, 0.82);
				color: #fff;
				font-size: 11.5px;
				font-weight: 700;
				padding: 4px 10px;
				border-radius: 6px;
				letter-spacing: 0.5px;
				text-transform: uppercase;
				backdrop-filter: blur(4px);
				z-index: 2;
			}
			.vehicle-badge-id {
				position: absolute;
				top: 12px;
				right: 12px;
				background: #fff;
				color: var(--vg-text);
				font-size: 11px;
				font-weight: 700;
				padding: 3px 8px;
				border-radius: 4px;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
				z-index: 2;
			}

			/* Card Body */
			.vehicle-card-body {
				padding: 18px 20px;
				display: flex;
				flex-direction: column;
				flex: 1;
			}
			.vehicle-card-header {
				margin-bottom: 12px;
			}
			.vehicle-card-title {
				font-size: 17px;
				font-weight: 700;
				color: var(--vg-text);
				margin: 0 0 6px 0;
				line-height: 1.35;
			}
			.vehicle-card-price {
				font-size: 19px;
				font-weight: 800;
				color: var(--vg-primary);
				display: flex;
				align-items: center;
				gap: 4px;
			}

			/* Key Specs Pills Grid */
			.vehicle-specs-grid {
				display: grid;
				grid-template-columns: repeat(2, 1fr);
				gap: 8px;
				margin: 14px 0 18px 0;
				padding: 12px;
				background: #f8fafc;
				border-radius: 8px;
				border: 1px solid #edf2f7;
			}
			.vehicle-spec-item {
				display: flex;
				align-items: center;
				gap: 7px;
				font-size: 12.5px;
				color: var(--vg-text);
			}
			.vehicle-spec-icon {
				font-size: 14px;
				line-height: 1;
				opacity: 0.85;
			}
			.vehicle-spec-label {
				color: var(--vg-text-muted);
				font-size: 11px;
				display: block;
			}
			.vehicle-spec-val {
				font-weight: 600;
				color: var(--vg-text);
			}

			/* Card Actions */
			.vehicle-card-footer {
				margin-top: auto;
				padding-top: 14px;
				border-top: 1px solid #f1f5f9;
				display: flex;
				gap: 10px;
			}
			.vehicle-btn {
				flex: 1;
				text-align: center;
				padding: 10px 14px;
				border-radius: 8px;
				font-size: 13.5px;
				font-weight: 600;
				text-decoration: none !important;
				cursor: pointer;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				gap: 6px;
				transition: 0.2s ease;
				border: none;
			}
			.vehicle-btn-primary {
				background: var(--vg-primary);
				color: #fff !important;
			}
			.vehicle-btn-primary:hover {
				background: var(--vg-primary-hover);
				color: #fff !important;
			}
			.vehicle-btn-outline {
				background: #fff;
				color: var(--vg-text) !important;
				border: 1px solid var(--vg-border);
			}
			.vehicle-btn-outline:hover {
				background: #f3f4f6;
				border-color: #cbd5e1;
			}
			.vehicle-btn-whatsapp {
				background: #25d366;
				color: #fff !important;
			}
			.vehicle-btn-whatsapp:hover {
				background: #1eb956;
				color: #fff !important;
			}

			/* Quick Details Modal */
			.vehicle-modal-backdrop {
				position: fixed;
				inset: 0;
				background: rgba(15, 23, 42, 0.65);
				backdrop-filter: blur(4px);
				display: none;
				align-items: center;
				justify-content: center;
				z-index: 999999;
				padding: 20px;
			}
			.vehicle-modal-content {
				background: #fff;
				border-radius: 16px;
				max-width: 750px;
				width: 100%;
				max-height: 90vh;
				overflow-y: auto;
				position: relative;
				box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
				animation: vgModalIn 0.25s ease-out;
			}
			@keyframes vgModalIn {
				from { opacity: 0; transform: scale(0.95) translateY(10px); }
				to { opacity: 1; transform: scale(1) translateY(0); }
			}
			.vehicle-modal-close {
				position: absolute;
				top: 14px;
				right: 16px;
				background: rgba(0,0,0,0.4);
				color: #fff;
				border: none;
				border-radius: 50%;
				width: 32px;
				height: 32px;
				font-size: 20px;
				cursor: pointer;
				display: flex;
				align-items: center;
				justify-content: center;
				z-index: 10;
				transition: 0.2s;
			}
			.vehicle-modal-close:hover { background: rgba(0,0,0,0.7); }
			.vehicle-modal-img {
				width: 100%;
				height: 300px;
				object-fit: cover;
				background: #f1f5f9;
			}
			.vehicle-modal-body {
				padding: 24px 28px;
			}
			.vehicle-modal-title {
				font-size: 22px;
				font-weight: 800;
				margin: 0 0 8px 0;
				color: var(--vg-text);
			}
			.vehicle-modal-price {
				font-size: 24px;
				font-weight: 800;
				color: var(--vg-primary);
				margin-bottom: 18px;
			}
			.vehicle-modal-specs-table {
				width: 100%;
				border-collapse: collapse;
				margin: 16px 0;
			}
			.vehicle-modal-specs-table th, .vehicle-modal-specs-table td {
				padding: 10px 14px;
				border-bottom: 1px solid #f1f5f9;
				text-align: left;
				font-size: 13.5px;
			}
			.vehicle-modal-specs-table th {
				color: var(--vg-text-muted);
				font-weight: 600;
				width: 40%;
				background: #f8fafc;
			}
			.vehicle-modal-desc {
				font-size: 14px;
				line-height: 1.6;
				color: var(--vg-text-muted);
				margin-top: 14px;
				padding-top: 14px;
				border-top: 1px solid #f1f5f9;
			}
			.vehicle-no-results {
				grid-column: 1 / -1;
				text-align: center;
				padding: 50px 20px;
				background: #f8fafc;
				border-radius: var(--vg-radius);
				border: 1px dashed var(--vg-border);
				color: var(--vg-text-muted);
			}
		' );

		// Frontend interactive script for filtering & modal.
		wp_register_script( 'dynamic-vehicle-grid-js', false, array( 'jquery' ), false, true );
		wp_enqueue_script( 'dynamic-vehicle-grid-js' );

		wp_add_inline_script( 'dynamic-vehicle-grid-js', '
			jQuery(document).ready(function($) {
				// Client-side quick filter and search.
				function applyVehicleFilters() {
					var searchTerm = $(".vehicle-search-input").val() ? $(".vehicle-search-input").val().toLowerCase() : "";
					var fuelFilter = $(".vehicle-filter-fuel").val() || "";
					var yearFilter = $(".vehicle-filter-year").val() || "";
					var transFilter = $(".vehicle-filter-transmission").val() || "";

					var visibleCount = 0;

					$(".vehicle-card").each(function() {
						var $card = $(this);
						var title = ($card.data("title") || "").toLowerCase();
						var carId = ($card.data("id") || "").toLowerCase();
						var fuel = $card.data("fuel") || "";
						var year = $card.data("year") || "";
						var trans = $card.data("transmission") || "";

						var matchesSearch = !searchTerm || title.indexOf(searchTerm) > -1 || carId.indexOf(searchTerm) > -1;
						var matchesFuel = !fuelFilter || fuel == fuelFilter;
						var matchesYear = !yearFilter || year == yearFilter;
						var matchesTrans = !transFilter || trans == transFilter;

						if (matchesSearch && matchesFuel && matchesYear && matchesTrans) {
							$card.fadeIn(200);
							visibleCount++;
						} else {
							$card.fadeOut(200);
						}
					});

					if (visibleCount === 0) {
						$(".vehicle-no-results").show();
					} else {
						$(".vehicle-no-results").hide();
					}
				}

				$(document).on("input keyup", ".vehicle-search-input", function() {
					applyVehicleFilters();
				});

				$(document).on("change", ".vehicle-filter-select", function() {
					applyVehicleFilters();
				});

				$(document).on("click", ".vehicle-reset-btn", function() {
					$(".vehicle-search-input").val("");
					$(".vehicle-filter-select").val("");
					applyVehicleFilters();
				});

				// Quick Details Modal Trigger.
				$(document).on("click", ".btn-view-vehicle-details", function(e) {
					e.preventDefault();
					var modalId = $(this).data("target-modal");
					$("#" + modalId).css("display", "flex");
					$("body").css("overflow", "hidden");
				});

				$(document).on("click", ".vehicle-modal-close, .vehicle-modal-backdrop", function(e) {
					if (e.target === this) {
						$(".vehicle-modal-backdrop").hide();
						$("body").css("overflow", "auto");
					}
				});
			});
		' );
	}

	/**
	 * Add admin submenu under Settings.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Sheet Sync & Vehicle Inventory Settings', 'dynamic-sheet-sync' ),
			__( 'Vehicle Sheet Sync', 'dynamic-sheet-sync' ),
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

		// Unique Identifier mappings (Default Car ID or Column B).
		if ( isset( $input['uid_sheet_col'] ) ) {
			$output['uid_sheet_col'] = sanitize_text_field( trim( $input['uid_sheet_col'] ) );
		}
		if ( isset( $input['uid_meta_key'] ) ) {
			$output['uid_meta_key'] = sanitize_key( trim( $input['uid_meta_key'] ) );
		}

		// Title Template (e.g. {Car Name} {Model} {Year} or {C} {D} {E}).
		if ( isset( $input['title_template'] ) ) {
			$output['title_template'] = sanitize_text_field( trim( $input['title_template'] ) );
		}

		// Standard Field mappings.
		if ( isset( $input['field_content'] ) ) {
			$output['field_content'] = sanitize_text_field( trim( $input['field_content'] ) );
		}
		if ( isset( $input['field_image'] ) ) {
			$output['field_image'] = sanitize_text_field( trim( $input['field_image'] ) );
		}
		if ( isset( $input['field_status'] ) ) {
			$output['field_status'] = sanitize_text_field( trim( $input['field_status'] ) );
		}
		if ( isset( $input['field_price'] ) ) {
			$output['field_price'] = sanitize_text_field( trim( $input['field_price'] ) );
		}
		if ( isset( $input['currency_symbol'] ) ) {
			$output['currency_symbol'] = sanitize_text_field( trim( $input['currency_symbol'] ) );
		}
		if ( isset( $input['whatsapp_number'] ) ) {
			$output['whatsapp_number'] = sanitize_text_field( trim( $input['whatsapp_number'] ) );
		}

		// Custom Meta & Specs Mappings (Repeater).
		$output['custom_meta'] = array();
		if ( isset( $input['custom_meta'] ) && is_array( $input['custom_meta'] ) ) {
			foreach ( $input['custom_meta'] as $row ) {
				$sheet_col = isset( $row['sheet_col'] ) ? sanitize_text_field( trim( $row['sheet_col'] ) ) : '';
				$meta_key  = isset( $row['meta_key'] ) ? sanitize_key( trim( $row['meta_key'] ) ) : '';
				$label     = isset( $row['label'] ) ? sanitize_text_field( trim( $row['label'] ) ) : '';
				$icon      = isset( $row['icon'] ) ? sanitize_text_field( trim( $row['icon'] ) ) : '';
				$display   = isset( $row['display'] ) ? sanitize_text_field( trim( $row['display'] ) ) : 'primary_spec';

				if ( ! empty( $sheet_col ) && ! empty( $meta_key ) ) {
					$output['custom_meta'][] = array(
						'sheet_col' => $sheet_col,
						'meta_key'  => $meta_key,
						'label'     => ! empty( $label ) ? $label : $sheet_col,
						'icon'      => $icon,
						'display'   => $display,
					);
				}
			}
		}

		// Sync Frequency.
		if ( isset( $input['frequency'] ) ) {
			$output['frequency'] = sanitize_text_field( $input['frequency'] );
		}

		// Keep existing log stats/results.
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
	 * Convert Column Letter (e.g. A, B, C, AA) to 0-based column index.
	 */
	public static function letter_to_col_index( $letter ) {
		$letter = strtoupper( trim( $letter ) );
		if ( ! preg_match( '/^[A-Z]+$/', $letter ) ) {
			return false;
		}
		$length = strlen( $letter );
		$index  = 0;
		for ( $i = 0; $i < $length; $i++ ) {
			$index = $index * 26 + ( ord( $letter[$i] ) - ord( 'A' ) + 1 );
		}
		return $index - 1; // Return 0-indexed
	}

	/**
	 * Resolve column index in row from either Column Letter (A, B, C...) or Column Header Name.
	 */
	public static function resolve_col_index( $col_identifier, $headers ) {
		if ( empty( $col_identifier ) ) {
			return false;
		}

		$col_identifier = trim( $col_identifier );

		// 1. Check if column identifier is a pure Column Letter (A, B, C... Z, AA...).
		if ( preg_match( '/^[A-Za-z]+$/', $col_identifier ) ) {
			$letter_idx = self::letter_to_col_index( $col_identifier );
			// If it matches an exact header name, prefer header name, otherwise use letter index.
			foreach ( $headers as $idx => $header ) {
				if ( strcasecmp( $header, $col_identifier ) === 0 ) {
					return $idx;
				}
			}
			return $letter_idx;
		}

		// 2. Look up case-insensitive header name match.
		foreach ( $headers as $idx => $header ) {
			if ( strcasecmp( trim( $header ), $col_identifier ) === 0 ) {
				return $idx;
			}
		}

		return false;
	}

	/**
	 * Build dynamic title from template or multi-column definitions.
	 */
	public static function build_dynamic_title( $template, $row_data, $headers, $fallback_id = '' ) {
		if ( empty( $template ) ) {
			$template = '{Car Name} {Model} {Year}';
		}

		// Extract all placeholders like {Car Name}, {Model}, {C}, {D}, {E}.
		$title = preg_replace_callback( '/\{([^}]+)\}/', function( $matches ) use ( $row_data, $headers ) {
			$col_ref = trim( $matches[1] );
			$idx = Dynamic_Sheet_Post_Sync::resolve_col_index( $col_ref, $headers );
			if ( false !== $idx && isset( $row_data[$idx] ) && '' !== trim( $row_data[$idx] ) ) {
				return trim( $row_data[$idx] );
			}
			return '';
		}, $template );

		$title = trim( preg_replace( '/\s+/', ' ', $title ) );

		// If template didn't match placeholders or resulted in empty string, try comma/space separated list.
		if ( empty( $title ) ) {
			$cols = array_map( 'trim', explode( ',', $template ) );
			$collected = array();
			foreach ( $cols as $col ) {
				$idx = Dynamic_Sheet_Post_Sync::resolve_col_index( $col, $headers );
				if ( false !== $idx && isset( $row_data[$idx] ) && '' !== trim( $row_data[$idx] ) ) {
					$collected[] = trim( $row_data[$idx] );
				}
			}
			if ( ! empty( $collected ) ) {
				$title = implode( ' ', $collected );
			}
		}

		// Clean final fallback (NEVER output "Sheet Row Sync").
		if ( empty( $title ) ) {
			$title = ! empty( $fallback_id ) ? sprintf( 'Vehicle #%s', $fallback_id ) : 'Vehicle Listing';
		}

		return $title;
	}

	/**
	 * Render settings page template with tabs and modern controls.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = get_option( 'dynamic_sheet_sync_options', array() );

		// Set default values.
		$csv_url         = isset( $options['csv_url'] ) ? $options['csv_url'] : '';
		$target_pt       = isset( $options['post_type'] ) ? $options['post_type'] : 'post';
		$uid_sheet_col   = isset( $options['uid_sheet_col'] ) ? $options['uid_sheet_col'] : 'Car ID';
		$uid_meta_key    = isset( $options['uid_meta_key'] ) ? $options['uid_meta_key'] : '_vehicle_car_id';
		$title_template  = isset( $options['title_template'] ) ? $options['title_template'] : '{Car Name} {Model} {Year}';
		$field_content   = isset( $options['field_content'] ) ? $options['field_content'] : 'Description';
		$field_image     = isset( $options['field_image'] ) ? $options['field_image'] : 'Image URL';
		$field_status    = isset( $options['field_status'] ) ? $options['field_status'] : 'Status';
		$field_price     = isset( $options['field_price'] ) ? $options['field_price'] : 'Price';
		$currency_symbol = isset( $options['currency_symbol'] ) ? $options['currency_symbol'] : '$';
		$whatsapp_number = isset( $options['whatsapp_number'] ) ? $options['whatsapp_number'] : '';
		$frequency       = isset( $options['frequency'] ) ? $options['frequency'] : 'hourly';
		$custom_meta     = isset( $options['custom_meta'] ) && is_array( $options['custom_meta'] ) ? $options['custom_meta'] : array();

		// Default initial vehicle custom meta rows if empty.
		if ( empty( $custom_meta ) ) {
			$custom_meta = array(
				array( 'sheet_col' => 'Year', 'meta_key' => '_vehicle_year', 'label' => 'Year', 'icon' => '🗓️', 'display' => 'badge' ),
				array( 'sheet_col' => 'Mileage', 'meta_key' => '_vehicle_mileage', 'label' => 'Mileage', 'icon' => '🛣️', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Fuel Type', 'meta_key' => '_vehicle_fuel', 'label' => 'Fuel', 'icon' => '⛽', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Transmission', 'meta_key' => '_vehicle_transmission', 'label' => 'Gearbox', 'icon' => '🕹️', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Engine', 'meta_key' => '_vehicle_engine', 'label' => 'Engine', 'icon' => '⚙️', 'display' => 'detail' ),
				array( 'sheet_col' => 'Color', 'meta_key' => '_vehicle_color', 'label' => 'Color', 'icon' => '🎨', 'display' => 'detail' ),
			);
		}

		$registered_pts = $this->get_registered_post_types();
		?>
		<div class="wrap sheet-sync-container">
			<div class="sheet-sync-card">
				<div class="sheet-sync-header">
					<div>
						<h1>🚗 <?php esc_html_e( 'Vehicle Sheet Sync & Product Catalog', 'dynamic-sheet-sync' ); ?></h1>
						<p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;"><?php esc_html_e( 'Seamlessly import vehicle inventory from Google Sheets and display as high-converting WooCommerce-style product cards.', 'dynamic-sheet-sync' ); ?></p>
					</div>
					<span class="sheet-sync-badge">v2.0 PRO</span>
				</div>

				<?php settings_errors(); ?>

				<!-- Sync Status Box -->
				<div class="status-box">
					<div class="status-box-content">
						<p><strong><?php esc_html_e( 'Last Sync Status:', 'dynamic-sheet-sync' ); ?></strong> 
							<?php 
							if ( ! empty( $options['last_sync_time'] ) ) {
								$timestamp = get_date_from_gmt( $options['last_sync_time'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
								printf(
									/* translators: 1: formatted date/time, 2: total items, 3: created count, 4: updated count */
									esc_html__( 'Last run on %1$s. Processed %2$d vehicles (Created: %3$d, Updated: %4$d). Status: %5$s', 'dynamic-sheet-sync' ),
									esc_html( $timestamp ),
									intval( $options['last_sync_total'] ),
									intval( $options['last_sync_created'] ),
									intval( $options['last_sync_updated'] ),
									esc_html( $options['last_sync_status'] )
								);
							} else {
								esc_html_e( 'Never run yet. Configure settings and click Sync Now.', 'dynamic-sheet-sync' );
							}
							?>
						</p>
					</div>
					<div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
							<?php wp_nonce_field( 'manual_sheet_sync_action', 'manual_sheet_sync_nonce' ); ?>
							<input type="hidden" name="action" value="dynamic_sheet_sync_now" />
							<button type="submit" class="button button-primary" style="padding: 4px 16px; height: 36px; font-weight: 600;">🔄 <?php esc_html_e( 'Sync Now', 'dynamic-sheet-sync' ); ?></button>
						</form>
					</div>
				</div>

				<!-- Navigation Tabs -->
				<div class="sheet-nav-tabs">
					<a class="sheet-nav-tab active" data-tab="tab-sheet-config">⚙️ <?php esc_html_e( 'Sheet & Column Mapping', 'dynamic-sheet-sync' ); ?></a>
					<a class="sheet-nav-tab" data-tab="tab-specs-repeater">📋 <?php esc_html_e( 'Vehicle Specs & Meta Attributes', 'dynamic-sheet-sync' ); ?></a>
					<a class="sheet-nav-tab" data-tab="tab-shortcodes">🎨 <?php esc_html_e( 'Frontend Shortcodes & Showcase', 'dynamic-sheet-sync' ); ?></a>
				</div>

				<!-- Settings Form -->
				<form method="post" action="options.php" id="vehicle-sheet-sync-form">
					<?php settings_fields( 'dynamic_sheet_sync_group' ); ?>

					<!-- TAB 1: SHEET & COLUMN CONFIG -->
					<div class="sheet-tab-panel active" id="tab-sheet-config">
						<table class="form-table sheet-sync-table">
							<!-- Google Sheet CSV URL -->
							<tr>
								<th scope="row"><label for="csv_url"><?php esc_html_e( 'Google Sheet Published CSV URL', 'dynamic-sheet-sync' ); ?></label></th>
								<td>
									<input type="text" id="csv_url" name="dynamic_sheet_sync_options[csv_url]" value="<?php echo esc_url( $csv_url ); ?>" placeholder="https://docs.google.com/spreadsheets/d/.../pub?output=csv" class="regular-text" />
									<p class="description"><?php esc_html_e( 'In Google Sheets: File > Share > Publish to web > Select CSV format and paste the URL here.', 'dynamic-sheet-sync' ); ?></p>
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
									<p class="description"><?php esc_html_e( 'Select the post type to store vehicles into (e.g. Posts, Products, or custom Vehicle post type).', 'dynamic-sheet-sync' ); ?></p>
								</td>
							</tr>

							<!-- Unique Identifier Section -->
							<tr style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
								<th scope="row" style="padding-left: 12px;">
									<strong><?php esc_html_e( 'Car ID / Unique Identifier', 'dynamic-sheet-sync' ); ?></strong>
								</th>
								<td>
									<div style="display: flex; gap: 15px; flex-wrap: wrap;">
										<div style="flex: 1; max-width: 250px;">
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;" for="uid_sheet_col"><?php esc_html_e( 'Sheet Column (Header Name or Letter)', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="uid_sheet_col" name="dynamic_sheet_sync_options[uid_sheet_col]" value="<?php echo esc_attr( $uid_sheet_col ); ?>" placeholder="e.g. Car ID or B" />
										</div>
										<div style="flex: 1; max-width: 250px;">
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;" for="uid_meta_key"><?php esc_html_e( 'WordPress Meta Key', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="uid_meta_key" name="dynamic_sheet_sync_options[uid_meta_key]" value="<?php echo esc_attr( $uid_meta_key ); ?>" placeholder="e.g. _vehicle_car_id or _sku" />
										</div>
									</div>
									<p class="description"><?php esc_html_e( 'Enter Column Letter (e.g. B) or Header Name (e.g. Car ID). This prevents duplicate imports and updates existing vehicles.', 'dynamic-sheet-sync' ); ?></p>
								</td>
							</tr>

							<!-- Dynamic Multi-Column Title Builder -->
							<tr>
								<th scope="row"><label for="title_template"><?php esc_html_e( 'Product Title Template', 'dynamic-sheet-sync' ); ?></label></th>
								<td>
									<input type="text" id="title_template" name="dynamic_sheet_sync_options[title_template]" value="<?php echo esc_attr( $title_template ); ?>" placeholder="{Car Name} {Model} {Year} or {C} {D} {E}" class="regular-text" />
									<p class="description" style="margin-top: 4px;">
										<?php esc_html_e( 'Combine multiple columns into the vehicle title. Examples:', 'dynamic-sheet-sync' ); ?>
										<br/>
										<span class="helper-tag" onclick="document.getElementById('title_template').value='{Car Name} {Model} {Year}'">{Car Name} {Model} {Year}</span>
										<span class="helper-tag" onclick="document.getElementById('title_template').value='{C} {D} {E}'">{C} {D} {E} (Column Letters)</span>
										<span class="helper-tag" onclick="document.getElementById('title_template').value='{Year} {Car Name} {Model}'">{Year} {Car Name} {Model}</span>
									</p>
								</td>
							</tr>

							<!-- Standard Mappings -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Core Vehicle Field Columns', 'dynamic-sheet-sync' ); ?></th>
								<td>
									<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; max-width: 520px;">
										<div>
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="field_price"><?php esc_html_e( 'Price Column', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="field_price" name="dynamic_sheet_sync_options[field_price]" value="<?php echo esc_attr( $field_price ); ?>" placeholder="e.g. Price or F" />
										</div>
										<div>
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="field_image"><?php esc_html_e( 'Featured Image URL Column', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="field_image" name="dynamic_sheet_sync_options[field_image]" value="<?php echo esc_attr( $field_image ); ?>" placeholder="e.g. Image URL or G" />
										</div>
										<div>
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="field_content"><?php esc_html_e( 'Description / Details Column', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="field_content" name="dynamic_sheet_sync_options[field_content]" value="<?php echo esc_attr( $field_content ); ?>" placeholder="e.g. Description or Details" />
										</div>
										<div>
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="field_status"><?php esc_html_e( 'Stock / Post Status Column', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="field_status" name="dynamic_sheet_sync_options[field_status]" value="<?php echo esc_attr( $field_status ); ?>" placeholder="e.g. Status (publish/draft)" />
										</div>
									</div>
								</td>
							</tr>

							<!-- Global Display Settings -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Pricing & Inquiries', 'dynamic-sheet-sync' ); ?></th>
								<td>
									<div style="display: flex; gap: 14px; max-width: 520px;">
										<div style="flex: 1;">
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="currency_symbol"><?php esc_html_e( 'Currency Symbol', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="currency_symbol" name="dynamic_sheet_sync_options[currency_symbol]" value="<?php echo esc_attr( $currency_symbol ); ?>" placeholder="$" style="width: 80px;" />
										</div>
										<div style="flex: 2;">
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="whatsapp_number"><?php esc_html_e( 'WhatsApp Inquiry Phone (Optional)', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="whatsapp_number" name="dynamic_sheet_sync_options[whatsapp_number]" value="<?php echo esc_attr( $whatsapp_number ); ?>" placeholder="e.g. 15551234567" />
										</div>
									</div>
								</td>
							</tr>

							<!-- Sync Frequency Selection -->
							<tr>
								<th scope="row"><label for="frequency"><?php esc_html_e( 'Automatic Sync Frequency', 'dynamic-sheet-sync' ); ?></label></th>
								<td>
									<select id="frequency" name="dynamic_sheet_sync_options[frequency]">
										<option value="manual" <?php selected( $frequency, 'manual' ); ?>><?php esc_html_e( 'Manual Only (Disable Background Cron)', 'dynamic-sheet-sync' ); ?></option>
										<option value="every_15_minutes" <?php selected( $frequency, 'every_15_minutes' ); ?>><?php esc_html_e( 'Every 15 Minutes', 'dynamic-sheet-sync' ); ?></option>
										<option value="hourly" <?php selected( $frequency, 'hourly' ); ?>><?php esc_html_e( 'Hourly (Recommended)', 'dynamic-sheet-sync' ); ?></option>
										<option value="twicedaily" <?php selected( $frequency, 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'dynamic-sheet-sync' ); ?></option>
										<option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Daily', 'dynamic-sheet-sync' ); ?></option>
									</select>
								</td>
							</tr>
						</table>
					</div>

					<!-- TAB 2: SPECS & META REPEATER -->
					<div class="sheet-tab-panel" id="tab-specs-repeater">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
							<div>
								<h3 style="margin:0 0 4px 0; font-size: 16px;"><?php esc_html_e( 'Vehicle Specs & Custom Attributes Mapping', 'dynamic-sheet-sync' ); ?></h3>
								<p class="description" style="margin:0;"><?php esc_html_e( 'Map any additional Google Sheet columns to WordPress vehicle specs to display them on product cards or details popup.', 'dynamic-sheet-sync' ); ?></p>
							</div>
							<button type="button" class="button button-secondary" id="btn-add-meta-row">+ <?php esc_html_e( 'Add Spec Column', 'dynamic-sheet-sync' ); ?></button>
						</div>

						<table class="repeater-table" id="custom-meta-repeater-table">
							<thead>
								<tr>
									<th style="width: 22%;"><?php esc_html_e( 'Sheet Column (Header/Letter)', 'dynamic-sheet-sync' ); ?></th>
									<th style="width: 22%;"><?php esc_html_e( 'WordPress Meta Key', 'dynamic-sheet-sync' ); ?></th>
									<th style="width: 20%;"><?php esc_html_e( 'Card Display Label', 'dynamic-sheet-sync' ); ?></th>
									<th style="width: 12%;"><?php esc_html_e( 'Icon / Emoji', 'dynamic-sheet-sync' ); ?></th>
									<th style="width: 18%;"><?php esc_html_e( 'Show On Card', 'dynamic-sheet-sync' ); ?></th>
									<th style="width: 6%; text-align: center;"><?php esc_html_e( 'Action', 'dynamic-sheet-sync' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$index = 0;
								if ( ! empty( $custom_meta ) ) :
									foreach ( $custom_meta as $row ) :
										$disp = isset( $row['display'] ) ? $row['display'] : 'primary_spec';
										?>
										<tr class="repeater-row">
											<td>
												<input type="text" name="dynamic_sheet_sync_options[custom_meta][<?php echo intval( $index ); ?>][sheet_col]" value="<?php echo esc_attr( $row['sheet_col'] ); ?>" placeholder="e.g. Mileage or H" />
											</td>
											<td>
												<input type="text" name="dynamic_sheet_sync_options[custom_meta][<?php echo intval( $index ); ?>][meta_key]" value="<?php echo esc_attr( $row['meta_key'] ); ?>" placeholder="e.g. _vehicle_mileage" />
											</td>
											<td>
												<input type="text" name="dynamic_sheet_sync_options[custom_meta][<?php echo intval( $index ); ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="e.g. Mileage" />
											</td>
											<td>
												<input type="text" name="dynamic_sheet_sync_options[custom_meta][<?php echo intval( $index ); ?>][icon]" value="<?php echo esc_attr( isset( $row['icon'] ) ? $row['icon'] : '' ); ?>" placeholder="e.g. 🛣️" style="text-align: center;" />
											</td>
											<td>
												<select name="dynamic_sheet_sync_options[custom_meta][<?php echo intval( $index ); ?>][display]">
													<option value="primary_spec" <?php selected( $disp, 'primary_spec' ); ?>><?php esc_html_e( 'Key Spec (Card Pill)', 'dynamic-sheet-sync' ); ?></option>
													<option value="badge" <?php selected( $disp, 'badge' ); ?>><?php esc_html_e( 'Top Image Badge', 'dynamic-sheet-sync' ); ?></option>
													<option value="detail" <?php selected( $disp, 'detail' ); ?>><?php esc_html_e( 'Details Popup Table', 'dynamic-sheet-sync' ); ?></option>
													<option value="hidden" <?php selected( $disp, 'hidden' ); ?>><?php esc_html_e( 'Hidden (Meta Only)', 'dynamic-sheet-sync' ); ?></option>
												</select>
											</td>
											<td style="text-align: center; vertical-align: middle;">
												<span class="btn-remove-row" title="<?php esc_attr_e( 'Remove Row', 'dynamic-sheet-sync' ); ?>">&times;</span>
											</td>
										</tr>
										<?php
										$index++;
									endforeach;
								endif;
								?>
							</tbody>
						</table>
					</div>

					<!-- TAB 3: SHORTCODE GENERATOR & SHOWCASE -->
					<div class="sheet-tab-panel" id="tab-shortcodes">
						<h3 style="margin: 0 0 8px 0; font-size: 16px;"><?php esc_html_e( 'WooCommerce-Style Vehicle Showcase Shortcodes', 'dynamic-sheet-sync' ); ?></h3>
						<p style="color: #64748b; font-size: 13.5px; margin-bottom: 20px;">
							<?php esc_html_e( 'Paste these shortcodes into any page, post, or Elementor/Gutenberg block to display your live vehicle inventory catalog.', 'dynamic-sheet-sync' ); ?>
						</p>

						<div style="margin-bottom: 20px;">
							<label style="font-weight: 700; display: block; margin-bottom: 4px;"><?php esc_html_e( '1. Complete Vehicle Product Grid with Search & Filters (Recommended)', 'dynamic-sheet-sync' ); ?></label>
							<div class="shortcode-preview-box">
								<code>[vehicle_products columns="3" posts_per_page="12" show_filter="yes" show_search="yes"]</code>
								<button type="button" class="copy-btn" onclick="navigator.clipboard.writeText('[vehicle_products columns=\'3\' posts_per_page=\'12\' show_filter=\'yes\' show_search=\'yes\']'); alert('Shortcode copied!');"><?php esc_html_e( 'Copy Shortcode', 'dynamic-sheet-sync' ); ?></button>
							</div>
						</div>

						<div style="margin-bottom: 20px;">
							<label style="font-weight: 700; display: block; margin-bottom: 4px;"><?php esc_html_e( '2. 4-Column Clean Product Grid (No Filters)', 'dynamic-sheet-sync' ); ?></label>
							<div class="shortcode-preview-box">
								<code>[vehicle_products columns="4" posts_per_page="8" show_filter="no" show_search="no"]</code>
								<button type="button" class="copy-btn" onclick="navigator.clipboard.writeText('[vehicle_products columns=\'4\' posts_per_page=\'8\' show_filter=\'no\' show_search=\'no\']'); alert('Shortcode copied!');"><?php esc_html_e( 'Copy Shortcode', 'dynamic-sheet-sync' ); ?></button>
							</div>
						</div>

						<div style="margin-bottom: 20px;">
							<label style="font-weight: 700; display: block; margin-bottom: 4px;"><?php esc_html_e( '3. WhatsApp Direct Inquiry Enabled Grid', 'dynamic-sheet-sync' ); ?></label>
							<div class="shortcode-preview-box">
								<code>[vehicle_products columns="3" whatsapp="15551234567" currency="$"]</code>
								<button type="button" class="copy-btn" onclick="navigator.clipboard.writeText('[vehicle_products columns=\'3\' whatsapp=\'15551234567\' currency=\'$\']'); alert('Shortcode copied!');"><?php esc_html_e( 'Copy Shortcode', 'dynamic-sheet-sync' ); ?></button>
							</div>
						</div>
					</div>

					<div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
						<?php submit_button( __( 'Save All Changes', 'dynamic-sheet-sync' ), 'primary', 'submit', false ); ?>
					</div>
				</form>
			</div>
		</div>

		<script>
			jQuery(document).ready(function($) {
				// Tab switching.
				$('.sheet-nav-tab').on('click', function(e) {
					e.preventDefault();
					$('.sheet-nav-tab').removeClass('active');
					$('.sheet-tab-panel').removeClass('active');

					$(this).addClass('active');
					$('#' + $(this).data('tab')).addClass('active');
				});

				var rowIndex = <?php echo intval( $index ); ?>;

				// Click handler to append a new metadata field mapping row.
				$('#btn-add-meta-row').on('click', function() {
					var html = '<tr class="repeater-row">' +
						'<td><input type="text" name="dynamic_sheet_sync_options[custom_meta][' + rowIndex + '][sheet_col]" placeholder="e.g. Color or H" value="" /></td>' +
						'<td><input type="text" name="dynamic_sheet_sync_options[custom_meta][' + rowIndex + '][meta_key]" placeholder="e.g. _vehicle_color" value="" /></td>' +
						'<td><input type="text" name="dynamic_sheet_sync_options[custom_meta][' + rowIndex + '][label]" placeholder="e.g. Color" value="" /></td>' +
						'<td><input type="text" name="dynamic_sheet_sync_options[custom_meta][' + rowIndex + '][icon]" placeholder="e.g. 🎨" style="text-align:center;" value="" /></td>' +
						'<td>' +
							'<select name="dynamic_sheet_sync_options[custom_meta][' + rowIndex + '][display]">' +
								'<option value="primary_spec">Key Spec (Card Pill)</option>' +
								'<option value="badge">Top Image Badge</option>' +
								'<option value="detail">Details Popup Table</option>' +
								'<option value="hidden">Hidden (Meta Only)</option>' +
							'</select>' +
						'</td>' +
						'<td style="text-align: center; vertical-align: middle;"><span class="btn-remove-row">&times;</span></td>' +
						'</tr>';
					$('#custom-meta-repeater-table tbody').append(html);
					rowIndex++;
				});

				// Event delegation for removal.
				$('#custom-meta-repeater-table').on('click', '.btn-remove-row', function() {
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
				esc_html__( 'Manual vehicle synchronization completed successfully.', 'dynamic-sheet-sync' ),
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

		$csv_url        = isset( $options['csv_url'] ) ? $options['csv_url'] : '';
		$post_type      = isset( $options['post_type'] ) ? $options['post_type'] : 'post';
		$uid_sheet_col  = isset( $options['uid_sheet_col'] ) ? $options['uid_sheet_col'] : 'Car ID';
		$uid_meta_key   = isset( $options['uid_meta_key'] ) ? $options['uid_meta_key'] : '_vehicle_car_id';
		$title_template = isset( $options['title_template'] ) ? $options['title_template'] : '{Car Name} {Model} {Year}';

		// If critical configurations are missing, abort immediately.
		if ( empty( $csv_url ) || empty( $uid_sheet_col ) || empty( $uid_meta_key ) ) {
			return new WP_Error( 'missing_config', __( 'Sync aborted. Please configure the Google Sheet URL, Car ID column, and Unique Identifier.', 'dynamic-sheet-sync' ) );
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

		$raw_headers = str_getcsv( array_shift( $lines ) );
		$headers = array_map( 'trim', $raw_headers );

		$uid_col_index = self::resolve_col_index( $uid_sheet_col, $headers );
		if ( false === $uid_col_index ) {
			$this->update_sync_stats( 'Car ID / Unique Identifier column not found in CSV.', 0, 0, 0 );
			return new WP_Error( 'uid_not_found', sprintf( __( 'The unique column "%s" was not found in CSV headers or columns.', 'dynamic-sheet-sync' ), $uid_sheet_col ) );
		}

		// Standard field mappings indexes.
		$content_col_header = isset( $options['field_content'] ) ? $options['field_content'] : '';
		$image_col_header   = isset( $options['field_image'] ) ? $options['field_image'] : '';
		$status_col_header  = isset( $options['field_status'] ) ? $options['field_status'] : '';
		$price_col_header   = isset( $options['field_price'] ) ? $options['field_price'] : '';

		$content_index = ! empty( $content_col_header ) ? self::resolve_col_index( $content_col_header, $headers ) : false;
		$image_index   = ! empty( $image_col_header ) ? self::resolve_col_index( $image_col_header, $headers ) : false;
		$status_index  = ! empty( $status_col_header ) ? self::resolve_col_index( $status_col_header, $headers ) : false;
		$price_index   = ! empty( $price_col_header ) ? self::resolve_col_index( $price_col_header, $headers ) : false;

		// Custom Meta mapping resolution.
		$custom_meta_mappings = isset( $options['custom_meta'] ) && is_array( $options['custom_meta'] ) ? $options['custom_meta'] : array();
		$custom_meta_resolved = array();
		foreach ( $custom_meta_mappings as $mapping ) {
			$idx = self::resolve_col_index( $mapping['sheet_col'], $headers );
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

		// Required libraries for image sideloading.
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

			// Build dynamic title (e.g. Car Name + Model + Year).
			$post_title = self::build_dynamic_title( $title_template, $row_data, $headers, $unique_id_value );

			$post_content = ( false !== $content_index && isset( $row_data[$content_index] ) ) ? wp_kses_post( trim( $row_data[$content_index] ) ) : '';
			$post_status  = ( false !== $status_index && isset( $row_data[$status_index] ) ) ? sanitize_text_field( trim( strtolower( $row_data[$status_index] ) ) ) : 'publish';
			$image_url    = ( false !== $image_index && isset( $row_data[$image_index] ) ) ? esc_url_raw( trim( $row_data[$image_index] ) ) : '';
			$price_value  = ( false !== $price_index && isset( $row_data[$price_index] ) ) ? sanitize_text_field( trim( $row_data[$price_index] ) ) : '';

			// Validate post status.
			$valid_statuses = array( 'publish', 'pending', 'draft', 'private', 'future' );
			if ( ! in_array( $post_status, $valid_statuses, true ) ) {
				$post_status = 'publish';
			}

			// Query if vehicle post already exists by Car ID meta query.
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
					'post_title'   => $post_title,
				);
				if ( ! empty( $post_content ) ) {
					$update_args['post_content'] = $post_content;
				}
				if ( false !== $status_index ) {
					$update_args['post_status'] = $post_status;
				}

				wp_update_post( $update_args );
				$updated_count++;
			} else {
				// Insert new post.
				$insert_args = array(
					'post_title'   => $post_title,
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
				// Save Car ID explicitly for product card rendering.
				update_post_meta( $post_id, '_vehicle_car_id', $unique_id_value );

				// Save Price.
				if ( ! empty( $price_value ) ) {
					update_post_meta( $post_id, '_vehicle_price', $price_value );
					update_post_meta( $post_id, '_price', preg_replace( '/[^0-9.]/', '', $price_value ) ); // For WooCommerce compatibility
				}

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

	/**
	 * Render WooCommerce-Style Vehicle Products Shortcode.
	 */
	public function render_vehicle_products_shortcode( $atts ) {
		$options = get_option( 'dynamic_sheet_sync_options', array() );

		$default_pt       = ! empty( $options['post_type'] ) ? $options['post_type'] : 'post';
		$default_currency = ! empty( $options['currency_symbol'] ) ? $options['currency_symbol'] : '$';
		$default_whatsapp = ! empty( $options['whatsapp_number'] ) ? $options['whatsapp_number'] : '';
		$custom_meta      = isset( $options['custom_meta'] ) && is_array( $options['custom_meta'] ) ? $options['custom_meta'] : array();

		$atts = shortcode_atts( array(
			'columns'        => '3',
			'posts_per_page' => '12',
			'post_type'      => $default_pt,
			'show_filter'    => 'yes',
			'show_search'    => 'yes',
			'currency'       => $default_currency,
			'whatsapp'       => $default_whatsapp,
			'orderby'        => 'date',
			'order'          => 'DESC',
		), $atts, 'vehicle_products' );

		// Query vehicles.
		$args = array(
			'post_type'      => sanitize_key( $atts['post_type'] ),
			'post_status'    => 'publish',
			'posts_per_page' => intval( $atts['posts_per_page'] ),
			'orderby'        => sanitize_key( $atts['orderby'] ),
			'order'          => sanitize_key( $atts['order'] ),
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '<div class="vehicle-grid-container"><div class="vehicle-no-results"><p>' . esc_html__( 'No vehicles available in the inventory yet.', 'dynamic-sheet-sync' ) . '</p></div></div>';
		}

		// Collect unique filter options (Fuels, Years, Transmissions).
		$all_fuels  = array();
		$all_years  = array();
		$all_trans  = array();

		$cards_html  = '';
		$modals_html = '';

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id   = get_the_ID();
			$car_title = get_the_title();
			$car_id    = get_post_meta( $post_id, '_vehicle_car_id', true );
			if ( empty( $car_id ) ) {
				$car_id = get_post_meta( $post_id, ( ! empty( $options['uid_meta_key'] ) ? $options['uid_meta_key'] : '_sku' ), true );
			}

			// Price.
			$price = get_post_meta( $post_id, '_vehicle_price', true );
			if ( empty( $price ) ) {
				$price = get_post_meta( $post_id, '_price', true );
			}

			// Parse custom specs.
			$primary_specs = array();
			$detail_specs  = array();
			$badge_val     = '';
			$fuel_val      = '';
			$year_val      = '';
			$trans_val     = '';

			foreach ( $custom_meta as $spec ) {
				$val = get_post_meta( $post_id, $spec['meta_key'], true );
				if ( '' === $val ) {
					continue;
				}

				// Check standard filters.
				if ( stripos( $spec['label'], 'Fuel' ) !== false || stripos( $spec['meta_key'], 'fuel' ) !== false ) {
					$fuel_val = $val;
					$all_fuels[ $val ] = true;
				}
				if ( stripos( $spec['label'], 'Year' ) !== false || stripos( $spec['meta_key'], 'year' ) !== false ) {
					$year_val = $val;
					$all_years[ $val ] = true;
				}
				if ( stripos( $spec['label'], 'Trans' ) !== false || stripos( $spec['meta_key'], 'trans' ) !== false || stripos( $spec['label'], 'Gear' ) !== false ) {
					$trans_val = $val;
					$all_trans[ $val ] = true;
				}

				$display_mode = isset( $spec['display'] ) ? $spec['display'] : 'primary_spec';
				if ( 'badge' === $display_mode && empty( $badge_val ) ) {
					$badge_val = $val;
				} elseif ( 'primary_spec' === $display_mode ) {
					$primary_specs[] = array(
						'label' => $spec['label'],
						'icon'  => ! empty( $spec['icon'] ) ? $spec['icon'] : '▪',
						'value' => $val,
					);
				} elseif ( 'detail' === $display_mode ) {
					$detail_specs[] = array(
						'label' => $spec['label'],
						'icon'  => ! empty( $spec['icon'] ) ? $spec['icon'] : '▪',
						'value' => $val,
					);
				}
			}

			// Image.
			$thumbnail_url = get_the_post_thumbnail_url( $post_id, 'large' );
			$modal_id = 'vehicle-modal-' . $post_id;

			// Inquire link.
			$whatsapp_url = '';
			if ( ! empty( $atts['whatsapp'] ) ) {
				$msg = rawurlencode( sprintf( 'Hello, I am interested in vehicle %s (ID: %s)', $car_title, $car_id ) );
				$whatsapp_url = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $atts['whatsapp'] ) . '?text=' . $msg;
			}

			// Build Card HTML.
			ob_start();
			?>
			<div class="vehicle-card" 
				data-title="<?php echo esc_attr( $car_title ); ?>" 
				data-id="<?php echo esc_attr( $car_id ); ?>" 
				data-fuel="<?php echo esc_attr( $fuel_val ); ?>" 
				data-year="<?php echo esc_attr( $year_val ); ?>" 
				data-transmission="<?php echo esc_attr( $trans_val ); ?>">
				
				<!-- Card Image -->
				<div class="vehicle-card-img-wrap">
					<?php if ( ! empty( $badge_val ) ) : ?>
						<span class="vehicle-badge-top"><?php echo esc_html( $badge_val ); ?></span>
					<?php endif; ?>

					<?php if ( ! empty( $car_id ) ) : ?>
						<span class="vehicle-badge-id">#<?php echo esc_html( $car_id ); ?></span>
					<?php endif; ?>

					<?php if ( $thumbnail_url ) : ?>
						<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $car_title ); ?>" class="vehicle-card-img" loading="lazy" />
					<?php else : ?>
						<div class="vehicle-card-placeholder">
							<svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.22.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
							<span style="font-size: 11px; margin-top: 4px; font-weight: 500;"><?php esc_html_e( 'Photo Coming Soon', 'dynamic-sheet-sync' ); ?></span>
						</div>
					<?php endif; ?>
				</div>

				<!-- Card Body -->
				<div class="vehicle-card-body">
					<div class="vehicle-card-header">
						<h3 class="vehicle-card-title"><?php echo esc_html( $car_title ); ?></h3>
						<?php if ( ! empty( $price ) ) : ?>
							<div class="vehicle-card-price">
								<span><?php echo esc_html( $atts['currency'] ); ?></span><?php echo esc_html( number_format_i18n( floatval( preg_replace( '/[^0-9.]/', '', $price ) ) ) ); ?>
							</div>
						<?php endif; ?>
					</div>

					<!-- Key Specs Pills -->
					<?php if ( ! empty( $primary_specs ) ) : ?>
						<div class="vehicle-specs-grid">
							<?php foreach ( array_slice( $primary_specs, 0, 4 ) as $item ) : ?>
								<div class="vehicle-spec-item">
									<span class="vehicle-spec-icon"><?php echo esc_html( $item['icon'] ); ?></span>
									<div>
										<span class="vehicle-spec-label"><?php echo esc_html( $item['label'] ); ?></span>
										<span class="vehicle-spec-val"><?php echo esc_html( $item['value'] ); ?></span>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<!-- Actions -->
					<div class="vehicle-card-footer">
						<button type="button" class="vehicle-btn vehicle-btn-outline btn-view-vehicle-details" data-target-modal="<?php echo esc_attr( $modal_id ); ?>">
							🔍 <?php esc_html_e( 'View Details', 'dynamic-sheet-sync' ); ?>
						</button>
						<?php if ( ! empty( $whatsapp_url ) ) : ?>
							<a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener" class="vehicle-btn vehicle-btn-whatsapp">
								💬 <?php esc_html_e( 'Inquire', 'dynamic-sheet-sync' ); ?>
							</a>
						<?php else : ?>
							<button type="button" class="vehicle-btn vehicle-btn-primary btn-view-vehicle-details" data-target-modal="<?php echo esc_attr( $modal_id ); ?>">
								<?php esc_html_e( 'View Specs', 'dynamic-sheet-sync' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
			$cards_html .= ob_get_clean();

			// Build Quick View Modal HTML.
			ob_start();
			?>
			<div class="vehicle-modal-backdrop" id="<?php echo esc_attr( $modal_id ); ?>">
				<div class="vehicle-modal-content">
					<button type="button" class="vehicle-modal-close">&times;</button>
					
					<?php if ( $thumbnail_url ) : ?>
						<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $car_title ); ?>" class="vehicle-modal-img" />
					<?php endif; ?>

					<div class="vehicle-modal-body">
						<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
							<div>
								<h2 class="vehicle-modal-title"><?php echo esc_html( $car_title ); ?></h2>
								<?php if ( ! empty( $car_id ) ) : ?>
									<span style="font-size: 13px; color: #64748b; font-weight: 600;"><?php esc_html_e( 'Car ID / VIN:', 'dynamic-sheet-sync' ); ?> #<?php echo esc_html( $car_id ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $price ) ) : ?>
								<div class="vehicle-modal-price">
									<span><?php echo esc_html( $atts['currency'] ); ?></span><?php echo esc_html( number_format_i18n( floatval( preg_replace( '/[^0-9.]/', '', $price ) ) ) ); ?>
								</div>
							<?php endif; ?>
						</div>

						<!-- Full Specifications Table -->
						<h4 style="margin: 20px 0 10px 0; font-size: 15px; color: #1e293b;"><?php esc_html_e( 'Specifications & Features', 'dynamic-sheet-sync' ); ?></h4>
						<table class="vehicle-modal-specs-table">
							<tbody>
								<?php if ( ! empty( $car_id ) ) : ?>
									<tr>
										<th><?php esc_html_e( 'Vehicle ID', 'dynamic-sheet-sync' ); ?></th>
										<td><strong>#<?php echo esc_html( $car_id ); ?></strong></td>
									</tr>
								<?php endif; ?>
								<?php
								$all_specs_merged = array_merge( $primary_specs, $detail_specs );
								foreach ( $all_specs_merged as $spec_row ) :
									?>
									<tr>
										<th><?php echo esc_html( $spec_row['icon'] . ' ' . $spec_row['label'] ); ?></th>
										<td><?php echo esc_html( $spec_row['value'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<!-- Description -->
						<?php $desc = get_the_content(); if ( ! empty( $desc ) ) : ?>
							<div class="vehicle-modal-desc">
								<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #1e293b;"><?php esc_html_e( 'Vehicle Overview', 'dynamic-sheet-sync' ); ?></h4>
								<?php echo wp_kses_post( wpautop( $desc ) ); ?>
							</div>
						<?php endif; ?>

						<!-- Modal Footer Action -->
						<div style="margin-top: 24px; display: flex; gap: 12px;">
							<?php if ( ! empty( $whatsapp_url ) ) : ?>
								<a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener" class="vehicle-btn vehicle-btn-whatsapp" style="flex: 2; padding: 12px;">
									💬 <?php esc_html_e( 'Contact Seller via WhatsApp', 'dynamic-sheet-sync' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
			$modals_html .= ob_get_clean();
		}

		wp_reset_postdata();

		// Build Filter Bar HTML if enabled.
		$filter_bar_html = '';
		if ( 'yes' === $atts['show_filter'] || 'yes' === $atts['show_search'] ) {
			$filter_bar_html .= '<div class="vehicle-filter-bar">';

			if ( 'yes' === $atts['show_search'] ) {
				$filter_bar_html .= '
					<div class="vehicle-search-box">
						<span class="vehicle-search-icon">🔍</span>
						<input type="text" class="vehicle-search-input" placeholder="' . esc_attr__( 'Search make, model, year, or Car ID...', 'dynamic-sheet-sync' ) . '" />
					</div>
				';
			}

			if ( 'yes' === $atts['show_filter'] ) {
				$filter_bar_html .= '<div class="vehicle-filter-controls">';

				// Fuel Filter.
				if ( ! empty( $all_fuels ) ) {
					$filter_bar_html .= '<select class="vehicle-filter-select vehicle-filter-fuel"><option value="">' . esc_html__( 'All Fuel Types', 'dynamic-sheet-sync' ) . '</option>';
					foreach ( array_keys( $all_fuels ) as $f ) {
						$filter_bar_html .= '<option value="' . esc_attr( $f ) . '">' . esc_html( $f ) . '</option>';
					}
					$filter_bar_html .= '</select>';
				}

				// Year Filter.
				if ( ! empty( $all_years ) ) {
					krsort( $all_years );
					$filter_bar_html .= '<select class="vehicle-filter-select vehicle-filter-year"><option value="">' . esc_html__( 'All Years', 'dynamic-sheet-sync' ) . '</option>';
					foreach ( array_keys( $all_years ) as $y ) {
						$filter_bar_html .= '<option value="' . esc_attr( $y ) . '">' . esc_html( $y ) . '</option>';
					}
					$filter_bar_html .= '</select>';
				}

				// Transmission Filter.
				if ( ! empty( $all_trans ) ) {
					$filter_bar_html .= '<select class="vehicle-filter-select vehicle-filter-transmission"><option value="">' . esc_html__( 'All Transmissions', 'dynamic-sheet-sync' ) . '</option>';
					foreach ( array_keys( $all_trans ) as $t ) {
						$filter_bar_html .= '<option value="' . esc_attr( $t ) . '">' . esc_html( $t ) . '</option>';
					}
					$filter_bar_html .= '</select>';
				}

				$filter_bar_html .= '<button type="button" class="vehicle-reset-btn">' . esc_html__( 'Reset Filters', 'dynamic-sheet-sync' ) . '</button>';
				$filter_bar_html .= '</div>';
			}

			$filter_bar_html .= '</div>';
		}

		$cols = max( 1, min( 4, intval( $atts['columns'] ) ) );

		// Final Output assembly.
		$output  = '<div class="vehicle-grid-container">';
		$output .= $filter_bar_html;
		$output .= '<div class="vehicle-grid" style="--vg-cols: ' . esc_attr( $cols ) . ';">';
		$output .= $cards_html;
		$output .= '<div class="vehicle-no-results" style="display:none;"><p>' . esc_html__( 'No vehicles matched your search filter criteria.', 'dynamic-sheet-sync' ) . '</p></div>';
		$output .= '</div>';
		$output .= $modals_html;
		$output .= '</div>';

		return $output;
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
			'uid_sheet_col'      => 'Car ID',
			'uid_meta_key'       => '_vehicle_car_id',
			'title_template'     => '{Car Name} {Model} {Year}',
			'field_content'      => 'Description',
			'field_image'        => 'Image URL',
			'field_status'       => 'Status',
			'field_price'        => 'Price',
			'currency_symbol'    => '$',
			'whatsapp_number'    => '',
			'frequency'          => 'hourly',
			'custom_meta'        => array(
				array( 'sheet_col' => 'Year', 'meta_key' => '_vehicle_year', 'label' => 'Year', 'icon' => '🗓️', 'display' => 'badge' ),
				array( 'sheet_col' => 'Mileage', 'meta_key' => '_vehicle_mileage', 'label' => 'Mileage', 'icon' => '🛣️', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Fuel Type', 'meta_key' => '_vehicle_fuel', 'label' => 'Fuel', 'icon' => '⛽', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Transmission', 'meta_key' => '_vehicle_transmission', 'label' => 'Gearbox', 'icon' => '🕹️', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Engine', 'meta_key' => '_vehicle_engine', 'label' => 'Engine', 'icon' => '⚙️', 'display' => 'detail' ),
				array( 'sheet_col' => 'Color', 'meta_key' => '_vehicle_color', 'label' => 'Color', 'icon' => '🎨', 'display' => 'detail' ),
			),
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

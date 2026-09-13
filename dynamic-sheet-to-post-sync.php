<?php
/**
 * Plugin Name: Dynamic Sheet to Post Type Sync & Vehicle Product Grid
 * Description: Automatically imports and syncs Google Sheet vehicle inventory into WordPress posts/products with multi-column titles, Google Drive image gallery slider (AA & AB columns), responsive creative filters, 9-card pagination, and dedicated full vehicle information pages.
 * Version: 2.1.5
 * Author: Eshmika Hettiarachchi
 * Text Domain: dynamic-sheet-sync
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Dynamic_Sheet_Post_Sync
 * Main controller for sheet sync, mapping, image slider, and product showcase.
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

		// Hook single post content to render full car information page template.
		add_filter( 'the_content', array( $this, 'render_single_vehicle_content' ) );

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
			.sheet-sync-card { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 24px 28px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); margin-bottom: 24px; }
			.sheet-sync-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f0f0f1; padding-bottom: 16px; margin-bottom: 20px; }
			.sheet-sync-header h1 { margin: 0; font-size: 22px; font-weight: 700; color: #1d2327; display: flex; align-items: center; gap: 10px; }
			.sheet-sync-badge { font-size: 12px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-weight: 700; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.5px; }
			.sheet-nav-tabs { display: flex; gap: 8px; border-bottom: 1px solid #c3c4c7; margin-bottom: 20px; }
			.sheet-nav-tab { padding: 10px 18px; font-size: 14px; font-weight: 600; color: #50575e; cursor: pointer; border: 1px solid transparent; border-bottom: none; border-radius: 6px 6px 0 0; background: #f6f7f7; text-decoration: none; }
			.sheet-nav-tab.active { background: #fff; color: #2271b1; border-color: #c3c4c7 #c3c4c7 #fff; margin-bottom: -1px; }
			.sheet-tab-panel { display: none; }
			.sheet-tab-panel.active { display: block; }
			.sheet-sync-table th { width: 240px; font-weight: 600; padding: 14px 10px 14px 0; color: #1d2327; font-size: 13.5px; }
			.sheet-sync-table td { padding: 10px 0; }
			.sheet-sync-table input[type="text"], .sheet-sync-table select, .sheet-sync-table textarea { width: 100%; max-width: 520px; border-radius: 6px; border: 1px solid #8c8f94; padding: 6px 10px; }
			.status-box { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 16px; border-radius: 0 8px 8px 0; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
			.status-box-content p { margin: 0 0 6px 0; font-size: 13.5px; }
			.status-box-content p:last-child { margin-bottom: 0; }
			.status-box-content strong { color: #1d2327; }
			.repeater-table { width: 100%; border-collapse: collapse; margin: 12px 0; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; overflow: hidden; }
			.repeater-table th { background: #f6f7f7; padding: 10px 12px; font-size: 13px; text-align: left; border-bottom: 1px solid #dcdcde; }
			.repeater-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f1; vertical-align: middle; }
			.repeater-table input, .repeater-table select { width: 100% !important; margin: 0; }
			.btn-remove-row { color: #d63638; cursor: pointer; font-size: 18px; line-height: 1; transition: 0.2s; }
			.btn-remove-row:hover { color: #a00; transform: scale(1.15); }
			.shortcode-preview-box { background: #111827; color: #f3f4f6; padding: 14px 18px; border-radius: 8px; font-family: monospace; font-size: 13px; display: flex; align-items: center; justify-content: space-between; margin: 10px 0 20px 0; border: 1px solid #374151; }
			.copy-btn { background: #2563eb; color: #fff; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 12px; font-family: sans-serif; font-weight: 600; transition: 0.2s; }
			.copy-btn:hover { background: #1d4ed8; }
			.helper-tag { display: inline-block; background: #e0f2fe; color: #0369a1; font-size: 11px; padding: 3px 8px; border-radius: 4px; font-family: monospace; cursor: pointer; margin-right: 6px; margin-top: 4px; font-weight: 600; }
			.helper-tag:hover { background: #bae6fd; }
			.col-pill { display: inline-block; background: #f1f5f9; color: #334155; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700; }
		' );
	}

	/**
	 * Register and enqueue frontend styling and interactive scripts for vehicle cards and single page.
	 */
	public function enqueue_frontend_assets() {
		wp_register_style( 'dynamic-vehicle-grid-css', false );
		wp_enqueue_style( 'dynamic-vehicle-grid-css' );

		// Creative, Responsive Vehicle Showroom Styles.
		wp_add_inline_style( 'dynamic-vehicle-grid-css', '
			:root {
				--vg-primary: #3a1f62;
				--vg-primary-dark: #170a3d;
				--vg-primary-hover: #26114e;
				--vg-accent: #5e359a;
				--vg-text-main: #170a3d;
				--vg-text-muted: #6b637d;
				--vg-bg: #ffffff;
				--vg-card-bg: #ffffff;
				--vg-border: #e8e5ef;
				--vg-border-focus: #3a1f62;
				--vg-radius-lg: 16px;
				--vg-radius-md: 10px;
				--vg-radius-sm: 6px;
				--vg-shadow-sm: 0 2px 8px rgba(23, 10, 61, 0.04), 0 1px 2px rgba(23, 10, 61, 0.02);
				--vg-shadow-md: 0 8px 24px -4px rgba(23, 10, 61, 0.07), 0 2px 8px -2px rgba(23, 10, 61, 0.03);
				--vg-shadow-hover: 0 20px 30px -6px rgba(23, 10, 61, 0.12), 0 8px 12px -4px rgba(23, 10, 61, 0.05);
			}

			.vehicle-grid-container {
				max-width: 1280px;
				margin: 30px auto;
				padding: 0 16px;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
				box-sizing: border-box;
				color: var(--vg-text-main);
			}
			.vehicle-grid-container * { box-sizing: border-box; }

			/* ==========================================================================
			   MODERN CREATIVE FILTER BAR (Clean White, Deep Purple Accent)
			   ========================================================================== */
			.vehicle-filter-wrapper {
				background: #ffffff;
				border: 1px solid var(--vg-border);
				border-radius: var(--vg-radius-lg);
				padding: 22px 26px;
				margin-bottom: 32px;
				box-shadow: var(--vg-shadow-md);
				position: relative;
				transition: border-color 0.25s ease, box-shadow 0.25s ease;
			}
			.vehicle-filter-wrapper::before {
				content: "";
				position: absolute;
				top: 0;
				left: 24px;
				right: 24px;
				height: 3px;
				background: linear-gradient(90deg, #170a3d 0%, #3a1f62 60%, rgba(58, 31, 98, 0.1) 100%);
				border-radius: 3px 3px 0 0;
			}
			.vehicle-filter-main-row {
				display: flex;
				flex-wrap: wrap;
				gap: 12px;
				align-items: center;
				justify-content: space-between;
			}
			.vehicle-search-box {
				position: relative;
				flex: 1 1 290px;
				min-width: 240px;
			}
			.vehicle-search-svg {
				position: absolute;
				left: 15px;
				top: 50%;
				transform: translateY(-50%);
				pointer-events: none;
				transition: color 0.2s ease;
			}
			.vehicle-search-input {
				width: 100%;
				height: 48px;
				padding: 10px 42px 10px 44px;
				border: 1.5px solid var(--vg-border);
				border-radius: var(--vg-radius-md);
				font-size: 14px;
				font-weight: 500;
				color: var(--vg-text-main);
				background: #ffffff;
				outline: none;
				transition: all 0.25s ease;
			}
			.vehicle-search-input::placeholder {
				color: #9b94a8;
				font-weight: 400;
			}
			.vehicle-search-input:focus {
				border-color: #3a1f62;
				background: #ffffff;
				box-shadow: 0 0 0 3px rgba(58, 31, 98, 0.12);
			}
			.vehicle-search-clear {
				position: absolute;
				right: 12px;
				top: 50%;
				transform: translateY(-50%);
				background: #f1eef8;
				color: #5c5470;
				border: none;
				width: 22px;
				height: 22px;
				border-radius: 50%;
				cursor: pointer;
				display: none;
				align-items: center;
				justify-content: center;
				transition: all 0.2s ease;
				padding: 0;
			}
			.vehicle-search-clear:hover {
				background: #3a1f62;
				color: #ffffff;
			}
			.vehicle-search-clear svg {
				width: 11px;
				height: 11px;
			}

			.vehicle-filter-controls {
				display: flex;
				flex-wrap: wrap;
				gap: 10px;
				align-items: center;
			}
			.vehicle-select-wrap {
				position: relative;
				min-width: 145px;
			}
			.vehicle-filter-select {
				width: 100%;
				height: 48px;
				padding: 8px 34px 8px 14px;
				border: 1.5px solid var(--vg-border);
				border-radius: var(--vg-radius-md);
				font-size: 13.5px;
				font-weight: 600;
				color: #170a3d;
				background-color: #ffffff;
				cursor: pointer;
				outline: none;
				appearance: none;
				-webkit-appearance: none;
				transition: all 0.2s ease;
			}
			.vehicle-filter-select:hover {
				border-color: #d2cbe0;
			}
			.vehicle-filter-select:focus {
				border-color: #3a1f62;
				box-shadow: 0 0 0 3px rgba(58, 31, 98, 0.12);
			}
			.vehicle-select-arrow {
				position: absolute;
				right: 13px;
				top: 50%;
				transform: translateY(-50%);
				pointer-events: none;
				color: #6b637d;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				transition: transform 0.2s ease, color 0.2s ease;
			}
			.vehicle-select-wrap:hover .vehicle-select-arrow {
				color: #3a1f62;
			}
			.vehicle-reset-btn {
				height: 48px;
				padding: 0 18px;
				background: #ffffff;
				color: #3a1f62;
				border: 1.5px solid #dfd8ec;
				border-radius: var(--vg-radius-md);
				font-size: 13px;
				font-weight: 700;
				cursor: pointer;
				display: inline-flex;
				align-items: center;
				gap: 7px;
				letter-spacing: 0.2px;
				transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
			}
			.vehicle-reset-btn svg {
				width: 14px;
				height: 14px;
				transition: transform 0.3s ease;
			}
			.vehicle-reset-btn:hover {
				background: #170a3d;
				color: #ffffff;
				border-color: #170a3d;
				box-shadow: 0 4px 12px rgba(23, 10, 61, 0.18);
			}
			.vehicle-reset-btn:hover svg {
				transform: rotate(-90deg);
			}

			.vehicle-filter-meta-bar {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-top: 16px;
				padding-top: 15px;
				border-top: 1px solid #f1eef6;
				font-size: 13px;
				color: var(--vg-text-muted);
			}
			.vehicle-count-badge {
				font-weight: 700;
				color: #170a3d;
				display: inline-flex;
				align-items: center;
				gap: 7px;
			}
			.vehicle-count-badge::before {
				content: "";
				display: inline-block;
				width: 8px;
				height: 8px;
				border-radius: 50%;
				background: #3a1f62;
			}
			.vehicle-active-chips {
				display: flex;
				flex-wrap: wrap;
				gap: 7px;
				align-items: center;
			}
			.vehicle-chip {
				background: #f8f6fc;
				color: #170a3d;
				border: 1px solid #dfd8ec;
				padding: 4px 11px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: 600;
				display: inline-flex;
				align-items: center;
				gap: 6px;
				transition: all 0.2s ease;
			}
			.vehicle-chip-remove {
				cursor: pointer;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 15px;
				height: 15px;
				border-radius: 50%;
				color: #6b637d;
				transition: all 0.2s ease;
			}
			.vehicle-chip-remove:hover {
				background: #3a1f62;
				color: #ffffff;
			}
			.vehicle-chip-remove svg {
				width: 9px;
				height: 9px;
				stroke-width: 2.5;
			}

			/* ==========================================================================
			   VEHICLE PRODUCT GRID (1 COLUMN HORIZONTAL CARDS)
			   ========================================================================== */
			.vehicle-grid {
				display: grid;
				grid-template-columns: 1fr;
				gap: 20px;
			}
			@media (max-width: 640px) {
				.vehicle-grid { gap: 16px; }
				.vehicle-filter-wrapper { padding: 16px; }
				.vehicle-select-wrap { width: 100%; min-width: 100%; }
				.vehicle-reset-btn { width: 100%; justify-content: center; }
			}

			/* ==========================================================================
			   HORIZONTAL 2-COLUMN VEHICLE CARD
			   ========================================================================== */
			.vehicle-card {
				background: #ffffff;
				border: 1px solid var(--vg-border);
				border-radius: var(--vg-radius-lg);
				overflow: hidden;
				display: flex;
				flex-direction: row;
				align-items: stretch;
				transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.25s ease;
				box-shadow: 0 2px 10px rgba(23, 10, 61, 0.04);
				position: relative;
			}
			.vehicle-card:hover {
				transform: translateY(-3px);
				box-shadow: 0 10px 24px rgba(23, 10, 61, 0.1);
				border-color: #d2cbe0;
			}

			/* ==========================================================================
			   LEFT COLUMN: IMAGE SLIDER (SMALL WIDTH)
			   ========================================================================== */
			.vehicle-card-slider-container {
				position: relative;
				flex: 0 0 280px;
				width: 280px;
				min-height: 190px;
				background: #0f172a;
				overflow: hidden;
				user-select: none;
			}
			.vehicle-slider-track {
				display: flex;
				width: 100%;
				height: 100%;
				transition: transform 0.35s cubic-bezier(0.25, 1, 0.5, 1);
			}
			.vehicle-slider-slide {
				flex: 0 0 100%;
				width: 100%;
				height: 100%;
				position: relative;
				background: #0f172a;
			}
			.vehicle-slider-img {
				width: 100%;
				height: 100%;
				object-fit: cover;
				display: block;
				transition: transform 0.4s ease;
			}
			.vehicle-card:hover .vehicle-slider-slide.active .vehicle-slider-img {
				transform: scale(1.04);
			}
			.vehicle-card-placeholder {
				width: 100%;
				height: 100%;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				color: #94a3b8;
				background: linear-gradient(135deg, #f8f6fc 0%, #ede8f5 100%);
			}
			.vehicle-card-placeholder svg { width: 44px; height: 44px; fill: currentColor; }

			/* Slider Navigation Controls */
			.vehicle-slider-btn {
				position: absolute;
				top: 50%;
				transform: translateY(-50%);
				width: 32px;
				height: 32px;
				background: rgba(23, 10, 61, 0.72);
				color: #ffffff;
				border: 1px solid rgba(255,255,255,0.25);
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				cursor: pointer;
				font-size: 17px;
				line-height: 1;
				z-index: 5;
				opacity: 0;
				transition: all 0.2s ease;
				backdrop-filter: blur(4px);
			}
			.vehicle-card:hover .vehicle-slider-btn {
				opacity: 1;
			}
			.vehicle-slider-btn:hover {
				background: #3a1f62;
				border-color: #3a1f62;
				transform: translateY(-50%) scale(1.08);
			}
			.vehicle-slider-btn.prev { left: 8px; }
			.vehicle-slider-btn.next { right: 8px; }

			/* Slider Counter & Dots */
			.vehicle-slider-counter {
				position: absolute;
				bottom: 8px;
				right: 10px;
				background: rgba(23, 10, 61, 0.78);
				color: #ffffff;
				font-size: 10.5px;
				font-weight: 700;
				padding: 2px 7px;
				border-radius: 10px;
				letter-spacing: 0.5px;
				backdrop-filter: blur(4px);
				z-index: 4;
			}
			.vehicle-slider-dots {
				position: absolute;
				bottom: 8px;
				left: 50%;
				transform: translateX(-50%);
				display: flex;
				gap: 4px;
				z-index: 4;
			}
			.vehicle-slider-dot {
				width: 5px;
				height: 5px;
				border-radius: 50%;
				background: rgba(255, 255, 255, 0.5);
				transition: all 0.25s ease;
				cursor: pointer;
			}
			.vehicle-slider-dot.active {
				width: 15px;
				border-radius: 8px;
				background: #ffffff;
			}

			/* ==========================================================================
			   RIGHT COLUMN: VEHICLE DETAILS (EXACTLY 3 CLEAN LINES)
			   ========================================================================== */
			.vehicle-card-body {
				flex: 1;
				padding: 24px 28px;
				display: flex;
				flex-direction: column;
				justify-content: center;
				background: #ffffff;
				min-width: 0;
			}
			.vehicle-card-details {
				display: flex;
				flex-direction: column;
				gap: 8px;
				min-width: 0;
			}

			/* Line 1: Vehicle Card Title */
			.vehicle-card-title {
				font-size: 16px;
				font-weight: 700;
				color: #170a3d;
				margin: 0;
				line-height: 1.35;
				letter-spacing: -0.25px;
				word-break: break-word;
			}
			.vehicle-card-title a {
				color: #170a3d;
				text-decoration: none;
				transition: color 0.2s ease;
			}
			.vehicle-card-title a:hover {
				color: #3a1f62;
			}

			/* Line 2: Mileage | Body Style | Condition (Plain values only, simple text) */
			.vehicle-card-specs-line {
				font-size: 14px;
				font-weight: 500;
				color: #6b637d;
				line-height: 1.4;
				margin: 0;
				letter-spacing: 0.1px;
			}

			/* Line 3: Price (Value only, unique prominent typography) */
			.vehicle-card-price-unique {
				font-size: 23px;
				font-weight: 800;
				color: #3a1f62;
				letter-spacing: -0.5px;
				line-height: 1.2;
				margin-top: 4px;
				font-feature-settings: "tnum";
				font-variant-numeric: tabular-nums;
			}

			/* Responsive styling for horizontal cards */
			@media (max-width: 768px) {
				.vehicle-card {
					flex-direction: column;
				}
				.vehicle-card-slider-container {
					flex: none;
					width: 100%;
					height: 220px;
					min-height: 220px;
				}
				.vehicle-card-body {
					padding: 18px 20px;
				}
				.vehicle-card-title {
					font-size: 15px;
				}
				.vehicle-card-specs-line {
					font-size: 13.5px;
				}
				.vehicle-card-price-unique {
					font-size: 21px;
				}
			}

			/* ==========================================================================
			   PAGINATION CONTROLS (9 PER PAGE)
			   ========================================================================== */
			.vehicle-pagination-container {
				margin-top: 40px;
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				justify-content: center;
				gap: 8px;
			}
			.vehicle-page-btn {
				min-width: 42px;
				height: 42px;
				padding: 0 12px;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				background: #ffffff;
				border: 1.5px solid var(--vg-border);
				border-radius: var(--vg-radius-md);
				font-size: 14px;
				font-weight: 600;
				color: var(--vg-text-main);
				cursor: pointer;
				transition: all 0.2s ease;
				user-select: none;
				text-decoration: none;
			}
			.vehicle-page-btn:hover:not(.disabled):not(.active) {
				background: #fbf9fd;
				border-color: #d2cbe0;
				color: #3a1f62;
			}
			.vehicle-page-btn.active {
				background: linear-gradient(135deg, #3a1f62 0%, #170a3d 100%);
				border-color: #170a3d;
				color: #ffffff;
				box-shadow: 0 4px 10px rgba(23, 10, 61, 0.25);
			}
			.vehicle-page-btn.disabled {
				opacity: 0.45;
				cursor: not-allowed;
			}
			.vehicle-page-ellipsis {
				padding: 0 6px;
				font-weight: 700;
				color: var(--vg-text-muted);
			}

			/* No Results State */
			.vehicle-no-results {
				grid-column: 1 / -1;
				text-align: center;
				padding: 60px 20px;
				background: #ffffff;
				border-radius: var(--vg-radius-lg);
				border: 2px dashed var(--vg-border);
				color: var(--vg-text-muted);
			}
			.vehicle-no-results svg { width: 56px; height: 56px; fill: #94a3b8; margin-bottom: 12px; }
			.vehicle-no-results h3 { font-size: 18px; color: var(--vg-text-main); margin: 0 0 6px 0; }

			/* Responsive mobile styling for vehicle card */
			@media (max-width: 640px) {
				.vehicle-card-title {
					font-size: 14px !important;
				}
				.vehicle-card-specs-line {
					font-size: 13px !important;
				}
				.vehicle-card-price-unique {
					font-size: 20px !important;
				}
				.vehicle-card-body {
					padding: 16px 18px;
				}
			}

			/* ==========================================================================
			   FULL VEHICLE INFORMATION SINGLE PAGE
			   ========================================================================== */
			.vehicle-single-page {
				max-width: 1200px;
				margin: 30px auto;
				padding: 0 20px;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				color: var(--vg-text-main);
			}
			.vehicle-single-breadcrumbs {
				margin-bottom: 20px;
				font-size: 13.5px;
				color: var(--vg-text-muted);
			}
			.vehicle-single-breadcrumbs a {
				color: var(--vg-primary);
				text-decoration: none;
				font-weight: 600;
			}
			.vehicle-single-breadcrumbs a:hover { text-decoration: underline; }

			.vehicle-single-layout {
				display: grid;
				grid-template-columns: 1.35fr 1fr;
				gap: 36px;
				align-items: start;
			}
			@media (max-width: 900px) {
				.vehicle-single-layout { grid-template-columns: 1fr; }
			}

			/* Single Gallery */
			.vehicle-single-gallery {
				background: #ffffff;
				border: 1px solid var(--vg-border);
				border-radius: var(--vg-radius-lg);
				padding: 12px;
				box-shadow: var(--vg-shadow-sm);
			}
			.vehicle-single-main-img-wrap {
				position: relative;
				width: 100%;
				height: 420px;
				border-radius: var(--vg-radius-md);
				overflow: hidden;
				background: #0f172a;
			}
			.vehicle-single-main-img {
				width: 100%;
				height: 100%;
				object-fit: cover;
				display: block;
			}
			.vehicle-single-thumbs {
				display: flex;
				gap: 10px;
				margin-top: 12px;
				overflow-x: auto;
				padding-bottom: 4px;
			}
			.vehicle-single-thumb {
				width: 80px;
				height: 60px;
				border-radius: var(--vg-radius-sm);
				overflow: hidden;
				cursor: pointer;
				border: 2px solid transparent;
				opacity: 0.65;
				flex: 0 0 80px;
				transition: all 0.2s ease;
			}
			.vehicle-single-thumb:hover, .vehicle-single-thumb.active {
				opacity: 1;
				border-color: var(--vg-primary);
			}
			.vehicle-single-thumb img { width: 100%; height: 100%; object-fit: cover; }

			/* Single Details Panel */
			.vehicle-single-info-panel {
				background: #ffffff;
				border: 1px solid var(--vg-border);
				border-radius: var(--vg-radius-lg);
				padding: 28px;
				box-shadow: var(--vg-shadow-md);
			}
			.vehicle-single-title {
				font-size: 26px;
				font-weight: 800;
				margin: 0 0 8px 0;
				line-height: 1.25;
				color: var(--vg-text-main);
			}
			.vehicle-single-id-tag {
				display: inline-block;
				background: #f1f5f9;
				color: #475569;
				font-size: 12px;
				font-weight: 700;
				padding: 4px 10px;
				border-radius: 20px;
				margin-bottom: 16px;
			}
			.vehicle-single-price-box {
				background: linear-gradient(135deg, #eff6ff, #f8fafc);
				border: 1px solid #bfdbfe;
				border-radius: var(--vg-radius-md);
				padding: 16px 20px;
				margin-bottom: 24px;
				display: flex;
				align-items: baseline;
				justify-content: space-between;
			}
			.vehicle-single-price {
				font-size: 30px;
				font-weight: 800;
				color: var(--vg-primary);
			}

			/* Specs Table */
			.vehicle-single-specs-grid {
				display: grid;
				grid-template-columns: repeat(2, 1fr);
				gap: 12px;
				margin: 20px 0;
			}
			.vehicle-single-spec-card {
				background: #f8fafc;
				border: 1px solid #e2e8f0;
				border-radius: var(--vg-radius-md);
				padding: 12px 14px;
				display: flex;
				align-items: center;
				gap: 10px;
			}
			.vehicle-single-spec-icon { font-size: 18px; }
			.vehicle-single-spec-label { font-size: 11.5px; color: var(--vg-text-muted); display: block; }
			.vehicle-single-spec-val { font-size: 13.5px; font-weight: 700; color: var(--vg-text-main); }

			.vehicle-single-actions {
				margin-top: 24px;
				display: flex;
				flex-direction: column;
				gap: 12px;
			}
			.vehicle-single-btn-whatsapp {
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
				padding: 14px 20px;
				background: #25d366;
				color: #ffffff !important;
				font-size: 15px;
				font-weight: 700;
				border-radius: var(--vg-radius-md);
				text-decoration: none !important;
				transition: 0.2s ease;
				box-shadow: 0 4px 12px rgba(37, 211, 102, 0.25);
			}
			.vehicle-single-btn-whatsapp:hover { background: #1eb956; transform: translateY(-1px); }

			.vehicle-single-desc-section {
				margin-top: 36px;
				background: #ffffff;
				border: 1px solid var(--vg-border);
				border-radius: var(--vg-radius-lg);
				padding: 28px;
				box-shadow: var(--vg-shadow-sm);
			}
			.vehicle-single-desc-section h3 {
				font-size: 18px;
				font-weight: 700;
				margin: 0 0 14px 0;
				color: var(--vg-text-main);
			}
		' );

		// Frontend interactive script for slider, filters, and 9-card pagination.
		wp_register_script( 'dynamic-vehicle-grid-js', false, array( 'jquery' ), false, true );
		wp_enqueue_script( 'dynamic-vehicle-grid-js' );

		wp_add_inline_script( 'dynamic-vehicle-grid-js', '
			jQuery(document).ready(function($) {

				// ==========================================================
				// 1. MULTI-IMAGE CARD SLIDER LOGIC
				// ==========================================================
				function updateCardSlider($container, newIndex) {
					var $track = $container.find(".vehicle-slider-track");
					var $slides = $container.find(".vehicle-slider-slide");
					var total = $slides.length;
					if (total <= 1) return;

					if (newIndex < 0) newIndex = total - 1;
					if (newIndex >= total) newIndex = 0;

					$container.data("current-index", newIndex);
					$track.css("transform", "translateX(-" + (newIndex * 100) + "%)");
					$slides.removeClass("active").eq(newIndex).addClass("active");
					$container.find(".vehicle-slider-dot").removeClass("active").eq(newIndex).addClass("active");
					$container.find(".vehicle-slider-counter").text((newIndex + 1) + " / " + total);
				}

				// Slider Previous Button
				$(document).on("click", ".vehicle-slider-btn.prev", function(e) {
					e.preventDefault();
					e.stopPropagation();
					var $container = $(this).closest(".vehicle-card-slider-container");
					var curr = parseInt($container.data("current-index") || 0, 10);
					updateCardSlider($container, curr - 1);
				});

				// Slider Next Button
				$(document).on("click", ".vehicle-slider-btn.next", function(e) {
					e.preventDefault();
					e.stopPropagation();
					var $container = $(this).closest(".vehicle-card-slider-container");
					var curr = parseInt($container.data("current-index") || 0, 10);
					updateCardSlider($container, curr + 1);
				});

				// Slider Dot Click
				$(document).on("click", ".vehicle-slider-dot", function(e) {
					e.preventDefault();
					e.stopPropagation();
					var $dot = $(this);
					var idx = parseInt($dot.data("index") || 0, 10);
					var $container = $dot.closest(".vehicle-card-slider-container");
					updateCardSlider($container, idx);
				});

				// Touch Swipe support for card slider
				var touchStartX = 0;
				var touchEndX = 0;
				$(document).on("touchstart", ".vehicle-card-slider-container", function(e) {
					touchStartX = e.originalEvent.touches[0].clientX;
				});
				$(document).on("touchend", ".vehicle-card-slider-container", function(e) {
					touchEndX = e.originalEvent.changedTouches[0].clientX;
					var diff = touchStartX - touchEndX;
					if (Math.abs(diff) > 40) {
						var $container = $(this);
						var curr = parseInt($container.data("current-index") || 0, 10);
						if (diff > 0) {
							updateCardSlider($container, curr + 1);
						} else {
							updateCardSlider($container, curr - 1);
						}
					}
				});

				// ==========================================================
				// 2. CREATIVE FILTER & ALL / PAGINATED SYSTEM
				// ==========================================================
				function getPerPageSetting() {
					var $grid = $(".vehicle-grid");
					if ($grid.length && $grid.data("per-page") !== undefined) {
						var parsed = parseInt($grid.data("per-page"), 10);
						if (!isNaN(parsed)) {
							return parsed; // -1 means all, or any custom positive number
						}
					}
					return -1; // Default to showing all vehicles
				}

				var currentPage = 1;

				function getFilteredCards() {
					var searchTerm = ($(".vehicle-search-input").val() || "").trim().toLowerCase();
					var fuelFilter = $(".vehicle-filter-fuel").val() || "";
					var yearFilter = $(".vehicle-filter-year").val() || "";
					var transFilter = $(".vehicle-filter-transmission").val() || "";

					var $allCards = $(".vehicle-card");
					var matching = [];

					$allCards.each(function() {
						var $card = $(this);
						var title = ($card.data("title") || "").toString().toLowerCase();
						var carId = ($card.data("id") || "").toString().toLowerCase();
						var fuel = ($card.data("fuel") || "").toString();
						var year = ($card.data("year") || "").toString();
						var trans = ($card.data("transmission") || "").toString();

						var matchesSearch = !searchTerm || title.indexOf(searchTerm) > -1 || carId.indexOf(searchTerm) > -1;
						var matchesFuel = !fuelFilter || fuel === fuelFilter;
						var matchesYear = !yearFilter || year === yearFilter;
						var matchesTrans = !transFilter || trans === transFilter;

						if (matchesSearch && matchesFuel && matchesYear && matchesTrans) {
							matching.push($card);
						}
					});

					return matching;
				}

				function renderPagination(totalMatches, activePage, perPage) {
					var $pagination = $(".vehicle-pagination-container");
					$pagination.empty();

					if (perPage <= 0 || perPage >= totalMatches) {
						return; // All vehicles shown, no pagination buttons needed!
					}

					var totalPages = Math.ceil(totalMatches / perPage);
					if (totalPages <= 1) {
						return; // No pagination needed for 1 page
					}

					// Prev button
					var $prev = $("<button>", {
						type: "button",
						class: "vehicle-page-btn vehicle-page-nav prev" + (activePage === 1 ? " disabled" : ""),
						html: "&laquo; Prev",
						"data-page": activePage - 1
					});
					$pagination.append($prev);

					// Page numbers
					for (var i = 1; i <= totalPages; i++) {
						if (totalPages > 7) {
							if (i > 2 && i < totalPages - 1 && Math.abs(i - activePage) > 1) {
								if (i === 3 || i === totalPages - 2) {
									$pagination.append($("<span>", { class: "vehicle-page-ellipsis", text: "..." }));
								}
								continue;
							}
						}

						var $pageBtn = $("<button>", {
							type: "button",
							class: "vehicle-page-btn" + (i === activePage ? " active" : ""),
							text: i,
							"data-page": i
						});
						$pagination.append($pageBtn);
					}

					// Next button
					var $next = $("<button>", {
						type: "button",
						class: "vehicle-page-btn vehicle-page-nav next" + (activePage === totalPages ? " disabled" : ""),
						html: "Next &raquo;",
						"data-page": activePage + 1
					});
					$pagination.append($next);
				}

				function updateActiveChips() {
					var $chipsContainer = $(".vehicle-active-chips");
					$chipsContainer.empty();

					var fuel = $(".vehicle-filter-fuel").val();
					var year = $(".vehicle-filter-year").val();
					var trans = $(".vehicle-filter-transmission").val();
					var search = $(".vehicle-search-input").val();

					var closeSvg = "<svg viewBox=\"0 0 24 24\" width=\"9\" height=\"9\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><line x1=\"18\" y1=\"6\" x2=\"6\" y2=\"18\"></line><line x1=\"6\" y1=\"6\" x2=\"18\" y2=\"18\"></line></svg>";

					if (search) {
						$chipsContainer.append("<span class=\"vehicle-chip\">Search: " + search + " <span class=\"vehicle-chip-remove\" data-target=\"search\" title=\"Remove search filter\">" + closeSvg + "</span></span>");
					}
					if (fuel) {
						$chipsContainer.append("<span class=\"vehicle-chip\">Fuel: " + fuel + " <span class=\"vehicle-chip-remove\" data-target=\"fuel\" title=\"Remove fuel filter\">" + closeSvg + "</span></span>");
					}
					if (year) {
						$chipsContainer.append("<span class=\"vehicle-chip\">Year: " + year + " <span class=\"vehicle-chip-remove\" data-target=\"year\" title=\"Remove year filter\">" + closeSvg + "</span></span>");
					}
					if (trans) {
						$chipsContainer.append("<span class=\"vehicle-chip\">Trans: " + trans + " <span class=\"vehicle-chip-remove\" data-target=\"trans\" title=\"Remove transmission filter\">" + closeSvg + "</span></span>");
					}
				}

				function applyFiltersAndPagination(resetToPageOne) {
					if (resetToPageOne) {
						currentPage = 1;
					}

					var perPage = getPerPageSetting();
					var matchingCards = getFilteredCards();
					var totalMatches = matchingCards.length;
					var isShowAll = (perPage <= 0 || perPage >= totalMatches);

					var totalPages = isShowAll ? 1 : Math.ceil(totalMatches / perPage);

					if (currentPage > totalPages && totalPages > 0) {
						currentPage = totalPages;
					}

					var startIndex = isShowAll ? 0 : (currentPage - 1) * perPage;
					var endIndex = isShowAll ? totalMatches : startIndex + perPage;

					// Hide all cards first
					$(".vehicle-card").hide();

					// Show cards in view (all or current page)
					for (var i = 0; i < matchingCards.length; i++) {
						if (i >= startIndex && i < endIndex) {
							matchingCards[i].fadeIn(150);
						}
					}

					// Update result count text
					if (totalMatches === 0) {
						$(".vehicle-no-results").show();
						$(".vehicle-count-badge").text("Showing 0 vehicles");
					} else {
						$(".vehicle-no-results").hide();
						if (isShowAll) {
							$(".vehicle-count-badge").text("Showing all " + totalMatches + " vehicles");
						} else {
							var startDisplay = startIndex + 1;
							var endDisplay = Math.min(endIndex, totalMatches);
							$(".vehicle-count-badge").text("Showing " + startDisplay + "–" + endDisplay + " of " + totalMatches + " vehicles");
						}
					}

					// Render Pagination buttons if applicable
					renderPagination(totalMatches, currentPage, perPage);
					updateActiveChips();

					// Toggle search clear button
					if ($(".vehicle-search-input").val()) {
						$(".vehicle-search-clear").css("display", "inline-flex");
					} else {
						$(".vehicle-search-clear").hide();
					}
				}

				// Search input listener
				$(document).on("input keyup", ".vehicle-search-input", function() {
					applyFiltersAndPagination(true);
				});

				// Clear search button
				$(document).on("click", ".vehicle-search-clear", function() {
					$(".vehicle-search-input").val("");
					applyFiltersAndPagination(true);
				});

				// Filter Select Change
				$(document).on("change", ".vehicle-filter-select", function() {
					applyFiltersAndPagination(true);
				});

				// Reset Filters Button
				$(document).on("click", ".vehicle-reset-btn", function() {
					$(".vehicle-search-input").val("");
					$(".vehicle-filter-select").val("");
					applyFiltersAndPagination(true);
				});

				// Active Chip Remove Click
				$(document).on("click", ".vehicle-chip-remove", function() {
					var target = $(this).data("target");
					if (target === "search") $(".vehicle-search-input").val("");
					if (target === "fuel") $(".vehicle-filter-fuel").val("");
					if (target === "year") $(".vehicle-filter-year").val("");
					if (target === "trans") $(".vehicle-filter-transmission").val("");
					applyFiltersAndPagination(true);
				});

				// Pagination Click Handler
				$(document).on("click", ".vehicle-page-btn", function(e) {
					e.preventDefault();
					var $btn = $(this);
					if ($btn.hasClass("disabled") || $btn.hasClass("active")) return;

					var targetPage = parseInt($btn.data("page"), 10);
					if (targetPage > 0) {
						currentPage = targetPage;
						applyFiltersAndPagination(false);

						// Smooth scroll to top of grid
						var $container = $(".vehicle-grid-container");
						if ($container.length) {
							$("html, body").animate({
								scrollTop: $container.offset().top - 80
							}, 300);
						}
					}
				});

				// Initial run
				if ($(".vehicle-grid").length) {
					applyFiltersAndPagination(true);
				}

				// ==========================================================
				// 3. SINGLE PAGE GALLERY THUMBNAIL CLICK
				// ==========================================================
				$(document).on("click", ".vehicle-single-thumb", function() {
					var $thumb = $(this);
					var fullSrc = $thumb.data("full-src");
					$(".vehicle-single-thumb").removeClass("active");
					$thumb.addClass("active");
					$(".vehicle-single-main-img").attr("src", fullSrc);
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

		// Main and Sub Image Column Mappings (Default AA and AB).
		$output['field_image']     = isset( $input['field_image'] ) && '' !== trim( $input['field_image'] ) ? sanitize_text_field( trim( $input['field_image'] ) ) : 'AA';
		$output['field_sub_images'] = isset( $input['field_sub_images'] ) && '' !== trim( $input['field_sub_images'] ) ? sanitize_text_field( trim( $input['field_sub_images'] ) ) : 'AB';

		// Standard Field mappings.
		if ( isset( $input['field_content'] ) ) {
			$output['field_content'] = sanitize_text_field( trim( $input['field_content'] ) );
		}
		if ( isset( $input['field_status'] ) ) {
			$output['field_status'] = sanitize_text_field( trim( $input['field_status'] ) );
		}
		if ( isset( $input['field_price'] ) ) {
			$output['field_price'] = sanitize_text_field( trim( $input['field_price'] ) );
		}
		if ( isset( $input['field_mileage'] ) ) {
			$output['field_mileage'] = sanitize_text_field( trim( $input['field_mileage'] ) );
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
	 * Convert Column Letter (e.g. A, B, C, AA, AB) to 0-based column index.
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
	 * Resolve column index in row from either Column Letter (A, B, C... AA, AB) or Column Header Name.
	 */
	public static function resolve_col_index( $col_identifier, $headers ) {
		if ( empty( $col_identifier ) ) {
			return false;
		}

		$col_identifier = trim( $col_identifier );

		// 1. Check if column identifier is a pure Column Letter (A, B, C... Z, AA, AB...).
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
	 * Convert Google Drive share link into high-speed direct streamable image URL.
	 */
	public static function convert_google_drive_url( $url ) {
		$url = trim( $url );
		if ( empty( $url ) ) {
			return '';
		}

		// Extract Google Drive File ID from various link formats.
		$file_id = '';
		if ( preg_match( '/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/i', $url, $matches ) ) {
			$file_id = $matches[1];
		} elseif ( preg_match( '/(?:drive|docs)\.google\.com\/(?:open|uc|thumbnail)\?(?:[^&]+&)*id=([a-zA-Z0-9_-]+)/i', $url, $matches ) ) {
			$file_id = $matches[1];
		} elseif ( preg_match( '/lh3\.googleusercontent\.com\/d\/([a-zA-Z0-9_-]+)/i', $url, $matches ) ) {
			$file_id = $matches[1];
		}

		if ( ! empty( $file_id ) ) {
			// Direct Google UserContent CDN link with high resolution size parameter.
			return 'https://lh3.googleusercontent.com/d/' . $file_id . '=s1600';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Parse and convert multiple Google Drive image URLs (from Column AA & AB).
	 */
	public static function parse_gallery_image_urls( $main_img_raw, $sub_imgs_raw ) {
		$gallery = array();

		// Main Image (Column AA).
		if ( ! empty( $main_img_raw ) ) {
			$main_converted = self::convert_google_drive_url( $main_img_raw );
			if ( ! empty( $main_converted ) ) {
				$gallery[] = $main_converted;
			}
		}

		// Sub Images (Column AB - separated by comma or newline).
		if ( ! empty( $sub_imgs_raw ) ) {
			$sub_list = preg_split( '/[,|\n\r]+/', $sub_imgs_raw );
			foreach ( $sub_list as $sub_item ) {
				$sub_item = trim( $sub_item );
				if ( ! empty( $sub_item ) ) {
					$converted = self::convert_google_drive_url( $sub_item );
					if ( ! empty( $converted ) && ! in_array( $converted, $gallery, true ) ) {
						$gallery[] = $converted;
					}
				}
			}
		}

		return $gallery;
	}

	/**
	 * Build dynamic title from template or multi-column definitions (e.g. {Car Name} {Model} {Year}).
	 */
	public static function build_dynamic_title( $template, $row_data, $headers, $fallback_id = '' ) {
		if ( empty( $template ) ) {
			$template = '{Car Name} {Model} {Year}';
		}

		// Extract all placeholders like {Car Name}, {Model}, {Year}, {C}, {D}, {E}.
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
		$csv_url          = isset( $options['csv_url'] ) ? $options['csv_url'] : '';
		$target_pt        = isset( $options['post_type'] ) ? $options['post_type'] : 'post';
		$uid_sheet_col    = isset( $options['uid_sheet_col'] ) ? $options['uid_sheet_col'] : 'Car ID';
		$uid_meta_key     = isset( $options['uid_meta_key'] ) ? $options['uid_meta_key'] : '_vehicle_car_id';
		$title_template   = isset( $options['title_template'] ) ? $options['title_template'] : '{Car Name} {Model} {Year}';
		$field_image      = isset( $options['field_image'] ) ? $options['field_image'] : 'AA';
		$field_sub_images = isset( $options['field_sub_images'] ) ? $options['field_sub_images'] : 'AB';
		$field_content    = isset( $options['field_content'] ) ? $options['field_content'] : 'Description';
		$field_status     = isset( $options['field_status'] ) ? $options['field_status'] : 'Status';
		$field_price      = isset( $options['field_price'] ) ? $options['field_price'] : 'Price';
		$field_mileage    = isset( $options['field_mileage'] ) ? $options['field_mileage'] : 'F';
		$currency_symbol  = isset( $options['currency_symbol'] ) ? $options['currency_symbol'] : '$';
		$whatsapp_number  = isset( $options['whatsapp_number'] ) ? $options['whatsapp_number'] : '';
		$frequency        = isset( $options['frequency'] ) ? $options['frequency'] : 'hourly';
		$custom_meta      = isset( $options['custom_meta'] ) && is_array( $options['custom_meta'] ) ? $options['custom_meta'] : array();

		// Default initial vehicle custom meta rows if empty.
		if ( empty( $custom_meta ) ) {
			$custom_meta = array(
				array( 'sheet_col' => 'Year', 'meta_key' => '_vehicle_year', 'label' => 'Year', 'icon' => '', 'display' => 'badge' ),
				array( 'sheet_col' => 'F', 'meta_key' => '_vehicle_mileage', 'label' => 'Mileage', 'icon' => '', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Fuel Type', 'meta_key' => '_vehicle_fuel', 'label' => 'Fuel', 'icon' => '', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Transmission', 'meta_key' => '_vehicle_transmission', 'label' => 'Gearbox', 'icon' => '', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Engine', 'meta_key' => '_vehicle_engine', 'label' => 'Engine', 'icon' => '', 'display' => 'detail' ),
				array( 'sheet_col' => 'Color', 'meta_key' => '_vehicle_color', 'label' => 'Color', 'icon' => '', 'display' => 'detail' ),
			);
		}

		$registered_pts = $this->get_registered_post_types();
		?>
		<div class="wrap sheet-sync-container">
			<div class="sheet-sync-card">
				<div class="sheet-sync-header">
					<div>
						<h1><?php esc_html_e( 'Vehicle Sheet Sync & Product Catalog', 'dynamic-sheet-sync' ); ?></h1>
						<p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;"><?php esc_html_e( 'Seamlessly import vehicle inventory from Google Sheets and display as high-converting vehicle showcase cards with Google Drive image sliders and dedicated detail pages.', 'dynamic-sheet-sync' ); ?></p>
					</div>
					<span class="sheet-sync-badge">v2.1 PRO</span>
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
									/* translators: 1: formatted date/time, 2: total items, 3: created count, 4: updated count, 5: status text */
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
							<button type="submit" class="button button-primary" style="padding: 4px 16px; height: 36px; font-weight: 600;"><?php esc_html_e( 'Sync Now', 'dynamic-sheet-sync' ); ?></button>
						</form>
					</div>
				</div>

				<!-- Navigation Tabs -->
				<div class="sheet-nav-tabs">
					<a class="sheet-nav-tab active" data-tab="tab-sheet-config"><?php esc_html_e( 'Sheet & Column Mapping', 'dynamic-sheet-sync' ); ?></a>
					<a class="sheet-nav-tab" data-tab="tab-specs-repeater"><?php esc_html_e( 'Vehicle Specs & Meta Attributes', 'dynamic-sheet-sync' ); ?></a>
					<a class="sheet-nav-tab" data-tab="tab-shortcodes"><?php esc_html_e( 'Frontend Shortcodes & Showcase', 'dynamic-sheet-sync' ); ?></a>
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
									<p class="description"><?php esc_html_e( 'In Google Sheets: File > Share > Publish to web > Select CSV format and paste the published URL here.', 'dynamic-sheet-sync' ); ?></p>
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
									<p class="description"><?php esc_html_e( 'Select the post type to store vehicles into (Posts, Products, or custom vehicle post type).', 'dynamic-sheet-sync' ); ?></p>
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
											<input type="text" id="uid_meta_key" name="dynamic_sheet_sync_options[uid_meta_key]" value="<?php echo esc_attr( $uid_meta_key ); ?>" placeholder="e.g. _vehicle_car_id" />
										</div>
									</div>
									<p class="description"><?php esc_html_e( 'Enter Column Letter (e.g. B) or Header Name (e.g. Car ID). Prevents duplicates and updates existing vehicles.', 'dynamic-sheet-sync' ); ?></p>
								</td>
							</tr>

							<!-- Dynamic Multi-Column Title Builder -->
							<tr>
								<th scope="row"><label for="title_template"><?php esc_html_e( 'Product Title Template', 'dynamic-sheet-sync' ); ?></label></th>
								<td>
									<input type="text" id="title_template" name="dynamic_sheet_sync_options[title_template]" value="<?php echo esc_attr( $title_template ); ?>" placeholder="{Car Name} {Model} {Year}" class="regular-text" />
									<p class="description" style="margin-top: 4px;">
										<?php esc_html_e( 'Combines columns into a single vehicle title:', 'dynamic-sheet-sync' ); ?>
										<br/>
										<span class="helper-tag" onclick="document.getElementById('title_template').value='{Car Name} {Model} {Year}'">{Car Name} {Model} {Year}</span>
										<span class="helper-tag" onclick="document.getElementById('title_template').value='{C} {D} {E}'">{C} {D} {E} (Column Letters)</span>
									</p>
								</td>
							</tr>

							<!-- Google Drive Images (AA and AB) -->
							<tr style="background: #f0fdf4; border-top: 1px solid #bbf7d0; border-bottom: 1px solid #bbf7d0;">
								<th scope="row" style="padding-left: 12px;">
									<strong><?php esc_html_e( 'Google Drive Image Columns (Slider)', 'dynamic-sheet-sync' ); ?></strong>
								</th>
								<td>
									<div style="display: flex; gap: 15px; flex-wrap: wrap;">
										<div style="flex: 1; max-width: 250px;">
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;" for="field_image"><?php esc_html_e( 'Main Image Column (Default: AA)', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="field_image" name="dynamic_sheet_sync_options[field_image]" value="<?php echo esc_attr( $field_image ); ?>" placeholder="AA" />
										</div>
										<div style="flex: 1; max-width: 250px;">
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;" for="field_sub_images"><?php esc_html_e( 'Sub Images Column (Default: AB)', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="field_sub_images" name="dynamic_sheet_sync_options[field_sub_images]" value="<?php echo esc_attr( $field_sub_images ); ?>" placeholder="AB" />
										</div>
									</div>
									<p class="description" style="color: #166534; font-weight: 500;">
										<?php esc_html_e( 'Column AA contains the main Google Drive image link. Column AB contains comma-separated sub-image Google Drive links. These automatically feed into the interactive card image slider and single vehicle page gallery.', 'dynamic-sheet-sync' ); ?>
									</p>
								</td>
							</tr>

							<!-- Standard Mappings -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Other Vehicle Columns', 'dynamic-sheet-sync' ); ?></th>
								<td>
									<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; max-width: 520px;">
										<div>
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="field_price"><?php esc_html_e( 'Price Column', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="field_price" name="dynamic_sheet_sync_options[field_price]" value="<?php echo esc_attr( $field_price ); ?>" placeholder="e.g. Price" />
										</div>
										<div>
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="field_mileage"><?php esc_html_e( 'Mileage Column (Default: F)', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="field_mileage" name="dynamic_sheet_sync_options[field_mileage]" value="<?php echo esc_attr( $field_mileage ); ?>" placeholder="e.g. F or Mileage" />
										</div>
										<div>
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="field_content"><?php esc_html_e( 'Description Column', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="field_content" name="dynamic_sheet_sync_options[field_content]" value="<?php echo esc_attr( $field_content ); ?>" placeholder="e.g. Description or Details" />
										</div>
										<div>
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="field_status"><?php esc_html_e( 'Stock / Post Status Column', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="field_status" name="dynamic_sheet_sync_options[field_status]" value="<?php echo esc_attr( $field_status ); ?>" placeholder="e.g. Status" />
										</div>
										<div>
											<label style="display:block; font-weight:600; font-size:12px; margin-bottom: 3px;" for="currency_symbol"><?php esc_html_e( 'Currency Symbol', 'dynamic-sheet-sync' ); ?></label>
											<input type="text" id="currency_symbol" name="dynamic_sheet_sync_options[currency_symbol]" value="<?php echo esc_attr( $currency_symbol ); ?>" placeholder="$" />
										</div>
									</div>
								</td>
							</tr>

							<!-- Global WhatsApp Settings -->
							<tr>
								<th scope="row"><label for="whatsapp_number"><?php esc_html_e( 'WhatsApp Inquiry Phone', 'dynamic-sheet-sync' ); ?></label></th>
								<td>
									<input type="text" id="whatsapp_number" name="dynamic_sheet_sync_options[whatsapp_number]" value="<?php echo esc_attr( $whatsapp_number ); ?>" placeholder="e.g. 15551234567" class="regular-text" />
									<p class="description"><?php esc_html_e( 'Phone number with country code for direct WhatsApp vehicle inquiries on single product pages.', 'dynamic-sheet-sync' ); ?></p>
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
								<p class="description" style="margin:0;"><?php esc_html_e( 'Map Google Sheet columns to vehicle specs to filter inventory and show comprehensive specs on the vehicle detail page.', 'dynamic-sheet-sync' ); ?></p>
							</div>
							<button type="button" class="button button-secondary" id="btn-add-meta-row">+ <?php esc_html_e( 'Add Spec Column', 'dynamic-sheet-sync' ); ?></button>
						</div>

						<table class="repeater-table" id="custom-meta-repeater-table">
							<thead>
								<tr>
									<th style="width: 22%;"><?php esc_html_e( 'Sheet Column (Header/Letter)', 'dynamic-sheet-sync' ); ?></th>
									<th style="width: 22%;"><?php esc_html_e( 'WordPress Meta Key', 'dynamic-sheet-sync' ); ?></th>
									<th style="width: 20%;"><?php esc_html_e( 'Display Label', 'dynamic-sheet-sync' ); ?></th>
									<th style="width: 12%;"><?php esc_html_e( 'Icon / Emoji', 'dynamic-sheet-sync' ); ?></th>
									<th style="width: 18%;"><?php esc_html_e( 'Show On Details Page', 'dynamic-sheet-sync' ); ?></th>
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
												<input type="text" name="dynamic_sheet_sync_options[custom_meta][<?php echo intval( $index ); ?>][icon]" value="<?php echo esc_attr( isset( $row['icon'] ) ? $row['icon'] : '' ); ?>" placeholder="e.g. •" style="text-align: center;" />
											</td>
											<td>
												<select name="dynamic_sheet_sync_options[custom_meta][<?php echo intval( $index ); ?>][display]">
													<option value="primary_spec" <?php selected( $disp, 'primary_spec' ); ?>><?php esc_html_e( 'Full Specs Grid', 'dynamic-sheet-sync' ); ?></option>
													<option value="badge" <?php selected( $disp, 'badge' ); ?>><?php esc_html_e( 'Highlight Spec', 'dynamic-sheet-sync' ); ?></option>
													<option value="detail" <?php selected( $disp, 'detail' ); ?>><?php esc_html_e( 'Details Table', 'dynamic-sheet-sync' ); ?></option>
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
						<h3 style="margin: 0 0 8px 0; font-size: 16px;"><?php esc_html_e( 'Vehicle Showcase Shortcodes', 'dynamic-sheet-sync' ); ?></h3>
						<p style="color: #64748b; font-size: 13.5px; margin-bottom: 20px;">
							<?php esc_html_e( 'Paste these shortcodes into any page, post, or block editor. Cards display 9 per page with real-time pagination, multi-image slider, and links to full vehicle pages.', 'dynamic-sheet-sync' ); ?>
						</p>

						<div style="margin-bottom: 20px;">
							<label style="font-weight: 700; display: block; margin-bottom: 4px;"><?php esc_html_e( '1. Complete Vehicle Product Grid (9 Per Page + Filter & Search)', 'dynamic-sheet-sync' ); ?></label>
							<div class="shortcode-preview-box">
								<code>[vehicle_products columns="3" per_page="9" show_filter="yes" show_search="yes"]</code>
								<button type="button" class="copy-btn" onclick="navigator.clipboard.writeText('[vehicle_products columns=\'3\' per_page=\'9\' show_filter=\'yes\' show_search=\'yes\']'); alert('Shortcode copied!');"><?php esc_html_e( 'Copy Shortcode', 'dynamic-sheet-sync' ); ?></button>
							</div>
						</div>

						<div style="margin-bottom: 20px;">
							<label style="font-weight: 700; display: block; margin-bottom: 4px;"><?php esc_html_e( '2. 4-Column Showcase Grid', 'dynamic-sheet-sync' ); ?></label>
							<div class="shortcode-preview-box">
								<code>[vehicle_products columns="4" per_page="12" show_filter="yes" show_search="yes"]</code>
								<button type="button" class="copy-btn" onclick="navigator.clipboard.writeText('[vehicle_products columns=\'4\' per_page=\'12\' show_filter=\'yes\' show_search=\'yes\']'); alert('Shortcode copied!');"><?php esc_html_e( 'Copy Shortcode', 'dynamic-sheet-sync' ); ?></button>
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
						'<td><input type="text" name="dynamic_sheet_sync_options[custom_meta][' + rowIndex + '][icon]" placeholder="e.g. •" style="text-align:center;" value="" /></td>' +
						'<td>' +
							'<select name="dynamic_sheet_sync_options[custom_meta][' + rowIndex + '][display]">' +
								'<option value="primary_spec">Full Specs Grid</option>' +
								'<option value="badge">Highlight Spec</option>' +
								'<option value="detail">Details Table</option>' +
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

		$csv_url          = isset( $options['csv_url'] ) ? $options['csv_url'] : '';
		$post_type        = isset( $options['post_type'] ) ? $options['post_type'] : 'post';
		$uid_sheet_col    = isset( $options['uid_sheet_col'] ) ? $options['uid_sheet_col'] : 'Car ID';
		$uid_meta_key     = isset( $options['uid_meta_key'] ) ? $options['uid_meta_key'] : '_vehicle_car_id';
		$title_template   = isset( $options['title_template'] ) ? $options['title_template'] : '{Car Name} {Model} {Year}';
		$field_image_col  = isset( $options['field_image'] ) ? $options['field_image'] : 'AA';
		$field_sub_img_col = isset( $options['field_sub_images'] ) ? $options['field_sub_images'] : 'AB';

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

		// Resolve Column Indexes.
		$content_col_header = isset( $options['field_content'] ) ? $options['field_content'] : '';
		$status_col_header  = isset( $options['field_status'] ) ? $options['field_status'] : '';
		$price_col_header   = isset( $options['field_price'] ) ? $options['field_price'] : '';

		$content_index   = ! empty( $content_col_header ) ? self::resolve_col_index( $content_col_header, $headers ) : false;
		$status_index    = ! empty( $status_col_header ) ? self::resolve_col_index( $status_col_header, $headers ) : false;
		$price_index     = ! empty( $price_col_header ) ? self::resolve_col_index( $price_col_header, $headers ) : false;
		$mileage_col     = isset( $options['field_mileage'] ) && '' !== trim( $options['field_mileage'] ) ? $options['field_mileage'] : 'F';
		$mileage_index   = self::resolve_col_index( $mileage_col, $headers );
		$body_style_index = self::resolve_col_index( 'M', $headers );
		if ( false === $body_style_index ) {
			$body_style_index = self::resolve_col_index( 'Body Style', $headers );
		}
		$condition_index = self::resolve_col_index( 'V', $headers );
		if ( false === $condition_index ) {
			$condition_index = self::resolve_col_index( 'Condition', $headers );
		}
		$image_index     = self::resolve_col_index( $field_image_col, $headers );
		$sub_image_index = self::resolve_col_index( $field_sub_img_col, $headers );

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

			// Build dynamic title (Car Name + Model + Year as one title).
			$post_title = self::build_dynamic_title( $title_template, $row_data, $headers, $unique_id_value );

			$post_content  = ( false !== $content_index && isset( $row_data[$content_index] ) ) ? wp_kses_post( trim( $row_data[$content_index] ) ) : '';
			$post_status   = ( false !== $status_index && isset( $row_data[$status_index] ) ) ? sanitize_text_field( trim( strtolower( $row_data[$status_index] ) ) ) : 'publish';
			$price_value   = ( false !== $price_index && isset( $row_data[$price_index] ) ) ? sanitize_text_field( trim( $row_data[$price_index] ) ) : '';
			$mileage_value = ( false !== $mileage_index && isset( $row_data[$mileage_index] ) ) ? sanitize_text_field( trim( $row_data[$mileage_index] ) ) : '';

			// Images from Column AA and AB.
			$main_img_raw = ( false !== $image_index && isset( $row_data[$image_index] ) ) ? trim( $row_data[$image_index] ) : '';
			$sub_imgs_raw = ( false !== $sub_image_index && isset( $row_data[$sub_image_index] ) ) ? trim( $row_data[$sub_image_index] ) : '';
			$gallery_urls = self::parse_gallery_image_urls( $main_img_raw, $sub_imgs_raw );

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

				// Save Mileage (Default Column F).
				if ( ! empty( $mileage_value ) ) {
					update_post_meta( $post_id, '_vehicle_mileage', $mileage_value );
				}

				// Save Body Style (Column M) & Condition (Column V).
				$body_style_val = ( false !== $body_style_index && isset( $row_data[$body_style_index] ) ) ? sanitize_text_field( trim( $row_data[$body_style_index] ) ) : '';
				if ( ! empty( $body_style_val ) ) {
					update_post_meta( $post_id, '_vehicle_body_style', $body_style_val );
				}

				$condition_val = ( false !== $condition_index && isset( $row_data[$condition_index] ) ) ? sanitize_text_field( trim( $row_data[$condition_index] ) ) : '';
				if ( ! empty( $condition_val ) ) {
					update_post_meta( $post_id, '_vehicle_condition', $condition_val );
				}

				// Process Custom Meta Fields.
				foreach ( $custom_meta_resolved as $mapping ) {
					$meta_value = isset( $row_data[$mapping['index']] ) ? sanitize_text_field( trim( $row_data[$mapping['index']] ) ) : '';
					update_post_meta( $post_id, $mapping['meta_key'], $meta_value );
				}

				// Save Gallery Images (Main + Sub Images).
				update_post_meta( $post_id, '_vehicle_gallery', $gallery_urls );
				if ( ! empty( $gallery_urls[0] ) ) {
					update_post_meta( $post_id, '_vehicle_main_image', $gallery_urls[0] );
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
	 * Filter single post content to output full vehicle information page.
	 */
	public function render_single_vehicle_content( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id   = get_the_ID();
		$post_type = get_post_type( $post_id );
		$options   = get_option( 'dynamic_sheet_sync_options', array() );
		$target_pt = ! empty( $options['post_type'] ) ? $options['post_type'] : 'post';

		// Only apply to the configured vehicle post type if it has vehicle metadata.
		$car_id = get_post_meta( $post_id, '_vehicle_car_id', true );
		if ( empty( $car_id ) && $post_type !== $target_pt ) {
			return $content;
		}

		$car_title        = get_the_title( $post_id );
		$price            = get_post_meta( $post_id, '_vehicle_price', true );
		$currency         = ! empty( $options['currency_symbol'] ) ? $options['currency_symbol'] : '$';
		$whatsapp         = ! empty( $options['whatsapp_number'] ) ? $options['whatsapp_number'] : '';
		$custom_meta      = isset( $options['custom_meta'] ) && is_array( $options['custom_meta'] ) ? $options['custom_meta'] : array();
		$gallery          = get_post_meta( $post_id, '_vehicle_gallery', true );

		if ( ! is_array( $gallery ) || empty( $gallery ) ) {
			$thumb = get_the_post_thumbnail_url( $post_id, 'full' );
			$gallery = $thumb ? array( $thumb ) : array();
		}

		$main_image = ! empty( $gallery[0] ) ? $gallery[0] : '';

		// Collect specs.
		$all_specs = array();
		foreach ( $custom_meta as $spec ) {
			$val = get_post_meta( $post_id, $spec['meta_key'], true );
			if ( '' !== $val && 'hidden' !== ( isset( $spec['display'] ) ? $spec['display'] : '' ) ) {
				$all_specs[] = array(
					'label' => $spec['label'],
					'icon'  => ! empty( $spec['icon'] ) ? $spec['icon'] : '▪',
					'value' => $val,
				);
			}
		}

		// WhatsApp URL.
		$whatsapp_url = '';
		if ( ! empty( $whatsapp ) ) {
			$msg = rawurlencode( sprintf( 'Hello, I am interested in %s (Car ID: %s)', $car_title, $car_id ) );
			$whatsapp_url = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $whatsapp ) . '?text=' . $msg;
		}

		ob_start();
		?>
		<div class="vehicle-single-page">
			<div class="vehicle-single-breadcrumbs">
				<a href="javascript:history.back()">← <?php esc_html_e( 'Back to Inventory', 'dynamic-sheet-sync' ); ?></a> &nbsp;/&nbsp; <span><?php echo esc_html( $car_title ); ?></span>
			</div>

			<div class="vehicle-single-layout">
				<!-- Left: Gallery -->
				<div class="vehicle-single-gallery">
					<div class="vehicle-single-main-img-wrap">
						<?php if ( ! empty( $main_image ) ) : ?>
							<img src="<?php echo esc_url( $main_image ); ?>" alt="<?php echo esc_attr( $car_title ); ?>" class="vehicle-single-main-img" />
						<?php else : ?>
							<div class="vehicle-card-placeholder">
								<svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.22.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
								<span style="font-size: 13px; margin-top: 6px; font-weight: 600;"><?php esc_html_e( 'Photo Coming Soon', 'dynamic-sheet-sync' ); ?></span>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( count( $gallery ) > 1 ) : ?>
						<div class="vehicle-single-thumbs">
							<?php foreach ( $gallery as $g_idx => $img_url ) : ?>
								<div class="vehicle-single-thumb <?php echo 0 === $g_idx ? 'active' : ''; ?>" data-full-src="<?php echo esc_url( $img_url ); ?>">
									<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $car_title . ' - photo ' . ( $g_idx + 1 ) ); ?>" loading="lazy" />
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Right: Details & Action -->
				<div class="vehicle-single-info-panel">
					<h1 class="vehicle-single-title"><?php echo esc_html( $car_title ); ?></h1>
					<?php if ( ! empty( $car_id ) ) : ?>
						<span class="vehicle-single-id-tag"><?php esc_html_e( 'Car ID / VIN:', 'dynamic-sheet-sync' ); ?> #<?php echo esc_html( $car_id ); ?></span>
					<?php endif; ?>

					<?php if ( ! empty( $price ) ) : ?>
						<div class="vehicle-single-price-box">
							<span style="font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase;"><?php esc_html_e( 'Vehicle Price', 'dynamic-sheet-sync' ); ?></span>
							<div class="vehicle-single-price">
								<span><?php echo esc_html( $currency ); ?></span><?php echo esc_html( number_format_i18n( floatval( preg_replace( '/[^0-9.]/', '', $price ) ) ) ); ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Specifications Grid -->
					<h4 style="margin: 20px 0 10px 0; font-size: 15px; font-weight: 700; color: #1e293b;"><?php esc_html_e( 'Vehicle Specifications', 'dynamic-sheet-sync' ); ?></h4>
					<div class="vehicle-single-specs-grid">
						<?php if ( ! empty( $car_id ) ) : ?>
							<div class="vehicle-single-spec-card">
								<div>
									<span class="vehicle-single-spec-label"><?php esc_html_e( 'Vehicle ID', 'dynamic-sheet-sync' ); ?></span>
									<span class="vehicle-single-spec-val">#<?php echo esc_html( $car_id ); ?></span>
								</div>
							</div>
						<?php endif; ?>
						<?php foreach ( $all_specs as $s ) : ?>
							<div class="vehicle-single-spec-card">
								<?php if ( ! empty( $s['icon'] ) ) : ?>
									<span class="vehicle-single-spec-icon"><?php echo esc_html( $s['icon'] ); ?></span>
								<?php endif; ?>
								<div>
									<span class="vehicle-single-spec-label"><?php echo esc_html( $s['label'] ); ?></span>
									<span class="vehicle-single-spec-val"><?php echo esc_html( $s['value'] ); ?></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<!-- CTAs -->
					<div class="vehicle-single-actions">
						<?php if ( ! empty( $whatsapp_url ) ) : ?>
							<a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener" class="vehicle-single-btn-whatsapp">
								<?php esc_html_e( 'Inquire via WhatsApp', 'dynamic-sheet-sync' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Full Overview -->
			<?php if ( ! empty( $content ) ) : ?>
				<div class="vehicle-single-desc-section">
					<h3><?php esc_html_e( 'Vehicle Overview & Features', 'dynamic-sheet-sync' ); ?></h3>
					<?php echo wp_kses_post( $content ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render WooCommerce-Style Vehicle Products Shortcode with 9 per page & slider.
	 */
	public function render_vehicle_products_shortcode( $atts ) {
		$options = get_option( 'dynamic_sheet_sync_options', array() );

		$default_pt       = ! empty( $options['post_type'] ) ? $options['post_type'] : 'post';
		$default_currency = ! empty( $options['currency_symbol'] ) ? $options['currency_symbol'] : '$';
		$custom_meta      = isset( $options['custom_meta'] ) && is_array( $options['custom_meta'] ) ? $options['custom_meta'] : array();

		$atts = shortcode_atts( array(
			'columns'        => '1',
			'per_page'       => '-1', // Default to -1 (show all vehicles) as requested
			'posts_per_page' => '-1', // Query all vehicles
			'post_status'    => 'publish,pending,draft,private,any',
			'post_type'      => $default_pt,
			'show_filter'    => 'yes',
			'show_search'    => 'yes',
			'currency'       => $default_currency,
			'orderby'        => 'date',
			'order'          => 'DESC',
		), $atts, 'vehicle_products' );

		// Query vehicles - ensure all synced vehicles are included regardless of status if requested.
		$query_status = 'publish';
		if ( ! empty( $atts['post_status'] ) ) {
			if ( 'any' === trim( $atts['post_status'] ) ) {
				$query_status = 'any';
			} elseif ( strpos( $atts['post_status'], ',' ) !== false ) {
				$query_status = array_map( 'trim', explode( ',', sanitize_text_field( $atts['post_status'] ) ) );
			} else {
				$query_status = sanitize_text_field( $atts['post_status'] );
			}
		}

		$args = array(
			'post_type'      => sanitize_key( $atts['post_type'] ),
			'post_status'    => $query_status,
			'posts_per_page' => intval( $atts['posts_per_page'] ),
			'orderby'        => sanitize_key( $atts['orderby'] ),
			'order'          => sanitize_key( $atts['order'] ),
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '<div class="vehicle-grid-container"><div class="vehicle-no-results"><h3>' . esc_html__( 'No vehicles available in the inventory yet.', 'dynamic-sheet-sync' ) . '</h3></div></div>';
		}

		// Collect unique filter options (Fuels, Years, Transmissions).
		$all_fuels = array();
		$all_years = array();
		$all_trans = array();

		$cards_html = '';

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id   = get_the_ID();
			$car_title = get_the_title();
			$permalink = get_permalink( $post_id );

			$car_id = get_post_meta( $post_id, '_vehicle_car_id', true );
			if ( empty( $car_id ) ) {
				$car_id = get_post_meta( $post_id, ( ! empty( $options['uid_meta_key'] ) ? $options['uid_meta_key'] : '_sku' ), true );
			}

			// Price.
			$price = get_post_meta( $post_id, '_vehicle_price', true );
			if ( empty( $price ) ) {
				$price = get_post_meta( $post_id, '_price', true );
			}

			// Parse custom specs for filter attributes.
			$fuel_val  = '';
			$year_val  = '';
			$trans_val = '';

			foreach ( $custom_meta as $spec ) {
				$val = get_post_meta( $post_id, $spec['meta_key'], true );
				if ( '' === $val ) {
					continue;
				}

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
			}

			// Mileage (Fetched from Column F or metadata).
			$mileage = get_post_meta( $post_id, '_vehicle_mileage', true );
			if ( empty( $mileage ) ) {
				foreach ( $custom_meta as $spec ) {
					if ( stripos( $spec['label'], 'Mileage' ) !== false || stripos( $spec['meta_key'], 'mileage' ) !== false ) {
						$m_val = get_post_meta( $post_id, $spec['meta_key'], true );
						if ( ! empty( $m_val ) ) {
							$mileage = $m_val;
							break;
						}
					}
				}
			}

			// Body Style (Column M) & Condition (Column V).
			$body_style = get_post_meta( $post_id, '_vehicle_body_style', true );
			if ( empty( $body_style ) ) {
				foreach ( $custom_meta as $spec ) {
					if ( ( isset( $spec['sheet_col'] ) && 'M' === strtoupper( trim( $spec['sheet_col'] ) ) )
						|| stripos( $spec['label'], 'Body' ) !== false
						|| stripos( $spec['meta_key'], 'body_style' ) !== false ) {
						$bs_val = get_post_meta( $post_id, $spec['meta_key'], true );
						if ( ! empty( $bs_val ) ) {
							$body_style = $bs_val;
							break;
						}
					}
				}
			}

			$condition = get_post_meta( $post_id, '_vehicle_condition', true );
			if ( empty( $condition ) ) {
				foreach ( $custom_meta as $spec ) {
					if ( ( isset( $spec['sheet_col'] ) && 'V' === strtoupper( trim( $spec['sheet_col'] ) ) )
						|| stripos( $spec['label'], 'Condition' ) !== false
						|| stripos( $spec['meta_key'], 'condition' ) !== false ) {
						$c_val = get_post_meta( $post_id, $spec['meta_key'], true );
						if ( ! empty( $c_val ) ) {
							$condition = $c_val;
							break;
						}
					}
				}
			}

			// Spec text line 2: Mileage | Body Style | Condition (plain text values only).
			$specs_line_items = array_filter( array( $mileage, $body_style, $condition ), function( $val ) {
				return null !== $val && '' !== trim( strval( $val ) );
			} );
			$specs_line_text = implode( ' | ', $specs_line_items );

			// Format Price line 3 (plain value only, unique display).
			$clean_price = preg_replace( '/[^0-9.]/', '', strval( $price ) );
			$formatted_price = ! empty( $clean_price ) ? $atts['currency'] . number_format_i18n( floatval( $clean_price ) ) : ( ! empty( $price ) ? esc_html( $price ) : '' );

			// Get Gallery Images (Column AA + AB).
			$gallery = get_post_meta( $post_id, '_vehicle_gallery', true );
			if ( ! is_array( $gallery ) || empty( $gallery ) ) {
				$main_img = get_post_meta( $post_id, '_vehicle_main_image', true );
				if ( ! empty( $main_img ) ) {
					$gallery = array( $main_img );
				} else {
					$thumb = get_the_post_thumbnail_url( $post_id, 'large' );
					$gallery = $thumb ? array( $thumb ) : array();
				}
			}

			$image_count = count( $gallery );

			// Build Card HTML with Modern Horizontal Layout: Left slider column, Right details column (3 lines).
			ob_start();
			?>
			<div class="vehicle-card" 
				data-title="<?php echo esc_attr( $car_title ); ?>" 
				data-id="<?php echo esc_attr( $car_id ); ?>" 
				data-fuel="<?php echo esc_attr( $fuel_val ); ?>" 
				data-year="<?php echo esc_attr( $year_val ); ?>" 
				data-transmission="<?php echo esc_attr( $trans_val ); ?>"
				data-mileage="<?php echo esc_attr( $mileage ); ?>">
				
				<!-- Left Column: Card Image Slider -->
				<div class="vehicle-card-slider-container" data-current-index="0">
					<?php if ( $image_count > 0 ) : ?>
						<div class="vehicle-slider-track">
							<?php foreach ( $gallery as $idx => $img_url ) : ?>
								<div class="vehicle-slider-slide <?php echo 0 === $idx ? 'active' : ''; ?>">
									<a href="<?php echo esc_url( $permalink ); ?>" style="display:block; width:100%; height:100%;">
										<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $car_title ); ?>" class="vehicle-slider-img" loading="lazy" />
									</a>
								</div>
							<?php endforeach; ?>
						</div>

						<?php if ( $image_count > 1 ) : ?>
							<!-- Navigation Buttons -->
							<button type="button" class="vehicle-slider-btn prev" aria-label="Previous image">‹</button>
							<button type="button" class="vehicle-slider-btn next" aria-label="Next image">›</button>

							<!-- Image Counter & Indicator Dots -->
							<div class="vehicle-slider-counter">1 / <?php echo intval( $image_count ); ?></div>
							<div class="vehicle-slider-dots">
								<?php for ( $i = 0; $i < min( 6, $image_count ); $i++ ) : ?>
									<span class="vehicle-slider-dot <?php echo 0 === $i ? 'active' : ''; ?>" data-index="<?php echo intval( $i ); ?>"></span>
								<?php endfor; ?>
							</div>
						<?php endif; ?>

					<?php else : ?>
						<a href="<?php echo esc_url( $permalink ); ?>" class="vehicle-card-placeholder">
							<svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.22.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
							<span style="font-size: 11px; margin-top: 4px; font-weight: 600;"><?php esc_html_e( 'Photo Coming Soon', 'dynamic-sheet-sync' ); ?></span>
						</a>
					<?php endif; ?>
				</div>

				<!-- Right Column: Vehicle Details (Line 1: Title, Line 2: Mileage | Body Style | Condition, Line 3: Price) -->
				<div class="vehicle-card-body">
					<div class="vehicle-card-details">
						<!-- Line 1: Vehicle Card Title -->
						<h3 class="vehicle-card-title">
							<a href="<?php echo esc_url( $permalink ); ?>">
								<?php echo esc_html( $car_title ); ?>
							</a>
						</h3>

						<!-- Line 2: Mileage | Body Style | Condition (Values only, simple text) -->
						<?php if ( ! empty( $specs_line_text ) ) : ?>
							<div class="vehicle-card-specs-line">
								<?php echo esc_html( $specs_line_text ); ?>
							</div>
						<?php endif; ?>

						<!-- Line 3: Price (Value only, unique prominent text) -->
						<?php if ( ! empty( $formatted_price ) ) : ?>
							<div class="vehicle-card-price-unique">
								<?php echo esc_html( $formatted_price ); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
			$cards_html .= ob_get_clean();
		}

		wp_reset_postdata();

		// Build Filter Bar HTML if enabled.
		$filter_bar_html = '';
		if ( 'yes' === $atts['show_filter'] || 'yes' === $atts['show_search'] ) {
			$filter_bar_html .= '<div class="vehicle-filter-wrapper">';
			$filter_bar_html .= '<div class="vehicle-filter-main-row">';

			if ( 'yes' === $atts['show_search'] ) {
				$filter_bar_html .= '
					<div class="vehicle-search-box">						
						<input type="text" class="vehicle-search-input" placeholder="' . esc_attr__( 'What are you looking for?', 'dynamic-sheet-sync' ) . '" />
						<button type="button" class="vehicle-search-clear" title="' . esc_attr__( 'Clear search', 'dynamic-sheet-sync' ) . '">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
						</button>
					</div>
				';
			}

			if ( 'yes' === $atts['show_filter'] ) {
				$filter_bar_html .= '<div class="vehicle-filter-controls">';

				$select_arrow_svg = '<span class="vehicle-select-arrow"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span>';

				// Fuel Filter.
				if ( ! empty( $all_fuels ) ) {
					$filter_bar_html .= '
					<div class="vehicle-select-wrap">
						<select class="vehicle-filter-select vehicle-filter-fuel">
							<option value="">' . esc_html__( 'All Fuels', 'dynamic-sheet-sync' ) . '</option>';
					foreach ( array_keys( $all_fuels ) as $f ) {
						$filter_bar_html .= '<option value="' . esc_attr( $f ) . '">' . esc_html( $f ) . '</option>';
					}
					$filter_bar_html .= '</select>' . $select_arrow_svg . '</div>';
				}

				// Year Filter.
				if ( ! empty( $all_years ) ) {
					krsort( $all_years );
					$filter_bar_html .= '
					<div class="vehicle-select-wrap">
						<select class="vehicle-filter-select vehicle-filter-year">
							<option value="">' . esc_html__( 'All Years', 'dynamic-sheet-sync' ) . '</option>';
					foreach ( array_keys( $all_years ) as $y ) {
						$filter_bar_html .= '<option value="' . esc_attr( $y ) . '">' . esc_html( $y ) . '</option>';
					}
					$filter_bar_html .= '</select>' . $select_arrow_svg . '</div>';
				}

				// Transmission Filter.
				if ( ! empty( $all_trans ) ) {
					$filter_bar_html .= '
					<div class="vehicle-select-wrap">
						<select class="vehicle-filter-select vehicle-filter-transmission">
							<option value="">' . esc_html__( 'All Transmissions', 'dynamic-sheet-sync' ) . '</option>';
					foreach ( array_keys( $all_trans ) as $t ) {
						$filter_bar_html .= '<option value="' . esc_attr( $t ) . '">' . esc_html( $t ) . '</option>';
					}
					$filter_bar_html .= '</select>' . $select_arrow_svg . '</div>';
				}

				$filter_bar_html .= '
					<button type="button" class="vehicle-reset-btn">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><polyline points="3 3 3 8 8 8"></polyline></svg>
						<span>' . esc_html__( 'Reset Filters', 'dynamic-sheet-sync' ) . '</span>
					</button>';
				$filter_bar_html .= '</div>';
			}

			$filter_bar_html .= '</div>';

			// Meta Status Row (Active chips & live result counter).
			$filter_bar_html .= '
				<div class="vehicle-filter-meta-bar">
					<div class="vehicle-count-badge">' . esc_html__( 'Showing vehicles...', 'dynamic-sheet-sync' ) . '</div>
					<div class="vehicle-active-chips"></div>
				</div>
			';

			$filter_bar_html .= '</div>';
		}

		$cols = max( 1, min( 4, intval( $atts['columns'] ) ) );

		$per_page_attr = intval( $atts['per_page'] );

		// Final Output assembly.
		$output  = '<div class="vehicle-grid-container">';
		$output .= $filter_bar_html;
		$output .= '<div class="vehicle-grid" data-per-page="' . esc_attr( $per_page_attr ) . '" style="--vg-cols: ' . esc_attr( $cols ) . ';">';
		$output .= $cards_html;
		$output .= '<div class="vehicle-no-results" style="display:none;">
			<svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
			<h3>' . esc_html__( 'No matching vehicles found', 'dynamic-sheet-sync' ) . '</h3>
			<p>' . esc_html__( 'Try adjusting your search criteria or reset filters to see all available inventory.', 'dynamic-sheet-sync' ) . '</p>
		</div>';
		$output .= '</div>';
		$output .= '<div class="vehicle-pagination-container"></div>';
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
			'field_image'        => 'AA',
			'field_sub_images'   => 'AB',
			'field_content'      => 'Description',
			'field_status'       => 'Status',
			'field_price'        => 'Price',
			'field_mileage'      => 'F',
			'currency_symbol'    => '$',
			'whatsapp_number'    => '',
			'frequency'          => 'hourly',
			'custom_meta'        => array(
				array( 'sheet_col' => 'Year', 'meta_key' => '_vehicle_year', 'label' => 'Year', 'icon' => '', 'display' => 'badge' ),
				array( 'sheet_col' => 'F', 'meta_key' => '_vehicle_mileage', 'label' => 'Mileage', 'icon' => '', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Fuel Type', 'meta_key' => '_vehicle_fuel', 'label' => 'Fuel', 'icon' => '', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Transmission', 'meta_key' => '_vehicle_transmission', 'label' => 'Gearbox', 'icon' => '', 'display' => 'primary_spec' ),
				array( 'sheet_col' => 'Engine', 'meta_key' => '_vehicle_engine', 'label' => 'Engine', 'icon' => '', 'display' => 'detail' ),
				array( 'sheet_col' => 'Color', 'meta_key' => '_vehicle_color', 'label' => 'Color', 'icon' => '', 'display' => 'detail' ),
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

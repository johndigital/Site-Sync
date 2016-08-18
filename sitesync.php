<?php 

/*
 *
 *	Plugin Name: Site Sync by Funkhaus
 *	Plugin URI: http://funkhaus.us
 *	Description: A simple import/export plugin for pages and posts
 *	Author: Funkhaus
 *	Version: 1.0
 *	Author URI: http://Funkhaus.us
 *	Requires at least: 3.8
 * 
 */

	require_once('ss-export.php');
	require_once('ss-import.php');
	require_once('ss-settings.php');

	function funkexporter_init() {
		$exporter = new Funkexporter;

	// ------------Settings-----------//

		// INTEGER: total number of posts/pages to return per request
		$exporter->total = esc_attr(get_option('sync_exp_max'));

		// INTEGER: ID of page to sync
		$exporter->page = esc_attr(get_option('sync_exp_page'));

		// ARRAY: list of category IDs to include
		$exporter->category = esc_attr(get_option('sync_exp_cat'));

	// ------------------------------//

		$exporter->export();

	}

	// Link exporter to admin-ajax
	add_action( 'wp_ajax_funkexporter_init', 'funkexporter_init' );
	add_action( 'wp_ajax_nopriv_funkexporter_init', 'funkexporter_init' );

	function funkimporter_init() {
		$importer = new Funkimporter;


	// ------------Settings-----------//

		// STRING: Root URL to get posts from
		$importer->source_url = esc_attr(get_option('sync_imp_url'));

		// INTEGER: ID of author
		$importer->author = esc_attr(get_option('sync_imp_author'));

		// INTEGER: ID of page to sync
		$importer->page = esc_attr(get_option('sync_exp_page'));

		// INTEGER: ID of category to add to imported posts
		$importer->category = esc_attr(get_option('sync_imp_cat'));

	// ------------------------------//


		$importer->import();

	}

	// Link importer to admin-ajax
	add_action( 'wp_ajax_funkimporter_init', 'funkimporter_init' );
	add_action( 'wp_ajax_nopriv_funkimporter_init', 'funkimporter_init' );


	// Set ten minute interval for cron
	function funkexporter_set_interval( $schedules ) {
		$schedules['ten_minutes'] = array(
			'interval' => 600,
			'display' => __('Every ten minutes')
		);
		return $schedules;
	}
	add_filter( 'cron_schedules', 'funkexporter_set_interval' );

	// If no cron scheduled, schedule it
	if ( ! wp_next_scheduled( 'funkexporter_cron' ) ) {
		wp_schedule_event( time(), 'ten_minutes', 'funkexporter_cron' );
	}

	// Hook import function to cron hook
	add_action( 'funkexporter_cron', 'funkimporter_init' );

?>
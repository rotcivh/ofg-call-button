<?php
/**
 * Cleanup plugin data on uninstall.
 *
 * @package Ofogh_Call_Button
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ofogh_call_btn_options' );
delete_option( 'ofogh_call_btn_clicks' );
delete_option( 'ofogh_call_btn_click_dedup' );

global $wpdb;
$ofg_call_button_table_name = esc_sql( preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix . 'ofogh_call_btn_events' ) );
$wpdb->query( "DROP TABLE IF EXISTS {$ofg_call_button_table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

delete_metadata( 'post', 0, '_ofogh_call_btn_hidden', '', true );
delete_metadata( 'post', 0, '_ofogh_call_btn_phone', '', true );

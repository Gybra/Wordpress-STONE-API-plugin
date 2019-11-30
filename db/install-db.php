<?php

/**
 * Executed when installing the plugin, it creates a table with suffix stone
 */
function stone_install() {
	global $wpdb;

  $table_name = $wpdb->prefix . 'stone';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		id_user int(11) NOT NULL,
		id_user_stone int(11) NOT NULL,
    email varchar(50) NOT NULL,
    referral varchar(50) NOT NULL,
		PRIMARY KEY  (id),
    INDEX user_index (id_user, email)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'stone_db_version', '1.0' );
}

register_activation_hook( PLUGIN_PATH, 'stone_install' );
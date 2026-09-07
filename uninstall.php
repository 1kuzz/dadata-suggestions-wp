<?php
/**
 * Remove plugin settings.
 *
 * @package Dadata_Suggestions
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'dadata_suggestions_options' );

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'dadata_suggestions_options' );
		restore_current_blog();
	}
}

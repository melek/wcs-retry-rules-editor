<?php
/**
 * Uninstall Script
 *
 * Removes all plugin data when the plugin is deleted.
 *
 * @package WCS_Retry_Rules_Editor
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'wcs_rre_active_rules' );

// Future: If version history is added, delete it here too.
// delete_option( 'wcs_rre_version_history' );

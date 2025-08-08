<?php
/**
 * Uninstall script for Royal Mail Note Blocker
 *
 * @package RoyalMailNoteBlocker
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('rmnb_keywords');
delete_option('rmnb_debug_mode');

// Clean up any transients or cached data if needed
wp_cache_flush();

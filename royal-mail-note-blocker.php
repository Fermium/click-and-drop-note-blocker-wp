<?php
/**
 * Plugin Name: Royal Mail Note Blocker
 * Plugin URI: https://github.com/Fermium/click-and-drop-note-blocker-wp
 * Description: Prevents Royal Mail tracking notes from being sent to customers as email notifications in WooCommerce.
 * Version: 0.1.0
 * Author: Fermium
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package RoyalMailNoteBlocker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RMNB_VERSION', '0.1.0');
define('RMNB_PLUGIN_FILE', __FILE__);
define('RMNB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RMNB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Declare HPOS compatibility
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Main plugin class
 */
class Royal_Mail_Note_Blocker {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Intercept customer note emails directly
        add_filter('woocommerce_email_enabled_customer_note', array($this, 'maybe_block_email'), 10, 2);
        
        // Optionally block completed order emails without tracking
        if (get_option('rmnb_block_emails', true)) {
            add_filter('woocommerce_email_enabled', array($this, 'maybe_block_email'), 10, 3);
        }
        
        // Hook for AST tracking email suppression if enabled
        if (get_option('rmnb_disable_no_tracking_emails', false)) {
            add_filter('woocommerce_email_enabled_customer_completed_order', array($this, 'disable_completed_email_without_tracking'), 10, 3);
        }
        
        // Add admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Block customer note emails if they contain Royal Mail keywords
     *
     * @param bool $enabled Whether the email is enabled
     * @param mixed $order_or_email_id The order object (for customer_note) or email ID (for general email filter)
     * @param WC_Order $order The order object (for general email filter)
     * @return bool
     */
    public function maybe_block_email($enabled, $order_or_email_id, $order = null) {
        if (!$enabled) {
            return false;
        }
        
        // Handle different hook signatures
        if ($order === null) {
            // Called from woocommerce_email_enabled_customer_note (2 params)
            $order = $order_or_email_id;
        }
        // else: Called from woocommerce_email_enabled (3 params)
        
        // Check if order object is valid
        if (!$order || !method_exists($order, 'get_id')) {
            return $enabled;
        }
        
        // Get the most recent note
        $notes = wc_get_order_notes(array(
            'order_id' => $order->get_id(),
            'limit' => 1,
            'orderby' => 'date_created',
            'order' => 'DESC'
        ));
        
        if (empty($notes)) {
            return $enabled;
        }
        
        $latest_note = $notes[0];
        
        // Get plugin settings
        $keywords = get_option('rmnb_keywords', array(
            'despatched via Royal Mail',
            'tracking number is',
            'royalmail.com/portal/rm/track'
        ));
        
        // Check if the latest note contains ALL keywords
        $note_content = strtolower($latest_note->content);
        $all_keywords_found = true;
        $found_keywords = array();
        
        foreach ($keywords as $keyword) {
            $keyword_trimmed = strtolower(trim($keyword));
            if (stripos($note_content, $keyword_trimmed) !== false) {
                $found_keywords[] = $keyword;
            } else {
                $all_keywords_found = false;
                break;
            }
        }
        
        if ($all_keywords_found && count($found_keywords) === count($keywords) && count($keywords) > 0) {
            // Log the blocked email
            if (get_option('rmnb_debug_mode', false)) {
                error_log('Royal Mail Note Blocker: Blocked email for order #' . $order->get_id() . ' - All keywords found: ' . implode(', ', $found_keywords));
                error_log('Royal Mail Note Blocker: Note content: ' . $latest_note->content);
            }
            return false; // Block the email
        }
        
        return $enabled;
    }
    
    /**
     * Disable completed order emails if no tracking info is available
     *
     * @param bool $enabled Whether the email is enabled
     * @param int $order_id The order ID
     * @param WC_Order $order The order object
     * @return bool
     */
    public function disable_completed_email_without_tracking($enabled, $order_id, $order) {
        if (!$enabled) {
            return false;
        }
        
        // Check if Advanced Shipment Tracking plugin is active
        if (function_exists('ast_get_tracking_items')) {
            $tracking_items = ast_get_tracking_items($order_id);
            if (empty($tracking_items) || !is_array($tracking_items)) {
                // Log the blocked email if debug mode is enabled
                if (get_option('rmnb_debug_mode', false)) {
                    error_log('Royal Mail Note Blocker: Blocked completed order email for order #' . $order_id . ' - No tracking items found');
                }
                return false; // Suppress email if no tracking info
            }
        }
        
        return $enabled;
    }
    
    /**
     * Show admin notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Royal Mail Note Blocker requires WooCommerce to be installed and active.', 'royal-mail-note-blocker');
        echo '</p></div>';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Royal Mail Note Blocker', 'royal-mail-note-blocker'),
            __('Note Blocker', 'royal-mail-note-blocker'),
            'manage_woocommerce',
            'royal-mail-note-blocker',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('rmnb_settings', 'rmnb_keywords', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_keywords'),
            'default' => array(
                'despatched via Royal Mail',
                'tracking number is',
                'royalmail.com/portal/rm/track'
            )
        ));
        
        register_setting('rmnb_settings', 'rmnb_debug_mode', array(
            'type' => 'boolean',
            'default' => false
        ));
        
        register_setting('rmnb_settings', 'rmnb_disable_no_tracking_emails', array(
            'type' => 'boolean',
            'default' => false
        ));
        
        // Add settings section
        add_settings_section(
            'rmnb_main_section',
            __('Royal Mail Note Blocking Settings', 'royal-mail-note-blocker'),
            array($this, 'settings_section_callback'),
            'rmnb_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'rmnb_keywords',
            __('Keywords to Block', 'royal-mail-note-blocker'),
            array($this, 'keywords_field_callback'),
            'rmnb_settings',
            'rmnb_main_section'
        );
        
        add_settings_field(
            'rmnb_debug_mode',
            __('Debug Mode', 'royal-mail-note-blocker'),
            array($this, 'debug_field_callback'),
            'rmnb_settings',
            'rmnb_main_section'
        );
        
        add_settings_field(
            'rmnb_disable_no_tracking_emails',
            __('Disable Completed Order Emails Without Tracking', 'royal-mail-note-blocker'),
            array($this, 'disable_no_tracking_field_callback'),
            'rmnb_settings',
            'rmnb_main_section'
        );
    }
    
    /**
     * Sanitize keywords array
     */
    public function sanitize_keywords($keywords) {
        if (is_string($keywords)) {
            $keywords = array_filter(array_map('trim', explode("\n", $keywords)));
        }
        return array_filter((array) $keywords);
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure which keywords will trigger email blocking for Royal Mail tracking information.', 'royal-mail-note-blocker') . '</p>';
    }
    
    /**
     * Keywords field callback
     */
    public function keywords_field_callback() {
        $keywords = get_option('rmnb_keywords', array(
            'despatched via Royal Mail',
            'tracking number is',
            'royalmail.com/portal/rm/track'
        ));
        ?>
        <textarea id="rmnb_keywords" name="rmnb_keywords" rows="10" cols="50" class="large-text code"><?php echo esc_textarea(implode("\n", $keywords)); ?></textarea>
        <p class="description">
            <?php echo __('Enter one keyword or phrase per line. Emails will be blocked when ALL of these keywords are found in a note.', 'royal-mail-note-blocker'); ?>
        </p>
        <?php
    }
    
    /**
     * Debug field callback
     */
    public function debug_field_callback() {
        $debug_mode = get_option('rmnb_debug_mode', false);
        ?>
        <input type="checkbox" id="rmnb_debug_mode" name="rmnb_debug_mode" value="1" <?php checked($debug_mode); ?> />
        <label for="rmnb_debug_mode"><?php echo __('Enable debug logging', 'royal-mail-note-blocker'); ?></label>
        <p class="description">
            <?php echo __('When enabled, blocked emails will be logged to the WordPress debug log.', 'royal-mail-note-blocker'); ?>
        </p>
        <?php
    }
    
    /**
     * Disable no tracking emails field callback
     */
    public function disable_no_tracking_field_callback() {
        $disable_emails = get_option('rmnb_disable_no_tracking_emails', false);
        ?>
        <input type="checkbox" id="rmnb_disable_no_tracking_emails" name="rmnb_disable_no_tracking_emails" value="1" <?php checked($disable_emails); ?> />
        <label for="rmnb_disable_no_tracking_emails"><?php echo __('Disable completed order emails when no tracking info is available', 'royal-mail-note-blocker'); ?></label>
        <p class="description">
            <?php echo __('When enabled and the Advanced Shipment Tracking plugin is active, completed order emails will be suppressed if no tracking information exists for the order.', 'royal-mail-note-blocker'); ?>
        </p>
        <?php
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo __('Royal Mail Note Blocker', 'royal-mail-note-blocker'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('rmnb_settings');
                do_settings_sections('rmnb_settings');
                submit_button();
                ?>
            </form>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php echo __('How it works', 'royal-mail-note-blocker'); ?></h2>
                <p><?php echo __('This plugin intercepts customer note emails and blocks them when ALL of the specified Royal Mail tracking keywords are found in the note content.', 'royal-mail-note-blocker'); ?></p>
                
                <h3><?php echo __('Default Keywords', 'royal-mail-note-blocker'); ?></h3>
                <ul>
                    <li>despatched via Royal Mail</li>
                    <li>tracking number is</li>
                    <li>royalmail.com/portal/rm/track</li>
                </ul>
                
                <h3><?php echo __('Plugin Location', 'royal-mail-note-blocker'); ?></h3>
                <p><?php echo __('This settings page can be found under: WooCommerce → Note Blocker', 'royal-mail-note-blocker'); ?></p>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new Royal_Mail_Note_Blocker();

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function() {
    // Set default options
    if (!get_option('rmnb_keywords')) {
        update_option('rmnb_keywords', array(
            'despatched via Royal Mail',
            'tracking number is',
            'royalmail.com/portal/rm/track'
        ));
    }
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Clean up if needed
});

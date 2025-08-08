<?php
/**
 * Plugin Name: Royal Mail Note Blocker
 * Plugin URI: https://github.com/Fermium/click-and-drop-note-blocker-wp
 * Description: Prevents Royal Mail tracking notes from being sent to customers as email notifications in WooCommerce.
 * Version: 0.2.0
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
define('RMNB_VERSION', '0.2.0');
define('RMNB_PLUGIN_FILE', __FILE__);
define('RMNB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RMNB_PLUGIN_URL', plugin_dir_url(__FILE__));

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
        
        // Add the filter to prevent Royal Mail note emails
        add_filter('woocommerce_new_order_note_data', array($this, 'prevent_royal_mail_note_emails'), 10, 2);
        
        // Add admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Prevent Royal Mail tracking notes from being sent to customers
     *
     * @param array $note_data The note data array
     * @param WC_Order $order The order object
     * @return array Modified note data
     */
    public function prevent_royal_mail_note_emails($note_data, $order) {
        // Get plugin settings
        $keywords = get_option('rmnb_keywords', array(
            'despatched via Royal Mail',
            'tracking number is',
            'royalmail.com/portal/rm/track'
        ));
        
        // Check if the note contains ALL Royal Mail tracking keywords
        $note_content = strtolower($note_data['comment_content']);
        $all_keywords_found = true;
        $found_keywords = array();
        
        foreach ($keywords as $keyword) {
            $keyword_trimmed = strtolower(trim($keyword));
            if (stripos($note_content, $keyword_trimmed) !== false) {
                $found_keywords[] = $keyword;
            } else {
                $all_keywords_found = false;
                break; // No need to check remaining keywords
            }
        }
        
        // Only block if ALL keywords are present
        if ($all_keywords_found && count($found_keywords) === count($keywords) && count($keywords) > 0) {
            // Set as private note (no customer email)
            $note_data['comment_agent'] = 'private';
            $note_data['comment_meta']['is_customer_note'] = 0;
            
            // Log the action if debug mode is enabled
            if (get_option('rmnb_debug_mode', false)) {
                error_log('Royal Mail Note Blocker: Blocked note for order #' . $order->get_id() . ' - All keywords found: ' . implode(', ', $found_keywords));
            }
        }
        
        return $note_data;
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
        // Add as submenu under WooCommerce instead of main Settings
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
        // Register settings
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
        echo '<p>' . __('Configure which keywords will trigger note blocking for Royal Mail tracking information.', 'royal-mail-note-blocker') . '</p>';
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
            <?php echo __('Enter one keyword or phrase per line. Notes containing ALL of these keywords will be blocked from customer emails.', 'royal-mail-note-blocker'); ?>
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
            <?php echo __('When enabled, blocked notes will be logged to the WordPress debug log.', 'royal-mail-note-blocker'); ?>
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
                <p><?php echo __('This plugin automatically prevents order notes containing ALL of the specified Royal Mail tracking keywords from being sent to customers via email. When a note is added to an order and contains ALL of the specified keywords, it will be marked as a private note instead of a customer note.', 'royal-mail-note-blocker'); ?></p>
                
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

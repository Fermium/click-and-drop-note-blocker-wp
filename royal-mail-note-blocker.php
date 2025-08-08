<?php
/**
 * Plugin Name: Royal Mail Note Blocker
 * Plugin URI: https://github.com/Fermium/click-and-drop-note-blocker-wp
 * Description: Prevents Royal Mail tracking notes from being sent to customers as email notifications in WooCommerce.
 * Version: 1.0.0
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
define('RMNB_VERSION', '1.0.0');
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
        
        // Check if the note contains Royal Mail tracking content
        $note_content = strtolower($note_data['comment_content']);
        
        foreach ($keywords as $keyword) {
            if (stripos($note_content, strtolower(trim($keyword))) !== false) {
                // Set as private note (no customer email)
                $note_data['comment_agent'] = 'private';
                $note_data['comment_meta']['is_customer_note'] = 0;
                
                // Log the action if debug mode is enabled
                if (get_option('rmnb_debug_mode', false)) {
                    error_log('Royal Mail Note Blocker: Blocked note for order #' . $order->get_id() . ' - Keyword: ' . $keyword);
                }
                
                break;
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
        add_options_page(
            __('Royal Mail Note Blocker', 'royal-mail-note-blocker'),
            __('Royal Mail Note Blocker', 'royal-mail-note-blocker'),
            'manage_options',
            'royal-mail-note-blocker',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('rmnb_settings', 'rmnb_keywords');
        register_setting('rmnb_settings', 'rmnb_debug_mode');
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            // Process form submission
            $keywords = array_filter(array_map('trim', explode("\n", $_POST['rmnb_keywords'])));
            update_option('rmnb_keywords', $keywords);
            update_option('rmnb_debug_mode', isset($_POST['rmnb_debug_mode']));
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'royal-mail-note-blocker') . '</p></div>';
        }
        
        $keywords = get_option('rmnb_keywords', array(
            'despatched via Royal Mail',
            'tracking number is',
            'royalmail.com/portal/rm/track'
        ));
        $debug_mode = get_option('rmnb_debug_mode', false);
        ?>
        <div class="wrap">
            <h1><?php echo __('Royal Mail Note Blocker Settings', 'royal-mail-note-blocker'); ?></h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="rmnb_keywords"><?php echo __('Keywords to Block', 'royal-mail-note-blocker'); ?></label>
                        </th>
                        <td>
                            <textarea id="rmnb_keywords" name="rmnb_keywords" rows="10" cols="50" class="large-text code"><?php echo esc_textarea(implode("\n", $keywords)); ?></textarea>
                            <p class="description">
                                <?php echo __('Enter one keyword or phrase per line. Notes containing any of these keywords will be blocked from customer emails.', 'royal-mail-note-blocker'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="rmnb_debug_mode"><?php echo __('Debug Mode', 'royal-mail-note-blocker'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="rmnb_debug_mode" name="rmnb_debug_mode" value="1" <?php checked($debug_mode); ?> />
                            <label for="rmnb_debug_mode"><?php echo __('Enable debug logging', 'royal-mail-note-blocker'); ?></label>
                            <p class="description">
                                <?php echo __('When enabled, blocked notes will be logged to the WordPress debug log.', 'royal-mail-note-blocker'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="card">
                <h2><?php echo __('How it works', 'royal-mail-note-blocker'); ?></h2>
                <p><?php echo __('This plugin automatically prevents order notes containing Royal Mail tracking information from being sent to customers via email. When a note is added to an order and contains any of the specified keywords, it will be marked as a private note instead of a customer note.', 'royal-mail-note-blocker'); ?></p>
                
                <h3><?php echo __('Default Keywords', 'royal-mail-note-blocker'); ?></h3>
                <ul>
                    <li>despatched via Royal Mail</li>
                    <li>tracking number is</li>
                    <li>royalmail.com/portal/rm/track</li>
                </ul>
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

<?php
/**
 * Plugin Name: Royal Mail Note Blocker
 * Plugin URI: https://github.com/Fermium/click-and-drop-note-blocker-wp
 * Description: Prevents Royal Mail tracking notes from being sent to customers as email notifications in WooCommerce.
 * Version: 0.3.0
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
define('RMNB_VERSION', '0.3.0');
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
        
        // Additional hook to catch notes that might slip through
        add_action('woocommerce_order_note_added', array($this, 'check_note_after_creation'), 10, 2);
        
        // Hook to prevent email notifications
        add_filter('woocommerce_email_enabled_customer_note', array($this, 'maybe_disable_customer_note_email'), 10, 2);
        
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
        // Debug logging
        if (get_option('rmnb_debug_mode', false)) {
            error_log('Royal Mail Note Blocker: Processing note for order #' . $order->get_id());
            error_log('Royal Mail Note Blocker: Note content: ' . $note_data['comment_content']);
        }
        
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
            // Set as private note (no customer email) - multiple approaches
            $note_data['comment_agent'] = 'private';
            $note_data['comment_approved'] = 0; // Mark as not approved
            
            // Ensure comment_meta exists
            if (!isset($note_data['comment_meta'])) {
                $note_data['comment_meta'] = array();
            }
            $note_data['comment_meta']['is_customer_note'] = 0;
            
            // Additional WooCommerce specific fields
            $note_data['comment_type'] = 'order_note';
            $note_data['comment_parent'] = 0;
            
            // Log the action if debug mode is enabled
            if (get_option('rmnb_debug_mode', false)) {
                error_log('Royal Mail Note Blocker: BLOCKED note for order #' . $order->get_id() . ' - All keywords found: ' . implode(', ', $found_keywords));
                error_log('Royal Mail Note Blocker: Note data after modification: ' . print_r($note_data, true));
            }
        }
        
        return $note_data;
    }
    
    /**
     * Additional check after note is created (backup method)
     * 
     * @param int $note_id The note ID
     * @param WC_Order $order The order object
     */
    public function check_note_after_creation($note_id, $order) {
        // Get the note
        $note = get_comment($note_id);
        if (!$note) {
            return;
        }
        
        // Get plugin settings
        $keywords = get_option('rmnb_keywords', array(
            'despatched via Royal Mail',
            'tracking number is',
            'royalmail.com/portal/rm/track'
        ));
        
        // Check if note contains any keywords
        $note_content = strtolower($note->comment_content);
        
        foreach ($keywords as $keyword) {
            $keyword_trimmed = strtolower(trim($keyword));
            if (stripos($note_content, $keyword_trimmed) !== false) {
                // Update the note to be private if it wasn't caught earlier
                if (get_comment_meta($note_id, 'is_customer_note', true) == 1) {
                    update_comment_meta($note_id, 'is_customer_note', 0);
                    
                    // Log the late catch
                    if (get_option('rmnb_debug_mode', false)) {
                        error_log('Royal Mail Note Blocker: Late catch - Updated note #' . $note_id . ' to private after creation');
                    }
                }
                break;
            }
        }
    }
    
    /**
     * Prevent customer note emails for blocked notes
     * 
     * @param bool $enabled Whether the email is enabled
     * @param WC_Order $order The order object
     * @return bool
     */
    public function maybe_disable_customer_note_email($enabled, $order) {
        if (!$enabled) {
            return false;
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
            if (get_option('rmnb_debug_mode', false)) {
                error_log('Royal Mail Note Blocker: Disabled customer note email for order #' . $order->get_id());
            }
            return false; // Disable the email
        }
        
        return $enabled;
    
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

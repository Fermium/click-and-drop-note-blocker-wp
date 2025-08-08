# Royal Mail Note Blocker - WordPress Plugin

A WordPress plugin that prevents Royal Mail tracking notes from being sent to customers as email notifications in WooCommerce, with advanced email management features.

## Description

This plugin provides comprehensive email management for WooCommerce orders, specifically targeting Royal Mail tracking communications. It can block order notes containing Royal Mail tracking information and optionally suppress completed order emails when no tracking information is available.

## Features

- **Royal Mail Note Blocking**: Automatically blocks order notes containing Royal Mail tracking keywords
- **Email Interception**: Direct email blocking before sending (more reliable than note modification)
- **AST Integration**: Suppress completed order emails when no tracking info exists (requires Advanced Shipment Tracking plugin)
- **Configurable Keywords**: Admin panel to customize blocked keywords with AND logic (all keywords must be present)
- **Debug Mode**: Optional logging for troubleshooting
- **WooCommerce Integration**: Seamlessly integrates with WooCommerce email system
- **Privacy Focused**: Prevents accidental sharing of tracking information

## Installation

1. Upload the plugin files to the `/wp-content/plugins/royal-mail-note-blocker/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the WooCommerce->Note Blocker screen to configure the plugin

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Default Blocked Keywords

The plugin comes pre-configured to block notes containing:
- "despatched via Royal Mail"
- "tracking number is"
- "royalmail.com/portal/rm/track"

## Configuration

After activation, you can customize the plugin settings:

1. Go to **WooCommerce** → **Note Blocker**
2. Edit the list of keywords to block (one per line)
3. Enable debug mode if needed for troubleshooting
4. **NEW**: Enable AST integration to suppress completed order emails without tracking
5. Save your changes

## Advanced Features

### AST (Advanced Shipment Tracking) Integration
When enabled, this feature works with the Advanced Shipment Tracking plugin to suppress completed order emails if no tracking information has been added to the order. This prevents customers from receiving completion emails for orders that haven't been shipped yet.

## How It Works

The plugin operates on two levels:

### 1. Email Interception (Primary Method)
Uses the `woocommerce_email_enabled` filter to block emails before they're sent when Royal Mail tracking content is detected in order notes.

### 2. AST Integration (Optional)
When enabled, uses the `woocommerce_email_enabled_customer_completed_order` filter to suppress completed order emails when no tracking items exist.
3. Optionally logs the action (if debug mode is enabled)

## Code Example

The core functionality is based on email interception:

```php
add_filter('woocommerce_email_enabled', array($this, 'maybe_block_email'), 10, 3);

public function maybe_block_email($enabled, $email_id, $order) {
    if (!$enabled || !$order) {
        return $enabled;
    }
    
    // Get all notes for this order
    $notes = wc_get_order_notes(array('order_id' => $order->get_id()));
    $keywords = get_option('rmnb_keywords', array());
    
    foreach ($notes as $note) {
        $all_keywords_found = true;
        foreach ($keywords as $keyword) {
            if (stripos($note->content, trim($keyword)) === false) {
                $all_keywords_found = false;
                break;
            }
        }
        
        if ($all_keywords_found) {
            return false; // Block the email
        }
    }
    
    return $enabled;
}
```

## Changelog

### 0.5.0
- Added AST (Advanced Shipment Tracking) integration
- Added option to suppress completed order emails without tracking
- Enhanced admin interface with new settings
- Improved reliability with direct email interception

### 0.4.0
- Simplified to direct email interception method
- Improved reliability and performance
- Enhanced debugging capabilities

### 0.3.0
- Changed logic to require ALL keywords (AND logic instead of OR)
- Improved keyword matching accuracy

### 0.2.0
- Added CI/CD pipeline
- Enhanced build process

### 0.1.0
- Initial release
- Basic keyword blocking functionality
- Admin settings panel
- Debug mode support

## Support

For support, please create an issue on the [GitHub repository](https://github.com/Fermium/click-and-drop-note-blocker-wp).

## License

This plugin is licensed under the GPL v2 or later.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
Wordpress plugin to intercept notes added by click&amp;drop and set them private before the email fires

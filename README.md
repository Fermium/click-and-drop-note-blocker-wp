# Royal Mail Note Blocker - WordPress Plugin

A WordPress plugin that prevents Royal Mail tracking notes from being sent to customers as email notifications in WooCommerce.

## Description

This plugin automatically blocks order notes containing Royal Mail tracking information from being sent to customers via email. When a note containing specific Royal Mail keywords is added to an order, it's automatically marked as a private note instead of a customer note.

## Features

- **Automatic Detection**: Scans order notes for Royal Mail tracking keywords
- **Configurable Keywords**: Admin panel to customize blocked keywords
- **Debug Mode**: Optional logging for troubleshooting
- **WooCommerce Integration**: Seamlessly integrates with WooCommerce order notes
- **Privacy Focused**: Prevents accidental sharing of tracking information

## Installation

1. Download the plugin files
2. Upload the `royal-mail-note-blocker.php` file to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings under 'Settings' → 'Royal Mail Note Blocker'

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

1. Go to **Settings** → **Royal Mail Note Blocker**
2. Edit the list of keywords to block (one per line)
3. Enable debug mode if needed for troubleshooting
4. Save your changes

## How It Works

The plugin uses the `woocommerce_new_order_note_data` filter to intercept order notes before they're saved. When a note contains any of the configured keywords, the plugin:

1. Sets the note as "private" (`comment_agent = 'private'`)
2. Marks it as not a customer note (`is_customer_note = 0`)
3. Optionally logs the action (if debug mode is enabled)

## Code Example

The core functionality is based on this filter:

```php
add_filter('woocommerce_new_order_note_data', 'prevent_royal_mail_note_emails', 10, 2);
function prevent_royal_mail_note_emails($note_data, $order) {
    // Check if the note contains Royal Mail tracking content
    if (stripos($note_data['comment_content'], 'despatched via Royal Mail') !== false || 
        stripos($note_data['comment_content'], 'tracking number is') !== false ||
        stripos($note_data['comment_content'], 'royalmail.com/portal/rm/track') !== false) {
        
        // Set as private note (no customer email)
        $note_data['comment_agent'] = 'private';
        $note_data['comment_meta']['is_customer_note'] = 0;
    }
    
    return $note_data;
}
```

## Changelog

### 1.0.0
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

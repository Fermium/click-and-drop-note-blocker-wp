=== Royal Mail Note Blocker ===
Contributors: fermium
Tags: woocommerce, royal mail, order notes, email, tracking
Requires at least: 5.0
Tested up to: 6.3
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Prevents Royal Mail tracking notes from being sent to customers as email notifications in WooCommerce.

== Description ==

Royal Mail Note Blocker is a lightweight WordPress plugin that automatically prevents order notes containing Royal Mail tracking information from being sent to customers via email. 

When a note containing specific Royal Mail keywords is added to a WooCommerce order, the plugin automatically marks it as a private note instead of a customer note, preventing the email notification from being sent.

= Features =

* Automatic detection of Royal Mail tracking notes
* Configurable keyword list through admin panel
* Debug mode for troubleshooting
* Seamless WooCommerce integration
* Privacy-focused approach

= Default Blocked Keywords =

* "despatched via Royal Mail"
* "tracking number is"
* "royalmail.com/portal/rm/track"

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/royal-mail-note-blocker/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->Royal Mail Note Blocker screen to configure the plugin

== Frequently Asked Questions ==

= Does this work with other shipping providers? =

This plugin is specifically designed for Royal Mail tracking notes, but you can customize the keywords to work with other shipping providers.

= Can I add my own keywords? =

Yes! Go to Settings -> Royal Mail Note Blocker to customize the list of blocked keywords.

= Will this affect all order notes? =

No, only notes containing the specified keywords will be blocked from customer emails. All other notes will work normally.

= Can I see which notes were blocked? =

Yes, enable debug mode in the settings to log blocked notes to the WordPress debug log.

== Screenshots ==

1. Admin settings page showing keyword configuration
2. Debug mode option for troubleshooting

== Changelog ==

= 0.1.0 =
* Initial release
* Basic keyword blocking functionality
* Admin settings panel
* Debug mode support

== Upgrade Notice ==

= 0.1.0 =
Initial release of Royal Mail Note Blocker plugin.

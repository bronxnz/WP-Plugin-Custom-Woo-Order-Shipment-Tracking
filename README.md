<h1>Custom Woo Order Shipment Tracking - WordPress Plugin</h1>
<br>
<strong>To add Carriers & Carrier Tracking Links:</strong><br>
Use the WordPress Admin Dashboard Menu Item: <code>Custom Tracking</code>

*****

<strong>Carrier Names & Tracking Link Data Stored in:</strong><br>
Database: <code>your wordpress database</code><br>
Table: <code>wp_options</code><br>
Option_Name: <code>custom_tracking_carriers</code>
<br><br>
<strong>Order Tracking Info Data Stored in:</strong><br>
Database: <code>your wordpress database</code><br>
Table: <code>wp_wc_orders_meta</code><br>
Option_Name: <code>_1a_carrier</code><br>
Option_Name: <code>_1b_tracking_number</code><br>
Option_Name: <code>_1c_tracking_link</code>

*****

<strong>YayMail Compatibility</strong><br>
This plugin is designed to be fully compatible with YayMail for WooCommerce. The tracking information is stored in WooCommerce Order meta using standardized keys, allowing YayMail to access and display the data in email templates.
<br><br>
<strong>Available order meta for YayMail templates:</strong><br>
<code>_1a_carrier</code> - The shipping carrier name<br>
<code>_1b_tracking_number</code> - The shipment tracking number<br>
<code>_1c_tracking_link</code> - The full tracking URL for the shipment
<br><br>
You can use these meta fields in YayMail templates with shortcodes or custom elements to display tracking information in your order emails.

*****

<strong>Need to delete ALL saved Carrier/Tracking Link/Tracking Number data from database?</strong>
<br><br>
Add the following script to <code>functions.php</code>, save changes, then load any wp-admin page.<br>
Comment it out/remove it to stop it from running with each wp-admin page load.
<br><br>
<code>add_action('init', 'reset_custom_order_tracking_data');
function reset_custom_order_tracking_data() {
    delete_option('custom_tracking_carriers');
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key IN ('_1a_carrier', '_1b_tracking_number', '_1c_tracking_link')");
}</code>

*****
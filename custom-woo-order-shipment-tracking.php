<?php
/*
Plugin Name: Custom Woo Order Shipment Tracking
Description: Add customised shipping Carriers, Tracking Links and Tracking Numbers to WooCommerce Orders, compatible with YayMail!
Version: 1.0
Author: Icecorp.
*/

// Add custom fields to order details page
add_action('woocommerce_admin_order_data_after_shipping_address', 'custom_tracking_shipping_fields_display_admin_order', 10, 1);

function custom_tracking_shipping_fields_display_admin_order($order){
    echo '<div class="order_custom_field" style="padding: 13px 15px 20px !important; background: #eee; border-radius: 10px; position: relative; width: fit-content; min-width: 200px; margin-left: -4px; display: grid;">';

    // Add an h3 header element
    echo '<h3 style="margin-top: 0px;">Tracking Information <a href="'.admin_url('admin.php?page=custom_tracking').'" style="position: absolute; top: 12px; right: 7px;"><span class="dashicons dashicons-admin-generic" style="text-decoration: none; color: #595959; font-size: 18px;"></span></a></h3>';

    // Get carriers, tracking numbers, and tracking links from the order
    $carriers = get_option('custom_tracking_carriers', array());
    $carrier_value = $order->get_meta('_1a_carrier', true);
    $tracking_number = $order->get_meta('_1b_tracking_number', true);
    $tracking_link = $order->get_meta('_1c_tracking_link', true);

    // Dropdown select list for carriers
    echo '<p style="color: #595959;"><strong style="margin-left: 3px;">Carrier:</strong><br>';
    echo '<select name="_1a_carrier" style="margin-top: 3px; width: 100%;">';
    echo '<option value="">Select Carrier</option>';

    // Separate carriers into two arrays: alphabetical and numerical
    $alphabetical_carriers = $numerical_carriers = array();
    foreach ($carriers as $carrier => $link) {
        // Check if the carrier name starts with a letter or a number
        if (ctype_alpha(substr($carrier, 0, 1))) {
            $alphabetical_carriers[$carrier] = $link;
        } else {
            $numerical_carriers[$carrier] = $link;
        }
    }

    // Sort carriers alphabetically
    ksort($alphabetical_carriers);
    // Sort carriers numerically
    uksort($numerical_carriers, 'strnatcmp');
    // Merge the sorted arrays
    $sorted_carriers = $alphabetical_carriers + $numerical_carriers;

    // Output the sorted carriers in the select box
    foreach ($sorted_carriers as $carrier => $link) {
        echo '<option value="' . esc_attr($carrier) . '" ' . selected($carrier_value, $carrier, false) . '>' . esc_html($carrier) . '</option>';
    }
    echo '</select>';

    // Tracking number input field
    echo '<style>.order_custom_field p.form-field._1b_tracking_number_field { width: 100% !important; }</style>';
    echo '<p style="margin: 0 0 -8px 0; color: #595959; width: 100%;"><strong style="margin-left: 3px;">Tracking Number:</strong><br>';
    woocommerce_wp_text_input(array(
        'id'          => '_1b_tracking_number',
        'placeholder' => __('Enter tracking number', 'woocommerce'),
        'value'       => $tracking_number, // Load the saved value
    ));

    // Tracking link display
    if (!empty($tracking_link)) {
        echo '<p style="display: none;"><a href="' . esc_url($tracking_link) . '" target="_blank">' . esc_html($tracking_link) . '</a></p>';
    }

    echo '</div>';
}

// Save custom fields using woocommerce_process_shop_order_meta hook
add_action('woocommerce_process_shop_order_meta', 'custom_tracking_save_fields', 10, 2);

function custom_tracking_save_fields($order_id, $post){
    $carrier = isset($_POST['_1a_carrier']) ? sanitize_text_field($_POST['_1a_carrier']) : '';
    $tracking_number = isset($_POST['_1b_tracking_number']) ? wp_kses($_POST['_1b_tracking_number'], array()) : '';

    // Retrieve the previous values of Carrier, Tracking Number, and Tracking Link from the order
    $order = wc_get_order($order_id);
    $prev_carrier = $order->get_meta('_1a_carrier', true);
    $prev_tracking_number = $order->get_meta('_1b_tracking_number', true);
    $prev_tracking_link = $order->get_meta('_1c_tracking_link', true);

    // Get the tracking link associated with the selected carrier
    $tracking_link = '';
    $carriers = get_option('custom_tracking_carriers', array());
    if (!empty($carrier) && isset($carriers[$carrier])) {
        $tracking_link = $carriers[$carrier];
    }

    // Check if both Carrier and Tracking Number fields are empty or set back to default
    $both_empty_or_default = empty($carrier) && empty($tracking_number);

    // Handle the case where both Carrier and Tracking Number are empty or set back to default
    if ($both_empty_or_default) {
        // Only add the note if there were previous values to remove and they haven't been removed already
        if (!empty($prev_carrier) || !empty($prev_tracking_number) || !empty($prev_tracking_link)) {
            // Clear the previous values of Carrier, Tracking Number, and Tracking Link
            $order->delete_meta_data('_1a_carrier');
            $order->delete_meta_data('_1b_tracking_number');
            $order->delete_meta_data('_1c_tracking_link');
            $order->save();

            // Add a note to the order notes box
            $order->add_order_note('All tracking information removed');
        }
    } else {
        // Check if there are changes to Carrier, Tracking Number, or Tracking Link
        $carrier_changed = $prev_carrier !== $carrier;
        $tracking_number_changed = $prev_tracking_number !== $tracking_number;
        $tracking_link_changed = $prev_tracking_link !== $tracking_link;

        // Update the values and add notes only if there are changes
        if ($carrier_changed || $tracking_number_changed || $tracking_link_changed) {
            // Clear Carrier if it's empty or set back to default
            if (empty($carrier)) {
                $order->delete_meta_data('_1a_carrier');
            } else {
                // Update the Carrier value
                $order->update_meta_data('_1a_carrier', $carrier);
            }

            // Clear Tracking Number if it's empty or set back to default
            if (empty($tracking_number)) {
                $order->delete_meta_data('_1b_tracking_number');
            } else {
                // Update the Tracking Number value
                $order->update_meta_data('_1b_tracking_number', $tracking_number);
            }

            // Update the Tracking Link value
            $order->update_meta_data('_1c_tracking_link', $tracking_link);

            // Save changes
            $order->save();

            // Add a note to the order notes box if either Carrier, Tracking Number, or Tracking Link is updated
            $carrier_note = empty($carrier) ? '[no carrier]' : $carrier;
            $tracking_note = empty($tracking_number) ? '[no tracking number]' : $tracking_number;
            $order->add_order_note(sprintf('Tracking information updated: %s - %s', $carrier_note, $tracking_note));
        }
    }
}


// Add admin menu for carrier settings
add_action('admin_menu', 'custom_tracking_admin_menu');

function custom_tracking_admin_menu() {
    add_menu_page(
        'Custom Tracking',
        'Custom Tracking',
        'manage_options',
        'custom_tracking',
        'custom_tracking_page_callback',
        'dashicons-airplane'
    );

    // Enqueue JavaScript for form validation
    add_action('admin_enqueue_scripts', 'custom_tracking_enqueue_scripts');
}

// Callback function for the admin page
function custom_tracking_page_callback() {
    if (isset($_POST['update_carrier'])) {
        $carriers = get_option('custom_tracking_carriers', array());
        $updated_carrier = isset($_POST['update_carrier']) ? sanitize_text_field($_POST['update_carrier']) : '';

        if (!empty($updated_carrier) && isset($_POST['carriers'][$updated_carrier]) && $_POST['carriers'][$updated_carrier] !== '') {
            $link_key = $updated_carrier . '_link';
            $link_value = isset($_POST['carriers'][$link_key]) ? wp_kses($_POST['carriers'][$link_key], array()) : '';

            // Check if both Carrier Name and Tracking Link are not empty
            if ($link_value !== '') {
                // Check if changes were made
                if ($carriers[$updated_carrier] !== $link_value || $updated_carrier !== $_POST['carriers'][$updated_carrier]) {
                    // Log the old and new tracking links
                    $old_tracking_link = isset($carriers[$updated_carrier]) ? $carriers[$updated_carrier] : '';
                    $new_tracking_link = $link_value;

                    // Update Carrier Name and its associated Tracking Link
                    $carriers[$updated_carrier] = $link_value; // Update the Tracking Link
                    $new_carrier_name = sanitize_text_field($_POST['carriers'][$updated_carrier]); // Get the updated Carrier Name
                    if ($new_carrier_name !== $updated_carrier) { // Check if the Carrier Name has changed
                        $carriers[$new_carrier_name] = $carriers[$updated_carrier]; // Add the updated Carrier Name with the same Tracking Link
                        unset($carriers[$updated_carrier]); // Remove the old Carrier Name
                    }

                    update_option('custom_tracking_carriers', $carriers);

                    // Log the update action
                    custom_tracking_log_action('update', $updated_carrier, $old_tracking_link, $new_carrier_name, $new_tracking_link);

                    // Display success message
                    echo '<div class="notice notice-success is-dismissible"><p>Successfully updated Carrier: ' . esc_html($updated_carrier) . '</p></div>';
                } else {
                    // Display error message if no changes were made
                    echo '<div class="notice notice-error"><p>No changes were made.</p></div>';
                }
            } else {
                // Display error message if Tracking Link is empty
                echo '<div class="notice notice-error"><p>You can\'t have an empty Tracking Link. No changes were saved.</p></div>';
            }
        } else {
            // Display error message if either Carrier Name is empty
            echo '<div class="notice notice-error"><p>You can\'t have an empty Carrier Name. No changes were saved.</p></div>';
        }
    } elseif (isset($_POST['delete_carrier'])) {
        // Delete a specific carrier
        $carrier_to_delete = sanitize_text_field($_POST['delete_carrier']);
        $carriers = get_option('custom_tracking_carriers', array());
        $tracking_link_deleted = isset($carriers[$carrier_to_delete]) ? $carriers[$carrier_to_delete] : '';
        unset($carriers[$carrier_to_delete]);
        update_option('custom_tracking_carriers', $carriers);

        // Log the delete action
        custom_tracking_log_action('delete', $carrier_to_delete, '', '', $tracking_link_deleted);

        // Display success message
        echo '<div class="notice notice-success is-dismissible"><p>Successfully deleted Carrier: ' . esc_html($carrier_to_delete) . '</p></div>';
    } elseif (isset($_POST['add_new_carrier'])) {
        // Add a new carrier and tracking link
        $new_carrier = sanitize_text_field($_POST['new_carrier']);
        $new_carrier_link = sanitize_text_field($_POST['new_carrier_link']);

        // Check if the new carrier name already exists
        $carriers = get_option('custom_tracking_carriers', array());
        if (array_key_exists($new_carrier, $carriers)) {
            // Display error message if the carrier name already exists
            echo '<div class="notice notice-error"><p>There\'s already a Carrier with that name: ' . esc_html($new_carrier) . '</p></div>';
        } else {
            if ($new_carrier !== '' && $new_carrier_link !== '') {
                $carriers[$new_carrier] = $new_carrier_link;
                update_option('custom_tracking_carriers', $carriers);

                // Log the add action
                custom_tracking_log_action('add', $new_carrier, '', '', $new_carrier_link);

                // Display success message
                echo '<div class="notice notice-success is-dismissible"><p>Successfully added new Carrier: ' . esc_html($new_carrier) . '</p></div>';
            } else {
                // Display error message if either Carrier Name or Tracking Link is empty
                echo '<div class="notice notice-error"><p>Please enter both a new Carrier Name and Tracking Link.</p></div>';
            }
        }
    } elseif (isset($_POST['clear_logs'])) {
        // Clear the log file
        $log_file = dirname(__FILE__) . '/custom_tracking_log.txt';
        if (file_exists($log_file)) {
            unlink($log_file);
            // Display success message
            echo '<div class="notice notice-success is-dismissible"><p>Successfully cleared the log.</p></div>';
        }
    }

    // Retrieve carriers and tracking links
    $carriers = get_option('custom_tracking_carriers', array());

    // Sort the carriers alphabetically in descending order
    ksort($carriers);
    ?>
    <div class="wrap">
        <h1 style="line-height: 1em; margin: 20px 0 0 0; font-weight: 800; letter-spacing: 0.5px; font-size: 23px; color: #000; text-transform: uppercase;">
            Custom Woo Order Shipment Tracking</h1>
        <form method="post" action="">
            <?php wp_nonce_field('custom_tracking_carriers_nonce', 'custom_tracking_carriers_nonce'); ?>
            <div style="display: flex; flex-direction: column; width: fit-content;">
                <div style="flex: 1; background: #e3e3e3; border-radius: 10px; padding: 15px 16px 20px 16px; margin: 30px 0 0 0;">
                <h2 style="margin: 0 0 -10px;">Update Existing Carriers</h2>
                <table class="form-table" style="width: auto;">
                    <tr valign="top">
                        <th scope="row" style="padding: 20px 0 0 3px; display: table-cell !important;">Carrier Name</th>
                        <th scope="row" style="padding: 20px 0 0 3px; display: table-cell !important;">Tracking Link</th>
                    </tr>
                    <?php 
                    if(empty($carriers)) {
                        echo '<tr valign="top"><td colspan="3" style="margin: 0; padding: 15px 0 10px 0;"><p style="font-size: 12.5px; color: #919191; padding: 5px; background: #dad9d9; margin: 5px 0 0 0; font-style: italic; text-align: center;">No saved carrier information...</p></td></tr>';
                    } else {
                        foreach ($carriers as $carrier => $link) { ?>
                            <tr valign="top">
                                <td style="padding: 10px 0 0 0; display: table-cell !important;"><input type="text" name="carriers[<?php echo esc_attr($carrier); ?>]" value="<?php echo esc_attr($carrier); ?>" style="width: -webkit-fill-available; margin-right: 10px;" /></td>
                                <td style="padding: 10px 0 0 0; display: table-cell !important;"><input type="text" name="carriers[<?php echo esc_attr($carrier); ?>_link]" value="<?php echo esc_attr($link); ?>" placeholder="(exclude &quot;https://&quot;)" style="width: -webkit-fill-available; margin-right: 10px;" /></td>
                                <td style="padding: 10px 0 0 0; display: table-cell !important;">
                                    <button type="submit" name="update_carrier" value="<?php echo esc_attr($carrier); ?>" class="button button-primary">Update</button>
                                    <button type="submit" name="delete_carrier" value="<?php echo esc_attr($carrier); ?>" class="button button-secondary" style="color: #fff !important; border-color: #f40000 !important; background: #f40000 !important;" onclick="return confirmDelete('<?php echo esc_js($carrier); ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php }
                    } ?>
                </table>
                </div>
    
                <div style="flex: 1; background: #e3e3e3; border-radius: 10px; padding: 15px 16px 13px 16px; margin: 30px 0 0 0;">
                <h2 style="margin: 0 0 -10px;">Add New Carrier</h2>
                <table class="form-table" style="width: auto;">
                    <tr valign="top">
                        <th scope="row" style="padding: 20px 0 0 3px; display: table-cell !important;">Carrier Name</th>
                        <th scope="row" style="padding: 20px 0 0 3px; display: table-cell !important;">Tracking Link</th>
                    </tr>
                    <tr valign="top">
                        <td style="padding: 10px 0 0 0;vertical-align: top; display: table-cell !important;"><input type="text" name="new_carrier" style="width: -webkit-fill-available; margin-right: 10px;" /></td>
                        <td style="padding: 10px 0 0 0;vertical-align: top; display: table-cell !important;"><input type="text" name="new_carrier_link" placeholder="(exclude &quot;https://&quot;)" style="width: -webkit-fill-available; margin-right: 10px;" />
                        <td style="padding: 10px 0 0 0;vertical-align: top; display: table-cell !important;">
                            <button type="button" class="button button-secondary" onclick="testTrackingLink()">Test Tracking Link</button>                      
                        </td>
                    </tr>
                </table>
                <p style="margin: 20px 0 7px 0;"><input type="submit" name="add_new_carrier" class="button button-primary" value="Add New Carrier"></p>
            </div>
        </form>
    </div>
</div>
    <?php
    // Display the log section
    custom_tracking_display_log();
}

// Function to display the log section
function custom_tracking_display_log() {
    $log_content = '';
    $log_file = dirname(__FILE__) . '/custom_tracking_log.txt';
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
    }
    ?>
    <button type="button" id="toggle-log" class="button button-secondary" style="margin: 30px 0 0 2px;">Show Log</button>
    <div id="log-section" style="margin: 18px 0 0 3px; display: none;">
        <pre style="font-size: 11.5px; margin-left: 2px; margin-right: 13px; margin-bottom: 0px !important; overflow-x: scroll; padding-bottom: 13px;"><?php echo esc_html($log_content); ?></pre>
        <form method="post" action="" onsubmit="return confirm('Are you sure you want to clear the log for this plugin?');" style="margin-top: -15px;">
            <?php wp_nonce_field('custom_tracking_clear_logs_nonce', 'custom_tracking_clear_logs_nonce'); ?>
            <?php if (!empty($log_content)) : ?>
                <p style="margin: 20px 0 7px 0;"><input type="submit" name="clear_logs" class="button button-primary" value="Clear Log" style="color: #fff !important; border-color: #f40000 !important; background: #f40000 !important;"></p>
            <?php endif; ?>
        </form>
    </div>
    <script>
        document.getElementById('toggle-log').addEventListener('click', function() {
            var logSection = document.getElementById('log-section');
            if (logSection.style.display === 'none') {
                logSection.style.display = 'block';
                document.getElementById('toggle-log').textContent = 'Hide Log';
            } else {
                logSection.style.display = 'none';
                document.getElementById('toggle-log').textContent = 'Show Log';
            }
        });
    </script>
    <?php
}

// Enqueue JavaScript for form validation and custom functionality
function custom_tracking_enqueue_scripts() {
    ?>
    <script>
        // Function to prompt confirmation before deletion
        function confirmDelete(carrierName) {
            if (confirm('Are you sure you want to delete the Carrier "' + carrierName + '"?')) {
                return true;
            }
            return false;
        }

        // Function to handle clicking the "Test Tracking Link" button
        function testTrackingLink() {
            // Get the value of the new_carrier_link input box
            var carrierLink = document.querySelector('input[name="new_carrier_link"]').value.trim();
            // Check if the carrier link is not empty
            if (carrierLink !== '') {
                // Add "https://" to the beginning of the carrier link
                carrierLink = 'https://' + carrierLink + 'ABC1234567890';
                // Open the carrier link in a new tab
                window.open(carrierLink, '_blank');
            } else {
                // Display an alert if the carrier link is empty
                alert('Please enter a tracking link.');
            }
        }
    </script>
    <?php
}

// Function to log actions
function custom_tracking_log_action($action, $carrier_name, $old_tracking_link, $new_carrier_name, $new_tracking_link) {
    date_default_timezone_set(get_option('timezone_string'));
    $timestamp = date('d-m-Y H:i:s');
    $log_entry = "$timestamp - ";

    switch ($action) {
        case 'add':
            $log_entry .= "Added Carrier: $carrier_name, Tracking Link: $new_tracking_link";
            break;
        case 'update':
            $log_entry .= "Updated Carrier: $carrier_name >>> $new_carrier_name, Tracking Link: $old_tracking_link >>> $new_tracking_link";
            break;
        case 'delete':
            $log_entry .= "Deleted Carrier: $carrier_name, Tracking Link: $new_tracking_link";
            break;
        default:
            $log_entry .= "Unknown action: $action";
            break;
    }

    $log_entry .= "\n";

    $log_file = dirname(__FILE__) . '/custom_tracking_log.txt';
    $current_content = file_get_contents($log_file);
    file_put_contents($log_file, $log_entry . $current_content);
}

?>

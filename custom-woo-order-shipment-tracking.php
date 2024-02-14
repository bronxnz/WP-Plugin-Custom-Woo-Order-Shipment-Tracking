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
    echo '<div class="order_custom_field" style="padding: 12px 15px 60px !important; background: #eee; border-radius: 10px; position: relative; width: fit-content; min-width: 200px; margin-left: -4px;">';

    // Add an h3 header element
    echo '<h3 style="margin-top: 0px;">Tracking Information <a href="'.admin_url('admin.php?page=custom_tracking').'" style="position: absolute; top: 12px; right: 7px;"><span class="dashicons dashicons-admin-generic" style="text-decoration: none; color: #595959; font-size: 18px;"></span></a></h3>';

    // Get carriers and tracking links from the options
    $carriers = get_option('custom_tracking_carriers', array());
    $carrier_value = $order->get_meta('_1a_carrier', true);
    $tracking_number = $order->get_meta('_1b_tracking_number', true);

    // Dropdown select list for carriers
    echo '<p style="color: #595959;"><strong style="margin-left: 3px;">Carrier:</strong><br>';
    echo '<select name="_1a_carrier" style="margin-top: 3px; width: 100%;">';
    echo '<option value="">Select Carrier</option>';
    foreach ($carriers as $carrier => $link) {
        echo '<option value="' . esc_attr($carrier) . '" ' . selected($carrier_value, $carrier, false) . '>' . esc_html($carrier) . '</option>';
    }
    echo '</select>';

    // Tracking number input field
    echo '<style>.order_custom_field p.form-field._1b_tracking_number_field { width: 100% !important; }</style>';
    echo '<p style="margin: 1em 0 -8px 0; color: #595959; width: 100%;"><strong style="margin-left: 3px;">Tracking Number:</strong><br>';
    woocommerce_wp_text_input(array(
        'id'          => '_1b_tracking_number',
        'placeholder' => __('Enter tracking number', 'woocommerce'),
        'value'       => $tracking_number, // Load the saved value
    ));

    echo '</div>';
}

// Save custom fields using woocommerce_process_shop_order_meta hook
add_action('woocommerce_process_shop_order_meta', 'custom_tracking_save_fields', 10, 2);

function custom_tracking_save_fields($order_id, $post){
    $carrier = isset($_POST['_1a_carrier']) ? sanitize_text_field($_POST['_1a_carrier']) : '';
    $tracking_number = isset($_POST['_1b_tracking_number']) ? wp_kses($_POST['_1b_tracking_number'], array()) : '';

    // Retrieve the previous values of Carrier and Tracking Number from the order
    $order = wc_get_order($order_id);
    $prev_carrier = $order->get_meta('_1a_carrier', true);
    $prev_tracking_number = $order->get_meta('_1b_tracking_number', true);

    // Check if both Carrier and Tracking Number fields are empty or set back to default
    $both_empty_or_default = empty($carrier) && empty($tracking_number);

    // Handle the case where both Carrier and Tracking Number are empty or set back to default
    if ($both_empty_or_default) {
        // Only add the note if there were previous values to remove and they haven't been removed already
        if (!empty($prev_carrier) || !empty($prev_tracking_number)) {
            // Clear the previous values of Carrier and Tracking Number
            $order->delete_meta_data('_1a_carrier');
            $order->delete_meta_data('_1b_tracking_number');
            $order->save();

            // Add a note to the order notes box
            $order->add_order_note('All tracking information removed');
        }
    } else {
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

        // Save changes
        $order->save();

        // Add a note to the order notes box if either Carrier or Tracking Number is updated
        $carrier_note = empty($carrier) ? '[no carrier]' : $carrier;
        $tracking_note = empty($tracking_number) ? '[no tracking number]' : $tracking_number;
        $order->add_order_note(sprintf('Tracking information updated: %s - %s', $carrier_note, $tracking_note));
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
                    // Update Carrier Name and its associated Tracking Link
                    $carriers[$updated_carrier] = $link_value; // Update the Tracking Link
                    $new_carrier_name = sanitize_text_field($_POST['carriers'][$updated_carrier]); // Get the updated Carrier Name
                    if ($new_carrier_name !== $updated_carrier) { // Check if the Carrier Name has changed
                        $carriers[$new_carrier_name] = $carriers[$updated_carrier]; // Add the updated Carrier Name with the same Tracking Link
                        unset($carriers[$updated_carrier]); // Remove the old Carrier Name
                    }

                    update_option('custom_tracking_carriers', $carriers);

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
        unset($carriers[$carrier_to_delete]);
        update_option('custom_tracking_carriers', $carriers);

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

                // Display success message
                echo '<div class="notice notice-success is-dismissible"><p>Successfully added new Carrier: ' . esc_html($new_carrier) . '</p></div>';
            } else {
                // Display error message if either Carrier Name or Tracking Link is empty
                echo '<div class="notice notice-error"><p>Please enter both a new Carrier Name and Tracking Link.</p></div>';
            }
        }
    }

    // Retrieve carriers and tracking links
    $carriers = get_option('custom_tracking_carriers', array());
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
                        <th scope="row" style="padding: 20px 0 0 3px;">Carrier Name</th>
                        <th scope="row" style="padding: 20px 0 0 3px;">Tracking Link</th>
                    </tr>
                    <?php 
                    if(empty($carriers)) {
                        echo '<tr valign="top"><td colspan="3" style="font-size: 13px; color: #8a8a8a; padding: 20px 0 10px 5px;">No saved Carriers.</td></tr>';
                    } else {
                        foreach ($carriers as $carrier => $link) { ?>
                            <tr valign="top">
                                <td style="padding: 10px 0 0 0;"><input type="text" name="carriers[<?php echo esc_attr($carrier); ?>]" value="<?php echo esc_attr($carrier); ?>" /></td>
                                <td style="padding: 10px 0 0 0;"><input type="text" name="carriers[<?php echo esc_attr($carrier); ?>_link]" value="<?php echo esc_attr($link); ?>" placeholder="(exclude &quot;https://&quot;)" /></td>
                                <td style="padding: 10px 0 0 0;">
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
                        <th scope="row" style="padding: 20px 0 0 3px;">Carrier Name</th>
                        <th scope="row" style="padding: 20px 0 0 3px;">Tracking Link</th>
                    </tr>
                    <tr valign="top">
                        <td style="padding: 10px 0 0 0;vertical-align: top;"><input type="text" name="new_carrier" /></td>
                        <td style="padding: 10px 0 0 0;vertical-align: top;"><input type="text" name="new_carrier_link" placeholder="(exclude &quot;https://&quot;)" />
                    </tr>
                </table>
                <p style="margin: 20px 0 7px 0;"><input type="submit" name="add_new_carrier" class="button button-primary" value="Add New Carrier"></p>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// Enqueue JavaScript for form validation
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
    </script>
    <?php
}
?>
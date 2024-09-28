<?php
/**
 * Plugin Name: Petty Auto Trademark Symbol Adder
 * Description: Automatically adds trademark, registered, or copyright symbols to specified terms.
 * Version: 1.0
 * Author: Stingray82
 */

// Activation hook to prepopulate default terms and symbols
register_activation_hook(__FILE__, 'pats_activate');
function pats_activate() {
    $default_terms = [
        'WordPress'        => '&#174;',  // Registered symbol for WordPress®
        'Woo'              => '&#174;',  // Registered symbol for Woo®
        'WooCommerce'      => '&#174;',  // Registered symbol for WooCommerce®
        'WooExpert'        => '&#174;',  // Registered symbol for WooExpert®
        'Woo Partner'      => '&#8482;', // Trademark symbol for Woo Partner™
        'WooPay'           => '&#8482;', // Trademark symbol for WooPay™
        'WooPayments'      => '&#8482;', // Trademark symbol for WooPayments™
        'WooThemes'        => '&#174;',  // Registered symbol for WooThemes®
        'Hosted Woo'       => '&#8482;', // Trademark symbol for Hosted Woo™
        'Managed Woo'      => '&#8482;', // Trademark symbol for Managed Woo™
        'Managed WordPress'=> '&#8482;'  // Trademark symbol for Managed WordPress™
    ];

    // Get the current terms from the database
    $existing_terms = get_option('pats_terms', []);

    // Merge existing terms with new default terms (existing terms won't be overwritten)
    $updated_terms = array_merge($existing_terms, $default_terms);

    // Update the terms in the database
    update_option('pats_terms', $updated_terms);
}

// Filter the content and title
add_filter('the_content', 'pats_replace_terms_in_content');
add_filter('the_title', 'pats_replace_terms_in_title');

// Bricks Builder: Hook into Bricks' `bricks/frontend/render_data` filter to modify content
add_filter('bricks/frontend/render_data', 'pats_bricks_render_data', 10, 3);

// Function to replace terms in Bricks content via `bricks/frontend/render_data`
function pats_bricks_render_data($content, $post, $area) {
    return pats_replace_terms($content);
}

// Function to replace terms in the content
function pats_replace_terms_in_content($content) {
    return pats_replace_terms($content);
}

// Function to replace terms in the title
function pats_replace_terms_in_title($title) {
    return pats_replace_terms($title);
}

// Main function to replace terms in the content using word boundaries and placeholders
function pats_replace_terms($text) {
    // Get the terms from the database
    $terms = pats_load_terms();

    // Sort terms by length in descending order to ensure longer terms are replaced first
    uksort($terms, function($a, $b) {
        return strlen($b) - strlen($a); // Sort longest terms first
    });

    // Placeholder replacements array to avoid conflicts
    $placeholders = [];
    $index = 0;

    // First pass: Replace terms with placeholders
    foreach ($terms as $term => $symbol) {
        if (!empty($term)) {
            // Create a unique placeholder for each term
            $placeholder = "##PLACEHOLDER_" . $index . "##";
            $placeholders[$placeholder] = $term . html_entity_decode($symbol);

            // Use preg_replace with word boundaries to replace the term with the placeholder
            $text = preg_replace(
                '/\b' . preg_quote($term, '/') . '\b/i',
                $placeholder,
                $text
            );

            $index++;
        }
    }

    // Second pass: Replace placeholders with the actual terms and symbols
    foreach ($placeholders as $placeholder => $replacement) {
        $text = str_replace($placeholder, $replacement, $text);
    }

    return $text;
}

// Load terms from the options table
function pats_load_terms() {
    static $cached_terms = null;

    if (is_null($cached_terms)) {
        $cached_terms = get_option('pats_terms', []); // Fetch the terms stored in the database
    }

    return $cached_terms;
}

// Register the admin page and settings
add_action('admin_menu', 'pats_register_settings_page');
function pats_register_settings_page() {
    add_options_page(
        'Petty Auto Symbol Settings', 
        'Petty Auto Symbols', 
        'manage_options', 
        'petty-auto-symbol-settings', 
        'pats_settings_page_html'
    );
}

// Register settings for terms and post type exclusions
add_action('admin_init', 'pats_register_settings');
function pats_register_settings() {
    register_setting('pats_settings_group', 'pats_terms', 'pats_sanitize_terms');
    register_setting('pats_settings_group', 'pats_excluded_post_types');
}

// Sanitize terms and store them as ASCII codes
function pats_sanitize_terms($input) {
    $cleaned_terms = [];

    if (isset($input['terms']) && isset($input['symbols'])) {
        foreach ($input['terms'] as $term => $value) {
            if (!empty($value)) {
                // Store the symbol as its ASCII code
                $ascii_symbol = htmlspecialchars_decode($input['symbols'][$term]);
                $cleaned_terms[sanitize_text_field($value)] = $ascii_symbol;
            }
        }
    }

    return $cleaned_terms;
}

// The HTML form for managing terms
function pats_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Petty Auto Trademark Symbol Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('pats_settings_group');
            do_settings_sections('pats_settings_group');
            
            // Get the current terms from the database
            $terms = get_option('pats_terms', []);
            ?>
            <table class="form-table" id="terms-table">
                <thead>
                    <tr>
                        <th>Term</th>
                        <th>Symbol (ASCII Code)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($terms)): ?>
                        <?php foreach ($terms as $term => $symbol): ?>
                            <tr>
                                <td><input type="text" name="pats_terms[terms][<?php echo esc_attr($term); ?>]" value="<?php echo esc_attr($term); ?>" /></td>
                                <td>
                                    <select name="pats_terms[symbols][<?php echo esc_attr($term); ?>]">
                                        <option value="&#174;" <?php selected($symbol, '&#174;'); ?>>®</option>
                                        <option value="&#169;" <?php selected($symbol, '&#169;'); ?>>©</option>
                                        <option value="&#8482;" <?php selected($symbol, '&#8482;'); ?>>™</option>
                                    </select>
                                </td>
                                <td><button class="button button-secondary pats-remove-row">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="button" class="button button-primary pats-add-row">Add Term</button>
            <?php submit_button(); ?>
        </form>
    </div>

    <script>
    document.querySelector('.pats-add-row').addEventListener('click', function() {
        var table = document.getElementById('terms-table').getElementsByTagName('tbody')[0];
        var rowCount = table.rows.length;

        // Add a new row for term and symbol
        var newRow = table.insertRow();
        newRow.innerHTML = '<td><input type="text" name="pats_terms[terms][newterm' + rowCount + ']" value="" /></td>' +
                           '<td><select name="pats_terms[symbols][newterm' + rowCount + ']">' +
                           '<option value="&#174;">®</option>' +
                           '<option value="&#169;">©</option>' +
                           '<option value="&#8482;">™</option>' +
                           '</select></td>' +
                           '<td><button class="button button-secondary pats-remove-row">Remove</button></td>';
    });

    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('pats-remove-row')) {
            event.preventDefault();
            event.target.closest('tr').remove();
        }
    });
    </script>
    <?php
}

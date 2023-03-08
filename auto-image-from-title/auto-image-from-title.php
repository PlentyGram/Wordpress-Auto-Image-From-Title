<?php
/**
 * Plugin Name: Auto Image From Title
 * Plugin URI: https://www.plentygram.com/blog/wordpress-auto-image-from-title/
 * Description: Automatically inserts an image from the post title using DuckDuckGo image search API.
 * Version: 1.0.0
 * Author: PlentyGram
 * Author URI: https://www.plentygram.com/
 */

// Add settings page to the WordPress admin menu
add_action('admin_menu', 'plentygram_auto_image_settings_page');
function plentygram_auto_image_settings_page() {
    add_menu_page(
        esc_html__('Auto Image Settings', 'auto-image-settings'),
        esc_html__('Auto Image Settings', 'auto-image-settings'),
        'manage_options',
        'auto-image-settings',
        'plentygram_auto_image_settings_page_callback'
    );
}

// Settings page callback function
function plentygram_auto_image_settings_page_callback() {
    // Save CORS Proxy Link input value after sanitizing and validating
    if (isset($_POST['auto_image_cors_proxy_link'])) {
        $auto_image_cors_proxy_link = sanitize_text_field($_POST['auto_image_cors_proxy_link']);
        update_option('auto_image_cors_proxy_link', $auto_image_cors_proxy_link);
    }
    // Save selected categories after validating and sanitizing
    if (isset($_POST['auto_image_categories'])) {
        $auto_image_categories = array_map('intval', $_POST['auto_image_categories']);
        update_option('auto_image_categories', $auto_image_categories);
    }

    // Get saved options
    $cors_proxy_link = get_option('auto_image_cors_proxy_link');
    $categories = get_option('auto_image_categories');

    // Output settings form with escaped and validated data
    ?>
    <div class="wrap">
    <h2><?php esc_html_e('Auto Image From Title Settings', 'auto-image-settings');?></h2>
    <form method="post" action="">
        <p>
            <label for="auto_image_cors_proxy_link"><?php esc_html_e('CORS Proxy Link:', 'auto-image-settings');?></label><br>
            <input type="text" name="auto_image_cors_proxy_link" id="auto_image_cors_proxy_link" value="<?php echo esc_attr($cors_proxy_link); ?>" size="50"><br>
            <small><?php esc_html_e('Enter the URL of the CORS Anywhere proxy or', 'auto-image-settings');?> <a href="https://www.plentygram.com/blog/create-a-free-cors-proxy-server-on-cloudflare/"> <?php esc_html_e('make your own server for free click here', 'auto-image-settings');?></a>.</small>
        </p>
        <p>
            <label for="auto_image_categories"><?php esc_html_e('Categories:', 'auto-image-settings');?></label><br>
            <?php
            // Get all categories
            $all_categories = get_categories();
            // Output checkboxes for each category with escaped and validated data
            foreach ($all_categories as $category) {
                $checked = (in_array($category->term_id, $categories)) ? 'checked' : '';
                $name = esc_attr('auto_image_categories[]');
                $value = esc_attr($category->term_id);
                $id = esc_attr('auto_image_category_' . $category->term_id);
                echo '<input type="checkbox" name="'. $name .'" value="'. $value .'" id="'. $id .'" '. $checked .'> '. esc_html($category->name) .'<br>';
            }
            ?>
            <input type="checkbox" name="auto_image_categories[]" value="all" <?php echo (in_array('all', $categories)) ? 'checked' : ''; ?>> <?php esc_html_e('All Categories', 'auto-image-settings');?><br>
            <small><?php esc_html_e('Select the categories to apply the plugin to or select "All Categories" to apply it to all categories.', 'auto-image-settings');?></small>
        </p>
        <?php submit_button(); ?>
    </form>
</div>
    <?php
}

// Add filter to insert image before content
add_filter( 'the_content', 'plentygram_insert_img_before_content' );
function plentygram_insert_img_before_content( $content ) {
    global $post;

    // Get selected categories
    $categories = get_option( 'auto_image_categories' );

    // Check if post belongs to selected categories
    if ( in_array( 'all', $categories ) || has_category( $categories, $post->ID ) ) {
        $title = $post->post_title;

        // Retrieve the CORS proxy link and sanitize it
        $cors_proxy_link = esc_url_raw( get_option( 'auto_image_cors_proxy_link' ) );

        // Append a trailing slash to the CORS proxy link if it doesn't have one
        if ( substr( $cors_proxy_link, -1 ) !== '/' ) {
            $modified_link = $cors_proxy_link . '/';
        } else {
            $modified_link = $cors_proxy_link;
        }

        // Retrieve the vqd hash
        $search_url = $modified_link . 'https://duckduckgo.com/?q=' . urlencode( $title ) . '&iar=images&iax=images&ia=images';
        $vqd = '';
        $img_url = '';
        $response = wp_remote_get( $search_url );

        if ( ! is_wp_error( $response ) ) {
            $data = wp_remote_retrieve_body( $response );
            preg_match( "/vqd='(.*?)'/", $data, $matches );
            $vqd = $matches[1];

            // Retrieve the image URL
            $img_search_url = $modified_link . 'https://duckduckgo.com/i.js?l=wt-wt&o=json&q=' . urlencode( $title ) . '&vqd=' . $vqd . '&f=,,,,,&p=1';
            $img_response = wp_remote_get( $img_search_url );

            if ( ! is_wp_error( $img_response ) ) {
                $img_data = json_decode( wp_remote_retrieve_body( $img_response ), true );
                $img_url = $img_data['results'][0]['image'];
                $img_tag = '<center><img width="90%" src="' . esc_url( $modified_link . $img_url ) . '" alt="' . esc_attr( $title ) . '"></center>';
                $content = $img_tag . $content;
            }
        }
    }

    return $content;
}
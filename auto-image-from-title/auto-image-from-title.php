<?php
/**
 * Plugin Name: Auto Image From Title
 * Plugin URI: https://www.plentygram.com/
 * Description: Automatically inserts an image from the post title using DuckDuckGo image search API.
 * Version: 1.0.0
 * Author: PlentyGram
 * Author URI: https://www.plentygram.com/
 */

// Add settings page to the WordPress admin menu
add_action('admin_menu', 'auto_image_settings_page');
function auto_image_settings_page() {
    add_menu_page(
        'Auto Image Settings',
        'Auto Image Settings',
        'manage_options',
        'auto-image-settings',
        'auto_image_settings_page_callback'
    );
}

// Settings page callback function
function auto_image_settings_page_callback() {
    // Save CORS Proxy Link input value
    if (isset($_POST['auto_image_cors_proxy_link'])) {
        update_option('auto_image_cors_proxy_link', $_POST['auto_image_cors_proxy_link']);
    }
    // Save selected categories
    if (isset($_POST['auto_image_categories'])) {
        update_option('auto_image_categories', $_POST['auto_image_categories']);
    }

    // Get saved options
    $cors_proxy_link = get_option('auto_image_cors_proxy_link');
    $categories = get_option('auto_image_categories');

    // Output settings form
    ?>
    <div class="wrap">
        <h2>Auto Image From Title Settings</h2>
        <form method="post" action="">
            <p>
                <label for="auto_image_cors_proxy_link">CORS Proxy Link:</label><br>
                <input type="text" name="auto_image_cors_proxy_link" id="auto_image_cors_proxy_link" value="<?php echo esc_attr($cors_proxy_link); ?>" size="50"><br>
                <small>Enter the URL of the CORS Anywhere proxy or <a href="https://www.plentygram.com/blog/create-a-free-cors-proxy-server-on-cloudflare/">make your own click here</a>.</small>
            </p>
            <p>
                <label for="auto_image_categories">Categories:</label><br>
                <?php
                // Get all categories
                $all_categories = get_categories();
                // Output checkboxes for each category
                foreach ($all_categories as $category) {
                    $checked = (in_array($category->term_id, $categories)) ? 'checked' : '';
                    echo '<input type="checkbox" name="auto_image_categories[]" value="' . $category->term_id . '" ' . $checked . '> ' . $category->name . '<br>';
                }
                ?>
                <input type="checkbox" name="auto_image_categories[]" value="all" <?php echo (in_array('all', $categories)) ? 'checked' : ''; ?>> All Categories<br>
                <small>Select the categories to apply the plugin to or select "All Categories" to apply it to all categories.</small>
            </p>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Add filter to insert image before content
add_filter('the_content', 'insert_img_before_content');
function insert_img_before_content($content) {
    global $post;
    // Get selected categories
    $categories = get_option('auto_image_categories');
    // Check if post belongs to selected categories
    if (in_array('all', $categories) || has_category($categories, $post->ID)) {
        $title = $post->post_title;
		// Retrieve the CORS proxy link
        $cors_proxy_link = get_option('auto_image_cors_proxy_link');
if (substr($cors_proxy_link, -1) !== '/') {
    $modified_link = $cors_proxy_link . '/';
} else {
    $modified_link = $cors_proxy_link;
}
        // Retrieve the vqd hash
        $search_url = $modified_link . "https://duckduckgo.com/?q=" . urlencode($title) . "&iar=images&iax=images&ia=images";
        $vqd = '';
        $img_url = '';
        $response = wp_remote_get($search_url);
        if(!is_wp_error($response)){
            $data = wp_remote_retrieve_body($response);
            preg_match("/vqd='(.*?)'/", $data, $matches);
            $vqd = $matches[1];
            // Retrieve the image URL
            $img_search_url = $modified_link . "https://duckduckgo.com/i.js?l=wt-wt&o=json&q=" . urlencode($title) . "&vqd=" . $vqd . "&f=,,,,,&p=1";
            $img_response = wp_remote_get($img_search_url);
            if(!is_wp_error($img_response)){
                $img_data = json_decode(wp_remote_retrieve_body($img_response), true);
                $img_url = $img_data['results'][0]['image'];
                $img_tag = '<center><img width="90%" src="'.$modified_link.''.$img_url.'" alt="'.$title.'"></center>';
                $content = $img_tag . $content;
            }
        }
    }
    return $content;
}
add_filter( 'the_content', 'insert_img_before_content' );
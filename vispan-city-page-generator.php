<?php
/**
 * Plugin Name: Vispan City Page Generator
 * Description: Generate city-based SEO pages for Vispan Solutions.
 * Version: 1.0.0
 * Author: Vispan Solutions
 */

defined('ABSPATH') || exit;

/*
|--------------------------------------------------------------------------
| Load Classes
|--------------------------------------------------------------------------
*/
require_once plugin_dir_path(__FILE__) . 'includes/class-city-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-content-database.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-openai-provider.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-quality-checker.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-content-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-seo-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-page-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-template-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-csv-job-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-csv-importer.php';

/*
|--------------------------------------------------------------------------
| Initialize
|--------------------------------------------------------------------------
*/
$city_manager     = new VCPG_City_Manager();
$ai_database      = new VCPG_AI_Content_Database();
$openai_provider  = new VCPG_OpenAI_Provider();
$quality_checker  = new VCPG_AI_Quality_Checker($openai_provider);
$ai_generator     = new VCPG_AI_Content_Generator($ai_database, $openai_provider, $quality_checker);
$seo_generator    = new VCPG_SEO_Generator();
$page_generator   = new VCPG_Page_Generator($ai_generator, $city_manager, $seo_generator);
new VCPG_Template_Manager();

/*
|--------------------------------------------------------------------------
| CSS Handler — prevents <style> from being stripped in VCPG pages
|--------------------------------------------------------------------------
*/
add_action('wp', 'vcpg_capture_page_styles');
function vcpg_capture_page_styles()
{
    if(is_singular('page'))
    {
        $post = get_post();
        if($post && preg_match('/<style>.*?<\/style>/s', $post->post_content, $matches))
        {
            $GLOBALS['vcpg_inline_styles'] = $matches[0];
        }
    }
}

add_action('wp_head', 'vcpg_output_styles');
function vcpg_output_styles()
{
    if(!empty($GLOBALS['vcpg_inline_styles']))
    {
        echo $GLOBALS['vcpg_inline_styles'];
    }
}

add_filter('the_content', 'vcpg_protect_styles', 1);
function vcpg_protect_styles($content)
{
    if(!empty($GLOBALS['vcpg_inline_styles']))
    {
        $content = str_replace($GLOBALS['vcpg_inline_styles'], '', $content);
    }
    elseif(preg_match('/^:root\{--vpg-/m', $content))
    {
        $content = preg_replace(
            '/^:root\{--vpg-[\s\S]*?\n(?=<header )/m',
            '<style>$0</style>',
            $content
        );
    }
    return $content;
}

/*
|--------------------------------------------------------------------------
| Custom Page Template — bypasses theme header/footer for VCPG pages
|--------------------------------------------------------------------------
*/
add_filter('template_include', 'vcpg_custom_template');
function vcpg_custom_template($template)
{
    if(is_singular('page'))
    {
        $post = get_post();
        $is_vcpg = get_post_meta($post->ID, '_vcpg_page', true)
                || strpos($post->post_content, 'vpg-container') !== false;

        if($is_vcpg)
        {
            $plugin_template = plugin_dir_path(__FILE__) . 'templates/page-template.php';
            if(file_exists($plugin_template))
            {
                return $plugin_template;
            }
        }
    }
    return $template;
}

/*
|--------------------------------------------------------------------------
| Batch Rewrite Flush — flushes rules once per batch instead of per page
|--------------------------------------------------------------------------
*/
add_action('admin_init', 'vcpg_batch_rewrite_flush');
function vcpg_batch_rewrite_flush()
{
    if(get_option('vcpg_needs_rewrite_flush', false))
    {
        flush_rewrite_rules(false);
        delete_option('vcpg_needs_rewrite_flush');
    }
}

/*
|--------------------------------------------------------------------------
| CSV Importer
|--------------------------------------------------------------------------
*/
$csv_job_manager = new VCPG_CSV_Job_Manager($page_generator);
$csv_importer    = new VCPG_CSV_Importer($csv_job_manager);

/*
|--------------------------------------------------------------------------
| Main Menu
|--------------------------------------------------------------------------
*/
add_action('admin_menu', 'vcpg_add_admin_menu');
function vcpg_add_admin_menu()
{
    add_menu_page(
        'City Page Generator',
        'City Page Generator',
        'manage_options',
        'vispan-city-generator',
        'vcpg_admin_page',
        'dashicons-location-alt',
        30
    );
}

/*
|--------------------------------------------------------------------------
| Sub Menus
|--------------------------------------------------------------------------
*/
add_action('admin_menu', 'vcpg_add_submenus', 99);
function vcpg_add_submenus()
{
    add_submenu_page(
        'vispan-city-generator',
        'City Database',
        'City Database',
        'manage_options',
        'vcpg-city-database',
        'vcpg_city_database_page'
    );

    add_submenu_page(
        'vispan-city-generator',
        'Templates',
        'Templates',
        'manage_options',
        'vcpg-templates',
        'vcpg_templates_page'
    );

    /*
    NOTE: "CSV Bulk Import" is intentionally NOT registered here.
    VCPG_CSV_Importer already registers it in its own constructor
    (hooked to admin_menu). Registering it again here produced a
    duplicate sidebar entry.
    */
}

function vcpg_city_database_page()
{
    echo '<div class="wrap">';
    echo '<h1>City Database</h1>';
    echo '<p>City database module working.</p>';
    echo '</div>';
}

function vcpg_templates_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'vcpg_templates';

    if (isset($_POST['save_template']) && check_admin_referer('vcpg_save_template')) {
        $name = sanitize_text_field($_POST['template_name']);
        $allowed_html = wp_kses_allowed_html('post');
        $allowed_html['style'] = array();
        $content = wp_kses(wp_unslash($_POST['template_content']), $allowed_html);

        $existing = $wpdb->get_var("SELECT id FROM $table_name LIMIT 1");

        if ($existing) {
            $wpdb->update($table_name, array('name' => $name, 'content' => $content), array('id' => $existing));
        } else {
            $wpdb->insert($table_name, array('name' => $name, 'content' => $content));
        }

        echo '<div class="notice notice-success">';
        echo '<p>Template saved successfully.</p>';
        echo '</div>';
    }

    $template = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");

    $template_name = $template ? $template->name : 'Premium Landing Page Template';

    $default_template_file = plugin_dir_path(__FILE__) . 'templates/premium-template.html';
    $template_content = $template
        ? $template->content
        : (file_exists($default_template_file) ? file_get_contents($default_template_file) : '<p>Template file not found.</p>');
?>

<div class="wrap">
<h1>Templates</h1>
<form method="post">
<?php wp_nonce_field('vcpg_save_template'); ?>
<table class="form-table">
<td><input type="text" name="template_name" value="<?php echo esc_attr($template_name); ?>" style="width:400px;"></td>
</tr>
<tr>
<th>Template Content</th>
<td><textarea name="template_content" rows="20" cols="100"><?php echo esc_textarea($template_content); ?></textarea></td>
</tr>
</table>
<?php submit_button('Save Template', 'primary', 'save_template'); ?>
</form>
</div>

<?php
}

function vcpg_admin_page()
{
    global $page_generator;

    if (isset($_POST['generate_page']) && check_admin_referer('vcpg_generate_page')) {
        $result = $page_generator->create_page(array(
            'country' => sanitize_text_field($_POST['country']),
            'country_code' => sanitize_title($_POST['country_code']),
            'city' => sanitize_text_field($_POST['city']),
            'state' => sanitize_text_field($_POST['state']),
            'service' => sanitize_text_field($_POST['service']),
            'service_keyword' => sanitize_title($_POST['service_keyword'])
        ));

        echo $result['status']
            ? '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>'
            : '<div class="notice notice-warning"><p>' . esc_html($result['message']) . '</p></div>';
    }
?>

<div class="wrap">
<h1>Vispan City Page Generator</h1>
<form method="post">
<?php wp_nonce_field('vcpg_generate_page'); ?>
<table class="form-table">
<tr><th>Country</th><td><input type="text" name="country" value="United States"></td></tr>
<tr><th>Country Code</th><td><input type="text" name="country_code" value="us"></td></tr>
<tr><th>State</th><td><input type="text" name="state" value="California"></td></tr>
<tr><th>City</th><td><input type="text" name="city" value="Los Angeles"></td></tr>
<tr><th>Service</th><td><input type="text" name="service" value="Digital Marketing Agency"></td></tr>
<tr><th>Service Keyword</th><td><input type="text" name="service_keyword" value="digital-marketing-agency"></td></tr>
</table>
<?php submit_button('Generate Page', 'primary', 'generate_page'); ?>
</form>
</div>
<?php
}
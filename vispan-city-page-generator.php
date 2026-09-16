<?php
/**
 * Plugin Name: Vispan City Page Generator
 * Plugin URI: https://vispansolutions.com
 * Description: Generate city-based SEO pages for Vispan Solutions with automatic inquiry lead handling & SMTP.
 * Version: 1.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Vispan Solutions
 * Author URI: https://vispansolutions.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vispan-city-page-generator
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
require_once plugin_dir_path(__FILE__) . 'includes/class-elementor-template-builder.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-static-elements.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-elementor-renderer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-page-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-template-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-keyword-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-csv-job-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-csv-importer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-inquiry-handler.php';

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
$keyword_manager  = new VCPG_Keyword_Manager();
$elementor_builder = new VCPG_Elementor_Template_Builder();
$page_generator   = new VCPG_Page_Generator($ai_generator, $city_manager, $seo_generator, $keyword_manager, $elementor_builder);
$inquiry_handler  = new VCPG_Inquiry_Handler();
/*
|--------------------------------------------------------------------------
| Plugin Activation & Deactivation Hooks
|--------------------------------------------------------------------------
*/
register_activation_hook(__FILE__, 'vcpg_activate_plugin');
function vcpg_activate_plugin() {
    if (!function_exists('wp_clean_plugins_cache') && defined('ABSPATH')) {
        $plugin_inc = ABSPATH . 'wp-admin/includes/plugin.php';
        if (file_exists($plugin_inc)) {
            require_once $plugin_inc;
        }
    }
    if (function_exists('wp_clean_plugins_cache')) {
        wp_clean_plugins_cache(true);
    }
}

register_deactivation_hook(__FILE__, 'vcpg_deactivate_plugin');
function vcpg_deactivate_plugin() {
    delete_option('vcpg_job_activity');
    delete_transient('vcpg_bulk_job');
}

/*
|--------------------------------------------------------------------------
| VCPG Page Isolation Helper
|--------------------------------------------------------------------------
*/
function is_vcpg_generated_page($post_id = 0)
{
    // NEVER apply VCPG page logic to front page or blog home
    if (function_exists('is_front_page') && is_front_page()) {
        return false;
    }
    if (function_exists('is_home') && is_home()) {
        return false;
    }

    if (!$post_id) {
        $post_id = get_queried_object_id();
    }
    if (!$post_id && is_singular('page')) {
        $post_id = get_the_ID();
    }
    if (!$post_id) {
        global $post;
        if (isset($post->ID)) {
            $post_id = $post->ID;
        }
    }
    if (!$post_id) {
        return false;
    }

    // Exclude front page or posts page explicitly by ID
    $front_id = (int) get_option('page_on_front');
    $home_id  = (int) get_option('page_for_posts');
    if (($front_id && (int)$post_id === $front_id) || ($home_id && (int)$post_id === $home_id)) {
        return false;
    }

    // 1. Direct explicit VCPG postmeta check
    if (get_post_meta($post_id, '_vcpg_page', true) === '1') {
        return true;
    }

    // 2. Specific VCPG location meta check
    if (get_post_meta($post_id, '_vcpg_city', true) || get_post_meta($post_id, '_vcpg_service', true) || get_post_meta($post_id, '_vcpg_country', true) || get_post_meta($post_id, '_vcpg_state', true) || get_post_meta($post_id, '_vcpg_county', true)) {
        update_post_meta($post_id, '_vcpg_page', '1');
        return true;
    }

    // 3. Page Template check
    if (get_post_meta($post_id, '_wp_page_template', true) === 'templates/page-template.php') {
        update_post_meta($post_id, '_vcpg_page', '1');
        return true;
    }

    // 4. Content / Elementor Data Structural Markers check
    $post_obj = get_post($post_id);
    if ($post_obj && $post_obj->post_type === 'page') {
        $content = $post_obj->post_content;
        if (empty($content)) {
            $elem_data = get_post_meta($post_id, '_elementor_data', true);
            if (is_array($elem_data)) {
                $content = wp_json_encode($elem_data);
            } elseif (is_string($elem_data)) {
                $content = $elem_data;
            }
        }
        if (is_string($content) && (
            strpos($content, 'vpg-container') !== false ||
            strpos($content, 'hero_proposal') !== false ||
            strpos($content, 'contact_proposal') !== false ||
            strpos($content, 'vcpg-dual-btn') !== false ||
            strpos($content, 'vp-hero') !== false ||
            strpos($content, 'vp-footer') !== false ||
            strpos($content, 'vcpg-nav-item') !== false ||
            strpos($content, 'e00000b') !== false ||
            strpos($content, 'e000007') !== false ||
            strpos($content, 'e000018') !== false
        )) {
            update_post_meta($post_id, '_vcpg_page', '1');
            return true;
        }

        // 5. Parent page ISO country code slug check
        if ($post_obj->post_parent > 0) {
            $parent = get_post($post_obj->post_parent);
            if ($parent) {
                $known_cc = array('in', 'us', 'uk', 'ca', 'au', 'de', 'fr', 'es', 'it', 'nl', 'br', 'mx', 'za', 'ae', 'sg', 'jp');
                if (in_array(strtolower($parent->post_name), $known_cc)) {
                    update_post_meta($post_id, '_vcpg_page', '1');
                    return true;
                }
            }
        }
    }

    return false;
}

add_filter('body_class', 'vcpg_add_body_class');
function vcpg_add_body_class($classes)
{
    if (is_vcpg_generated_page()) {
        $classes[] = 'vcpg-page';
        $classes[] = 'is-vcpg-page';
    }
    return $classes;
}

/*
|--------------------------------------------------------------------------
| CSS Handler — prevents <style> from being stripped in VCPG pages
|--------------------------------------------------------------------------
*/
add_action('wp', 'vcpg_capture_page_styles');
function vcpg_capture_page_styles()
{
    if(is_singular('page') && is_vcpg_generated_page())
    {
        $post = get_post();
        if($post && preg_match('/<style>.*?<\/style>/s', $post->post_content, $matches))
        {
            $GLOBALS['vcpg_inline_styles'] = $matches[0];
        }
    }
}

add_action('wp_head', 'vcpg_output_styles', 99999);
add_action('wp_footer', 'vcpg_output_styles', 99999);
add_action('template_redirect', 'vcpg_disable_wpautop_for_vcpg_pages');
function vcpg_disable_wpautop_for_vcpg_pages() {
    if (is_singular('page') && is_vcpg_generated_page()) {
        remove_filter('the_content', 'wpautop');
    }
}
function vcpg_safe_preg_replace($pattern, $replacement, $subject) {
    if (!is_string($subject) || empty($subject)) {
        return $subject;
    }
    $res = preg_replace($pattern, $replacement, $subject);
    if ($res === null) {
        return $subject; // Never return null on PCRE failure
    }
    return $res;
}

add_filter('the_content', 'vcpg_clean_content_inline_styles', 1);
add_filter('the_content', 'vcpg_clean_content_inline_styles', 99999);
function vcpg_clean_content_inline_styles($content) {
    if (!is_string($content) || empty($content)) {
        return $content;
    }
    if (is_singular('page') && is_vcpg_generated_page()) {
        // 1. Strip conflicting CSS rules
        $content = vcpg_safe_preg_replace('/\.elementor-element-e000003\s*\{[^}]*\}/i', '', $content);
        // 2. Strip commented-out template blocks that wpautop corrupts
        $content = vcpg_safe_preg_replace('/<!--\s*1\.\s*TOPBAR\s*&\s*HEADER.*?-->/is', '', $content);
        $content = vcpg_safe_preg_replace('/<!--\s*13\.\s*FOOTER.*?-->/is', '', $content);
        // 3. Strip legacy topbar & header HTML blocks from post_content safely
        $content = vcpg_safe_preg_replace('/<div[^>]*class=["\'][^"\']*vp-topbar[^"\']*["\'][^>]*>.*?<\/div>\s*<\/div>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<header[^>]*class=["\'][^"\']*vp-header[^"\']*["\'][^>]*>.*?<\/header>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<section[^>]*data-id=["\']e000003["\'][^>]*>.*?<\/section>/is', '', $content);
        // 4. Strip legacy footer HTML blocks from post_content safely
        $content = vcpg_safe_preg_replace('/<footer[^>]*class=["\'][^"\']*vp-footer[^"\']*["\'][^>]*>.*?<\/footer>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<section[^>]*data-id=["\']e000043["\'][^>]*>.*?<\/section>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<section[^>]*data-id=["\']e000044["\'][^>]*>.*?<\/section>/is', '', $content);
        // 5. Strip any isolated stray fragments and unbalanced closing divs
        $content = vcpg_safe_preg_replace('/<p>\s*<!--\s*VCPG-TEMPLATE.*?-->\s*<!--\s*1\.\s*TOPBAR\s*&\s*HEADER\s*-->\s*<\/p>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<div>\s*Vispan Solutions Pvt\. Ltd\.\s*<\/div>\s*(?:<\/p>)?\s*(?:<\/div>\s*){1,3}/is', '', $content);
        $content = vcpg_safe_preg_replace('/<div>\s*Vispan Solutions Pvt\. Ltd\.\s*<\/div>/is', '', $content);
        // 6. Strip unwanted portfolio section if present in post_content
        $content = vcpg_safe_preg_replace('/<!--\s*8\.\s*PORTFOLIO\s*-->\s*<section[^>]*class=["\'][^"\']*vp-portfolio-sec[^"\']*["\'][^>]*>.*?<\/section>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<section[^>]*class=["\'][^"\']*vp-portfolio-sec[^"\']*["\'][^>]*>.*?<\/section>/is', '', $content);
        // 7. Strip unwanted capsule box above hero header
        $content = vcpg_safe_preg_replace('/<div[^>]*padding:\s*6px\s*16px[^>]*>.*?<\/div>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<div[^>]*class=["\'][^"\']*vp-hero-city-label[^"\']*["\'][^>]*>.*?<\/div>/is', '', $content);
    }
    return $content;
}

function vcpg_output_styles()
{
    if (!is_vcpg_generated_page()) {
        return; // ZERO CSS output on built-in website pages!
    }
    if(!empty($GLOBALS['vcpg_inline_styles']) && !did_action('wp_footer'))
    {
        // Clean conflicting header position rules from captured inline styles
        $clean_captured = preg_replace('/\.elementor-element-e000003\s*\{[^}]*\}/i', '', $GLOBALS['vcpg_inline_styles']);
        echo $clean_captured;
    }

    echo '<style id="vcpg-brand-overrides">
    /* Theme & Elementor Pro header & footer display suppression (COMMENTED OUT FOR ELEMENTOR THEME INTEGRATION)
    html body.vcpg-page header,
    html body.vcpg-page #site-header,
    html body.vcpg-page .site-header,
    html body.vcpg-page div[data-elementor-type="header"],
    html body.vcpg-page .elementor-location-header,
    html body.vcpg-page footer:not(.vp-footer),
    html body.vcpg-page #site-footer,
    html body.vcpg-page .site-footer,
    html body.vcpg-page div[data-elementor-type="footer"],
    html body.vcpg-page .elementor-location-footer,
    html body.vcpg-page #masthead,
    html body.vcpg-page #colophon,
    header.site-header,
    footer.site-footer,
    #site-header,
    #site-footer,
    #masthead,
    #colophon {
        display: none !important;
    }
    */

    /* Hide legacy custom template header & footer to display single Elementor theme header & footer */
    .vp-topbar,
    .vp-header,
    header.vp-header,
    .vp-nav,
    .vp-footer,
    footer.vp-footer,
    html body.vcpg-page .vp-topbar,
    html body.vcpg-page .vp-header,
    html body.vcpg-page header.vp-header,
    html body.vcpg-page .vp-nav,
    html body.vcpg-page .vp-footer,
    html body.vcpg-page footer.vp-footer,
    html body.vcpg-page #vcpg-header,
    html body.vcpg-page .elementor-element-e000003,
    html body.vcpg-page .elementor-element-e000043,
    html body.vcpg-page .elementor-element-e000044 {
        display: none !important;
    }

    /* Core Layout & Colors for all VCPG pages */
    :root {
      --vp-primary: #0B63F6;
      --vp-primary-dark: #094bc4;
      --vp-dark: #0A3663;
      --vp-dark-2: #070D18;
      --vp-white: #FFFFFF;
      --vp-bg: #F8FAFC;
      --vp-text: #334155;
      --vp-text-light: #64748B;
      --vp-border: #E2E8F0;
      --vp-radius: 12px;
      --vp-radius-lg: 16px;
      --vp-shadow: 0 4px 20px rgba(0,0,0,0.05);
      --vp-font: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;
    }

    .vp-container { max-width: 1200px !important; margin: 0 auto !important; padding: 0 24px !important; width: 100% !important; box-sizing: border-box !important; }
    .vp-section { padding: 80px 0 !important; }
    .vp-title { font-size: 2.2rem !important; font-weight: 800 !important; color: #0A3663 !important; line-height: 1.25 !important; margin-bottom: 16px !important; }
    .vp-desc { font-size: 1rem !important; color: #334155 !important; line-height: 1.8 !important; max-width: 800px !important; }
    .vp-title-center { text-align: center !important; }
    .vp-desc-center { margin-left: auto !important; margin-right: auto !important; text-align: center !important; }

    /* HERO */
    .vp-hero { position: relative !important; padding: 190px 0 90px !important; color: #000000 !important; overflow: hidden !important; background: #FFFFFF !important; }
    .vp-hero video { transform: translate(-50%, -50%) scale(1.4) !important; }
    .vp-hero-grid { display: grid !important; grid-template-columns: 1fr 480px !important; gap: 60px !important; align-items: center !important; position: relative !important; z-index: 1 !important; }
    .vp-hero h1 { font-size: 3.2rem !important; font-weight: 800 !important; line-height: 1.15 !important; margin-bottom: 10px !important; color: #02426A !important; }
    .vp-hero h2 { color: #000000 !important; font-size: 1.6rem !important; font-weight: 500 !important; margin-bottom: 20px !important; line-height: 1.3 !important; }
    .vp-hero p { font-size: 1.1rem !important; color: #334155 !important; line-height: 1.65 !important; margin-bottom: 30px !important; }
    .vp-btn-hero { background: #02426A !important; color: #FFFFFF !important; padding: 14px 32px !important; border-radius: 50px !important; text-decoration: none !important; font-weight: 700 !important; font-size: 0.95rem !important; display: inline-flex !important; align-items: center !important; gap: 10px !important; }

    /* Hero inquiry form card border */
    .vp-hero-form-card,
    .vp-hero-right {
        background: rgba(255, 255, 255, 0.92) !important;
        border: 2px solid #02426A !important;
        border-radius: 30px !important;
        padding: 35px 30px !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
        box-sizing: border-box !important;
    }
    .vp-hero-form-card h3,
    .vp-hero-right h3 {
        font-size: 28px !important;
        font-weight: 700 !important;
        margin-bottom: 24px !important;
        text-align: left !important;
        color: #02426A !important;
    }

    /* INTRO */
    .vp-intro { background: #FFFFFF !important; text-align: center !important; }

    /* ABOUT */
    .vp-about { background: #FFFFFF !important; }
    .vp-about-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 60px !important; align-items: center !important; }
    .vp-feature-card { background: #FFFFFF !important; border: 1px solid #E2E8F0 !important; border-radius: 12px !important; padding: 18px !important; display: flex !important; gap: 14px !important; box-shadow: 0 2px 10px rgba(0,0,0,0.03) !important; }

    /* SERVICES */
    .vp-services-sec { color: #FFFFFF !important; }
    .vp-services-sec .vp-title { color: #0A3663 !important; }
    .vp-services-sec .vp-desc { color: #334155 !important; }
    .vp-service-card { background: #FFFFFF !important; border-radius: 16px !important; padding: 28px !important; color: #0A3663 !important; box-shadow: 0 10px 30px rgba(0,0,0,0.06) !important; }

    /* WHY CHOOSE */
    .vp-why-sec { background: #FFFFFF !important; }
    .vp-tabs { display: flex !important; gap: 12px !important; flex-wrap: wrap !important; justify-content: center !important; margin-top: 30px !important; }
    .vp-tab-active { background: #FFFFFF !important; color: #081828 !important; border: 1px solid #CBD5E1 !important; padding: 10px 20px !important; border-radius: 6px !important; font-weight: 700 !important; }
    .vp-tab-dark { background: #0F172A !important; color: #FFFFFF !important; padding: 10px 20px !important; border-radius: 6px !important; font-weight: 600 !important; }

    .vp-casestudy-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 50px !important; align-items: stretch !important; }

    /* LOGOS */
    .vp-logos-bar { padding: 40px 0 !important; background: #FFFFFF !important; border-top: 1px solid #E2E8F0 !important; border-bottom: 1px solid #E2E8F0 !important; }

    /* TESTIMONIAL */
    .vp-testi-sec { background: #FFFFFF !important; text-align: center !important; }

    /* CERTIFICATIONS */
    .vp-cert-sec { background: #F8FAFC !important; text-align: center !important; }

    /* CONTACT FORM */
    .vp-contact-card { max-width: 760px !important; margin: 0 auto !important; background: #FFFFFF !important; border-radius: 20px !important; padding: 44px !important; box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important; }

    /* Suppress unwanted portfolio section */
    .vp-portfolio-sec {
        display: none !important;
    }

    /* Suppress unwanted empty capsule box above hero header */
    .vp-hero div[style*="border-radius:30px"]:empty,
    .vp-hero div[style*="border-radius: 30px"]:empty,
    .vp-hero-city-label {
        display: none !important;
    }

    @media (max-width: 900px) {
      .vp-hero-grid, .vp-about-grid, .vp-footer-grid, .vp-casestudy-grid { grid-template-columns: 1fr !important; gap: 40px !important; }
      .vp-casestudy-grid > div:first-child { order: 1 !important; }
      .vp-casestudy-grid > div:last-child { order: 2 !important; }
    }

    /* Ensure Theme & ElementsKit Header is 100% visible at scroll 0 */
    html body.vcpg-page .ekit-template-content-header,
    html body.vcpg-page header.elementskit-menu-container,
    html body.vcpg-page .elementor-location-header,
    html body.vcpg-page .elementor-35930 {
        position: relative !important;
        z-index: 999999 !important;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        background-color: #FFFFFF !important;
        width: 100% !important;
    }
    html body.vcpg-page .elementor-35930 .elementor-element.elementor-element-8eb9496 {
        position: relative !important;
        z-index: 999999 !important;
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: 100% !important;
    }
    html body.vcpg-page .elementor-35930 .elementor-element.elementor-element-8602ba9 {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        background-color: #FFFFFF !important;
    }
    html body.vcpg-page .vp-hero {
        position: relative !important;
        z-index: 1 !important;
        margin-top: 0 !important;
    }

    html body.vcpg-page footer.vp-footer a { color: #CBD5E1 !important; text-decoration: none !important; }
    html body.vcpg-page footer.vp-footer a:hover { color: #FFFFFF !important; }
    html body.vcpg-page .vp-footer a[href^="tel:"] { color: #FFFFFF !important; font-weight: 700 !important; }
    html body.vcpg-page .vp-footer a[href^="mailto:"] { color: #38BDF8 !important; font-weight: 600 !important; }
    /* Fixed Centered Background Video Positioning System */
    html body.vcpg-page .elementor-background-video-container {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100% !important;
        height: 100% !important;
        overflow: hidden !important;
        z-index: 0 !important;
        pointer-events: none !important;
    }
    html body.vcpg-page .elementor-background-video-hosted,
    html body.vcpg-page .elementor-background-video,
    html body.vcpg-page video.elementor-background-video-hosted {
        position: absolute !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) scale(1.35) !important;
        min-width: 100% !important;
        min-height: 100% !important;
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        object-position: center center !important;
        pointer-events: none !important;
        display: block !important;
    }
    html body.vcpg-page .elementor-element-e00000b {
        position: relative !important;
        z-index: 1 !important;
        padding-top: 190px !important;
    }
    html body.vcpg-page .elementor-element-e00000b .elementor-container {
        position: relative !important;
        z-index: 2 !important;
    }
    html body.vcpg-page .elementor-element-e00000b h1,
    html body.vcpg-page .elementor-element-e00000b p {
        position: relative !important;
        z-index: 2 !important;
        text-shadow: 0 1px 4px rgba(0, 0, 0, 0.2) !important;
    }
    
    /* Typography Spacing Legibility Overrides */
    html body.vcpg-page h1, html body.vcpg-page h2, html body.vcpg-page h3, html body.vcpg-page h4, html body.vcpg-page h5, html body.vcpg-page h6 {
        letter-spacing: 0.02em !important;
        word-spacing: 0.08em !important;
    }
    html body.vcpg-page p, html body.vcpg-page li, html body.vcpg-page label, html body.vcpg-page input, html body.vcpg-page textarea, html body.vcpg-page select {
        letter-spacing: 0.01em !important;
        word-spacing: 0.05em !important;
    }
    
    html body.vcpg-page .vp-casestudy-grid {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 50px !important;
        align-items: stretch !important;
    }
    @media (max-width: 900px) {
        html body.vcpg-page .vp-casestudy-grid {
            grid-template-columns: 1fr !important;
            gap: 40px !important;
        }
        html body.vcpg-page .vp-casestudy-grid > div:first-child { order: 1 !important; }
        html body.vcpg-page .vp-casestudy-grid > div:last-child { order: 2 !important; }
    }

    /* ==========================================================================
       Global Nav Item Safety - Prevent Text Line-Wrapping inside Nav Links
       ========================================================================== */
    html body.vcpg-page .vcpg-nav-link {
        white-space: nowrap !important;
        word-break: keep-all !important;
    }

    /* ==========================================================================
       VCPG Comprehensive Responsive Breakpoint System (Mobile, Tablet, Desktop)
       ========================================================================== */
    
    /* Box Safety & Global Media Responsiveness */
    html body.vcpg-page img,
    html body.vcpg-page iframe,
    html body.vcpg-page video {
        max-width: 100% !important;
        height: auto !important;
    }
    
    /* TABLET & MOBILE RESPONSIVE HEADER & LAYOUT (max-width: 1024px) */
    @media (max-width: 1024px) {
        html body.vcpg-page .elementor-container,
        html body.vcpg-page .vpg-container {
            padding-left: 20px !important;
            padding-right: 20px !important;
            box-sizing: border-box !important;
        }

        /* 1. Header non-overlap fix: make header relative on mobile/tablet with solid white backdrop */
        html body.vcpg-page .elementor-element-e000003 {
            position: relative !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            z-index: 99999 !important;
            background: #FFFFFF !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
        }

        /* Main navbar bar background white on mobile/tablet */
        html body.vcpg-page .elementor-element-e000003 div[style*="height:60px"],
        html body.vcpg-page .elementor-element-e000003 div[style*="height: 60px"] {
            background: #FFFFFF !important;
            height: auto !important;
            padding: 12px 16px !important;
        }

        /* Main navbar layout: logo & button on top row */
        html body.vcpg-page .elementor-element-e000003 div[style*="height:60px"] > div,
        html body.vcpg-page .elementor-element-e000003 div[style*="height: 60px"] > div {
            flex-wrap: wrap !important;
            gap: 12px !important;
            justify-content: space-between !important;
            align-items: center !important;
        }

        /* Navigation Links horizontally scrollable row */
        html body.vcpg-page .elementor-element-e000003 ul {
            width: 100% !important;
            overflow-x: auto !important;
            white-space: nowrap !important;
            -webkit-overflow-scrolling: touch !important;
            justify-content: flex-start !important;
            padding: 8px 0 !important;
            margin: 6px 0 0 0 !important;
            scrollbar-width: none !important;
            display: flex !important;
            gap: 8px !important;
        }
        html body.vcpg-page .elementor-element-e000003 ul::-webkit-scrollbar {
            display: none !important;
        }

        /* Individual nav items styling on mobile/tablet */
        html body.vcpg-page .vcpg-nav-item {
            white-space: nowrap !important;
            flex-shrink: 0 !important;
            display: inline-block !important;
        }
        html body.vcpg-page .vcpg-nav-link {
            white-space: nowrap !important;
            word-break: keep-all !important;
            padding: 8px 14px !important;
            font-size: 14px !important;
            height: auto !important;
            line-height: 1.4 !important;
            background: #F8FAFC !important;
            border-radius: 20px !important;
            color: #02426A !important;
        }

        /* Hero section top padding adjustment since header is no longer absolute on mobile */
        html body.vcpg-page .elementor-element-e00000b {
            padding-top: 40px !important;
        }

        /* Force hero 2 columns (title/text vs proposal card) to stack vertically */
        html body.vcpg-page .elementor-element-e00000b .elementor-container {
            flex-direction: column !important;
        }
        html body.vcpg-page .elementor-element-e00000b .elementor-column {
            width: 100% !important;
            max-width: 100% !important;
        }

        /* Tablet 3+ grid columns reflow to 2 columns */
        html body.vcpg-page div[style*="grid-template-columns:repeat(3"],
        html body.vcpg-page div[style*="grid-template-columns: repeat(3"],
        html body.vcpg-page div[style*="grid-template-columns:repeat(4"],
        html body.vcpg-page div[style*="grid-template-columns: repeat(4"] {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 20px !important;
        }

        .vcpg-dual-btn .vcpg-btn-pill {
            font-size: 14px !important;
            padding: 0 16px !important;
        }
    }

    /* MOBILE & SMALL TABLET RESPONSIVE STYLES (max-width: 768px) */
    @media (max-width: 768px) {
        html, body.vcpg-page {
            overflow-x: hidden !important;
        }
        
        /* Force ALL Elementor columns and inline grid containers to single column on mobile */
        html body.vcpg-page .elementor-column,
        html body.vcpg-page .elementor-col-10,
        html body.vcpg-page .elementor-col-20,
        html body.vcpg-page .elementor-col-25,
        html body.vcpg-page .elementor-col-30,
        html body.vcpg-page .elementor-col-33,
        html body.vcpg-page .elementor-col-40,
        html body.vcpg-page .elementor-col-50,
        html body.vcpg-page .elementor-col-60,
        html body.vcpg-page .elementor-col-66,
        html body.vcpg-page .elementor-col-70,
        html body.vcpg-page .elementor-col-75,
        html body.vcpg-page .elementor-col-80,
        html body.vcpg-page .elementor-col-100 {
            width: 100% !important;
            max-width: 100% !important;
        }

        html body.vcpg-page div[style*="grid-template-columns"] {
            grid-template-columns: 1fr !important;
            gap: 16px !important;
        }

        /* Tabs container wrap on mobile */
        html body.vcpg-page div[style*="flex-direction:row"][style*="flex-wrap:nowrap"],
        html body.vcpg-page div[style*="flex-direction: row"][style*="flex-wrap: nowrap"] {
            flex-wrap: wrap !important;
            gap: 8px !important;
        }
        .vcpg-tab-btn {
            flex: 1 1 100% !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        /* Google Partner + Certifications section wrap */
        html body.vcpg-page div[style*="max-width:1180px"][style*="display:flex"],
        html body.vcpg-page div[style*="max-width: 1180px"][style*="display: flex"] {
            flex-direction: column !important;
            align-items: center !important;
            text-align: center !important;
            gap: 24px !important;
        }

        /* Testimonial slider navigation buttons on mobile */
        .vcpg-t-prev, .vcpg-t-next {
            width: 36px !important;
            height: 36px !important;
            font-size: 0.9rem !important;
        }

        /* Typography Scaling on Mobile */
        html body.vcpg-page h1,
        html body.vcpg-page h1.elementor-heading-title {
            font-size: 1.85rem !important;
            line-height: 1.25 !important;
        }
        html body.vcpg-page h2,
        html body.vcpg-page h2.elementor-heading-title {
            font-size: 1.5rem !important;
            line-height: 1.3 !important;
        }
        html body.vcpg-page h3,
        html body.vcpg-page h3.elementor-heading-title {
            font-size: 1.25rem !important;
            line-height: 1.35 !important;
        }

        /* Topbar Mobile Formatting */
        .elementor-element-e000003 div[style*="background:#02426A"],
        .elementor-element-e000003 div[style*="background: #02426A"] {
            height: auto !important;
            padding: 8px 12px !important;
        }
        .elementor-element-e000003 div[style*="background:#02426A"] > div,
        .elementor-element-e000003 div[style*="background: #02426A"] > div {
            flex-wrap: wrap !important;
            justify-content: center !important;
            gap: 8px 16px !important;
            text-align: center !important;
        }

        /* Forms Layout on Mobile */
        html body.vcpg-page form div[style*="grid-template-columns:1fr 1fr"],
        html body.vcpg-page form div[style*="grid-template-columns: 1fr 1fr"] {
            grid-template-columns: 1fr !important;
            gap: 12px !important;
        }

        /* Mega Menu Mobile Popup */
        .vcpg-mega-menu {
            padding: 16px !important;
        }
        .vcpg-mega-grid {
            grid-template-columns: 1fr !important;
            gap: 4px !important;
        }

        /* Case Study & Grid Mobile Reflow */
        .vp-casestudy-grid {
            grid-template-columns: 1fr !important;
            gap: 24px !important;
        }

        /* Footer Column Stacking */
        html body footer.vp-footer > div,
        html body .vp-footer > div {
            flex-direction: column !important;
            gap: 30px !important;
        }

        /* Button Sizing on Mobile */
        .vcpg-dual-btn .vcpg-btn-pill {
            padding: 0 14px !important;
            font-size: 13px !important;
            height: 36px !important;
        }
        .vcpg-dual-btn .vcpg-btn-circle-left,
        .vcpg-dual-btn .vcpg-btn-circle-right {
            height: 36px !important;
            width: 36px !important;
        }

        /* Back to Top button position on mobile */
        .vcpg-back-to-top {
            bottom: 20px !important;
            right: 20px !important;
        }
    }

    /* EXTRA SMALL MOBILE RESPONSIVE STYLES (max-width: 480px) */
    @media (max-width: 480px) {
        html body.vcpg-page h1,
        html body.vcpg-page h1.elementor-heading-title {
            font-size: 1.6rem !important;
        }
        html body.vcpg-page h2,
        html body.vcpg-page h2.elementor-heading-title {
            font-size: 1.35rem !important;
        }
        html body.vcpg-page .elementor-element-e00000b {
            padding-top: 20px !important;
        }
    }
    </style>';
}

add_filter('pre_get_document_title', 'vcpg_filter_page_title', 99999);
function vcpg_filter_page_title($title)
{
    if (function_exists('is_front_page') && (is_front_page() || is_home())) {
        return $title;
    }
    if (is_singular('page')) {
        $pid = get_the_ID();
        if (is_vcpg_generated_page($pid)) {
            // Check if there is an AI generated title saved
            $ai_title = get_post_meta($pid, 'rank_math_title', true);
            if (!$ai_title) {
                $ai_title = get_post_meta($pid, '_yoast_wpseo_title', true);
            }
            if ($ai_title && is_string($ai_title)) {
                // Remove Yoast and RankMath double-percent placeholders (%%title%%, %%sitedesc%%, etc.)
                $cleaned = preg_replace('/%%[^%]+%%/', '', $ai_title);
                $cleaned = preg_replace('/%[^%]+%/', '', $cleaned);
                $cleaned = trim($cleaned, " \t\n\r\0\x0B|-:");
                if (!empty($cleaned)) {
                    return $cleaned;
                }
            }
        }
    }
    return $title;
}

add_action('wp_head', 'vcpg_output_seo_meta_and_schema', 5);
function vcpg_output_seo_meta_and_schema()
{
    if (!is_singular('page')) {
        return;
    }

    $page_id = get_the_ID();
    if (!is_vcpg_generated_page($page_id)) {
        return;
    }

    // 1. Fallback Meta Description tag if no SEO plugin is active
    if (!defined('WPSEO_VERSION') && !class_exists('RankMath')) {
        $desc = get_post_meta($page_id, 'rank_math_description', true);
        if (!$desc) {
            $desc = get_post_meta($page_id, '_yoast_wpseo_metadesc', true);
        }
        if ($desc) {
            echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
        }
    }

    // 2. Build and output JSON-LD schemas
    $city         = get_post_meta($page_id, '_vcpg_city', true);
    $state        = get_post_meta($page_id, '_vcpg_state', true);
    $country      = get_post_meta($page_id, '_vcpg_country', true);
    $country_code = get_post_meta($page_id, '_vcpg_country_code', true);
    $service      = get_post_meta($page_id, '_vcpg_service', true);
    $faq          = get_post_meta($page_id, '_vcpg_faq', true);

    $page_url = get_permalink($page_id);
    $home_url = home_url('/');

    $schemas = array();

    // A. BreadcrumbList Schema
    $breadcrumb_items = array(
        array(
            "@type" => "ListItem",
            "position" => 1,
            "name" => "Home",
            "item" => $home_url
        )
    );
    if (!empty($service)) {
        $service_slug = sanitize_title($service);
        $breadcrumb_items[] = array(
            "@type" => "ListItem",
            "position" => 2,
            "name" => $service,
            "item" => $home_url . $service_slug . '/'
        );
    }
    $breadcrumb_items[] = array(
        "@type" => "ListItem",
        "position" => !empty($service) ? 3 : 2,
        "name" => !empty($city) ? $city : get_the_title($page_id),
        "item" => $page_url
    );

    $schemas[] = array(
        "@context" => "https://schema.org",
        "@type" => "BreadcrumbList",
        "itemListElement" => $breadcrumb_items
    );

    // B. LocalBusiness Schema
    $local_business = array(
        "@context" => "https://schema.org",
        "@type" => "LocalBusiness",
        "name" => "Vispan Solutions",
        "image" => "https://vispansolutions.com/wp-content/uploads/2022/11/logo.png",
        "@id" => $page_url . "#localbusiness",
        "url" => $page_url,
        "telephone" => "+918485986860",
        "address" => array(
            "@type" => "PostalAddress",
            "addressLocality" => !empty($city) ? $city : "",
            "addressRegion" => !empty($state) ? $state : "",
            "addressCountry" => !empty($country_code) ? strtoupper($country_code) : "US"
        )
    );
    $schemas[] = $local_business;

    // C. FAQPage Schema
    if (!empty($faq) && is_array($faq)) {
        $faq_elements = array();
        foreach ($faq as $item) {
            if (isset($item['question']) && isset($item['answer'])) {
                $faq_elements[] = array(
                    "@type" => "Question",
                    "name" => esc_html($item['question']),
                    "acceptedAnswer" => array(
                        "@type" => "Answer",
                        "text" => wp_strip_all_tags($item['answer'])
                    )
                );
            }
        }
        if (!empty($faq_elements)) {
            $schemas[] = array(
                "@context" => "https://schema.org",
                "@type" => "FAQPage",
                "mainEntity" => $faq_elements
            );
        }
    }

    // Output JSON-LD
    echo "\n" . '<!-- VCPG SEO Schema Begin -->' . "\n";
    foreach ($schemas as $schema) {
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        echo '</script>' . "\n";
    }
    echo '<!-- VCPG SEO Schema End -->' . "\n\n";
}

add_filter('the_content', 'vcpg_protect_styles', 1);
function vcpg_protect_styles($content)
{
    if (!is_singular('page') || !is_vcpg_generated_page()) {
        return $content;
    }
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
add_filter('template_include', 'vcpg_custom_template', 99999);
function vcpg_custom_template($template)
{
    if(is_singular('page') || is_page())
    {
        if(is_vcpg_generated_page())
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

    add_submenu_page(
        'vispan-city-generator',
        'OpenAI Status',
        'OpenAI Status',
        'manage_options',
        'vcpg-openai-status',
        'vcpg_openai_status_page'
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

function vcpg_openai_status_page()
{
    global $wpdb;

    $provider = new VCPG_OpenAI_Provider();

    $test_result = null;

    if(isset($_POST['vcpg_test_openai']) && check_admin_referer('vcpg_test_openai'))
    {
        $test_result = $provider->test_connection();
    }

    if(isset($_POST['vcpg_save_key']) && check_admin_referer('vcpg_save_key'))
    {
        $new_key = sanitize_text_field(wp_unslash($_POST['openai_key']));
        update_option('vcpg_openai_api_key', trim($new_key));
        echo '<div class="notice notice-success"><p>API key saved. (It is stored in the WordPress options table.)</p></div>';
    }

    if(isset($_POST['vcpg_reset_data']) && check_admin_referer('vcpg_reset_data'))
    {
        $confirmed = isset($_POST['vcpg_confirm_reset']) && $_POST['vcpg_confirm_reset'] === 'yes';

        if($confirmed)
        {
            $tables = array('vcpg_ai_content', 'vcpg_cities', 'vcpg_csv_jobs', 'vcpg_keywords');

            foreach($tables as $t)
            {
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}$t");
            }

            delete_option('vcpg_content_version_purged');
            delete_option('vcpg_keywords_purged');

            echo '<div class="notice notice-success"><p>VCPG database reset. Cached content, city data, CSV jobs, and keywords were cleared. Your generated pages, API key, and template were kept.</p></div>';
        }
        else
        {
            echo '<div class="notice notice-error"><p>Nothing was reset — you must type <strong>yes</strong> in the confirmation box.</p></div>';
        }
    }

    $configured = $provider->is_configured();

    $table = $wpdb->prefix . 'vcpg_ai_content';

    $recent = $wpdb->get_results(
        "SELECT id, service, city, state, content_source, created_at
         FROM $table
         ORDER BY id DESC
         LIMIT 30"
    );
?>

<div class="wrap">
<h1>OpenAI Status</h1>

<table class="widefat striped" style="max-width:720px;">
<tbody>
<tr>
<th>API Key Configured</th>
<td>
<?php if($configured): ?>
    <span style="color:#00a32a; font-weight:700;">Yes</span> — content can be generated with OpenAI.
<?php else: ?>
    <span style="color:#d63638; font-weight:700;">NO — this is why pages generate instantly.</span><br>
    When no key is set, the plugin silently falls back to pre-written template content and does NOT call OpenAI.
<?php endif; ?>
</td>
</tr>
<tr>
<th>Save / Update API Key</th>
<td>
<form method="post">
<?php wp_nonce_field('vcpg_save_key'); ?>
<input type="password" name="openai_key" value="" placeholder="sk-..." style="min-width:320px;">
<?php submit_button('Save Key', 'secondary', 'vcpg_save_key'); ?>
</form>
<p class="description">Stored in the WordPress options table. This lets you configure the API key from the dashboard — no file access needed.</p>
</td>
</tr>
<tr>
<th>Test Connection</th>
<td>
<form method="post">
<?php wp_nonce_field('vcpg_test_openai'); ?>
<?php submit_button('Test OpenAI Connection', 'primary', 'vcpg_test_openai'); ?>
</form>
<?php if($test_result !== null): ?>
    <?php if($test_result['ok']): ?>
        <div class="notice notice-success inline"><p><?php echo esc_html($test_result['msg']); ?></p></div>
    <?php else: ?>
        <div class="notice notice-error inline"><p><?php echo esc_html($test_result['msg']); ?></p></div>
    <?php endif; ?>
<?php endif; ?>
</td>
</tr>
</tbody>
</table>

<h2>How to check if a generated page used the API</h2>
<ol>
<li>Run <strong>Test Connection</strong> above. If it fails, the API never runs, so all pages are fallback.</li>
<li>Generate ONE page and time it. API pages take roughly 30–120 seconds; fallback pages take a couple of seconds.</li>
<li>Compare the page's hero title. Fallback titles are fixed templates like <em>"Award-Winning {Service} Serving {City} Businesses"</em> or <em>"{City} {Service} — Data-Backed Strategies for Measurable Growth"</em>. API content is unique and tailored.</li>
</ol>

<h2>Recent Content Records</h2>
<p class="description">Records are stamped with <strong>API</strong> when generated by OpenAI and <strong>Fallback</strong> when OpenAI failed. (Records created before this update may not show an accurate source.)</p>
<table class="widefat striped">
<thead>
<tr><th>ID</th><th>Service</th><th>City</th><th>State</th><th>Source</th><th>Created</th></tr>
</thead>
<tbody>
<?php if(!empty($recent)): foreach($recent as $r): ?>
<tr>
<td><?php echo (int)$r->id; ?></td>
<td><?php echo esc_html($r->service); ?></td>
<td><?php echo esc_html($r->city); ?></td>
<td><?php echo esc_html($r->state); ?></td>
<td>
<?php if($r->content_source === 'fallback'): ?>
    <span style="color:#d63638; font-weight:700;">Fallback</span>
<?php else: ?>
    <span style="color:#00a32a; font-weight:700;">API</span>
<?php endif; ?>
</td>
<td><?php echo esc_html($r->created_at); ?></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="6">No content records yet.</td></tr>
<?php endif; ?>
</tbody>
</table>

<h2>Database Reset</h2>
<p class="description">Clears the plugin's database values only. This resets:
cached AI content, the city database, CSV jobs, and keywords.
Your <strong>generated pages are NOT deleted</strong> — your <strong>OpenAI API key</strong> and <strong>template</strong> are also kept.</p>
<form method="post" onsubmit="return confirm('This clears all VCPG database values. Generated pages are not touched. Continue?');">
<?php wp_nonce_field('vcpg_reset_data'); ?>
<p>
<label for="vcpg_confirm_reset">Type <strong>yes</strong> to confirm:</label>
<input type="text" id="vcpg_confirm_reset" name="vcpg_confirm_reset" style="min-width:120px;">
</p>
<?php submit_button('Reset Database', 'delete', 'vcpg_reset_data'); ?>
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
<tr><th>Country</th><td><input type="text" id="vcpg_country" name="country" value="United States" style="min-width:300px;"></td></tr>
<tr>
  <th>Country Code</th>
  <td>
    <input type="text" id="vcpg_country_code" name="country_code" value="us" list="vcpg_cc_list" autocomplete="off" style="min-width:300px;" placeholder="Type to search or enter code…">
    <datalist id="vcpg_cc_list">
      <option value="af" data-country="Afghanistan">af — Afghanistan</option>
      <option value="al" data-country="Albania">al — Albania</option>
      <option value="dz" data-country="Algeria">dz — Algeria</option>
      <option value="ad" data-country="Andorra">ad — Andorra</option>
      <option value="ao" data-country="Angola">ao — Angola</option>
      <option value="ag" data-country="Antigua and Barbuda">ag — Antigua and Barbuda</option>
      <option value="ar" data-country="Argentina">ar — Argentina</option>
      <option value="am" data-country="Armenia">am — Armenia</option>
      <option value="au" data-country="Australia">au — Australia</option>
      <option value="at" data-country="Austria">at — Austria</option>
      <option value="az" data-country="Azerbaijan">az — Azerbaijan</option>
      <option value="bs" data-country="Bahamas">bs — Bahamas</option>
      <option value="bh" data-country="Bahrain">bh — Bahrain</option>
      <option value="bd" data-country="Bangladesh">bd — Bangladesh</option>
      <option value="bb" data-country="Barbados">bb — Barbados</option>
      <option value="by" data-country="Belarus">by — Belarus</option>
      <option value="be" data-country="Belgium">be — Belgium</option>
      <option value="bz" data-country="Belize">bz — Belize</option>
      <option value="bj" data-country="Benin">bj — Benin</option>
      <option value="bt" data-country="Bhutan">bt — Bhutan</option>
      <option value="bo" data-country="Bolivia">bo — Bolivia</option>
      <option value="ba" data-country="Bosnia and Herzegovina">ba — Bosnia and Herzegovina</option>
      <option value="bw" data-country="Botswana">bw — Botswana</option>
      <option value="br" data-country="Brazil">br — Brazil</option>
      <option value="bn" data-country="Brunei">bn — Brunei</option>
      <option value="bg" data-country="Bulgaria">bg — Bulgaria</option>
      <option value="bf" data-country="Burkina Faso">bf — Burkina Faso</option>
      <option value="bi" data-country="Burundi">bi — Burundi</option>
      <option value="cv" data-country="Cabo Verde">cv — Cabo Verde</option>
      <option value="kh" data-country="Cambodia">kh — Cambodia</option>
      <option value="cm" data-country="Cameroon">cm — Cameroon</option>
      <option value="ca" data-country="Canada">ca — Canada</option>
      <option value="cf" data-country="Central African Republic">cf — Central African Republic</option>
      <option value="td" data-country="Chad">td — Chad</option>
      <option value="cl" data-country="Chile">cl — Chile</option>
      <option value="cn" data-country="China">cn — China</option>
      <option value="co" data-country="Colombia">co — Colombia</option>
      <option value="km" data-country="Comoros">km — Comoros</option>
      <option value="cg" data-country="Congo">cg — Congo</option>
      <option value="cd" data-country="Congo (DRC)">cd — Congo (DRC)</option>
      <option value="cr" data-country="Costa Rica">cr — Costa Rica</option>
      <option value="ci" data-country="Côte d'Ivoire">ci — Côte d'Ivoire</option>
      <option value="hr" data-country="Croatia">hr — Croatia</option>
      <option value="cu" data-country="Cuba">cu — Cuba</option>
      <option value="cy" data-country="Cyprus">cy — Cyprus</option>
      <option value="cz" data-country="Czech Republic">cz — Czech Republic</option>
      <option value="dk" data-country="Denmark">dk — Denmark</option>
      <option value="dj" data-country="Djibouti">dj — Djibouti</option>
      <option value="dm" data-country="Dominica">dm — Dominica</option>
      <option value="do" data-country="Dominican Republic">do — Dominican Republic</option>
      <option value="ec" data-country="Ecuador">ec — Ecuador</option>
      <option value="eg" data-country="Egypt">eg — Egypt</option>
      <option value="sv" data-country="El Salvador">sv — El Salvador</option>
      <option value="gq" data-country="Equatorial Guinea">gq — Equatorial Guinea</option>
      <option value="er" data-country="Eritrea">er — Eritrea</option>
      <option value="ee" data-country="Estonia">ee — Estonia</option>
      <option value="sz" data-country="Eswatini">sz — Eswatini</option>
      <option value="et" data-country="Ethiopia">et — Ethiopia</option>
      <option value="fj" data-country="Fiji">fj — Fiji</option>
      <option value="fi" data-country="Finland">fi — Finland</option>
      <option value="fr" data-country="France">fr — France</option>
      <option value="ga" data-country="Gabon">ga — Gabon</option>
      <option value="gm" data-country="Gambia">gm — Gambia</option>
      <option value="ge" data-country="Georgia">ge — Georgia</option>
      <option value="de" data-country="Germany">de — Germany</option>
      <option value="gh" data-country="Ghana">gh — Ghana</option>
      <option value="gr" data-country="Greece">gr — Greece</option>
      <option value="gd" data-country="Grenada">gd — Grenada</option>
      <option value="gt" data-country="Guatemala">gt — Guatemala</option>
      <option value="gn" data-country="Guinea">gn — Guinea</option>
      <option value="gw" data-country="Guinea-Bissau">gw — Guinea-Bissau</option>
      <option value="gy" data-country="Guyana">gy — Guyana</option>
      <option value="ht" data-country="Haiti">ht — Haiti</option>
      <option value="hn" data-country="Honduras">hn — Honduras</option>
      <option value="hu" data-country="Hungary">hu — Hungary</option>
      <option value="is" data-country="Iceland">is — Iceland</option>
      <option value="in" data-country="India">in — India</option>
      <option value="id" data-country="Indonesia">id — Indonesia</option>
      <option value="ir" data-country="Iran">ir — Iran</option>
      <option value="iq" data-country="Iraq">iq — Iraq</option>
      <option value="ie" data-country="Ireland">ie — Ireland</option>
      <option value="il" data-country="Israel">il — Israel</option>
      <option value="it" data-country="Italy">it — Italy</option>
      <option value="jm" data-country="Jamaica">jm — Jamaica</option>
      <option value="jp" data-country="Japan">jp — Japan</option>
      <option value="jo" data-country="Jordan">jo — Jordan</option>
      <option value="kz" data-country="Kazakhstan">kz — Kazakhstan</option>
      <option value="ke" data-country="Kenya">ke — Kenya</option>
      <option value="ki" data-country="Kiribati">ki — Kiribati</option>
      <option value="kp" data-country="North Korea">kp — North Korea</option>
      <option value="kr" data-country="South Korea">kr — South Korea</option>
      <option value="kw" data-country="Kuwait">kw — Kuwait</option>
      <option value="kg" data-country="Kyrgyzstan">kg — Kyrgyzstan</option>
      <option value="la" data-country="Laos">la — Laos</option>
      <option value="lv" data-country="Latvia">lv — Latvia</option>
      <option value="lb" data-country="Lebanon">lb — Lebanon</option>
      <option value="ls" data-country="Lesotho">ls — Lesotho</option>
      <option value="lr" data-country="Liberia">lr — Liberia</option>
      <option value="ly" data-country="Libya">ly — Libya</option>
      <option value="li" data-country="Liechtenstein">li — Liechtenstein</option>
      <option value="lt" data-country="Lithuania">lt — Lithuania</option>
      <option value="lu" data-country="Luxembourg">lu — Luxembourg</option>
      <option value="mg" data-country="Madagascar">mg — Madagascar</option>
      <option value="mw" data-country="Malawi">mw — Malawi</option>
      <option value="my" data-country="Malaysia">my — Malaysia</option>
      <option value="mv" data-country="Maldives">mv — Maldives</option>
      <option value="ml" data-country="Mali">ml — Mali</option>
      <option value="mt" data-country="Malta">mt — Malta</option>
      <option value="mh" data-country="Marshall Islands">mh — Marshall Islands</option>
      <option value="mr" data-country="Mauritania">mr — Mauritania</option>
      <option value="mu" data-country="Mauritius">mu — Mauritius</option>
      <option value="mx" data-country="Mexico">mx — Mexico</option>
      <option value="fm" data-country="Micronesia">fm — Micronesia</option>
      <option value="md" data-country="Moldova">md — Moldova</option>
      <option value="mc" data-country="Monaco">mc — Monaco</option>
      <option value="mn" data-country="Mongolia">mn — Mongolia</option>
      <option value="me" data-country="Montenegro">me — Montenegro</option>
      <option value="ma" data-country="Morocco">ma — Morocco</option>
      <option value="mz" data-country="Mozambique">mz — Mozambique</option>
      <option value="mm" data-country="Myanmar">mm — Myanmar</option>
      <option value="na" data-country="Namibia">na — Namibia</option>
      <option value="nr" data-country="Nauru">nr — Nauru</option>
      <option value="np" data-country="Nepal">np — Nepal</option>
      <option value="nl" data-country="Netherlands">nl — Netherlands</option>
      <option value="nz" data-country="New Zealand">nz — New Zealand</option>
      <option value="ni" data-country="Nicaragua">ni — Nicaragua</option>
      <option value="ne" data-country="Niger">ne — Niger</option>
      <option value="ng" data-country="Nigeria">ng — Nigeria</option>
      <option value="mk" data-country="North Macedonia">mk — North Macedonia</option>
      <option value="no" data-country="Norway">no — Norway</option>
      <option value="om" data-country="Oman">om — Oman</option>
      <option value="pk" data-country="Pakistan">pk — Pakistan</option>
      <option value="pw" data-country="Palau">pw — Palau</option>
      <option value="ps" data-country="Palestine">ps — Palestine</option>
      <option value="pa" data-country="Panama">pa — Panama</option>
      <option value="pg" data-country="Papua New Guinea">pg — Papua New Guinea</option>
      <option value="py" data-country="Paraguay">py — Paraguay</option>
      <option value="pe" data-country="Peru">pe — Peru</option>
      <option value="ph" data-country="Philippines">ph — Philippines</option>
      <option value="pl" data-country="Poland">pl — Poland</option>
      <option value="pt" data-country="Portugal">pt — Portugal</option>
      <option value="qa" data-country="Qatar">qa — Qatar</option>
      <option value="ro" data-country="Romania">ro — Romania</option>
      <option value="ru" data-country="Russia">ru — Russia</option>
      <option value="rw" data-country="Rwanda">rw — Rwanda</option>
      <option value="kn" data-country="Saint Kitts and Nevis">kn — Saint Kitts and Nevis</option>
      <option value="lc" data-country="Saint Lucia">lc — Saint Lucia</option>
      <option value="vc" data-country="Saint Vincent and the Grenadines">vc — Saint Vincent and the Grenadines</option>
      <option value="ws" data-country="Samoa">ws — Samoa</option>
      <option value="sm" data-country="San Marino">sm — San Marino</option>
      <option value="st" data-country="São Tomé and Príncipe">st — São Tomé and Príncipe</option>
      <option value="sa" data-country="Saudi Arabia">sa — Saudi Arabia</option>
      <option value="sn" data-country="Senegal">sn — Senegal</option>
      <option value="rs" data-country="Serbia">rs — Serbia</option>
      <option value="sc" data-country="Seychelles">sc — Seychelles</option>
      <option value="sl" data-country="Sierra Leone">sl — Sierra Leone</option>
      <option value="sg" data-country="Singapore">sg — Singapore</option>
      <option value="sk" data-country="Slovakia">sk — Slovakia</option>
      <option value="si" data-country="Slovenia">si — Slovenia</option>
      <option value="sb" data-country="Solomon Islands">sb — Solomon Islands</option>
      <option value="so" data-country="Somalia">so — Somalia</option>
      <option value="za" data-country="South Africa">za — South Africa</option>
      <option value="ss" data-country="South Sudan">ss — South Sudan</option>
      <option value="es" data-country="Spain">es — Spain</option>
      <option value="lk" data-country="Sri Lanka">lk — Sri Lanka</option>
      <option value="sd" data-country="Sudan">sd — Sudan</option>
      <option value="sr" data-country="Suriname">sr — Suriname</option>
      <option value="se" data-country="Sweden">se — Sweden</option>
      <option value="ch" data-country="Switzerland">ch — Switzerland</option>
      <option value="sy" data-country="Syria">sy — Syria</option>
      <option value="tw" data-country="Taiwan">tw — Taiwan</option>
      <option value="tj" data-country="Tajikistan">tj — Tajikistan</option>
      <option value="tz" data-country="Tanzania">tz — Tanzania</option>
      <option value="th" data-country="Thailand">th — Thailand</option>
      <option value="tl" data-country="Timor-Leste">tl — Timor-Leste</option>
      <option value="tg" data-country="Togo">tg — Togo</option>
      <option value="to" data-country="Tonga">to — Tonga</option>
      <option value="tt" data-country="Trinidad and Tobago">tt — Trinidad and Tobago</option>
      <option value="tn" data-country="Tunisia">tn — Tunisia</option>
      <option value="tr" data-country="Turkey">tr — Turkey</option>
      <option value="tm" data-country="Turkmenistan">tm — Turkmenistan</option>
      <option value="tv" data-country="Tuvalu">tv — Tuvalu</option>
      <option value="ug" data-country="Uganda">ug — Uganda</option>
      <option value="ua" data-country="Ukraine">ua — Ukraine</option>
      <option value="ae" data-country="United Arab Emirates">ae — United Arab Emirates</option>
      <option value="gb" data-country="United Kingdom">gb — United Kingdom</option>
      <option value="us" data-country="United States">us — United States</option>
      <option value="uy" data-country="Uruguay">uy — Uruguay</option>
      <option value="uz" data-country="Uzbekistan">uz — Uzbekistan</option>
      <option value="vu" data-country="Vanuatu">vu — Vanuatu</option>
      <option value="va" data-country="Vatican City">va — Vatican City</option>
      <option value="ve" data-country="Venezuela">ve — Venezuela</option>
      <option value="vn" data-country="Vietnam">vn — Vietnam</option>
      <option value="ye" data-country="Yemen">ye — Yemen</option>
      <option value="zm" data-country="Zambia">zm — Zambia</option>
      <option value="zw" data-country="Zimbabwe">zw — Zimbabwe</option>
    </datalist>
    <p class="description">Type a country code or name to search. You can also type a custom code manually.</p>
  </td>
</tr>
<tr><th>State</th><td><input type="text" name="state" value="California" style="min-width:300px;"></td></tr>
<tr><th>City</th><td><input type="text" name="city" value="Los Angeles" style="min-width:300px;"></td></tr>
<tr><th>Service</th><td><input type="text" name="service" value="Digital Marketing Agency" style="min-width:300px;"></td></tr>
<tr><th>Service Keyword</th><td><input type="text" name="service_keyword" value="digital-marketing-agency" style="min-width:300px;"></td></tr>
</table>
<?php submit_button('Generate Page', 'primary', 'generate_page'); ?>
</form>
</div>
<script>
(function(){
    var ccMap = {};
    var opts = document.querySelectorAll('#vcpg_cc_list option');
    for (var i = 0; i < opts.length; i++) {
        ccMap[opts[i].value] = opts[i].getAttribute('data-country');
    }
    var ccInput = document.getElementById('vcpg_country_code');
    var countryInput = document.getElementById('vcpg_country');
    ccInput.addEventListener('input', function(){
        var val = this.value.toLowerCase().trim();
        // If user typed "xx — Country Name", extract just the code
        if (val.indexOf(' — ') !== -1) {
            val = val.split(' — ')[0].trim();
            this.value = val;
        }
        if (ccMap[val]) {
            countryInput.value = ccMap[val];
        }
    });
    ccInput.addEventListener('change', function(){
        var val = this.value.toLowerCase().trim();
        if (val.indexOf(' — ') !== -1) {
            val = val.split(' — ')[0].trim();
            this.value = val;
        }
        if (ccMap[val]) {
            countryInput.value = ccMap[val];
        }
    });
})();
</script>
<?php
}
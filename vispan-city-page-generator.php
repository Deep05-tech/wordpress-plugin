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
    static $cache = array();

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
    if (!$post_id && function_exists('is_singular') && is_singular('page')) {
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

    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }

    // Exclude front page or posts page explicitly by ID
    $front_id = (int) get_option('page_on_front');
    $home_id  = (int) get_option('page_for_posts');
    if (($front_id && (int)$post_id === $front_id) || ($home_id && (int)$post_id === $home_id)) {
        $cache[$post_id] = false;
        return false;
    }

    $post_obj = get_post($post_id);
    if (!$post_obj || $post_obj->post_type !== 'page') {
        $cache[$post_id] = false;
        return false;
    }

    // Explicit blacklist of known built-in website pages: NEVER hijack these
    $built_in_slugs = array(
        'home', '', 'about-us', 'about', 'what-we-do', 'contact-us', 'contact',
        'blog', 'career', 'careers', 'investor', 'financial-reporting',
        'digital-marketing-services', 'google-ads-services-in-india',
        'branding-services', 'seo-services', 'web-development',
        'social-media-management-services', 'online-reputation-management',
        'video-production', 'cgi-services', 'vfx-services-in-rajkot',
        'privacy-policy', 'terms-and-conditions', 'terms-of-service',
        'sample-page'
    );
    if (in_array(strtolower($post_obj->post_name), $built_in_slugs, true)) {
        $cache[$post_id] = false;
        return false;
    }

    // 1. Direct explicit VCPG postmeta check
    if (get_post_meta($post_id, '_vcpg_page', true) === '1') {
        $cache[$post_id] = true;
        return true;
    }

    // 2. Specific VCPG location meta check
    if (get_post_meta($post_id, '_vcpg_city', true) || get_post_meta($post_id, '_vcpg_service', true) || get_post_meta($post_id, '_vcpg_country', true) || get_post_meta($post_id, '_vcpg_state', true) || get_post_meta($post_id, '_vcpg_county', true)) {
        $cache[$post_id] = true;
        return true;
    }

    // 3. Page Template check
    if (get_post_meta($post_id, '_wp_page_template', true) === 'templates/page-template.php') {
        $cache[$post_id] = true;
        return true;
    }

    // CRITICAL: Top-level pages (post_parent == 0) without VCPG postmeta are NEVER generated city pages!
    // They are 100% built-in site pages. Do NOT perform loose content sniffing on root pages.
    if ((int)$post_obj->post_parent === 0) {
        $cache[$post_id] = false;
        return false;
    }

    // 4. Parent page ISO country code check
    // ALL generated city pages (modern and legacy like 48349) are child pages of a country slug ('us', 'in', etc.)
    if ($post_obj->post_parent > 0) {
        $parent = get_post($post_obj->post_parent);
        if ($parent) {
            $known_cc = array('in', 'us', 'uk', 'ca', 'au', 'de', 'fr', 'es', 'it', 'nl', 'br', 'mx', 'za', 'ae', 'sg', 'jp');
            if (in_array(strtolower($parent->post_name), $known_cc, true)) {
                $cache[$post_id] = true;
                return true;
            }
        }
    }

    $cache[$post_id] = false;
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
        // 3. Strip rogue legacy navbar from older pages (phone/email SVG/logo through nav list and LET'S TALK)
        $content = vcpg_safe_preg_replace('/(?:<p[^>]*>\s*)?(?:<svg[^>]*>.*?<\/svg>\s*<a[^>]*href=[\x22\x27]tel:[^>]*>.*?<\/a>.*?)(?:<ul[^>]*style=[\x22\x27][^\x22\x27]*list-style:none[^\x22\x27]*[\x22\x27][^>]*>.*?<\/ul>\s*)(?:<p[^>]*>\s*)?<a[^>]*href=[\x22\x27][^\x22\x27]*contact-us[^\x22\x27]*[\x22\x27][^>]*>.*?LET(?:&#8217;|\x27)S TALK.*?<\/a>(?:\s*<\/p>)?/is', '', $content);
        // Fallbacks for any remaining stray nav list or topbar phone block
        $content = vcpg_safe_preg_replace('/<ul[^>]*style=[\x22\x27][^\x22\x27]*list-style:none[^\x22\x27]*display:flex[^\x22\x27]*[\x22\x27][^>]*>.*?<\/ul>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<p[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<a[^>]*href=[\x22\x27]tel:[^>]*>.*?<\/a>.*?<\/p>/is', '', $content);
        // 4. Strip legacy topbar & header HTML blocks from post_content safely
        $content = vcpg_safe_preg_replace('/<div[^>]*class=["\'][^"\']*vp-topbar[^"\']*["\'][^>]*>.*?<\/div>\s*<\/div>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<header[^>]*class=["\'][^"\']*vp-header[^"\']*["\'][^>]*>.*?<\/header>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<section[^>]*data-id=["\']e000003["\'][^>]*>.*?<\/section>/is', '', $content);
        // 5. Strip legacy footer HTML blocks (both older raw HTML footer and newer section/class footers)
        $content = vcpg_safe_preg_replace('/(?:<p[^>]*>\s*)?(?:<img[^>]*VSPL-Web-Logo\.webp[^>]*>.*?)(?:<p[^>]*>)?\s*Feel free to reach out.*?©\s*202\d\s*Vispan Solutions Pvt\.\s*Ltd\.\s*All rights reserved\.(?:\s*<\/p>)?/is', '', $content);
        $content = vcpg_safe_preg_replace('/<footer[^>]*class=["\'][^"\']*vp-footer[^"\']*["\'][^>]*>.*?<\/footer>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<section[^>]*data-id=["\']e000043["\'][^>]*>.*?<\/section>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<section[^>]*data-id=["\']e000044["\'][^>]*>.*?<\/section>/is', '', $content);
        // 6. Strip any isolated stray fragments and unbalanced closing divs
        $content = vcpg_safe_preg_replace('/<p>\s*<!--\s*VCPG-TEMPLATE.*?-->\s*<!--\s*1\.\s*TOPBAR\s*&\s*HEADER\s*-->\s*<\/p>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<div>\s*Vispan Solutions Pvt\. Ltd\.\s*<\/div>\s*(?:<\/p>)?\s*(?:<\/div>\s*){1,3}/is', '', $content);
        $content = vcpg_safe_preg_replace('/<div>\s*Vispan Solutions Pvt\. Ltd\.\s*<\/div>/is', '', $content);
        // 7. Strip unwanted portfolio section if present in post_content
        $content = vcpg_safe_preg_replace('/<!--\s*8\.\s*PORTFOLIO\s*-->\s*<section[^>]*class=["\'][^"\']*vp-portfolio-sec[^"\']*["\'][^>]*>.*?<\/section>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<section[^>]*class=["\'][^"\']*vp-portfolio-sec[^"\']*["\'][^>]*>.*?<\/section>/is', '', $content);
        // 8. Strip unwanted capsule box above hero header
        $content = vcpg_safe_preg_replace('/<div[^>]*padding:\s*6px\s*16px[^>]*>.*?<\/div>/is', '', $content);
        $content = vcpg_safe_preg_replace('/<div[^>]*class=["\'][^"\']*vp-hero-city-label[^"\']*["\'][^>]*>.*?<\/div>/is', '', $content);
        // 9. Format all legacy sections into modern template structure
        if (strpos($content, 'vp-about-grid') === false) {
            // 9a. Hero Section
            if (strpos($content, 'vp-hero-grid') === false && strpos($content, 'hero_proposal') !== false) {
                $hero_regex = '/(<h1[^>]*>.*?)(<h3[^>]*>Request A Marketing Proposal<\/h3>\s*<form id=[\x22\x27]hero_proposal[\x22\x27].*?<\/form>)/is';
                if (preg_match($hero_regex, $content, $m)) {
                    $video_url = plugins_url('assets/vispan-banner.webm', __FILE__);
                    $video_html = '<div style="position:absolute;top:0;left:0;width:100%;height:100%;overflow:hidden;z-index:0;pointer-events:none;"><video autoplay muted playsinline loop style="position:absolute;top:50%;left:50%;transform:translate(-50%, -50%) scale(1.4);min-width:100%;min-height:100%;width:auto;height:auto;object-fit:cover;opacity:1;"><source src="' . esc_url($video_url) . '" type="video/webm"></video></div>';
                    $left = '<div class="vp-hero-left" style="position:relative;z-index:1;">' . $m[1] . '</div>';
                    $right = '<div class="vp-hero-form-card vp-hero-right" style="position:relative;z-index:1;background:rgba(255,255,255,0.92);border:2px solid #02426A;border-radius:30px;padding:40px 30px;box-shadow:0 10px 30px rgba(0,0,0,0.1);box-sizing:border-box;">' . $m[2] . '</div>';
                    $hero_html = '<section class="vp-hero" style="position:relative;padding:190px 0 90px;overflow:hidden;background:#FFFFFF;">' . $video_html . '<div class="vp-container vp-hero-grid" style="position:relative;z-index:1;">' . $left . $right . '</div></section>';
                    $content = vcpg_safe_preg_replace($hero_regex, $hero_html, $content);
                }
            }

            // 9b. Intro Section
            $intro_regex = '/<h2[^>]*>((?:Get .*?:\s*)?Why Your .*? Needs Online Marketing.*?)<\/h2>\s*<p[^>]*>(.*?)<\/p>/is';
            if (preg_match($intro_regex, $content, $m)) {
                $intro_html = '<section class="vp-section vp-intro" style="background:#FFFFFF;padding:80px 0;text-align:center;">'
                            . '<div class="vp-container">'
                            . '<h2 class="vp-title vp-title-center" style="color:#0A3663;font-size:36px;font-weight:800;line-height:1.25;max-width:920px;margin:0 auto 24px;text-align:center;">' . trim(strip_tags($m[1])) . '</h2>'
                            . '<div class="vp-desc vp-desc-center" style="color:#334155;font-size:16px;line-height:1.8;max-width:960px;margin:0 auto;text-align:center;">' . trim($m[2]) . '</div>'
                            . '</div></section>';
                $content = vcpg_safe_preg_replace($intro_regex, $intro_html, $content);
            }

            // 9c. About / Value Section
            $about_regex = '/(<h2[^>]*>(?:Creating a Strong Online Presence|About).*?<\/h2>)(.*?)(?:<style[^>]*>.*?<\/style>\s*)?(<p>\s*<img[^>]*src=[\x22\x27][^\x22\x27]*section-3\.webp[\x22\x27][^>]*>\s*<\/p>)/is';
            if (preg_match($about_regex, $content, $m)) {
                $about_title = trim(strip_tags($m[1]));
                $middle = $m[2];
                $img_tag = $m[3];
                $img_src = '';
                if (preg_match('/src=[\x22\x27]([^\x22\x27]+)[\x22\x27]/i', $img_tag, $im)) {
                    $img_src = $im[1];
                }
                preg_match_all('/(?:<p>\s*)?(<svg[^>]*>.*?<\/svg>)(?:\s*<\/p>)?\s*<h3[^>]*>(.*?)<\/h3>\s*<p[^>]*>(.*?)<\/p>/is', $middle, $cards, PREG_SET_ORDER);
                $first_svg_pos = strpos($middle, '<svg');
                $intro_paragraphs = ($first_svg_pos !== false) ? substr($middle, 0, $first_svg_pos) : '';

                $cards_html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:32px;">';
                foreach ($cards as $c) {
                    $cards_html .= '<div class="vp-feature-card" style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:18px;padding:28px 24px;box-shadow:0 6px 24px rgba(0,0,0,0.04);display:flex;flex-direction:column;align-items:flex-start;text-align:left;box-sizing:border-box;">'
                                . '<div style="margin-bottom:14px;">' . $c[1] . '</div>'
                                . '<h3 style="margin:0 0 8px;font-size:17.5px;font-weight:700;color:#111827;line-height:1.3;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">' . trim(strip_tags($c[2])) . '</h3>'
                                . '<p style="margin:0;font-size:13.5px;color:#475569;line-height:1.55;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">' . trim(strip_tags($c[3])) . '</p>'
                                . '</div>';
                }
                $cards_html .= '</div>';

                $about_html = '<section class="vp-section vp-about" id="about" style="background:#FFFFFF;padding:80px 0;">'
                            . '<div class="vp-container vp-about-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;">'
                            . '<div>'
                            . '<h2 class="vp-title" style="color:#0A3663;font-size:2.2rem;font-weight:800;line-height:1.25;margin-bottom:16px;">' . $about_title . '</h2>'
                            . '<div class="vp-desc" style="margin-bottom:24px;color:#334155;font-size:1rem;line-height:1.8;">'
                            . $intro_paragraphs
                            . '<p style="margin-top:15px; font-size:14px; color:#475569;">'
                            . 'Learn more about industry standards on <a href="https://en.wikipedia.org/wiki/Digital_marketing" target="_blank" rel="noopener noreferrer" style="color:#0B63F6; text-decoration:underline;">Wikipedia</a> or consult the <a href="https://www.w3.org/" target="_blank" rel="noopener noreferrer" style="color:#0B63F6; text-decoration:underline;">W3C Web Standards</a>.'
                            . '</p></div>'
                            . $cards_html
                            . '</div>'
                            . '<div><img src="' . esc_url($img_src) . '" alt="About" style="width:100%;border-radius:24px;box-shadow:0 10px 30px rgba(0,0,0,0.06);display:block;object-fit:cover;"></div>'
                            . '</div></section>';

                $content = vcpg_safe_preg_replace($about_regex, $about_html, $content);
            }

            // 9d. Services Section
            $services_regex = '/(<h2[^>]*>(?:Services of|Our Services).*?<\/h2>)(.*?)(?=<h2[^>]*>(?:Distinct Advantages|Why Choose))/is';
            if (preg_match($services_regex, $content, $m)) {
                $svc_title = trim(strip_tags($m[1]));
                $svc_body = $m[2];
                $svc_desc = '';
                if (preg_match('/^(\s*<p[^>]*>.*?<\/p>)/is', $svc_body, $pm)) {
                    $svc_desc = trim($pm[1]);
                    $svc_items_raw = substr($svc_body, strlen($pm[0]));
                } else {
                    $svc_items_raw = $svc_body;
                }
                preg_match_all('/(?:<p>\s*)?(<svg[^>]*>.*?<\/svg>)(?:\s*<\/p>)?\s*<h3[^>]*>(.*?)<\/h3>\s*(?:<p[^>]*>(.*?)<\/p>\s*)?<p[^>]*>(.*?)<\/p>/is', $svc_items_raw, $services, PREG_SET_ORDER);

                $services_cards_html = '';
                foreach ($services as $s) {
                    $stitle = trim(strip_tags($s[2]));
                    $ssub   = isset($s[3]) ? trim(strip_tags($s[3])) : '';
                    $sdesc  = trim(strip_tags($s[4]));

                    $services_cards_html .= '<div class="vp-service-card" style="background:#FFFFFF;border-radius:16px;padding:36px 32px;box-shadow:0 10px 30px rgba(0,0,0,0.06);display:flex;flex-direction:column;text-align:left;height:100%;box-sizing:border-box;">'
                                         . '<div style="margin-bottom:16px;">' . $s[1] . '</div>'
                                         . '<h3 style="margin:0 0 6px;font-size:20px;font-weight:700;color:#000000;">' . $stitle . '</h3>'
                                         . ($ssub !== '' ? '<div style="font-size:14px;font-weight:700;color:#1E293B;margin-bottom:12px;">' . $ssub . '</div>' : '')
                                         . '<p style="color:#475569;font-size:14px;line-height:1.65;margin:0;">' . $sdesc . '</p>'
                                         . '</div>';
                }

                $services_html = '<section class="vp-section vp-services-sec" id="services" style="background:#F8FAFC;padding:90px 0;">'
                               . '<div class="vp-container">'
                               . '<h2 class="vp-title vp-title-center" style="color:#0A3663;text-align:center;font-size:2.2rem;font-weight:800;line-height:1.25;margin-bottom:16px;">' . $svc_title . '</h2>'
                               . ($svc_desc !== '' ? '<div class="vp-desc vp-desc-center" style="margin:0 auto 40px;color:#334155;text-align:center;max-width:800px;font-size:1rem;line-height:1.8;">' . $svc_desc . '</div>' : '')
                               . '<div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:24px;">'
                               . $services_cards_html
                               . '</div></div></section>';

                $content = vcpg_safe_preg_replace($services_regex, $services_html, $content);
            }

            // 9e. Advantages / Why Choose Section
            $advantages_regex = '/(<h2[^>]*>(?:Distinct Advantages|Why Choose).*?<\/h2>)(.*?)(?=<h2[^>]*>Ready to get started)/is';
            if (preg_match($advantages_regex, $content, $m)) {
                $adv_html = '<section class="vp-section vp-why-sec" style="background:#FFFFFF;padding:90px 0;">'
                          . '<div class="vp-container">'
                          . $m[1] . $m[2]
                          . '</div></section>';
                $content = vcpg_safe_preg_replace($advantages_regex, $adv_html, $content);
            }

            // 9f. CTA Section ("Ready to get started?")
            $cta_regex = '/<h2[^>]*>Ready to get started\?<\/h2>(.*?)(?=(?:<p>\s*)?<img[^>]*src=[\x22\x27][^\x22\x27]*case-study|<h2[^>]*>Case Study:)/is';
            if (preg_match($cta_regex, $content, $m)) {
                $cta_body = $m[1];
                $cta_img = '';
                if (preg_match('/<img[^>]*src=[\x22\x27]([^\x22\x27]*section-5\.[^\x22\x27]*)[\x22\x27][^>]*>/is', $cta_body, $im)) {
                    $cta_img = $im[1];
                }
                $cta_text = '';
                if (preg_match('/<p[^>]*>(.*?)(?:<\/p>|<a\s+href=[\x22\x27]#contact)/is', $cta_body, $tm)) {
                    $cta_text = trim(strip_tags($tm[1]));
                }

                $cta_html = '<section class="vp-section" style="background:#FFFFFF;padding:80px 0;">'
                          . '<div class="vp-container vp-about-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;">'
                          . '<div>'
                          . '<h3 class="vp-title" style="color:#0A3663;font-size:2.2rem;font-weight:800;line-height:1.25;margin-bottom:16px;">Ready to get started?</h3>'
                          . '<div class="vp-desc" style="margin-bottom:30px;color:#334155;font-size:1rem;line-height:1.8;">'
                          . '<p>' . $cta_text . '</p>'
                          . '<p style="margin-top:15px; font-size:14px;">'
                          . 'Explore our <a href="#services" style="color:#0A3663; text-decoration:underline; font-weight:700;">expert services</a> or read about our <a href="#about" style="color:#0A3663; text-decoration:underline; font-weight:700;">company background</a>.'
                          . '</p></div>'
                          . '<a href="#contact" class="vp-btn-hero" style="background:#0A3663;color:#FFFFFF;padding:14px 32px;border-radius:50px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">Claim Your Free Audit</a>'
                          . '</div>'
                          . '<div><img src="' . esc_url($cta_img) . '" alt="Get Started" style="width:100%;border-radius:24px;display:block;object-fit:cover;"></div>'
                          . '</div></section>';

                $content = vcpg_safe_preg_replace($cta_regex, $cta_html, $content);
            }

            // 9g. Case Study Section
            $cs_regex = '/(?:<p>\s*)?(<img[^>]*src=[\x22\x27][^\x22\x27]*case-study\.[^\x22\x27]*[\x22\x27][^>]*>)(?:\s*<\/p>)?\s*(<h[23][^>]*>Case Study:.*?<\/h[23]>)(.*?)(?=(?:<div[^>]*class=[\x22\x27][^\x22\x27]*marquee|<p>\s*<img[^>]*app-store|<h2[^>]*>What Clients Say))/is';
            if (preg_match($cs_regex, $content, $m)) {
                $cs_img_tag = $m[1];
                $cs_title = $m[2];
                $cs_body = $m[3];

                $cs_html = '<section class="vp-section vp-casestudy-sec" style="background:#FFFFFF;padding:90px 0;">'
                         . '<div class="vp-container" style="padding:0;">'
                         . '<div class="vp-casestudy-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:50px;align-items:stretch;">'
                         . '<div style="order:1;display:flex;height:100%;">' . $cs_img_tag . '</div>'
                         . '<div style="order:2;">' . $cs_title . $cs_body . '</div>'
                         . '</div></div></section>';

                $content = vcpg_safe_preg_replace($cs_regex, $cs_html, $content);
            }

            // 9h. Partner Logos Bar
            $logos_regex = '/(?:<div[^>]*class=[\x22\x27][^\x22\x27]*marquee-container.*?<\/div>\s*<\/div>|<p>\s*(?:<img[^>]*src=[\x22\x27][^\x22\x27]*(?:clickfunnels|app-store|Twitter|snapchat|WooCommerce|mad-mimi|WordPress|Customer-Data|LinkedIn|Google|YouTube|Instagram|Facebook|Google-Ads)[^\x22\x27]*[\x22\x27][^>]*>\s*)+<\/p>)/is';
            if (preg_match($logos_regex, $content, $m)) {
                $logo_images = array(
                    array('alt' => 'App Store & Google Play', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/555-5550859_app-store-icons-01-logo-google-play-store-removebg-preview-300x145.png'),
                    array('alt' => 'ClickFunnels', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/clickfunnels-removebg-preview-e1680065435819-300x46.png'),
                    array('alt' => 'Twitter', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/Twitter-Logo-2010-removebg-preview-e1680065450167-300x125.png'),
                    array('alt' => 'Snapchat Ads', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/52-522810_1048-x-550-19-snapchat-ads-logo-png-removebg-preview-e1680065312976-300x94.png'),
                    array('alt' => 'WooCommerce', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/WooCommerce-removebg-preview-e1680065348919-300x63.png'),
                    array('alt' => 'Mad Mimi', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/mad-mimi-300-removebg-preview-e1680065393990.png'),
                    array('alt' => 'WordPress', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/WordPress-Logo.wine-removebg-preview-e1680065298338-300x80.png'),
                    array('alt' => 'Customer Data Platform', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/png-transparent-business-customer-data-platform-marketing-market-segmentation-logo-business-text-trademark-people-removebg-preview-768x165-1-300x64.png'),
                    array('alt' => 'LinkedIn', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/hd-linkedin-official-logo-transparent-background-citypng-removebg-preview-768x243-1-300x95.png'),
                    array('alt' => 'Google', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/82035-logo-google-text-free-transparent-image-hd-removebg-preview-300x180.png'),
                    array('alt' => 'YouTube', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/png-transparent-google-logo-youtube-youtuber-youtube-rewind-text-area-line-removebg-preview-768x279-1-300x109.png'),
                    array('alt' => 'Instagram', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/184-1847294_ai-instagram-hd-png-download-removebg-preview-768x215-1-300x84.png'),
                    array('alt' => 'Facebook', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/Logo-Facebook-Png-1024x1024-removebg-preview-e1680063077617-300x92.png'),
                    array('alt' => 'Google Ads', 'src' => 'https://vispansolutions.com/wp-content/uploads/2025/02/Google-Ads-Logo-PNG-768x135-1-300x53.png'),
                );
                $logos_set = '';
                foreach($logo_images as $li) {
                    $logos_set .= '<div style="display:inline-flex;align-items:center;justify-content:center;margin-right:50px;height:50px;box-sizing:border-box;flex-shrink:0;">'
                                . '<img decoding="async" src="' . esc_url($li['src']) . '" alt="' . esc_attr($li['alt']) . '" style="max-height:42px;max-width:160px;width:auto;height:auto;object-fit:contain;display:block;">'
                                . '</div>';
                }
                $marquee_html = '<div class="vp-logos-bar" style="padding:40px 0;background:#FFFFFF;border-top:1px solid #E2E8F0;border-bottom:1px solid #E2E8F0;">'
                              . '<div class="vcpg-marquee-container" style="width:100%;overflow:hidden;position:relative;padding:16px 0;mask-image:linear-gradient(to right, transparent, black 6%, black 94%, transparent);-webkit-mask-image:linear-gradient(to right, transparent, black 6%, black 94%, transparent);">'
                              . '<style>'
                              . '@keyframes vcpgMarqueeScroll { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }'
                              . '.vcpg-marquee-track { display: flex; align-items: center; width: max-content; animation: vcpgMarqueeScroll 35s linear infinite; }'
                              . '.vcpg-marquee-container:hover .vcpg-marquee-track { animation-play-state: paused; }'
                              . '</style>'
                              . '<div class="vcpg-marquee-track">' . $logos_set . $logos_set . '</div>'
                              . '</div></div>';

                $content = vcpg_safe_preg_replace($logos_regex, $marquee_html, $content);
            }

            // 9i. Testimonials Section
            $testi_regex = '/<h2[^>]*>What Clients Say<\/h2>.*?(?:<button[^>]*aria-label=[\x22\x27]Next review[\x22\x27][^>]*>&#10095;<\/button>(?:\s*<br\s*\/?>)?|&#10095;<\/button>)/is';
            if (preg_match($testi_regex, $content, $m)) {
                $reviews = array(
                    array('text' => 'I recently worked with Vispan Solutions to develop a new website for my business, and I could not be more pleased with the results. The team at Vispan Solutions incorporated my vision into their design perfectly and was incredibly responsive to my requests.', 'author' => 'RONIT SHARMA', 'title' => 'CEO'),
                    array('text' => 'Vispan Solutions is the best web development company for e-commerce. Our customers have seen immense success in their projects due to our industry-leading expertise and commitment to customer service.', 'author' => 'MEGHNA JADAV', 'title' => 'CEO'),
                    array('text' => 'I recently needed help with developing a new website in Shopify and knew that I wanted to work with an experienced team. After researching on the web, I came across Vispan Solutions. Their extensive portfolio of projects made it clear that they would be a great choice for me.', 'author' => 'EMERSON STRAW', 'title' => 'CEO'),
                );
                $slides_list = $reviews;
                $slides_list[] = $reviews[0];
                $uid = 'vcpg_t_' . uniqid();

                $carousel_html = '<div id="' . $uid . '" style="max-width:850px;margin:0 auto;padding:10px 20px 40px;position:relative;font-family:inherit;">'
                               . '<div style="text-align:center;font-size:3rem;font-family:Georgia,serif;font-weight:900;color:#0F172A;line-height:1;margin-bottom:16px;">&#8221;</div>'
                               . '<div style="display:flex;align-items:center;justify-content:space-between;gap:20px;">'
                               . '<button class="vcpg-t-prev" aria-label="Previous review" style="width:48px;height:48px;border-radius:50%;border:1px solid #E2E8F0;background:#FFFFFF;box-shadow:0 4px 14px rgba(0,0,0,0.06);cursor:pointer;display:flex;align-items:center;justify-content:center;color:#0F172A;font-size:1.1rem;flex-shrink:0;transition:all 0.2s ease;outline:none;" onmouseover="this.style.background=\'#F8FAFC\';this.style.transform=\'scale(1.05)\';" onmouseout="this.style.background=\'#FFFFFF\';this.style.transform=\'scale(1)\';">&#10094;</button>'
                               . '<div style="flex-grow:1;max-width:680px;text-align:center;overflow:hidden;position:relative;min-height:160px;display:flex;align-items:center;">'
                               . '<div class="vcpg-t-track" style="display:flex;width:100%;transition:transform 0.5s cubic-bezier(0.25, 1, 0.5, 1);will-change:transform;">';

                foreach ($slides_list as $r) {
                    $carousel_html .= '<div class="vcpg-t-slide" style="width:100%;flex-shrink:0;box-sizing:border-box;padding:0 10px;text-align:center;">'
                                    . '<p style="font-size:1.08rem;color:#121212;line-height:1.75;font-style:italic;margin:0 0 24px 0;font-weight:400;">' . esc_html($r['text']) . '</p>'
                                    . '<div style="font-weight:800;color:#0A3663;font-size:1rem;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">' . esc_html($r['author']) . '</div>'
                                    . '<div style="font-size:0.85rem;color:#64748B;font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">' . esc_html($r['title']) . '</div>'
                                    . '</div>';
                }
                $carousel_html .= '</div></div>'
                                . '<button class="vcpg-t-next" aria-label="Next review" style="width:48px;height:48px;border-radius:50%;border:1px solid #E2E8F0;background:#FFFFFF;box-shadow:0 4px 14px rgba(0,0,0,0.06);cursor:pointer;display:flex;align-items:center;justify-content:center;color:#0F172A;font-size:1.1rem;flex-shrink:0;transition:all 0.2s ease;outline:none;" onmouseover="this.style.background=\'#F8FAFC\';this.style.transform=\'scale(1.05)\';" onmouseout="this.style.background=\'#FFFFFF\';this.style.transform=\'scale(1)\';">&#10095;</button>'
                                . '</div>'
                                . '<script>'
                                . '(function(){ var root = document.getElementById("' . $uid . '"); if(!root) return; var track = root.querySelector(".vcpg-t-track"); var prevBtn = root.querySelector(".vcpg-t-prev"); var nextBtn = root.querySelector(".vcpg-t-next"); var current = 0; var realTotal = 3; var isAnimating = false; var timer = null; function goToSlide(index, animate) { if(animate) { track.style.transition = "transform 0.5s cubic-bezier(0.25, 1, 0.5, 1)"; } else { track.style.transition = "none"; } track.style.transform = "translateX(-" + (index * 100) + "%)"; current = index; } function nextSlide() { if(isAnimating) return; isAnimating = true; current++; goToSlide(current, true); if(current === realTotal) { setTimeout(function(){ goToSlide(0, false); isAnimating = false; }, 500); } else { setTimeout(function(){ isAnimating = false; }, 500); } } function prevSlide() { if(isAnimating) return; isAnimating = true; if(current === 0) { goToSlide(realTotal, false); setTimeout(function(){ current = realTotal - 1; goToSlide(current, true); setTimeout(function(){ isAnimating = false; }, 500); }, 20); } else { current--; goToSlide(current, true); setTimeout(function(){ isAnimating = false; }, 500); } } function startTimer() { stopTimer(); timer = setInterval(nextSlide, 3000); } function stopTimer() { if(timer) clearInterval(timer); } if(prevBtn) prevBtn.addEventListener("click", function() { prevSlide(); startTimer(); }); if(nextBtn) nextBtn.addEventListener("click", function() { nextSlide(); startTimer(); }); root.addEventListener("mouseenter", stopTimer); root.addEventListener("mouseleave", startTimer); startTimer(); })();'
                                . '</script></div>';

                $testi_html = '<section class="vp-section vp-testi-sec" style="background:#FFFFFF;padding:80px 0;text-align:center;">'
                            . '<div class="vp-container">'
                            . '<h3 class="vp-title vp-title-center" style="color:#0A3663;text-align:center;margin-bottom:24px;">What Clients Say</h3>'
                            . $carousel_html
                            . '</div></section>';

                $content = vcpg_safe_preg_replace($testi_regex, $testi_html, $content);
            }

            // 9j. Certifications Section
            $cert_regex = '/(?:<p[^>]*>\s*)?<a[^>]*href=[\x22\x27][^\x22\x27]*google\.com\/partners.*?Google Ads Display Certification\s*<\/p>/is';
            if (preg_match($cert_regex, $content, $m)) {
                $cert_html = '<section class="vp-section vp-cert-sec" style="background:#F8FAFC;padding:80px 0;text-align:center;">'
                           . '<div class="vp-container">'
                           . '<h3 class="vp-title vp-title-center" style="margin-bottom:30px;color:#0A3663;text-align:center;">Certifications</h3>'
                           . '<div style="max-width:1180px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:48px;box-sizing:border-box;padding:10px 0;width:100%;">'
                           . '<div style="flex-shrink:0;">'
                           . '<a href="https://www.google.com/partners/agency/partner/" target="_blank" rel="noopener" style="display:block;text-decoration:none;" aria-label="Google Partner Page">'
                           . '<img src="https://vispansolutions.com/wp-content/plugins/vispan-city-page-generator/assets/google-partner.png" alt="Google Partner" style="width:205px;height:auto;display:block;">'
                           . '</a></div>'
                           . '<div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:28px 36px;align-items:center;flex:1;">'
                           . '<div style="display:flex;align-items:center;gap:14px;"><img src="https://vispansolutions.com/wp-content/plugins/vispan-city-page-generator/assets/google-analytics..webp" alt="Google Analytics" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;"><div style="font-weight:600;font-size:0.92rem;color:#222222;line-height:1.35;">Google Analytics</div></div>'
                           . '<div style="display:flex;align-items:center;gap:14px;"><img src="https://vispansolutions.com/wp-content/plugins/vispan-city-page-generator/assets/google-ad-video.png" alt="Google Ads Video Certification" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;"><div style="font-weight:600;font-size:0.92rem;color:#222222;line-height:1.35;">Google Ads Video Certification</div></div>'
                           . '<div style="display:flex;align-items:center;gap:14px;"><img src="https://vispansolutions.com/wp-content/plugins/vispan-city-page-generator/assets/shopping-ad-creation.png" alt="Shopping Ads Certification" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;"><div style="font-weight:600;font-size:0.92rem;color:#222222;line-height:1.35;">Shopping Ads Certification</div></div>'
                           . '<div style="display:flex;align-items:center;gap:14px;"><img src="https://vispansolutions.com/wp-content/plugins/vispan-city-page-generator/assets/google-ad-search.webp" alt="Google Ads Search Certification" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;"><div style="font-weight:600;font-size:0.92rem;color:#222222;line-height:1.35;">Google Ads Search Certification</div></div>'
                           . '<div style="display:flex;align-items:center;gap:14px;"><img src="https://vispansolutions.com/wp-content/plugins/vispan-city-page-generator/assets/google-ad-measurment.webp" alt="Google Ads – Measurement Certification" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;"><div style="font-weight:600;font-size:0.92rem;color:#222222;line-height:1.35;">Google Ads – Measurement Certification</div></div>'
                           . '<div style="display:flex;align-items:center;gap:14px;"><img src="https://vispansolutions.com/wp-content/plugins/vispan-city-page-generator/assets/google-display-ad.png" alt="Google Ads Display Certification" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;"><div style="font-weight:600;font-size:0.92rem;color:#222222;line-height:1.35;">Google Ads Display Certification</div></div>'
                           . '</div></div></div></section>';

                $content = vcpg_safe_preg_replace($cert_regex, $cert_html, $content);
            }

            // 9k. Bottom Proposal Form
            $contact_regex = '/(<h2[^>]*>Request A Marketing Proposal<\/h2>\s*<form id=[\x22\x27]contact_proposal[\x22\x27].*?<\/form>)/is';
            if (preg_match($contact_regex, $content, $m)) {
                $contact_html = '<section class="vp-section vp-contact-sec" id="contact" style="background:#070D18;padding:90px 0;">'
                              . '<div class="vp-container">'
                              . '<div class="vp-contact-card" style="max-width:760px;margin:0 auto;background:#FFFFFF;border-radius:20px;padding:44px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">'
                              . $m[1]
                              . '</div></div></section>';
                $content = vcpg_safe_preg_replace($contact_regex, $contact_html, $content);
            }
        }
    }
    return $content;
}

function vcpg_output_styles()
{
    static $already_output = false;
    if ($already_output) {
        return;
    }

    if (!function_exists('is_singular') || !is_singular('page') || !is_vcpg_generated_page()) {
        return; // ZERO CSS output on built-in website pages!
    }

    $already_output = true;

    if(!empty($GLOBALS['vcpg_inline_styles']))
    {
        // Clean conflicting header position rules from captured inline styles
        $clean_captured = preg_replace('/\.elementor-element-e000003\s*\{[^}]*\}/i', '', $GLOBALS['vcpg_inline_styles']);
        echo $clean_captured;
    }

    echo '<style id="vcpg-brand-overrides">
    /* Hide legacy custom template header & footer to display single Elementor theme header & footer */
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
    html body.vcpg-page {
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

    html body.vcpg-page .vp-container { max-width: 1200px !important; margin: 0 auto !important; padding: 0 24px !important; width: 100% !important; box-sizing: border-box !important; }
    html body.vcpg-page .vp-section { padding: 80px 0 !important; }
    html body.vcpg-page .vp-title { font-size: 2.2rem !important; font-weight: 800 !important; color: #0A3663 !important; line-height: 1.25 !important; margin-bottom: 16px !important; }
    html body.vcpg-page .vp-desc { font-size: 1rem !important; color: #334155 !important; line-height: 1.8 !important; max-width: 800px !important; }
    html body.vcpg-page .vp-title-center { text-align: center !important; }
    html body.vcpg-page .vp-desc-center { margin-left: auto !important; margin-right: auto !important; text-align: center !important; }

    /* HERO */
    html body.vcpg-page .vp-hero { position: relative !important; padding: 190px 0 90px !important; color: #000000 !important; overflow: hidden !important; background: #FFFFFF !important; }
    html body.vcpg-page .vp-hero video { transform: translate(-50%, -50%) scale(1.4) !important; }
    html body.vcpg-page .vp-hero-grid { display: grid !important; grid-template-columns: 1fr 480px !important; gap: 60px !important; align-items: center !important; position: relative !important; z-index: 1 !important; }
    html body.vcpg-page .vp-hero h1 { font-size: 3.2rem !important; font-weight: 800 !important; line-height: 1.15 !important; margin-bottom: 10px !important; color: #02426A !important; }
    html body.vcpg-page .vp-hero h2 { color: #000000 !important; font-size: 1.6rem !important; font-weight: 500 !important; margin-bottom: 20px !important; line-height: 1.3 !important; }
    html body.vcpg-page .vp-hero p { font-size: 1.1rem !important; color: #334155 !important; line-height: 1.65 !important; margin-bottom: 30px !important; }
    html body.vcpg-page .vp-btn-hero { background: #02426A !important; color: #FFFFFF !important; padding: 14px 32px !important; border-radius: 50px !important; text-decoration: none !important; font-weight: 700 !important; font-size: 0.95rem !important; display: inline-flex !important; align-items: center !important; gap: 10px !important; }

    /* Hero inquiry form card border */
    html body.vcpg-page .vp-hero-form-card,
    html body.vcpg-page .vp-hero-right {
        background: rgba(255, 255, 255, 0.92) !important;
        border: 2px solid #02426A !important;
        border-radius: 30px !important;
        padding: 35px 30px !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
        box-sizing: border-box !important;
    }
    html body.vcpg-page .vp-hero-form-card h3,
    html body.vcpg-page .vp-hero-right h3 {
        font-size: 28px !important;
        font-weight: 700 !important;
        margin-bottom: 24px !important;
        text-align: left !important;
        color: #02426A !important;
    }

    /* Legacy hero left column & typography */
    html body.vcpg-page .vp-hero-left h1 {
        font-size: 3.2rem !important;
        font-weight: 800 !important;
        line-height: 1.15 !important;
        margin-bottom: 15px !important;
        color: #02426A !important;
    }
    html body.vcpg-page .vp-hero-left h3 {
        font-size: 1.5rem !important;
        font-weight: 600 !important;
        color: #0A3663 !important;
        margin-bottom: 20px !important;
        line-height: 1.3 !important;
    }
    html body.vcpg-page .vp-hero-left p {
        font-size: 1.1rem !important;
        color: #334155 !important;
        line-height: 1.65 !important;
        margin-bottom: 24px !important;
    }
    html body.vcpg-page .vp-hero-left a[href="#contact"] {
        background: #02426A !important;
        color: #FFFFFF !important;
        padding: 14px 32px !important;
        border-radius: 50px !important;
        text-decoration: none !important;
        font-weight: 700 !important;
        font-size: 0.95rem !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 10px !important;
    }

    /* Legacy page body container & typography */
    html body.vcpg-page .vp-legacy-content {
        max-width: 1200px !important;
        margin: 0 auto !important;
        padding: 60px 24px 100px !important;
        font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif !important;
        color: #334155 !important;
        line-height: 1.8 !important;
        box-sizing: border-box !important;
    }
    html body.vcpg-page .vp-legacy-content h2 {
        color: #0A3663 !important;
        font-size: 2.3rem !important;
        font-weight: 800 !important;
        line-height: 1.25 !important;
        margin-top: 80px !important;
        margin-bottom: 24px !important;
        text-align: center !important;
    }
    html body.vcpg-page .vp-legacy-content h2:first-of-type {
        margin-top: 30px !important;
    }
    html body.vcpg-page .vp-legacy-content h2 + p {
        font-size: 1.05rem !important;
        color: #334155 !important;
        line-height: 1.8 !important;
        max-width: 860px !important;
        margin-left: auto !important;
        margin-right: auto !important;
        margin-bottom: 35px !important;
        text-align: center !important;
    }
    html body.vcpg-page .vp-legacy-content h3 {
        color: #02426A !important;
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        margin-top: 36px !important;
        margin-bottom: 12px !important;
    }
    html body.vcpg-page .vp-legacy-content h4 {
        font-size: 1.25rem !important;
        font-weight: 700 !important;
        color: #02426A !important;
        margin-top: 28px !important;
        margin-bottom: 10px !important;
    }
    html body.vcpg-page .vp-legacy-content p {
        font-size: 1.05rem !important;
        color: #334155 !important;
        line-height: 1.8 !important;
        margin-bottom: 20px !important;
    }
    html body.vcpg-page .vp-legacy-content svg[width="40"],
    html body.vcpg-page .vp-legacy-content svg[width="42"] {
        display: inline-block !important;
        padding: 12px !important;
        background: #EFF6FF !important;
        border-radius: 12px !important;
        stroke: #02426A !important;
        margin-top: 24px !important;
        margin-bottom: 10px !important;
    }
    html body.vcpg-page .vp-legacy-content img {
        max-width: 100% !important;
        height: auto !important;
        border-radius: 20px !important;
        margin: 35px auto !important;
        display: block !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
    }
    html body.vcpg-page .vp-legacy-content p:has(> button[onclick*="vcpgSwitchTab"]),
    html body.vcpg-page .vp-legacy-content p:has(button) {
        display: flex !important;
        gap: 12px !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
        margin: 35px 0 !important;
    }

    /* Proposal form styling */
    html body.vcpg-page #hero_proposal,
    html body.vcpg-page #contact_proposal {
        display: flex !important;
        flex-direction: column !important;
        gap: 16px !important;
    }
    html body.vcpg-page #hero_proposal input[type="text"],
    html body.vcpg-page #hero_proposal input[type="email"],
    html body.vcpg-page #hero_proposal input[type="tel"],
    html body.vcpg-page #hero_proposal textarea,
    html body.vcpg-page #hero_proposal select,
    html body.vcpg-page #contact_proposal input[type="text"],
    html body.vcpg-page #contact_proposal input[type="email"],
    html body.vcpg-page #contact_proposal input[type="tel"],
    html body.vcpg-page #contact_proposal textarea,
    html body.vcpg-page #contact_proposal select {
        width: 100% !important;
        padding: 13px 22px !important;
        border-radius: 50px !important;
        border: 1px solid #7E7E7E !important;
        background: #F3F4F6 !important;
        color: #1E293B !important;
        font-size: 14px !important;
        box-sizing: border-box !important;
        outline: none !important;
    }
    html body.vcpg-page #hero_proposal button[type="submit"],
    html body.vcpg-page #hero_proposal input[type="submit"],
    html body.vcpg-page #contact_proposal button[type="submit"],
    html body.vcpg-page #contact_proposal input[type="submit"] {
        width: 100% !important;
        padding: 15px !important;
        border-radius: 50px !important;
        background: #02426A !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 16px !important;
        border: none !important;
        cursor: pointer !important;
    }

    /* INTRO */
    html body.vcpg-page .vp-intro { background: #FFFFFF !important; text-align: center !important; }

    /* ABOUT */
    html body.vcpg-page .vp-about { background: #FFFFFF !important; padding: 80px 0 !important; }
    html body.vcpg-page .vp-about-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 60px !important; align-items: center !important; }
    html body.vcpg-page .vp-feature-card { background: #FFFFFF !important; border: 1px solid #E2E8F0 !important; border-radius: 18px !important; padding: 28px 24px !important; display: flex !important; flex-direction: column !important; align-items: flex-start !important; text-align: left !important; box-shadow: 0 6px 24px rgba(0,0,0,0.04) !important; box-sizing: border-box !important; }

    /* SERVICES */
    html body.vcpg-page .vp-services-sec { background: #F8FAFC !important; padding: 90px 0 !important; }
    html body.vcpg-page .vp-services-sec .vp-title { color: #0A3663 !important; }
    html body.vcpg-page .vp-services-sec .vp-desc { color: #334155 !important; }
    html body.vcpg-page .vp-service-card { background: #FFFFFF !important; border-radius: 16px !important; padding: 36px 32px !important; color: #0A3663 !important; box-shadow: 0 10px 30px rgba(0,0,0,0.06) !important; display: flex !important; flex-direction: column !important; text-align: left !important; height: 100% !important; box-sizing: border-box !important; }

    /* WHY CHOOSE */
    html body.vcpg-page .vp-why-sec { background: #FFFFFF !important; padding: 90px 0 !important; }
    html body.vcpg-page .vp-tabs { display: flex !important; gap: 12px !important; flex-wrap: wrap !important; justify-content: center !important; margin-top: 30px !important; }
    html body.vcpg-page .vp-tab-active { background: #FFFFFF !important; color: #081828 !important; border: 1px solid #CBD5E1 !important; padding: 10px 20px !important; border-radius: 6px !important; font-weight: 700 !important; }
    html body.vcpg-page .vp-tab-dark { background: #0F172A !important; color: #FFFFFF !important; padding: 10px 20px !important; border-radius: 6px !important; font-weight: 600 !important; }

    /* CASE STUDY */
    html body.vcpg-page .vp-casestudy-sec { background: #FFFFFF !important; padding: 90px 0 !important; }
    html body.vcpg-page .vp-casestudy-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 50px !important; align-items: stretch !important; }
    html body.vcpg-page .vp-casestudy-grid img { width: 100% !important; height: 100% !important; min-height: 100% !important; border-radius: 24px !important; box-shadow: 0 12px 36px rgba(2,66,106,0.12) !important; display: block !important; object-fit: cover !important; margin: 0 !important; }

    /* LOGOS */
    html body.vcpg-page .vp-logos-bar { padding: 40px 0 !important; background: #FFFFFF !important; border-top: 1px solid #E2E8F0 !important; border-bottom: 1px solid #E2E8F0 !important; }
    html body.vcpg-page .vcpg-marquee-container { width: 100% !important; overflow: hidden !important; position: relative !important; padding: 16px 0 !important; }
    html body.vcpg-page .vcpg-marquee-track { display: flex !important; align-items: center !important; width: max-content !important; }

    /* TESTIMONIAL */
    html body.vcpg-page .vp-testi-sec { background: #FFFFFF !important; padding: 80px 0 !important; text-align: center !important; }

    /* CERTIFICATIONS */
    html body.vcpg-page .vp-cert-sec { background: #F8FAFC !important; padding: 80px 0 !important; text-align: center !important; }

    /* CONTACT FORM */
    html body.vcpg-page .vp-contact-sec { background: #070D18 !important; padding: 90px 0 !important; }
    html body.vcpg-page .vp-contact-sec .vp-contact-card,
    html body.vcpg-page .vp-contact-card {
        max-width: 760px !important;
        margin: 0 auto !important;
        background: #FFFFFF !important;
        border-radius: 20px !important;
        padding: 48px 36px !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
        border: none !important;
        box-sizing: border-box !important;
    }
    html body.vcpg-page .vp-contact-card h2,
    html body.vcpg-page .vp-contact-card h3 {
        text-align: center !important;
        color: #02426A !important;
        margin-top: 0 !important;
        margin-bottom: 24px !important;
    }

    /* Suppress unwanted portfolio section */
    html body.vcpg-page .vp-portfolio-sec {
        display: none !important;
    }

    /* Suppress unwanted empty capsule box above hero header */
    html body.vcpg-page .vp-hero div[style*="border-radius:30px"]:empty,
    html body.vcpg-page .vp-hero div[style*="border-radius: 30px"]:empty,
    html body.vcpg-page .vp-hero-city-label {
        display: none !important;
    }

    @media (max-width: 900px) {
      html body.vcpg-page .vp-hero-grid, html body.vcpg-page .vp-about-grid, html body.vcpg-page .vp-footer-grid, html body.vcpg-page .vp-casestudy-grid { grid-template-columns: 1fr !important; gap: 40px !important; }
      html body.vcpg-page .vp-casestudy-grid > div:first-child { order: 1 !important; }
      html body.vcpg-page .vp-casestudy-grid > div:last-child { order: 2 !important; }
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
        html body.vcpg-page .vcpg-tab-btn {
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
        html body.vcpg-page .vcpg-t-prev, html body.vcpg-page .vcpg-t-next {
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
        html body.vcpg-page .elementor-element-e000003 div[style*="background:#02426A"],
        html body.vcpg-page .elementor-element-e000003 div[style*="background: #02426A"] {
            height: auto !important;
            padding: 8px 12px !important;
        }
        html body.vcpg-page .elementor-element-e000003 div[style*="background:#02426A"] > div,
        html body.vcpg-page .elementor-element-e000003 div[style*="background: #02426A"] > div {
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
        html body.vcpg-page .vcpg-mega-menu {
            padding: 16px !important;
        }
        html body.vcpg-page .vcpg-mega-grid {
            grid-template-columns: 1fr !important;
            gap: 4px !important;
        }

        /* Case Study & Grid Mobile Reflow */
        html body.vcpg-page .vp-casestudy-grid {
            grid-template-columns: 1fr !important;
            gap: 24px !important;
        }

        /* Footer Column Stacking */
        html body.vcpg-page footer.vp-footer > div,
        html body.vcpg-page .vp-footer > div {
            flex-direction: column !important;
            gap: 30px !important;
        }

        /* Button Sizing on Mobile */
        html body.vcpg-page .vcpg-dual-btn .vcpg-btn-pill {
            padding: 0 14px !important;
            font-size: 13px !important;
            height: 36px !important;
        }
        html body.vcpg-page .vcpg-dual-btn .vcpg-btn-circle-left,
        html body.vcpg-page .vcpg-dual-btn .vcpg-btn-circle-right {
            height: 36px !important;
            width: 36px !important;
        }

        /* Back to Top button position on mobile */
        html body.vcpg-page .vcpg-back-to-top {
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
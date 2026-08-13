<?php

defined('ABSPATH') || exit;

class VCPG_Elementor_Template_Builder
{
    private $json_path;

    public function __construct($json_path = null)
    {
        if($json_path !== null)
        {
            $this->json_path = $json_path;
        }
        else
        {
            $this->json_path = dirname(__DIR__) . '/templates/elementor-landing-template.json';
        }
    }

    public function build($data)
    {
        $raw = file_get_contents($this->json_path);
        if(!$raw)
        {
            return array();
        }

        $json = json_decode($raw, true);
        if(!is_array($json) || !isset($json['content']))
        {
            return array();
        }

        $map = $this->build_map($data);

        return $this->fill($json['content'], $map);
    }

    /**
     * Public shim so external classes (VCPG_Elementor_Renderer,
     * VCPG_Static_Elements) can obtain the full {{token}} => value map
     * without duplicating HTML generation logic.
     *
     * @param  array $data  Merged data array from page generator.
     * @return array        Flat associative array of {{token}} => string.
     */
    public function get_replacements( array $data ): array
    {
        return $this->build_map( $data );
    }

    public function build_html($data)
    {
        $tpl_path = dirname(__DIR__) . '/templates/premium-template.html';
        if(!file_exists($tpl_path))
        {
            return '';
        }
        $html = file_get_contents($tpl_path);
        $map  = $this->build_map($data);
        return strtr($html, $map);
    }

    private function fill($node, $map)
    {
        if(is_string($node))
        {
            return isset($map[$node]) ? $map[$node] : strtr($node, $map);
        }

        if(is_array($node))
        {
            $out = array();
            foreach($node as $key => $value)
            {
                $out[$key] = $this->fill($value, $map);
            }
            return $out;
        }

        return $node;
    }

    private function t($value)
    {
        return trim((string)$value);
    }

    private function e($value)
    {
        $value = (string)$value;
        return function_exists('esc_html')
            ? esc_html($value)
            : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function nl($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        // 1. Split on existing double-newlines (author-defined paragraphs).
        $raw_paragraphs = preg_split('/\n\s*\n/', $value);

        // 2. Further split any paragraph that exceeds 50 words into
        //    chunks of ~40-50 words, preferring sentence boundaries.
        $final_paragraphs = array();
        foreach ($raw_paragraphs as $raw_p) {
            $raw_p = trim($raw_p);
            if ($raw_p === '') {
                continue;
            }
            $words = preg_split('/\s+/', $raw_p);
            if (count($words) <= 50) {
                $final_paragraphs[] = $raw_p;
                continue;
            }
            // Break into sentence-aware chunks of 40-50 words.
            $chunks = $this->split_into_word_chunks($raw_p, 40, 50);
            foreach ($chunks as $chunk) {
                $final_paragraphs[] = $chunk;
            }
        }

        $p_html = array();
        foreach ($final_paragraphs as $p) {
            $p = trim($p);
            if ($p !== '') {
                $p_html[] = '<p style="margin:0 0 18px 0;">' . nl2br($this->e($p)) . '</p>';
            }
        }
        return implode('', $p_html);
    }

    /**
     * Split text into chunks of $min to $max words.
     * Prefers breaking after sentence-ending punctuation (. ! ?) when a
     * break point falls within the target window.
     */
    private function split_into_word_chunks($text, $min = 40, $max = 50)
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($text));
        $chunks = array();
        $current_chunk = array();
        $current_word_count = 0;

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }
            $sentence_words = preg_split('/\s+/', $sentence);
            $sentence_word_count = count($sentence_words);

            if ($current_word_count > 0 && ($current_word_count + $sentence_word_count) > $max) {
                $chunks[] = implode(' ', $current_chunk);
                $current_chunk = $sentence_words;
                $current_word_count = $sentence_word_count;
            } else {
                $current_chunk = array_merge($current_chunk, $sentence_words);
                $current_word_count += $sentence_word_count;
            }
        }

        if (!empty($current_chunk)) {
            $chunks[] = implode(' ', $current_chunk);
        }

        return $chunks;
    }

    private function clean_service($service)
    {
        return preg_replace('/\s+(Services|Service|Agency|Solutions|Company|Marketing)\b.*$/i', '', $service);
    }

    private function svg_uri($svg)
    {
        if (strpos($svg, 'data:') === 0 || strpos($svg, 'http://') === 0 || strpos($svg, 'https://') === 0) {
            return $svg;
        }
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
    }

    private function get_asset_url($file)
    {
        if (function_exists('plugins_url')) {
            return plugins_url('assets/' . $file, dirname(__DIR__) . '/vispan-city-page-generator.php');
        }
        return '/wp-content/plugins/assets/' . $file;
    }

    private function build_map($data)
    {
        $svc   = $this->t(isset($data['service']) ? $data['service'] : 'Digital Marketing Agency');
        $city  = $this->t(isset($data['city']) ? $data['city'] : 'Italy');
        $state = $this->t(isset($data['state']) ? $data['state'] : '');
        $phone = $this->t(isset($data['phone']) ? $data['phone'] : '+91 84859 86860');
        $email = $this->t(isset($data['email']) ? $data['email'] : 'contact@vispansolutions.com');

        $home = function_exists('home_url') ? home_url('/') : '/';

        $nouns = $this->get_service_nouns($svc);

        $hero_small = $this->t(isset($data['hero_subtitle']) ? $data['hero_subtitle'] : '');
        if(empty($hero_small))
        {
            $hero_small = strtoupper(trim($city . ' ' . $svc));
        }

        $hero_title = $this->t(isset($data['hero_title']) ? $data['hero_title'] : '');
        if(empty($hero_title))
        {
            $hero_title = $svc . ' in ' . $city;
        }

        $hero_description = $this->t(isset($data['hero_description']) ? $data['hero_description'] : '');
        if(empty($hero_description))
        {
            $hero_description = "Welcome to our team in " . $city . "! In " . $city . "’s competitive landscape, exceptional offerings alone are insufficient. Partnering with a top agency can elevate your " . $nouns['business_type'] . ".\n\nWe specialize in customized outreach strategies for your team. Our expertise in tailored layouts ensures maximum web visibility, attracting new " . $nouns['client_type'] . "s and retaining existing ones.\n\nAs leaders in the area, we offer solutions from optimization to engaging branding. Our websites are user-friendly and attractive, helping you stand out.\n\nLet us help you grow with top-notch designs, effective promotions, and comprehensive search strategies. Our social campaign and website development will ensure your " . $nouns['business_type'] . " reaches its full potential.";
        }

        $about_title = $this->t(isset($data['about_title']) ? $data['about_title'] : '');
        if(empty($about_title))
        {
            if(stripos($svc, 'agency') !== false || stripos($svc, 'marketing') !== false) {
                $about_title = 'Creating a Strong Online Presence for Local Businesses with our ' . $svc . ' in ' . $city . '.';
            } else {
                $about_title = 'Creating a Strong Online Presence for ' . $svc . ' with our Digital Marketing Agency in ' . $city . '.';
            }
        }

        $about_content = $this->t(isset($data['about_content']) ? $data['about_content'] : '');
        if(empty($about_content))
        {
            $about_content = "Enhance your business's online presence in " . $city . " with customized online marketing solutions tailored to your " . $nouns['business_type'] . ". We specialize in optimizing your website for search engines and engaging " . $nouns['client_type'] . "s through various social media platforms.\n\nOur comprehensive outreach services focus on attracting new clients while fostering loyalty among your current clientele. Partner with us to transform your business and achieve lasting success in the competitive virtual landscape.\n\nReach out to us to navigate the complexities of online marketing and realize sustained growth for your " . $nouns['business_type'] . ", utilizing our proven expertise in advertising, web design, SEO strategies, and social media marketing. Together, we can build a thriving online presence for your business.";
        }

        $about_paras = preg_split('/\n+/', $about_content);
        $about_html_paras = array();
        foreach($about_paras as $ap) {
            if(!empty(trim($ap))) {
                $about_html_paras[] = '<p style="margin-bottom:16px;line-height:1.65;font-size:15px;color:#121212;">' . esc_html(trim($ap)) . '</p>';
            }
        }
        $about_content_html = implode('', $about_html_paras);

        $intro_title = $this->t(isset($data['intro_title']) ? $data['intro_title'] : '');
        if(empty($intro_title))
        {
            $intro_title = 'Get ' . $svc . ' in ' . $city . ': Why Your ' . ucwords($nouns['business_type']) . ' Needs Online Marketing in ' . $city;
        }

        $intro_content = $this->t(isset($data['intro_content']) ? $data['intro_content'] : '');
        if(empty($intro_content))
        {
            $intro_content = "Unlock the modern revolution in " . strtolower($svc) . " and discover why your " . $nouns['business_type'] . " must embrace online promotions. Our specialized visibility services are designed to enhance your " . $nouns['business_type'] . "'s reach and attract new " . $nouns['client_type'] . "s. In today's competitive landscape, having a strong online presence is crucial for growth and " . $nouns['client_type'] . " engagement.\n\nWe excel in local search optimization, ensuring your " . $nouns['business_type'] . " stands out in local queries. As a leading growth partner, we offer tailored strategies that incorporate social platforms and effective advertising. Our goal is to help you connect with potential " . $nouns['client_type'] . "s in your area and build a loyal base.\n\nStrengthen your brand with our expert optimization techniques, which are specifically designed to maximize exposure. We provide targeted solutions that include enhancing layouts and optimizing your website to ensure it effectively attracts and retains visitors.\n\nTransform your " . $nouns['business_type'] . " in " . $city . " by partnering with us. Our comprehensive services, from innovative search techniques to strategic outreach campaigns, will revolutionize your organization. With our expertise, your website will not only shine with captivating layouts but will also leverage smart campaigns to drive sustained growth and success in the competitive market.";
        }

        $services_heading = $this->t(isset($data['services_heading']) ? $data['services_heading'] : '');
        if(empty($services_heading))
        {
            $services_heading = 'Services of ' . $svc . ' for ' . ucwords($nouns['business_type']) . 's in ' . $city;
        }

        $services_description = $this->t(isset($data['services_description']) ? $data['services_description'] : '');
        if(empty($services_description))
        {
            $services_description = 'Our ' . strtolower($svc) . ' services in ' . $city . ' encompass a range of modern strategies, all working together to achieve your ' . $nouns['business_type'] . '\'s unique goals. Here\'s a closer look at some key components:';
        }

        $why_choose_heading = $this->t(isset($data['why_choose_heading']) ? $data['why_choose_heading'] : '');
        if(empty($why_choose_heading))
        {
            $why_choose_heading = "Distinct Advantages of Partnering with Vispan Solutions' Specialists";
        }

        $why_choose_description = $this->t(isset($data['why_choose_description']) ? $data['why_choose_description'] : '');
        if(empty($why_choose_description))
        {
            $why_choose_description = 'As your growth partner in ' . $city . ', we understand the unique challenges faced in today\'s competitive landscape.';
        }

        $cta_description = $this->t(isset($data['cta_content']) ? $data['cta_content'] : '');
        if(empty($cta_description))
        {
            $cta_description = "Don't let your brand get lost in the virtual world of " . $city . ". Our comprehensive outreach agency can help you attract new visitors, build a strong online presence, and ultimately achieve your growth goals.\n\nContact us today for a free consultation and discuss how we can help your team thrive in the modern age.";
        }

        $cta_paras = preg_split('/\n+/', $cta_description);
        $cta_html_paras = array();
        foreach($cta_paras as $cp) {
            if(!empty(trim($cp))) {
                $cta_html_paras[] = '<p style="margin-bottom:14px;line-height:1.65;font-size:15px;">' . esc_html(trim($cp)) . '</p>';
            }
        }
        $cta_description_html = implode('', $cta_html_paras);

        $logo_uri         = $this->svg_uri($this->logo_svg());
        $hero_bg_uri      = $this->get_asset_url('hero-bg.png');
        $hero_video_uri   = $this->get_asset_url('vispan-banner.webm');
        $services_bg_uri  = $this->get_asset_url('section-5.jpg');
        $about_uri        = $this->get_asset_url('section-3.webp');
        $cta_bg_uri       = $this->get_asset_url('section-5.jpg');
        $contact_bg_uri   = $this->get_asset_url('demo.jpg');

        $map = array(
            '{{home_url}}'                 => $home,
            '{{phone}}'                    => $phone,
            '{{email}}'                    => $email,
            '{{logo}}'                     => $logo_uri,
            '{{footer_logo}}'              => $logo_uri,
            '{{nav_menu}}'                 => $this->nav_html(),
            '{{topbar}}'                   => $this->topbar_html($phone, $email),
            '{{hero_bg}}'                  => $hero_bg_uri,
            '{{hero_video}}'               => $hero_video_uri,
            '{{contact_bg}}'               => $contact_bg_uri,
            '{{hero_small_title}}'         => '',
            '{{hero_title}}'               => $this->e($hero_title),
            '{{hero_subtitle}}'            => $this->e($hero_small),
            '{{hero_description}}'         => $this->nl($hero_description),
            '{{hero_primary_btn}}'         => 'Learn More',
            '{{consultation_title}}'       => 'Get A Free Consultation',
            '{{consultation_label}}'       => '',
            '{{consultation_form}}'        => $this->hero_form_html(),
            '{{intro_title}}'              => $this->e($intro_title),
            '{{intro_content}}'            => $this->nl($intro_content),
            '{{about_title}}'              => $this->e($about_title),
            '{{about_content}}'            => $this->nl($about_content),
            '{{about_content_html}}'       => $about_content_html,
            '{{about_image}}'              => $about_uri,
            '{{about_features}}'           => $this->about_features_html($nouns),
            '{{stats}}'                   => $this->stats_html($data),
            '{{services_bg}}'              => $services_bg_uri,
            '{{services_heading}}'         => $this->e($services_heading),
            '{{services_description}}'     => $this->nl($services_description),
            '{{services}}'                => $this->services_html($data),
            '{{why_choose_heading}}'       => $this->e($why_choose_heading),
            '{{why_choose_description}}'   => $this->nl($why_choose_description),
            '{{benefits}}'                => $this->benefits_html($data),
            '{{cta_description}}'          => $this->nl($cta_description),
            '{{cta_description_html}}'     => $cta_description_html,
            '{{cta_button}}'               => 'Claim Your Free Audit',
            '{{cta_image}}'                => $cta_bg_uri,
            '{{portfolio_heading}}'        => 'We Succeed When Our Customers Thrive - Our portfolio!',
            '{{portfolio_description}}'    => 'We\'re committed to helping you grow your ' . $nouns['business_type'] . ' and achieve your business goals through strategic digital marketing.',
            '{{portfolio_images}}'         => $this->portfolio_html(),
            '{{logos}}'                    => $this->logos_html(),
            '{{testimonial}}'              => $this->testimonial_html($data),
            '{{certifications_heading}}'   => 'Certifications',
            '{{certifications}}'           => $this->certifications_html(),
            '{{contact_title}}'            => 'Request A Marketing Proposal',
            '{{contact_form}}'             => $this->contact_form_html(),
            '{{footer_about}}'             => 'Feel free to reach out if you want to collaborate with us, or simply have a chat.',
            '{{footer_services}}'          => $this->footer_services_html($data),
            '{{footer_links}}'             => $this->footer_links_html(),
            '{{footer_contact}}'           => $this->footer_contact_html($phone, $email),
            '{{case_study_html}}'          => $this->case_study_html($data),
            '{{social_icons}}'             => $this->social_html(),
            '{{faq}}'                      => VCPG_Page_Generator::generate_faq($data),
        );

        return $map;
    }

    private function topbar_html($phone, $email)
    {
        return '';
    }

    private function nav_html()
    {
        // Load exact icons from the reference site
        $icons_file = dirname(dirname(__FILE__)) . '/assets/mega_menu_icons.json';
        $icons = array();
        if (file_exists($icons_file)) {
            $icons = json_decode(file_get_contents($icons_file), true);
        }

        $services = array(
            array('name' => 'Digital Marketing', 'desc' => 'Boost your brand with tailored digital marketing services etc.', 'url' => 'https://vispansolutions.com/digital-marketing-services/'),
            array('name' => 'Google Ads', 'desc' => 'Maximize your online presence with expert Google Ads Service', 'url' => 'https://vispansolutions.com/google-ads-services-in-india/'),
            array('name' => 'Branding Services', 'desc' => 'Enhance your brand identity with our expert branding services', 'url' => 'https://vispansolutions.com/branding-services/'),
            array('name' => 'SEO', 'desc' => 'Enhance your website\'s visibility and organic traffic with expert SEO', 'url' => 'https://vispansolutions.com/seo-services/'),
            array('name' => 'Web Development', 'desc' => 'We deliver high performing websites as per your business', 'url' => 'https://vispansolutions.com/web-development/'),
            array('name' => 'Social Media Management', 'desc' => 'Optimize your brand\'s online presence', 'url' => 'https://vispansolutions.com/social-media-management-services/'),
            array('name' => 'Online Reputation Management', 'desc' => 'We help improve your brand\'s online image', 'url' => 'https://vispansolutions.com/online-reputation-management/'),
            array('name' => 'Video Production', 'desc' => 'From concept to final cut, we create engaging videos', 'url' => 'https://vispansolutions.com/video-production/'),
            array('name' => 'VFX', 'desc' => 'Our VFX services bring imagination to life with stunning visual effects', 'url' => 'https://vispansolutions.com/vfx-services-in-rajkot/'),
            array('name' => 'CGI Services', 'desc' => 'We deliver high-end CGI visuals, including 3D modeling, animation', 'url' => 'https://vispansolutions.com/cgi-services/')
        );

        // Attach icons from the JSON file
        foreach ($services as &$s) {
            $s['icon'] = isset($icons[$s['name']]) ? $icons[$s['name']] : '';
        }
        unset($s);

        $services_dropdown_html = '<div class="vcpg-mega-menu"><div class="vcpg-mega-grid">';
        foreach($services as $s)
        {
            $services_dropdown_html .= '<a href="' . $s['url'] . '" class="vcpg-mega-item" target="_blank">'
                . '<div class="vcpg-mega-icon">' . $s['icon'] . '</div>'
                . '<div>'
                . '<div class="vcpg-mega-title">' . $this->e($s['name']) . '</div>'
                . '<div class="vcpg-mega-desc">' . $this->e($s['desc']) . '</div>'
                . '</div>'
                . '</a>';
        }
        $services_dropdown_html .= '</div></div>';

        $investor_dropdown_html = '<div class="vcpg-simple-menu">'
            . '<a href="https://vispansolutions.com/financial-reporting" class="vcpg-simple-item" target="_blank">Financial Reporting</a>'
            . '</div>';

        $menu = array(
            array('name' => 'Home', 'url' => 'https://vispansolutions.com/'),
            array('name' => 'About Us', 'url' => 'https://vispansolutions.com/about-us/'),
            array('name' => 'What We Do <span class="vcpg-arrow"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-left:2px;"><polyline points="6 9 12 15 18 9"></polyline></svg></span>', 'url' => 'https://vispansolutions.com/what-we-do/', 'dropdown' => $services_dropdown_html, 'class' => 'vcpg-has-dropdown vcpg-has-mega'),
            array('name' => 'Blog', 'url' => 'https://vispansolutions.com/blog/'),
            array('name' => 'Investor <span class="vcpg-arrow"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-left:2px;"><polyline points="6 9 12 15 18 9"></polyline></svg></span>', 'url' => 'https://vispansolutions.com/financial-reporting', 'dropdown' => $investor_dropdown_html, 'class' => 'vcpg-has-dropdown'),
            array('name' => 'Career', 'url' => 'https://vispansolutions.com/career/'),
            array('name' => 'Contact Us', 'url' => 'https://vispansolutions.com/contact-us/'),
        );

        $html = '';
        foreach($menu as $item)
        {
            $cls = isset($item['class']) ? $item['class'] : '';
            $dropdown = isset($item['dropdown']) ? $item['dropdown'] : '';
            $html .= '<li class="vcpg-nav-item ' . $cls . '">'
                . '<a href="' . $item['url'] . '" class="vcpg-nav-link" target="_blank">' . $item['name'] . '</a>'
                . $dropdown
                . '</li>';
        }
        return $html;
    }

    private function hero_form_html()
    {
        return $this->build_proposal_form('hero_proposal');
    }

    private function about_features_html($nouns = null)
    {
        if (is_null($nouns)) {
            $nouns = array(
                'business_type' => 'business',
                'client_type'   => 'client',
                'practitioner'  => 'professional',
            );
        }
        $items = array(
            array(
                'title' => 'Increase appointments',
                'desc' => 'Convert online interest into confirmed bookings and a growing ' . $nouns['client_type'] . ' base through online marketing.',
                'svg' => '<svg width="42" height="42" viewBox="0 0 48 48" fill="none" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="16" cy="28" r="8"/><line x1="8" y1="20" x2="32" y2="4"/><rect x="24" y="6" width="12" height="12"/><polygon points="40,32 44,40 36,40"/></svg>'
            ),
            array(
                'title' => 'Build Trust and Credibility',
                'desc' => 'Showcase your expertise, advanced technology, and ' . $nouns['client_type'] . '-centric approach.',
                'svg' => '<svg width="42" height="42" viewBox="0 0 48 48" fill="none" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="6" width="15" height="15" rx="1"/><circle cx="13.5" cy="13.5" r="5"/><circle cx="13.5" cy="13.5" r="2"/><rect x="27" y="6" width="15" height="15" rx="1"/><circle cx="34.5" cy="13.5" r="5"/><circle cx="34.5" cy="13.5" r="2"/><rect x="6" y="27" width="15" height="15" rx="1"/><rect x="27" y="27" width="15" height="15" rx="1"/><circle cx="34.5" cy="34.5" r="5"/><circle cx="34.5" cy="34.5" r="2"/></svg>'
            ),
            array(
                'title' => 'Enhance ' . $nouns['client_type'] . ' engagement',
                'desc' => 'Foster strong relationships by providing valuable information and interacting with your audience online.',
                'svg' => '<svg width="42" height="42" viewBox="0 0 48 48" fill="none" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M24 6L42 16.5V31.5L24 42L6 31.5V16.5L24 6Z"/><path d="M6 16.5L24 27L42 16.5"/><path d="M24 27V42"/></svg>'
            ),
            array(
                'title' => 'Boost Online visibility',
                'desc' => 'Improve your search engine ranking and ensure your ' . $nouns['business_type'] . ' appears at the top of local searches.',
                'svg' => '<svg width="42" height="42" viewBox="0 0 48 48" fill="none" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 42H42"/><path d="M6 42V6"/><rect x="14" y="22" width="14" height="14"/><path d="M18 30L34 14"/><path d="M24 14H34V24"/></svg>'
            ),
        );
        $html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:32px;">';
        foreach($items as $it)
        {
            $html .= '<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:18px;padding:28px 24px;box-shadow:0 6px 24px rgba(0,0,0,0.04);display:flex;flex-direction:column;align-items:flex-start;text-align:left;box-sizing:border-box;">';
            $html .= '<div style="margin-bottom:14px;">' . $it['svg'] . '</div>';
            $html .= '<h3 style="margin:0 0 8px;font-size:17.5px;font-weight:700;color:#111827;line-height:1.3;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">' . $this->e($it['title']) . '</h3>';
            $html .= '<p style="margin:0;font-size:13.5px;color:#475569;line-height:1.55;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">' . $this->e($it['desc']) . '</p></div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function stats_html($data)
    {
        return $this->about_features_html();
    }

    private function services_html($data)
    {
        $svc = isset($data['service']) ? $data['service'] : 'Digital Marketing';
        $nouns = $this->get_service_nouns($svc);
        $svc_lower = strtolower($svc);

        if (preg_match('/(seo|search|optimization)/i', $svc_lower)) {
            $services = array(
                array(
                    'title' => 'On-Page SEO Optimization',
                    'sub' => 'Aligning Content with Search Intent',
                    'desc' => 'We optimize page titles, headings, content structure, and internal linking to align with user search intent. By ensuring target keywords are strategically placed and content is highly engaging, we improve your website\'s relevance and ranking potential for your ' . $nouns['business_type'] . '.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2v20M2 12h20M12 12l4.5-4.5"/></svg>'
                ),
                array(
                    'title' => 'Technical SEO Auditing',
                    'sub' => ucwords($nouns['industry_noun']) . ' Crawler Accessibility',
                    'desc' => 'We enhance your website\'s crawlability, indexing speed, mobile responsiveness, and schema markup. Resolving crawl errors, optimizing sitemaps, and maximizing page speed ensures that search engines can seamlessly discover and list your primary landing pages.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>'
                ),
                array(
                    'title' => 'Off-Page Link Acquisition',
                    'sub' => 'Cultivating Authoritative Domain Signals',
                    'desc' => 'Our team secures high-quality backlinks, guest posts, and digital PR placements to build domain authority. Connecting your brand with reputable industry portals signals trust and authority to search engines, lifting your keyword rankings.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'
                ),
                array(
                    'title' => 'Local SEO & Google Maps',
                    'sub' => 'Optimizing Hyper-Local Search Presence',
                    'desc' => 'We optimize your Google Business Profile and local citations to ensure prominent placement in the local map pack. Consistent Name, Address, and Phone (NAP) data combined with local keyword alignment helps you capture nearby ' . $nouns['client_type'] . 's.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 12 8 12s8-6.75 8-12a8 8 0 0 0-8-8z"/><circle cx="12" cy="10" r="3"/></svg>'
                )
            );
        } elseif (preg_match('/(ppc|ads|adwords|advertising|pay)/i', $svc_lower)) {
            $services = array(
                array(
                    'title' => 'Search & Shopping Campaigns',
                    'sub' => 'Capturing High-Intent Searches',
                    'desc' => 'We build and manage targeted search campaigns and product shopping feeds. By bidding on keywords with commercial intent, we put your brand in front of users ready to buy, maximizing conversion rates and minimizing wasted budget.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
                ),
                array(
                    'title' => 'Display & Remarketing Campaigns',
                    'sub' => 'Nurturing Past Website Visitors',
                    'desc' => 'Retarget users who previously interacted with your website using targeted banner and video promotions. We segment audiences based on behavior to deliver highly personalized messaging, turning window shoppers into loyal customers.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="21" y1="12" x2="3" y2="12"/><line x1="12" y1="21" x2="12" y2="3"/></svg>'
                ),
                array(
                    'title' => 'Social Media Advertising',
                    'sub' => 'Paid Social Lead Acquisition',
                    'desc' => 'We create, test, and optimize visual ad campaigns across Meta, LinkedIn, and TikTok. Combining creative storytelling with precise demographic and behavioral targeting ensures your ' . $nouns['business_type'] . ' reaches ideal decision-makers.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>'
                ),
                array(
                    'title' => 'Attribution & Conversion Analytics',
                    'sub' => 'Granular Return on Investment Tracking',
                    'desc' => 'We implement end-to-end attribution tracking for every call, form submission, and checkout. With detailed reporting on Cost-Per-Acquisition (CPA) and Customer Lifetime Value (LTV), you can scale your paid acquisition with full clarity.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'
                )
            );
        } elseif (preg_match('/(web|development|design)/i', $svc_lower)) {
            $services = array(
                array(
                    'title' => 'Custom UI/UX & Responsive Design',
                    'sub' => 'Crafting Engaging Web Experiences',
                    'desc' => 'We design pixel-perfect, custom user interfaces that reflect your branding guidelines. Ensuring smooth navigation, visual hierarchy, and full mobile optimization helps convert visitors into customers on any screen size.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>'
                ),
                array(
                    'title' => 'Frontend & Backend Architecture',
                    'sub' => 'Building Secure & Scalable Applications',
                    'desc' => 'Our developers write clean, standards-compliant code using modern frameworks and secure database configurations. We implement custom CMS setups and APIs that allow your team to manage content effortlessly.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>'
                ),
                array(
                    'title' => 'E-Commerce Engineering',
                    'sub' => 'Seamless Shopping & Checkout Systems',
                    'desc' => 'We construct robust online stores with secure payment gateway integrations, automated inventory synchronization, and flexible shipping calculations. We make the purchasing journey frictionless for your customers.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>'
                ),
                array(
                    'title' => 'Speed & Core Web Vitals Optimization',
                    'sub' => 'Optimizing for Speed and Search Rankings',
                    'desc' => 'We optimize assets, enable advanced server caching, compress media, and audit script loading. Achieving fast load times and strong Core Web Vitals grades satisfies both human visitors and search engine indexing bots.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'
                )
            );
        } elseif (preg_match('/(social|media|brand|branding)/i', $svc_lower)) {
            $services = array(
                array(
                    'title' => 'Brand Identity & Visual Guidelines',
                    'sub' => 'Defining Your Corporate Voice',
                    'desc' => 'We design memorable logos, custom color schemes, typography guides, and brand collateral. Defining a consistent visual identity ensures your ' . $nouns['business_type'] . ' stands out across print and digital media.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
                ),
                array(
                    'title' => 'Social Media Strategy & Planning',
                    'sub' => 'Growing an Engaged Digital Community',
                    'desc' => 'We prioritize target networks, outline custom posting schedules, and plan themed campaigns. Designing custom content calendars and posting workflows ensures consistent brand engagement with your target audience.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
                ),
                array(
                    'title' => 'Content Styling & Graphic Assets',
                    'sub' => 'Creating Reels, Stories, & Banner Collateral',
                    'desc' => 'We design engaging reels, stories, graphics, and infographics that capture attention and drive shares. Our content production workflow ensures high-quality creative assets align with current platform trends.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'
                ),
                array(
                    'title' => 'Reputation & Feedback Management',
                    'sub' => 'Fostering Trust Across Review Networks',
                    'desc' => 'We monitor local directories, track brand mentions, and manage consumer feedback. Engaging with comments and reviews helps build credibility and maintains a positive online reputation for your ' . $nouns['business_type'] . '.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>'
                )
            );
        } else {
            $services = array(
                array(
                    'title' => 'Website Development',
                    'sub' => 'Building a Strong Foundation for Your ' . ucwords($nouns['business_type']),
                    'desc' => 'Understanding your ideal ' . $nouns['client_type'] . ' will guide the website\'s content and tone. Craft clear and informative content to highlight your services, procedures, and the expertise of your ' . $nouns['practitioner'] . 's. Use easy-to-understand language and avoid excessive ' . $nouns['jargon'] . '. A significant portion of web traffic comes from mobile devices. Ensure your website displays seamlessly on all screen sizes.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="2"/><circle cx="5" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><circle cx="8" cy="19" r="2"/><circle cx="16" cy="19" r="2"/><path d="M12 7v3M7 12l3-2M17 12l-3-2M8 17l2.5-3.5M16 17l-2.5-3.5"/></svg>'
                ),
                array(
                    'title' => 'Search Engine Optimization',
                    'sub' => ucwords($nouns['industry_noun']) . ' SEO Service',
                    'desc' => 'We optimize your website and online listings with relevant keywords to ensure your ' . $nouns['business_type'] . ' appears in local searches for "' . $nouns['practitioner'] . ' near me" or specific services. We create informative blog posts, articles, and ' . $nouns['client_type'] . ' education materials that address common ' . $nouns['concerns'] . ' and establish your ' . $nouns['business_type'] . ' as a trusted source & ensure your website is mobile-friendly, user-friendly, and provides a seamless client experience.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M2 12h20M7 7l10 10M17 7L7 17"/></svg>'
                ),
                array(
                    'title' => 'Pay-Per-Click (PPC) Advertising',
                    'sub' => 'Targeted Google and Meta Ads',
                    'desc' => 'Targeted Google and Meta ads campaigns are managed with precision to maximize ROI. Automated bid strategies and detailed attribution models ensure your business captures high-intent leads while controlling ad spend effectively.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>'
                ),
                array(
                    'title' => 'Social Media & Branding',
                    'sub' => 'Engage ' . ucwords($nouns['client_type']) . 's & Build Trust',
                    'desc' => 'Establish and grow your brand presence across key social media channels. We craft compelling visual assets, manage online reputation across local directories, and nurture ' . $nouns['client_type'] . ' relationships to drive long-term ' . $nouns['business_type'] . ' growth.',
                    'svg' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg>'
                ),
            );
        }

        $html = '';
        foreach($services as $s)
        {
            $title = $this->e($s['title']);
            $sub   = $this->e($s['sub']);
            $desc  = $this->e($s['desc']);
            $svg   = $s['svg'];

            $html .= '<div style="background:#FFFFFF;border-radius:16px;padding:36px 32px;box-shadow:0 10px 30px rgba(0,0,0,0.06);display:flex;flex-direction:column;text-align:left;height:100%;box-sizing:border-box;">';
            $html .= '<div style="margin-bottom:16px;">' . $svg . '</div>';
            $html .= '<h3 style="margin:0 0 6px;font-size:20px;font-weight:700;color:#000000;">' . $title . '</h3>';
            if($sub !== '')
            {
                $html .= '<div style="font-size:14px;font-weight:700;color:#1E293B;margin-bottom:12px;">' . $sub . '</div>';
            }
            $html .= '<p style="color:#475569;font-size:14px;line-height:1.65;margin:0;">' . $desc . '</p>';
            $html .= '</div>';
        }
        return $html;
    }

    private function benefits_html($data)
    {
        $city  = $this->e(isset($data['city']) ? $data['city'] : 'Los Angeles');
        $state = $this->e(isset($data['state']) ? $data['state'] : 'California');
        $svc   = $this->e(isset($data['service']) ? $data['service'] : 'Digital Marketing');

        // Load counties for the current state
        $state_counties = array();
        $counties_file = dirname(__FILE__) . '/counties.json';
        if (file_exists($counties_file)) {
            $all_counties = json_decode(file_get_contents($counties_file), true);
            if (is_array($all_counties)) {
                foreach ($all_counties as $s_name => $c_list) {
                    if (strcasecmp($s_name, $state) === 0) {
                        $state_counties = $c_list;
                        break;
                    }
                }
            }
        }

        // Split into 3 parts for Tab 2, 3, and 4
        $tab2_counties = array();
        $tab3_counties = array();
        $tab4_counties = array();

        if (!empty($state_counties)) {
            $total_counties = count($state_counties);
            $part_size = ceil($total_counties / 3);
            $tab2_counties = array_slice($state_counties, 0, $part_size);
            $tab3_counties = array_slice($state_counties, $part_size, $part_size);
            $tab4_counties = array_slice($state_counties, $part_size * 2);
        }

        $tab2_suffix = '';
        $tab3_suffix = '';
        $tab4_suffix = '';

        if (!empty($tab2_counties)) {
            $phrases = array();
            foreach ($tab2_counties as $c) {
                $c_clean = trim(preg_replace('/\bcounty\b/i', '', $c));
                $phrases[] = $svc . ' in ' . $this->e($c_clean);
            }
            $tab2_suffix = ' We offer our specialized services across the region, including ' . implode(', ', $phrases) . '.';
        }

        if (!empty($tab3_counties)) {
            $phrases = array();
            foreach ($tab3_counties as $c) {
                $c_clean = trim(preg_replace('/\bcounty\b/i', '', $c));
                $phrases[] = $svc . ' in ' . $this->e($c_clean);
            }
            $tab3_suffix = ' We manage campaigns and digital growth throughout the territory, including ' . implode(', ', $phrases) . '.';
        }

        if (!empty($tab4_counties)) {
            $phrases = array();
            foreach ($tab4_counties as $c) {
                $c_clean = trim(preg_replace('/\bcounty\b/i', '', $c));
                $phrases[] = $svc . ' in ' . $this->e($c_clean);
            }
            $tab4_suffix = ' We provide attribution and tracking solutions for businesses locally, including ' . implode(', ', $phrases) . '.';
        }

        $html  = '<div style="max-width:1100px;margin:0 auto;font-family:inherit;">';
        $html .= '  <div style="display:flex;flex-direction:row;flex-wrap:nowrap;gap:10px;justify-content:space-between;align-items:center;margin-bottom:28px;width:100%;">';
        $html .= '    <button onclick="vcpgSwitchTab(0)" class="vcpg-tab-btn" style="flex:1;min-width:0;white-space:nowrap;background:#FFFFFF;color:#1E293B;padding:12px 10px;border-radius:4px;font-weight:600;font-size:13px;border:1px solid #0F172A;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.05);transition:all 0.2s;text-align:center;">Strategic AI Implementation</button>';
        $html .= '    <button onclick="vcpgSwitchTab(1)" class="vcpg-tab-btn" style="flex:1;min-width:0;white-space:nowrap;background:#0F172A;color:#FFFFFF;padding:12px 10px;border-radius:4px;font-weight:600;font-size:13px;border:1px solid #0F172A;cursor:pointer;box-shadow:none;transition:all 0.2s;text-align:center;">Dedicated Industry Specialists</button>';
        $html .= '    <button onclick="vcpgSwitchTab(2)" class="vcpg-tab-btn" style="flex:1;min-width:0;white-space:nowrap;background:#0F172A;color:#FFFFFF;padding:12px 10px;border-radius:4px;font-weight:600;font-size:13px;border:1px solid #0F172A;cursor:pointer;box-shadow:none;transition:all 0.2s;text-align:center;">Holistic Campaign Management</button>';
        $html .= '    <button onclick="vcpgSwitchTab(3)" class="vcpg-tab-btn" style="flex:1;min-width:0;white-space:nowrap;background:#0F172A;color:#FFFFFF;padding:12px 10px;border-radius:4px;font-weight:600;font-size:13px;border:1px solid #0F172A;cursor:pointer;box-shadow:none;transition:all 0.2s;text-align:center;">Advanced Attribution Modeling</button>';
        $html .= '  </div>';

        // Tab Panels Box
        $html .= '  <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:4px;padding:36px 40px;box-shadow:0 2px 10px rgba(0,0,0,0.02);text-align:left;">';

        // Generate keyword-rich panel text (expanded to ~80-100 words each with state mentions)
        $panel_texts = array(
            'Vispan Solutions integrates cutting-edge machine learning models to forecast trends and optimize targeting, ensuring campaigns adapt dynamically to changing local consumer behaviors and market conditions in ' . $city . ', ' . $state . '. Our data-driven digital marketing strategy leverages advanced analytics, conversion optimization, and performance tracking to deliver measurable ROI for businesses seeking top-rated online marketing solutions. By deploying predictive modeling and real-time audience segmentation across the ' . $state . ' region, we help local businesses establish a dominant search presence and sustain long-term digital authority.',
            'Our team brings deep domain expertise across SEO, PPC, social media marketing, branding, and content creation tailored specifically to the ' . $city . ', ' . $state . ' market. As a trusted digital marketing agency, we combine local search optimization, Google Ads management, and strategic campaign planning to ensure your business stays ahead of local competitors. Our seasoned professionals customize outreach tactics to align with local demographic needs, ensuring every local campaign achieves maximum exposure and high-quality lead generation in ' . $state . '.' . $tab2_suffix,
            'From website optimization and paid ad acquisition to reputation management and email marketing, we manage every touchpoint of your digital presence in ' . $city . ', ' . $state . '. Our comprehensive approach includes conversion rate optimization, lead generation, social media management, and search engine marketing — delivering end-to-end strategy with precision for ' . $city . ' businesses. We implement multi-layered SEO blueprints and interactive content funnels to ensure your brand stands out, converts visitors, and maintains a distinct competitive edge.' . $tab3_suffix,
            'Track every lead, call, and appointment with complete attribution clarity. We deliver detailed performance dashboards, Google Analytics reporting, and actionable growth insights to maximize your ROI. Our advanced marketing analytics cover cost-per-acquisition, customer lifetime value, and multi-channel attribution modeling for ' . $city . ', ' . $state . ' businesses. Through granular conversion tracking and transparent data sharing, we verify that every advertising dollar directly contributes to your bottom-line success.' . $tab4_suffix
        );

        for ($i = 0; $i < 4; $i++) {
            $display = ($i === 0) ? 'block' : 'none';
            $html .= '    <div class="vcpg-tab-panel" style="display:' . $display . ';">';
            $html .= '      <p style="margin:0;font-size:1.05rem;color:#121212;line-height:1.75;">' . $panel_texts[$i] . '</p>';
            $html .= '    </div>';
        }

        $html .= '  </div>';

        $html .= '<script>
function vcpgSwitchTab(idx) {
  var btns = document.querySelectorAll(".vcpg-tab-btn");
  var panels = document.querySelectorAll(".vcpg-tab-panel");
  btns.forEach(function(btn, i) {
    if(i === idx) {
      btn.style.background = "#FFFFFF";
      btn.style.color = "#1E293B";
      btn.style.border = "1px solid #0F172A";
      btn.style.boxShadow = "0 1px 3px rgba(0,0,0,0.05)";
    } else {
      btn.style.background = "#0F172A";
      btn.style.color = "#FFFFFF";
      btn.style.border = "1px solid #0F172A";
      btn.style.boxShadow = "none";
    }
  });
  panels.forEach(function(panel, i) {
    panel.style.display = (i === idx) ? "block" : "none";
  });
}
</script>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Generate 1000+ service-specific keyword phrases for SEO density.
     * Keywords are combinations of base terms, modifiers, geo-variations,
     * and action prefixes tailored to the service type and city.
     */
    private function generate_keyword_pool($data)
    {
        $city = isset($data['city']) ? $data['city'] : 'Los Angeles';
        $state = isset($data['state']) ? $data['state'] : 'California';
        $service = isset($data['service']) ? $data['service'] : 'Digital Marketing';
        $svc_lower = strtolower($service);

        // --- Base service keywords (vary by service type) ---
        $common_bases = array(
            $svc_lower, $svc_lower . ' agency', $svc_lower . ' company',
            $svc_lower . ' firm', $svc_lower . ' services', $svc_lower . ' solutions',
            $svc_lower . ' consultant', $svc_lower . ' expert', $svc_lower . ' specialist',
            $svc_lower . ' provider', $svc_lower . ' team', $svc_lower . ' partner',
        );

        // Service-specific sub-keywords
        $service_keywords = $this->get_service_keyword_list($svc_lower);

        // Marketing general keywords
        $marketing_bases = array(
            'online marketing', 'internet marketing', 'digital advertising',
            'brand marketing', 'performance marketing', 'growth marketing',
            'marketing automation', 'marketing analytics', 'marketing strategy',
            'lead generation', 'conversion optimization', 'customer acquisition',
            'digital strategy', 'online advertising', 'digital presence',
            'online branding', 'digital branding', 'web marketing',
            'inbound marketing', 'outbound marketing', 'content marketing',
            'email marketing', 'video marketing', 'mobile marketing',
            'affiliate marketing', 'influencer marketing', 'remarketing',
            'retargeting', 'programmatic advertising', 'native advertising',
            'display advertising', 'search engine marketing', 'paid search',
            'organic search', 'local search', 'voice search optimization',
        );

        // Combine all base keywords
        $all_bases = array_merge($common_bases, $service_keywords, $marketing_bases);
        $all_bases = array_unique($all_bases);

        // --- Modifiers ---
        $modifiers = array(
            'best', 'top', 'leading', 'trusted', 'professional', 'expert',
            'affordable', 'premium', 'certified', 'experienced', 'reliable',
            'proven', 'award-winning', 'strategic', 'specialized', 'result-driven',
            'data-driven', 'innovative', 'custom', 'advanced', 'comprehensive',
            'effective', 'modern', 'smart', 'targeted', 'scalable',
            'top-rated', 'reputable', 'established', 'recognized', 'full-service',
        );

        // --- Geo variations ---
        $geo_variations = array(
            'in ' . $city,
            $city . ' ' . $state,
            'near ' . $city,
            $city . ' area',
            'for ' . $city . ' businesses',
            $city,
        );

        // --- Action prefixes ---
        $action_prefixes = array(
            'hire', 'find', 'get', 'book', 'contact',
            'compare', 'choose', 'discover', 'explore',
        );

        // --- Suffix phrases ---
        $suffixes = array(
            'for small business', 'for startups', 'for enterprises',
            'for local business', 'for ecommerce', 'for healthcare',
            'for real estate', 'for restaurants', 'for B2B',
            'for B2C', 'for service providers', 'for professionals',
            'near me', 'that delivers results', 'with proven ROI',
            'with free consultation', 'with guaranteed results',
        );

        $keywords = array();

        // Pattern 1: modifier + base + geo  (e.g., "best digital marketing agency in Los Angeles")
        foreach ($modifiers as $mod) {
            foreach ($all_bases as $base) {
                foreach ($geo_variations as $geo) {
                    $keywords[] = $mod . ' ' . $base . ' ' . $geo;
                    if (count($keywords) >= 500) break 3;
                }
            }
        }

        // Pattern 2: base + geo  (e.g., "digital marketing agency Los Angeles California")
        foreach ($all_bases as $base) {
            foreach ($geo_variations as $geo) {
                $keywords[] = $base . ' ' . $geo;
            }
        }

        // Pattern 3: action + modifier + base + geo  (e.g., "hire best SEO expert in Los Angeles")
        foreach ($action_prefixes as $action) {
            foreach ($modifiers as $mod) {
                foreach ($common_bases as $base) {
                    $geo = $geo_variations[array_rand($geo_variations)];
                    $keywords[] = $action . ' ' . $mod . ' ' . $base . ' ' . $geo;
                    if (count($keywords) >= 900) break 3;
                }
            }
        }

        // Pattern 4: base + suffix  (e.g., "digital marketing for small business")
        foreach ($all_bases as $base) {
            foreach ($suffixes as $suffix) {
                $keywords[] = $base . ' ' . $suffix;
            }
        }

        // Pattern 5: modifier + base + suffix  (e.g., "affordable SEO services for startups")
        foreach ($modifiers as $mod) {
            foreach ($common_bases as $base) {
                $suffix = $suffixes[array_rand($suffixes)];
                $keywords[] = $mod . ' ' . $base . ' ' . $suffix;
            }
        }

        // Pattern 6: city-specific long-tail  (e.g., "Los Angeles digital marketing agency near me")
        $longtail_templates = array(
            $city . ' ' . $svc_lower . ' agency near me',
            $city . ' ' . $svc_lower . ' company reviews',
            $city . ' ' . $svc_lower . ' pricing',
            $city . ' ' . $svc_lower . ' packages',
            $city . ' ' . $svc_lower . ' cost',
            $city . ' ' . $svc_lower . ' quotes',
            $city . ' ' . $svc_lower . ' consultation',
            $city . ' ' . $svc_lower . ' free audit',
            'how much does ' . $svc_lower . ' cost in ' . $city,
            'why hire a ' . $svc_lower . ' agency in ' . $city,
            $svc_lower . ' ROI for ' . $city . ' businesses',
            $svc_lower . ' trends in ' . $city . ' ' . date('Y'),
            $svc_lower . ' case studies ' . $city,
            $svc_lower . ' success stories ' . $city,
            $city . ' ' . $state . ' ' . $svc_lower . ' experts',
            'local ' . $svc_lower . ' ' . $city,
            $svc_lower . ' for ' . $city . ' startups',
            $svc_lower . ' for ' . $city . ' small business',
            $svc_lower . ' agency ' . $city . ' ' . $state,
            'white label ' . $svc_lower . ' ' . $city,
            'outsource ' . $svc_lower . ' ' . $city,
            $svc_lower . ' proposal ' . $city,
            $svc_lower . ' strategy ' . $city,
            $svc_lower . ' audit ' . $city,
            $svc_lower . ' report ' . $city,
        );
        $keywords = array_merge($keywords, $longtail_templates);

        // Deduplicate and clean
        $keywords = array_map('strtolower', $keywords);
        $keywords = array_map('trim', $keywords);
        $keywords = array_unique($keywords);
        $keywords = array_values($keywords);

        // Shuffle for natural distribution
        shuffle($keywords);

        // Ensure minimum 1000
        if (count($keywords) < 1000) {
            // Generate additional geo combinations to hit 1000
            $extra_geos = array(
                'around ' . $city, 'close to ' . $city, $city . ' metro',
                $city . ' downtown', $city . ' county', 'greater ' . $city,
                $state . ' ' . $city, $city . ' and surrounding areas',
            );
            foreach ($extra_geos as $eg) {
                foreach ($all_bases as $base) {
                    $keywords[] = $base . ' ' . $eg;
                    if (count($keywords) >= 1200) break 2;
                }
            }
            $keywords = array_unique($keywords);
            $keywords = array_values($keywords);
            shuffle($keywords);
        }

        return $keywords;
    }

    /**
     * Return an array of sub-service keyword phrases specific to the service type.
     */
    private function get_service_keyword_list($service_lower)
    {
        $seo_keywords = array(
            'seo', 'search engine optimization', 'local seo', 'technical seo',
            'on-page seo', 'off-page seo', 'link building', 'keyword research',
            'google maps optimization', 'citation building', 'seo audit',
            'seo consulting', 'national seo', 'enterprise seo', 'ecommerce seo',
            'content seo', 'seo strategy', 'organic traffic', 'google ranking',
            'serp optimization', 'backlink building', 'domain authority',
            'page speed optimization', 'core web vitals', 'schema markup',
            'google my business optimization', 'local pack ranking',
            'seo for dentists', 'seo for lawyers', 'seo for doctors',
            'seo for contractors', 'seo for restaurants', 'seo for real estate',
            'monthly seo services', 'seo management', 'seo packages',
            'white hat seo', 'organic search optimization', 'google penalty recovery',
            'mobile seo', 'voice search seo', 'video seo', 'image seo',
            'seo competitor analysis', 'keyword mapping', 'content optimization',
            'meta tag optimization', 'title tag optimization', 'internal linking',
            'xml sitemap', 'robots.txt optimization', 'canonical tags',
        );

        $ppc_keywords = array(
            'ppc', 'pay per click', 'google ads', 'google adwords',
            'facebook ads', 'instagram ads', 'linkedin ads', 'youtube ads',
            'display advertising', 'remarketing', 'retargeting',
            'shopping ads', 'ppc management', 'ppc agency', 'paid search',
            'ad campaign management', 'bid management', 'quality score optimization',
            'landing page optimization', 'ad copywriting', 'conversion tracking',
            'cost per click optimization', 'cost per acquisition', 'roas optimization',
            'google shopping', 'performance max campaigns', 'responsive search ads',
            'dynamic search ads', 'call-only ads', 'app install campaigns',
            'ppc audit', 'ppc strategy', 'negative keyword management',
            'ad extensions', 'audience targeting', 'lookalike audiences',
            'custom audiences', 'geo-targeted ads', 'dayparting',
            'ad scheduling', 'a/b testing ads', 'ad creative optimization',
            'microsoft ads', 'bing ads', 'amazon ads', 'tiktok ads',
            'twitter ads', 'pinterest ads', 'snapchat ads', 'programmatic ads',
        );

        $social_keywords = array(
            'social media marketing', 'social media management', 'social media strategy',
            'social media advertising', 'facebook marketing', 'instagram marketing',
            'linkedin marketing', 'twitter marketing', 'tiktok marketing',
            'youtube marketing', 'pinterest marketing', 'social media content',
            'social media branding', 'community management', 'social media analytics',
            'influencer marketing', 'social media campaigns', 'social media audit',
            'social media optimization', 'social media engagement',
            'social media for business', 'social media posting', 'content calendar',
            'social media scheduling', 'organic social media', 'paid social media',
            'social media roi', 'social media reputation', 'social listening',
            'user-generated content', 'social commerce', 'shoppable posts',
            'reels marketing', 'stories marketing', 'live streaming marketing',
            'social media customer service', 'brand awareness campaigns',
        );

        $web_design_keywords = array(
            'web design', 'website design', 'website development', 'web development',
            'responsive web design', 'mobile-first design', 'ecommerce website',
            'wordpress development', 'shopify development', 'custom web design',
            'landing page design', 'ui/ux design', 'website redesign',
            'website maintenance', 'website hosting', 'website speed optimization',
            'website security', 'ssl certificate', 'website migration',
            'website analytics setup', 'conversion-focused design',
            'cms development', 'website copywriting', 'website seo',
            'portfolio website', 'corporate website', 'small business website',
            'website accessibility', 'ada compliant website', 'website audit',
            'website consulting', 'website support', 'website updates',
            'website backup', 'domain registration', 'website builder',
        );

        $content_keywords = array(
            'content marketing', 'content strategy', 'blog writing',
            'article writing', 'copywriting', 'seo content writing',
            'content creation', 'content distribution', 'content optimization',
            'content calendar', 'editorial planning', 'thought leadership',
            'whitepapers', 'case studies', 'ebooks', 'infographics',
            'video content', 'podcast production', 'email newsletters',
            'press releases', 'ghostwriting', 'technical writing',
            'brand storytelling', 'content audit', 'content gap analysis',
            'pillar page strategy', 'topic cluster strategy', 'evergreen content',
            'content repurposing', 'content performance tracking',
        );

        $general_digital = array(
            'digital marketing', 'online marketing', 'internet marketing',
            'digital strategy', 'marketing consulting', 'brand strategy',
            'competitive analysis', 'market research', 'customer journey mapping',
            'conversion rate optimization', 'cro', 'analytics setup',
            'google analytics', 'google tag manager', 'heatmap analysis',
            'funnel optimization', 'lead nurturing', 'email marketing',
            'marketing automation', 'crm integration', 'hubspot marketing',
            'salesforce marketing', 'mailchimp marketing', 'drip campaigns',
            'reputation management', 'online reputation', 'review management',
            'google reviews', 'yelp marketing', 'public relations',
            'crisis management', 'brand monitoring', 'media buying',
        );

        // Combine based on service type detection
        $all_keywords = $general_digital;

        if (stripos($service_lower, 'seo') !== false || stripos($service_lower, 'search') !== false) {
            $all_keywords = array_merge($all_keywords, $seo_keywords);
        }
        if (stripos($service_lower, 'ppc') !== false || stripos($service_lower, 'ads') !== false || stripos($service_lower, 'advertising') !== false || stripos($service_lower, 'paid') !== false) {
            $all_keywords = array_merge($all_keywords, $ppc_keywords);
        }
        if (stripos($service_lower, 'social') !== false || stripos($service_lower, 'media') !== false) {
            $all_keywords = array_merge($all_keywords, $social_keywords);
        }
        if (stripos($service_lower, 'web') !== false || stripos($service_lower, 'design') !== false || stripos($service_lower, 'development') !== false) {
            $all_keywords = array_merge($all_keywords, $web_design_keywords);
        }
        if (stripos($service_lower, 'content') !== false || stripos($service_lower, 'writing') !== false || stripos($service_lower, 'blog') !== false) {
            $all_keywords = array_merge($all_keywords, $content_keywords);
        }

        // For generic "digital marketing" or unmatched services, include everything
        if (stripos($service_lower, 'digital marketing') !== false || stripos($service_lower, 'marketing agency') !== false) {
            $all_keywords = array_merge($all_keywords, $seo_keywords, $ppc_keywords, $social_keywords, $web_design_keywords, $content_keywords);
        }

        return array_unique($all_keywords);
    }

    private function testimonial_html($data)
    {
        $reviews = array(
            array(
                'text' => 'I recently worked with Vispan Solutions to develop a new website for my business, and I could not be more pleased with the results. The team at Vispan Solutions incorporated my vision into their design perfectly and was incredibly responsive to my requests.',
                'author' => 'RONIT SHARMA',
                'title' => 'CEO',
            ),
            array(
                'text' => 'Vispan Solutions is the best web development company for e-commerce. Our customers have seen immense success in their projects due to our industry-leading expertise and commitment to customer service.',
                'author' => 'MEGHNA JADAV',
                'title' => 'CEO',
            ),
            array(
                'text' => 'I recently needed help with developing a new website in Shopify and knew that I wanted to work with an experienced team. After researching on the web, I came across Vispan Solutions. Their extensive portfolio of projects made it clear that they would be a great choice for me.',
                'author' => 'EMERSON STRAW',
                'title' => 'CEO',
            ),
        );

        $slides_list = $reviews;
        $slides_list[] = $reviews[0]; // clone first slide for infinite seamless right-to-left loop

        $uid = 'vcpg_t_' . uniqid();

        $html  = '<div id="' . $uid . '" style="max-width:850px;margin:0 auto;padding:10px 20px 40px;position:relative;font-family:inherit;">';
        $html .= '<div style="text-align:center;font-size:3rem;font-family:Georgia,serif;font-weight:900;color:#0F172A;line-height:1;margin-bottom:16px;">&#8221;</div>';
        
        $html .= '<div style="display:flex;align-items:center;justify-content:space-between;gap:20px;">';
        
        // Prev button
        $html .= '<button class="vcpg-t-prev" aria-label="Previous review" style="width:48px;height:48px;border-radius:50%;border:1px solid #E2E8F0;background:#FFFFFF;box-shadow:0 4px 14px rgba(0,0,0,0.06);cursor:pointer;display:flex;align-items:center;justify-content:center;color:#0F172A;font-size:1.1rem;flex-shrink:0;transition:all 0.2s ease;outline:none;" onmouseover="this.style.background=\'#F8FAFC\';this.style.transform=\'scale(1.05)\';" onmouseout="this.style.background=\'#FFFFFF\';this.style.transform=\'scale(1)\';">&#10094;</button>';
        
        // Slides mask & flex track for Right-to-Left Slide Transition
        $html .= '<div style="flex-grow:1;max-width:680px;text-align:center;overflow:hidden;position:relative;min-height:160px;display:flex;align-items:center;">';
        $html .= '<div class="vcpg-t-track" style="display:flex;width:100%;transition:transform 0.5s cubic-bezier(0.25, 1, 0.5, 1);will-change:transform;">';
        
        foreach ($slides_list as $idx => $r) {
            $html .= '<div class="vcpg-t-slide" style="width:100%;flex-shrink:0;box-sizing:border-box;padding:0 10px;text-align:center;">';
            $html .= '<p style="font-size:1.08rem;color:#121212;line-height:1.75;font-style:italic;margin:0 0 24px 0;font-weight:400;">' . $this->e($r['text']) . '</p>';
            $html .= '<div style="font-weight:800;color:#0A3663;font-size:1rem;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">' . $this->e($r['author']) . '</div>';
            $html .= '<div style="font-size:0.85rem;color:#64748B;font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">' . $this->e($r['title']) . '</div>';
            $html .= '</div>';
        }

        $html .= '</div>'; // end track
        $html .= '</div>'; // end mask
        
        // Next button
        $html .= '<button class="vcpg-t-next" aria-label="Next review" style="width:48px;height:48px;border-radius:50%;border:1px solid #E2E8F0;background:#FFFFFF;box-shadow:0 4px 14px rgba(0,0,0,0.06);cursor:pointer;display:flex;align-items:center;justify-content:center;color:#0F172A;font-size:1.1rem;flex-shrink:0;transition:all 0.2s ease;outline:none;" onmouseover="this.style.background=\'#F8FAFC\';this.style.transform=\'scale(1.05)\';" onmouseout="this.style.background=\'#FFFFFF\';this.style.transform=\'scale(1)\';">&#10095;</button>';
        
        $html .= '</div>'; // end flex container

        // Auto-rotation JS (3 seconds) with continuous Right-to-Left Slide effect
        $html .= '<script>
        (function(){
            var root = document.getElementById("' . $uid . '");
            if(!root) return;
            var track = root.querySelector(".vcpg-t-track");
            var prevBtn = root.querySelector(".vcpg-t-prev");
            var nextBtn = root.querySelector(".vcpg-t-next");
            var current = 0;
            var realTotal = 3;
            var isAnimating = false;
            var timer = null;

            function goToSlide(index, animate) {
                if(animate) {
                    track.style.transition = "transform 0.5s cubic-bezier(0.25, 1, 0.5, 1)";
                } else {
                    track.style.transition = "none";
                }
                track.style.transform = "translateX(-" + (index * 100) + "%)";
                current = index;
            }

            function nextSlide() {
                if(isAnimating) return;
                isAnimating = true;
                current++;
                goToSlide(current, true);

                if(current === realTotal) {
                    setTimeout(function(){
                        goToSlide(0, false);
                        isAnimating = false;
                    }, 500);
                } else {
                    setTimeout(function(){
                        isAnimating = false;
                    }, 500);
                }
            }

            function prevSlide() {
                if(isAnimating) return;
                isAnimating = true;
                if(current === 0) {
                    goToSlide(realTotal, false);
                    setTimeout(function(){
                        current = realTotal - 1;
                        goToSlide(current, true);
                        setTimeout(function(){
                            isAnimating = false;
                        }, 500);
                    }, 20);
                } else {
                    current--;
                    goToSlide(current, true);
                    setTimeout(function(){
                        isAnimating = false;
                    }, 500);
                }
            }

            function startTimer() {
                stopTimer();
                timer = setInterval(nextSlide, 3000);
            }

            function stopTimer() {
                if(timer) clearInterval(timer);
            }

            if(prevBtn) {
                prevBtn.addEventListener("click", function() {
                    prevSlide();
                    startTimer();
                });
            }
            if(nextBtn) {
                nextBtn.addEventListener("click", function() {
                    nextSlide();
                    startTimer();
                });
            }

            root.addEventListener("mouseenter", stopTimer);
            root.addEventListener("mouseleave", startTimer);

            startTimer();
        })();
        </script>';

        $html .= '</div>';
        return $html;
    }

    private function portfolio_html()
    {
        $projects = array(
            array('label' => 'RLEY HAAS', 'bg' => '#E0E7FF'),
            array('label' => 'DİZREAL', 'bg' => '#FEF3C7'),
            array('label' => 'Moving Beyond', 'bg' => '#D1FAE5'),
            array('label' => 'Yoga with Lynn', 'bg' => '#FCE7F3'),
            array('label' => 'Record Vinyl', 'bg' => '#F3E8FF'),
            array('label' => 'MDRG Campaign', 'bg' => '#CFFAFE'),
        );

        $html = '';
        foreach($projects as $p)
        {
            $html .= '<div style="height:220px;border-radius:16px;display:flex;align-items:center;justify-content:center;padding:18px;color:#0A3663;font-weight:800;font-size:1.2rem;background:' . $p['bg'] . ';border:1px solid #E2E8F0;box-shadow:0 4px 14px rgba(0,0,0,0.04);">';
            $html .= $this->e($p['label']);
            $html .= '</div>';
        }
        return $html;
    }

    private function logos_html()
    {
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

        $render_logos = function($items_arr) {
            $logos_html = '';
            foreach($items_arr as $item) {
                $logos_html .= '<div style="display:inline-flex;align-items:center;justify-content:center;margin-right:50px;height:50px;box-sizing:border-box;flex-shrink:0;">'
                             . '<img decoding="async" src="' . esc_url($item['src']) . '" alt="' . esc_attr($item['alt']) . '" style="max-height:42px;max-width:160px;width:auto;height:auto;object-fit:contain;display:block;">'
                             . '</div>';
            }
            return $logos_html;
        };

        $logos_set = $render_logos($logo_images);

        $html  = '<div class="vcpg-marquee-container" style="width:100%;overflow:hidden;position:relative;padding:16px 0;mask-image:linear-gradient(to right, transparent, black 6%, black 94%, transparent);-webkit-mask-image:linear-gradient(to right, transparent, black 6%, black 94%, transparent);">';
        $html .= '<style>
          @keyframes vcpgMarqueeScroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
          }
          .vcpg-marquee-track {
            display: flex;
            align-items: center;
            width: max-content;
            animation: vcpgMarqueeScroll 35s linear infinite;
          }
          .vcpg-marquee-container:hover .vcpg-marquee-track {
            animation-play-state: paused;
          }
        </style>';
        $html .= '<div class="vcpg-marquee-track">';
        $html .= $logos_set . $logos_set; // duplicate set for seamless infinite loop
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private function certifications_html()
    {
        $certs = array(
            array('title' => 'Google Analytics', 'img' => $this->get_asset_url('google-analytics..webp')),
            array('title' => 'Google Ads Video Certification', 'img' => $this->get_asset_url('google-ad-video.png')),
            array('title' => 'Shopping Ads Certification', 'img' => $this->get_asset_url('shopping-ad-creation.png')),
            array('title' => 'Google Ads Search Certification', 'img' => $this->get_asset_url('google-ad-search.webp')),
            array('title' => 'Google Ads – Measurement Certification', 'img' => $this->get_asset_url('google-ad-measurment.webp')),
            array('title' => 'Google Ads Display Certification', 'img' => $this->get_asset_url('google-display-ad.png')),
        );

        $html  = '<div style="max-width:1180px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:48px;box-sizing:border-box;padding:10px 0;width:100%;">';
        
        // Left Google Partner Badge (Matching Image-1 1:1)
        $html .= '  <div style="flex-shrink:0;">';
        $html .= '    <a href="https://www.google.com/partners/agency/partner/" target="_blank" rel="noopener" style="display:block;text-decoration:none;" aria-label="Google Partner Page">';
        $html .= '      <img src="' . $this->get_asset_url('google-partner.png') . '" alt="Google Partner" style="width:205px;height:auto;display:block;">';
        $html .= '    </a>';
        $html .= '  </div>';

        // Right Certifications Grid (3 columns x 2 rows matching Image-1)
        $html .= '  <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:28px 36px;align-items:center;flex:1;">';
        foreach($certs as $c)
        {
            $html .= '<div style="display:flex;align-items:center;gap:14px;">';
            $html .= '<img src="' . $c['img'] . '" alt="' . $this->e($c['title']) . '" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;">';
            $html .= '<div style="font-weight:600;font-size:0.92rem;color:#222222;line-height:1.35;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">' . $this->e($c['title']) . '</div>';
            $html .= '</div>';
        }
        $html .= '  </div>';
        $html .= '</div>';
        return $html;
    }

    private function contact_form_html()
    {
        return $this->build_proposal_form('contact_proposal');
    }

    private function build_proposal_form($form_id)
    {
        $countries = array(
            array('c'=>'+91','n'=>'India','i'=>'IND'),
            array('c'=>'+1','n'=>'United States','i'=>'USA'),
            array('c'=>'+44','n'=>'United Kingdom','i'=>'GBR'),
            array('c'=>'+61','n'=>'Australia','i'=>'AUS'),
            array('c'=>'+971','n'=>'United Arab Emirates','i'=>'ARE'),
            array('c'=>'+65','n'=>'Singapore','i'=>'SGP'),
            array('c'=>'+1','n'=>'Canada','i'=>'CAN'),
            array('c'=>'+93','n'=>'Afghanistan','i'=>'AFG'),
            array('c'=>'+355','n'=>'Albania','i'=>'ALB'),
            array('c'=>'+213','n'=>'Algeria','i'=>'DZA'),
            array('c'=>'+376','n'=>'Andorra','i'=>'AND'),
            array('c'=>'+244','n'=>'Angola','i'=>'AGO'),
            array('c'=>'+54','n'=>'Argentina','i'=>'ARG'),
            array('c'=>'+374','n'=>'Armenia','i'=>'ARM'),
            array('c'=>'+43','n'=>'Austria','i'=>'AUT'),
            array('c'=>'+994','n'=>'Azerbaijan','i'=>'AZE'),
            array('c'=>'+1','n'=>'Bahamas','i'=>'BHS'),
            array('c'=>'+973','n'=>'Bahrain','i'=>'BHR'),
            array('c'=>'+880','n'=>'Bangladesh','i'=>'BGD'),
            array('c'=>'+375','n'=>'Belarus','i'=>'BLR'),
            array('c'=>'+32','n'=>'Belgium','i'=>'BEL'),
            array('c'=>'+501','n'=>'Belize','i'=>'BLZ'),
            array('c'=>'+229','n'=>'Benin','i'=>'BEN'),
            array('c'=>'+975','n'=>'Bhutan','i'=>'BTN'),
            array('c'=>'+591','n'=>'Bolivia','i'=>'BOL'),
            array('c'=>'+387','n'=>'Bosnia and Herzegovina','i'=>'BIH'),
            array('c'=>'+267','n'=>'Botswana','i'=>'BWA'),
            array('c'=>'+55','n'=>'Brazil','i'=>'BRA'),
            array('c'=>'+673','n'=>'Brunei','i'=>'BRN'),
            array('c'=>'+359','n'=>'Bulgaria','i'=>'BGR'),
            array('c'=>'+226','n'=>'Burkina Faso','i'=>'BFA'),
            array('c'=>'+257','n'=>'Burundi','i'=>'BDI'),
            array('c'=>'+855','n'=>'Cambodia','i'=>'KHM'),
            array('c'=>'+237','n'=>'Cameroon','i'=>'CMR'),
            array('c'=>'+238','n'=>'Cape Verde','i'=>'CPV'),
            array('c'=>'+236','n'=>'Central African Republic','i'=>'CAF'),
            array('c'=>'+235','n'=>'Chad','i'=>'TCD'),
            array('c'=>'+56','n'=>'Chile','i'=>'CHL'),
            array('c'=>'+86','n'=>'China','i'=>'CHN'),
            array('c'=>'+57','n'=>'Colombia','i'=>'COL'),
            array('c'=>'+269','n'=>'Comoros','i'=>'COM'),
            array('c'=>'+242','n'=>'Congo','i'=>'COG'),
            array('c'=>'+243','n'=>'Congo (DRC)','i'=>'COD'),
            array('c'=>'+506','n'=>'Costa Rica','i'=>'CRI'),
            array('c'=>'+385','n'=>'Croatia','i'=>'HRV'),
            array('c'=>'+53','n'=>'Cuba','i'=>'CUB'),
            array('c'=>'+357','n'=>'Cyprus','i'=>'CYP'),
            array('c'=>'+420','n'=>'Czech Republic','i'=>'CZE'),
            array('c'=>'+45','n'=>'Denmark','i'=>'DNK'),
            array('c'=>'+253','n'=>'Djibouti','i'=>'DJI'),
            array('c'=>'+1','n'=>'Dominica','i'=>'DMA'),
            array('c'=>'+1','n'=>'Dominican Republic','i'=>'DOM'),
            array('c'=>'+593','n'=>'Ecuador','i'=>'ECU'),
            array('c'=>'+20','n'=>'Egypt','i'=>'EGY'),
            array('c'=>'+503','n'=>'El Salvador','i'=>'SLV'),
            array('c'=>'+240','n'=>'Equatorial Guinea','i'=>'GNQ'),
            array('c'=>'+291','n'=>'Eritrea','i'=>'ERI'),
            array('c'=>'+372','n'=>'Estonia','i'=>'EST'),
            array('c'=>'+268','n'=>'Eswatini','i'=>'SWZ'),
            array('c'=>'+251','n'=>'Ethiopia','i'=>'ETH'),
            array('c'=>'+679','n'=>'Fiji','i'=>'FJI'),
            array('c'=>'+358','n'=>'Finland','i'=>'FIN'),
            array('c'=>'+33','n'=>'France','i'=>'FRA'),
            array('c'=>'+241','n'=>'Gabon','i'=>'GAB'),
            array('c'=>'+220','n'=>'Gambia','i'=>'GMB'),
            array('c'=>'+995','n'=>'Georgia','i'=>'GEO'),
            array('c'=>'+49','n'=>'Germany','i'=>'DEU'),
            array('c'=>'+233','n'=>'Ghana','i'=>'GHA'),
            array('c'=>'+30','n'=>'Greece','i'=>'GRC'),
            array('c'=>'+1','n'=>'Grenada','i'=>'GRD'),
            array('c'=>'+502','n'=>'Guatemala','i'=>'GTM'),
            array('c'=>'+224','n'=>'Guinea','i'=>'GIN'),
            array('c'=>'+245','n'=>'Guinea-Bissau','i'=>'GNB'),
            array('c'=>'+592','n'=>'Guyana','i'=>'GUY'),
            array('c'=>'+509','n'=>'Haiti','i'=>'HTI'),
            array('c'=>'+504','n'=>'Honduras','i'=>'HND'),
            array('c'=>'+852','n'=>'Hong Kong','i'=>'HKG'),
            array('c'=>'+36','n'=>'Hungary','i'=>'HUN'),
            array('c'=>'+354','n'=>'Iceland','i'=>'ISL'),
            array('c'=>'+62','n'=>'Indonesia','i'=>'IDN'),
            array('c'=>'+98','n'=>'Iran','i'=>'IRN'),
            array('c'=>'+964','n'=>'Iraq','i'=>'IRQ'),
            array('c'=>'+353','n'=>'Ireland','i'=>'IRL'),
            array('c'=>'+972','n'=>'Israel','i'=>'ISR'),
            array('c'=>'+39','n'=>'Italy','i'=>'ITA'),
            array('c'=>'+1','n'=>'Jamaica','i'=>'JAM'),
            array('c'=>'+81','n'=>'Japan','i'=>'JPN'),
            array('c'=>'+962','n'=>'Jordan','i'=>'JOR'),
            array('c'=>'+7','n'=>'Kazakhstan','i'=>'KAZ'),
            array('c'=>'+254','n'=>'Kenya','i'=>'KEN'),
            array('c'=>'+686','n'=>'Kiribati','i'=>'KIR'),
            array('c'=>'+965','n'=>'Kuwait','i'=>'KWT'),
            array('c'=>'+996','n'=>'Kyrgyzstan','i'=>'KGZ'),
            array('c'=>'+856','n'=>'Laos','i'=>'LAO'),
            array('c'=>'+371','n'=>'Latvia','i'=>'LVA'),
            array('c'=>'+961','n'=>'Lebanon','i'=>'LBN'),
            array('c'=>'+266','n'=>'Lesotho','i'=>'LSO'),
            array('c'=>'+231','n'=>'Liberia','i'=>'LBR'),
            array('c'=>'+218','n'=>'Libya','i'=>'LBY'),
            array('c'=>'+423','n'=>'Liechtenstein','i'=>'LIE'),
            array('c'=>'+370','n'=>'Lithuania','i'=>'LTU'),
            array('c'=>'+352','n'=>'Luxembourg','i'=>'LUX'),
            array('c'=>'+853','n'=>'Macau','i'=>'MAC'),
            array('c'=>'+261','n'=>'Madagascar','i'=>'MDG'),
            array('c'=>'+265','n'=>'Malawi','i'=>'MWI'),
            array('c'=>'+60','n'=>'Malaysia','i'=>'MYS'),
            array('c'=>'+960','n'=>'Maldives','i'=>'MDV'),
            array('c'=>'+223','n'=>'Mali','i'=>'MLI'),
            array('c'=>'+356','n'=>'Malta','i'=>'MLT'),
            array('c'=>'+692','n'=>'Marshall Islands','i'=>'MHL'),
            array('c'=>'+222','n'=>'Mauritania','i'=>'MRT'),
            array('c'=>'+230','n'=>'Mauritius','i'=>'MUS'),
            array('c'=>'+52','n'=>'Mexico','i'=>'MEX'),
            array('c'=>'+691','n'=>'Micronesia','i'=>'FSM'),
            array('c'=>'+373','n'=>'Moldova','i'=>'MDA'),
            array('c'=>'+377','n'=>'Monaco','i'=>'MCO'),
            array('c'=>'+976','n'=>'Mongolia','i'=>'MNG'),
            array('c'=>'+382','n'=>'Montenegro','i'=>'MNE'),
            array('c'=>'+212','n'=>'Morocco','i'=>'MAR'),
            array('c'=>'+258','n'=>'Mozambique','i'=>'MOZ'),
            array('c'=>'+95','n'=>'Myanmar','i'=>'MMR'),
            array('c'=>'+264','n'=>'Namibia','i'=>'NAM'),
            array('c'=>'+674','n'=>'Nauru','i'=>'NRU'),
            array('c'=>'+977','n'=>'Nepal','i'=>'NPL'),
            array('c'=>'+31','n'=>'Netherlands','i'=>'NLD'),
            array('c'=>'+64','n'=>'New Zealand','i'=>'NZL'),
            array('c'=>'+505','n'=>'Nicaragua','i'=>'NIC'),
            array('c'=>'+227','n'=>'Niger','i'=>'NER'),
            array('c'=>'+234','n'=>'Nigeria','i'=>'NGA'),
            array('c'=>'+850','n'=>'North Korea','i'=>'PRK'),
            array('c'=>'+82','n'=>'South Korea','i'=>'KOR'),
            array('c'=>'+389','n'=>'North Macedonia','i'=>'MKD'),
            array('c'=>'+47','n'=>'Norway','i'=>'NOR'),
            array('c'=>'+968','n'=>'Oman','i'=>'OMN'),
            array('c'=>'+92','n'=>'Pakistan','i'=>'PAK'),
            array('c'=>'+680','n'=>'Palau','i'=>'PLW'),
            array('c'=>'+970','n'=>'Palestine','i'=>'PSE'),
            array('c'=>'+507','n'=>'Panama','i'=>'PAN'),
            array('c'=>'+675','n'=>'Papua New Guinea','i'=>'PNG'),
            array('c'=>'+595','n'=>'Paraguay','i'=>'PRY'),
            array('c'=>'+51','n'=>'Peru','i'=>'PER'),
            array('c'=>'+63','n'=>'Philippines','i'=>'PHL'),
            array('c'=>'+48','n'=>'Poland','i'=>'POL'),
            array('c'=>'+351','n'=>'Portugal','i'=>'PRT'),
            array('c'=>'+974','n'=>'Qatar','i'=>'QAT'),
            array('c'=>'+40','n'=>'Romania','i'=>'ROU'),
            array('c'=>'+7','n'=>'Russia','i'=>'RUS'),
            array('c'=>'+250','n'=>'Rwanda','i'=>'RWA'),
            array('c'=>'+1','n'=>'Saint Kitts and Nevis','i'=>'KNA'),
            array('c'=>'+1','n'=>'Saint Lucia','i'=>'LCA'),
            array('c'=>'+1','n'=>'Saint Vincent','i'=>'VCT'),
            array('c'=>'+685','n'=>'Samoa','i'=>'WSM'),
            array('c'=>'+378','n'=>'San Marino','i'=>'SMR'),
            array('c'=>'+239','n'=>'Sao Tome and Principe','i'=>'STP'),
            array('c'=>'+966','n'=>'Saudi Arabia','i'=>'SAU'),
            array('c'=>'+221','n'=>'Senegal','i'=>'SEN'),
            array('c'=>'+381','n'=>'Serbia','i'=>'SRB'),
            array('c'=>'+248','n'=>'Seychelles','i'=>'SYC'),
            array('c'=>'+232','n'=>'Sierra Leone','i'=>'SLE'),
            array('c'=>'+421','n'=>'Slovakia','i'=>'SVK'),
            array('c'=>'+386','n'=>'Slovenia','i'=>'SVN'),
            array('c'=>'+677','n'=>'Solomon Islands','i'=>'SLB'),
            array('c'=>'+252','n'=>'Somalia','i'=>'SOM'),
            array('c'=>'+27','n'=>'South Africa','i'=>'ZAF'),
            array('c'=>'+82','n'=>'South Korea','i'=>'KOR'),
            array('c'=>'+211','n'=>'South Sudan','i'=>'SSD'),
            array('c'=>'+34','n'=>'Spain','i'=>'ESP'),
            array('c'=>'+94','n'=>'Sri Lanka','i'=>'LKA'),
            array('c'=>'+249','n'=>'Sudan','i'=>'SDN'),
            array('c'=>'+597','n'=>'Suriname','i'=>'SUR'),
            array('c'=>'+46','n'=>'Sweden','i'=>'SWE'),
            array('c'=>'+41','n'=>'Switzerland','i'=>'CHE'),
            array('c'=>'+963','n'=>'Syria','i'=>'SYR'),
            array('c'=>'+886','n'=>'Taiwan','i'=>'TWN'),
            array('c'=>'+992','n'=>'Tajikistan','i'=>'TJK'),
            array('c'=>'+255','n'=>'Tanzania','i'=>'TZA'),
            array('c'=>'+66','n'=>'Thailand','i'=>'THA'),
            array('c'=>'+670','n'=>'Timor-Leste','i'=>'TLS'),
            array('c'=>'+228','n'=>'Togo','i'=>'TGO'),
            array('c'=>'+676','n'=>'Tonga','i'=>'TON'),
            array('c'=>'+1','n'=>'Trinidad and Tobago','i'=>'TTO'),
            array('c'=>'+216','n'=>'Tunisia','i'=>'TUN'),
            array('c'=>'+90','n'=>'Turkey','i'=>'TUR'),
            array('c'=>'+993','n'=>'Turkmenistan','i'=>'TKM'),
            array('c'=>'+688','n'=>'Tuvalu','i'=>'TUV'),
            array('c'=>'+256','n'=>'Uganda','i'=>'UGA'),
            array('c'=>'+380','n'=>'Ukraine','i'=>'UKR'),
            array('c'=>'+598','n'=>'Uruguay','i'=>'URY'),
            array('c'=>'+998','n'=>'Uzbekistan','i'=>'UZB'),
            array('c'=>'+678','n'=>'Vanuatu','i'=>'VUT'),
            array('c'=>'+379','n'=>'Vatican City','i'=>'VAT'),
            array('c'=>'+58','n'=>'Venezuela','i'=>'VEN'),
            array('c'=>'+84','n'=>'Vietnam','i'=>'VNM'),
            array('c'=>'+967','n'=>'Yemen','i'=>'YEM'),
            array('c'=>'+260','n'=>'Zambia','i'=>'ZMB'),
            array('c'=>'+263','n'=>'Zimbabwe','i'=>'ZWE')
        );
        $select_arrow = 'data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2220%22%20height%3D%2220%22%20viewBox%3D%220%200%2020%2020%22%3E%3Cpath%20fill%3D%22%231E293B%22%20d%3D%22M5.293%207.293a1%201%200%20011.414%200L10%2010.586l3.293-3.293a1%201%200%20111.414%201.414l-4%204a1%201%200%2001-1.414%200l-4-4a1%201%200%20010-1.414z%22%2F%3E%3C%2Fsvg%3E';
        return '<form id="' . $form_id . '" style="display:flex;flex-direction:column;gap:16px;" onsubmit="return handleFormSubmit_' . $form_id . '(event);">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <input name="fullname" placeholder="Full Name*" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="text" required>
    <input name="email" placeholder="Email Address*" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="email" required>
  </div>
  <div style="display:grid;grid-template-columns:120px 1fr;gap:8px;">
    <div style="position:relative;width:100%;">
      <input name="country_code" id="cc_input_' . $form_id . '" value="+91 IND" placeholder="+91 IND" style="width:100%;padding:13px 8px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:13px;outline:none;box-sizing:border-box;font-family:inherit;text-align:center;cursor:pointer;" autocomplete="off">
      <div id="cc_dropdown_' . $form_id . '" style="position:absolute;top:100%;left:0;right:0;max-height:220px;overflow-y:auto;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:12px;z-index:99999;box-shadow:0 10px 25px rgba(0,0,0,0.15);display:none;margin-top:6px;padding:4px;box-sizing:border-box;text-align:left;">
        <style>
        #cc_dropdown_' . $form_id . '::-webkit-scrollbar { width: 6px; }
        #cc_dropdown_' . $form_id . '::-webkit-scrollbar-track { background: transparent; }
        #cc_dropdown_' . $form_id . '::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
        #cc_dropdown_' . $form_id . '::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
        </style>
      </div>
    </div>
    <input name="phone" placeholder="Contact No.*" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="tel" required>
  </div>
  <div style="position:relative;width:100%;">
    <input type="text" readonly id="service_trigger_' . $form_id . '" placeholder="Select Service*" value="" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;cursor:pointer;font-family:inherit;background-image:url(\'' . $select_arrow . '\');background-repeat:no-repeat;background-position:right 18px center;padding-right:45px;" required>
    <input type="hidden" name="service" id="service_input_' . $form_id . '" required>
    <div id="service_dropdown_' . $form_id . '" style="position:absolute;top:100%;left:0;right:0;max-height:200px;overflow-y:auto;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:12px;z-index:99999;box-shadow:0 10px 25px rgba(0,0,0,0.15);display:none;margin-top:6px;padding:4px;box-sizing:border-box;text-align:left;">
      <style>
      #service_dropdown_' . $form_id . '::-webkit-scrollbar { width: 6px; }
      #service_dropdown_' . $form_id . '::-webkit-scrollbar-track { background: transparent; }
      #service_dropdown_' . $form_id . '::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
      #service_dropdown_' . $form_id . '::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
      </style>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <input name="company" placeholder="Company Name*" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="text" required>
    <input name="website" placeholder="Website/Landing Page*" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="text" required>
  </div>
  <div>
    <div style="position:relative;width:100%;">
      <input type="text" readonly id="budget_trigger_' . $form_id . '" placeholder="Budget Spent In Last 30 Days?*" value="" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;cursor:pointer;font-family:inherit;background-image:url(\'' . $select_arrow . '\');background-repeat:no-repeat;background-position:right 18px center;padding-right:45px;" required>
      <input type="hidden" name="budget" id="budget_input_' . $form_id . '" required>
      <div id="budget_dropdown_' . $form_id . '" style="position:absolute;top:100%;left:0;right:0;max-height:200px;overflow-y:auto;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:12px;z-index:99999;box-shadow:0 10px 25px rgba(0,0,0,0.15);display:none;margin-top:6px;padding:4px;box-sizing:border-box;text-align:left;">
        <style>
        #budget_dropdown_' . $form_id . '::-webkit-scrollbar { width: 6px; }
        #budget_dropdown_' . $form_id . '::-webkit-scrollbar-track { background: transparent; }
        #budget_dropdown_' . $form_id . '::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
        #budget_dropdown_' . $form_id . '::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
        </style>
      </div>
    </div>
  </div>
  <div>
    <textarea name="details" placeholder="Additional Details/Purpose of Business" style="width:100%;padding:16px 22px;border-radius:18px;border:1px solid #7E7E7E;background:#FFFFFF;color:#1E293B;font-size:14px;box-sizing:border-box;height:120px;resize:vertical;outline:none;font-family:inherit;"></textarea>
  </div>
  <div style="display:flex;flex-direction:column;align-items:flex-start;gap:10px;margin-top:8px;text-align:left;">
    <button type="submit" style="background:#02426A;color:#FFFFFF;border:none;border-radius:50px;padding:14px 34px;font-weight:600;font-size:14px;cursor:pointer;display:inline-block;transition:all 0.2s;font-family:inherit;">Request a marketing Proposal</button>
    <div id="error_' . $form_id . '" style="color:#DC2626;font-size:13px;display:none;font-weight:500;"></div>
    <div id="success_' . $form_id . '" style="color:#16A34A;font-size:13px;display:none;font-weight:500;"></div>
  </div>
</form>
<script>
(function() {
    // 1. Country Code Dropdown
    var countries = ' . json_encode($countries) . ';
    var input = document.getElementById("cc_input_' . $form_id . '");
    var dropdown = document.getElementById("cc_dropdown_' . $form_id . '");
    
    function renderList(filter) {
        dropdown.innerHTML = "";
        
        var styleTag = dropdown.querySelector("style");
        if (styleTag) {
            dropdown.innerHTML = "";
            dropdown.appendChild(styleTag);
        }
        
        var filtered = countries.filter(function(c) {
            var searchStr = (c.c + " " + c.i + " " + c.n).toLowerCase();
            return !filter || searchStr.indexOf(filter.toLowerCase()) !== -1;
        });
        
        if (filtered.length === 0) {
            var empty = document.createElement("div");
            empty.textContent = "No matches";
            empty.style.padding = "10px 14px";
            empty.style.fontSize = "13px";
            empty.style.color = "#94A3B8";
            empty.style.textAlign = "center";
            dropdown.appendChild(empty);
            return;
        }
        
        filtered.forEach(function(c) {
            var item = document.createElement("div");
            item.textContent = c.n + " (" + c.c + " " + c.i + ")";
            item.style.padding = "10px 14px";
            item.style.cursor = "pointer";
            item.style.fontSize = "13px";
            item.style.color = "#1E293B";
            item.style.borderRadius = "8px";
            item.style.transition = "background 0.15s, color 0.15s";
            
            item.addEventListener("mouseenter", function() {
                item.style.background = "#F1F5F9";
                item.style.color = "#0F172A";
            });
            item.addEventListener("mouseleave", function() {
                item.style.background = "transparent";
                item.style.color = "#1E293B";
            });
            item.addEventListener("click", function(e) {
                input.value = c.c + " " + c.i;
                dropdown.style.display = "none";
                e.stopPropagation();
            });
            dropdown.appendChild(item);
        });
    }
    
    input.addEventListener("focus", function() {
        dropdown.style.display = "block";
        input.dataset.prev = input.value;
        input.value = "";
        renderList("");
    });
    
    input.addEventListener("input", function() {
        dropdown.style.display = "block";
        renderList(input.value);
    });
    
    input.addEventListener("blur", function() {
        setTimeout(function() {
            if (input.value === "") {
                input.value = input.dataset.prev || "+91 IND";
            }
            dropdown.style.display = "none";
        }, 200);
    });
    
    dropdown.addEventListener("wheel", function(e) {
        e.stopPropagation();
    }, { passive: false });
    
    dropdown.addEventListener("touchmove", function(e) {
        e.stopPropagation();
    }, { passive: false });
    
    // 2. Service Dropdown
    var serviceTrigger = document.getElementById("service_trigger_' . $form_id . '");
    var serviceInput = document.getElementById("service_input_' . $form_id . '");
    var serviceDropdown = document.getElementById("service_dropdown_' . $form_id . '");
    
    var services = [
        { v: "digital-marketing", n: "Digital Marketing" },
        { v: "google-ads", n: "Google Ads" },
        { v: "branding-services", n: "Branding Services" },
        { v: "seo", n: "SEO" },
        { v: "web-development", n: "Web Development" },
        { v: "social-media-management", n: "Social Media Management" },
        { v: "online-reputation-management", n: "Online Reputation Management" },
        { v: "video-production", n: "Video Production" },
        { v: "vfx", n: "VFX" },
        { v: "cgi-services", n: "CGI Services" }
    ];
    
    function renderServices() {
        serviceDropdown.innerHTML = "";
        
        var styleTag = serviceDropdown.querySelector("style");
        if (styleTag) {
            serviceDropdown.innerHTML = "";
            serviceDropdown.appendChild(styleTag);
        }
        
        services.forEach(function(s) {
            var item = document.createElement("div");
            item.textContent = s.n;
            item.style.padding = "10px 14px";
            item.style.cursor = "pointer";
            item.style.fontSize = "13px";
            item.style.color = "#1E293B";
            item.style.borderRadius = "8px";
            item.style.transition = "background 0.15s, color 0.15s";
            
            item.addEventListener("mouseenter", function() {
                item.style.background = "#F1F5F9";
                item.style.color = "#0F172A";
            });
            item.addEventListener("mouseleave", function() {
                item.style.background = "transparent";
                item.style.color = "#1E293B";
            });
            item.addEventListener("click", function(e) {
                serviceTrigger.value = s.n;
                serviceInput.value = s.v;
                serviceDropdown.style.display = "none";
                e.stopPropagation();
            });
            serviceDropdown.appendChild(item);
        });
    }
    
    serviceTrigger.addEventListener("click", function(e) {
        var isDisplayed = serviceDropdown.style.display === "block";
        serviceDropdown.style.display = isDisplayed ? "none" : "block";
        if (!isDisplayed) {
            renderServices();
        }
        e.stopPropagation();
    });
    
    serviceDropdown.addEventListener("wheel", function(e) {
        e.stopPropagation();
    }, { passive: false });
    
    serviceDropdown.addEventListener("touchmove", function(e) {
        e.stopPropagation();
    }, { passive: false });
    
    // 3. Budget Dropdown
    var budgetTrigger = document.getElementById("budget_trigger_' . $form_id . '");
    var budgetInput = document.getElementById("budget_input_' . $form_id . '");
    var budgetDropdown = document.getElementById("budget_dropdown_' . $form_id . '");
    
    var budgets = [
        { v: "under1k", n: "Under $1,000" },
        { v: "1k-5k", n: "$1,000 - $5,000" },
        { v: "5k-10k", n: "$5,000 - $10,000" },
        { v: "10k+", n: "$10,000+" }
    ];
    
    function renderBudgets() {
        budgetDropdown.innerHTML = "";
        
        var styleTag = budgetDropdown.querySelector("style");
        if (styleTag) {
            budgetDropdown.innerHTML = "";
            budgetDropdown.appendChild(styleTag);
        }
        
        budgets.forEach(function(b) {
            var item = document.createElement("div");
            item.textContent = b.n;
            item.style.padding = "10px 14px";
            item.style.cursor = "pointer";
            item.style.fontSize = "13px";
            item.style.color = "#1E293B";
            item.style.borderRadius = "8px";
            item.style.transition = "background 0.15s, color 0.15s";
            
            item.addEventListener("mouseenter", function() {
                item.style.background = "#F1F5F9";
                item.style.color = "#0F172A";
            });
            item.addEventListener("mouseleave", function() {
                item.style.background = "transparent";
                item.style.color = "#1E293B";
            });
            item.addEventListener("click", function(e) {
                budgetTrigger.value = b.n;
                budgetInput.value = b.v;
                budgetDropdown.style.display = "none";
                e.stopPropagation();
            });
            budgetDropdown.appendChild(item);
        });
    }
    
    budgetTrigger.addEventListener("click", function(e) {
        var isDisplayed = budgetDropdown.style.display === "block";
        budgetDropdown.style.display = isDisplayed ? "none" : "block";
        if (!isDisplayed) {
            renderBudgets();
        }
        e.stopPropagation();
    });
    
    budgetDropdown.addEventListener("wheel", function(e) {
        e.stopPropagation();
    }, { passive: false });
    
    budgetDropdown.addEventListener("touchmove", function(e) {
        e.stopPropagation();
    }, { passive: false });
    
    // Global Document Clicks
    document.addEventListener("click", function(e) {
        if (e.target !== input && !dropdown.contains(e.target)) {
            dropdown.style.display = "none";
        }
        if (e.target !== serviceTrigger && !serviceDropdown.contains(e.target)) {
            serviceDropdown.style.display = "none";
        }
        if (e.target !== budgetTrigger && !budgetDropdown.contains(e.target)) {
            budgetDropdown.style.display = "none";
        }
    });
})();

function handleFormSubmit_' . $form_id . '(event) {
    event.preventDefault();
    var form = event.target;
    var errorDiv = document.getElementById("error_' . $form_id . '");
    var successDiv = document.getElementById("success_' . $form_id . '");
    errorDiv.style.display = "none";
    successDiv.style.display = "none";

    var name = form.fullname.value.trim();
    var email = form.email.value.trim();
    var phone = form.phone.value.trim();
    var service = form.service.value;
    var company = form.company.value.trim();
    var website = form.website.value.trim();
    var budget = form.budget.value;

    if (!name || !email || !phone || !service || !company || !website || !budget) {
        errorDiv.textContent = "Please fill in all required fields.";
        errorDiv.style.display = "block";
        return false;
    }

    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        errorDiv.textContent = "Please enter a valid email address.";
        errorDiv.style.display = "block";
        return false;
    }

    var phoneClean = phone.replace(/[\s\-\(\)\+]/g, "");
    if (!/^\d{7,15}$/.test(phoneClean)) {
        errorDiv.textContent = "Please enter a valid contact number (7-15 digits).";
        errorDiv.style.display = "block";
        return false;
    }

    var webRegex = /^(https?:\/\/)?(www\.)?[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}(\/\S*)?$/;
    if (!webRegex.test(website)) {
        errorDiv.textContent = "Please enter a valid website URL or domain name.";
        errorDiv.style.display = "block";
        return false;
    }

    successDiv.textContent = "Thank you! Your proposal request has been submitted successfully.";
    successDiv.style.display = "block";
    form.reset();
    return false;
}
</script>';
    }

    private function form_html()
    {
        return $this->hero_form_html();
    }

    private function footer_about_html()
    {
        return 'Feel free to reach out if you want to collaborate with us, or simply have a chat.';
    }

    private function footer_services_html($data)
    {
        $services = array(
            'Digital Marketing' => 'https://vispansolutions.com/digital-marketing-services/',
            'Google Ads Management' => 'https://vispansolutions.com/google-ads-services-in-india/',
            'Branding Services' => 'https://vispansolutions.com/branding-services/',
            'Search Engine Optimization' => 'https://vispansolutions.com/seo-services/',
            'Web Development' => 'https://vispansolutions.com/web-development/',
            'Social Media Management' => 'https://vispansolutions.com/social-media-management-services/',
            'Online Reputation Management' => 'https://vispansolutions.com/online-reputation-management/',
            'Video Production' => 'https://vispansolutions.com/video-production/',
            'CGI Services' => 'https://vispansolutions.com/cgi-services/',
            'VFX Service' => 'https://vispansolutions.com/vfx-services-in-rajkot/'
        );
        $html = '';
        foreach($services as $name => $url)
        {
            $html .= '<li style="list-style:none;margin-bottom:8px;"><a style="color:#121212;text-decoration:none;font-size:14px;font-weight:400;transition:color 0.2s;" href="' . $url . '" target="_blank" onmouseover="this.style.color=\'#0F172A\'" onmouseout="this.style.color=\'#334155\'">' . $this->e($name) . '</a></li>';
        }
        return $html;
    }

    private function footer_links_html()
    {
        $links = array(
            'Home' => 'https://vispansolutions.com/',
            'About Us' => 'https://vispansolutions.com/about-us/',
            'Blog' => 'https://vispansolutions.com/blog/',
            'Career' => 'https://vispansolutions.com/career/',
            'Contact Us' => 'https://vispansolutions.com/contact-us/',
            'Financial Reporting' => 'https://vispansolutions.com/financial-reporting'
        );
        $html = '';
        foreach($links as $name => $url)
        {
            $html .= '<li style="list-style:none;margin-bottom:8px;"><a style="color:#121212;text-decoration:none;font-size:14px;font-weight:400;transition:color 0.2s;" href="' . $url . '" target="_blank" onmouseover="this.style.color=\'#0F172A\'" onmouseout="this.style.color=\'#334155\'">' . $this->e($name) . '</a></li>';
        }
        return $html;
    }

    private function footer_contact_html($phone, $email)
    {
        return '<div style="font-size:14px;color:#121212;line-height:1.6;font-family:\'DM Sans\',sans-serif;">'
             . '<div style="margin-bottom:14px;"><a href="https://maps.app.goo.gl/FZYzad69d5pguZSZ8" target="_blank" style="color:#121212;text-decoration:none;">R K Complex, 16/28-Vijay Plot, Gondal Road, RAJKOT – 360002.</a></div>'
             . '<div style="margin-bottom:6px;"><a href="tel:+918485986860" style="color:#121212;text-decoration:none;">+91 (848) 598-6860</a></div>'
             . '<div style="margin-bottom:18px;"><a href="mailto:contact@vispansolutions.com" style="color:#121212;text-decoration:none;">contact@vispansolutions.com</a></div>'
             . '<div style="display:inline-block;"><a href="https://www.google.com/partners/agency?id=1282018658" target="_blank" style="text-decoration:none;display:block;"><img src="https://www.gstatic.com/partners/badge/images/2022/PartnerBadgeClickable.svg" alt="Google Partner" style="width:120px;height:auto;"></a></div>'
             . '</div>';
    }

    private function social_html()
    {
        $socials = array(
            array('name' => 'Facebook', 'url' => 'https://www.facebook.com/VispanSolutions/', 'icon' => '<svg width="14" height="14" fill="#FFFFFF" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'),
            array('name' => 'Instagram', 'url' => 'https://www.instagram.com/vispan_solutions/', 'icon' => '<svg width="14" height="14" fill="#FFFFFF" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>'),
            array('name' => 'Pinterest', 'url' => 'https://in.pinterest.com/vispansolutions/', 'icon' => '<svg width="14" height="14" fill="#FFFFFF" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738.098.119.112.224.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>'),
            array('name' => 'X-Twitter', 'url' => 'https://twitter.com/vispansolutions', 'icon' => '<svg width="14" height="14" fill="#FFFFFF" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'),
            array('name' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/vispan-solutions/', 'icon' => '<svg width="14" height="14" fill="#FFFFFF" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>'),
            array('name' => 'YouTube', 'url' => 'https://www.youtube.com/user/vispansolutions', 'icon' => '<svg width="14" height="14" fill="#FFFFFF" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>'),
        );
        $html = '<div style="display:flex;gap:8px;margin-top:12px;">';
        foreach($socials as $s)
        {
            $html .= '<a href="' . $s['url'] . '" target="_blank" style="width:28px;height:28px;border-radius:50%;background:#000000;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:opacity 0.2s;" title="' . $this->e($s['name']) . '">' . $s['icon'] . '</a>';
        }
        $html .= '</div>';
        return $html;
    }

    private function logo_svg()
    {
        if (function_exists('plugins_url')) {
            return plugins_url('assets/VSPL-Web-Logo.webp', dirname(dirname(__FILE__)) . '/vispan-city-page-generator.php');
        }
        $file = dirname(dirname(__FILE__)) . '/assets/VSPL-Web-Logo.webp';
        if (file_exists($file)) {
            return 'data:image/webp;base64,' . base64_encode(file_get_contents($file));
        }
        return "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 170 42' width='170' height='42'><text x='0' y='32' font-family='-apple-system, BlinkMacSystemFont, \"Plus Jakarta Sans\", \"Segoe UI\", Roboto, sans-serif' font-weight='900' font-size='36' fill='#003D6D' letter-spacing='-1.5px'>vispan</text></svg>";
    }

    private function hero_bg_svg()
    {
        if (function_exists('plugins_url')) {
            return plugins_url('assets/hero_mesh_bg.png', dirname(dirname(__FILE__)) . '/vispan-city-page-generator.php');
        }
        $b64_file = dirname(dirname(__FILE__)) . '/assets/hero_mesh_bg_base64.txt';
        if(file_exists($b64_file))
        {
            $content = trim(file_get_contents($b64_file));
            if (!empty($content)) {
                return $content;
            }
        }
        $img_file = dirname(dirname(__FILE__)) . '/assets/hero_mesh_bg.png';
        if (file_exists($img_file)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($img_file));
        }
        return "<svg xmlns='http://www.w3.org/2000/svg' width='1600' height='900'><rect width='1600' height='900' fill='#070D18'/></svg>";
    }

    private function services_bg_svg()
    {
        if (function_exists('plugins_url')) {
            return plugins_url('assets/image-2.jpg', dirname(dirname(__FILE__)) . '/vispan-city-page-generator.php');
        }
        $file = dirname(dirname(__FILE__)) . '/assets/image-2.jpg';
        if (file_exists($file)) {
            return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($file));
        }
        return "<svg xmlns='http://www.w3.org/2000/svg' width='1600' height='900'><rect width='1600' height='900' fill='#1A1713'/><circle cx='800' cy='450' r='500' fill='#0B63F6' opacity='0.05'/></svg>";
    }

    private function about_svg()
    {
        if (function_exists('plugins_url')) {
            return plugins_url('assets/image-1.webp', dirname(dirname(__FILE__)) . '/vispan-city-page-generator.php');
        }
        $file = dirname(dirname(__FILE__)) . '/assets/image-1.webp';
        if (file_exists($file)) {
            return 'data:image/webp;base64,' . base64_encode(file_get_contents($file));
        }
        return "<svg xmlns='http://www.w3.org/2000/svg' width='800' height='600'><rect width='800' height='600' rx='16' fill='#F1F5F9'/><rect x='100' y='100' width='600' height='400' rx='12' fill='#FFFFFF' stroke='#E2E8F0' stroke-width='2'/><text x='400' y='310' font-size='24' font-weight='700' fill='#0B63F6' text-anchor='middle' font-family='Arial'>Everything you need to grow online</text></svg>";
    }

    private function cta_svg()
    {
        if (function_exists('plugins_url')) {
            return plugins_url('assets/image-2.jpg', dirname(dirname(__FILE__)) . '/vispan-city-page-generator.php');
        }
        $file = dirname(dirname(__FILE__)) . '/assets/image-2.jpg';
        if (file_exists($file)) {
            return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($file));
        }
        return "<svg xmlns='http://www.w3.org/2000/svg' width='800' height='600'><rect width='800' height='600' rx='16' fill='#F8FAFC'/><rect x='80' y='80' width='640' height='440' rx='16' fill='#FFFFFF' stroke='#E2E8F0' stroke-width='2'/><text x='400' y='310' font-size='22' font-weight='700' fill='#081828' text-anchor='middle' font-family='Arial'>Digital Marketing Strategy</text></svg>";
    }

    private function get_service_nouns($service)
    {
        $service_lower = strtolower($service);
        $nouns = array(
            'business_type' => 'business',
            'client_type'   => 'client',
            'industry_noun' => 'industry',
            'practitioner'  => 'professional',
            'jargon'        => 'technical jargon',
            'concerns'      => 'needs',
            'service_name'  => ucwords($service),
        );

        if (preg_match('/(law|legal|attorney|lawyer|advocate)/i', $service_lower)) {
            $nouns['business_type'] = 'firm';
            $nouns['client_type']   = 'client';
            $nouns['industry_noun'] = 'legal';
            $nouns['practitioner']  = 'attorney';
            $nouns['jargon']        = 'legal jargon';
            $nouns['concerns']      = 'legal needs';
        } elseif (preg_match('/(dental|dentist|orthodontist|teeth|smile)/i', $service_lower)) {
            $nouns['business_type'] = 'practice';
            $nouns['client_type']   = 'patient';
            $nouns['industry_noun'] = 'dental';
            $nouns['practitioner']  = 'dentist';
            $nouns['jargon']        = 'dental jargon';
            $nouns['concerns']      = 'dental concerns';
        } elseif (preg_match('/(medical|clinic|doctor|health|physician|patient)/i', $service_lower)) {
            $nouns['business_type'] = 'clinic';
            $nouns['client_type']   = 'patient';
            $nouns['industry_noun'] = 'healthcare';
            $nouns['practitioner']  = 'doctor';
            $nouns['jargon']        = 'medical jargon';
            $nouns['concerns']      = 'health concerns';
        }

        return $nouns;
    }

    private function case_study_html($data)
    {
        $city = $this->t(isset($data['city']) ? $data['city'] : 'Los Angeles');
        $svc = $this->t(isset($data['service']) ? $data['service'] : 'Law Firm Marketing');
        $nouns = $this->get_service_nouns($svc);
        
        $niche = ucwords($svc);
        if (stripos($niche, $nouns['business_type']) === false) {
            $niche .= ' ' . ucwords($nouns['business_type']);
        }
        $client_type = $nouns['client_type'];
        
        $service_name = strtolower($svc);
        
        $title = "Case Study: Helping a " . $city . " " . $niche . " Improve Local Search Visibility";
        
        $challenge = "A " . $city . " " . strtolower($niche) . " had a professional website but was not getting enough inquiries from Google. The " . strtolower($nouns['business_type']) . " was ranking for some general terms, but visibility for important local and service-specific searches was limited. The client wanted one thing: more relevant people finding the " . strtolower($nouns['business_type']) . " when they were actively looking for " . $service_name . " solutions.";
        
        $what_we_did = "We reviewed the website, competitors, local search presence, and existing content. We then focused on improving key service pages, targeting relevant " . $city . " " . $service_name . " searches, strengthening internal links, optimizing the Google Business Profile, and improving calls-to-action on important landing pages. Instead of creating content only to target keywords, we focused on answering the questions potential " . $client_type . "s were actually asking before contacting them.";
        
        $result = "Within the campaign period, the " . strtolower($nouns['business_type']) . " saw improved visibility for targeted local searches, stronger organic traffic to its practice-area pages, and an increase in consultation inquiries from organic search. Key improvement: The marketing strategy shifted the focus from simply getting more website visitors to attracting potential " . $client_type . "s actively looking for professional services in " . $city . ".";
        
        $what_this_shows = "Effective local SEO and digital marketing is not about repeating keywords or publishing large amounts of generic content. It is about understanding what potential " . $client_type . "s search for, creating useful content around those needs, improving local visibility, and making it easy for the right visitor to contact the " . strtolower($nouns['business_type']) . ".";
        
        $image_url = $this->get_asset_url('case-study.png');
        
        $html = '
<div class="vp-container" style="padding: 0;">
    <div class="vp-casestudy-grid">
        <div style="order: 1; display: flex; height: 100%;">
            <img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" style="width: 100%; height: 100%; min-height: 100%; border-radius: 24px; box-shadow: 0 12px 36px rgba(2,66,106,0.12); display: block; object-fit: cover;">
        </div>
        <div style="order: 2;">
            <h3 style="font-size: 2.2rem; font-weight: 800; color: #02426A; line-height: 1.25; margin-bottom: 30px; font-family: \'DM Sans\', sans-serif;">' . $this->e($title) . '</h3>
            
            <div style="margin-bottom: 24px;">
                <h4 style="font-size: 1.1rem; font-weight: 700; color: #02426A; margin-bottom: 8px; font-family: \'DM Sans\', sans-serif;">The Challenge</h4>
                <p style="font-size: 15px; color: #334155; line-height: 1.7; font-family: \'Plus Jakarta Sans\', sans-serif;">' . $this->e($challenge) . '</p>
            </div>
            
            <div style="margin-bottom: 24px;">
                <h4 style="font-size: 1.1rem; font-weight: 700; color: #02426A; margin-bottom: 8px; font-family: \'DM Sans\', sans-serif;">What We Did</h4>
                <p style="font-size: 15px; color: #334155; line-height: 1.7; font-family: \'Plus Jakarta Sans\', sans-serif;">' . $this->e($what_we_did) . '</p>
            </div>
            
            <div style="margin-bottom: 24px;">
                <h4 style="font-size: 1.1rem; font-weight: 700; color: #02426A; margin-bottom: 8px; font-family: \'DM Sans\', sans-serif;">The Result</h4>
                <p style="font-size: 15px; color: #334155; line-height: 1.7; font-family: \'Plus Jakarta Sans\', sans-serif;">' . $this->e($result) . '</p>
            </div>
            
            <div style="margin-bottom: 0;">
                <h4 style="font-size: 1.1rem; font-weight: 700; color: #02426A; margin-bottom: 8px; font-family: \'DM Sans\', sans-serif;">What This Shows</h4>
                <p style="font-size: 15px; color: #334155; line-height: 1.7; font-family: \'Plus Jakarta Sans\', sans-serif;">' . $this->e($what_this_shows) . '</p>
            </div>
        </div>
    </div>
</div>';
        return $html;
    }
}
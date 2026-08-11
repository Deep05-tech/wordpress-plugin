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
        $paragraphs = preg_split('/\n\s*\n/', $value);
        if (count($paragraphs) > 1) {
            $p_html = array();
            foreach ($paragraphs as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $p_html[] = '<p style="margin:0 0 18px 0;">' . nl2br($this->e($p)) . '</p>';
                }
            }
            return implode('', $p_html);
        }
        return '<p style="margin:0 0 18px 0;">' . nl2br($this->e($value)) . '</p>';
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
        if(empty($hero_description) || strpos($hero_description, "\n\n") === false)
        {
            $hero_description = "Welcome to our digital marketing agency in " . $city . "! In " . $city . "’s competitive " . strtolower($svc) . " " . $nouns['business_type'] . " industry, exceptional services alone are insufficient. Partnering with a top " . strtolower($svc) . " " . $nouns['business_type'] . " can elevate your " . $nouns['business_type'] . ".\n\nWe specialize in customized digital marketing strategies for " . strtolower($svc) . " " . $nouns['business_type'] . "s. Our expertise in tailored designs ensures maximum online visibility, attracting new " . $nouns['client_type'] . "s and retaining existing ones.\n\nAs leaders in " . strtolower($svc) . ", we offer solutions from SEO services to engaging social media marketing. Our websites are user-friendly and attractive, helping your " . $nouns['business_type'] . " stand out.\n\nLet us help you grow with top-notch designs, effective " . strtolower($svc) . ", and comprehensive SEO strategies. Our social media marketing and website development will ensure your " . $nouns['business_type'] . " reaches its full potential.";
        }

        if(stripos($svc, 'agency') !== false || stripos($svc, 'marketing') !== false) {
            $about_title = 'Creating a Strong Online Presence for Local Businesses with our ' . $svc . ' in ' . $city . '.';
        } else {
            $about_title = 'Creating a Strong Online Presence for ' . $svc . ' with our Digital Marketing Agency in ' . $city . '.';
        }

        $about_content = "Enhance your business's online presence in " . $city . " with customized digital marketing solutions tailored to your " . $nouns['business_type'] . ". We specialize in optimizing your website for search engines and engaging " . $nouns['client_type'] . "s through various social media platforms.\n\nOur comprehensive digital marketing services focus on attracting new clients while fostering loyalty among your current clientele. Partner with us to transform your business and achieve lasting success in the competitive digital landscape.\n\nReach out to us to navigate the complexities of online marketing and realize sustained growth for your " . $nouns['business_type'] . ", utilizing our proven expertise in advertising, web design, SEO strategies, and social media marketing. Together, we can build a thriving online presence for your business.";

        $about_paras = explode("\n\n", $about_content);
        $about_html_paras = array();
        foreach($about_paras as $ap) {
            if(!empty(trim($ap))) {
                $about_html_paras[] = '<p style="margin-bottom:16px;line-height:1.65;font-size:15px;color:#121212;">' . esc_html(trim($ap)) . '</p>';
            }
        }
        $about_content_html = implode('', $about_html_paras);

        $intro_title = 'Get ' . $svc . ' in ' . $city . ': Why Your ' . ucwords($nouns['business_type']) . ' Needs Online Marketing in ' . $city;
        $intro_content = "Unlock the digital revolution in " . strtolower($svc) . " and discover why your " . $nouns['business_type'] . " must embrace online marketing. Our specialized SEO services for " . strtolower($svc) . " are designed to enhance your " . $nouns['business_type'] . "'s visibility and attract new " . $nouns['client_type'] . "s. In today's competitive landscape, having a strong online presence is crucial for growth and " . $nouns['client_type'] . " engagement.\n\nWe excel in local SEO for " . strtolower($svc) . ", ensuring your " . $nouns['business_type'] . " stands out in local searches. As a leading " . strtolower($svc) . " agency, we offer tailored strategies that incorporate social media marketing and effective advertising. Our goal is to help you connect with potential " . $nouns['client_type'] . "s in your area and build a loyal " . $nouns['client_type'] . " base.\n\nStrengthen your " . $nouns['business_type'] . "'s online presence with our expert SEO " . $nouns['practitioner'] . " services, which are specifically designed to maximize visibility and foster " . $nouns['client_type'] . " engagement. We provide targeted solutions that include enhancing designs and optimizing your website to ensure it effectively attracts and retains " . $nouns['client_type'] . "s.\n\nTransform your " . $nouns['business_type'] . " in " . $city . " by partnering with us. Our comprehensive services, from innovative SEO " . $nouns['practitioner'] . " techniques to strategic social media marketing, will revolutionize your " . $nouns['business_type'] . ". With our expertise, your website will not only shine with captivating designs but will also leverage effective advertising to drive sustained growth and success in the competitive market.";

        $services_heading = 'Services of ' . $svc . ' for ' . ucwords($nouns['business_type']) . 's in ' . $city;
        $services_description = 'Our ' . strtolower($svc) . ' services in ' . $city . ' encompass a range of digital strategies, all working together to achieve your ' . $nouns['business_type'] . '\'s unique goals. Here\'s a closer look at some key components:';

        $why_choose_heading = "Distinct Advantages of Partnering with Vispan Solutions' Digital Marketing Experts";
        $why_choose_description = $this->t(isset($data['why_choose_description']) ? $data['why_choose_description'] : '');
        if(empty($why_choose_description))
        {
            $why_choose_description = 'As your digital marketing partner in ' . $city . ', we understand the unique challenges faced in today\'s competitive landscape.';
        }

        $cta_description = $this->t(isset($data['cta_content']) ? $data['cta_content'] : '');
        if(empty($cta_description) || strlen($cta_description) > 300 || strlen($cta_description) < 150)
        {
            $cta_description = "Don't let your business get lost in the digital world of " . $city . ". Our comprehensive digital marketing agency can help you attract new customers, build a strong online presence, and ultimately achieve your " . $nouns['business_type'] . "'s growth goals.\n\nContact us today for a free consultation and discuss how we can help your business thrive in the digital age.";
        }

        $cta_paras = explode("\n\n", $cta_description);
        $cta_html_paras = array();
        foreach($cta_paras as $cp) {
            if(!empty(trim($cp))) {
                $cta_html_paras[] = '<p style="margin-bottom:14px;line-height:1.65;font-size:15px;">' . esc_html(trim($cp)) . '</p>';
            }
        }
        $cta_description_html = implode('', $cta_html_paras);

        $logo_uri         = $this->svg_uri($this->logo_svg());
        $hero_bg_uri      = $this->get_asset_url('hero-bg.png');
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
            '{{contact_bg}}'               => $contact_bg_uri,
            '{{hero_small_title}}'         => '',
            '{{hero_title}}'               => $this->e($hero_title),
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
            '{{social_icons}}'             => $this->social_html(),
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
        return '<form style="display:flex;flex-direction:column;gap:16px;" onsubmit="return false;">
  <div>
    <input placeholder="Full Name*" style="width:100%;padding:14px 24px;border-radius:50px;border:none;background:#FFFFFF;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="text" required>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <input placeholder="Email Address*" style="width:100%;padding:14px 20px;border-radius:50px;border:none;background:#FFFFFF;color:#1E293B;font-size:13px;box-sizing:border-box;outline:none;font-family:inherit;" type="email" required>
    <input placeholder="Contact No. with Country Code*" style="width:100%;padding:14px 20px;border-radius:50px;border:none;background:#FFFFFF;color:#1E293B;font-size:13px;box-sizing:border-box;outline:none;font-family:inherit;" type="tel" required>
  </div>
  <div>
    <textarea placeholder="Additional Details/Purpose of Business*" style="width:100%;padding:16px 22px;border-radius:20px;border:none;background:#FFFFFF;color:#1E293B;font-size:14px;box-sizing:border-box;height:140px;resize:vertical;outline:none;font-family:inherit;" required></textarea>
  </div>
  <div style="margin-top:4px;text-align:left;">
    <button type="submit" style="background:transparent;color:#FFFFFF;border:1.5px solid #FFFFFF;border-radius:50px;padding:12px 36px;font-weight:600;font-size:15px;cursor:pointer;display:inline-block;transition:all 0.2s;font-family:inherit;">Submit Now</button>
  </div>
</form>';
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
        $city = $this->e(isset($data['city']) ? $data['city'] : 'Los Angeles');
        $svc  = $this->e(isset($data['service']) ? $data['service'] : 'Digital Marketing');

        $html  = '<div style="max-width:1100px;margin:0 auto;font-family:inherit;">';
        $html .= '  <div style="display:flex;flex-direction:row;flex-wrap:nowrap;gap:10px;justify-content:space-between;align-items:center;margin-bottom:28px;width:100%;">';
        $html .= '    <button onclick="vcpgSwitchTab(0)" class="vcpg-tab-btn" style="flex:1;min-width:0;white-space:nowrap;background:#FFFFFF;color:#1E293B;padding:12px 10px;border-radius:4px;font-weight:600;font-size:13px;border:1px solid #0F172A;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.05);transition:all 0.2s;text-align:center;">Strategic AI Implementation</button>';
        $html .= '    <button onclick="vcpgSwitchTab(1)" class="vcpg-tab-btn" style="flex:1;min-width:0;white-space:nowrap;background:#0F172A;color:#FFFFFF;padding:12px 10px;border-radius:4px;font-weight:600;font-size:13px;border:1px solid #0F172A;cursor:pointer;box-shadow:none;transition:all 0.2s;text-align:center;">Dedicated Industry Specialists</button>';
        $html .= '    <button onclick="vcpgSwitchTab(2)" class="vcpg-tab-btn" style="flex:1;min-width:0;white-space:nowrap;background:#0F172A;color:#FFFFFF;padding:12px 10px;border-radius:4px;font-weight:600;font-size:13px;border:1px solid #0F172A;cursor:pointer;box-shadow:none;transition:all 0.2s;text-align:center;">Holistic Campaign Management</button>';
        $html .= '    <button onclick="vcpgSwitchTab(3)" class="vcpg-tab-btn" style="flex:1;min-width:0;white-space:nowrap;background:#0F172A;color:#FFFFFF;padding:12px 10px;border-radius:4px;font-weight:600;font-size:13px;border:1px solid #0F172A;cursor:pointer;box-shadow:none;transition:all 0.2s;text-align:center;">Advanced Attribution Modeling</button>';
        $html .= '  </div>';

        // Tab Panels Box
        $html .= '  <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:4px;padding:36px 40px;box-shadow:0 2px 10px rgba(0,0,0,0.02);text-align:left;">';

        // Generate keyword-rich panel text (50-60 words each)
        $panel_texts = array(
            'Vispan Solutions integrates cutting-edge machine learning models to forecast trends and optimize targeting, ensuring campaigns adapt dynamically to changing local consumer behaviors and market conditions in ' . $city . '. Our data-driven digital marketing strategy leverages advanced analytics, conversion optimization, and performance tracking to deliver measurable ROI for businesses seeking top-rated online marketing solutions.',
            'Our team brings deep domain expertise across SEO, PPC, social media marketing, branding, and content creation tailored specifically to the ' . $city . ' market. As a trusted digital marketing agency, we combine local search optimization, Google Ads management, and strategic campaign planning to ensure your business stays ahead of local competitors.',
            'From website optimization and paid ad acquisition to reputation management and email marketing, we manage every touchpoint of your digital presence. Our comprehensive approach includes conversion rate optimization, lead generation, social media management, and search engine marketing — delivering end-to-end strategy with precision for ' . $city . ' businesses.',
            'Track every lead, call, and appointment with complete attribution clarity. We deliver detailed performance dashboards, Google Analytics reporting, and actionable growth insights to maximize your ROI. Our advanced marketing analytics cover cost-per-acquisition, customer lifetime value, and multi-channel attribution modeling for ' . $city . ' businesses.',
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
        $select_arrow = 'data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2220%22%20height%3D%2220%22%20viewBox%3D%220%200%2020%2020%22%3E%3Cpath%20fill%3D%22%231E293B%22%20d%3D%22M5.293%207.293a1%201%200%20011.414%200L10%2010.586l3.293-3.293a1%201%200%20111.414%201.414l-4%204a1%201%200%2001-1.414%200l-4-4a1%201%200%20010-1.414z%22%2F%3E%3C%2Fsvg%3E';
        return '<form style="display:flex;flex-direction:column;gap:16px;" onsubmit="return false;">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <input placeholder="Full Name*" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="text" required>
    <input placeholder="Email Address*" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="email" required>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <input placeholder="Contact No. with Country Code*" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="tel" required>
    <select style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#121212;font-size:14px;box-sizing:border-box;outline:none;cursor:pointer;font-family:inherit;-webkit-appearance:none;-moz-appearance:none;appearance:none;background-image:url(\'' . $select_arrow . '\');background-repeat:no-repeat;background-position:right 18px center;padding-right:45px;">
      <option value="">Select Service</option>
      <option value="seo">Search Engine Optimization</option>
      <option value="ppc">PPC & Google Ads</option>
      <option value="web">Website Development</option>
      <option value="social">Social Media Marketing</option>
    </select>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <input placeholder="Company Name*" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="text" required>
    <input placeholder="Website/Landing Page*" style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#1E293B;font-size:14px;box-sizing:border-box;outline:none;font-family:inherit;" type="url">
  </div>
  <div>
    <select style="width:100%;padding:13px 22px;border-radius:50px;border:1px solid #7E7E7E;background:#F3F4F6;color:#121212;font-size:14px;box-sizing:border-box;outline:none;cursor:pointer;font-family:inherit;-webkit-appearance:none;-moz-appearance:none;appearance:none;background-image:url(\'' . $select_arrow . '\');background-repeat:no-repeat;background-position:right 18px center;padding-right:45px;">
      <option value="">Budget Spent In Last 30 Days?</option>
      <option value="under1k">Under $1,000</option>
      <option value="1k-5k">$1,000 - $5,000</option>
      <option value="5k-10k">$5,000 - $10,000</option>
      <option value="10k+">$10,000+</option>
    </select>
  </div>
  <div>
    <textarea placeholder="Additional Details/Purpose of Business" style="width:100%;padding:16px 22px;border-radius:18px;border:1px solid #7E7E7E;background:#FFFFFF;color:#1E293B;font-size:14px;box-sizing:border-box;height:140px;resize:vertical;outline:none;font-family:inherit;"></textarea>
  </div>
  <div style="margin-top:8px;text-align:left;">
    <button type="submit" style="background:#111827;color:#FFFFFF;border:none;border-radius:50px;padding:14px 34px;font-weight:600;font-size:14px;cursor:pointer;display:inline-block;transition:all 0.2s;font-family:inherit;">Request a marketing Proposal</button>
  </div>
</form>';
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
}
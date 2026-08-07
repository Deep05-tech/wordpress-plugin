<?php

defined('ABSPATH') || exit;

/**
 * VCPG_Static_Elements
 *
 * Manages site-wide static chrome and static proof content:
 * - Site chrome (nav, logos, forms, footer, social icons)
 * - Static proof content (portfolio, certifications, real client testimonial)
 *
 * Fully configured with production-grade assets and markup.
 */
class VCPG_Static_Elements
{
    /**
     * Return an associative array of {{token}} => rendered-HTML pairs
     * covering all static chrome and static proof-content fields.
     *
     * @param  array $data Merged data array.
     * @return array
     */
    public function get_replacements( array $data = array() ): array
    {
        $home = function_exists('home_url') ? home_url('/') : '/';

        if (function_exists('plugins_url')) {
            $logo_url = plugins_url('assets/VSPL-Web-Logo.webp', dirname(__DIR__) . '/vispan-city-page-generator.php');
        } else {
            $logo_file = dirname(__DIR__) . '/assets/VSPL-Web-Logo.webp';
            if (file_exists($logo_file)) {
                $logo_url = 'data:image/webp;base64,' . base64_encode(file_get_contents($logo_file));
            } else {
                $logo_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 170 42" width="170" height="42"><text x="0" y="32" font-family="-apple-system, BlinkMacSystemFont, \'Plus Jakarta Sans\', \'Inter\', \'Segoe UI\', sans-serif" font-weight="900" font-size="36" fill="#003D6D" letter-spacing="-1.5px">vispan</text></svg>';
                $logo_url = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($logo_svg);
            }
        }

        $phone = isset($data['phone']) ? $data['phone'] : '+918485986860';
        $email = isset($data['email']) ? $data['email'] : 'contact@vispansolutions.com';

        return array(
            // Site chrome tokens
            '{{home_url}}'              => $home,
            '{{logo}}'                  => $logo_url,
            '{{footer_logo}}'           => $logo_url,
            '{{phone}}'                 => $phone,
            '{{email}}'                 => $email,
            '{{nav_menu}}'              => $this->nav_menu_html(),
            '{{consultation_form}}'     => $this->consultation_form_html(),
            '{{contact_form}}'          => $this->contact_form_html(),
            '{{footer_services}}'       => $this->footer_services_html(),
            '{{footer_links}}'          => $this->footer_links_html(),
            '{{footer_contact}}'        => $this->footer_contact_html($phone, $email),
            '{{social_icons}}'          => $this->social_icons_html(),
            '{{footer_about}}'          => 'Feel free to reach out if you want to collaborate with us, or simply have a chat.',
            '{{topbar}}'                => $this->topbar_html($phone, $email),

            '{{consultation_title}}'    => 'Get A Free Consultation',

            // Static proof-content tokens
            '{{certifications_heading}}'=> 'Certifications',
            '{{contact_title}}'         => 'Request a Personalized Marketing Proposal',
            '{{certifications}}'        => $this->certifications_html(),
            '{{testimonial}}'           => $this->testimonial_html(),
            '{{logos}}'                 => $this->logos_html(),
        );
    }

    private function nav_menu_html(): string
    {
        $links = array(
            '#home'      => 'Home',
            '#about'     => 'About Us',
            '#services'  => 'What We Do <span style="font-size:0.7rem;margin-left:3px;display:inline-block;">∨</span>',
            '#blog'      => 'Blog',
            '#investor'  => 'Investor <span style="font-size:0.7rem;margin-left:3px;display:inline-block;">∨</span>',
            '#career'    => 'Career',
            '#contact'   => 'Contact Us',
        );
        $html = '';
        foreach ($links as $href => $label) {
            $html .= '<li style="list-style:none;display:inline-block;margin:0 12px;"><a style="color:#0F172A;text-decoration:none;font-weight:700;font-size:0.92rem;transition:color 0.2s ease;" href="' . esc_url($href) . '">' . $label . '</a></li>';
        }
        return $html;
    }

    private function consultation_form_html(): string
    {
        return '<form style="display:flex;flex-direction:column;gap:14px;" action="#contact" method="post">
  <div>
    <input name="fullname" placeholder="Full Name*" style="width:100%;padding:14px 22px;border-radius:30px;border:none;background:#FFFFFF;color:#081828;font-size:14px;box-sizing:border-box;outline:none;" type="text" required>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <input name="email" placeholder="Email Address*" style="width:100%;padding:14px 22px;border-radius:30px;border:none;background:#FFFFFF;color:#081828;font-size:14px;box-sizing:border-box;outline:none;" type="email" required>
    <input name="phone" placeholder="Contact No. with Country Code*" style="width:100%;padding:14px 22px;border-radius:30px;border:none;background:#FFFFFF;color:#081828;font-size:14px;box-sizing:border-box;outline:none;" type="tel" required>
  </div>
  <div>
    <textarea name="message" placeholder="Additional Details/Purpose of Business*" style="width:100%;padding:16px 22px;border-radius:18px;border:none;background:#FFFFFF;color:#081828;font-size:14px;box-sizing:border-box;height:110px;resize:vertical;outline:none;" required></textarea>
  </div>
  <div style="margin-top:6px;text-align:left;">
    <button type="submit" style="background:#0B2538;color:#FFFFFF;border:1px solid rgba(255,255,255,0.4);border-radius:50px;padding:14px 38px;font-weight:700;font-size:15px;cursor:pointer;display:inline-block;transition:all 0.2s ease;">Submit Now</button>
  </div>
</form>';
    }

    private function contact_form_html(): string
    {
        return '<form style="display:flex;flex-direction:column;gap:14px;" action="#contact" method="post">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <input name="fullname" placeholder="Full Name*" style="width:100%;padding:14px 18px;border-radius:10px;border:1px solid #CBD5E1;background:#F8FAFC;font-size:0.9rem;box-sizing:border-box;outline:none;" type="text" required>
    <input name="email" placeholder="Email Address*" style="width:100%;padding:14px 18px;border-radius:10px;border:1px solid #CBD5E1;background:#F8FAFC;font-size:0.9rem;box-sizing:border-box;outline:none;" type="email" required>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <input name="phone" placeholder="Contact No. with Country Code*" style="width:100%;padding:14px 18px;border-radius:10px;border:1px solid #CBD5E1;background:#F8FAFC;font-size:0.9rem;box-sizing:border-box;outline:none;" type="tel" required>
    <select name="service" style="width:100%;padding:14px 18px;border-radius:10px;border:1px solid #CBD5E1;background:#F8FAFC;font-size:0.9rem;box-sizing:border-box;color:#475569;outline:none;">
      <option value="">Select Service</option>
      <option value="seo">Search Engine Optimization</option>
      <option value="ppc">PPC & Google Ads</option>
      <option value="web">Website Development</option>
      <option value="social">Social Media Marketing</option>
    </select>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <input name="company" placeholder="Company Name*" style="width:100%;padding:14px 18px;border-radius:10px;border:1px solid #CBD5E1;background:#F8FAFC;font-size:0.9rem;box-sizing:border-box;outline:none;" type="text" required>
    <input name="website" placeholder="Website/Landing Page*" style="width:100%;padding:14px 18px;border-radius:10px;border:1px solid #CBD5E1;background:#F8FAFC;font-size:0.9rem;box-sizing:border-box;outline:none;" type="url">
  </div>
  <div>
    <select name="budget" style="width:100%;padding:14px 18px;border-radius:10px;border:1px solid #CBD5E1;background:#F8FAFC;font-size:0.9rem;box-sizing:border-box;color:#475569;outline:none;">
      <option value="">Budget Spent In Last 30 Days?</option>
      <option value="under1k">Under $1,000</option>
      <option value="1k-5k">$1,000 - $5,000</option>
      <option value="5k-10k">$5,000 - $10,000</option>
      <option value="10k+">$10,000+</option>
    </select>
  </div>
  <div><textarea name="details" placeholder="Additional Details/Purpose of Business" style="width:100%;padding:14px 18px;border-radius:10px;border:1px solid #CBD5E1;background:#F8FAFC;font-size:0.9rem;box-sizing:border-box;height:100px;resize:vertical;outline:none;"></textarea></div>
  <button type="submit" style="width:100%;background:#0A3663;color:#ffffff;border:none;border-radius:50px;padding:16px;font-weight:700;font-size:0.98rem;cursor:pointer;margin-top:6px;">Request a Marketing Proposal</button>
</form>';
    }

    private function footer_services_html(): string
    {
        $services = array('Digital Marketing', 'Google Ads Management', 'Branding Services', 'Search Engine Optimization', 'Web Development', 'Social Media Management', 'Online Reputation Management', 'VFX Service');
        $html = '';
        foreach ($services as $n) {
            $html .= '<li style="list-style:none;margin-bottom:8px;"><a style="color:#CBD5E1;text-decoration:none;font-size:0.88rem;" href="#">' . esc_html($n) . '</a></li>';
        }
        return '<ul style="margin:0;padding:0;">' . $html . '</ul>';
    }

    private function footer_links_html(): string
    {
        $links = array('Home', 'About Us', 'Blog', 'Career', 'Contact Us', 'Financial Reporting');
        $html = '';
        foreach ($links as $l) {
            $html .= '<li style="list-style:none;margin-bottom:8px;"><a style="color:#CBD5E1;text-decoration:none;font-size:0.88rem;" href="#">' . esc_html($l) . '</a></li>';
        }
        return '<ul style="margin:0;padding:0;">' . $html . '</ul>';
    }

    private function footer_contact_html(string $phone, string $email): string
    {
        return '<div style="font-size:0.88rem;color:#CBD5E1;line-height:1.8;">'
             . 'R K Complex, 16/28-Vijay Plot, Gondal Road, RAIKOT - 360002.<br><br>'
             . 'Call: <a style="color:#FFFFFF;text-decoration:none;font-weight:700;" href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a><br>'
             . 'Email: <a style="color:#38BDF8;text-decoration:none;font-weight:600;" href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>'
             . '</div>';
    }

    private function social_icons_html(): string
    {
        $icons = array(
            'Facebook'  => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'Instagram' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
            'LinkedIn'  => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>',
            'YouTube'   => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
        );

        $html = '<div style="display:flex;gap:12px;margin-top:14px;">';
        foreach ($icons as $label => $svg) {
            $html .= '<a title="' . esc_attr($label) . '" href="#" style="width:38px;height:38px;border-radius:50%;background:#F1F5F9;display:flex;align-items:center;justify-content:center;color:#0A3663;border:1px solid #CBD5E1;transition:all 0.2s ease;text-decoration:none;">' . $svg . '</a>';
        }
        $html .= '</div>';
        return $html;
    }

    private function topbar_html(string $phone, string $email): string
    {
        return '<div style="background:#034375;padding:8px 30px;color:#FFFFFF;font-size:0.85rem;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
  <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:24px;">
    <span>📞 <a href="tel:' . esc_attr($phone) . '" style="color:#FFFFFF;text-decoration:none;font-weight:600;margin-left:4px;">' . esc_html($phone) . '</a></span>
    <span>✉ <a href="mailto:' . esc_attr($email) . '" style="color:#FFFFFF;text-decoration:none;margin-left:4px;">' . esc_html($email) . '</a></span>
  </div>
</div>';
    }

    private function portfolio_images_html(): string
    {
        $projects = array(
            array('title' => 'RLEY HAAS', 'category' => 'E-Commerce Branding', 'stat' => '+340% Revenue', 'bg' => 'linear-gradient(135deg, #1E1B4B 0%, #4338CA 100%)', 'badge' => 'E-COMMERCE'),
            array('title' => 'DİZREAL', 'category' => 'SaaS Marketing', 'stat' => '+210% Free-to-Paid', 'bg' => 'linear-gradient(135deg, #064E3B 0%, #059669 100%)', 'badge' => 'SAAS PLATFORM'),
            array('title' => 'Moving Beyond', 'category' => 'Healthcare SEO', 'stat' => '+450% Patient Leads', 'bg' => 'linear-gradient(135deg, #0F172A 0%, #0284C7 100%)', 'badge' => 'HEALTHCARE'),
            array('title' => 'Yoga with Lynn', 'category' => 'Wellness & App Growth', 'stat' => '+180% Members', 'bg' => 'linear-gradient(135deg, #831843 0%, #DB2777 100%)', 'badge' => 'WELLNESS'),
            array('title' => 'Record Vinyl', 'category' => 'Retail & Local SEO', 'stat' => '+290% Foot Traffic', 'bg' => 'linear-gradient(135deg, #451A03 0%, #D97706 100%)', 'badge' => 'RETAIL'),
            array('title' => 'MDRG Campaign', 'category' => 'B2B Enterprise', 'stat' => '+310% Pipeline', 'bg' => 'linear-gradient(135deg, #311042 0%, #7C3AED 100%)', 'badge' => 'B2B GROWTH'),
        );

        $html = '';
        foreach ($projects as $p) {
            $html .= '<div style="height:240px;border-radius:18px;padding:24px;color:#FFFFFF;background:' . $p['bg'] . ';border:1px solid rgba(255,255,255,0.1);box-shadow:0 10px 25px rgba(0,0,0,0.1);display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;">';
            $html .= '<div style="display:flex;justify-content:space-between;align-items:center;">';
            $html .= '<span style="background:rgba(255,255,255,0.2);backdrop-filter:blur(6px);padding:4px 12px;border-radius:20px;font-size:0.72rem;font-weight:700;letter-spacing:1px;">' . esc_html($p['badge']) . '</span>';
            $html .= '<span style="font-size:0.82rem;font-weight:700;color:#FDE047;">' . esc_html($p['stat']) . '</span>';
            $html .= '</div>';
            $html .= '<div>';
            $html .= '<h3 style="margin:0 0 4px;font-size:1.4rem;font-weight:900;color:#FFFFFF;letter-spacing:0.5px;">' . esc_html($p['title']) . '</h3>';
            $html .= '<p style="margin:0;font-size:0.88rem;color:rgba(255,255,255,0.8);">' . esc_html($p['category']) . '</p>';
            $html .= '</div>';
            $html .= '</div>';
        }
        return $html;
    }

    private function get_asset_url(string $file): string
    {
        if (function_exists('plugins_url')) {
            return plugins_url('assets/' . $file, dirname(__DIR__) . '/vispan-city-page-generator.php');
        }
        return '/wp-content/plugins/assets/' . $file;
    }

    private function certifications_html(): string
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
            $html .= '<img src="' . $c['img'] . '" alt="' . esc_html($c['title']) . '" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;">';
            $html .= '<div style="font-weight:600;font-size:0.92rem;color:#222222;line-height:1.35;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">' . esc_html($c['title']) . '</div>';
            $html .= '</div>';
        }
        $html .= '  </div>';
        $html .= '</div>';
        return $html;
    }

    private function testimonial_html(): string
    {
        $testimonial = array(
            'name'    => 'RONIT SHARMA',
            'role'    => 'CEO & Founder, Vispan Partner Network',
            'content' => 'I recently worked with Vispan Solutions to develop a new website and digital marketing campaign for my business, and I could not be more pleased with the results. The team at Vispan Solutions incorporated my vision into their design perfectly and delivered a 300% growth in qualified local inquiries.',
        );

        $html  = '<div style="max-width:780px;margin:0 auto;text-align:center;padding:36px 30px;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:24px;box-shadow:0 12px 30px rgba(0,0,0,0.05);">';
        $html .= '<div style="color:#F59E0B;font-size:1.4rem;margin-bottom:14px;">★★★★★</div>';
        $html .= '<div style="font-size:1.12rem;color:#1E293B;line-height:1.8;font-style:italic;margin-bottom:24px;">&ldquo;' . esc_html($testimonial['content']) . '&rdquo;</div>';
        $html .= '<div style="font-weight:800;color:#0A3663;font-size:1.08rem;letter-spacing:1px;text-transform:uppercase;">' . esc_html($testimonial['name']) . '</div>';
        $html .= '<div style="font-size:0.85rem;color:#64748B;margin-top:4px;">' . esc_html($testimonial['role']) . '</div>';
        $html .= '</div>';
        return $html;
    }

    private function logos_html(): string
    {
        $brands = array(
            array('name' => 'YouTube', 'color' => '#FF0000'),
            array('name' => 'Instagram', 'color' => '#E1306C'),
            array('name' => 'Facebook', 'color' => '#1877F2'),
            array('name' => 'Google Ads', 'color' => '#4285F4'),
            array('name' => 'App Store', 'color' => '#007AFF'),
            array('name' => 'Click Funnels', 'color' => '#3182CE'),
        );
        $html = '<div style="display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:28px;">';
        foreach ($brands as $b) {
            $html .= '<div style="display:flex;align-items:center;gap:8px;font-weight:800;color:#0A3663;font-size:1.1rem;">'
                   . '<span style="color:' . $b['color'] . ';font-size:1.3rem;">●</span> ' . esc_html($b['name'])
                   . '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}

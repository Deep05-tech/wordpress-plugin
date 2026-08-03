<?php

defined('ABSPATH') || exit;


class VCPG_Page_Generator
{


    private $ai_generator;

    private $city_manager;

    private $seo_generator;

    private $keyword_manager;



    public function __construct(
        $ai_generator,
        $city_manager,
        $seo_generator,
        $keyword_manager = null
    )
    {

        $this->ai_generator = $ai_generator;

        $this->city_manager = $city_manager;

        $this->seo_generator = $seo_generator;

        $this->keyword_manager = $keyword_manager;

    }





    public function create_page($data)
    {


        /*
        Get Data
        */

        $country = sanitize_text_field(
            $data['country']
        );

        error_log(
            'PAGE GENERATOR DATA: '.print_r($data,true)
        );

        $country_code = isset($data['country_code'])
            ? sanitize_key($data['country_code'])
            : '';



        /*
        Fallback protection:
        If country code is missing,
        generate slug from country name.
        */

        if(empty($country_code) && !empty($country))
        {

            $country_code = sanitize_key(
                $country
            );

        }

        $city = sanitize_text_field(
            $data['city']
        );

        $state = isset($data['state'])
            ? sanitize_text_field($data['state'])
            : '';

        $service = sanitize_text_field(
            $data['service']
        );


        $city = sanitize_text_field(
            $data['city']
        );


        $state = isset($data['state']) 
            ? sanitize_text_field($data['state']) 
            : '';



        $service = sanitize_text_field(
            $data['service']
        );


        $service_keyword = sanitize_title(
            $data['service_keyword']
        );





        /*
        Duplicate Check
        Using AI Content Database
        */

        global $wpdb;


        $table = $wpdb->prefix . 'vcpg_ai_content';



        $existing_content = $wpdb->get_row(

            $wpdb->prepare(

                "
                SELECT *
                FROM $table
                WHERE service = %s
                AND city = %s
                AND state = %s
                AND country = %s
                AND prompt_version = %s
                LIMIT 1
                ",

                $service,

                $city,

                $state,

                $country,

                VCPG_AI_Content_Database::CONTENT_VERSION

            )

        );





        if($existing_content)
        {

            return array(

                'status'=>false,

                'message'=>'Page already exists',

                'page_id'=>''

            );

        }





        /*
        Save City Database
        */

        if($this->city_manager)
        {

            $this->city_manager->save_city(

                array(

                    'country' => $country,

                    'country_code' => $country_code,

                    'state' => $state,

                    'city' => $city

                )

            );

        }





        /*
        Page Title — "{Service} in {State}" (state-first, falls back to city)
        */

        $page_title = $this->build_page_title(
            $service,
            $city,
            $state
        );


        $slug_text = $service . ' in ' . $city;

        if(!empty($state))
        {
            $slug_text .= ' ' . $state;
        }

        $page_slug = sanitize_title($slug_text);





        /*
        Check Existing WordPress Page
        */


        $existing_page = get_page_by_path(
            $country_code . '/' . $page_slug,
            OBJECT,
            'page'
        );



        if($existing_page)
        {

            return array(

                'status'=>false,

                'message'=>'WordPress page already exists',

                'page_id'=>$existing_page->ID

            );

        }





        /*
        Assign Target Keywords — real Google search terms this page must cover.
        Unused keywords are picked first so every keyword gets used across pages.
        */

        $target_keywords = array();

        if($this->keyword_manager)
        {
            $target_keywords = $this->keyword_manager->get_keywords_for_page(
                $service,
                $city,
                $state,
                50
            );

            $data['target_keywords'] = $target_keywords;
        }



        /*
        Generate AI Content
        */


        $ai_content = $this->ai_generator->generate(

            array(

                'service' => $service,

                'city' => $city,

                'state' => $state,

                'country' => $country,

                'country_code' => $country_code,

                'target_keywords' => $target_keywords

            )
        );



        /*
        Merge Content
        */

        $data = array_merge(

            $data,

            $ai_content

        );





        /*
        Find Country Page
        */

        $country_page = get_page_by_path(

            $country_code,

            OBJECT,

            'page'

        );





        if($country_page)
        {

            $country_page_id = $country_page->ID;

        }
        else
        {


            $country_page_id = wp_insert_post(

                array(

                    'post_title' => $country,

                    'post_name' => $country_code,

                    'post_status' => 'publish',

                    'post_type' => 'page'

                )

            );

            if($country_page_id && !is_wp_error($country_page_id))
            {

                clean_post_cache(
                    $country_page_id
                );

            }


        }





        /*
        Generate SEO Metadata
        */


        $data['page_title'] = $page_title;

        $seo_data = array();


        if($this->seo_generator)
        {

            $seo_data = $this->seo_generator->generate(

                $data

            );

        }





        /*
        Generate Template Content
        */

        $content = $this->get_template_content(

            $data

        );





        /*
        Create City Page
        */


        $page_id = wp_insert_post(

            array(

                'post_title' => $page_title,


                'post_name' => $page_slug,


                'post_content' => $content,


                'post_status' => 'publish',


                'post_type' => 'page',


                'post_parent' => $country_page_id

            )

        );





        if(
            !$page_id ||
            is_wp_error($page_id)
        )
        {

            return array(

                'status'=>false,

                'message'=>'Page creation failed'

            );

        }

        /*
        Mark as VCPG page for custom template
        */

        update_post_meta(
            $page_id,
            '_vcpg_page',
            '1'
        );

        /*
        Mark the assigned keywords as covered by this page so the
        next generated page picks a fresh batch of unused keywords.
        */

        if($this->keyword_manager && !empty($target_keywords))
        {
            $this->keyword_manager->mark_keywords_used(
                $target_keywords,
                $page_id
            );
        }

        /*
        Save SEO Metadata
        */


        if(
            $page_id &&
            !empty($seo_data)
        )
        {


            /*
            RankMath
            */


            if(isset($seo_data['meta_title']))
            {

                update_post_meta(

                    $page_id,

                    'rank_math_title',

                    $seo_data['meta_title']

                );

            }



            if(isset($seo_data['meta_description']))
            {

                update_post_meta(

                    $page_id,

                    'rank_math_description',

                    $seo_data['meta_description']

                );

            }



            if(isset($seo_data['focus_keyword']))
            {

                update_post_meta(

                    $page_id,

                    'rank_math_focus_keyword',

                    $seo_data['focus_keyword']

                );

            }





            /*
            Yoast
            */


            if(isset($seo_data['meta_title']))
            {

                update_post_meta(

                    $page_id,

                    '_yoast_wpseo_title',

                    $seo_data['meta_title']

                );

            }



            if(isset($seo_data['meta_description']))
            {

                update_post_meta(

                    $page_id,

                    '_yoast_wpseo_metadesc',

                    $seo_data['meta_description']

                );

            }



            if(isset($seo_data['focus_keyword']))
            {

                update_post_meta(

                    $page_id,

                    '_yoast_wpseo_focuskw',

                    $seo_data['focus_keyword']

                );

            }


        }





        /*
        Update AI Database Status
        */


        if($this->ai_generator)
        {

            global $wpdb;


            $table = $wpdb->prefix . 'vcpg_ai_content';



            $wpdb->update(

                $table,

                array(

                    'status'=>'completed'

                ),

                array(

                    'service'=>$service,

                    'city'=>$city,

                    'country'=>$country

                )

            );

        }





        /*
        Defer rewrite flush — flag it for batch flush
        to avoid flushing on every single page creation.
        */

        update_option(
            'vcpg_needs_rewrite_flush',
            true
        );



        return array(

            'status'=>true,

            'message'=>'Page created successfully',

            'page_id'=>$page_id

        );


    }








    public function get_template_content($data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'vcpg_templates';

        $template = $wpdb->get_row(
            "SELECT * FROM $table_name ORDER BY id DESC LIMIT 1"
        );

        if(!$template)
        {
            return $this->default_template();
        }

        $content = $template->content;

        $search = array(
            '{{service}}',
            '{{city}}',
            '{{state}}',
            '{{country}}',
            '{{country_code}}',
            '{{company_name}}',
            '{{logo}}',
            '{{website}}',
            '{{phone}}',
            '{{email}}',
            '{{hero_title}}',
            '{{hero_subtitle}}',
            '{{hero_description}}',
            '{{about_title}}',
            '{{about_content}}',
            '{{benefits_description}}',
            '{{benefit_cards}}',
            '{{why_choose}}',
            '{{why_choose_description}}',
            '{{service_cards}}',
            '{{services_description}}',
            '{{local_insight}}',
            '{{technology}}',
            '{{technology_description}}',
            '{{faq}}',
            '{{faq_description}}',
            '{{cta_title}}',
            '{{cta_content}}',
            '{{stats}}',
            '{{testimonial}}',
            '{{testimonials_description}}',
            '{{difference_content}}',
            '{{process_title}}',
            '{{process_description}}',
            '{{process_steps}}',
            '{{services_title}}',
            '{{case_studies_description}}',
            '{{case_studies}}'
        );

        $replace = array(            isset($data['service']) ? $data['service'] : '',
            isset($data['city']) ? $data['city'] : '',
            isset($data['state']) ? $data['state'] : '',
            isset($data['country']) ? $data['country'] : '',
            isset($data['country_code']) ? $data['country_code'] : '',
            'Vispan Solutions',
            'Vispan Solutions',
            'https://vispansolutions.com',
            '+91 XXXXX XXXXX',
            'info@vispansolutions.com',
            isset($data['hero_title']) ? $data['hero_title'] : '',
            isset($data['hero_subtitle']) ? $data['hero_subtitle'] : '',
            isset($data['hero_description']) ? $data['hero_description'] : '',
            isset($data['about_title']) ? $data['about_title'] : 'About '.(isset($data['company_name']) ? $data['company_name'] : 'Vispan Solutions'),
            isset($data['about_content']) ? $data['about_content'] : '',
            isset($data['benefits_description']) ? $data['benefits_description'] : '',
            $this->generate_benefit_cards($data),
            $this->generate_why_choose($data),
            isset($data['why_choose_description']) ? $data['why_choose_description'] : '',
            $this->generate_service_cards($data),
            isset($data['services_description']) ? $data['services_description'] : '',
            isset($data['local_insight']) ? $data['local_insight'] : '',
            $this->generate_technology($data),
            isset($data['technology_description']) ? $data['technology_description'] : '',
            $this->generate_faq($data),
            isset($data['faq_description']) ? $data['faq_description'] : '',
            isset($data['cta_title']) ? $data['cta_title'] : '',
            isset($data['cta_content']) ? $data['cta_content'] : '',
            $this->generate_stats($data),
            $this->generate_testimonial($data),
            isset($data['testimonials_description']) ? $data['testimonials_description'] : '',
            isset($data['difference_content']) ? $data['difference_content'] : '',
            isset($data['process_title']) ? $data['process_title'] : 'Our Proven Process',
            isset($data['process_description']) ? $data['process_description'] : 'A proven methodology that drives measurable growth.',
            $this->generate_process_steps($data),
            $this->generate_services_title($data),
            isset($data['case_studies_description']) ? $data['case_studies_description'] : 'We deliver measurable outcomes for businesses across industries, no matter where they are.',
            $this->generate_case_studies($data)
        );

        return str_replace($search, $replace, $content);
    }

    private function generate_services_title($data)
    {
        $svc = isset($data['service']) ? trim($data['service']) : '';
        if(empty($svc))
        {
            return 'Our Services';
        }
        $clean = preg_replace('/\s+Services$/i', '', $svc);
        return 'Our ' . $clean . ' Services';
    }

    private function build_page_title($service, $city, $state)
    {
        $title = $this->vcpg_title_case($service);

        $location = '';

        if(!empty($state))
        {
            $location = $state;
        }
        elseif(!empty($city))
        {
            $location = $city;
        }

        if(!empty($location))
        {
            $title .= ' in ' . $this->vcpg_title_case($location);
        }

        return $title;
    }

    private function vcpg_title_case($text)
    {
        $text = trim($text);

        if($text === '')
        {
            return '';
        }

        $words = preg_split('/\s+/', $text);
        $output = array();

        foreach($words as $word)
        {
            $word = trim($word);

            if($word === '')
            {
                continue;
            }

            if(strlen($word) <= 4 && strtoupper($word) === $word)
            {
                $output[] = $word;
            }
            else
            {
                $output[] = ucfirst(strtolower($word));
            }
        }

        return implode(' ', $output);
    }

    private function generate_case_studies($data)
    {
        $items = isset($data['case_studies']) && is_array($data['case_studies'])
            ? $data['case_studies']
            : array();

        if(empty($items))
        {
            return '';
        }

        $html = '';

        foreach($items as $item)
        {
            if(!is_array($item))
            {
                continue;
            }

            $client   = isset($item['client']) ? esc_html($item['client']) : '';
            $industry = isset($item['industry']) ? esc_html($item['industry']) : '';
            $result   = isset($item['result']) ? esc_html($item['result']) : '';
            $summary  = isset($item['summary']) ? wp_kses_post($item['summary']) : '';

            if(empty($client) && empty($result) && empty($summary))
            {
                continue;
            }

            $html .= '<div class="vp-case-card">';
            $html .= '<div class="vp-case-top">';
            $html .= '<span class="vp-case-client">' . $client . '</span>';
            if(!empty($industry))
            {
                $html .= '<span class="vp-case-industry">' . $industry . '</span>';
            }
            $html .= '</div>';
            $html .= '<div class="vp-case-result">' . $result . '</div>';
            $html .= '<p class="vp-case-summary">' . $summary . '</p>';
            $html .= '</div>';
        }

        return $html;
    }

    private function generate_process_steps($data)
    {
        if(isset($data['process']) && is_array($data['process']))
        {
            $html = '';
            $count = 1;
            foreach($data['process'] as $step)
            {
                $title = isset($step['title']) ? esc_html($step['title']) : '';
                $desc = isset($step['description']) ? wp_kses_post($step['description']) : '';
                $num = str_pad((string)$count, 2, '0', STR_PAD_LEFT);
                $html .= '<div class="vp-process-card">';
                $html .= '<div class="vp-process-num">' . $num . '</div>';
                $html .= '<h4>' . $title . '</h4>';
                $html .= '<p>' . $desc . '</p>';
                $html .= '</div>';
                $count++;
            }
            return $html;
        }
        return '';
    }

    private function generate_service_cards($data)
    {
        if(isset($data['services']) && is_array($data['services']))
        {
            $html = '';
            foreach($data['services'] as $service)
            {
                $title = isset($service['title']) ? esc_html($service['title']) : '';
                $desc = isset($service['description']) ? wp_kses_post($service['description']) : '';
                $html .= '<div class="vpg-card">';
                $html .= '<h3>' . $title . '</h3>';
                $html .= '<p>' . $desc . '</p>';
                $html .= '</div>';
            }
            return $html;
        }
        return isset($data['service_list']) ? $data['service_list'] : '';
    }

    private function generate_benefit_cards($data)
    {
        if(isset($data['benefits']) && is_array($data['benefits']))
        {
            $html = '';
            foreach($data['benefits'] as $benefit)
            {
                $title = isset($benefit['title']) ? esc_html($benefit['title']) : '';
                $desc = isset($benefit['description']) ? wp_kses_post($benefit['description']) : '';
                $html .= '<div class="vpg-card">';
                $html .= '<div class="vpg-icon">✦</div>';
                $html .= '<h3>' . $title . '</h3>';
                $html .= '<p>' . $desc . '</p>';
                $html .= '</div>';
            }
            return $html;
        }
        return '';
    }

    private function generate_why_choose($data)
    {
        if(isset($data['why_choose']) && is_array($data['why_choose']))
        {
            $html = '';
            foreach($data['why_choose'] as $item)
            {
                $title = isset($item['title']) ? esc_html($item['title']) : '';
                $desc = isset($item['description']) ? wp_kses_post($item['description']) : '';
                $html .= '<div class="vpg-card">';
                $html .= '<div class="vpg-icon">✦</div>';
                $html .= '<h3>' . $title . '</h3>';
                $html .= '<p>' . $desc . '</p>';
                $html .= '</div>';
            }
            return $html;
        }
        return '';
    }

    private function default_template()
    {
        return $this->load_premium_template();
    }

    private function load_premium_template()
    {
        $file = plugin_dir_path(__FILE__) . '../templates/premium-template.html';
        if(file_exists($file))
        {
            return file_get_contents($file);
        }
        return $this->built_in_template();
    }

    private function built_in_template()
    {
        $file = plugin_dir_path(__FILE__) . '../templates/premium-template.html';
        if(file_exists($file))
        {
            return file_get_contents($file);
        }
        return '<p>Template file not found. Please ensure templates/premium-template.html exists.</p>';
    }

    private function generate_technology($data)
    {
        if(isset($data['technology']) && is_array($data['technology']))
        {
            $html = '<ul class="vpg-tech-list">';
            foreach($data['technology'] as $tech)
            {
                if(is_string($tech))
                {
                    $html .= '<li>' . esc_html($tech) . '</li>';
                }
                elseif(is_array($tech) && isset($tech['name']))
                {
                    $html .= '<li>' . esc_html($tech['name']) . '</li>';
                }
            }
            $html .= '</ul>';
            return $html;
        }
        return '';
    }

    private function generate_faq($data)
    {
        if(!isset($data['faq']))
        {
            return '';
        }

        if(is_string($data['faq']))
        {
            return $data['faq'];
        }

        if(!is_array($data['faq']))
        {
            return '';
        }

        $html = '';
        foreach($data['faq'] as $item)
        {
            $question = isset($item['question']) ? esc_html($item['question']) : '';
            $answer = isset($item['answer']) ? wp_kses_post($item['answer']) : '';
            $html .= '<details class="vp-faq-item">
                <summary class="vp-faq-q">' . $question . '</summary>
                <div class="vp-faq-a">' . $answer . '</div>
            </details>';
        }
        return $html;
    }

    private function generate_stats($data)
    {
        if(isset($data['stats']) && is_array($data['stats']))
        {
            $html = '';
            foreach($data['stats'] as $stat)
            {
                if(is_array($stat))
                {
                    $number = isset($stat['number']) ? esc_html($stat['number']) : '';
                    $label = isset($stat['label']) ? esc_html($stat['label']) : '';
                    $html .= '<div class="vpg-stat">';
                    $html .= '<span class="vpg-stat-number">' . $number . '</span>';
                    $html .= '<span class="vpg-stat-label">' . $label . '</span>';
                    $html .= '</div>';
                }
            }
            return $html;
        }
        return '';
    }

    private function generate_testimonial($data)
    {
        if(isset($data['testimonials']) && is_array($data['testimonials']))
        {
            $html = '';
            foreach($data['testimonials'] as $item)
            {
                $name = isset($item['name']) ? esc_html($item['name']) : '';
                $role = isset($item['role']) ? esc_html($item['role']) : '';
                $content = isset($item['content']) ? wp_kses_post($item['content']) : '';
                $initials = $name ? implode('', array_map(function($n) { return strtoupper($n[0]); }, explode(' ', $name))) : '?';
                $html .= '<div class="vpg-testimonial-card">';
                $html .= '<div class="vpg-testimonial-stars">★★★★★</div>';
                $html .= '<p class="vpg-testimonial-text">"' . $content . '"</p>';
                $html .= '<div class="vpg-testimonial-author">';
                $html .= '<div class="vpg-testimonial-avatar">' . $initials . '</div>';
                $html .= '<div>';
                $html .= '<strong>' . $name . '</strong>';
                $html .= '<span>' . $role . '</span>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            return $html;
        }
        return '';
    }

}
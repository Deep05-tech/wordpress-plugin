<?php

defined('ABSPATH') || exit;


class VCPG_Page_Generator
{


    private $ai_generator;

    private $city_manager;

    private $seo_generator;



    public function __construct(
        $ai_generator,
        $city_manager,
        $seo_generator
    )
    {

        $this->ai_generator = $ai_generator;

        $this->city_manager = $city_manager;

        $this->seo_generator = $seo_generator;

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
            error_log(
                'FINAL URL DATA: '.print_r(
                    array(
                        'country_code'=>$country_code,
                        'country'=>$country,
                        'state'=>$state,
                        'city'=>$city,
                        'slug'=>$page_slug
                    ),
                    true
                )
            );



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
                LIMIT 1
                ",

                $service,

                $city,

                $state,

                $country

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
        Page Title
        */

        $page_title = 'Best '.$data['service'].' in '.$data['city'];

        if(!empty($state))
        {

            $page_title .= ', ' . trim($state);

        }


        $slug_text = 
            'best ' .
            $service .
            ' in ' .
            $city;


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
        Generate AI Content
        */


        $ai_content = $this->ai_generator->generate(

            array(

                'service' => $service,

                'city' => $city,

                'state' => $state,

                'country' => $country,

                'country_code' => $country_code

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

        $data['page_title'] = $page_title;

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
            '{{benefits_description}}',
            '{{benefit_cards}}',
            '{{why_choose}}',
            '{{why_choose_description}}',
            '{{service_cards}}',
            '{{services_description}}',
            '{{technology}}',
            '{{technology_description}}',
            '{{faq}}',
            '{{faq_description}}',
            '{{cta_title}}',
            '{{cta_content}}',
            '{{stats}}',
            '{{testimonial}}',
            '{{testimonials_description}}',
            '{{difference_content}}'
        );

        $replace = array(
            isset($data['service']) ? $data['service'] : '',
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
            isset($data['benefits_description']) ? $data['benefits_description'] : '',
            $this->generate_benefit_cards($data),
            $this->generate_why_choose($data),
            isset($data['why_choose_description']) ? $data['why_choose_description'] : '',
            $this->generate_service_cards($data),
            isset($data['services_description']) ? $data['services_description'] : '',
            $this->generate_technology($data),
            isset($data['technology_description']) ? $data['technology_description'] : '',
            $this->generate_faq($data),
            isset($data['faq_description']) ? $data['faq_description'] : '',
            isset($data['cta_title']) ? $data['cta_title'] : '',
            isset($data['cta_content']) ? $data['cta_content'] : '',
            $this->generate_stats($data),
            $this->generate_testimonial($data),
            isset($data['testimonials_description']) ? $data['testimonials_description'] : '',
            isset($data['difference_content']) ? $data['difference_content'] : ''
        );

        return str_replace($search, $replace, $content);
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
            if(isset($item['question']))
            {
                $html .= '<p><strong>' . esc_html($item['question']) . '</strong></p>';
            }
            if(isset($item['answer']))
            {
                $html .= '<p>' . wp_kses_post($item['answer']) . '</p>';
            }
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
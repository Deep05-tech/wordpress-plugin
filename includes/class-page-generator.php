<?php

defined('ABSPATH') || exit;


class VCPG_Page_Generator
{


    private $ai_generator;

    private $city_manager;

    private $seo_generator;

    private $keyword_manager;

    private $elementor_builder;



    public function __construct(
        $ai_generator,
        $city_manager,
        $seo_generator,
        $keyword_manager = null,
        $elementor_builder = null
    )
    {

        $this->ai_generator = $ai_generator;

        $this->city_manager = $city_manager;

        $this->seo_generator = $seo_generator;

        $this->keyword_manager = $keyword_manager;

        $this->elementor_builder = $elementor_builder
            ? $elementor_builder
            : new VCPG_Elementor_Template_Builder();

    }





    public function create_page($data)
    {


        /*
        Get Data
        */

        $country = sanitize_text_field(
            $data['country']
        );

        $city = sanitize_text_field(
            $data['city']
        );

        $service = sanitize_text_field(
            $data['service']
        );

        update_option('vcpg_job_activity', 'Checking local database cache for ' . $city . ' - ' . $service . '...');

        error_log(
            'PAGE GENERATOR DATA: '.print_r($data,true)
        );

        /*
        Output mode — 'html' (default) preserves the existing post_content path.
        'elementor' writes to _elementor_data postmeta instead.
        */
        $mode = get_option( 'vcpg_output_mode', 'html' );

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



        $service_variations = $this->get_service_variations($service);
        $variation_seed = $city . '|' . $state;
        $hash = md5($variation_seed);
        $index = hexdec(substr($hash, 0, 4)) % count($service_variations);
        $selected_service = $service_variations[$index];

        $service = $selected_service;
        $data['service'] = $selected_service;

        $service_keyword = sanitize_title($selected_service);





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
        if($this->ai_generator)
        {
            update_option('vcpg_job_activity', 'Querying OpenAI API to generate professional landing page copy (this can take 5-15 seconds)...');

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
        }

        update_option('vcpg_job_activity', 'Checking if WordPress page already exists and preparing structure...');

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
            $data['page_title'] = $page_title;

            if ( $mode === 'elementor' ) {

                update_option('vcpg_job_activity', 'Designing landing page template in Elementor...');

                $renderer       = new VCPG_Elementor_Renderer();
                $tpl_content    = $renderer->load_template_content();
                $elementor_content = $tpl_content !== null
                    ? $renderer->build_elementor_content( $tpl_content, $data )
                    : null;

                if ( $elementor_content === null ) {
                    // Renderer returned null — fall back to HTML mode for this page.
                    error_log( 'VCPG create_page (update): Elementor renderer returned null, falling back to HTML mode.' );
                    $html_content = $this->elementor_builder->build_html( $data );
                    if ( $this->ai_generator ) {
                        $html_content = $this->ai_generator->sanitize_html_content( $html_content, $data );
                    }
                    update_option('vcpg_job_activity', 'Saving generated page updates to database...');
                    wp_update_post( array(
                        'ID'           => $existing_page->ID,
                        'post_content' => $html_content,
                    ) );
                } else {
                    if ( $this->ai_generator ) {
                        $elementor_content = $this->ai_generator->sanitize_elementor_content( $elementor_content, $data );
                    }
                    update_option('vcpg_job_activity', 'Saving generated page updates to database...');
                    wp_update_post( array(
                        'ID'           => $existing_page->ID,
                        'post_content' => '',
                    ) );
                    update_post_meta( $existing_page->ID, '_elementor_data', wp_slash( wp_json_encode( $elementor_content ) ) );
                    delete_post_meta( $existing_page->ID, '_elementor_element_cache' );
                    update_post_meta( $existing_page->ID, '_elementor_edit_mode', 'builder' );
                    update_post_meta( $existing_page->ID, '_elementor_template_type', 'wp-page' );
                    update_post_meta( $existing_page->ID, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.23.0' );
                    update_post_meta( $existing_page->ID, '_wp_page_template', 'default' );
                    try {
                        update_option('vcpg_job_activity', 'Regenerating Elementor layout CSS and clearing cache...');
                        if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
                            ( new \Elementor\Core\Files\CSS\Post( $existing_page->ID ) )->update();
                        }
                    } catch ( \Throwable $css_err ) {
                        error_log( 'VCPG Elementor CSS regen failed (update): ' . $css_err->getMessage() );
                    }
                }

            } else {

                // html mode — identical to previous behaviour
                update_option('vcpg_job_activity', 'Designing HTML landing page and building layouts...');
                $elementor_content = $this->get_template_content( $data );
                if ( $this->ai_generator ) {
                    $elementor_content = $this->ai_generator->sanitize_elementor_content( $elementor_content, $data );
                }
                $html_content      = $this->elementor_builder->build_html( $data );
                if ( $this->ai_generator ) {
                    $html_content = $this->ai_generator->sanitize_html_content( $html_content, $data );
                }

                update_option('vcpg_job_activity', 'Saving generated page updates to database...');
                wp_update_post( array(
                    'ID'           => $existing_page->ID,
                    'post_content' => $html_content
                ) );

                update_post_meta( $existing_page->ID, '_elementor_data', wp_slash( wp_json_encode( $elementor_content ) ) );
                update_post_meta( $existing_page->ID, '_elementor_edit_mode', 'builder' );
                update_post_meta( $existing_page->ID, '_elementor_template_type', 'wp-page' );
                update_post_meta( $existing_page->ID, '_elementor_version', '3.23.0' );
                update_post_meta( $existing_page->ID, '_wp_page_template', 'default' );

                if( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) )
                {
                    $doc = \Elementor\Plugin::$instance->documents->get( $existing_page->ID );
                    if( $doc )
                    {
                        $doc->save( array( 'elements' => $elementor_content ) );
                    }
                    if( isset( \Elementor\Plugin::$instance->files_manager ) )
                    {
                        \Elementor\Plugin::$instance->files_manager->clear_cache();
                    }
                }

            } // end mode switch

            update_post_meta( $existing_page->ID, '_vcpg_page', '1' );
            clean_post_cache( $existing_page->ID );

            return array(
                'status'  => true,
                'message' => 'Existing WordPress page (ID: ' . $existing_page->ID . ') was updated with the latest Elementor template!',
                'page_id' => $existing_page->ID
            );
        }





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

        update_option('vcpg_job_activity', 'Designing landing page template in Elementor...');

        if ( $mode === 'elementor' ) {

            $renderer    = new VCPG_Elementor_Renderer();
            $tpl_content = $renderer->load_template_content();

            if ( $tpl_content !== null ) {
                $elementor_content = $renderer->build_elementor_content( $tpl_content, $data );
            } else {
                $elementor_content = null;
            }

            if ( $elementor_content === null ) {
                // Renderer returned null — fall back to HTML mode for this page.
                error_log( 'VCPG create_page (insert): Elementor renderer returned null, falling back to HTML mode.' );
                $mode = 'html';
            }

        }

        if ( $mode === 'html' ) {
            $elementor_content = $this->get_template_content( $data );
        }

        $html_content = ( $mode === 'html' )
            ? $this->elementor_builder->build_html( $data )
            : '';

        if ( $mode === 'html' && $this->ai_generator && !empty( $html_content ) ) {
            $html_content = $this->ai_generator->sanitize_html_content( $html_content, $data );
        }

        update_option('vcpg_job_activity', 'Saving generated page to WordPress database...');

        $page_id = wp_insert_post(
            array(
                'post_title'   => $page_title,
                'post_name'    => $page_slug,
                'post_content' => $html_content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => $country_page_id
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
        Save Elementor builder data so the page renders as a
        fully editable Elementor landing page when Elementor is active.
        */

        if ( $mode === 'elementor' && !empty( $elementor_content ) ) {

            $elementor_version = defined( 'ELEMENTOR_VERSION' )
                ? ELEMENTOR_VERSION
                : '3.23.0';

            if ( $this->ai_generator ) {
                $elementor_content = $this->ai_generator->sanitize_elementor_content( $elementor_content, $data );
            }

            update_post_meta( $page_id, '_elementor_edit_mode',     'builder' );
            update_post_meta( $page_id, '_elementor_data',          wp_slash( wp_json_encode( $elementor_content ) ) );
            delete_post_meta( $page_id, '_elementor_element_cache' );
            update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
            update_post_meta( $page_id, '_elementor_version',       $elementor_version );
            update_post_meta( $page_id, '_wp_page_template',        'default' );

            try {
                update_option('vcpg_job_activity', 'Regenerating Elementor layout CSS and clearing cache...');
                if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
                    ( new \Elementor\Core\Files\CSS\Post( $page_id ) )->update();
                }
            } catch ( \Throwable $css_err ) {
                error_log( 'VCPG Elementor CSS regen failed (insert): ' . $css_err->getMessage() );
            }

        } elseif ( $mode === 'html' && !empty( $elementor_content ) ) {

            // html mode — identical to previous behaviour
            update_option('vcpg_job_activity', 'Saving generated page metadata to WordPress...');
            $elementor_version = defined('ELEMENTOR_VERSION')
                ? ELEMENTOR_VERSION
                : '3.23.0';

            if ( $this->ai_generator ) {
                $elementor_content = $this->ai_generator->sanitize_elementor_content( $elementor_content, $data );
            }

            update_post_meta(
                $page_id,
                '_elementor_edit_mode',
                'builder'
            );

            update_post_meta(
                $page_id,
                '_elementor_data',
                wp_slash(wp_json_encode($elementor_content))
            );
            delete_post_meta( $page_id, '_elementor_element_cache' );

            update_post_meta(
                $page_id,
                '_elementor_template_type',
                'wp-page'
            );

            update_post_meta(
                $page_id,
                '_elementor_version',
                $elementor_version
            );

            update_post_meta($page_id, '_wp_page_template', 'default');

            if(class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->documents))
            {
                $doc = \Elementor\Plugin::$instance->documents->get($page_id);
                if($doc)
                {
                    $doc->save(array('elements' => $elementor_content));
                }
                if(isset(\Elementor\Plugin::$instance->files_manager))
                {
                    \Elementor\Plugin::$instance->files_manager->clear_cache();
                }
            }
        }

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

        update_option('vcpg_job_activity', 'Page successfully created!');

        return array(

            'status'=>true,

            'message'=>'Page created successfully',

            'page_id'=>$page_id

        );


    }








    public function get_template_content($data)
    {
        if(!$this->elementor_builder)
        {
            return array();
        }

        return $this->elementor_builder->build($data);
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

    private function get_service_variations($service)
    {
        $service = trim($service);
        $variations = array($service);

        // Pattern 1: Contains "Marketing Agency" (case insensitive)
        if (preg_match('/^(.*)\bmarketing\s+agency$/i', $service, $matches)) {
            $base = trim($matches[1]);
            $variations[] = $base . ' Marketing Services';
            $variations[] = $base . ' SEO Agency';
            $variations[] = $base . ' Advertising Agency';
            $variations[] = $base . ' Digital Marketing';
            $variations[] = $base . ' Growth Partner';
            $variations[] = $base . ' SEO Specialists';
            $variations[] = $base . ' Marketing Solutions';
            $variations[] = $base . ' Lead Generation';
            $variations[] = $base . ' SEO Experts';
            $variations[] = $base . ' Online Marketing';
            if (stripos($base, 'law') !== false || stripos($base, 'legal') !== false || stripos($base, 'attorney') !== false) {
                $variations[] = 'Legal Marketing Agency';
                $variations[] = 'Attorney Marketing Services';
                $variations[] = 'Lawyer SEO Agency';
                $variations[] = 'Law Firm SEO Experts';
                $variations[] = 'Legal Advertising Specialists';
                $variations[] = 'Lawyer Lead Generation';
                $variations[] = 'Law Firm Digital Growth';
            }
        }
        // Pattern 2: Contains "SEO Agency"
        elseif (preg_match('/^(.*)\bseo\s+agency$/i', $service, $matches)) {
            $base = trim($matches[1]);
            $variations[] = $base . ' SEO Services';
            $variations[] = $base . ' SEO Experts';
            $variations[] = $base . ' Search Engine Optimization';
            $variations[] = $base . ' Search Marketing';
            $variations[] = $base . ' Organic Traffic Agency';
            $variations[] = $base . ' SEO Specialists';
            $variations[] = $base . ' SEO Solutions';
            $variations[] = $base . ' Local SEO Agency';
        }
        // Pattern 3: General Fallback
        else {
            $variations[] = $service . ' Services';
            $variations[] = $service . ' Agency';
            $variations[] = $service . ' Experts';
            $variations[] = $service . ' Specialists';
            $variations[] = $service . ' Solutions';
            $variations[] = $service . ' Company';
            $variations[] = $service . ' Consulting';
            $variations[] = $service . ' Partner';
            $variations[] = 'Professional ' . $service;
            $variations[] = 'Top ' . $service;
        }

        // Filter out duplicate variations and the original service name
        $unique_vars = array();
        $seen = array();
        $seen[strtolower(trim($service))] = true; // Exclude original service
        foreach ($variations as $var) {
            $key = strtolower(trim($var));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique_vars[] = $var;
            }
        }
        if (empty($unique_vars)) {
            $unique_vars[] = $service;
        }
        return $unique_vars;
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

    public static function generate_case_studies($data)
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

            $html .= '<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:18px;padding:28px;box-shadow:0 10px 25px rgba(0,0,0,0.05);display:flex;flex-direction:column;text-align:left;">';
            $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
            $html .= '<span style="font-weight:700;color:#0A3663;font-size:1.05rem;">' . $client . '</span>';
            if(!empty($industry))
            {
                $html .= '<span style="background:#F1F5F9;color:#475569;padding:4px 12px;border-radius:20px;font-size:0.78rem;font-weight:600;">' . $industry . '</span>';
            }
            $html .= '</div>';
            $html .= '<div style="color:#0B63F6;font-size:1.25rem;font-weight:800;margin-bottom:10px;">' . $result . '</div>';
            $html .= '<p style="color:#475569;font-size:0.88rem;line-height:1.65;margin:0;">' . $summary . '</p>';
            $html .= '</div>';
        }

        return $html;
    }

    public static function generate_process_steps($data)
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
                $html .= '<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,0.04);text-align:left;">';
                $html .= '<div style="font-size:1.4rem;font-weight:900;color:#0B63F6;background:#EFF6FF;width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:12px;">' . $num . '</div>';
                $html .= '<h4 style="margin:0 0 8px;font-size:1.05rem;font-weight:700;color:#0A3663;">' . $title . '</h4>';
                $html .= '<p style="color:#475569;font-size:0.88rem;line-height:1.6;margin:0;">' . $desc . '</p>';
                $html .= '</div>';
                $count++;
            }
            return $html;
        }
        return '';
    }

    public static function generate_service_cards($data)
    {
        if(isset($data['services']) && is_array($data['services']))
        {
            $html = '';
            foreach($data['services'] as $service)
            {
                $title = isset($service['title']) ? esc_html($service['title']) : '';
                $desc = isset($service['description']) ? wp_kses_post($service['description']) : '';
                $html .= '<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;padding:28px;box-shadow:0 10px 25px rgba(0,0,0,0.05);display:flex;flex-direction:column;height:100%;text-align:left;">';
                $html .= '<div style="font-size:1.6rem;margin-bottom:14px;color:#0B63F6;background:#EFF6FF;width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;">⚙️</div>';
                $html .= '<h3 style="margin:0 0 8px;font-size:1.15rem;font-weight:700;color:#0A3663;">' . $title . '</h3>';
                $html .= '<p style="color:#475569;font-size:0.9rem;line-height:1.65;margin:0;">' . $desc . '</p>';
                $html .= '</div>';
            }
            return $html;
        }
        return isset($data['service_list']) ? $data['service_list'] : '';
    }

    public static function generate_benefit_cards($data)
    {
        if(isset($data['benefits']) && is_array($data['benefits']))
        {
            $html = '';
            foreach($data['benefits'] as $benefit)
            {
                $title = isset($benefit['title']) ? esc_html($benefit['title']) : '';
                $desc = isset($benefit['description']) ? wp_kses_post($benefit['description']) : '';
                $html .= '<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,0.04);display:flex;flex-direction:column;text-align:left;">';
                $html .= '<div style="font-size:1.2rem;color:#0B63F6;background:#EFF6FF;width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;">✦</div>';
                $html .= '<h3 style="margin:0 0 6px;font-size:1.05rem;font-weight:700;color:#0A3663;">' . $title . '</h3>';
                $html .= '<p style="color:#475569;font-size:0.88rem;line-height:1.6;margin:0;">' . $desc . '</p>';
                $html .= '</div>';
            }
            return $html;
        }
        return '';
    }

    public static function generate_why_choose($data)
    {
        if(isset($data['why_choose']) && is_array($data['why_choose']))
        {
            $html = '';
            foreach($data['why_choose'] as $item)
            {
                $title = isset($item['title']) ? esc_html($item['title']) : '';
                $desc = isset($item['description']) ? wp_kses_post($item['description']) : '';
                $html .= '<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,0.04);display:flex;flex-direction:column;text-align:left;">';
                $html .= '<div style="font-size:1.2rem;color:#0B63F6;background:#EFF6FF;width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;">✦</div>';
                $html .= '<h3 style="margin:0 0 6px;font-size:1.05rem;font-weight:700;color:#0A3663;">' . $title . '</h3>';
                $html .= '<p style="color:#475569;font-size:0.88rem;line-height:1.6;margin:0;">' . $desc . '</p>';
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

    public static function generate_technology($data)
    {
        if(isset($data['technology']) && is_array($data['technology']))
        {
            $html = '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
            foreach($data['technology'] as $tech)
            {
                $val = is_string($tech) ? $tech : (is_array($tech) && isset($tech['name']) ? $tech['name'] : '');
                if(!empty($val))
                {
                    $html .= '<span style="background:#F1F5F9;color:#0A3663;border:1px solid #CBD5E1;padding:8px 18px;border-radius:20px;font-weight:600;font-size:0.88rem;">' . esc_html($val) . '</span>';
                }
            }
            $html .= '</div>';
            return $html;
        }
        return '';
    }

    public static function generate_faq($data)
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
            $html .= '<details style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,0.02);overflow:hidden;">';
            $html .= '<summary style="color:#0A3663;font-weight:700;font-size:1.02rem;padding:16px 20px;cursor:pointer;outline:none;">' . $question . '</summary>';
            $html .= '<div style="color:#475569;font-size:0.92rem;line-height:1.65;padding:0 20px 18px;border-top:1px solid #F1F5F9;margin-top:4px;">' . $answer . '</div>';
            $html .= '</details>';
        }
        return $html;
    }

    public static function generate_stats($data)
    {
        if(isset($data['stats']) && is_array($data['stats']))
        {
            $html = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;">';
            foreach($data['stats'] as $stat)
            {
                if(is_array($stat))
                {
                    $number = isset($stat['number']) ? esc_html($stat['number']) : '';
                    $label = isset($stat['label']) ? esc_html($stat['label']) : '';
                    $html .= '<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,0.04);text-align:center;">';
                    $html .= '<span style="display:block;color:#0A3663;font-size:2.2rem;font-weight:900;line-height:1;">' . $number . '</span>';
                    $html .= '<span style="display:block;color:#475569;font-size:0.88rem;font-weight:600;margin-top:6px;">' . $label . '</span>';
                    $html .= '</div>';
                }
            }
            $html .= '</div>';
            return $html;
        }
        return '';
    }

    public static function generate_testimonial($data)
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
                $html .= '<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,0.04);margin-bottom:16px;text-align:left;">';
                $html .= '<div style="color:#F59E0B;font-size:1.2rem;margin-bottom:10px;">★★★★★</div>';
                $html .= '<p style="color:#334155;font-size:0.95rem;line-height:1.6;font-style:italic;margin-bottom:14px;">"' . $content . '"</p>';
                $html .= '<div style="display:flex;align-items:center;gap:12px;">';
                $html .= '<div style="width:40px;height:40px;border-radius:50%;background:#0A3663;color:#FFFFFF;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;">' . $initials . '</div>';
                $html .= '<div>';
                $html .= '<strong style="display:block;color:#0A3663;font-size:0.92rem;">' . $name . '</strong>';
                $html .= '<span style="color:#64748B;font-size:0.8rem;">' . $role . '</span>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            return $html;
        }
        return '';
    }

}
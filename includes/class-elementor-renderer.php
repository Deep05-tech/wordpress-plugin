<?php

defined('ABSPATH') || exit;

/**
 * VCPG_Elementor_Renderer
 *
 * Renders a decoded Elementor JSON content array by replacing every
 * {{token}} placeholder with its final value.  Used by VCPG_Page_Generator
 * when vcpg_output_mode = 'elementor'.
 */
class VCPG_Elementor_Renderer
{
    /**
     * Expected array item counts for grids in the Elementor template.
     * Enforced to prevent grid layout breakage.
     */
    private const EXPECTED_COUNTS = array(
        'services'     => 6,
        'benefits'     => 4,
        'why_choose'   => 4,
        'stats'        => 4,
        'process'      => 4,
        'case_studies' => 3,
        'testimonials' => 3,
        'faq'          => 6,
    );

    /**
     * Build the final Elementor content array ready to be stored in
     * _elementor_data postmeta.
     *
     * @param  array      $template_json_content Decoded 'content' array from Elementor export.
     * @param  array      $data                  Merged data array.
     * @return array|null Filled content array, or null on failure.
     */
    public function build_elementor_content( array $template_json_content, array $data ): ?array
    {
        if ( empty( $template_json_content ) ) {
            error_log( 'VCPG_Elementor_Renderer: empty template_json_content — cannot render.' );
            return null;
        }

        // ------------------------------------------------------------------ //
        // 1.  Enforce expected array lengths for grid slots (slice/pad).
        // ------------------------------------------------------------------ //
        $normalized_data = $this->normalize_array_counts( $data );

        // ------------------------------------------------------------------ //
        // 2.  Fetch static site-wide chrome & static proof replacements.
        // ------------------------------------------------------------------ //
        $static_elements = new VCPG_Static_Elements();
        $replacements    = $static_elements->get_replacements( $normalized_data );

        // ------------------------------------------------------------------ //
        // 3.  Delegate HTML generator methods via builder & page generator.
        // ------------------------------------------------------------------ //
        $builder = new VCPG_Elementor_Template_Builder();
        $builder_replacements = $builder->get_replacements( $normalized_data );

        $replacements = array_merge( $replacements, $builder_replacements );

        // ------------------------------------------------------------------ //
        // 4.  Overlay AI scalar fields when present.
        // ------------------------------------------------------------------ //
        $scalar_overrides = array(
            '{{services_heading}}'   => isset( $normalized_data['services_heading'] )   ? $normalized_data['services_heading']   : null,
            '{{why_choose_heading}}' => isset( $normalized_data['why_choose_heading'] ) ? $normalized_data['why_choose_heading'] : null,
            '{{consultation_title}}' => isset( $normalized_data['consultation_title'] ) ? $normalized_data['consultation_title'] : null,
            '{{contact_title}}'      => isset( $normalized_data['contact_title'] )      ? $normalized_data['contact_title']      : null,
            '{{cta_button}}'         => isset( $normalized_data['cta_button'] )         ? $normalized_data['cta_button']         : null,
        );

        foreach ( $scalar_overrides as $token => $value ) {
            if ( $value !== null && $value !== '' ) {
                $replacements[ $token ] = (string) $value;
            }
        }

        // ------------------------------------------------------------------ //
        // 5.  Walk the entire nested content array replacing strings via strtr.
        // ------------------------------------------------------------------ //
        $result = $template_json_content;

        array_walk_recursive( $result, function( &$value ) use ( $replacements ) {
            if ( is_string( $value ) ) {
                $value = strtr( $value, $replacements );
            }
        } );

        // ------------------------------------------------------------------ //
        // 6.  Sanity check: warn about surviving {{token}} markers.
        // ------------------------------------------------------------------ //
        $encoded = wp_json_encode( $result );
        if ( $encoded === false ) {
            error_log( 'VCPG_Elementor_Renderer: wp_json_encode() failed on rendered content.' );
            return null;
        }

        preg_match_all( '/\{\{[a-zA-Z_]+\}\}/', $encoded, $surviving );
        if ( ! empty( $surviving[0] ) ) {
            $unique = array_unique( $surviving[0] );
            error_log(
                'VCPG_Elementor_Renderer WARNING: ' . count( $unique ) .
                ' unresolved token(s) in rendered Elementor content: ' .
                implode( ', ', $unique )
            );
        }

        return $result;
    }

    /**
     * Slice or pad data arrays to guarantee exact count matching for Elementor grid slots.
     */
    private function normalize_array_counts( array $data ): array
    {
        // TODO: define fallback filler service/benefit/stat/process/case_study copy
        $fallbacks = array(
            'services' => array(
                'title'       => 'Digital Marketing Strategy',
                'description' => 'Comprehensive online growth and client acquisition strategies for local market leadership.',
                'icon'        => '⚙️',
            ),
            'benefits' => array(
                'title'       => 'Measurable Growth & ROI',
                'description' => 'Trackable analytics and performance-driven campaign execution.',
            ),
            'why_choose' => array(
                'title'       => 'Dedicated Specialist Team',
                'description' => 'Experienced digital marketing professionals committed to client success.',
            ),
            'stats' => array(
                'number' => '98%',
                'label'  => 'Client Satisfaction',
            ),
            'process' => array(
                'title'       => 'Ongoing Optimization',
                'description' => 'Continuous monitoring, testing, and strategy refinement.',
            ),
            'case_studies' => array(
                'client'   => 'Local Enterprise',
                'industry' => 'Professional Services',
                'result'   => '+240% Organic Inquiries',
                'summary'  => 'Implemented targeted local SEO and strategic digital positioning.',
            ),
            'testimonials' => array(
                'name'    => 'Satisfied Client',
                'role'    => 'Business Director',
                'content' => 'Outstanding digital strategy and excellent results for our business growth.',
            ),
            'faq' => array(
                'question' => 'How quickly will we see results?',
                'answer'   => 'Initial rankings and traffic improvements typically begin within 30 to 60 days.',
            ),
        );

        foreach ( self::EXPECTED_COUNTS as $key => $expected ) {
            if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
                $data[ $key ] = array();
            }

            $current_count = count( $data[ $key ] );

            if ( $current_count > $expected ) {
                $data[ $key ] = array_slice( $data[ $key ], 0, $expected );
            } elseif ( $current_count < $expected ) {
                $pad_item = isset( $fallbacks[ $key ] ) ? $fallbacks[ $key ] : array();
                while ( count( $data[ $key ] ) < $expected ) {
                    $data[ $key ][] = $pad_item;
                }
            }
        }

        return $data;
    }

    /**
     * Convenience helper: load the plugin's default Elementor JSON template file.
     */
    public function load_template_content( ?string $json_path = null ): ?array
    {
        if ( $json_path === null ) {
            $json_path = dirname( __DIR__ ) . '/templates/elementor-landing-template.json';
        }

        if ( ! file_exists( $json_path ) ) {
            error_log( 'VCPG_Elementor_Renderer: template file not found at ' . $json_path );
            return null;
        }

        $raw = file_get_contents( $json_path );
        if ( empty( $raw ) ) {
            error_log( 'VCPG_Elementor_Renderer: template file is empty at ' . $json_path );
            return null;
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) || ! isset( $decoded['content'] ) || ! is_array( $decoded['content'] ) ) {
            error_log( 'VCPG_Elementor_Renderer: malformed template JSON at ' . $json_path );
            return null;
        }

        return $decoded['content'];
    }
}

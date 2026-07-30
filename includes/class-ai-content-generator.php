<?php

defined('ABSPATH') || exit;


class VCPG_AI_Content_Generator
{


    private $database;

    private $provider;

    private $quality_checker;



    public function __construct(

        $database,

        $provider,

        $quality_checker

    )
    {

        $this->database = $database;

        $this->provider = $provider;

        $this->quality_checker = $quality_checker;

    }





    public function generate($data)
    {


        /*
        Check Existing Generated Content
        */


        $existing = $this->database->get_content(
            $data
        );



        if($existing)
        {


            return json_decode(

                $existing->content,

                true

            );


        }





        /*
        Create Unique Content Context
        */


        $previous_patterns = $this->database->get_recent_content_patterns(40);

        error_log(
            "PREVIOUS PATTERNS COUNT: ".count($previous_patterns)
        );


        $avoid_phrases = array();


        if($previous_patterns)
        {

            foreach($previous_patterns as $pattern)
            {
        
                if(!empty($pattern->used_phrases))
                {
            
                    $phrases = json_decode(
                        $pattern->used_phrases,
                        true
                    );


                    if(is_array($phrases))
                    {
                
                        foreach($phrases as $phrase)
                        {
                    
                            $avoid_phrases[] = $phrase;

                        }

                    }

                }

            }

        }



        $avoid_phrases = array_unique(
            array_slice(
                $avoid_phrases,
                0,
                200
            )
        );



        $avoid_text = implode(
            "\n- ",
            $avoid_phrases
        );

        error_log(
            "AVOID PHRASES: ".$avoid_text
        );




        /*
        Create AI Prompt
        */


        $prompt = "

        Create a completely unique local SEO landing page for [city].
        Every single section below must use completely different wording from any previous page.
        If a section pattern repeats, the page is rejected.

        Service:
        ".$data['service']."

        City:
        ".$data['city']."

        State:
        ".(isset($data['state']) ? $data['state'] : '')."

        Country:
        ".$data['country']."


        PREVIOUSLY USED PHRASES TO AVOID:
        - ".$avoid_text."


        CRITICAL RULE — READ CAREFULLY:
        Each page section below has specific DO and DO NOT instructions.
        Violating any DO NOT rule means the content is rejected.
        Every section must use a UNIQUE angle, sentence structure, and vocabulary
        that differs from ALL previous pages for this service.


        REQUIRED JSON STRUCTURE:

        {
        \"hero_title\":\"\",
        \"hero_subtitle\":\"\",
        \"hero_description\":\"\",
        \"benefits_description\":\"\",
        \"why_choose_description\":\"\",
        \"services_description\":\"\",
        \"testimonials_description\":\"\",
        \"faq_description\":\"\",
        \"services\":[{\"title\":\"\",\"description\":\"\"}],
        \"benefits\":[{\"title\":\"\",\"description\":\"\"}],
        \"why_choose\":[{\"title\":\"\",\"description\":\"\"}],
        \"technology\":[\"\"],
        \"technology_description\":\"\",
        \"faq\":[{\"question\":\"\",\"answer\":\"\"}],
        \"stats\":[{\"number\":\"\",\"label\":\"\"}],
        \"testimonials\":[{\"name\":\"\",\"role\":\"\",\"content\":\"\"}],
        \"difference_content\":\"\",
        \"cta_title\":\"\",
        \"cta_content\":\"\"
        }


        SECTION REQUIREMENTS — FOLLOW EXACTLY:

        === HERO ===
        hero_title: Unique headline. DO NOT start with 'Best', 'Top', 'Expert', 'Leading'.
        hero_subtitle: Short tagline. DO NOT use 'Trusted', 'Reliable', 'Premier', 'Partner'.
          Use a city-specific angle: e.g. a local challenge, trend, or opportunity.
        hero_description: 2-3 sentence local introduction. DO NOT start with 'At Vispan Solutions',
          'We help', 'We specialize'. Must reference a specific local market condition.

        === BENEFITS DESCRIPTION ===
        Short paragraph (2-3 sentences) on why local businesses choose this service.
        DO NOT start with 'Discover how', 'Learn why', 'Find out why'.
        Focus on a specific challenge businesses in [city] face.

        === WHY CHOOSE DESCRIPTION ===
        One sentence. DO NOT use 'Vispan Solutions is the trusted choice',
          'Vispan Solutions is the right partner', 'Choose Vispan Solutions for'.
        Must mention a concrete differentiator (process, team, results, methodology).

        === SERVICES DESCRIPTION ===
        One sentence framing the service range.
        DO NOT start with 'Comprehensive', 'Full range of', 'Wide variety of'.
        Use an action-oriented framing.

        === SERVICES (array) ===
        4-6 items with unique titles and descriptions.
        Each title must be a specific service name, not generic.
        Descriptions must be 2-3 sentences, city-specific.

        === BENEFITS (array) ===
        4 items. Title and description format.
        Each benefit must tie to a [city]-specific advantage.
        DO NOT use generic benefits like 'Experienced Team', 'Proven Results'.

        === WHY CHOOSE (array) ===
        4 trust-building points.
        Each must reference a specific capability or process, not a generic claim.

        === TECHNOLOGY ===
        4-6 platform names.
        technology_description: 2-3 sentences on how the tech stack benefits [city] businesses.
        DO NOT use 'cutting-edge', 'state-of-the-art', 'industry-leading'.

        === FAQ ===
        5 questions. Each Q&A must be city-specific.
        DO NOT reuse question formats from previous pages.
        Vary question structure between pages (some how, some what, some why).

        === STATS ===
        4 items with number and label.
        Vary the stat categories between pages.
        DO NOT reuse the same stat labels across pages.

        === TESTIMONIALS ===
        2 items with name, role, content.
        Use different names, roles, and industries each time.
        Content must feel authentic and specific, not generic praise.

        === DIFFERENCE CONTENT ===
        2-3 sentence paragraph.
        DO NOT start with 'At Vispan Solutions, we combine', 'We stand out',
          'What sets us apart'.
        Use a fresh angle: process, methodology, local commitment, team expertise.

        === CTA ===
        cta_title: Unique closing headline. DO NOT use 'Grow Your Business', 'Get Started Today',
          'Ready to', 'Partner with'.
        cta_content: 2 sentences. DO NOT start with 'Contact us', 'Reach out'.
        Must include Vispan Solutions naturally.


        BRAND RULES:
        - Vispan Solutions must appear in: hero_description, benefits_description,
          why_choose_description, difference_content, cta section.
        - Vary WHERE Vispan Solutions appears in each sentence (not always at the start).
        - Do not put Vispan Solutions in hero_title or hero_subtitle unless it fits naturally.

        OUTPUT: ONLY valid JSON. No markdown. No code fences. No explanations before or after.";





        /*
        Call OpenAI
        */


        $response = $this->provider->generate(

            $prompt

        );





        error_log(

            "OPENAI RAW RESPONSE: ".print_r($response,true)

        );





        /*
        Clean OpenAI JSON response
        */


        $response = trim($response);


        /*
        Remove markdown JSON wrapper
        */

        $response = preg_replace(
            '/^```json|```$/',
            '',
            $response
        );


        $response = trim($response);



        $content = json_decode(

            $response,

            true

        );


        /*
        Fallback
        */


        if(
            !$content ||
            !is_array($content)
        )
        {


            error_log(
                "OPENAI FAILED USING FALLBACK"
            );



            $content = array(
                'hero_title' => 'Best '.$data['service'].' in '.$data['city'].', '.$data['state'],
                'hero_subtitle' => 'Trusted '.$data['service'].' Partner',
                'hero_description' => 'Vispan Solutions helps businesses in '.$data['city'].' grow through customized '.$data['service'].' strategies.',
                'benefits_description' => 'Discover how our '.$data['service'].' expertise delivers real results for businesses in '.$data['city'].'.',
                'why_choose_description' => 'Vispan Solutions is the trusted choice for businesses in '.$data['city'].'.',
                'services_description' => 'Comprehensive '.$data['service'].' solutions tailored for '.$data['city'].' businesses.',
                'testimonials_description' => 'Hear from our clients in '.$data['city'].' and beyond.',
                'faq_description' => 'Common questions about our '.$data['service'].' services in '.$data['city'].'.',
                'technology_description' => 'We use cutting-edge tools to deliver measurable results.',
                'difference_content' => 'Vispan Solutions combines local expertise with data-driven strategies to deliver exceptional results for businesses in '.$data['city'].'.',
                'services' => array(
                    array('title' => $data['service'], 'description' => 'Professional solutions designed for local businesses.'),
                    array('title' => 'Custom Strategy', 'description' => 'Strategies created according to your business goals.')
                ),
                'benefits' => array(
                    array('title' => 'Experienced Team', 'description' => 'Professional guidance and proven methods.')
                ),
                'why_choose' => array(
                    array('title' => 'Local Expertise', 'description' => 'Understanding of local market requirements.')
                ),
                'technology' => array('Google Analytics', 'SEMrush', 'HubSpot'),
                'faq' => array(
                    array('question' => 'What services does Vispan Solutions provide?', 'answer' => 'We provide customized '.$data['service'].' solutions.')
                ),
                'stats' => array(
                    array('number' => '100+', 'label' => 'Clients Served'),
                    array('number' => '5+', 'label' => 'Years Experience'),
                    array('number' => '300+', 'label' => 'Projects Delivered'),
                    array('number' => '20+', 'label' => 'Cities Covered')
                ),
                'testimonials' => array(
                    array('name' => 'Client Name', 'role' => 'Business Owner', 'content' => 'Vispan Solutions delivered exceptional results for our business.')
                ),
                'cta_title' => 'Grow Your Business With Vispan Solutions',
                'cta_content' => 'Contact Vispan Solutions today for customized '.$data['service'].' solutions.'
            );


        }





        /*
        Save Generated Content
        */


        /*
        AI Quality Check — log only, never blocks page creation
        */
        
        
        $quality = $this->quality_checker->check(
        
            $content,
        
            $data
        
        );
        
        error_log(
            "AI QUALITY SCORE: ".$quality['score']
        );

        if(!$quality['approved'])
        {
            error_log(
                "AI CONTENT BELOW THRESHOLD (SCORE: ".$quality['score'].") — PROCEEDING WITH AS-IS CONTENT"
            );
        }


        /*
        Check duplicate content hash
        */


        $content_hash = md5(
            json_encode($content)
        );


        if(
            $this->database->content_hash_exists(
                $content_hash
            )
        )
        {

            error_log(
                "DUPLICATE CONTENT HASH FOUND. REJECTING."
            );


            return array();

        }
        
        
        
        
        /*
        Save Final AI Content
        */
        
        
        $result = $this->database->save_content(
        
            $data,
        
            $content
        
        );


        error_log(

            "SAVE RESULT: ".print_r($result,true)

        );





        return $content;


    }


}
<?php

defined('ABSPATH') || exit;


class VCPG_AI_Quality_Checker
{


    private $provider;



    public function __construct($provider)
    {

        $this->provider = $provider;

    }





    public function check($content,$data)
    {


        $score = 100;

        $issues = array();



        $full_content = strtolower(
            wp_strip_all_tags(
                json_encode($content)
            )
        );



        $service = strtolower(
            $data['service']
        );


        $city = strtolower(
            $data['city']
        );




        /*
        Keyword Check
        */


        if(
            strpos(
                $full_content,
                $service
            ) === false
        )
        {

            $score -= 10;

            $issues[] = 'Service keyword missing';

        }




        /*
        City Check
        */


        if(
            strpos(
                $full_content,
                $city
            ) === false
        )
        {

            $score -= 15;

            $issues[] = 'City mention missing';

        }





        /*
        Forbidden Patterns Check
        */

        $forbidden_starts = array(
            'hero_subtitle' => array('trusted', 'reliable', 'premier', 'partner'),
            'hero_description' => array('at vispan solutions', 'we help', 'we specialize'),
            'benefits_description' => array('discover how', 'learn why', 'find out why'),
            'why_choose_description' => array('vispan solutions is the trusted choice', 'vispan solutions is the right partner', 'choose vispan solutions for'),
            'services_description' => array('comprehensive', 'full range of', 'wide variety of'),
            'technology_description' => array('cutting-edge', 'state-of-the-art', 'industry-leading'),
            'difference_content' => array('at vispan solutions, we combine', 'we stand out', 'what sets us apart'),
            'cta_title' => array('grow your business', 'get started today', 'ready to', 'partner with'),
            'cta_content' => array('contact us', 'reach out')
        );

        foreach($forbidden_starts as $field => $patterns)
        {
            if(isset($content[$field]) && is_string($content[$field]))
            {
                $lower = strtolower($content[$field]);
                foreach($patterns as $pattern)
                {
                    if(strpos($lower, $pattern) !== false)
                    {
                        $score -= 5;
                        $issues[] = $field . ' uses forbidden pattern: ' . $pattern;
                        break;
                    }
                }
            }
        }


        /*
        Hero Content
        */


        if(isset($content['hero_description']) && is_string($content['hero_description']))
        {


            $hero_words = str_word_count(
                strip_tags(
                    $content['hero_description']
                )
            );



            if($hero_words < 20)
            {

                $score -= 5;

                $issues[] = 'Hero content too short';

            }




            if(
                strpos(
                    strtolower($content['hero_description']),
                    $service
                ) === false
            )
            {

                $score -= 5;

                $issues[] = 'Keyword missing in hero section';

            }


        }





        /*
        About Content Check
        */


        if(isset($content['about_content']) && is_string($content['about_content']))
        {

            $about_words = str_word_count(strip_tags($content['about_content']));

            if($about_words < 100)
            {

                $score -= 5;

                $issues[] = 'About content too short ('.$about_words.' words)';

            }

        }


        /*
        Local Insight Check
        */


        if(isset($content['local_insight']) && is_string($content['local_insight']))
        {

            $local_words = str_word_count(strip_tags($content['local_insight']));

            if($local_words < 100)
            {

                $score -= 5;

                $issues[] = 'Local insight too short ('.$local_words.' words)';

            }

        }


        /*
        Services Structure Check
        */


        if(
            empty($content['services'])
            ||
            !is_array($content['services'])
        )
        {

            $score -= 5;

            $issues[] = 'Services section missing';

        }
        else
        {

            if(
                count($content['services']) < 4
            )
            {

                $score -= 5;

                $issues[] = 'Not enough service cards';

            }

        }




        /*
        FAQ Check
        */


        if(
            isset($content['faq'])
        )
        {


            if(
                !is_array($content['faq'])
            )
            {

                $score -= 5;

                $issues[]='FAQ format incorrect';

            }
            else
            {


                if(
                    count($content['faq']) < 5
                )
                {

                    $score -= 5;

                    $issues[]='FAQ needs improvement';

                }

            }


}







        /*
        Local SEO Signals
        */


        $local_terms = array(

            'local',
            'near',
            'business',
            'area',
            'community'

        );



        $local_found = 0;



        foreach($local_terms as $term)
        {

            if(
                strpos(
                    $full_content,
                    $term
                ) !== false
            )
            {

                $local_found++;

            }

        }



        if($local_found < 2)
        {

            $score -= 5;

            $issues[] = 'Weak local SEO signals';

        }







        /*
        Content Repetition Check
        */


        $words = str_word_count(
            $full_content,
            1
        );


        $duplicates = array_count_values(
            $words
        );



        foreach($duplicates as $word=>$count)
        {


           $ignore_words = array(
                'vispan',
                'solutions',
                strtolower($data['city']),
                strtolower($data['service'])
            );
            
            
            if(
                strlen($word) > 6 &&
                $count > 15 &&
                !in_array($word,$ignore_words)
            )
            {

                $score -= 5;

                $issues[] = 'Keyword repetition detected';

                break;

            }


        }







        /*
        Total Word Count Check (2000-3000 target)
        */

        $all_text = '';
        foreach($content as $key => $value)
        {
            if(is_string($value))
            {
                $all_text .= ' ' . $value;
            }
            elseif(is_array($value))
            {
                foreach($value as $item)
                {
                    if(is_string($item))
                    {
                        $all_text .= ' ' . $item;
                    }
                    elseif(is_array($item))
                    {
                        foreach($item as $sub)
                        {
                            if(is_string($sub))
                            {
                                $all_text .= ' ' . $sub;
                            }
                        }
                    }
                }
            }
        }

        $total_words = str_word_count(
            strip_tags($all_text)
        );

        error_log(
            "TOTAL WORD COUNT: ".$total_words
        );

        if($total_words < 1500)
        {
            $score -= 20;
            $issues[] = 'Content too short: '.$total_words.' words (target 2000-3000)';
        }
        elseif($total_words < 2000)
        {
            $score -= 10;
            $issues[] = 'Content below target: '.$total_words.' words (target 2000-3000)';
        }


        /*
        Keyword Diversity Check
        */

        $keyword_categories = array(
            'trust' => array('best', 'top', 'leading', 'trusted', 'premium', 'certified', 'experienced', 'reliable', 'proven', 'award-winning', 'strategic', 'specialized', 'recognized', 'established'),
            'growth' => array('growth', 'scale', 'expand', 'increase', 'boost', 'improve', 'generate', 'drive', 'maximize', 'accelerate', 'revenue', 'results'),
            'marketing' => array('digital', 'online', 'performance', 'brand', 'strategy', 'conversion', 'analytics', 'engagement', 'optimization'),
            'seo' => array('seo', 'search', 'ranking', 'visibility', 'traffic', 'organic', 'google', 'authority', 'content'),
            'audience' => array('local', 'small', 'business', 'enterprise', 'startup', 'service')
        );

        $keywords_found = 0;
        $keyword_total = 0;

        foreach($keyword_categories as $category => $keywords)
        {
            $cat_found = 0;
            foreach($keywords as $keyword)
            {
                if(strpos($full_content, $keyword) !== false)
                {
                    $cat_found++;
                }
            }
            $keywords_found += $cat_found;
            $keyword_total += count($keywords);

            if($cat_found < 2)
            {
                $score -= 3;
                $issues[] = 'Missing ' . $category . ' keywords (found ' . $cat_found . '/' . count($keywords) . ')';
            }
        }

        error_log(
            "KEYWORDS FOUND: ".$keywords_found."/".$keyword_total
        );


        if($score < 0)
        {

            $score = 0;

        }





        error_log(
            "AI QUALITY SCORE: ".$score
        );



        error_log(
            "AI QUALITY ISSUES: ".print_r($issues,true)
        );







        return array(

            'approved'=>$score >= 85,

            'score'=>$score,

            'issues'=>$score >= 85 ? array() : $issues,

            'content'=>$content

        );


    }








    public function improve($content,$data,$issues)
    {


        $prompt = '

        Improve this local SEO page content. Fix the specific problems listed below.
        Every section must use DIFFERENT wording from what was in the original.
        Do not just add words — rewrite problem sections with fresh language.

        Service:
        '.$data['service'].'

        City:
        '.$data['city'].'

        State:
        '.(isset($data['state']) ? $data['state'] : '').'

        Country:
        '.$data['country'].'


        Problems to fix:

        '.implode(
            "\n",
            $issues
        ).'


        Current Content:

        '.json_encode($content).'


        SECTION-SPECIFIC AVOID RULES:

        - hero_subtitle: DO NOT use "Trusted", "Reliable", "Premier", "Partner"
        - hero_description: DO NOT start with "At Vispan Solutions", "We help", "We specialize"
        - benefits_description: DO NOT start with "Discover how", "Learn why", "Find out why"
        - why_choose_description: DO NOT use "trusted choice", "right partner"
        - services_description: DO NOT start with "Comprehensive", "Full range of"
        - technology_description: DO NOT use "cutting-edge", "state-of-the-art", "industry-leading"
        - difference_content: DO NOT start with "At Vispan Solutions, we", "We stand out", "What sets us apart"
        - cta_title: DO NOT use "Grow Your Business", "Get Started Today", "Ready to"
        - cta_content: DO NOT start with "Contact us", "Reach out"

        Requirements:

        - Return ONLY valid JSON
        - No markdown
        - No ```json
        - Keep same fields and structure
        - FAQ must remain an array of question and answer objects.
        - Services must remain an array of title and description objects.
        - Benefits must remain an array of title and description objects.
        - Why Choose must remain an array of title and description objects.
        - Stats must remain an array of number and label objects.
        - Testimonials must remain an array of name, role, and content objects.
        - Add local SEO relevance
        - Mention city naturally
        - Increase content depth
        - Avoid keyword stuffing
        - Rewrite problem sections completely — do not keep original phrasing

        {
        "hero_title":"",
        "hero_subtitle":"",
        "hero_description":"",
        "benefits_description":"",
        "why_choose_description":"",
        "services_description":"",
        "testimonials_description":"",
        "faq_description":"",
        "technology_description":"",
        "services":[],
        "benefits":[],
        "why_choose":[],
        "technology":[],
        "faq":[],
        "stats":[],
        "testimonials":[],
        "difference_content":"",
        "cta_title":"",
        "cta_content":""
        }

        ';





        $response = $this->provider->generate(
            $prompt
        );





        error_log(
            "AI IMPROVEMENT RESPONSE: ".$response
        );





        $response = str_replace(
            array(
                '```json',
                '```'
            ),
            '',
            $response
        );





        $improved = json_decode(
            trim($response),
            true
        );





        if(
            $improved &&
            is_array($improved)
        )
        {

            return $this->normalize_content(
                $improved
            );

        }




        return $this->normalize_content(
            $content
        );


    }







    private function normalize_content($content)
    {


       /*
        FAQ Array
        */


        if(
            isset($content['faq'])
            &&
            is_array($content['faq'])
        )
        {


            $faq = '';



            foreach($content['faq'] as $item)
            {
        
        
                if(isset($item['question']))
                {
            
                    $faq .= '<p><strong>';
                    $faq .= $item['question'];
                    $faq .= '</strong></p>';

                }



                if(isset($item['answer']))
                {
            
                    $faq .= '<p>';
                    $faq .= $item['answer'];
                    $faq .= '</p>';

                }


            }


            $content['faq'] = $faq;


        }



        /*
        Service List Conversion
        */


        if(
            isset($content['service_list'])
            &&
            is_string($content['service_list'])
        )
        {


            if(
                strpos(
                    $content['service_list'],
                    '<ul>'
                ) === false
            )
            {


                $items = explode(
                    ',',
                    $content['service_list']
                );



                $html = '<ul>';



                foreach($items as $item)
                {

                    $html .= '<li>';
                    $html .= trim($item);
                    $html .= '</li>';

                }


                $html .= '</ul>';



                $content['service_list'] = $html;


            }


        }





        return $content;


    }



}
<?php

defined('ABSPATH') || exit;


class VCPG_SEO_Generator
{


    public function generate($data)
    {
        $city = isset($data['city']) ? $data['city'] : '';
        $state = isset($data['state']) ? $data['state'] : '';
        $service = isset($data['service']) ? $data['service'] : '';

        $location = $city;
        if(!empty($state))
        {
            $location .= ', ' . $state;
        }

        $meta_title = $service . ' in ' . $location . ' | Vispan Solutions';
        if(isset($data['page_title']) && !empty($data['page_title']))
        {
            $meta_title = wp_strip_all_tags($data['page_title']) . ' | Vispan Solutions';
        }

        return array(
            'meta_title' => $meta_title,

            'meta_description' =>
                'Looking for ' . $service . ' in ' . $location .
                '? Vispan Solutions helps businesses grow with professional local solutions.',

            'focus_keyword' =>
                $service . ' ' . $city . ' ' . $state
        );
    }


}

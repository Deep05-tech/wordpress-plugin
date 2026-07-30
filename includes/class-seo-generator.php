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

        return array(
            'meta_title' =>
                $service . ' in ' . $location . ' | Vispan Solutions',

            'meta_description' =>
                'Looking for ' . $service . ' in ' . $location .
                '? Vispan Solutions helps businesses grow with professional local solutions.',

            'focus_keyword' =>
                $service . ' ' . $city . ' ' . $state
        );
    }


}

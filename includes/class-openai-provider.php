<?php

defined('ABSPATH') || exit;


class VCPG_OpenAI_Provider
{


    private $api_key;


    public function __construct()
    {

        /*
        SECURITY FIX: the previous version had a live OpenAI API key
        hardcoded in plaintext right here, committed into a file you
        zip up and hand around. That key must be revoked in your
        OpenAI dashboard regardless of this fix — assume it's already
        leaked.

        Now it's read from a wp-config.php constant first (never
        touches the DB or a zip file), falling back to a WP option
        you can set from PHP once (e.g. via WP-CLI or an admin
        screen) if you don't have wp-config.php access:

            define('VCPG_OPENAI_API_KEY', 'sk-...');   // wp-config.php

        or

            update_option('vcpg_openai_api_key', 'sk-...');
        */

        if(defined('VCPG_OPENAI_API_KEY'))
        {

            $this->api_key = VCPG_OPENAI_API_KEY;

        }
        else
        {

            $this->api_key = get_option('vcpg_openai_api_key', '');

        }

    }






    public function is_configured()
    {
        return !empty($this->api_key);
    }


    /*
    Make a tiny real API call to verify the key works and the live server can
    reach OpenAI. Returns an array with 'ok' (bool) and 'msg' (string).
    */

    public function test_connection()
    {

        if(empty($this->api_key))
        {
            return array(
                'ok' => false,
                'msg' => 'API key is not configured. Set VCPG_OPENAI_API_KEY in wp-config.php or save a key below.'
            );
        }

        $response = $this->generate(
            'Reply with only this exact JSON: {"ok":true}'
        );

        if($response === false || !is_string($response))
        {
            return array(
                'ok' => false,
                'msg' => 'The OpenAI request failed on the live server (see debug.log for OPENAI ERROR). Usually: key invalid, or the server cannot reach api.openai.com.'
            );
        }

        return array(
            'ok' => true,
            'msg' => 'Connection successful. OpenAI responded: ' . substr(trim($response), 0, 80)
        );
    }



    public function generate($prompt)
    {

        if(empty($this->api_key))
        {

            error_log(
                'VCPG: No OpenAI API key configured. Set VCPG_OPENAI_API_KEY in wp-config.php or the vcpg_openai_api_key option.'
            );

            return false;

        }


        $response = wp_remote_post(

            'https://api.openai.com/v1/chat/completions',

            array(

                'headers' => array(

                    'Content-Type' => 'application/json',

                    'Authorization' => 'Bearer ' . $this->api_key

                ),


                'body' => json_encode(

                    array(

                        'model' => 'gpt-4.1-mini',

                        'messages' => array(

                            array(

                                'role' => 'system',

                                'content' =>
                                'You are an expert SEO content writer. Return only valid JSON.'

                            ),


                            array(

                                'role' => 'user',

                                'content' => $prompt

                            )

                        ),


                        'temperature' => 0.8,

                        'max_tokens' => 16384,

                        'response_format' => array('type' => 'json_object')

                    )

                ),


                'timeout' => 120

            )

        );





        if(is_wp_error($response))
        {

            error_log(
                'OPENAI ERROR: '.$response->get_error_message()
            );


            return false;

        }





        $body = json_decode(

            wp_remote_retrieve_body($response),

            true

        );





        if(isset($body['choices'][0]['message']['content']))
        {


            return $body['choices'][0]['message']['content'];


        }





        error_log(
            'OPENAI RESPONSE ERROR: '.print_r($body,true)
        );


        return false;


    }


}

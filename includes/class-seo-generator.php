<?php

defined('ABSPATH') || exit;

class VCPG_SEO_Generator
{
    public function generate($data)
    {
        $city = isset($data['city']) ? trim($data['city']) : '';
        $state = isset($data['state']) ? trim($data['state']) : '';
        $service = isset($data['service']) ? trim($data['service']) : '';

        $location = $city;
        if (!empty($state)) {
            $location .= ', ' . $state;
        }

        // Use AI-generated title if present and valid, otherwise fallback
        $meta_title = '';
        if (isset($data['meta_title']) && !empty($data['meta_title'])) {
            $meta_title = wp_strip_all_tags($data['meta_title']);
        } elseif (isset($data['page_title']) && !empty($data['page_title'])) {
            $meta_title = wp_strip_all_tags($data['page_title']);
        }

        if (empty($meta_title)) {
            $meta_title = $service . ' in ' . $location;
        }

        $meta_title = $this->adjust_title_length($meta_title, $service, $location);

        // Use AI-generated description if present, otherwise fallback
        $meta_desc = '';
        if (isset($data['meta_description']) && !empty($data['meta_description'])) {
            $meta_desc = wp_strip_all_tags($data['meta_description']);
        }

        if (empty($meta_desc)) {
            $meta_desc = 'Looking for professional ' . $service . ' in ' . $location . '? Vispan Solutions helps businesses scale operations and grow organic traffic.';
        }

        $meta_desc = $this->adjust_description_length($meta_desc, $service, $location);

        $focus_keyword = $service;
        if (!empty($city)) {
            $focus_keyword .= ' ' . $city;
        }

        return array(
            'meta_title'       => $meta_title,
            'meta_description' => $meta_desc,
            'focus_keyword'    => $focus_keyword
        );
    }

    private function adjust_title_length($title, $service, $location)
    {
        $title = trim(preg_replace('/\s+/', ' ', $title));
        $len = mb_strlen($title);

        if ($len >= 50 && $len <= 60) {
            return $title;
        }

        if ($len < 50) {
            // Append brand if it fits
            if (mb_strlen($title . ' | Vispan') <= 60) {
                $title .= ' | Vispan';
            }
            if (mb_strlen($title) < 50 && mb_strlen($title . ' Solutions') <= 60) {
                $title .= ' Solutions';
            }
            // If still too short, add local mod
            if (mb_strlen($title) < 50 && !empty($location) && stripos($title, $location) === false) {
                $title = $title . ' in ' . $location;
            }
            // If still too short, pad with "Best " or "Top " prefix
            if (mb_strlen($title) < 50) {
                $title = 'Top ' . $title;
            }
            if (mb_strlen($title) < 50) {
                $title = 'Best ' . $title . ' Agency';
            }
        }

        // If it's too long, truncate or clean
        if (mb_strlen($title) > 60) {
            // Remove brand if present to shorten
            $title = str_ireplace(' | Vispan Solutions', '', $title);
            $title = str_ireplace(' | Vispan', '', $title);
            $title = str_ireplace(' Vispan Solutions', '', $title);
            $title = trim($title);

            if (mb_strlen($title) > 60) {
                $title = mb_substr($title, 0, 57) . '...';
            }
        }

        // Final safety guard: force trim to 60 characters
        if (mb_strlen($title) > 60) {
            $title = mb_substr($title, 0, 60);
        }
        // Force minimum length to 50
        if (mb_strlen($title) < 50) {
            $title = str_pad($title, 50, " ");
        }

        return $title;
    }

    private function adjust_description_length($desc, $service, $location)
    {
        $desc = trim(preg_replace('/\s+/', ' ', $desc));
        $len = mb_strlen($desc);

        if ($len >= 120 && $len <= 155) {
            return $desc;
        }

        if ($len < 120) {
            // Add a standard CTA message to pad the description
            $padding_ctas = array(
                ' Contact our team of experts today for a free custom audit and strategy session.',
                ' Request your free audit online now.',
                ' Get in touch today to discuss your project growth goals.',
                ' Elevate your brand visibility and acquire more local clients with our proven digital strategy.'
            );
            foreach ($padding_ctas as $cta) {
                if (mb_strlen($desc . $cta) <= 155) {
                    $desc .= $cta;
                }
                if (mb_strlen($desc) >= 120) {
                    break;
                }
            }
            if (mb_strlen($desc) < 120) {
                $desc = str_pad($desc, 120, " ");
            }
        }

        if (mb_strlen($desc) > 155) {
            // Attempt to cut at the end of a sentence
            $sentences = preg_split('/(?<=[.!?])\s+/', $desc);
            $new_desc = '';
            foreach ($sentences as $sentence) {
                if (mb_strlen($new_desc . ' ' . $sentence) <= 152) {
                    $new_desc = trim($new_desc . ' ' . $sentence);
                } else {
                    break;
                }
            }
            if (!empty($new_desc) && mb_strlen($new_desc) >= 120) {
                $desc = $new_desc;
            } else {
                $desc = mb_substr($desc, 0, 152) . '...';
            }
        }

        // Final safety guard: force length limits
        if (mb_strlen($desc) > 155) {
            $desc = mb_substr($desc, 0, 155);
        }
        if (mb_strlen($desc) < 120) {
            $desc = str_pad($desc, 120, " ");
        }

        return $desc;
    }
}

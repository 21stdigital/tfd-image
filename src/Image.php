<?php

namespace TFD;

class Image extends \WP_Model
{
    public static $landscape = 'landscape';
    public static $portrait = 'portrait';
    public static $square = 'square';

    public $imageSizeGroup;

    public $postType = 'attachment';
    public $image_src;
    public $drawType = '';
    public $focalPoint;

    public $virtual = [
        'name',
        'alt',
        'caption',
        'description',
        'href',
        'src',
        'width',
        'height',
        'orientation',
        'fpx',
        'fpy',
        'mimeType',
        'originalSrc'
    ];

    /**
     * Create a new instace with data
     *
     * @param array $insert
     * @return void
     */
    public function __construct(array $insert = [])
    {
        parent::__construct($insert);
        $this->imageSizeGroup = 'poster';
    }

    protected function boot()
    {
        if (!empty($this->ID)) {
            $this->image_src = $this->getSrc($this->ID, 'full');
            $this->focalPoint = $this->getFocalPoint();
        }
        parent::boot();
    }


    /**
     * Check if the value is valid.
     *
     * @param string $key
     * @param string $value
     * @return bool
     */
    private static function validValue($key = '', $value = '')
    {
        if (('w' === $key || 'h' === $key) && empty($value)) {
            return false;
        }
        return true;
    }

    /**
     * Build a Cloudinary transformation slug from arguments.
     *
     * @param  array $args
     * @return string
     */
    public static function buildTransformationSlug($args = array())
    {
        if (empty($args)) {
            return '';
        }

        $cloudinary_params = array(
            'angle'                => 'a',
            'aspect_ratio'         => 'ar',
            'background'           => 'b',
            'border'               => 'bo',
            'crop'                 => 'c',
            'color'                => 'co',
            'dpr'                  => 'dpr',
            'duration'             => 'du',
            'effect'               => 'e',
            'end_offset'           => 'eo',
            'flags'                => 'fl',
            'height'               => 'h',
            'overlay'              => 'l',
            'opacity'              => 'o',
            'quality'              => 'q',
            'radius'               => 'r',
            'start_offset'         => 'so',
            'named_transformation' => 't',
            'underlay'             => 'u',
            'video_codec'          => 'vc',
            'width'                => 'w',
            'x'                    => 'x',
            'y'                    => 'y',
            'zoom'                 => 'z',
            'audio_codec'          => 'ac',
            'audio_frequency'      => 'af',
            'bit_rate'             => 'br',
            'color_space'          => 'cs',
            'default_image'        => 'd',
            'delay'                => 'dl',
            'density'              => 'dn',
            'fetch_format'         => 'f',
            'gravity'              => 'g',
            'prefix'               => 'p',
            'page'                 => 'pg',
            'video_sampling'       => 'vs',
        );

        $slug = array();
        foreach ($args as $key => $value) {
            if (array_key_exists($key, $cloudinary_params) && self::valid_value($cloudinary_params[$key], $value)) {
                $slug[] = $cloudinary_params[$key] . '_' . $value;
            }
        }
        return implode(',', $slug);
    }


    public static function cloudinaryUrl($originalUrl, $transformations = [])
    {
        if(function_exists('cloudinary_url') ) {
            $cloudName = apply_filters('cloudinary_cloud_name', get_option('cloudinary_cloud_name'));
            $autoMappingFolder = apply_filters('cloudinary_auto_mapping_folder', get_option('cloudinary_auto_mapping_folder'));
            $uploadDir = wp_upload_dir();
            $uploadUrl = apply_filters('cloudinary_upload_url', $uploadDir['baseurl']);
            $urls = apply_filters('cloudinary_urls', get_option('cloudinary_urls'));
            $urls = apply_filters('cloudinary_urls', ['https://res.cloudinary.com']);
            $domain = is_array($urls) && count($urls) ? $urls[0] : 'https://res.cloudinary.com';

            // Validate URL.
            if (0 !== strpos($originalUrl, $uploadUrl)) {
                return $originalUrl;
            }
            $url = $domain . '/' . $cloudName;


            // Transformations.
            if (!empty($transformations) ) {
                $transformations_slug = self::buildTransformationSlug($transformations);
                if (!empty($transformations_slug)) {
                    $url .= '/' . $transformations_slug;
                }
            }

            $url .= '/' . $autoMappingFolder . str_replace($uploadUrl, '', $originalUrl);

            return $url;
        }

        return $originalUrl;
    }

    protected function getSrc($id, $size)
    {
        $args = [];
        if (function_exists('cloudinary_url')) {
            $image = wp_get_attachment_image_src($id, $size);
            $image_src = cloudinary_url($id);
            if (isset($image) && !empty($image) && $image_src) {
                return [
                    'src' => $image_src,
                    'width' => $image[1],
                    'height' => $image[2],
                ];
            }
            return null;
        }

        if (function_exists('fly_get_attachment_image_src')) {
            $size = [$args['transform']['width'], $args['transform']['height']];
            $crop = true;
            $image = fly_get_attachment_image_src($id, $size, $crop);

            if (isset($image) && !empty($image)) {
                return [
                    'src' => $image['src'],
                    'width' => $image['width'],
                    'height' => $image['height'],
                ];
            }
        }

        $image = wp_get_attachment_image_src($id, $size);
        if (isset($image) && !empty($image)) {
            return [
                'src' => $image[0],
                'width' => $image[1],
                'height' => $image[2],
            ];
        }

        return null;
    }

    public function _getMimeType()
    {
        return get_post_mime_type($this->ID);
    }

    public function _getAlt()
    {
        return $this->getMeta('_wp_attachment_image_alt');
    }

    public function _getName()
    {
        return $this->title;
    }

    public function _getCaption()
    {
        return $this->_post->post_excerpt ?: $this->_post->post_content;
    }

    public function _getDescription()
    {
        return $this->_post->post_content;
    }

    public function _getHref()
    {
        return get_permalink($this->ID);
    }

    public function _getSrc()
    {
        if (!isset($this->image_src)) {
            $this->image_src = $this->getSrc($this->ID, $this->imageSize);
        }
        return $this->image_src['src'];
    }


    public function _getOriginalSrc()
    {
        return wp_get_attachment_url($this->ID);
        // $image = wp_get_attachment_image_src($this->ID, 'full');
        // if (isset($image) && !empty($image) && is_array($image)) {
        //     $src = $image[0];
        //     if (function_exists('cloudinary_url')) {
        //         $src = str_replace(Config::get('CLOUDINARY_URL'), Config::get('WP_HOME'), $src);
        //         $src = str_replace(Config::get('CLOUDINARY_CLOUD_NAME').'/', '', $src);
        //         $src = str_replace(Config::get('CLOUDINARY_AUTO_MAPPING_FOLDER'), 'app/uploads', $src);
        //     }
        //     return $src;
        // }
        // return null;
    }

    public function _getWidth()
    {
        if (!isset($this->image_src)) {
            $this->image_src = $this->getSrc($this->ID, $this->imageSize);
        }
        return $this->image_src['width'];
    }

    public function _getHeight()
    {
        if (!isset($this->image_src)) {
            $this->image_src = $this->getSrc($this->ID, $this->imageSize);
        }
        return $this->image_src['height'];
    }

    public function _getOrientation()
    {
        if ($this->width < $this->height) {
            return 'portrait';
        } elseif ($this->width == $this->height) {
            return 'square';
        }
        return 'landscape';
    }

    public function _getFpx()
    {
        return $this->focalPoint['x'];
    }

    public function _getFpy()
    {
        return $this->focalPoint['y'];
    }


    public function getFocalPoint()
    {
        $focalPoint = $this->getMeta('theiaSmartThumbnails_position');

        $x = isset($focalPoint) && !empty($focalPoint) ? $focalPoint[0] : .5;
        $y = isset($focalPoint) && !empty($focalPoint) ? $focalPoint[1] : .5;

        return [
            'x' => $x,
            'y' => $y,
            'bg_pos' => $x * 100 . '%' . $y * 100 . '%',
            'bg_pos_x' => $x * 100 . '%',
            'bg_pos_y' => $y * 100 . '%',
        ];
    }

    // ----------------------------------------------------
    // SPECIAL FINDERS
    // ----------------------------------------------------
    /**
     * Find featured image model by it's post ID
     *
     * @param  int $ID
     * @return Object|NULL
     */
    public static function findFeaturedImage($id)
    {
        return has_post_thumbnail($id) ? Image::find((int)get_post_thumbnail_id($id)) : null;
    }



    // ----------------------------------------------------
    // DRAWING
    // ----------------------------------------------------
    public function draw()
    {
        echo "<img src=$this->src>";
    }

    private function getSources($imageSizeGroup = null)
    {
        $imageSizeGroup = $imageSizeGroup ?: $this->imageSizeGroup;
        return $imageSizeGroup->get_sources($this->ID);
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Main class for plugin 'media_ableplayer'
 *
 * @package   media_ableplayer
 * @copyright 2024 Dustin Jeffery
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class media_ableplayer_plugin extends core_media_player_native {
    /** @var array caches supported extensions */
    protected $extensions = null;

    public function embed($urls, $name, $width, $height, $options) {
        global $OUTPUT;
        $mediamanager = core_media_manager::instance();

        $sources = array();
        $arrsources = array();
        $tracks = array();
        $text = null;
        $isaudio = null;
        $hastracks = false;
        $hasposter = false;

        if (array_key_exists(core_media_manager::OPTION_ORIGINAL_TEXT, $options) &&
            preg_match('/^<(video|audio)\b/i', $options[core_media_manager::OPTION_ORIGINAL_TEXT], $matches)) {
            // Original text already had media tag - get some data from it.
            $text = $options[core_media_manager::OPTION_ORIGINAL_TEXT];
            $isaudio = strtolower($matches[1]) === 'audio';
            $hastracks = preg_match('/<track\b/i', $text);
            if ($hastracks) {
                preg_match_all('/(\<track.*?>)/', $text,$originaltracks);
                foreach($originaltracks[1] as $track) {
                    $tracks[] = $track;
                }
            }
            $arrtracks = $tracks;
            $tracks = implode("\n", $tracks);
            $hasposter = self::get_attribute($text, 'poster') !== null;
        }

        // Build list of source tags.
        foreach ($urls as $url) {
            $mimetype = core_media_manager::instance()->get_mimetype($url);
            if ($mimetype === 'video/quicktime' && (core_useragent::is_chrome() || core_useragent::is_edge())) {
                // Set mimetype of quicktime videos to mp4 for Chrome/Edge browsers .
                $mimetype = 'video/mp4';
            }

            $source = html_writer::empty_tag('source', array('src' => $url, 'type' => $mimetype));
            // create array, bypassing html_writer
            $arrsource = [
                'src' => $url,
                'type' => $mimetype
            ];
            if ($mimetype === 'video/mp4') {
                // Better add m4v as first source, it might be a bit more
                // compatible with problematic browsers
                array_unshift($sources, $source);
                // array, bypassing html_writer
                array_unshift($arrsources, $arrsource);
            } else {
                $sources[] = $source;
                $arrsources[] = $arrsource;
            }
        }

        $sources = implode("\n", $sources);
        $title = $this->get_name($name, $urls);
        // Escape title but prevent double escaping.
        $title = s(preg_replace(['/&amp;/', '/&gt;/', '/&lt;/'], ['&', '>', '<'], $title));

        self::pick_video_size($width, $height);
        if (!$height) {
            // Let browser choose height automatically.
            $size = "width=\"$width\"";
        } else {
            $size = "width=\"$width\" height=\"$height\"";
        }

        // We don't want fallback to another player because list_supported_urls() is already smart.
        // Otherwise we could end up with nested <video> tags. Fallback to link only.
        $fallback = self::LINKPLACEHOLDER;

        $templatecontext = [
            'text' => $text,
            'isaudio' => $isaudio,
            'hasposter' => $hasposter,
            'hastracks' => $hastracks,
            'title' => $title,
            'size' => $size,
            'sources' => $sources,
            'arrsources' => $arrsources,
            'tracks' => $tracks,
            'arrtracks' => $arrtracks,
            'fallback' => $fallback
        ];
        
        return $OUTPUT->render_from_template('media_ableplayer/player', $templatecontext);
    }

    public function get_supported_extensions() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        if ($this->extensions === null) {
            // Get extensions set by user in UI config.
            $filetypes = preg_split('/\s*,\s*/',
                strtolower(trim(get_config('media_ableplayer', 'videoextensions') . ',' .
                get_config('media_ableplayer', 'audioextensions'))));

            $this->extensions = file_get_typegroup('extension', $filetypes);
            if ($this->extensions) {
                // Get extensions supported by player.
                $supportedextensions = array_merge(file_get_typegroup('extension', 'html_video'),
                    file_get_typegroup('extension', 'html_audio'), file_get_typegroup('extension', 'media_source'));
                $this->extensions = array_intersect($this->extensions, $supportedextensions);
            }
        }
        return $this->extensions;
    }

    public function list_supported_urls(array $urls, array $options = array()) {
        $extensions = $this->get_supported_extensions();
        $result = array();
        foreach ($urls as $url) {
            $ext = core_media_manager::instance()->get_extension($url);
            if (in_array('.' . $ext, $extensions) && core_useragent::supports_html5($ext)) {
                // Unfortunately html5 video does not handle fallback properly.
                // https://www.w3.org/Bugs/Public/show_bug.cgi?id=10975
                // That means we need to do browser detect and not use html5 on
                // browsers which do not support the given type, otherwise users
                // will not even see the fallback link.
                $result[] = $url;
            }
        }
        return $result;
    }

     /**
     * Utility function that sets width and height to defaults if not specified
     * as a parameter to the function (will be specified either if, (a) the calling
     * code passed it, or (b) the URL included it).
     * @param int $width Width passed to function (updated with final value)
     * @param int $height Height passed to function (updated with final value)
     */
    protected static function pick_video_size(&$width, &$height) {
        global $CFG;
        if (!$width) {
            $width = $CFG->media_default_width;
        }
    }

    /**
     * Default rank
     * @return int
     */
    public function get_rank() {
        return 2000;
    }

    /**
     * Setup page requirements
     * 
     * @param moodle_page $page The page we are going to add requirements to.
     */
    public function setup($page) {
        $page->requires->jquery();
        $page->requires->js('/media/player/ableplayer/build/ableplayer.min.js',true);
        $page->requires->js('/media/player/ableplayer/thirdparty/js.cookie.min.js',true);
        $page->requires->css('/media/player/ableplayer/build/ableplayer.min.css');
    }

}
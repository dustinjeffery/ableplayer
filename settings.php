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
 * Settings file for plugin 'media_ableplayer'
 *
 * @package   media_ableplayer
 * @copyright 2016 Dustin Jeffery
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_filetypes('media_ableplayer/videoextensions',
        new lang_string('videoextensions', 'media_ableplayer'),
        new lang_string('configvideoextensions', 'media_ableplayer'),
        'html_video,media_source,.f4v,.flv',
        array('onlytypes' => array('video', 'web_video', 'html_video', 'media_source'))));

    $settings->add(new admin_setting_filetypes('media_ableplayer/audioextensions',
        new lang_string('audioextensions', 'media_ableplayer'),
        new lang_string('configaudioextensions', 'media_ableplayer'),
        'html_audio',
        array('onlytypes' => array('audio', 'web_audio', 'html_audio'))));
}
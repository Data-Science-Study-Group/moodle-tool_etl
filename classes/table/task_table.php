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
 * Table to display a list of tasks.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\table;

use tool_etl\common\common_interface;
use tool_etl\scheduler;
use tool_etl\task_interface;
use flexible_table;
use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/tablelib.php');

class task_table extends flexible_table {
    /**
     * Autogenerated id.
     *
     * @var int
     */
    private static $autoid = 0;

    /**
     * Constructor
     *
     * @param string|null $id To be used by the table.
     */
    public function __construct($id = null) {
        global $PAGE;

        $id = (is_null($id) ? self::$autoid++ : $id);
        parent::__construct('tool-etl-tasks-' . $id);
        $this->define_baseurl($PAGE->url);
        $this->set_attribute('class', 'generaltable admintable');

        $this->define_columns(array(
                'source',
                'target',
                'processor',
                'schedule',
                'enabled',
                'actions'
            )
        );
        $this->define_headers(array(
                get_string('source', 'tool_etl'),
                get_string('target', 'tool_etl'),
                get_string('processor', 'tool_etl'),
                get_string('schedule', 'tool_etl'),
                get_string('enabled', 'tool_etl'),
                get_string('actions'),
            )
        );

        $this->setup();
    }

    /**
     * Sets the data of the table.
     *
     * @param array $tasks A list of tasks.
     */
    public function display(array $tasks) {
        foreach ($tasks as $task) {
            $this->display_task($task);
        }

        $this->finish_output();
    }

    /**
     * Display a single task.
     *
     * @param \tool_etl\task_interface $task A task object.
     */
    protected function display_task(task_interface $task) {
        if ($task->is_enabled()) {
            $class = '';
            $enabled = get_string('yes');
        } else {
            $class = 'dimmed_text';
            $enabled = get_string('no');
        }

        $this->add_data(array(
            $this->display_task_item($task->source),
            $this->display_task_item($task->target),
            $this->display_task_item($task->processor),
            $this->display_schedule($task->schedule),
            $enabled,
            $this->create_action_buttons($task),
        ), $class);
    }

    /**
     * Render a single task item.
     *
     * @param \tool_etl\common\common_interface $item
     *
     * @return string
     */
    protected function display_task_item(common_interface $item) {
        $name = html_writer::start_tag('strong') . $item->get_name() . html_writer::end_tag('strong');
        $settings = $this->display_settings($item->get_settings_for_display());

        return $name . $settings;
    }

    /**
     * @param \tool_etl\scheduler $schedule
     *
     * @return string
     */
    protected function display_schedule(scheduler $schedule) {
        $output = $schedule->get_formatted();

        if ($next = $schedule->get_scheduled_time()) {
            if ($next < time()) {
                $next = time();
            }

            $output .= '<br />' . userdate($next);
        }

        return $output;
    }

    /**
     * Render settings.
     *
     * @param array $settings
     *
     * @return string
     */
    protected function display_settings(array $settings) {
        $output = html_writer::empty_tag('br');

        foreach ($settings as $name => $value) {
            $output .= html_writer::div($name . ': ' . $value);

        }

        return $output;
    }

    /**
     * Create action buttons for the task row.
     *
     * @param \tool_etl\task_interface $task A task object.
     *
     * @return string
     * @throws \coding_exception
     */
    protected function create_action_buttons(task_interface $task) {
        $buttons = '';

        // Edit button.
        $buttons .= html_writer::link(
            new moodle_url('/admin/tool/etl/history.php',  array('taskid' => $task->id)),
            html_writer::empty_tag('img', [
                'src' => $this->display_icon('t/viewdetails'),
                'alt' => get_string('viewhistory', 'tool_etl'),
                'class' => 'iconsmall',
            ]),
            ['title' => get_string('viewhistory', 'tool_etl')]
        );

        // Enable/disable button.
        $action = $task->is_enabled() ? 'hide' : 'show';
        $title = $task->is_enabled() ? 'disable' : 'enable';

        $buttons .= html_writer::link(
            new moodle_url('/admin/tool/etl/status.php', array('id' => $task->id)),
            html_writer::empty_tag('img', [
                'src' => $this->display_icon('t/' . $action),
                'alt' => get_string($title),
                'class' => 'iconsmall',
            ]),
            ['title' => get_string($title)]
        );

        // Edit button.
        $buttons .= html_writer::link(
            new moodle_url('/admin/tool/etl/index.php',  array('id' => $task->id)),
            html_writer::empty_tag('img', [
                'src' => $this->display_icon('t/edit'),
                'alt' => get_string('edit'),
                'class' => 'iconsmall',
            ]),
            ['title' => get_string('edit')]
        );

        // Delete button.
        $buttons .= html_writer::link(
            new moodle_url('/admin/tool/etl/delete.php',  array('id' => $task->id)),
            html_writer::empty_tag('img', [
                'src' => $this->display_icon('t/delete'),
                'alt' => get_string('delete'),
                'class' => 'iconsmall',
            ]),
            ['title' => get_string('delete')]
        );

        return html_writer::tag('nobr', $buttons);
    }

    /**
     * Display icon depending on Moodle version.
     *
     * @param string $icon Icon name.
     *
     * @return mixed
     */
    protected function display_icon($icon) {
        global $CFG, $OUTPUT;

        if ($CFG->version < 2017051500) {
            $function = 'pix_url';
        } else {
            $function = 'image_url';

        }

        return $OUTPUT->$function($icon);
    }

}

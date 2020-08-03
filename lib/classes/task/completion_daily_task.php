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
 * A scheduled task.
 *
 * @package    core
 * @copyright  2013 onwards Martin Dougiamas  http://dougiamas.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\task;

/**
 * Simple task to run the daily completion cron.
 * @copyright  2013 onwards Martin Dougiamas  http://dougiamas.com.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class completion_daily_task extends scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcompletiondaily', 'admin');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $CFG, $DB;

        if ($CFG->enablecompletion) {
            require_once($CFG->libdir . "/completionlib.php");

            if (debugging()) {
                mtrace('Marking users as started');
            }

            $this->update_completions();
        }
    }

    /**
     * It's purpose is to locate all the active participants of a course with course completion enabled.
     * We also only want the users with no course_completions record as this functions job is to create
     * the missing ones :)
     * We want to record the user's enrolment start time for the course. This gets tricky because there can be
     * multiple enrolment plugins active in a course, hence the possibility of multiple records for each
     * couse/user in the results.
     *
     * @return  bool
     */
    public function update_completions() {
        global $CFG, $DB;
        $sqlroles = '';
            if (!empty($CFG->gradebookroles)) {
                $sqlroles = ' AND ra.roleid IN (' . $CFG->gradebookroles.')';
            }
        $sql = "INSERT INTO {course_completions}
                            (course, userid, timeenrolled, timestarted, reaggregate)
                SELECT c.id AS course, ue.userid AS userid,
                    CASE
                    WHEN MIN(ue.timestart) <> 0
                    THEN MIN(ue.timestart)
                    ELSE :timestarted
                    END,
                    0, :reaggregate
                    FROM {user} u
                INNER JOIN {user_enrolments} ue ON ue.userid = u.id
                INNER JOIN {enrol} e ON e.id = ue.enrolid
                INNER JOIN {course} c ON c.id = e.courseid
                INNER JOIN {context} con ON con.contextlevel = :context AND con.instanceid = c.id
                INNER JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = con.id
                LEFT JOIN {course_completions} crc ON crc.course = c.id AND crc.userid = ue.userid
                    WHERE c.enablecompletion = 1
                    AND crc.id IS NULL
                    AND ue.status = :uestatus
                    AND e.status = :estatus
                    AND ue.timestart < :timestart
                    AND (ue.timeend > :timeend OR ue.timeend = 0)
                    $sqlroles
                GROUP BY c.id, ue.userid";
        $now = time();
        $params = array(
            'timestarted' => $now,
            'reaggregate' => $now,
            'uestatus' => ENROL_USER_ACTIVE,
            'estatus' => ENROL_INSTANCE_ENABLED,
            'timestart' => $now,
            'timeend' => $now,
            'context' => CONTEXT_COURSE
        );
        $DB->execute($sql, $params, true);
    }
}

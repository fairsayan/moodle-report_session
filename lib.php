<?php
/**
 * This file contains public API of session report
 *
 * @package    report
 * @subpackage session
 * @author     Domenico Pontari <fairsayan@gmail.com>
 * @copyright  Institute of Tropical Medicine - Antwerp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the course navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_session_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('report/session:view', $context)) {
        $url = new moodle_url('/report/session/index.php', array('id'=>$course->id));
        $navigation->add(get_string('pluginname', 'report_session'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

/**
 * This function extends the course navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $user
 * @param stdClass $course The course to object for the report
 */
function report_session_extend_navigation_user($navigation, $user, $course) {
    if (report_session_can_access_user_report($user, $course)) {
        $url = new moodle_url('/report/session/user.php', array('userid'=>$user->id, 'id'=>$course->id));
        $navigation->add(get_string('pluginname', 'report_session'), $url);
    }
}

/**
 * Is current user allowed to access this report
 *
 * @private defined in lib.php for performance reasons
 *
 * @param stdClass $user
 * @param stdClass $course
 * @return bool
 */
function report_session_can_access_user_report($user, $course) {
    global $USER;

    $coursecontext = context_course::instance($course->id);
    $personalcontext = context_user::instance($user->id);

    if (has_capability('report/session:view', $coursecontext)) {
        return true;
    }

    if (has_capability('moodle/user:viewuseractivitiesreport', $personalcontext)) {
        if ($course->showreports and (is_viewing($coursecontext, $user) or is_enrolled($coursecontext, $user))) {
            return true;
        }

    } else if ($user->id == $USER->id) {
        if ($course->showreports and (is_viewing($coursecontext, $USER) or is_enrolled($coursecontext, $USER))) {
            return true;
        }
    }

    return false;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array
 *//*
function report_session_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = array(
        '*'                    => get_string('page-x', 'pagetype'),
        'report-*'             => get_string('page-report-x', 'pagetype'),
        'report-session-*'     => get_string('page-report-session-x',  'report_session'),
        'report-session-index' => get_string('page-report-session-index',  'report_session'),
        'report-session-user'  => get_string('page-report-session-user',  'report_session'),
        'report-session-cm'    => get_string('page-report-session-cm',  'report_session')
    );
    return $array;
}
*/

function report_session_cron ($verbose = false) {
    require_once ('locallib.php');
    $result = false;
    $page_num = 0;
    $users = get_users(true, '', true, null, 'id', '', '', $page_num, 100, 'id, username');
    while (!$result && !empty($users)) {
        foreach ($users as $user) {
            if ($user->username = 'guest') continue;
            $result = report_session_update_user_sessions ($user->id, $verbose);
            if ($result) break;
        }
        $users = get_users(true, '', true, null, 'id', '', '', ++$page_num, 100, 'id, username');
    }
}

<?php
/**
 * Force log update for a specific course
 *
 * @package    report
 * @subpackage session
 * @author     Domenico Pontari <fairsayan@gmail.com>
 * @copyright  Institute of tropical medicine Antwerp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
require('../../config.php');
require_once($CFG->dirroot.'/report/session/locallib.php');

$courseid = required_param('courseid',PARAM_INT);       // course id
$userid = optional_param('userid',0, PARAM_INT);       // userid id
$PAGE->set_url('/report/session/update_course.php', array('courseid'=>$courseid, 'userid'=>$userid));

// starting sql string to find all users enrolled in this course
$sql_users_in_course = "SELECT ue.userid
    FROM {user_enrolments} ue 
    JOIN {enrol} e ON (e.id = ue.enrolid) WHERE e.courseid = :courseid";

if ($userid == 0) 
    $userid = $DB->get_field_sql($sql_users_in_course . " ORDER BY ue.userid LIMIT 1",  array('courseid' => $courseid));

if (!$userid) die; //no users for this course

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$user = $DB->get_record('user', array('id'=>$userid), '*', MUST_EXIST);

require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('report/outline:view', $context);

report_session_update_course_user_sessions ($userid, $courseid, true);

$next_userid = $DB->get_field_sql($sql_users_in_course . " AND ue.userid > :userid ORDER BY ue.userid LIMIT 1",
    array('courseid' => $courseid, 'userid' => $userid));

if (!empty($next_userid))
    redirect ($PAGE->url->out(true, array('userid' => $next_userid)), 'continue', 2);
else redirect ("$CFG->wwwroot/report/session/index.php?id=$courseid", 'continue', 2);

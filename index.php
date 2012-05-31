<?php
/**
 * Display user session reports for a course (totals)
 *
 * @package    report
 * @subpackage session
 * @author     Domenico Pontari <fairsayan@gmail.com>
 * @copyright  Institute of Tropical Medicine - Antwerp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/report/session/locallib.php');

// Setup params
$id = required_param('id',PARAM_INT);       // course id
$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$strreport = get_string('pluginname', 'report_session');
$conditions = array('course' => $id);
$aggregated_sessions = $DB->get_records('report_session_aggregate', $conditions);
$sql_users_in_course = "SELECT DISTINCT u.*
    FROM {user_enrolments} ue 
    JOIN {enrol} e ON (e.id = ue.enrolid) 
    JOIN {user} u ON (ue.userid = u.id) WHERE e.courseid = :courseid ORDER BY u.lastname";
$users = $DB->get_records_sql($sql_users_in_course,  array('courseid' => $id));

// Check access and log user
require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('report/session:view', $context);
add_to_log($course->id, 'course', 'report session', "report/session/index.php?id=$course->id");

// Page settings
$PAGE->set_url('/report/session/index.php', array('id'=>$id));
$PAGE->set_pagelayout('report');
$PAGE->set_title($course->shortname .': '. $strreport);
$PAGE->set_heading($strreport);
echo $OUTPUT->header();

//Over title string: total time
$total_session = $DB->get_record('report_session_aggregate', array_merge($conditions, array('cmid' => 0, 'userid' => 0)));
$total_time = ($total_session)?format_time($total_session->duration):0;
$total_str = get_string('total') . " ";
$total_str .= "<a href=\"detail.php?id=$id\">";
$total_str .= strtolower(get_string('course')  . " " . get_string('time')) . '</a>';
echo <<<EOD
    <div class="report-session-summary-box">
        $total_str: $total_time
    </div>

EOD;

//Title
echo $OUTPUT->heading($course->fullname);
echo '<div class="submain">(<a href="export_xls.php?id=' . $id . '">' . get_string('download_excel', 'report_session') . '</a>)</div>';

// Content
// Users table
echo '<div style="float:left;width: 50%"><h2 class="main">' . get_string('users_table', 'report_session')  . '</h2>';
if (!empty($users)) {
    $str_user_header = get_string('user');
    $str_duration_header = get_string('duration', 'report_session');

    $users_table = new html_table();
    $users_table->attributes['class'] = 'generaltable boxaligncenter';
    $users_table->cellpadding = 5;
    $users_table->id = 'users_table';
    $users_table->head = array($str_user_header, $str_duration_header);
    foreach ($users as $user) {
        $duration = 0;
        foreach ($aggregated_sessions as $session) {
            if (($session->userid == $user->id) && ($session->cmid == 0)) $duration = $session->duration;
            if ($duration > 0) break; //found something
        }
        $row['user'] = "<a href=\"user.php?id=$id&userid=$user->id\">" . fullname($user) . '</a>';
        if (report_session_is_user_online($user->id)) $row['user'] .= report_session_print_online_tag();
        $row['duration'] = ($duration)?format_time ($duration):'0';
        $users_table->data[] = $row;
    }
    echo html_writer::table($users_table);
} else echo $OUTPUT->notification(get_string('nothingtodisplay'));
echo '</div>';

// Activities table
echo '<div style="float:right;width: 50%"><h2 class="main">' . get_string('activities_table', 'report_session')  . '</h2>';
$cms = get_course_mods($id);
$modinfo = get_fast_modinfo($course);
if (!empty($cms)) {
    $str_activity_header = get_string('activity');
    $str_duration_header = get_string('duration', 'report_session');

    $activities_table = new html_table();
    $activities_table->attributes['class'] = 'generaltable boxaligncenter';
    $activities_table->cellpadding = 5;
    $activities_table->id = 'activities_table';
    $activities_table->head = array($str_activity_header, $str_duration_header);
    unset($row);
    foreach ($cms as $cm) {
        $cminfo = $modinfo->cms[$cm->id];
        $row['activity'] = "<a href=\"cm.php?id=$id&cmid=$cm->id\">" . $cminfo->name . '</a>';
        $row['duration'] = 0;
        foreach ($aggregated_sessions as $session) { // looking for correct session
            if (($session->userid != 0)||($session->cmid != $cm->id)) continue;
            $row['duration'] = format_time ($session->duration);
            break;
        }
        $activities_table->data[] = $row;
    }
    echo html_writer::table($activities_table);
} else echo $OUTPUT->notification(get_string('nothingtodisplay'));
echo '</div>';

report_session_print_forceupdate(NULL, $id, NULL);

echo $OUTPUT->footer();

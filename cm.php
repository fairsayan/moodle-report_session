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
$cmid = required_param('cmid',PARAM_INT);       // cmid
$cminfo = get_fast_modinfo($course);
$strreport = get_string('cm_report', 'report_session');

// Check access and log user
require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('report/session:view', $context);
add_to_log($course->id, 'course', 'view activity course session report', "report/session/cm.php?id=$course->id&cmid=$cmid");

// Page settings
$PAGE->set_url('/report/session/index.php', array('id'=>$id));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add($strreport);
$PAGE->set_title($course->shortname .': '. $strreport);
$PAGE->set_heading($strreport);
echo $OUTPUT->header();

//Over title string: total time
$conditions = array('course' => $id, 'cmid'=>$cmid);
$aggregated_sessions = $DB->get_records('report_session_aggregate', $conditions);
$total_session = $DB->get_record('report_session_aggregate', array_merge($conditions, array('userid' => 0)));
$total_time = ($total_session)?format_time($total_session->duration):0;
$total_str = get_string('total') . " ";
$total_str .= "<a href=\"detail.php?id=$id&cmid=$cmid\">";
$total_str .= strtolower(get_string('activity')  . " " . get_string('time')) . '</a>';
echo <<<EOD
    <div class="report-session-summary-box">
        $total_str: $total_time
    </div>

EOD;

//Title
echo $OUTPUT->heading(format_string("$course->shortname: " . $cminfo->cms[$cmid]->name));
echo '<div class="submain">(<a href="export_xls.php?id=' . $id . '">' . get_string('download_excel', 'report_session') . '</a>)</div>';


if (count($aggregated_sessions) > 1) { // if there is something should be at least 2 records: one is userid specific computation, the other user generic
    $str_user_header = get_string('user');
    $str_duration_header = get_string('duration', 'report_session');

    $users_table = new html_table();
    $users_table->attributes['class'] = 'generaltable boxaligncenter';
    $users_table->cellpadding = 5;
    $users_table->id = 'users_table';
    $users_table->head = array($str_user_header, $str_duration_header);
    foreach ($aggregated_sessions as $session) {
        if ($session->userid == 0) continue;
        $user = $DB->get_record('user', array('id' => $session->userid));
        $row['user'] = "<a href=\"detail.php?id=$id&userid=$user->id&cmid=$session->cmid\">" . fullname($user) . '</a>';
        $row['duration'] = format_time($session->duration);
        $users_table->data[] = $row;
    }
    echo html_writer::table($users_table);
} else echo $OUTPUT->notification(get_string('nothingtodisplay'));

report_session_print_forceupdate(NULL, $id, $cmid);

echo $OUTPUT->footer();

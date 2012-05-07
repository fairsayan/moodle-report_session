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
$userid = required_param('userid',PARAM_INT);       // course id
$user = $DB->get_record('user', array('id'=>$userid), '*', MUST_EXIST);
$strreport = get_string('user_report', 'report_session');

// Check access and log user
require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('report/session:view', $context);
add_to_log($course->id, 'course', 'view user session report', "report/session/user.php?id=$course->id&userid=$userid");

// Page settings
$PAGE->set_url('/report/session/index.php', array('id'=>$id));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add($strreport);
$PAGE->set_title($course->shortname .': '. $strreport);
$PAGE->set_heading($strreport);
echo $OUTPUT->header();

//Over title string: total time
$conditions = array('course' => $id, 'userid'=>$userid);
$aggregated_sessions = $DB->get_records('report_session_aggregate', $conditions);
$total_session = $DB->get_record('report_session_aggregate', array_merge($conditions, array('cmid' => 0)));
$total_time = ($total_session)?format_time($total_session->duration):0;
$total_str = get_string('total') . " ";
$total_str .= "<a href=\"detail.php?id=$id&userid=$userid\">";
$total_str .= strtolower(get_string('course')  . " " . get_string('time')) . '</a>';
echo <<<EOD
    <div class="report-session-summary-box">
        $total_str: $total_time
    </div>

EOD;

//Title
echo $OUTPUT->heading(format_string("$course->shortname: " . fullname($user)));
if (report_session_is_user_online($userid)) report_session_print_online_tag('text-align:center');

$cms = get_course_mods($id);
$modinfo = get_fast_modinfo($course);
if (!empty($cms)) {
    $str_activity_header = get_string('activity');
    $str_duration_header = get_string('duration', 'report_session');

    $activities_table = new html_table();
    $activities_table->attributes['class'] = 'generaltable boxaligncenter';
    $activities_table->cellpadding = 5;
    $activities_table->id = 'summary_table';
    $activities_table->head = array($str_activity_header, $str_duration_header);
    unset($row);
    foreach ($cms as $cm) {
        $cminfo = $modinfo->cms[$cm->id];
        $row['activity'] = "<a href=\"detail.php?id=$id&userid=$userid&cmid=$cm->id\">" . $cminfo->name . '</a>';
        $row['duration'] = 0;
        foreach ($aggregated_sessions as $session) { // looking for correct session
            if ($session->cmid != $cm->id) continue;
            $row['duration'] = format_time ($session->duration);
            break;
        }
        $activities_table->data[] = $row;
    }
    echo html_writer::table($activities_table);
} else echo $OUTPUT->notification(get_string('nothingtodisplay'));

report_session_print_forceupdate($userid, $id, NULL);

echo $OUTPUT->footer();

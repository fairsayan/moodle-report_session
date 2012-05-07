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
//require_once($CFG->dirroot.'/lib/modinfolib.php');

// Setup params
$id = required_param('id',PARAM_INT);       // course id
$userid = optional_param('userid',0, PARAM_INT);       // user id
$cmid = optional_param('cmid',0, PARAM_INT);       // cm id
$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$strreport = get_string('detail_report', 'report_session');
if ($cmid != 0) {
    $cmid = required_param('cmid',PARAM_INT);       // cmid
    $cminfo = get_fast_modinfo($course);
    $title = "$course->shortname: " . $cminfo->cms[$cmid]->name;
} else $title = $course->fullname;
if ($userid != 0) $title .= ' - ' . fullname($DB->get_record('user', array('id'=>$userid)));

// Check access and log user
require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('report/session:view', $context);
add_to_log($course->id, 'course', 'view detailed session report', "report/session/detail.php?id=$course->id&userid=$userid&cmid=$cmid");

// Page settings
$PAGE->set_url('/report/session/index.php', array('id'=>$id));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add($strreport);
$PAGE->set_title($course->shortname .': '. $strreport);
$PAGE->set_heading($strreport);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);


// Content
$conditions['course'] = $id;
if ($userid != 0) $conditions['userid'] = $userid;
if ($cmid != 0) $conditions['cmid'] = $cmid; else $conditions['cmid'] = NULL;

$sessions = $DB->get_records('report_session', $conditions, 'starttime DESC');

if (!empty($sessions)) {
    $session_table = new html_table();
    $session_table->attributes['class'] = 'generaltable boxaligncenter';
    $session_table->cellpadding = 5;
    $session_table->id = 'session_table';
    if ($userid == 0) $session_table->head[] = get_string('user');
    $session_table->head[] = get_string('starttime', 'report_session');
    $session_table->head[] = get_string('endtime', 'report_session');
    $session_table->head[] = get_string('duration', 'report_session');
    foreach ($sessions as $session) {
        if ($userid == 0) {
            $row['user'] = fullname ($DB->get_record('user', array('id' => $session->userid)));
            if (report_session_is_user_online($session->userid)) $row['user'] .= report_session_print_online_tag();
        }
        $row['starttime'] = userdate($session->starttime);
        $row['endtime'] = userdate($session->endtime);
        $row['duration'] = format_time ($session->endtime - $session->starttime);
        $session_table->data[] = $row;
    }
    echo html_writer::table($session_table);
} else echo $OUTPUT->notification(get_string('nothingtodisplay'));

report_session_print_forceupdate(($userid)?$userid:NULL, $id, ($cmid)?$cmid:NULL);

echo $OUTPUT->footer();

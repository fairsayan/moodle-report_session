<?php
/**
 * Export data in excel
 *
 * @package    report
 * @subpackage session
 * @author     Domenico Pontari <fairsayan@gmail.com>
 * @copyright  Institute of Tropical Medicine - Antwerp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/lib/excellib.class.php');

// Setup params
$id = required_param('id',PARAM_INT);       // course id
$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$strreport = get_string('pluginname', 'report_session');
$str_detailed_report = get_string('detail_report', 'report_session');
$str_user_report = get_string('user_report', 'report_session');
$str_activity_report = get_string('cm_report', 'report_session');
$modinfo = get_fast_modinfo($course);

// Check access and log user
require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('report/session:view', $context);
add_to_log($course->id, 'course', 'download Excel course session report', "report/session/excel.php?id=$course->id");

$shortname = format_string($course->shortname, true, array('context' => get_context_instance(CONTEXT_COURSE, $course->id)));
$downloadfilename = clean_filename("$shortname $strreport.xls");
/// Creating a workbook
$workbook = new MoodleExcelWorkbook("-");
/// Sending HTTP headers
$workbook->send($downloadfilename);

/// USER REPORT
$detailed_report =& $workbook->add_worksheet($str_user_report);

$detailed_report->write_string(0,0,get_string("fullname"));
$detailed_report->write_string(0,1,get_string("duration", 'report_session'));

$conditions = array();
$conditions['course'] = $id;
$conditions['cmid'] = 0;
$user_sessions = $DB->get_records('report_session_aggregate', $conditions, 'duration DESC');
if (!empty($user_sessions)) {
    $pos = 1;
    foreach ($user_sessions as $session) {
        if (empty($session->userid)) continue;
        $user = $DB->get_record('user', array('id' => $session->userid));
        $detailed_report->write_string($pos,0,fullname($user));
        $detailed_report->write_string($pos,1,format_time($session->duration));
        ++$pos;
    }
}

/// ACTIVITY REPORT
$activity_report =& $workbook->add_worksheet($str_activity_report);

$activity_report->write_string(0,0,get_string("activity"));
$activity_report->write_string(0,1,get_string("duration", 'report_session'));

$conditions = array();
$conditions['course'] = $id;
$conditions['userid'] = 0;
$activity_sessions = $DB->get_records('report_session_aggregate', $conditions, 'duration DESC');
if (!empty($activity_sessions)) {
    $pos = 1;
    foreach ($activity_sessions as $session) {
        if (empty($session->cmid)) continue;
        $cminfo = $modinfo->cms[$session->cmid];
        $activity_report->write_string($pos,0,$cminfo->name);
        $activity_report->write_string($pos,1,format_time($session->duration));
        ++$pos;
    }
}

/// DETAILED REPORT
$detailed_report =& $workbook->add_worksheet($str_detailed_report);

$detailed_report->write_string(0,0,get_string("fullname"));
$detailed_report->write_string(0,1,get_string("activity"));
$detailed_report->write_string(0,2,get_string("starttime", 'report_session'));
$detailed_report->write_string(0,3,get_string("endtime", 'report_session'));
$detailed_report->write_string(0,4,get_string("duration", 'report_session'));

$conditions = array();
$conditions['course'] = $id;
$detailed_sessions = $DB->get_records('report_session', $conditions, 'starttime DESC');
if (!empty($detailed_sessions)) {
    $pos = 1;
    foreach ($detailed_sessions as $session) {
        if (empty($session->cmid)) continue;
        $cminfo = $modinfo->cms[$session->cmid];
        $user = $DB->get_record('user', array('id' => $session->userid));
        $detailed_report->write_string($pos,0,fullname($user));
        $detailed_report->write_string($pos,1,$cminfo->name);
        $detailed_report->write_string($pos,2,userdate($session->starttime));
        $detailed_report->write_string($pos,3,userdate($session->endtime));
        $detailed_report->write_string($pos,4,format_time($session->endtime - $session->starttime));
        ++$pos;
    }
}

/// Close the workbook
$workbook->close();

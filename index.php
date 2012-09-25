<?php
/**
 * This file contains functions used by the session reports
 *
 * @package    report
 * @subpackage session
 * @author     Domenico Pontari <fairsayan@gmail.com>
 * @copyright  Institute of Tropical Medicine - Antwerp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/report/session/locallib.php');
require_once($CFG->libdir.'/adminlib.php');

$id          = optional_param('id', 0, PARAM_INT);// Course ID
$host_course = optional_param('host_course', '', PARAM_PATH);// Course ID

if (empty($host_course)) {
    $hostid = $CFG->mnet_localhost_id;
    if (empty($id)) {
        $site = get_site();
        $id = $site->id;
    }
} else {
    list($hostid, $id) = explode('/', $host_course);
}

$group       = optional_param('group', 0, PARAM_INT); // Group to display
$user        = optional_param('user', 0, PARAM_INT); // User to display
$date        = optional_param('date', 0, PARAM_INT); // Date to display
$modname     = optional_param('modname', '', PARAM_PLUGIN); // course_module->id
$modid       = optional_param('modid', 0, PARAM_FILE); // number or 'site_errors'
$type        = optional_param('type', 'all', PARAM_ALPHA); // session type
$page        = optional_param('page', '0', PARAM_INT);     // which page to show
$perpage     = optional_param('perpage', '100', PARAM_INT); // how many per page
$showcourses = optional_param('showcourses', 0, PARAM_INT); // whether to show courses if we're over our limit.
$showusers   = optional_param('showusers', 0, PARAM_INT); // whether to show users if we're over our limit.
$chooselog   = optional_param('chooselog', 0, PARAM_INT);
$logformat   = optional_param('logformat', 'showashtml', PARAM_ALPHA);

$params = array();
if ($id !== 0) {
    $params['id'] = $id;
}
if ($host_course !== '') {
    $params['host_course'] = $host_course;
}
if ($group !== 0) {
    $params['group'] = $group;
}
if ($user !== 0) {
    $params['user'] = $user;
}
if ($date !== 0) {
    $params['date'] = $date;
}
if ($modname !== '') {
    $params['modname'] = $modname;
}
if ($modid !== 0) {
    $params['modid'] = $modid;
}

if ($page !== '0') {
    $params['page'] = $page;
}
if ($perpage !== '100') {
    $params['perpage'] = $perpage;
}
if ($showcourses !== 0) {
    $params['showcourses'] = $showcourses;
}
if ($showusers !== 0) {
    $params['showusers'] = $showusers;
}
if ($chooselog !== 0) {
    $params['chooselog'] = $chooselog;
}
if ($logformat !== 'showashtml') {
    $params['logformat'] = $logformat;
}
$PAGE->set_url('/report/session/index.php', $params);
$PAGE->set_pagelayout('report');

if ($hostid == $CFG->mnet_localhost_id) {
    $course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

} else {
    $course_stub       = $DB->get_record('mnet_log', array('hostid'=>$hostid, 'course'=>$id), '*', true);
    $course->id        = $id;
    $course->shortname = $course_stub->coursename;
    $course->fullname  = $course_stub->coursename;
}

require_login($course);

$context = get_context_instance(CONTEXT_COURSE, $course->id);

require_capability('report/session:view', $context);

add_to_log($course->id, "course", "report session", "report/session/index.php?id=$course->id", $course->id);

$strlogs = get_string('sessions', 'report_session');
$stradministration = get_string('administration');
$strreports = get_string('reports');

session_get_instance()->write_close();

if (!empty($chooselog)) {
    $userinfo = get_string('allparticipants');
    $dateinfo = get_string('alldays');

    if ($user) {
        $u = $DB->get_record('user', array('id'=>$user, 'deleted'=>0), '*', MUST_EXIST);
        $userinfo = fullname($u, has_capability('moodle/site:viewfullnames', $context));
        if ($user && report_session_is_user_online($user)) $userinfo .= report_session_print_online_tag('display:inline; margin:0 5px;');
    }
    if ($date) {
        $dateinfo = userdate($date, get_string('strftimedaydate'));
    }

    switch ($logformat) {
        case 'showashtml':
            if ($hostid != $CFG->mnet_localhost_id || $course->id == SITEID) {
                admin_externalpage_setup('reportlog');
                echo $OUTPUT->header();

            } else {
                $PAGE->set_title($course->shortname .': '. $strlogs);
                $PAGE->set_heading($course->fullname);
                $PAGE->navbar->add("$userinfo, $dateinfo");
                echo $OUTPUT->header();
            }

            echo $OUTPUT->heading(format_string($course->fullname) . ": $userinfo, $dateinfo (".usertimezone().")");
//            report_session_print_mnet_selector_form($hostid, $course, $user, $date, $modname, $modid, $group, $showcourses, $showusers, $logformat);

            if (!$user) report_session_print_online_users();
            
            report_session_print_selector_form($course, $user, $date, $modname, $modid, $type, $group, $showcourses, $showusers, $logformat);
                       
            if ($hostid == $CFG->mnet_localhost_id) {
                report_session_print_sessions($course, $user, $date, $type, 'starttime DESC', $page, $perpage,
                        "index.php?id=$course->id&amp;chooselog=1&amp;user=$user&amp;date=$date&amp;modid=$modid&amp;type=$type&amp;group=$group",
                        $modid, $group);
            } else {
//                print_mnet_log($hostid, $id, $user, $date, 'starttime DESC', $page, $perpage, "", $modname, $modid, $group);
            }
            report_session_print_forceupdate(($user)?$u->id:NULL, $course->id, $modid);
            
            break;
        case 'downloadasexcel':
            if (!report_session_excel_sessions($course, $user, $date, $type, 'starttime DESC', $modid, $group)) {
                echo $OUTPUT->notification("No logs found!");
                echo $OUTPUT->footer();
            }
            exit;
    }


} else {
    if ($hostid != $CFG->mnet_localhost_id || $course->id == SITEID) {
        admin_externalpage_setup('reportlog', '', null, '', array('pagelayout'=>'report'));
        echo $OUTPUT->header();
    } else {
        $PAGE->set_title($course->shortname .': '. $strlogs);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
    }

    echo $OUTPUT->heading(get_string('choosesessions', 'report_session') .':');

    report_session_print_selector_form($course, $user, $date, $modname, $modid, $type, $group, $showcourses, 1, $logformat);
}

echo $OUTPUT->footer();


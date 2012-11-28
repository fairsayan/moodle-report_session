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

defined('MOODLE_INTERNAL') || die;
require_once ($CFG->dirroot. '/lib/adminlib.php');

function report_session_print_selector_form($course, $selecteduser=0, $selecteddate='today',
                $modname="", $modid=0, $type='all', $selectedgroup=-1, $showcourses=0, $showusers=0, $logformat='showashtml') {

    global $USER, $CFG, $DB, $OUTPUT, $SESSION;

    // first check to see if we can override showcourses and showusers
    $numcourses =  $DB->count_records("course");
    if ($numcourses < COURSE_MAX_COURSES_PER_DROPDOWN && !$showcourses) {
        $showcourses = 1;
    }

    $sitecontext = get_context_instance(CONTEXT_SYSTEM);
    $context = get_context_instance(CONTEXT_COURSE, $course->id);

    /// Setup for group handling.
    if ($course->groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
        $selectedgroup = -1;
        $showgroups = false;
    } else if ($course->groupmode) {
        $showgroups = true;
    } else {
        $selectedgroup = 0;
        $showgroups = false;
    }

    if ($selectedgroup === -1) {
        if (isset($SESSION->currentgroup[$course->id])) {
            $selectedgroup =  $SESSION->currentgroup[$course->id];
        } else {
            $selectedgroup = groups_get_all_groups($course->id, $USER->id);
            if (is_array($selectedgroup)) {
                $selectedgroup = array_shift(array_keys($selectedgroup));
                $SESSION->currentgroup[$course->id] = $selectedgroup;
            } else {
                $selectedgroup = 0;
            }
        }
    }

    // Get all the possible users
    $users = array();

    // Define limitfrom and limitnum for queries below
    // If $showusers is enabled... don't apply limitfrom and limitnum
    $limitfrom = empty($showusers) ? 0 : '';
    $limitnum  = empty($showusers) ? COURSE_MAX_USERS_PER_DROPDOWN + 1 : '';

    $courseusers = get_enrolled_users($context, '', $selectedgroup, 'u.id, u.firstname, u.lastname', 'lastname ASC, firstname ASC', $limitfrom, $limitnum);
    if (count($courseusers) < COURSE_MAX_USERS_PER_DROPDOWN && !$showusers) {
        $showusers = 1;
    }

    if ($showusers) {
        if ($courseusers) {
            foreach ($courseusers as $courseuser) {
                $users[$courseuser->id] = fullname($courseuser, has_capability('moodle/site:viewfullnames', $context));
            }
        }
        $users[$CFG->siteguest] = get_string('guestuser');
    }

    if (has_capability('report/session:view', $sitecontext) && $showcourses) {
        if ($ccc = $DB->get_records("course", null, "fullname", "id,fullname,category")) {
            foreach ($ccc as $cc) {
                if ($cc->category) {
                    $courses["$cc->id"] = format_string($cc->fullname);
                } else {
                    $courses["$cc->id"] = format_string($cc->fullname) . ' (Site)';
                }
            }
        }
        asort($courses);
    }

    $activities = array();
    $selectedactivity = "";

    /// Casting $course->modinfo to string prevents one notice when the field is null
    if ($modinfo = unserialize((string)$course->modinfo)) {
        $section = 0;
        $sections = get_all_sections($course->id);
        foreach ($modinfo as $mod) {
            if ($mod->mod == "label") {
                continue;
            }
            if ($mod->section > 0 and $section <> $mod->section) {
                $activities["section/$mod->section"] = '--- '.get_section_name($course, $sections[$mod->section]).' ---';
            }
            $section = $mod->section;
            $mod->name = strip_tags(format_string($mod->name, true));
            if (textlib::strlen($mod->name) > 55) {
                $mod->name = textlib::substr($mod->name, 0, 50)."...";
            }
            if (!$mod->visible) {
                $mod->name = "(".$mod->name.")";
            }
            $activities["$mod->cm"] = $mod->name;

            if ($mod->cm == $modid) {
                $selectedactivity = "$mod->cm";
            }
        }
    }

    if (has_capability('report/session:view', $sitecontext) && ($course->id == SITEID)) {
        $activities["site_errors"] = get_string("siteerrors");
        if ($modid === "site_errors") {
            $selectedactivity = "site_errors";
        }
    }

    $strftimedate = get_string("strftimedate");
    $strftimedaydate = get_string("strftimedaydate");

    asort($users);

    // Prepare the list of types options.
    $types = array(
        'all' => get_string('all_session_types', 'report_session'),
        'offline' => get_string('offline', 'report_session'),
        'online' => get_string('online', 'report_session'),
    );

    // Get all the possible dates
    // Note that we are keeping track of real (GMT) time and user time
    // User time is only used in displays - all calcs and passing is GMT

    $timenow = time(); // GMT

    // What day is it now for the user, and when is midnight that day (in GMT).
    $timemidnight = $today = usergetmidnight($timenow);

    // Put today up the top of the list
    $dates = array("$timemidnight" => get_string("today").", ".userdate($timenow, $strftimedate) );

    if (!$course->startdate or ($course->startdate > $timenow)) {
        $course->startdate = $course->timecreated;
    }

    $numdates = 1;
    while ($timemidnight > $course->startdate and $numdates < 365) {
        $timemidnight = $timemidnight - 86400;
        $timenow = $timenow - 86400;
        $dates["$timemidnight"] = userdate($timenow, $strftimedaydate);
        $numdates++;
    }

    if ($selecteddate == "today") {
        $selecteddate = $today;
    }

    echo "<form class=\"logselectform\" action=\"$CFG->wwwroot/report/session/index.php\" method=\"get\">\n";
    echo "<div>\n";
    echo "<input type=\"hidden\" name=\"chooselog\" value=\"1\" />\n";
    echo "<input type=\"hidden\" name=\"showusers\" value=\"$showusers\" />\n";
    echo "<input type=\"hidden\" name=\"showcourses\" value=\"$showcourses\" />\n";
    if (has_capability('report/session:view', $sitecontext) && $showcourses) {
        echo html_writer::select($courses, "id", $course->id, false);
    } else {
        //        echo '<input type="hidden" name="id" value="'.$course->id.'" />';
        $courses = array();
        $courses[$course->id] = $course->fullname . (($course->id == SITEID) ? ' ('.get_string('site').') ' : '');
        echo html_writer::select($courses,"id",$course->id, false);
        if (has_capability('report/session:view', $sitecontext)) {
            $a = new stdClass();
            $a->url = "$CFG->wwwroot/report/log/index.php?chooselog=0&group=$selectedgroup&user=$selecteduser"
            ."&id=$course->id&date=$selecteddate&modid=$selectedactivity&showcourses=1&showusers=$showusers";
            print_string('logtoomanycourses','moodle',$a);
        }
        }

        if ($showgroups) {
            if ($cgroups = groups_get_all_groups($course->id)) {
                foreach ($cgroups as $cgroup) {
                    $groups[$cgroup->id] = $cgroup->name;
                }
            }
            else {
                $groups = array();
            }
            echo html_writer::select($groups, "group", $selectedgroup, get_string("allgroups"));
        }

        if ($showusers) {
            $new_users[-1] = get_string('all_students', 'report_session');
            foreach ($users as $tmpindex => $tmpuser)
                $new_users[$tmpindex] = $tmpuser;
            echo html_writer::select($new_users, "user", $selecteduser, get_string("allparticipants"));
        }
        else {
            $users = array();
            if (!empty($selecteduser)) {
                $user = $DB->get_record('user', array('id'=>$selecteduser));
                $users[$selecteduser] = fullname($user);
            }
            else {
                $users[0] = get_string('allparticipants');
            }
            echo html_writer::select($users, "user", $selecteduser, false);
            $a = new stdClass();
            $a->url = "$CFG->wwwroot/report/session/index.php?chooselog=0&group=$selectedgroup&user=$selecteduser"
            ."&id=$course->id&date=$selecteddate&modid=$selectedactivity&showusers=1&showcourses=$showcourses";
            print_string('logtoomanyusers','moodle',$a);
        }
        echo html_writer::select($dates, "date", $selecteddate, get_string("alldays"));

        echo html_writer::select($activities, "modid", $selectedactivity, get_string("allactivities"));

        echo html_writer::select($types, "type", $type);
        
        $logformats = array('showashtml' => get_string('displayonpage'),
                        'downloadasexcel' => get_string('downloadexcel'));

        echo html_writer::select($logformats, 'logformat', $logformat, false);
        echo '<input type="submit" value="'.get_string('gettheselogs').'" />';
        echo '</div>';
        echo '</form>';
}

function report_session_print_sessions($course, $user=0, $date=0, $type='all', $order="starttime DESC", $page=0, $perpage=100,
                $url="", $modid=0, $groupid=0) {

    global $CFG, $DB, $OUTPUT;
    $modinfos = array(); // courseid -> modinfo -> cms -> name

    $modid = (int)$modid;
    if (!$sessions = report_session_get_paged($user, $course, $modid, $type, $date, $order, $page*$perpage, $perpage, $groupid)) {
        echo $OUTPUT->notification("No sessions found!");
        echo $OUTPUT->footer();
        exit;
    }

    $courses = array();

    if ($course->id == SITEID) {
        $courses[0] = '';
        if ($ccc = get_courses('all', 'c.id ASC', 'c.id,c.shortname')) {
            foreach ($ccc as $cc) {
                $courses[$cc->id] = $cc->shortname;
            }
        }
    } else {
        $courses[$course->id] = $course->shortname;
    }

    $totalcount = $sessions['totalcount'];

    echo "<div class=\"info\">\n";
    print_string("displayingrecords", "", $totalcount);
    echo "</div>\n";

    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, "$url&perpage=$perpage");

    $table = new html_table();
    $table->classes = array('sessiontable','generalbox');
    $table->head = array(
                    get_string('starttime', 'report_session'),
                    get_string('endtime', 'report_session'),
                    get_string('duration', 'report_session'),
                    get_string('description'),
    );
    $table->data = array();

    if ($type == 'all') array_splice($table->head, 3, 0, get_string('session_type', 'report_session'));
    if ($modid == 0) array_unshift($table->head, get_string('activity'));
    if ($user <= 0) array_unshift($table->head, get_string('user'));
    if ($course->id == SITEID) array_unshift($table->head, get_string('course'));

    $users = array();
    foreach ($sessions['data'] as $session) {
        unset($row);
        
        if ($course->id == SITEID) $row[] = ($session->courseid)?$courses[$session->courseid]:$courses[SITEID];
        if ($user <= 0) {
            if (!isset($users[$session->userid])) $users[$session->userid] = $DB->get_record ('user', array('id' => $session->userid));
            $row[] = fullname($users[$session->userid]);            
        }
        if ($modid == 0) {
            if (!empty($session->cmid)) {
                if (!isset($modinfo[$session->courseid])) {
                    $modcourse = $DB->get_record ('course', array('id' => $session->courseid));
                    $modinfo[$session->courseid] = get_fast_modinfo($modcourse);
                }
                $row[] = $modinfo[$session->courseid]->cms[$session->cmid]->name;
            } else $row[] = '';
        }
        $row[] = userdate($session->starttime);
        $row[] = userdate($session->endtime);
        $row[] = format_time($session->duration);
        if ($type == 'all') $row[] = $session->type;
        $row[] = $session->description;
        $table->data[] = $row;
    }

    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, "$url&perpage=$perpage");
}

function report_session_excel_sessions ($course, $user, $date, $type, $order, $modid, $group) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/lib/excellib.class.php');

    $modid = (int)$modid;
    $strreport = get_string('pluginname', 'report_session');
    $users = array();
    $courses = array();
    $modinfos = array(); // courseid -> modinfo -> cms -> name
        
    if (!$sessions = report_session_get_paged($user, $course, $modid, $type, $date, $order, 0, 0, $group))
        return false;
    
    
    if ($course->id == SITEID) {
        $courses[0] = '';
        if ($ccc = get_courses('all', 'c.id ASC', 'c.id,c.shortname')) {
            foreach ($ccc as $cc) {
                $courses[$cc->id] = $cc->shortname;
            }
        }
    } else {
        $courses[$course->id] = $course->shortname;
    }
    
    $downloadfilename = clean_filename("$strreport.xls");
    /// Creating a workbook
    $workbook = new MoodleExcelWorkbook("-");
    /// Sending HTTP headers
    $workbook->send($downloadfilename);
    
    $report =& $workbook->add_worksheet($strreport);
    $report->write_string(0,0,get_string("course"));
    $report->write_string(0,1,get_string("user"));
    $report->write_string(0,2,get_string("activity"));
    $report->write_string(0,3,get_string('starttime', 'report_session'));
    $report->write_string(0,4,get_string('endtime', 'report_session'));
    $report->write_string(0,5,get_string('minutes'));
    $report->write_string(0,6,get_string('session_type', 'report_session'));
    $report->write_string(0,7,get_string('description'));
    
    $pos = 1;
    foreach ($sessions['data'] as $session) {
        if (!isset($users[$session->userid]))
            $users[$session->userid] = $DB->get_record ('user', array('id' => $session->userid));

        if (!empty($session->cmid)) {
            if (!isset($modinfo[$session->courseid])) {
                $modcourse = $DB->get_record ('course', array('id' => $session->courseid));
                $modinfo[$session->courseid] = get_fast_modinfo($modcourse);
            }
            if (isset($modinfo[$session->courseid]->cms[$session->cmid]))
                $activity = $modinfo[$session->courseid]->cms[$session->cmid]->name;
                else $activity = $session->cmid;
        }
        
        $report->write_string($pos,0,$courses[$session->courseid]);
        $report->write_string($pos,1,fullname($users[$session->userid]));
        if (!empty($session->cmid)) $report->write_string($pos,2,$activity);
        $report->write_string($pos,3,userdate($session->starttime));
        $report->write_string($pos,4,userdate($session->endtime));
        $report->write_number($pos,5,(int)($session->duration / 60));
        $report->write_string($pos,6,$session->type);
        $report->write_string($pos,7,$session->description);
        ++$pos;
    }
    $workbook->close();
    return true;
}

/**
 *  Return session list in report session format.
 *
 *  Format:
 *      - starttime (EPOCH)
 *      - endtime (EPOCH)
 *      - duration (seconds)
 *      - userid (0 => all users)
 *      - courseid (0 => all courses)
 *      - cmid (0 => all cms)
 *      - description
 *      - session type
 *
 */
function report_session_get_paged ($userid, $course, $cmid, $type, $date, $order, $starting_record, $perpage, $groupid) {
    global $DB;
    global $USER;
    global $SESSION;
    
    $str_conditions = array();
    $str_offline_conditions = array();
    if ($course->id == SITEID) $courseid = 0; else $courseid = $course->id;
    $mindate = $date;
    $maxdate = $date + (24 * 3600);
    $sql_strs = array();
    
    // check is offline sessions are needed
    if (($type == 'all' || $type == 'offline') && report_session_offline_exists($courseid))
        $joinoffline = true; else $joinoffline = false;

    /// If the group mode is separate, and this user does not have editing privileges,
    /// then only the user's group can be viewed.
    if ($course->groupmode == SEPARATEGROUPS and !has_capability('moodle/course:managegroups', get_context_instance(CONTEXT_COURSE, $course->id))) {
        if (isset($SESSION->currentgroup[$course->id])) {
            $groupid =  $SESSION->currentgroup[$course->id];
        } else {
            $groupid = groups_get_all_groups($course->id, $USER->id);
            if (is_array($groupid)) {
                $groupid = array_shift(array_keys($groupid));
                $SESSION->currentgroup[$course->id] = $groupid;
            } else {
                $groupid = 0;
            }
        }
    }
    /// If this course doesn't have groups, no groupid can be specified.
    else if (!$course->groupmode) {
        $groupid = 0;
    }
    
    
    $params = array (
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'cmid' => $cmid,
                    'type' => $type,
                    'mindate' => $mindate,
                    'maxdate' => $maxdate,
                    'starting_record' => $starting_record,
                    'perpage' => $perpage,
                    'groupid' => $groupid,
                    'ouserid' => $userid,
                    'ocourseid' => $courseid,
                    'ocmid' => $cmid,
                    'otype' => $type,
                    'omindate' => $mindate,
                    'omaxdate' => $maxdate,
                    'ostarting_record' => $starting_record,
                    'operpage' => $perpage,
                    'ogroupid' => $groupid,
    );
    
    if ($userid > 0) {
        array_push($str_conditions,         'rs.userid = :userid');
        array_push($str_offline_conditions, 'od.userid = :ouserid');
    } elseif ($userid == -1) { // all students
        $str_student_conditions = array();
        $str_offline_student_conditions = array();
        $context = get_context_instance(CONTEXT_COURSE, $course->id);
        $enrolled_users = get_enrolled_users($context);
        foreach ($enrolled_users as $enrolled_user) {
            $is_student = false;
            $roles = get_user_roles($context, $enrolled_user->id);
            foreach ($roles as $role) {
                if ($role->shortname == 'student') {
                    $is_student = true;
                    break;
                }
            }
            if ($is_student) {
                array_push($str_student_conditions, "rs.userid = $enrolled_user->id");
                array_push($str_offline_student_conditions, "od.userid = $enrolled_user->id");
            }
        }
        if (!empty($str_student_conditions)) {
            array_push($str_conditions, '(' . implode(' OR ', $str_student_conditions) . ')');
            array_push($str_offline_conditions, '(' . implode(' OR ', $str_offline_student_conditions) . ')');
        }
    }
    
    if ($cmid !== 0) array_push($str_offline_conditions, 'od.cmid = :ocmid');
    if ($cmid !== 0) array_push($str_conditions, 'rs.cmid = :cmid');
        else array_push($str_conditions, 'rs.cmid <> :cmid'); 

    if ($courseid !== 0) array_push($str_offline_conditions, 'o.course = :ocourseid');
    array_push($str_conditions, 'rs.course =  :courseid');
    
    if ($date) {
        array_push($str_conditions, 'rs.starttime >= :mindate');
        array_push($str_conditions, 'rs.starttime <= :maxdate');
        array_push($str_offline_conditions, 'od.starttime >= :omindate');
        array_push($str_offline_conditions, 'od.starttime <= :omaxdate');
    }
    
    $str_condition = 'WHERE ' . implode(' AND ', $str_conditions);
    $str_offline_condition = empty($str_offline_conditions)?'':'WHERE ' . implode(' AND ', $str_offline_conditions);
    
    if ($type == 'online' || $type == 'all') // online select
        $sql_strs[] = "SELECT (rs.id * 10) as id, starttime, endtime, (endtime - starttime) AS duration, 
            userid, course as courseid, cmid, '' as description , 'online' as type 
            FROM {report_session} as rs $str_condition";

    if ($joinoffline) {// offline select
        $sql_strs[] .= "SELECT (od.id * 10 + 1) as id, starttime, 
            (starttime + duration) as endtime, duration, userid, course as courseid, cmid, 
            description , 'offline' as type 
            FROM {offlinesession_data} as od JOIN {offlinesession} as o
            ON (od.offlinesessionid = o.id) $str_offline_condition";
    }
    
    $count_sql = 'SELECT COUNT(*) FROM (' . implode(' UNION ', $sql_strs) . ') as s';
    $sql = 'SELECT * FROM (' . implode(' UNION ', $sql_strs) . ") as s ORDER BY $order ";
    if ($perpage) $sql .= " LIMIT $starting_record, $perpage";

    $sessions['data'] = $DB->get_records_sql ($sql, $params);
    $sessions['totalcount'] = $DB->count_records_sql($count_sql, $params);
    return $sessions;
}

function report_session_offline_exists ($courseid) {
    $cmids = report_session_get_offlinesession_cmids($courseid);
    return !empty($cmids);
}

/**
 * 
 * @param int $courseid if 0 => check in all courses
 */
function report_session_get_offlinesession_cmids ($courseid) {
    global $DB;
    static $cmids;

    if (!isset($cmids)) $cmids = array();
        else return $cmids;
    
    if ($courseid) {
        $sql = "SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON (cm.module = m.id) 
            WHERE cm.course = :courseid AND m.name = 'offlinesession'";
        $records = $DB->get_records_sql ($sql, array('courseid' => $courseid));
    } else {
        $sql = "SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON (cm.module = m.id)
        WHERE m.name = 'offlinesession'";
        $records = $DB->get_records_sql ($sql);
    }
    foreach ($records as $record) array_push ($cmids, $record->id);
    return $cmids;
}

/**
 * Given an array of logs, this function returns an array of end of sessions
 * @param array
 * @param boolean if true get the courseid from the logs otherwise set NULL the courseid field
 * @param boolean if true get the cmid from the logs otherwise set NULL the cmid field
 */
function _report_session_get_session_from_logs (&$logs, $userid, $courseid = true, $cmid = true) {
    global $CFG;
    
    $sessions = array();
    $start_time = 0;
    $prev_time = 0;
    foreach ($logs as $log) {
        if ($start_time == 0) $start_time = $log->time;
        if ($prev_time == 0) $prev_time = $log->time;
        if ($log->time - $prev_time >= ($CFG->reportsession_timeout*60)) {
            $session->starttime = $start_time;
            $session->endtime = $prev_time + ($CFG->reportsession_extratime*60);
            $session->userid = $userid;
            if ($courseid) $session->course = $log->course; else $session->course = 0;
            if ($cmid) $session->cmid = $log->cmid; else $session->cmid = 0;
            $sessions[] = $session;
            $start_time = $log->time;
            unset($session);
        }
        $prev_time = $log->time;
    }  
    
    //current session if exists
    if (!empty($logs)) {
        $last_log = array_pop($logs);
        if (time() - $last_log->time < ($CFG->reportsession_timeout*60)) {
            $session->starttime = $start_time;
            $session->endtime = 0;
            $session->userid = $userid;
            if ($courseid) $session->course = $last_log->course;
            if ($cmid) $session->cmid = $last_log->cmid;
            $sessions[] = $session;
        }
        $logs[$last_log->time] = $last_log;
    }
    return $sessions;
}

/**
 * Update session calculation
 * @param int current num of logs to consider
 * @return int 0 means no limit
 */
function _report_session_get_next_dimension_step ($dimension) {
    $dimension_step = 2000;
    $dimension_limit = 10000;

    $dimension += $dimension_step;
    if ($dimension >= $dimension_limit) $dimension = 0;
    
    return $dimension;
}

function report_session_update_user_sessions ($userid, $verbose = false, $stop_on_first_job = false) {
    global $DB;
    $result = false;

    // starting sql string to find all users enrolled in this course
    $sql_users_courses = "SELECT e.courseid
        FROM {user_enrolments} ue 
        JOIN {enrol} e ON (e.id = ue.enrolid) WHERE ue.userid = :userid";
    
    $courses = $DB->get_records_sql ($sql_users_courses, array('userid' => $userid));
    foreach ($courses as $course) {
        $result = report_session_update_course_user_sessions ($userid, $course->courseid, $verbose, $stop_on_first_job);
        if ($result && $stop_on_first_job) break;
    }
    
    return $result;
}

function report_session_print_forceupdate ($userid, $course, $cmid) {
    global $DB;

    $conditions['course'] = $course;
    $conditions['cmid'] = $cmid;
    if ($userid !== NULL) $conditions['userid'] = $userid;
    $time = 0;

    $last_log_time = $DB->get_records ('report_session_last_log_time',$conditions);

    if (!empty($last_log_time)) {
        foreach ($last_log_time as $record)
            $time = max ($record->lastlogtime,$time);
    }

    if ($time) $strtime = get_string('last_update', 'report_session') . ": " . userdate($time);
    else $strtime = get_string('never_calculated', 'report_session');
    $strupdate = get_string('update');

    echo <<<EOD
<div class="report-session-lastlogtime">
    $strtime (<a href="update_course.php?courseid=$course">$strupdate</a>)
</div>
EOD;
}

/**
 * Update session calculation for each session type of a specific user and a course (optional)
 * @param int user id
 * @param int course id
 * @param bool
 */
function report_session_update_course_user_sessions ($userid, $courseid, $verbose = false, $stop_on_first_job = false) {
    global $DB;
    $result = false;
    
    $cms = $DB->get_records ('course_modules',  array('course' => $courseid), 'id', 'id');

    foreach ($cms as $cm) {
        $result = $result OR _report_session_update_sessions ($userid, $courseid, $cm->id, $verbose);
        if ($result && $stop_on_first_job) break;
    }
    
    if (!($result && $stop_on_first_job))
        $result = $result OR _report_session_update_sessions ($userid, $courseid, 0, $verbose);
    
    if (!($result && $stop_on_first_job))
        $result = $result OR _report_session_update_sessions ($userid, 0, 0, $verbose);
    
    return $result;
}    

/**
 * Update session calculation
 * @return bool starting time of the current session or false if there isn't an opened session
 */
function report_session_is_user_online ($userid) {
    global $DB;
    global $CFG;
    
    $lastlog = $DB->get_field_sql('SELECT time FROM {log} WHERE userid = :userid ORDER BY time DESC LIMIT 1', array('userid' => $userid));
    return ($lastlog !== false)&&(time() - $lastlog < ($CFG->reportsession_timeout*60));
}

function report_session_print_online_tag ($style) {
    return <<<EOD
<div class="online-tag" style="$style">- now online -</div>
EOD;
}

/**
 * Update session calculation
 * @return bool starting time of the current session or false if there isn't an opened session
 */
function report_session_get_online_users () {
    global $DB;
    global $CFG;

    $result = array();
    $lastlogs = $DB->get_records_sql('SELECT userid, MAX(time) AS log FROM {log} GROUP BY userid');
    foreach ($lastlogs as $lastlog) {
        if (time() - $lastlog->log < ($CFG->reportsession_timeout*60))
            $result[$lastlog->userid] = $lastlog->log;
    }
    return $result;
}

function report_session_print_online_users() {
    global $DB;
    
    $users_online = report_session_get_online_users();
    $online_users = get_string ('online_users', 'report_session');
    $last_log = get_string ('last_log', 'report_session');
    echo <<<EOD
<table>
    <tr><th>$online_users</th><th>$last_log</th></tr>
EOD;
    
    foreach ($users_online as $userid => $lastlog) {
        $user = $DB->get_record('user', array('id' => $userid));
        $fullname = fullname($user);
        $time = userdate($lastlog);
        echo "<tr><td>$fullname</td><td>$time</td></tr>\n";
    }
    echo '</table>';
}

/**
 * Update session calculation
 * @param int user id
 * @param int course id. If 0 => all courses are considered
 * @param int cmid. If 0 => all cmids are considered
 * @param bool
 * @param int number of logs to consider
 * @return bool true if something has been updated, false otherwise
 */
function _report_session_update_sessions ($userid, $courseid = 0, $cmid = 0, $verbose = false, $dimension = 1000) {
    global $DB;
    $lockingtimeout = 1800; //seconds

    if ($verbose) {
        $user = $DB->get_record ('user', array('id' => $userid));
        $verbose_data_string = " (user: " . fullname($user) . ", course: $courseid, cmid: $cmid, dimension: $dimension)<br />";
    }
    
    /*****    GET LAST SESSION END TIME   *****/
    $last_log_time_conditions['userid'] = $userid;
    $last_log_time_conditions['course'] = $courseid;
    $last_log_time_conditions['cmid'] = $cmid;
    $last_log_time = $DB->get_record ('report_session_last_log_time', $last_log_time_conditions);
    if (!$last_log_time) {
        $last_log_time->userid = $userid;
        $last_log_time->course = $courseid;
        $last_log_time->cmid = $cmid;
        $last_log_time->lastlogtime = 0;
        $last_log_time->id = $DB->insert_record('report_session_last_log_time', $last_log_time);
    } elseif (time() - $last_log_time->lockedattime < $lockingtimeout) {
        if ($verbose) echo "Exit: already locked" . $verbose_data_string;
        return false; // nothing to do: it's already locked
    }

    /*****    LOCK SESSION BUILDER   *****/
    $last_log_time->lockedattime = time();
    $DB->update_record('report_session_last_log_time', $last_log_time);
    $last_log_time->lockedattime = 0; // ready to be unlocked


    /*****    GET USEFUL LOGS        *****/
    $logs_conditions['userid'] = $userid;
    if ($courseid !== 0) $logs_conditions['course'] = $courseid;
    if ($cmid !== 0) $logs_conditions['cmid'] = $cmid;
    $where_fields = array_keys ($logs_conditions);
    foreach ($where_fields as $where_field) 
      $where_sentences[] = " $where_field = :$where_field";
    $logs_conditions['lastlogtime'] = $last_log_time->lastlogtime;
    $where_sentences[] = ' time > :lastlogtime';
    $where_clause = implode (' AND', $where_sentences);

    $logs = $DB->get_records_sql ("SELECT id, time, action, course, cmid FROM {log} WHERE $where_clause ORDER BY time",
        $logs_conditions, 0, $dimension);

    if (empty($logs)) {
        $DB->update_record('report_session_last_log_time', $last_log_time); // unlock session builder
        if ($verbose) echo "No new logs" . $verbose_data_string;
        return false;
    }

    /*****    BUILD SESSIONS         *****/
    $sessions = _report_session_get_session_from_logs ($logs, $userid, ($courseid != 0), ($cmid != 0));

    if (empty($sessions)) { // check if you have a session with more logs than dimension limit
        $result = false;
        $DB->update_record('report_session_last_log_time', $last_log_time); // unlock session builder
        if ($verbose) echo 'No new sessions' . $verbose_data_string;
        if ($dimension > 0) { // if $dimension == 0 => already there is no limit
            $dimension = _report_session_get_next_dimension_step ($dimension);
            $result = _report_session_update_sessions ($userid, $courseid, $cmid, $verbose, $dimension);
        }
        return $result;
    }

    /*****    SAVE SESSIONS ON DB    *****/
    $last_session_endtime = 0;
    $something_done = false;
    foreach ($sessions as $session) {
        if ($session->endtime == 0) continue; // avoid to write current session
            else $last_session_endtime = $session->endtime;
        $DB->insert_record ('report_session', $session, false, true);

        if ($verbose) echo 'Saving session from ' . userdate($session->starttime) . ' to '. userdate($session->endtime) . $verbose_data_string;
        $something_done = true;
    }

    /*****    UPDATE LAST SESSION END TIME AND UNLOCK   *****/
    if ($last_session_endtime > 0)
        $last_log_time->lastlogtime = $last_session_endtime;

    $DB->update_record('report_session_last_log_time', $last_log_time);
    if (!$something_done && $verbose) echo 'Nothing to do' . $verbose_data_string;
    return $something_done;
}


/**
 * No setting - just text.
 */
class admin_setting_confightmlcode extends admin_setting {

    /**
     * not a setting, just text
     * @param string $name unique ascii name, either 'mysetting' for settings that in config, or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $heading heading
     * @param string $information text in box
     */
    public function __construct($name, $heading, $information) {
        $this->nosave = true;
        parent::__construct($name, $heading, $information, '');
    }

    /**
     * Always returns true
     * @return bool Always returns true
     */
    public function get_setting() {
        return true;
    }

    /**
     * Always returns true
     * @return bool Always returns true
     */
    public function get_defaultsetting() {
        return true;
    }

    /**
     * Never write settings
     * @return string Always returns an empty string
     */
    public function write_setting($data) {
    // do not write any setting
        return '';
    }

    /**
     * Returns an HTML string
     * @return string Returns an HTML string
     */
    public function output_html($data, $query='') {
        return format_admin_setting($this, $this->visiblename,
        "<div class=\"form-text defaultsnext\">$this->description</div>",
        '', true, '', $default, $query);
    }
}

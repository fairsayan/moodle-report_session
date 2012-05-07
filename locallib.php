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

define('REPORT_SESSION_TIMEOUT', 1800);
define('REPORT_SESSION_EXTRATIME_WITHOUT_LOGOUT', 900);

/**
 * Given an array of logs, this function returns an array of end of sessions
 * @param array
 * @param boolean if true get the courseid from the logs otherwise set NULL the courseid field
 * @param boolean if true get the cmid from the logs otherwise set NULL the cmid field
 */
function _report_session_get_session_from_logs (&$logs, $userid, $courseid = true, $cmid = true) {
    $sessions = array();
    $start_time = 0;
    $prev_time = 0;
    foreach ($logs as $log) {
        if ($start_time == 0) $start_time = $log->time;
        if ($prev_time == 0) $prev_time = $log->time;
        if ($log->time - $prev_time >= REPORT_SESSION_TIMEOUT) {
            $session->starttime = $start_time;
            $session->endtime = $prev_time + REPORT_SESSION_EXTRATIME_WITHOUT_LOGOUT;
            $session->userid = $userid;
            if ($courseid) $session->course = $log->course;
            if ($cmid) $session->cmid = $log->cmid;
            $sessions[] = $session;
            $start_time = $log->time;
            unset($session);
        }
        $prev_time = $log->time;
    }  
    
    //current session if exists
    if (!empty($logs)) {
        $last_log = array_pop($logs);
        if (time() - $last_log->time < REPORT_SESSION_TIMEOUT) {
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
        $result = $result OR _report_session_update_sessions ($userid, $courseid, NULL, $verbose);
    
    return $result;
}    

/**
 * Update session calculation
 * @return bool starting time of the current session or false if there isn't an opened session
 */
function report_session_is_user_online ($userid) {
    global $DB;
    $lastlog = $DB->get_field_sql('SELECT time FROM {log} WHERE userid = :userid ORDER BY time DESC LIMIT 1', array('userid' => $userid));
    return ($lastlog !== false)&&(time() - $lastlog < REPORT_SESSION_TIMEOUT);
}

function report_session_print_online_tag ($style) {
    return <<<EOD
<div class="online-tag" style="$style">- online -</div>
EOD;
}

/**
 * Update session calculation
 * @param int user id
 * @param int course id. If NULL => all courses are considered
 * @param int cmid. If NULL => all cmids are considered
 * @param bool
 * @param int number of logs to consider
 * @return bool true if something has been updated, false otherwise
 */
function _report_session_update_sessions ($userid, $courseid = NULL, $cmid = NULL, $verbose = false, $dimension = 1000) {
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
    if ($courseid !== NULL) $logs_conditions['course'] = $courseid;
    if ($cmid !== NULL) $logs_conditions['cmid'] = $cmid;
    $where_fields = array_keys ($logs_conditions);
    foreach ($where_fields as $where_field) 
      $where_sentences[] = " $where_field = :$where_field";
    $logs_conditions['lastlogtime'] = $last_log_time->lastlogtime;
    $where_sentences[] = ' time > :lastlogtime';
    $where_clause = implode (' AND', $where_sentences);

    $logs = $DB->get_records_sql ("SELECT time, action, course, cmid FROM {log} WHERE $where_clause ORDER BY time",
        $logs_conditions, 0, $dimension);

    if (empty($logs)) {
        $DB->update_record('report_session_last_log_time', $last_log_time); // unlock session builder
        if ($verbose) echo "No new logs" . $verbose_data_string;
        return false;
    }

    /*****    BUILD SESSIONS         *****/
    $sessions = _report_session_get_session_from_logs ($logs, $userid, ($courseid !== NULL), ($cmid !== NULL));

    if (empty($sessions)) { // check if you have a session with more logs then dimension limit
        $result = false;
        $DB->update_record('report_session_last_log_time', $last_log_time); // unlock session builder
        if ($verbose) echo 'No new sessions' . $verbose_data_string;
        if ($dimension > 0) { // if $dimension == 0 => already there is no limit
            $dimension = _report_session_get_next_dimension_step ($dimension);
            $result = _report_session_update_sessions ($userid, $courseid, $cmid, $verbose, $dimension);
        }
        return $result;
    }

    /*****    SAVE SESSIONS ON DB AND CALCULATE AGGREGATED VALUES    *****/
    $last_session_endtime = 0;
    $something_done = false;
    $aggregated_session_values = array(); //multidimensional array => [userid] x [courseid] x [cmid]
    foreach ($sessions as $session) {
        if ($session->endtime == 0) continue; // avoid to write current session
            else $last_session_endtime = $session->endtime;
        $DB->insert_record ('report_session', $session, false, true);

        /** add duration to aggregated values **/
        $duration = $session->endtime - $session->starttime;
        if (!isset($session->cmid)) { // platform aggregated value
            if (isset($session->course)) { // calc course aggregated value
                if (!isset($aggregated_session_values[$userid][$session->course][0]))
                    $aggregated_session_values[$userid][$session->course][0] = 0;
                if (!isset($aggregated_session_values[0][$session->course][0]))
                    $aggregated_session_values[0][$session->course][0] = 0;
                $aggregated_session_values[$userid][$session->course][0] += $duration; // how much time $userid spent on the course
                $aggregated_session_values[0][$session->course][0] += $duration; // how much time users spent on the course
            }
            
            if (!isset($aggregated_session_values[$userid][0][0]))
                $aggregated_session_values[$userid][0][0] = 0;
            $aggregated_session_values[$userid][0][0] += $duration; // how much time $userid spent on the platform
        } else {
            if (!isset($aggregated_session_values[$userid][$session->course][$session->cmid]))
                $aggregated_session_values[$userid][$session->course][$session->cmid] = 0;
            if (!isset($aggregated_session_values[0][$session->course][$session->cmid]))
                $aggregated_session_values[0][$session->course][$session->cmid] = 0;
            $aggregated_session_values[$userid][$session->course][$session->cmid] += $duration; // how much time $userid spent on the activity
            $aggregated_session_values[0][$session->course][$session->cmid] += $duration; // how much time users spent on the activity
        }
        
        if ($verbose) echo 'Saving session from ' . userdate($session->starttime) . ' to '. userdate($session->endtime) . $verbose_data_string;
        $something_done = true;
    }

    /*****    UPDATE AGGREGATED VALUES   *****/
    foreach ($aggregated_session_values as $curr_userid => $step1_session) {
        foreach ($step1_session as $curr_course => $step2_session) {
            foreach ($step2_session as $curr_cmid => $curr_duration) {
                unset($aggregated_session);
                $aggregated_session = $DB->get_record ('report_session_aggregate',
                    array ('userid' => $curr_userid, 'course' => $curr_course, 'cmid' => $curr_cmid));
                if (empty($aggregated_session)) {
                    $aggregated_session->userid = $curr_userid;
                    $aggregated_session->course = $curr_course;
                    $aggregated_session->cmid = $curr_cmid;
                    $aggregated_session->duration = $curr_duration;
                    $DB->insert_record('report_session_aggregate',$aggregated_session);
                } else {
                    $aggregated_session->duration += $curr_duration;
                    $DB->update_record('report_session_aggregate',$aggregated_session);
                }
            }
        }
    }

    /*****    UPDATE LAST SESSION END TIME AND UNLOCK   *****/
    if ($last_session_endtime > 0)
        $last_log_time->lastlogtime = $last_session_endtime;

    $DB->update_record('report_session_last_log_time', $last_log_time);
    if (!$something_done && $verbose) echo 'Nothing to do' . $verbose_data_string;
    return $something_done;
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

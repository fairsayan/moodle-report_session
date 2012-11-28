<?php
/**
 * Strings
 *
 * @package    report
 * @subpackage session
 * @author     Domenico Pontari <fairsayan@gmail.com>
 * @copyright  Institute of Tropical Medicine - Antwerp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['session:view'] = 'View session report';
$string['cm_report'] = 'Activity course session report';
$string['user_report'] = 'User session report';
$string['detail_report'] = 'Detailed session report';
$string['pluginname'] = 'Session report';

$string['starttime'] = 'Start time';
$string['endtime'] = 'End time';
$string['duration'] = 'Duration';

$string['activities_table'] = 'Activities table';
$string['users_table'] = 'Users table';

$string['sessions'] = 'Sessions';
$string['choosesessions'] = 'Choose which sessions you want to see';
$string['never_calculated'] = 'Last update: never calculated or nothing to report';
$string['last_update'] = 'Last update';

$string['download_excel'] = 'Download excel version';
$string['course_activity'] = 'Course activity';
$string['all_students'] = 'All students';

$string['online'] = 'online';
$string['online_users'] = 'Online users';
$string['last_log'] = 'Last log';
$string['offline'] = 'offline';
$string['all_session_types'] = 'All types of session';
$string['session_type'] = 'Session type';
$string['see_details'] = '(see details)';

$string['timeout'] = 'Timeout';
$string['description_timeout'] = 'Timeout for expiration of a session';
$string['extratime'] = 'Extra time';
$string['description_extratime'] = 'Minutes added to each session after the last log';
$string['reset_link'] = 'Reset link';
$string['description_reset'] = <<<EOD
<a href="$CFG->wwwroot/report/session/reset.php">Rebuild all sessions</a>.
Be careful, when you delete all previous sessions, a cron task will start and it could take a lot of resources.

EOD;
$string['reset_done'] = "All previous online sessions are deleted";

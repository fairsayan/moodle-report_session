<?php
/**
 * Reset all 
 *
 * @package    report
 * @subpackage session
 * @author     Domenico Pontari <fairsayan@gmail.com>
 * @copyright  Institute of tropical medicine Antwerp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
require('../../config.php');
require_once($CFG->dirroot.'/report/session/locallib.php');

$PAGE->set_url('/report/session/reset.php');

require_login();
$context = get_context_instance(CONTEXT_SYSTEM);
require_capability('moodle/site:config', $context);

$DB->delete_records('report_session');
$DB->delete_records('report_session_last_log_time');

redirect ($CFG->wwwroot, get_string('reset_done', 'report_session'), 100);

<?php
/**
 * Force log update for a specific user
 *
 * @package    report
 * @subpackage session
 * @author     Domenico Pontari <fairsayan@gmail.com>
 * @copyright  Institute of tropical medicine Antwerp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
require('../../config.php');
require_once($CFG->dirroot.'/report/session/locallib.php');

$userid = required_param('id', PARAM_INT);       // userid id

require_login();
report_session_update_user_sessions ($userid, false);

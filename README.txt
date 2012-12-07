Session report for Moodle 
===================================
Author:     Domenico Pontari <fairsayan@gmail.com>
License:    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
Copyright:  2012 Institute of Tropical Medicine - Antwerp
Repository: https://github.com/fairsayan/moodle-report_session

Summary
=======
Session report provides a list of online sessions built from Moodle's logs for each enrolled user in a course.
Online sessions are created considering a timeout of 30 minutes: if there isn't any log
for 30 minutes the session is considered closed and an extra time of 15 minutes is attached
later the last log in the session. Timeout and extra time can be configured.

Extensions
==========
Session report can work together Offline Session Register to display offline sessions, too.
When you install both offline session register and session report, you will be able to display reports
for online and offline sessions together and download them.
For more information about offline session register visit:
https://github.com/fairsayan/moodle-report_session   
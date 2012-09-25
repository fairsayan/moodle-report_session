<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('reportsession_timeout', get_string('timeout', 'report_session'),
                       get_string('description_timeout', 'report_session'), 30, PARAM_INT));

    $settings->add(new admin_setting_configtext('reportsession_extratime', get_string('extratime', 'report_session'),
                       get_string('description_extratime', 'report_session'), 15, PARAM_INT));
}

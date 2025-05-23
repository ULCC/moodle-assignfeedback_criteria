<?php

require('../../../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$id = required_param('id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'assign');

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/assign:view', $context);
$delete = optional_param('delete',0, PARAM_INT);
if ($delete == 1 && $id <> 0){
$DB->delete_records('assignfeedback_criteria_cs',array('id' => $id));
header('location:listcriteriasets.php?cmid='.$cmid);
}
$record = $DB->get_record('assignfeedback_criteria_cs',array('id' => $id));

$content = '<table class="generaltable"><tr><td colspan="2">Are you sure you want to delete the Criteria set "'.$record->name.'" ?</td></tr><tr>';
$content .= '<td ><form><input type="hidden" value="'.$id.'" name="id"><input type="hidden" value="'.$cmid.'" name="cmid"><input type="hidden" value="1" name="delete"><input type="submit" name="submit" value="Delete"/></td><td><a href="listcriteriasets.php?cmid='.$cmid.'">No, return to list of criteriasets</a></td></tr></table>';
$PAGE->set_context(get_system_context());
$PAGE->set_url($CFG->wwwroot."/mod/assign/feedback/criteria/listcriteriasets.php");
$PAGE->navbar->ignore_active();
$PAGE->navbar->add("Criteria Feedback", new moodle_url("/mod/assign/feedback/criteria/listcriteriasets.php?cmid=".$cmid));
$PAGE->set_pagelayout('base');
$PAGE->set_title("Criteria Feedback");
$PAGE->set_heading("Manage Criteria Feedback Sets");
//$PAGE->set_cacheable(false);
/*if($USER->id <> 15){
$output = '<h4>Sorry, the workload planning system is currently closed for upgrade work.</h4>';
}*/
echo $OUTPUT->header();
echo $content;
echo $OUTPUT->footer();




?>
 
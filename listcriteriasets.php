<?php

require('../../../../config.php');
$cmid = required_param('cmid', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'assign');

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/assign:view', $context);

if ($criteriasets = $DB->get_records_sql("select cs.*, u.firstname, u.lastname from mdl_assignfeedback_criteria_cs cs, mdl_user u where u.id = cs.owner and (cs.owner = ".$USER->id." or cs.shared = 1) order by cs.id")){
$content = '<table class="generaltable"><tr><td colspan="6"><a href="addcriteriaset.php?cmid='.$cmid.'"><button class="button" style="float:right;">Add New Criteria Set</button></a></td></tr><tr><th>Name of Criteria Set</th><th>Owner</th><th>Shared</th><th></th><th></th><th></th></tr>';
$shared = array('No', 'Yes');
foreach($criteriasets as $criteriaset){
if ($DB->get_records('assignfeedback_criteria', array('csid' => $criteriaset->id))){
$content .= "<tr><td>".$criteriaset->name."</td><td>".$criteriaset->firstname.' '.$criteriaset->lastname.'</td><td>'.$shared[$criteriaset->shared].'</td><td>In Use</td></td><td>In Use</td><td><a href="addCriteriaset.php?id='.$criteriaset->id.'&action=copy&cmid='.$cmid.'">Copy</a></td></tr>';
} else {
$content .= "<tr><td>".$criteriaset->name."</td><td>".$criteriaset->firstname.' '.$criteriaset->lastname.'</td><td>'.$shared[$criteriaset->shared].'</td><td><a href="addCriteriaset.php?id='.$criteriaset->id.'&cmid='.$cmid.'">Edit</a></td></td><td><a href="delCriteriaset.php?id='.$criteriaset->id.'&cmid='.$cmid.'">Delete</a></td><td><a href="addCriteriaset.php?id='.$criteriaset->id.'&action=copy&cmid='.$cmid.'">Copy</a></td></tr>';
}
}
$content .= '</table>';
} else {
$content = '<h5>No Criteria Sets have been added yet</h5><a href="addcriteriaset.php?cmid='.$cmid.'"><button class="button" style="float:right;">Add New Criteria Set</button></a>';
}

$systemcontext	=	context_system::instance();

$PAGE->set_context($systemcontext);
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
//echo '<script type="text/javascript">function setPageWidth(){ document.getElementById("region-main").style.width = "99%";}</script>';
//echo '<script type="text/javascript">document.getElementById("region-main").style.width = "95%";</script>';
//echo '<div onload="setPageWidth()"';
	//print($output);
	//echo '</div >';
	echo $content;
echo $OUTPUT->footer();

?>
 
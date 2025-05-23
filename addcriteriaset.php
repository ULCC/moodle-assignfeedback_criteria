<?php

require('../../../../config.php');
require_once('criteriaform.php');
$cmid = required_param('cmid', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'assign');

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/assign:view', $context);
$mform = new edit_criteria_form(null,array('id' => $_GET['id']));
$toform = new stdClass();
$toform->cmid = $cmid;
if (isset($_GET['id'])){
$criteriaset = $DB->get_record('assignfeedback_criteria_cs',array('id' => $_GET['id']));


if($_GET['action'] <> 'copy'){
$toform->id = $criteriaset->id;
}
$toform->name = $criteriaset->name;
$toform->shared = $criteriaset->shared;
$toform->scoring = $criteriaset->scoring;
$criteria = json_decode($criteriaset->criteria);
$toform->criteriatype = $criteriaset->criteriatype;
//var_dump($criteria);
//die;
$i = 0;
$categories = array('Generic', 'Module Specific');
foreach ($categories as $cat){
foreach ($criteria as $key => $criterium){
if($criterium->category === $cat){
$category[$i] = $criterium->category;
$descriptionpos[$i] = $criterium->descriptionpos;
$descriptionneg[$i] = $criterium->descriptionneg;
$i++;
}
}
}


$toform->category = $category;
$toform->descriptionpos = $descriptionpos;
$toform->descriptionneg = $descriptionneg;
}
$mform->set_data($toform);

if ($mform->is_cancelled()) {
    
    redirect('listcriteriasets.php?cmid='.$cmid);
 
} else if ($fromform = $mform->get_data()) {
    // This branch is where you process validated data.
  $criteriaset = new stdClass();
  $criteriaset->name = $fromform->name;
  $criteriaset->shared = $fromform->shared;
  $criteriaset->criteriatype = $fromform->criteriatype;
  $criteriaset->scoring = $fromform->scoring;
  $criteriaset->owner = $USER->id;
  $criteriaset->private = 0;
  $criteria = array();
  $i = 0;
  foreach($fromform->category as $key => $category){
  if($fromform->descriptionpos[$key] <> '' || $fromform->descriptionneg[$key] <> ''){
  $criteria[$i]['category'] = $category;
  $criteria[$i]['descriptionpos'] = $fromform->descriptionpos[$key];
  $criteria[$i]['descriptionneg'] = $fromform->descriptionneg[$key];
  $i++;
 }
  }

  $criteriaset->criteria = json_encode($criteria);
  //var_dump($fromform);
  //var_dump($criteria);
  //die;
 if ($fromform->id <> ''){
 $criteriaset->id = $fromform->id;
 $DB->update_record('assignfeedback_criteria_cs',$criteriaset);
} else {
$id = $DB->insert_record('assignfeedback_criteria_cs',$criteriaset);
} 
 // Typically you finish up by redirecting to somewhere where the user
    // can see what they did.
    redirect('listcriteriasets.php?cmid='.$cmid);
}
$PAGE->set_context(get_system_context());
$PAGE->set_url($CFG->wwwroot."/mod/assign/feedback/criteria/criteriasets.php");
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
	$mform->display();
echo $OUTPUT->footer();




?>
 
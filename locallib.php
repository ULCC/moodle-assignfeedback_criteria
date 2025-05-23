<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the definition for the library class for the criteria feedback plugin.
 *
 * @package   assignfeedback_criteria
 * @copyright 2017 Lancaster University {@link http://www.lancaster.ac.uk/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Tony Butler <a.butler4@lancaster.ac.uk>
 */

defined('MOODLE_INTERNAL') || die();

// File area for criteria feedback.
define('ASSIGNFEEDBACK_criteria_FILEAREA', 'feedback_criteria');

/**
 * Library class for criteria feedback plugin extending feedback plugin base class.
 *
 * @package   assignfeedback_criteria
 * @copyright 2017 Lancaster University {@link http://www.lancaster.ac.uk/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Tony Butler <a.butler4@lancaster.ac.uk>
 */
class assign_feedback_criteria extends assign_feedback_plugin {

    /**
     * @var context The assignment context for this plugin instance.
     */
    private $context = null;

    /**
     * @var string The JSON encoded criteria configured for this plugin instance.
     */
    private $criteria = '';

    /**
     * Get the name of the criteria feedback plugin.
     *
     * @return string The name of the plugin.
     */
    public function get_name() {
        return get_string('pluginname', 'assignfeedback_criteria');
    }

    /**
     * Cache and return the assignment context for this plugin instance.
     *
     * @return context
     */
    private function get_context() {
        if (isset($this->context)) {
            return $this->context;
        }

        return $this->assignment->get_context();
    }

    /**
     * Cache and return the JSON encoded criteria configured for this assignment instance.
     *
     * @return string The configured criteria.
     */
    private function get_criteria_config() {
        if ($this->criteria) {
            return $this->criteria;
        }

        if ($criteria = $this->get_config('criteriaset')) {
            $this->criteria = $criteria;
        }

        return $this->criteria;
    }

    /**
     * Fetch the saved criteria set with the given id.
     *
     * @param int $criteriasetid The id of the criteria set to fetch.
     * @return stdClass|bool The criteria set or false.
     */
    private function get_criteria_set($criteriasetid) {
        global $DB;

        // A criteria set id must be provided.
        if (empty($criteriasetid)) {
            return false;
        }

        return $DB->get_record('assignfeedback_criteria_cs', array('id' => $criteriasetid));
    }

    /**
     * Return the criteria for the given criteria set id, or those configured for this assignment instance.
     *
     * @param int $criteriasetid The id of a saved criteria set (optional).
     * @return array The criteria data.
     */
    public function get_criteria() {
	global $DB;
        if (!$criteriasetid = $this->get_config('criteriaset')) {
		                  
				return array();
				}
				if (!$criteriaset = $DB->get_record('assignfeedback_criteria_cs', array('id' => $criteriasetid))) {
            		
			return array();
			
			}
         /*   $criteria = $criteriaset->criteria;
        } else {
            if (!$criteria = $this->get_criteria_config()) {
                return array();
            }
        }*/
 
        return $criteriaset;
    }

    /**
     * Check whether the criterion with the given key has any feedback associated with it.
     *
     * @param int $criterion Key of criterion to check.
     * @return bool True if any feedback exists.
     */
    private function is_criterion_used($criterion) {
        global $DB;

        $assignment = $this->assignment->get_instance()->id;

        return $DB->record_exists('assignfeedback_criteria', array('assignment' => $assignment, 'criterion' => $criterion));
    }

    /**
     * Return all saved criteria sets that the current user can manage (or copy into this assignment instance).
     *
     * @param bool $includeshared Include shared criteria sets owned by other users (for copying only).
     * @return array|string Grouped array of criteria sets, or an error string.
     */
    public function get_criteria_sets_for_user($includeshared = false) {
        global $DB, $USER;

        // Return an error if user is copying, and any criteria are configured and have feedback already.
        if ($criteria = $this->get_criteria() and $includeshared) {
            foreach ($criteria as $key => $criterion) {
                if ($this->is_criterion_used($key)) {
                    return get_string('criteriaused', 'assignfeedback_criteria');
                }
            }
        }

        $criteriasets = array();
        $params = array('user' => $USER->id);

        $select = "name <> ''";
        if (!has_capability('moodle/site:config', context_system::instance()) || $includeshared) {
            $select .= " AND owner = :user";
        }
        $ownedsets = $DB->get_records_select('assignfeedback_criteria_cs', $select, $params, 'name', 'id, name, shared');
        if ($ownedsets) {
            foreach ($ownedsets as $ownedset) {
                $ownedset->shared = (bool) $ownedset->shared;
            }
            $criteriasets['ownedsets'] = array_values($ownedsets);
        }

        if ($includeshared) {
            $select = "name <> '' AND owner <> :user AND shared = 1";
            $sharedsets = $DB->get_records_select('assignfeedback_criteria_cs', $select, $params, 'name', 'id, name');
            if ($sharedsets) {
                $criteriasets['sharedsets'] = array_values($sharedsets);
            }
        }

        return $criteriasets;
    }

    /**
     * Save a new named criteria set to copy later, using the data provided.
     *
     * @param string $name A name for the new criteria set.
     * @param array $criteria The criteria data.
     * @param bool $shared Whether the new criteria set should be shared.
     * @return bool Success status.
     */
    public function save_criteria_set($name, $criteria, $shared) {
        global $DB, $PAGE, $USER;

        // Make sure user has the appropriate permissions to save.
        if (!has_capability('assignfeedback/criteria:manageowncriteriasets', $PAGE->context)) {
            return false;
        }

        // Write the new criteria set to the database.
        $criteriaset = new stdClass();
        $criteriaset->name = ucfirst($name);
        $criteriaset->name_lc = strtolower($name);
        $criteriaset->criteria = json_encode($criteria);
        $criteriaset->owner = $USER->id;
        $criteriaset->shared = $shared;

        return $DB->insert_record('assignfeedback_criteria_cs', $criteriaset);
    }

    /**
     * Update one or more criteria set attributes (e.g. name, visibility) with the new values provided.
     *
     * @param int $criteriasetid The id of the criteria set to be updated.
     * @param array $updates The key/value pairs of attributes to be updated.
     * @return bool Success status.
     */
    public function update_criteria_set($criteriasetid, $updates) {
        global $DB, $PAGE;

        // A criteria set id must be provided.
        if (empty($criteriasetid)) {
            return false;
        }

        // Make sure user has the appropriate permissions to update.
        if (!has_capability('assignfeedback/criteria:manageowncriteriasets', $PAGE->context)) {
            return false;
        }

        // Write the updates to the database.
        $criteriaset = new stdClass();
        $criteriaset->id = $criteriasetid;
        foreach ($updates as $key => $value) {
            $criteriaset->$key = $value;
        }

        return $DB->update_record('assignfeedback_criteria_cs', $criteriaset);
    }

    /**
     * Delete the saved criteria set with the given id.
     *
     * @param int $criteriasetid The id of the criteria set to be deleted.
     * @return bool Success status.
     */
    public function delete_criteria_set($criteriasetid) {
        global $DB, $PAGE, $USER;

        // A criteria set id must be provided.
        if (empty($criteriasetid)) {
            return false;
        }

        if (!$criteriaset = $this->get_criteria_set($criteriasetid)) {
            return false;
        }

        // Make sure user has the appropriate permissions to delete.
        if (!has_capability('moodle/site:config', context_system::instance())) {
            if ($criteriaset->owner != $USER->id ||
                    !has_capability('assignfeedback/criteria:manageowncriteriasets', $PAGE->context)) {
                return false;
            }
        }

        return $DB->delete_records('assignfeedback_criteria_cs', array('id' => $criteriasetid));
    }

    /**
     * Get the criteria feedback comments from the database.
     *
     * @param int $gradeid The grade id.
     * @return array An array of feedback comments for the given grade, indexed by criterion key.
	 
	      */
		  
		  
// get scoring set for UG / PG (at present) built this way to allow putting into table in future	
	  
    public function get_scoring_set($scoringid){
	 $scoringsets = array();
	 $scoringsets[0] = array(1 => 'Excellent/1st class', 2 => 'Very good/2.1', 3 => 'Good/2.2', 4 => 'Adequate/3rd', 5 => 'Fail');
	 $scoringsets[1] = array(1 => 'Distinction (> 70%)', 2 => 'Good Pass (60-70%)', 3 => 'Pass (50-60%)', 4 => 'Borderline Fail (40-50%)', 5 => 'Fail (< 40%)');
	 return $scoringsets[$scoringid];
	 }
		  
    public function get_feedback_scores($gradeid) {
        global $DB;

        if (!$scores = $DB->get_records('assignfeedback_criteria', array('grade' => $gradeid))) {
            return array();
        }

        $feedback = array();
        foreach ($scores as $score) {
            $feedback[$score->criterion] = $score;
        }

        return $feedback;
    }

    /**
     * Has the criteria feedback been modified?
     *
     * @param stdClass $grade The grade object.
     * @param stdClass $data Data from the form submission.
     * @return bool True if the criteria feedback has been modified, else false.
     */
    public function is_feedback_modified(stdClass $grade, stdClass $data) {
      
	
        if (!$criteria = $this->get_criteria()) {
            return false;
        }

        $keys = array_keys((array)$criteria);
		
        if ($grade) {
            $feedbackscores = $this->get_feedback_scores($grade->id);
        }

        foreach ($keys as $key) {
            $scoretext = !empty($feedbackscores[$key]) ? $feedbackscores[$key]->score : '';

            $score = 'assignfeedbackcriteria_score_' . $key;
            if (isset($data->{$score}) && $scoretext != $data->{$score}) {
                return true;
            }
        }

        return true;
    }

    /**
     * Return a list of the text fields that can be imported/exported by this plugin.
     *
     * @return array An array of field names and descriptions (name => description, ... ).
     */
    public function get_editor_fields() {
        if (!$criteria = $this->get_criteria()) {
            return array();
        }

        $fields = array();
        foreach ($criteria as $criterion) {
            $fields[$criterion->name] = $criterion;
        }

        return $fields;
    }

    /**
     * Get the saved text content for the editor.
     *
     * @param string $name The field name.
     * @param int $gradeid The grade id.
     * @return string The text content.
     */
    public function get_editor_text($name, $gradeid) {
        if (!$criteria = $this->get_criteria()) {
            return '';
        }

        $feedbackscores = $this->get_feedback_scores($gradeid);

        foreach ($criteria as $key => $criterion) {
            if ($name == $criterion->name) {
                $scoretext = !empty($feedbackscores[$key]) ? $feedbackscores[$key]->score : '';
                return $scoretext;
            }
        }

        return '';
    }



    /**
     * Override to indicate a plugin supports quickgrading.
     *
     * @return bool True if the plugin supports quickgrading.
     */
    public function supports_quickgrading() {
        return false;
    }

    /**
     * Get quickgrading form elements as html.
     *
     * @param int $userid The user id in the table this quickgrading element relates to.
     * @param stdClass|null $grade The grade data - may be null if there are no grades for this user (yet).
     * @return string An html string containing the html form elements required for quickgrading.
     */
    public function get_quickgrading_html($userid, $grade) {
        return false;
    }

    /**
     * Has the plugin quickgrading form element been modified in the current form submission?
     *
     * @param int $userid The user id in the table this quickgrading element relates to.
     * @param stdClass $grade The grade object.
     * @return bool True if the quickgrading form element has been modified, else false.
     */
    public function is_quickgrading_modified($userid, $grade) {
         
        return false;
    }

    /**
     * Save quickgrading changes.
     *
     * @param int $userid The user id in the table this quickgrading element relates to.
     * @param stdClass $grade The grade.
     * @return bool True if the grade changes were saved correctly.
     */
    public function save_quickgrading_changes($userid, $grade) {
        
        return false;
    }

    /**
     * Save the settings for the criteria feedback plugin instance.
     *
     * @param stdClass $data The data from the config form.
     * @return bool True.
     */
    public function save_settings(stdClass $data) {
        /*$criteria = array();
        $criteriaset = $data->criteriaset;
        foreach ($data->assignfeedback_criteria_critname as $key => $critname) {
            $critname = trim($critname);
            // Ignore unnamed criteria.
            if (empty($critname)) {
                continue;
            }
            $criterion = new stdClass();
            $criterion->name = $critname;
            $criterion->descriptionpos = trim($data->assignfeedback_criteria_critdescpos[$key]);
			$criterion->descriptionneg = trim($data->assignfeedback_criteria_critdescneg[$key]);
            $criteria[] = $criterion;
        }*/
        $criteriaset = $data->criteriaset;
        if (!empty($criteriaset)) {
            // Update the config if criteria have changed.
            if ($criteriaset != $this->get_criteria_config()) {
                $this->set_config('criteriaset', $criteriaset);
            }
        } 
		
		
		/*else {
            // If no criteria are configured, unset the config and disable the plugin instance.
            if ($this->get_criteria_config()) {
                $this->set_config('criteriaset', '');
            }
            $this->disable();
        }*/
		/*
		if (empty($data->enabled)){
		 if ($this->get_criteria_config()) {
                $this->set_config('enabled', '0');
            }
            $this->disable();

		}*/
		$this->set_config('enabled', !empty($data->assignfeedback_criteria_enabled));
        return true;
    }

    /**
     * Get the settings for the criteria feedback plugin instance.
     *
     * @param MoodleQuickForm $mform The form to add elements to.
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $PAGE, $DB, $USER;
		$criteriasets = $DB->get_records_sql_menu('select id, name from mdl_assignfeedback_criteria_cs where owner = '.$USER->id.' or shared = 1');
 $attributes = '';
 $message = "";
if (isset($PAGE->cm->instance)){
if ($result = $DB->get_records('assignfeedback_criteria',array('assignment' => $PAGE->cm->instance))){
$attributes = ' disabled '; 
$message = '<p><strong>The criteriaset cannot be changed once feedback has been given</strong></p>';
}
$criteriaset = $DB->get_record('assign_plugin_config', array('plugin' => 'criteria', 'assignment' => $PAGE->cm->instance, 'name' => 'criteriaset'));
}
        $mform->addElement('select','criteriaset','Select a criteria set',$criteriasets,$attributes);
		if (isset($criteriaset->value)){
		$mform->setDefault('criteriaset',$criteriaset->value);
		}
		$mform->disabledIf('criteriaset', 'assignfeedback_criteria_enabled');
		$mform->addElement('html', $message);
		if (isset($PAGE->cm->id)){
		$mform->addElement('html','<div style="float:right;margin-right:50px;"><a href="../mod/assign/feedback/criteria/listcriteriasets.php?cmid='.$PAGE->cm->id.'" title="Add or edit a criteria set" target="_new">Add or Edit a Criteria Set</a></div>');
        }
		/*$mform->addElement('header', 'assignfeedback_criteria_criteria', get_string('criteria', 'assignfeedback_criteria'));

        $criteriasetbutton = $mform->addElement('button', 'assignfeedback_criteria_critset',
                get_string('criteriasetusesaved', 'assignfeedback_criteria'));
        $criteriasetbutton->setLabel(get_string('criteriaset', 'assignfeedback_criteria'));
        $mform->addHelpButton('assignfeedback_criteria_critset', 'criteriaset', 'assignfeedback_criteria');

        // Check if there are any saved criteria sets that can be used here.
        if (is_array($criteriasets = $this->get_criteria_sets_for_user(true))) {
            $params = array_merge(array(
                'contextid'  => $PAGE->context->id,
                'manage'     => false,
                'canpublish' => false
            ), $criteriasets);
            $PAGE->requires->js_call_amd('assignfeedback_criteria/criteriasets', 'init', $params);
        } else {
            $mform->updateElementAttr('assignfeedback_criteria_critset',
                    array('title' => $criteriasets, 'disabled' => 'disabled'));
            $criterialocked = true;
        }

        $critelements = array();
        $critelements[] = $mform->createElement('text', 'assignfeedback_criteria_critname',
                get_string('criterionname', 'assignfeedback_criteria'), array('size' => '64', 'maxlength' => '255'));
        $critelements[] = $mform->createElement('textarea', 'assignfeedback_criteria_critdescpos',
                'Positive Criteria', 'rows="4" cols="64"');
				$critelements[] = $mform->createElement('textarea', 'assignfeedback_criteria_critdescneg',
                'Negative Criteria', 'rows="4" cols="64"');

        if ($criteria = $this->get_criteria()) {
            $mform->setExpanded('assignfeedback_criteria_criteria', true);
            if (!empty($criterialocked)) {
                $critrepeats = count($criteria);
            } else {
                $critrepeats = count($criteria) + 2;
            }
        } else {
            $critrepeats = 5;
        }

        $critoptions = array();
        $critoptions['assignfeedback_criteria_critname']['rule'] = array(null, 'maxlength', 255, 'client');
        $critoptions['assignfeedback_criteria_critname']['type'] = PARAM_TEXT;
        $critoptions['assignfeedback_criteria_critdescpos']['type'] = PARAM_TEXT;
        $critoptions['assignfeedback_criteria_critdescneg']['type'] = PARAM_TEXT;
        $critfields = $this->repeat_elements($mform, $critelements, $critrepeats, $critoptions, 'assignfeedback_criteria_repeats',
                'assignfeedback_criteria_critfieldsadd', 3, get_string('criteriafieldsadd', 'assignfeedback_criteria'), true);
        $lastfield = 'assignfeedback_criteria_critname[' . ($critfields - 1) . ']';
        $mform->disabledIf('assignfeedback_criteria_critfieldsadd', $lastfield, 'eq', '');
        $mform->addHelpButton('assignfeedback_criteria_critname[0]', 'criteria', 'assignfeedback_criteria');
        if (!empty($criterialocked)) {
            $mform->updateElementAttr('assignfeedback_criteria_critfieldsadd',
                    array('title' => get_string('criteriaused', 'assignfeedback_criteria'), 'disabled' => 'disabled'));
        }

        if (has_capability('assignfeedback/criteria:manageowncriteriasets', $PAGE->context)) {
            // Enable users to save criteria sets for use in other assignments.
            $criteriasetsavebutton = $mform->addElement('button', 'assignfeedback_criteria_critsetsave',
                    get_string('criteriasetsave', 'assignfeedback_criteria'));
            $criteriasetsavebutton->setLabel(get_string('criteriasetsave', 'assignfeedback_criteria'));
            $mform->addHelpButton('assignfeedback_criteria_critsetsave', 'criteriasetsave', 'assignfeedback_criteria');
            $mform->setAdvanced('assignfeedback_criteria_critsetsave');
            $mform->disabledIf('assignfeedback_criteria_critsetsave', 'assignfeedback_criteria_critname[0]', 'eq', '');
            $params = array(
                'contextid'  => $PAGE->context->id,
                'canpublish' => has_capability('assignfeedback/criteria:publishcriteriasets', $PAGE->context)
            );
            $PAGE->requires->js_call_amd('assignfeedback_criteria/criteriasetsave', 'init', $params);

            // Enable users to manage their saved criteria sets.
            $criteriasetsmanagebutton = $mform->addElement('button', 'assignfeedback_criteria_critsetsmanage',
                    get_string('criteriasetsmanage', 'assignfeedback_criteria'));
            $criteriasetsmanagebutton->setLabel(get_string('criteriasetsmanage', 'assignfeedback_criteria'));
            $mform->addHelpButton('assignfeedback_criteria_critsetsmanage', 'criteriasetsmanage', 'assignfeedback_criteria');
            $mform->setAdvanced('assignfeedback_criteria_critsetsmanage');
            $params = array(
                'contextid'  => $PAGE->context->id,
                'manage'     => true,
                'canpublish' => has_capability('assignfeedback/criteria:publishcriteriasets', $PAGE->context)
            );
            if ($criteriasets = $this->get_criteria_sets_for_user(false)) {
                $params = array_merge($params, $criteriasets);
            } else {
                $mform->updateElementAttr('assignfeedback_criteria_critsetsmanage', array('disabled' => 'disabled'));
            }
            $PAGE->requires->js_call_amd('assignfeedback_criteria/criteriasets', 'init', $params);
        }

        // If this is not the last feedback plugin, add a section to contain the settings for the rest.
        if (!$this->is_last()) {
            $mform->addElement('header', 'feedbacksettings', get_string('feedbacksettings', 'assign'));
        }

        // Pre-populate fields with existing data and lock as appropriate.
        $elements = array();
        foreach ($criteria as $key => $criterion) {
            $mform->setDefault('assignfeedback_criteria_critname[' . $key . ']', $criterion->name);
            $mform->setDefault('assignfeedback_criteria_critdescpos[' . $key . ']', $criterion->descriptionpos);
			$mform->setDefault('assignfeedback_criteria_critdescneg[' . $key . ']', $criterion->descriptionneg);
            if (!empty($criterialocked)) {
                $elements[] = 'assignfeedback_criteria_critname[' . $key . ']';
                $elements[] = 'assignfeedback_criteria_critdescpos[' . $key . ']';
				$elements[] = 'assignfeedback_criteria_critdescneg[' . $key . ']';
            }
        }
        if (!empty($criterialocked) && !empty($elements)) {
            $mform->freeze($elements);
            $mform->updateElementAttr($elements, array('title' => get_string('criteriaused', 'assignfeedback_criteria')));
        }*/
    }

    /**
     * Helper used by {@link repeat_elements()}.
     *
     * @param int $i the index of this element.
     * @param HTML_QuickForm_element $elementclone
     * @param array $namecloned array of names
     */
    private function repeat_elements_fix_clone($i, $elementclone, &$namecloned) {
        $name = $elementclone->getName();
        $namecloned[] = $name;

        if (!empty($name)) {
            $elementclone->setName($name."[$i]");
        }

        if (is_a($elementclone, 'HTML_QuickForm_header')) {
            $value = $elementclone->_text;
            $elementclone->setValue(str_replace('{$a}', ($i + 1), $value));
        } else if (is_a($elementclone, 'HTML_QuickForm_submit') || is_a($elementclone, 'HTML_QuickForm_button')) {
            $elementclone->setValue(str_replace('{$a}', ($i + 1), $elementclone->getValue()));
        } else {
            $value = $elementclone->getLabel();
            $elementclone->setLabel(str_replace('{$a}', ($i + 1), $value));
        }
    }

    /**
     * Method to add a repeating group of elements to a form.
     *
     * @param MoodleQuickForm $mform The form to add elements to.
     * @param array $elementobjs Array of elements or groups of elements that are to be repeated
     * @param int $repeats no of times to repeat elements initially
     * @param array $options a nested array. The first array key is the element name.
     *    the second array key is the type of option to set, and depend on that option,
     *    the value takes different forms.
     *         'default'    - default value to set. Can include '{$a}' which is replaced by the repeat number.
     *         'type'       - PARAM_* type.
     *         'helpbutton' - array containing the helpbutton params.
     *         'disabledif' - array containing the disabledIf() arguments after the element name.
     *         'rule'       - array containing the addRule arguments after the element name.
     *         'expanded'   - whether this section of the form should be expanded by default. (Name be a header element.)
     *         'advanced'   - whether this element is hidden by 'Show more ...'.
     * @param string $repeathiddenname name for hidden element storing no of repeats in this form
     * @param string $addfieldsname name for button to add more fields
     * @param int $addfieldsno how many fields to add at a time
     * @param string $addstring name of button, {$a} is replaced by no of blanks that will be added.
     * @param bool $addbuttoninside if true, don't call closeHeaderBefore($addfieldsname). Default false.
     * @return int no of repeats of element in this page
     */
    private function repeat_elements(&$mform, $elementobjs, $repeats, $options, $repeathiddenname, $addfieldsname, $addfieldsno = 5,
                                     $addstring = null, $addbuttoninside = false) {
        if ($addstring === null) {
            $addstring = get_string('addfields', 'form', $addfieldsno);
        } else {
            $addstring = str_ireplace('{$a}', $addfieldsno, $addstring);
        }
        $repeats = optional_param($repeathiddenname, $repeats, PARAM_INT);
        $addfields = optional_param($addfieldsname, '', PARAM_TEXT);
        if (!empty($addfields)) {
            $repeats += $addfieldsno;
        }
        $mform->registerNoSubmitButton($addfieldsname);
        $mform->addElement('hidden', $repeathiddenname, $repeats);
        $mform->setType($repeathiddenname, PARAM_INT);
        // Value not to be overridden by submitted value.
        $mform->setConstants(array($repeathiddenname => $repeats));
        $namecloned = array();
        for ($i = 0; $i < $repeats; $i++) {
            foreach ($elementobjs as $elementobj) {
                $elementclone = fullclone($elementobj);
                $this->repeat_elements_fix_clone($i, $elementclone, $namecloned);

                if ($elementclone instanceof HTML_QuickForm_group && !$elementclone->_appendName) {
                    foreach ($elementclone->getElements() as $el) {
                        $this->repeat_elements_fix_clone($i, $el, $namecloned);
                    }
                    $elementclone->setLabel(str_replace('{$a}', $i + 1, $elementclone->getLabel()));
                }

                $mform->addElement($elementclone);
            }
        }
        for ($i = 0; $i < $repeats; $i++) {
            foreach ($options as $elementname => $elementoptions) {
                $pos = strpos($elementname, '[');
                if ($pos !== false) {
                    $realelementname = substr($elementname, 0, $pos) . "[$i]";
                    $realelementname .= substr($elementname, $pos);
                } else {
                    $realelementname = $elementname . "[$i]";
                }
                foreach ($elementoptions as $option => $params) {
                    switch ($option){
                        case 'default':
                            $mform->setDefault($realelementname, str_replace('{$a}', $i + 1, $params));
                            break;
                        case 'helpbutton':
                            $params = array_merge(array($realelementname), $params);
                            call_user_func_array(array(&$mform, 'addHelpButton'), $params);
                            break;
                        case 'disabledif':
                            foreach ($namecloned as $num => $name) {
                                if ($params[0] == $name) {
                                    $params[0] = $params[0] . "[$i]";
                                    break;
                                }
                            }
                            $params = array_merge(array($realelementname), $params);
                            call_user_func_array(array(&$mform, 'disabledIf'), $params);
                            break;
                        case 'rule':
                            if (is_string($params)) {
                                $params = array(null, $params, null, 'client');
                            }
                            $params = array_merge(array($realelementname), $params);
                            call_user_func_array(array(&$mform, 'addRule'), $params);
                            break;

                        case 'type':
                            $mform->setType($realelementname, $params);
                            break;

                        case 'expanded':
                            $mform->setExpanded($realelementname, $params);
                            break;

                        case 'advanced':
                            $mform->setAdvanced($realelementname, $params);
                            break;
                    }
                }
            }
        }
        $mform->addElement('submit', $addfieldsname, $addstring);

        if (!$addbuttoninside) {
            $mform->closeHeaderBefore($addfieldsname);
        }

        return $repeats;
    }

    /**
     * Get form elements for the grading page.
     *
     * @param stdClass|null $grade The grade object.
     * @param MoodleQuickForm $mform The form to add elements to.
     * @param stdClass $data The feedback data.
     * @param int $userid Unused param from parent method.
     * @return bool True if elements were added to the form.
     */
    public function get_form_elements_for_user($grade, MoodleQuickForm $mform, stdClass $data, $userid) {
	global $DB;
        if (!$criteriarecord = $this->get_criteria()) {
            return false;
        }
		$scoring_set = $this->get_scoring_set($criteriarecord->scoring);
		//$criteriasetid = $this->get_config('criteriaset');
		//$criteriarecord =$DB->get_record('assignfeedback_criteria_cs', array('id' => $criteriasetid));
		$criteria = json_decode($criteriarecord->criteria);
		
        if ($grade) {
            $feedbackscores = $this->get_feedback_scores($grade->id);
        }
		
		         $html = '<table class="table table-bordered critfeedback" style="background-color:#fff;"><tr style="background-color:#f0f0f0;"><td colspan="7" ><h5>Criteria Feedback</h5></td></tr><tr><td colspan="7" style="text-align:center;"><strong>';
		foreach($scoring_set as $key => $scoredef){
		$html .= $key.' = '.$scoredef.' &nbsp;&nbsp;';
		}
		$html .= '</strong></td></tr>';
		         $mform->addElement('html',$html);
		 $category = "";
		 //$mform->addElement('html','<style> .fitemtitle {display:none !important;}</style>');
		 if ($criteriarecord->criteriatype <> 0){
		 $poswidth ='35%';
		 $poscolspan = '';
		 $negcol = '<td style="width:35%"></td>';
		 } else {
		 $poswidth = '70%';
		 $poscolspan = 'colspan="2"';
		 $negcol = '';
		 }
		 
	


        foreach ($criteria as $key => $criterion) {
            $editor = 'assignfeedbackcriteria_score_' . $key;
            if (!empty($feedbackscores[$key]) && !empty($feedbackscores[$key]->score)) {
                $data->{$editor} = $feedbackscores[$key]->score;
               // $data->{$editor}['format'] = $feedbackcomments[$key]->commentformat;
            }
			if($criterion->category <> $category){
			$category = $criterion->category;
			
			$mform->addElement('html','<tr style="background-color:#f0f0f0;"><td '.$poscolspan.' style="width:'.$poswidth.'"><h5>'.$criterion->category.'</h5></td><td style="width:6%;text-align:center;vertical-align:middle;font-weight:bold;">1</td><td style="width:6%;text-align:center;vertical-align:middle;font-weight:bold;">2</td><td style="width:6%;text-align:center;vertical-align:middle;font-weight:bold;">3</td><td style="width:6%;text-align:center;vertical-align:middle;font-weight:bold;">4</td><td style="width:6%;text-align:center;vertical-align:middle;font-weight:bold;">5</td>'.$negcol.'</tr>');
			}
            /*$mform->addElement('editor', $editor, get_string('criteriontitle', 'assignfeedback_criteria',
                    array('name' => $criterion->name, 'desc' => $criterion->description)));*/
					$mform->addElement('html','<tr><td '.$poscolspan.' style="width:'.$poswidth.'">'.$criterion->descriptionpos.'</td><td style="width:6%">');
					
					
$mform->addElement('radio', $editor, '', '', 1);
$mform->addElement('html','</td><td style="width:6%">');
$mform->addElement('radio', $editor, '', '', 2);
$mform->addElement('html','</td><td style="width:6%">');
$mform->addElement('radio', $editor, '', '', 3);
$mform->addElement('html','</td><td style="width:6%">');
$mform->addElement('radio', $editor, '', '', 4);
$mform->addElement('html','</td><td style="width:6%">');
$mform->addElement('radio', $editor, '', '', 5);

$feedbackvalue  =   (!empty($feedbackscores[$key]->id)) ?   $feedbackscores[$key]->id : '';

$mform->addElement('hidden', 'assignfeedbackcriteria_id_' . $key, $feedbackvalue);

//$mform->addGroup($radioarray, 'assignfeedbackcriteria_score_array_'. $key, '', array(' '), false);
if ($criteriarecord->criteriatype <> 0){
	$mform->addElement('html','</td><td style="width:35%;" >'.$criterion->descriptionneg.'</td></tr>');		
} else {
	$mform->addElement('html','</td></tr>');	
	}
            $mform->setType($editor, PARAM_RAW);
        }
		$mform->addElement('html','</table>');
         $mform->addElement('hidden','csid',$criteriarecord->id);
		//$mform->setDefault('csid', 0);
		
        return true;
    }

    /**
     * Save the feedback content to the database.
     *
     * @param stdClass $grade The grade object.
     * @param stdClass $data The feedback data.
     * @return bool Success status.
     */
    public function save(stdClass $grade, stdClass $data) {
        global $DB;
	if (!$this->is_feedback_modified($grade, $data)){
		return false;
	}
	
	
        if (!$criteriarecord = $this->get_criteria()) {
            return false;
        }

        $feedbackscores = $this->get_feedback_scores($grade->id);
$criteria = json_decode($criteriarecord->criteria);
        foreach ($criteria as $key => $criterion) {
            $score = 'assignfeedbackcriteria_score_' . $key;
			$critid =  'assignfeedbackcriteria_id_' . $key;
			
           /* if ( !empty($data->{$critid})) {
                $feedbackscore = new stdClass();
				$feedbackscore->id = $data->{$critid};
                $feedbackscore->criterion = $key;
                $feedbackscore->score = $data->{$score};
				$feedbackscore->csid = $data->csid;
				$feedbackscore->grade = $grade->id;
                $feedbackscore->assignment = $this->assignment->get_instance()->id;
                $DB->update_record('assignfeedback_criteria', $feedbackscore);
            } else 
				
			*/if (!empty($data->{$score})) {
                $feedbackscore = new stdClass();
                $feedbackscore->criterion = $key;
                $feedbackscore->score = $data->{$score};
				$feedbackscore->csid = $data->csid;
                //$feedbackcomment->commentformat = $data->{$editor}['format'];
                $feedbackscore->grade = $grade->id;
				//Fix for duplication of records - shouldn't happen anymore!
                $feedbackscore->assignment = $this->assignment->get_instance()->id;
				if ($DB->count_records('assignfeedback_criteria',array('assignment' => $feedbackscore->assignment, 'grade' => $feedbackscore->grade, 'csid' => $feedbackscore->csid, 'criterion' => $feedbackscore->criterion)) > 1){
					$DB->delete_records('assignfeedback_criteria',array('assignment' => $feedbackscore->assignment, 'grade' => $feedbackscore->grade, 'csid' => $feedbackscore->csid, 'criterion' => $feedbackscore->criterion));
					}
				if($recordid = $DB->get_field('assignfeedback_criteria','id',array('assignment' => $feedbackscore->assignment, 'grade' => $feedbackscore->grade, 'csid' => $feedbackscore->csid, 'criterion' => $feedbackscore->criterion))){
                $feedbackscore->id = $recordid;
                  $DB->update_record('assignfeedback_criteria', $feedbackscore);
				} else {
					$DB->insert_record('assignfeedback_criteria', $feedbackscore);
				}
            }
        }

        return true;
    }

    /**
     * Display the feedback in the feedback summary.
     *
     * @param stdClass $grade The grade object.
     * @param bool $showviewlink Set to true to show a link to view the full feedback.
     * @return string The feedback to display.
     */
    public function view_summary(stdClass $grade, &$showviewlink) {
	//return '';
	global $DB, $PAGE, $USER;
//Horrible kludge to hide Criteria feedback in assignment table without changing core code!
//Automatically set user preferences to have that column hidden.
$contextid = $this->assignment->get_context()->id;
// Findout which plugin in the table is the criteria feedback
$assess_plugins = $DB->count_records_sql("select count(apc.id) from {assign_plugin_config} apc, {course_modules} cm where cm.id = ".$PAGE->cm->id. " and apc.assignment = cm.instance and apc.name = 'enabled' and apc.value = 1 and apc.subtype = 'assignsubmission' ");
$assign_plugins = $DB->get_records_sql("SELECT apc.*, cp.value as sortorder FROM {assign_plugin_config} apc, {course_modules} cm, {config_plugins} cp WHERE cm.id = ".$PAGE->cm->id. " and apc.assignment = cm.instance and apc.name = 'enabled' and apc.value = 1 and apc.subtype = 'assignfeedback' and cp.plugin = concat('assignfeedback_',apc.plugin) and cp.name = 'sortorder' order by sortorder;");
$i = 0;
foreach($assign_plugins as $assign_plugin){
	if ($assign_plugin->plugin === 'criteria'){
		$plugin_number = $i;
	}
	$i++;
}
$plugin_number = 'plugin'.($plugin_number + $assess_plugins);
if($user_prefs = $DB->get_records('user_preferences',array('userid' => $USER->id,'name' => 'flextable_mod_assign_grading-'.$contextid))){	
$user_prefs=current($user_prefs);
$pref_values =(json_decode($user_prefs->value));
//var_dump($pref_values);
//die;
$pref_values->collapse = array($plugin_number => true);
$user_prefs->value = json_encode($pref_values);

$DB->update_record('user_preferences',$user_prefs);
} else {
	$user_prefs = new stdClass();
	$user_prefs->userid = $USER->id;
	$user_prefs->name = 'flextable_mod_assign_grading-'.$contextid;
	$pref_values = new stdClass();
	$collapse = new stdClass();
	$collapse->{$plugin_number} = true;
	$pref_values->collapse = $collapse;
	$pref_values->sortby = array();
	$pref_values->i_first = '';
	$pref_values->i_last = '';
	$pref_values->textsort = array();
	$user_prefs->value = json_encode($pref_values);
$DB->insert_record('user_preferences',$user_prefs);
	
}

//var_dump($user_prefs);
//die;
//$table_prefs = $DB->get_record('user_preferences', array(
 if (!$criteriarecord = $this->get_criteria()) {
            //return 'Summary';
		
        }
		$scoring_set = $this->get_scoring_set($criteriarecord->scoring);
		$criteria = json_decode($criteriarecord->criteria);
$disclaimer = <<<EOT
NOTE: The following tick boxes are for general formative guidance on your work, helping you to identify strong and weak points with reference both to generic and module-specific marking criteria. Your final mark depends on a number of factors and does not merely represent a sum of the ticks below; you should also carefully read the examiner’s comments below and comments written on/digitally added to your submission.
Regarding marking criteria, see Section 5 in the Programme Handbook for further details.<br/><strong>
EOT;
foreach($scoring_set as $key => $scoredef){
		$disclaimer .= $key.' = '.$scoredef.' &nbsp;&nbsp;';
		}

        $feedbackscores = $this->get_feedback_scores($grade->id);
        //$gradetext = array('Dummy Element','Excellent/1st Class','Very Good/2.1','Good/2.2','Adequate/3rd','Fail');
        $text = '</table><table class="table table-bordered assignfbtable" style="width:830px !important;"><tr><td colspan="7">'.$disclaimer.'</td></tr>';
		$category = "";
        foreach ($criteria as $key => $criterion) {
	   if($criterion->category <> $category){
	   $text .= '<tr style="backgroud-color:#eee;"><th width=40%>'.$criterion->category.'</th><th width="4%" style="text-align:center;">1</th><th width="4%" style="text-align:center;">2</th><th width="4%" style="text-align:center;">3</th><th width="4%" style="text-align:center;" >4</th><th width="4%" style="text-align:center;" >5</th><th width="40%" ></th></tr>';
	  $category = $criterion->category;
	  }
		$text .= '<tr><td>'.$criterion->descriptionpos.'</td>';
            
			$i = 1;
			while ($i <= 5){
			if ( !empty($feedbackscores[$key]->score) && intval($feedbackscores[$key]->score) === $i){
			$text .= '<td style="background-color:green;text-align:center;color:#fff;vertical-align:middle;" title="'.$scoring_set[$i].'">'.$i.'</a></td>';
			} else {
			$text .= '<td style="text-align:center;color:#fff;vertical-align:middle;"></td>';
			}
			$i++;
			}
			
			$text .= '<td>'.$criterion->descriptionneg.'</td></tr>';
			}
			$text .= '</table><table class="generaltable">';
                /*$desc = format_text($criterion->description, FORMAT_PLAIN, array('context' => $this->get_context()));
                $crit = get_string('criteriontitle', 'assignfeedback_criteria',
                        array('name' => format_string($criterion->name), 'desc' => ''));
                $comment = format_text($feedbackcomments[$key]->commenttext, $feedbackcomments[$key]->commentformat,
                        array('context' => $this->get_context()));
                $text .= html_writer::div($crit, '', array('title' => $desc));
                $text .= html_writer::div($comment, 'well-small');*/
//var_dump($grade);            
//var_dump($text);
//die;


        return $text;
    }

    /**
     * Display the feedback in the feedback table.
     *
     * @param stdClass $grade The grade object.
     * @return string The feedback to display.
     */
	
    public function view(stdClass $grade) {
	//return 'FULL!';
		global $DB, $PAGE, $USER;
//Horrible kludge to hide Criteria feedback in assignment table without changing core code!
//Automatically set user preferences to have that column hidden.
$contextid = $this->assignment->get_context()->id;
// Findout which plugin in the table is the criteria feedback
$assess_plugins = $DB->count_records_sql("select count(apc.id) from {assign_plugin_config} apc, {course_modules} cm where cm.id = ".$PAGE->cm->id. " and apc.assignment = cm.instance and apc.name = 'enabled' and apc.value = 1 and apc.subtype = 'assignsubmission' ");
$assign_plugins = $DB->get_records_sql("SELECT apc.*, cp.value as sortorder FROM {assign_plugin_config} apc, {course_modules} cm, {config_plugins} cp WHERE cm.id = ".$PAGE->cm->id. " and apc.assignment = cm.instance and apc.name = 'enabled' and apc.value = 1 and apc.subtype = 'assignfeedback' and cp.plugin = concat('assignfeedback_',apc.plugin) and cp.name = 'sortorder' order by sortorder;");
$i = 0;
foreach($assign_plugins as $assign_plugin){
	if ($assign_plugin->plugin === 'criteria'){
		$plugin_number = $i;
	}
	$i++;
}
$plugin_number = 'plugin'.($plugin_number + $assess_plugins);
if($user_prefs = $DB->get_records('user_preferences',array('userid' => $USER->id,'name' => 'flextable_mod_assign_grading-'.$contextid))){	
$user_prefs=current($user_prefs);
$pref_values =(json_decode($user_prefs->value));
//var_dump($pref_values);
//die;
$pref_values->collapse = array($plugin_number => true);
$user_prefs->value = json_encode($pref_values);

$DB->update_record('user_preferences',$user_prefs);
} else {
	$user_prefs = new stdClass();
	$user_prefs->userid = $USER->id;
	$user_prefs->name = 'flextable_mod_assign_grading-'.$contextid;
	$pref_values = new stdClass();
	$collapse = new stdClass();
	$collapse->{$plugin_number} = true;
	$pref_values->collapse = $collapse;
	$pref_values->sortby = array();
	$pref_values->i_first = '';
	$pref_values->i_last = '';
	$pref_values->textsort = array();
	$user_prefs->value = json_encode($pref_values);
$DB->insert_record('user_preferences',$user_prefs);
	
}

if (!$criteriarecord = $this->get_criteria()) {
            
        }
		$scoring_set = $this->get_scoring_set($criteriarecord->scoring);
		$criteria = json_decode($criteriarecord->criteria);
$disclaimer = <<<EOT
NOTE: The following tick boxes are for general formative guidance on your work, helping you to identify strong and weak points with reference both to generic and module-specific marking criteria. Your final mark depends on a number of factors and does not merely represent a sum of the ticks below; you should also carefully read the examiner’s comments below and comments written on/digitally added to your submission.
Regarding marking criteria, see Section 5 in the Programme Handbook for further details.<br/><strong>
EOT;
foreach($scoring_set as $key => $scoredef){
		$disclaimer .= $key.' = '.$scoredef.' &nbsp;&nbsp;';
		}

        $feedbackscores = $this->get_feedback_scores($grade->id);
        //$gradetext = array('Dummy Element','Excellent/1st Class','Very Good/2.1','Good/2.2','Adequate/3rd','Fail');
        $text = '</table><table class="table table-bordered" style="width:800px;"><tr><td colspan="7">'.$disclaimer.'</td></tr>';
		$category = "";
        foreach ($criteria as $key => $criterion) {
	   if($criterion->category <> $category){
	   $text .= '<tr style="backgroud-color:#eee;"><th width=40%>'.$criterion->category.'</th><th width="4%" style="text-align:center;">1</th><th width="4%" style="text-align:center;">2</th><th width="4%" style="text-align:center;">3</th><th width="4%" style="text-align:center;" >4</th><th width="4%" style="text-align:center;" >5</th><th width="40%" ></th></tr>';
	  $category = $criterion->category;
	  }
		$text .= '<tr><td>'.$criterion->descriptionpos.'</td>';
            
			$i = 1;
			while ($i <= 5){
			if ( !empty($feedbackscores[$key]->score) && intval($feedbackscores[$key]->score) === $i){
			$text .= '<td style="background-color:green;text-align:center;color:#fff;vertical-align:middle;" title="'.$scoring_set[$i].'">'.$i.'</a></td>';
			} else {
			$text .= '<td style="text-align:center;color:#fff;vertical-align:middle;"></td>';
			}
			$i++;
			}
			
			$text .= '<td>'.$criterion->descriptionneg.'</td></tr>';
			}
			$text .= '</table><table class="generaltable">';
                /*$desc = format_text($criterion->description, FORMAT_PLAIN, array('context' => $this->get_context()));
                $crit = get_string('criteriontitle', 'assignfeedback_criteria',
                        array('name' => format_string($criterion->name), 'desc' => ''));
                $comment = format_text($feedbackcomments[$key]->commenttext, $feedbackcomments[$key]->commentformat,
                        array('context' => $this->get_context()));
                $text .= html_writer::div($crit, '', array('title' => $desc));
                $text .= html_writer::div($comment, 'well-small');*/
//var_dump($grade);            
//var_dump($text);
//die;

        return $text;
    }

    /**
     * Produce a list of files suitable for export that represent this feedback.
     *
     * @param stdClass $grade The user grade.
     * @param stdClass $user The user record.
     * @return array An array of files indexed by filename.
     */
    public function get_files(stdClass $grade, stdClass $user) {
        

        return array();
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type and version.
     *
     * @param string $type Old assignment subtype.
     * @param int $version Old assignment version.
     * @return bool True if upgrade is possible.
     */
    public function can_upgrade($type, $version) {
        return false;
    }

    /**
     * The assignment has been deleted - cleanup.
     *
     * @return bool True.
     */
    public function delete_instance() {
        global $DB;

        // Will throw exception on failure.
        $DB->delete_records('assignfeedback_criteria', array('assignment' => $this->assignment->get_instance()->id));

        return true;
    }

    /**
     * Automatically enable the criteria feedback plugin.
     *
     * @return bool True.
     */
    public function is_enabled() {
	if(!empty($this->get_config('enabled'))){
        return true;
		} else {
		return false;
		}
    }

    /**
     * Automatically hide the checkbox for the criteria feedback plugin.
     *
     * @return bool False.
     */
    public function is_configurable() {
        return true;
    }

    /**
     * Returns true if there is no criteria feedback for the given grade.
     *
     * @param stdClass $grade The grade object.
     * @return bool True if no feedback.
     */
    public function is_empty(stdClass $grade) {
        return $this->view($grade) == '';
    }
    public function hide_gradebook() {
        return true;
    }
    /**
     * Should the criteria feedback plugin include a column in the grading table or a row on the summary page?
     *
     * @return bool True if any criteria are defined.
     */
    public function has_user_summary() {
        if (empty($this->get_criteria())) {
            return false;
        }

        return true;
    }

    /**
     * Return a description of external params suitable for uploading criteria feedback from a webservice.
     *
     * @return array Description of external params.
     */
    public function get_external_parameters() {
        if (!$criteria = $this->get_criteria()) {
            return array();
        }

        $editors = array();
        foreach ($criteria as $key => $criterion) {
            $editorparams = array(
                'text' => new external_value(PARAM_RAW, 'The comment text for criterion: ' . $criterion->name),
                'format' => new external_value(PARAM_INT, 'The comment format for criterion: ' . $criterion->name)
            );
            $editorstructure = new external_single_structure($editorparams, 'Criterion ' . $key . ' editor structure',
                    VALUE_OPTIONAL);
            $editors['assignfeedbackcriteria_editor_' . $key] = $editorstructure;
        }

        return $editors;
    }

    /**
     * Return the plugin config for external functions.
     *
     * @return array The list of settings.
     */
    public function get_config_for_external() {
        return (array) $this->get_config();
    }

}
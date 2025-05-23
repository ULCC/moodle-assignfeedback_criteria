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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');


require_once($CFG->libdir.'/formslib.php');


/**
 * Assignment grade form
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_criteria_form extends moodleform {
    
    
    public function definition() {
	global $USER, $DB;
        $mform = $this->_form;
		$mform->addElement('hidden','id');
        $mform->setType('id', PARAM_INT);
		$mform->addElement('hidden','cmid');
        $mform->setType('cmid', PARAM_INT);
		$mform->addElement('text','name','Name of Criteria Set');
        $mform->setType('name', PARAM_RAW);
		$mform->addElement('selectyesno','shared','Shared - If not shared will only be visible to you');
		$mform->addElement('select','criteriatype','Single or Dual Criteria',array(0 => 'Single (positive only)', 1 => ' Dual (Positive and Negative)'));
		$mform->addElement('select','scoring', 'Select UG or PG Scoring',array( 0 => 'UG', 1 => 'PG'));
		$mform->setDefault('scoring', 1);
		$mform->addElement('html', '<style>.felement {float: left;	margin-left: 0 !important;} .fitemtitle {max-width: 150px; text-align: left !important; }</style>');
				$mform->addElement('html','<table class="generaltable">');
                 $repeatarray = array();
				 $repeatarray[] = $mform->createElement('html','<tr><td>');
        $repeatarray[] = $mform->createElement('select','category','Select Category',array('Generic' =>'Generic','Module Specific' => 'Module Specific'));
		$repeatarray[] = $mform->createElement('html','</td><td>');
        $repeatarray[] = $mform->createElement('textarea','descriptionpos','Positive Criteria','wrap="virtual" rows="4" cols="100"');
		$repeatarray[] = $mform->createElement('html','</td><td>');
        $repeatarray[] = $mform->createElement('textarea','descriptionneg','Negative Criteria','wrap="virtual" rows="4" cols="100"');
		$repeatarray[] = $mform->createElement('html','</td></tr>');
 
        if ($this->_customdata['id']){
		$record = $DB->get_record('assignfeedback_criteria_cs',array('id' => $this->_customdata['id']));	
		
            $repeatno = count(json_decode($record->criteria));
            $repeatno += 2;
			
        } else {
            $repeatno = 10;
        }
 
        $repeateloptions = array();
        //$repeateloptions['category']['default'] = 'Generic';
		$repeateloptions['category']['type'] = PARAM_CLEANHTML;
        $repeateloptions['definitionpos']['type'] = PARAM_CLEANHTML;
        $repeateloptions['definitionneg']['type'] = PARAM_CLEANHTML;
        $repeateloptions['option']['helpbutton'] = array('choiceoptions', 'choice');
                $this->repeat_elements($repeatarray, $repeatno,
                    $repeateloptions, 'option_repeats', 'option_add_fields', 3, null, true);
					for ($x = 0; $x < $repeatno; $x++){
					$mform->hideIf('descriptionneg['.$x.']','criteriatype', 'eq', 0);
					}
					$mform->addElement('html','<tr><td id="repeat_button_td" colspan="3"></td></tr></table>');
					$mform->addElement('html','<script>$("#fitem_id_option_add_fields").appendTo("#repeat_button_td");</script>');
					
					$this->add_action_buttons();
    }

    
}

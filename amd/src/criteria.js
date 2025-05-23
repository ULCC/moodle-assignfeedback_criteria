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
 * Displays the criteria data for the selected criteria set in the criteria feedback criteria set selector modal.
 *
 * See template: assignfeedback_criteria/criteria
 *
 * @module     assignfeedback_criteria/criteria
 * @class      criteria
 * @package    assignfeedback_criteria
 * @copyright  2017 Lancaster University {@link http://www.lancaster.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Tony Butler <a.butler4@lancaster.ac.uk>
 */
define(['jquery', 'core/templates'], function($, templates) {
    return {
        /**
         * Init function.
         *
         * @param {object[]} criteriaData An array of data objects for the criteria returned.
         */
        init: function(criteriaData) {
            var context = {
                criteriaData: criteriaData
            };

            templates.render('assignfeedback_criteria/criteria', context);
        }
    };
});

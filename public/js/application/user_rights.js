/**! compression tag for ftp-deployment */

/**
 * Finds a checkbox within the given parent.
 * 
 * @param $parent
 *            the parent element to search in.
 * @returns the checkbox, if found; null otherwise.
 */
function findCheckBox($parent) {
    'use strict';

    if ($parent) {
        return $parent.findExists(":checkbox");
    }
    return null;
}

/**
 * Gets all checkboxes of the edit table.
 * 
 * @returns the checkboxes.
 */
function getAllCheckboxes() {
    'use strict';

    return $('#table-edit :checkbox');
}

/**
 * Updates the checkboxes.
 * 
 * @param $parent
 *            the parent element to iterate over.
 * @param callback
 *            the callback function used to find the check box within the
 *            current element.
 * @returns void
 */
function updateCheckBoxes($parent, callback) {
    'use strict';

    let $inputs = [];
    let checked = 0;
    let unchecked = 0;

    // get values
    $parent.each(function () {
        const $input = callback($(this));
        if ($input) {
            $inputs.push($input);
            if ($input.isChecked()) {
                checked++;
            } else {
                unchecked++;
            }
        }
    });

    // update
    const newValue = checked === 0 || checked !== 0 && unchecked !== 0;
    $.each($inputs, function () {
        $(this).setChecked(newValue);
    });
}

/**
 * Updates all checkbox values.
 * 
 * @param checked
 *            true if checked, false if unchecked.
 */
function updateAllCheckboxes(checked) {
    'use strict';
    getAllCheckboxes().setChecked(checked);
}

/**
 * Toggle all checkbox values.
 */
function toggle() {
    'use strict';

    getAllCheckboxes().toggleChecked();
}

/**
 * Sets default values.
 */
function defaultValues() {
    'use strict';

    getAllCheckboxes().each(function () {
        const $this = $(this);
        $this.setChecked($this.data('default') || false);
    });

    const $overwrite = $('#user_rights_overwrite');
    if ($overwrite.length) {
        $overwrite.setChecked($overwrite.data('default') || false);
        onOverwriteClick($overwrite);
    }
}

/**
 * Handles the column header click event.
 * 
 * @param $element
 *            the column header.
 */
function onColumnClick($element) {
    'use strict';

    // get column index
    const $cell = $element.parents("th");
    const $row = $cell.parents("tr");
    const index = $row.children("th").index($cell);

    // update
    updateCheckBoxes($("#table-edit tbody tr"), function ($elem) {
        const $parent = $elem.find("td").eq(index);
        return findCheckBox($parent);
    });
}

/**
 * Handles the first cell row click event.
 * 
 * @param $element
 *            the first cell of the row.
 */
function onRowClick($element) {
    'use strict';

    // get row
    const $row = $element.parents("tr");

    // update
    updateCheckBoxes($row.children("td"), findCheckBox);
}

/**
 * Handles the overwrite click event.
 */
function onOverwriteClick($element) {
    'use strict';

    const disabled = !$element.isChecked();
    getAllCheckboxes().attr('disabled', disabled);
    $(".btn-col, .btn-row").attr('disabled', disabled);
    $("#all, #none, #toggle").attr('disabled', disabled);
}

/**
 * Ready function
 */
(function ($) {
    'use strict';

    // validation
    $("form").initValidator();

    // bind events
    $("#all").on("click", function () {
        updateAllCheckboxes(true);
    });
    $("#none").on("click", function () {
        updateAllCheckboxes(false);
    });
    $("#toggle").on("click", function () {
        toggle();
    });
    $("#default").on("click", function () {
        defaultValues();
    });
    $(".btn-col").on("click", function () {
        onColumnClick($(this));
    });
    $(".btn-row").on("click", function () {
        onRowClick($(this));
    });
    $('#user_rights_overwrite').on("click", function () {
        onOverwriteClick($(this));
    });

}(jQuery));
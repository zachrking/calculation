/**! compression tag for ftp-deployment */

/* globals Toaster */

/**
 * Fomats the given value.
 * 
 * @param number
 *            value - the value to format.
 * @returns string - the formatted value.
 */
function formatValue(value) {
    'use strict';

    const $form = $('#edit-form');
    if (!$form.formatter) {
        $form.formatter = new Intl.NumberFormat('de-CH', {
            'minimumFractionDigits': 2,
            'maximumFractionDigits': 2
        });
    }
    return $form.formatter.format(value);
}

/**
 * Update the given input.
 * 
 * @param string
 *            id - the input identifier.
 * @param number
 *            value - the value to set.
 */
function updateValue(id, value) {
    'use strict';
    $('#' + id).text(formatValue(value));
}

/**
 * Reset all plain text to 0.
 */
function resetValues() {
    'use strict';
    const value = formatValue(0);
    $('#edit-form .form-control-plaintext').text(value);
}

/**
 * Display an error message.
 * 
 * @param string
 *            message - the message to display.
 */
function showError(message) {
    'use strict';
    resetValues();
    const title = $('.card-title').text();
    const options = $('#flashbags').data();
    Toaster.danger(message, title, options);
}

/**
 * Send form to server and update UI.
 * 
 * @param form
 *            form - the submitted form.
 */
function update(form) {
    'use strict';

    // get items
    const items = $('#table-edit > tbody > tr:not(.d-none) .item-input:checked').map(function () {
        return Number.parseInt($(this).attr('value'), 10);
    }).get();
    if (items.length === 0) {
        $('.task-items-empty').removeClass('d-none');
        resetValues();
        return;
    }
    $('.task-items-empty').addClass('d-none');

    // get data
    const $form = $(form);
    const url = $form.data('url');
    const data = {
        'id': $('#task').intVal(),
        'quantity': $('#quantity').floatVal(),
        'items': items
    };

    // cancel send
    if ($form.jqXHR) {
        $form.jqXHR.abort();
        $form.jqXHR = null;
    }
    // send
    $form.jqXHR = $.post(url, data, function (response) {
        if (response.result) {
            // update
            response.results.forEach(function (item) {
                updateValue('value_' + item.id, item.value);
                updateValue('amount_' + item.id, item.amount);
            });
            updateValue('overall', response.overall);
        } else {
            showError(response.message || $form.data('failed'));
        }
    }).fail(function (jqXHR, textStatus) {
        if (textStatus !== 'abort') {
            showError($form.data('failed'));
        }
    });
}

/**
 * Handles input change.
 */
function onInputChanged() {
    'use strict';

    // submit
    $("#edit-form").submit();
}

/**
 * Handles task change.
 */
function onTaskChanged() {
    'use strict';

    // toogle rows visibility
    const id = $('#task').intVal();
    const selector = '[task-id="' + id + '"]';
    $('.item-row' + selector).removeClass('d-none');
    $('.item-row:not(' + selector + ')').addClass('d-none');

    // task items?
    const empty = $('.item-row:not(.d-none)').length === 0;
    $('.row-table').toggleClass('d-none', empty);
    $('.row-empty').toggleClass('d-none', !empty);

    // submit
    if (!empty) {
        $("#edit-form").submit();
    }
}

/**
 * Ready function
 */
(function ($) {
    'use strict';

    // attach handlers
    $('#task').on('input', function () {
        $(this).updateTimer(onTaskChanged, 250);
    });
    $('#quantity').on('input', function () {
        $(this).updateTimer(onInputChanged, 250);
    }).inputNumberFormat();
    $('.item-input').on('change', function () {
        $(this).updateTimer(onInputChanged, 250);
    });

    // validation
    const options = {
        submitHandler: function (form) {
            update(form);
        },
        rules: {
            quantity: {
                greaterThanValue: 0
            }
        }
    };
    $('form').initValidator(options);

    // update
    onInputChanged();

}(jQuery));
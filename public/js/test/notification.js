/**! compression tag for ftp-deployment */

/* globals Toaster */

/**
 * Display a notification.
 *
 * @param {string} type - the message type.
 * @param {string} title - the message title.
 * @param {Object} options - the custom options.
 */
function notify(type, title, options) {
    'use strict';

    // get random text
    const url = $('#position').data('random');
    $.getJSON(url, function (response) {
        if (response.result) {
            const message = '<p class="m-0 p-0">{0}</p>'.format(response.content);
            Toaster[type](message, title, options);
        } else {
            Toaster.danger("Impossible d'afficher une notification.", $('.card-title').text(), options);
        }
    }).fail(function () {
        Toaster.danger("Impossible d'afficher une notification.", $('.card-title').text(), options);
    });
}

/**
 * Display a random notification.
 */
function random() {
    'use strict';
    const button = $('.btn-notify').toArray().randomElement();
    $(button).trigger('click'); //.focus();
}

/**
 * Document ready function
 */
(function ($) {
    'use strict';

    // default options
    const options = $('#flashbags').data();
    options.onHide = function (settings) {
        window.console.log(settings);
    };

    $('.btn-notify').on('click', function () {
        // options
        options.icon = $('#icon').isChecked();
        options.title = $('#title').isChecked();
        options.position = $('#position').val();
        options.timeout = $('#timeout').intVal();
        options.progress = $('#progress').intVal();
        options.autohide = $('#autohide').isChecked();
        options.displayClose = $('#close').isChecked();
        options.displaySubtitle = $('#subtitle').isChecked();

        // notify
        const type = $(this).data('type');
        const title = options.title ? $(this).text() : null;
        notify(type, title, options);

        // update checkbox style
        const className = 'custom-control-input control-option custom-control-' + type;
        $(":checkbox.custom-control-input").attr('class', className);
    });

    // default values
    $('.btn-default').on('click', function () {
        $('.card-body [data-default]').each(function () {
            const $this = $(this);
            const value = $this.data('default');
            if ($this.is(':checkbox')) {
                $this.setChecked(value);
            } else {
                $this.val(value);
            }
        });
        random();
    });

    $('#position, #timeout, #progress').on('input', function () {
        random();
    });
    $('.control-option').on('click', function () {
        random();
    });

    // random notification
    random();
}(jQuery));

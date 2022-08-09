/**! compression tag for ftp-deployment */

/* globals Toaster */

/**
 * Reset widgets to default values (if any).
 */
function setDefaultValues() {
    'use strict';
    $('#edit-form :input:not(button)[data-default], :checkbox[data-default]').each(function () {
        const $this = $(this);
        const value = $this.data('default');
        if ($this.is(':checkbox')) {
            $this.prop('checked', value);
        } else {
            $this.val(value);
        }
    });

    // special case for radio inputs
    $('#edit-form .form-group[data-default]:has(:radio)').each(function () {
        const $this = $(this);
        const value = $this.data('default');
        $this.find(`:radio[value="${value}"]`).setChecked(true);
    });
}

/**
 * Display a notification.
 */
function displayNotification() {
    'use strict';
    // get random text
    let title = $('.card-title').text();
    const url = $("form").data("random");
    $.getJSON(url, function (response) {
        if (response.result && response.content) {
            // content
            const content = '<p class="m-0 p-0">' + response.content + '</p>';
            // position
            const $position = $("#message_position");
            const oldPosition = $position.data('position');
            const newPosition = $position.val();
            $position.data('position', newPosition);
            // type
            const last = $position.data('type');
            const types = Object.values(Toaster.NotificationTypes);
            const type = types.randomElement(last);
            $position.data('type', type);
            // title
            if (!$('#message_title').isChecked()) {
                title = null;
            }
            // options
            const options = $.extend({}, $("#flashbags").data(), {
                position: newPosition,
                icon: $('#message_icon').isChecked(),
                timeout: $('#message_timeout').intVal(),
                progress: $('#message_progress').intVal(),
                displayClose: $('#message_close').isChecked(),
                displaySubtitle: $('#message_sub_title').isChecked(),
            });
            // remove container is needed
            if (oldPosition && oldPosition !== newPosition) {
                Toaster.removeContainer();
            }
            Toaster.notify(type, content, title, options);
        } else {
            const message = $('form').data('failure');
            Toaster.danger(message, title, $("#flashbags").data());
        }
    }).fail(function () {
        const message = $('form').data('failure');
        Toaster.danger(message, title, $("#flashbags").data());
    });
}

/**
 * Display the customer URL in a blank window.
 */
function displayUrl($url) {
    'use strict';
    if ($url.valid() && $url.val().trim()) {
        const url = $url.val().trim();
        // eslint-disable-next-line
        window.open(url, '_blank');
    } else {
        $url.focus();
    }
}

/**
 * Display the customer mail to.
 */
function displayEmail($email) {
    'use strict';
    if ($email.valid() && $email.val().trim()) {
        window.location.href = 'mailto:' + $email.val().trim();
    } else {
        $email.focus();
    }
}

/**
 * Ready function
 */
(function ($) {
    'use strict';
    // numbers
    $('#default_product_quantity').inputNumberFormat();

    // validation
    $('#edit-form').initValidator({
        inline: true,
        rules: {
            'customer_url': {
                url: true
            }
        }
    });

    // toggle icons and titles
    $('.toggle-icon').on('show.bs.collapse', function () {
        const $prev = $(this).prev();
        $prev.find('i').toggleClass('fa-caret-left fa-caret-down');
        $prev.find('a').attr('title', $prev.data('hide'));
    }).on('hide.bs.collapse', function () {
        const $prev = $(this).prev();
        $prev.find('i').toggleClass('fa-caret-left fa-caret-down');
        $prev.find('a').attr('title', $prev.data('show'));
    }).on('shown.bs.collapse', function () {
        if ($(this).find('.is-invalid').length === 0) {
            $(this).find(':input:first').trigger('focus');
        }
    });

    // add handlers
    $('.btn-default').on('click', function (e) {
        e.preventDefault();
        setDefaultValues();
    });
    $('.btn-notify').on('click', function (e) {
        e.preventDefault();
        displayNotification();
    });

    const $url = $('#customer_url');
    const $urlGroup = $url.parents('.input-group').find('.input-group-url');
    if ($url.length && $urlGroup.length) {
        const handler = function (e) {
            e.preventDefault();
            displayUrl($url);
        };
        $url.on('input', function () {
            $urlGroup.off('click', handler);
            $urlGroup.removeClass('cursor-pointer');
            if ($url.valid()) {
                $urlGroup.on('click', handler);
                $urlGroup.addClass('cursor-pointer');
            }
        });
        $url.trigger('input');
    }

    const $email = $('#customer_email');
    const $emailGroup = $email.parents('.input-group').find('.input-group-email');
    if ($email.length && $emailGroup.length) {
        const handler = function (e) {
            e.preventDefault();
            displayEmail($email);
        };
        $email.on('input', function () {
            $emailGroup.off('click', handler);
            $emailGroup.removeClass('cursor-pointer');
            if ($email.valid()) {
                $emailGroup.on('click', handler);
                $emailGroup.addClass('cursor-pointer');
            }
        });
        $email.trigger('input');
    }

    const $captcha = $('#display_captcha');
    if ($captcha.length) {
        const $strength = $('#strength_level');
        $captcha.on('input', function () {
            const enabled = $captcha.getSelectedOption().intVal();
            if (enabled) {
                $strength.removeAttr('disabled');
            } else {
                $strength.attr('disabled', 'disabled');
            }
        });
        $captcha.trigger('input');
    }
}(jQuery));

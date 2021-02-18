/**! compression tag for ftp-deployment */

/**
 * Ready function
 */
(function ($) {
    'use strict';

    // remove pattern attribute
    $("#username").removeAttr("pattern");

    // delete checkbox handler
    let callback = null;
    const $delete = $("#imageFile_delete");
    if ($delete.length) {
        callback = function ($file) {
            const source = $file.data('src') || '';
            const target = $file.parents('.form-group').find('img').attr('src') || '';
            $delete.setChecked(source !== target);
        };
    }

    // image file handler
    $("#imageFile_file").initFileInput(callback);

    // options
    const urlName = $("form").data("check_name");
    const urlEmail = $("form").data("check_email");
    const options = {
        rules: {
            "fos_user_profile_form[username]": {
                remote: {
                    url: urlName,
                    data: {
                        id: function () {
                            return $("#id").val();
                        },
                        username: function () {
                            return $("#username").val();
                        }
                    }
                }
            },
            "fos_user_profile_form[email]": {
                remote: {
                    url: urlEmail,
                    data: {
                        id: function () {
                            return $("#id").val();
                        },
                        email: function () {
                            return $("#email").val();
                        }
                    }
                }
            }
        }
    };

    // validation
    $("#edit-form").initValidator(options);
}(jQuery));

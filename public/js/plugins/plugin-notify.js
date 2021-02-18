/**! compression tag for ftp-deployment */

/**
 * Ready function
 */
(function (window, $) {
    'use strict';

    /**
     * Shared Instance.
     */
    window.Notifier = {

        // ------------------------
        // Public API
        // ------------------------

        /**
         * Display a notification.
         * 
         * @param {string}
         *            type - The type.
         * @param {string}
         *            message - The message.
         * @param {string}
         *            [title] - The title.
         * @param {Object}
         *            [options] - The options.
         * @returns {jQuery} this instance.
         */
        notify: function (type, message, title, options) {
            // merge options
            const settings = $.extend({}, this.DEFAULTS, options);
            settings.position = this.checkPosition(settings.position);
            settings.type = this.checkType(type);
            settings.message = message;
            settings.title = title;

            // create DOM
            const $container = this.getContainer(settings);
            const $title = this.createTitle(settings);
            const $close = this.createCloseButton(settings);
            const $message = this.createMessage(settings);
            const $alert = this.createAlert(settings);

            // save identifier
            this.containerId = $container.attr('id');

            // add children
            if ($title) {
                $alert.append($title);
            }
            if ($close) {
                $alert.append($close);
            }
            $alert.append($message);
            if (this.isPrepend(settings)) {
                $container.prepend($alert);
            } else {
                $container.append($alert);
            }

            // handle events
            const that = this;
            $alert.on('click', function (e) {
                e.preventDefault();
                that.hideAlert($alert, settings);
            });
            if ($close) {
                $close.on('click', function (e) {
                    e.preventDefault();
                    that.hideAlert($alert, settings);
                });
            }

            // show
            return that.showAlert($alert, settings);
        },

        /**
         * Display a notification with info style.
         * 
         * @param {string}
         *            message - The message.
         * @param {string}
         *            [title] - The title.
         * @param {Object}
         *            [options] - The options.
         * @returns {jQuery} this instance.
         */
        info: function (message, title, options) {
            return this.notify(this.NotificationTypes.INFO, message, title, options);
        },

        /**
         * Display a notification with success style.
         * 
         * @param {string}
         *            message - The message.
         * @param {string}
         *            [title] - The title.
         * @param {Object}
         *            [options] - The options.
         * @returns {jQuery} this instance.
         */
        success: function (message, title, options) {
            return this.notify(this.NotificationTypes.SUCCESS, message, title, options);
        },

        /**
         * Display a notification with warning style.
         * 
         * @param {string}
         *            message - The message.
         * @param {string}
         *            [title] - The title.
         * @param {Object}
         *            [options] - The options.
         * @returns {jQuery} this instance.
         */
        warning: function (message, title, options) {
            return this.notify(this.NotificationTypes.WARNING, message, title, options);
        },

        /**
         * Display a notification with danger style.
         * 
         * @param {string}
         *            message - The message.
         * @param {string}
         *            [title] - The title.
         * @param {Object}
         *            [options] - The options.
         * @returns {jQuery} this instance.
         */
        danger: function (message, title, options) {
            return this.notify(this.NotificationTypes.DANGER, message, title, options);
        },

        /**
         * Display a notification with primary style.
         * 
         * @param {string}
         *            message - The message.
         * @param {string}
         *            [title] - The title.
         * @param {Object}
         *            [options] - The options.
         * @returns {jQuery} this instance.
         */
        primary: function (message, title, options) {
            return this.notify(this.NotificationTypes.PRIMARY, message, title, options);
        },

        /**
         * Display a notification with secondary style.
         * 
         * @param {string}
         *            message - The message.
         * @param {string}
         *            [title] - The title.
         * @param {Object}
         *            [options] - The options.
         * @returns {jQuery} this instance.
         */
        secondary: function (message, title, options) {
            return this.notify(this.NotificationTypes.SECONDARY, message, title, options);
        },

        /**
         * Display a notification with dark style.
         * 
         * @param {string}
         *            message - The message.
         * @param {string}
         *            [title] - The title.
         * @param {Object}
         *            [options] - The options.
         * @returns {jQuery} this instance.
         */
        dark: function (message, title, options) {
            return this.notify(this.NotificationTypes.DARK, message, title, options);
        },

        /**
         * Remove this alerts DIV container.
         * 
         * @returns {jQuery} this instance.
         */
        removeContainer: function () {
            if (this.containerId) {
                $('#' + this.containerId).remove();
                this.containerId = null;
            }
            return this;
        },

        /**
         * The allowed message types.
         */
        NotificationTypes: {
            INFO: 'info',
            SUCCESS: 'success',
            WARNING: 'warning',
            DANGER: 'danger',
            PRIMARY: 'primary',
            SECONDARY: 'secondary',
            DARK: 'dark'
        },

        /**
         * The allowed positions.
         */
        NotificationPositions: {
            TOP_LEFT: 'top-left',
            TOP_CENTER: 'top-center',
            TOP_RIGHT: 'top-right',
            
            CENTER_LEFT: 'center-left',
            CENTER_CENTER: 'center-center',
            CENTER_RIGHT: 'center-right',
            
            BOTTOM_LEFT: 'bottom-left',
            BOTTOM_CENTER: 'bottom-center',
            BOTTOM_RIGHT: 'bottom-right'
        },


        /**
         * The default options.
         */
        DEFAULTS: {
            // the target to append notify container to
            target: 'body',

            // the container identifier
            containerId: 'div_notify_alert_div',

            // the notify width
            containerWidth: 400,

            // the notify position
            position: 'bottom-right',

            // the container margins
            marginTop: 20,
            marginBottom: 10,
            marginLeft: 20,
            marginRight: 20,

            // the show duration in milliseconds
            timeout: 4000,

            // the show method
            showMethod: 'fadeIn',
            showDuration: 'slow',
            showEasing: 'swing',

            // the hide method
            hideMethod: 'fadeOut',
            hideDuration: 'slow',
            hideEasing: 'swing',

            // the close button
            closeButton: true,
            closeText: 'Close',

            // the icon
            icon: null,

            // the notify z-index
            zindex: 3,

            // the classes
            titleClass: 'h6',
            messageClass: null,
        },

        // ------------------------
        // Private API
        // ------------------------
        
        /**
         * Check the type.
         * 
         * @param {string}
         *            type - The type to valdiate.
         * 
         * @returns {string} A valid type.
         */
        checkType: function (type) {
            const types = this.NotificationTypes;
            switch (type) {
            case types.INFO:
            case types.SUCCESS:
            case types.WARNING:
            case types.DANGER:
            case types.PRIMARY:
            case types.SECONDARY:
            case types.DARK:
                return type;
            default:
                return types.INFO;
            }
        },

        /**
         * Check the position type.
         * 
         * @param {string}
         *            type - The position type.
         * 
         * @returns {string} A valid position.
         */
        checkPosition: function (position) {
            const positions = this.NotificationPositions;
            switch (position) {
            case positions.TOP_LEFT:
            case positions.TOP_CENTER:
            case positions.TOP_RIGHT:
            case positions.CENTER_LEFT:
            case positions.CENTER_CENTER:
            case positions.CENTER_RIGHT:
                return position;
            case positions.BOTTOM_LEFT:
            case positions.BOTTOM_CENTER:
            case positions.BOTTOM_RIGHT:
                return position;
            default:
                return positions.BOTTOM_RIGHT;
            }
        },

        /**
         * Returns if notification must be prepend or append to the list; depending of
         * the position.
         * 
         * @param {Object}
         *            [options] - The custom options.
         * 
         * @returns {boolean} true to prepend (top positions), false to append
         *          (bottom positions).
         */
        isPrepend: function (options) {
            const positions = this.NotificationPositions;
            switch (options.position) {
            case positions.TOP_LEFT:
            case positions.TOP_CENTER:
            case positions.TOP_RIGHT:
            case positions.CENTER_LEFT:
            case positions.CENTER_CENTER:
            case positions.CENTER_RIGHT:
                return false;
            default: // BOTTOM_XXX
                return true;
            }
        },

        /**
         * Gets or creates the alerts container div.
         * 
         * @param {Object}
         *            [options] - The custom options.
         * 
         * @returns {jQuery} The alerts container.
         */
        getContainer: function (options) {
            // check if div is already created
            const id = options.containerId;
            let $div = $('#' + id);
            if ($div.length) {
                return $div;
            }

            // global style
            let css = {
                'cursor': 'pointer',
                'position': 'fixed',
                'z-index': options.zindex,
                'min-width': this.toPixel(options.containerWidth),
                'max-width': this.toPixel(options.containerWidth)
            };

            // position
            const positions = this.NotificationPositions;
            switch (options.position) {
            case positions.TOP_LEFT:
                css.top = 0;
                css.left = 0;
                css['margin-top'] = this.toPixel(options.marginTop);
                css['margin-left'] = this.toPixel(options.marginLeft);                
                break;
            case positions.TOP_CENTER:
                css.top = 0;
                css.left = '50%';
                css['margin-top'] = this.toPixel(options.marginTop);
                css.transform = 'translateX(-50%)';
                break;
            case positions.TOP_RIGHT:
                css.top = 0;
                css.right = 0;
                css['margin-top'] = this.toPixel(options.marginTop);
                css['margin-right'] = this.toPixel(options.marginRight);
                break;
            
            case positions.CENTER_LEFT:
                css.top = '50%';
                css.left = 0;
                css.transform ='translateY(-50%)';
                css['margin-left'] = this.toPixel(options.marginLeft);
                break;
            case positions.CENTER_CENTER:
                css.top = '50%';
                css.left = '50%';
                css.transform = 'translate(-50%,-50%)';
                break;
            case positions.CENTER_RIGHT:
                css.top = '50%';
                css.right = 0;
                css.transform ='translateY(-50%)';
                css['margin-right'] = this.toPixel(options.marginRight);
                break;
                
            case positions.BOTTOM_LEFT:
                css.bottom = 0;
                css.left = 0;
                css['margin-bottom'] = this.toPixel(options.marginBottom);
                css['margin-left'] = this.toPixel(options.marginLeft);
                break;
            case positions.BOTTOM_CENTER:
                css.bottom = 0;
                css.left = '50%';
                css['margin-bottom'] = this.toPixel(options.marginBottom);
                css.transform = 'translateX(-50%)';
                break;  
            case positions.BOTTOM_RIGHT:
            default:
                css.bottom = 0;
                css.right = 0;
                css['margin-bottom'] = this.toPixel(options.marginBottom);
                css['margin-right'] = this.toPixel(options.marginRight);
                break;
            }

            // target
            const $target = $(options.target);

            // create and append
            return $('<div/>', {
                id: id,
                css: css
            }).appendTo($target);
        },

        /**
         * Creates the icon title.
         * 
         * @param {Object}
         *            options - The custom options.
         * 
         * @returns {jQuery} The icon.
         */
        createIcon: function (options) {
            let clazz = 'mr-2 fas fa-lg fa-';
            switch (options.type) {
            case this.NotificationTypes.INFO:
                clazz += 'info-circle';
                break;
            case this.NotificationTypes.SUCCESS:
                clazz += 'check-circle';
                break;
            case this.NotificationTypes.WARNING:
                clazz += 'exclamation-circle';
                break;
            case this.NotificationTypes.DANGER:
                clazz += 'exclamation-triangle';
                break;
            default:
                clazz += 'check-circle';
                break;
            }

            // create
            return $('<i/>', {
                'class': clazz,
                'aria-hidden': true
            });
        },

        /**
         * Creates the div title.
         * 
         * @param {Object}
         *            options - The custom options.
         * 
         * @returns {jQuery} The div title or null if no title.
         */
        createTitle: function (options) {
            if (options.title) {
                // div
                const $div = $('<div/>', {
                    'html': options.title
                });
                if (options.titleClass) {
                    $div.addClass(options.titleClass);
                }

                // icon
                const $icon = options.icon ? $(options.icon) : this.createIcon(options);
                if ($icon) {
                    $div.prepend($icon);
                }

                // separator
                const $hr = $('<hr/>', {
                    'class': 'my-2 mr-3 p-0'
                });
                return $div.append($hr);
            }
            return null;
        },

        /**
         * Creates the close button.
         * 
         * @param {Object}
         *            options - The custom options.
         * 
         * @returns {jQuery} The close button or null if no button.
         */
        createCloseButton: function (options) {
            if (options.closeButton) {
                const $span = $('<span/>', {
                    'aria-hidden': 'true',
                    'html': '&times;'
                });
                const title = options.closeText || 'Close';
                const $button = $('<button/>', {
                    'class': 'close py-2 px-3',
                    'aria-label': title,
                    'title': title,
                    'type': 'button'
                });
                return $button.append($span);
            }
            return null;
        },

        /**
         * Creates the div message.
         * 
         * options - The custom options.
         * 
         * @returns {jQuery} The div message.
         */
        createMessage: function (options) {
            const $div = $('<div/>', {
                'html': options.message
            });
            if (options.title) {
                $div.addClass('mr-3');
            }
            if (options.messageClass) {
                $div.addClass(options.messageClass);
            }
            return $div;
        },

        /**
         * Creates the div alert.
         * 
         * @param {Object}
         *            [options] - The custom options.
         * 
         * @returns {jQuery} The div alert.
         */
        createAlert: function (options) {
            let clazz = 'alert alert-dismissible alert-' + options.type;
            if (options.title) {
                clazz += ' pr-1';
            }
            return $('<div/>', {
                'class': clazz,
                'role': 'alert'
            });
        },

        /**
         * Returns the given value with the appended pixel (px) unit.
         * 
         * @param {int}
         *            value - The value to append unit to.
         * 
         * @return {string} The value within the pixel unit.
         */
        toPixel: function (value) {
            return value + 'px';
        },

        /**
         * Show the notification.
         * 
         * @param {jQuery}
         *            [$notification] - The notification.
         * @param {Object}
         *            [options] - The custom options.
         * @return {Object} This instance.
         */
        showAlert: function ($notification, options) {
            const that = this;
            const callback = function () {
                that.hideAlert($notification, options);
            };
            $notification.hide()[options.showMethod]({
                easing: options.showEasing,
                duration: options.showDuration,
                complete: function () {
                    const timeout = options.timeout;
                    if (timeout > 0) {
                        $notification.createTimer(callback, timeout).hover(function () {
                            $notification.removeTimer();
                        }, function () {
                            $notification.createTimer(callback, timeout);
                        });
                    }
                }
            });
            return that;
        },

        /**
         * Hide the notification.
         * 
         * @param {jQuery}
         *            [$notification] - The notification.
         * @param {Object}
         *            [options] - The custom options.
         * @return {Object} This instance.
         */
        hideAlert: function ($notification, options) {
            const that = this;
            $notification.stop()[options.hideMethod]({
                duration: options.hideDuration,
                easing: options.hideEasing,
                complete: function () {
                    $notification.removeTimer().remove();
                }
            });
            return that;
        }
    };


}(window, jQuery));

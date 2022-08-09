/**! compression tag for ftp-deployment */

/**
 * Ready function
 */
(function ($) {
    'use strict';

    // ------------------------------------
    // Typeahead public class definition
    // ------------------------------------
    const Typeahead = class {
        // -----------------------------
        // public functions
        // -----------------------------

        /**
         * Constructor.
         *
         * @param {HTMLElement} element - the element to handle.
         * @param {Object} [options] - the options.
         */
        constructor(element, options) {
            const that = this;
            that.$element = $(element);
            that.options = $.extend(true, {}, Typeahead.DEFAULTS, options);
            that.$menu = $(that.options.menu).insertAfter(that.$element);

            // Method overrides
            that.eventSupported = that.options.eventSupported || that.eventSupported;
            that.filter = that.options.filter || that.filter;
            that.highlighter = that.options.highlighter || that.highlighter;
            that.lookup = that.options.lookup || that.lookup;
            that.matcher = that.options.matcher || that.matcher;
            that.render = that.options.render || that.render;
            that.onSelect = that.options.onSelect || null;
            that.onError = that.options.onError || null;
            that.sorter = that.options.sorter || that.sorter;
            that.select = that.options.select || that.select;
            that.source = that.options.source || that.source;
            that.displayField = that.options.displayField;
            that.valueField = that.options.valueField;

            if (that.options.ajax) {
                const ajax = that.options.ajax;
                if (that.isString(ajax)) {
                    that.ajax = $.extend({}, Typeahead.DEFAULTS.ajax, {
                        url: ajax
                    });
                } else {
                    if (that.isString(ajax.displayField)) {
                        that.displayField = that.options.displayField = ajax.displayField;
                    }
                    if (that.isString(ajax.valueField)) {
                        that.valueField = that.options.valueField = ajax.valueField;
                    }
                    that.ajax = $.extend({}, Typeahead.DEFAULTS.ajax, ajax);
                }
                if (!that.ajax.url) {
                    that.ajax = null;
                }
                if (that.ajax) {
                    that.ajaxExecuteProxy = function () {
                        that._ajaxExecute();
                    };
                    that.ajaxSuccessProxy = function (data) {
                        that._ajaxSuccess(data);
                    };
                    that.ajaxErrorProxy = function (jqXHR, textStatus, errorThrown) {
                        that._ajaxError(jqXHR, textStatus, errorThrown);
                    };
                }
                that.query = '';
            } else {
                that.source = that.options.source;
                that.ajax = null;
            }
            that.visible = false;
            that._listen();
        }

        destroy() {
            // remove element handlers
            const that = this;
            const $element = this.$element;
            $element.off('focus', that.focusProxy);
            $element.off('blur', that.blurProxy);
            $element.off('keypress', that.keyPressProxy);
            $element.off('keyup', that.keyUpProxy);
            if (this._eventSupported('keydown')) {
                $element.off('keydown', that.keyDownProxy);
            }

            // remove menu handlers
            const $menu = this.$menu;
            const selector = this.options.selector;
            $menu.off('click', that.clickProxy);
            $menu.off('mouseenter', selector, that.mouseEnterProxy);
            $menu.off('mouseleave', selector, that.mouseLeaveProxy);

            // remove data
            $element.removeData('typeahead');
        }

        /**
         * Show the drop-down menu.
         *
         * @return {Typeahead} this instance for chaining.
         */
        show() {
            const pos = $.extend({}, this.$element.position(), {
                height: this.$element[0].offsetHeight
            });
            this.$menu.css({
                top: pos.top + pos.height,
                left: pos.left
            });
            if (this.options.alignWidth) {
                const width = $(this.$element[0]).outerWidth();
                this.$menu.css({
                    width: width
                });
            }
            this.$menu.show();
            this.visible = true;
            return this;
        }

        /**
         * Hide the drop-down menu.
         *
         * @return {Typeahead} this instance for chaining.
         */
        hide() {
            if (this.visible) {
                this.$menu.hide();
                this.visible = false;
            }
            return this;
        }

        /**
         * Lookup query.
         *
         * @return {Typeahead} this instance for chaining.
         */
        lookup() {
            this.query = this.$element.val();
            if (!this.query) {
                return this.hide();
            }
            const items = this.filter(this.source);
            if (!items || items.length === 0) {
                return this.hide();
            }
            return this.render(items.slice(0, this.options.items)).show();
        }

        /**
         * Returns if the given match the query.
         *
         * @param {Object} item - the item to validate.
         * @return {boolean} true if matched.
         */
        matcher(item) {
            return item.toLowerCase().indexOf(this.query.toLowerCase()) !== -1;
        }

        /**
         * Sort items
         *
         * @param {*[]|*} items the items to sort.
         * @return {*[]|*}
         */
        sorter(items) {
            if (!this.options.ajax) {
                const begins_with = [];
                const case_sensitive = [];
                const case_insensitive = [];
                let item = items.shift();

                while (item !== null) {
                    if (!item.toLowerCase().indexOf(this.query.toLowerCase())) {
                        begins_with.push(item);
                    } else if (item.indexOf(this.query) !== -1) {
                        case_sensitive.push(item);
                    } else {
                        case_insensitive.push(item);
                    }
                    item = items.shift();
                }
                return begins_with.concat(case_sensitive, case_insensitive);
            } else {
                return items;
            }
        }

        /**
         * Render the items.
         * @param {*[]|*} items - the items to render.
         * @return {Typeahead} this instance for chaining.
         */
        render(items) {
            const data = [];
            const that = this;

            let display;
            let newSeparator;
            let oldSeparator = null;
            const separator = that.options.separator;
            const isStringSeparator = that._isString(separator);
            const isStringDisplay = that._isString(that.displayField);

            // run over items and add separators and categories if applicable
            $.each(items, function (key, value) {
                // get separators
                newSeparator = isStringSeparator ? value[separator] : separator(value);
                if (key > 0) {
                    oldSeparator = isStringSeparator ? items[key - 1][separator] : separator(items[key - 1]);
                }

                // inject separator
                if (key > 0 && newSeparator !== oldSeparator) {
                    data.push({
                        __type__: 'divider'
                    });
                }

                // inject category
                if (newSeparator && (key === 0 || newSeparator !== oldSeparator)) {
                    data.push({
                        __type__: 'category',
                        name: newSeparator
                    });
                }

                // inject value
                data.push(value);
            });

            // render categories, separators and items
            items = $(data).map(function (_index, item) {
                // category
                if (item.__type__ === 'category') {
                    return $(that.options.header).text(item.name)[0];
                }
                // separator
                if (item.__type__ === 'divider') {
                    return $(that.options.divider)[0];
                }
                // item
                if (that._isObject(item)) {
                    display = isStringDisplay ? item[that.displayField] : that.displayField(item);
                } else {
                    display = item;
                }
                const value = JSON.stringify(item);
                const html = that.highlighter(display);
                return $(that.options.item).data('value', value).html(html)[0];
            });

            if (that.options.autoSelect) {
                const selector = that.options.selector;
                items.filter(selector).first().addClass('active');
            }
            this.$menu.html(items);
            return this;
        }

        /**
         * Filter data.
         * @param {Object} data the data to filter.
         * @return {*[]|*|*[]|null}
         */
        filter(data) {
            // filters relevant results
            let items;
            let display;
            const that = this;
            const isStringDisplay = that._isString(that.displayField);

            if (isStringDisplay && data && data.length) {
                if (data[0].hasOwnProperty(that.displayField)) {
                    items = $.grep(data, function (item) {
                        display = isStringDisplay ? item[that.displayField] : that.displayField(item);
                        return that.matcher(display);
                    });
                } else if (that._isString(data[0])) {
                    items = $.grep(data, function (item) {
                        return that.matcher(item);
                    });
                } else {
                    return null;
                }
            } else {
                return null;
            }
            return this.sorter(items);
        }

        // -----------------------------
        // private functions
        // -----------------------------

        /**
         * Returns if the given event name is supported.
         * @param {string} eventName - the event name.
         * @return {boolean} true if supported.
         * @private
         */
        _eventSupported(eventName) {
            let supported = eventName in this.$element;
            if (!supported) {
                this.$element.setAttribute(eventName, 'return;');
                supported = typeof this.$element[eventName] === 'function';
            }
            return supported;
        }

        /**
         * Returns if the given data is a string.
         * @param {*} data - the data to test.
         * @return {boolean} true if a string.
         * @private
         */
        _isString(data) {
            return typeof data === 'string';
        }

        /**
         * Returns if the given data is an object.
         * @param {*} data - the data to test.
         * @return {boolean} true if an object.
         * @private
         */
        _isObject(data) {
            return typeof data === 'object';
        }

        /**
         * Select te active menu item.
         *
         * @return {Typeahead}  this instance for chaining.
         * @private
         */
        _select() {
            const $selectedItem = this.$menu.find('.active');
            if ($selectedItem.length) {
                let item = JSON.parse($selectedItem.data('value'));
                let text = $selectedItem.text();
                if (this.valueField) {
                    text = item[this.valueField];
                }
                this.$element.val(text).trigger('change');
                if (typeof this.onSelect === 'function') {
                    this.onSelect(item);
                }
            }
            return this.hide();
        }


        /**
         * Call ajax function.
         *
         * @return {Typeahead} this instance for chaining.
         * @private
         */
        _ajaxLookup() {
            const query = this.$element.val().trim();
            if (query === this.query) {
                return this;
            }

            // query changed
            this.query = query;

            // cancel last timer if set
            if (this.ajax.timerId) {
                clearTimeout(this.ajax.timerId);
                this.ajax.timerId = null;
            }
            if (!query || query.length < this.ajax.triggerLength) {
                // cancel the ajax callback if in progress
                if (this.ajax.xhr) {
                    this.ajax.xhr.abort();
                    this.ajax.xhr = null;
                }
                return this.hide();
            }

            // query is good to send, set a timer
            this.ajax.timerId = setTimeout(this.ajaxExecuteProxy, this.ajax.timeout);
            return this;
        }

        /**
         * Execute the ajax function.
         *
         * @return {Typeahead} this instance for chaining.
         * @private
         */
        _ajaxExecute() {
            // cancel last call if already in progress
            if (this.ajax.xhr) {
                this.ajax.xhr.abort();
            }
            const query = this.query;
            const data = typeof this.ajax.preDispatch === 'function' ? this.ajax.preDispatch(query) : {
                query: query
            };
            this.ajax.xhr = $.getJSON({
                success: this.ajaxSuccessProxy,
                error: this.ajaxErrorProxy,
                url: this.ajax.url,
                data: data
            });
            this.ajax.timerId = null;
            return this;
        }

        /**
         * Handle the ajax success call.
         *
         * @param {Object} data - the ajax data.
         * @return {Typeahead} this instance for chaining.
         * @private
         */
        _ajaxSuccess(data) {
            if (!this.ajax.xhr) {
                return this;
            }
            if (typeof this.ajax.preProcess === 'function') {
                data = this.ajax.preProcess(data);
            }
            // save for selection retrieval
            this.ajax.data = data;

            // manipulate objects
            const items = this.filter(this.ajax.data) || [];
            if (items.length) {
                this.ajax.xhr = null;
                this.$menu.removeClass('py-0 bg-secondary text-white');
                return this.render(items.slice(0, this.options.items)).show();
            }
            if (this.options.empty) {
                const $item = $('<a/>', {
                    class: 'dropdown-item disabled',
                    text: this.options.empty
                });
                this.$menu.addClass('py-0').html($item);
                return this.show();
            }
            return this.hide();
        }

        /**
         * Handle the ajax error.
         *
         * @param {XMLHttpRequest} jqXHR the ajax request.
         * @param {String} textStatus - the text status.
         * @param {Error} errorThrown - the ajax error.
         * @return {Typeahead} this instance for chaining.
         * @private
         */
        _ajaxError(jqXHR, textStatus, errorThrown) {
            if (textStatus !== 'abort' && typeof this.onError === 'function') {
                this.onError(jqXHR, textStatus, errorThrown);
            }
            return this;
        }

        /**
         * Highlight the given item.
         * @param {String} item - the item to highlight.
         *
         * @return {String}
         */
        highlighter(item) {
            const query = this.query.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, '\\$&');
            const pattern = '(' + query + ')';
            const flags = 'gi';
            const regex = new RegExp(pattern, flags);
            return item.replace(regex, function (_$1, match) {
                return '<strong>' + match + '</strong>';
            });
        }

        /**
         * Select the next menu item.
         * @private
         */
        _next() {
            const that = this;
            const selector = that.options.selector;
            const active = that.$menu.find('.active').removeClass('active');
            let next = active.nextAll(selector).first();
            if (!next.length) {
                next = that.$menu.find(selector).first();
            }
            if (next.length) {
                next.addClass('active');
            }
        }

        /**
         * Select the previous menu item.
         * @private
         */
        _prev() {
            const that = this;
            const selector = that.options.selector;
            const active = that.$menu.find('.active').removeClass('active');
            let prev = active.prevAll(selector).first();
            if (!prev.length) {
                prev = that.$menu.find(selector).last();
            }
            if (prev.length) {
                prev.addClass('active');
            }
        }

        /**
         * Handle the key event.
         * @param {Event} e - the event.
         * @private
         */
        _move(e) {
            const that = this;
            if (!that.visible) {
                return;
            }
            switch (e.keyCode) {
                case 9: // tab
                case 13: // enter
                case 27: // escape
                    e.preventDefault();
                    break;
                case 38: // up arrow
                    e.preventDefault();
                    that._prev();
                    break;
                case 40: // down arrow
                    e.preventDefault();
                    that._next();
                    break;
            }
            e.stopPropagation();
        }

        /**
         * Handle the key down event.
         * @param {Event} e - the event.
         * @private
         */
        _keydown(e) {
            const that = this;
            that.suppressKeyPressRepeat = $.inArray(e.keyCode, [40, 38, 9, 13, 27]) !== -1;
            that._move(e);
        }

        /**
         * Handle the key press event.
         * @param {Event} e - the event.
         * @private
         */
        _keypress(e) {
            if (this.suppressKeyPressRepeat) {
                return;
            }
            this._move(e);
        }

        /**
         * Handle the key up event.
         * @param {Event} e - the event.
         * @private
         */
        _keyup(e) {
            switch (e.keyCode) {
                case 40: // down arrow
                    if (e.ctrlKey && !this.visible && this.query !== '') {
                        this.show();
                    }
                    break;
                case 38: // up arrow
                case 16: // shift
                case 17: // ctrl
                case 18: // alt
                    break;
                case 9: // tab
                case 13: // enter
                    if (this.visible) {
                        this._select();
                    }
                    break;
                case 27: // escape
                    if (this.visible) {
                        this.hide();
                    }
                    break;
                default:
                    if (this.ajax) {
                        this._ajaxLookup();
                    } else {
                        this.lookup();
                    }
                    break;
            }
            e.preventDefault();
        }

        /**
         * Handle thefocus event.
         * @param {Event} e - the event.
         * @private
         */
        _focus() {
            this.focused = true;
        }

        /**
         * Handle the blur event.
         * @param {Event} e - the event.
         * @private
         */
        _blur() {
            this.focused = false;
            if (!this.isMouseOver && this.visible) {
                this.hide();
            }
        }

        /**
         * Handle the click event.
         * @param {Event} e - the event.
         * @private
         */
        _click(e) {
            e.preventDefault();
            this.$element.trigger('focus');
            this._select();
        }

        /**
         * Handle the mouse enter event.
         * @param {Event} e - the event.
         * @private
         */
        _mouseenter(e) {
            this.isMouseOver = true;
            this.$menu.find('.active').removeClass('active');
            $(e.currentTarget).addClass('active');
        }

        /**
         * Handle the mouse leave event.
         * @param {Event} e - the event.
         * @private
         */
        _mouseleave() {
            this.isMouseOver = false;
            if (!this.focused && this.visible) {
                this.hide();
            }
        }

        /**
         * Start listen events.
         * @private
         */
        _listen() {
            const that = this;
            // add element handlers
            const $element = that.$element;
            that.focusProxy = function () {
                that._focus();
            };
            that.blurProxy = function () {
                that._blur();
            };
            that.keyPressProxy = function (e) {
                that._keypress(e);
            };
            that.keyUpProxy = function (e) {
                that._keyup(e);
            };
            $element.on('focus', that.focusProxy);
            $element.on('blur', that.blurProxy);
            $element.on('keypress', that.keyPressProxy);
            $element.on('keyup', that.keyUpProxy);
            if (that._eventSupported('keydown')) {
                that.keyDownProxy = function (e) {
                    that._keydown(e);
                };
                $element.on('keydown', that.keyPressProxy);
            }

            // add menu handlers
            const $menu = that.$menu;
            const selector = that.options.selector;
            that.clickProxy = function (e) {
                that._click(e);
            };
            that.mouseEnterProxy = function (e) {
                that._mouseenter(e);
            };
            that.mouseLeaveProxy = function () {
                that._mouseleave();
            };
            $menu.on('click', that.clickProxy);
            $menu.on('mouseenter', selector, that.mouseEnterProxy);
            $menu.on('mouseleave', selector, that.mouseLeaveProxy);
        }
    };

    // -----------------------------------
    // Default options
    // -----------------------------------
    Typeahead.DEFAULTS = {
        source: [],
        items: 15,
        autoSelect: true,
        alignWidth: true,
        valueField: 'id',
        displayField: 'name',
        separator: 'category',
        empty: null,

        // UI
        selector: '.dropdown-item',
        menu: '<div class="typeahead dropdown-menu" role="listbox"></div>',
        item: '<button class="dropdown-item" type="button" role="option" />',
        header: '<h6 class="dropdown-header text-uppercase"></h6>',
        divider: '<div class="dropdown-divider"></div>',

        // functions
        onSelect: null,
        onError: null,

        // ajax
        ajax: {
            url: null,
            timeout: 100,
            method: 'get',
            triggerLength: 1,
            preDispatch: null,
            preProcess: null
        }
    };

    // -----------------------------
    // Typeahead plugin definition
    // -----------------------------
    const oldTypeahead = $.fn.typeahead;

    $.fn.typeahead = function (options) { // jslint ignore:line
        return this.each(function () {
            const $this = $(this);
            let data = $this.data('typeahead');
            if (!data) {
                const settings = typeof options === 'object' && options;
                $this.data('typeahead', data = new Typeahead(this, settings));
            }
            if (data._isString(options)) {
                data[options]();
            }
        });
    };

    $.fn.typeahead.Constructor = Typeahead;

    // ------------------------------------
    // Typeahead no conflict
    // ------------------------------------
    $.fn.typeahead.noConflict = function () {
        $.fn.typeahead = oldTypeahead;
        return this;
    };

    // --------------------------------
    // Typeahead data-api
    // --------------------------------
    (function ($) {
        $('body').on('focus.typeahead.data-api', '[data-provide="typeahead"]', function (e) {
            const $this = $(this);
            if (!$this.data('typeahead')) {
                $this.typeahead($this.data());
                e.preventDefault();
            }
        });
    }(jQuery));

}(jQuery));

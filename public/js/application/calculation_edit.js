/**! compression tag for ftp-deployment */

/* globals updateErrors, sortable, Toaster, MenuBuilder  */

/**
 * -------------- The type ahead search helper --------------
 */
var SearchHelper = {

    /**
     * Initialize type ahead searches.
     * 
     * @return {SearchHelper} this instance for chaining.
     */
    init: function () {
        'use strict';
        
        this.initSearchCustomer();
        this.initSearchProduct();
        this.initSearchUnits();
        
        return this;
    },

    /**
     * Initialize the type ahead search customers.
     * 
     * @return {Typeahead} The type ahead instance.
     */
    initSearchCustomer: function () {
        'use strict';

        const $element = $('#calculation_customer');
        return $element.initSearch({
            url: $('#edit-form').data('search-customer'),
            error: $('#edit-form').data('error-customer')
        });
    },

    /**
     * Initialize the type ahead search products.
     * 
     * @return {Typeahead} The type ahead instance.
     */
    initSearchProduct: function () {
        'use strict';

        const $element = $('#item_search_input');
        return $element.initSearch({
            alignWidth: false,
            valueField: 'description',
            displayField: 'description',

            url: $('#edit-form').data('search-product'),
            error: $('#edit-form').data('error-unit'),

            onSelect: function (item) {
                // copy values
                $('#item_description').val(item.description);
                $('#item_unit').val(item.unit);
                $('#item_category').val(item.categoryId);
                $('#item_price').floatVal(item.price);
                $('#item_price').trigger('input');

                // clear
                $element.val('');// .data('typeahead').query = '';

                // select
                if (item.price) {
                    $('#item_quantity').selectFocus();
                } else {
                    $('#item_price').selectFocus();
                }
            }
        });
    },

    /**
     * Initialize the type ahead search product units.
     * 
     * @return {Typeahead} The type ahead instance.
     */
    initSearchUnits: function () {
        'use strict';

        const $element = $('#item_unit');
        return $element.initSearch({
            url: $('#edit-form').data('search-unit'),
            error: $('#edit-form').data('error-unit')
        });
    }
};

/**
 * -------------- The move rows handler --------------
 */
var MoveRowHandler = {

    /**
     * Initialize.
     */
    init: function () {
        'use strict';

        const that = this;
        $('#data-table-edit').on('click', '.btn-first-item', function (e) {
            e.preventDefault();
            that.moveFirst($(this).getParentRow());
        }).on('click', '.btn-up-item', function (e) {
            e.preventDefault();
            that.moveUp($(this).getParentRow());
        }).on('click', '.btn-down-item', function (e) {
            e.preventDefault();
            that.moveDown($(this).getParentRow());
        }).on('click', '.btn-last-item', function (e) {
            e.preventDefault();
            that.moveLast($(this).getParentRow());
        });
    },

    /**
     * Move a source row before or after the target row.
     * 
     * @param {JQuery}
     *            $source - the row to move.
     * @param {JQuery}
     *            $target - the target row.
     * @param {boolean}
     *            up - true to move before the target (up); false to move after
     *            (down).
     * 
     * @return {JQuery} - The moved row.
     */
    move: function ($source, $target, up) {
        'use strict';

        if ($source && $target) {
            if (up) {
                $source.insertBefore($target);
            } else {
                $source.insertAfter($target);
            }
            $source.swapIdAndNames($target).scrollInViewport().timeoutToggle('table-success');
        }
        return $source;
    },

    /**
     * Move a calculation item to the first position.
     * 
     * @param {JQuery}
     *            $row - the row to move.
     * 
     * @return {JQuery} - The parent row.
     */
    moveFirst: function ($row) {
        'use strict';

        const index = $row.index();
        if (index > 1 && $row.prev()) {
            const $target = $row.siblings(':nth-child(2)');
            return this.move($row, $target, true);
        }
        return $row;
    },

    /**
     * Move a calculation item to the last position.
     * 
     * @param {JQuery}
     *            $row - the row to move.
     * 
     * @return {JQuery} - The parent row.
     */
    moveLast: function ($row) {
        'use strict';

        const index = $row.index();
        const count = $row.siblings().length;
        if (index < count && $row.next()) {
            const $target = $row.siblings(':last');
            return this.move($row, $target, false);
        }
        return $row;
    },

    /**
     * Move up a calculation item.
     * 
     * @param {JQuery}
     *            $row - the row to move.
     * 
     * @return {JQuery} - The parent row.
     */
    moveUp: function ($row) {
        'use strict';

        const index = $row.index();
        if (index > 1 && $row.prev()) {
            const $target = $row.prev();
            return this.move($row, $target, true);
        }
        return $row;
    },

    /**
     * Move down a calculation item.
     * 
     * @param {JQuery}
     *            $row - the row to move.
     * 
     * @return {JQuery} - The parent row.
     */
    moveDown: function ($row) {
        'use strict';

        const index = $row.index();
        const count = $row.siblings().length;        
        if (index < count && $row.next()) {
            const $target = $row.next();
            return this.move($row, $target, false);
        }
        return $row;
    }
};

/**
 * -------------- The Application handler --------------
 */
var Application = {

    /**
     * Initialize application.
     * 
     * @return {Application} This application instance for chaining.
     */
    init: function () {
        'use strict';

        return this.initDragDrop(false).initMenus();
    },

    /**
     * Initialize the drag and drop.
     * 
     * @param {boolean}
     *            destroy - true to destroy the existing sortable.
     * @return {Application} This application instance for chaining.
     */
    initDragDrop: function (destroy) {
        'use strict';

        const that = this;
        const selector = '#data-table-edit tbody';
        const $bodies = $(selector);

        if (destroy) {
            // remove proxies
            $bodies.off('sortstart', that.dragStartProxy).off('sortupdate', that.dragStopProxy);
            sortable(selector, 'destroy');
        } else {
            // create proxies
            that.dragStartProxy = $.proxy(that.onDragStart, that);
            that.dragStopProxy = $.proxy(that.onDragStop, that);
        }

        // create
        sortable(selector, {
            items: 'tr:not(.drag-skip)',
            placeholderClass: 'table-primary',
            forcePlaceholderSize: false,
            acceptFrom: 'tbody'
        });

        // remove role attribute (aria)
        $('#data-table-edit tbody tr[role="option"').removeAttr('role');

        // add handlers
        $bodies.on('sortstart', that.dragStartProxy).on('sortupdate', that.dragStopProxy);

        return that;
    },

    /**
     * Initialize the edit dialog.
     * 
     * @return {Application} This application instance for chaining.
     */
    initItemDialog: function () {
        'use strict';

        // already initialized?
        const that = this;
        if (that.dialogInitialized) {
            return;
        }

        // dialog validator
        const options = {
            submitHandler: function () {
                if (that.$editingRow) {
                    that.onEditDialogSubmit();
                } else {
                    that.onAddDialogSubmit();
                }
            }
        };
        $('#item_form').initValidator(options);

        // dialog events
        $('#item_modal').on('show.bs.modal', function () {
            const key = that.$editingRow ? 'edit' : 'add';
            const title = $('#item_form').data(key);
            $('#dialog-title').html(title);
            if (that.$editingRow) {
                $('#item_search_row').hide();
                $('#item_delete_button').show();
            } else {
                $('#item_search_row').show();
                $('#item_delete_button').hide();
            }
        }).on('shown.bs.modal', function () {
            if ($('#item_price').attr('readonly')) {
                $('#item_reset_button').focus();
            } else if (that.$editingRow) {
                if ($('#item_price').isEmptyValue()) {
                    $('#item_price').selectFocus();
                } else {
                    $('#item_quantity').selectFocus();
                }
                that.$editingRow.addClass('table-primary');
            } else {
                $('#item_search_input').selectFocus();
            }
        }).on('hide.bs.modal', function () {
            $('#data-table-edit tbody tr').removeClass('table-primary');
        });

        // buttons
        $('#item_delete_button').on('click', function () {
            $('#item_modal').modal('hide');
            if (that.$editingRow) {
                const button = that.$editingRow.findExists('.btn-delete-item');
                if (button) {
                    that.removeItem(button);
                }
            }
        });

        // widgets
        $('#item_price').inputNumberFormat();
        $('#item_quantity').inputNumberFormat();

        // bind
        const proxy = $.proxy(that.updateItemLine, that);
        $('#item_price, #item_quantity').on('input', proxy);

        // ok
        this.dialogInitialized = true;
        return that;
    },

    /**
     * Initialize group and item menus.
     * 
     * @return {Application} This application instance for chaining.
     */
    initMenus: function () {
        'use strict';

        const that = this;

        // adjust button
        $('.btn-adjust').on('click', function (e) {
            e.preventDefault();
            $(this).tooltip('hide');
            that.updateTotals(true);
        });

        // add item button
        $('#items-panel .card-header .btn-add-item').on('click', function (e) {
            e.preventDefault();
            that.showAddDialog($(this));
        });

        // sort items button
        $('.btn-sort-items').on('click', function (e) {
            e.preventDefault();
            that.sortItems();
        });

        // data table buttons
        $('#data-table-edit').on('click', '.btn-add-item', function (e) {
            e.preventDefault();
            that.showAddDialog($(this));
        }).on('click', '.btn-edit-item', function (e) {
            e.preventDefault();
            that.showEditDialog($(this));
        }).on('click', '.btn-delete-item', function (e) {
            e.preventDefault();
            that.removeItem($(this));
        }).on('click', '.btn-delete-group', function (e) {
            e.preventDefault();
            that.removeGroup($(this));
        }).on('click', '.btn-sort-group', function (e) {
            e.preventDefault();
            that.sortGroupItems($(this));
        });

        return that;
    },

    /**
     * Format a value with 2 fixed decimals and grouping separator.
     * 
     * @param {Number}
     *            value - the value to format.
     * @returns {String} - the formatted value.
     */
    toLocaleString: function (value) {
        'use strict';

        // get value
        let parsedValue = Number.parseFloat(value);
        if (isNaN(parsedValue)) {
            parsedValue = Number.parseFloat(0);
        }

        // format
        let formatted = parsedValue.toLocaleString('de-CH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // replace grouping and separator
        const grouping = $('#edit-form').data('grouping');
        if (grouping) {
            formatted = formatted.replace(/’|'/g, grouping);
        }
        const decimal = $('#edit-form').data('decimal');
        if (decimal) {
            formatted = formatted.replace(/\./g, decimal);
        }

        return formatted;
    },

    /**
     * Update the total of the line in the item dialog.
     * 
     * @return {Application} This application instance for chaining.
     */
    updateItemLine: function () {
        'use strict';

        const that = this;
        const price = $('#item_price').floatVal();
        const quantity = $('#item_quantity').floatVal();
        const total = that.toLocaleString(price * quantity);
        $('#item_total').val(total);
        
        return that;
    },

    /**
     * Update the move up/down buttons.
     * 
     * @return {Application} This application instance for chaining.
     */
    updateUpDownButton: function () {
        'use strict';

        // run hover bodies
        $('#data-table-edit tbody').each(function (index, element) {
            const $body = $(element);
            const $rows = $body.find('tr:not(:first)');
            const lastIndex = $rows.length - 1;

            // run over rows
            $rows.each(function (index, element) {
                const $row = $(element);
                const hideUp = index === 0;
                const hideDown = index === lastIndex;
                $row.find('.btn-first-item').toggleClass('d-none', hideUp);
                $row.find('.btn-up-item').toggleClass('d-none', hideUp);
                $row.find('.btn-down-item').toggleClass('d-none', hideDown);
                $row.find('.btn-last-item').toggleClass('d-none', hideDown);
                $row.find('.dropdown-divider:first').toggleClass('d-none', hideUp && hideDown);
            });

            const $sortGroup = $body.find('.btn-sort-group');
            $sortGroup.toggleClass('d-none', $rows.length === 1);
        });

        return this;
    },

    /**
     * Update the totals.
     * 
     * @param {boolean}
     *            adjust - true to adjust the user margin.
     * 
     * @return {Application} This application instance for chaining.
     */
    updateTotals: function (adjust) {
        'use strict';

        const that = this;

        // show or hide empty items
        $('#empty-items').toggleClass('d-none', $('#data-table-edit tbody').length !== 0);

        // validate user margin
        if (!$('#calculation_userMargin').valid()) {
            if ($('#user-margin-row').length === 0) {
                const $tr = $('<tr/>', {
                    'id': 'user-margin-row'
                });
                const $td = $('<td>', {
                    'class': 'text-muted',
                    'text': $('#edit-form').data('error-margin')
                });
                $tr.append($td);
                $('#totals-table tbody:first tr').remove();
                $('#totals-table tbody:first').append($tr);
            } else {
                $('#user-margin-row').removeClass('d-none');
            }
            $('.btn-adjust').attr('disabled', 'disabled').addClass('cursor-default');
            return that;
        }

        // abort
        if (that.jqXHR) {
            that.jqXHR.abort();
            that.jqXHR = null;
        }

        // parameters
        adjust = adjust || false;
        let data = $('#edit-form').serializeArray();
        if (adjust) {
            data.push({
                name: 'adjust',
                value: true
            });
        }

        // call
        const url = $('#edit-form').data('update');
        that.jqXHR = $.post(url, data, function (response) {
            // error?
            if (!response.result) {
                return that.disable(response.message);
            }

            // update content
            const $totalPanel = $('#totals-panel');
            if (response.body) {
                $('#totals-table > tbody').html(response.body);
                $totalPanel.fadeIn();
            } else {
                $totalPanel.fadeOut();
            }
            if (adjust && !isNaN(response.overall_margin)) {
                $('#calculation_userMargin').intVal(response.overall_margin).selectFocus();
            }
            if (response.overall_below) {
                $('.btn-adjust').removeAttr('disabled').removeClass('cursor-default');
            } else {
                $('.btn-adjust').attr('disabled', 'disabled').addClass('cursor-default');
            }
            updateErrors();
            return that;
        });

        return that;
    },

    /**
     * Disable edition.
     * 
     * @param {string}
     *            message - the error message to display.
     * 
     * @return {Application} This application instance for chaining.
     */
    disable: function (message) {
        'use strict';

        $(':submit').fadeOut();
        $('.btn-adjust').fadeOut();
        $('.btn-add-item').fadeOut();
        $('#totals-panel').fadeOut();
        $('#edit-form :input').attr('readonly', 'readonly');
        $('#item_form :input').attr('readonly', 'readonly');

        $('#data-table-edit *').css('cursor', 'auto');
        $('#data-table-edit a.btn-add-item').removeClass('btn-add-item');
        $('#data-table-edit a.btn-edit-item').removeClass('btn-edit-item');
        $('#data-table-edit a.btn-delete-item').removeClass('btn-delete-item');
        $('#data-table-edit a.btn-delete-group').removeClass('btn-delete-group');
        $('#data-table-edit a.btn-sort-group').removeClass('btn-sort-group');

        // $('#item_delete_button').remove();
        $('#data-table-edit div.dropdown').fadeOut();
        $('#error-all > p').html('<br>').addClass('small').removeClass('text-right');

        $.contextMenu('destroy');
        sortable('#data-table-edit tbody', 'destroy');

        // display error message
        const title = $('#edit-form').data('title');
        message = message || $('#edit-form').data('error-update');
        const options = $.extend({}, $('#flashbags').data(), {
            onHide: function () {
                const html = message.replace('<br><br>', ' ');
                $('#error-all > p').addClass('text-danger text-center').html(html);
            }
        });
        Toaster.danger(message, title, options);

        return this;
    },

    /**
     * Finds the table body for the given category.
     * 
     * @param {int}
     *            id - the category identifier.
     * @returns {JQuery} - the table body, if found; null otherwise.
     */
    findGroup: function (id) {
        'use strict';

        const $body = $("#data-table-edit tbody:has(input[name*='categoryId'][value=" + id + "])");
        if ($body.length) {
            return $body;
        }
        return null;
    },

    /**
     * Gets the edit dialog group.
     * 
     * @returns {Object} the group.
     */
    getDialogGroup: function () {
        'use strict';

        return {
            categoryId: $('#item_category').val(),
            code: $('#item_category :selected').text()
        };
    },

    /**
     * Gets the edit dialog item.
     * 
     * @returns {Object} the item.
     */
    getDialogItem: function () {
        'use strict';

        return {
            description: $('#item_description').val(),
            unit: $('#item_unit').val(),
            price: $('#item_price').val(),
            quantity: $('#item_quantity').val()
        };

    },

    /**
     * Sort all items.
     * 
     * @return {Application} This application instance for chaining.
     */
    sortItems: function () {
        'use strict';

        const that = this;
        $('#data-table-edit tbody').each(function () {
            that.sortGroupItems($(this));
        });

        return that;
    },

    /**
     * Sort items of a group.
     * 
     * @param {JQuery}
     *            $element - the caller element (button or tbody).
     * @return {Application} This application instance for chaining.
     */
    sortGroupItems: function ($element) {
        'use strict';

        // get rows
        const that = this;
        const $tbody = $element.closest('tbody');
        const $rows = $tbody.find('tr:not(:first)');
        if ($rows.length < 2) {
            return that;
        }

        // save identifiers
        const identifiers = $rows.map(function() {
            return $(this).inputIndex();
        });
        
        // sort
        $rows.sort(function (rowA, rowB) {
            const textA = $('td:first', rowA).text();
            const textB = $('td:first', rowB).text();
            return textA.localeCompare(textB);
        }).appendTo($tbody);
        
        // update identifiers
        $rows.each(function(index) {
            const $row = $(this);
            const oldId = $row.inputIndex(); 
            const newId = identifiers[index];
            if (oldId !== newId) {
                $rows.filter(function() {
                    return newId === $(this).inputIndex();
                }).swapIdAndNames($row);                
            }
        });
        
        // update UI
        return that.updateUpDownButton().initDragDrop(true);
    },

    /**
     * Sort groups by name.
     * 
     * @return {Application} This application instance for chaining.
     */
    sortGroups: function () {
        'use strict';

        let $table = $('#data-table-edit');
        $table.find('tbody').sort(function (bodyA, bodyB) {
            const textA = $('th:first', bodyA).text();
            const textB = $('th:first', bodyB).text();
            return textA.localeCompare(textB);            
        }).appendTo($table);

        return this;
    },

    /**
     * Appends the given group to the table.
     * 
     * @param {Object}
     *            group - the group data used to update row.
     * @returns {JQuery} - the appended group.
     */
    appendGroup: function (group) {
        'use strict';

        // get prototype
        const $parent = $('#data-table-edit');
        let prototype = $parent.getPrototype(/__groupIndex__/g, 'groupIndex');

        // append and update
        let $newGroup = $(prototype).appendTo($parent);
        $newGroup.find('tr:first th:first').text(group.code);
        $newGroup.findNamedInput('categoryId').val(group.categoryId);
        $newGroup.findNamedInput('code').val(group.code);

        // sort
        this.sortGroups();

        // reset the drag and drop handler.
        this.initDragDrop(true);

        return $newGroup;
    },

    /**
     * Display the add item dialog.
     * 
     * @param {JQuery}
     *            $source - the caller element (normally a button).
     */
    showAddDialog: function ($source) {
        'use strict';

        // initialize
        this.initItemDialog();
        this.$editingRow = null;

        // reset
        $('tr.table-success').removeClass('table-success');
        $('#item_form').resetValidator();

        // update values
        const $input = $source.parents('tbody').find("tr:first input[name*='categoryId']");
        if ($input.length) {
            $('#item_category').val($input.val());
        }
        $('#item_price').floatVal(1);
        $('#item_quantity').floatVal(1);
        $('#item_total').floatVal(1);

        // show
        $('#item_modal').modal('show');
    },

    /**
     * Display the edit item dialog.
     * 
     * This function copy the element to the dialog and display it.
     * 
     * @param {JQuery}
     *            $source - the caller element (normally a button).
     */
    showEditDialog: function (source) {
        'use strict';

        // row
        const $row = source.getParentRow();

        // initialize
        this.initItemDialog();
        this.$editingRow = $row;

        // reset
        $row.addClass('table-primary');
        $('#item_form').resetValidator();

        // update values
        $('#item_description').val($row.findNamedInput('description').val());
        $('#item_unit').val($row.findNamedInput('unit').val());
        $('#item_category').val($row.parent().findNamedInput('categoryId').val());
        $('#item_price').floatVal($row.findNamedInput('price').val());
        $('#item_quantity').floatVal($row.findNamedInput('quantity').val());
        $('#item_total').floatVal($row.findNamedInput('total').val());

        // show
        $('#item_modal').modal('show');
    },

    /**
     * Remove a calculation group.
     * 
     * @param {JQuery}
     *            $element - the caller element (normally a button).
     * @return {Application} This application instance for chaining.
     */
    removeGroup: function ($element) {
        'use strict';

        const that = this;
        $element.closest('tbody').removeFadeOut(function () {
            that.updateUpDownButton().updateTotals().initDragDrop(true);
        });
        return that;
    },

    /**
     * Remove a calculation item.
     * 
     * @param {JQuery}
     *            $element - the caller element (button).
     * @return {Application} This application instance for chaining.
     */
    removeItem: function ($element) {
        'use strict';

        // get row and body
        const that = this;
        let $row = $element.getParentRow();
        const $body = $row.parents('tbody');

        // if it is the last item then remove the group instead
        if ($body.children().length === 2) {
            $row = $body;
        }
        $row.removeFadeOut(function () {
            that.updateUpDownButton().updateTotals().initDragDrop(true);
        });
        return that;
    },

    /**
     * Handle the dialog form submit event when adding an item.
     */
    onAddDialogSubmit: function () {
        'use strict';

        // hide
        $('#item_modal').modal('hide');
        $('#empty-items').addClass('d-none');

        // get values
        const that = this;
        const group = that.getDialogGroup();
        const item = that.getDialogItem();

        // get or add group
        const $group = that.findGroup(group.categoryId) || that.appendGroup(group);

        // append
        const $item = $group.appendRow(item);

        // update total and scroll
        this.updateUpDownButton().updateTotals();
        $item.scrollInViewport().timeoutToggle('table-success');
    },

    /**
     * Handle the dialog form submit event when editing an item.
     */
    onEditDialogSubmit: function () {
        'use strict';

        // hide
        $('#item_modal').modal('hide');

        // row?
        const that = this;
        if (!that.$editingRow) {
            return;
        }

        // get values
        const group = that.getDialogGroup();
        const item = that.getDialogItem();

        let $oldBody = that.$editingRow.parents('tbody');
        let $oldGroup = that.$editingRow.parent().findNamedInput('categoryId');
        let oldCategoryId = $oldGroup.val();

        // same category?
        if (oldCategoryId !== group.categoryId) {
            // get or add group
            const $group = that.findGroup(group.categoryId) || that.appendGroup(group);

            // append
            const $row = $group.appendRow(item);

            // update callback
            const callback = function () {
                that.$editingRow.remove();
                that.$editingRow = null;
                that.updateUpDownButton().updateTotals();
                $row.scrollInViewport().timeoutToggle('table-success');
            };

            // remove old group if empty
            if ($oldBody.children().length === 2) {
                $oldBody.removeFadeOut(callback);
            } else {
                // update
                callback.call();
            }

        } else {
            // update
            that.$editingRow.updateRow(item);
            that.updateUpDownButton().updateTotals();
            that.$editingRow.timeoutToggle('table-success');
            that.$editingRow = null;
        }
    },

    /**
     * Handles the row drag start event.
     */
    onDragStart: function () {
        'use strict';
        $('tr.table-success').removeClass('table-success');
    },

    /**
     * Handles the row drag stop event.
     * 
     * @param {Event}
     *            e - the source event.
     */
    onDragStop: function (e) {
        'use strict';

        const that = this;
        const $row = $(e.detail.item);
        const origin = e.detail.origin;
        const destination = e.detail.destination;

        if (origin.container !== destination.container) {
            // -----------------------------
            // Moved to an other category
            // -----------------------------

            // create template and replace content
            const item = $row.getRowItem();
            const $newBody = $(destination.container);
            const $newRow = $newBody.appendRow(item);
            $row.replaceWith($newRow);

            // swap ids and names
            const rows = $newBody.children();
            for (let i = destination.index + 2, len = rows.length; i < len; i++) {
                const $source = $(rows[i - 1]);
                const $target = $(rows[i]);
                $source.swapIdAndNames($target);
            }

            // update callback
            const callback = function () {
                that.updateUpDownButton().updateTotals().initDragDrop(true);
                $newRow.timeoutToggle('table-success');
            };

            // remove old group if empty
            const $oldBody = $(origin.container);
            if ($oldBody.children().length === 1) {
                $oldBody.removeFadeOut(callback);
            } else {
                // update
                callback.call();
            }

        } else if (origin.index !== destination.index) {
            // -----------------------------
            // Moved to a new position
            // -----------------------------
            const $target = origin.index < destination.index ? $row.prev() : $row.next();
            $row.swapIdAndNames($target).timeoutToggle('table-success');
        } else {
            // -----------------------------
            // No change
            // -----------------------------
            $row.timeoutToggle('table-success');
        }
    },
};

/**
 * -------------- JQuery extensions --------------
 */
$.fn.extend({

    /**
     * Gets the index, for a row, of the first item input.
     * 
     * For example: calculation_groups_4_items_12_total will return 12.
     * 
     * @returns {int} - the index, if found; -1 otherwise.
     */
    inputIndex() {
        'use strict';
        const $input = $(this).find('input:first');
        const values = $input.attr('id').split('_');
        const value = Number.parseInt(values[values.length - 2], 10);
        return isNaN(value) ? - 1 : value;
    },
    
    /**
     * Swap id and name input attributes.
     * 
     * @param {JQuery}
     *            $target - the target row.
     * 
     * @return {JQuery} - The JQuery source row.
     */
    swapIdAndNames: function ($target) {
        'use strict';

        // get inputs
        const $source = $(this);
        const sourceInputs = $source.find('input');
        const targetInputs = $target.find('input');

        for (let i = 0, len = sourceInputs.length; i < len; i++) {
            // get source attributes
            const $sourceInput = $(sourceInputs[i]);
            const sourceId = $sourceInput.attr('id');
            const sourceName = $sourceInput.attr('name');

            // get target attributes
            const $targetInput = $(targetInputs[i]);
            const targetId = $targetInput.attr('id');
            const targetName = $targetInput.attr('name');

            // swap
            $targetInput.attr('id', sourceId).attr('name', sourceName);
            $sourceInput.attr('id', targetId).attr('name', targetName);
        }

        // update
        Application.updateUpDownButton();

        return $source;
    },
    
    /**
     * Finds an input element that have the name attribute within a given
     * substring.
     * 
     * @param {string}
     *            name - the partial attribute name.
     * 
     * @return {JQuery} - The input, if found; null otherwise.
     */
    findNamedInput: function (name) {
        'use strict';

        const selector = "input[name*='" + name + "']";
        const $result = $(this).find(selector);
        return $result.length ? $result : null;
    },

    /**
     * Fade out and remove the selected element.
     * 
     * @param {Function}
     *            callback - the optional function to call after the element is
     *            removed.
     */
    removeFadeOut: function (callback) {
        'use strict';

        const $element = $(this);
        $element.fadeOut(400, function () {
            $element.remove();
            if ($.isFunction(callback)) {
                callback();
            }
        });
    },

    /**
     * Gets the template prototype from the current element.
     * 
     * @param {String}
     *            pattern - the regex pattern used to replace the index.
     * @param {String}
     *            key - the data key used to retrieve and update the index.
     * @returns {String} - the template.
     */
    getPrototype: function (pattern, key) {
        'use strict';

        const $parent = $(this);

        // get and update index
        const $table = $('#data-table-edit');
        const index = $table.data(key);
        $table.data(key, index + 1);

        // get prototype
        const prototype = $parent.data('prototype');

        // replace index
        return prototype.replace(pattern, index);
    },

    /**
     * Gets item data from the current row.
     * 
     * @returns {Object} the item data.
     */
    getRowItem: function () {
        'use strict';

        const $row = $(this);
        return {
            description: $row.findNamedInput('description').val(),
            unit: $row.findNamedInput('unit').val(),
            price: $row.findNamedInput('price').val(),
            quantity: $row.findNamedInput('quantity').val(),
        };
    },

    /**
     * Create a new row and appends to this current parent group (tbody).
     * 
     * @param {Object}
     *            item - the row data used to update the row
     * @returns {JQuery} - the created row.
     */
    appendRow: function (item) {
        'use strict';

        // tbody
        const $parent = $(this);

        // get prototype
        const prototype = $parent.getPrototype(/__itemIndex__/g, 'itemIndex');

        // append and update
        return $(prototype).appendTo($parent).updateRow(item);
    },

    /**
     * Copy the values of the item to the current row.
     * 
     * @param {Object}
     *            item - the item to get values from.
     * @returns {JQuery} - The updated row.
     */
    updateRow: function (item) {
        'use strict';

        const $row = $(this);

        // update inputs
        $row.findNamedInput('description').val(item.description);
        $row.findNamedInput('unit').val(item.unit);
        $row.findNamedInput('price').val(item.price);
        $row.findNamedInput('quantity').val(item.quantity);
        $row.findNamedInput('total').val(item.price * item.quantity);

        // update cells
        $row.find('td:eq(0) .btn-edit-item').text(item.description);
        $row.find('td:eq(1)').text(item.unit);
        $row.find('td:eq(2)').text(Application.toLocaleString(item.price));
        $row.find('td:eq(3)').text(Application.toLocaleString(item.quantity));
        $row.find('td:eq(4)').text(Application.toLocaleString(item.price * item.quantity));

        return $row;
    },

    /**
     * Initialize a type ahead search.
     * 
     * @param {Object}
     *            options - the options to override.
     * 
     * @return {Typeahead} The type ahead instance.
     */
    initSearch: function (options) {
        'use strict';

        const $element = $(this);

        // default options
        const defaults = {
            valueField: '',
            ajax: {
                url: options.url
            },
            // overridden functions (all are set in the server side)
            matcher: function () {
                return true;
            },
            grepper: function (data) {
                return data;
            },
            onSelect: function () {
                $element.select();
            },
            onError: function () {
                const message = options.error;
                const title = $('#edit-form').data('title');
                Toaster.danger(message, title, $('#flashbags').data());
            }
        };

        // merge
        const settings = $.extend(true, defaults, options);

        return $element.typeahead(settings);
    },

    /**
     * Gets the parent row.
     * 
     * @returns {JQuery} - The parent row.
     */
    getParentRow: function () {
        'use strict';

        return $(this).parents('tr:first');
    },

    /**
     * Creates the context menu items.
     * 
     * @returns {Object} the context menu items.
     */
    getContextMenuItems: function () {
        'use strict';
        
        const $elements = $(this).getParentRow().find('.dropdown-menu').children();
        return (new MenuBuilder()).fill($elements).getItems();
    }
});

/**
 * Ready function
 */
(function ($) {
    'use strict';

    // searches
    SearchHelper.init();

    // move rows
    MoveRowHandler.init();

    // application
    Application.init();

    // context menu
    const selector = '.table-edit th:not(.d-print-none), .table-edit td:not(.d-print-none)';
    const show = function () {
        $('.dropdown-menu.show').removeClass('show');
        $(this).parent().addClass('table-primary');
    };
    const hide = function () {
        $(this).parent().removeClass('table-primary');
    };
    $('.table-edit').initContextMenu(selector, show, hide);

    // errors
    updateErrors();

    // main form validation
    $('#edit-form').initValidator();

    // user margin
    const $margin = $('#calculation_userMargin');
    $margin.on('input propertychange', function () {
        $margin.updateTimer(function () {
            Application.updateTotals();
        }, 250);
    });
}(jQuery));

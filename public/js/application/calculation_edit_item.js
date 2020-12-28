/**! compression tag for ftp-deployment */

/**
 * Edit item dialog handler.
 */
var EditItemDialog = function (application) {
    'use strict';
    this.application = application;
    this._init();
};

EditItemDialog.prototype = {

    /**
     * Constructor.
     */
    constructor: EditItemDialog,

    /**
     * Display the add item dialog.
     * 
     * @param {jQuery}
     *            $row - the selected row.
     * 
     * @return {EditItemDialog} - the selected row.
     */
    showAdd: function ($row) {
        'use strict';

        // initialize
        this.application.initDragDialog();
        this.$editingRow = null;

        // reset
        this.$form.resetValidator();

        // update values
        if ($row) {
            const $input = $row.siblings(':first').findNamedInput('category');
            if ($input) {
                this.$category.val($input.val());
            }
        }
        this.$price.floatVal(1);
        this.$quantity.floatVal(1);
        this.$total.text(this._formatValue(1));

        // show
        this.$modal.modal('show');

        return this;
    },

    /**
     * Display the edit item dialog.
     * 
     * This function copy the element to the dialog and display it.
     * 
     * @param {jQuery}
     *            $row - the selected row.
     * 
     * @return {EditItemDialog} This instance for chaining.
     */
    showEdit: function ($row) {
        'use strict';

        // initialize
        this.application.initDragDialog();
        this.$editingRow = $row;

        // reset
        this.$form.resetValidator();

        // copy values
        this.$description.val($row.findNamedInput('description').val());
        this.$unit.val($row.findNamedInput('unit').val());
        this.$category.val($row.parent().findNamedInput('category').val());
        this.$price.floatVal($row.findNamedInput('price').floatVal());
        this.$quantity.floatVal($row.findNamedInput('quantity').floatVal());
        this.$total.text(this._formatValue($row.findNamedInput('total').floatVal()));

        // show
        this.$modal.modal('show');

        return this;
    },

    /**
     * Hide the dialog.
     * 
     * @return {EditItemDialog} This instance for chaining.
     */
    hide: function () {
        'use strict';
        this.$modal.modal('hide');
        return this;
    },

    /**
     * Gets the selected group.
     * 
     * @returns {Object} the group.
     */
    getGroup: function () {
        'use strict';

        const $selection = this.$category.find(':selected');
        return {
            id: parseInt($selection.data('groupId'), 10),
            code: $selection.data('groupCode')
        };
    },

    /**
     * Gets the selected category.
     * 
     * @returns {Object} the category.
     */
    getCategory: function () {
        'use strict';
        const $selection = this.$category.find(':selected');
        return {
            id: this.$category.intVal(),
            code: $selection.text()
        };
    },

    /**
     * Gets the selected item.
     * 
     * @returns {Object} the item.
     */
    getItem: function () {
        'use strict';
        const price = this.$price.floatVal();
        const quantity = this.$quantity.floatVal();
        const total = Math.round(price * quantity * 100 + Number.EPSILON) / 100;

        return {
            description: this.$description.val(),
            unit: this.$unit.val(),
            price: price,
            quantity: quantity,
            total: total
        };
    },

    /**
     * Gets the editing row.
     * 
     * @return {JQuery} the row or null if none.
     */
    getEditingRow: function () {
        'use strict';
        return this.$editingRow;
    },

    /**
     * Initialize.
     * 
     * @return {EditItemDialog} This instance for chaining.
     */
    _init: function () {
        'use strict';

        const that = this;
        that.$form = $('#item_form');
        that.$modal = $('#item_modal');

        that.$description = $('#item_description');
        that.$unit = $('#item_unit');
        that.$category = $('#item_category');
        that.$price = $('#item_price').inputNumberFormat();
        that.$quantity = $('#item_quantity').inputNumberFormat();
        that.$total = $('#item_total');
        that.$search = $('#item_search_input');
        that.$searchRow = $('#item_search_row');
        that.$cancelButton = $('#item_cancel_button');
        that.$deleteButton = $('#item_delete_button');

        // validator
        const options = {
            submitHandler: function () {
                if (that.$editingRow) {
                    that.application.onEditItemDialogSubmit();
                } else {
                    that.application.onAddItemDialogSubmit();
                }
            }
        };
        that.$form.initValidator(options);

        // handle dialog events
        that.$modal.on('show.bs.modal', $.proxy(that._onDialogShow, that));
        that.$modal.on('shown.bs.modal', $.proxy(that._onDialogVisible, that));
        that.$modal.on('hide.bs.modal', $.proxy(that._onDialogHide, that));

        // handle input events
        const updateProxy = $.proxy(that._updateTotal, that);
        that.$price.on('input', updateProxy);
        that.$quantity.on('input', updateProxy);
        
        // handle delete button
        that.$deleteButton.on('click', $.proxy(that._onDelete, that));

        return that;
    },

    /**
     * Update the total line.
     */
    _updateTotal: function () {
        'use strict';
        const price = this.$price.floatVal();
        const quantity = this.$quantity.floatVal();
        const total = Math.round(price * quantity * 100 + Number.EPSILON) / 100;
        this.$total.text(this._formatValue(total));
    },

    /**
     * Handles the delete click event.
     */
    _onDelete: function () {
        'use strict';
        this.hide();
        if (this.$editingRow) {
            const button = this.$editingRow.findExists('.btn-delete-item');
            if (button) {
                this.application.removeItem(button);
            }
        }
    },

    // /**
    // * Initialize the type ahead search products.
    // *
    // * @return {EditItemDialog} This instance for chaining.
    // */
    // _initSearchProduct: function () {
    // 'use strict';
    //
    // const that = this;
    //SearchHelper
    // return that.$search.initSearch({
    // alignWidth: false,
    // valueField: 'description',
    // displayField: 'description',
    //
    // url: that.$form.data('search-product'),
    // error: that.$form.data('error-product'),
    //
    // onSelect: function (item) {
    // // copy values
    // that.$description.val(item.description);
    // that.$unit.val(item.unit);
    // that.$category.val(item.categoryId);
    // that.$price.floatVal(item.price);
    // that.$price.trigger('input');
    //
    // // clear
    // that.$search.val('');
    //
    // // select
    // if (item.price) {
    // that.$quantity.selectFocus();
    // } else {
    // that.$price.selectFocus();
    // }
    // }
    // });
    // },

    /**
     * Handles the dialog show event.
     */
    _onDialogShow: function () {
        'use strict';
        const key = this.$editingRow ? 'edit' : 'add';
        const title = this.$form.data(key);
        this.$modal.find('.dialog-title').text(title);
        if (this.$editingRow) {
            this.$searchRow.hide();
            this.$deleteButton.show();
        } else {
            this.$searchRow.show();
            this.$deleteButton.hide();
        }
    },

    /**
     * Handles the dialog visible event.
     */
    _onDialogVisible: function () {
        'use strict';
        if (this.$price.attr('readonly')) {
            this.$cancelButton.focus();
        } else if (this.$editingRow) {
            if (this.$price.isEmptyValue()) {
                this.$price.selectFocus();
            } else {
                this.$quantity.selectFocus();
            }
            this.$editingRow.addClass('table-primary');
        } else {
            this.$search.selectFocus();
        }
    },

    /**
     * Handles the dialog hide event.
     */
    _onDialogHide: function () {
        'use strict';
        $('#data-table-edit tbody tr.table-primary').removeClass('table-primary');
    },

    /**
     * Format a value with 2 fixed decimals and grouping separator.
     * 
     * @param {Number}
     *            value - the value to format.
     * @returns {string} - the formatted value.
     */
    _formatValue: function (value) {
        'use strict';
        return this.application.formatValue(value);
    }
};

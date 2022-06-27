    /**! compression tag for ftp-deployment */

    /**
     * Abstract edit dialog handler.
     *
     * @property {jQuery} $form
     * @property {jQuery} $modal
     * @property {jQuery} $category
     */
    class EditDialog {

        /**
         * Constructor.
         *
         * @param {Application} application - the parent application.
         */
        constructor(application) {
            'use strict';
            if (this.constructor === EditDialog) {
                throw new TypeError('Abstract class "EditDialog" cannot be instantiated directly.');
            }
            this.application = application;
            this._init();
        }

        /**
         * Display the add item dialog.
         *
         * @param {jQuery} $row - the selected row.
         * @return {EditDialog} This instance for chaining.
         */
        showAdd($row) {
            'use strict';

            // initialize
            this.application.initDragDialog();
            this.$editingRow = null;

            // reset
            this.$form.resetValidator();

            // initialize
            this._initAdd($row);

            // show
            this.$modal.modal('show');

            return this;
        }

        /**
         * Display the edit dialog.
         *
         * @param {jQuery} $row - the selected row.
         * @return {EditDialog} This instance for chaining.
         */
        showEdit($row) {
            'use strict';

            // initialize
            this.application.initDragDialog();
            this.$editingRow = $row;

            // reset
            this.$form.resetValidator();

            // initialize
            this._initEdit($row);


            // show
            this.$modal.modal('show');

            return this;
        }

        /**
         * Hide the dialog.
         *
         * @return {EditDialog} This instance for chaining.
         */
        hide() {
            'use strict';
            this.$modal.modal('hide');
            return this;
        }

        /**
         * Gets the selected group.
         *
         * @returns {Object} the group.
         */
        getGroup() {
            'use strict';
            const $selection = this.$category.getSelectedOption();
            const id = Number.parseInt($selection.data('groupId'), 10);
            const code = $selection.data('groupCode');
            return {
                id: id,
                code: code
            };
        }

        /**
         * Gets the selected category.
         *
         * @returns {Object} the category.
         */
        getCategory() {
            'use strict';
            const $selection = this.$category.getSelectedOption();
            const id = this.$category.intVal();
            const code = $selection.text();
            return {
                id: id,
                code: code
            };
        }

        /**
         * Gets the editing row.
         *
         * @return {jQuery} the row or null if none.
         */
        getEditingRow() {
            'use strict';
            return this.$editingRow;
        }

        /**
         * Initialize.
         *
         * @return {EditDialog} This instance for chaining.
         */
        _init() {
            'use strict';
            return this;
        }

        /**
         * Initialize the modal dialog.
         *
         * @param {jQuery} $modal - the modal dialog.
         *
         * @return {EditDialog} This instance for chaining.
         */
        _initDialog($modal) {
            const that = this;
            $modal.on('show.bs.modal', function () {
                that._onDialogShow();
            });
            $modal.on('shown.bs.modal', function () {
                that._onDialogVisible();
            });
            $modal.on('hide.bs.modal', function () {
                that._onDialogHide();
            });

            return that;
        }

        /**
         * Initialize the dialog add.
         *
         * @param {jQuery} _$row - the selected row.
         *
         * @return {EditDialog} This instance for chaining.
         */
        _initAdd(_$row) { // jshint ignore:line
            'use strict';
            return this;
        }

        /**
         * Initialize the dialog edit.
         *
         * @param {jQuery} _$row - the selected row.
         *
         * @return {EditDialog} This instance for chaining.
         */
        _initEdit(_$row) { // jshint ignore:line
            'use strict';
            return this;
        }

        /**
         * Handles the dialog show event.
         *
         * @return {EditDialog} This instance for chaining.
         */
        _onDialogShow() {
            'use strict';
            const key = this.$editingRow ? 'edit' : 'add';
            const title = this.$form.data(key);
            this.$modal.find('.dialog-title').text(title);
            return this;
        }

        /**
         * Handles the dialog visible event.
         *
         * @return {EditDialog} This instance for chaining.
         */
        _onDialogVisible() {
            'use strict';
            if (this.$editingRow) {
                this.$editingRow.addClass('table-primary');
            }
            return this;
        }

        /**
         * Handles the dialog hide event.
         *
         * @return {EditDialog} This instance for chaining.
         */
        _onDialogHide() {
            'use strict';
            $('tr.table-primary').removeClass('table-primary');
            return this;
        }
    }

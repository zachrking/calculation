/**! compression tag for ftp-deployment */

/* globals MenuBuilder */

/**
 * Gets the state style color of the given row.
 * 
 * @param {object}
 *            row - the record data.
 * @returns {string} the state style color, if any; null otherwise.
 */
function getStateColor(row) {
    'use strict';
    const color = row.color || row.stateColor || row['state.color'] || null;
    if(color) {
        return color + ' !important';
    }
    return null;
}

/**
 * Style for a state column (calculations or status).
 * 
 * @param {number}
 *            value - the field value.
 * @param {object}
 *            row - the record data.
 * 
 * @returns {object} the cell style.
 */
function styleStateColor(value, row) { // jshint ignore:line
    'use strict';
    const color = getStateColor(row);
    if(color) {
        return {
            css: {
                'border-left-color': color
            }
        };
    }
}

/**
 * Style for the log created date column.
 * 
 * @param {number}
 *            value - the field value.
 * @param {object}
 *            row - the record data.
 * 
 * @returns {object} the cell style.
 */
function styleLogCreatedAt(value, row) { // jshint ignore:line
    'use strict';
    let color;
    switch(row.level.toLowerCase()) {
        case 'debug':
            color = 'secondary';
            break;
        case 'warning':
            color = 'warning';
            break;
        case 'error':
        case 'critical':
        case 'alert':
        case 'emergency':
            color = 'danger';
            break;
        case 'info':
        case 'notice':
        default:
            color = 'info';
            break;
    }
    return {
        css: {
            'border-left-color': 'var(--' + color + ') !important'
        }
    };
}

/**
 * Returns if the current row is rendered for the connected user
 * 
 * @param $table
 *            {jQuery} the parent table.
 * @param row
 *            {object} the row data.
 * @returns {boolean} true if connected user
 */
function isConnectedUser($table, row) {
    'use strict';
    const currentId = Number.parseInt(row.id, 10);
    const connectedId = Number.parseInt($table.data('user-id'), 10);
    return Number.isNaN(currentId) || Number.isNaN(connectedId) || currentId === connectedId;
}

/**
 * Update the user action.
 * 
 * @param $table
 *            {jQuery} the parent table.
 * @param row
 *            {object} the row data.
 * @param $element
 *            {jQuery} the table row.
 * @param $action
 *            {jQuery} the action to update
 */
function updateUserAction($table, row, $element, $action) {
    'use strict';
    if(isConnectedUser($table, row)) {
        $action.remove();
    }
}

/**
 * Update the switch user action.
 * 
 * @param $table
 *            {jQuery} the parent table.
 * @param row
 *            {object} the row data.
 * @param $element
 *            {jQuery} the table row.
 * @param $action
 *            {jQuery} the action to update
 */
function updateUserSwitchAction($table, row, $element, $action) {
    'use strict';
    if(isConnectedUser($table, row)) {
        $action.prev('.dropdown-divider').remove();
        $action.remove();
    } else {
        const source = $action.attr('href').split('?')[0];
        const params = {
            '_switch_user': row.username
        };
        const href = source + '?' + $.param(params);
        $action.attr('href', href);
    }
}

/**
 * Update the search action.
 * 
 * @param $table
 *            {jQuery} the parent table.
 * @param row
 *            {object} the row data.
 * @param $element
 *            {jQuery} the table row.
 * @param $action
 *            {jQuery} the action to update
 */
function updateSearchAction($table, row, $element, $action) {
    'use strict';
    if($action.is('.btn-show') && !row.showGranted) {
        $action.remove();
    } else if($action.is('.btn-edit') && !row.editGranted) {
        $action.remove();
    } else if($action.is('.btn-delete') && !row.deleteGranted) {
        $action.remove();
    } else {
        const id = row.id;
        const type = row.type;
        const href = $action.attr('href').replace('_type_', type).replace('_id_', id);
        $action.attr('href', href);
        const defaultAction = $table.data('defaultAction');
        if($action.is('.btn-show') && defaultAction === 'show') {
            $action.addClass('btn-default');
        } else if($action.is('.btn-edit') && defaultAction === 'edit') {
            $action.addClass('btn-default');
        }
    }
}

/**
 * Update the export calculation action.
 * 
 * @param $table
 *            {jQuery} the parent table.
 * @param row
 *            {object} the row data.
 * @param $element
 *            {jQuery} the table row.
 * @param $action
 *            {jQuery} the action to update
 */
function updateCalculationPdfAction($table, row, $element, $action) {
    'use strict';
    const href = $action.attr('href').split('?')[0];
    $action.attr('href', href);
}

/**
 * Update the task compute action.
 * 
 * @param $table
 *            {jQuery} the parent table.
 * @param row
 *            {object} the row data.
 * @param $element
 *            {jQuery} the table row.
 * @param $action
 *            {jQuery} the action to update
 */
function updateTaskComputeAction($table, row, $element, $action) {
    'use strict';
    const items = Number.parseInt(row.items, 10);
    if(Number.isNaN(items) || items === 0) {
        $action.prev('.dropdown-divider').remove();
        $action.remove();
    }
}

/**
 * Formatter for the actions column.
 * 
 * @param {number}
 *            value - the field value (id).
 * @param {object}
 *            row - the row record data.
 * 
 * @returns {string} the rendered cell.
 */
function formatActions(value, row) { // jshint ignore:line
    'use strict';
    const substr = '$1' + value;
    const regex = /(\/|\bid=)(\d+)/;
    const $actions = $('#dropdown-actions').clone().removeClass('d-none');
    $actions.find('.dropdown-item-path').each(function() {
        const $link = $(this);
        const source = $link.attr('href');
        const target = source.replace(regex, substr);
        $link.attr('href', target);
    });
    return $actions.html();
}

/**
 * jQuery extensions.
 */
$.fn.extend({
    
    getDataValue: function() {
        'use strict';
        const $this = $(this);
        return $this.data('value') || null;
    },

    setDataValue(value, $selection) {
        'use strict';
        const $this = $(this);
        const $items = $this.next('.dropdown-menu').find('.dropdown-item').removeClass('active');
        $this.data('value', value);
        if(value) {
            $selection.addClass('active');
            return $this.text($selection.text());
        }
        $items.first().addClass('active');
        return $this.text($this.data('default'));
    },

    initDropdown: function() {
        'use strict';
        const $this = $(this);
        const $menu = $this.next('.dropdown-menu');
        $menu.on('click', '.dropdown-item', function() {
            const $item = $(this);
            const newValue = $item.getDataValue();
            const oldValue = $this.getDataValue();
            if(newValue !== oldValue) {
                $this.setDataValue(newValue || '', $item).trigger('input');
            }
            $this.focus();
        });
        $this.parent().on('shown.bs.dropdown', function() {
            $menu.find('.active').focus();
        });
        return $this;
    },

    /**
     * Gets the context menu items for the selected cell.
     * 
     * @return {object} the context menu items.
     */
    getContextMenuItems: function() {
        'use strict';
        const $row = $(this).parents('tr');
        const $elements = $row.find('.dropdown-menu').children();
        const builder = new MenuBuilder();
        return builder.fill($elements).getItems();
    }
});

/**
 * Ready function
 */
(function($) {
    'use strict';
    
    const $toggle = $('#toggle');
    const $table = $('#table-edit');
    const $pageButton = $('#button_page');
    const $clearButton = $('#clear_search');
    const $inputs = $('.dropdown-toggle.dropdown-input');
    
    // initialize table
    const options = {
        queryParams: function(params) {
            $inputs.each(function() {
                const $this = $(this);
                const value = $this.getDataValue();
                if(value) {
                    params[$this.attr('id')] = value;
                }
            });
            return params;
        },

        onPreBody: function(data) {
            // update pages list and page button
            if ($pageButton.length) {
                const options = $table.getOptions();
                let pageList = options.pageList;
                for(let i = 0; i < pageList.length; i++) {
                    if(pageList[i] >= options.totalRows) {
                        pageList = pageList.splice(0, i + 1);
                        break;
                    }
                }
                if(pageList.length <= 1) {
                    $pageButton.addClass('disabled');
                } else {
                    const pageSize = Number.parseInt(options.pageSize, 10);
                    const $links = pageList.map(function(page) {
                        const $link = $('<button/>', {
                            'class': 'dropdown-page dropdown-item',
                            'data-value': page,
                            'text': page
                        });
                        if(page === pageSize) {
                            $link.addClass('active');
                        }
                        return $link;
                    });
                    $('.dropdown-page').remove();
                    $('.dropdown-menu-page').append($links);
                    $pageButton.removeClass('disabled');
                }
            }
            
            // update clear search button
            if ($clearButton.length) {
                let enabled = $table.isSearchText();
                if (!enabled && $inputs.length) {
                    $inputs.each(function() {
                        if ($(this).getDataValue()) {
                            enabled = true;
                            return false;
                        }
                    });
                }    
                $clearButton.toggleClass('disabled', !enabled);
            }
            
            // update toggle view button
            $toggle.toggleClass('disabled', data.length === 0);
        },

        onRenderCardView: function($table, row, $element) {
            const color = getStateColor(row);
            if(color) {
                const $cell = $element.find('td:first');
                const style = 'border-left-color: ' + color;
                $cell.addClass('text-border').attr('style', style);
            }
        },

        onRenderAction: function($table, row, $element, $action) {
            if($action.is('.btn-user-switch')) {
                updateUserSwitchAction($table, row, $element, $action);
            } else if($action.is('.btn-user-message, .btn-user-delete')) {
                updateUserAction($table, row, $element, $action);
            } else if($action.is('.btn-calculation-pdf')) {
                updateCalculationPdfAction($table, row, $element, $action);
            } else if($action.is('.btn-search')) {
                updateSearchAction($table, row, $element, $action);
            } else if($action.is('.btn-task-compute')) {
                updateTaskComputeAction($table, row, $element, $action);
            }
        },
        
        onUpdateHref: function($table, $actions) {
            if($actions.length === 1) {
                $actions.addClass('btn-default');
            }
        }
    };
    $table.initBootstrapTable(options);

    // handle drop-down input buttons
    $inputs.each(function() {
        $(this).initDropdown().on('input', function() {
            $table.refresh();
        });
    });

    // handle toggle button
    $toggle.on('click', function() {
        $table.toggleView();
    });

    // handle clear search button
    if ($clearButton.length) {
        $clearButton.on('click', function() {
            const isSearchText = $table.isSearchText();
            const isQueryParams = !$.isEmptyObject(options.queryParams({}));
            // clear drop-down
            $inputs.each(function() {
                $(this).setDataValue(null);
            });
            if(isSearchText) {
                $table.resetSearch();
            } else if(isQueryParams) {
                $table.refresh();
            }
            $('input.search-input').focus();
        });
    }
    
    // handle the page button
    if ($pageButton.length) {
        $pageButton.initDropdown().on('input', function() {
            const pageSize = $pageButton.getDataValue();
            $table.refresh({
                pageSize: pageSize
            });
        });
    }

    // handle keys enablement
    $('.card-body').on('focus', '.search-input, .btn, .dropdown-item, .page-link, .rowlink-skip', function() {
        $table.disableKeys();
    }).on('blur', function() {
        $table.enableKeys();
    });

    // initialize context menu
    const rowSelector = $table.getOptions().rowSelector;
    const ctxSelector = rowSelector + ' td:not(.d-print-none)';
    const show = function() {
        $('.dropdown-menu.show').removeClass('show');
    };
    $table.initContextMenu(ctxSelector, show);

    // initialize danger tooltips
    if($table.data('danger-tooltip-selector')) {
        $table.customTooltip({
            type: 'danger',
            trigger: 'hover',
            selector: $table.data('danger-tooltip-selector')
        });
    }

    // update UI
    $('.fixed-table-pagination').appendTo('.card-footer');
    $('.fixed-table-toolbar').appendTo('.col-search');
    $('.fixed-table-toolbar input.search-input').attr('type', 'text').addClass('form-control-sm').prependTo('.input-group-search');
    $('.fixed-table-toolbar .search').remove();
    $('.btn-group-search').appendTo('.fixed-table-toolbar');

    // focus
    if($table.getData().length === 0) {
        $('input.search-input').focus();
    }
}(jQuery));

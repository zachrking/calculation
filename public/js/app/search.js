/**! compression tag for ftp-deployment */

/* globals URLSearchParams, triggerClick */

/**
 * -------------- JQuery extensions --------------
 */

/**
 * Update a button link.
 * 
 * @param {string}
 *            type - the entity type.
 * @param {boolean}
 *            granted - the granted action (autorization).
 * @param {object}
 *            params - the parameters.
 * 
 * @returns {JQuery} the button.
 */
$.fn.updateHref = function (type, granted, params) {
    'use strict';

    const $that = $(this);
    if (type === null || !granted) {
        return $that.attr('href', '#').addClass('disabled');
    }

    // build URL
    const path = $that.data('path').replace('_type_', type).replace('_id_', params.id);
    const href = path + '?' + $.param(params);

    // update
    return $that.attr('href', href).removeClass('disabled');
};

/**
 * -------------- DataTables Extensions --------------
 */

/**
 * Render the entity name cell.
 * 
 * @param {any}
 *            data - the cell data.
 * @param {string}
 *            type - the type call data requested.
 * @param {any}
 *            row - the full data source for the row.
 */
$.fn.dataTable.renderEntityName = function (data, type, row) {
    'use strict';

    let icon;
    switch (row.type) {
    case 'Calculation':
        icon = 'calculator fas';
        break;
    case 'CalculationState':
        icon = 'flag far';
        break;
    case 'Category':
        icon = 'folder far';
        break;
    case 'Product':
        icon = 'file-alt far';
        break;
    case 'Customer':
        icon = 'address-card far';
        break;
    default:
        icon = 'file far';
        break;
    }

    return '<i class="fa-fw fa-' + icon + '" aria-hidden="true"></i>&nbsp;' + data;
};

/**
 * Update buttons link and enablement.
 * 
 * @returns {DataTables.Api} this instance.
 */
$.fn.dataTable.Api.register('updateButtons()', function () {
    'use strict';

    let type = null;
    let params = null;
    let show_granted = false;
    let edit_granted = false;
    let delete_granted = false;
    const row = this.getSelectedRow();

    // build parameters
    if (row !== null) {
        const data = row.data();
        const info = this.page.info();
        params = {
            id: data.id,
            page: info.page,
            pagelength: info.length,
            query: this.search(),
            caller: window.location.href.split('?')[0]
        };
        type = data.type.toLowerCase();
        show_granted = data.show_granted;
        edit_granted = data.edit_granted;
        delete_granted = data.delete_granted;
    }

    // update buttons
    $('.btn-table-show').updateHref(type, show_granted, params);
    $('.btn-table-edit').updateHref(type, edit_granted, params);
    $('.btn-table-delete').updateHref(type, delete_granted, params);

    return this;
});

/**
 * Binds events.
 * 
 * @param {integer}
 *            id - the selected row identifier (if any).
 * 
 * @returns {DataTables.Api} this instance.
 */
$.fn.dataTable.Api.register('bindEvents()', function (id) {
    'use strict';

    const table = this;
    let lastPageCalled = false;

    // bind search and page length
    const $button = $('.btn-clear');
    $('#table_search').initSearchInput(searchCallback, table, $button);
    $('#table_length').initTableLength(table);

    // bind table body rows
    $('#data-table tbody').on('dblclick', 'tr', function (e) {
        return editOrShow(e);
    });

    // bind datatable key down
    $(document).on('keydown.keyTable', function (e) {
        if (e.ctrlKey) {
            switch (e.keyCode) {
            case 35: // end => last page
                const endInfo = table.page.info();
                if (endInfo.pages > 0 && endInfo.page < endInfo.pages - 1) {
                    e.stopPropagation();
                    lastPageCalled = true;
                    table.page('last').draw('page');
                }
                break;
            case 36: // home => first page
                const homeInfo = table.page.info();
                if (homeInfo.pages > 0 && homeInfo.page > 0) {
                    e.stopPropagation();
                    table.page('first').draw('page');
                }
                break;
            }
        }
    });

    // bind table events
    table.one('init', function () {
        let found = false;
        if (id !== 0) {
            const row = table.row('[id=' + id + ']');
            if (row && row.length) {
                table.cell(row.index(), '0:visIdx').focus();
                found = true;
            }
        }
        if (!found) {
            $('#table_search').selectFocus();
        }
    }).on('draw', function () {
        // select row or input
        if (table.row(0).node()) {
            const selector = lastPageCalled ? ':last' : ':first';
            const row = table.row(selector);
            table.cell(row.index(), '0:visIdx').focus();
        } else {
            $('#table_search').selectFocus();
        }
        lastPageCalled = false;
        table.updateButtons();

    }).on('key-focus', function (e, datatable, cell) {
        // select row
        const row = datatable.row(cell.index().row);
        $(row.node()).addClass('selection').scrollInViewport(0, 60);
        table.updateButtons();

    }).on('key-blur', function (e, datatable, cell) {
        // unselect row
        const row = datatable.row(cell.index().row);
        $(row.node()).removeClass('selection');
        table.updateButtons();

    }).on('key', function (e, datatable, key, cell, event) {
        switch (key) {
        case 13: // enter
            return editOrShow(event);
        case 46: // delete
            return triggerClick(event, '.btn-table-delete');
        }
    });

    return table;
});

/**
 * -------------- Application specific --------------
 */

/**
 * Search callback.
 * 
 * @param {DataTables.Api}
 *            table - the table to update.
 */
function searchCallback(table) {
    'use strict';

    const $input = $('#table_search');
    const oldSearch = table.search() || '';
    const newSearch = $input.val().trim();
    if (oldSearch !== newSearch) {
        if (newSearch.length > 1) {
            $input.removeClass('is-invalid');
            $('#minimum').addClass('d-none');
            table.search(newSearch).draw();
        } else {
            $input.addClass('is-invalid');
            $('#minimum').removeClass('d-none');
        }
    }
}

/**
 * Edit or show the selected item.
 * 
 * @param {Object}
 *            e - the source event.
 * @returns {boolean} true if handle.
 */
function editOrShow(e) {
    'use strict';

    // edit?
    if ($('#data-table').data('edit')) {
        return triggerClick(e, '.btn-table-edit') || triggerClick(e, '.btn-table-show');
    } else {
        return triggerClick(e, '.btn-table-show') || triggerClick(e, '.btn-table-edit');
    }
}

/**
 * Document ready function
 */
$(function () {
    'use strict';

    // table
    const $table = $('#data-table');

    // columns and order
    const columns = $table.getColumns(true);

    // remote
    const ajax = $table.data('ajax');
    const language = $table.data('lang');

    // loaded?
    let deferLoading = null;
    const total = $table.data('total');
    const filtered = $table.data('filtered');
    if (total !== 0) {
        deferLoading = [filtered, total];
    }

    // remove
    if (!$table.data('debug')) {
        $table.removeDataAttributes();
    }

    // parameters
    const defaultLength = $table.data('pagelength') || 15;
    const params = new URLSearchParams(window.location.search);
    // const paging = total > 15;
    const id = params.getOrDefault('id', 0);
    const page = params.getOrDefault('page', 0);
    const pagelength = params.getOrDefault('pagelength', defaultLength);
    const query = params.getOrDefault('query', null);

    // order
    let order = $table.getDefaultOrder(columns);
    const ordercolumn = params.getOrDefault('ordercolumn', null);
    const orderdir = params.getOrDefault('orderdir', null);
    if (ordercolumn !== null && orderdir !== null) {
        order = [[ordercolumn, orderdir]];
    }

    // options
    const options = {
        ajax: ajax,
        deferLoading: deferLoading,

        pageLength: pagelength,
        displayStart: page * pagelength,

        order: order,
        ordering: order.length > 0,
        columns: columns,

        language: {
            url: language
        },

        rowId: function (data) {
            return parseInt(data.id, 10);
        },

        search: {
            search: query
        }
    };

    // debug
    if ($table.data('debug')) {
        console.log(JSON.stringify(options, '', '    '));
    }

    // initialize
    $table.initDataTable(options).bindEvents(id);

    // update
    $('#table_search').val(query);
    $('#table_length').val(pagelength);
    if (query === null || query.length < 2) {
        $('#table_search').addClass('is-invalid');
        $('#minimum').removeClass('d-none');
    }

    // content sorting
    // $table.find("th:eq(3)").addClass('sorting_asc');
});
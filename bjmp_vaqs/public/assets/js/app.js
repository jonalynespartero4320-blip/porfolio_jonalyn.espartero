if (window.jQuery && $.fn.dataTable) {
    $.extend(true, $.fn.dataTable.defaults, {
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        autoWidth: false,
        dom: '<"dt-top"f>rt<"dt-bottom"ilp>',
        language: {
            search: '',
            searchPlaceholder: 'Search records',
            lengthMenu: 'Rows per page _MENU_',
            info: '_START_ - _END_ of _TOTAL_ entries',
            infoEmpty: '0 - 0 of 0 entries',
            infoFiltered: '(filtered from _MAX_ total entries)',
            paginate: {
                first: '<i class="bi bi-chevron-double-left" aria-hidden="true"></i>',
                previous: '<i class="bi bi-chevron-left" aria-hidden="true"></i>',
                next: '<i class="bi bi-chevron-right" aria-hidden="true"></i>',
                last: '<i class="bi bi-chevron-double-right" aria-hidden="true"></i>'
            },
            emptyTable: 'No records found.'
        }
    });

    $(document).on('init.dt', function (_event, settings) {
        const api = new $.fn.dataTable.Api(settings);
        const $container = $(api.table().container());

        $container.closest('.table-responsive').addClass('vaqs-datatable-shell');
        $container.find('.dataTables_filter input').attr('autocomplete', 'off');
        $container.find('.pagination').addClass('pagination-sm');
    });
}

document.addEventListener('click', function (event) {
    const logoutLink = event.target.closest('[data-logout-clear-state]');
    if (!logoutLink) {
        return;
    }

    try {
        sessionStorage.clear();
        localStorage.removeItem('activeTab');
        localStorage.removeItem('open_tab');
        Object.keys(localStorage).forEach(function (key) {
            if (/tab|tabs|datatable|visitor-registration/i.test(key)) {
                localStorage.removeItem(key);
            }
        });
    } catch (error) {
        // Storage can be unavailable in private browsing or locked-down browsers.
    }
});

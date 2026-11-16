(function () {
    function configureDataTables() {
        if (!window.jQuery || !jQuery.fn || !jQuery.fn.dataTable) {
            return;
        }

        jQuery.extend(true, jQuery.fn.dataTable.defaults, {
            language: {
                processing: 'Loading data, please wait...'
            }
        });
    }

    configureDataTables();

    const exitMessage = 'Are you sure you want to exit? The data you entered will be lost.';
    const guards = window.__IMSUnsavedGuards || [];
    window.__IMSUnsavedGuards = guards;

    window.IMSUnsavedGuard = {
        create: function (options) {
            const form = document.querySelector(options.form);
            if (!form) {
                return null;
            }

            const modal = options.modal ? document.querySelector(options.modal) : null;
            const dirtySelectors = options.dirtySelectors || [];
            let dirty = false;
            let bypass = false;

            const markDirty = function () {
                if (!bypass) {
                    dirty = true;
                }
            };

            const markClean = function () {
                dirty = false;
            };

            const forceClean = function () {
                bypass = true;
                dirty = false;
                window.setTimeout(function () {
                    bypass = false;
                }, 400);
            };

            form.addEventListener('input', markDirty, true);
            form.addEventListener('change', markDirty, true);

            dirtySelectors.forEach(function (selector) {
                document.addEventListener('click', function (event) {
                    if (event.target.closest(selector)) {
                        markDirty();
                    }
                }, true);
            });

            if (modal && window.bootstrap) {
                modal.addEventListener('shown.bs.modal', markClean);
                modal.addEventListener('hide.bs.modal', function (event) {
                    if (!dirty || bypass) {
                        return;
                    }

                    event.preventDefault();
                    const confirmClose = function () {
                        forceClean();
                        const instance = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
                        instance.hide();
                    };

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Unsaved changes',
                            text: exitMessage,
                            showCancelButton: true,
                            confirmButtonText: 'Yes, exit',
                            cancelButtonText: 'Stay',
                            confirmButtonColor: '#dc3545'
                        }).then(function (result) {
                            if (result.isConfirmed) {
                                confirmClose();
                            }
                        });
                    } else if (window.confirm(exitMessage)) {
                        confirmClose();
                    }
                });
            }

            const guard = {
                isDirty: function () { return dirty && !bypass; },
                markDirty: markDirty,
                markClean: markClean,
                forceClean: forceClean,
                message: exitMessage
            };

            guards.push(guard);
            return guard;
        },
        hasDirty: function () {
            return guards.some(function (guard) {
                return guard.isDirty();
            });
        },
        message: exitMessage
    };

    if (!window.__IMSUnsavedBeforeUnloadBound) {
        window.__IMSUnsavedBeforeUnloadBound = true;
        window.addEventListener('beforeunload', function (event) {
            if (!window.IMSUnsavedGuard || !window.IMSUnsavedGuard.hasDirty()) {
                return;
            }

            event.preventDefault();
            event.returnValue = exitMessage;
            return exitMessage;
        });
    }
})();

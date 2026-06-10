document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('#wpadminbar #wp-admin-bar-ops-center');

    if (!root) {
        return;
    }

    var trigger = root.querySelector(':scope > .ab-item');
    var panelNode = root.querySelector('#wp-admin-bar-ops-center-panel');
    var panelWrapper = root.querySelector(':scope > .ab-sub-wrapper');

    if (!trigger || !panelNode || !panelWrapper) {
        return;
    }

    trigger.setAttribute('aria-haspopup', 'true');
    trigger.setAttribute('aria-expanded', 'false');

    function closePanel() {
        root.classList.remove('ops-center-panel-open');
        root.classList.remove('hover');
        document.documentElement.classList.remove('ops-center-open');
        document.body.classList.remove('ops-center-open');
        trigger.setAttribute('aria-expanded', 'false');
    }

    function openPanel() {
        root.classList.add('ops-center-panel-open');
        root.classList.add('hover');
        document.documentElement.classList.add('ops-center-open');
        document.body.classList.add('ops-center-open');
        trigger.setAttribute('aria-expanded', 'true');
    }

    function togglePanel() {
        if (root.classList.contains('ops-center-panel-open')) {
            closePanel();
        } else {
            openPanel();
        }
    }

    trigger.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        togglePanel();
    });

    trigger.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            togglePanel();
        }

        if (event.key === 'Escape') {
            closePanel();
            trigger.focus();
        }
    });

    panelWrapper.addEventListener('click', function (event) {
        event.stopPropagation();

        if (event.target === panelWrapper) {
            closePanel();
            trigger.focus();
        }
    });

    panelWrapper.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePanel();
            trigger.focus();
        }
    });

    document.addEventListener('click', function (event) {
        if (!root.contains(event.target)) {
            closePanel();
        }
    });

    root.querySelectorAll('[data-ops-center-pane-trigger]').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-ops-center-pane-trigger');
            var target = targetId ? root.querySelector('#' + CSS.escape(targetId)) : null;

            if (!target) {
                return;
            }

            root.querySelectorAll('[data-ops-center-pane-trigger]').forEach(function (otherButton) {
                var isActive = otherButton === button;
                otherButton.classList.toggle('is-active', isActive);
                otherButton.setAttribute('aria-expanded', isActive ? 'true' : 'false');
            });

            root.querySelectorAll('.ops-center-panel__pane').forEach(function (pane) {
                var isActive = pane === target;
                pane.classList.toggle('is-active', isActive);
                pane.hidden = !isActive;
            });

            var input = target.querySelector('[data-ops-center-browser-search]');
            if (input) {
                input.focus();
            }
        });
    });

    root.querySelectorAll('.ops-center-panel__pane').forEach(function (pane) {
        var input = pane.querySelector('[data-ops-center-browser-search]');
        var list = pane.querySelector('[data-ops-center-browser-results]');
        var actions = pane.querySelector('.ops-center-browser__actions');

        if (!list) {
            return;
        }

        var rows = Array.prototype.slice.call(list.querySelectorAll('.ops-center-panel__item'));
        var currentUser = actions ? actions.getAttribute('data-ops-center-current-user') : '';
        var activeFilter = 'all';

        function applyBrowserFilters() {
            var query = input ? input.value.trim().toLowerCase() : '';

            rows.forEach(function (row) {
                var text = row.textContent || '';
                var matchesSearch = !query || text.toLowerCase().includes(query);
                var matchesOwner = activeFilter !== 'mine' || (currentUser && row.getAttribute('data-ops-center-author') === currentUser);

                row.hidden = !matchesSearch || !matchesOwner;
            });
        }

        if (input) {
            input.addEventListener('input', applyBrowserFilters);
        }

        pane.querySelectorAll('[data-ops-center-browser-filter]').forEach(function (button) {
            button.addEventListener('click', function () {
                activeFilter = button.getAttribute('data-ops-center-browser-filter') || 'all';

                pane.querySelectorAll('[data-ops-center-browser-filter]').forEach(function (otherButton) {
                    var isActive = otherButton === button;
                    otherButton.classList.toggle('is-active', isActive);
                    otherButton.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                applyBrowserFilters();
            });
        });
    });
    root.querySelectorAll('[data-ops-center-wp-editor-url]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var wpEditorUrl = link.getAttribute('data-ops-center-wp-editor-url');
            var wantsWpEditor = Boolean(wpEditorUrl && (event.metaKey || event.ctrlKey) && event.altKey);

            if (!wantsWpEditor) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            window.open(wpEditorUrl, '_blank', 'noopener');
        });
    });


});

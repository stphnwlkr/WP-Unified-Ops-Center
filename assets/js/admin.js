document.addEventListener('DOMContentLoaded', function () {
    function setupRepeater(list) {
        var fieldKey = list.getAttribute('data-ops-center-repeater') || 'shortcut_links';
        var draggedRow = null;
        function rows() { return Array.prototype.slice.call(list.querySelectorAll('[data-ops-center-row]')); }
        function renumberRows() {
            rows().forEach(function (row, index) {
                row.querySelectorAll('[data-ops-center-field]').forEach(function (field) {
                    field.name = 'ops_center_settings[' + fieldKey + '][' + index + '][' + field.getAttribute('data-ops-center-field') + ']';
                });
            });
        }
        function blankRow() {
            var source = rows()[0];
            if (!source) { return null; }
            var row = source.cloneNode(true);
            row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
            row.classList.remove('is-dragging');
            return row;
        }
        list.addEventListener('dragstart', function (event) {
            var row = event.target.closest('[data-ops-center-row]');
            if (!row) { return; }
            draggedRow = row; row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', '');
        });
        list.addEventListener('dragover', function (event) {
            var targetRow = event.target.closest('[data-ops-center-row]');
            if (!draggedRow || !targetRow || draggedRow === targetRow) { return; }
            event.preventDefault();
            var rect = targetRow.getBoundingClientRect();
            list.insertBefore(draggedRow, event.clientY > rect.top + rect.height / 2 ? targetRow.nextSibling : targetRow);
        });
        list.addEventListener('dragend', function () { if (draggedRow) { draggedRow.classList.remove('is-dragging'); } draggedRow = null; renumberRows(); });
        list.addEventListener('click', function (event) {
            var removeButton = event.target.closest('[data-ops-center-remove]');
            if (removeButton) {
                var removeRow = removeButton.closest('[data-ops-center-row]');
                if (removeRow && rows().length > 1) { removeRow.remove(); } else if (removeRow) { removeRow.querySelectorAll('input').forEach(function (input) { input.value = ''; }); }
                renumberRows(); return;
            }
            var button = event.target.closest('[data-ops-center-move]');
            if (!button) { return; }
            var row = button.closest('[data-ops-center-row]');
            if (!row) { return; }
            if ('up' === button.getAttribute('data-ops-center-move') && row.previousElementSibling) { list.insertBefore(row, row.previousElementSibling); row.focus({preventScroll: true}); }
            if ('down' === button.getAttribute('data-ops-center-move') && row.nextElementSibling) { list.insertBefore(row.nextElementSibling, row); row.focus({preventScroll: true}); }
            renumberRows();
        });
        document.querySelectorAll('[data-ops-center-add-row="' + fieldKey + '"]').forEach(function (button) {
            button.addEventListener('click', function () { var row = blankRow(); if (!row) { return; } list.appendChild(row); renumberRows(); var input = row.querySelector('input'); if (input) { input.focus(); } });
        });
        renumberRows();
    }
    document.querySelectorAll('[data-ops-center-sortable]').forEach(setupRepeater);
});

(function () {
    function setAppearance(root, value) {
        if (!root) { return; }
        root.classList.remove('ops-center-admin--auto', 'ops-center-admin--light', 'ops-center-admin--dark');
        root.classList.add('ops-center-admin--' + value);
    }

    function saveAppearance(value, status) {
        if (!window.opsCenterAdmin || !window.opsCenterAdmin.ajaxUrl) { return; }
        if (status) { status.textContent = 'Saving…'; }
        var body = new URLSearchParams();
        body.set('action', 'ops_center_save_appearance');
        body.set('nonce', window.opsCenterAdmin.nonce || '');
        body.set('appearance', value);
        fetch(window.opsCenterAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        }).then(function (response) {
            if (!response.ok) { throw new Error('Request failed'); }
            return response.json();
        }).then(function () {
            if (status) { status.textContent = 'Saved'; window.setTimeout(function () { status.textContent = ''; }, 1600); }
        }).catch(function () {
            if (status) { status.textContent = 'Could not save automatically. Use Save Settings.'; }
        });
    }

    document.addEventListener('change', function (event) {
        var input = event.target.closest('[data-ops-center-appearance-control] input[type="radio"]');
        if (!input) { return; }
        var root = input.closest('[data-ops-center-admin]');
        var status = root ? root.querySelector('[data-ops-center-save-status]') : null;
        setAppearance(root, input.value);
        saveAppearance(input.value, status);
    });
}());

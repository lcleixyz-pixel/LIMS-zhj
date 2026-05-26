(function () {
    function controls(row) {
        return Array.prototype.slice.call(row.querySelectorAll('[data-column-key]'));
    }

    function controlValue(control) {
        if (control.type === 'checkbox') {
            return control.checked ? '1' : '';
        }

        return control.value || '';
    }

    function setControlValue(row, columnKey, value) {
        var control = row.querySelector('[data-column-key="' + columnKey + '"]');
        if (!control) {
            return;
        }

        if (control.type === 'checkbox') {
            control.checked = value === '1';
            return;
        }

        control.value = value || '';
    }

    function getControlValue(row, columnKey) {
        var control = row.querySelector('[data-column-key="' + columnKey + '"]');

        return control ? controlValue(control).trim() : '';
    }

    function clearRow(row) {
        controls(row).forEach(function (control) {
            if (control.type === 'checkbox') {
                control.checked = false;
                return;
            }

            control.value = '';
        });
    }

    function isRowEmpty(row) {
        return controls(row).every(function (control) {
            return controlValue(control).trim() === '';
        });
    }

    function isOnlyEmployeeColumnsFilled(row, nameColumn, departmentColumn) {
        return controls(row).every(function (control) {
            var columnKey = control.dataset.columnKey;
            var value = controlValue(control).trim();

            return value === '' || columnKey === nameColumn || columnKey === departmentColumn;
        });
    }

    function reindexRows(table) {
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-repeatable-row]'));
        rows.forEach(function (row, index) {
            controls(row).forEach(function (control) {
                control.name = 'fields[' + control.dataset.fieldKey + '][' + index + '][' + control.dataset.columnKey + ']';
            });
        });
    }

    function addRow(table) {
        var template = table.querySelector('template[data-repeatable-row-template]');
        var tbody = table.querySelector('tbody');
        if (!template || !tbody) {
            return null;
        }

        var fragment = template.content.cloneNode(true);
        var row = fragment.querySelector('tr[data-repeatable-row]');
        tbody.appendChild(fragment);
        reindexRows(table);

        return row;
    }

    function removeOrClearRow(table, row) {
        var rows = table.querySelectorAll('tbody tr[data-repeatable-row]');
        if (rows.length <= 1) {
            clearRow(row);
        } else {
            row.remove();
        }

        reindexRows(table);
    }

    function findRepeatableTable(fieldKey) {
        return Array.prototype.slice.call(document.querySelectorAll('[data-repeatable-table]')).find(function (table) {
            return table.dataset.repeatableTable === fieldKey;
        }) || null;
    }

    function findBlankRow(table) {
        return Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-repeatable-row]')).find(isRowEmpty) || null;
    }

    function findEmployeeRow(table, name, department, nameColumn, departmentColumn) {
        return Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-repeatable-row]')).find(function (row) {
            var sameName = getControlValue(row, nameColumn) === name;
            var sameDepartment = departmentColumn === '' || department === '' || getControlValue(row, departmentColumn) === department;

            return sameName && sameDepartment;
        }) || null;
    }

    function syncPickerState(picker, table) {
        var nameColumn = picker.dataset.nameColumn || 'name';
        var departmentColumn = picker.dataset.departmentColumn || 'department';
        Array.prototype.slice.call(picker.querySelectorAll('[data-employee-option]')).forEach(function (checkbox) {
            checkbox.checked = !!findEmployeeRow(
                table,
                checkbox.dataset.employeeName || '',
                checkbox.dataset.employeeDepartment || '',
                nameColumn,
                departmentColumn
            );
        });
    }

    function handleEmployeePickerChange(checkbox) {
        var picker = checkbox.closest('[data-employee-picker]');
        if (!picker) {
            return;
        }

        var table = findRepeatableTable(picker.dataset.employeePicker);
        if (!table) {
            return;
        }

        var nameColumn = picker.dataset.nameColumn || 'name';
        var departmentColumn = picker.dataset.departmentColumn || 'department';
        var name = checkbox.dataset.employeeName || '';
        var department = checkbox.dataset.employeeDepartment || '';
        var row = findEmployeeRow(table, name, department, nameColumn, departmentColumn);

        if (checkbox.checked) {
            row = row || findBlankRow(table) || addRow(table);
            if (!row) {
                return;
            }

            setControlValue(row, nameColumn, name);
            if (departmentColumn !== '') {
                setControlValue(row, departmentColumn, department);
            }
            return;
        }

        if (row && isOnlyEmployeeColumnsFilled(row, nameColumn, departmentColumn)) {
            removeOrClearRow(table, row);
        }
    }

    document.addEventListener('click', function (event) {
        var addButton = event.target.closest('[data-add-repeatable-row]');
        if (addButton) {
            var table = addButton.closest('[data-repeatable-table]');
            if (table) {
                var row = addRow(table);
                if (row) {
                    var firstControl = row.querySelector('[data-column-key]');
                    if (firstControl) {
                        firstControl.focus();
                    }
                }
            }
            return;
        }

        var removeButton = event.target.closest('[data-remove-repeatable-row]');
        if (removeButton) {
            var rowToRemove = removeButton.closest('tr[data-repeatable-row]');
            var tableToUpdate = removeButton.closest('[data-repeatable-table]');
            if (rowToRemove && tableToUpdate) {
                removeOrClearRow(tableToUpdate, rowToRemove);
            }
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-employee-option]')) {
            handleEmployeePickerChange(event.target);
        }
    });

    document.addEventListener('input', function (event) {
        var table = event.target.closest('[data-repeatable-table]');
        if (!table) {
            return;
        }

        Array.prototype.slice.call(document.querySelectorAll('[data-employee-picker="' + table.dataset.repeatableTable + '"]')).forEach(function (picker) {
            syncPickerState(picker, table);
        });
    });

    document.querySelectorAll('[data-repeatable-table]').forEach(function (table) {
        reindexRows(table);
    });

    document.querySelectorAll('[data-employee-picker]').forEach(function (picker) {
        var table = findRepeatableTable(picker.dataset.employeePicker);
        if (table) {
            syncPickerState(picker, table);
        }
    });
}());

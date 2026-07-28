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

    function splitPickerValue(value) {
        return (value || '')
            .split(/[、,，;；]/)
            .map(function (item) { return item.trim(); })
            .filter(function (item, index, items) {
                return item !== '' && items.indexOf(item) === index;
            });
    }

    function pickerValueControl(picker) {
        return picker.parentElement
            ? picker.parentElement.querySelector('[data-multi-picker-value]')
            : null;
    }

    function pickerSummary(picker) {
        return picker.parentElement
            ? picker.parentElement.querySelector('[data-multi-picker-summary]')
            : null;
    }

    function pickerOptions(picker) {
        return Array.prototype.slice.call(picker.querySelectorAll('[data-multi-picker-option]'));
    }

    function updatePickerSummary(picker, values) {
        var summary = pickerSummary(picker);
        if (!summary) {
            return;
        }

        summary.textContent = values.length === 0
            ? '尚未选择'
            : '已选' + values.length + '项：' + values.join('、');
    }

    function initializeMultiPicker(picker) {
        var valueControl = pickerValueControl(picker);
        if (!valueControl) {
            return;
        }

        var values = splitPickerValue(valueControl.value);
        var optionValues = pickerOptions(picker).map(function (option) { return option.value; });
        picker.dataset.unmatchedValues = JSON.stringify(values.filter(function (value) {
            return optionValues.indexOf(value) === -1;
        }));
        pickerOptions(picker).forEach(function (option) {
            option.checked = values.indexOf(option.value) !== -1;
        });
        updatePickerSummary(picker, values);
    }

    function syncMultiPickerValue(picker) {
        var valueControl = pickerValueControl(picker);
        if (!valueControl) {
            return;
        }

        var unmatched = [];
        try {
            unmatched = JSON.parse(picker.dataset.unmatchedValues || '[]');
        } catch (error) {
            unmatched = [];
        }
        var selected = pickerOptions(picker)
            .filter(function (option) { return option.checked; })
            .map(function (option) { return option.value; });
        var values = unmatched.concat(selected).filter(function (value, index, items) {
            return value !== '' && items.indexOf(value) === index;
        });
        valueControl.value = values.join('、');
        updatePickerSummary(picker, values);
    }

    function syncMultiPickers(container) {
        Array.prototype.slice.call(container.querySelectorAll('[data-multi-picker]')).forEach(initializeMultiPicker);
    }

    function localDateValue(periodType) {
        var now = new Date();
        var year = String(now.getFullYear());
        var month = String(now.getMonth() + 1).padStart(2, '0');
        if (periodType === 'month') {
            return year + '-' + month;
        }

        return year + '-' + month + '-' + String(now.getDate()).padStart(2, '0');
    }

    function clearRow(row) {
        controls(row).forEach(function (control) {
            if (control.type === 'checkbox') {
                control.checked = false;
                return;
            }

            control.value = '';
        });
        syncMultiPickers(row);
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

    function syncRowRequired(row) {
        var rowHasValue = !isRowEmpty(row);
        controls(row).forEach(function (control) {
            if (control.dataset.baseRequired === undefined) {
                control.dataset.baseRequired = control.required ? '1' : '0';
            }

            var required = control.dataset.baseRequired === '1' && rowHasValue;
            if (control.dataset.requiredWhenField) {
                var dependent = row.querySelector(
                    '[data-column-key="' + control.dataset.requiredWhenField + '"]'
                );
                required = !!dependent
                    && controlValue(dependent).trim() === (control.dataset.requiredWhenEquals || '');
            }
            var baseLabel = (control.getAttribute('aria-label') || '')
                .replace(/（此行填写后必填）$/, '')
                .replace(/（判定不符合时必填）$/, '');
            control.required = required;
            if (control.dataset.requiredWhenField) {
                control.setAttribute('aria-label', baseLabel + (required ? '（判定不符合时必填）' : ''));
            } else {
                control.setAttribute('aria-label', baseLabel + (
                    control.dataset.baseRequired === '1' ? '（此行填写后必填）' : ''
                ));
            }
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
        syncRowRequired(row);
        syncMultiPickers(row);

        return row;
    }

    function removeOrClearRow(table, row) {
        var rows = table.querySelectorAll('tbody tr[data-repeatable-row]');
        if (rows.length <= 1) {
            clearRow(row);
            syncRowRequired(row);
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
            syncRowRequired(row);
            return;
        }

        if (row && isOnlyEmployeeColumnsFilled(row, nameColumn, departmentColumn)) {
            removeOrClearRow(table, row);
        }
    }

    document.addEventListener('click', function (event) {
        var periodButton = event.target.closest('[data-fill-current-period]');
        if (periodButton) {
            var target = periodButton.dataset.targetId
                ? document.getElementById(periodButton.dataset.targetId)
                : periodButton.closest('.input-group').querySelector('input[type="date"], input[type="month"]');
            if (target && !target.readOnly && !target.disabled) {
                target.value = localDateValue(periodButton.dataset.periodType || target.type);
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
                target.focus();
            }
            return;
        }

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
        if (event.target.matches('[data-multi-picker-option]')) {
            var multiPicker = event.target.closest('[data-multi-picker]');
            if (multiPicker) {
                syncMultiPickerValue(multiPicker);
            }
        }
        if (event.target.matches('[data-employee-option]')) {
            handleEmployeePickerChange(event.target);
        }
        var row = event.target.closest('tr[data-repeatable-row]');
        if (row) {
            syncRowRequired(row);
        }
    });

    document.addEventListener('input', function (event) {
        var table = event.target.closest('[data-repeatable-table]');
        if (!table) {
            return;
        }
        var row = event.target.closest('tr[data-repeatable-row]');
        if (row) {
            syncRowRequired(row);
        }

        Array.prototype.slice.call(document.querySelectorAll('[data-employee-picker="' + table.dataset.repeatableTable + '"]')).forEach(function (picker) {
            syncPickerState(picker, table);
        });
    });

    document.querySelectorAll('[data-repeatable-table]').forEach(function (table) {
        reindexRows(table);
        Array.prototype.slice.call(table.querySelectorAll('tr[data-repeatable-row]')).forEach(syncRowRequired);
    });

    document.querySelectorAll('[data-employee-picker]').forEach(function (picker) {
        var table = findRepeatableTable(picker.dataset.employeePicker);
        if (table) {
            syncPickerState(picker, table);
        }
    });

    syncMultiPickers(document);
}());

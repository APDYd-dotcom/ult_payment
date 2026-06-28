(function () {
    function normalizeText(value) {
        return value.trim().toLowerCase();
    }

    function parseNumber(value) {
        let cleaned = value.replace(/[^\d,.-]/g, '').replace(/\s/g, '');
        const lastComma = cleaned.lastIndexOf(',');
        const lastDot = cleaned.lastIndexOf('.');
        const decimalSeparator = lastComma > lastDot ? ',' : '.';

        if (lastComma !== -1 && lastDot !== -1) {
            const thousandSeparator = decimalSeparator === ',' ? '.' : ',';
            cleaned = cleaned.replaceAll(thousandSeparator, '').replace(decimalSeparator, '.');
        } else if (lastComma !== -1) {
            const decimalPart = cleaned.slice(lastComma + 1);
            cleaned = decimalPart.length <= 2
                ? cleaned.replace(',', '.')
                : cleaned.replaceAll(',', '');
        } else {
            const parts = cleaned.split('.');
            if (parts.length > 2) {
                const decimalPart = parts.pop();
                cleaned = parts.join('') + '.' + decimalPart;
            }
        }

        const parsed = Number.parseFloat(cleaned);
        return Number.isNaN(parsed) ? 0 : parsed;
    }

    function parseDate(value) {
        const timestamp = Date.parse(value.trim());
        return Number.isNaN(timestamp) ? 0 : timestamp;
    }

    function getCellValue(row, columnIndex, type) {
        const rawValue = row.cells[columnIndex]?.textContent || '';

        if (type === 'number') {
            return parseNumber(rawValue);
        }

        if (type === 'date') {
            return parseDate(rawValue);
        }

        return normalizeText(rawValue);
    }

    window.createTableSorter = function createTableSorter(table, rows, onSortChange) {
        if (!table || !rows.length) {
            return {
                apply: filteredRows => filteredRows,
            };
        }

        const tableBody = table.tBodies[0];
        const sortableHeaders = Array.from(table.querySelectorAll('th[data-sort-index]'));
        let sortState = {
            columnIndex: null,
            direction: 'none',
            type: 'text',
        };

        rows.forEach((row, index) => {
            row.dataset.originalIndex = String(index);
        });

        function updateHeaders() {
            sortableHeaders.forEach(header => {
                header.classList.remove('sort-asc', 'sort-desc', 'sort-active');
                header.setAttribute('aria-sort', 'none');

                if (Number(header.dataset.sortIndex) === sortState.columnIndex && sortState.direction !== 'none') {
                    header.classList.add('sort-active', sortState.direction === 'asc' ? 'sort-asc' : 'sort-desc');
                    header.setAttribute('aria-sort', sortState.direction === 'asc' ? 'ascending' : 'descending');
                }
            });
        }

        function getNextDirection(headerColumnIndex) {
            if (sortState.columnIndex !== headerColumnIndex || sortState.direction === 'none') {
                return 'asc';
            }

            if (sortState.direction === 'asc') {
                return 'desc';
            }

            return 'none';
        }

        function apply(filteredRows) {
            const rowSet = new Set(filteredRows);
            let orderedRows = [...filteredRows];

            if (sortState.direction === 'none' || sortState.columnIndex === null) {
                orderedRows.sort((a, b) => Number(a.dataset.originalIndex) - Number(b.dataset.originalIndex));
            } else {
                orderedRows.sort((a, b) => {
                    const aValue = getCellValue(a, sortState.columnIndex, sortState.type);
                    const bValue = getCellValue(b, sortState.columnIndex, sortState.type);
                    let result = 0;

                    if (typeof aValue === 'number' && typeof bValue === 'number') {
                        result = aValue - bValue;
                    } else {
                        result = String(aValue).localeCompare(String(bValue), undefined, {
                            numeric: true,
                            sensitivity: 'base',
                        });
                    }

                    if (result === 0) {
                        result = Number(a.dataset.originalIndex) - Number(b.dataset.originalIndex);
                    }

                    return sortState.direction === 'asc' ? result : -result;
                });
            }

            orderedRows.forEach(row => tableBody.appendChild(row));
            rows.filter(row => !rowSet.has(row)).forEach(row => tableBody.appendChild(row));

            return orderedRows;
        }

        sortableHeaders.forEach(header => {
            header.classList.add('sortable-header');
            header.setAttribute('role', 'button');
            header.setAttribute('tabindex', '0');
            header.setAttribute('aria-sort', 'none');

            function toggleSort() {
                const columnIndex = Number(header.dataset.sortIndex);
                const direction = getNextDirection(columnIndex);

                sortState = {
                    columnIndex: direction === 'none' ? null : columnIndex,
                    direction,
                    type: header.dataset.sortType || 'text',
                };

                updateHeaders();
                onSortChange();
            }

            header.addEventListener('click', toggleSort);
            header.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggleSort();
                }
            });
        });

        updateHeaders();

        return { apply };
    };
})();

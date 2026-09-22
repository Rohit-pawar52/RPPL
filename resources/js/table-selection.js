/**
 * Wires up "select all on this page" + row checkboxes for admin tables
 * that offer a selected-rows report/export action. Selection is always
 * explicit and always scoped to rows actually rendered on the current
 * page — never interpreted as "every record matching the filters".
 *
 * Markup contract:
 *   <div data-row-selection="#export-selected-button">
 *     <input type="checkbox" data-select-all>
 *     ...
 *     <input type="checkbox" data-row-checkbox name="selected_ids[]" value="...">
 *   </div>
 *   <button id="export-selected-button" data-label="Export Selected ({count})" hidden>
 */
export function initTableRowSelection() {
    document.querySelectorAll('[data-row-selection]').forEach((root) => {
        const selectAll = root.querySelector('[data-select-all]');
        const actionButton = document.querySelector(root.dataset.rowSelection);
        const rowCheckboxes = () => root.querySelectorAll('[data-row-checkbox]');

        const updateButton = () => {
            if (! actionButton) return;

            const checkedCount = Array.from(rowCheckboxes()).filter((checkbox) => checkbox.checked).length;

            if (checkedCount > 0) {
                actionButton.hidden = false;
                actionButton.textContent = actionButton.dataset.label.replace('{count}', checkedCount);
            } else {
                actionButton.hidden = true;
            }
        };

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                rowCheckboxes().forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
                updateButton();
            });
        }

        rowCheckboxes().forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (! checkbox.checked && selectAll) {
                    selectAll.checked = false;
                }
                updateButton();
            });
        });

        updateButton();
    });
}

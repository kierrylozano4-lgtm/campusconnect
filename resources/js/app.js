const selectAllUsers = document.querySelector('[data-select-all-users]');

if (selectAllUsers) {
    const userCheckboxes = [...document.querySelectorAll('.admin-user-checkbox')];

    selectAllUsers.addEventListener('change', () => {
        userCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAllUsers.checked;
        });
    });

    userCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            selectAllUsers.checked = userCheckboxes.length > 0
                && userCheckboxes.every((userCheckbox) => userCheckbox.checked);
        });
    });
}

const bulkUserForm = document.querySelector('#bulk-user-form');

if (bulkUserForm) {
    bulkUserForm.addEventListener('submit', (event) => {
        const selectedAction = bulkUserForm.querySelector('[name="action"]').value;
        const selectedUsers = bulkUserForm.querySelectorAll('.admin-user-checkbox:checked').length;

        if (selectedUsers === 0 || !selectedAction) {
            event.preventDefault();
            window.alert(selectedUsers === 0
                ? 'Select at least one user first.'
                : 'Choose a bulk action first.');
            return;
        }

        if (selectedAction === 'delete' && !window.confirm(`Delete ${selectedUsers} selected user(s)? This cannot be undone.`)) {
            event.preventDefault();
        }
    });
}

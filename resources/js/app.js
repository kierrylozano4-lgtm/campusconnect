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

const conversationMessages = document.querySelector('.conversation-messages');

if (conversationMessages) {
    conversationMessages.scrollTop = conversationMessages.scrollHeight;
}

const serviceSearchForm = document.querySelector('[data-service-search-form]');

if (serviceSearchForm) {
    const searchInput = serviceSearchForm.querySelector('[data-service-search-input]');
    const categoryFilter = serviceSearchForm.querySelector('[data-service-category-filter]');
    const serviceCards = [...document.querySelectorAll('[data-service-card]')];
    const serviceGrid = document.querySelector('[data-service-grid]');
    const resultCount = document.querySelector('[data-service-result-count]');
    const emptyState = document.querySelector('[data-service-search-empty]');
    const layoutButtons = [...document.querySelectorAll('[data-service-layout]')];

    const updateServiceResults = () => {
        const searchTerm = searchInput.value.trim().toLowerCase();
        const selectedCategory = categoryFilter.value;
        let visibleCount = 0;

        serviceCards.forEach((card) => {
            const matchesTitle = card.dataset.serviceTitle.startsWith(searchTerm);
            const matchesCategory = !selectedCategory || card.dataset.serviceCategory === selectedCategory;
            card.hidden = !matchesTitle || !matchesCategory;

            if (!card.hidden) {
                visibleCount += 1;
            }
        });

        if (resultCount) {
            resultCount.textContent = `Showing ${visibleCount} service${visibleCount === 1 ? '' : 's'}`;
        }

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }
    };

    const setServiceLayout = (layout) => {
        serviceGrid.dataset.serviceLayout = layout;
        layoutButtons.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.serviceLayout === layout));
        });
    };

    searchInput.addEventListener('input', updateServiceResults);
    categoryFilter.addEventListener('change', updateServiceResults);
    layoutButtons.forEach((button) => {
        button.addEventListener('click', () => setServiceLayout(button.dataset.serviceLayout));
    });

    updateServiceResults();
}

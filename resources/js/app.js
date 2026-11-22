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

const appearanceForm = document.querySelector('[data-appearance-form]');

if (appearanceForm) {
    const themeSelect = appearanceForm.querySelector('[data-theme-select]');
    const paletteInputs = [...appearanceForm.querySelectorAll('[name="design_palette"]')];
    const allowedThemes = ['modern', 'glass', 'skeuomorphic', 'brutalist', 'editorial'];
    const allowedPalettes = ['campus', 'ocean', 'botanical', 'sunset', 'monochrome'];

    const applyAppearancePreview = () => {
        const selectedPalette = paletteInputs.find((input) => input.checked)?.value;

        if (allowedThemes.includes(themeSelect.value)) {
            document.documentElement.dataset.designTheme = themeSelect.value;
        }

        if (allowedPalettes.includes(selectedPalette)) {
            document.documentElement.dataset.designPalette = selectedPalette;
        }
    };

    appearanceForm.addEventListener('change', applyAppearancePreview);
    appearanceForm.addEventListener('submit', () => {
        const selectedPalette = paletteInputs.find((input) => input.checked)?.value;

        if (allowedThemes.includes(themeSelect.value) && allowedPalettes.includes(selectedPalette)) {
            try {
                localStorage.setItem('campusconnect:appearance', JSON.stringify({
                    theme: themeSelect.value,
                    palette: selectedPalette,
                }));
            } catch {}
        }
    });
}

const profilePhotoDialog = document.querySelector('[data-profile-photo-dialog]');

if (profilePhotoDialog) {
    const previewImage = profilePhotoDialog.querySelector('[data-profile-photo-dialog-image]');

    document.querySelectorAll('[data-profile-photo-preview]').forEach((previewButton) => {
        previewButton.addEventListener('click', () => {
            previewImage.src = previewButton.dataset.photoUrl;
            previewImage.alt = previewButton.dataset.photoAlt;
            profilePhotoDialog.showModal();
        });
    });

    profilePhotoDialog.addEventListener('click', (event) => {
        if (event.target === profilePhotoDialog) {
            profilePhotoDialog.close();
        }
    });

    profilePhotoDialog.addEventListener('close', () => {
        previewImage.removeAttribute('src');
        previewImage.alt = '';
    });
}

const profileEditForm = document.querySelector('.profile-edit-panel form[enctype="multipart/form-data"]');

if (profileEditForm) {
    const photoInputs = [...profileEditForm.querySelectorAll('[data-profile-photo-input]')];
    const uploadStatus = profileEditForm.querySelector('[data-profile-upload-status]');
    const targetImageSize = 1.5 * 1024 * 1024;
    let preparedSubmission = false;

    const compressPhoto = async (file, maxDimension) => {
        if (file.size <= targetImageSize) {
            return file;
        }

        if (typeof createImageBitmap !== 'function') {
            throw new Error('This browser cannot resize the selected image. Please choose an image under 1.5 MB.');
        }

        const bitmap = await createImageBitmap(file);
        let scale = Math.min(1, maxDimension / Math.max(bitmap.width, bitmap.height));
        let quality = 0.84;

        try {
            while (scale >= 0.2) {
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                canvas.height = Math.max(1, Math.round(bitmap.height * scale));

                const context = canvas.getContext('2d');
                if (!context) {
                    throw new Error('The selected image could not be prepared. Please try another image.');
                }

                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', quality));
                if (!blob) {
                    throw new Error('The selected image could not be compressed. Please try another image.');
                }

                if (blob.size <= targetImageSize) {
                    const extension = blob.type === 'image/webp' ? 'webp' : 'jpg';
                    const baseName = file.name.replace(/\.[^.]+$/, '');
                    return new File([blob], `${baseName}.${extension}`, {
                        type: blob.type || 'image/jpeg',
                        lastModified: Date.now(),
                    });
                }

                if (quality > 0.58) {
                    quality -= 0.12;
                } else {
                    scale *= 0.82;
                    quality = 0.82;
                }
            }
        } finally {
            bitmap.close();
        }

        throw new Error('This image is too large to prepare for upload. Please choose a smaller image.');
    };

    profileEditForm.addEventListener('submit', async (event) => {
        if (preparedSubmission) {
            return;
        }

        const selectedInputs = photoInputs.filter((input) => input.files?.[0]);
        if (selectedInputs.length === 0) {
            return;
        }

        event.preventDefault();
        uploadStatus.hidden = false;
        uploadStatus.textContent = 'Preparing photos for upload…';
        uploadStatus.classList.remove('has-error');

        try {
            for (const input of selectedInputs) {
                const originalFile = input.files[0];
                const maxDimension = Number(input.dataset.maxDimension);
                const preparedFile = await compressPhoto(originalFile, maxDimension);

                if (preparedFile !== originalFile) {
                    const transfer = new DataTransfer();
                    transfer.items.add(preparedFile);
                    input.files = transfer.files;
                }
            }

            const submitter = event.submitter;
            preparedSubmission = true;
            profileEditForm.requestSubmit(submitter || undefined);
        } catch (error) {
            uploadStatus.textContent = error instanceof Error ? error.message : 'The selected photos could not be prepared.';
            uploadStatus.classList.add('has-error');
        }
    });
}

// AccessToggleManager.js

export class AccessToggleManager {
    constructor(dom, changedRowClass = 'sentinelpro-changed-row') {
        this.dom = dom;
        this.changedRowClass = changedRowClass;
        this.unsavedChangeTrackingSetup = false;
    }

    activateAccessLabelToggles() {
        const labels = document.querySelectorAll('.sentinelpro-access-label');
        labels.forEach(label => {
            if (label.dataset.locked === '1') return;

            label.style.cursor = 'pointer';

            label.addEventListener('click', () => {
                const isAllowed = label.dataset.status === '1';
                const newStatus = !isAllowed;

                label.textContent = newStatus ? 'ALLOWED' : 'RESTRICTED';
                label.dataset.status = newStatus ? '1' : '0';
                label.classList.toggle('allowed', newStatus);
                label.classList.toggle('restricted', !newStatus);

                const hiddenInput = label.nextElementSibling;
                if (hiddenInput && hiddenInput.type === 'hidden') {
                    hiddenInput.value = newStatus ? '1' : '0';
                }

                const row = label.closest('tr');
                if (row) row.classList.add(this.changedRowClass);
            });
        });
    }

    setupUnsavedChangeTracking() {
        if (this.unsavedChangeTrackingSetup) return;
        this.unsavedChangeTrackingSetup = true;

        let hasUnsavedChanges = false;

        const labels = this.dom.get('ACCESS_LABELS');
        labels?.forEach(label => {
            if (label.dataset.locked === '1') return;
            label.addEventListener('click', () => {
                hasUnsavedChanges = true;
            });
        });

        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
            }
        });

        const saveButton = document.querySelector('form button[type="submit"]');
        saveButton?.addEventListener('click', () => {
            hasUnsavedChanges = false;
        });
    }
}

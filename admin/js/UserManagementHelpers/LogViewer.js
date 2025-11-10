export class LogViewer {
    constructor(triggerButtonId = 'sentinelpro-view-access-logs', modalContainerId = 'sentinelpro-access-logs-modal') {
        this.triggerButton = document.getElementById(triggerButtonId);
        this.modalContainer = document.getElementById(modalContainerId);
        this.currentPage = 1;
        this.rowsPerPage = 25;
        this.allLogs = [];

        if (this.triggerButton && this.modalContainer) {
            this.triggerButton.addEventListener('click', () => this.showLogs());
        }
    }

    showLogs() {
        this.modalContainer.innerHTML = '🔄 Loading access logs...';
        this.modalContainer.style.display = 'block';

        fetch(sentinelpro_user_management_vars.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams({ 
                action: 'sentinelpro_get_access_logs',
                nonce: sentinelpro_user_management_vars.nonce
            })
        })
        .then(res => res.json())
        .then(({ success, data }) => {
            if (!success || !Array.isArray(data)) {
                this.modalContainer.innerHTML = '❌ Failed to load logs.';
                return;
            }

            this.allLogs = data;
            this.currentPage = 1;
            this.renderLogs();
        })
        .catch(err => {
            this.modalContainer.innerHTML = '❌ Error fetching logs.';
        });
    }

    renderLogs() {
        const startIndex = (this.currentPage - 1) * this.rowsPerPage;
        const endIndex = startIndex + this.rowsPerPage;
        const currentLogs = this.allLogs.slice(startIndex, endIndex);
        const totalPages = Math.ceil(this.allLogs.length / this.rowsPerPage);

        this.modalContainer.innerHTML = `
            <div class="sentinelpro-modal-content">
                <div class="modal-header">
                    <h2>🔐 Access Change Logs</h2>
                    <button class="modal-close-btn" id="close-log-modal-x">×</button>
                </div>
                <div class="table-container">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>User Email</th>
                                <th>Page</th>
                                <th>Old</th>
                                <th>New</th>
                                <th>Changed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${currentLogs.map(log => `
                                <tr>
                                    <td>${log.user_email || '(unknown)'}</td>
                                    <td><code>${log.page_key}</code></td>
                                    <td><span class="status-${log.old_value.toLowerCase()}">${log.old_value}</span></td>
                                    <td><span class="status-${log.new_value.toLowerCase()}">${log.new_value}</span></td>
                                    <td>${new Date(log.changed_at).toLocaleString()}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <div class="pagination-info">
                        Showing ${startIndex + 1}-${Math.min(endIndex, this.allLogs.length)} of ${this.allLogs.length} logs
                    </div>
                    <div class="pagination-controls">
                        ${this.renderPaginationControls(totalPages)}
                    </div>
                    <button class="close-button" id="close-log-modal">Close</button>
                </div>
            </div>
        `;

        this.attachEventListeners();
    }

    renderPaginationControls(totalPages) {
        if (totalPages <= 1) return '';

        let controls = '';
        
        // Previous button
        if (this.currentPage > 1) {
            controls += `<button class="pagination-btn" data-page="${this.currentPage - 1}">‹ Previous</button>`;
        }

        // Page numbers
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(totalPages, this.currentPage + 2);

        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === this.currentPage;
            controls += `<button class="pagination-btn ${isActive ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        // Next button
        if (this.currentPage < totalPages) {
            controls += `<button class="pagination-btn" data-page="${this.currentPage + 1}">Next ›</button>`;
        }

        return controls;
    }

    attachEventListeners() {
        // Close button (X)
        const closeBtnX = document.getElementById('close-log-modal-x');
        if (closeBtnX) {
            closeBtnX.addEventListener('click', () => {
                this.modalContainer.style.display = 'none';
            });
        }

        // Close button (bottom)
        const closeBtn = document.getElementById('close-log-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.modalContainer.style.display = 'none';
            });
        }

        // Pagination buttons
        const paginationBtns = document.querySelectorAll('.pagination-btn');
        paginationBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const page = parseInt(e.target.dataset.page);
                if (page && page !== this.currentPage) {
                    this.currentPage = page;
                    this.renderLogs();
                }
            });
        });

        // Close modal when clicking outside
        this.modalContainer.addEventListener('click', (e) => {
            if (e.target === this.modalContainer) {
                this.modalContainer.style.display = 'none';
            }
        });
    }
}

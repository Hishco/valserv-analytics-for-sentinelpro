// admin/js/user-management.js


import { DOMCache } from './UserManagementHelpers/DOMCache.js';
import { TableManager } from './UserManagementHelpers/TableManager.js';
import { FileUploader } from './UserManagementHelpers/FileUploader.js';
import { PreviewRenderer } from './UserManagementHelpers/PreviewRenderer.js';
import { exportCSV, handleAnchorClick } from './UserManagementHelpers/Utils.js';
import { LogViewer } from './UserManagementHelpers/LogViewer.js';
import { fetchUsers } from './UserManagementHelpers/UserFetcher.js';
import { AccessToggleManager } from './UserManagementHelpers/AccessToggleManager.js';
import { UserTableRenderer } from './UserManagementHelpers/UserTableRenderer.js';

const SELECTORS = {
    ACCESS_LABELS: '.sentinelpro-access-label',
    ROWS_PER_PAGE_SELECT: '#sentinelpro-rows-per-page',
    PAGINATION_CONTROLS: '#sentinelpro-pagination-controls',
    PAGINATION_STATUS: '#sentinelpro-pagination-status',
    EXPORT_ALL_BUTTON: '#export-user-all',
    EXPORT_VISIBLE_BUTTON: '#export-user-visible',
    UPLOAD_DROPZONE: '#sentinelpro-upload-dropzone',
    UPLOAD_INPUT: '#sentinelpro-upload-input',
    PREVIEW_WRAPPER: '#sentinelpro-preview-wrapper',
    HIDDEN_ACCESS_INPUT: '#sentinelpro-access-hidden',
    UPLOAD_FORM: '#sentinelpro-upload-form',
    TEXTAREA_UPLOAD: '#sentinelpro-access-textarea',
    TEXTAREA_UPLOAD_BUTTON: '#sentinelpro-upload-textarea-btn',
    URL_INPUT: '#sentinelpro-access-url-input',
    URL_IMPORT_BUTTON: '#sentinelpro-import-url-btn',
    USER_TABLE_ROWS: '.sentinelpro-user-row',
    USER_TABLE_HEAD_TH: '#sentinelpro-user-table thead th',
    SEARCH_INPUT: '#sentinelpro-user-search',
    NO_USERS_ROW: '.sentinelpro-no-users-row',
    FILTER_STATUS_SELECT: '#filter-by-status',
    USER_TABLE_BODY: '#sentinelpro-user-table-body', // ADDED
    ROLE_FILTER_SELECT: '#sentinelpro-user-role-filter', // ADDED (if you have a role filter)
    TOTAL_USERS_SPAN: '#sentinelpro-total-users', // ADDED (for updating total user count display)
};

const DEFAULT_CSV_HEADERS = ['User', 'Full Name', 'Email', 'Role', 'API Input', 'Dashboard', 'User Management', 'Post Analytics Column'];

function valservSanitizeEmail(email) {
    return email.replace(/^[\s"'`]+|[\s"'`]+$/g, '').toLowerCase();
}

class ValservUserManager {
    constructor() {
        this.dom = new DOMCache(SELECTORS);

        this.accessToggleManager = new AccessToggleManager(this.dom);
        this.userTableRenderer = new UserTableRenderer(this.dom, this.accessToggleManager);

        this.fileUploader = new FileUploader(
            this.dom.get('UPLOAD_DROPZONE'),
            this.dom.get('UPLOAD_INPUT')
        );

        this.previewRenderer = new PreviewRenderer(
            this.dom, // Pass DOMCache instance
            this.dom.get('PREVIEW_WRAPPER'),
            this.dom.get('HIDDEN_ACCESS_INPUT'),
            this.dom.get('UPLOAD_FORM'),
            'sentinelpro-changed-row'
        );

        this.setupEventListeners();
        this.initializeState();
        // Debug: Confirm table body exists before fetching users
        if (!this.dom.get('USER_TABLE_BODY')) {
                    // USER_TABLE_BODY element not found
    } else {
            this.fetchUsers(); // ADDED: Initial fetch to populate the table on page load
        }
    }

    initializeState() {
        this.accessToggleManager.activateAccessLabelToggles();
        this.accessToggleManager.setupUnsavedChangeTracking();

        const activeTab = localStorage.getItem('sentinelpro_active_tab');
        if (activeTab) {
            document.querySelectorAll('.nav-tab').forEach(tab => {
                if (tab.dataset.tab === activeTab) {
                    tab.click();
                }
            });
        }
    }

    fetchUsers(page = 1) {
        fetchUsers({
            dom: this.dom,
            page: page,
            search: this.dom.get('SEARCH_INPUT')?.value ?? '',
            role: this.dom.get('ROLE_FILTER_SELECT')?.value ?? '',
            perPage: this.dom.get('ROWS_PER_PAGE_SELECT')?.value ?? 25,
            onSuccess: (data) => {
                this.userTableRenderer.render(data.users);
                this.userTableRenderer.getTableManager().updatePagination(data.total_users, data.per_page, data.current_page);
                this.updateTotalUsersCount(data.total_users);
                this.accessToggleManager.setupUnsavedChangeTracking();
            },
            onError: (message) => {
                this.updateTotalUsersCount(0);
                // UserFetcher error
            }
        });
    }

    updateTotalUsersCount(count) {
        const totalUsersSpan = this.dom.get('TOTAL_USERS_SPAN');
        if (totalUsersSpan) {
            totalUsersSpan.textContent = count;
        } else {
    
        }
    }

    setupEventListeners() {
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', event => {
                event.preventDefault();
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                tab.classList.add('nav-tab-active');
                const contentId = tab.dataset.tab;
                document.getElementById(contentId).classList.add('active');
                localStorage.setItem('sentinelpro_active_tab', contentId);
            });
        });

        this.dom.get('TEXTAREA_UPLOAD_BUTTON')?.addEventListener('click', () => {
            const textarea = this.dom.get('TEXTAREA_UPLOAD');
            if (!textarea || textarea.value.trim() === '') {
                alert('Please enter CSV data into the textarea.');
                return;
            }

            // Use PapaParse for robust CSV parsing
            const { data: rows } = Papa.parse(textarea.value.trim(), { header: false });
            if (rows.length < 2) {
                alert('Textarea is empty or invalid CSV format.');
                return;
            }
            const headers = rows[0];
            const dataRows = rows.slice(1);
            // Validate column count
            if (!dataRows.every(row => row.length === headers.length)) {
                alert('CSV rows have inconsistent column counts.');
                return;
            }
            this.handleUploadedData(dataRows, headers);
        });

        this.dom.get('ROLE_FILTER_SELECT')?.addEventListener('change', () => {
            const selected = this.dom.get('ROLE_FILTER_SELECT')?.value;
    
            this.fetchUsers();
        });

        this.dom.get('URL_IMPORT_BUTTON')?.addEventListener('click', async (e) => {
            e.preventDefault();
            const url = this.dom.get('URL_INPUT')?.value.trim();

            if (!url) {
                alert('Please enter a public CSV URL.');
                return;
            }

            try {
                const response = await fetch(sentinelpro_user_management_vars.ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'sentinelpro_import_csv_url',
                        _wpnonce: sentinelpro_user_management_vars.nonce,
                        url: url
                    })
                });

                const result = await response.json();
                if (result.success && result.data && result.data.content) {
                    // Parse CSV content and show preview table
                    const csvText = result.data.content;
                    const rows = csvText.trim().split('\n').map(line => line.split(',').map(s => s.trim()));
                    if (rows.length < 2) throw new Error('CSV is empty or invalid');
                    const headers = rows[0];
                    const dataRows = rows.slice(1);
                    this.handleUploadedData(dataRows, headers);
                    alert(result.data?.message || '✅ Permissions from URL loaded. Review and apply changes below.');
                    return;
                } else if (result.success) {
                    alert(result.data?.message || '✅ Permissions from URL applied successfully.');
                    return;
                } else {
                    throw new Error(result.data?.message || 'Invalid server response');
                }
            } catch (err) {
                // URL Import Error
                alert('❌ URL Import Error: ' + err.message);
            }
        });

        const searchInputEl = this.dom.get('SEARCH_INPUT');
        if (searchInputEl) {
            let searchTimeout; // Debounce variable
            searchInputEl.addEventListener('input', () => {
        
                clearTimeout(searchTimeout); // Clear any previous timeout
                searchTimeout = setTimeout(() => {
                    this.fetchUsers(); // Call fetchUsers to trigger AJAX request
                }, 300); // Debounce time (300ms)
            });
            
            // Add Enter key handler for immediate search
            searchInputEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(searchTimeout); // Clear any pending debounced search
                    this.fetchUsers(); // Immediately trigger search
                }
            });
        } else {
    
        }

        // Status filter listener (UPDATED to call fetchUsers)
        this.dom.get('FILTER_STATUS_SELECT')?.addEventListener('change', () => {
            this.fetchUsers(); // Call fetchUsers when status filter changes
        });

        // Rows per page select listener (ADDED - if you want changing this to trigger fetch)
        this.dom.get('ROWS_PER_PAGE_SELECT')?.addEventListener('change', () => {
            this.fetchUsers(); // Call fetchUsers when rows per page changes
        });

        // Export buttons
        this.dom.get('EXPORT_ALL_BUTTON')?.addEventListener('click', () => {
            const rows = document.querySelectorAll('.sentinelpro-user-row');
            const allUserData = TableManager.extractFromRows(rows); // You may need to implement this utility
            const csvRows = [
                DEFAULT_CSV_HEADERS,
                ...allUserData.rows
            ];
            exportCSV(csvRows, 'all_user_access.csv');
        });

        this.dom.get('EXPORT_VISIBLE_BUTTON')?.addEventListener('click', () => {
            const rows = document.querySelectorAll('.sentinelpro-user-row');
            const visibleUserData = TableManager.extractFromRows(rows, true); // Pass true for visible only if needed
            const csvRows = [
                DEFAULT_CSV_HEADERS,
                ...visibleUserData.rows
            ];
            exportCSV(csvRows, 'visible_user_access.csv');
        });

        this.fileUploader.onFileReady = (dataRows, headers) => {
            this.handleUploadedData(dataRows, headers);
        };

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', handleAnchorClick);
        });
    }

    handleUploadedData(dataRows, headers) {
        if (!dataRows?.length || !headers?.length) {
            alert('Invalid or empty file content for processing.');
            if (this.fileUploader.fileInput?.files.length > 0) {
                this.fileUploader.reset();
            }
            return;
        }
        this.previewRenderer.render(dataRows, headers, this.findUserTableRow.bind(this));
        this.fileUploader.reset();
    }

    findUserTableRow(email) {
        const rows = document.querySelectorAll('.sentinelpro-user-row');
        const cleanEmail = valservSanitizeEmail(email);
        for (const row of rows) {
            // Use data-user-email attribute if present
            if (row.dataset.userEmail && valservSanitizeEmail(row.dataset.userEmail) === cleanEmail) {
                return row;
            }
            const userCode = row.querySelector('td code');
            if (userCode && valservSanitizeEmail(userCode.textContent) === cleanEmail) {
                return row;
            }
        }
        return null;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new ValservUserManager();
    new LogViewer();
    document.getElementById('view-access-logs')?.addEventListener('click', () => {
        const panel = document.getElementById('access-log-panel');
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    });
});
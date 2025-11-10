// UserTableRenderer.js

import { TableManager } from './TableManager.js';

/**
 * Responsible for rendering the user table rows and managing table updates.
 */
export class UserTableRenderer {
    constructor(dom, accessToggleManager) {
        this.dom = dom;
        this.accessToggleManager = accessToggleManager;
        this.tableManager = null; // Will be set on first render
    }

    render(users) {
        const userTableBody = this.dom.get('USER_TABLE_BODY');
        const noUsersClass = this.dom.get('NO_USERS_ROW')?.className || 'sentinelpro-no-users-row';

        if (!userTableBody) return;

        userTableBody.innerHTML = ''; // Clear table

        if (!Array.isArray(users)) {
            userTableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: red;">❌ Invalid user data returned from server.</td></tr>`;
            return;
        }

        if (users.length === 0) {
            userTableBody.innerHTML = `<tr class="${noUsersClass}">
                <td colspan="7" style="text-align: center;">No users found.</td>
            </tr>`;
            return;
        }

        users.forEach(user => {
            const row = document.createElement('tr');
            row.classList.add('sentinelpro-user-row');
            row.dataset.userId = user.id;
            row.dataset.username = user.user_login;
            row.dataset.useremail = user.user_email;
            row.dataset.role = user.role;

            row.dataset.apiInputStatus = user.access.api_input ? '1' : '0';
            row.dataset.dashboardStatus = user.access.dashboard ? '1' : '0';
            row.dataset.userMgmtStatus = user.access.user_mgmt ? '1' : '0';
            row.dataset.postColumnStatus = user.access.post_column ? '1' : '0';
            row.dataset.accessstatus = user.access.api_input ? 'allowed' : 'restricted';

            row.innerHTML = `
                <td>${user.user_login}${user.is_superuser ? ' <span title="Superuser" style="color:#f5b400;font-size:16px;margin-left:4px;">★</span>' : ''}</td>
                <td>${user.full_name || 'N/A'}</td>
                <td><code>${user.user_email}</code></td>
                <td>${user.role}</td>
                <td>
                    <span class="sentinelpro-access-label ${user.access.api_input ? 'allowed' : 'restricted'}"
                          data-status="${user.access.api_input ? '1' : '0'}"
                          data-locked="${user.is_superuser ? '1' : '0'}">
                        ${user.access.api_input ? 'ALLOWED' : 'RESTRICTED'}
                    </span>
                    <input type="hidden" name="sentinelpro_access[${user.id}][api_input]" value="${user.access.api_input ? '1' : '0'}">
                </td>
                <td>
                    <span class="sentinelpro-access-label ${user.access.dashboard ? 'allowed' : 'restricted'}"
                          data-status="${user.access.dashboard ? '1' : '0'}"
                          data-locked="${user.is_superuser ? '1' : '0'}">
                        ${user.access.dashboard ? 'ALLOWED' : 'RESTRICTED'}
                    </span>
                    <input type="hidden" name="sentinelpro_access[${user.id}][dashboard]" value="${user.access.dashboard ? '1' : '0'}">
                </td>
                <td>
                    <span class="sentinelpro-access-label ${user.access.user_mgmt ? 'allowed' : 'restricted'}"
                          data-status="${user.access.user_mgmt ? '1' : '0'}"
                          data-locked="${user.is_superuser ? '1' : '0'}">
                        ${user.access.user_mgmt ? 'ALLOWED' : 'RESTRICTED'}
                    </span>
                    <input type="hidden" name="sentinelpro_access[${user.id}][user_mgmt]" value="${user.access.user_mgmt ? '1' : '0'}">
                </td>
                <td>
                    <span class="sentinelpro-access-label ${user.access.post_column ? 'allowed' : 'restricted'}"
                          data-status="${user.access.post_column ? '1' : '0'}"
                          data-locked="${user.is_superuser ? '1' : '0'}">
                        ${user.access.post_column ? 'ALLOWED' : 'RESTRICTED'}
                    </span>
                    <input type="hidden" name="sentinelpro_access[${user.id}][post_column]" value="${user.access.post_column ? '1' : '0'}">
                </td>
            `;

            userTableBody.appendChild(row);
        });

        const allRows = Array.from(userTableBody.querySelectorAll('.sentinelpro-user-row'));

        if (!this.tableManager) {
            this.tableManager = new TableManager(
                allRows,
                this.dom.get('ROWS_PER_PAGE_SELECT'),
                this.dom.get('PAGINATION_CONTROLS'),
                this.dom.get('PAGINATION_STATUS'),
                this.dom.get('USER_TABLE_HEAD_TH'),
                noUsersClass
            );
        } else {
            this.tableManager.allRows = allRows;
            this.tableManager.applyFilters(
                this.dom.get('SEARCH_INPUT')?.value.trim().toLowerCase() || '',
                this.dom.get('ROLE_FILTER_SELECT')?.value.trim().toLowerCase() || ''
            );
        }

        this.accessToggleManager.activateAccessLabelToggles();
    }

    getTableManager() {
        return this.tableManager;
    }
}

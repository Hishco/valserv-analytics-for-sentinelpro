// UserFetcher.js

/**
 * Handles AJAX request to fetch users.
 * @param {Object} options
 * @param {DOMCache} options.dom - Instance of DOMCache
 * @param {number} options.page - Page number
 * @param {string} options.search - Search query
 * @param {string} options.role - Selected role
 * @param {number} options.perPage - Users per page
 * @param {function} options.onSuccess - Callback on successful fetch
 * @param {function} options.onError - Callback on error
 */
export function fetchUsers({ dom, page = 1, search = '', role = '', perPage = 25, onSuccess, onError }) {
    const userTableBody = dom.get('USER_TABLE_BODY');
    if (!userTableBody) {
        return;
    }

    userTableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">🔄 Loading users...</td></tr>';

    const data = new URLSearchParams({
        action: 'sentinelpro_fetch_users',
        page: page,
        search: search,
        role: role,
        per_page: perPage,
        nonce: sentinelpro_user_management_vars.nonce
    });

    fetch(sentinelpro_user_management_vars.ajaxurl, {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(response => {
        if (response.success && Array.isArray(response.data?.users)) {
            onSuccess(response.data);
        } else {
            const msg = response.data?.message || 'Unknown error from server.';
            userTableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: red;">❌ Error: ${msg}</td></tr>`;
            onError(msg);
        }
    })
    .catch(error => {
        userTableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: red;">❌ Network Error. Please try again.</td></tr>';
        onError(error.message);
    });
}

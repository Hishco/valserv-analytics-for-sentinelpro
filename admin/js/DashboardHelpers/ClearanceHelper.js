// DashboardHelpers/ClearanceHelper.js

export const ClearanceLevels = {
  ADMIN: 'admin',
  EDITOR: 'editor',
  VIEWER: 'viewer',
  RESTRICTED: 'restricted'
};

const DEBUG = false;

export async function fetchClearanceLevel() {
  try {
    const response = await fetch(window.valservDashboardData?.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'sentinelpro_get_clearance',
        nonce: window.valservDashboardData?.nonce
      })
    });
    if (!response.ok) throw new Error('Network error');
    const data = await response.json();
    return data.success ? data.data.clearance : ClearanceLevels.RESTRICTED;
  } catch (err) {
    return ClearanceLevels.RESTRICTED;
  }
}
  
export async function setClearanceLevel(level) {
  try {
    await fetch(window.valservDashboardData?.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'sentinelpro_set_clearance',
        clearance: level,
        nonce: window.valservDashboardData?.nonce
      })
    });
    const updated = await fetchClearanceLevel();
    return updated;
  } catch (err) {
    return ClearanceLevels.RESTRICTED;
  }
}
  
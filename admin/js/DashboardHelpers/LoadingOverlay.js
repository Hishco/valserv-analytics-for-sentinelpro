// DashboardHelpers/LoadingOverlay.js

export function showLoadingOverlay() {
    let overlay = document.getElementById('sentinelpro-loading-message');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'sentinelpro-loading-message';
      overlay.style.position = 'fixed';
      overlay.style.top = 0;
      overlay.style.left = 0;
      overlay.style.width = '100vw';
      overlay.style.height = '100vh';
      overlay.style.background = 'rgba(255,255,255,0.7)';
      overlay.style.display = 'flex';
      overlay.style.alignItems = 'center';
      overlay.style.justifyContent = 'center';
      overlay.style.zIndex = 9999;
      overlay.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
          <div class="sentinelpro-spinner" style="border:4px solid #eee;border-top:4px solid #1976d2;border-radius:50%;width:36px;height:36px;animation:spin 1s linear infinite;"></div>
          <div style="font-size:20px;color:#1976d2;font-weight:600;">Loading...</div>
      </div>`;
      document.body.appendChild(overlay);
  
      const style = document.createElement('style');
      style.innerHTML = `@keyframes spin { 0% { transform: rotate(0deg);} 100% { transform: rotate(360deg);} }`;
      document.head.appendChild(style);
    }
    overlay.style.display = 'flex';
  }
  
  export function hideLoadingOverlay() {
    const overlay = document.getElementById('sentinelpro-loading-message');
    if (overlay) overlay.style.display = 'none';
  }
  
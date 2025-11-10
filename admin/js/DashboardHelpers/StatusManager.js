// StatusManager.js
export default class StatusManager {
    constructor(elementId) {
        this.el = document.getElementById(elementId);
        if (!this.el) {
            this.el = document.createElement('div');
            this.el.id = elementId;
            this.el.style.margin = '10px 0';
            document.getElementById('sentinelpro-chart')?.parentNode?.insertBefore(this.el, document.getElementById('sentinelpro-chart'));
        }
    }

        show(message, isError = false) {
        this.el.textContent = message;
        this.el.classList.toggle('error', isError);
        this.el.style.opacity = 1;

        clearTimeout(this.fadeTimeout);
        this.fadeTimeout = setTimeout(() => {
            this.el.style.opacity = 0.3;
        }, 5000);
    }

    clear() {
        this.el.textContent = '';
        this.el.classList.remove('error');
        this.el.style.opacity = 0;
    }
}

class RTRApiClient {
    constructor(config) {
        this.config = config;
        this.baseUrl = this._getBaseUrl();
        this.nonce = this._getNonce();
        
        if (!this.nonce) {
            console.error('WARNING: No REST API nonce found.', {
                rtrDashboardConfig: window.rtrDashboardConfig,
                wpApiSettings: window.wpApiSettings
            });
        }
    }

    _getBaseUrl() {

        return this.config?.apiUrl || this.config?.restUrl || '';
    }

    _getNonce() {
        return window.rtrDashboardConfig?.nonce || 
               window.wpApiSettings?.nonce || 
               this.config?.nonce || 
               '';
    }

    async _request(endpoint, options = {}) {
        let baseUrl;
        if (endpoint.startsWith('/emails')) {
            baseUrl = this.config?.emailApiUrl || this.config.siteUrl + '/wp-json/directreach/v2';
        } else {
            baseUrl = this.config?.apiUrl || this.config?.restUrl || '';
        }
        
        const url = `${baseUrl}${endpoint}`;
        
        const fetchOptions = {
            ...options,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce,
                ...(options.headers || {})
            }
        };

        const response = await fetch(url, fetchOptions);

        if (!response.ok) {
            const errorText = await response.text();
            let errorData;
            
            try {
                errorData = JSON.parse(errorText);
            } catch {
                errorData = { message: errorText };
            }

            // Special handling for nonce errors
            if (response.status === 403 && errorData.code === 'rest_cookie_invalid_nonce') {
                throw new Error('Session expired. Please refresh the page and try again.');
            }

            throw new Error(errorData.message || `Request failed: ${response.status}`);
        }

        return response.json();
    }

    async get(endpoint) {
        return this._request(endpoint, { method: 'GET' });
    }

    async post(endpoint, data) {
        return this._request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
}

export default RTRApiClient;
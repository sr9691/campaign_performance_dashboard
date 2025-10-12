/**
 * Global Templates - Main Entry Point
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.5.0
 */

import TemplateManager from './modules/template-manager.js';
import NotificationSystem from './modules/notifications.js';

class GlobalTemplatesApp {
    constructor(config) {
        this.config = config;
        this.notifications = new NotificationSystem();
        
        // Initialize template manager with isGlobal flag
        this.templateManager = new TemplateManager(config, null, { isGlobal: true });
        
        this.setupEventListeners();
    }
    
    async init() {
        try {
            await this.templateManager.loadTemplates(0); // Load global templates
            console.log('Global Templates initialized successfully');
        } catch (error) {
            console.error('Error initializing Global Templates:', error);
        }
    }
    
    setupEventListeners() {
        // Template manager events
        this.templateManager.on('notification', (notification) => {
            this.showNotification(notification.message, notification.type);
        });
        
        // Settings dropdown
        this.setupSettingsDropdown();
    }
    
    showNotification(message, type = 'info') {
        const container = document.querySelector('.notification-container');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = this.getNotificationIcon(type);
        notification.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        });
    }
    
    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    setupSettingsDropdown() {
        const settingsBtn = document.querySelector('[data-toggle="settings-dropdown"]');
        const dropdown = document.querySelector('.settings-dropdown');
        
        if (!settingsBtn || !dropdown) return;
        
        settingsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        
        document.addEventListener('click', () => {
            dropdown.classList.remove('active');
        });
        
        dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    if (typeof drGlobalTemplatesConfig !== 'undefined') {
        const app = new GlobalTemplatesApp(drGlobalTemplatesConfig);
        await app.init();
        window.globalTemplatesApp = app;
    }
});
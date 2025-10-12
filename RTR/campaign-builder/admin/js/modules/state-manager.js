/**
 * State Manager Module
 * Handles state persistence (localStorage + database)
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

export default class StateManager {
    constructor(config) {
        this.config = config;
        this.storageKey = 'dr_campaign_builder_state';
        this.saveTimeout = null;
        this.autoSaveInterval = 30000; // 30 seconds
        this.lastSaved = null;
        this.isDirty = false;
    }
    
    /**
     * Initialize state manager
     */
    init() {
        this.startAutoSave();
        window.addEventListener('beforeunload', (e) => this.handleBeforeUnload(e));
    }
    
    /**
     * Get current state from localStorage
     * 
     * @returns {Object} Current workflow state
     */
    getState() {
        try {
            const stored = localStorage.getItem(this.storageKey);
            if (stored) {
                return JSON.parse(stored);
            }
        } catch (error) {
            console.error('Error reading state from localStorage:', error);
        }
        
        return this.getDefaultState();
    }
    
    /**
     * Save state to localStorage
     * 
     * @param {Object} state - Workflow state to save
     */
    saveToLocalStorage(state) {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(state));
            this.isDirty = false;
            return true;
        } catch (error) {
            console.error('Error saving state to localStorage:', error);
            return false;
        }
    }
    
    /**
     * Save state to database via REST API
     * 
     * @param {Object} state - Workflow state to save
     * @returns {Promise}
     */
    async saveToDatabase(state) {
        try {
            const response = await fetch(`${this.config.apiUrl}/workflow`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify(state)
            });
            
            if (!response.ok) {
                throw new Error('Failed to save to database');
            }
            
            const data = await response.json();
            this.lastSaved = new Date();
            this.isDirty = false;
            
            return data;
        } catch (error) {
            console.error('Error saving state to database:', error);
            throw error;
        }
    }
    
    /**
     * Update state (saves to localStorage immediately, queues database save)
     * 
     * @param {Object} updates - Partial state updates
     * @returns {Object} Updated state
     */
    updateState(updates) {
        const currentState = this.getState();
        const newState = { ...currentState, ...updates };
        
        // Save to localStorage immediately
        this.saveToLocalStorage(newState);
        this.isDirty = true;
        
        // Queue database save (debounced)
        this.queueDatabaseSave(newState);
        
        return newState;
    }
    
    /**
     * Queue database save with debouncing
     * 
     * @param {Object} state - State to save
     */
    queueDatabaseSave(state) {
        // Clear existing timeout
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }
        
        // Set new timeout (debounce 2 seconds)
        this.saveTimeout = setTimeout(() => {
            this.saveToDatabase(state);
        }, 2000);
    }
    
    /**
     * Force immediate save to database
     * 
     * @returns {Promise}
     */
    async forceSave() {
        const state = this.getState();
        return await this.saveToDatabase(state);
    }
    
    /**
     * Start auto-save interval
     */
    startAutoSave() {
        setInterval(() => {
            if (this.isDirty) {
                const state = this.getState();
                this.saveToDatabase(state).catch(error => {
                    console.error('Auto-save failed:', error);
                });
            }
        }, this.autoSaveInterval);
    }
    
    /**
     * Clear all state
     */
    clearState() {
        localStorage.removeItem(this.storageKey);
        this.isDirty = false;
    }
    
    /**
     * Load state from database
     * 
     * @returns {Promise<Object>}
     */
    async loadFromDatabase() {
        try {
            const response = await fetch(`${this.config.apiUrl}/workflow`, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to load from database');
            }
            
            const data = await response.json();
            
            // Merge with localStorage (localStorage takes precedence if newer)
            const localState = this.getState();
            const dbState = data.data || this.getDefaultState();
            
            // Use whichever is newer
            if (localState.lastModified && dbState.lastModified) {
                const state = new Date(localState.lastModified) > new Date(dbState.lastModified) 
                    ? localState 
                    : dbState;
                this.saveToLocalStorage(state);
                return state;
            }
            
            this.saveToLocalStorage(dbState);
            return dbState;
            
        } catch (error) {
            console.error('Error loading state from database:', error);
            return this.getState();
        }
    }
    
    /**
     * Get default state structure
     * 
     * @returns {Object}
     */
    getDefaultState() {
        return {
            currentStep: 'client',
            clientId: null,
            clientName: '',
            campaignId: null,
            campaignName: '',
            utmCampaign: '',
            templates: {
                problem: [],
                solution: [],
                offer: []
            },
            settings: {
                roomThresholds: {},
                scoringRules: {}
            },
            completedSteps: [],
            lastModified: new Date().toISOString()
        };
    }
    
    /**
     * Handle before unload event
     * 
     * @param {Event} e - Before unload event
     */
    handleBeforeUnload(e) {
        if (this.isDirty) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    }
    
    /**
     * Get last saved timestamp
     * 
     * @returns {Date|null}
     */
    getLastSaved() {
        return this.lastSaved;
    }
    
    /**
     * Check if state has unsaved changes
     * 
     * @returns {boolean}
     */
    hasUnsavedChanges() {
        return this.isDirty;
    }
}
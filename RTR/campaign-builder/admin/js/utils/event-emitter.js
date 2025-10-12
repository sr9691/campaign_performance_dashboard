/**
 * Event Emitter Utility
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.0.0
 */

export default class EventEmitter {
    constructor() {
        this.events = {};
    }
    
    on(event, callback) {
        if (!this.events[event]) {
            this.events[event] = [];
        }
        this.events[event].push(callback);
    }
    
    off(event, callback) {
        if (!this.events[event]) return;
        
        this.events[event] = this.events[event].filter(cb => cb !== callback);
    }
    
    emit(event, data) {
        if (!this.events[event]) return;
        
        this.events[event].forEach(callback => {
            callback(data);
        });
    }
}
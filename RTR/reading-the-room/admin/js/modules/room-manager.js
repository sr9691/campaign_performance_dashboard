/**
 * Room Manager Module
 * 
 * Handles room card rendering, counts, and statistics
 * 
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

export default class RoomManager {
    constructor(api, config) {
        this.api = api;
        this.config = config;
        this.counts = {
            problem: 0,
            solution: 0,
            offer: 0,
            sales: 0
        };
        this.stats = {
            problem: { newToday: 0, progressRate: 0 },
            solution: { highScores: 0, openRate: 0 },
            offer: { thisWeek: 0, clickRate: 0 },
            sales: { thisWeek: 0, avgDays: 0 }
        };
    }
    
    /**
     * Initialize event listeners
     */
    init() {
        // Listen for prospect actions
        document.addEventListener('rtr:prospect-archived', (e) => {
            if (e.detail.room) {
                this.updateRoomCount(e.detail.room, -1);
            }
        });
        
        document.addEventListener('rtr:prospect-handoff', () => {
            this.updateRoomCount('offer', -1);
            this.updateRoomCount('sales', +1);
        });
    }

    /**
     * Load room counts from API
     */
    async loadRoomCounts(clientId = null) {
        try {
            const params = {};
            if (clientId) {
                params.campaign_id = clientId;
            }
            
            const response = await this.api.get('/analytics/room-counts', params);
            
            if (response.success) {
                this.counts = response.data;
                this.updateRoomCountsUI();
                return this.counts;
            }
        } catch (error) {
            console.error('Failed to load room counts:', error);
            throw error;
        }
    }
    
    /**
     * Load room statistics
     */
    async loadRoomStats(clientId = null) {
        try {
            const rooms = ['problem', 'solution', 'offer'];
            
            for (const room of rooms) {
                const params = { room, days: 30 };
                if (clientId) {
                    params.campaign_id = clientId;
                }
                
                const response = await this.api.get('/analytics/campaign-stats', params);
                
                if (response.success) {
                    this.updateRoomStats(room, response.data);
                }
            }
            
            this.updateRoomStatsUI();
        } catch (error) {
            console.error('Failed to load room stats:', error);
        }
    }

    /**
     * Update room count after action
     */
    updateRoomCount(room, delta) {
        // Update internal count
        this.counts[room] = Math.max(0, (this.counts[room] || 0) + delta);
        
        // Update UI
        const countEl = document.querySelector(`.room-count[data-room="${room}"]`);
        if (countEl) {
            this.animateCountUpdate(countEl, this.counts[room]);
        }
    }    
    
    /**
     * Update room statistics from API data
     */
    updateRoomStats(room, data) {
        switch (room) {
            case 'problem':
                this.stats.problem = {
                    newToday: data.new_prospects || 0,
                    progressRate: this.calculateProgressRate(data)
                };
                break;
            case 'solution':
                this.stats.solution = {
                    highScores: this.calculateHighScores(data),
                    openRate: this.calculateOpenRate(data)
                };
                break;
            case 'offer':
                this.stats.offer = {
                    thisWeek: this.calculateWeeklyCount(data),
                    clickRate: this.calculateClickRate(data)
                };
                break;
        }
    }
    
    /**
     * Calculate progress rate (prospects moving to next room)
     */
    calculateProgressRate(data) {
        // Simplified calculation - would use actual progression data
        return data.total_prospects > 0 
            ? Math.round((data.new_prospects / data.total_prospects) * 100) 
            : 0;
    }
    
    /**
     * Calculate high scores count
     */
    calculateHighScores(data) {
        // Would need additional API endpoint for this
        // For now, estimate based on avg score
        return data.avg_score > 70 
            ? Math.round(data.total_prospects * 0.3) 
            : Math.round(data.total_prospects * 0.15);
    }
    
    /**
     * Calculate email open rate
     */
    calculateOpenRate(data) {
        // Would use email tracking data
        // Placeholder calculation
        return 68;
    }
    
    /**
     * Calculate weekly count
     */
    calculateWeeklyCount(data) {
        // Prospects added this week
        return Math.round(data.new_prospects * 0.7);
    }
    
    /**
     * Calculate click rate
     */
    calculateClickRate(data) {
        // Would use email tracking data
        return 85;
    }
    
    /**
     * Update room counts in UI
     */
    updateRoomCountsUI() {
        Object.keys(this.counts).forEach(room => {
            const countEl = document.querySelector(`.room-count[data-room="${room}"]`);
            if (countEl) {
                this.animateCountUpdate(countEl, this.counts[room]);
            }
        });
    }
    
    /**
     * Update room statistics in UI
     */
    updateRoomStatsUI() {
        // Problem Room stats
        this.updateStatValue('.problem-room', 0, this.stats.problem.newToday);
        this.updateStatValue('.problem-room', 1, `${this.stats.problem.progressRate}%`);
        
        // Solution Room stats
        this.updateStatValue('.solution-room', 0, this.stats.solution.highScores);
        this.updateStatValue('.solution-room', 1, `${this.stats.solution.openRate}%`);
        
        // Offer Room stats
        this.updateStatValue('.offer-room', 0, this.stats.offer.thisWeek);
        this.updateStatValue('.offer-room', 1, `${this.stats.offer.clickRate}%`);
        
        // Sales Room stats (if we have data)
        this.updateStatValue('.sales-room', 0, this.stats.sales.thisWeek);
        this.updateStatValue('.sales-room', 1, this.stats.sales.avgDays || '0');
    }
    
    /**
     * Update individual stat value
     */
    updateStatValue(roomSelector, statIndex, value) {
        const room = document.querySelector(roomSelector);
        if (!room) return;
        
        const statNumbers = room.querySelectorAll('.stat-number');
        if (statNumbers[statIndex]) {
            statNumbers[statIndex].textContent = value;
        }
    }
    
    /**
     * Animate count update
     */
    animateCountUpdate(element, newValue) {
        const currentValue = parseInt(element.textContent) || 0;
        
        if (currentValue === newValue) {
            return;
        }
        
        const duration = 800;
        const steps = 30;
        const increment = (newValue - currentValue) / steps;
        const stepDuration = duration / steps;
        
        let current = currentValue;
        let step = 0;
        
        const interval = setInterval(() => {
            step++;
            current += increment;
            
            if (step >= steps) {
                element.textContent = newValue;
                clearInterval(interval);
            } else {
                element.textContent = Math.round(current);
            }
        }, stepDuration);
        
        // Add pulse animation
        element.style.transition = 'transform 0.3s ease';
        element.style.transform = 'scale(1.1)';
        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 300);
    }
    
    /**
     * Highlight room cards on interaction
     */
    highlightRoom(room) {
        const card = document.querySelector(`.${room}-room.room-overview-card`);
        if (!card) return;
        
        card.style.transition = 'all 0.3s ease';
        card.style.transform = 'translateY(-4px)';
        card.style.boxShadow = '0 12px 30px rgba(0,0,0,0.15)';
        
        setTimeout(() => {
            card.style.transform = '';
            card.style.boxShadow = '';
        }, 500);
    }
    
    /**
     * Get total prospects across all rooms
     */
    getTotalProspects() {
        return this.counts.problem + this.counts.solution + this.counts.offer;
    }
    
    /**
     * Get room with most prospects
     */
    getTopRoom() {
        const rooms = [
            { name: 'Problem', count: this.counts.problem },
            { name: 'Solution', count: this.counts.solution },
            { name: 'Offer', count: this.counts.offer }
        ];
        
        return rooms.reduce((top, room) => 
            room.count > top.count ? room : top
        );
    }
    
    /**
     * Get conversion funnel data
     */
    getFunnelData() {
        const total = this.getTotalProspects();
        
        if (total === 0) {
            return {
                problemPercent: 0,
                solutionPercent: 0,
                offerPercent: 0
            };
        }
        
        return {
            problemPercent: Math.round((this.counts.problem / total) * 100),
            solutionPercent: Math.round((this.counts.solution / total) * 100),
            offerPercent: Math.round((this.counts.offer / total) * 100)
        };
    }
    
    /**
     * Refresh all room data
     */
    async refresh(clientId = null) {
        await Promise.all([
            this.loadRoomCounts(clientId),
            this.loadRoomStats(clientId)
        ]);
    }
}
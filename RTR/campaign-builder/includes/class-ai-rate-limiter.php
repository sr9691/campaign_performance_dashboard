<?php
/**
 * AI Rate Limiter
 *
 * Tracks and enforces API usage limits to prevent excessive costs.
 *
 * @package DirectReach
 * @subpackage RTR
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPD_AI_Rate_Limiter {

    /**
     * Transient key for rate limit tracking
     */
    const TRANSIENT_KEY = 'directreach_ai_rate_limit';

    /**
     * Option key for daily statistics
     */
    const STATS_OPTION_KEY = 'directreach_ai_daily_stats';

    /**
     * Check if rate limit allows another request
     *
     * @return true|WP_Error True if allowed, error if limit exceeded
     */
    public function check_limit() {
        // Check if rate limiting is disabled
        $rate_limit_enabled = get_option( 'dr_rate_limit_enabled', true );
        if ( ! $rate_limit_enabled ) {
            return true; // No limit
        }
        
        $settings = new CPD_AI_Settings_Manager();
        
        $limit = $settings->get_rate_limit();

        $current_count = $this->get_current_count();

        if ( $current_count >= $limit ) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    'AI generation rate limit exceeded (%d/%d per hour). Please try again later.',
                    $current_count,
                    $limit
                ),
                array( 'status' => 429 )
            );
        }

        return true;
    }

    /**
     * Increment the rate limit counter
     *
     * @return int New count
     */
    public function increment() {
        $current = $this->get_rate_limit_data();
        $current['count']++;
        $current['requests'][] = current_time( 'timestamp' );

        set_transient( self::TRANSIENT_KEY, $current, HOUR_IN_SECONDS );

        // Also update daily stats
        $this->update_daily_stats();

        return $current['count'];
    }

    /**
     * Get current count
     *
     * @return int Current request count
     */
    public function get_current_count() {
        $data = $this->get_rate_limit_data();
        return $data['count'];
    }

    /**
     * Get rate limit data
     *
     * @return array Rate limit data
     */
    private function get_rate_limit_data() {
        $data = get_transient( self::TRANSIENT_KEY );

        if ( false === $data ) {
            $data = array(
                'count' => 0,
                'started_at' => current_time( 'timestamp' ),
                'requests' => array(),
            );
        }

        return $data;
    }

    /**
     * Reset rate limit counter
     *
     * @return bool Success
     */
    public function reset() {
        return delete_transient( self::TRANSIENT_KEY );
    }

    /**
     * Get remaining requests
     *
     * @return int Remaining requests allowed
     */
    public function get_remaining() {
        $settings = new CPD_AI_Settings_Manager();
        $limit = $settings->get_rate_limit();
        $current = $this->get_current_count();

        return max( 0, $limit - $current );
    }

    /**
     * Update daily statistics
     */
    private function update_daily_stats() {
        $today = current_time( 'Y-m-d' );
        $stats = get_option( self::STATS_OPTION_KEY, array() );

        if ( ! isset( $stats[ $today ] ) ) {
            $stats[ $today ] = array(
                'count' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
            );
        }

        $stats[ $today ]['count']++;

        // Keep only last 30 days
        $cutoff = date( 'Y-m-d', strtotime( '-30 days' ) );
        foreach ( array_keys( $stats ) as $date ) {
            if ( $date < $cutoff ) {
                unset( $stats[ $date ] );
            }
        }

        update_option( self::STATS_OPTION_KEY, $stats );
    }

    /**
     * Record generation stats
     *
     * @param int   $tokens Token count
     * @param float $cost Cost in USD
     */
    public function record_generation( $tokens, $cost ) {
        $today = current_time( 'Y-m-d' );
        $stats = get_option( self::STATS_OPTION_KEY, array() );

        if ( ! isset( $stats[ $today ] ) ) {
            $stats[ $today ] = array(
                'count' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
            );
        }

        $stats[ $today ]['total_tokens'] += $tokens;
        $stats[ $today ]['total_cost'] += $cost;

        update_option( self::STATS_OPTION_KEY, $stats );
    }

    /**
     * Get usage statistics
     *
     * @param string $period Period (today, week, month)
     * @return array Statistics
     */
    public function get_usage_stats( $period = 'today' ) {
        $stats = get_option( self::STATS_OPTION_KEY, array() );
        $today = current_time( 'Y-m-d' );

        switch ( $period ) {
            case 'today':
                return $stats[ $today ] ?? array(
                    'count' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0,
                );

            case 'week':
                return $this->aggregate_stats( $stats, 7 );

            case 'month':
                return $this->aggregate_stats( $stats, 30 );

            default:
                return array();
        }
    }

    /**
     * Aggregate stats over period
     *
     * @param array $stats All stats
     * @param int   $days Number of days
     * @return array Aggregated stats
     */
    private function aggregate_stats( $stats, $days ) {
        $cutoff = date( 'Y-m-d', strtotime( "-{$days} days" ) );
        
        $aggregated = array(
            'count' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
        );

        foreach ( $stats as $date => $day_stats ) {
            if ( $date >= $cutoff ) {
                $aggregated['count'] += $day_stats['count'];
                $aggregated['total_tokens'] += $day_stats['total_tokens'];
                $aggregated['total_cost'] += $day_stats['total_cost'];
            }
        }

        return $aggregated;
    }

    /**
     * Get average cost per email
     *
     * @param string $period Period
     * @return float Average cost
     */
    public function get_average_cost( $period = 'month' ) {
        $stats = $this->get_usage_stats( $period );

        if ( $stats['count'] === 0 ) {
            return 0;
        }

        return $stats['total_cost'] / $stats['count'];
    }
}
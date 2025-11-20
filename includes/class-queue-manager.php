<?php
/**
 * Queue Manager - Steps 2-3: Total Count and Collect All Listing IDs
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Queue_Manager {
    
    /**
     * Transient name for listing IDs queue
     */
    const QUEUE_TRANSIENT = 'as24_listing_ids_queue';
    
    /**
     * Transient name for queue metadata
     */
    const QUEUE_META_TRANSIENT = 'as24_listing_ids_queue_meta';
    
    /**
     * Step 2: Get total listings count
     * 
     * @return int|WP_Error Total count or error
     */
    public static function get_total_listings() {
        AS24_Logger::info('[Step 2] Getting total listings count...', 'import');
        
        $query = AS24_Queries::get_total_count_query();
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            AS24_Logger::error('[Step 2] Failed to get total count: ' . $data->get_error_message(), 'import');
            return $data;
        }
        
        if (!isset($data['data']['listings']['metadata']['totalItems'])) {
            AS24_Logger::error('[Step 2] Invalid response format', 'import');
            return new WP_Error('invalid_response', __('Invalid API response format.', 'as24-sync'));
        }
        
        $total = intval($data['data']['listings']['metadata']['totalItems']);
        
        // Store in transient with timestamp
        set_transient('as24_total_listings', $total, HOUR_IN_SECONDS);
        set_transient('as24_total_listings_time', time(), HOUR_IN_SECONDS);
        
        AS24_Logger::info(sprintf('[Step 2] Total listings: %d', $total), 'import');
        
        return $total;
    }
    
    /**
     * Step 3: Collect all listing IDs
     * Fetches all IDs using lightweight query and stores in queue
     * 
     * @return array|WP_Error Array of listing IDs or error
     */
    public static function collect_all_listing_ids() {
        AS24_Logger::info('[Step 3] Collecting all listing IDs...', 'import');
        
        // First get total count
        $total = self::get_total_listings();
        if (is_wp_error($total)) {
            return $total;
        }
        
        $all_ids = array();
        $page = 1;
        $per_page = 50; // Fetch 50 IDs per request
        $total_pages = ceil($total / $per_page);
        
        AS24_Logger::info(sprintf('[Step 3] Fetching %d listings in %d pages...', $total, $total_pages), 'import');
        
        while ($page <= $total_pages) {
            AS24_Logger::info(sprintf('[Step 3] Fetching page %d of %d...', $page, $total_pages), 'import');
            
            $query = AS24_Queries::get_ids_only_query($page, $per_page);
            $data = AS24_Queries::make_request($query);
            
            if (is_wp_error($data)) {
                AS24_Logger::error(sprintf('[Step 3] Failed to fetch page %d: %s', $page, $data->get_error_message()), 'import');
                // Continue with next page instead of failing completely
                $page++;
                continue;
            }
            
            if (!isset($data['data']['search']['listings']['listings'])) {
                AS24_Logger::warning(sprintf('[Step 3] No listings in page %d', $page), 'import');
                $page++;
                continue;
            }
            
            $listings = $data['data']['search']['listings']['listings'];
            
            foreach ($listings as $listing) {
                if (isset($listing['id'])) {
                    $all_ids[] = array(
                        'id' => $listing['id'],
                        'timestamp' => isset($listing['details']['publication']['changedTimestamp']) 
                            ? $listing['details']['publication']['changedTimestamp'] 
                            : null
                    );
                }
            }
            
            AS24_Logger::info(sprintf('[Step 3] Collected %d IDs from page %d (Total so far: %d)', 
                count($listings), $page, count($all_ids)), 'import');
            
            $page++;
        }
        
        if (empty($all_ids)) {
            AS24_Logger::error('[Step 3] No listing IDs collected', 'import');
            return new WP_Error('no_ids', __('No listing IDs were collected from API.', 'as24-sync'));
        }
        
        // Store in transient (24 hours)
        set_transient(self::QUEUE_TRANSIENT, $all_ids, DAY_IN_SECONDS);
        
        // Store metadata
        $metadata = array(
            'total' => count($all_ids),
            'collected_at' => current_time('mysql'),
            'collected_timestamp' => time()
        );
        set_transient(self::QUEUE_META_TRANSIENT, $metadata, DAY_IN_SECONDS);
        
        AS24_Logger::info(sprintf('[Step 3] Successfully collected %d listing IDs', count($all_ids)), 'import');
        
        return $all_ids;
    }
    
    /**
     * Get next listing ID from queue
     * 
     * @return array|false Next listing ID data or false if queue empty
     */
    public static function get_next_listing_id() {
        $queue = get_transient(self::QUEUE_TRANSIENT);
        
        if (empty($queue) || !is_array($queue)) {
            return false;
        }
        
        // Get first item from queue
        $next = array_shift($queue);
        
        // Update queue
        set_transient(self::QUEUE_TRANSIENT, $queue, DAY_IN_SECONDS);
        
        return $next;
    }
    
    /**
     * Mark listing as complete
     * 
     * @param string $listing_id Listing ID
     * @return bool Success status
     */
    public static function mark_complete($listing_id) {
        // Track processed IDs
        $processed = get_option('as24_processed_ids', array());
        if (!in_array($listing_id, $processed)) {
            $processed[] = $listing_id;
            update_option('as24_processed_ids', $processed);
        }
        
        return true;
    }
    
    /**
     * Get queue progress
     * 
     * @return array Queue progress information
     */
    public static function get_queue_progress() {
        $queue = get_transient(self::QUEUE_TRANSIENT);
        $metadata = get_transient(self::QUEUE_META_TRANSIENT);
        $processed = get_option('as24_processed_ids', array());
        
        $total = isset($metadata['total']) ? $metadata['total'] : 0;
        $remaining = is_array($queue) ? count($queue) : 0;
        $processed_count = count($processed);
        
        return array(
            'total' => $total,
            'processed' => $processed_count,
            'remaining' => $remaining,
            'progress_percent' => $total > 0 ? round(($processed_count / $total) * 100, 2) : 0,
            'collected_at' => isset($metadata['collected_at']) ? $metadata['collected_at'] : null
        );
    }
    
    /**
     * Reset queue for new import
     * 
     * @return bool Success status
     */
    public static function reset_queue() {
        delete_transient(self::QUEUE_TRANSIENT);
        delete_transient(self::QUEUE_META_TRANSIENT);
        delete_option('as24_processed_ids');
        
        AS24_Logger::info('Queue reset', 'import');
        
        return true;
    }
    
    /**
     * Check if queue exists and has items
     * 
     * @return bool True if queue exists and has items
     */
    public static function has_queue() {
        $queue = get_transient(self::QUEUE_TRANSIENT);
        return !empty($queue) && is_array($queue) && count($queue) > 0;
    }
}


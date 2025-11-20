<?php
/**
 * Sync Comparator - Compare Local vs Remote Listings
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Sync_Comparator {
    
    /**
     * Get all local listing IDs (from WordPress)
     * 
     * @return array Array of AutoScout24 IDs with post_id mapping
     */
    public static function get_local_listing_ids() {
        global $wpdb;
        
        $meta_key = 'autoscout24-id';
        $post_type = 'listings';
        
        // Get all posts with autoscout24-id meta
        $query = $wpdb->prepare(
            "SELECT p.ID, pm.meta_value as as24_id
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND pm.meta_key = %s
             AND pm.meta_value != ''",
            $post_type,
            $meta_key
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $local_ids = array();
        foreach ($results as $row) {
            if (!empty($row['as24_id'])) {
                $local_ids[$row['as24_id']] = array(
                    'post_id' => intval($row['ID']),
                    'as24_id' => $row['as24_id']
                );
            }
        }
        
        AS24_Logger::info(sprintf('Found %d local listings with AutoScout24 IDs', count($local_ids)), 'sync');
        
        return $local_ids;
    }
    
    /**
     * Get all remote listing IDs (from API)
     * Uses existing queue manager logic
     * 
     * @return array|WP_Error Array of listing IDs or error
     */
    public static function get_remote_listing_ids() {
        AS24_Logger::info('Fetching remote listing IDs from API...', 'sync');
        
        // Use existing queue manager to collect IDs
        $result = AS24_Queue_Manager::collect_all_listing_ids();
        
        if (is_wp_error($result)) {
            AS24_Logger::error('Failed to get remote listing IDs: ' . $result->get_error_message(), 'sync');
            return $result;
        }
        
        // Convert to simple array of IDs
        $remote_ids = array();
        if (is_array($result)) {
            foreach ($result as $item) {
                if (isset($item['id'])) {
                    $remote_ids[] = $item['id'];
                }
            }
        }
        
        AS24_Logger::info(sprintf('Found %d remote listings', count($remote_ids)), 'sync');
        
        return $remote_ids;
    }
    
    /**
     * Compare local vs remote and identify differences
     * 
     * @return array|WP_Error {
     *   'orphaned_local' => array, // IDs that exist locally but not in remote
     *   'missing_remote' => array,  // IDs that exist in remote but not locally
     *   'synced' => array,          // IDs that exist in both
     *   'local_count' => int,
     *   'remote_count' => int,
     *   'orphaned_count' => int,
     *   'missing_count' => int,
     *   'synced_count' => int
     * }
     */
    public static function compare_listings() {
        AS24_Logger::info('Starting listing comparison...', 'sync');
        
        // Get local listings
        $local_listings = self::get_local_listing_ids();
        $local_ids = array_keys($local_listings);
        
        // Get remote listings
        $remote_ids_result = self::get_remote_listing_ids();
        if (is_wp_error($remote_ids_result)) {
            return $remote_ids_result;
        }
        $remote_ids = $remote_ids_result;
        
        // Compare
        $local_set = array_flip($local_ids);
        $remote_set = array_flip($remote_ids);
        
        // Find differences
        $orphaned_local = array(); // In local but not in remote
        $missing_remote = array(); // In remote but not in local
        $synced = array();         // In both
        
        // Check local listings
        foreach ($local_ids as $id) {
            if (isset($remote_set[$id])) {
                $synced[$id] = $local_listings[$id];
            } else {
                $orphaned_local[$id] = $local_listings[$id];
            }
        }
        
        // Check remote listings
        foreach ($remote_ids as $id) {
            if (!isset($local_set[$id])) {
                $missing_remote[] = $id;
            }
        }
        
        $result = array(
            'orphaned_local' => $orphaned_local,
            'missing_remote' => $missing_remote,
            'synced' => $synced,
            'local_count' => count($local_ids),
            'remote_count' => count($remote_ids),
            'orphaned_count' => count($orphaned_local),
            'missing_count' => count($missing_remote),
            'synced_count' => count($synced)
        );
        
        AS24_Logger::info(sprintf(
            'Comparison complete: %d local, %d remote, %d orphaned, %d missing, %d synced',
            $result['local_count'],
            $result['remote_count'],
            $result['orphaned_count'],
            $result['missing_count'],
            $result['synced_count']
        ), 'sync');
        
        // Cache results for 1 hour
        set_transient('as24_sync_comparison', $result, HOUR_IN_SECONDS);
        
        return $result;
    }
    
    /**
     * Handle orphaned listings (exist locally but not in remote)
     * 
     * @param array $listing_ids Array of AutoScout24 IDs or post IDs
     * @param string $action Action to take: 'trash', 'draft', 'mark', or 'delete'
     * @return array Result with counts
     */
    public static function handle_orphaned_listings($listing_ids, $action = 'trash') {
        if (empty($listing_ids)) {
            return array(
                'success' => true,
                'message' => __('No orphaned listings to handle.', 'as24-sync'),
                'processed' => 0
            );
        }
        
        AS24_Logger::info(sprintf('Handling %d orphaned listings with action: %s', count($listing_ids), $action), 'sync');
        
        $processed = 0;
        $errors = 0;
        
        foreach ($listing_ids as $id) {
            // If it's an AutoScout24 ID, find the post
            $post_id = null;
            if (is_array($id) && isset($id['post_id'])) {
                $post_id = $id['post_id'];
            } elseif (is_numeric($id)) {
                $post_id = intval($id);
            } else {
                // Try to find by AutoScout24 ID
                $posts = get_posts(array(
                    'post_type' => 'listings',
                    'post_status' => 'any',
                    'posts_per_page' => 1,
                    'meta_query' => array(
                        array(
                            'key' => 'autoscout24-id',
                            'value' => $id,
                            'compare' => '='
                        )
                    )
                ));
                
                if (!empty($posts)) {
                    $post_id = $posts[0]->ID;
                }
            }
            
            if (!$post_id) {
                $errors++;
                continue;
            }
            
            $result = false;
            switch ($action) {
                case 'trash':
                    $result = wp_trash_post($post_id);
                    break;
                    
                case 'draft':
                    $result = wp_update_post(array(
                        'ID' => $post_id,
                        'post_status' => 'draft'
                    ));
                    break;
                    
                case 'mark':
                    update_post_meta($post_id, 'as24-orphaned', true);
                    update_post_meta($post_id, 'as24-orphaned-date', current_time('mysql'));
                    $result = true;
                    break;
                    
                case 'delete':
                    $result = wp_delete_post($post_id, true); // Force delete
                    break;
            }
            
            if ($result && !is_wp_error($result)) {
                $processed++;
                AS24_Logger::info(sprintf('Processed orphaned listing (Post ID: %d, Action: %s)', $post_id, $action), 'sync');
            } else {
                $errors++;
                AS24_Logger::error(sprintf('Failed to process orphaned listing (Post ID: %d, Action: %s)', $post_id, $action), 'sync');
            }
        }
        
        return array(
            'success' => $errors === 0,
            'message' => sprintf(
                __('Processed %d orphaned listings (%d errors)', 'as24-sync'),
                $processed,
                $errors
            ),
            'processed' => $processed,
            'errors' => $errors
        );
    }
    
    /**
     * Import missing listings (exist in remote but not in local)
     * 
     * @param array $listing_ids Array of AutoScout24 IDs
     * @return array Result with counts
     */
    public static function import_missing_listings($listing_ids) {
        if (empty($listing_ids)) {
            return array(
                'success' => true,
                'message' => __('No missing listings to import.', 'as24-sync'),
                'processed' => 0
            );
        }
        
        AS24_Logger::info(sprintf('Importing %d missing listings', count($listing_ids)), 'sync');
        
        $processed = 0;
        $errors = 0;
        $imported = 0;
        $updated = 0;
        
        foreach ($listing_ids as $listing_id) {
            $result = AS24_Listing_Processor::process_single_listing($listing_id);
            
            if (is_wp_error($result)) {
                $errors++;
                AS24_Logger::error(sprintf('Failed to import missing listing %s: %s', $listing_id, $result->get_error_message()), 'sync');
            } else {
                $processed++;
                if (isset($result['action'])) {
                    if ($result['action'] === 'imported') {
                        $imported++;
                    } elseif ($result['action'] === 'updated') {
                        $updated++;
                    }
                }
                AS24_Logger::info(sprintf('Imported missing listing %s', $listing_id), 'sync');
            }
        }
        
        return array(
            'success' => $errors === 0,
            'message' => sprintf(
                __('Processed %d missing listings: %d imported, %d updated, %d errors', 'as24-sync'),
                $processed,
                $imported,
                $updated,
                $errors
            ),
            'processed' => $processed,
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors
        );
    }
    
    /**
     * Get cached comparison results
     * 
     * @return array|false Comparison results or false if not cached
     */
    public static function get_cached_comparison() {
        return get_transient('as24_sync_comparison');
    }
}


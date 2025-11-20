<?php
/**
 * Listing Processor - Step 4: Process Listings One by One
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Listing_Processor {
    
    /**
     * Process single listing - Step 4
     * Fetches full data, creates/updates listing, queues images
     * 
     * @param string $listing_id AutoScout24 listing ID
     * @return array|WP_Error Result with action or error
     */
    public static function process_single_listing($listing_id) {
        AS24_Logger::info(sprintf('[Step 4] Processing listing ID: %s', $listing_id), 'import');
        
        // Fetch full listing data
        $listing_data = self::fetch_listing_data($listing_id);
        if (is_wp_error($listing_data)) {
            AS24_Logger::error(sprintf('[Step 4] Failed to fetch listing %s: %s', $listing_id, $listing_data->get_error_message()), 'import');
            return $listing_data;
        }
        
        // Check if listing exists locally
        $existing_post_id = self::find_existing_listing($listing_id);
        
        if ($existing_post_id) {
            // Update existing listing
            $result = self::update_listing($existing_post_id, $listing_data);
            if (!is_wp_error($result)) {
                AS24_Logger::info(sprintf('[Step 4] Updated listing %s (Post ID: %d)', $listing_id, $existing_post_id), 'import');
                return array('action' => 'updated', 'post_id' => $existing_post_id);
            } else {
                AS24_Logger::error(sprintf('[Step 4] Failed to update listing %s: %s', $listing_id, $result->get_error_message()), 'import');
                return $result;
            }
        } else {
            // Create new listing
            $result = self::create_listing($listing_data);
            if (!is_wp_error($result)) {
                AS24_Logger::info(sprintf('[Step 4] Created listing %s (Post ID: %d)', $listing_id, $result), 'import');
                return array('action' => 'imported', 'post_id' => $result);
            } else {
                AS24_Logger::error(sprintf('[Step 4] Failed to create listing %s: %s', $listing_id, $result->get_error_message()), 'import');
                return $result;
            }
        }
    }
    
    /**
     * Fetch full listing data from API
     * 
     * @param string $listing_id AutoScout24 listing ID (GUID)
     * @return array|WP_Error Listing data or error
     */
    private static function fetch_listing_data($listing_id) {
        // Use direct single listing query with guid parameter
        $query = AS24_Queries::get_single_listing_query($listing_id);
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            AS24_Logger::error(sprintf('Failed to fetch listing %s: %s', $listing_id, $data->get_error_message()), 'import');
            return $data;
        }
        
        // Check if listing exists in response
        if (!isset($data['data']['listing']) || empty($data['data']['listing'])) {
            AS24_Logger::error(sprintf('Listing %s not found in API response', $listing_id), 'import');
            return new WP_Error('listing_not_found', sprintf(__('Listing %s not found in API', 'as24-sync'), $listing_id));
        }
        
        $listing = $data['data']['listing'];
        
        // Verify the ID matches (safety check)
        if (isset($listing['id']) && $listing['id'] !== $listing_id) {
            AS24_Logger::warning(sprintf('Listing ID mismatch: expected %s, got %s', $listing_id, $listing['id']), 'import');
        }
        
        AS24_Logger::debug(sprintf('Successfully fetched listing %s', $listing_id), 'import');
        
        return $listing;
    }
    
    /**
     * Find existing listing by AutoScout24 ID
     * 
     * @param string $listing_id AutoScout24 listing ID
     * @return int|false Post ID or false if not found
     */
    private static function find_existing_listing($listing_id) {
        $posts = get_posts(array(
            'post_type' => 'listings',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => 'autoscout24-id',
                    'value' => $listing_id,
                    'compare' => '='
                )
            )
        ));
        
        if (!empty($posts)) {
            return $posts[0]->ID;
        }
        
        return false;
    }
    
    /**
     * Create new listing
     * 
     * @param array $listing_data Listing data from API
     * @return int|WP_Error Post ID or error
     */
    private static function create_listing($listing_data) {
        // Map to post data
        $post_data = AS24_Field_Mapper::map_to_post_data($listing_data);
        
        // Insert post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Add meta data
        $meta_data = AS24_Field_Mapper::map_to_meta_data($listing_data);
        foreach ($meta_data as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
        
        // Add taxonomies
        AS24_Field_Mapper::add_taxonomies($post_id, $listing_data);
        
        // Update as24-updated-at meta
        if (!empty($listing_data['details']['publication']['changedTimestamp'])) {
            update_post_meta($post_id, 'as24-updated-at', $listing_data['details']['publication']['changedTimestamp']);
        }
        
        // Update FS feature data
        AS24_Field_Mapper::update_fs_feature_data($post_id);
        
        // Add equipment to additional features
        AS24_Field_Mapper::add_equipment_as24_to_additional_features($post_id);
        
        // Log the import
        $listing_id = $listing_data['id'] ?? '';
        AS24_Listing_Logs::add_log($listing_id, $post_id, 'imported', array(), 'Listing imported successfully');
        
        // Queue images (don't download yet)
        if (!empty($listing_data['details']['media']['images'])) {
            AS24_Image_Queue::queue_images($post_id, $listing_data['details']['media']['images']);
        }
        
        return $post_id;
    }
    
    /**
     * Update existing listing
     * 
     * @param int $post_id Post ID
     * @param array $listing_data Listing data from API
     * @return int|WP_Error Post ID or error
     */
    private static function update_listing($post_id, $listing_data) {
        // Get old data for comparison
        $old_meta = array();
        $old_meta['post_title'] = get_the_title($post_id);
        $old_meta['mileage'] = get_post_meta($post_id, 'mileage', true);
        $old_meta['price'] = get_post_meta($post_id, 'price', true);
        $old_meta['stm_genuine_price'] = get_post_meta($post_id, 'stm_genuine_price', true);
        $old_meta['engine_power'] = get_post_meta($post_id, 'engine_power', true);
        $old_meta['fuel-consumption'] = get_post_meta($post_id, 'fuel-consumption', true);
        $old_meta['fuel-economy'] = get_post_meta($post_id, 'fuel-economy', true);
        $old_meta['as24-updated-at'] = get_post_meta($post_id, 'as24-updated-at', true);

        
        // Map to post data
        $post_data = AS24_Field_Mapper::map_to_post_data($listing_data);
        $post_data['ID'] = $post_id;
        
        // Update post
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Update meta data
        $meta_data = AS24_Field_Mapper::map_to_meta_data($listing_data);
        $new_meta = $meta_data;
        $new_meta['post_title'] = $post_data['post_title'];
        
        // Detect changes
        $changes = AS24_Listing_Logs::detect_changes($old_meta, $new_meta);
        
        // Log the update
        $listing_id = $listing_data['id'] ?? '';
        $message = empty($changes) ? 'No changes detected' : sprintf('%d field(s) changed', count($changes));
        AS24_Listing_Logs::add_log($listing_id, $post_id, 'updated', $changes, $message);
        
        foreach ($meta_data as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
        
        // Update taxonomies
        AS24_Field_Mapper::add_taxonomies($post_id, $listing_data);
        
        // Update as24-updated-at meta
        if (!empty($listing_data['details']['publication']['changedTimestamp'])) {
            update_post_meta($post_id, 'as24-updated-at', $listing_data['details']['publication']['changedTimestamp']);
        }
        
        // Update FS feature data
        AS24_Field_Mapper::update_fs_feature_data($post_id);
        
        // Add equipment to additional features
        AS24_Field_Mapper::add_equipment_as24_to_additional_features($post_id);
        
        // Queue images (don't download yet)
        if (!empty($listing_data['details']['media']['images'])) {
            AS24_Image_Queue::queue_images($post_id, $listing_data['details']['media']['images']);
        }
        
        return $post_id;
    }
}


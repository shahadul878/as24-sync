<?php
/**
 * Image Processor - Cron + Async Image Processing
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Image_Processor {
    
    /**
     * Process images asynchronously (immediate background processing)
     * 
     * @param int $post_id Post ID
     * @return bool Success status
     */
    public static function process_images_async($post_id) {
        if (empty($post_id)) {
            return false;
        }
        
        // Schedule immediate processing
        if (!wp_next_scheduled('as24_process_image_queue', array($post_id))) {
            $scheduled = wp_schedule_single_event(time(), 'as24_process_image_queue', array($post_id));
            AS24_Logger::debug(sprintf('Scheduled async image processing for post %d', $post_id), 'import');
            return $scheduled !== false;
        }
        
        return true;
    }
    
    /**
     * Process image queue (called by cron or async)
     * 
     * @param int $post_id Post ID
     * @return array|WP_Error Processing result or error
     */
    public static function process_image_queue($post_id = null) {
        if (!$post_id) {
            // Process all pending queues
            return self::process_all_queues();
        }
        
        AS24_Logger::debug(sprintf('Processing image queue for post %d', $post_id), 'import');
        
        $image_queue = AS24_Image_Queue::get_queue($post_id);
        $queue_status = AS24_Image_Queue::get_queue_status($post_id);
        
        if (empty($image_queue) || empty($queue_status)) {
            AS24_Logger::debug(sprintf('No image queue found for post %d', $post_id), 'import');
            return array('status' => 'empty', 'message' => 'No images to process');
        }
        
        // Process next image in queue
        $image = array_shift($image_queue);
        
        if (empty($image)) {
            // Queue completed
            self::complete_queue($post_id, $queue_status);
            return array('status' => 'complete', 'message' => 'All images processed');
        }
        
        // Check if image already exists
        $attachment_id = self::get_existing_attachment($image['url']);
        
        if (!$attachment_id) {
            // Import new image
            $attachment_id = self::import_single_image($image['url'], $post_id);
        } else {
            // Update parent if needed
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_parent' => $post_id
            ));
        }
        
        if ($attachment_id && !is_wp_error($attachment_id)) {
            $queue_status['image_ids'][] = $attachment_id;
            
            // Set first image as featured
            if ($image['index'] === 0) {
                set_post_thumbnail($post_id, $attachment_id);
            }
            
            $queue_status['processed']++;
            AS24_Logger::debug(sprintf('Processed image %d of %d for post %d', 
                $queue_status['processed'], $queue_status['total'], $post_id), 'import');
        } else {
            $queue_status['failed']++;
            AS24_Logger::error(sprintf('Failed to import image for post %d: %s', 
                $post_id, is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'Unknown error'), 'import');
        }
        
        // Update queue and status
        if (!empty($image_queue)) {
            update_post_meta($post_id, '_as24_image_queue', $image_queue);
            update_post_meta($post_id, '_as24_image_queue_status', $queue_status);
            
            // Schedule next image processing
            wp_schedule_single_event(time() + 1, 'as24_process_image_queue', array($post_id));
        } else {
            // Queue completed
            self::complete_queue($post_id, $queue_status);
        }
        
        return array(
            'status' => 'processing',
            'processed' => $queue_status['processed'],
            'total' => $queue_status['total'],
            'failed' => $queue_status['failed']
        );
    }
    
    /**
     * Process all pending image queues
     * 
     * @return array Processing result
     */
    private static function process_all_queues() {
        global $wpdb;
        
        // Get all posts with pending image queues
        $posts_with_queues = $wpdb->get_col(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_as24_image_queue'"
        );
        
        if (empty($posts_with_queues)) {
            return array('status' => 'empty', 'message' => 'No pending queues');
        }
        
        $processed = 0;
        foreach ($posts_with_queues as $post_id) {
            if (!wp_next_scheduled('as24_process_image_queue', array($post_id))) {
                wp_schedule_single_event(time(), 'as24_process_image_queue', array($post_id));
                $processed++;
            }
        }
        
        return array(
            'status' => 'scheduled',
            'queues_scheduled' => $processed,
            'total_queues' => count($posts_with_queues)
        );
    }
    
    /**
     * Complete image queue processing
     * 
     * @param int $post_id Post ID
     * @param array $queue_status Queue status
     */
    private static function complete_queue($post_id, $queue_status) {
        // Update gallery
        if (!empty($queue_status['image_ids'])) {
            // Store as array (WordPress native format)
            update_post_meta($post_id, 'gallery', $queue_status['image_ids']);
            
            // Store as comma-separated string (legacy format)
            update_post_meta($post_id, '_gallery_images', implode(',', $queue_status['image_ids']));
        }
        
        // Cleanup
        delete_post_meta($post_id, '_as24_image_queue');
        delete_post_meta($post_id, '_as24_image_queue_status');
        
        AS24_Logger::info(sprintf('Completed image processing for post %d: %d processed, %d failed', 
            $post_id, $queue_status['processed'], $queue_status['failed']), 'import');
    }
    
    /**
     * Check if image already exists in media library
     * 
     * @param string $image_url Image URL
     * @return int|false Attachment ID or false
     */
    private static function get_existing_attachment($image_url) {
        global $wpdb;
        
        $attachment = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1",
            $image_url
        ));
        
        return $attachment ? (int)$attachment : false;
    }
    
    /**
     * Import single image
     * 
     * @param string $image_url Image URL
     * @param int $post_id Post ID
     * @return int|WP_Error Attachment ID or error
     */
    private static function import_single_image($image_url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            AS24_Logger::error('Failed to download image: ' . $tmp->get_error_message(), 'import');
            return $tmp;
        }
        
        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );
        
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            AS24_Logger::error('Failed to sideload image: ' . $attachment_id->get_error_message(), 'import');
            return $attachment_id;
        }
        
        @unlink($tmp);
        
        return $attachment_id;
    }
}


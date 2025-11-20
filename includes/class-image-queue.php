<?php
/**
 * Image Queue - Queue Images Without Downloading
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Image_Queue {
    
    /**
     * Queue images for a listing
     * Stores image URLs in post meta for later processing
     * 
     * @param int $post_id Post ID
     * @param array $images Images array from API
     * @return array Queue status
     */
    public static function queue_images($post_id, $images) {
        if (empty($post_id) || !is_numeric($post_id)) {
            AS24_Logger::error('Invalid post ID provided for image queue', 'import');
            return array('status' => 'error', 'message' => 'Invalid post ID');
        }
        
        if (empty($images) || !is_array($images)) {
            AS24_Logger::debug(sprintf('No images to queue for post %d', $post_id), 'import');
            return array('status' => 'empty', 'message' => 'No images to queue');
        }
        
        $image_queue = array();
        
        foreach ($images as $index => $image) {
            $image_url = self::get_best_image_url($image);
            if (!empty($image_url)) {
                $image_queue[] = array(
                    'url' => $image_url,
                    'index' => $index,
                    'post_id' => $post_id
                );
            }
        }
        
        if (empty($image_queue)) {
            AS24_Logger::debug(sprintf('No valid image URLs found for post %d', $post_id), 'import');
            return array('status' => 'empty', 'message' => 'No valid image URLs');
        }
        
        // Store image queue in post meta
        update_post_meta($post_id, '_as24_image_queue', $image_queue);
        
        // Store queue status
        $queue_status = array(
            'total' => count($image_queue),
            'processed' => 0,
            'failed' => 0,
            'image_ids' => array(),
            'queued_at' => current_time('mysql')
        );
        update_post_meta($post_id, '_as24_image_queue_status', $queue_status);
        
        AS24_Logger::info(sprintf('Queued %d images for post %d', count($image_queue), $post_id), 'import');
        
        // Schedule async processing
        AS24_Image_Processor::process_images_async($post_id);
        
        return array(
            'status' => 'queued',
            'total_images' => count($image_queue),
            'post_id' => $post_id
        );
    }
    
    /**
     * Get best quality image URL from image data
     * 
     * @param array $image Image data from API
     * @return string|null Image URL or null
     */
    private static function get_best_image_url($image) {
        // Prefer WebP format at 1280x960
        if (!empty($image['formats']['webp']['size1280x960'])) {
            return $image['formats']['webp']['size1280x960'];
        }
        
        // Fallback to JPG at 1280x960
        if (!empty($image['formats']['jpg']['size1280x960'])) {
            return $image['formats']['jpg']['size1280x960'];
        }
        
        // Fallback to 800x600 WebP
        if (!empty($image['formats']['webp']['size800x600'])) {
            return $image['formats']['webp']['size800x600'];
        }
        
        // Fallback to 800x600 JPG
        if (!empty($image['formats']['jpg']['size800x600'])) {
            return $image['formats']['jpg']['size800x600'];
        }
        
        // Fallback to any available size
        if (!empty($image['formats']['webp']['size640x480'])) {
            return $image['formats']['webp']['size640x480'];
        }
        
        if (!empty($image['formats']['jpg']['size640x480'])) {
            return $image['formats']['jpg']['size640x480'];
        }
        
        return null;
    }
    
    /**
     * Get image queue for a post
     * 
     * @param int $post_id Post ID
     * @return array|false Image queue or false
     */
    public static function get_queue($post_id) {
        return get_post_meta($post_id, '_as24_image_queue', true);
    }
    
    /**
     * Get queue status for a post
     * 
     * @param int $post_id Post ID
     * @return array|false Queue status or false
     */
    public static function get_queue_status($post_id) {
        return get_post_meta($post_id, '_as24_image_queue_status', true);
    }
    
    /**
     * Check if post has pending images
     * 
     * @param int $post_id Post ID
     * @return bool True if has pending images
     */
    public static function has_pending_images($post_id) {
        $queue = self::get_queue($post_id);
        return !empty($queue) && is_array($queue) && count($queue) > 0;
    }
}


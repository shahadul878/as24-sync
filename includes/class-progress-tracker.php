<?php
/**
 * Progress Tracker - Real-time Progress Updates
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Progress_Tracker {
    
    /**
     * Option name for progress data
     */
    const PROGRESS_OPTION = 'as24_import_progress';
    
    /**
     * Update progress state
     * 
     * @param string $step Step identifier
     * @param array $data Progress data
     */
    public static function update_progress($step, $data = array()) {
        $progress = get_option(self::PROGRESS_OPTION, array());
        
        $progress[$step] = array(
            'timestamp' => current_time('mysql'),
            'data' => $data
        );
        
        update_option(self::PROGRESS_OPTION, $progress);
    }
    
    /**
     * Get current progress
     * 
     * @return array Progress information
     */
    public static function get_progress() {
        $progress = get_option(self::PROGRESS_OPTION, array());
        $import_state = get_option('as24_import_state');
        
        $result = array(
            'current_step' => 'idle',
            'steps' => array(),
            'overall_progress' => 0,
            'message' => __('No import in progress.', 'as24-sync')
        );
        
        // Step 1: API Validation
        if (isset($progress['step1_complete'])) {
            $result['steps']['step1'] = array(
                'status' => 'complete',
                'message' => __('API connection validated', 'as24-sync'),
                'data' => $progress['step1_complete']['data']
            );
            $result['current_step'] = 'step2';
        } elseif (isset($progress['step1_failed'])) {
            $result['steps']['step1'] = array(
                'status' => 'failed',
                'message' => __('API validation failed', 'as24-sync'),
                'error' => $progress['step1_failed']['data']['error'] ?? ''
            );
            return $result;
        } else {
            $result['steps']['step1'] = array(
                'status' => 'pending',
                'message' => __('Waiting for API validation...', 'as24-sync')
            );
        }
        
        // Step 2: Total Count
        if (isset($progress['step2_complete'])) {
            $result['steps']['step2'] = array(
                'status' => 'complete',
                'message' => sprintf(__('Total listings: %d', 'as24-sync'), 
                    $progress['step2_complete']['data']['total'] ?? 0),
                'data' => $progress['step2_complete']['data']
            );
            $result['current_step'] = 'step3';
        } elseif (isset($progress['step2_failed'])) {
            $result['steps']['step2'] = array(
                'status' => 'failed',
                'message' => __('Failed to get total count', 'as24-sync'),
                'error' => $progress['step2_failed']['data']['error'] ?? ''
            );
            return $result;
        } else {
            $result['steps']['step2'] = array(
                'status' => 'pending',
                'message' => __('Waiting for total count...', 'as24-sync')
            );
        }
        
        // Step 3: Collect IDs
        if (isset($progress['step3_complete'])) {
            $result['steps']['step3'] = array(
                'status' => 'complete',
                'message' => sprintf(__('Collected %d listing IDs', 'as24-sync'), 
                    $progress['step3_complete']['data']['total_ids'] ?? 0),
                'data' => $progress['step3_complete']['data']
            );
            $result['current_step'] = 'step4';
        } elseif (isset($progress['step3_failed'])) {
            $result['steps']['step3'] = array(
                'status' => 'failed',
                'message' => __('Failed to collect IDs', 'as24-sync'),
                'error' => $progress['step3_failed']['data']['error'] ?? ''
            );
            return $result;
        } else {
            $result['steps']['step3'] = array(
                'status' => 'pending',
                'message' => __('Waiting for ID collection...', 'as24-sync')
            );
        }
        
        // Step 4: Processing
        if (isset($progress['step4_complete'])) {
            $result['steps']['step4'] = array(
                'status' => 'complete',
                'message' => __('All listings processed', 'as24-sync'),
                'data' => $progress['step4_complete']['data']
            );
            $result['current_step'] = 'complete';
            $result['overall_progress'] = 100;
            $result['message'] = __('Import completed successfully!', 'as24-sync');
        } elseif (isset($progress['step4_progress'])) {
            $step4_data = $progress['step4_progress']['data'];
            $result['steps']['step4'] = array(
                'status' => 'processing',
                'message' => sprintf(__('Processing: %d of %d (%s%%)', 'as24-sync'),
                    $step4_data['processed'] ?? 0,
                    $step4_data['total'] ?? 0,
                    $step4_data['progress'] ?? 0),
                'data' => $step4_data
            );
            $result['current_step'] = 'step4';
            $result['overall_progress'] = $step4_data['progress'] ?? 0;
            $result['message'] = $result['steps']['step4']['message'];
        } elseif (isset($progress['step4_started'])) {
            $result['steps']['step4'] = array(
                'status' => 'processing',
                'message' => __('Starting to process listings...', 'as24-sync'),
                'data' => $progress['step4_started']['data']
            );
            $result['current_step'] = 'step4';
        } else {
            $result['steps']['step4'] = array(
                'status' => 'pending',
                'message' => __('Waiting for processing to start...', 'as24-sync')
            );
        }
        
        return $result;
    }
    
    /**
     * Render progress HTML
     */
    public static function render_progress() {
        $progress = self::get_progress();
        ?>
        <div class="as24-progress-wrapper">
            <div class="as24-progress-steps">
                <?php foreach ($progress['steps'] as $step_key => $step): ?>
                    <div class="as24-progress-step as24-step-<?php echo esc_attr($step['status']); ?>">
                        <div class="as24-step-indicator"></div>
                        <div class="as24-step-content">
                            <div class="as24-step-title"><?php echo esc_html(self::get_step_title($step_key)); ?></div>
                            <div class="as24-step-message"><?php echo esc_html($step['message']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="as24-progress-status">
                <?php echo esc_html($progress['message']); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get step title
     * 
     * @param string $step_key Step key
     * @return string Step title
     */
    private static function get_step_title($step_key) {
        $titles = array(
            'step1' => __('Step 1: API Validation', 'as24-sync'),
            'step2' => __('Step 2: Total Count', 'as24-sync'),
            'step3' => __('Step 3: Collect IDs', 'as24-sync'),
            'step4' => __('Step 4: Process Listings', 'as24-sync')
        );
        
        return isset($titles[$step_key]) ? $titles[$step_key] : $step_key;
    }
    
    /**
     * Initialize AJAX endpoint
     * Note: AJAX handler is registered in AS24_Ajax_Handler to avoid duplicates
     */
    public static function init() {
        // AJAX handler moved to AS24_Ajax_Handler to avoid duplicate registrations
    }
}

// Initialize progress tracker
add_action('init', array('AS24_Progress_Tracker', 'init'));


<?php
/**
 * Logger - File-based logging system
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Logger {
    
    /**
     * Log directory
     * @var string
     */
    private static $log_dir = null;
    
    /**
     * Initialize logger
     */
    public static function init() {
        $upload_dir = wp_upload_dir();
        self::$log_dir = $upload_dir['basedir'] . '/as24-sync-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
            
            // Create .htaccess to protect logs
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents(self::$log_dir . '/.htaccess', $htaccess_content);
            
            // Create index.php to prevent directory listing
            file_put_contents(self::$log_dir . '/index.php', '<?php // Silence is golden');
        }
    }
    
    /**
     * Get log file path
     * 
     * @param string $type Log type (general, import, sync, error)
     * @return string Log file path
     */
    private static function get_log_file($type = 'general') {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $filename = 'as24-' . sanitize_file_name($type) . '.log';
        return self::$log_dir . '/' . $filename;
    }
    
    /**
     * Write log entry
     * 
     * @param string $message Log message
     * @param string $type Log type
     * @param mixed $context Additional context data
     * @param string $level Log level (info, warning, error, debug)
     */
    public static function log($message, $type = 'general', $context = null, $level = 'info') {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $log_file = self::get_log_file($type);
        $timestamp = current_time('Y-m-d H:i:s');
        $level_upper = strtoupper($level);
        
        $log_entry = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            $level_upper,
            $message
        );
        
        if ($context !== null) {
            $log_entry .= "\nContext: " . print_r($context, true);
        }
        
        $log_entry .= "\n";
        
        // Write to log file
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Also write errors to error log
        if ($type === 'error' || $level === 'error') {
            error_log('[AS24 Sync] ' . $message);
        }
        
        // Rotate log if too large (10MB)
        self::maybe_rotate_log($log_file);
    }
    
    /**
     * Log info message
     * 
     * @param string $message Log message
     * @param string $type Log type
     * @param mixed $context Additional context
     */
    public static function info($message, $type = 'general', $context = null) {
        self::log($message, $type, $context, 'info');
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Log message
     * @param string $type Log type
     * @param mixed $context Additional context
     */
    public static function warning($message, $type = 'general', $context = null) {
        self::log($message, $type, $context, 'warning');
    }
    
    /**
     * Log error message
     * 
     * @param string $message Log message
     * @param string $type Log type
     * @param mixed $context Additional context
     */
    public static function error($message, $type = 'general', $context = null) {
        self::log($message, $type, $context, 'error');
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Log message
     * @param string $type Log type
     * @param mixed $context Additional context
     */
    public static function debug($message, $type = 'general', $context = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log($message, $type, $context, 'debug');
        }
    }
    
    /**
     * Rotate log file if it exceeds 10MB
     * 
     * @param string $log_file Log file path
     */
    private static function maybe_rotate_log($log_file) {
        if (!file_exists($log_file)) {
            return;
        }
        
        $max_size = 10 * 1024 * 1024; // 10MB
        $file_size = filesize($log_file);
        
        if ($file_size > $max_size) {
            // Create backup with timestamp
            $backup_file = $log_file . '.' . time() . '.bak';
            copy($log_file, $backup_file);
            
            // Clear current log
            file_put_contents($log_file, '');
            
            // Clean up old backups (older than 7 days)
            self::cleanup_old_logs(dirname($log_file));
        }
    }
    
    /**
     * Clean up old log files (older than 7 days)
     * 
     * @param string $log_dir Log directory
     */
    private static function cleanup_old_logs($log_dir) {
        $files = glob($log_dir . '/*.bak');
        $retention_days = 7;
        $cutoff_time = time() - ($retention_days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                @unlink($file);
            }
        }
    }
    
}


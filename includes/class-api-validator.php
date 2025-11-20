<?php
/**
 * API Validator - Step 1: Mandatory API Connection Check
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_API_Validator {
    
    /**
     * Validate API connection - Step 1 (Mandatory)
     * Must pass before any import operation
     * 
     * @return array|WP_Error Connection status or error
     */
    public static function validate_connection() {
        AS24_Logger::info('=== Starting API Connection Validation ===', 'general');
        
        $result = array(
            'valid' => false,
            'credentials_ok' => false,
            'endpoint_ok' => false,
            'api_ok' => false,
            'total_listings' => 0,
            'message' => '',
            'errors' => array()
        );
        
        // Step 1.1: Test credentials
        $credentials_result = self::test_credentials();
        if (is_wp_error($credentials_result)) {
            $result['errors'][] = $credentials_result->get_error_message();
            $result['message'] = __('API credentials validation failed.', 'as24-sync');
            AS24_Logger::error('Credentials validation failed: ' . $credentials_result->get_error_message(), 'general');
            return new WP_Error('credentials_failed', $result['message'], $result);
        }
        
        $result['credentials_ok'] = true;
        AS24_Logger::info('Credentials validation passed', 'general');
        
        // Step 1.2: Test endpoint
        $endpoint_result = self::test_endpoint();
        if (is_wp_error($endpoint_result)) {
            $result['errors'][] = $endpoint_result->get_error_message();
            $result['message'] = __('API endpoint is not reachable.', 'as24-sync');
            AS24_Logger::error('Endpoint test failed: ' . $endpoint_result->get_error_message(), 'general');
            return new WP_Error('endpoint_failed', $result['message'], $result);
        }
        
        $result['endpoint_ok'] = true;
        AS24_Logger::info('Endpoint test passed', 'general');
        
        // Step 1.3: Test API with actual query
        $api_result = self::test_api();
        if (is_wp_error($api_result)) {
            $result['errors'][] = $api_result->get_error_message();
            $result['message'] = __('API query failed.', 'as24-sync');
            AS24_Logger::error('API test failed: ' . $api_result->get_error_message(), 'general');
            return new WP_Error('api_failed', $result['message'], $result);
        }
        
        $result['api_ok'] = true;
        $result['total_listings'] = isset($api_result['total_listings']) ? $api_result['total_listings'] : 0;
        $result['valid'] = true;
        $result['message'] = __('API connection validated successfully.', 'as24-sync');
        
        AS24_Logger::info('=== API Connection Validation Passed ===', 'general');
        return $result;
    }
    
    /**
     * Test API credentials
     * 
     * @return bool|WP_Error True if valid, WP_Error on failure
     */
    public static function test_credentials() {
        $credentials = as24_sync()->get_api_credentials();
        
        if (!$credentials) {
            return new WP_Error('no_credentials', __('API credentials not configured. Please enter username and password in settings.', 'as24-sync'));
        }
        
        if (empty($credentials['username']) || empty($credentials['password'])) {
            return new WP_Error('empty_credentials', __('API credentials are empty. Please enter username and password in settings.', 'as24-sync'));
        }
        
        return true;
    }
    
    /**
     * Test API endpoint reachability
     * 
     * @return bool|WP_Error True if reachable, WP_Error on failure
     */
    public static function test_endpoint() {
        // Test the actual GraphQL endpoint that the plugin uses
        $endpoint_url = 'https://listing-search.api.autoscout24.com/graphql';
        
        // GraphQL endpoints require POST requests, so we'll make a minimal POST request
        // to test if the endpoint is reachable (even without auth, we should get a response)
        $test_response = wp_remote_post($endpoint_url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'AS24-Sync/' . AS24_SYNC_VERSION
            ),
            'body' => json_encode(array(
                'query' => 'query { __typename }'
            )),
            'timeout' => 15,
            'sslverify' => true
        ));
        
        if (is_wp_error($test_response)) {
            $error_message = $test_response->get_error_message();
            $error_code = $test_response->get_error_code();
            
            // Provide more helpful error messages
            if (strpos($error_message, 'SSL') !== false || strpos($error_message, 'certificate') !== false) {
                return new WP_Error('endpoint_unreachable', sprintf(
                    __('API endpoint SSL certificate error: %s. Please check your server\'s SSL configuration.', 'as24-sync'),
                    $error_message
                ));
            }
            
            if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'timed out') !== false) {
                return new WP_Error('endpoint_unreachable', sprintf(
                    __('API endpoint connection timeout: %s. Please check your network connection or firewall settings.', 'as24-sync'),
                    $error_message
                ));
            }
            
            if (strpos($error_message, 'resolve') !== false || strpos($error_message, 'DNS') !== false) {
                return new WP_Error('endpoint_unreachable', sprintf(
                    __('API endpoint DNS resolution failed: %s. Please check your DNS settings.', 'as24-sync'),
                    $error_message
                ));
            }
            
            return new WP_Error('endpoint_unreachable', sprintf(
                __('API endpoint is not reachable: %s (Error code: %s)', 'as24-sync'),
                $error_message,
                $error_code
            ));
        }
        
        $status_code = wp_remote_retrieve_response_code($test_response);
        
        // Accept 200 (OK), 400 (Bad Request - endpoint exists but needs auth/valid query), 
        // 401 (Unauthorized - endpoint exists), 405 (Method Not Allowed - unlikely but endpoint exists)
        // These status codes indicate the endpoint is reachable
        if ($status_code >= 200 && $status_code < 500) {
            return true;
        }
        
        // 5xx errors indicate server issues, but endpoint is reachable
        if ($status_code >= 500) {
            return new WP_Error('endpoint_error', sprintf(
                __('API endpoint is reachable but returned server error: HTTP %d. The AutoScout24 API may be temporarily unavailable.', 'as24-sync'),
                $status_code
            ));
        }
        
        return new WP_Error('endpoint_error', sprintf(
            __('API endpoint returned unexpected status: HTTP %d', 'as24-sync'),
            $status_code
        ));
    }
    
    /**
     * Test API with actual query
     * 
     * @return array|WP_Error API response data or error
     */
    public static function test_api() {
        $query = AS24_Queries::get_total_count_query();
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        if (!isset($data['data']['listings']['metadata']['totalItems'])) {
            return new WP_Error('invalid_response', __('API returned invalid response format.', 'as24-sync'));
        }
        
        return array(
            'total_listings' => $data['data']['listings']['metadata']['totalItems']
        );
    }
    
}


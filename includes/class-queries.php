<?php
/**
 * Optimized GraphQL Queries
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Queries {
    
    /**
     * Get lightweight query for fetching only listing IDs and timestamps
     * 
     * @param int $page Page number
     * @param int $size Page size
     * @return string GraphQL query
     */
    public static function get_ids_only_query($page = 1, $size = 50) {
        return "query GetListingIds {
            search {
                listings(metadata: { page: $page, size: $size }) {
                    listings {
                        id
                        details {
                            publication {
                                changedTimestamp
                                createdTimestamp
                            }
                        }
                    }
                    metadata {
                        currentPage
                        totalItems
                        totalPages
                        pageSize
                    }
                }
            }
        }";
    }
    
    /**
     * Get query for fetching a single listing by ID (GUID)
     * 
     * @param string $listing_id AutoScout24 listing ID (GUID)
     * @return string GraphQL query
     */
    public static function get_single_listing_query($listing_id) {
        // Use the direct listing query with guid parameter
        return "query SingleListing {
            listing(guid: \"{$listing_id}\") {
                id
                details {
                    description
                    identifier {
                        id
                        legacyId
                        offerReference
                        crossReferenceId
                    }
                    vehicle {
                        numberOfDoors
                        bodyColorOriginal
                        usageState
                        newDriverSuitable
                        engine {
                            numberOfGears
                            numberOfCylinders
                            power {
                                hp { raw formatted }
                                kw { raw formatted }
                            }
                            engineDisplacementInCCM { raw formatted }
                            transmissionType { raw formatted }
                            driveTrain { raw formatted }
                        }
                        fuels {
                            primary {
                                source
                                type { raw formatted }
                                consumption {
                                    combined { raw formatted }
                                    urban { raw formatted }
                                    extraUrban { raw formatted }
                                }
                                co2emissionInGramPerKm { raw formatted }
                            }
                            fuelCategory { raw formatted }
                            electricRange { raw formatted }
                        }
                        condition {
                            firstRegistrationDate { raw formatted }
                            mileageInKm { raw formatted }
                            numberOfPreviousOwners
                            nonSmoking
                            newInspection
                            damage {
                                accidentFree
                                isCurrentlyDamaged
                                isRoadworthy
                                hasRepairedDamages
                            }
                        }
                        interior {
                            numberOfSeats
                            upholstery { raw formatted }
                            upholsteryColor { raw formatted }
                        }
                        equipment {
                            userInput
                            as24 {
                                id { raw formatted }
                                category { raw formatted }
                                equipmentCategory { raw formatted }
                            }
                        }
                        highlightedEquipment {
                            id { raw formatted }
                            category { raw formatted }
                            equipmentCategory { raw formatted }
                        }
                        identifier {
                            vin
                            licensePlate
                        }
                        bodyColor { raw formatted }
                        bodyType { raw formatted }
                        legalCategories { raw formatted }
                        classification {
                            make { raw formatted }
                            model { raw formatted }
                            modelVariant { raw formatted }
                            motorType { raw formatted }
                            trimLine { raw formatted }
                            modelYear
                            type
                        }
                    }
                    prices {
                        public {
                            amountInEUR { raw formatted }
                            netAmountInEUR { raw formatted }
                            vatRate
                            negotiable
                            taxDeductible
                        }
                        dealer {
                            amountInEUR { raw formatted }
                            netAmountInEUR { raw formatted }
                            vatRate
                        }
                    }
                    media {
                        youtubeLink
                        images {
                            ... on StandardImage {
                                formats {
                                    webp {
                                        size540x405
                                        size640x480
                                        size800x600
                                        size1280x960
                                        size2560x1920
                                    }
                                    jpg {
                                        size540x405
                                        size640x480
                                        size800x600
                                        size1280x960
                                        size2560x1920
                                    }
                                }
                            }
                        }
                    }
                    location {
                        countryCode
                        zip
                        city
                        street
                    }
                    adProduct {
                        title
                    }
                    publication {
                        changedTimestamp
                        createdTimestamp
                    }
                }
            }
        }";
    }
    
    /**
     * Get total count query
     * 
     * @return string GraphQL query
     */
    public static function get_total_count_query() {
        return "query TotalListings {
            listings {
                metadata {
                    totalItems
                    totalPages
                    pageSize
                    currentPage
                }
            }
        }";
    }
    
    /**
     * Make API request with retry logic
     * 
     * @param string $query GraphQL query
     * @param array $variables Query variables
     * @param int $retry_count Current retry attempt
     * @return array|WP_Error Response data or error
     */
    public static function make_request($query, $variables = array(), $retry_count = 0) {
        $credentials = as24_sync()->get_api_credentials();
        
        if (!$credentials) {
            AS24_Logger::error('API credentials not configured', 'api');
            return new WP_Error('no_credentials', __('API credentials not configured.', 'as24-sync'));
        }
        
        $auth = base64_encode($credentials['username'] . ':' . $credentials['password']);
        
        $request_body = json_encode(array(
            'query' => $query,
            'variables' => $variables
        ));
        
        AS24_Logger::debug('Making API request (attempt ' . ($retry_count + 1) . ')', 'api');
        
        $response = wp_remote_post('https://listing-search.api.autoscout24.com/graphql', array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $auth,
            ),
            'body' => $request_body,
            'timeout' => 60,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            $error_message = $response->get_error_message();
            
            AS24_Logger::error('API request failed: ' . $error_code . ' - ' . $error_message, 'api');
            
            // Retry logic for network errors
            if ($retry_count < 3 && in_array($error_code, array('http_request_failed', 'timeout', 'connect_timeout'))) {
                $delay = pow(2, $retry_count); // Exponential backoff: 2, 4, 8 seconds
                AS24_Logger::info("Retrying API request in {$delay} seconds...", 'api');
                sleep($delay);
                return self::make_request($query, $variables, $retry_count + 1);
            }
            
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $response_message = wp_remote_retrieve_response_message($response);
            AS24_Logger::error('API HTTP Error: ' . $status_code . ' - ' . $response_message, 'api');
            
            // Retry for server errors (5xx)
            if ($retry_count < 3 && $status_code >= 500) {
                $delay = pow(2, $retry_count);
                AS24_Logger::info("Retrying API request in {$delay} seconds due to server error...", 'api');
                sleep($delay);
                return self::make_request($query, $variables, $retry_count + 1);
            }
            
            return new WP_Error('http_error', sprintf(__('HTTP Error %d: %s', 'as24-sync'), $status_code, $response_message));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            AS24_Logger::error('Failed to parse JSON response: ' . json_last_error_msg(), 'api');
            return new WP_Error('json_error', __('Failed to parse API response: ', 'as24-sync') . json_last_error_msg());
        }
        
        if (isset($data['errors'])) {
            AS24_Logger::error('API returned errors: ' . json_encode($data['errors']), 'api');
            return new WP_Error('api_error', $data['errors'][0]['message']);
        }
        
        AS24_Logger::debug('API request successful', 'api');
        return $data;
    }
}


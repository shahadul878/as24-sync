<?php
/**
 * Field Mapper - Maps AutoScout24 data to WordPress
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Field_Mapper {
    
    /**
     * Map AutoScout24 listing to WordPress post data
     * 
     * @param array $listing AutoScout24 listing data
     * @return array WordPress post data
     */
    public static function map_to_post_data($listing) {
        $title = $listing['details']['adProduct']['title'] ?? '';
        
        if (empty($title)) {
            $make = $listing['details']['vehicle']['classification']['make']['formatted'] ?? '';
            $model = $listing['details']['vehicle']['classification']['model']['formatted'] ?? '';
            $year = $listing['details']['vehicle']['classification']['modelYear'] ?? '';
            $title = trim($make . ' ' . $model . ' ' . $year);
        }
        
        $post_data = array(
            'post_title' => sanitize_text_field($title),
            'post_content' => wp_kses_post($listing['details']['description'] ?? ''),
            'post_type' => 'listings',
            'post_status' => 'publish',
            'post_author' => 1
        );
        
        return $post_data;
    }
    
    /**
     * Map to meta data
     * 
     * @param array $listing AutoScout24 listing data
     * @return array Meta data array
     */
    public static function map_to_meta_data($listing) {
        $meta = array();
        
        // Core identifiers
        $meta['autoscout24-id'] = sanitize_text_field($listing['id']);
        $meta['as24-updated-at'] = sanitize_text_field($listing['details']['publication']['changedTimestamp'] ?? '');
        $meta['as24-created-at'] = sanitize_text_field($listing['details']['publication']['createdTimestamp'] ?? '');

		//condition
	    if(!empty($listing['details']['vehicle']['legalCategories'][0]['raw'])){
			$meta['condition'] = sanitize_text_field($listing['details']['vehicle']['legalCategories'][0]['raw']);
	    }

		// Body type
		if (!empty($listing['details']['vehicle']['bodyType']['raw'])) {
			$meta['body'] = sanitize_text_field($listing['details']['vehicle']['bodyType']['raw']);
		}

		// Make
	    if (!empty($listing['details']['vehicle']['classification']['make']['raw'])) {
			$meta['make'] = sanitize_text_field($listing['details']['vehicle']['classification']['make']['raw']);
		}

		// Model (serie)
	    if (!empty($listing['details']['vehicle']['classification']['model']['raw'])) {
			$meta['serie'] = sanitize_text_field($listing['details']['vehicle']['classification']['model']['raw']);
		}

		// Year
		if (!empty($listing['details']['vehicle']['classification']['modelYear'])) {
			$meta['ca-year'] = absint($listing['details']['vehicle']['classification']['modelYear']);
		}

        // VIN
        if (!empty($listing['details']['vehicle']['identifier']['vin'])) {
            $meta['vin_number'] = sanitize_text_field($listing['details']['vehicle']['identifier']['vin']);
        }
        
        // Mileage
        if (!empty($listing['details']['vehicle']['condition']['mileageInKm']['raw'])) {
            $meta['mileage'] = absint($listing['details']['vehicle']['condition']['mileageInKm']['raw']);
        }

		// Fuel
        if (!empty($listing['details']['vehicle']['fuels']['fuelCategory']['raw'])){
			$meta['fuel'] = sanitize_text_field($listing['details']['vehicle']['fuels']['fuelCategory']['raw']);
	    }


        // Engine
        if (!empty($listing['details']['vehicle']['engine']['engineDisplacementInCCM']['raw'])) {
            $meta['engine'] = absint($listing['details']['vehicle']['engine']['engineDisplacementInCCM']['raw']);
            $meta['engine_power'] = absint($listing['details']['vehicle']['engine']['engineDisplacementInCCM']['raw']);
        }
        
        // Power
        if (!empty($listing['details']['vehicle']['engine']['power']['hp']['raw'])) {
            $meta['power-hp'] = absint($listing['details']['vehicle']['engine']['power']['hp']['raw']);
        }
        
        // Price
        if (!empty($listing['details']['prices']['public']['amountInEUR']['raw'])) {
            $price = floatval($listing['details']['prices']['public']['amountInEUR']['raw']);
            $meta['price'] = $price;
            $meta['stm_genuine_price'] = $price;
        }
        
        // Price without VAT
        if (!empty($listing['details']['prices']['public']['netAmountInEUR']['raw'])) {
            $meta['price_without_vat'] = floatval($listing['details']['prices']['public']['netAmountInEUR']['raw']);
        }
        
        // Registration date
        if (!empty($listing['details']['vehicle']['condition']['firstRegistrationDate']['formatted'])) {
            $meta['registration_date'] = sanitize_text_field($listing['details']['vehicle']['condition']['firstRegistrationDate']['formatted']);
        }
        
        // Fuel consumption
        if (!empty($listing['details']['vehicle']['fuels']['primary']['consumption']['combined']['raw'])) {
            $meta['fuel-consumption'] = floatval($listing['details']['vehicle']['fuels']['primary']['consumption']['combined']['raw']);
        }

		//Transmission
		if (!empty($listing['details']['vehicle']['engine']['transmissionType']['raw'])){
			$meta['transmission'] = sanitize_text_field($listing['details']['vehicle']['engine']['transmissionType']['raw']);
		}
        
        // CO2 emissions
        if (!empty($listing['details']['vehicle']['fuels']['primary']['co2emissionInGramPerKm']['raw'])) {
            $meta['fuel-economy'] = absint($listing['details']['vehicle']['fuels']['primary']['co2emissionInGramPerKm']['raw']);
        }

		// Interior color
		if (!empty($listing['details']['vehicle']['interior']['upholsteryColor']['raw'])){
			$meta['interior-color'] = sanitize_text_field($listing['details']['vehicle']['interior']['upholsteryColor']['raw']);
		}

		// Exterior color
	    if ( ! empty( $listing['details']['vehicle']['bodyColor']['raw'] ) ) {
		    $meta['exterior-color'] = sanitize_text_field( $listing['details']['vehicle']['bodyColor']['raw'] );
	    }
        
        // Other fields
        if (!empty($listing['details']['vehicle']['numberOfDoors'])) {
            $meta['number-of-doors'] = absint($listing['details']['vehicle']['numberOfDoors']);
        }
        
        if (!empty($listing['details']['vehicle']['interior']['numberOfSeats'])) {
            $meta['number-of-seats'] = absint($listing['details']['vehicle']['interior']['numberOfSeats']);
        }
        
        if (!empty($listing['details']['vehicle']['engine']['numberOfGears'])) {
            $meta['number-of-gears'] = absint($listing['details']['vehicle']['engine']['numberOfGears']);
        }
        
        // Equipment
        if (!empty($listing['details']['vehicle']['equipment']['as24'])) {
            $equipment = $listing['details']['vehicle']['equipment']['as24'];
            $meta['equipment-as24'] = $equipment;
        }
        
        if (!empty($listing['details']['vehicle']['highlightedEquipment'])) {
            $meta['highlighted-equipment'] = $listing['details']['vehicle']['highlightedEquipment'];
        }
        
        // Location will be handled separately by location_maker()
        
        return $meta;
    }
    
    /**
     * Add taxonomies to post
     * 
     * @param int $post_id Post ID
     * @param array $listing AutoScout24 listing data
     */
    public static function add_taxonomies($post_id, $listing) {
        // Condition
        if (!empty($listing['details']['vehicle']['legalCategories'][0]['formatted'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'condition', $listing['details']['vehicle']['legalCategories'][0]['formatted']);
        }
        
        // Body type
        if (!empty($listing['details']['vehicle']['bodyType']['formatted'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'body', $listing['details']['vehicle']['bodyType']['formatted']);
        }
        
        // Make
        if (!empty($listing['details']['vehicle']['classification']['make']['formatted'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'make', $listing['details']['vehicle']['classification']['make']['formatted']);
        }
        
        // Model (serie)
        if (!empty($listing['details']['vehicle']['classification']['model']['formatted'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'serie', $listing['details']['vehicle']['classification']['model']['formatted']);
        }

        // Fuel
        if (!empty($listing['details']['vehicle']['fuels']['fuelCategory']['formatted'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'fuel', $listing['details']['vehicle']['fuels']['fuelCategory']['formatted']);
        }

        // Year
        if (!empty($listing['details']['vehicle']['classification']['modelYear'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'ca-year', $listing['details']['vehicle']['classification']['modelYear']);
        }
        
        // Price
        if (!empty($listing['details']['prices']['public']['amountInEUR']['raw'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'price', $listing['details']['prices']['public']['amountInEUR']['raw']);
        }

        
        // Fuel consumption
        if (!empty($listing['details']['vehicle']['fuels']['primary']['consumption']['combined']['raw'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'fuel-consumption', $listing['details']['vehicle']['fuels']['primary']['consumption']['combined']['raw']);
        }
        
        // Transmission
        if (!empty($listing['details']['vehicle']['engine']['transmissionType']['formatted'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'transmission', $listing['details']['vehicle']['engine']['transmissionType']['formatted']);
        }
        
        // CO2 emissions (fuel-economy)
        if (!empty($listing['details']['vehicle']['fuels']['primary']['co2emissionInGramPerKm']['raw'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'fuel-economy', $listing['details']['vehicle']['fuels']['primary']['co2emissionInGramPerKm']['raw']);
        }
        
        // Interior color
        if (!empty($listing['details']['vehicle']['interior']['upholsteryColor']['formatted'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'interior-color', $listing['details']['vehicle']['interior']['upholsteryColor']['formatted']);
        }
        
        // Exterior color
        if (!empty($listing['details']['vehicle']['bodyColor']['formatted'])) {
            self::add_taxonomy_term_meta_direct($post_id, 'exterior-color', $listing['details']['vehicle']['bodyColor']['formatted']);
        }

	    // Location
	    if (!empty($listing['details']['location'])) {
		    self::location_maker($post_id, $listing['details']['location']);
	    }
    }
    
    /**
     * Insert term into custom taxonomy if not exists, then add to post meta.
     *
     * @param int $post_id Post ID
     * @param string $taxonomy Taxonomy name
     * @param string $term_name Term name
     * @return int|false Term ID or false on failure
     */
    public static function add_taxonomy_term_meta_direct($post_id, $taxonomy, $term_name): bool|int {
        global $wpdb;
        
        if (empty($term_name)) {
            return false;
        }
        
        $term_id = false;
        
        // Process term name: capitalize for display
        $display_name = ucwords(strtolower($term_name));
        
        // Create slug: lowercase and replace spaces with dashes
        $slug = strtolower(str_replace(' ', '-', $term_name));
        
        // 1. Check if term exists
        $term = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT t.term_id 
                FROM {$wpdb->prefix}terms t
                INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
                WHERE t.name = %s AND tt.taxonomy = %s
                LIMIT 1",
                $display_name, $taxonomy
            )
        );
        
        if ($term) {
            $term_id = $term->term_id;
        } else {
            // 2. Insert into wp_terms
            $wpdb->insert(
                $wpdb->terms,
                array(
                    'name' => $display_name,
                    'slug' => $slug,
                ),
                array('%s', '%s')
            );
            $term_id = $wpdb->insert_id;
            
            // 3. Insert into wp_term_taxonomy
            $wpdb->insert(
                $wpdb->term_taxonomy,
                array(
                    'term_id' => $term_id,
                    'taxonomy' => $taxonomy,
                    'description' => '',
                    'parent' => 0,
                    'count' => 0,
                ),
                array('%d', '%s', '%s', '%d', '%d')
            );
        }
        
        // 4. Create relationship between post and term
        if ($term_id) {
            wp_set_post_terms($post_id, array($term_id), $taxonomy, true);
            $meta_key = self::get_meta_key_from_taxonomy($taxonomy);
            add_post_meta($post_id, "{$taxonomy}_term_id", $term_id, true);
            if (!metadata_exists('post', $post_id, $meta_key)) {
                add_post_meta($post_id, $meta_key, strtolower($term_name), true);
            } else {
                update_post_meta($post_id, $meta_key, strtolower($term_name));
            }
            
            return $term_id;
        }
        
        return false;
    }
    
    /**
     * Get meta_key from taxonomy using mapping
     *
     * @param string $taxonomy Taxonomy name
     * @return string Meta key
     */
    private static function get_meta_key_from_taxonomy($taxonomy): string {
        $mapping = self::mapping_array();
        return $mapping[$taxonomy]['meta_key'] ?? $taxonomy;
    }
    
    /**
     * Get taxonomy to meta key mapping
     *
     * @return array Mapping array
     */
    private static function mapping_array() {
        return array(
            'make' => array(
                'slug' => 'make',
                'meta_key' => 'make',
            ),
            'serie' => array(
                'slug' => 'serie',
                'meta_key' => 'serie',
            ),
            'fuel' => array(
                'slug' => 'fuel',
                'meta_key' => 'fuel',
            ),
            'body' => array(
                'slug' => 'body',
                'meta_key' => 'body',
            ),
            'condition' => array(
                'slug' => 'condition',
                'meta_key' => 'condition',
            ),
            'interior-color' => array(
                'slug' => 'interior-color',
                'meta_key' => 'interior-color',
            ),
            'exterior-color' => array(
                'slug' => 'exterior-color',
                'meta_key' => 'exterior-color',
            ),
            'transmission' => array(
                'slug' => 'transmission',
                'meta_key' => 'transmission',
            ),
            'price' => array(
                'slug' => 'price',
                'meta_key' => 'price',
            ),
            'fuel-consumption' => array(
                'slug' => 'fuel-consumption',
                'meta_key' => 'fuel-consumption',
            ),
            'fuel-economy' => array(
                'slug' => 'fuel-economy',
                'meta_key' => 'fuel-economy',
            ),
            'ca-year' => array(
                'slug' => 'ca-year',
                'meta_key' => 'ca-year',
            ),
        );
    }
    
    /**
     * Format and save location data
     *
     * @param int $post_id Post ID
     * @param array $location Location data
     * @return bool|int Meta ID on success, false on failure
     */
    public static function location_maker($post_id, $location) {
        $required_keys = array('street', 'zip', 'city', 'countryCode');
        $missing_keys = array();
        
        foreach ($required_keys as $key) {
            if (empty($location[$key])) {
                $missing_keys[] = $key;
            }
        }
        
        if (!empty($missing_keys)) {
            AS24_Logger::warning(sprintf('location_maker: Missing location data for post_id %d. Missing keys: %s', $post_id, implode(', ', $missing_keys)), 'import');
            return false;
        }
        
        $address = $location['street'] . ", " . $location['zip'] . " " . $location['city'] . ", " . $location['countryCode'];
        
        return update_post_meta($post_id, 'stm_car_location', $address);
    }
    
    /**
     * Extract equipment-as24 data and add to additional_features post meta
     *
     * @param int $post_id Post ID
     * @return bool Success status
     */
    public static function add_equipment_as24_to_additional_features($post_id) {
        // Get the equipment-as24 data from post meta
        $equipment_as24 = get_post_meta($post_id, 'equipment-as24', true);
        
        if (empty($equipment_as24)) {
            return false;
        }
        
        // If it's a serialized string, unserialize it
        if (is_string($equipment_as24)) {
            $equipment_as24 = maybe_unserialize($equipment_as24);
        }
        
        // If it's not an array, return false
        if (!is_array($equipment_as24)) {
            return false;
        }
        
        $features = array();
        
        // Extract equipment names from the as24 equipment data
        foreach ($equipment_as24 as $equipment) {
            // Check for formatted equipment name
            if (isset($equipment['id']['formatted']) && !empty($equipment['id']['formatted'])) {
                $feature_name = sanitize_text_field($equipment['id']['formatted']);
                $features[] = $feature_name;
            }
            // Fallback to raw equipment name if formatted is not available
            elseif (isset($equipment['id']['raw']) && !empty($equipment['id']['raw'])) {
                $feature_name = sanitize_text_field($equipment['id']['raw']);
                $features[] = $feature_name;
            }
        }
        
        // Also check for highlighted equipment
        $highlighted_equipment = get_post_meta($post_id, 'highlighted-equipment', true);
        if (!empty($highlighted_equipment)) {
            if (is_string($highlighted_equipment)) {
                $highlighted_equipment = maybe_unserialize($highlighted_equipment);
            }
            
            if (is_array($highlighted_equipment)) {
                foreach ($highlighted_equipment as $highlighted) {
                    if (isset($highlighted['id']['formatted']) && !empty($highlighted['id']['formatted'])) {
                        $feature_name = sanitize_text_field($highlighted['id']['formatted']);
                        $features[] = $feature_name;
                    }
                }
            }
        }
        
        // Get existing additional features
        $existing_features = get_post_meta($post_id, 'additional_features', true);
        $existing_array = array();
        
        if (!empty($existing_features)) {
            $existing_array = explode(',', $existing_features);
            $existing_array = array_map('trim', $existing_array);
        }
        
        // Merge existing features with new equipment features
        $all_features = array_merge($existing_array, $features);
        $all_features = array_unique($all_features);
        $all_features = array_filter($all_features); // Remove empty values
        
        // Update the additional_features post meta
        if (!empty($all_features)) {
            update_post_meta($post_id, 'additional_features', implode(',', $all_features));
            
            // Also add to stm_additional_features taxonomy
            foreach ($all_features as $feature) {
                wp_set_object_terms($post_id, $feature, 'stm_additional_features', true);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update FS (Feature System) feature data
     *
     * @param int $post_id Post ID
     * @return bool Success status
     */
    public static function update_fs_feature_data($post_id) {
        // Get existing settings option
        $settings = get_option('mvl_listing_details_settings', array());
        $settings = maybe_unserialize($settings);
        
        // Ensure fs_user_features exists
        if (!isset($settings['fs_user_features']) || !is_array($settings['fs_user_features'])) {
            $settings['fs_user_features'] = array();
        }
        
        // Get equipment-as24 from post meta
        $equipment = get_post_meta($post_id, 'equipment-as24', true);
        $equipment = maybe_unserialize($equipment);
        
        if (empty($equipment) || !is_array($equipment)) {
            return false;
        }
        
        // Convert current fs_user_features to associative array by category slug
        $existing_tabs = array();
        foreach ($settings['fs_user_features'] as $tab) {
            $slug = sanitize_title($tab['tab_title_single']);
            $existing_tabs[$slug] = $tab;
        }
        
        // Group equipment by category and merge with existing
        foreach ($equipment as $item) {
            $category_label = $item['equipmentCategory']['formatted'] ?? 'Misc';
            $category_slug = $item['equipmentCategory']['raw'] ?? sanitize_title($category_label);
            $feature_label = $item['id']['formatted'] ?? '';
            
            if (empty($feature_label)) {
                continue;
            }
            
            // If category tab doesn't exist, create it
            if (!isset($existing_tabs[$category_slug])) {
                $existing_tabs[$category_slug] = array(
                    'tab_title_single' => $category_label,
                    'tab_title_selected_labels' => array(),
                    'single_group' => true,
                );
            }
            
            // Add feature if not already present
            $exists = false;
            foreach ($existing_tabs[$category_slug]['tab_title_selected_labels'] as $feature) {
                if (strtolower($feature['label']) === strtolower($feature_label)) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $existing_tabs[$category_slug]['tab_title_selected_labels'][] = array(
                    'value' => sanitize_title($feature_label),
                    'label' => $feature_label,
                );
            }
        }
        
        // Reindex and update option
        $settings['fs_user_features'] = array_values($existing_tabs);
        update_option('mvl_listing_details_settings', $settings);
        
        return true;
    }
}


<?php
/**
 * Elementor Price Details Widget
 *
 * @package AutoScout24Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Make sure Elementor is loaded
if (!did_action('elementor/loaded')) {
    return;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Icons_Manager;

class AS24_Elementor_Widget_Price_Details extends Widget_Base {

    /**
     * Prevent duplicate inline styles.
     *
     * @var bool
     */
    private static $styles_printed = false;

    public function get_name() {
        return 'as24_price_details';
    }

    public function get_title() {
        return __('Price Details', 'autoscout24-importer');
    }

    public function get_icon() {
        return 'eicon-price-table';
    }

    public function get_categories() {
        return array('autoscout24');
    }

    public function get_keywords() {
        return array('price', 'autoscout', 'listing', 'finance');
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('Price Details', 'autoscout24-importer'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'cta_text',
            array(
                'label'       => __('CTA Text', 'autoscout24-importer'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Krediet aanvragen', 'autoscout24-importer'),
                'placeholder' => __('CTA label', 'autoscout24-importer'),
            )
        );

        $this->add_control(
            'cta_icon',
            array(
                'label'   => __('CTA Icon', 'autoscout24-importer'),
                'type'    => Controls_Manager::ICONS,
                'default' => array(
                    'value'   => 'fas fa-handshake',
                    'library' => 'fa-solid',
                ),
            )
        );

        $this->add_control(
            'cta_link',
            array(
                'label'         => __('CTA Link', 'autoscout24-importer'),
                'type'          => Controls_Manager::URL,
                'placeholder'   => __('https://example.com', 'autoscout24-importer'),
                'default'       => array(
                    'url' => '#',
                ),
                'show_external' => true,
            )
        );

        $this->add_control(
            'financing_text',
            array(
                'label'       => __('Financing Text', 'autoscout24-importer'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Financieringsdetails', 'autoscout24-importer'),
                'placeholder' => __('Financing details label', 'autoscout24-importer'),
            )
        );

        $this->add_control(
            'financing_link_text',
            array(
                'label'       => __('Financing Link Text', 'autoscout24-importer'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('hier', 'autoscout24-importer'),
                'placeholder' => __('Link label', 'autoscout24-importer'),
            )
        );

        $this->add_control(
            'financing_link',
            array(
                'label'         => __('Financing Link', 'autoscout24-importer'),
                'type'          => Controls_Manager::URL,
                'placeholder'   => __('https://example.com', 'autoscout24-importer'),
                'default'       => array(
                    'url' => '#',
                ),
                'show_external' => true,
            )
        );

        $this->add_control(
            'note_text',
            array(
                'label'       => __('Disclaimer', 'autoscout24-importer'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Let op, geld lenen kost ook geld.', 'autoscout24-importer'),
                'placeholder' => __('Disclaimer text', 'autoscout24-importer'),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $price_data = $this->prepare_price_data($settings);
        $price_value = $price_data['price'];
        $price_context = $price_data['context'];

        $cta_link       = isset($settings['cta_link']) ? $settings['cta_link'] : array();
        $cta_url        = isset($cta_link['url']) ? $cta_link['url'] : '';
        $cta_target     = !empty($cta_link['is_external']) ? ' target="_blank"' : '';
        $cta_nofollow   = !empty($cta_link['nofollow']) ? ' rel="nofollow"' : '';

        $finance_link   = isset($settings['financing_link']) ? $settings['financing_link'] : array();
        $finance_url    = isset($finance_link['url']) ? $finance_link['url'] : '';
        $finance_target = !empty($finance_link['is_external']) ? ' target="_blank"' : '';
        $finance_rel    = !empty($finance_link['nofollow']) ? ' rel="nofollow"' : '';

        $this->print_styles();
        ?>
        <div class="as24-price-details">
            <?php if (!empty($price_value)) : ?>
                <div class="as24-price-value"><?php echo esc_html($price_value); ?></div>
            <?php endif; ?>

            <?php if (!empty($price_context)) : ?>
                <div class="as24-price-context"><?php echo esc_html($price_context); ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Prepare price string and context from post meta with fallbacks.
     *
     * @param array $settings Widget settings.
     *
     * @return array
     */
    private function prepare_price_data($settings) {
        $price_value   = '';
        $context_value = '';

        $post_id = get_the_ID();

        if ($post_id) {
            $raw_price       = get_post_meta($post_id, 'price', true);
            $raw_price_vat   = get_post_meta($post_id, 'price_without_vat', true);

            $formatted_price     = $this->format_currency($raw_price);
            $formatted_price_vat = $this->format_currency($raw_price_vat);

            if ($formatted_price) {
                $price_value = $formatted_price;
            }

            if ($formatted_price && $formatted_price_vat) {
                $context_value = sprintf(
                    /* translators: %s = price without VAT */
                    __('incl. BTW | %s excl. BTW', 'autoscout24-importer'),
                    $formatted_price_vat
                );
            }
        }

        if (!$price_value) {
            $price_value = '';
        }

        if (!$context_value) {
            $context_value = '';
        }

        return array(
            'price'   => $price_value,
            'context' => $context_value,
        );
    }

    /**
     * Format numeric value to Euro currency string (e.g. € 112.999,-).
     *
     * @param mixed $value Raw value.
     *
     * @return string
     */
    private function format_currency($value) {
        if ($value === '' || $value === null) {
            return '';
        }

        $normalized = preg_replace('/[^\d,.\-]/', '', (string) $value);
        if ($normalized === '') {
            return '';
        }

        // Replace comma with dot if comma is used as decimal separator.
        if (substr_count($normalized, ',') === 1 && substr_count($normalized, '.') === 0) {
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        if (!is_numeric($normalized)) {
            return '';
        }

        $amount   = (float) $normalized;
        $decimals = floor($amount) === $amount ? 0 : 2;
        $thousands_sep = '.';
        $decimal_sep   = ',';

        $number = number_format($amount, $decimals, $decimal_sep, $thousands_sep);

        if ($decimals === 0) {
            $number .= '';
        }

        return sprintf(__('€ %s', 'autoscout24-importer'), $number);
    }

    /**
     * Output minimal inline styles.
     */
    private function print_styles() {
        if (self::$styles_printed) {
            return;
        }

        self::$styles_printed = true;
        ?>
        <style>
            .as24-price-details {
                border: 1px solid #e2e2e2;
                border-radius: 10px;
                padding: 18px 20px;
                background: #fff;
            }

            .as24-price-details .as24-price-value {
                font-size: 26px;
                font-weight: 600;
                margin-bottom: 4px;
            }

            .as24-price-details .as24-price-context {
                font-size: 12px;
                color: #6b6b6b;
            }

        </style>
        <?php
    }
}
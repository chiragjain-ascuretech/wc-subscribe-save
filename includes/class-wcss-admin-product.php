<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Admin_Product {
    public function init() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_product_data_tab' ] );
        add_action( 'woocommerce_product_data_panels', [ $this, 'render_product_data_panel' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_plans_via_wc' ], 10 );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'wcss-admin', WCSS_PLUGIN_URL . 'assets/admin.css', [], WCSS_VERSION );
        wp_enqueue_script( 'wcss-admin', WCSS_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], WCSS_VERSION, true );
    }

    public function add_product_data_tab( $tabs ) {
        $tabs['wcss_subscribe'] = [
            'label'    => 'Subscribe & Save',
            'target'   => 'wcss_product_data',
            'class'    => [ 'show_if_simple' ],
            'priority' => 70,
        ];
        return $tabs;
    }

    public function render_product_data_panel() {
        global $post;
        $product = wc_get_product( $post->ID );
        if ( ! $product || ! $product instanceof WC_Product_Simple ) {
            return;
        }
        $plans = get_post_meta( $product->get_id(), '_wcss_plans', true );
        if ( ! is_array( $plans ) ) {
            $plans = [];
        }
        ?>
        <div id="wcss_product_data" class="panel woocommerce_options_panel">
            <div class="options_group wcss-plans-wrapper">
                <p>Define one or more subscription options for this product. Customers can choose One-time or Subscribe & Save.</p>
                <table class="widefat wcss-plans-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Interval Count</th>
                            <th>Interval Unit</th>
                            <th>Discount Type</th>
                            <th>Discount Value</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="wcss-plans-body">
                        <?php if ( empty( $plans ) ) : ?>
                            <tr class="wcss-plan-row">
                                <td><input type="text" name="wcss_plans[0][label]" value="Every 3 weeks"/></td>
                                <td><input type="number" min="1" name="wcss_plans[0][interval_count]" value="3"/></td>
                                <td>
                                    <select name="wcss_plans[0][interval_unit]">
                                        <option value="day">day</option>
                                        <option value="week" selected>week</option>
                                        <option value="month">month</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="wcss_plans[0][discount_type]">
                                        <option value="percent" selected>percent</option>
                                        <option value="fixed">fixed</option>
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" min="0" name="wcss_plans[0][discount_value]" value="10"/></td>
                                <td><button type="button" class="button wcss-remove-plan">Remove</button></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $plans as $idx => $plan ) : ?>
                                <tr class="wcss-plan-row">
                                    <td><input type="text" name="wcss_plans[<?php echo esc_attr( $idx ); ?>][label]" value="<?php echo esc_attr( $plan['label'] ?? '' ); ?>"/></td>
                                    <td><input type="number" min="1" name="wcss_plans[<?php echo esc_attr( $idx ); ?>][interval_count]" value="<?php echo esc_attr( $plan['interval_count'] ?? 1 ); ?>"/></td>
                                    <td>
                                        <select name="wcss_plans[<?php echo esc_attr( $idx ); ?>][interval_unit]">
                                            <?php $unit = $plan['interval_unit'] ?? 'week'; ?>
                                            <option value="day" <?php selected( $unit, 'day' ); ?>>day</option>
                                            <option value="week" <?php selected( $unit, 'week' ); ?>>week</option>
                                            <option value="month" <?php selected( $unit, 'month' ); ?>>month</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="wcss_plans[<?php echo esc_attr( $idx ); ?>][discount_type]">
                                            <?php $dtype = $plan['discount_type'] ?? 'percent'; ?>
                                            <option value="percent" <?php selected( $dtype, 'percent' ); ?>>percent</option>
                                            <option value="fixed" <?php selected( $dtype, 'fixed' ); ?>>fixed</option>
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" min="0" name="wcss_plans[<?php echo esc_attr( $idx ); ?>][discount_value]" value="<?php echo esc_attr( $plan['discount_value'] ?? 0 ); ?>"/></td>
                                    <td><button type="button" class="button wcss-remove-plan">Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button button-primary" id="wcss-add-plan">Add Plan</button></p>
            </div>
        </div>
        <?php
    }

    public function sanitize_plan( $plan ) {
        $label = sanitize_text_field( $plan['label'] ?? '' );
        $interval_count = max( 1, absint( $plan['interval_count'] ?? 1 ) );
        $interval_unit = in_array( $plan['interval_unit'] ?? 'week', [ 'day', 'week', 'month' ], true ) ? $plan['interval_unit'] : 'week';
        $discount_type = in_array( $plan['discount_type'] ?? 'percent', [ 'percent', 'fixed' ], true ) ? $plan['discount_type'] : 'percent';
        $discount_value = max( 0, floatval( $plan['discount_value'] ?? 0 ) );
        return [
            'label' => $label,
            'interval_count' => $interval_count,
            'interval_unit' => $interval_unit,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
        ];
    }

    public function save_product_plans_via_wc( $post_id ) {
        $product = wc_get_product( $post_id );
        if ( ! $product || ! $product instanceof WC_Product_Simple ) {
            return;
        }
        $plans_raw = isset( $_POST['wcss_plans'] ) ? (array) $_POST['wcss_plans'] : [];
        $plans = [];
        if ( is_array( $plans_raw ) ) {
            foreach ( $plans_raw as $plan ) {
                if ( empty( $plan['label'] ) ) { continue; }
                $plans[] = $this->sanitize_plan( $plan );
            }
        }
        update_post_meta( $product->get_id(), '_wcss_plans', $plans );
    }
}



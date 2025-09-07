<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
?>

<div class="wrap act-admin-wrap">
    <h1><?php _e('Abandoned Cart Analytics', 'abandoned-cart-tracker'); ?></h1>
    
    <div class="act-header">
        <div class="act-period-selector">
            <label for="act-period"><?php _e('Period:', 'abandoned-cart-tracker'); ?></label>
            <select id="act-period" name="period">
                <option value="7"><?php _e('Last 7 days', 'abandoned-cart-tracker'); ?></option>
                <option value="30" selected><?php _e('Last 30 days', 'abandoned-cart-tracker'); ?></option>
                <option value="90"><?php _e('Last 90 days', 'abandoned-cart-tracker'); ?></option>
            </select>
            <button id="act-refresh" class="button button-primary"><?php _e('Refresh', 'abandoned-cart-tracker'); ?></button>
            <button id="act-export" class="button"><?php _e('Export CSV', 'abandoned-cart-tracker'); ?></button>
        </div>
    </div>

    <div id="act-loading" class="act-loading" style="display: none;">
        <div class="spinner is-active"></div>
        <p><?php _e('Loading statistics...', 'abandoned-cart-tracker'); ?></p>
    </div>

    <div id="act-stats-container">
        <!-- Summary Cards -->
        <div class="act-summary-cards">
            <div class="act-card">
                <div class="act-card-header">
                    <h3><?php _e('Total Carts', 'abandoned-cart-tracker'); ?></h3>
                    <span class="act-icon dashicons dashicons-cart"></span>
                </div>
                <div class="act-card-content">
                    <div class="act-number" id="total-carts">-</div>
                    <div class="act-label"><?php _e('Cart Events', 'abandoned-cart-tracker'); ?></div>
                </div>
            </div>

            <div class="act-card act-card-danger">
                <div class="act-card-header">
                    <h3><?php _e('Abandoned Carts', 'abandoned-cart-tracker'); ?></h3>
                    <span class="act-icon dashicons dashicons-dismiss"></span>
                </div>
                <div class="act-card-content">
                    <div class="act-number" id="abandoned-carts">-</div>
                    <div class="act-label">
                        <span id="abandonment-rate">-</span>% <?php _e('Abandonment Rate', 'abandoned-cart-tracker'); ?>
                    </div>
                </div>
            </div>

            <div class="act-card act-card-success">
                <div class="act-card-header">
                    <h3><?php _e('Converted Carts', 'abandoned-cart-tracker'); ?></h3>
                    <span class="act-icon dashicons dashicons-yes"></span>
                </div>
                <div class="act-card-content">
                    <div class="act-number" id="converted-carts">-</div>
                    <div class="act-label">
                        <span id="conversion-rate">-</span>% <?php _e('Conversion Rate', 'abandoned-cart-tracker'); ?>
                    </div>
                </div>
            </div>

            <div class="act-card act-card-warning">
                <div class="act-card-header">
                    <h3><?php _e('Pending Carts', 'abandoned-cart-tracker'); ?></h3>
                    <span class="act-icon dashicons dashicons-clock"></span>
                </div>
                <div class="act-card-content">
                    <div class="act-number" id="pending-carts">-</div>
                    <div class="act-label"><?php _e('Active Sessions', 'abandoned-cart-tracker'); ?></div>
                </div>
            </div>
        </div>

        <!-- Revenue Cards -->
        <div class="act-revenue-cards">
            <div class="act-card act-card-revenue-lost">
                <div class="act-card-header">
                    <h3><?php _e('Lost Revenue', 'abandoned-cart-tracker'); ?></h3>
                    <span class="act-icon dashicons dashicons-money-alt"></span>
                </div>
                <div class="act-card-content">
                    <div class="act-number act-currency" id="lost-revenue">-</div>
                    <div class="act-label"><?php _e('From Abandoned Carts', 'abandoned-cart-tracker'); ?></div>
                </div>
            </div>

            <div class="act-card act-card-revenue-recovered">
                <div class="act-card-header">
                    <h3><?php _e('Recovered Revenue', 'abandoned-cart-tracker'); ?></h3>
                    <span class="act-icon dashicons dashicons-money-alt"></span>
                </div>
                <div class="act-card-content">
                    <div class="act-number act-currency" id="recovered-revenue">-</div>
                    <div class="act-label"><?php _e('From Converted Carts', 'abandoned-cart-tracker'); ?></div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="act-charts-section">
            <div class="act-chart-container">
                <div class="act-chart-header">
                    <h3><?php _e('Daily Cart Activity', 'abandoned-cart-tracker'); ?></h3>
                </div>
                <div class="act-chart-content">
                    <canvas id="daily-chart" width="400" height="200"></canvas>
                </div>
            </div>

            <div class="act-chart-container">
                <div class="act-chart-header">
                    <h3><?php _e('Conversion Overview', 'abandoned-cart-tracker'); ?></h3>
                </div>
                <div class="act-chart-content">
                    <canvas id="conversion-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Abandoned Products -->
        <div class="act-table-section">
            <div class="act-table-header">
                <h3><?php _e('Top Abandoned Products', 'abandoned-cart-tracker'); ?></h3>
            </div>
            <div class="act-table-content">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Product Name', 'abandoned-cart-tracker'); ?></th>
                            <th><?php _e('Abandonment Count', 'abandoned-cart-tracker'); ?></th>
                            <th><?php _e('Lost Revenue', 'abandoned-cart-tracker'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="top-abandoned-products">
                        <tr>
                            <td colspan="3" class="act-no-data"><?php _e('No data available', 'abandoned-cart-tracker'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="act-table-section">
            <div class="act-table-header">
                <h3><?php _e('Recent Cart Activity', 'abandoned-cart-tracker'); ?></h3>
            </div>
            <div class="act-table-content">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'abandoned-cart-tracker'); ?></th>
                            <th><?php _e('Total Carts', 'abandoned-cart-tracker'); ?></th>
                            <th><?php _e('Abandoned', 'abandoned-cart-tracker'); ?></th>
                            <th><?php _e('Converted', 'abandoned-cart-tracker'); ?></th>
                            <th><?php _e('Pending', 'abandoned-cart-tracker'); ?></th>
                            <th><?php _e('Abandonment Rate', 'abandoned-cart-tracker'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="daily-activity">
                        <tr>
                            <td colspan="6" class="act-no-data"><?php _e('No data available', 'abandoned-cart-tracker'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Help Section -->
    <div class="act-help-section">
        <h3><?php _e('How It Works', 'abandoned-cart-tracker'); ?></h3>
        <div class="act-help-content">
            <div class="act-help-item">
                <h4><?php _e('Cart Tracking', 'abandoned-cart-tracker'); ?></h4>
                <p><?php _e('The plugin automatically tracks when customers add products to their cart and monitors their journey through the checkout process.', 'abandoned-cart-tracker'); ?></p>
            </div>
            <div class="act-help-item">
                <h4><?php _e('Status Updates', 'abandoned-cart-tracker'); ?></h4>
                <p><?php _e('Cart status is updated in real-time: Pending (just added), Abandoned (30+ minutes with no order), Converted (order completed).', 'abandoned-cart-tracker'); ?></p>
            </div>
            <div class="act-help-item">
                <h4><?php _e('User Information', 'abandoned-cart-tracker'); ?></h4>
                <p><?php _e('For logged-in users, we capture their email and user ID. For guests, we track their session and attempt to capture their email during checkout.', 'abandoned-cart-tracker'); ?></p>
            </div>
            <div class="act-help-item">
                <h4><?php _e('Data Retention', 'abandoned-cart-tracker'); ?></h4>
                <p><?php _e('Cart data is automatically cleaned up after 90 days to maintain optimal database performance.', 'abandoned-cart-tracker'); ?></p>
            </div>
        </div>
    </div>
</div>

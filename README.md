# WooCommerce Abandoned Cart Tracker

A comprehensive WordPress plugin for tracking abandoned carts in WooCommerce, providing detailed analytics and insights into customer behavior.

## Features

- **Real-time Cart Tracking**: Automatically tracks when customers add products to their cart
- **User Information Capture**: Records user email, ID, and session data for both logged-in and guest users
- **Smart Status Management**: Updates cart status from pending → abandoned → converted based on user actions
- **Comprehensive Analytics**: Detailed statistics on abandonment rates, conversion rates, and revenue impact
- **Visual Dashboard**: Charts and graphs showing daily activity, conversion overview, and trends
- **Product Insights**: Identifies which products are most frequently abandoned
- **Data Export**: Export all cart data to CSV for further analysis
- **Automatic Cleanup**: Removes old data after 90 days to maintain performance
- **Responsive Design**: Admin dashboard works on all device sizes

## Installation

1. Upload the `abandoned-cart` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Abandoned Carts** in your WordPress admin menu
4. Start tracking immediately - no configuration required!

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## How It Works

### Cart Tracking Process

1. **Add to Cart Event**: When a customer adds a product to their cart, the plugin records:
   - Product information (ID, name, price, quantity)
   - User information (email, user ID if logged in)
   - Session data and cart total
   - IP address and user agent
   - Timestamp

2. **Status Updates**:
   - **Pending**: Initial status when product is added to cart
   - **Abandoned**: Status changes after 30 minutes with no order completion
   - **Converted**: Status updates when customer completes an order

3. **Order Completion Tracking**: The plugin monitors WooCommerce order events and automatically updates cart status when orders are completed.

### Database Schema

The plugin creates a table `wp_wc_abandoned_carts` with the following structure:

- `id`: Unique identifier
- `session_id`: Customer session ID
- `user_id`: WordPress user ID (for logged-in users)
- `user_email`: Customer email address
- `product_id`: WooCommerce product ID
- `product_name`: Product name at time of cart addition
- `quantity`: Number of items added
- `price`: Product price at time of addition
- `cart_total`: Total cart value
- `status`: Current status (pending/abandoned/converted)
- `user_agent`: Browser/device information
- `ip_address`: Customer IP address
- `created_at`: When item was added to cart
- `updated_at`: Last status update time
- `converted_at`: When order was completed (if applicable)
- `order_id`: WooCommerce order ID (if converted)

## Dashboard Features

### Summary Cards
- **Total Carts**: Number of cart events in selected period
- **Abandoned Carts**: Count and percentage of abandoned carts
- **Converted Carts**: Count and conversion rate
- **Pending Carts**: Currently active sessions
- **Lost Revenue**: Potential revenue from abandoned carts
- **Recovered Revenue**: Actual revenue from converted carts

### Charts
- **Daily Cart Activity**: Line chart showing cart trends over time
- **Conversion Overview**: Pie chart showing status distribution

### Data Tables
- **Top Abandoned Products**: Most frequently abandoned products with revenue impact
- **Recent Cart Activity**: Daily breakdown of cart events and metrics

### Export Functionality
- Export all cart data to CSV format
- Includes all tracked information for external analysis

## API Hooks

The plugin provides several action hooks for developers:

```php
// Fired when a cart is marked as abandoned
do_action('act_cart_abandoned', $cart_data);

// Fired when a cart is converted
do_action('act_cart_converted', $cart_data, $order_id);

// Fired during data cleanup
do_action('act_before_cleanup', $days_old);
```

## Customization

### Abandonment Time Threshold
To change the 30-minute abandonment threshold, add this to your theme's functions.php:

```php
add_filter('act_abandonment_threshold', function($minutes) {
    return 60; // Change to 60 minutes
});
```

### Data Retention Period
To modify the 90-day data retention period:

```php
add_filter('act_data_retention_days', function($days) {
    return 180; // Keep data for 180 days
});
```

## Privacy Considerations

This plugin collects and stores customer data including:
- Email addresses
- IP addresses
- User agent strings
- Shopping behavior

Ensure compliance with applicable privacy laws (GDPR, CCPA, etc.) by:
- Including cart tracking in your privacy policy
- Providing data deletion options if required
- Implementing appropriate data retention policies

## Troubleshooting

### Plugin Not Tracking Carts
1. Ensure WooCommerce is active and properly configured
2. Check that your theme properly triggers WooCommerce hooks
3. Verify database table was created during activation

### Missing User Emails for Guests
- Guest user emails are captured during checkout process
- For cart abandonment before checkout, only session data is available

### Performance Considerations
- The plugin automatically cleans up old data to maintain performance
- For high-traffic sites, consider adjusting the cleanup frequency
- Database indexes are optimized for common queries

## Support

For support and feature requests, please refer to the plugin documentation or contact the developer.

## Changelog

### Version 1.0.0
- Initial release
- Complete cart tracking functionality
- Admin dashboard with analytics
- Data export capabilities
- Automatic cleanup system

## License

This plugin is licensed under the GPL v2 or later.

<?php
/**
 * Database Handler for Mister Coffee Outlet Finder
 *
 * @package MisterCoffeeOutletFinder
 */

if (!defined('ABSPATH')) {
    exit;
}

class MC_Outlet_DB {

    /**
     * Get table names
     */
    public static function get_outlets_table() {
        global $wpdb;
        return $wpdb->prefix . 'mc_outlets';
    }

    public static function get_products_table() {
        global $wpdb;
        return $wpdb->prefix . 'mc_products';
    }

    /**
     * Create tables on plugin activation
     */
    public static function init_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $outlets_table = self::get_outlets_table();
        $products_table = self::get_products_table();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Outlets Table
        $sql_outlets = "CREATE TABLE $outlets_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            retailer varchar(100) DEFAULT '',
            country varchar(100) DEFAULT 'Malaysia',
            state varchar(100) DEFAULT '',
            region varchar(100) DEFAULT '',
            region_tag varchar(255) DEFAULT '',
            address text,
            operating_hours varchar(255) DEFAULT 'Mon - Sun: 10:00 AM - 10:00 PM',
            phone varchar(100) DEFAULT '',
            lat decimal(10, 7) NOT NULL DEFAULT '0.0000000',
            lng decimal(10, 7) NOT NULL DEFAULT '0.0000000',
            grinder tinyint(1) NOT NULL DEFAULT '0',
            photo_url text,
            maps_url text,
            products longtext,
            is_active tinyint(1) NOT NULL DEFAULT '1',
            sort_order int(11) NOT NULL DEFAULT '0',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY state (state),
            KEY retailer (retailer),
            KEY grinder (grinder)
        ) $charset_collate;";
        dbDelta($sql_outlets);

        // Products Catalog Table
        $sql_products = "CREATE TABLE $products_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            category varchar(100) NOT NULL DEFAULT 'Other',
            description text,
            sku varchar(100) DEFAULT '',
            is_active tinyint(1) NOT NULL DEFAULT '1',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY category (category)
        ) $charset_collate;";
        dbDelta($sql_products);

        // Seed data if empty
        self::seed_data_if_empty();
    }

    /**
     * Seeds bundled 170 outlets and 102 products if database is empty
     */
    public static function seed_data_if_empty($force = false) {
        global $wpdb;
        $outlets_table = self::get_outlets_table();
        $products_table = self::get_products_table();

        $outlet_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $outlets_table");
        $product_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $products_table");

        // Seed Products Catalog
        if ($product_count === 0 || $force) {
            $prod_file = MC_OUTLET_PLUGIN_DIR . 'data/seed_products.json';
            if (file_exists($prod_file)) {
                $products = json_decode(file_get_contents($prod_file), true);
                if (is_array($products)) {
                    if ($force) {
                        $wpdb->query("TRUNCATE TABLE $products_table");
                    }
                    foreach ($products as $p) {
                        $wpdb->insert($products_table, [
                            'name' => sanitize_text_field($p['name']),
                            'category' => sanitize_text_field($p['category']),
                            'is_active' => 1
                        ]);
                    }
                }
            }
        }

        // Seed Outlets
        if ($outlet_count === 0 || $force) {
            $outlet_file = MC_OUTLET_PLUGIN_DIR . 'data/seed_outlets.json';
            if (file_exists($outlet_file)) {
                $outlets = json_decode(file_get_contents($outlet_file), true);
                if (is_array($outlets)) {
                    if ($force) {
                        $wpdb->query("TRUNCATE TABLE $outlets_table");
                    }
                    $i = 1;
                    foreach ($outlets as $o) {
                        $wpdb->insert($outlets_table, [
                            'name' => sanitize_text_field($o['name']),
                            'retailer' => sanitize_text_field($o['retailer']),
                            'country' => sanitize_text_field($o['country']),
                            'state' => sanitize_text_field($o['state']),
                            'region' => sanitize_text_field($o['region']),
                            'region_tag' => sanitize_text_field($o['name']),
                            'address' => sanitize_text_field($o['name'] . ', ' . $o['state'] . ', ' . $o['country']),
                            'operating_hours' => 'Mon - Sun: 10:00 AM - 10:00 PM',
                            'phone' => '+60 12-345 6789',
                            'lat' => floatval($o['lat']),
                            'lng' => floatval($o['lng']),
                            'grinder' => !empty($o['grinder']) ? 1 : 0,
                            'photo_url' => '',
                            'maps_url' => esc_url_raw($o['maps_url']),
                            'products' => wp_json_encode($o['products']),
                            'is_active' => 1,
                            'sort_order' => $i++
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Retrieve all outlets with optional filters
     */
    public static function get_outlets($args = []) {
        global $wpdb;
        $table = self::get_outlets_table();

        $where = ['is_active = 1'];
        $params = [];

        if (!empty($args['state'])) {
            $where[] = 'state = %s';
            $params[] = $args['state'];
        }

        if (!empty($args['retailer'])) {
            $where[] = 'retailer = %s';
            $params[] = $args['retailer'];
        }

        if (isset($args['grinder']) && $args['grinder'] !== '') {
            $where[] = 'grinder = %d';
            $params[] = (int) $args['grinder'];
        }

        $where_sql = implode(' AND ', $where);
        $order_by = !empty($args['order_by']) ? sanitize_sql_orderby($args['order_by']) : 'sort_order ASC, name ASC';

        $query = "SELECT * FROM $table WHERE $where_sql ORDER BY $order_by";

        if (!empty($params)) {
            $prepared = $wpdb->prepare($query, $params);
            $results = $wpdb->get_results($prepared, ARRAY_A);
        } else {
            $results = $wpdb->get_results($query, ARRAY_A);
        }

        // Unserialize product lists
        foreach ($results as &$r) {
            $r['products'] = !empty($r['products']) ? json_decode($r['products'], true) : [];
            $r['lat'] = floatval($r['lat']);
            $r['lng'] = floatval($r['lng']);
            $r['grinder'] = (bool) $r['grinder'];
        }

        return $results;
    }

    /**
     * Get single outlet by ID
     */
    public static function get_outlet($id) {
        global $wpdb;
        $table = self::get_outlets_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if ($row) {
            $row['products'] = !empty($row['products']) ? json_decode($row['products'], true) : [];
            $row['lat'] = floatval($row['lat']);
            $row['lng'] = floatval($row['lng']);
            $row['grinder'] = (bool) $row['grinder'];
        }
        return $row;
    }

    /**
     * Save / Update an outlet
     */
    public static function save_outlet($data) {
        global $wpdb;
        $table = self::get_outlets_table();

        $record = [
            'name' => sanitize_text_field($data['name']),
            'retailer' => sanitize_text_field($data['retailer'] ?? ''),
            'country' => sanitize_text_field($data['country'] ?? 'Malaysia'),
            'state' => sanitize_text_field($data['state'] ?? ''),
            'region' => sanitize_text_field($data['region'] ?? ''),
            'region_tag' => sanitize_text_field($data['region_tag'] ?? ''),
            'address' => sanitize_textarea_field($data['address'] ?? ''),
            'operating_hours' => sanitize_text_field($data['operating_hours'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'lat' => floatval($data['lat'] ?? 0),
            'lng' => floatval($data['lng'] ?? 0),
            'grinder' => !empty($data['grinder']) ? 1 : 0,
            'photo_url' => esc_url_raw($data['photo_url'] ?? ''),
            'maps_url' => esc_url_raw($data['maps_url'] ?? ''),
            'products' => isset($data['products']) && is_array($data['products']) ? wp_json_encode(array_values($data['products'])) : '[]',
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1
        ];

        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            $wpdb->update($table, $record, ['id' => $id]);
            return $id;
        } else {
            $wpdb->insert($table, $record);
            return $wpdb->insert_id;
        }
    }

    /**
     * Delete an outlet
     */
    public static function delete_outlet($id) {
        global $wpdb;
        $table = self::get_outlets_table();
        return $wpdb->delete($table, ['id' => (int) $id]);
    }

    /**
     * Fast update for outlet photo URL
     */
    public static function update_outlet_photo($id, $photo_url) {
        global $wpdb;
        $table = self::get_outlets_table();
        return $wpdb->update($table, ['photo_url' => esc_url_raw($photo_url)], ['id' => (int) $id]);
    }

    /**
     * Get all catalog products
     */
    public static function get_products() {
        global $wpdb;
        $table = self::get_products_table();
        return $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY category ASC, name ASC", ARRAY_A);
    }

    /**
     * Save product / SKU
     */
    public static function save_product($data) {
        global $wpdb;
        $table = self::get_products_table();

        $record = [
            'name' => sanitize_text_field($data['name']),
            'category' => sanitize_text_field($data['category'] ?? 'Other'),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'sku' => sanitize_text_field($data['sku'] ?? ''),
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1
        ];

        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            $wpdb->update($table, $record, ['id' => $id]);
            return $id;
        } else {
            $wpdb->insert($table, $record);
            return $wpdb->insert_id;
        }
    }

    /**
     * Delete product / SKU
     */
    public static function delete_product($id) {
        global $wpdb;
        $table = self::get_products_table();
        return $wpdb->delete($table, ['id' => (int) $id]);
    }
}

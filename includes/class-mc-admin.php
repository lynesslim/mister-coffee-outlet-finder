<?php
/**
 * Admin Dashboard & Management UI
 *
 * @package MisterCoffeeOutletFinder
 */

if (!defined('ABSPATH')) {
    exit;
}

class MC_Outlet_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX handlers
        add_action('wp_ajax_mc_save_outlet', [$this, 'ajax_save_outlet']);
        add_action('wp_ajax_mc_delete_outlet', [$this, 'ajax_delete_outlet']);
        add_action('wp_ajax_mc_get_outlet', [$this, 'ajax_get_outlet']);
        add_action('wp_ajax_mc_save_product', [$this, 'ajax_save_product']);
        add_action('wp_ajax_mc_delete_product', [$this, 'ajax_delete_product']);
        add_action('wp_ajax_mc_reseed_data', [$this, 'ajax_reseed_data']);
        add_action('wp_ajax_mc_fetch_google_photo', [$this, 'ajax_fetch_google_photo']);
        add_action('wp_ajax_mc_get_sync_outlets', [$this, 'ajax_get_sync_outlets']);
    }

    public function register_admin_menus() {
        add_menu_page(
            __('Mister Coffee Outlets', 'mc-outlet-finder'),
            __('Coffee Outlets', 'mc-outlet-finder'),
            'manage_options',
            'mc-outlets',
            [$this, 'render_outlets_page'],
            'dashicons-location-alt',
            26
        );

        add_submenu_page(
            'mc-outlets',
            __('All Outlets', 'mc-outlet-finder'),
            __('All Outlets', 'mc-outlet-finder'),
            'manage_options',
            'mc-outlets',
            [$this, 'render_outlets_page']
        );

        add_submenu_page(
            'mc-outlets',
            __('Product Catalog / SKUs', 'mc-outlet-finder'),
            __('Product Catalog', 'mc-outlet-finder'),
            'manage_options',
            'mc-products',
            [$this, 'render_products_page']
        );

        add_submenu_page(
            'mc-outlets',
            __('Google Photos Sync', 'mc-outlet-finder'),
            __('Google Photos Sync', 'mc-outlet-finder'),
            'manage_options',
            'mc-photo-sync',
            [$this, 'render_photo_sync_page']
        );

        add_submenu_page(
            'mc-outlets',
            __('Settings & Data Sync', 'mc-outlet-finder'),
            __('Settings & Sync', 'mc-outlet-finder'),
            'manage_options',
            'mc-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'mc-outlets') === false && strpos($hook, 'mc-products') === false && strpos($hook, 'mc-settings') === false && strpos($hook, 'mc-photo-sync') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('mc-admin-css', MC_OUTLET_PLUGIN_URL . 'assets/css/admin.css', [], MC_OUTLET_VERSION);
        wp_enqueue_script('mc-admin-js', MC_OUTLET_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], MC_OUTLET_VERSION, true);

        wp_localize_script('mc-admin-js', 'mcAdminData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mc_admin_nonce')
        ]);
    }

    /**
     * Page 1: Outlets Management
     */
    public function render_outlets_page() {
        $outlets = MC_Outlet_DB::get_outlets();
        $products = MC_Outlet_DB::get_products();
        
        $synced_count = count(array_filter($outlets, fn($x) => !empty($x['photo_url'])));
        $missing_count = count($outlets) - $synced_count;
        ?>
        <div class="wrap mc-admin-wrap">
            <div class="mc-admin-header">
                <div>
                    <h1 class="mc-admin-title">Mister Coffee Retail Outlets</h1>
                    <p class="mc-admin-sub">Manage outlet locations, Google Maps coordinates, in-store grinders, and product stock availability.</p>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="<?php echo admin_url('admin.php?page=mc-photo-sync'); ?>" class="button button-secondary" style="display:inline-flex; align-items:center; gap:5px; font-weight:600;">
                        📸 Bulk Sync Google Photos
                    </a>
                    <button type="button" class="button button-primary mc-btn-primary" onclick="mcOpenOutletModal()">
                        + Add New Outlet
                    </button>
                </div>
            </div>

            <div class="mc-admin-card">
                <div class="mc-table-controls" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                        <input type="text" id="mcAdminSearch" placeholder="Search outlets by name, mall, or state..." oninput="mcFilterAdminTable()" class="regular-text" style="min-width:280px;" />
                        <div class="mc-photo-filter-group" style="display:inline-flex; gap:4px;">
                            <button type="button" class="button mc-photo-filter-btn active" data-filter="all" onclick="mcFilterPhotoStatus('all')">
                                All (<?php echo count($outlets); ?>)
                            </button>
                            <button type="button" class="button mc-photo-filter-btn" data-filter="synced" onclick="mcFilterPhotoStatus('synced')">
                                ✅ With Photo (<?php echo $synced_count; ?>)
                            </button>
                            <button type="button" class="button mc-photo-filter-btn" data-filter="missing" onclick="mcFilterPhotoStatus('missing')">
                                ⚠️ Missing Photo (<?php echo $missing_count; ?>)
                            </button>
                        </div>
                    </div>
                    <span class="mc-count-badge">Showing: <strong id="mcTotalCount"><?php echo count($outlets); ?></strong></span>
                </div>

                <table class="wp-list-table widefat fixed striped mc-admin-table" id="mcOutletsTable">
                    <thead>
                        <tr>
                            <th width="45">ID</th>
                            <th width="65">Photo</th>
                            <th width="200">Outlet Name</th>
                            <th width="110">Retailer</th>
                            <th width="120">State / Region</th>
                            <th width="80">Grinder</th>
                            <th width="110">Products</th>
                            <th>Coordinates / Map</th>
                            <th width="130">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($outlets)) : ?>
                            <tr><td colspan="9" style="text-align:center; padding:30px;">No outlets found. Click "Settings & Sync" to import the seed data.</td></tr>
                        <?php else : ?>
                            <?php foreach ($outlets as $o) : 
                                $prod_count = count($o['products']);
                                $has_photo = !empty($o['photo_url']);
                            ?>
                                <tr data-id="<?php echo esc_attr($o['id']); ?>" data-name="<?php echo esc_attr(strtolower($o['name'])); ?>" data-state="<?php echo esc_attr(strtolower($o['state'])); ?>" data-has-photo="<?php echo $has_photo ? '1' : '0'; ?>">
                                    <td><strong>#<?php echo esc_html($o['id']); ?></strong></td>
                                    <td>
                                        <?php if ($has_photo) : ?>
                                            <a href="<?php echo esc_url($o['photo_url']); ?>" target="_blank" title="View Full Google Image">
                                                <img src="<?php echo esc_url($o['photo_url']); ?>" style="width:38px; height:38px; object-fit:cover; border-radius:4px; border:1px solid #d1d5db; display:block;" />
                                            </a>
                                        <?php else : ?>
                                            <span style="display:inline-block; font-size:10px; font-weight:700; color:#dc2626; background:#fef2f2; border:1px solid #fecaca; padding:2px 5px; border-radius:3px;">No Photo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="row-title"><?php echo esc_html($o['name']); ?></strong>
                                    </td>
                                    <td><span class="mc-tag"><?php echo esc_html($o['retailer']); ?></span></td>
                                    <td><?php echo esc_html($o['state']); ?> <small style="color:#666;">(<?php echo esc_html($o['region']); ?>)</small></td>
                                    <td>
                                        <?php if ($o['grinder']) : ?>
                                            <span class="mc-badge-grinder">☕ Yes</span>
                                        <?php else : ?>
                                            <span style="color:#aaa;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="mc-badge-stock"><?php echo esc_html($prod_count); ?> SKUs</span>
                                    </td>
                                    <td>
                                        <small style="color:#555;"><?php echo esc_html($o['lat']); ?>, <?php echo esc_html($o['lng']); ?></small><br/>
                                        <?php if (!empty($o['maps_url'])) : ?>
                                            <a href="<?php echo esc_url($o['maps_url']); ?>" target="_blank" style="font-size:11px;">View on Google Maps ↗</a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small" onclick="mcEditOutlet(<?php echo esc_attr($o['id']); ?>)">Edit</button>
                                        <button type="button" class="button button-small button-link-delete" onclick="mcDeleteOutlet(<?php echo esc_attr($o['id']); ?>, '<?php echo esc_js($o['name']); ?>')">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Outlet Edit / Create Modal -->
            <?php $this->render_outlet_modal($products); ?>

        </div>
        <?php
    }

    /**
     * Shared Outlet Modal Renderer
     */
    private function render_outlet_modal($products = null) {
        if ($products === null) {
            $products = MC_Outlet_DB::get_products();
        }

        // Group products by category
        $grouped_products = [];
        foreach ($products as $p) {
            $cat = !empty($p['category']) ? $p['category'] : 'Other Products';
            $grouped_products[$cat][] = $p['name'];
        }
        ?>
        <div id="mcOutletModal" class="mc-modal-backdrop" style="display:none;">
            <div class="mc-modal-dialog">
                <div class="mc-modal-header">
                    <h2 id="mcModalTitle">Add New Outlet</h2>
                    <button type="button" class="mc-modal-close" onclick="mcCloseOutletModal()">&times;</button>
                </div>
                <form id="mcOutletForm" onsubmit="mcSaveOutletForm(event)">
                    <input type="hidden" id="outlet_id" name="id" value="" />
                    
                    <div class="mc-modal-body">
                        <div class="mc-modal-two-col">
                            <!-- Left Column: Outlet Details -->
                            <div class="mc-modal-col-details">
                                <div class="mc-form-grid">
                                    <div class="mc-form-col">
                                        <label><strong>Outlet Name *</strong></label>
                                        <input type="text" id="outlet_name" name="name" required class="widefat" placeholder="e.g. Mercato Kulim" />
                                    </div>
                                    <div class="mc-form-col">
                                        <label><strong>Retailer / Chain</strong></label>
                                        <input type="text" id="outlet_retailer" name="retailer" class="widefat" placeholder="e.g. Lotus, Mercato, AEON, Showroom" />
                                    </div>
                                </div>

                                <div class="mc-form-grid">
                                    <div class="mc-form-col">
                                        <label><strong>State</strong></label>
                                        <input type="text" id="outlet_state" name="state" class="widefat" placeholder="e.g. KL & SELANGOR, KEDAH, PENANG, JOHOR" />
                                    </div>
                                    <div class="mc-form-col">
                                        <label><strong>Region</strong></label>
                                        <input type="text" id="outlet_region" name="region" class="widefat" placeholder="e.g. Northern, Central, Southern, East Coast" />
                                    </div>
                                </div>

                                <div class="mc-form-row">
                                    <label><strong>Address / Storefront Location</strong></label>
                                    <textarea id="outlet_address" name="address" rows="3" class="widefat" placeholder="e.g. Lot G-12, Ground Floor, Mall Name..."></textarea>
                                </div>

                                <div class="mc-form-grid">
                                    <div class="mc-form-col">
                                        <label><strong>Operating Hours</strong></label>
                                        <input type="text" id="outlet_operating_hours" name="operating_hours" class="widefat" value="Mon - Sun: 10:00 AM - 10:00 PM" />
                                    </div>
                                    <div class="mc-form-col">
                                        <label><strong>Phone Number</strong></label>
                                        <input type="text" id="outlet_phone" name="phone" class="widefat" placeholder="e.g. +60 4-490 8822" />
                                    </div>
                                </div>

                                <div class="mc-form-grid">
                                    <div class="mc-form-col">
                                        <label><strong>Latitude</strong></label>
                                        <input type="number" step="0.0000001" id="outlet_lat" name="lat" class="widefat" placeholder="e.g. 5.3857255" />
                                    </div>
                                    <div class="mc-form-col">
                                        <label><strong>Longitude</strong></label>
                                        <input type="number" step="0.0000001" id="outlet_lng" name="lng" class="widefat" placeholder="e.g. 100.5468934" />
                                    </div>
                                </div>

                                <div class="mc-form-grid">
                                    <div class="mc-form-col">
                                        <label><strong>Google Maps URL</strong></label>
                                        <input type="url" id="outlet_maps_url" name="maps_url" class="widefat" placeholder="https://maps.app.goo.gl/..." />
                                    </div>
                                    <div class="mc-form-col">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                            <label style="margin:0;"><strong>Store Photo URL</strong></label>
                                            <button type="button" class="button button-small" id="mcFetchGmapPhotoBtn" onclick="mcFetchGooglePhoto()" style="color:#1d4ed8; border-color:#93c5fd; background:#eff6ff;" title="Auto-find storefront photo via Google Places API">
                                                📍 Fetch from Google Maps
                                            </button>
                                        </div>
                                        <div style="display:flex; gap:6px;">
                                            <input type="text" id="outlet_photo_url" name="photo_url" class="widefat" placeholder="https://... image link" />
                                            <button type="button" class="button" onclick="mcUploadMedia('outlet_photo_url')">Upload</button>
                                        </div>
                                        <div id="mcPhotoFetchStatus" style="font-size:11px; margin-top:4px; display:none;"></div>
                                    </div>
                                </div>

                                <div class="mc-form-row" style="margin-top:10px;">
                                    <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
                                        <input type="checkbox" id="outlet_grinder" name="grinder" value="1" />
                                        <span>☕ <strong>In-Store Coffee Grinder Available</strong> at this outlet</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Right Column: Product Availability Checklist -->
                            <div class="mc-modal-col-products">
                                <div class="mc-products-header">
                                    <label><strong>Product Availability Checklist</strong></label>
                                    <div class="mc-checklist-actions">
                                        <button type="button" class="button button-small" onclick="mcToggleAllCheckboxes(true)">Check All</button>
                                        <button type="button" class="button button-small" onclick="mcToggleAllCheckboxes(false)">Uncheck All</button>
                                    </div>
                                </div>
                                
                                <div class="mc-products-search-wrap">
                                    <input type="text" id="mcProductSearch" placeholder="🔍 Quick search products..." class="widefat" onkeyup="mcFilterChecklist(this.value)" />
                                </div>
                                
                                <div class="mc-products-checklist-box">
                                    <?php foreach ($grouped_products as $cat_title => $cat_items) : ?>
                                        <div class="mc-cat-section">
                                            <h4><?php echo esc_html($cat_title); ?> (<?php echo count($cat_items); ?>)</h4>
                                            <div class="mc-checkbox-grid">
                                                <?php foreach ($cat_items as $item_name) : ?>
                                                    <label class="mc-product-check-item">
                                                        <input type="checkbox" name="products[]" value="<?php echo esc_attr($item_name); ?>" />
                                                        <span><?php echo esc_html($item_name); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mc-modal-footer">
                        <button type="button" class="button" onclick="mcCloseOutletModal()">Cancel</button>
                        <button type="submit" class="button button-primary mc-btn-primary" id="mcSaveBtn">Save Outlet</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Page 4: Bulk Google Maps Photo Sync Dashboard
     */
    public function render_photo_sync_page() {
        $outlets = MC_Outlet_DB::get_outlets();
        $api_key = get_option('mc_google_maps_api_key', '');
        
        $total_count = count($outlets);
        $synced_outlets = array_filter($outlets, fn($o) => !empty($o['photo_url']));
        $missing_outlets = array_values(array_filter($outlets, fn($o) => empty($o['photo_url'])));
        $synced_count = count($synced_outlets);
        $missing_count = count($missing_outlets);
        $sync_percentage = $total_count > 0 ? round(($synced_count / $total_count) * 100) : 0;
        ?>
        <div class="wrap mc-admin-wrap">
            <div class="mc-admin-header">
                <div>
                    <h1 class="mc-admin-title">⚡ Google Maps Photos Bulk Sync</h1>
                    <p class="mc-admin-sub">Batch query Google Places API (New) to pull authentic storefront and interior photos for all retail locations.</p>
                </div>
                <div>
                    <a href="<?php echo admin_url('admin.php?page=mc-outlets'); ?>" class="button button-secondary">← Back to Outlets List</a>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="mc-sync-metrics-grid">
                <div class="mc-sync-metric-card">
                    <span class="mc-metric-label">Total Outlets</span>
                    <strong class="mc-metric-val"><?php echo $total_count; ?></strong>
                    <span class="mc-metric-sub">Registered in database</span>
                </div>
                <div class="mc-sync-metric-card success">
                    <span class="mc-metric-label">✅ Photos Synced</span>
                    <strong class="mc-metric-val" id="mcSyncedCountMetric"><?php echo $synced_count; ?></strong>
                    <span class="mc-metric-sub" id="mcSyncedPercentMetric"><?php echo $sync_percentage; ?>% Coverage</span>
                </div>
                <div class="mc-sync-metric-card warning">
                    <span class="mc-metric-label">⚠️ Missing Photos</span>
                    <strong class="mc-metric-val" id="mcMissingCountMetric"><?php echo $missing_count; ?></strong>
                    <span class="mc-metric-sub">Need synchronization</span>
                </div>
                <div class="mc-sync-metric-card <?php echo !empty($api_key) ? 'info' : 'danger'; ?>">
                    <span class="mc-metric-label">API Status</span>
                    <strong class="mc-metric-val" style="font-size:18px; line-height:1.4;">
                        <?php echo !empty($api_key) ? '🟢 Key Ready' : '🔴 Key Missing'; ?>
                    </strong>
                    <span class="mc-metric-sub">
                        <?php if (!empty($api_key)) : ?>
                            Places API (New) Enabled
                        <?php else : ?>
                            <a href="<?php echo admin_url('admin.php?page=mc-settings'); ?>">Configure in Settings ↗</a>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <?php if (empty($api_key)) : ?>
                <div class="notice notice-error" style="padding:12px; margin:15px 0;">
                    <strong>Google Places API Key is not configured!</strong>
                    Please go to <a href="<?php echo admin_url('admin.php?page=mc-settings'); ?>"><strong>Settings & Sync</strong></a> and enter your Google Places API Key before starting auto-sync.
                </div>
            <?php endif; ?>

            <!-- Sync Controls Card -->
            <div class="mc-admin-card" style="margin-top:20px;">
                <h2 style="margin-top:0;">1. Synchronization Settings</h2>
                <div style="display:flex; flex-wrap:wrap; gap:20px; align-items:center; margin:15px 0;">
                    <div>
                        <label style="font-weight:600; display:block; margin-bottom:5px;">Target Scope:</label>
                        <label style="margin-right:15px; cursor:pointer;">
                            <input type="radio" name="mcSyncScope" value="missing" checked />
                            <strong>Only Outlets Missing Photos (<?php echo $missing_count; ?> outlets)</strong> <span style="color:#666;">(Fastest & Recommended)</span>
                        </label>
                        <label style="cursor:pointer;">
                            <input type="radio" name="mcSyncScope" value="all" />
                            <strong>Force Re-Sync All (<?php echo $total_count; ?> outlets)</strong>
                        </label>
                    </div>

                    <div style="border-left:1px solid #e5e7eb; padding-left:20px;">
                        <label style="font-weight:600; display:block; margin-bottom:5px;">Delay Between Requests:</label>
                        <select id="mcSyncDelay" style="min-width:160px;">
                            <option value="400">400 ms (Fast)</option>
                            <option value="600" selected>600 ms (Recommended)</option>
                            <option value="1000">1.0 second (Safe)</option>
                            <option value="1500">1.5 seconds (Very Safe)</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="button" class="button button-primary button-hero" id="mcStartSyncBtn" onclick="mcStartBatchPhotoSync()" <?php disabled(empty($api_key)); ?>>
                        <span class="dashicons dashicons-update" style="margin-top:4px;"></span> Start Google Photos Auto-Sync
                    </button>
                    <button type="button" class="button button-secondary button-hero" id="mcStopSyncBtn" onclick="mcStopBatchPhotoSync()" style="display:none; color:#dc2626; border-color:#fca5a5;">
                        <span class="dashicons dashicons-controls-pause" style="margin-top:4px;"></span> Pause / Stop
                    </button>
                </div>
            </div>

            <!-- Live Progress Card -->
            <div class="mc-admin-card" id="mcSyncProgressCard" style="margin-top:20px; display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <h3 style="margin:0;" id="mcProgressStatusTitle">Sync in progress...</h3>
                    <span id="mcProgressPercentText" style="font-weight:700; font-size:16px; color:#2563eb;">0%</span>
                </div>

                <div class="mc-progress-bar-wrap">
                    <div class="mc-progress-bar-fill" id="mcProgressBarFill" style="width:0%;"></div>
                </div>

                <div style="display:flex; justify-content:space-between; font-size:12px; color:#6b7280; margin-top:8px;">
                    <span id="mcProgressSubtext">Preparing synchronization...</span>
                    <span>
                        Synced: <strong id="mcLiveSyncedCount" style="color:#16a34a;">0</strong> |
                        No Photo / Failed: <strong id="mcLiveFailedCount" style="color:#dc2626;">0</strong>
                    </span>
                </div>

                <!-- Real-time Console Log -->
                <div style="margin-top:14px;">
                    <label style="font-weight:600; font-size:11px; text-transform:uppercase; color:#6b7280; letter-spacing:0.5px;">Activity Console</label>
                    <div id="mcSyncTerminalLog" class="mc-terminal-console"></div>
                </div>
            </div>

            <!-- Identified Unsynced Outlets Section -->
            <div class="mc-admin-card" style="margin-top:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <div>
                        <h2 style="margin:0;">2. Identified Unsynced Outlets (<span id="mcUnsyncedTableCount"><?php echo $missing_count; ?></span>)</h2>
                        <p style="margin:4px 0 0; color:#6b7280; font-size:13px;">These outlets currently have no Google Maps storefront photo. You can edit their address or name to retry, or upload an image manually.</p>
                    </div>
                    <button type="button" class="button" onclick="mcRefreshUnsyncedTable()">↻ Refresh List</button>
                </div>

                <table class="wp-list-table widefat fixed striped" id="mcUnsyncedTable">
                    <thead>
                        <tr>
                            <th width="50">ID</th>
                            <th width="220">Outlet Name</th>
                            <th width="140">Retailer</th>
                            <th width="160">State</th>
                            <th>Address</th>
                            <th width="120">Sync Status</th>
                            <th width="140">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="mcUnsyncedTableBody">
                        <?php if (empty($missing_outlets)) : ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#16a34a;"><strong>🎉 Excellent! All outlets have Google storefront photos synced!</strong></td></tr>
                        <?php else : ?>
                            <?php foreach ($missing_outlets as $u) : ?>
                                <tr id="unsynced-row-<?php echo esc_attr($u['id']); ?>">
                                    <td>#<?php echo esc_html($u['id']); ?></td>
                                    <td><strong><?php echo esc_html($u['name']); ?></strong></td>
                                    <td><span class="mc-tag"><?php echo esc_html($u['retailer']); ?></span></td>
                                    <td><?php echo esc_html($u['state']); ?></td>
                                    <td><small><?php echo esc_html($u['address']); ?></small></td>
                                    <td><span class="status-badge warning" id="sync-status-<?php echo esc_attr($u['id']); ?>">No Photo</span></td>
                                    <td>
                                        <button type="button" class="button button-small" onclick="mcEditOutlet(<?php echo esc_attr($u['id']); ?>)">Edit Outlet</button>
                                        <button type="button" class="button button-small" onclick="mcRetrySingleSync(<?php echo esc_attr($u['id']); ?>)">Retry</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Include shared Edit/Add Outlet Modal -->
            <?php $this->render_outlet_modal(); ?>

        </div>
        <?php
    }

    /**
     * Page 2: Product Catalog & SKUs
     */
    public function render_products_page() {
        $products = MC_Outlet_DB::get_products();
        ?>
        <div class="wrap mc-admin-wrap">
            <div class="mc-admin-header">
                <div>
                    <h1 class="mc-admin-title">Mister Coffee Product Catalog & SKUs</h1>
                    <p class="mc-admin-sub">Add or edit coffee blends, drip brews, capsules, and beans available in your retail outlets.</p>
                </div>
                <button type="button" class="button button-primary mc-btn-primary" onclick="mcOpenProductModal()">
                    + Add New Product SKU
                </button>
            </div>

            <div class="mc-admin-card">
                <table class="wp-list-table widefat fixed striped mc-admin-table">
                    <thead>
                        <tr>
                            <th width="60">ID</th>
                            <th>Product Name / SKU</th>
                            <th width="240">Category</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p) : ?>
                            <tr>
                                <td>#<?php echo esc_html($p['id']); ?></td>
                                <td><strong><?php echo esc_html($p['name']); ?></strong></td>
                                <td><span class="mc-tag"><?php echo esc_html($p['category']); ?></span></td>
                                <td>
                                    <button type="button" class="button button-small button-link-delete" onclick="mcDeleteProduct(<?php echo esc_attr($p['id']); ?>, '<?php echo esc_js($p['name']); ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add Product Modal -->
            <div id="mcProductModal" class="mc-modal-backdrop" style="display:none;">
                <div class="mc-modal-dialog" style="max-width:500px;">
                    <div class="mc-modal-header">
                        <h2>Add New Product SKU</h2>
                        <button type="button" class="mc-modal-close" onclick="mcCloseProductModal()">&times;</button>
                    </div>
                    <form id="mcProductForm" onsubmit="mcSaveProductForm(event)">
                        <div class="mc-modal-body">
                            <div class="mc-form-row">
                                <label><strong>Product Name *</strong></label>
                                <input type="text" id="prod_name" name="name" required class="widefat" placeholder="e.g. Bean - Geisha Limited 500g" />
                            </div>
                            <div class="mc-form-row">
                                <label><strong>Category *</strong></label>
                                <select id="prod_category" name="category" class="widefat" required>
                                    <option value="Coffee Beans & Ground">Coffee Beans & Ground</option>
                                    <option value="Bagbrew (BG)">Bagbrew (BG)</option>
                                    <option value="Dripbrew (DB)">Dripbrew (DB)</option>
                                    <option value="Kopi O">Kopi O</option>
                                    <option value="Ncap Pods (Nespresso Compatible)">Ncap Pods (Nespresso Compatible)</option>
                                    <option value="Esebrew">Esebrew</option>
                                    <option value="Podbrew">Podbrew</option>
                                    <option value="Instant Beverages">Instant Beverages</option>
                                    <option value="Latte & Tea">Latte & Tea</option>
                                    <option value="Coffee Machines">Coffee Machines</option>
                                    <option value="Food Service">Food Service</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="mc-modal-footer">
                            <button type="button" class="button" onclick="mcCloseProductModal()">Cancel</button>
                            <button type="submit" class="button button-primary mc-btn-primary">Add Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Page 3: Settings & Data Sync
     */
    public function render_settings_page() {
        if (isset($_POST['mc_save_settings']) && check_admin_referer('mc_settings_verify')) {
            update_option('mc_google_maps_api_key', sanitize_text_field($_POST['mc_google_maps_api_key'] ?? ''));
            update_option('mc_brand_color', sanitize_hex_color($_POST['mc_brand_color'] ?? '#BC1419'));
            update_option('mc_default_zoom', intval($_POST['mc_default_zoom'] ?? 6));
            update_option('mc_github_repo', sanitize_text_field($_POST['mc_github_repo'] ?? ''));
            update_option('mc_github_token', sanitize_text_field($_POST['mc_github_token'] ?? ''));
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
        }

        $api_key = get_option('mc_google_maps_api_key', '');
        $brand_color = get_option('mc_brand_color', '#BC1419');
        $zoom = get_option('mc_default_zoom', 6);
        $github_repo = get_option('mc_github_repo', 'lynesslim/mister-coffee-outlet-finder');
        $github_token = get_option('mc_github_token', '');
        ?>
        <div class="wrap mc-admin-wrap">
            <h1 class="mc-admin-title">Mister Coffee Outlet Finder Settings</h1>
            
            <div class="mc-admin-card" style="max-width:760px; margin-top:20px;">
                <form method="post">
                    <?php wp_nonce_field('mc_settings_verify'); ?>
                    
                    <h2>Display Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="mc_brand_color">Brand Accent Color</label></th>
                            <td>
                                <input name="mc_brand_color" type="color" id="mc_brand_color" value="<?php echo esc_attr($brand_color); ?>" />
                                <span class="description">Default: <code>#BC1419</code> (Mister Coffee Signature Red)</span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mc_default_zoom">Initial Map Zoom Level</label></th>
                            <td>
                                <input name="mc_default_zoom" type="number" min="4" max="15" id="mc_default_zoom" value="<?php echo esc_attr($zoom); ?>" class="small-text" />
                                <span class="description">Default: <code>6</code> (Zoomed out to show whole country: Malaysia & Singapore)</span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mc_google_maps_api_key">Google Places & Maps API Key</label></th>
                            <td>
                                <input name="mc_google_maps_api_key" type="text" id="mc_google_maps_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" placeholder="AIzaSy..." />
                                <p class="description">Used for <strong>"📍 Fetch Photo from Google Maps"</strong> auto-pulling and Street View photos.</p>
                                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:12px; margin-top:8px; font-size:12px; line-height:1.6; max-width:600px;">
                                    <strong>📖 How to get your Google Places API Key:</strong>
                                    <ol style="margin:6px 0 0 16px; padding:0;">
                                        <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> and create or select a project.</li>
                                        <li>Go to <strong>APIs & Services &gt; Library</strong>.</li>
                                        <li>Search and enable <strong>Places API (New)</strong> and <strong>Street View Static API</strong>.</li>
                                        <li>Go to <strong>APIs & Services &gt; Credentials</strong> and click <strong>Create Credentials &gt; API Key</strong>.</li>
                                        <li>Copy your API key, paste it here, and click <strong>Save Settings</strong>.</li>
                                    </ol>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <h2 style="margin-top:24px;">GitHub Auto-Update Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="mc_github_repo">GitHub Repository</label></th>
                            <td>
                                <input name="mc_github_repo" type="text" id="mc_github_repo" value="<?php echo esc_attr($github_repo); ?>" class="regular-text" placeholder="username/repository-name" />
                                <p class="description">Format: <code>username/repository-name</code> (e.g. <code>mistercoffee/mister-coffee-outlet-finder</code>)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mc_github_token">GitHub Personal Access Token (Optional)</label></th>
                            <td>
                                <input name="mc_github_token" type="password" id="mc_github_token" value="<?php echo esc_attr($github_token); ?>" class="regular-text" />
                                <p class="description">Only required if your GitHub repository is <strong>private</strong>. For public repositories, leave this blank.</p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="mc_save_settings" class="button button-primary mc-btn-primary">Save Settings</button>
                    </p>
                </form>
            </div>

            <!-- Manual GitHub Update Check Box -->
            <div class="mc-admin-card" style="max-width:760px; margin-top:24px; border-left:4px solid #2563eb;">
                <h2>Check for Plugin Updates from GitHub</h2>
                <p>Installed Version: <strong>v<?php echo esc_html(MC_OUTLET_VERSION); ?></strong></p>
                <div style="margin: 12px 0;">
                    <button type="button" class="button" id="mcCheckUpdatesBtn" onclick="mcCheckGithubUpdates()">
                        🔍 Check GitHub for Updates Now
                    </button>
                </div>
                <div id="mcUpdateCheckResult" style="display:none; margin-top:14px; padding:12px 16px; border-radius:6px;"></div>
            </div>

            <!-- Re-Seed / Restore Data Tool -->
            <div class="mc-admin-card" style="max-width:760px; margin-top:24px; border-left:4px solid #BC1419;">
                <h2>Restore / Re-Import All 170 Outlets from Seed File</h2>
                <p>If you ever need to reset or re-sync all 170 outlets, coordinates, and product catalogs from the compiled Excel database, click the button below.</p>
                <button type="button" class="button button-secondary" onclick="mcReseedData()">
                    🔄 Restore & Re-Sync 170 Outlets Database
                </button>
            </div>

            <!-- Shortcode Usage Guide -->
            <div class="mc-admin-card" style="max-width:760px; margin-top:24px;">
                <h2>Shortcode Integration</h2>
                <p>To display the Outlet & Product Finder anywhere on your site (Page, Post, or Elementor Shortcode widget), simply paste:</p>
                <div style="background:#f3f4f6; padding:12px 18px; border-radius:6px; font-family:monospace; font-size:16px; font-weight:bold; color:#BC1419; margin:10px 0;">
                    [mister_coffee_outlets]
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX Handlers
     */
    public function ajax_save_outlet() {
        check_ajax_referer('mc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $data = [
            'id' => !empty($_POST['id']) ? (int) $_POST['id'] : null,
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'retailer' => sanitize_text_field($_POST['retailer'] ?? ''),
            'state' => sanitize_text_field($_POST['state'] ?? ''),
            'region' => sanitize_text_field($_POST['region'] ?? ''),
            'address' => sanitize_textarea_field($_POST['address'] ?? ''),
            'operating_hours' => sanitize_text_field($_POST['operating_hours'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'lat' => floatval($_POST['lat'] ?? 0),
            'lng' => floatval($_POST['lng'] ?? 0),
            'maps_url' => esc_url_raw($_POST['maps_url'] ?? ''),
            'photo_url' => esc_url_raw($_POST['photo_url'] ?? ''),
            'grinder' => !empty($_POST['grinder']) ? 1 : 0,
            'products' => isset($_POST['products']) && is_array($_POST['products']) ? array_map('sanitize_text_field', $_POST['products']) : []
        ];

        $id = MC_Outlet_DB::save_outlet($data);
        wp_send_json_success(['id' => $id, 'message' => 'Outlet saved successfully!']);
    }

    public function ajax_get_outlet() {
        check_ajax_referer('mc_admin_nonce', 'nonce');
        $id = (int) ($_GET['id'] ?? 0);
        $outlet = MC_Outlet_DB::get_outlet($id);
        if ($outlet) {
            wp_send_json_success($outlet);
        } else {
            wp_send_json_error('Outlet not found');
        }
    }

    public function ajax_delete_outlet() {
        check_ajax_referer('mc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        $id = (int) ($_POST['id'] ?? 0);
        MC_Outlet_DB::delete_outlet($id);
        wp_send_json_success(['message' => 'Outlet deleted successfully!']);
    }

    public function ajax_save_product() {
        check_ajax_referer('mc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? 'Other')
        ];
        $id = MC_Outlet_DB::save_product($data);
        wp_send_json_success(['id' => $id, 'message' => 'Product SKU added!']);
    }

    public function ajax_delete_product() {
        check_ajax_referer('mc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        $id = (int) ($_POST['id'] ?? 0);
        MC_Outlet_DB::delete_product($id);
        wp_send_json_success(['message' => 'Product SKU deleted!']);
    }

    public function ajax_reseed_data() {
        check_ajax_referer('mc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        MC_Outlet_DB::seed_data_if_empty(true);
        wp_send_json_success(['message' => 'Database successfully restored with all 170 outlets!']);
    }

    public function ajax_fetch_google_photo() {
        check_ajax_referer('mc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $api_key = get_option('mc_google_maps_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error('Google Places API key is missing. Please enter your API key in "Settings & Sync" first.');
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $address = sanitize_text_field($_POST['address'] ?? '');
        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);

        $outlet_id = (int) ($_POST['outlet_id'] ?? 0);

        if (empty($name)) {
            wp_send_json_error('Please enter an Outlet Name first.');
        }

        $search_query = trim($name . ' ' . $address);

        // -------------------------------------------------------------
        // 1. PRIMARY: Modern Google Places API (New) - places.googleapis.com
        // -------------------------------------------------------------
        $places_new_url = 'https://places.googleapis.com/v1/places:searchText';
        $payload = [
            'textQuery' => $search_query,
        ];
        if ($lat && $lng) {
            $payload['locationBias'] = [
                'circle' => [
                    'center' => [
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ],
                    'radius' => 2000.0,
                ],
            ];
        }

        $response_new = wp_remote_post($places_new_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $api_key,
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.photos',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (!is_wp_error($response_new)) {
            $code_new = wp_remote_retrieve_response_code($response_new);
            $body_new = json_decode(wp_remote_retrieve_body($response_new), true);

            if ($code_new === 200 && !empty($body_new['places'])) {
                foreach ($body_new['places'] as $place) {
                    if (!empty($place['photos'])) {
                        $photo_name = $place['photos'][0]['name']; // e.g. "places/ChIJ.../photos/..."
                        
                        // Query the photo media URL with skipHttpRedirect=true for direct CDN link
                        $media_query_url = 'https://places.googleapis.com/v1/' . $photo_name . '/media?maxWidthPx=1000&maxHeightPx=800&skipHttpRedirect=true&key=' . urlencode($api_key);
                        $media_resp = wp_remote_get($media_query_url, ['timeout' => 12]);

                        $photo_url = '';
                        if (!is_wp_error($media_resp)) {
                            $media_data = json_decode(wp_remote_retrieve_body($media_resp), true);
                            if (!empty($media_data['photoUri'])) {
                                $photo_url = $media_data['photoUri'];
                            }
                        }

                        // If skipHttpRedirect didn't return a URI, use the direct media endpoint
                        if (empty($photo_url)) {
                            $photo_url = 'https://places.googleapis.com/v1/' . $photo_name . '/media?maxWidthPx=1000&maxHeightPx=800&key=' . urlencode($api_key);
                        }

                        if ($outlet_id > 0) {
                            MC_Outlet_DB::update_outlet_photo($outlet_id, $photo_url);
                        }

                        wp_send_json_success([
                            'photo_url' => $photo_url,
                            'place_name' => $place['displayName']['text'] ?? $name,
                            'source' => 'Places API (New)',
                            'saved_to_db' => $outlet_id > 0,
                        ]);
                        return;
                    }
                }
            }
        }

        // -------------------------------------------------------------
        // 2. SECONDARY: Legacy Places API (Text Search) for older projects
        // -------------------------------------------------------------
        $legacy_text_url = add_query_arg([
            'query' => $search_query,
            'key' => $api_key,
        ], 'https://maps.googleapis.com/maps/api/place/textsearch/json');

        if ($lat && $lng) {
            $legacy_text_url .= '&location=' . $lat . ',' . $lng . '&radius=1000';
        }

        $response_legacy = wp_remote_get($legacy_text_url, ['timeout' => 12]);
        if (!is_wp_error($response_legacy)) {
            $data_legacy = json_decode(wp_remote_retrieve_body($response_legacy), true);
            if (!empty($data_legacy['results'])) {
                foreach ($data_legacy['results'] as $place) {
                    if (!empty($place['photos'])) {
                        $photo_ref = $place['photos'][0]['photo_reference'];
                        $photo_url = add_query_arg([
                            'maxwidth' => 1000,
                            'photo_reference' => $photo_ref,
                            'key' => $api_key,
                        ], 'https://maps.googleapis.com/maps/api/place/photo');

                        if ($outlet_id > 0) {
                            MC_Outlet_DB::update_outlet_photo($outlet_id, $photo_url);
                        }

                        wp_send_json_success([
                            'photo_url' => $photo_url,
                            'place_name' => $place['name'] ?? $name,
                            'source' => 'Legacy Places API',
                            'saved_to_db' => $outlet_id > 0,
                        ]);
                        return;
                    }
                }
            }
        }

        // -------------------------------------------------------------
        // 3. TERTIARY: Street View Static API if lat/lng are available
        // -------------------------------------------------------------
        if ($lat && $lng) {
            $street_view_url = add_query_arg([
                'size' => '800x450',
                'location' => $lat . ',' . $lng,
                'fov' => 90,
                'heading' => 0,
                'pitch' => 0,
                'key' => $api_key,
            ], 'https://maps.googleapis.com/maps/api/streetview');

            if ($outlet_id > 0) {
                MC_Outlet_DB::update_outlet_photo($outlet_id, $street_view_url);
            }

            wp_send_json_success([
                'photo_url' => $street_view_url,
                'is_street_view' => true,
                'message' => 'Street View photo generated!',
                'saved_to_db' => $outlet_id > 0,
            ]);
            return;
        }

        // If Places API (New) returned an error message, show it to help the user
        if (isset($body_new['error']['message'])) {
            wp_send_json_error('Google API Error: ' . $body_new['error']['message']);
        }

        wp_send_json_error('No photo found on Google Maps for "' . esc_html($name) . '". Try adding more address details.');
    }

    /**
     * AJAX: Get Outlets for Batch Sync & Unsynced identification
     */
    public function ajax_get_sync_outlets() {
        check_ajax_referer('mc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $scope = sanitize_text_field($_POST['scope'] ?? 'missing');
        $all_outlets = MC_Outlet_DB::get_all_outlets();

        $missing_outlets = [];
        $synced_outlets = [];

        foreach ($all_outlets as $o) {
            if (empty($o['photo_url'])) {
                $missing_outlets[] = $o;
            } else {
                $synced_outlets[] = $o;
            }
        }

        $target_outlets = ($scope === 'all') ? $all_outlets : $missing_outlets;

        wp_send_json_success([
            'outlets' => array_map(function($o) {
                return [
                    'id' => (int) $o['id'],
                    'name' => $o['name'],
                    'retailer' => $o['retailer'] ?? '',
                    'state' => $o['state'] ?? '',
                    'address' => $o['address'] ?? '',
                    'lat' => (float) ($o['latitude'] ?? 0),
                    'lng' => (float) ($o['longitude'] ?? 0),
                    'has_photo' => !empty($o['photo_url']),
                    'photo_url' => $o['photo_url'] ?? '',
                ];
            }, $target_outlets),
            'total_count' => count($all_outlets),
            'missing_count' => count($missing_outlets),
            'synced_count' => count($synced_outlets),
            'target_count' => count($target_outlets),
        ]);
    }
}
new MC_Outlet_Admin();

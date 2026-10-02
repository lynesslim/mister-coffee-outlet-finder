<?php
/**
 * Frontend Shortcode Handler: [mister_coffee_outlets]
 *
 * @package MisterCoffeeOutletFinder
 */

if (!defined('ABSPATH')) {
    exit;
}

class MC_Outlet_Shortcode {

    public function __construct() {
        add_shortcode('mister_coffee_outlets', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets() {
        // Montserrat font
        wp_register_style('mc-font-montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap', [], null);

        // Leaflet CSS & JS for high performance multi-marker map
        wp_register_style('mc-leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
        wp_register_script('mc-leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);

        // Frontend plugin styles & scripts
        wp_register_style('mc-outlet-frontend-css', MC_OUTLET_PLUGIN_URL . 'assets/css/frontend.css', ['mc-font-montserrat', 'mc-leaflet-css'], MC_OUTLET_VERSION);
        wp_register_script('mc-outlet-frontend-js', MC_OUTLET_PLUGIN_URL . 'assets/js/frontend.js', ['jquery', 'mc-leaflet-js'], MC_OUTLET_VERSION, true);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'state' => '',
            'retailer' => '',
            'height' => '720px'
        ], $atts, 'mister_coffee_outlets');

        // Enqueue assets
        wp_enqueue_style('mc-font-montserrat');
        wp_enqueue_style('mc-leaflet-css');
        wp_enqueue_script('mc-leaflet-js');
        wp_enqueue_style('mc-outlet-frontend-css');
        wp_enqueue_script('mc-outlet-frontend-js');

        // Fetch all outlets & products from database
        $all_db_outlets = MC_Outlet_DB::get_outlets();
        $products = MC_Outlet_DB::get_products();

        // Exclude outlets with 0 SKU stock from frontend finder
        $outlets = array_values(array_filter($all_db_outlets, function($o) {
            $prods = isset($o['products']) ? (is_array($o['products']) ? $o['products'] : json_decode($o['products'], true)) : [];
            return !empty($prods) && is_array($prods) && count($prods) > 0;
        }));

        $brand_color = get_option('mc_brand_color', '#BC1419');
        $default_zoom = intval(get_option('mc_default_zoom', 6));
        $api_key = get_option('mc_google_maps_api_key', '');

        // Pass data to frontend JS
        wp_localize_script('mc-outlet-frontend-js', 'mcFinderSettings', [
            'brandColor' => $brand_color,
            'defaultZoom' => $default_zoom,
            'googleApiKey' => $api_key,
            'outlets' => $outlets,
            'products' => $products
        ]);

        // Get unique states from outlets
        $states = [];
        foreach ($outlets as $o) {
            if (!empty($o['state']) && !in_array($o['state'], $states)) {
                $states[] = $o['state'];
            }
        }
        sort($states);

        ob_start();
        ?>
        <!-- MISTER COFFEE OUTLET FINDER WIDGET -->
        <div class="mc-finder-widget" style="--mc-brand-color: <?php echo esc_attr($brand_color); ?>; height: <?php echo esc_attr($atts['height']); ?>;">

            <!-- LEFT SIDEBAR -->
            <aside class="mc-widget-sidebar">

                <!-- VIEW 1: SEARCH & OUTLETS LIST -->
                <div class="mc-sidebar-list-view" id="mcSidebarListView">
                    
                    <div class="mc-sidebar-header">
                        <div class="mc-capsule-tag">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/></svg>
                            Retail Outlets & Showrooms
                        </div>
                        
                        <h2 class="mc-sidebar-title">Find An Outlet</h2>

                        <!-- Search Field & Near Me Button -->
                        <div class="mc-search-field-row">
                            <div class="mc-search-field-wrap">
                                <svg class="mc-search-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input 
                                    type="text" 
                                    id="mcLiveSearchInput" 
                                    class="mc-search-field" 
                                    placeholder="Search area, outlet, mall..." 
                                    oninput="mcHandleFilterInput()"
                                />
                            </div>
                            <button type="button" class="mc-near-me-btn" id="mcNearMeBtn" onclick="mcToggleNearMe()" title="Sort outlets by distance from your current location">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                                <span>Near Me</span>
                            </button>
                        </div>

                        <!-- Dropdowns -->
                        <div class="mc-filters-row">
                            <select id="mcStateSelect" class="mc-filter-select" onchange="mcHandleFilterInput()">
                                <option value="">ALL STATES</option>
                                <?php foreach ($states as $st) : ?>
                                    <option value="<?php echo esc_attr($st); ?>"><?php echo esc_html($st); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select id="mcProductSelect" class="mc-filter-select" onchange="mcHandleFilterInput()">
                                <option value="">ALL PRODUCTS</option>
                                <option value="grinder">☕ GRINDER AVAILABLE</option>
                                <option value="Beans">WHOLE BEANS</option>
                                <option value="Dripbrew">DRIPBREW</option>
                                <option value="Bagbrew">BAGBREW</option>
                                <option value="Ncap">NCAP CAPSULES</option>
                                <option value="Esebrew">ESEBREW</option>
                            </select>
                        </div>
                    </div>

                    <!-- Scrollable Outlets List -->
                    <div class="mc-sidebar-items-scroll" id="mcOutletCardsContainer">
                        <!-- Populated dynamically via JS -->
                    </div>

                    <!-- Footer Count -->
                    <div class="mc-sidebar-bottom-meta">
                        <span class="label">Outlets Available</span>
                        <span class="number" id="mcTotalOutletsBadge"><?php echo count($outlets); ?></span>
                    </div>

                </div>

                <!-- VIEW 2: OUTLET DETAIL (Slide-in) -->
                <div class="mc-sidebar-detail-view" id="mcSidebarDetailView">
                    
                    <div class="mc-detail-top-bar">
                        <button type="button" class="mc-back-btn" onclick="mcReturnToListView()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                            Back To All Outlets
                        </button>
                        <span class="mc-retailer-chip" id="mcDetailRetailerTag">Retailer</span>
                    </div>

                    <div class="mc-detail-scroll-area">
                        
                        <!-- Header with Montserrat Semibold & Watermark -->
                        <div class="mc-detail-head-box">
                            <span class="mc-watermark-num" id="mcDetailIndexNum">01</span>
                            <h3 class="mc-detail-outlet-title" id="mcDetailTitle">Outlet Name</h3>
                            <div class="mc-detail-tag-pill" id="mcDetailTagPill">Location Tag</div>
                        </div>

                        <!-- Store Image -->
                        <div class="mc-detail-photo-card">
                            <img id="mcDetailPhotoImg" src="" alt="Outlet Storefront" />
                            <div class="mc-detail-photo-bar">
                                <span>Official Retail Partner</span>
                                <span id="mcDetailGrinderBadge" style="color:#fef08a; display:none;">☕ In-Store Grinder</span>
                            </div>
                        </div>

                        <!-- Meta Info (No coordinates shown) -->
                        <div class="mc-detail-info-list">
                            <div class="mc-info-item">
                                <span class="mc-info-ico">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                </span>
                                <div>
                                    <span class="label">Address</span>
                                    <p class="val" id="mcDetailAddressTxt">—</p>
                                </div>
                            </div>

                            <div class="mc-info-item">
                                <span class="mc-info-ico">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </span>
                                <div>
                                    <span class="label">Operating Hours</span>
                                    <p class="val" id="mcDetailHoursTxt">Mon - Sun: 10:00 AM - 10:00 PM</p>
                                </div>
                            </div>

                            <div class="mc-info-item">
                                <span class="mc-info-ico">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                </span>
                                <div>
                                    <span class="label">Phone</span>
                                    <p class="val" id="mcDetailPhoneTxt">—</p>
                                </div>
                            </div>
                        </div>

                        <!-- In-Store Product Stock Section -->
                        <div class="mc-detail-stock-box">
                            <div class="mc-stock-header">
                                <span class="title">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/></svg>
                                    In-Store Product Stock
                                </span>
                                <span class="badge" id="mcDetailStockBadge">0 SKUs</span>
                            </div>
                            <div class="mc-stock-body" id="mcDetailStockItems">
                                <!-- Populated dynamically -->
                            </div>
                        </div>

                        <!-- CTAs -->
                        <div class="mc-detail-btn-row">
                            <a href="#" target="_blank" class="mc-btn-directions" id="mcDetailDirectionsLink">
                                Get Directions
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                            </a>
                            <a href="#" class="mc-btn-call" id="mcDetailCallLink">
                                Call Outlet
                            </a>
                        </div>

                    </div>

                </div>

            </aside>

            <!-- RIGHT PANEL: INTERACTIVE GOOGLE MAP -->
            <section class="mc-widget-map-panel">
                
                <div class="mc-map-top-bar">
                    <div class="mc-map-status">
                        <span class="pulse-dot"></span>
                        <span id="mcMapStatusLabel">Showing <?php echo count($outlets); ?> Outlets in Malaysia & Singapore</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div class="mc-map-theme-toggle">
                            <button type="button" class="mc-theme-btn active" id="mcThemeNightBtn" onclick="mcSetMapTheme('night')" title="Google Maps Night Theme">🌙 Night</button>
                            <button type="button" class="mc-theme-btn" id="mcThemeNormalBtn" onclick="mcSetMapTheme('normal')" title="Standard Google Maps">☀️ Normal</button>
                        </div>
                        <button type="button" class="mc-map-reset-btn" onclick="mcResetToCountryView()" title="Zoom out to whole country">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            Country View
                        </button>
                    </div>
                </div>

                <!-- Leaflet interactive multi-marker map -->
                <div id="mcInteractiveMap" style="width:100%; height:100%;"></div>

                <!-- Embedded Real Google Maps Iframe (Shown when single outlet is selected for street view / direct Google Map) -->
                <div id="mcGoogleEmbedContainer" class="mc-google-embed-wrap" style="display:none;">
                    <iframe id="mcGoogleIframe" src="" loading="lazy" allowfullscreen></iframe>
                </div>

            </section>

        </div>
        <?php
        return ob_get_clean();
    }
}
new MC_Outlet_Shortcode();

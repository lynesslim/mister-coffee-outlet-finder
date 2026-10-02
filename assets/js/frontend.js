/**
 * Mister Coffee Outlet Finder Frontend Script
 * Scoped and self-contained
 */

(function($) {
  'use strict';

  let map = null;
  let markersLayer = null;
  let userMarker = null;
  let allOutlets = [];
  let currentFiltered = [];
  let activeOutletId = null;
  let catalogProducts = [];
  let defaultZoom = 6.5;
  let countryCenter = [3.8, 102.0];
  let isNearMeActive = false;
  let userCoords = null;

  // Haversine Distance Formula (in KM)
  function getDistanceKm(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
  }

  function formatDistance(km) {
    if (km < 1) {
      return Math.round(km * 1000) + ' m';
    }
    return km.toFixed(1) + ' km';
  }

  // Curated Fallback Store Photos
  const DEFAULT_PHOTOS = [
    'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1521017432531-fbd92d768814?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1559925393-8be0ec4767c8?auto=format&fit=crop&w=800&q=80'
  ];

  function getPhotoForOutlet(outlet, idx) {
    if (outlet.photo_url && outlet.photo_url.trim() !== '') {
      return outlet.photo_url;
    }
    // If Google Maps API key provided, Street View static image can be used
    if (window.mcFinderSettings && mcFinderSettings.googleApiKey && outlet.lat && outlet.lng) {
      return `https://maps.googleapis.com/maps/api/streetview?size=800x400&location=${outlet.lat},${outlet.lng}&key=${mcFinderSettings.googleApiKey}`;
    }
    return DEFAULT_PHOTOS[idx % DEFAULT_PHOTOS.length];
  }

  // Switch Map Theme between Night and Normal Google Map
  window.mcSetMapTheme = function(theme) {
    const mapEl = $('#mcInteractiveMap');
    if (theme === 'night') {
      mapEl.addClass('mc-night-tiles');
      $('#mcThemeNightBtn').addClass('active');
      $('#mcThemeNormalBtn').removeClass('active');
    } else {
      mapEl.removeClass('mc-night-tiles');
      $('#mcThemeNormalBtn').addClass('active');
      $('#mcThemeNightBtn').removeClass('active');
    }
  };

  // Initialize Map with Country Overview
  function initMap() {
    const mapEl = document.getElementById('mcInteractiveMap');
    if (!mapEl || typeof L === 'undefined') return;

    if (window.mcFinderSettings && mcFinderSettings.defaultZoom) {
      defaultZoom = parseFloat(mcFinderSettings.defaultZoom);
    }

    // Zoomed out to show the whole country (Malaysia & Singapore)
    map = L.map('mcInteractiveMap', {
      zoomControl: true,
      attributionControl: false
    }).setView(countryCenter, defaultZoom);

    // Standard Google Maps Tile Layer (replaces Carto, no watermarks)
    L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
      maxZoom: 20,
      subdomains: ['0', '1', '2', '3']
    }).addTo(map);

    // Apply Night theme by default
    mcSetMapTheme('night');

    markersLayer = L.layerGroup().addTo(map);
    renderMapMarkers(allOutlets);
  }

  // Render Pins for all outlets
  function renderMapMarkers(outletsList) {
    if (!markersLayer) return;
    markersLayer.clearLayers();

    outletsList.forEach((outlet, i) => {
      if (!outlet.lat || !outlet.lng) return;

      const markerHtml = `
        <div class="mc-custom-pin ${outlet.id === activeOutletId ? 'active' : ''}" id="mc_pin_${outlet.id}">
          <div class="mc-pin-marker">
            <span class="mc-pin-cup">☕</span>
          </div>
        </div>
      `;

      const customIcon = L.divIcon({
        className: 'mc-leaflet-div-icon',
        html: markerHtml,
        iconSize: [30, 36],
        iconAnchor: [15, 36]
      });

      const marker = L.marker([outlet.lat, outlet.lng], { icon: customIcon });

      marker.on('click', function() {
        mcSelectOutlet(outlet.id, true);
      });

      markersLayer.addLayer(marker);
    });
  }

  // Render Outlet Cards in the Left Sidebar
  function renderOutletCards(list) {
    const container = document.getElementById('mcOutletCardsContainer');
    const badge = document.getElementById('mcTotalOutletsBadge');
    if (!container) return;

    if (badge) badge.innerText = list.length;

    if (list.length === 0) {
      container.innerHTML = `
        <div style="padding: 40px 24px; text-align: center; color: #9ca3af;">
          <div style="font-size: 32px; margin-bottom: 8px;">🔍</div>
          <p style="font-size: 13px; font-weight: 500;">No outlets match your filter.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = list.map((outlet, i) => {
      const idxStr = (i + 1 < 10 ? '0' : '') + (i + 1);
      const skuCount = Array.isArray(outlet.products) ? outlet.products.length : 0;
      const isActive = outlet.id === activeOutletId;
      const distanceBadge = outlet._distanceFormatted 
        ? `<span class="mc-pill distance">📍 ${outlet._distanceFormatted}</span>` 
        : '';

      return `
        <div class="mc-outlet-card-item ${isActive ? 'active' : ''}" 
             id="mc_card_${outlet.id}" 
             onclick="mcSelectOutlet(${outlet.id}, true)">
          
          <div class="mc-card-left">
            <span class="mc-card-index">${idxStr}</span>
            <div class="mc-card-texts">
              <h4 class="mc-card-title">${escapeHtml(outlet.name)}</h4>
              <span class="mc-card-sub">${escapeHtml(outlet.state || outlet.country)}</span>
              <div class="mc-card-pills">
                ${distanceBadge}
                <span class="mc-pill skus">${skuCount} SKUs Stocked</span>
                ${outlet.grinder ? '<span class="mc-pill grinder">☕ In-Store Grinder</span>' : ''}
              </div>
            </div>
          </div>

          <span class="mc-card-arrow">→</span>
        </div>
      `;
    }).join('');
  }

  // Toggle "Near Me" Geolocation
  window.mcToggleNearMe = function() {
    const btn = $('#mcNearMeBtn');

    if (isNearMeActive) {
      // Deactivate Near Me
      isNearMeActive = false;
      btn.removeClass('active loading').find('span').text('Near Me');
      
      // Remove distance data
      allOutlets.forEach(o => {
        delete o._distanceKm;
        delete o._distanceFormatted;
      });

      // Remove user location pin
      if (userMarker && map) {
        map.removeLayer(userMarker);
        userMarker = null;
      }

      mcHandleFilterInput();
      mcResetToCountryView();
      return;
    }

    if (!navigator.geolocation) {
      alert('Geolocation is not supported by your browser.');
      return;
    }

    btn.addClass('loading').find('span').text('Locating...');

    navigator.geolocation.getCurrentPosition(
      function(pos) {
        isNearMeActive = true;
        userCoords = [pos.coords.latitude, pos.coords.longitude];

        btn.removeClass('loading').addClass('active').find('span').text('Near Me (Active)');

        // Calculate distance to each outlet
        allOutlets.forEach(o => {
          if (o.lat && o.lng) {
            const dist = getDistanceKm(userCoords[0], userCoords[1], parseFloat(o.lat), parseFloat(o.lng));
            o._distanceKm = dist;
            o._distanceFormatted = formatDistance(dist);
          } else {
            o._distanceKm = 999999;
            o._distanceFormatted = '';
          }
        });

        // Add/Update User Beacon Pin on Leaflet Map
        if (map) {
          if (userMarker) {
            map.removeLayer(userMarker);
          }

          const beaconHtml = `
            <div class="mc-user-beacon" title="Your Current Location">
              <div class="mc-user-pulse"></div>
              <div class="mc-user-dot"></div>
            </div>
          `;

          const beaconIcon = L.divIcon({
            className: 'mc-user-icon-wrap',
            html: beaconHtml,
            iconSize: [22, 22],
            iconAnchor: [11, 11]
          });

          userMarker = L.marker(userCoords, { icon: beaconIcon }).addTo(map);
        }

        // Apply filters & sort by distance
        mcHandleFilterInput();

        // Fit map bounds to show user location and top 5 closest outlets
        if (map && currentFiltered.length > 0) {
          const closestPoints = currentFiltered.slice(0, 5).map(o => [o.lat, o.lng]);
          closestPoints.push(userCoords);
          const bounds = L.latLngBounds(closestPoints);
          map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
        }
      },
      function(err) {
        btn.removeClass('loading').find('span').text('Near Me');
        let msg = 'Could not retrieve your location.';
        if (err.code === 1) msg = 'Location access was denied. Please allow location permissions in your browser to sort by distance.';
        else if (err.code === 2) msg = 'Location information is currently unavailable.';
        else if (err.code === 3) msg = 'Location request timed out.';
        alert(msg);
      },
      { timeout: 10000, enableHighAccuracy: true }
    );
  };

  // Select Outlet: Zooms Map and Opens Detail View
  window.mcSelectOutlet = function(outletId, animateMap) {
    const outlet = allOutlets.find(o => parseInt(o.id) === parseInt(outletId));
    if (!outlet) return;

    activeOutletId = outlet.id;

    // Highlight card
    $('.mc-outlet-card-item').removeClass('active');
    $(`#mc_card_${outlet.id}`).addClass('active');

    // Highlight pin
    $('.mc-custom-pin').removeClass('active');
    $(`#mc_pin_${outlet.id}`).addClass('active');

    // Smoothly fly map to outlet coordinates
    if (animateMap && map && outlet.lat && outlet.lng) {
      map.flyTo([outlet.lat, outlet.lng], 15, {
        duration: 1.2,
        easeLinearity: 0.25
      });
    }

    // Populate Detail View (NO coordinates shown as requested)
    const listIndex = allOutlets.indexOf(outlet) + 1;
    const idxStr = (listIndex < 10 ? '0' : '') + listIndex;

    $('#mcDetailIndexNum').text(idxStr);
    $('#mcDetailTitle').text(outlet.name);
    $('#mcDetailRetailerTag').text(outlet.retailer || 'Retailer');
    $('#mcDetailTagPill').text(outlet.state ? `${outlet.state}, ${outlet.country}` : outlet.country);
    $('#mcDetailAddressTxt').text(outlet.address || `${outlet.name}, ${outlet.state}`);
    $('#mcDetailHoursTxt').text(outlet.operating_hours || 'Mon - Sun: 10:00 AM - 10:00 PM');
    $('#mcDetailPhoneTxt').text(outlet.phone || '+60 12-345 6789');

    // Photo
    const photoUrl = getPhotoForOutlet(outlet, listIndex);
    $('#mcDetailPhotoImg').attr('src', photoUrl);

    // In-store Grinder badge
    if (outlet.grinder) {
      $('#mcDetailGrinderBadge').show();
    } else {
      $('#mcDetailGrinderBadge').hide();
    }

    // Directions Link
    const mapsLink = outlet.maps_url && outlet.maps_url !== '' 
      ? outlet.maps_url 
      : `https://www.google.com/maps/search/?api=1&query=${outlet.lat},${outlet.lng}`;
    $('#mcDetailDirectionsLink').attr('href', mapsLink);

    // Call Link
    const phoneClean = (outlet.phone || '').replace(/[^0-9+]/g, '');
    $('#mcDetailCallLink').attr('href', phoneClean ? `tel:${phoneClean}` : '#');

    // Categorized Product Stock Breakdown
    const prods = Array.isArray(outlet.products) ? outlet.products : [];
    $('#mcDetailStockBadge').text(`${prods.length} SKUs`);

    // Group stocked products by category
    const grouped = {};
    prods.forEach(prodName => {
      let cat = 'Other';
      if (prodName.startsWith('BG ')) cat = 'Bagbrew (BG)';
      else if (prodName.startsWith('DB ')) cat = 'Dripbrew (DB)';
      else if (prodName.includes('Kopi O') || prodName.includes('Coffee Bag')) cat = 'Kopi O';
      else if (prodName.startsWith('Ncap ')) cat = 'Ncap Pods';
      else if (prodName.startsWith('Esebrew ')) cat = 'Esebrew';
      else if (prodName.includes('Bean') || prodName.includes('Beans') || prodName.includes('Ground')) cat = 'Whole Beans & Ground';
      else if (prodName.startsWith('Instant ')) cat = 'Instant Beverages';
      else if (prodName.startsWith('Coffee Machine')) cat = 'Coffee Machines';

      if (!grouped[cat]) grouped[cat] = [];
      grouped[cat].push(prodName);
    });

    let stockHtml = '';
    for (const [catTitle, items] of Object.entries(grouped)) {
      stockHtml += `
        <div>
          <div class="mc-stock-cat-title">${escapeHtml(catTitle)} (${items.length})</div>
          <div class="mc-stock-chips">
            ${items.map(sku => `
              <div class="mc-stock-chip">
                <span class="chk">✓</span>
                <span>${escapeHtml(sku)}</span>
              </div>
            `).join('')}
          </div>
        </div>
      `;
    }
    $('#mcDetailStockItems').html(stockHtml || '<p style="font-size:12px; color:#888;">No product inventory listed for this outlet.</p>');

    // Status label
    $('#mcMapStatusLabel').text(`Selected: ${outlet.name}`);

    // Transition sidebar to Detail View: replace list view entirely
    $('#mcSidebarListView').addClass('hidden').hide();
    $('#mcSidebarDetailView').addClass('active').show();

    // Reset detail scroll position to top
    const detailScrollEl = document.querySelector('.mc-detail-scroll-area');
    if (detailScrollEl) {
      detailScrollEl.scrollTop = 0;
    }
  };

  // Back to All Outlets List View: restore list view entirely
  window.mcReturnToListView = function() {
    $('#mcSidebarDetailView').removeClass('active').hide();
    $('#mcSidebarListView').removeClass('hidden').show();
    activeOutletId = null;
    $('.mc-outlet-card-item').removeClass('active');
    $('.mc-custom-pin').removeClass('active');
    mcResetToCountryView();
  };

  // Reset to Country Overview Zoom
  window.mcResetToCountryView = function() {
    if (!map) return;
    if (currentFiltered.length > 0 && currentFiltered.length < allOutlets.length) {
      const bounds = L.latLngBounds(currentFiltered.map(o => [o.lat, o.lng]));
      map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
    } else {
      map.setView(countryCenter, defaultZoom);
    }
    $('#mcMapStatusLabel').text(`Showing ${currentFiltered.length} Outlets in Malaysia & Singapore`);
  };

  // Live Filter: Search & Dropdowns
  // CRITICAL FIX: Only filters in place, DOES NOT AUTO-OPEN OUTLET!
  window.mcHandleFilterInput = function() {
    const q = ($('#mcLiveSearchInput').val() || '').trim().toLowerCase();
    const stateVal = $('#mcStateSelect').val();
    const prodVal = $('#mcProductSelect').val();

    currentFiltered = allOutlets.filter(outlet => {
      // Search Query
      if (q) {
        const nameMatch = (outlet.name || '').toLowerCase().includes(q);
        const stateMatch = (outlet.state || '').toLowerCase().includes(q);
        const addressMatch = (outlet.address || '').toLowerCase().includes(q);
        const retailerMatch = (outlet.retailer || '').toLowerCase().includes(q);
        if (!nameMatch && !stateMatch && !addressMatch && !retailerMatch) return false;
      }

      // State Filter
      if (stateVal && outlet.state !== stateVal) return false;

      // Product / Grinder Filter
      if (prodVal === 'grinder' && !outlet.grinder) return false;
      if (prodVal && prodVal !== 'grinder') {
        const prods = Array.isArray(outlet.products) ? outlet.products : [];
        const hasProd = prods.some(p => p.toLowerCase().includes(prodVal.toLowerCase()));
        if (!hasProd) return false;
      }

      return true;
    });

    // If Near Me is active, sort by distance ascending (closest first)
    if (isNearMeActive) {
      currentFiltered.sort((a, b) => (a._distanceKm || 999999) - (b._distanceKm || 999999));
    }

    renderOutletCards(currentFiltered);
    renderMapMarkers(currentFiltered);

    // If active outlet is no longer in filtered list and detail view was open, return to list view
    if (activeOutletId && !currentFiltered.some(o => o.id === activeOutletId)) {
      mcReturnToListView();
    }
  };

  function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function(m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
  }

  // Bulletproof mouse wheel scroll trapping for sidebar containers
  function setupScrollTraps() {
    // 1. Trap scrolling on the outlets card list
    $('#mcOutletCardsContainer').on('wheel', function(e) {
      const el = this;
      const delta = e.originalEvent.deltaY;
      const up = delta < 0;
      const down = delta > 0;
      const atTop = el.scrollTop <= 0;
      const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 1;

      if ((up && atTop) || (down && atBottom)) {
        e.preventDefault();
      } else {
        el.scrollTop += delta;
        e.preventDefault();
        e.stopPropagation();
      }
    });

    // 2. Trap scrolling on the outlet detail view area & product stock list
    $('.mc-detail-scroll-area').on('wheel', function(e) {
      // Check if mouse is hovering over the inner product stock list
      const stockEl = $(e.target).closest('.mc-stock-body')[0];
      if (stockEl && stockEl.scrollHeight > stockEl.clientHeight) {
        const delta = e.originalEvent.deltaY;
        const up = delta < 0;
        const down = delta > 0;
        const atTop = stockEl.scrollTop <= 0;
        const atBottom = stockEl.scrollTop + stockEl.clientHeight >= stockEl.scrollHeight - 1;

        if ((up && atTop) || (down && atBottom)) {
          e.preventDefault();
        } else {
          stockEl.scrollTop += delta;
          e.preventDefault();
          e.stopPropagation();
        }
        return;
      }

      const el = this;
      const delta = e.originalEvent.deltaY;
      const up = delta < 0;
      const down = delta > 0;
      const atTop = el.scrollTop <= 0;
      const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 1;

      if ((up && atTop) || (down && atBottom)) {
        e.preventDefault();
      } else {
        el.scrollTop += delta;
        e.preventDefault();
        e.stopPropagation();
      }
    });

    // 3. Header & Meta area scroll fallback (scrolls the list)
    $('.mc-sidebar-header, .mc-sidebar-bottom-meta').on('wheel', function(e) {
      const listEl = document.getElementById('mcOutletCardsContainer');
      if (listEl) {
        listEl.scrollTop += e.originalEvent.deltaY;
        e.preventDefault();
        e.stopPropagation();
      }
    });
  }

  // Startup on DOM Ready
  $(document).ready(function() {
    if (window.mcFinderSettings) {
      // Filter out outlets with 0 SKU stock
      allOutlets = (window.mcFinderSettings.outlets || []).filter(o => {
        const prods = Array.isArray(o.products) ? o.products : [];
        return prods.length > 0;
      });
      catalogProducts = window.mcFinderSettings.products || [];
    }

    currentFiltered = [...allOutlets];

    // Update count badge with active stocked outlets
    $('#mcTotalOutletsBadge').text(allOutlets.length);

    initMap();
    renderOutletCards(allOutlets);
    setupScrollTraps();
  });

})(jQuery);

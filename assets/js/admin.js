/**
 * Admin JavaScript for Mister Coffee Outlet Finder
 */

(function($) {
  'use strict';

  // Open Modal for Add
  window.mcOpenOutletModal = function() {
    $('#mcModalTitle').text('Add New Outlet');
    $('#mcOutletForm')[0].reset();
    $('#outlet_id').val('');
    $('#mcProductSearch').val('');
    mcFilterChecklist('');
    $('input[name="products[]"]').prop('checked', false);
    $('#mcOutletModal').css('display', 'flex');
  };

  window.mcCloseOutletModal = function() {
    $('#mcOutletModal').hide();
  };

  // Open Modal for Edit
  window.mcEditOutlet = function(id) {
    $('#mcModalTitle').text('Edit Outlet #' + id);
    
    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'GET',
      data: {
        action: 'mc_get_outlet',
        id: id,
        nonce: mcAdminData.nonce
      },
      success: function(res) {
        if (res.success && res.data) {
          const o = res.data;
          $('#outlet_id').val(o.id);
          $('#outlet_name').val(o.name);
          $('#outlet_retailer').val(o.retailer);
          $('#outlet_state').val(o.state);
          $('#outlet_region').val(o.region);
          $('#outlet_address').val(o.address);
          $('#outlet_operating_hours').val(o.operating_hours);
          $('#outlet_phone').val(o.phone);
          $('#outlet_lat').val(o.lat);
          $('#outlet_lng').val(o.lng);
          $('#outlet_maps_url').val(o.maps_url);
          $('#outlet_photo_url').val(o.photo_url);
          $('#outlet_grinder').prop('checked', o.grinder);

          // Check products
          $('#mcProductSearch').val('');
          mcFilterChecklist('');
          $('input[name="products[]"]').prop('checked', false);
          if (Array.isArray(o.products)) {
            o.products.forEach(p => {
              $(`input[name="products[]"][value="${p}"]`).prop('checked', true);
            });
          }

          $('#mcOutletModal').css('display', 'flex');
        } else {
          alert('Could not load outlet details.');
        }
      },
      error: function() {
        alert('Server error loading outlet.');
      }
    });
  };

  // Save Outlet Form Submit
  window.mcSaveOutletForm = function(e) {
    e.preventDefault();
    const btn = $('#mcSaveBtn');
    btn.prop('disabled', true).text('Saving...');

    const formData = $('#mcOutletForm').serializeArray();
    formData.push({ name: 'action', value: 'mc_save_outlet' });
    formData.push({ name: 'nonce', value: mcAdminData.nonce });

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: formData,
      success: function(res) {
        btn.prop('disabled', false).text('Save Outlet');
        if (res.success) {
          alert(res.data.message || 'Saved successfully!');
          window.location.reload();
        } else {
          alert(res.data || 'Failed to save.');
        }
      },
      error: function() {
        btn.prop('disabled', false).text('Save Outlet');
        alert('Server error saving outlet.');
      }
    });
  };

  // Delete Outlet
  window.mcDeleteOutlet = function(id, name) {
    if (!confirm(`Are you sure you want to delete "${name}"?`)) return;

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: {
        action: 'mc_delete_outlet',
        id: id,
        nonce: mcAdminData.nonce
      },
      success: function(res) {
        if (res.success) {
          $(`tr[data-id="${id}"]`).fadeOut(300, function() { $(this).remove(); });
        } else {
          alert('Delete failed.');
        }
      }
    });
  };

  // Check / Uncheck All Products
  window.mcToggleAllCheckboxes = function(state) {
    $('input[name="products[]"]').prop('checked', state);
  };

  // Filter Checklist Products in Modal
  window.mcFilterChecklist = function(term) {
    const q = (term || '').toLowerCase().trim();
    $('.mc-product-check-item').each(function() {
      const text = $(this).text().toLowerCase();
      if (!q || text.includes(q)) {
        $(this).css('display', 'flex');
      } else {
        $(this).hide();
      }
    });

    // Also show/hide category section if all items hidden
    $('.mc-cat-section').each(function() {
      const hasVisible = $(this).find('.mc-product-check-item:visible').length > 0;
      if (!q || hasVisible) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });
  };

  // Filter Admin Table by Search and Photo Status
  let mcActivePhotoFilter = 'all';

  window.mcFilterPhotoStatus = function(filter) {
    mcActivePhotoFilter = filter;
    $('.mc-photo-filter-btn').removeClass('active');
    $(`.mc-photo-filter-btn[data-filter="${filter}"]`).addClass('active');
    mcFilterAdminTable();
  };

  window.mcFilterAdminTable = function() {
    const q = ($('#mcAdminSearch').val() || '').toLowerCase().trim();
    let visible = 0;

    $('#mcOutletsTable tbody tr').each(function() {
      const name = $(this).attr('data-name') || '';
      const state = $(this).attr('data-state') || '';
      const hasPhoto = $(this).attr('data-has-photo') === '1';

      const matchesSearch = !q || name.includes(q) || state.includes(q);
      let matchesFilter = true;
      if (mcActivePhotoFilter === 'synced') {
        matchesFilter = hasPhoto;
      } else if (mcActivePhotoFilter === 'missing') {
        matchesFilter = !hasPhoto;
      }

      if (matchesSearch && matchesFilter) {
        $(this).show();
        visible++;
      } else {
        $(this).hide();
      }
    });

    $('#mcTotalCount').text(visible);
  };

  // WP Media Upload Helper
  window.mcUploadMedia = function(targetInputId) {
    const customUploader = wp.media({
      title: 'Select Outlet Storefront Photo',
      button: { text: 'Use this photo' },
      multiple: false
    }).on('select', function() {
      const attachment = customUploader.state().get('selection').first().toJSON();
      $(`#${targetInputId}`).val(attachment.url);
    }).open();
  };

  // Product SKU Management
  window.mcOpenProductModal = function() {
    $('#mcProductForm')[0].reset();
    $('#mcProductModal').css('display', 'flex');
  };

  window.mcCloseProductModal = function() {
    $('#mcProductModal').hide();
  };

  window.mcSaveProductForm = function(e) {
    e.preventDefault();
    const data = $('#mcProductForm').serializeArray();
    data.push({ name: 'action', value: 'mc_save_product' });
    data.push({ name: 'nonce', value: mcAdminData.nonce });

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: data,
      success: function(res) {
        if (res.success) {
          alert('Product SKU added successfully!');
          window.location.reload();
        } else {
          alert('Failed to add product.');
        }
      }
    });
  };

  window.mcDeleteProduct = function(id, name) {
    if (!confirm(`Are you sure you want to delete SKU "${name}"?`)) return;

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: {
        action: 'mc_delete_product',
        id: id,
        nonce: mcAdminData.nonce
      },
      success: function(res) {
        if (res.success) {
          window.location.reload();
        } else {
          alert('Delete failed.');
        }
      }
    });
  };

  // Re-seed all 170 outlets
  window.mcReseedData = function() {
    if (!confirm('This will restore all 170 outlets and 102 products from the bundled Excel database. Continue?')) return;

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: {
        action: 'mc_reseed_data',
        nonce: mcAdminData.nonce
      },
      success: function(res) {
        if (res.success) {
          alert(res.data.message);
          window.location.reload();
        } else {
          alert('Re-seed failed.');
        }
      }
    });
  };

  // Check for updates from GitHub
  window.mcCheckGithubUpdates = function() {
    const btn = $('#mcCheckUpdatesBtn');
    const resultBox = $('#mcUpdateCheckResult');

    btn.prop('disabled', true).text('Checking GitHub...');
    resultBox.hide().empty();

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: {
        action: 'mc_check_github_update',
        nonce: mcAdminData.nonce
      },
      success: function(res) {
        btn.prop('disabled', false).text('🔍 Check GitHub for Updates Now');
        resultBox.show();

        if (res.success && res.data) {
          const d = res.data;
          if (d.has_update) {
            resultBox.css({ background: '#fef2f2', border: '1px solid #f87171', color: '#991b1b' });
            resultBox.html(`
              <h4 style="margin:0 0 6px; color:#991b1b; font-size:14px;">🎉 New Version Available: <strong>v${d.latest_version}</strong></h4>
              <p style="margin:0 0 8px; font-size:13px;">You are running <strong>v${d.current_version}</strong>. A newer release (${d.release_name}) was published on ${d.published_at}.</p>
              <div style="display:flex; gap:10px; align-items:center;">
                <a href="${d.release_url}" target="_blank" class="button button-primary">View Release on GitHub ↗</a>
                <span style="font-size:12px; color:#666;">Or update via standard WordPress <strong>Plugins</strong> screen.</span>
              </div>
            `);
          } else {
            resultBox.css({ background: '#f0fdf4', border: '1px solid #86efac', color: '#166534' });
            resultBox.html(`
              <h4 style="margin:0 0 4px; color:#166534; font-size:14px;">✅ Plugin is Up to Date!</h4>
              <p style="margin:0; font-size:13px;">Installed version <strong>v${d.current_version}</strong> matches the latest GitHub release.</p>
            `);
          }
        } else {
          resultBox.css({ background: '#fffbeb', border: '1px solid #fde68a', color: '#92400e' });
          resultBox.html(`<p style="margin:0;">${res.data || 'Could not check updates.'}</p>`);
        }
      },
      error: function() {
        btn.prop('disabled', false).text('🔍 Check GitHub for Updates Now');
        resultBox.show().css({ background: '#fef2f2', border: '1px solid #f87171', color: '#991b1b' });
        resultBox.html('<p style="margin:0;">Connection error while checking GitHub API.</p>');
      }
    });
  };

  // Fetch Storefront Photo from Google Places API
  window.mcFetchGooglePhoto = function() {
    const outletId = $('#outlet_id').val() || 0;
    const name = $('#outlet_name').val().trim();
    const address = $('#outlet_address').val().trim();
    const lat = $('#outlet_lat').val().trim();
    const lng = $('#outlet_lng').val().trim();
    const btn = $('#mcFetchGmapPhotoBtn');
    const status = $('#mcPhotoFetchStatus');

    if (!name) {
      alert('Please enter the Outlet Name first.');
      $('#outlet_name').focus();
      return;
    }

    btn.prop('disabled', true).text('⏳ Fetching...');
    status.show().html('<span style="color:#2563eb;">Searching Google Places (New)...</span>');

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: {
        action: 'mc_fetch_google_photo',
        nonce: mcAdminData.nonce,
        outlet_id: outletId,
        name: name,
        address: address,
        lat: lat,
        lng: lng
      },
      success: function(res) {
        btn.prop('disabled', false).html('📍 Fetch from Google Maps');
        if (res.success && res.data.photo_url) {
          $('#outlet_photo_url').val(res.data.photo_url);
          const label = res.data.is_street_view ? 'Street View photo generated!' : 'Photo found on Google Places!';
          status.html(`<span style="color:#16a34a; font-weight:600;">✅ ${label}</span>`);
        } else {
          status.html(`<span style="color:#dc2626;">❌ ${res.data || 'No photo found.'}</span>`);
        }
      },
      error: function() {
        btn.prop('disabled', false).html('📍 Fetch from Google Maps');
        status.html('<span style="color:#dc2626;">❌ Request failed. Check server connection.</span>');
      }
    });
  };

  // -------------------------------------------------------------
  // Bulk Google Photos Sync Logic
  // -------------------------------------------------------------
  const mcSyncState = {
    running: false,
    outlets: [],
    index: 0,
    synced: 0,
    failed: 0,
    delay: 600
  };

  function mcLogToTerminal(msg, type = 'info') {
    const term = $('#mcSyncTerminalLog');
    if (!term.length) return;
    const time = new Date().toLocaleTimeString();
    let color = '#93c5fd'; // blue
    if (type === 'success') color = '#4ade80'; // green
    if (type === 'warn') color = '#facc15'; // yellow
    if (type === 'error') color = '#f87171'; // red
    if (type === 'bold') color = '#ffffff';

    const line = `<div style="margin-bottom:3px;"><span style="color:#6b7280;">[${time}]</span> <span style="color:${color};">${msg}</span></div>`;
    term.append(line);
    term.scrollTop(term.prop('scrollHeight'));
  }

  window.mcStartBatchPhotoSync = function() {
    const scope = $('input[name="mcSyncScope"]:checked').val() || 'missing';
    const delay = parseInt($('#mcSyncDelay').val(), 10) || 600;

    $('#mcStartSyncBtn').hide();
    $('#mcStopSyncBtn').show();
    $('#mcSyncProgressCard').slideDown();

    mcSyncState.running = true;
    mcSyncState.delay = delay;
    mcSyncState.index = 0;
    mcSyncState.synced = 0;
    mcSyncState.failed = 0;
    mcSyncState.outlets = [];

    $('#mcLiveSyncedCount').text('0');
    $('#mcLiveFailedCount').text('0');
    $('#mcProgressPercentText').text('0%');
    $('#mcProgressBarFill').css('width', '0%');
    $('#mcProgressStatusTitle').text('Fetching outlets list...');
    $('#mcProgressSubtext').text('Querying database...');
    $('#mcSyncTerminalLog').empty();

    mcLogToTerminal(`Initializing batch synchronization (Scope: ${scope.toUpperCase()}, Delay: ${delay}ms)...`, 'bold');

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: {
        action: 'mc_get_sync_outlets',
        nonce: mcAdminData.nonce,
        scope: scope
      },
      success: function(res) {
        if (!res.success || !res.data.outlets || res.data.outlets.length === 0) {
          mcLogToTerminal('No outlets match the selected sync criteria, or all are already synced!', 'success');
          $('#mcProgressStatusTitle').text('Complete');
          $('#mcProgressSubtext').text('Nothing to sync.');
          $('#mcStartSyncBtn').show();
          $('#mcStopSyncBtn').hide();
          mcSyncState.running = false;
          return;
        }

        mcSyncState.outlets = res.data.outlets;
        mcLogToTerminal(`Found ${res.data.outlets.length} target outlets. Starting automated Google search...`, 'bold');
        $('#mcProgressStatusTitle').text(`Syncing ${res.data.outlets.length} outlets...`);
        
        mcRunSyncStep();
      },
      error: function(xhr, status, error) {
        let msg = 'Failed to retrieve outlets from server.';
        if (xhr.responseJSON && xhr.responseJSON.data) {
          msg += ' Reason: ' + xhr.responseJSON.data;
        } else if (xhr.responseText) {
          msg += ' Status: ' + xhr.status + ' (' + error + ')';
        }
        mcLogToTerminal(msg, 'error');
        mcStopBatchPhotoSync();
      }
    });
  };

  function mcRunSyncStep() {
    if (!mcSyncState.running) {
      mcLogToTerminal('Synchronization stopped by user.', 'warn');
      $('#mcStartSyncBtn').show();
      $('#mcStopSyncBtn').hide();
      $('#mcProgressStatusTitle').text('Paused');
      return;
    }

    if (mcSyncState.index >= mcSyncState.outlets.length) {
      // Completed all
      mcSyncState.running = false;
      $('#mcStartSyncBtn').show();
      $('#mcStopSyncBtn').hide();
      $('#mcProgressBarFill').css('width', '100%');
      $('#mcProgressPercentText').text('100%');
      $('#mcProgressStatusTitle').text('🎉 Synchronization Complete!');
      $('#mcProgressSubtext').text(`Finished processing ${mcSyncState.outlets.length} outlets. Newly Synced: ${mcSyncState.synced}, Not Found/Failed: ${mcSyncState.failed}`);
      mcLogToTerminal(`========================================`, 'bold');
      mcLogToTerminal(`Batch sync finished! Total: ${mcSyncState.outlets.length} | Synced: ${mcSyncState.synced} | No Photo: ${mcSyncState.failed}`, 'success');
      
      // Update Unsynced Table & Metric cards
      mcRefreshUnsyncedTable();
      return;
    }

    const outlet = mcSyncState.outlets[mcSyncState.index];
    const currentNum = mcSyncState.index + 1;
    const total = mcSyncState.outlets.length;
    const pct = Math.round((currentNum / total) * 100);

    $('#mcProgressBarFill').css('width', `${pct}%`);
    $('#mcProgressPercentText').text(`${pct}%`);
    $('#mcProgressSubtext').text(`Processing (${currentNum}/${total}): ${outlet.name}`);

    mcLogToTerminal(`[${currentNum}/${total}] Searching Google Places for "${outlet.name}"...`, 'info');

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: {
        action: 'mc_fetch_google_photo',
        nonce: mcAdminData.nonce,
        outlet_id: outlet.id,
        name: outlet.name,
        address: outlet.address,
        lat: outlet.lat,
        lng: outlet.lng
      },
      success: function(res) {
        if (res.success && res.data.photo_url) {
          mcSyncState.synced++;
          $('#mcLiveSyncedCount').text(mcSyncState.synced);
          mcLogToTerminal(`✅ [#${outlet.id}] Photo saved for "${outlet.name}" (${res.data.source || 'Places API'})`, 'success');
          
          // Animate and remove from Unsynced table if present
          const statusBadge = $(`#sync-status-${outlet.id}`);
          if (statusBadge.length) {
            statusBadge.removeClass('warning error').addClass('active').text('Synced');
            setTimeout(function() {
              $(`#unsynced-row-${outlet.id}`).fadeOut(400, function() {
                $(this).remove();
                const currentUnsynced = parseInt($('#mcUnsyncedTableCount').text(), 10) || 1;
                const newCount = Math.max(0, currentUnsynced - 1);
                $('#mcUnsyncedTableCount').text(newCount);
                $('#mcMissingCountMetric').text(newCount);
              });
            }, 500);
          }
        } else {
          mcSyncState.failed++;
          $('#mcLiveFailedCount').text(mcSyncState.failed);
          const errMsg = res.data || 'No photo found';
          mcLogToTerminal(`⚠️ [#${outlet.id}] No photo for "${outlet.name}": ${errMsg}`, 'warn');
          
          const statusBadge = $(`#sync-status-${outlet.id}`);
          if (statusBadge.length) {
            statusBadge.removeClass('warning active').addClass('error').text('No Photo');
          }
        }
      },
      error: function() {
        mcSyncState.failed++;
        $('#mcLiveFailedCount').text(mcSyncState.failed);
        mcLogToTerminal(`❌ [#${outlet.id}] Connection failed for "${outlet.name}"`, 'error');
      },
      complete: function() {
        mcSyncState.index++;
        if (mcSyncState.running) {
          setTimeout(mcRunSyncStep, mcSyncState.delay);
        }
      }
    });
  }

  window.mcStopBatchPhotoSync = function() {
    mcSyncState.running = false;
    $('#mcStartSyncBtn').show();
    $('#mcStopSyncBtn').hide();
    mcLogToTerminal('Pause requested... Finishing active request.', 'warn');
  };

  // Single Retry from Unsynced Table
  window.mcRetrySingleSync = function(outletId) {
    const row = $(`#unsynced-row-${outletId}`);
    const statusBadge = $(`#sync-status-${outletId}`);
    if (!row.length) return;

    statusBadge.removeClass('error active warning').addClass('warning').text('Searching...');

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: {
        action: 'mc_get_outlet',
        nonce: mcAdminData.nonce,
        id: outletId
      },
      success: function(getRes) {
        if (!getRes.success || !getRes.data) {
          statusBadge.removeClass('warning active').addClass('error').text('Outlet Error');
          return;
        }
        const o = getRes.data;
        $.ajax({
          url: mcAdminData.ajax_url,
          type: 'POST',
          data: {
            action: 'mc_fetch_google_photo',
            nonce: mcAdminData.nonce,
            outlet_id: o.id,
            name: o.name,
            address: o.address,
            lat: o.latitude,
            lng: o.longitude
          },
          success: function(photoRes) {
            if (photoRes.success && photoRes.data.photo_url) {
              statusBadge.removeClass('warning error').addClass('active').text('Synced ✅');
              setTimeout(function() {
                row.fadeOut(400, function() {
                  row.remove();
                  const currentUnsynced = parseInt($('#mcUnsyncedTableCount').text(), 10) || 1;
                  const newCount = Math.max(0, currentUnsynced - 1);
                  $('#mcUnsyncedTableCount').text(newCount);
                  $('#mcMissingCountMetric').text(newCount);
                });
              }, 600);
            } else {
              statusBadge.removeClass('warning active').addClass('error').text('Not Found');
              alert(photoRes.data || 'No photo found for this outlet on Google Maps.');
            }
          },
          error: function() {
            statusBadge.removeClass('warning active').addClass('error').text('Failed');
          }
        });
      }
    });
  };

  // Refresh Unsynced Table dynamically
  window.mcRefreshUnsyncedTable = function() {
    const tbody = $('#mcUnsyncedTableBody');
    tbody.html('<tr><td colspan="7" style="text-align:center; padding:20px;">Refreshing unsynced outlets...</td></tr>');

    $.ajax({
      url: mcAdminData.ajax_url,
      type: 'POST',
      data: {
        action: 'mc_get_sync_outlets',
        nonce: mcAdminData.nonce,
        scope: 'missing'
      },
      success: function(res) {
        if (res.success) {
          $('#mcUnsyncedTableCount').text(res.data.missing_count);
          $('#mcMissingCountMetric').text(res.data.missing_count);
          $('#mcSyncedCountMetric').text(res.data.synced_count);
          if (res.data.total_count > 0) {
            const pct = Math.round((res.data.synced_count / res.data.total_count) * 100);
            $('#mcSyncedPercentMetric').text(`${pct}% Coverage`);
          }

          if (!res.data.outlets || res.data.outlets.length === 0) {
            tbody.html('<tr><td colspan="7" style="text-align:center; padding:30px; color:#16a34a;"><strong>🎉 Excellent! All outlets have Google storefront photos synced!</strong></td></tr>');
            return;
          }

          let html = '';
          res.data.outlets.forEach(function(u) {
            html += `<tr id="unsynced-row-${u.id}">
              <td>#${u.id}</td>
              <td><strong>${u.name}</strong></td>
              <td><span class="mc-tag">${u.retailer}</span></td>
              <td>${u.state}</td>
              <td><small>${u.address}</small></td>
              <td><span class="status-badge warning" id="sync-status-${u.id}">No Photo</span></td>
              <td>
                <button type="button" class="button button-small" onclick="mcEditOutlet(${u.id})">Edit Outlet</button>
                <button type="button" class="button button-small" onclick="mcRetrySingleSync(${u.id})">Retry</button>
              </td>
            </tr>`;
          });
          tbody.html(html);
        }
      }
    });
  };

})(jQuery);

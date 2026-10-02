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

  // Filter Admin Table
  window.mcFilterAdminTable = function() {
    const q = ($('#mcAdminSearch').val() || '').toLowerCase().trim();
    let visible = 0;

    $('#mcOutletsTable tbody tr').each(function() {
      const name = $(this).attr('data-name') || '';
      const state = $(this).attr('data-state') || '';
      if (!q || name.includes(q) || state.includes(q)) {
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

})(jQuery);

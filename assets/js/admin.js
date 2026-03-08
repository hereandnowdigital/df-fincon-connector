jQuery(document).ready(function($) {
  const $settingsForm = $('#df-fincon-settings-form');
  const $testButton = $('#df-fincon-test-connection');
  const $statusDiv = $('#df-fincon-test-status');
  const $apiTabContent = $('#tab-api');

  // Tab Switching Logic

  function switchTab(target) {
      // Hide all tab content
      $('.tab-content').hide();
      // Remove active class from all tabs
      $('.nav-tab').removeClass('nav-tab-active');

      // Show the target content and set the active tab
      $('#tab-' + target).show();
      $(`.nav-tab[data-tab="${target}"]`).addClass('nav-tab-active');

      // Update URL hash
      window.history.replaceState(null, null, location.pathname + location.search + '#' + target);
      
      // Update Test Connection button state
      checkConnectionButtonState();
  }

  $('.nav-tab-wrapper a').on('click', function(e) {
      e.preventDefault();
      const target = $(this).data('tab');
      switchTab(target);
  });

  // Determine default tab - use first tab if 'api' tab doesn't exist
  let defaultTab = 'api';
  const $firstTab = $('.nav-tab-wrapper a').first();
  if ($firstTab.length && $('#tab-api').length === 0) {
    defaultTab = $firstTab.data('tab') || 'product';
  }
  const initialTab = window.location.hash.substring(1) || defaultTab;
  switchTab(initialTab);


   //Test Connection Button Logic

    function isApiFieldsPopulated() {
        // Critical fields needed for a successful login request
        const url = $('#server_url').val();
        const username = $('#username').val();
        const password = $('#password').val();
        
        return url && username && password;
    }

    function checkConnectionButtonState() {
      if ($testButton.length === 0) return;
      
      const isApiTab = ($apiTabContent.css('display') !== 'none');

      if (isApiTab && isApiFieldsPopulated()) {
          $testButton.prop('disabled', false).removeClass('df-fincon-disabled');
      } else {
          $testButton.prop('disabled', true).addClass('df-fincon-disabled');
      }
    }

    $apiTabContent.find('input').on('change keyup', checkConnectionButtonState);
    checkConnectionButtonState();
    $testButton.on('click', function(e) {
      e.preventDefault();
      
      $statusDiv.html('<span style="color:#0073AA;">... Testing connection ...</span>').removeClass('success error');
      $testButton.prop('disabled', true);

      const data = {
        action: 'df_fincon_test_connection',
        nonce: DF_FINCON_ADMIN.nonces.test,
        server_url: $('#server_url').val(),
        server_port: $('#server_port').val(),
        username: $('#username').val(),
        password: $('#password').val(),
        data_id: $('#data_id').val(),
        use_alt_extension: $('#use_alt_extension').is(':checked') ? 1 : 0,
      };

    $.post(DF_FINCON_ADMIN.ajax_url, data)
      .done(function(response) {
        if (response.success) {
            $statusDiv.html('<span style="color:#22A300;">Connection successful!');
        } else {
            const errorMessage = response.data.message || response.data || 'Unknown API Error.';
            $statusDiv.html('<span style="color:#B32D2E;">Connection test failed: ' + errorMessage + '</span>');
        }
      })
      .fail(function(xhr) {
        $statusDiv.html('<span style="color:#B32D2E;">Connection test failed: </span>');
      })
      .always(function() {
        checkConnectionButtonState();
      });
    });

    $('#df-fincon-import-btn').on('click', function(e) {
      e.preventDefault();
      var $button = $(this);
      var originalText = $button.text();
      var $feedback = $('#df-fincon-import-feedback');
      var productItemCode = $('#df_fincon_import_product_item_code').val();
      var count = $('#df_fincon_import_count').val();

      $feedback.removeClass('success error').html('<p>' + DF_FINCON_ADMIN.messages.importing + '...</p>');
      $button.prop('disabled', true).text(DF_FINCON_ADMIN.messages.importing);

      $.post(DF_FINCON_ADMIN.ajax_url, {
        action: 'df_fincon_manual_import_products',
        nonce: DF_FINCON_ADMIN.nonces.import,
        count: count,
        product_item_code: productItemCode,
      })
      .done(function(response) {
        if (response.success) {

          const data = response.data.details;
              
          // --- Formatting Logic Starts Here ---
          let html = '<div class="notice notice-success is-dismissible"><p><strong>✅ ' + response.data.message + '</strong></p></div>';

          html += '<table class="wp-list-table widefat fixed striped">';
          html += '<thead><tr><th colspan="2">Import Summary</th></tr></thead>';
          html += '<tbody>';
          
          html += '<tr><th>Total Fetched from API</th><td>' + data.total_fetched + '</td></tr>';
          html += '<tr><th>Successfully Imported (Placeholder)</th><td>' + data.imported_count + '</td></tr>';
          html += '<tr><th>Skipped/Failed (Placeholder)</th><td>' + data.skipped_count + '</td></tr>';
          html += '<tr><th>Next Recommended Offset (RecNo)</th><td><strong>' + data.next_rec_no + '</strong></td></tr>';
          
          html += '</tbody></table>';

          if (data.errors && data.errors.length > 0) {
            html += '<div class="notice notice-error"><p><strong>❌ Errors Encountered:</strong></p><ul>';
            data.errors.forEach(function(error) {
                html += '<li>' + error + '</li>';
            });
            html += '</ul></div>';
          } else {
            html += '<div class="notice notice-info is-dismissible"><p>No specific errors logged during this test run.</p></div>';
          }
          
          // Optional: Show raw response for debugging
          html += '<h3>Raw API Summary:</h3><pre>' + JSON.stringify(data.raw_response_summary, null, 2) + '</pre>';
          
          $feedback.addClass('success').html(html);


          // Display success message and details (e.g., list of imported product IDs)
          $feedback.addClass('success').html('<p>✅ ' + response.data.message + '</p><pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>');
        } else {
          // Display failure message and error details
          var errorMessage = response.data.message || DF_FINCON_ADMIN.messages.import_failed;
          var errorDetails = JSON.stringify(response.data.details, null, 2) || 'No further details provided.';
          $feedback.addClass('error').html('<p>❌ ' + errorMessage + '</p><pre>' + errorDetails + '</pre>');
        }
      })
      .fail(function(xhr, status, error) {
        $feedback.addClass('error').html('<p>❌ ' + DF_FINCON_ADMIN.messages.network_error + ' (HTTP Status: ' + xhr.status + ')</p>');
      })
      .always(function() {
        $button.prop('disabled', false).text(originalText);
      });
  });

  $('#df-fincon-customer-import-btn').on('click', function(e) {
    e.preventDefault();
    var $button = $(this);
    if ($button.length === 0) return;

    var originalText = $button.text();
    var $feedback = $('#df-fincon-customer-import-feedback');
    var accno = $('#df_fincon_customer_accno').val();
    var count = $('#df_fincon_customer_count').val();
    var offset = $('#df_fincon_customer_offset').val();
    var onlyChanged = $('#df_fincon_customer_only_changed').is(':checked') ? 1 : 0;
    var webListOnly = $('#df_fincon_customer_weblist_only').is(':checked') ? 1 : 0;

    $feedback.removeClass('success error').html('<p>' + (DF_FINCON_ADMIN.messages.customer_importing || 'Importing customers') + '...</p>');
    $button.prop('disabled', true).text(DF_FINCON_ADMIN.messages.customer_importing || originalText);

    // Debug logging
    console.log('Manual customer import parameters:', {
      customer_accno: accno,
      count: count,
      offset: offset,
      only_changed: onlyChanged,
      webListOnly: webListOnly
    });

    $.post(DF_FINCON_ADMIN.ajax_url, {
      action: 'df_fincon_manual_import_customers',
      nonce: DF_FINCON_ADMIN.nonces.import,
      customer_accno: accno,
      count: count,
      offset: offset,
      only_changed: onlyChanged,
      webListOnly: webListOnly
    })
    .done(function(response) {
      console.log('AJAX response received:', response);
      
      if (response.success) {
        var html = '<div class="notice notice-success is-dismissible"><p><strong>' + response.data.message + '</strong></p></div>';
        // Check if we have data to display
        console.log('Response data structure:', response.data);
        if (response.data.data) {
          html += '<pre>' + JSON.stringify(response.data.data, null, 2) + '</pre>';
        } else {
          console.log('No data.data property in response');
        }
        $feedback.addClass('success').html(html);
      } else {
        var errorMessage = response.data.message || DF_FINCON_ADMIN.messages.generic_error;
        $feedback.addClass('error').html('<p>' + errorMessage + '</p>');
      }
    })
    .fail(function(xhr) {
      $feedback.addClass('error').html('<p>' + DF_FINCON_ADMIN.messages.network_error + ' (HTTP Status: ' + xhr.status + ')</p>');
    })
    .always(function() {
      $button.prop('disabled', false).text(originalText);
    });
  });

  // User profile sync button
  $(document).on('click', '#df-fincon-sync-user-btn:not(:disabled)', function(e) {
    e.preventDefault();
    const $button = $(this);
    const originalText = $button.text();
    const $feedback = $('#df-fincon-sync-user-feedback');
    const userId = $button.data('user-id');
    const nonce = $button.data('nonce');

    $feedback.removeClass('success error').html('<p>' + (DF_FINCON_ADMIN.messages.syncing || 'Syncing with Fincon...') + '</p>').show();
    $button.prop('disabled', true).text(DF_FINCON_ADMIN.messages.syncing || originalText);

    $.post(DF_FINCON_ADMIN.ajax_url, {
      action: 'df_fincon_sync_user_by_accno',
      nonce: nonce,
      user_id: userId,
    })
    .done(function(response) {
      if (response.success) {
        const message = response.data.message || 'Sync completed successfully';
        $feedback.addClass('success').html('<div class="notice notice-success is-dismissible"><p><strong>' + message + '</strong></p></div>');
        
        // Refresh the page to show updated timestamps after a short delay
        setTimeout(() => location.reload(), 2000);
      } else {
        const errorMessage = response.data.message || DF_FINCON_ADMIN.messages.generic_error;
        $feedback.addClass('error').html('<div class="notice notice-error is-dismissible"><p><strong>' + errorMessage + '</strong></p></div>');
      }
    })
    .fail(function(xhr) {
      $feedback.addClass('error').html('<p>' + DF_FINCON_ADMIN.messages.network_error + ' (HTTP Status: ' + xhr.status + ')</p>');
    })
    .always(function() {
      $button.prop('disabled', false).text(originalText);
    });
  });

  // Schedule settings - show/hide day and time fields based on frequency
  function toggleScheduleFields() {
    const $frequencySelect = $('#sync_schedule_frequency');
    const $dayField = $('#sync_schedule_day').closest('tr');
    const $timeField = $('#sync_schedule_time').closest('tr');
    const frequency = $frequencySelect.val();
    
    if ($frequencySelect.length) {
      // Show/hide day field (only for weekly)
      if ($dayField.length) {
        if (frequency === 'weekly') {
          $dayField.show();
        } else {
          $dayField.hide();
        }
      }
      
      // Show/hide time field (for daily and weekly, not hourly or 5 minutes)
      if ($timeField.length) {
        if (frequency === 'daily' || frequency === 'weekly') {
          $timeField.show();
        } else {
          $timeField.hide();
        }
      }
    }
  }

  // Initialize on page load
  toggleScheduleFields();
  
  // Update when frequency changes
  $('#sync_schedule_frequency').on('change', toggleScheduleFields);

  // Stock Locations Management
  // Handle delete, set default, and toggle active actions via AJAX
  $(document).on('click', '.df-fincon-stock-location-action', function(e) {
    e.preventDefault();
    
    const $link = $(this);
    const action = $link.data('action');
    const locationCode = $link.data('location-code');
    const nonce = $link.data('nonce');
    const confirmMessage = $link.data('confirm');
    
    if (confirmMessage && !confirm(confirmMessage)) {
      return;
    }
    
    // Determine AJAX endpoint based on action
    let ajaxAction = '';
    switch (action) {
      case 'delete':
        ajaxAction = 'df_fincon_stock_location_delete';
        break;
      case 'set_default':
        ajaxAction = 'df_fincon_stock_location_set_default';
        break;
      case 'toggle_active':
        ajaxAction = 'df_fincon_stock_location_toggle_active';
        break;
      default:
        console.error('Unknown action:', action);
        return;
    }
    
    // Show loading indicator
    const $row = $link.closest('tr');
    $row.addClass('processing');
    
    $.post(DF_FINCON_ADMIN.ajax_url, {
      action: ajaxAction,
      location_code: locationCode,
      nonce: nonce
    })
    .done(function(response) {
      if (response.success) {
        // Reload page to reflect changes
        window.location.reload();
      } else {
        alert(response.data.message || 'Action failed.');
        $row.removeClass('processing');
      }
    })
    .fail(function(xhr) {
      alert('Network error occurred. Please try again.');
      $row.removeClass('processing');
    });
  });
  
  // Stock location edit form validation and AJAX submission
  $('.df-fincon-location-form').on('submit', function(e) {
    e.preventDefault();
    
    const $form = $(this);
    const $codeInput = $('#location_code');
    const $nameInput = $('#location_name');
    const $shortNameInput = $('#location_short_name');
    const $repCodeInput = $('#location_rep_code');
    const $submitButton = $form.find('.button-primary');
    const originalButtonText = $submitButton.val();
    
    // Validate location code (numeric, 2-6 digits)
    const code = $codeInput.val().trim();
    if (!/^[0-9]{2,6}$/.test(code)) {
      alert('Location code must be 2-6 numeric characters only.');
      $codeInput.focus();
      return;
    }
    
    // Validate name (required)
    if ($nameInput.val().trim() === '') {
      alert('Location name is required.');
      $nameInput.focus();
      return;
    }
    
    // Validate short name (required)
    if ($shortNameInput.val().trim() === '') {
      alert('Short name is required.');
      $shortNameInput.focus();
      return;
    }
    
    // Validate rep code (optional, numeric)
    const repCode = $repCodeInput.val().trim();
    if (repCode !== '' && !/^[0-9]{1,10}$/.test(repCode)) {
      alert('Rep code must be 1-10 numeric characters only.');
      $repCodeInput.focus();
      return;
    }
    
    // Collect form data
    const formData = {
      action: 'df_fincon_stock_location_save',
      nonce: DF_FINCON_ADMIN.nonces.stock_locations,
      original_code: $form.find('input[name="original_code"]').val() || '',
      location: {
        code: code,
        name: $nameInput.val().trim(),
        short_name: $shortNameInput.val().trim(),
        rep_code: repCode,
        sort_order: $form.find('input[name="location[sort_order]"]').val() || 99,
        active: $form.find('input[name="location[active]"]').is(':checked') ? 1 : 0,
        is_default: $form.find('input[name="location[is_default]"]').is(':checked') ? 1 : 0
      }
    };
    
    // Show loading state
    $submitButton.prop('disabled', true).val('Saving...');
    
    // Submit via AJAX
    $.post(DF_FINCON_ADMIN.ajax_url, formData)
      .done(function(response) {
        if (response.success) {
          // Show success message and redirect to list page
          alert(response.data.message);
          window.location.href = $form.find('a.button').attr('href');
        } else {
          alert(response.data.message || 'Failed to save location.');
          $submitButton.prop('disabled', false).val(originalButtonText);
        }
      })
      .fail(function(xhr) {
        alert('Network error occurred. Please try again.');
        $submitButton.prop('disabled', false).val(originalButtonText);
      });
  });
  
  // Numeric-only input for location code and rep code
  $('#location_code, #location_rep_code').on('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
  });

});
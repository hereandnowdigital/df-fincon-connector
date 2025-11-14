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

  const initialTab = window.location.hash.substring(1) || 'api';
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
      
      var count = $('#df_fincon_import_count').val();
      var offset = $('#df_fincon_import_offset').val();

      $feedback.removeClass('success error').html('<p>' + DF_FINCON_ADMIN.messages.importing + '...</p>');
      $button.prop('disabled', true).text(DF_FINCON_ADMIN.messages.importing);

      $.post(DF_FINCON_ADMIN.ajax_url, {
        action: 'df_fincon_manual_import_products',
        nonce: DF_FINCON_ADMIN.nonces.import,
        count: count,
        offset: offset
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

});
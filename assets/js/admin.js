jQuery(document).ready(function($) {
  // Tab switching
  function switchTab(target) {
    $('.df-fincon-tab').removeClass('active');
    $('.df-fincon-tab-content').removeClass('active');
    $(target).addClass('active');
    $('#' + $(target).data('target')).addClass('active');
  }

  $('.df-fincon-tab').on('click', function(e) {
    e.preventDefault();
    switchTab(this);
  });

  // API Settings Tab
  (function() {
    const $apiFields = $('#df_fincon_api_settings');
    const $testButton = $('#df-fincon-test-connection-btn');

    function isApiFieldsPopulated() {
      const required = ['server_url', 'server_port', 'username', 'password', 'data_id'];
      let filled = 0;
      required.forEach(function(field) {
        var value = $apiFields.find('[name="' + field + '"]').val();
        if (value && value.trim() !== '')
          filled++;
      });
      return filled === required.length;
    }

    function checkConnectionButtonState() {
      $testButton.prop('disabled', !isApiFieldsPopulated());
    }

    // Initial state
    checkConnectionButtonState();

    // Monitor input changes
    $apiFields.find('input').on('input', checkConnectionButtonState);

    // Test connection handler
    $testButton.on('click', function(e) {
      e.preventDefault();
      const $button = $(this);
      const originalText = $button.text();
      const $feedback = $('#df-fincon-test-feedback');

      $feedback.removeClass('success error').html('<p>' + DF_FINCON_ADMIN.messages.testing + '...</p>');
      $button.prop('disabled', true).text(DF_FINCON_ADMIN.messages.testing);

      const data = {
        action: 'df_fincon_test_connection',
        nonce: DF_FINCON_ADMIN.nonces.test,
        server_url: $apiFields.find('[name="server_url"]').val(),
        server_port: $apiFields.find('[name="server_port"]').val(),
        username: $apiFields.find('[name="username"]').val(),
        password: $apiFields.find('[name="password"]').val(),
        data_id: $apiFields.find('[name="data_id"]').val()
      };

      $.post(DF_FINCON_ADMIN.ajax_url, data)
        .done(function(response) {
          if (response.success) {
            $feedback.addClass('success').html('<p>✅ ' + response.data.message + '</p>');
          } else {
            const errorMessage = response.data.message || DF_FINCON_ADMIN.messages.test_failed;
            const errorDetails = JSON.stringify(response.data.data, null, 2) || 'No further details provided.';
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
  })();

  // Manual Product Import Tab
  (function() {
    // Single product import handler
    $('#df-fincon-import-single-btn').on('click', function(e) {
      e.preventDefault();
      var $button = $(this);
      var originalText = $button.text();
      var $feedback = $('#df-fincon-import-feedback');
      var $inlineFeedback = $('#df-fincon-single-feedback');
      var productItemCode = $('#df_fincon_import_product_item_code').val();

      if (!productItemCode || productItemCode.trim() === '') {
        $inlineFeedback.html('<span style="color:#B32D2E;">Please enter a product item code.</span>');
        return;
      }

      $inlineFeedback.html('<span style="color:#0073AA;">Importing single product...</span>');
      $feedback.removeClass('success error').html('<p>' + DF_FINCON_ADMIN.messages.importing + '...</p>');
      $button.prop('disabled', true).text(DF_FINCON_ADMIN.messages.importing);

      $.post(DF_FINCON_ADMIN.ajax_url, {
        action: 'df_fincon_manual_import_products',
        nonce: DF_FINCON_ADMIN.nonces.import,
        product_item_code: productItemCode,
        count: 0,
      })
      .done(function(response) {
        if (response.success) {
          const data = response.data.data;
          let html = '<div class="notice notice-success is-dismissible"><p><strong>✅ ' + response.data.message + '</strong></p></div>';

          // Single product import summary
          html += '<table class="wp-list-table widefat fixed striped">';
          html += '<thead><tr><th colspan="2">Single Product Import Summary</th></tr></thead>';
          html += '<tbody>';
          html += '<tr><th>Product Item Code</th><td>' + productItemCode + '</td></tr>';
          html += '<tr><th>Status</th><td>' + (data.imported_count ? 'Imported successfully' : 'Skipped/failed') + '</td></tr>';
          if (data.errors && data.errors.length > 0) {
            html += '<tr><th>Errors</th><td><ul>';
            data.errors.forEach(function(error) {
              html += '<li>' + error + '</li>';
            });
            html += '</ul></td></tr>';
          }
          html += '</tbody></table>';
          
          $feedback.addClass('success').html(html);
          $inlineFeedback.html('<span style="color:#22A300;">Import completed.</span>');
        } else {
          var errorMessage = response.data.message || DF_FINCON_ADMIN.messages.import_failed;
          var errorDetails = JSON.stringify(response.data.data, null, 2) || 'No further details provided.';
          $feedback.addClass('error').html('<p>❌ ' + errorMessage + '</p><pre>' + errorDetails + '</pre>');
          $inlineFeedback.html('<span style="color:#B32D2E;">Import failed.</span>');
        }
      })
      .fail(function(xhr, status, error) {
        $feedback.addClass('error').html('<p>❌ ' + DF_FINCON_ADMIN.messages.network_error + ' (HTTP Status: ' + xhr.status + ')</p>');
        $inlineFeedback.html('<span style="color:#B32D2E;">Network error.</span>');
      })
      .always(function() {
        $button.prop('disabled', false).text(originalText);
      });
    });

    // Batch product import handler
    $('#df-fincon-import-batch-btn').on('click', function(e) {
      e.preventDefault();
      var $button = $(this);
      var originalText = $button.text();
      var $feedback = $('#df-fincon-import-feedback');
      var $inlineFeedback = $('#df-fincon-batch-feedback');
      var count = $('#df_fincon_import_count').val();

      $inlineFeedback.html('<span style="color:#0073AA;">Starting batch import...</span>');
      $feedback.removeClass('success error').html('<p>' + DF_FINCON_ADMIN.messages.importing + '...</p>');
      $button.prop('disabled', true).text(DF_FINCON_ADMIN.messages.importing);

      // Function to process batch recursively
      function processBatch(resume) {
        var isResume = resume || false;
        
        $.post(DF_FINCON_ADMIN.ajax_url, {
          action: 'df_fincon_manual_import_products',
          nonce: DF_FINCON_ADMIN.nonces.import,
          count: count,
          product_item_code: '', // empty for batch
          resume: isResume ? 1 : 0,
        })
        .done(function(response) {
          if (response.success) {
            const data = response.data.data;
            
            // Update progress display
            var progress = data.progress || {};
            var totalProcessed = progress.total_processed || data.actual_processed || 0;
            var requestedCount = data.requested_count || count;
            
            // Update inline feedback with current progress
            if (requestedCount > 0) {
              $inlineFeedback.html('<span style="color:#0073AA;">Processing... ' + totalProcessed + ' of ' + requestedCount + ' products</span>');
            } else {
              $inlineFeedback.html('<span style="color:#0073AA;">Processing... ' + totalProcessed + ' products imported</span>');
            }
            
            // Check if import is complete
            if (data.import_complete === false && data.next_rec_no) {
              // Continue with next batch after a short delay
              setTimeout(function() {
                processBatch(true);
              }, 500);
            } else {
              // Import complete - show final summary
              let html = '<div class="notice notice-success is-dismissible"><p><strong>✅ ' + response.data.message + '</strong></p></div>';

              html += '<table class="wp-list-table widefat fixed striped">';
              html += '<thead><tr><th colspan="2">Import Summary</th></tr></thead>';
              html += '<tbody>';
              
              // Show API count vs processed count for clarity
              var apiCount = (data.raw_response_summary && data.raw_response_summary.Count) || data.api_count || 0;
              var actualProcessed = data.actual_processed || data.total_fetched || 0;
              
              html += '<tr><th>Items fetched from API</th><td>' + apiCount + '</td></tr>';
              html += '<tr><th>Items processed</th><td>' + actualProcessed + '</td></tr>';
              if (apiCount > actualProcessed) {
                html += '<tr><th>Note</th><td><em>Limited processing to respect requested count of ' + (data.requested_count || 'N/A') + ' products</em></td></tr>';
              }
              html += '<tr><th>Successfully imported</th><td>' + (data.imported_count || 0) + '</td></tr>';
              html += '<tr><th>Skipped/failed</th><td>' + (data.skipped_count || 0) + '</td></tr>';
              html += '<tr><th>Next record number (RecNo)</th><td><strong>' + (data.next_rec_no || 'N/A') + '</strong></td></tr>';
              
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
              $inlineFeedback.html('<span style="color:#22A300;">Batch import completed. Total processed: ' + totalProcessed + ' products</span>');
              $button.prop('disabled', false).text(originalText);
            }
          } else {
            var errorMessage = response.data.message || DF_FINCON_ADMIN.messages.import_failed;
            var errorDetails = JSON.stringify(response.data.data, null, 2) || 'No further details provided.';
            $feedback.addClass('error').html('<p>❌ ' + errorMessage + '</p><pre>' + errorDetails + '</pre>');
            $inlineFeedback.html('<span style="color:#B32D2E;">Batch import failed.</span>');
            $button.prop('disabled', false).text(originalText);
          }
        })
        .fail(function(xhr, status, error) {
          $feedback.addClass('error').html('<p>❌ ' + DF_FINCON_ADMIN.messages.network_error + ' (HTTP Status: ' + xhr.status + ')</p>');
          $inlineFeedback.html('<span style="color:#B32D2E;">Network error.</span>');
          $button.prop('disabled', false).text(originalText);
        });
      }
      
      // Start the first batch
      processBatch(false);
    });

    // Resume import handler - uses the same recursive logic
    $('#df-fincon-resume-import-btn').on('click', function(e) {
      e.preventDefault();
      var $button = $(this);
      var originalText = $button.text();
      var $feedback = $('#df-fincon-import-feedback');
      var $inlineFeedback = $('#df-fincon-batch-feedback');
      var count = $('#df_fincon_import_count').val();

      $inlineFeedback.html('<span style="color:#0073AA;">Resuming batch import...</span>');
      $feedback.removeClass('success error').html('<p>' + DF_FINCON_ADMIN.messages.importing + '...</p>');
      $button.prop('disabled', true).text(DF_FINCON_ADMIN.messages.importing);

      // Reuse the same processBatch function from above
      // Since it's defined inside the batch import handler closure, we need to define it here too
      function processBatch(resume) {
        var isResume = resume || false;
        
        $.post(DF_FINCON_ADMIN.ajax_url, {
          action: 'df_fincon_manual_import_products',
          nonce: DF_FINCON_ADMIN.nonces.import,
          count: count,
          product_item_code: '', // empty for batch
          resume: isResume ? 1 : 0,
        })
        .done(function(response) {
          if (response.success) {
            const data = response.data.data;
            
            // Update progress display
            var progress = data.progress || {};
            var totalProcessed = progress.total_processed || data.actual_processed || 0;
            var requestedCount = data.requested_count || count;
            
            // Update inline feedback with current progress
            if (requestedCount > 0) {
              $inlineFeedback.html('<span style="color:#0073AA;">Processing... ' + totalProcessed + ' of ' + requestedCount + ' products</span>');
            } else {
              $inlineFeedback.html('<span style="color:#0073AA;">Processing... ' + totalProcessed + ' products imported</span>');
            }
            
            // Check if import is complete
            if (data.import_complete === false && data.next_rec_no) {
              // Continue with next batch after a short delay
              setTimeout(function() {
                processBatch(true);
              }, 500);
            } else {
              // Import complete - show final summary
              let html = '<div class="notice notice-success is-dismissible"><p><strong>✅ ' + response.data.message + '</strong></p></div>';

              html += '<table class="wp-list-table widefat fixed striped">';
              html += '<thead><tr><th colspan="2">Import Summary</th></tr></thead>';
              html += '<tbody>';
              
              // Show API count vs processed count for clarity
              var apiCount = (data.raw_response_summary && data.raw_response_summary.Count) || data.api_count || 0;
              var actualProcessed = data.actual_processed || data.total_fetched || 0;
              
              html += '<tr><th>Items fetched from API</th><td>' + apiCount + '</td></tr>';
              html += '<tr><th>Items processed</th><td>' + actualProcessed + '</td></tr>';
              if (apiCount > actualProcessed) {
                html += '<tr><th>Note</th><td><em>Limited processing to respect requested count of ' + (data.requested_count || 'N/A') + ' products</em></td></tr>';
              }
              html += '<tr><th>Successfully imported</th><td>' + (data.imported_count || 0) + '</td></tr>';
              html += '<tr><th>Skipped/failed</th><td>' + (data.skipped_count || 0) + '</td></tr>';
              html += '<tr><th>Next record number (RecNo)</th><td><strong>' + (data.next_rec_no || 'N/A') + '</strong></td></tr>';
              
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
              $inlineFeedback.html('<span style="color:#22A300;">Batch import completed. Total processed: ' + totalProcessed + ' products</span>');
              $button.prop('disabled', false).text(originalText);
            }
          } else {
            var errorMessage = response.data.message || DF_FINCON_ADMIN.messages.import_failed;
            var errorDetails = JSON.stringify(response.data.data, null, 2) || 'No further details provided.';
            $feedback.addClass('error').html('<p>❌ ' + errorMessage + '</p><pre>' + errorDetails + '</pre>');
            $inlineFeedback.html('<span style="color:#B32D2E;">Batch import failed.</span>');
            $button.prop('disabled', false).text(originalText);
          }
        })
        .fail(function(xhr, status, error) {
          $feedback.addClass('error').html('<p>❌ ' + DF_FINCON_ADMIN.messages.network_error + ' (HTTP Status: ' + xhr.status + ')</p>');
          $inlineFeedback.html('<span style="color:#B32D2E;">Network error.</span>');
          $button.prop('disabled', false).text(originalText);
        });
      }
      
      // Start with resume=true
      processBatch(true);
    });

    // Reset import progress handler
    $('#df-fincon-reset-import-btn').on('click', function(e) {
      e.preventDefault();
      var $button = $(this);
      var originalText = $button.text();
      var $feedback = $('#df-fincon-import-feedback');
      var $inlineFeedback = $('#df-fincon-batch-feedback');

      if (!confirm('Are you sure you want to reset the import progress? This will clear any ongoing import state.')) {
        return;
      }

      $inlineFeedback.html('<span style="color:#0073AA;">Resetting import progress...</span>');
      $feedback.removeClass('success error').html('<p>Resetting import progress...</p>');
      $button.prop('disabled', true).text('Resetting...');

      $.post(DF_FINCON_ADMIN.ajax_url, {
        action: 'df_fincon_reset_import_progress',
        nonce: DF_FINCON_ADMIN.nonces.import,
      })
      .done(function(response) {
        if (response.success) {
          $feedback.addClass('success').html('<p>✅ ' + response.data.message + '</p>');
          $inlineFeedback.html('<span style="color:#22A300;">Import progress reset.</span>');
        } else {
          var errorMessage = response.data.message || 'Failed to reset import progress';
          $feedback.addClass('error').html('<p>❌ ' + errorMessage + '</p>');
          $inlineFeedback.html('<span style="color:#B32D2E;">Reset failed.</span>');
        }
      })
      .fail(function(xhr, status, error) {
        $feedback.addClass('error').html('<p>❌ Network error (HTTP Status: ' + xhr.status + ')</p>');
        $inlineFeedback.html('<span style="color:#B32D2E;">Network error.</span>');
      })
      .always(function() {
        $button.prop('disabled', false).text(originalText);
      });
    });

  })(); // End of Manual Product Import Tab IIFE

}); // End of jQuery(document).ready()

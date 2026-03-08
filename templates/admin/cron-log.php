<div class="wrap">
  <h1><?php esc_html_e( 'Cron Log', 'df-fincon' ); ?></h1>
  
  <p><?php esc_html_e( 'This log shows when scheduled product syncs started and whether they completed successfully.', 'df-fincon' ); ?></p>
  
  <?php if ( ! empty( $logs ) ) : ?>
    <p>
      <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'clear_log' ), 'clear_cron_log' ) ); ?>" 
         class="button" 
         onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear the cron log?', 'df-fincon' ); ?>');">
        <?php esc_html_e( 'Clear Log', 'df-fincon' ); ?>
      </a>
    </p>
    
    <table class="wp-list-table widefat fixed striped">
      <thead>
        <tr>
          <th style="width: 180px;"><?php esc_html_e( 'Started At', 'df-fincon' ); ?></th>
          <th style="width: 180px;"><?php esc_html_e( 'Completed At', 'df-fincon' ); ?></th>
          <th style="width: 100px;"><?php esc_html_e( 'Status', 'df-fincon' ); ?></th>
          <th style="width: 100px;"><?php esc_html_e( 'Duration', 'df-fincon' ); ?></th>
          <th><?php esc_html_e( 'Message', 'df-fincon' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $logs as $log ) : ?>
          <tr>
            <td>
              <?php echo esc_html( $log['started_at'] ?? '-' ); ?>
            </td>
            <td>
              <?php echo esc_html( $log['completed_at'] ?? '-' ); ?>
            </td>
            <td>
              <?php
              $status = $log['status'] ?? 'unknown';
              $status_label = '';
              $status_color = '';
              
              switch ( $status ) :
                case 'running':
                  $status_label = __( 'Running', 'df-fincon' );
                  $status_color = '#0073AA';
                  break;
                case 'success':
                  $status_label = __( 'Success', 'df-fincon' );
                  $status_color = '#22A300';
                  break;
                case 'failed':
                  $status_label = __( 'Failed', 'df-fincon' );
                  $status_color = '#B32D2E';
                  break;
                default:
                  $status_label = __( 'Unknown', 'df-fincon' );
                  $status_color = '#666';
              endswitch;
              ?>
              <strong style="color: <?php echo esc_attr( $status_color ); ?>;">
                <?php echo esc_html( $status_label ); ?>
              </strong>
            </td>
            <td>
              <?php
              $duration = isset( $log['duration'] ) ? (int) $log['duration'] : 0;
              if ( $duration > 0 ) :
                echo esc_html( sprintf( '%d s', $duration ) );
              elseif ( $status === 'running' ) :
                echo '<span style="color: #666;">—</span>';
              else :
                echo '<span style="color: #666;">—</span>';
              endif;
              ?>
            </td>
            <td>
              <?php if ( ! empty( $log['message'] ) ) : ?>
                <?php echo esc_html( $log['message'] ); ?>
              <?php elseif ( $status === 'running' ) : ?>
                <span style="color: #666;"><?php esc_html_e( 'Sync in progress...', 'df-fincon' ); ?></span>
              <?php else : ?>
                <span style="color: #666;">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else : ?>
    <div class="notice notice-info">
      <p><?php esc_html_e( 'No cron log entries yet. The log will populate once scheduled syncs start running.', 'df-fincon' ); ?></p>
    </div>
    
    <?php
    // Show next scheduled run if available
    $next_run_utc = wp_next_scheduled( \DF_FINCON\Cron::HOOK );
    if ( $next_run_utc ) :
      // Convert UTC timestamp to local WordPress timezone for display
      $gmt_offset = (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
      $next_run_local_timestamp = $next_run_utc + (int) $gmt_offset;
      $now_local_timestamp = current_time( 'timestamp' );
      ?>
      <p>
        <strong><?php esc_html_e( 'Next scheduled sync:', 'df-fincon' ); ?></strong> 
        <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run_local_timestamp ) ); ?>
        (<?php echo esc_html( human_time_diff( $next_run_local_timestamp, $now_local_timestamp ) ); ?>)
      </p>
      <?php
    endif;
    ?>
  <?php endif; ?>
</div>


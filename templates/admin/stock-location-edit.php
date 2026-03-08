<div class="wrap">
  <h1>
    <?php if ( $is_edit ) : ?>
      <?php esc_html_e( 'Edit Stock Location', 'df-fincon' ); ?>
    <?php else : ?>
      <?php esc_html_e( 'Add New Stock Location', 'df-fincon' ); ?>
    <?php endif; ?>
  </h1>
  
  <?php if ( ! empty( $errors ) ) : ?>
    <div class="notice notice-error">
      <ul>
        <?php foreach ( $errors as $error ) : ?>
          <li><?php echo esc_html( $error ); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  
  <form method="post" action="" class="df-fincon-location-form">
    <?php wp_nonce_field( 'save_location', 'df_fincon_location_nonce' ); ?>
    
    <input type="hidden" name="action" value="save_location">
    <?php if ( $is_edit ) : ?>
      <input type="hidden" name="original_code" value="<?php echo esc_attr( $location['code'] ); ?>">
    <?php endif; ?>
    
    <table class="form-table">
      <tbody>
        <tr>
          <th scope="row">
            <label for="location_code"><?php esc_html_e( 'Location Code', 'df-fincon' ); ?> <span class="required">*</span></label>
          </th>
          <td>
            <input type="text"
                   id="location_code"
                   name="location[code]"
                   value="<?php echo esc_attr( $location['code'] ?? '' ); ?>"
                   class="regular-text"
                   maxlength="6"
                   pattern="[0-9]{2,6}"
                   required>
            <p class="description">
              <?php esc_html_e( '2-6 numeric characters only. This code must match the location code in your Fincon system.', 'df-fincon' ); ?>
            </p>
          </td>
        </tr>
        
        <tr>
          <th scope="row">
            <label for="location_name"><?php esc_html_e( 'Location Name', 'df-fincon' ); ?> <span class="required">*</span></label>
          </th>
          <td>
            <input type="text" 
                   id="location_name" 
                   name="location[name]" 
                   value="<?php echo esc_attr( $location['name'] ?? '' ); ?>" 
                   class="regular-text" 
                   maxlength="100" 
                   required>
            <p class="description">
              <?php esc_html_e( 'Full name of the location (e.g., Johannesburg, Cape Town).', 'df-fincon' ); ?>
            </p>
          </td>
        </tr>
        
        <tr>
          <th scope="row">
            <label for="location_short_name"><?php esc_html_e( 'Short Name', 'df-fincon' ); ?> <span class="required">*</span></label>
          </th>
          <td>
            <input type="text" 
                   id="location_short_name" 
                   name="location[short_name]" 
                   value="<?php echo esc_attr( $location['short_name'] ?? '' ); ?>" 
                   class="regular-text" 
                   maxlength="10" 
                   required>
            <p class="description">
              <?php esc_html_e( 'Abbreviated name (e.g., JHB, CPT). Used in product meta fields.', 'df-fincon' ); ?>
            </p>
          </td>
        </tr>
        
        <tr>
          <th scope="row">
            <label for="location_rep_code"><?php esc_html_e( 'Rep Code', 'df-fincon' ); ?></label>
          </th>
          <td>
            <input type="text" 
                   id="location_rep_code" 
                   name="location[rep_code]" 
                   value="<?php echo esc_attr( $location['rep_code'] ?? '' ); ?>" 
                   class="regular-text" 
                   maxlength="10" 
                   pattern="[0-9]*">
            <p class="description">
              <?php esc_html_e( 'Optional numeric rep code for this location. Used in order synchronization.', 'df-fincon' ); ?>
            </p>
          </td>
        </tr>
        
        <tr>
          <th scope="row">
            <label for="location_sort_order"><?php esc_html_e( 'Sort Order', 'df-fincon' ); ?></label>
          </th>
          <td>
            <input type="number" 
                   id="location_sort_order" 
                   name="location[sort_order]" 
                   value="<?php echo esc_attr( $location['sort_order'] ?? 99 ); ?>" 
                   class="small-text" 
                   min="1" 
                   max="999" 
                   step="1">
            <p class="description">
              <?php esc_html_e( 'Lower numbers appear first in lists. Default is 99.', 'df-fincon' ); ?>
            </p>
          </td>
        </tr>
        
        <tr>
          <th scope="row"><?php esc_html_e( 'Status', 'df-fincon' ); ?></th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><?php esc_html_e( 'Location Status', 'df-fincon' ); ?></legend>
              <label for="location_active">
                <input type="checkbox" 
                       id="location_active" 
                       name="location[active]" 
                       value="1" 
                       <?php checked( ! isset( $location['active'] ) || ! empty( $location['active'] ) ); ?>>
                <?php esc_html_e( 'Active', 'df-fincon' ); ?>
              </label>
              <p class="description">
                <?php esc_html_e( 'Active locations are included in API calls and product synchronization.', 'df-fincon' ); ?>
              </p>
            </fieldset>
          </td>
        </tr>
        
        <tr>
          <th scope="row"><?php esc_html_e( 'Default Location', 'df-fincon' ); ?></th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><?php esc_html_e( 'Default Location', 'df-fincon' ); ?></legend>
              <label for="location_is_default">
                <input type="checkbox" 
                       id="location_is_default" 
                       name="location[is_default]" 
                       value="1" 
                       <?php checked( ! empty( $location['is_default'] ) ); ?>
                       <?php echo ( $is_edit && ! empty( $location['is_default'] ) ) ? 'disabled' : ''; ?>>
                <?php esc_html_e( 'Set as default location', 'df-fincon' ); ?>
              </label>
              <p class="description">
                <?php esc_html_e( 'The default location is used for order synchronization when no specific location is available. Only one location can be default.', 'df-fincon' ); ?>
                <?php if ( $is_edit && ! empty( $location['is_default'] ) ) : ?>
                  <br><strong><?php esc_html_e( 'Note: This location is currently set as default. To change the default location, edit another location and set it as default.', 'df-fincon' ); ?></strong>
                <?php endif; ?>
              </p>
            </fieldset>
          </td>
        </tr>
      </tbody>
    </table>
    
    <p class="submit">
      <?php submit_button( $is_edit ? __( 'Update Location', 'df-fincon' ) : __( 'Add Location', 'df-fincon' ), 'primary', 'submit', false ); ?>
      <a href="<?php echo esc_url( remove_query_arg( [ 'action', 'code' ] ) ); ?>" class="button">
        <?php esc_html_e( 'Cancel', 'df-fincon' ); ?>
      </a>
    </p>
  </form>
</div>

<script>
jQuery(document).ready(function($) {
  // Client-side validation for numeric fields
  $('#location_code').on('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
  });
  
  $('#location_rep_code').on('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
  });
  
  // Toggle default location checkbox behavior
  $('#location_is_default').on('change', function() {
    if (this.checked) {
      if (!confirm('<?php esc_attr_e( 'Setting this location as default will remove the default flag from any other location. Continue?', 'df-fincon' ); ?>')) {
        this.checked = false;
      }
    }
  });
  
  // Form validation
  $('.df-fincon-location-form').on('submit', function(e) {
    var code = $('#location_code').val();
    var name = $('#location_name').val();
    var shortName = $('#location_short_name').val();
    
    if (!code.match(/^[0-9]{2,6}$/)) {
      alert('<?php esc_attr_e( 'Please enter a valid location code (2-6 numeric characters).', 'df-fincon' ); ?>');
      $('#location_code').focus();
      e.preventDefault();
      return false;
    }
    
    if (!name.trim()) {
      alert('<?php esc_attr_e( 'Please enter a location name.', 'df-fincon' ); ?>');
      $('#location_name').focus();
      e.preventDefault();
      return false;
    }
    
    if (!shortName.trim()) {
      alert('<?php esc_attr_e( 'Please enter a short name.', 'df-fincon' ); ?>');
      $('#location_short_name').focus();
      e.preventDefault();
      return false;
    }
    
    var repCode = $('#location_rep_code').val();
    if (repCode && !repCode.match(/^[0-9]{1,10}$/)) {
      alert('<?php esc_attr_e( 'Rep code must be 1-10 numeric characters only.', 'df-fincon' ); ?>');
      $('#location_rep_code').focus();
      e.preventDefault();
      return false;
    }
    
    return true;
  });
});
</script>

<style>
.df-fincon-location-form .form-table {
  max-width: 800px;
}
.df-fincon-location-form .required {
  color: #d63638;
}
.df-fincon-location-form input[readonly] {
  background-color: #f5f5f5;
  border-color: #ddd;
  color: #777;
}
</style>
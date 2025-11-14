<?php
  /**
   * Autoloader for DF Fincon Connector plugin classes.
   * 
   * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
   * @package df-fincon-connector
   * Text Domain: df-fincon
   * 
   */

  spl_autoload_register('DF_FINCON_autoloader');
  function DF_FINCON_autoloader( $class ) {
    $namespace = 'DF_FINCON';

    if ( strpos( $class, $namespace ) !== 0 ) 
		  return;

    $class_name = substr( $class, strlen( $namespace ) );
	  
    // Trim leading backslash if present
	  $class_name = ltrim( $class_name, '\\' );

    $class_file = DF_FINCON_PLUGIN_DIR . 'includes/'  . str_replace( '\\', '/', $class_name ) . '.php';

    if (file_exists($class_file))
      require_once($class_file);

  }

<<<<<<< HEAD
<?php
/*
Plugin Name: BMoney Ultimate CSV Importer
Plugin URI: https://github.com/solepixel/bmoney-ultimate-csv-importer/
Description: Imports (almost) anything into WordPress.
Version: 2.6
Author: Brian DiChiara
Author URI: http://www.briandichiara.com
*/

define('BMUCI_VERSION', '2.6');
define('BMUCI_PI_NAME', 'Ultimate CSV Importer');
define('BMUCI_PI_DESCRIPTION', 'Imports (almost) anything into WordPress.');
define('BMUCI_OPT_PREFIX', 'bmuci_');
define('BMUCI_PATH', plugin_dir_path( __FILE__ ));
define('BMUCI_DIR', plugin_dir_url( __FILE__ ));

require_once(BMUCI_PATH.'classes/bm-ultimate-csv-importer.class.php');

$bmuci_plugin = new BM_Ultimate_CSV_Importer();
$bmuci_plugin->initialize();
=======
<?php/** * Plugin Name: BMoney Ultimate CSV Importer * Plugin URI: https://github.com/solepixel/bmoney-ultimate-csv-importer/ * Description: Imports (almost) anything into WordPress. * Version: 2.61 * Author: Brian DiChiara * Author URI: http://www.briandichiara.com */define('BMUCI_VERSION', '2.61');define('BMUCI_PI_NAME', 'Ultimate CSV Importer');define('BMUCI_PI_DESCRIPTION', 'Imports (almost) anything into WordPress.');define('BMUCI_OPT_PREFIX', 'bmuci_');define('BMUCI_PATH', plugin_dir_path( __FILE__ ));define('BMUCI_DIR', plugin_dir_url( __FILE__ ));require_once(BMUCI_PATH.'classes/bm-ultimate-csv-importer.class.php');global $bmuci_plugin;$bmuci_plugin = new BM_Ultimate_CSV_Importer();$bmuci_plugin->initialize();
>>>>>>> 51ba784e3cef9bcb0ef6f3292d1d86c6e935fbe8

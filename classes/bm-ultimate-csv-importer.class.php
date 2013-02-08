<<<<<<< HEAD
<?php

if(!class_exists('BM_Ultimate_CSV_Importer')){
	
	class BM_Ultimate_CSV_Importer {
		
		private $debug_mode = false;
		
		var $admin_page = 'ultimate-csv-importer';
		
		public $errors = array();
		public $import_type = 'posts';
		public $multisite = false;
		public $post_id = NULL;
		public $unique = NULL;
		public $unique_key = NULL;
		public $serialized = array();
		public $serialized_keys = array();
		public $last_import = 0;
		public $defaults = array();
		
		public $increment = NULL;
		public $start = NULL;
		public $end = NULL;
		
		public $customization_methods = array();
		
		private $_default_fields;
		private $_custom_fields;
		
		private $storage = array();
		
		public $total_rows;
		public $total_imported = 0;
		public $failed_imports = 0;
		public $results;
		
		/**
		 * BM_Ultimate_CSV_Importer::__construct()
		 * 
		 * @return void
		 */
		function __construct(){
			
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::initialize()
		 * 
		 * @return void
		 */
		function initialize(){
			add_action( 'init', array($this, '_init'));
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_init()
		 * 
		 * @return void
		 */
		function _init(){
			do_action('bmuci_before_init', $this);
			
			ini_set('memory_limit', '-1');
			set_time_limit(600); // 10 minutes
			ini_set('auto_detect_line_endings', true);
			
			add_action(BMUCI_OPT_PREFIX.'scheduled_import', array($this, '_cron_import'));
			
			if(is_admin()){
				add_action('admin_menu', array($this, '_admin_menu'));
				
				// add css/js file for admin
				wp_register_style(BMUCI_OPT_PREFIX.'admin', BMUCI_DIR.'css/bmuci-admin.css', array(), BMUCI_VERSION);
				//TODO import uploadify
				wp_register_script(BMUCI_OPT_PREFIX.'admin', BMUCI_DIR.'js/bmuci-admin.js', array('jquery'), BMUCI_VERSION);
				
				register_importer('importer-bmuci', BMUCI_PI_NAME, BMUCI_PI_DESCRIPTION, array($this, 'register_importer_callback'));
			}
			
			$this->customization_methods = apply_filters('bmuci_customization_methods', array(
				'CONCAT' => 'Concatenation',
				'SERIALIZE' => 'Serialize',
				'VALUE' => 'Custom Value'
			));
			
			add_filter('bmuci_post_meta', array($this, '_handle_attachments'), 10, 3);
			
			do_action('bmuci_after_init', $this);
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_admin_menu()
		 * 
		 * @return void
		 */
		function _admin_menu(){
			add_submenu_page('tools.php', BMUCI_PI_NAME, BMUCI_PI_NAME, 8, $this->admin_page, array($this, 'importer'));
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::register_importer_callback()
		 * 
		 * @return void
		 */
		function register_importer_callback(){
			wp_redirect(admin_url().'tools.php?page='.$this->admin_page);
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::importer()
		 * 
		 * @return void
		 */
		function importer(){
			wp_enqueue_style(BMUCI_OPT_PREFIX.'admin');
			wp_enqueue_script(BMUCI_OPT_PREFIX.'admin');
			
			$processed = false;
			
			$csv = $this->_handle_upload();
			
			$this->last_import = isset($_POST['bmuci_last_import']) ? $_POST['bmuci_last_import'] : $this->last_import;
			
			$previous_matched = get_option(BMUCI_OPT_PREFIX.'matched', array());
			$has_previous = ($previous_matched) ? true : false;
			
			if($this->last_import){
				$this->setup_vars();
				
				$custom_columns = array();
				if(is_array($previous_matched) && count($previous_matched) > 0){
					foreach($previous_matched as $pm => $val){
						if(is_array($val)){
							$custom_columns['col'][] = $pm;
							$custom_columns['method'][] = $val[0];
							$custom_columns['value'][] = $val[1];
							unset($previous_matched[$pm]);
						}
					}
				}
			} else {
				$this->import_type	= isset($_POST['bmuci_import_type']) ? $_POST['bmuci_import_type'] : $this->import_type;
				$this->multisite	= isset($_POST['bmuci_multisite']) ? $_POST['bmuci_multisite'] : array();
				$this->post_id		= isset($_POST['bmuci_id']) ? $_POST['bmuci_id'] : NULL;
				$this->unique		= isset($_POST['bmuci_unique']) ? $_POST['bmuci_unique'] : NULL;
				$this->serialized	= isset($_POST['bmuci_serialize']) ? $_POST['bmuci_serialize'] : array();
				$this->increment	= isset($_POST['bmuci_increment']) ? $_POST['bmuci_increment'] : $this->increment;
				
				$custom_columns = isset($_POST['custom_columns']) ? $_POST['custom_columns'] : array();
				
				if($this->import_type != 'other'){
					$this->defaults = array(
						'post_type' => isset($_POST['bmuci_post_type']) && $_POST['bmuci_post_type'] != '__custom__' ? $_POST['bmuci_post_type'] : (isset($_POST['bmuci_custom_post_type']) && $_POST['bmuci_custom_post_type'] ? $_POST['bmuci_custom_post_type'] : 'post'),
						'post_status' => isset($_POST['bmuci_post_status']) ? $_POST['bmuci_post_status'] : 'publish',
						'comment_status' => isset($_POST['bmuci_comment_status']) ? $_POST['bmuci_comment_status'] : 'open',
						'ping_status' => isset($_POST['bmuci_ping_status']) ? $_POST['bmuci_ping_status'] : 'open',
						'post_author' => isset($_POST['bmuci_post_author']) ? $_POST['bmuci_post_author'] : NULL,
						'user_role' => isset($_POST['bmuci_user_role']) ? $_POST['bmuci_user_role'] : 'subscriber'
					);
				}
			}
			
			$schedule = isset($_POST['bmuci_schedule']) ? $_POST['bmuci_schedule'] : 'now';
			$when = isset($_POST['bmuci_schedule_datetime']) ? $_POST['bmuci_schedule_datetime'] : '';
			
			$data = $this->_parse_csv($csv);
			
			$matched = $this->_match_columns($data, $custom_columns);
			
			if($matched){
				if($schedule == 'now'){
					$this->results = $this->_do_import($matched, $data);
					if($this->total_imported){
						$processed = 'success';
						$success_message = 'Congratulations! '.$this->total_imported.' total records (out of '.$this->total_rows.') were imported!';
						
					} else {
						$this->errors[] = 'Sorry but there must have been a problem with your data. Nothing got imported.';
					}
					if($this->failed_imports){
						$this->errors[] = $this->failed_imports.' records weren\'t imported because of missing required fields.';
					}
				} elseif($schedule == 'incremental'){
					$when = 'now + 1 minute';
					$processed = 'success';
					$success_message = 'Your import will start momentarily.';
				} else {
					$processed = 'success';
					$success_message = 'Your import has been successfully scheduled.';
				}
				
				
				if($matched === true) $matched = NULL;
				
				// store data for future imports
				update_option(BMUCI_OPT_PREFIX.'import_type', $this->import_type);
				update_option(BMUCI_OPT_PREFIX.'defaults', $this->defaults);
				update_option(BMUCI_OPT_PREFIX.'multisite', $this->multisite);
				update_option(BMUCI_OPT_PREFIX.'post_id', $this->post_id);
				update_option(BMUCI_OPT_PREFIX.'unique', $this->unique);
				update_option(BMUCI_OPT_PREFIX.'serialized', $this->serialized);
				update_option(BMUCI_OPT_PREFIX.'increment', $this->increment);
				update_option(BMUCI_OPT_PREFIX.'matched', $matched);
				
				if($schedule != 'now'){
					update_option(BMUCI_OPT_PREFIX.'csv', $csv);
				}
				
				if($when){
					if($this->increment){
						wp_schedule_event(strtotime($when), 'hourly', BMUCI_OPT_PREFIX.'scheduled_import');
					} else {
						wp_schedule_single_event(strtotime($when), BMUCI_OPT_PREFIX.'scheduled_import');
					}
				}
				
				unset($csv);
			}
			
			if($this->last_import){
				$matched = $previous_matched;
			}
			
			include(BMUCI_PATH.'views/importer.php');
		}
		
		
		/**
		 * BM_Ultimate_CSV_Importer::setup_vars()
		 * 
		 * @return void
		 */
		function setup_vars(){
			$this->import_type	= get_option(BMUCI_OPT_PREFIX.'import_type');
			$this->multisite	= get_option(BMUCI_OPT_PREFIX.'multisite');
			$this->defaults		= get_option(BMUCI_OPT_PREFIX.'defaults');
			$this->post_id		= get_option(BMUCI_OPT_PREFIX.'post_id');
			$this->unique		= get_option(BMUCI_OPT_PREFIX.'unique');
			$this->serialized	= get_option(BMUCI_OPT_PREFIX.'serialized');
			$this->increment	= get_option(BMUCI_OPT_PREFIX.'increment');
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_cron_import()
		 * 
		 * @return void
		 */
		function _cron_import(){
			
			$cron_log = 'Starting cron at '.date('Y-m-d H:i:s').'<br />';
			$this->setup_vars();
			
			$matched = get_option(BMUCI_OPT_PREFIX.'matched');
			$csv = get_option(BMUCI_OPT_PREFIX.'csv');
			
			if($csv && $matched){
				$data = $this->_parse_csv($csv);
				$cron_log .= 'Import data loaded.<br />';
				$last = get_option(BMUCI_OPT_PREFIX.'increment_last');
				if($last && $last >= $this->total_rows){
					$cron_log .= 'Incremental import complete.<br />';
					$this->_update_cron_log($cron_log);
					return;
				}
				$cron_log .= 'Starting import.<br />';
				$this->results = $this->_do_import($matched, $data);
				$cron_log .= 'Import ended.<br />';
				if($this->increment){
					$last = get_option(BMUCI_OPT_PREFIX.'increment_last');
					$cron_log .= 'Incremental Import ended at '.$last.' of '.$this->total_rows.' total records.<br />';
					if($last < $this->total_rows){
						$cron_log .= 'Scheduling the cron again to complete the job.<br />';
						wp_schedule_event(strtotime('now + 1 minute'), 'hourly', BMUCI_OPT_PREFIX.'scheduled_import');
					} else {
						$cron_log .= 'Incremental import complete.<br />';
						$this->increment = false;
						wp_unschedule_event($scheduled_cron, BMUCI_OPT_PREFIX.'scheduled_import');
					}
				}
				
				if(!$this->increment){
					$cron_log .= 'Resetting CSV option value.<br />';
					update_option(BMUCI_OPT_PREFIX.'csv', '');
				}
				unset($csv);
			}
			
			$cron_log .= 'Cron Finished.';
			$this->_update_cron_log($cron_log);
			exit();
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_update_cron_log()
		 * 
		 * @param string $cron_log
		 * @return void
		 */
		function _update_cron_log($cron_log=''){
			update_option(BMUCI_OPT_PREFIX.'cron_log', $cron_log);
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_build_value()
		 * 
		 * @param mixed $val
		 * @param mixed $row
		 * @param mixed $columns
		 * @return
		 */
		function _build_value($val, $row, $columns){
			$method = $val[0];
			$val = $val[1];
			
			if($method == 'VALUE'){
				return $val;
			} elseif($method == 'CONCAT') {
				foreach($columns as $col){
					if(strpos($val, '{'.$col.'}') !== false && isset($row[$col])){
						$val = $this->_token_replace($col, trim($row[$col]), $val);
					}
				}
			} elseif($method == 'SERIALIZE') {
				// do something here.
			}
			return $val;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_get_time()
		 * 
		 * @param bool $string
		 * @return
		 */
		function _get_time($string=true){
			if($string){
				$time = microtime();
				$time = ltrim(substr($time, 0, strpos($time, ' ')), '0');
				$time = date('Y-m-d H:i:s').$time;
				return $time;
			}
			return microtime(true);
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_do_import()
		 * 
		 * @param mixed $columns
		 * @param mixed $data
		 * @return
		 */
		function _do_import($columns, $data=array()){
			#$log = $this->_get_time().': Import Started.'."\n";
			do_action('bmuci_before_import', $this);
			
			$headings = $data['headings'];
			$rows = $data['rows'];
			
			$imported_data = array();
			
			if($this->import_type == 'posts'){
				$primary_table = 'posts';
			} elseif($this->import_type == 'users'){
				$primary_table = 'users';
			}
			
			$counter = 0;
			
			#$log .= $this->_get_time().': Check last increment value.'."\n";
			
			if($this->increment){
				$last = get_option(BMUCI_OPT_PREFIX.'increment_last');
				if(!$last) $last = 0;
				$this->start = $last+1;
				$this->end = $this->start + $this->increment;
			}
			#$log .= $this->_get_time().': Starting Loop.'."\n";
			
			foreach($rows as $row){ // loop through each row in CSV
				$import_data = array();
				$import_meta = array();
				
				$counter++;
				
				if($this->start && $counter < $this->start) continue;
				
				# start with all default values
				if(is_array($this->defaults) && count($this->defaults) > 0){
					foreach($this->defaults as $col => $default){
						if(!isset($import_data[$col])){
							$import_data[$col] = $default;
						}
					}
				}
				
				#$log .= $this->_get_time().': Building Columns.'."\n";
				
				# setup custom columns
				if(is_array($columns) && count($columns) > 0){
					foreach($columns as $ref => $val){
						if(is_array($val) && $ref){
							list($table, $column) = explode('.', $ref);
							$value = $this->_build_value($val, $row, $columns);
							if($table == 'posts' || $table == 'users'){
								$import_data[$column] = trim($value);
							} elseif($table == 'postmeta' || $table == 'usermeta'){
								$import_meta[$column] = trim($value);
							}
						}
					}
				}
				
				#$log .= $this->_get_time().': Matching Columns.'."\n";
				
				# loop through each column to match with database
				foreach($row as $key => $value){
					if(is_array($columns) && in_array($key, $columns) && ($value || $value === '0')){
						$db_col = array_search($key, $columns);
						if(strpos($db_col, '.') === false){
							$db_col = $this->_add_table_to_column($db_col);
						}
						
						list($table, $column) = explode('.', $db_col);
						if($key == $this->unique){
							$this->unique_key = $column;
						}
						if(in_array($key, $this->serialized)){
							$this->serialized_keys[$key] = $column;
						}
						if($table == 'posts' || $table == 'users'){
							$import_data[$column] = trim($value);
						} elseif($table == 'postmeta' || $table == 'usermeta'){
							$import_meta[$column] = trim($value);
						}
					}
				}
				
				if(count($import_data) > 0){
					#$log .= $this->_get_time().': Starting Insert.'."\n";
					$user_role = $import_data['user_role']; // do this before any filters
					
					$original_import_data = $import_data;
					$import_data = $this->_column_check($primary_table, $import_data);
					
					if($primary_table == 'posts'){
						#$log .= $this->_get_time().': Inserting post type.'."\n";
						$import_data = apply_filters('bmuci_post_data', $import_data, $row);
						
						if($this->unique){
							#$log .= $this->_get_time().': Unique enabled. Inserting or grabbing post ID.'."\n";
							$post_id = $this->get_unique($import_data, $import_meta);
						} else {
							#$log .= $this->_get_time().': Insert post type now.'."\n";
							// insert post first to get the ID
							$post_id = wp_insert_post($import_data);
							#$log .= $this->_get_time().': Insert post type done.'."\n";
						}
						
						if(is_numeric($post_id)){
							$imported_data = $row;
							$import_meta = apply_filters('bmuci_post_meta', $import_meta, $post_id, $row);
							#$log .= $this->_get_time().': Inserting post meta.'."\n";
							
							// insert post meta
							foreach($import_meta as $k => $v){
								if($v || $v === '0'){
									$v = apply_filters('bmuci_postmeta_value', $v, $post_id, $k, $row);
									
									if($this->unique){
										$this->store($post_id, $k, $v);
									} else {
										update_post_meta($post_id, $k, $v);
									}
								}
							}
							#$log .= $this->_get_time().': Post meta insert complete.'."\n";
							$this->finalize_storage($post_id);
							#$log .= $this->_get_time().': Post meta storage insert complete.'."\n";
							$this->total_imported++;
						} else {
							if(is_object($post_id)){
								$this->errors[] = 'ERROR on row '.$counter.': '.$post_id->get_error_message();
								$this->failed_imports++;
							}
						}
						#$log .= $this->_get_time().': End of loop. Starting over.'."\n";
						
					} elseif($primary_table == 'users'){
						$import_data = apply_filters('bmuci_user_import_data', $import_data, $row);
						
						if(!$import_data['user_email'] || is_email($import_data['user_email']) === false){
							$error = 'Some records were not imported due to invalid email address. ('.$import_data['user_email'].')';
							if(!in_array($error, $this->errors))
								 $this->errors[] = $error;
							$this->failed_imports++;
							continue;
						}
						
						// insert post first to get the ID
						$new_user = array(
							'user_login' => $import_data['user_login'],
							'user_pass' => $import_data['user_pass'],
							'user_email' => $import_data['user_email'],
							'role' => $user_role
						);
						
						$new_user = apply_filters('bmuci_insert_user', $new_user, $row);
						$user_id = wp_insert_user($new_user);
						
						unset($import_data['user_login']);
						unset($import_data['user_pass']);
						unset($import_data['user_email']);
						
						if(is_numeric($user_id)){
							
							// put the rest of the user data with the user
							if(count($import_data) > 0){
								$import_data['ID'] = $user_id;
								$import_data = apply_filters('bmuci_user_data', $import_data, $row);
								wp_update_user($import_data);
							}
							
							if(count($this->multisite)){
								foreach($this->multisite as $blog_id){
									add_user_to_blog($blog_id, $user_id, $user_role);
								}
							}
							
							// insert user meta
							foreach($import_meta as $k => $v){
								if($v || $v === '0'){
									$v = apply_filters('bmuci_usermeta_value', $v, $user_id, $k, $row);
									update_user_meta($user_id, $k, $v);
								}
							}
							
							$this->total_imported++;
						} else {
							if(is_object($user_id)){
								$this->errors[] = 'ERROR on row '.$counter.': '.$user_id->get_error_message().' ('.$import_data['user_login'].')';
								$this->failed_imports++;
							}
						}
					}
					
					#$log .= $this->_get_time().': Row Insertion complete.'."\n";
					$imported_data[] = $row;
					
					update_option(BMUCI_OPT_PREFIX.'increment_last', $counter);
					#$log .= $this->_get_time().': Last Increment value updated..'."\n";
					
					if($counter == 300) break;
					if($this->end && $counter >= $this->end) break;
					
				} elseif($this->import_type == 'other'){
					# let's just let the user define what needs to happen shall we.
					do_action('bmuci_custom_import', $this, $row);
					$imported_data[] = $row;
				} else {
					$this->failed_imports++;
				}
			}
			
			do_action('bmuci_after_import', $this);
			
			#$log .= $this->_get_time().': Import complete.'."\n";
			
			#$this->_update_cron_log($log);
			
			return $imported_data;
		}
		
		
		/**
		 * BM_Ultimate_CSV_Importer::store()
		 * 
		 * @param mixed $post_id
		 * @param mixed $meta_key
		 * @param mixed $meta_value
		 * @return void
		 */
		function store($post_id, $meta_key, $meta_value){
			if(!isset($this->storage[$post_id])){
				$this->storage[$post_id] = array();
			}
			if(!isset($this->storage[$post_id][$meta_key])){
				$this->storage[$post_id][$meta_key] = array($meta_value);
			} elseif($meta_key != $this->unique_key) {
				if(in_array($meta_key, $this->serialized_keys)){
					$this->storage[$post_id][$meta_key][] = $meta_value;
				}
			}
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::finalize_storage()
		 * 
		 * @return void
		 */
		function finalize_storage($post_id){
			$data = isset($this->storage[$post_id]) ? $this->storage[$post_id] : array();
			
			if($data){
				foreach($data as $key => $value){
					if(is_array($value) && count($value) == 1){
						$value = $value[0];
					}
					
					update_post_meta($post_id, $key, $value);
				}
			}
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::get_unique()
		 * 
		 * @param mixed $import_data
		 * @param mixed $import_meta
		 * @return
		 */
		function get_unique($import_data, $import_meta){
			$meta = false;
			if(isset($import_data[$this->unique_key])){
				$match = $import_data[$this->unique_key];
			} elseif(isset($import_meta[$this->unique_key])){
				$match = $import_meta[$this->unique_key];
				$meta = true;
			}
			
			foreach($this->storage as $post_id => $meta){
				foreach($meta as $key => $value){
					if($key == $this->unique_key && $value[0] == $match){
						return $post_id;
					}
				}
			}
			
			$args = array(
				'posts_per_page' => 1,
				'post_type' => $this->defaults['post_type']
			);
			if($meta){
				$args['meta_key'] = $this->unique_key;
				$args['meta_value'] = $match;
			} else { // for post data matching
				$args[$this->unique_key] = $match;
			}
			
			$get_unique = new WP_Query($args);
			if(count($get_unique->posts) > 0){
				return $get_unique->posts[0]->ID;
			}
			
			$post_id = wp_insert_post($import_data);
			
			return $post_id;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_match_columns()
		 * 
		 * @param mixed $data
		 * @param mixed $custom_columns
		 * @return
		 */
		function _match_columns($data, $custom_columns){
			if(!count($data)) return false;
			
			if($this->import_type == 'other') return true;
			
			$db_cols = isset($_POST['db_field']) ? $_POST['db_field'] : array();
			if(count($db_cols) > 0){
				$matched = array();
				$headings = $data['headings'];
				foreach($db_cols as $match => $key){
					if(is_array($key)) $key = 'postmeta.'.end($key);
					if(in_array($match, $headings)){
						$matched[$key] = $match;
					}
				}
				
				$matched = $this->_custom_columns($matched, $custom_columns);
				
				if(!$this->_required_field_check($matched)){
					return false;
				}
				
				return $matched;
			}
			return false;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_required_field_check()
		 * 
		 * @param mixed $matched
		 * @return
		 */
		function _required_field_check($matched){
			$requireds = array();
				
			if($this->import_type == 'posts'){
				$requireds = array(
					'posts.post_title' => 'post title'
				);
			} elseif($this->import_type == 'users'){
				$requireds = array(
					'users.user_login' => 'user login',
					'users.user_pass' => 'user pass',
					'users.user_email' => 'user email'
				);
			}
			
			foreach($requireds as $req => $text){
				if(!array_key_exists($req, $matched)){
					$this->errors[] = 'You did not specify a "'.$text.'" field. This field must be assigned to import data correctly.';
				}
			}
			
			if(count($this->errors) > 0) return false;
			
			return true;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_custom_columns()
		 * 
		 * @param mixed $matched
		 * @param mixed $custom_columns
		 * @return
		 */
		function _custom_columns($matched, $custom_columns){
			$cols = $custom_columns['col'];
			$methods = $custom_columns['method'];
			$values = $custom_columns['value'];
			
			$total_custom = count($cols);
			
			for($i=0; $i<$total_custom; $i++){
				$matched[$cols[$i]] = array($methods[$i], $values[$i]);
			}
			
			return $matched;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_db_field_select()
		 * 
		 * @param mixed $field_name
		 * @param bool $found
		 * @param mixed $matched
		 * @return
		 */
		function _db_field_select($field_name, &$found=false, $matched=array()){
			$db_fields = $this->_default_fields();
			$db_fields = array_merge($db_fields, $this->_custom_fields());
			
			$selected = $this->_match_post_val($field_name, $matched);
					
			$db_select = '<select name="db_field['.$field_name.']">
				<option value="">[NOT SET]</option>';
				foreach($db_fields as $k => $v){
					if($v == 'post_title'){
						$v = '* POST TITLE';
					} elseif($v == 'user_login'){
						$v = '* USER LOGIN';
					} elseif($v == 'user_email'){
						$v = '* USER EMAIL';
					} elseif($v == 'user_pass'){
						$v = '* USER PASSWORD';
					}
					$db_select .= '<option value="'.$k.'"';
					if($k == $selected){
						$found = true;
						$db_select .= ' selected="selected"';
					}
					$db_select .= '>'.$v.'</option>';
				}
				if($field_name != 'custom_col'){
					$db_select .= '<option value="__custom__"';
					if($selected && !$found){
						$db_select .= ' selected="selected"';
					}
					$db_select .= '>- CUSTOM META FIELD -</option>';
				}
			$db_select .= '</select>';
			
			return $db_select;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_match_post_val()
		 * 
		 * @param mixed $field_name
		 * @param mixed $matched
		 * @return
		 */
		function _match_post_val($field_name, $matched=array()){
			$arr = isset($_POST['db_field']) ? $_POST['db_field'] : $matched;
			if(is_array($arr) && count($arr) > 0){
				foreach($arr as $k => $v){
					if($k == $field_name){
						if(is_array($v)) $v = end($v);
						return $v;
					} elseif(!is_array($v) && $v == $field_name){
						return $k;
					}
				}
			}
			return NULL;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_default_fields()
		 * 
		 * @return
		 */
		function _default_fields(){
			if($this->_default_fields) return $this->_default_fields;
			
			global $wpdb;
			$cols = array();
			
			if($this->import_type == 'posts'){
				$key = 'posts';
				$query = 'SHOW FULL FIELDS FROM `'.$wpdb->posts.'`';
			} elseif($this->import_type == 'users'){
				$key = 'users';
				$query = 'SHOW FULL FIELDS FROM `'.$wpdb->users.'`';
			}
			if($query){
				$results = $wpdb->get_results($query);
				
				if(count($results)){
					foreach($results as $result){
						$cols[$key.'.'.$result->Field] = $result->Field;
					}
				}
			}
			$this->_default_fields = $cols;
			return $cols;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_custom_fields()
		 * 
		 * @return
		 */
		function _custom_fields(){
			if($this->_custom_fields) return $this->_custom_fields;
			
			global $wpdb;
			$fields = array();
			
			if($this->import_type == 'posts'){
				$key = 'postmeta';
				$query = 'SELECT DISTINCT `meta_key` FROM `'.$wpdb->postmeta.'` ORDER BY `meta_key`';
			} elseif($this->import_type == 'users') {
				$key = 'usermeta';
				$query = 'SELECT DISTINCT `meta_key` FROM `'.$wpdb->usermeta.'` ORDER BY `meta_key`';
			}
			
			if($query){
				$results = $wpdb->get_results($query);
				if(count($results)){
					foreach($results as $result){
						$fields[$key.'.'.$result->meta_key] = $result->meta_key;
					}
				}
			}
			
			// special custom fields
			//TODO: prevent this from being limited to only 1 value
			$fields['__featured_image__'] = 'FEATURED IMAGE';
			$fields['__attachment__'] = 'ATTACHMENT';
			
			$fields = apply_filters('bmuci_custom_fields', $fields);
			
			$this->_custom_fields = $fields;
			
			return $fields;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_handle_attachments()
		 * 
		 * @param mixed $import_meta
		 * @param mixed $post_id
		 * @param mixed $row
		 * @return
		 */
		function _handle_attachments($import_meta, $post_id, $row){
			foreach($import_meta as $key => &$value){
				if($key == '__featured_image__' || $key == '__attachment__'){
					$featured = ($key == '__featured_image__');
					if(filter_var($value, FILTER_VALIDATE_URL) !== FALSE
						#&& preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $value)
						#&& (bool)parse_url($value)
					){
						$filename = basename($value);
						$wp_filetype = wp_check_filetype($filename, null );
						if(!$wp_filetype['type']){
							$image_info = getimagesize($value);
							if(!$image_info){
								unset($import_meta[$key]);
								continue;
							}
							$wp_filetype['type'] = $image_info['mime'];
							
							list($type, $extension) = explode('/',$image_info['mime']);
							$extension = str_replace(array('jpeg'),array('jpg'), $extension);
							$filename = uniqid().'.'.$extension;
							
							
							$uploads_dir = wp_upload_dir();
							
							$file_placement = '/'.$this->admin_page.'/attachments';
							
							if(!is_dir($uploads_dir['basedir'].$file_placement)){
								mkdir($uploads_dir['basedir'].$file_placement, 0755);
							}
							$file_placement .= '/';
							$file = file_get_contents($value);
							file_put_contents($uploads_dir['basedir'].$file_placement.$filename, $file);
							$value = $uploads_dir['basedir'].$file_placement.$filename;
						}
						$attachment = array(
							'post_mime_type' => $wp_filetype['type'],
							'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
							'post_content' => '',
							'post_status' => 'inherit'
						);
						$attach_id = wp_insert_attachment( $attachment, $value, $post_id );
						if(!function_exists('wp_generate_attachment_metadata')){
							require_once(ABSPATH . 'wp-admin/includes/image.php');
						}
						$attach_data = wp_generate_attachment_metadata( $attach_id, $value );
						wp_update_attachment_metadata( $attach_id, $attach_data );
					
						if($featured){
							$ret = update_post_meta($post_id, '_thumbnail_id', $attach_id);
						}
					}
					unset($import_meta[$key]);
				}
			}
			return $import_meta;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_handle_upload()
		 * 
		 * @return
		 */
		function _handle_upload(){
			if(isset($_POST['uploaded_csv'])) return $_POST['uploaded_csv'];
			
			if(!isset($_FILES['csv'])) return NULL;
			
			$csv = $_FILES['csv'];
			
			if ($csv['error'] > 0) {
				$this->errors[] = 'ERROR: ' . $this->_upload_error($csv['error']);
				return false;
			} else {
				
				//if file already exists
				$uploads_dir = wp_upload_dir();
		
				$file_placement = '/'.$this->admin_page;
				
				if(!is_dir($uploads_dir['basedir'].$file_placement)){
					mkdir($uploads_dir['basedir'].$file_placement, 0755);
				}
				$file_placement .= '/';
				if (file_exists($uploads_dir['basedir'].$file_placement . $csv['name'])) {
					// warn the user the file is going to be overwritten.
				}
				
				//Store file in directory 'upload' with the name of 'uploaded_file.txt'
				move_uploaded_file($csv['tmp_name'], $uploads_dir['basedir'].$file_placement.$csv['name']);
				
				return array(
					'filename' => $csv['name'],
					'path' => $file_placement.$csv['name'],
					'url' => $uploads_dir['baseurl'].'/'.$this->admin_page.'/'.$csv['name']
				);
			}
			
			$this->errors[] = 'ERROR: There was a problem with your upload';
			return false;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_parse_csv()
		 * 
		 * @param mixed $csv
		 * @return
		 */
		function _parse_csv($csv=array()){
			if(!is_array($csv)) return $csv;
			$uploads_dir = wp_upload_dir();
			
			$path = $uploads_dir['basedir'].$csv['path'];
			
			if(file_exists($path) && $handle = fopen( $path , 'r')){
					
				$headings = array();
				$row_data = array();
				$i=0;
				
				while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
			        $cols = count($data);
			        $row = array();
			        for ($c=0; $c < $cols; $c++) {
			        	if($i == 0){
			            	$headings[] = $data[$c];
			            } else {
		            		$row[$headings[$c]] = $data[$c];
			            }
			        }
			        if($i > 0){
			        	$row_data[] = $row;
			        }
			        
			        $i++;
			    }
			    fclose($handle);
			    
			    $this->total_rows = count($row_data);
			    
			    return array('headings' => $headings, 'rows' => $row_data);
	  		}
	  		
	  		return false;
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_upload_error()
		 * 
		 * @param mixed $num
		 * @return
		 */
		function _upload_error($num){
			if($num == 4){
				return 'File missing.';
			}
			return 'An unknown error has occured ('.$num.')';
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_column_check()
		 * 
		 * @param mixed $table
		 * @param mixed $arr
		 * @return
		 */
		function _column_check($table, $arr=array()){
			if(count($arr) > 0){
				global $wpdb;
				
				$query = 'SHOW FULL FIELDS FROM `'.$wpdb->{$table}.'`';
				$results = $wpdb->get_results($query);
				
				$cols = array();
				foreach($results as $result){
					$cols[] = $result->Field;
				}
				
				foreach($arr as $col=>$v){
					if(strpos($col, '.') !== false){
						list($tbl, $col) = explode('.', $col);
					}
					if(!in_array($col, $cols)){
						unset($arr[$col]);
					}
				}
			}
			return $arr;
		}
		
		
		/**
		 * BM_Ultimate_CSV_Importer::_token_replace()
		 * 
		 * @param mixed $token
		 * @param mixed $replacement
		 * @param mixed $src
		 * @return
		 */
		function _token_replace($token, $replacement, $src){
			$pattern = "/\{".$token."\}/";
			return preg_replace($pattern, $replacement, $src);
		}
		
		/**
		 * BM_Ultimate_CSV_Importer::_add_table_to_column()
		 * 
		 * @param mixed $col
		 * @return
		 */
		function _add_table_to_column($col){
			if($this->import_type == 'posts'){
				$col = 'postmeta.'.$col;
			} elseif($this->import_type == 'users'){
				if($col == 'user_role'){
					$col = 'users.'.$col;
				} else {
					$col = 'usermeta.'.$col;
				}
			}
			return $col;
		}

		
		/**
		 * BM_Ultimate_CSV_Importer::get_blog_list()
		 * 
		 * @return
		 */
		function get_blog_list(){
			global $wpdb;
			$prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
			$sql = 'SELECT blog_id AS `id`, `domain`, `path` FROM '.$prefix.'blogs ORDER BY blog_id';
			$sites = $wpdb->get_results( $wpdb->prepare( $sql ) );
			
			return $sites;
		}
	}
	
}
=======
<?phpif(!class_exists('BM_Ultimate_CSV_Importer')){		class BM_Ultimate_CSV_Importer {				private $debug_mode = false;				var $admin_page = 'ultimate-csv-importer';				public $errors = array();		public $import_type = 'posts';		public $multisite = false;		public $post_id = NULL;		public $unique = NULL;		public $unique_key = NULL;		public $serialized = array();		public $serialized_keys = array();		public $last_import = 0;		public $defaults = array();				public $increment = NULL;		public $start = NULL;		public $end = NULL;				public $csv;				public $customization_methods = array();				private $_default_fields;		private $_custom_fields;				private $storage = array();				public $total_rows;		public $total_imported = 0;		public $failed_imports = 0;		public $results;				/**		 * BM_Ultimate_CSV_Importer::__construct()		 * 		 * @return void		 */		function __construct(){					}				/**		 * BM_Ultimate_CSV_Importer::initialize()		 * 		 * @return void		 */		function initialize(){			add_action( 'init', array($this, '_init'));		}				/**		 * BM_Ultimate_CSV_Importer::_init()		 * 		 * @return void		 */		function _init(){			do_action('bmuci_before_init', $this);						ini_set('memory_limit', '-1');			set_time_limit(600); // 10 minutes			ini_set('auto_detect_line_endings', true);						add_action(BMUCI_OPT_PREFIX.'scheduled_import', array($this, '_cron_import'));						if(is_admin()){				add_action('admin_menu', array($this, '_admin_menu'));								// add css/js file for admin				wp_register_style(BMUCI_OPT_PREFIX.'admin', BMUCI_DIR.'css/bmuci-admin.css', array(), BMUCI_VERSION);				//TODO import uploadify				wp_register_script(BMUCI_OPT_PREFIX.'admin', BMUCI_DIR.'js/bmuci-admin.js', array('jquery'), BMUCI_VERSION);								if(function_exists('register_importer')){					register_importer('importer-bmuci', BMUCI_PI_NAME, BMUCI_PI_DESCRIPTION, array($this, 'register_importer_callback'));				} else {					$locale = get_locale();					$popular_importers = get_site_transient( 'popular_importers_' . $locale );					if(!isset($popular_importers['importers']['importer-bmuci'])){						$popular_importers['importers']['importer-bmuci'] = array(							'name' => BMUCI_PI_NAME,							'description' => BMUCI_PI_DESCRIPTION,							'plugin-slug' => 'bmuci-importer',							'importer-id' => 'importer-bmuci'						);						set_site_transient( 'popular_importers_' . $locale, $popular_importers, 2 * DAY_IN_SECONDS );						// wp-admin/plugin-install.php?tab=plugin-information&plugin=bmuci-importer					}					if(isset($_GET['tab']) && $_GET['tab'] == 'plugin-information' && isset($_GET['plugin']) && $_GET['plugin'] == 'bmuci-importer'){						$this->register_importer_callback(true);					}				}							}						$this->customization_methods = apply_filters('bmuci_customization_methods', array(				'CONCAT' => 'Concatenation',				'SERIALIZE' => 'Serialize',				'VALUE' => 'Custom Value'			));						add_filter('bmuci_post_meta', array($this, '_handle_attachments'), 10, 3);						do_action('bmuci_after_init', $this);		}				/**		 * BM_Ultimate_CSV_Importer::_admin_menu()		 * 		 * @return void		 */		function _admin_menu(){			add_submenu_page('tools.php', BMUCI_PI_NAME, BMUCI_PI_NAME, 8, $this->admin_page, array($this, 'importer'));		}				/**		 * BM_Ultimate_CSV_Importer::register_importer_callback()		 * 		 * @return void		 */		function register_importer_callback($js=false){			$url = admin_url().'tools.php?page='.$this->admin_page;			if($js){				echo '<script type="text/javascript">window.top.location.href = "'.$url.'";</script>';				exit();			} else {				wp_redirect($url);			}		}				/**		 * BM_Ultimate_CSV_Importer::importer()		 * 		 * @return void		 */		function importer(){			wp_enqueue_style(BMUCI_OPT_PREFIX.'admin');			wp_enqueue_script(BMUCI_OPT_PREFIX.'admin');						$processed = false;						$csv = $this->_handle_upload();			$this->csv = $csv;						$this->last_import = isset($_POST['bmuci_last_import']) ? $_POST['bmuci_last_import'] : $this->last_import;						$previous_matched = get_option(BMUCI_OPT_PREFIX.'matched', array());			$has_previous = ($previous_matched) ? true : false;						if($this->last_import){				$this->setup_vars();								$custom_columns = array();				if(is_array($previous_matched) && count($previous_matched) > 0){					foreach($previous_matched as $pm => $val){						if(is_array($val)){							$custom_columns['col'][] = $pm;							$custom_columns['method'][] = $val[0];							$custom_columns['value'][] = $val[1];							unset($previous_matched[$pm]);						}					}				}			} else {				$this->import_type	= isset($_POST['bmuci_import_type']) ? $_POST['bmuci_import_type'] : $this->import_type;				$this->multisite	= isset($_POST['bmuci_multisite']) ? $_POST['bmuci_multisite'] : array();				$this->post_id		= isset($_POST['bmuci_id']) ? $_POST['bmuci_id'] : NULL;				$this->unique		= isset($_POST['bmuci_unique']) ? $_POST['bmuci_unique'] : NULL;				$this->serialized	= isset($_POST['bmuci_serialize']) ? $_POST['bmuci_serialize'] : array();				$this->increment	= isset($_POST['bmuci_increment']) ? $_POST['bmuci_increment'] : $this->increment;								$custom_columns = isset($_POST['custom_columns']) ? $_POST['custom_columns'] : array();								if($this->import_type != 'other'){					$this->defaults = array(						'post_type' => isset($_POST['bmuci_post_type']) && $_POST['bmuci_post_type'] != '__custom__' ? $_POST['bmuci_post_type'] : (isset($_POST['bmuci_custom_post_type']) && $_POST['bmuci_custom_post_type'] ? $_POST['bmuci_custom_post_type'] : 'post'),						'post_status' => isset($_POST['bmuci_post_status']) ? $_POST['bmuci_post_status'] : 'publish',						'comment_status' => isset($_POST['bmuci_comment_status']) ? $_POST['bmuci_comment_status'] : 'open',						'ping_status' => isset($_POST['bmuci_ping_status']) ? $_POST['bmuci_ping_status'] : 'open',						'post_author' => isset($_POST['bmuci_post_author']) ? $_POST['bmuci_post_author'] : NULL,						'user_role' => isset($_POST['bmuci_user_role']) ? $_POST['bmuci_user_role'] : 'subscriber'					);				}			}						$schedule = isset($_POST['bmuci_schedule']) ? $_POST['bmuci_schedule'] : 'now';			$when = isset($_POST['bmuci_schedule_datetime']) ? $_POST['bmuci_schedule_datetime'] : '';						$data = $this->_parse_csv($csv);						$matched = $this->_match_columns($data, $custom_columns);						if($matched){				if($schedule == 'now'){					$this->results = $this->_do_import($matched, $data);					if($this->total_imported){						$processed = 'success';						$success_message = 'Congratulations! '.$this->total_imported.' total records (out of '.$this->total_rows.') were imported!';											} else {						$this->errors[] = 'Sorry but there must have been a problem with your data. Nothing got imported.';					}					if($this->failed_imports){						$this->errors[] = $this->failed_imports.' records weren\'t imported because of missing required fields.';					}				} elseif($schedule == 'incremental'){					$when = 'now + 1 minute';					$processed = 'success';					$success_message = 'Your import will start momentarily.';				} else {					$processed = 'success';					$success_message = 'Your import has been successfully scheduled.';				}												if($matched === true) $matched = NULL;								// store data for future imports				update_option(BMUCI_OPT_PREFIX.'import_type', $this->import_type);				update_option(BMUCI_OPT_PREFIX.'defaults', $this->defaults);				update_option(BMUCI_OPT_PREFIX.'multisite', $this->multisite);				update_option(BMUCI_OPT_PREFIX.'post_id', $this->post_id);				update_option(BMUCI_OPT_PREFIX.'unique', $this->unique);				update_option(BMUCI_OPT_PREFIX.'serialized', $this->serialized);				update_option(BMUCI_OPT_PREFIX.'increment', $this->increment);				update_option(BMUCI_OPT_PREFIX.'matched', $matched);								if($schedule != 'now'){					update_option(BMUCI_OPT_PREFIX.'csv', $csv);				}								if($when){					if($this->increment){						wp_schedule_event(strtotime($when), 'hourly', BMUCI_OPT_PREFIX.'scheduled_import');					} else {						wp_schedule_single_event(strtotime($when), BMUCI_OPT_PREFIX.'scheduled_import');					}				}								unset($csv);				$this->csv = NULL;			}						if($this->last_import){				$matched = $previous_matched;			}						include(BMUCI_PATH.'views/importer.php');		}						/**		 * BM_Ultimate_CSV_Importer::setup_vars()		 * 		 * @return void		 */		function setup_vars(){			$this->import_type	= get_option(BMUCI_OPT_PREFIX.'import_type');			$this->multisite	= get_option(BMUCI_OPT_PREFIX.'multisite');			$this->defaults		= get_option(BMUCI_OPT_PREFIX.'defaults');			$this->post_id		= get_option(BMUCI_OPT_PREFIX.'post_id');			$this->unique		= get_option(BMUCI_OPT_PREFIX.'unique');			$this->serialized	= get_option(BMUCI_OPT_PREFIX.'serialized');			$this->increment	= get_option(BMUCI_OPT_PREFIX.'increment');		}				/**		 * BM_Ultimate_CSV_Importer::_cron_import()		 * 		 * @return void		 */		function _cron_import(){						$cron_log = 'Starting cron at '.date('Y-m-d H:i:s').'<br />';			$this->setup_vars();						$matched = get_option(BMUCI_OPT_PREFIX.'matched');			$csv = get_option(BMUCI_OPT_PREFIX.'csv');			$this->csv = $csv;						if($csv && $matched){				$data = $this->_parse_csv($csv);				$cron_log .= 'Import data loaded.<br />';				$last = get_option(BMUCI_OPT_PREFIX.'increment_last');				if($last && $last >= $this->total_rows){					$cron_log .= 'Incremental import complete.<br />';					$this->_update_cron_log($cron_log);					return;				}				$cron_log .= 'Starting import.<br />';				$this->results = $this->_do_import($matched, $data);				$cron_log .= 'Import ended.<br />';				if($this->increment){					$last = get_option(BMUCI_OPT_PREFIX.'increment_last');					$cron_log .= 'Incremental Import ended at '.$last.' of '.$this->total_rows.' total records.<br />';					if($last < $this->total_rows){						$cron_log .= 'Scheduling the cron again to complete the job.<br />';						wp_schedule_event(strtotime('now + 1 minute'), 'hourly', BMUCI_OPT_PREFIX.'scheduled_import');					} else {						$cron_log .= 'Incremental import complete.<br />';						$this->increment = false;						wp_unschedule_event($scheduled_cron, BMUCI_OPT_PREFIX.'scheduled_import');					}				}								if(!$this->increment){					$cron_log .= 'Resetting CSV option value.<br />';					update_option(BMUCI_OPT_PREFIX.'csv', '');				}				unset($csv);				$this->csv = NULL;			}						$cron_log .= 'Cron Finished.';			$this->_update_cron_log($cron_log);			exit();		}				/**		 * BM_Ultimate_CSV_Importer::_update_cron_log()		 * 		 * @param string $cron_log		 * @return void		 */		function _update_cron_log($cron_log=''){			update_option(BMUCI_OPT_PREFIX.'cron_log', $cron_log);		}				/**		 * BM_Ultimate_CSV_Importer::_build_value()		 * 		 * @param mixed $val		 * @param mixed $row		 * @param mixed $columns		 * @return		 */		function _build_value($val, $row, $columns){			$method = $val[0];			$val = $val[1];						if($method == 'VALUE'){				return $val;			} elseif($method == 'CONCAT') {				foreach($columns as $col){					if(strpos($val, '{'.$col.'}') !== false && isset($row[$col])){						$val = $this->_token_replace($col, trim($row[$col]), $val);					}				}			} elseif($method == 'SERIALIZE') {				// do something here.			}			return $val;		}				/**		 * BM_Ultimate_CSV_Importer::_get_time()		 * 		 * @param bool $string		 * @return		 */		function _get_time($string=true){			if($string){				$time = microtime();				$time = ltrim(substr($time, 0, strpos($time, ' ')), '0');				$time = date('Y-m-d H:i:s').$time;				return $time;			}			return microtime(true);		}				/**		 * BM_Ultimate_CSV_Importer::_do_import()		 * 		 * @param mixed $columns		 * @param mixed $data		 * @return		 */		function _do_import($columns, $data=array()){			#$log = $this->_get_time().': Import Started.'."\n";			do_action('bmuci_before_import', $this);						$headings = $data['headings'];			$rows = $data['rows'];						$imported_data = array();						if($this->import_type == 'posts'){				$primary_table = 'posts';			} elseif($this->import_type == 'users'){				$primary_table = 'users';			}						$counter = 0;						#$log .= $this->_get_time().': Check last increment value.'."\n";						if($this->increment){				$last = get_option(BMUCI_OPT_PREFIX.'increment_last');				if(!$last) $last = 0;				$this->start = $last+1;				$this->end = $this->start + $this->increment;			}			#$log .= $this->_get_time().': Starting Loop.'."\n";						foreach($rows as $row){ // loop through each row in CSV				$import_data = array();				$import_meta = array();								$counter++;								if($this->start && $counter < $this->start) continue;								# start with all default values				if(is_array($this->defaults) && count($this->defaults) > 0){					foreach($this->defaults as $col => $default){						if(!isset($import_data[$col])){							$import_data[$col] = $default;						}					}				}								#$log .= $this->_get_time().': Building Columns.'."\n";								# setup custom columns				if(is_array($columns) && count($columns) > 0){					foreach($columns as $ref => $val){						if(is_array($val) && $ref){							list($table, $column) = explode('.', $ref);							$value = $this->_build_value($val, $row, $columns);							if($table == 'posts' || $table == 'users'){								$import_data[$column] = trim($value);							} elseif($table == 'postmeta' || $table == 'usermeta'){								$import_meta[$column] = trim($value);							}						}					}				}								#$log .= $this->_get_time().': Matching Columns.'."\n";								# loop through each column to match with database				foreach($row as $key => $value){					if(is_array($columns) && in_array($key, $columns) && ($value || $value === '0')){						$db_col = array_search($key, $columns);						if(strpos($db_col, '.') === false){							$db_col = $this->_add_table_to_column($db_col);						}												list($table, $column) = explode('.', $db_col);						if($key == $this->unique){							$this->unique_key = $column;						}						if(in_array($key, $this->serialized)){							$this->serialized_keys[$key] = $column;						}						if($table == 'posts' || $table == 'users'){							$import_data[$column] = trim($value);						} elseif($table == 'postmeta' || $table == 'usermeta'){							$import_meta[$column] = trim($value);						}					}				}								if(count($import_data) > 0){					#$log .= $this->_get_time().': Starting Insert.'."\n";					$user_role = $import_data['user_role']; // do this before any filters										$original_import_data = $import_data;					$import_data = $this->_column_check($primary_table, $import_data);										if($primary_table == 'posts'){						#$log .= $this->_get_time().': Inserting post type.'."\n";						$import_data = apply_filters('bmuci_post_data', $import_data, $row, $this);												if($this->unique){							#$log .= $this->_get_time().': Unique enabled. Inserting or grabbing post ID.'."\n";							$post_id = $this->get_unique($import_data, $import_meta);						} else {							#$log .= $this->_get_time().': Insert post type now.'."\n";							// insert post first to get the ID							$post_id = wp_insert_post($import_data);							#$log .= $this->_get_time().': Insert post type done.'."\n";						}												if(is_numeric($post_id)){							$imported_data = $row;							$import_meta = apply_filters('bmuci_post_meta', $import_meta, $post_id, $row, $this);							#$log .= $this->_get_time().': Inserting post meta.'."\n";														// insert post meta							foreach($import_meta as $k => $v){								if($v || $v === '0'){									$v = apply_filters('bmuci_postmeta_value', $v, $post_id, $k, $row, $this);																		if($this->unique){										$this->store($post_id, $k, $v);									} else {										update_post_meta($post_id, $k, $v);									}								}							}							#$log .= $this->_get_time().': Post meta insert complete.'."\n";							$this->finalize_storage($post_id);							#$log .= $this->_get_time().': Post meta storage insert complete.'."\n";							$this->total_imported++;						} else {							if(is_object($post_id)){								$this->errors[] = 'ERROR on row '.$counter.': '.$post_id->get_error_message();								$this->failed_imports++;							}						}						#$log .= $this->_get_time().': End of loop. Starting over.'."\n";											} elseif($primary_table == 'users'){						$import_data = apply_filters('bmuci_user_import_data', $import_data, $row, $this);												if(!$import_data['user_email'] || is_email($import_data['user_email']) === false){							$error = 'Some records were not imported due to invalid email address. ('.$import_data['user_email'].')';							if(!in_array($error, $this->errors))								 $this->errors[] = $error;							$this->failed_imports++;							continue;						}												// insert post first to get the ID						$new_user = array(							'user_login' => $import_data['user_login'],							'user_pass' => $import_data['user_pass'],							'user_email' => $import_data['user_email'],							'role' => $user_role						);												$new_user = apply_filters('bmuci_insert_user', $new_user, $row, $this);						$user_id = wp_insert_user($new_user);												unset($import_data['user_login']);						unset($import_data['user_pass']);						unset($import_data['user_email']);												if(is_numeric($user_id)){														// put the rest of the user data with the user							if(count($import_data) > 0){								$import_data['ID'] = $user_id;								$import_data = apply_filters('bmuci_user_data', $import_data, $row, $this);								wp_update_user($import_data);							}														if(count($this->multisite)){								foreach($this->multisite as $blog_id){									add_user_to_blog($blog_id, $user_id, $user_role);								}							}														// insert user meta							foreach($import_meta as $k => $v){								if($v || $v === '0'){									$v = apply_filters('bmuci_usermeta_value', $v, $user_id, $k, $row, $this);									update_user_meta($user_id, $k, $v);								}							}														$this->total_imported++;						} else {							if(is_object($user_id)){								$this->errors[] = 'ERROR on row '.$counter.': '.$user_id->get_error_message().' ('.$import_data['user_login'].')';								$this->failed_imports++;							}						}					}										#$log .= $this->_get_time().': Row Insertion complete.'."\n";					$imported_data[] = $row;										update_option(BMUCI_OPT_PREFIX.'increment_last', $counter);					#$log .= $this->_get_time().': Last Increment value updated..'."\n";										if($counter == 300) break;					if($this->end && $counter >= $this->end) break;									} elseif($this->import_type == 'other'){					# let's just let the user define what needs to happen shall we.					do_action('bmuci_custom_import', $this, $row);					$imported_data[] = $row;				} else {					$this->failed_imports++;				}			}						do_action('bmuci_after_import', $this);						#$log .= $this->_get_time().': Import complete.'."\n";						#$this->_update_cron_log($log);						return $imported_data;		}						/**		 * BM_Ultimate_CSV_Importer::store()		 * 		 * @param mixed $post_id		 * @param mixed $meta_key		 * @param mixed $meta_value		 * @return void		 */		function store($post_id, $meta_key, $meta_value){			if(!isset($this->storage[$post_id])){				$this->storage[$post_id] = array();			}			if(!isset($this->storage[$post_id][$meta_key])){				$this->storage[$post_id][$meta_key] = array($meta_value);			} elseif($meta_key != $this->unique_key) {				if(in_array($meta_key, $this->serialized_keys)){					$this->storage[$post_id][$meta_key][] = $meta_value;				}			}		}				/**		 * BM_Ultimate_CSV_Importer::finalize_storage()		 * 		 * @return void		 */		function finalize_storage($post_id){			$data = isset($this->storage[$post_id]) ? $this->storage[$post_id] : array();						if($data){				foreach($data as $key => $value){					if(is_array($value) && count($value) == 1){						$value = $value[0];					}										update_post_meta($post_id, $key, $value);				}			}		}				/**		 * BM_Ultimate_CSV_Importer::get_unique()		 * 		 * @param mixed $import_data		 * @param mixed $import_meta		 * @return		 */		function get_unique($import_data, $import_meta){			$meta = false;			if(isset($import_data[$this->unique_key])){				$match = $import_data[$this->unique_key];			} elseif(isset($import_meta[$this->unique_key])){				$match = $import_meta[$this->unique_key];				$meta = true;			}						foreach($this->storage as $post_id => $meta){				foreach($meta as $key => $value){					if($key == $this->unique_key && $value[0] == $match){						return $post_id;					}				}			}						$args = array(				'posts_per_page' => 1,				'post_type' => $this->defaults['post_type']			);			if($meta){				$args['meta_key'] = $this->unique_key;				$args['meta_value'] = $match;			} else { // for post data matching				$args[$this->unique_key] = $match;			}						$get_unique = new WP_Query($args);			if(count($get_unique->posts) > 0){				return $get_unique->posts[0]->ID;			}						$post_id = wp_insert_post($import_data);						return $post_id;		}				/**		 * BM_Ultimate_CSV_Importer::_match_columns()		 * 		 * @param mixed $data		 * @param mixed $custom_columns		 * @return		 */		function _match_columns($data, $custom_columns){			if(!count($data)) return false;						if($this->import_type == 'other') return true;						$db_cols = isset($_POST['db_field']) ? $_POST['db_field'] : array();			if(count($db_cols) > 0){				$matched = array();				$headings = $data['headings'];				foreach($db_cols as $match => $key){					if(is_array($key)) $key = 'postmeta.'.end($key);					if(in_array($match, $headings)){						$matched[$key] = $match;					}				}								$matched = $this->_custom_columns($matched, $custom_columns);								if(!$this->_required_field_check($matched)){					return false;				}								return $matched;			}			return false;		}				/**		 * BM_Ultimate_CSV_Importer::_required_field_check()		 * 		 * @param mixed $matched		 * @return		 */		function _required_field_check($matched){			$requireds = array();							if($this->import_type == 'posts'){				$requireds = array(					'posts.post_title' => 'post title'				);			} elseif($this->import_type == 'users'){				$requireds = array(					'users.user_login' => 'user login',					'users.user_pass' => 'user pass',					'users.user_email' => 'user email'				);			}						foreach($requireds as $req => $text){				if(!array_key_exists($req, $matched)){					$this->errors[] = 'You did not specify a "'.$text.'" field. This field must be assigned to import data correctly.';				}			}						if(count($this->errors) > 0) return false;						return true;		}				/**		 * BM_Ultimate_CSV_Importer::_custom_columns()		 * 		 * @param mixed $matched		 * @param mixed $custom_columns		 * @return		 */		function _custom_columns($matched, $custom_columns){			$cols = $custom_columns['col'];			$methods = $custom_columns['method'];			$values = $custom_columns['value'];						$total_custom = count($cols);						for($i=0; $i<$total_custom; $i++){				$matched[$cols[$i]] = array($methods[$i], $values[$i]);			}						return $matched;		}				/**		 * BM_Ultimate_CSV_Importer::_db_field_select()		 * 		 * @param mixed $field_name		 * @param bool $found		 * @param mixed $matched		 * @return		 */		function _db_field_select($field_name, &$found=false, $matched=array()){			$db_fields = $this->_default_fields();			$db_fields = array_merge($db_fields, $this->_custom_fields());						$selected = $this->_match_post_val($field_name, $matched);								$db_select = '<select name="db_field['.$field_name.']">				<option value="">[NOT SET]</option>';				foreach($db_fields as $k => $v){					if($v == 'post_title'){						$v = '* POST TITLE';					} elseif($v == 'user_login'){						$v = '* USER LOGIN';					} elseif($v == 'user_email'){						$v = '* USER EMAIL';					} elseif($v == 'user_pass'){						$v = '* USER PASSWORD';					}					$db_select .= '<option value="'.$k.'"';					if($k == $selected){						$found = true;						$db_select .= ' selected="selected"';					}					$db_select .= '>'.$v.'</option>';				}				if($field_name != 'custom_col'){					$db_select .= '<option value="__custom__"';					if($selected && !$found){						$db_select .= ' selected="selected"';					}					$db_select .= '>- CUSTOM META FIELD -</option>';				}			$db_select .= '</select>';						return $db_select;		}				/**		 * BM_Ultimate_CSV_Importer::_match_post_val()		 * 		 * @param mixed $field_name		 * @param mixed $matched		 * @return		 */		function _match_post_val($field_name, $matched=array()){			$arr = isset($_POST['db_field']) ? $_POST['db_field'] : $matched;			if(is_array($arr) && count($arr) > 0){				foreach($arr as $k => $v){					if($k == $field_name){						if(is_array($v)) $v = end($v);						return $v;					} elseif(!is_array($v) && $v == $field_name){						return $k;					}				}			}			return NULL;		}				/**		 * BM_Ultimate_CSV_Importer::_default_fields()		 * 		 * @return		 */		function _default_fields(){			if($this->_default_fields) return $this->_default_fields;						global $wpdb;			$cols = array();						if($this->import_type == 'posts'){				$key = 'posts';				$query = 'SHOW FULL FIELDS FROM `'.$wpdb->posts.'`';			} elseif($this->import_type == 'users'){				$key = 'users';				$query = 'SHOW FULL FIELDS FROM `'.$wpdb->users.'`';			}			if($query){				$results = $wpdb->get_results($query);								if(count($results)){					foreach($results as $result){						$cols[$key.'.'.$result->Field] = $result->Field;					}				}			}			$this->_default_fields = $cols;			return $cols;		}				/**		 * BM_Ultimate_CSV_Importer::_custom_fields()		 * 		 * @return		 */		function _custom_fields(){			if($this->_custom_fields) return $this->_custom_fields;						global $wpdb;			$fields = array();						if($this->import_type == 'posts'){				$key = 'postmeta';				$query = 'SELECT DISTINCT `meta_key` FROM `'.$wpdb->postmeta.'` ORDER BY `meta_key`';			} elseif($this->import_type == 'users') {				$key = 'usermeta';				$query = 'SELECT DISTINCT `meta_key` FROM `'.$wpdb->usermeta.'` ORDER BY `meta_key`';			}						if($query){				$results = $wpdb->get_results($query);				if(count($results)){					foreach($results as $result){						$fields[$key.'.'.$result->meta_key] = $result->meta_key;					}				}			}						// special custom fields			//TODO: prevent this from being limited to only 1 value			$fields['__featured_image__'] = 'FEATURED IMAGE';			$fields['__attachment__'] = 'ATTACHMENT';						$fields = apply_filters('bmuci_custom_fields', $fields);						$this->_custom_fields = $fields;						return $fields;		}				/**		 * BM_Ultimate_CSV_Importer::_handle_attachments()		 * 		 * @param mixed $import_meta		 * @param mixed $post_id		 * @param mixed $row		 * @return		 */		function _handle_attachments($import_meta, $post_id, $row){			foreach($import_meta as $key => &$value){				if($key == '__featured_image__' || $key == '__attachment__'){					$featured = ($key == '__featured_image__');					if(filter_var($value, FILTER_VALIDATE_URL) !== FALSE						#&& preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $value)						#&& (bool)parse_url($value)					){						$filename = basename($value);						$wp_filetype = wp_check_filetype($filename, null );						if(!$wp_filetype['type']){							$image_info = getimagesize($value);							if(!$image_info){								unset($import_meta[$key]);								continue;							}							$wp_filetype['type'] = $image_info['mime'];														list($type, $extension) = explode('/',$image_info['mime']);							$extension = str_replace(array('jpeg'),array('jpg'), $extension);							$filename = uniqid().'.'.$extension;																					$uploads_dir = wp_upload_dir();														$file_placement = '/'.$this->admin_page.'/attachments';														if(!is_dir($uploads_dir['basedir'].$file_placement)){								mkdir($uploads_dir['basedir'].$file_placement, 0755);							}							$file_placement .= '/';							$file = file_get_contents($value);							file_put_contents($uploads_dir['basedir'].$file_placement.$filename, $file);							$value = $uploads_dir['basedir'].$file_placement.$filename;						}						$attachment = array(							'post_mime_type' => $wp_filetype['type'],							'post_title' => preg_replace('/\.[^.]+$/', '', $filename),							'post_content' => '',							'post_status' => 'inherit'						);						$attach_id = wp_insert_attachment( $attachment, $value, $post_id );						if(!function_exists('wp_generate_attachment_metadata')){							require_once(ABSPATH . 'wp-admin/includes/image.php');						}						$attach_data = wp_generate_attachment_metadata( $attach_id, $value );						wp_update_attachment_metadata( $attach_id, $attach_data );											if($featured){							$ret = update_post_meta($post_id, '_thumbnail_id', $attach_id);						}					}					unset($import_meta[$key]);				}			}			return $import_meta;		}				/**		 * BM_Ultimate_CSV_Importer::_handle_upload()		 * 		 * @return		 */		function _handle_upload(){			if(isset($_POST['uploaded_csv'])) return $_POST['uploaded_csv'];						if(!isset($_FILES['csv'])) return NULL;						$csv = $_FILES['csv'];						if ($csv['error'] > 0) {				$this->errors[] = 'ERROR: ' . $this->_upload_error($csv['error']);				return false;			} else {								//if file already exists				$uploads_dir = wp_upload_dir();						$file_placement = '/'.$this->admin_page;								if(!is_dir($uploads_dir['basedir'].$file_placement)){					mkdir($uploads_dir['basedir'].$file_placement, 0755);				}				$file_placement .= '/';				if (file_exists($uploads_dir['basedir'].$file_placement . $csv['name'])) {					// warn the user the file is going to be overwritten.				}								//Store file in directory 'upload' with the name of 'uploaded_file.txt'				move_uploaded_file($csv['tmp_name'], $uploads_dir['basedir'].$file_placement.$csv['name']);								return array(					'filename' => $csv['name'],					'path' => $file_placement.$csv['name'],					'url' => $uploads_dir['baseurl'].'/'.$this->admin_page.'/'.$csv['name']				);			}						$this->errors[] = 'ERROR: There was a problem with your upload';			return false;		}				/**		 * BM_Ultimate_CSV_Importer::_parse_csv()		 * 		 * @param mixed $csv		 * @return		 */		function _parse_csv($csv=array()){			if(!is_array($csv)) return $csv;			$uploads_dir = wp_upload_dir();						$path = $uploads_dir['basedir'].$csv['path'];						if(file_exists($path) && $handle = fopen( $path , 'r')){									$headings = array();				$row_data = array();				$i=0;								while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {			        $cols = count($data);			        $row = array();			        for ($c=0; $c < $cols; $c++) {			        	if($i == 0){			            	$headings[] = $data[$c];			            } else {		            		$row[$headings[$c]] = $data[$c];			            }			        }			        if($i > 0){			        	$row_data[] = $row;			        }			        			        $i++;			    }			    fclose($handle);			    			    $this->total_rows = count($row_data);			    			    return array('headings' => $headings, 'rows' => $row_data);	  		}	  			  		return false;		}				/**		 * BM_Ultimate_CSV_Importer::_upload_error()		 * 		 * @param mixed $num		 * @return		 */		function _upload_error($num){			if($num == 4){				return 'File missing.';			}			return 'An unknown error has occured ('.$num.')';		}				/**		 * BM_Ultimate_CSV_Importer::_column_check()		 * 		 * @param mixed $table		 * @param mixed $arr		 * @return		 */		function _column_check($table, $arr=array()){			if(count($arr) > 0){				global $wpdb;								$query = 'SHOW FULL FIELDS FROM `'.$wpdb->{$table}.'`';				$results = $wpdb->get_results($query);								$cols = array();				foreach($results as $result){					$cols[] = $result->Field;				}								foreach($arr as $col=>$v){					if(strpos($col, '.') !== false){						list($tbl, $col) = explode('.', $col);					}					if(!in_array($col, $cols)){						unset($arr[$col]);					}				}			}			return $arr;		}						/**		 * BM_Ultimate_CSV_Importer::_token_replace()		 * 		 * @param mixed $token		 * @param mixed $replacement		 * @param mixed $src		 * @return		 */		function _token_replace($token, $replacement, $src){			$pattern = "/\{".$token."\}/";			return preg_replace($pattern, $replacement, $src);		}				/**		 * BM_Ultimate_CSV_Importer::_add_table_to_column()		 * 		 * @param mixed $col		 * @return		 */		function _add_table_to_column($col){			if($this->import_type == 'posts'){				$col = 'postmeta.'.$col;			} elseif($this->import_type == 'users'){				if($col == 'user_role'){					$col = 'users.'.$col;				} else {					$col = 'usermeta.'.$col;				}			}			return $col;		}				/**		 * BM_Ultimate_CSV_Importer::get_blog_list()		 * 		 * @return		 */		function get_blog_list(){			global $wpdb;			$prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;			$sql = 'SELECT blog_id AS `id`, `domain`, `path` FROM '.$prefix.'blogs ORDER BY blog_id';			$sites = $wpdb->get_results( $wpdb->prepare( $sql ) );						return $sites;		}	}	}
>>>>>>> 51ba784e3cef9bcb0ef6f3292d1d86c6e935fbe8

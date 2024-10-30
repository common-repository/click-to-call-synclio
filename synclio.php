<?php include_once( ABSPATH . 'wp-admin/includes/plugin.php' ); ?>
<?php 
		/*
		Plugin Name: Synclio Click to Call, Chat, Connect
		Plugin URI: http://www.synclio.com
		Description: Plugin for displaying Synclio's click to call, chat, and connect widget on your Wordpress blog.
		Author: S-Dog
		Version: 2.1
		Author URI: http://www.synclio.com
		*/
defined( 'synclio__API_BASE' ) or define( 'synclio__API_BASE', 'https://synclio.wordpress.com/synclio.' );
define( 'synclio__API_VERSION', 1 );
define( 'synclio__MINIMUM_WP_VERSION', '3.3' );
defined( 'synclio_CLIENT__AUTH_LOCATION' ) or define( 'synclio_CLIENT__AUTH_LOCATION', 'header' );
defined( 'synclio_CLIENT__HTTPS' ) or define( 'synclio_CLIENT__HTTPS', 'AUTO' );
define( 'synclio__VERSION', '2.2.2' );
define( 'synclio__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'synclio__GLOTPRESS_LOCALES_PATH' ) or define( 'synclio__GLOTPRESS_LOCALES_PATH', synclio__PLUGIN_DIR . 'locales.php' );
define( 'synclio_MASTER_USER', true );

if(!function_exists('_log')){
	  function _log( $message ) {
	    if( WP_DEBUG === true ){
	      if( is_array( $message ) || is_object( $message ) ){
	        error_log( print_r( $message, true ) );
	      } else {
	        error_log( $message );
	      }
	    }
	  }
	}	


	class Synclio {


		/**
		 * Constructor.  Initializes WordPress hooks
		 */
		function Synclio() {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}

		/**
		 * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
		 * @static
		 */
		public static function plugin_activation( $network_wide ) {
			_log("Synclio plugin_activation!");
			update_option( 'synclio_activated', 1 );
			// update_option( 'synclio_id', '' );
		}

		/**
		 * Removes all connection options
		 * @static
		 */
		public static function plugin_deactivation( $network_wide ) {
			_log("Synclio plugin_deactivation!");
			update_option( 'synclio_id', '' );
			update_option( 'synclio_activated', 0 );
			wp_unregister_sidebar_widget('SynclioWidget');
		}


		/**
		 * Is synclio active?
		 */
		public static function is_active() {

			if (get_option( 'synclio_activated') == 1) {
		    	_log('is_active(): Plugin is active');
		    	
		    	return true;
			} else {
				_log('is_active(): Plugin is not active');
				return false;
			}

			//return (bool) Synclio_Data::get_access_token( synclio_MASTER_USER );
		}

		/**
		 * Is synclio in development (offline) mode?
		 */
		public static function is_development_mode() {
			$development_mode = false;

			if ( defined( 'synclio_DEV_DEBUG' ) ) {
				$development_mode = synclio_DEV_DEBUG;
			}

			elseif ( site_url() && false === strpos( site_url(), '.' ) ) {
				$development_mode = true;
			}

			return apply_filters( 'synclio_development_mode', $development_mode );
		}

		function admin_init() {
			// If the plugin is not connected, display a connect message.

			if (
				// the plugin was auto-activated and needs its candy
				Synclio::get_option( 'do_activate' )
			||
				// the plugin is active, but was never activated.  Probably came from a site-wide network activation
				!Synclio::get_option( 'activated' )
			) {
				//Synclio::plugin_initialize();
			}

			if ( !Synclio::is_active() && ! Synclio::is_development_mode() ) {
				if ( 4 != Synclio::get_option( 'activated' ) ) {
					// Show connect notice on dashboard and plugins pages
					add_action( 'load-index.php', 'prepare_connect_notice'  );
					add_action( 'load-plugins.php', 'prepare_connect_notice'  );
				}
			}

			add_action( 'admin_enqueue_scripts', 'admin_styles' );
			add_action( 'admin_head', 'admin_menu_css'  );

			add_action( 'admin_head', 'prepare_connect_notice'  );

			add_action( 'wp_ajax_synclio_debug', array( $this, 'ajax_debug' ) );

			if ( Synclio::is_active() || Synclio::is_development_mode() ) {
				// Artificially throw errors in certain whitelisted cases during plugin activation
				add_action( 'activate_plugin', array( $this, 'throw_error_on_activate_plugin' ) );

				// Kick off synchronization of user role when it changes
				add_action( 'set_user_role', array( $this, 'user_role_change' ) );

				// Add retina images hotfix to admin
				global $wp_db_version;
				if ( $wp_db_version > 19470  ) {
					// WP 3.4.x
					// TODO will need to add && $wp_db_version < xxxxx when 3.5 comes out.
					// add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_retina_scripts' ) );
					// /wp-admin/customize.php omits the action above.
					// add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_retina_scripts' ) );
				}
			}
		}

		

		public static function get_option_names( $type = 'compact' ) {
			switch ( $type ) {
			case 'non-compact' :
			case 'non_compact' :
				return array(
					'register',
					'activated',
					'active_modules',
					'do_activate',
					'publicize',
					'widget_twitter',
				);
			}

			return array(
				'id',                           // (int)    The Client ID/WP.com Blog ID of this site.
				'blog_token',                   // (string) The Client Secret/Blog Token of this site.
				'user_token',                   // (string) The User Token of this site. (deprecated)
				'publicize_connections',        // (array)  An array of Publicize connections from WordPress.com
				'master_user',                  // (int)    The local User ID of the user who connected this site to synclio.wordpress.com.
				'user_tokens',                  // (array)  User Tokens for each user of this site who has connected to synclio.wordpress.com.
				'version',                      // (string) Used during upgrade procedure to auto-activate new modules. version:time
				'old_version',                  // (string) Used to determine which modules are the most recently added. previous_version:time
				'fallback_no_verify_ssl_certs', // (int)    Flag for determining if this host must skip SSL Certificate verification due to misconfigured SSL.
				'time_diff',                    // (int)    Offset between synclio server's clocks and this server's clocks. synclio Server Time = time() + (int) Synclio::get_option( 'time_diff' )
				'public',                       // (int|bool) If we think this site is public or not (1, 0), false if we haven't yet tried to figure it out.
			);
		}

		/**
		 * Returns the requested option.  Looks in synclio_options or synclio_$name as appropriate.
	 	 *
		 * @param string $name    Option name
		 * @param mixed  $default (optional)
		 */
		public static function get_option( $name, $default = false ) {
			if ( in_array( $name, Synclio::get_option_names( 'non_compact' ) ) ) {
				return get_option( "synclio_$name" );
			} else if ( !in_array( $name, Synclio::get_option_names() ) ) {
				trigger_error( sprintf( 'Invalid synclio option name: %s', $name ), E_USER_WARNING );
				return false;
			}

			$options = get_option( 'synclio_options' );
			if ( is_array( $options ) && isset( $options[$name] ) ) {
				return $options[$name];
			}

			return $default;
		}


		/**
		 * Singleton
		 * @static
		 */
		public static function init() {
			static $instance = false;
			if ( !$instance ) {
				
				$instance = new Synclio;
			}

			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui-core');
			wp_enqueue_script('jquery-ui-tabs');
			wp_enqueue_script('jquery-ui-dialog');
			
			wp_enqueue_script('custom-script', plugins_url( basename( dirname( __FILE__ ) ) )  . '/_inc/jquery.ba-postmessage.js',array( 'jquery' ));


			
			return $instance;
		}

		/**
		 * State is passed via cookies from one request to the next, but never to subsequent requests.
		 * SET: state( $key, $value );
		 * GET: $value = state( $key );
		 *
		 * @param string $key
		 * @param string $value
		 * @param bool $restate private
		 */
		public static function state( $key = null, $value = null, $restate = false ) {
			static $state = array();
			static $path, $domain;
			if ( !isset( $path ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				$admin_url = Synclio::admin_url();
				$bits = parse_url( $admin_url );

				if ( is_array( $bits ) ) {
					$path = ( isset( $bits['path'] ) ) ? dirname( $bits['path'] ) : null;
					$domain = ( isset( $bits['host'] ) ) ? $bits['host'] : null;
				} else {
					$path = $domain = null;
				}
			}

			// Extract state from cookies and delete cookies
			if ( isset( $_COOKIE[ 'synclioState' ] ) && is_array( $_COOKIE[ 'synclioState' ] ) ) {
				$yum = $_COOKIE[ 'synclioState' ];
				unset( $_COOKIE[ 'synclioState' ] );
				foreach ( $yum as $k => $v ) {
					if ( strlen( $v ) )
						$state[ $k ] = $v;
					setcookie( "synclioState[$k]", false, 0, $path, $domain );
				}
			}

			if ( $restate ) {
				foreach ( $state as $k => $v ) {
					setcookie( "synclioState[$k]", $v, 0, $path, $domain );
				}
				return;
			}

			// Get a state variable
			if ( isset( $key ) && !isset( $value ) ) {
				if ( array_key_exists( $key, $state ) )
					return $state[ $key ];
				return null;
			}

			// Set a state variable
			if ( isset ( $key ) && isset( $value ) ) {
				$state[ $key ] = $value;
				setcookie( "synclioState[$key]", $value, 0, $path, $domain );
			}
		}

		public static function admin_url( $args = null ) {
			$url = admin_url( 'admin.php?page=synclio' );
			if ( is_array( $args ) )
				$url = add_query_arg( $args, $url );
			return $url;
		}


	}

	class Synclio_Data {
	/**
	 * Gets locally stored token
	 *
	 * @return object|false
	 */
	public static function get_access_token( $user_id = false ) {
		if ( $user_id ) {
			if ( !$tokens = Synclio::get_option( 'user_tokens' ) ) {
				return false;
			}
			if ( $user_id === synclio_MASTER_USER ) {
				if ( !$user_id = Synclio::get_option( 'master_user' ) ) {
					return false;
				}
			}
			if ( !isset( $tokens[$user_id] ) || !$token = $tokens[$user_id] ) {
				return false;
			}
			$token_chunks = explode( '.', $token );
			if ( empty( $token_chunks[1] ) || empty( $token_chunks[2] ) ) {
				return false;
			}
			if ( $user_id != $token_chunks[2] ) {
				return false;
			}
			$token = "{$token_chunks[0]}.{$token_chunks[1]}";
		} else {
			$token = Synclio::get_option( 'blog_token' );
			if ( empty( $token ) ) {
				return false;
			}
		}

		return (object) array(
			'secret' => $token,
			'external_user_id' => (int) $user_id,
		);
	}
}

	function add_admin_menu() {

		add_action('admin_menu', 'synclio_admin_actions');

	}

	/**
	 * Admin Pages
	 */
	function stats_admin_menu() {
	        global $pagenow;


		// If we're at an old Stats URL, redirect to the new one.
		// Don't even bother with caps, menu_page_url(), etc.  Just do it.
		if ( 'index.php' == $pagenow && isset( $_GET['page'] ) && 'stats' == $_GET['page'] ) {
			$redirect_url =	str_replace( array( '/wp-admin/index.php?', '/wp-admin/?' ), '/wp-admin/admin.php?', $_SERVER['REQUEST_URI'] );
			$relative_pos = strpos(	$redirect_url, '/wp-admin/' );
			if ( false !== $relative_pos ) {
				wp_safe_redirect( admin_url( substr( $redirect_url, $relative_pos + 10 ) ) );
				exit;
			}
		}

		$hook = add_submenu_page( 'synclio', __( 'Site Stats', 'synclio' ), __( 'Site Stats', 'synclio' ), 'view_stats', 'stats', 'stats_reports_page' );
		add_action( "load-$hook", 'stats_reports_load' );
	}

	function admin_menu_css() { 
		_log('admin_menu_css begin');
		?>
		
		
		
		<style type="text/css" id="synclio-menu-css">
			#toplevel_page_synclio .wp-menu-image {
				background: url( <?php echo plugins_url( basename( dirname( __FILE__ ) ) . '/_inc/images/menuicon-sprite.png' ) ?> ) 0 90% no-repeat;
			}
			/* Retina synclio Menu Icon */
			@media only screen and (-moz-min-device-pixel-ratio: 1.5), only screen and (-o-min-device-pixel-ratio: 3/2), only screen and (-webkit-min-device-pixel-ratio: 1.5), only screen and (min-device-pixel-ratio: 1.5) {
				#toplevel_page_synclio .wp-menu-image {
					background: url( <?php echo plugins_url( basename( dirname( __FILE__ ) ) . '/_inc/images/menuicon-sprite-2x.png' ) ?> ) 0 90% no-repeat;
					background-size:30px 64px;
				}
			}
			#toplevel_page_synclio.current .wp-menu-image,
			#toplevel_page_synclio.wp-has-current-submenu .wp-menu-image,
			#toplevel_page_synclio:hover .wp-menu-image {
				background-position: top left;
			}
			
		</style>
		
		<?php
	}


	function admin_styles() {
		_log('admin_styles begin');
		global $wp_styles;
		$pathString = plugins_url( basename( dirname( __FILE__ ) ) . '/_inc/synclio.css' );
		_log('pathString ' . $pathString);
		wp_enqueue_style( 'synclio', $pathString);
		$wp_styles->add_data( 'synclio', 'rtl', true );
	}

	function admin_menu() {
		_log('admin_menu begin');
		/*
		list( $synclio_version ) = explode( ':', Synclio::get_option( 'version' ) );
		if (
			$synclio_version
		&&
			$synclio_version != synclio__VERSION
		&&
			( $new_modules = Synclio::get_default_modules( $synclio_version, synclio__VERSION ) )
		&&
			is_array( $new_modules )
		&&
			( $new_modules_count = count( $new_modules ) )
		&&
			( Synclio::is_active() || Synclio::is_development_mode() )
		) {
			$new_modules_count_i18n = number_format_i18n( $new_modules_count );
			$span_title = esc_attr( sprintf( _n( 'One New synclio Module', '%s New synclio Modules', $new_modules_count, 'synclio' ), $new_modules_count_i18n ) );
			$title = sprintf( 'synclio %s', "<span class='update-plugins count-{$new_modules_count}' title='$span_title'><span class='update-count'>$new_modules_count_i18n</span></span>" );
		} else {
			$title = __( 'synclio', 'synclio' );
		}
		*/

		// $hook = add_menu_page( 'synclio', 'Synclio', 'read', 'synclio', 'admin_page', 'div', 3 );

		/*
		add_action( "load-$hook", array( $this, 'admin_page_load' ) );

		if ( version_compare( $GLOBALS['wp_version'], '3.3', '<' ) ) {
			if ( isset( $_GET['page'] ) && 'synclio' == $_GET['page'] ) {
				add_contextual_help( $hook, $this->synclio_help() );
			}
		} else {
			add_action( "load-$hook", array( $this, 'admin_help' ) );
		}
		add_action( "admin_head-$hook", array( $this, 'admin_head' ) );
		add_filter( 'custom_menu_order', array( $this, 'admin_menu_order' ) );
		*/
		add_filter( 'menu_order', 'synclio_menu_order' );

		/*
		add_action( "admin_print_styles-$hook", array( $this, 'admin_styles' ) );

		add_action( "admin_print_scripts-$hook", array( $this, 'admin_scripts' ) );

		do_action( 'synclio_admin_menu' );
		
		add_action( 'admin_print_styles', 'admin_styles' );
		*/

	}

	function prepare_connect_notice() {
		_log('prepare_connect_notice()');

		add_action( 'admin_print_styles', 'admin_styles'  );

		add_action( 'admin_notices', 'admin_connect_notice'  );

		if ( Synclio::state( 'network_nag' ) )
			add_action( 'network_admin_notices', array( $this, 'network_connect_notice' ) );
	}

	function admin_connect_notice() {
		_log("admin_connect_notice");
		echo "<script type='text/javascript'>console.log('admin_connect_notice');</script>";
		$uniqueId=get_option('synclio_id');
		echo "<script type='text/javascript'>
				var uniqueId = '<?php echo $uniqueId; ?>';
				console.log(uniqueId);
		</script>";


		// Don't show the connect notice on the synclio settings page. @todo: must be a better way?
		if ( false !== strpos( $_SERVER['QUERY_STRING'], 'page=synclio' ) ) {
			_log("admin_connect_notice ". $_SERVER['QUERY_STRING']);
			//return;
		}
			

		if ( !current_user_can( 'manage_options' ) ) {
			_log('admin_connect_notice() current user cannot manage options');
			return;
		} else {
			_log('admin_connect_notice() returning css to page');
		}
		?>
		
		<link href='http://fonts.googleapis.com/css?family=Signika:400,600' rel='stylesheet' type='text/css'>
		<?php if(strcasecmp(get_option('synclio_id'),'') == 0){ ?>
		<div id="message" class="updated synclio-message jp-connect">
			
			<div class="synclio-wrap-container">
				<div class="synclio-text-container">
					<h4>
						<?php if ( Synclio::is_active() ) : ?>
							<p><?php _e( '<strong>Click-To-Call is almost ready!</strong> Last step is to activate by signing in or registering your account.', 'synclio' ); ?></p>
						<?php else : ?>
							<p><?php _e( '<strong>Synclio is installed</strong> and ready to bring awesome, WordPress.com cloud-powered features to your site.', 'synclio' ) ?></p>
						<?php endif; ?>
					</h4>
				</div>
				<div class="synclio-install-container">
					<?php if ( Synclio::is_active() ) : ?>
						<p class="submit"><a href="" class="button-connector" id="wpcom-connect"><?php _e( 'Sign In / Register', 'synclio' ); ?></a></p>
					<?php else : ?>
						<p class="submit"><a href="<?php echo Synclio::admin_url() ?>" class="button-connector" id="wpcom-connect"><?php _e( 'Learn More', 'synclio' ); ?></a></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php } ?>
		
		
		 <script language="javascript">
    		var $ = jQuery;
   			var popUp;
	
			$(document).ready(function () {
			    $("#wpcom-connect").click(function () {
			    	
			    	var iframe_url = "https://auth.synclio.com?source=wordpress";
			    	// var iframe_url = "http://127.0.0.1:8080";
			    	var child_domain = iframe_url.substring(0, iframe_url.indexOf('/', 9));
					var parent_domain = window.location.protocol + '//' + window.location.host;
					var w = 1200;
					var h = 700;
					var left = (screen.width/2)-(w/2);
		  			var top = (screen.height/2)-(h/2);
					
					popUp = window.open(iframe_url,'','toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=1, resizable=no, copyhistory=no, width='+w+', height='+h+', top='+top+', left='+left);
					$(popUp).focus();

					$(popUp).bind('unload',
					  function(){
					    console.log("Window closed!");

					});

					//create popup window
					var myPopup = popUp
					var keepPinging = true;

					// //periodical message sender
					// setInterval(function(){
					// 	if (keepPinging == true) {
					// 		var message = 'Hello!  The time is: ' + (new Date().getTime());
					// 		//console.log('blog.local:  sending message:  ' + message);
					// 		myPopup.postMessage(message,"https://login.syncleo.com"); //send the message and target URI
					// 	} else {
					// 		return;
					// 	}
						
					// },100);

					//listen to holla back
					window.addEventListener('message',function(event) {
						console.log('received response:  ',event.data);
						keepPinging=false;

						//Now save this sucker!
						var data = {
							action: 'save_id',
							data_to_send: event.data
						};
						jQuery.post(ajaxurl, data, function(response) {
							console.log('Got this from the server: ' + response);
							$('#message').hide();
							$("#toplevel_page_synclio").hide();
						});
					},false);
			        return false;
			    });
			});
		</script>

		<?php
	}


	function synclio_menu_order( $menu_order ) {
		$jp_menu_order = array();

		foreach ( $menu_order as $index => $item ) {
			if ( $item != 'Synclio' )
				$jp_menu_order[] = $item;

			if ( $index == 0 )
				$jp_menu_order[] = 'Synclio';
		}

		return $jp_menu_order;
	}


	function curPageURL() {
 		$pageURL = 'http';
 		if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
 		$pageURL .= "://";
 		if ($_SERVER["SERVER_PORT"] != "80") {
  			$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
 		} else {
  			$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
 		}
 		return $pageURL;
	}

	add_action('wp_head', 'showSynclioPlugin');
 
	function showSynclioPlugin(){
		$url=curPageURL();
		if(strpos($url, 'wp-admin') === false  &&  strpos($url, 'wp-login') === false){
			$uniqueId=get_option('synclio_id');
			if(strcasecmp($uniqueId,'') != 0){
				echo "<script type='text/javascript'>
						var $ = jQuery.noConflict();
						console.log($);
					  </script>
					  <script type=text/javascript src='http://login.synclio.com/clicktocall/clicktocall.js?uniquePin=".$uniqueId."'></script>";
			}
		}
	}
	

	function synclio_admin_actions() {
	    add_options_page("Synclio Control Panel", "Synclio Control Panel", 1, "Synclio Control Panel", "oscimp_admin");
	}

	function oscimp_admin() {
		include('oscommerce_import_admin.php');
	}


	add_action( 'init', array( 'Synclio', 'init' ) );

	_log("Synclio ID" . get_option('synclio_id'));

	if (get_option('synclio_id') == NULL) {
		add_action( 'admin_head', 'admin_menu_css' );
		add_action( 'admin_menu', 'admin_menu', 999 ); // run late so that other plugins hooking into this menu don't get left out
	
	}
	
	add_action('wp_ajax_save_id', 'save_id');


	function save_id() {
		
		_log("test_theme_save_ajax");
		$whatever = $_POST['data_to_send'] ;
		update_option( 'synclio_id', $whatever );
		echo $whatever;
		die();
		// _log("test_theme_save_ajax");
		// require_once( ABSPATH . WPINC . '/class-json.php' );
		// 	$wp_json = new Services_JSON();

		// $res = $wp_json->decode( stripslashes($_REQUEST['data_to_send']) );
		// _log("JSON Data" . $res);
		// update_option( 'synclio_id', $res );
		// echo $res;

		// die(); // this is required to return a proper result
	}

	
	

	register_activation_hook( __FILE__, array( 'Synclio', 'plugin_activation' ) );
	register_deactivation_hook( __FILE__, array( 'Synclio', 'plugin_deactivation' ) );
?>
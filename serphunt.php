<?php
/*
Plugin Name: Серпхант
Description: Первый инструмент для поисковой аналитики | <a href="options-general.php?page=serphunt/options.php">Страница настроек</a>
Version: 1.0
Author:
*/

class SEOT
{
  public static $plugin_title = 'Серпхант';
  public static $plugin_dir = '';
  public static $varname = 'seotools';
  public static $current_module;
  public static $tmp_path;
  public static $tmp_url;
  public static $dbdir;
  public static $dbpath;
  public static $path;
  public static $url;
  public static $prefix;
  public static $start_time;
  public static $execution_time = 25;
  public static $seoadmin_url = 'https://serphunt.ru';

  public static $options = array(
    'site_apikey' => '',
    //'seoadmin_url' => ''
  );

  public static function menu()
  {
    $menu_item =
      add_options_page(
        'Серпхант',
        'Серпхант',
        'administrator',
        'seotools/options.php',
        array('SEOT', 'view')
      );

    add_action('admin_print_styles-'.$menu_item, array('SEOT', 'includeAssets'));
  }


  public static function includeAssets()
  {
    wp_enqueue_style('seotools-admin',  SEOT::$url . '/assets/css/admin.css');
  }

  public static function submitButton($handler, $varname)
  {
    if ( is_array($handler) )
    {
      if ( count($handler) > 1 )
      {
        $submit_button =
          SEOTL_HTML_Tag::select($handler, $varname)
          .'&nbsp;&nbsp;<button class="button-primary">'.__('Apply').'</button>';
      }
      else
      {
        $submit_button =
          '<button name="'.$varname.'" value="'.key($handler).'" class="button-primary">'.
            current($handler).
          '</button>';
      }
    }
    elseif ($handler !== false)
    {
      $submit_button =
        '<button name="'.$varname.'" value="'.$handler.'" class="button-primary">'.
          __('Apply').
        '</button>';
    }
    else
    {
      $submit_button = '';
    }

    return $submit_button;
  }


  public static function form($title, $form_content, $module_key, $handler, $method = 'POST')
  {
    if ( !is_array($title) )
    {
      $title = array(
        $title,
        SEOT::$plugin_title
      );
    }

    $form_content = explode('<!--form-->', $form_content);
    if ( count($form_content) > 0 )
    {
      $form_content_after =
        implode( '', array_slice($form_content, 1, count($form_content) - 1) );
    }
    else
    {
      $form_content_after = '';
    }

    echo
      '<div class="seot-wrapper">'
        .'<h2>'
          .'<div id="seot-page-title">'.$title[0].'</div>'
          .'<div id="seot-module-name">'.$title[1].'</div>'
          .'<div class="clear"></div>'
        .'</h2>'
        .'<div class="hr"></div>'
        .'<div class="break"></div>'
        .'<form action="" method="'.$method.'" autocomplete="off" enctype="multipart/form-data" id="seotools-form">'
          .$form_content[0]
          .'<div class="break"></div>'
          .'<input type="hidden" name="seot_module" value="'.$module_key.'">'
          .self::submitButton($handler, 'seot_handler')
        .'</form>'
        .$form_content_after
      .'</div>';
  }


  public static function tableOptions($data, $varname, $attr = '')
  {
    return SEOTL_HTML_Template_Options::table($data, $varname, $attr, false);
  }


  public static function trashedPost( $post_id )
  {
    global $wpdb;

    SEOT::setModuleOptions('core');

    if ( trim( SEOT::$seoadmin_url ) )
    {
      $response = SEOTL_Net_URL_Get::content(
        SEOTL_Net_URL::addParametrs(
          SEOT::$seoadmin_url,
          array(
            'seoa_module' => 'core',
            'seoa_handler' => 'page-delete',
            'seoa_site_apikey' => SEOT::$options['site_apikey']
          )
        ),

        array(
          'post' => array( 'post_id' => array($post_id) ),
          'post_to_string' => true
        )
      );
    }
  }


  public static function updatePost( $post_id )
  {
    global $wpdb;

    if ( trim($post_id) && wp_is_post_revision( $post_id ) )
    {
	   	return;
    }

    $check_post = $wpdb->get_var('
      SELECT COUNT(*)
      FROM ' . $wpdb->posts . '
      WHERE
        ID = ' . $post_id . '
          AND
        post_type = "post"
          AND
        post_title != ""
          AND
        post_status NOT IN ("auto-draft", "revision", "trash")
    ');

    if ( $check_post )
    {
      SEOT::setModuleOptions('core');

      $GLOBALS['_POST']['seoa_site_apikey'] = SEOT::$options['site_apikey'];

      $response = SEOT::handler(
        'sync',
        'core',
        array(
          'hide_info' => 1,
          'post' => array( $post_id )
        )
      );
//SEOTL_PHP_View::xmp( $response );  exit;
    }
  }


  public static function moduleInfo($module_key = null)
  {
    global $wpdb;

    if ($module_key === null)
    {
      if ( isset($_POST['seot_module']) )
      {
        $module_key = $_POST['seot_module'];
      }
      elseif ( isset($_GET['seot_module']) )
      {
        $module_key = $_GET['seot_module'];
      }
    }

    if ( $module_key == 'core' )
    {
      $module_key = null;
    }

    $wp_content_dir = str_replace('\\', '/', WP_CONTENT_DIR);

    if ( trim($module_key) )
    {
      $module_class_words = array_map('ucfirst', explode('-', $module_key));
      $objname = 'seot_'.str_replace('-', '_', $module_key);

      return (object) array(
        'key' => $module_key,
        'objname' => $objname,
        'prefix' => $objname.'_',
        'varname' => 'seotools['.$module_key.']',
        'classname' => 'SEOT_'.implode('_', $module_class_words),
        'dbtable' => $wpdb->prefix.'seotools_'.str_replace('-', '_', $module_key),
        'dbpath' => $wp_content_dir.'/plugins-data/serphunt/'.SEOT::$dbdir.'/data/'.$module_key,
        'dburl' => WP_CONTENT_URL.'/plugins-data/serphunt/'.SEOT::$dbdir.'/data/'.$module_key,
        'path' => self::$path.'/modules/'.$module_key,
        'url' => self::$url.'/modules/'.$module_key
      );
    }
    else
    {
      return (object) array(
        //'key' => false,
        'key' => 'core',
        'objname' => 'seotools',
        'prefix' => 'seotools_',
        'varname' => 'seotools',
        'classname' => 'SEOT',
        'dbtable' => $wpdb->prefix.'seotools',
        'dbpath' => $wp_content_dir.'/plugins-data/serphunt/'.SEOT::$dbdir.'/data',
        'dburl' => WP_CONTENT_URL.'/plugins-data/serphunt/'.SEOT::$dbdir.'/data',
        'path' => self::$path,
        'url' => self::$url
      );
    }
  }


  public static function handler($handler = null, $module_key = null, $extdata = null, $redirect_delay = 0)
  {
    global $post, $wpdb, $user_ID;

    if ( isset($_POST['seot_filter']) )
    {
      return;
    }

    if ( !headers_sent() )
    {
      header('Content-type: text/html; charset=utf-8');
    }

    $wpdb->show_errors();

    $seot_module = self::moduleInfo($module_key);

    if ( trim($handler) )
    {
      $redirect_delay = -1;
    }
    else
    {
      if ( !empty($_POST['seot_handler']) )
      {
        $handler = $_POST['seot_handler'];
      }
      elseif ( !empty($_GET['seot_handler']) )
      {
        $handler = $_GET['seot_handler'];
      }
    }

    if ( trim($handler) )
    {
      $seot_module->handler = $handler;
      $seot_module->action = 'handler';


      $handler_path = $seot_module->path.'/handlers/'.str_replace('_', '/', $seot_module->handler.'.php');

      if ( !file_exists( $handler_path ) )
      {
        $handler_path = null;
      }

      $option_key = $seot_module->prefix.'options';

      if ( isset($_POST[$option_key]) )
      {
        $seot_module->options = $_POST[$option_key];
        $extdata = $_POST[$option_key];
      }

      if ( $handler_path  !== null )
      {
        $_REQUEST = self::stripSlashes($_REQUEST);
        $_POST    = self::stripSlashes($_POST);
        $_GET     = self::stripSlashes($_GET);

        if ( is_array($extdata) )
        {
          $seot_module->extdata = $extdata;
        }
        elseif ( $seot_module->key && isset($_POST['seotools'][$seot_module->key]) )
        {
          $seot_module->extdata = $_POST['seotools'][$seot_module->key];
        }
        elseif ( isset($_POST['seotools']) )
        {
          $seot_module->extdata = $_POST['seotools'];
        }

        global ${$seot_module->objname};

        if ( isset($_POST['display_processing']) || isset($_GET['display_processing']) )
        {
          $style = file_get_contents(SEOT::$path.'/assets/css/processing.css');
          $style = str_replace('{SEOT::$url}', SEOT::$url, $style);
          echo '<style>'.$style.'</style>';
        }

        require $handler_path;
      }

      if ($seot_module->handler == 'options' && isset($_POST[$option_key]))
      {
        self::updateModuleOptions($seot_module->key, $seot_module->options);
      }

      if ($redirect_delay !== -1 && !empty($_SERVER['HTTP_REFERER']) && !stristr($_SERVER['HTTP_REFERER'], 'seot_handler='.$seot_module->handler))
      {
        self::redirect();
        exit;
      }
      else if ( $redirect_delay == -1 && isset($seot_module->return_data) )
      {
        return $seot_module->return_data;
      }
    }
  }


  public static function view($view = null, $module_key = null, $extdata = null)
  {
    global $post, $wpdb, $user_ID;

    if ( !empty($_GET['seot_view']) && !empty($_GET['seot_module']) )
    {
      $module_key = $_GET['seot_module'];
      $view = $_GET['seot_view'];
    }

    if ( !trim($view)
        && !empty($_GET['page'])
        && preg_match('/^seotools\/(?:([^\/]+)\/)?([^.]+)\.php/', $_GET['page'], $matches)
      )
    {
      $module_key = $matches[1];

      if ( !empty($_GET['seot_view']) )
      {
        $view = $_GET['seot_view'];
      }
      else
      {
        $view = $matches[2];
      }
    }

    if ( !trim($view) && isset($_GET['seot_view']) )
    {
      $module_key = null;
      $view = $_GET['seot_view'];
    }

    $seot_module = self::moduleInfo($module_key);
    $view_path = $seot_module->path.'/views/'.str_replace('_', '/', $view).'.php';

    if ( trim($view) && file_exists($view_path) )
    {
      global ${$seot_module->objname};

      $seot_module->view = $view;
      $seot_module->action = 'view';

      if ( $extdata != null)
      {
        $seot_module->extdata = $extdata;
      }
      elseif ( $seot_module->key && isset($_POST['seotools'][$seot_module->key]) )
      {
        $seot_module->extdata = $_POST['seotools'][$seot_module->key];
      }

      require $view_path;
    }
  }


  public static function writeDataToFile($options_file, $options_data)
  {
    if ( is_object($options_data) )
    {
      $options_data = clone($options_data);
    }

    if ( is_array($options_data) || is_object($options_data) )
    {
      $options_data = SEOTL_PHP_Array::serialize($options_data);
    }

    $options_data = '<?php exit; ?'.'>'.$options_data;

    if ( !file_exists( dirname($options_file) ) )
    {
      mkdir( dirname($options_file), 0755, true );
    }

    file_put_contents($options_file, $options_data, LOCK_EX);
  }


  public static function getDataFromFile($options_file, $default_data = array(), $options = array())
  {
    if ( !file_exists($options_file) )
    {
      return $default_data;
    }

    $options = SEOTL_PHP_Array::merge(
      array('merge' => array('compare' => true)),
      $options
    );

    $data = file_get_contents($options_file);
    $data = trim( substr_replace($data, '', 0, 14) );

    if ( SEOTL_PHP_Str::isSerialized($data) )
    {
      $test = @unserialize($data);
      if ( empty( $test ) )
      {
        //$data = preg_replace('/\r+/su', '', $data);
        $data = preg_replace('/\r+/', '', $data);

        $data_temp = preg_replace_callback(
          //'/s:(\d+):"(.*?)";/',
          '/s:(\d+):"(.*?)";/su',

          array( 'SEOTL_PHP_Str', 'unserializeCallback'),

          $data
        );

        $test = @unserialize($data_temp);
        if ( empty( $test ) )
        {
          $data = preg_replace_callback(
            //'/s:(\d+):"(\W+)";/',
            '/s:(\d+):"(\W+)";/su',

            array( 'SEOTL_PHP_Str', 'unserializeCallback'),

            $data
          );

          $data = unserialize($data);
        }
        else
        {
          $data = $test;
        }

        SEOA::writeDataToFile( $options_file, $data );
      }
      else
      {
        $data = $test;
      }
    }
    else
    {
      $data = $data;
    }

    if ( $options['merge'] && is_array($data) && ( is_array($default_data) && count($default_data) > 0 ) )
    {
      //$data = SEOTL_PHP_Array::intersectKey($data, $default_data);
      $data = SEOTL_PHP_Array::merge($default_data, $data, $options['merge']);
    }

    return $data;
  }



  public static function updateModuleOptions($module_key, $options, $set = array())
  {
    $set = array_merge( array('dbtype' => 'file'), $set);

    $seot_module = self::moduleInfo($module_key);

    if ( $set['dbtype'] == 'mysql' )
    {
      update_option($seot_module->prefix.'options', $options);
    }
    elseif (  $set['dbtype'] == 'file' )
    {

      $options = self::writeDataToFile( $seot_module->dbpath.'/options.php', $options );
    }
  }


  public static function setModuleOptions($module_key, $set = array())
  {
    static $is_setup = array();

    $set = array_merge(
      array(
        'return' => false,
        'dbtype' => 'file',
        'merge' => array('compare' => true)
      ),
      $set
    );

    if ( !$set['return'] && isset( $is_setup[$module_key] ) )
    {
      return null;
    }

    $seot_module = self::moduleInfo($module_key);


    if ( property_exists($seot_module->classname, 'options') )
    {
      eval('$options = '.$seot_module->classname.'::$options;');
    }
    else
    {
      return null;
    }

    if ( $set['return'] && isset( $is_setup[$module_key] ) )
    {
      return $options;
    }

    if ( $set['dbtype'] == 'mysql' )
    {
      if ( $options_current = get_option($seot_module->prefix.'options') )
      {
        $options = SEOTL_PHP_Array::merge($options, $options_current, $set['merge']);
      }
    }
    elseif (  $set['dbtype'] == 'file' )
    {
      $options = self::getDataFromFile( $seot_module->dbpath.'/options.php', $options, $set );
    }

    if ( method_exists($seot_module->classname, 'optionsHandler') )
    {
      eval($seot_module->classname.'::optionsHandler($options);');
    }

    eval($seot_module->classname.'::$options = $options;');

    $is_setup[$module_key] = true;

    if ( $set['return'] )
    {
      return $options;
    }
  }


  public static function uploadInfo( $dir = 'seotools' )
  {
    static $upload_info;

    if ( !is_array($upload_info) || $upload_info['basedir'] != str_replace('\\', '/', $upload_info['basedir']).'/'.$dir )
    {
      $upload_info = wp_upload_dir();
      $upload_info['basedir'] = str_replace('\\', '/', $upload_info['basedir']).'/'.$dir;
      $upload_info['baseurl'] = $upload_info['baseurl'].'/'.$dir;
    }

    return $upload_info;
  }


  public static function redirect($location = null, $delay = 0)
  {
    if ( $location === null && !empty($_SERVER['HTTP_REFERER']) )
    {
      $location = $_SERVER['HTTP_REFERER'];
    }

    if ( !trim($location) )
    {
      $location = './';
    }

    if ($delay >= 0)
    {
      if (!headers_sent())
      {
        header('location: '.$location, true, 303);
      }
      else
      {
        $location = preg_replace('/[?&]\d+/', '', $location);
        $location = SEOTL_Net_URL::addParametrs($location, time());
        echo '<meta http-equiv="Refresh" content="' . $delay . ';URL=' . htmlspecialchars($location) . '" />';
      }
    }

    exit;
  }


  public static function stripSlashes($arr)
  {
    if (!is_array($arr))
    {
      return stripslashes($arr);
    }
    else
    {
      foreach ($arr as $k => $str)
      {
        $new_arr[stripslashes($k)] = self::stripSlashes($str);
      }

      if (isset($new_arr))
      {
        $arr = $new_arr;
      }

      return $arr;
    }
  }

  public static function getPageURI( $url )
  {
    $uri = preg_replace('/(https?\:)?\/\/[^\/]+/', '', $url);

    if ( !trim( $uri ) )
    {
      $uri = '/';
    }

    return $uri;
  }


  public static function uriKey( $url )
  {
    $uri = self::getPageURI( $url );
    return md5( $uri );
  }


  public static function pluginSync()
  {
    SEOTL_FileSys_File::write( SEOT::$tmp_path . '/plugin-sync', time() );
  }


  public static function sync()
  {
  //&& !isset( $_GET['activate'] ) && !isset( $_GET['deactivate'] )
    if ( is_admin() && file_exists( SEOT::$tmp_path . '/plugin-sync' ) )
    {
      unlink( SEOT::$tmp_path . '/plugin-sync' );
      SEOT::setModuleOptions('core');
  /*
      $GLOBALS['_POST']['seoa_site_apikey'] = SEOT::$options['site_apikey'];

      SEOT::handler(
        'sync',
        'core',
        array(
          'hide_info' => 1,
          'post' => array( $post_id )
        )
      );
  */
      SEOTL_Net_URL_Get::content(
        get_site_url(),
        array(
          'timeout' => 5,

          'post' => array(
            'seot_handler' => 'sync',
            'seot_module' => 'core',
            'seoa_site_apikey' => SEOT::$options['site_apikey']
          )
        )
      );
    }

    if ( is_admin() && !file_exists( SEOT::$tmp_path . '/old-data-deleted' ) )
    {
      global $wpdb;

      SEOTL_FileSys_File::write( SEOT::$tmp_path . '/old-data-deleted', 1 );

      SEOTL_DB::query('DROP TABLE IF EXISTS `' . $wpdb->prefix.'seotools' . '`');
      SEOTL_DB::query('DROP TABLE IF EXISTS `' . $wpdb->prefix.'seotools' . '_competitors`');
      SEOTL_DB::query('DROP TABLE IF EXISTS `' . $wpdb->prefix.'seotools' . '_competitors_sites`');
      SEOTL_DB::query('DROP TABLE IF EXISTS `' . $wpdb->prefix.'seotools' . '_seo_keywords`');
      SEOTL_DB::query('DROP TABLE IF EXISTS `' . $wpdb->prefix.'seotools' . '_seo_backlinks`');
      SEOTL_DB::query('DROP TABLE IF EXISTS `' . $wpdb->prefix.'seotools' . '_seo_interlinks`');
      SEOTL_DB::query('DROP TABLE IF EXISTS `' . $wpdb->prefix.'seotools' . '_seo_positions`');
      SEOTL_DB::query('DROP TABLE IF EXISTS `' . $wpdb->prefix.'seotools' . '_stats`');
      SEOTL_DB::query('DROP TABLE IF EXISTS `' . $wpdb->prefix.'seotools' . '_stats_custom_period`');
      SEOTL_DB::query('DROP TABLE IF EXISTS `' . $wpdb->prefix.'seotools' . '_stats_keywords`');
    }
  }


  public static function checkRobotsRule( $page_uri )
  {
    static $rules;

    $page_uri = preg_replace('/^(?:https?\:)?\/\/[^\/]+/', '', $page_uri );

    if ( !is_array( $rules ) )
    {
      $rules = array(
        'Allow' => array(),
        'Disallow' => array()
      );

      $robots_path = ABSPATH . 'robots.txt';
      if ( file_exists( $robots_path ) )
      {
        $robots_content = file_get_contents( $robots_path );

        foreach ( array( 'Allow', 'Disallow' )  as $k )
        {
          if ( preg_match_all( '/(?:^|\s)' . $k . '\s*:\s*(\S+)/sui', $robots_content, $matches ) )
          {
            $matches[1] = array_unique( $matches[1] );
            foreach ( $matches[1] as $rule )
            {
              $regexp = preg_quote( $rule, '/' );
              $regexp = str_replace(
                array( '\\*', '\\$' ),
                array( '.*', '$' ),
                $regexp
              );

              $rules[$k][] = $regexp;
            }
          }
        }
      }
    }


    foreach ( $rules as $k => $regexp_list )
    {
      foreach ( $regexp_list as $regexp )
      {
        if ( preg_match( '/^' . $regexp . '/sui', $page_uri ) )
        {
          if ( $k == 'Allow' )
          {
            return true;
          }
          else
          {
            return false;
          }
        }
      }
    }

    return true;
  }
}


require_once dirname(__FILE__).'/libraries/php/config.php';

global $wpdb;

if ( preg_match('/^[a-zA-Z]:\\\OpenServer\\\domains\\\(order|cms|site|work)/', __FILE__)
    || strstr($_SERVER['SERVER_NAME'], 'unidemo.net') )
{
  $wpdb->show_errors();
  error_reporting(E_ALL | E_STRICT);
  ini_set('error_reporting', E_ALL | E_STRICT);
  ini_set('display_errors','On');
}


SEOT::$plugin_dir = 'serphunt';
SEOT::$prefix = 'serphunt';
SEOT::$path = str_replace('\\', '/', dirname(__FILE__));
SEOT::$dbdir = strtolower( preg_replace('/^www./', '', $_SERVER['SERVER_NAME']) );
SEOT::$dbpath = WP_CONTENT_DIR.'/plugins-data/serphunt/'.SEOT::$dbdir.'/data';
SEOT::$url = plugins_url( SEOT::$plugin_dir );
SEOT::$tmp_path =
  str_replace('\\', '/', WP_CONTENT_DIR.'/plugins-data/serphunt/'.SEOT::$dbdir.'/tmp');
SEOT::$tmp_url = get_site_url().'/wp-content/plugins-data/serphunt/'.SEOT::$dbdir.'/tmp';


if ( $_SERVER['SERVER_NAME'] == 'wordpress.order' )
{
  SEOT::$seoadmin_url = 'http://scripts.order/seoadmin/';
}

if ( $wpdb->use_mysqli )
{
  SEOTL_DB::connectFromLink( $wpdb->dbh, 'wordpress', 'mysqli' );
}
else
{
  SEOTL_DB::connectFromLink( $wpdb->dbh, 'wordpress', 'mysql' );
}



if ( isset($_GET['seot_module']) || isset($_POST['seot_module']) )
{
  if ( isset($_POST['seot_handler']) || isset($_GET['seot_handler']) )
  {
    add_action('wp_loaded', array('SEOT', 'handler'), 0);
  }
}


if ( is_admin() )
{
  $wpseo_control_file = str_replace(
    array( 'seotools.php', 'seotools' ),
    array( 'wp-seo.php',  'wordpress-seo' ),
    __FILE__
  );
  register_activation_hook( $wpseo_control_file, array('SEOT', 'pluginSync') );
  register_deactivation_hook( $wpseo_control_file, array('SEOT', 'pluginSync') );


  $aioseop_control_file = str_replace(
    array( 'seotools.php', 'seotools' ),
    array( 'all_in_one_seo_pack.php',  'all-in-one-seo-pack' ),
    __FILE__
  );
  register_activation_hook( $aioseop_control_file, array('SEOT', 'pluginSync') );
  register_deactivation_hook( $aioseop_control_file, array('SEOT', 'pluginSync') );


  add_action('admin_menu', array('SEOT', 'menu'));


  add_action('trashed_post', array('SEOT', 'trashedPost'));
  add_action('untrashed_post', array('SEOT', 'updatePost'));
  add_action('save_post', array('SEOT', 'updatePost'), 10, 3 );
}



SEOT::$start_time = time();
SEOT::sync();

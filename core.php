<?php

require_once("Observer.php");
require_once("Request.php");
require_once("Route.php");
require_once("APP_Controller.php");
require_once("Model.php");
require_once("Main_trigger.php");
require_once("Component.php");

class core {
	protected $config = array();
	public $request;
	public $route;
	public $db ;
	public $components;
	public $observer;
	
	public $is_admin = false;
	
	/*
	* @the vars array
	* @access private
	*/
	public $vars = array();
	
	/**
	* @set undefined vars
	* @param string $index
	* @param mixed $value
	* @return void
	*/
	public function __set($index, $value)
	{
		$this->vars[$index] = $value;
	}
	
	/**
	* @get variables
	* @param mixed $index
	* @return mixed
	*/
	public function __get($index)
	{
		if (isset($this->vars[$index]))
			return $this->vars[$index];
		else
			return ;
	}
	
	/**
	* @load a specific file
	* @param string $type
	* @param string $file
	* @return void
	*/
	public function load($type , $file , $init = false)
	{
		if (class_exists($file))
		{
			if ($init)
			{
				$s_file = strtolower($file);
				
				if ($type == "models")
				{
					$instance = new $file($this->db, $this->config);
					$instance->init_template($this->template); 
					//$instance->set_components($this->set_components);
					$this->$s_file = $instance;
				}
				else
					$this->$s_file = new $file();
			}
			return true;
		}
		
		if (file_exists(ENGINE_PATH . $type . DS . $file . ".php"))
		{
			$adr = ENGINE_PATH . $type . DS . $file . ".php";
		}	
		elseif (file_exists(APP_PATH . $type . DS . $file . ".php"))
		{
			$adr = APP_PATH . $type . DS . $file . ".php";
		}	
		
		if (isset($adr))
		{
			require_once($adr);
			if ($init)
			{
				$s_file = strtolower($file);
			
				if ($type == "models")
				{
					$instance = new $file($this->db, $this->config);
					$instance->init_template($this->template); 
					//$instance->set_components($this->set_components);
					$this->$s_file = $instance;
				}
				else
					$this->$s_file = new $file();
			}
			
		}
		else
			return false;
	}
	
	/**
	* @Just init CORE. This is magic :)
	*/
	public function init_core(){
		removeMagicQuotes();
		
		if (!defined('CORE_PATH'))
			define('CORE_PATH', ENGINE_PATH . 'core' . DS );

		if (!defined('LIB_PATH'))
			define('LIB_PATH', ENGINE_PATH . 'lib' . DS );

		$this->observer = new Observer();
		
		$this->load("lib", "Spyc", true);
		
		$this->init_config("db");
		$this->init_config("general");
		$this->init_db();
		$this->init_template();
		$this->set_errors();
		$this->request = new request();
		
		$loc_1 = $this->request->location(1);
		if ($loc_1 == "admin")
		{
			define('APP_PATH', ROOT . DS . 'admin' . DS );
			$this->is_admin = true;
		}
		else
			define('APP_PATH', ROOT . DS . 'app' . DS );
		
		$this->set_lang();
		
		$this->route = new route();
		$this->route->parse();
		
		global $admin_menu;
		$admin_menu = array();
		
		$this->init_components();
		$this->init_app();
	}
	
	/**
	* @Init in manual mode CORE. This is magic :)
	*/
	public function manual_init(){
		error_reporting(E_ALL);
		ini_set('error_reporting', E_ALL);

		if(session_id() == '') {
			session_start();
		}
		$_SESSION['time_start_script'] = microtime(true);

		removeMagicQuotes();
		
		if (!defined('DS'))
			define('DS', DIRECTORY_SEPARATOR);

		if (!defined('ROOT'))
		{
			$root = dirname(__FILE__);
			$root = str_replace("engine" . DS . "core" , "" , $root);
			define('ROOT', $root);
		}		
		
		if (!defined('ADR'))
		{
			$adr=str_replace("/index.php" , "" ,$_SERVER['PHP_SELF']);
			define('ADR', $adr);
		}

		if (!defined('ENGINE_PATH'))
			define('ENGINE_PATH', ROOT . DS . 'engine' . DS );

		if (!defined('ADMIN_PATH'))
			define('ADMIN_PATH', ROOT . DS . 'admin' . DS );
		
		if (!defined('CORE_PATH'))
			define('CORE_PATH', ENGINE_PATH . 'core' . DS );

		if (!defined('LIB_PATH'))
			define('LIB_PATH', ENGINE_PATH . 'lib' . DS );

		$this->observer = new Observer();
		
		$this->load("lib", "Spyc", true);
		
		$this->init_config("db");
		$this->init_config("general");
		self::init_db();
		self::init_template();
		$this->set_errors();
		$this->request = new request();
		
		$loc_1 = $this->request->location(1);
		if ($loc_1 == "admin")
		{
			if (!defined('APP_PATH'))
				define('APP_PATH', ROOT . DS . 'admin' . DS );
			$this->is_admin = true;
		}
		else
			if (!defined('APP_PATH'))
				define('APP_PATH', ROOT . DS . 'app' . DS );
		
		$this->set_lang();
		
		$this->route = new route();
		$this->route->parse();
		
		global $admin_menu;
		$admin_menu = array();
		
		$this->init_components();
		
		$this->init();
	}
	
	/**
	* @Just init Application
	*/
	function init_app(){
		$this->route->find_route();
		$found_action_flague = false;
		
		if ($this->route->action) // Searching a controller and a route
		{
			$app_class = $this->route->class_name;
			$app_method = $this->route->method_name;
			
			$path = realpath(APP_PATH . "controllers" . DS . $app_class . ".php");
			if (file_exists($path))
				require_once($path);
			if (class_exists($app_class))
			{
				if (method_exists($app_class , $app_method))
				{
					$instance = new $app_class;
					$instance->setObserver($this->observer);
					$instance->set_config($this->config);
					$instance->init_db($this->db); 
					$instance->init_template($this->template); 
					$instance->set_components($this->components);
					$instance->init(); 
					call_user_func_array(array($instance, $app_method), $this->route->vars);
					
					$found_action_flague = true;
				}
				else
				{
					$instance = new $app_class();
					$instance->setObserver($this->observer);
					$instance->set_config($this->config);
					$instance->init_db($this->db); 
					$instance->init_template($this->template); 
					$instance->set_components($this->components);
					$instance->init(); 
					
					$found_action_flague = true;
				}
			}
			
		}
		if (!$found_action_flague) { // Searching in DB so content
			
			$tmp_trigger = explode(":",$this->config['general']['main_trigger']);
			$class_name = $tmp_trigger[0];
			$path = realpath(ROOT . DS . $tmp_trigger[1]);
			
			if (file_exists($path))
				require_once($path);
			
			if (class_exists($class_name))
			{
				$instance = new $class_name;
				$instance->setObserver($this->observer);
				$instance->set_config($this->config);
				$instance->init_db($this->db);
				$instance->init_template($this->template); 
				$instance->set_components($this->components);
				$instance->init(); 
			}
		}
	}
	
	public function init()
	{
		if (!$this->request )
			$this->request = new request();
		$loc_1 = $this->request->location(1);
		if ($loc_1 == "admin")
		{
			$this->is_admin = true;
		}
	}
	
	/**
	* @load config file
	* @param string $file
	*/
	function init_components() {
		$components = $this->db->get("p_components");
		foreach($components as &$component)
		{
			$path = realpath(ROOT . DS . "components" . DS . $component['label'] . DS . $component['label'] . ".php");
			require_once($path);
			
			$instance = new $component['label'];
			$instance->label = $component['label'];
			$instance->setObserver($this->observer);
			$instance->set_config($this->config);
			$instance->init_db($this->db);
			$instance->init_template($this->template); 
			$instance->set_components($this->components);
			$instance->init(); 
			$this->components->$component['label'] = $instance;
		}
	}
	
	/**
	* @load config file
	* @param string $file
	*/
	function init_config($file) {
		$this->config[$file] = $this->spyc->YAMLLoad( ROOT . DS . "app" . DS . "config" . DS . $file . ".yml");
	}
	
	
	/**
	* @init template
	*/
	public function init_template()
	{
		$this->template = new template();
		$this->template->config = $this->config;
	}

	
	/**
	* @load config file
	* @param string $file
	*/
	function init_db() {
		$this->load("lib", "MysqliDb", false);
		$this->db = new MysqliDb ($this->config['db']['host'],  $this->config['db']['user'], $this->config['db']['password'], $this->config['db']['name']);
		//$this->db->rawQuery('set names utf8');
	}
	
	/**
	* @read error level from config and set in php settings
	*/
	function set_errors()
	{
		if ($this->config['general']['errors'] == true)
		{
			error_reporting(E_ALL);
			ini_set("display_errors", 1);
		}
		else
		{
			error_reporting(E_ALL & ~E_NOTICE);
			
			$error_path = ENGINE_PATH . "cache" . DS . "errors" . DS . "error.log";
			ini_set('display_errors','Off');
			ini_set('log_errors', 'On');
			ini_set('error_log', $error_path);
		}
	}
	
	function __construct()
	{
		
	}
	
	function __destruct()
	{  
		global $admin_menu;
		$admin_menu = array();
	}

	/**
	* @init multilang script
	*/
	function set_lang()
	{
		$lang_arr = $this->config['general']['langs'];
		if ($lang_arr)
		{
			$_SESSION['langs'] = $lang_arr;
			
			if (empty($_SESSION['lang']) && isset($_COOKIE['lang']))
			{
				$_SESSION['lang'] = $_COOKIE['lang'];
			}
			if (empty($_SESSION['lang']) || !in_array($_SESSION['lang'] , array_keys($lang_arr)))
			{
				reset($lang_arr);
				$_SESSION['lang'] = key($lang_arr);
			}
			
			$lang = $this->request->location(1);
			if (!empty($lang) && in_array($lang , array_keys($lang_arr)))
			{
				$_SESSION['lang'] = $lang;
			}
			
			if (!defined('LANG'))
				define('LANG', $_SESSION['lang']); 
			setcookie("lang", $_SESSION['lang'], time()+3600*24*365, "/");
		}
		else
		{
			$_SESSION['langs'] = null;
			$_SESSION['lang'] = null;
		}
	}
	
	public function add_to_twig($name, $obj)
	{
		$this->template->twig->register_global($name, $obj);
	}
	
	function setObserver(&$observer)
	{
		$this->observer = $observer;
	}
	
	function notify($mt, &$arg_0 = null, &$arg_1 = null, &$arg_2 = null, &$arg_3 = null, &$arg_4 = null, &$arg_5 = null, &$arg_6 = null, &$arg_7 = null, &$arg_8 = null, &$arg_9 = null)
	{
		$args = func_get_args();
		unset($args[0]);
		$this->observer->notify($mt, $args);
		if(is_array($args))
		{
			foreach($args as $k=>$arg)
			{
				$var_name = "arg_" . $k;
				$$var_name = $arg;
			}
		}
		else
		{
			$arg_0 = $args;
		}
	}
}


/** Check for Magic Quotes and remove them **/
function stripSlashesDeep($value) {
    $value = is_array($value) ? array_map('stripSlashesDeep', $value) : stripslashes($value);
    return $value;
}

function removeMagicQuotes() {
	if ( get_magic_quotes_gpc() )
	{
		$_GET    = stripSlashesDeep($_GET   );
		$_POST   = stripSlashesDeep($_POST  );
		$_COOKIE = stripSlashesDeep($_COOKIE);
	}
}

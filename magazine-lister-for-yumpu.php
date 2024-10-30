<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
/*
	Plugin Name: Magazine Lister for Yumpu
	Plugin URI: https://wordpress.org/plugins/magazine-lister-for-yumpu/
	Description: Displays your Yumpu Magazines as thumb nails, opens them in fullscreen. Magazines have to be organized in sections.
	Version: 1.4.0
	Author: Roberto Cornice <cornice@lemm.de>, Lemm Werbeagentur Gmbh (www.lemm.de)
	Author URI: https://www.lemm.de
	License: GPLv2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
	Text Domain: magazine-lister-for-yumpu
	Domain Path: /languages
*/

class magazinelisterforyumpu	{
	
	public static $instance;
	
	public $yumpu_collections;
	
	public $collections;
	
	public $token;
	
	public $showtitle;
	
	public $showdate;
	
	public $linktext;
	
	public $tokenAccepted;
	
	public $yumpu;
	
	
	/**
	 * @var string Yumpu collection ID
	 */
	private $yumpu_collection;
	
	/**
	 * @var string Yumpu section ID
	 */
	private $yumpu_section;
	
	
	/**
	 * @var Array Yumpu magazines
	 */
	public $docs;
	
	/**
	 * @var string thumbnails container template 
	 */
	private $container;
	
	
	/**
	 * @var string thumbnail template 
	 */
	private $template;
	
	/**
	 * @var parsed thumbnail template
	 */
	private $html;
	
	
	private $allowedCollections;
	
	
	/**
	 * Constructer
	 */
	public function __construct() {
		$this->init();
	}
	
	/**
	 * initializing
	 */
	private function init()	{
		include plugin_dir_path(__FILE__)."yumpu/yumpuMainClass.php";
		$this->getConfig();
		$this->token		= get_option("token", "");
		$this->showtitle	= get_option("showtitle", "");
		$this->showdate		= get_option("showdate", "");
		$this->linktext		= get_option("linktext", __("browse it","magazine-lister-for-yumpu"));
		$this->setYumpu();
		add_action( 'wp_enqueue_scripts', array( &$this, "setScripts" ) );

		
        add_shortcode( "yumpulister", array( $this, 'publish' ) );
		add_action('admin_init', array( &$this, 'admin_init' ) );
		add_action('admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', Array( &$this, 'loadAdminScripts') );
		add_action('plugins_loaded', array( &$this, 'hook_plugins_loaded'));
		add_action ( 'wp_enqueue_scripts', array( &$this, 'registerScripts' ) );
	}
	
	public function getCollectionObject()	{
		$obj	= new stdClass();
		$obj->collection_id	= "";
		$obj->section_id	= "";
		return $obj;
	}
	
	public function setCollections($collections)	{
		if( $collections["state"] === "success" )	{
			foreach( $collections["collections"] AS $colls )	{
				if( !empty($this->allowedCollections) && sizeof( $this->allowedCollections ) > 0 && !in_array( $colls["id"], $this->allowedCollections ) )	{
					continue;
				}
				$this->collections[$colls["id"]]	= new stdClass();
				$this->collections[$colls["id"]]->id	= $colls["id"];
				$this->collections[$colls["id"]]->name	= $colls["name"];
				$this->collections[$colls["id"]]->sections		= Array();
				if( isset( $colls["sections"] ) && is_array( $colls["sections"] ) )	{
					foreach( $colls["sections"] AS $section )	{
						$this->collections[$colls["id"]]->sections[$section["id"]]	= new stdClass();
						$this->collections[$colls["id"]]->sections[$section["id"]]->id	= $section["id"];
						$this->collections[$colls["id"]]->sections[$section["id"]]->name	= $section["name"];
					}
				}
			}
		}
	}

	public function turnOffCache()	{
		if( !defined('DONOTCACHEPAGE') )	{
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/**
	 * Yumpu setzen
	 */ 
	public function setYumpu()	{
		$this->yumpu		= new YumpuMainClass();
		$this->yumpu->config['token']	= $this->token;
	}
	
	public function ajaxAction()	{
		error_reporting(E_ALL);
		ini_set('display_errors', 1);
		$retObj	= new stdClass();
		$retObj->success	= false;
		if( isset( $_POST["mode"] ) )	{
			switch( $_POST["mode"] )	{
				case "getEmbedUrl":
					$retObj	= $this->ajaxGetEmbed($retObj);
					break;
			}
		}
		wp_send_json( $retObj );
		exit();
	}
	
	public function ajaxGetEmbed( $retObj )	{
		if( isset( $_POST["documentid"] ) )	{
			$data	= Array(
				"id"			=> $_POST["documentid"],
				"return_fields"	=> "embed_code"
			);

			$doc	= $this->yumpu->getDocument($data);

			if( is_array( $doc ) && isset( $doc["state"] ) && $doc["state"] === "success" )	{
				$retObj->success	= true;
				$iframe	= new \SimpleXMLElement( $doc["document"][0]["embed_code"] );
				$retObj->url	= (String) $iframe["src"];
			}
		}
		return $retObj;
	}
	
	public function publish( $atts, $content=null , $code )	{
		$this->turnOffCache();
		$this->setYumpuCollections();
		if( isset( $this->yumpu_collections[$atts["id"] ] ) )	{
			$this->yumpu_collection	= $this->yumpu_collections[$atts["id"] ]->collection_id;
			$this->yumpu_section	= $this->yumpu_collections[$atts["id"] ]->section_id;
		}
		$this->resetHTML();
		$this->getDocs();
		$this->getHTMLContainerTemplate();
		$this->getHTMLTemplate();
		$this->parseTemplate();
		$this->templateOptions();
		return $content.$this->html;
	}
	
	public function templateOptions()	{
		if( is_array( $this->docs["section"][0]["documents"] ) )	{
			if( intval($this->showtitle) !== 1 )	{
				$dom = new DomDocument();
				$dom->loadHTML(  utf8_decode( $this->html ) );
				$finder = new DomXPath($dom);
				$classname="yumpuPlayerDocHead";
				$nodes = $finder->query("//*[contains(@class, '$classname')]");
				if( $nodes )	{
					foreach( $nodes AS $node )	{
						$node->parentNode->removeChild($node);
					}
				}
				$this->html = $dom->saveHTML();
			}
			if( intval($this->showdate) !== 1 )	{
				$dom = new DomDocument();
				$dom->loadHTML( utf8_decode( $this->html ) );
				$finder = new DomXPath($dom);
				$classname="yumpuPlayerVadility";
				$nodes = $finder->query("//*[contains(@class, '$classname')]");
				if( $nodes )	{
					foreach( $nodes AS $node )	{
						$node->parentNode->removeChild($node);
					}
				}
				$this->html = $dom->saveHTML();
			}
		}
	}
	
	public function resetHTML()	{
		$this->html	= "";
	}
	
	/**
	 * enqueues the JS script
	 */
	public function setScripts()	{
		wp_enqueue_script( 'yumpueEpaperLister',
			plugins_url( '/js/yumpuPlayer.min.js', __FILE__ ),
			array('jquery')
		);
		wp_localize_script( 'yumpueEpaperLister', 'yumpuepaperlister_bob', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'title' => get_the_title()
			)
		);
	}
	
	/**
	 * get all Yumpu magazines of the given collection and section
	 */
	private function  getDocs()	{
		$yumpu   = $this->yumpu;
		$yumpu->config['token']	= $this->token;

		$data   = Array(
			"id"         => $this->yumpu_section,
			"return_fields"   => "documents"
		);
		$this->docs   = $yumpu->getSection($data);	
	}
	
	
	/**
	 * gets and sets the HTML template
	 */
	private function getHTMLContainerTemplate()	{
		$this->container	= file_get_contents(plugin_dir_path(__FILE__)."/templates/container.html");
	}
	
	
	/**
	 * gets and sets the HTML template
	 */
	private function getHTMLTemplate()	{
		$this->template	= file_get_contents(plugin_dir_path(__FILE__)."/templates/list.html");
	}
	
	/**
	 * parses the template by iterating every magazine
	 */
	private function parseTemplate()	{ 
		$innerHTML	= "";
		if( is_array( $this->docs["section"][0]["documents"] ) )	{
			foreach( $this->docs["section"][0]["documents"] AS $doc )	{
				$vonString	= "";
				$bisString	= "";
				$von	= null;
				$bis	= null;

				$vonString	= $doc["settings"]["date_validity_from"];
				$bisString	= $doc["settings"]["date_validity_until"];
				if( isset( $doc["settings"]["date_validity_from"] ) )   {
					$von   = $this->yumpuDate2Ts($doc["settings"]["date_validity_from"]);
				}
				if( isset( $doc["settings"]["date_validity_until"] ) )   {
					$bis   = $this->yumpuDate2Ts($doc["settings"]["date_validity_until"]);
				}
				
				if( !empty($von) && time() < $von )   {
					   continue;
				}

				if(  !empty($bis) && time() > $bis )   {
					   continue;
				}

				$gueltigkeit = "";
				if( ( isset( $von ) && !empty($von) ) || ( isset( $bis ) && !empty($bis) )  )   {
					if( empty( $von ) )	{
						$gueltigkeit = "Gültigkeit bis: ".$this->ts2date($bis)."";
					} elseif( empty( $bis ) )	{
						$gueltigkeit = "Gültigkeit ab: ".$this->ts2date($von)."";
					} else {
						$gueltigkeit = "Gültigkeit: ".$this->ts2date($von)." &ndash; ".$this->ts2date($bis)."";
					}
				}
//				$vonString      = "";
//				$bisString      = "";
//				$vonbistrenner   = "";
				$bildelemente = explode("/", $doc["cover"]);
				
				$bildelemente[5]	= "200x640";
				$thumbs_mobile		= implode("/", $bildelemente);
				$bildelemente[5]	= "425x640";
				$thumbs_tablet		= implode("/", $bildelemente);
				$bildelemente[5]	= "625x640";
				$thumbs_desktop		= implode("/", $bildelemente);
				$innerHTML	.= str_replace(
					Array(
						'<!--@id-->', 
						'<!--@link-->', 
						'<!--@mobile-->', 
						'<!--@tablet-->', 
						'<!--@desktop-->', 
						'<!--@name-->', 
						'<!--@gueltig-->', 
						'<!--@linktext-->', 
					),
					Array(
						$doc["id"],
						"https://www.yumpu.com/xx/document/view/".$doc["id"],
						htmlentities( $thumbs_mobile ),
						htmlentities( $thumbs_tablet ),
						htmlentities( $thumbs_desktop ),
						stripslashes($doc["title"]),
						$gueltigkeit,
						htmlentities( $this->linktext ),
					),
					$this->template
				);
			}
			$this->html	= str_replace("<!--@documents-->", $innerHTML, $this->container);
		}
	}
	
	
	/**
	 * generates a unix timestamp out of the retrieved magazine date
	 * @param string $yumpuDate
	 * @return int
	 */
	private function yumpuDate2Ts($yumpuDate)   {
		if( trim($yumpuDate) === "" )   {
		   return null;
		}
		$datum      = explode( " ", $yumpuDate);
		$datumArr   = explode("-", $datum[0]);
		$timeArr    = explode(":", $datum[1]);
		return mktime($timeArr[0], $timeArr[1], $timeArr[2], $datumArr[1], $datumArr[2], $datumArr[0]);
	}
	
	/**
	 * gets a date string out of a unix timestamp
	 * @param int $ts unix timestamp
	 * @param int $mode a mode to generate different date strings
	 * @return string
	 */
	private function ts2date($ts, $mode = 0) {
		switch (intval($mode)) {
			case 0:
				$retStr = "d.m.Y";
				break;
			case 1:
				$retStr = "d.m.Y (H:i:s)";
				break;
			case 2:
				$retStr = "H";
				break;
			case 3:
				$retStr = "i";
				break;
		}
		if (isset($ts) && $ts != "") {
			if ($ret = date($retStr, $ts)) {
				return $ret;
			}
		}
		return "n/a";
	}	
	
	public function setHTML($html)	{
		$this->html	= $html;
	}
	

	/**
	 * outputs the parsed html
	 */
	private function output()	{
		return $this->html;
	}
	
	
	public function admin_init()	{
		load_plugin_textdomain('magazine-lister-for-yumpu', false, plugin_basename(dirname(__FILE__)) . '/languages');
	}
	
	public function loadAdminScripts()	{
		wp_register_script( 'backendscript', plugins_url('/js/epaperlister_backend.js', __FILE__) );
		wp_enqueue_script( 'backendscript' );
	}
	
	public function admin_menu()	{
		$page_title = 'Magazine Lister for Yumpu';
		$menu_title = 'Magazine Lister for Yumpu';
		$capability = 'edit_posts';
		$menu_slug = 'magazine-lister-for-yumpu';
		$function = Array( &$this, 'option_page');
		$icon_url = '';
		$position = 24;
		add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
	}
	
	public function setYumpuCollections()	{
		$this->yumpu_collections	= get_option( 'yumpu_collections' );
//		$this->yumpu_collections	= (array) json_decode( get_option( 'yumpu_collections', json_encode(Array() ) ) );
	}
	
	public function updateYumpuCollections()	{
		update_option('yumpu_collections', $this->yumpu_collections);
//		update_option('yumpu_collections', json_encode($this->yumpu_collections));
	}
	
	public function checkToken()	{
		$retObj	= new stdClass();
		$retObj->success	= true;
		$retObj->err		= null;
		$collections	= $this->yumpu->getCollections(
			Array(
				"limit"		=> 100
				)
		);
		if( $collections["state"] !== "success" )	{
			$retObj->success	= false;
			$retObj->err		= $collections["error"];
		} else {
			$this->setCollections($collections);
		}
		return $retObj;
	}
	
	
	public function getConfig()	{
		include "config.php";
		$this->allowedCollections	= $allowed_collections;
		if( !empty( $this->allowedCollections ) && is_string( $this->allowedCollections ) )	{
			$this->allowedCollections	= Array( $this->allowedCollections );
		}
	}
	
	public function option_page() {
		$errMsg	= "";
		if( isset( $_POST["saveYumpuSettings"] ) )	{
			if( isset( $_POST["token"] ) && $this->token !== trim($_POST['token']) )	{
				$this->token					= trim($_POST['token']);
				$this->yumpu->config['token']	= trim($_POST['token']);
				update_option('token', $this->token);
			}
			if( !isset( $_POST["showtitle"] ) )	{
				$_POST["showtitle"]	= 0;
			}
			if( !isset( $_POST["showdate"] ) )	{
				$_POST["showdate"]	= 0;
			}
			if( !isset( $_POST["linktext"] ) )	{
				$_POST["linktext"]	= "";
			}
			$this->showtitle	= intval($_POST['showtitle']);
			update_option('showtitle', $this->showtitle);
				
			$this->showdate	= intval($_POST['showdate']);
			update_option('showdate', $this->showdate);
				
			$this->linktext	= trim($_POST['linktext']);
			update_option('linktext', $this->linktext);
			
		}
		$checkToken	= $this->checkToken();
		if( !$checkToken->success )	{
			$errMsg	= $checkToken->err;
			$this->tokenAccepted	= false;
		} else {
			$this->tokenAccepted	= true;
		}
		
		
		if( $this->tokenAccepted )	{
			$this->setYumpuCollections();
						
			$this->saveCollections();
			
			if (isset($_POST['add_collection'])) {
				$this->addCollection();
			}
			if (isset($_POST['collection_del'])) {
				$this->delCollection();
			}
			
			
		}

		include 'templates/backendForm.php';
	}
	
	public function saveCollections()	{
		
		if( is_array( $_POST["collection_id"] ) )	{
			foreach( $_POST["id"] AS $id=>$string_id )	{
				$this->yumpu_collections[$string_id]->collection_id	= $_POST["collection_id"][(string) $id];
				$this->yumpu_collections[intval($id)]->section_id		= $_POST["section_id"][$string_id];
			}
			$this->updateYumpuCollections();
		}
	}
	
	public function addCollection()	{
		if( is_array( $this->yumpu_collections ) && sizeof( $this->yumpu_collections ) > 0 )	{
			$id	= intval(max(array_keys($this->yumpu_collections)))+1;
		} else {
			$id	= 1;
		}
		$this->yumpu_collections[$id] = $this->getCollectionObject();
		$this->updateYumpuCollections();
	}
	
	public function delCollection()	{
		unset($this->yumpu_collections[ $_POST['collection_del'] ]);
		$this->updateYumpuCollections();
	}
	
	/**
	 * Registriert die notwendingen JS- und CSS-Files
	 */
	public function registerScripts()   {
	   wp_register_style( 'magazine-lister-for-yumpu', plugins_url( '/css/style.min.css?a', __FILE__ ) );
	   wp_enqueue_style( 'magazine-lister-for-yumpu' );
	}
	
	/**
	 * @return listerForYumpu
	 */
	public static function getInstance()	{
		if( !is_object( static::$instance ) )	{
			static::$instance	= new static();
		}
		return static::$instance;
	}
	
	
	/**
     * Load plugin textdomain.
     */
    function hook_plugins_loaded() {
//        if (function_exists('load_plugin_textdomain')) {
            load_plugin_textdomain('magazine-lister-for-yumpu', false, plugin_basename(dirname(__FILE__)) . '/languages');
//        }
    }
	
	
	public function checked( $expected, $val, $strict = false, $default = null )	{
		
		if( $default !== null && $val === null )	{
			$val = $default;
		}
		
		if( $strict && $val === $expected )	{
			return " checked=\"checked\"";
		} elseif( $val == $expected )	{
			return " checked=\"checked\"";
		}
		return "";
	}
	
	public function decho( $var )	{
		echo "<div style='padding:10px;border:4px dashed #666;z-index:999999999999;position: relative;background:#fefefe;left:0'><pre>";
		var_dump( $var );
		echo "</pre></div>";
	}
	
}


/**
 * start the action
 */
$magazinelisterforyumpu	= magazinelisterforyumpu::getInstance();
add_action( 'wp_ajax_nopriv_yumpuEpaperListerAjax', array( &$magazinelisterforyumpu, 'ajaxAction' ) );	
if( is_admin() )	{
	add_action( 'wp_ajax_yumpuEpaperListerAjax', array( &$magazinelisterforyumpu, 'ajaxAction' ) );
}
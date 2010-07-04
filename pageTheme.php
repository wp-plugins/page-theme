<?php
/*
Plugin Name: Page Theme
Plugin URI: http://wordpress.org/extend/plugins/page-theme/
Description: Per-page theme selection. With many thanks to Stephen Carroll's page plug-in. I re-used most of it :)
Version: 1.3
Author: Chris Ravenscroft
Author URI: http://nexus.zteo.com
*/

/* GPL License */

if(!empty($_GET['pcompaction'])) {
	new PCompAction($_GET['pcompaction']);
	exit;
}

// Blog posts: ..../?p=3109
// Pages: .../?page_id=2

$ruri = $_SERVER['REQUEST_URI'];
if(strlen($ruri) >= 2) {
	// Oh man a bug in strrpos...why???
#	$p = strrpos(rtrim($ruri, '/'), '/');
	$c = strlen($ruri) - 2;
	while($c > 0 && $ruri{$c} != '/') $c--;
	if($c !== false) {
		$pageTheme = new PageTheme(strtolower(str_replace('/', '', substr($ruri, $c))));
	}
}

if (!function_exists('plugin_dir_url')) {
        function plugin_dir_url($file) {
                if (!function_exists('plugins_url')) {
                        return trailingslashit(get_option('siteurl') . '/wp-content/plugins/' . plugin_basename($file));
                }
                return trailingslashit(plugins_url(plugin_basename(dirname($file))));
        }
}

class CustomPageColumnHandler {

	protected $options;

	function __construct() {
		try {
			$this->options = unserialize(get_option("pageTheme_options"));
		}
		catch(Exception $e) {
			$this->options = get_option("pageTheme_options");
		}
		
		if (gettype($this->options)!='array'){
		    $this->options = array();
		}
	}

	function getOptions($id) {
		// Locate the matching index for the current page
		foreach($this->options as $dt){
			if ($dt['id'] == $id){
				// Update the settings for the matching page
				return(
					array(
						'stylesheet' => $dt['theme'],
						'template' => $dt['theme']
					)
				);
			    }   
		}
		return false;
	}
}

class CustomPostColumnHandler {

	protected $options;

	function __construct() {
		try {
			$this->options = unserialize(get_option("pageTheme_options"));
		}
		catch(Exception $e) {
			$this->options = get_option("pageTheme_options");
		}
		
		if (gettype($this->options)!='array'){
		    $this->options = array();
		}
	}

	function getOptions($id) {
		// Locate the matching index for the current page
		foreach($this->options as $dt){
			if ($dt['id'] == $id){
				// Update the settings for the matching page
				return(
					array(
						'stylesheet' => $dt['theme'],
						'template' => $dt['theme']
					)
				);
			    }   
		}
		return false;
	}
}

class CustomColumnManager {
	const PAGE = 'page';
	const POST = 'post';

	protected static $instances = false;
	protected static $themes = false;
	protected $customColumnHandler = false;

	public static function getInstance($handlerType) {
		if(!self::$instances) {
			self::$instances = array();
			self::$themes = get_themes();
		}
		if(!self::$instances[$handlerType]) {
			switch($handlerType) {
				case self::PAGE:
					self::$instances[$handlerType] = new self(new CustomPageColumnHandler());
					break;
				case self::POST:
					self::$instances[$handlerType] = new self(new CustomPostColumnHandler());
					break;
				default:
					throw new Exception('Unknown handler type in CustomColumnManager');
			}
		}
		return self::$instances[$handlerType];
	}

	function __construct($customColumnHandler) {
		$this->customColumnHandler = $customColumnHandler;
	}

	function enumColumns($defaults) {
		$newDefaults = array();
		foreach($defaults as $key => $value) {
			if($key == 'author') {
				$newDefaults['ptheme'] = __('Theme');
			}
			$newDefaults[$key] = $value;
		}
		static $iKnowColumnsCount = false;
		if(!$iKnowColumnsCount) {
			$iKnowColumnsCount = true;
			$columnsCount = count($newDefaults);
			?>
			<script type="text/javascript" language="JavaScript">
				var columns_count = <?php echo $columnsCount; ?>;
			</script>
			<?php
		}
		return $newDefaults;
	}

	function handleRow($columnName, $pageId) {
		echo "<div id='ptheme".$pageId."' class='pthemebutton'>\n";
		$foundTheme = false;
		$options = $this->customColumnHandler->getOptions($pageId);
		if($options) {
			foreach(self::$themes as $theme){
				if($options['template'] == $theme['Template']) {
					$foundTheme = true;
					echo '<img class="pthemepreview" style="border:1px solid lightgray;" align="left" width="32" height="32" src="'.$theme["Theme Root URI"].'/'.$theme["Stylesheet"].'/'.$theme["Screenshot"]."\" />&nbsp;" . $theme['Name'] . "\n";
				}
			}
		}
		if(!$foundTheme) {
			echo '<img style="border:1px solid lightgray;" align="left" width="32" height="32" src="'.plugin_dir_url(__FILE__)."white.png\" />&nbsp;<em>Default Theme</em>\n";
		}
		echo "</div>\n";
	}
}

class PageTheme {   
    var $currentpage;
    var $stylesheet;
    var $template;
    var $options;
    var $uri;

    function __construct($page){
        // Get default settings
        $this->stylesheet = get_option("stylesheet");
        $this->template = get_option("template");
        $this->currentpage = $page;
        
	$this->customColumnManager = false;

        // Load page option settings
	// Some versions of Wordpress deserialize automatically. WP3.0 doesn't.
        global $wp_version;
	try {
		$this->options = unserialize(get_option("pageTheme_options"));
	}
	catch(Exception $e) {
		$this->options = get_option("pageTheme_options");
	}
        
        if (gettype($this->options)!="array"){
            $this->options = array();
        }
        
        // Locate the matching index for the current page
        foreach($this->options as $dt){
            if ($this->currentpage==$dt['url']){
                
                // Update the settings for the matching page
                $this->stylesheet = $dt['theme'];
                $this->template = $dt['theme'];
            }   
        }
        
        // Apply filters and actions
        add_filter('pre_option_stylesheet', array(&$this, 'getStylesheet'));
        add_filter('pre_option_template', array(&$this, 'getTemplate'));
	add_action('admin_head', array(&$this,'displayAdminHead'));
        add_action('admin_menu', array(&$this, 'displayAdminMenu'));
        
	add_action('manage_pages_columns', array(&$this, 'managePagesColumns'));
	add_action('manage_posts_columns', array(&$this, 'managePostsColumns'));
	add_action('manage_pages_custom_column', array(&$this, 'managePagesCustomColumn'), 10, 2);
	add_action('manage_posts_custom_column', array(&$this, 'managePostsCustomColumn'), 10, 2);

        // Specify uri for admin panels
        $this->uri = '?page=' . $this->getRightMost(__FILE__, 'plugins/');
    }

	// Display pages/posts management columns headers
	function managePagesColumns($defaults) {
		return CustomColumnManager::getInstance(CustomColumnManager::PAGE)->enumColumns($defaults);
	}
    
	function managePostsColumns($defaults) {
		return CustomColumnManager::getInstance(CustomColumnManager::POST)->enumColumns($defaults);
	}

	function managePagesCustomColumn($column_name, $page_id) {
		if($column_name != 'ptheme') {
			return;
		}
		CustomColumnManager::getInstance(CustomColumnManager::PAGE)->handleRow($column_name, $page_id);
	}

	function managePostsCustomColumn($column_name, $post_id) {
		if($column_name != 'ptheme') {
			return;
		}
		CustomColumnManager::getInstance(CustomColumnManager::POST)->handleRow($column_name, $post_id);
	}

    // Common string functions
    function getRightMost($sSrc, $sSrch) {
        for ($i = strlen($sSrc); $i >= 0; $i = $i - 1) {
            $f = strpos($sSrc, $sSrch, $i);
            if ($f !== FALSE) {
               return substr($sSrc,$f + strlen($sSrch), strlen($sSrc));
            }
        }
        return $sSrc;
    }

    function getThemeTitleByTemplate($template){
        
        // Return descriptive name for a given template name
        $themes = get_themes();
        foreach($themes as $theme){
            if ($template==$theme["Template"]){
                break;
            }
        }
        return $theme["Title"];
    }

	function displayAdminHead() {
		$remoteLoader = '/wp-admin/edit.php?post_type=page&pcompaction=pcomphtml';
		$remoteSave = '/wp-admin/edit.php?post_type=page&pcompaction=pcompsave';
		?>
                <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) ?>emposha/fcbklistselection.css" type="text/css" media="screen" />
		<script type="text/javascript" src="<?php echo plugin_dir_url(__FILE__) ?>emposha/fcbklistselection.js" />
		<script type="text/javascript" language="JavaScript">
		var CCHandler = {
			current_theme_screen_comp: null,
			show_theme_screen: function(comp) {
				if(this.current_theme_screen_comp == comp) {
					this.current_theme_screen_comp = null;
					return this;
				}
				this.current_theme_screen_comp = comp;
				if(jQuery(comp).parent().parent().hasClass('alternate')) {
					var class_str = ' class="alternate"';
				}
				else {
					var class_str = '';
				}
				jQuery(comp).parent().parent().after(function(index) {
					return "<tr" + class_str + " id='pcompcurrent'><td colspan='"+columns_count+"'><div id='pcompcontainer'></div></td></tr>"
				});
				var post_id = this.current_theme_screen_comp.id.replace(/ptheme/, '');
				jQuery('#pcompcontainer').html("Hold it...").load(
					'<?php echo $remoteLoader; ?>&post_id=' + post_id, function() {
					CCHandler.prepare_for_selection();
				});
				return this;
			},
			hide_theme_screen: function() {
				if(this.current_theme_screen_comp != null) {
					jQuery('#pcompcurrent').remove();
				}
				return this;
			},
			prepare_for_selection: function() {
				jQuery.fcbkListSelection("#fcbklist","300","250","2", function(selected) {
					var themeSelect = jQuery('#theme')[0];
					for(var i=0; i<themeSelect.options.length; i++) {
						if(themeSelect.options[i].value == selected) {
							themeSelect.options[i].selected = true;
							break;
						}
					}
				});
				updateGalleryUsingSelect = function() {
					var selected = jQuery('#theme option::selected')[0].value;
					jQuery('#fcbklist').each(function() {
						jQuery(this).children().each(function() {
							var myid = jQuery(this)[0].id;
							if(myid == selected) {
								var obj = jQuery(jQuery(jQuery(this)[0]).children()[0]);
								obj.click();
							}
						});
					});
				}
				jQuery('#theme').change(function() {
					updateGalleryUsingSelect();
				});
				updateGalleryUsingSelect();
				jQuery('#pthemesubmit').click(function() {
					var selected = jQuery('#theme option::selected')[0].value;
					var post_id = CCHandler.current_theme_screen_comp.id.replace(/ptheme/, '');
					jQuery.get(
						'<?php echo $remoteSave; ?>',
						{post_id:post_id, theme:selected},
						function(responseText) {
							var res = responseText.split("\n");
							if(res.length != 3) {
								alert(responseText);
							}
							else {
								jQuery(CCHandler.current_theme_screen_comp).html(
									'<img class="pthemepreview" style="border:1px solid lightgray;" align="left" width="32" height="32" src="' + res[2] + "\" />&nbsp;" + res[1] + "\n");
								CCHandler.hide_theme_screen().show_theme_screen(CCHandler.current_theme_screen_comp);
							}
						},
						'html');
					return false;
				});
			}
		};
		jQuery(document).ready(function() {
			jQuery.ajaxSetup({cache: false}); // TODO Really?
			jQuery('.pthemebutton').click(function() {
				CCHandler.hide_theme_screen().show_theme_screen(this);
			}).hover(
				function() {
					this.style.cursor = 'pointer';
					var thisCopy = this;
					hover_intent = setTimeout(
						function() {
							jQuery(thisCopy).find('.pthemepreview').width(300).height(250);
						}, 1000);
				},
				function() {
					try{clearTimeout(hover_intent);} catch(e){}
					jQuery(this).find('.pthemepreview').width(32).height(32);
				});
		});

		</script>
                <?php

	}

    function displayAdminMenu(){
	// Show up under "Appearance"
        add_theme_page('Page Theme Options', 'Page Theme', 8, __FILE__, array(&$this, 'createAdminPanel'));
    }
    
    function getStylesheet(){
        return $this->stylesheet;
    }

    function getTemplate(){
        return $this->template;
    }
    
	function getPageByID($id) {
		global $wpdb;
		$id = intval($id);
		$pagepost = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE ID = $id");
		if($pagepost && count($pagepost) == 1) {
			return $pagepost[0];
		}
		else {
			return false;
		}
	}

	function getPageByName($name) {
		global $wpdb;
		$name = mysql_real_escape_string($name);
		$pagepost = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_title = '$name'");
		if($pagepost && count($pagepost) == 1) {
			$page = $pagepost[0];
			return $page->ID;
		}
		else {
			return false;
		}
	}

	function getPages($post_parent, $page_id=false, $depth = 0){
			global $wpdb, $wp_version, $excludePagesFeature, $excludePagesList, $renamePagesFeature;
			$ret = '';
			$pageposts = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_type = 'page' AND post_parent = '$post_parent' ORDER BY menu_order");
			
			if ($pageposts) {
				foreach ($pageposts as $page) {
					$text = str_repeat('--', $depth) . ' ' . $page->post_title;
					if($page_id !== false && $page_id == $page->ID) {
						$ret .= '<option value="'.$page->ID.'" selected>'.$text."</option>\n";
					}
					else {
						$ret .= '<option value="'.$page->ID.'">'.$text."</option>\n";
					}
					$ret .= $this->getPages($page->ID, $page_id, $depth + 1);

				}
				return $ret;
			} else {
				return false;
			}
		}

    // Create the administration panel
    function createAdminPanel(){
        
        // Check if we need to add a page
        if ($_GET['action']=="addPage"){
            $page['id']=intval($_POST['id']);
            $page['url']=strtolower($this->getPageByID($_POST['id']));
            if(!$page['url']) {
                die("Error retrieving page name");
            }
            $page['theme']=$_POST['theme'];
            array_push($this->options, $page);

            update_option("pageTheme_options", serialize($this->options));
        }
        
        // Check if we need to edit a page
        if ($_GET['action']=="editPage"){
            $pid = $_GET['pid'];
            $this->options[$pid]['id']=intval($_POST['id']);
            $this->options[$pid]['url']=strtolower($this->getPageById($_POST['id']));
            if(!$this->options[$pid]['url']) {
                die("Error retrieving page name");
            }
            $this->options[$pid]['theme']=$_POST['theme'];
            update_option("pageTheme_options", serialize($this->options));
        }
        
        // Check if we need to delete one or more pages.
        if ($_GET['action']=="del" && $_POST['chkDelete']){
            foreach(array_reverse($_POST['chkDelete']) as $pid){
                array_splice($this->options,$pid,1);
            }
            update_option("pageTheme_options", serialize($this->options));
        }
        
$javascript = <<<EOB
<script type="text/javascript" language="JavaScript">
    jQuery(document).ready(function() {
	// Yes, A changes B and B changes A.
	// There should not be any awful loop as B will not update itself if value already selected.

      //id(ul id),width,height(element height),row(elements in row)
      jQuery.fcbkListSelection("#fcbklist","300","250","2", function(selected) {
		var themeSelect = jQuery('#theme')[0];
		for(var i=0; i<themeSelect.options.length; i++) {
			if(themeSelect.options[i].value == selected) {
				themeSelect.options[i].selected = true;
				break;
			}
		}
	});

	updateGalleryUsingSelect = function() {
		var selected = jQuery('#theme option::selected')[0].value;
		jQuery('#fcbklist').each(function() {
			jQuery(this).children().each(function() {
				var myid = jQuery(this)[0].id;
				if(myid == selected) {
					var obj = jQuery(jQuery(jQuery(this)[0]).children()[0]);
					obj.click();
				}
			});
		});
	}

	jQuery('#theme').change(function() {
		updateGalleryUsingSelect();
	});

	updateGalleryUsingSelect();
    });
</script>
EOB;

        // Check if we should display the edit panel
        if ($_GET['action']=="pageProps"){
            $pid = $_GET['pid'];
            echo '<div class="wrap">
                    <form name="editPage" id="editPage" action="'.$this->uri.'&action=editPage&pid='.$pid.'" method="post">
                    <h2>' . __('Edit Page Theme') . '</h2>
                    <br class="clear" />
                    <div class="tablenav">
                        <br class="clear" />
                    </div>
                    <br class="clear" />
                    <table class="form-table">
                        <tr class="form-field">
                            <th scope="row" valign="top"><label for="page">Page</label></th>
			<td><select name="page" id="page" class="postform">';

			echo $this->getPages(0, $this->options[$pid]['id']);
         echo '
                            </select><br />The page that is used to access the site (i.e. www.example.com).</td>
                        </tr>
                        <tr class="form-field">
                            <th scope="row" valign="top"><label for="theme">Theme</label></th>
                            <td>
                                <select name="theme" id="theme" class="postform" >';
                                $themes = get_themes();
                                foreach($themes as $theme){
                                    if ($theme["Template"]==$this->options[$pid]['theme']){
                                        echo '<option value="'.$theme["Template"].'" selected>'.$theme["Name"].'</option>';
                                    }else{
                                        echo '<option value="'.$theme["Template"].'">'.$theme["Name"].'</option>';
                                    }
                                }
         echo '                     </select>
<ul id="fcbklist">';
                                    foreach($themes as $theme){
					echo '<li id="'.$theme['Template'].'"><div style="font-weight:bold;">'.$theme["Name"].'</div><img src="'.$theme["Theme Root URI"].'/'.$theme["Stylesheet"].'/'.$theme["Screenshot"]."\" /></li>\n";
				}
            echo '              </ul>
                                <br />
                                Specify the theme to use when the site is accessed by the given page.
                            </td>
                        </tr>
                    </table>
                    <p class="submit"><input type="submit" class="button" name="submit" value="Edit Page" /> <a href='.$this->uri.'>Back to list</a></p>
                    </form>
                 </div>';
		echo $javascript;
            return;
        }
        
        // Inject the javascript for delete check all option
        echo '<script language="Javascript">
                (function($){
                    $(function(){
                        $("#chkAll").click(function(){
                            c=this.checked;
                            $(".chkDelete").each(function(i){
                                this.checked=c;
                            })
                        });
                    })
                })(jQuery);
              </script>';
        
        // Create the list
        echo '<div class="wrap">
                <form name="page" id="pageList" action="'.$this->uri.'&action=del'.'" method="post">
                <h2>' . __('Page Theme') . ' (<a href="#addPage">add new</a>)</h2>
                <br class="clear" />
                <div class="tablenav">
                    <div class="alignleft">
                        <input type="submit" value="Delete" name="deleteit" class="button-secondary delete" />
                    </div>
                    <br class="clear" />
                </div>
                <br class="clear" />
                <table class="widefat">
                <thead>
                    <tr>
                        <th scope="col" class="check-column"><input type="checkbox" id="chkAll" /></th>
                        <th scope="col">Page</th>
                        <th scope="col">Theme</th>
                    </tr>
                </thead>
                <tbody id="the-list" class="list:page">';
        $i=0;
        foreach($this->options as $page){
            echo'   <tr id="page-'.$i.'" ';
            if (!fmod($i,2)){
                echo '>';
            }else{
                echo 'class="alternate">'; 
            }
            echo'       <th scope="row" class="check-column"><input type="checkbox" name="chkDelete[]" class="chkDelete" value="'.$i.'" /></th>
                        <td><a href="'.$this->uri.'&action=pageProps&pid='.$i.'">'.$page['url'].'</a></td>
                        <td>'.$this->getThemeTitleByTemplate($page['theme']).'</td>
                    </tr>
                </tbody>
                ';            
            $i++;
        }
        
        // Create the add form
        echo '  </table>
                </form>
                <div class="tablenav">
                    <br class="clear" />
                </div>
                </div>
                <br class="clear" />
                <br class="clear" />
                <div class="wrap">
                    <h2>Custom Theme</h2>
                    <form name="addPage" id="addPage" action="'.$this->uri.'&action=addPage" method="post">
                        <table class="form-table">
                            <tr class="form-field">
                                <th scope="row" valign="top"><label for="page">Page</label></th>
				<td><select name="page" id="page" class="postform">';
				echo $this->getPages(0);
         echo '
                                </select><br />The page that is used to access the site (i.e. www.example.com).</td>
                            </tr>
                            <tr class="form-field">
                                <th scope="row" valign="top"><label for="theme">Theme</label></th>
                                <td>
                                    <select name="theme" id="theme" class="postform" >';
                                    $themes = get_themes();
                                    foreach($themes as $theme){
                                        echo '<option value="'.$theme["Template"].'">'.$theme["Name"].'</option>';
                                    }
         echo '                     </select>
<ul id="fcbklist">';
                                    foreach($themes as $theme){
					echo '<li id="'.$theme['Template'].'"><div style="font-weight:bold;">'.$theme["Name"].'</div><img src="'.$theme["Theme Root URI"].'/'.$theme["Stylesheet"].'/'.$theme["Screenshot"]."\" /></li>\n";
				}
         echo '                     </ul>
                                    <br />
                                    Specify the theme to use when the site is accessed by the given page.
                                </td>
                            </tr>
                        </table>
                    <p class="submit"><input type="submit" class="button" name="submit" value="Set Theme" /></p>
                    </form>
                </div>
        ';
	echo $javascript;
    }
}       

class PCompAction {
	function __construct($action){
		switch($action) {
			case 'pcomphtml':
				$this->pcomphtml();
				break;
			case 'pcompsave':
				$this->pcompsave();
				break;
		}
	}

	function getPageThemeOptions() {
		try {
			$options = unserialize(get_option("pageTheme_options"));
		}
		catch(Exception $e) {
			$options = get_option("pageTheme_options");
		}
		
		if (gettype($options)!='array'){
		    $options = array();
		}
		return $options;
	}

	function getPostByID($id) {
		global $wpdb;
		$pagepost = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE ID = $id");
		if($pagepost && count($pagepost) == 1) {
			return $pagepost[0];
		}
		else {
			return false;
		}
	}

	function getThemeInfoByTemplate($template) {
	    $themes = get_themes();
	    foreach($themes as $theme){
		if($theme['Template'] == $template) {
			return $theme;
		}
	    }
	 return false;
	}

	function pcomphtml() {
		if(empty($_GET['post_id'])) {
			die("Missing parameters");
		}
		$post_id = intval($_GET['post_id']);
		if(!$post_id) {
			die("Invalid post id");
		}
		$currentTheme = false;
		$options = $this->getPageThemeOptions();
		foreach($options as $option) {
			if($option['id'] == $post_id) {
				$currentTheme = $option['theme'];
				break;
			}
		}
                echo '<div class="wrap">
                    <h2>Custom Theme</h2>
                        <table class="form-table">
                            <tr class="form-field">
                                <th scope="row" valign="top"><label for="theme">Theme</label></th>
                                <td>
                                    <select name="theme" id="theme" class="postform" >
					<option value="pthemedefault">[Default]</option>';
                                    $themes = get_themes();
                                    foreach($themes as $theme){
					if($theme['Template'] == $currentTheme) {
						echo '<option value="'.$theme["Template"].'" selected>'.$theme["Name"].'</option>';
					}
					else {
						echo '<option value="'.$theme["Template"].'">'.$theme["Name"].'</option>';
					}
                                    }
// TODO Provide real blank image
		 echo '             </select>
<ul id="fcbklist">
<li id="pthemedefault"><div style="font-weight:bold;">[Default]</div><img src="' . plugin_dir_url(__FILE__) . 'white.png" /></li>';
                                    foreach($themes as $theme){
					echo '<li id="'.$theme['Template'].'"><div style="font-weight:bold;">'.$theme["Name"].'</div><img src="'.$theme["Theme Root URI"].'/'.$theme["Stylesheet"].'/'.$theme["Screenshot"]."\" /></li>\n";
				}
		 echo '                     </ul>
                                    <br />
                                    Specify the theme to use when the site is accessed by the given page.
                                </td>
                            </tr>
                        </table>
                    <p class="submit"><input type="submit" id="pthemesubmit" class="button" name="submit" value="Set Theme" /></p>
                </div>
		';
	}

	function pcompsave() {
		if(empty($_GET['post_id']) || empty($_GET['theme'])) {
			die("Missing parameters");
		}
		$post_id = intval($_GET['post_id']);
		if(!$post_id) {
			die("Invalid post id");
		}
		$theme_name = mysql_real_escape_string($_GET['theme']);
		$post = array();
		$post['id']  = $post_id;
		$postInfo = $this->getPostByID($post_id);
		if(!$postInfo) {
			die("Error retrieving post name");
		}
		$post['url'] = strtolower($postInfo->post_name);
		$post['theme'] = $theme_name;
		$options = $this->getPageThemeOptions();
		$newOptions = array();
		foreach($options as $option) {
			if($option['id'] == $post_id) {
				continue;
			}
			array_push($newOptions, $option);
		}
		if($theme_name != 'pthemedefault') {
			array_push($newOptions, $post); // add new option for this post
		}
		update_option("pageTheme_options", serialize($newOptions));

		if($theme_name != 'pthemedefault') {
			$theme = $this->getThemeInfoByTemplate($theme_name);
			$response = "OK\n" . $theme['Name'] . "\n" . $theme["Theme Root URI"].'/'.$theme["Stylesheet"].'/'.$theme["Screenshot"];
		}
		else {
			$response = "OK\n<em>Default Theme</em>\n".plugin_dir_url(__FILE__)."white.png";
		}
		echo $response;
	}
}

?>

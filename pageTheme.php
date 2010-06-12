<?php
/*
Plugin Name: Page Theme
Plugin URI: http://wordpress.org/extend/plugins/page-theme/
Description: Per-page theme selection. With many thanks to Stephen Carroll's page plug-in. I re-used most of it :)
Version: 1.2
Author: Chris Ravenscroft
Author URI: http://nexus.zteo.com
*/

/* GPL License */

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

class PageTheme {   
    var $currentpage;
    var $stylesheet;
    var $template;
    var $options;
    var $uri;
            
    function PageTheme($page){
        // Get default settings
        $this->stylesheet = get_option("stylesheet");
        $this->template = get_option("template");
        $this->currentpage = $page;
        
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
        
        // Specify uri for admin panels
        $this->uri = '?page=' . $this->getRightMost(__FILE__, 'plugins/');
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
		?>
                <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) ?>emposha/fcbklistselection.css" type="text/css" media="screen" />
		<script type="text/javascript" src="<?php echo plugin_dir_url(__FILE__) ?>emposha/fcbklistselection.js">
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
    
	function getPages($post_parent, $page_url=false, $depth = 0){
			global $wpdb, $wp_version, $excludePagesFeature, $excludePagesList, $renamePagesFeature;
			$ret = '';
			$pageposts = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_type = 'page' AND post_parent = '$post_parent' ORDER BY menu_order");
			
			if ($pageposts == true){
				foreach ($pageposts as $page) {
					$text = str_repeat('--', $depth) . ' ' . $page->post_title;
					if($page_url !== false && $page_url == $page->post_name) {
						$ret .= '<option value="'.$page->post_name.'" selected>'.$text."</option>\n";
					}
					else {
						$ret .= '<option value="'.$page->post_name.'">'.$text."</option>\n";
					}
					$ret .= $this->getPages($page->ID, $page_url, $depth + 1);

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
            $page['url']=strtolower($_POST['page']);
            $page['theme']=$_POST['theme'];
            array_push($this->options, $page);

            update_option("pageTheme_options", serialize($this->options));
        }
        
        // Check if we need to edit a page
        if ($_GET['action']=="editPage"){
            $id = $_GET['id'];
            $this->options[$id]['url']=strtolower($_POST['page']);
            $this->options[$id]['theme']=$_POST['theme'];
            update_option("pageTheme_options", serialize($this->options));
        }
        
        // Check if we need to delete one or more pages.
        if ($_GET['action']=="del" && $_POST['chkDelete']){
            foreach(array_reverse($_POST['chkDelete']) as $id){
                array_splice($this->options,$id,1);
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
            $id = $_GET['id'];
            echo '<div class="wrap">
                    <form name="editPage" id="editPage" action="'.$this->uri.'&action=editPage&id='.$id.'" method="post">
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

			echo $this->getPages(0, $this->options[$id]['url']);
         echo '
                            </select><br />The page that is used to access the site (i.e. www.example.com).</td>
                        </tr>
                        <tr class="form-field">
                            <th scope="row" valign="top"><label for="theme">Theme</label></th>
                            <td>
                                <select name="theme" id="theme" class="postform" >';
                                $themes = get_themes();
                                foreach($themes as $theme){
                                    if ($theme["Template"]==$this->options[$id]['theme']){
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
                        <td><a href="'.$this->uri.'&action=pageProps&id='.$i.'">'.$page['url'].'</a></td>
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
                    <h2>Add Page</h2>
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
                    <p class="submit"><input type="submit" class="button" name="submit" value="Add Page" /></p>
                    </form>
                </div>
        ';
	echo $javascript;
    }
}       
?>

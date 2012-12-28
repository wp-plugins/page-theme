<?php
/*
Plugin Name: Page Theme
Plugin URI: http://wordpress.org/extend/plugins/page-theme/
Description: Per-page, per-post theme selection. Works with both SEO and non-SEO permalinks.
Version: 3.5
Author: Chris Ravenscroft
Author URI: http://nexus.zteo.com
*/

/* GPL License */

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        if (!function_exists('plugins_url')) {
            return trailingslashit(get_option('siteurl') . '/wp-content/plugins/' . plugin_basename($file));
        }
        return trailingslashit(plugins_url(plugin_basename(dirname($file))));
    }
}

if(!empty($_GET['pcompaction'])) {
    new PCompAction($_GET['pcompaction']);
    exit;
}

$ruri = $_SERVER['REQUEST_URI'];

if(strlen($ruri) >= 2) {
    $pageId = false;
    list($first, $second) = explode('?', $ruri);
    if(!empty($second)) {
        $fragments = explode('&', substr($ruri, ($p + 1)));
        foreach($fragments as $fragment) {
            $bits = explode('=', $fragment);
            if($bits[0] == 'p' || $bits[0] == 'page') {
                $pageId = intval($bits[1]);
                break;
            }
        }
    }
    if(false !== $pageId) {
        $pageTheme = new PageTheme($pageId);
    }
    else {
        $c = strlen($first) - 2;
        while($c > 0 && $first{$c} != '/') $c--;
        $pageTheme = new PageTheme(strtolower(str_replace('/', '', substr($first, $c))));
    }
}
else if($ruri == '/') {
    $pageTheme = new PageTheme('');
}

class PageThemeOptions {

    protected static $instance = false;

    public static function getInstance() {
        if(!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function getOptions() {
        // Load page option settings
        // Some versions of Wordpress deserialize automatically. WP3.0 doesn't.
        try {
            $options = unserialize(get_option("pageTheme_options"));
        }
        catch(Exception $e) {
            $options = get_option("pageTheme_options");
        }
        
        if (gettype($options)!="array"){
            $options = array();
        }

        return $options;
    }

    function setOptions($options) {
        update_option("pageTheme_options", serialize($options));
    }

    function getDispOptions() {
        try {
            $options = unserialize(get_option("pageTheme_disp_options"));
        }
        catch(Exception $e) {
            $options = get_option("pageTheme_disp_options");
        }
        
        if (gettype($options)!="array"){
            $options = array();
        }

        return $options;
    }

    function setDispOptions($options) {
        update_option("pageTheme_disp_options", serialize($options));
    }
}

class PageThemeAdminManager {

    protected $uri;

    function __construct() {
        $dispOptions = PageThemeOptions::getInstance()->getDispOptions();

        add_action('admin_head', array(&$this,'displayAdminHead'));
        add_action('admin_menu', array(&$this, 'displayAdminMenu'));

        if($dispOptions['disppages']) {
            add_action('manage_pages_columns', array(&$this, 'managePagesColumns'));
            add_action('manage_pages_custom_column', array(&$this, 'managePagesCustomColumn'), 10, 2);
        }

        if($dispOptions['dispposts']) {
            add_action('manage_posts_columns', array(&$this, 'managePostsColumns'));
            add_action('manage_posts_custom_column', array(&$this, 'managePostsCustomColumn'), 10, 2);
        }
    
        // Specify uri for admin panels
        $this->uri = '?page=' . $this->getRightMost(__FILE__, 'plugins/');
    }

    // Common string function
    private function getRightMost($sSrc, $sSrch) {
        for ($i = strlen($sSrc); $i >= 0; $i = $i - 1) {
            $f = strpos($sSrc, $sSrch, $i);
            if ($f !== FALSE) {
               return substr($sSrc,$f + strlen($sSrch), strlen($sSrc));
            }
        }
        return $sSrc;
    }

    function displayAdminHead() {
        wp_enqueue_script('jquery');
        $remoteLoader = get_option('siteurl').'/wp-admin/edit.php?post_type=page&pcompaction=pcomphtml';
        $remoteSave = get_option('siteurl').'/wp-admin/edit.php?post_type=page&pcompaction=pcompsave';
        $loadingHTML = '<img src="'.plugin_dir_url(__FILE__)."loading.gif\" />";
        ?>
        <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) ?>emposha/fcbklistselection.css" type="text/css" media="screen" />
        <script type="text/javascript" src="<?php echo plugin_dir_url(__FILE__) ?>emposha/fcbklistselection.js"></script>
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
                jQuery('#pcompcontainer').html('<?php echo $loadingHTML; ?>').load(
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
                    var selected = jQuery('#theme option:selected')[0].value;
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
                    var selected = jQuery('#theme option:selected')[0].value;
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

    // Create the administration panel
    function createAdminPanel(){
        if ($_GET['action']=="saveSettings"){
            $dispOptions = array(
                'disppages' => (!empty($_POST['pthemedisppages']) ? $_POST['pthemedisppages'] : 0),
                'dispposts' => (!empty($_POST['pthemedispposts']) ? $_POST['pthemedispposts'] : 0),
            );
            PageThemeOptions::getInstance()->setDispOptions($dispOptions);
        }
        else {
            $dispOptions = PageThemeOptions::getInstance()->getDispOptions();
        }

        echo '
            <div class="wrap">
                <div id="icon-themes" class="icon32"><br /></div> 
                <h2>Page-Theme Settings</h2>
                <form name="themeSettings" id="themeSettings" action="'.$this->uri.'&action=saveSettings" method="post">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><strong>Admin Panel</strong></th>
                            <td>
                                <p><label>
                                    <input type="checkbox" name="pthemedisppages" value="1" ' . ($dispOptions['disppages'] ? 'checked' : '') . '/>
                                    Add to "Pages" admin screen
                                    <br /><em>When this option is selected, the "Pages > Pages" admin screen will display an additional column, letting you edit each page\'s theme.</em>
                                </label></p>
                                <p><label>
                                    <input type="checkbox" name="pthemedispposts" value="1" ' . ($dispOptions['dispposts'] ? 'checked' : '') . ' />
                                    Add to "Posts" admin screen
                                    <br /><em>When this option is selected, the "Posts > Posts" admin screen will display an additional column, letting you edit each post\'s theme.</em>
                                </label></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit"><input type="submit" class="button-primary" name="submit" value="Save Settings" /></p>
                </form>
            </div>

<div id="dashboard-widgets-wrap"> 
    <div id="dashboard-widgets" class="metabox-holder"> 
        <div class="postbox-container">
            <div id="normal-sortables" class="meta-box-sortables">
                <div id="dashboard_right_now" class="postbox "> 
                    <div class="handlediv" title="Click to toggle"><br /></div>
                    <h3 class="hndle"><span>About</span></h3> 
                    <div class="inside" style="padding:8px"> 
                        <p>
                            This plugin lets you select specific themes for selected pages and blog posts. Despite its name, it works with both pages and posts. It also supports both SEO and non-SEO permalinks.
                        </p>
                        <p>
                            After enabling the extra columns for pages and/or posts using this screen, you will be able to select these themes on their respective screens by clicking on each page/post\'s current theme.
                        </p>
                        <p>
                            <strong>Note</strong>: by default, all posts and pages are displayed using the "Default Theme."
                        </p>
                        <hr />
                        <p>
                            <em>This plugin was created by Chris F. Ravenscroft.</em> Need assistance? Visit my blog at <a href="http://nexus.zteo.com" target="_blank">http://nexus.zteo.com</a> or follow me on <a href="http://twitter.com/chrisfr" target="_blank">Twitter</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        ';
    }

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
}

// Four possibilities: 
// - a SEO page, ...../name
// - a non-SEO page, ..../?page_id=n
// - a SEO post, ..../name
// - a non-SEO post, ..../?p=n

class PageThemeThemeSwitcher {

    private $stylesheet;
    private $template;

    function __construct($page) {
        // Get default settings
        $this->stylesheet = get_option("stylesheet");
        $this->template = get_option("template");

        $options = PageThemeOptions::getInstance()->getOptions();
        
        $pAliased = false;
        if($page == '') {
            if(get_option("show_on_front") == 'page') {
                $page = intval(get_option("page_on_front"));
                if($page > 0) {
                    $pAliased = true;
                }
            }
            if(!$pAliased) {
                return;
            }
        }

        $page_id = false;
        if($pAliased) {
            $page_id = $page;
        }
        else if(strncmp($page, '?p=', 3) == 0) {
            $page_id = substr($page, 3);
        }
        else if(strncmp($page, '?page_id=', 9) == 0) {
            $page_id = substr($page, 9);
        }

        if($page_id) {
            foreach($options as $dt){
                if ($page_id==$dt['id']){
                    // Update the settings for the matching page
                    $this->stylesheet = $dt['theme'];
                    $this->template = $dt['theme'];
                }   
            }
        }
        else {
            // Locate the matching index for the current page
            foreach($options as $dt){
                if ($page==$dt['url']){
                    // Update the settings for the matching page
                    $this->stylesheet = $dt['theme'];
                    $this->template = $dt['theme'];
                }   
            }
        }

        add_filter('pre_option_stylesheet', array(&$this, 'getStylesheet'));
        add_filter('pre_option_template', array(&$this, 'getTemplate'));
    }

    function getStylesheet(){
        return $this->stylesheet;
    }

    function getTemplate(){
        return $this->template;
    }
}

class CustomPageColumnHandler {

    protected $options;

    function __construct() {
        $this->options = PageThemeOptions::getInstance()->getOptions();
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
        $this->options = PageThemeOptions::getInstance()->getOptions();
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

    function __construct($page){
        new PageThemeThemeSwitcher($page);
        new PageThemeAdminManager();
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
        return PageThemeOptions::getInstance()->getOptions();
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
        PageThemeOptions::getInstance()->setOptions($newOptions);

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

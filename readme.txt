=== Page Theme ===
Contributors: Chris Ravenscroft
Tags: page, pages, url, interface, appearance, style, CSS, theme, themes, skin
Requires at least: 2.6.1
Tested up to: 3.0.0
Stable tag: 1.1

Specify the theme (not template, that is already taken care of by Wordpress) you wish to use with each given page.

== Description ==

* Keep updated! http://nexus.zteo.com/
* This plugin can be used to display a different theme for each page of your blog.
* The interface used to configure this is very user-friendly.
* Many thanks to Stephen Carroll whose domain page's script was a heavy "inspiration"
* Thanks also go to emposha.com for their image selection script, which I shamelessly hacked and surely made worse.

== Installation ==

1. Expand this plugin's archive content to wp-content/plugins/
2. Go to Administration > Plugins and activate this plugin.
3. You can now configure your page/theme matches at ACP > Appearance > Page Theme

== Limitations ==

* Due to Wordpress selecting themes and stylesheets very early on when building a page, I cannot use Wordpress' "official" way to detect the current page. Currently, this means that only "beautiful" links will work; e.g. http://www.example.com/mypage/... and not http://www.example.com/?p=xxxx
* So far, this plugin only handles pages. It would not be difficult to handle posts as well. Just let me know if you would like this feature added.

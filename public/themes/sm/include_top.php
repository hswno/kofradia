<?php

// utf-8
header("Content-Type: text/html; charset=utf-8");

// sett opp head
$head = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="author" content="Henrik Steen; http://www.henrist.net" />
<meta name="keywords" content="'.ess::$b->page->generate_keywords().'" />
<meta name="description" content="'.ess::$b->page->description.'" />
<link rel="shortcut icon" href="'.ess::$s['relative_path'].'/favicon.ico" />
<link rel="alternate" href="'.ess::$s['relative_path'].'/rss/forum_topics" type="application/rss+xml" title="Siste tråder i forumene" />
<link rel="alternate" href="'.ess::$s['relative_path'].'/rss/forum_replies" type="application/rss+xml" title="Siste svar i forumene" />
<link rel="alternate" href="http://kofradia.wordpress.com/feed/" type="application/rss+xml" title="Nyheter fra bloggen" />
<link href="'.ess::$s['path'].'/themes/sm/default.css?'.@filemtime(dirname(__FILE__)."/default.css").'" rel="stylesheet" type="text/css" />';

if (!ess::$b->page->js_disable)
{
	$head .= '
<script type="text/javascript">var js_start = (new Date).getTime();</script>';
	
	// html5 fiks for IE
	$head .= '
<!--[if lte IE 8]>
<script src="'.ess::$s['rpath'].'/js/html5ie.js" type="text/javascript"></script>
<![endif]-->';
	
	// mootools
	if (MAIN_SERVER)
	{
		$head .= '
<script src="'.LIB_HTTP.'/mootools/mootools-1.2.x-yc.js" type="text/javascript"></script>';
	}
	else
	{
		$head .= '
<script src="'.LIB_HTTP.'/mootools/mootools-1.2.x-core-nc.js" type="text/javascript"></script>
<script src="'.LIB_HTTP.'/mootools/mootools-1.2.x-more-nc.js" type="text/javascript"></script>';
	}
	
	$head .= '
<script type="text/javascript">var js_mootools_loaded = (new Date).getTime();</script>
<script src="'.ess::$s['relative_path'].'/js/default.js?update='.@filemtime(dirname(dirname(dirname("js/default.js")))).'" type="text/javascript"></script>';
	
	ess::$b->page->add_js('var serverTime='.(round(microtime(true)+ess::$b->date->timezone->getOffset(ess::$b->date->get()), 3)*1000).',relative_path='.js_encode(ess::$s['relative_path']).',static_link='.js_encode(STATIC_LINK).',imgs_http='.js_encode(IMGS_HTTP).',pcookie='.js_encode(ess::$s['cookie_prefix']).';');
	if (login::$logged_in) ess::$b->page->add_js('var pm_new='.login::$user->data['u_inbox_new'].',log_new='.(login::$user->player->data['up_log_new']+login::$user->player->data['up_log_ff_new']).',http_path='.js_encode(ess::$s['http_path']).',https_path='.js_encode(ess::$s['https_path'] ? ess::$s['https_path'] : ess::$s['http_path']).',use_https='.(HTTPS && login::$logged_in && login::$info['ses_secure'] ? "true" : "false").';');
	if (defined("LOCK") && LOCK) ess::$b->page->add_js('var theme_lock=true;');
}

// legg til øverst i head
ess::$b->page->head = $head . ess::$b->page->head;

// sett opp nettleser "layout engine" til CSS
$list = array(
	"opera" => "presto",
	"applewebkit" => "webkit",
	"msie 8" => "trident6 trident",
	"msie 7" => "trident5 trident",
	"msie 6" => "trident4 trident",
	"gecko" => "gecko"
);
$class_browser = 'unknown_engine';
$browser = mb_strtolower($_SERVER['HTTP_USER_AGENT']);
foreach ($list as $key => $item)
{
	if (mb_strpos($browser, $key) !== false)
	{
		$class_browser = $item;
		break;
	}
}

if (!isset($_SERVER['HTTP_USER_AGENT']) || mb_strpos($_SERVER['HTTP_USER_AGENT'], "MSIE 6") === false)
{
	#header("Content-Type: application/xhtml+xml; charset=utf-8");
	#header("Content-Type: text/html; charset=utf-8");
	/*echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";*/
}
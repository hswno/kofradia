<?php

/*
 * Bibliotek for Open Flash Charts
 * Tilpasset for Versjon 2 Kvasir (utgitt 16. juni 2009)
 * 
 * Oppsettet her er laget utifra analyse av kildekoden til OFC.
 * @author Henrik Steen
 */

class OFC_Colours
{
	public static $colours = array(
		"#e31a1c",
		"#377db8",
		"#4daf4a",
		"#ffff33",
		"#984ea3",
		"#ff7f00",
		"#a65628",
		"#f781bf",
		"#bc80bd",
		"#ccebc5",
		"#ffed6f"
	);
	protected $colours_int = array();
	public function __construct()
	{
		$this->colours_int = self::$colours;
	}
	public function pick()
	{
		return array_shift($this->colours_int);
	}
	public function pick_random()
	{
		$rand = array_rand($this->colours_int);
		$colour = $this->colours_int[$rand];
		unset($this->colours_int);
		return $colour;
	}
	public static function random()
	{
		return self::$colours[array_rand(self::$colours)];
	}
}

class OFC
{
	public $elements = array();
	/**
	 * @return OFC
	 */
	public function add_element(OFC_Charts $e) { $this->elements[] = $e; return $this; }
	/**
	 * @return OFC
	 */
	public function bg_colour($val = "#F8F8D8") { $this->bg_colour = $val; return $this; }
	/**
	 * @return OFC
	 */
	public function bg_image($url, $x = NULL, $y = NULL)
	{
		$this->bg_image = $url;
		if ($x) $this->bg_image_x = $x;
		if ($x) $this->bg_image_y = $y;
		return $this;
	}
	/**
	 * @return OFC_Tooltip
	 */
	public function tooltip() {
		if (!isset($this->tooltip)) $this->tooltip = new OFC_Tooltip();
		return $this->tooltip;
	}
	/**
	 * @return OFC
	 */
	public function title(OFC_Title $val) { $this->title = $val; return $this; }
	/**
	 * @return OFC
	 */
	public function legend_x(OFC_Legend $val) { $this->x_legend = $val; return $this; }
	/**
	 * @return OFC
	 */
	public function legend_y(OFC_Legend $val) { $this->y_legend = $val; return $this; }
	/**
	 * @return OFC
	 */
	public function legend_y_right(OFC_Legend $val) { $this->y2_legend = $val; return $this; }
	/**
	 * @return OFC_Axis_X
	 */
	public function axis_x() { if (!isset($this->x_axis)) $this->x_axis = new OFC_Axis_X(); return $this->x_axis; }
	/**
	 * @return OFC_Axis_Y
	 */
	public function axis_y() { if (!isset($this->y_axis)) $this->y_axis = new OFC_Axis_Y(); return $this->y_axis; }
	/**
	 * @return OFC_Axis_Y
	 */
	public function axis_y_right() { if (!isset($this->y2_axis)) $this->y2_axis = new OFC_Axis_Y(); return $this->y2_axis; }
	/**
	 * @return string
	 */
	public function __toString() { return js_encode($this); }
	public function dump() { /*header("Content-Type: text/plain; charset=utf8");*/ echo js_encode($this); die; }
	
	// quick functions
	/**
	 * Hide x-axis and text
	 * @return OFC
	 */
	public function hide_x_axis()
	{
		// x-axis
		$this->axis_x()->grid_visible(false)->label()->visible(false);
		if (isset($this->bg_colour)) $this->axis_x()->colour($this->bg_colour);
		return $this;
	}
	/**
	 * Hide y-axis and text
	 * @return OFC
	 */
	public function hide_y_axis()
	{
		// y-axis
		$this->axis_y()->visible(false)->labels()->visible(false);
		return $this;
	}
	/**
	 * Customize to dark colors
	 * @return OFC
	 */
	public function dark_colors()
	{
		$this->bg_colour("#333333");
		
		// x-axis
		$this->axis_x()->colour("#AAAAAA")->grid_colour("#555555")->label()->colour("#CCCCCC");
		
		// y-axis
		$this->axis_y()->colour("#AAAAAA")->grid_colour("#555555")->label()->colour("#CCCCCC");
		
		/// tooltip
		$this->tooltip()->colour("#AAAAAA")->background("#EEEEEE");
		if (!isset($this->tooltip->title) || strpos($this->tooltip->title, "font-size") === false) $this->tooltip->title .= "; font-size: 12px";
		if (strpos($this->tooltip->title, "font-weight") === false) $this->tooltip->title .= "; font-weight: bold";
		if (!isset($this->tooltip->text) || strpos($this->tooltip->text, "font-size") === false) $this->tooltip->text .= "; font-size: 12px";
		
		// legends
		if (isset($this->x_legend) && (!isset($this->x_legend->style) || strpos($this->x_legend->style, "color") === false)) $this->x_legend->style .= "; color: #EEEEEE";
		if (isset($this->y_legend) && (!isset($this->y_legend->style) || strpos($this->y_legend->style, "color") === false)) $this->y_legend->style .= "; color: #EEEEEE";
		if (isset($this->y2_legend) && (!isset($this->y2_legend->style) || strpos($this->y2_legend->style, "color") === false)) $this->y2_legend->style .= "; color: #EEEEEE";
		
		// title
		if (isset($this->title) && (!isset($this->title->style) || strpos($this->title->style, "color") === false)) $this->title->style .= "; color: #EEEEEE";
		if (isset($this->title) && (!isset($this->title->style) || strpos($this->title->style, "font-size") === false)) $this->title->style .= "; font-size: 12px";
		
		return $this;
	}
	
	public static function embed_string($name, $data_file, $width = 300, $height = 200)
	{
		return 'swfobject.embedSWF("'.LIB_HTTP.'/ofc/open-flash-chart.swf", '.js_encode($name).', '.js_encode($width).', '.js_encode($height).', "9.0.0", false, {"data-file": "'.urlencode($data_file).'"});';
	}
	public static function embed($name, $data_file, $width = 300, $height = 200)
	{
		global $_base;
		$_base->page->add_js_file(LIB_HTTP.'/swfobject/swfobject.js');
		$_base->page->add_js_domready(self::embed_string($name, $data_file, $width, $height));
	}
}

class OFC_Tooltip
{
	const CLOSEST = 0;
	const PROXIMITY = 1;
	const NORMAL = 2;
	/**
	 * @return OFC_Tooltip
	 */
	public function shadow($val = true) { if ($val) $this->shadow = true; else unset($this->shadow); return $this; }
	/**
	 * @return OFC_Tooltip
	 */
	public function rounded($val = 6) { $this->rounded = $val; return $this; }
	/**
	 * @return OFC_Tooltip
	 */
	public function stroke($val = 2) { $this->stroke = $val; return $this; }
	/**
	 * @return OFC_Tooltip
	 */
	public function colour($val = "#808080") { $this->colour = $val; return $this; }
	/**
	 * @return OFC_Tooltip
	 */
	public function background($val = "#F0F0F0") { $this->background = $val; return $this; }
	/**
	 * @return OFC_Tooltip
	 */
	public function title($val = "color: #0000F0; font-weight:bold; font-size: 12;") {$this->title = $val; return $this; }
	/**
	 * @return OFC_Tooltip
	 */
	public function body($val = "color: #000000; font-weight: normal; font-size: 12;") { $this->body = $val; return $this; }
	/**
	 * @return OFC_Tooltip
	 */
	public function mouse($val = self::CLOSEST) { $this->mouse = $val; return $this; }
}

class OFC_Title
{
	/**
	 * @return OFC_Title
	 */
	public function __construct($text = "", $style = NULL) { $this->text = $text; if ($style) $this->style($style); return $this; }
	/**
	 * @return OFC_Title
	 */
	public function style($val) { $this->style = $val; return $this; }
}

class OFC_Legend
{
	public $style = "";
	/**
	 * @return OFC_Legend
	 */
	public function __construct($text = "") { $this->text = $text; return $this; }
	/**
	 * @return OFC_Legend
	 */
	public function style($val) { $this->style = $val; return $this; }
}

abstract class OFC_Axis
{
	/**
	 * @return OFC_Axis
	 */
	public function stroke($val = 2) { $this->stroke = $val; return $this; }
	/**
	 * @return OFC_Axis
	 */
	public function tick_height($val = 3) { $this->{'tick-height'} = $val; return $this; }
	/**
	 * @return OFC_Axis
	 */
	public function colour($val = "#784016") { $this->colour = $val; return $this; }
	/**
	 * @param bool
	 * @return OFC_Axis
	 */
	public function offset($val) { $this->offset = $val; return $this; }
	/**
	 * @return OFC_Axis
	 */
	public function grid_colour($val = "#F5E1AA") { $this->{'grid-colour'} = $val; return $this; }
	/**
	 * @param bool
	 * @return OFC_Axis
	 */
	public function grid_visible($val) { $this->{'grid-visible'} = $val; return $this; }
	/**
	 * @return OFC_Axis
	 */
	public function three_d($val = 0) { $this->{'3d'} = $val; return $this; }
	/**
	 * @return OFC_Axis
	 */
	public function steps($val = 1) { $this->steps = $val; return $this; }
	/**
	 * @return OFC_Axis
	 */
	public function min($val = 0) { $this->min = $val; return $this; }
	/**
	 * @return OFC_Axis
	 */
	public function max($val = NULL) { $this->max = $val; return $this; }
	/**
	 * Calculates best max and step value
	 * @return OFC_Axis
	 */
	public function set_numbers($min, $max, $steps = 10)
	{
		#if ($min != 0 || isset($this->min)) $this->min = $min;
		if ($max <= 100 && $min >= -100) $l = 10;
		else $l = (int) '10'.str_repeat("0", strlen(ceil($max)-floor($min))-3);
		$this->min = floor(min(0, $min)/$l)*$l;
		$this->max = ceil(max(1, $max)/$l)*$l;
		$this->steps = ($this->max - $this->min) / $steps;
	}
}
class OFC_Axis_X extends OFC_Axis
{
	/**
	 * @return OFC_Axis_X_Label
	 */
	public function label()
	{
		if (!isset($this->labels) || !is_object($this->labels)) $this->labels = new OFC_Axis_X_Label();
		return $this->labels;
	}
}
class OFC_Axis_X_Label
{
	/**
	 * @return OFC_Axis_X_Label
	 */
	public function rotate($val = 0) { $this->rotate = $val; return $this; }
	/**
	 * @return OFC_Axis_X_Label
	 */
	public function visible($val = NULL) { $this->visible = $val; return $this; }
	/**
	 * @return OFC_Axis_X_Label
	 */
	public function labels(array $val) {
		// make sure it is strings
		$this->labels = array_map("strval", $val);
		return $this;
	}
	/**
	 * @return OFC_Axis_X_Label
	 */
	public function text($val = "#val#") { $this->text = $val; return $this; }
	/**
	 * @return OFC_Axis_X_Label
	 */
	public function steps($val = NULL) { $this->steps = $val; return $this; }
	/**
	 * @return OFC_Axis_X_Label
	 */
	public function size($val = 10) { $this->size = $val; return $this; }
	/**
	 * @return OFC_Axis_X_Label
	 */
	public function align($val = "auto") { $this->align = $val; return $this; }
	/**
	 * @return OFC_Axis_X_Label
	 */
	public function colour($val = "#000000") { $this->colour = $val; return $this; }
	/**
	 * @return OFC_Axis_X_Label
	 */
	public function visible_steps($val = NULL) { $this->{'visible_steps'} = $val; return $this; }
}
class OFC_Axis_Y extends OFC_Axis
{
	/**
	 * @return OFC_Axis_Y
	 */
	public function rotate($val = 0) { $this->rotate = $val; return $this; }
	/**
	 * @return OFC_Axis_Y
	 */
	public function visible($val) { $this->visible = $val; return $this; }
	/**
	 * @return OFC_Axis_Y_Label
	 */
	public function label()
	{
		if (!isset($this->labels)) $this->labels = new OFC_Axis_Y_Label();
		elseif (is_array($this->labels))
		{
			$obj = new OFC_Axis_Y_Label();
			$obj->labels = $this->labels;
			$this->labels = $obj;
		}
		return $this->labels;
	}
	/**
	 * @return OFC_Axis_Y
	 */
	public function labels(array $val) {
		if (isset($this->labels) && !is_array($this->labels)) $this->labels->labels = $val;
		else $this->labels = $val;
		return $this;
	}
}
class OFC_Axis_Y_Label
{
	/**
	 * @return OFC_Axis_Y_Label
	 */
	public function text($val = "#val#") { $this->text = $val; return $this; }
	/**
	 * @return OFC_Axis_Y_Label
	 */
	public function steps($val = NULL) { $this->steps = $val; return $this; }
	/**
	 * @return OFC_Axis_Y_Label
	 */
	public function colour($val = "#000000") { $this->colour = $val; return $this; }
	/**
	 * @return OFC_Axis_Y_Label
	 */
	public function show_labels($val = true) { $this->show_label = $val; return $this; }
	/**
	 * @return OFC_Axis_Y_Label
	 */
	public function visible($val = true) { $this->visible = $val; return $this; }
	/**
	 * @return OFC_Axis_Y_Label
	 */
	public function add_label(OFC_Axis_Y_Label_Item $val) { $this->labels[] = $val; return $this; }
}
class OFC_Axis_Y_Label_Item
{
	/**
	 * @return OFC_Axis_Y_Label_Item
	 */
	public function __construct($y) { $this->y = $y; return $this; }
	/**
	 * @return OFC_Axis_Y_Label_Item
	 */
	public function text($val) { $this->text = $val; return $this; }
	/**
	 * @return OFC_Axis_Y_Label_Item
	 */
	public function colour($val) { $this->colour = $val; return $this; }
	/**
	 * @return OFC_Axis_Y_Label_Item
	 */
	public function size($val) { $this->size = $val; return $this; }
	/**
	 * @return OFC_Axis_Y_Label_Item
	 */
	public function rotate($val) { $this->rotate = $val; return $this; }
}

abstract class OFC_Charts
{
	public $values = array();
	/**
	 * @return OFC_Charts
	 */
	public function __construct($type) { $this->type = $type; return $this; }
	/**
	 * @return OFC_Charts
	 */
	public function add_value($val) { $this->values[] = $val; return $this; }
	/**
	 * @return OFC_Charts
	 */
	public function values(array $val) { $this->values = $val; return $this; }
	/**
	 * @return OFC_Charts
	 */
	public function colour($val = "#3030D0") { $this->colour = $val; return $this; }
	/**
	 * @return OFC_Charts
	 */
	public function text($val = "") { $this->text = $val; return $this; }
	/**
	 * @return OFC_Charts
	 */
	public function font_size($val = 12) { $this->{'font-size'} = $val; return $this; }
	/**
	 * @return OFC_Charts
	 */
	public function tip($val = "#val#") { $this->tip = $val; return $this; }
	/**
	 * @return OFC_Charts
	 */
	public function loop($val = false) { $this->loop = $val; return $this; }
	/**
	 * @return OFC_Charts
	 */
	public function axis($val = "left") { $this->axis = $val; return $this; }
	/**
	 * @return OFC_Line_Style
	 */
	public function line_style() { if (!isset($this->{'line-style'})) $this->{'line-style'} = new OFC_Line_Style(); return $this->{'line-style'}; }
	/**
	 * @return OFC_Dot_Style
	 */
	public function dot_style() { if (!isset($this->{'dot-style'})) $this->{'dot-style'} = new OFC_Dot_Style(); return $this->{'dot-style'}; }
	/**
	 * @return OFC_Charts
	 */
	public function on_show(OFC_On_Show $val) { $this->{'on-show'} = $val; return $this; }
	// TODO: animate
}
class OFC_Line_Style
{
	/**
	 * @return OFC_Line_Style
	 */
	public function style($val = "solid") { $this->style = $val; return $this; }
	/**
	 * @return OFC_Line_Style
	 */
	public function on($val = 1) { $this->on = $val; return $this; }
	/**
	 * @return OFC_Line_Style
	 */
	public function off($val = 5) { $this->off = $val; return $this; }
}
class OFC_Dot_Style
{
	// TODO: andre typer
	/**
	 * @return OFC_Dot_Style
	 */
	public function type($val = "dot") { $this->type = $val; return $this; }
	/**
	 * @return OFC_Dot_Style
	 */
	public function dot_size($val = 5) { $this->{'dot-size'} = $val; return $this; }
	/**
	 * @return OFC_Dot_Style
	 */
	public function halo_size($val = 2) { $this->{'halo-size'} = $val; return $this; }
	/**
	 * @return OFC_Dot_Style
	 */
	public function colour($val = "#3030D0") { $this->colour = $val; return $this; }
	/**
	 * @return OFC_Dot_Style
	 */
	public function tip($val = "#val#") { $this->tip = $val; return $this; }
	/**
	 * @return OFC_Dot_Style
	 */
	public function alpha($val = 1) { $this->alpha = $val; return $this; }
	/**
	 * For anchors
	 * @return OFC_Dot_Style
	 */
	public function rotation($val = 0) { $this->rotation = $val; return $this; }
	/**
	 * For anchors
	 * @return OFC_Dot_Style
	 */
	public function sides($val = 3) { $this->sides = $val; return $this; }
	/**
	 * For hollow
	 * @return OFC_Dot_Style
	 */
	public function width($val = 1) { $this->width = $val; return $this; }
}
class OFC_On_Show
{
	/**
	 * @param none, pop-up, explode, mid-slide, slide-in-up, drop, fade-in, shrink-in
	 * @return OFC_On_Show
	 */
	public function type($val = "none") { $this->type = $val; return $this; }
	/**
	 * @return OFC_On_Show
	 */
	public function cascade($val = 0.5) { $this->cascade = $val; return $this; }
	/**
	 * @return OFC_On_Show
	 */
	public function delay($val = 0) { $this->delay = $val; return $this; }
}

class OFC_Charts_Area extends OFC_Charts
{
	/**
	 * @return OFC_Charts_Area
	 */
	public function __construct() { parent::__construct("area"); $this->fill_alpha(0.3); return $this; }
	/**
	 * @return OFC_Charts_Area
	 */
	public function fill($val = "#3030D0") { $this->fill = $val; return $this; }
	/**
	 * @return OFC_Charts_Area
	 */
	public function fill_alpha($val = 0.5) { $this->{'fill-alpha'} = $val; return $this; }
	/**
	 * @return OFC_Charts_Area
	 */
	public function width($val = 2) { $this->width = $val; return $this; }
}

abstract class OFC_Charts_BarBase extends OFC_Charts
{
	/**
	 * @return OFC_Charts_BarBase
	 */
	public function tip($val = "#val#<br>#x_label#") { $this->tip = $val; return $this; }
	/**
	 * @return OFC_Charts_BarBase
	 */
	public function alpha($val = 0.6) { $this->alpha = $val; return $this; }
	/**
	 * @return OFC_Charts_BarBase
	 */
	public function on_click($val = false) { $this->{'on-click'} = $val; return $this; }
}
class OFC_Charts_Bar extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar"); } }
class OFC_Charts_Bar_3D extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_3d"); } }
class OFC_Charts_Bar_Cylinder extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_cylinder"); } }
class OFC_Charts_Bar_Outline extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_outline"); } }
class OFC_Charts_Bar_Dome extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_dome"); } }
class OFC_Charts_Bar_Fade extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_fade"); } }
class OFC_Charts_Bar_Filled extends OFC_Charts_BarBase
{
	/**
	 * @return OFC_Charts_Bar_Filled
	 */
	public function __construct() { parent::__construct("bar_filled"); return $this; }
	/**
	 * @return OFC_Charts_Bar_Filled
	 */
	public function outline_colour($val = "#000000") { $this->{'outline-colour'} = $val; return $this; }
}
class OFC_Charts_Bar_Glass extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_glass"); } }
class OFC_Charts_Bar_Plastic extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_plastic"); } }
class OFC_Charts_Bar_Plastic_Flat extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_plastic_flat"); } }
class OFC_Charts_Bar_Round extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_round"); } }
class OFC_Charts_Bar_Round_3D extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_round3d"); } }
class OFC_Charts_Bar_Round_Glass extends OFC_Charts_BarBase { public function __construct() { parent::__construct("bar_round_glass"); } }
class OFC_Charts_Bar_Sketch extends OFC_Charts_BarBase
{
	/**
	 * @return OFC_Charts_Bar_Sketch
	 */
	public function __construct() { parent::__construct("bar_sketch"); return $this; }
	/**
	 * @return OFC_Charts_Bar_Sketch
	 */
	public function outline_colour($val = "#000000") { $this->{'outline-colour'} = $val; return $this; }
	/**
	 * @return OFC_Charts_Bar_Sketch
	 */
	public function offset($val = 6) { $this->offset = $val; return $this; }
}
class OFC_Charts_Bar_Stack extends OFC_Charts_BarBase
{
	/**
	 * @return OFC_Charts_Bar_Stack
	 */
	public function __construct() { parent::__construct("bar_stack"); return $this; }
	/**
	 * @return OFC_Charts_Bar_Stack
	 */
	public function add_key($text, $font_size, $colour)
	{
		$this->keys[] = array("text" => $text, "font-size" => $font_size, "colour" => $colour);
		return $this;
	}
	/**
	 * Default: [#FF0000, #00FF00]
	 * @return OFC_Charts_Bar_Stack
	 */
	public function colours(array $val) { $this->colours = $val; return $this; }
	/**
	 * @return OFC_Charts_Bar_Stack
	 */
	public function text($val = "#x_label# : #val#<br>Total: #total#") { $this->text = $val; return $this; }
}

class OFC_Charts_Candle extends OFC_Charts { public function __construct() { parent::__construct("candle"); } }

class OFC_Charts_HBar extends OFC_Charts {
	/**
	 * @return OFC_Charts_HBar
	 */
	public function __construct() { parent::__construct("hbar"); return $this; }
	/**
	 * @return OFC_Charts_HBar
	 */
	public function add_value($left, $right) { $this->values[] = array("left" => $left, "right" => $right); return $this; }
	/**
	 * @return OFC_Charts_HBar
	 */
	public function on_click($val = false) { $this->{'on-click'} = $val; return $this; }
}

class OFC_Charts_Line extends OFC_Charts {
	/**
	 * @return OFC_Charts_Line
	 */
	public function __construct() { parent::__construct("line"); return $this; }
	/**
	 * @return OFC_Charts_Line
	 */
	public function width($val = 2) { $this->width = $val; return $this; }
}

class OFC_Charts_Pie extends OFC_Charts {
	/**
	 * @return OFC_Charts_Pie
	 */
	public function __construct($start_angle = 0) { parent::__construct("pie"); $this->{'start-angle'} = $start_angle; return $this; }
	/**
	 * Default: [#90000000, #0090000]
	 * @return OFC_Charts_Pie
	 */
	public function colours(array $val) { $this->colours = $val; return $this; }
	/**
	 * @return OFC_Charts_Pie
	 */
	public function add_value($value, $label = NULL)
	{
		$arr = array("value" => $value);
		if ($label) $arr['label'] = $label;
		$this->values[] = $arr;
		return $this;
	}
}

class OFC_Charts_Scatter extends OFC_Charts {
	/**
	 * @return OFC_Charts_Scatter
	 */
	public function __construct() { parent::__construct("scatter"); return $this; }
	/**
	 * @return OFC_Charts_Scatter
	 */
	public function add_value(OFC_Charts_Scatter_Value $val) { $this->values[] = $val; return $this; }
	/**
	 * @return OFC_Charts_Scatter
	 */
	public function on_show() { return $this; }
	/**
	 * @return OFC_Charts_Scatter
	 */
	public function tip($val = "[#x#,#y#] #size#") { $this->tip = $val; return $this; }
	/**
	 * @return OFC_Charts_Scatter
	 */
	public function width($val = 2) { $this->width = $val; return $this; }
}
class OFC_Charts_Scatter_Line extends OFC_Charts {
	/**
	 * @return OFC_Charts_Scatter_Line
	 */
	public function __construct() { parent::__construct("scatter_line"); return $this; }
	/**
	 * @return OFC_Charts_Scatter_Line
	 */
	public function add_value(OFC_Charts_Scatter_Value $val) { $this->values[] = $val; return $this; }
	/**
	 * @return OFC_Charts_Scatter_Line
	 */
	public function on_show() { return $this; }
	/**
	 * @param 1 for horizontal, 2 for vertical 
	 * @return OFC_Charts_Scatter_Line
	 */
	public function stepgraph($val = 0) { $this->stepgraph = $val; return $this; }
	/**
	 * @return OFC_Charts_Scatter_Line
	 */
	public function area_style($x, $y, $colour, $alpha = 1)
	{
		$this->{'area-style'} = array(
			"x" => $x,
			"y" => $y,
			"colour" => $colour,
			"alpha" => $alpha
		);
		return $this;
	}
	/**
	 * @return OFC_Charts_Scatter_Line
	 */
	public function width($val = 2) { $this->width = $val; return $this; }
}
class OFC_Charts_Scatter_Value extends OFC_Dot_Style
{
	/**
	 * @return OFC_Charts_Scatter_Value
	 */
	public function __construct($x, $y) { $this->x = $x; $this->y = $y; return $this; }
}

class OFC_Charts_Shape extends OFC_Charts {
	/**
	 * @return OFC_Charts_Shape
	 */
	public function __construct() { parent::__construct("shappe"); return $this; }
	/**
	 * @return OFC_Charts_Shape
	 */
	public function add_value($x, $y) { $this->values[] = array("x" => $x, "y" => $y); return $this; }
	/**
	 * @return OFC_Charts_Shape
	 */
	public function points(array $val) { $this->points = $val; return $this; }
	/**
	 * @return OFC_Charts_Shape
	 */
	public function colour($val = "#808080") { $this->colour = $val; return $this; }
	/**
	 * @return OFC_Charts_Shape
	 */
	public function alpha($val = 0.5) { $this->alpha = $val; return $this; }
	/**
	 * @return OFC_Charts_Shape
	 */
	public function width($val = 2) { $this->width = $val; return $this; }
}
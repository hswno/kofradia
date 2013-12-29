<?php

// sett opp riktig adresse til kartfilen
bydeler::$map_dir = BYDELER_MAP_FOLDER;
bydeler::$map_file = bydeler::$map_dir."/map.png";

class bydeler
{
	/** Adresse til kartfilen (det store kartet) */
	public static $map_file = NULL;
	
	/** Mappen for alle bildene skal lagres */
	public static $map_dir = NULL;
	
	/**
	 * De ulike stedene man kan oppdage folk
	 */
	public static $places = array(
		// Åssiden
		1 => array(
			"travbanen",
			"vårveien",
			"Smak gatekjøkken"),
		
		// Bragernes
		4 => array(
			"McDonalds",
			"Snappys",
			"parken"),
		
		// Strøtvet
		5 => array(
			"Kiwi",
			"Strøtvet gård",
			"kjappen"),
		
		// Strømsø
		6 => array(
			"Globusgården",
			"jernbanestasjonen",
			"Shell"),
		
		// Holmen
		7 => array(
			"siloen",
			"Holmennokken"),
		
		// Gulskogen
		10 => array(
			"Gulskogen gård",
			"Gulskogen senter"),
		
		// Brakerøya
		12 => array(
			"CC",
			"fjordparken"),
		
		// Grønland
		13 => array(
			"politistasjonen",
			"Union Scene",
			"Ypsilon")
	);
	
	/**
	 * Finn en tilfeldig plass
	 */
	public static function get_random_place($bydel_id)
	{
		if (!isset(self::$places[$bydel_id]) || count(self::$places[$bydel_id]) == 0)
		{
			return false;
		}
		
		return self::$places[$bydel_id][array_rand(self::$places[$bydel_id])];
	}
	
	/**
	 * Generer kartfil for alle bydelene
	 */
	public static function generate_map_bydeler()
	{
		foreach (game::$bydeler as $bydel)
		{
			if (!$bydel['active']) continue;
			
			$map = new bydeler_map();
			$map->bydel($bydel['id']);
			
			$data = $map->generate();
			$map->destroy();
			file_put_contents(self::$map_dir."/bydel_".$bydel['id'].".png", $data);
		}
		
		// forminsk det store kartet
		$map = new bydeler_map();
		$map->scale();
		$data = $map->generate();
		$map->destroy();
		file_put_contents(self::$map_dir."/bydeler.png", $data);
	}
	
	/**
	 * Generer kart for bestemt bydel
	 * @param integer $b_id ID til bydel
	 * @return binary png
	 */
	public static function generate_map_bydel($b_id)
	{
		// finn bydel
		if (!isset(game::$bydeler[$b_id]))
		{
			throw new HSException("Fant ikke bydelen.");
		}
		$bydel = game::$bydeler[$b_id];
		
		// lag gd objekt og crop til riktig perspektiv
		$img = imagecreatetruecolor($bydel['b_size_x'], $bydel['b_size_y']);
		$map = imagecreatefromstring(file_get_contents("map.png"));
		imagecopy($img, $map, 0, 0,
			$bydel['b_coords_x'], $bydel['b_coords_y'],
			$bydel['b_size_x'], $bydel['b_size_y']);
		imagedestroy($map);
		
		// print ut
		$pre = @ob_get_contents();
		@ob_clean();
		imagepng($img);
		$data = ob_get_contents();
		ob_clean();
		echo $pre;
		
		return $data;
	}
	
	/**
	 * Tegn på ressurs på kart
	 */
	#public static function 
}


/**
 * Grafisk kart (fysisk) for et område
 */
class bydeler_map
{
	/** Kartobjekt */
	protected $img = null;
	
	/** Kartkoordinater på originalt kart (x0, y0, x1, y1) */
	protected $coords = array(0, 0, 0, 0);
	
	/** Skalering */
	protected $scale = 1;
	
	/** Kartressurser */
	protected $resources = array();
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		// hent inn hele kartet
		$this->create_map();
	}
	
	/**
	 * Hent inn kartet
	 */
	protected function create_map()
	{
		// opprett kartobjekt
		$this->img = @imagecreatefromstring(file_get_contents(bydeler::$map_file));
		
		// fikk ikke åpnet kartfil?
		if (!$this->img)
		{
			error_log("Kunne ikke generere kart fra fil: ".bydeler::$map_file);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Forminsk kartet x antall ganger
	 * @param int $x antall ganger
	 */
	public function scale($x = 3)
	{
		$img = imagecreatetruecolor(round(imagesx($this->img)/$x), round(imagesy($this->img)/$x));
		imagecopyresampled($img, $this->img, 0, 0, 0, 0, imagesx($img), imagesy($img), imagesx($this->img), imagesy($this->img));
		imagedestroy($this->img);
		$this->scale = $x;
		$this->img = $img;
	}
	
	/**
	 * Begrens kartet til en bestemt bydel
	 */
	public function bydel($b_id)
	{
		// har ikke kartfil?
		if (!$this->img) return false;
		
		// finn bydel
		if (!isset(game::$bydeler[$b_id]) || !game::$bydeler[$b_id]['active'])
		{
			throw new HSException("Fant ikke bydelen.");
		}
		$bydel = game::$bydeler[$b_id];
		
		// begrens kartet til denne bydelen
		$img = imagecreatetruecolor($bydel['b_size_x'], $bydel['b_size_y']);
		imagecopy($img, $this->img, 0, 0,
			$bydel['b_coords_x'], $bydel['b_coords_y'],
			$bydel['b_size_x'], $bydel['b_size_y']);
		imagedestroy($this->img);
		$this->img = $img;
		
		// lagre kartkoordinater
		$this->coords = array($bydel['b_coords_x'], $bydel['b_coords_y'], $bydel['b_coords_x']+$bydel['b_size_x'], $bydel['b_coords_y']+$bydel['b_size_y']);
		
		return true;
	}
	
	/**
	 * Hent ressursene (for en bestemt bydel)
	 */
	public function load_resources($b_id = 0)
	{
		global $_base;
		
		$where = $b_id == 0 ? '' : ' WHERE br_b_id = '.intval($b_id);
		$result = \Kofradia\DB::get()->query("SELECT br_id, br_b_id, br_type, br_pos_x, br_pos_y FROM bydeler_resources$where");
		
		while ($row = $result->fetch())
		{
			$this->resources[$row['br_id']] = $row;
		}
	}
	
	/**
	 * Tegn ressurser for en bestemt bydel
	 */
	public function draw_resources($b_id)
	{
		// har ikke kart?
		if (!$this->img) return false;
		
		// hent ressursene
		$this->load_resources($b_id);
		
		foreach ($this->resources as $resource)
		{
			if ($resource['br_b_id'] == $b_id)
			{
				$this->draw_resource($resource['br_id']);
			}
		}
		
		return true;
	}
	
	/**
	 * Tegn ressurs
	 */
	public function draw_resource($br_id)
	{
		global $_base;
		
		// mellomlagret?
		if (isset($this->resources[$br_id]))
		{
			$resource = $this->resources[$br_id];
		}
		
		else
		{
			// hent ressursen
			$br_id = (int) $br_id;
			$result = \Kofradia\DB::get()->query("SELECT br_b_id, br_type, br_pos_x, br_pos_y FROM bydeler_resources WHERE br_id = $br_id");
			if (!($resource = $result->fetch()))
			{
				return false;
			}
			
			$this->resources[$br_id] = $resource;
		}
		
		// tegn
		$x = $resource['br_pos_x'] - $this->coords[0];
		$y = $resource['br_pos_y'] - $this->coords[1];
		
		$punkt = imagecreatefromstring(file_get_contents(bydeler::$map_dir."/familiepunkt_transparent.png"));
		imagecopy($this->img, $punkt, $x-imagesx($punkt)/2, $y-imagesy($punkt)/2, 0, 0, imagesx($punkt), imagesy($punkt));
		imagedestroy($punkt);
		
		return true;
	}
	
	/**
	 * Tegn en miniversjon av kart for å illustrere hvor en ressurs ligger
	 */
	public function mini_map($br_id, $radius = 150, $scale = 3, $show_all = false)
	{
		global $_base;
		
		// har ikke kart?
		if (!$this->img) return false;
		
		// hent informasjon om ressursen
		$br_id = (int) $br_id;
		$result = \Kofradia\DB::get()->query("SELECT br_id, br_b_id, br_type, br_pos_x, br_pos_y FROM bydeler_resources WHERE br_id = $br_id");
		$br = $result->fetch();
		
		if (!$br)
		{
			throw new HSException("Ressursen finnes ikke.");
		}
		
		// finn ut hvor vi skal avgrense på kartet
		$map_size = array(imagesx($this->img), imagesy($this->img));
		#$map_size = array(1664, 1536);
		
		$x1 = max(0, $br['br_pos_x']-$radius);
		$x2 = min($map_size[0], max($radius*2, $br['br_pos_x']+$radius));
		$x1 = min(max(0, $map_size[0]-$radius*2), $x1);
		
		$y1 = max(0, $br['br_pos_y']-$radius);
		$y2 = min($map_size[1], max($radius*2, $br['br_pos_y']+$radius));
		$y1 = min(max(0, $map_size[1]-$radius*2), $y1);
		
		$w = $x2-$x1;
		$h = $y2-$y1;
		
		// tegn på den aktive bydelen
		$punkt = imagecreatefromstring(file_get_contents(bydeler::$map_dir."/familiepunkt.png"));
		imagecopy($this->img, $punkt, $br['br_pos_x']-imagesx($punkt)/2, $br['br_pos_y']-imagesy($punkt)/2, 0, 0, imagesx($punkt), imagesy($punkt));
		imagedestroy($punkt);
		
		// hent ut ressursene i dette området
		$result = \Kofradia\DB::get()->query("SELECT br_id, br_b_id, br_type, br_pos_x, br_pos_y FROM bydeler_resources LEFT JOIN ff ON ff_br_id = br_id AND ff_inactive = 0 WHERE br_pos_x BETWEEN $x1 AND $x2 AND br_pos_y BETWEEN $y1 AND $y2 AND br_id != {$br['br_id']} AND ff_id IS NOT NULL");
		
		// tegn på ressursene
		$punkt = imagecreatefromstring(file_get_contents(bydeler::$map_dir."/familiepunkt_transparent.png"));
		while ($row = $result->fetch())
		{
			imagecopy($this->img, $punkt, $row['br_pos_x']-imagesx($punkt)/2, $row['br_pos_y']-imagesy($punkt)/2, 0, 0, imagesx($punkt), imagesy($punkt));
		}
		imagedestroy($punkt);
		
		// kopier over kartutsnitt
		$img = imagecreatetruecolor($w/$scale, $h/$scale);
		imagecopyresampled($img, $this->img, 0, 0, $x1, $y1, $w/$scale, $h/$scale, $w, $h);
		imagedestroy($this->img);
		$this->scale = $scale;
		$this->img = $img;
		
		return true;
	}
	
	/**
	 * Send bildet til nettleseren
	 */
	public function push()
	{
		@ob_clean();
		header("Content-Type: image/png");
		
		imagepng($this->img);
		die;
	}
	
	/**
	 * Generer og returner dataen til bildet
	 */
	public function generate()
	{
		// har ikke kart?
		if (!$this->img) return false;
		
		// print ut
		@ob_start();
		$pre = @ob_get_contents();
		@ob_clean();
		imagepng($this->img);
		$data = ob_get_contents();
		ob_clean();
		echo $pre;
		
		return $data;
	}
	
	/**
	 * Forkast kartet
	 */
	public function destroy()
	{
		@imagedestroy($this->img);
	}
}
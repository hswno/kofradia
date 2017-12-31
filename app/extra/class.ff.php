<?php

/**
 * Firma og broderskap
 * 1 => familie
 * 2 => avis
 * 3 => bank
 * 4 => bomberom
 * 5 => vapbes
 * 6 => sykehus
 */
class ff
{
	const TYPE_FAMILIE = 1;
	const TYPE_AVIS = 2;
	const TYPE_BANK = 3;
	const TYPE_BOMBEROM = 4;
	const TYPE_VAPBES = 5;
	const TYPE_SYKEHUS = 6;
	const TYPE_GARASJE = 7;
	
	/**
	 * De forskjellige typene
	 */
	public static $types = array(
		1 => array(
			"type" => "familie",
			"refobj" => "broderskapet",
			"typename" => "broderskap",
			"priority" => array(
				1 => "capofamiglia",
				2 => "sotto capo",
				3 => "capodecina",
				4 => "uomini d'onore",
				5 => "consigliere"
			),
			"priority_short" => array(
				1 => "capofamiglia",
				2 => "sotto capo",
				3 => "capodecina",
				4 => "uomini d'onore",
				5 => "consigliere"
			),
			"priority_rank" => array(
				0 => 4, // bølle
				1 => 7, // forretningsmann - tidligere 11 gudfar
				2 => 7, // forretningsmann - tidligere 10 italian stallion
				3 => 6, // forhandler - tidligere 7 forretningsmann
				4 => 5 // pusher
			),
			"bydeler_graphic" => "/imgs/bydeler/familiepunkt.png",
			"bydeler_alt_pre" => "Broderskap: ",
			"parent" => true, // har denne typen parent mellom pri3 og pri4?
			"pri2_takeover" => true // tar medeier over for eier?
		),
		2 => array(
			"type" => "avis",
			"refobj" => "avisen",
			"typename" => "avisfirma",
			"priority" => array(
				1 => "ansvarlig redaktør",
				2 => "redaktør",
				3 => "journalist",
				4 => "VIP"
			),
			"priority_short" => array(
				1 => "ansv. redaktør",
				2 => "redaktør",
				3 => "journalist",
				4 => "VIP"
			),
			"priority_rank" => array(
				0 => 5, // pusher (0 = alle som ikke har egen spesifisert)
				1 => 7, // forretningsmann
				2 => 6 // forhandler
			),
			"bydeler_graphic" => "/imgs/bydeler/firma.png",
			"bydeler_alt_pre" => "Firma: ",
			"parent" => false, // har denne typen parent mellom pri3 og pri4?
			"pri2_takeover" => true // tar medeier over for eier?
		),
		3 => array(
			"type" => "bank",
			"refobj" => "banken",
			"typename" => "bankfirma",
			"priority" => array(
				1 => "direktør",
				2 => "assisterende direktør",
				3 => "funksjonær",
				4 => "VIP"
			),
			"priority_short" => array(
				1 => "direktør",
				2 => "asst. direktør",
				3 => "funksjonær",
				4 => "VIP"
			),
			"priority_rank" => array(
				0 => 5, // pusher (0 = alle som ikke har egen spesifisert)
				1 => 7, // forretningsmann
				2 => 6 // forhandler
			),
			"bydeler_graphic" => "/imgs/bydeler/firma.png",
			"bydeler_alt_pre" => "Firma: ",
			"parent" => false, // har denne typen parent mellom pri3 og pri4?
			"pri2_takeover" => true // tar medeier over for eier?
		),
		4 => array(
			"type" => "bomberom",
			"refobj" => "bomberommet",
			"typename" => "bomberom",
			"priority" => array(
				1 => "eier",
				2 => "medeier",
				3 => "funksjonær"
			),
			"priority_short" => array(
				1 => "eier",
				2 => "medeier",
				3 => "funksjonær"
			),
			"priority_rank" => array(
				0 => 5, // pusher (0 = alle som ikke har egen spesifisert)
				1 => 7, // forretningsmann
				2 => 6 // forhandler
			),
			"bydeler_graphic" => "/imgs/bydeler/bomberom.png",
			"bydeler_alt_pre" => "Bomberom: ",
			"parent" => false, // har denne typen parent mellom pri3 og pri4?
			"pri2_takeover" => true // tar medeier over for eier?
		),
		5 => array(
			"type" => "vapbes",
			"refobj" => "våpen/beskyttelse-firmaet",
			"typename" => "våpen/beskyttelse-firma",
			"priority" => array(
				1 => "eier",
				2 => "medeier",
				3 => "funksjonær"
			),
			"priority_short" => array(
				1 => "eier",
				2 => "medeier",
				3 => "funksjonær"
			),
			"priority_rank" => array(
				0 => 5, // pusher (0 = alle som ikke har egen spesifisert)
				1 => 7, // forretningsmann
				2 => 6 // forhandler
			),
			"bydeler_graphic" => "/imgs/bydeler/vapbes.png",
			"bydeler_alt_pre" => "Våpen og beskyttelse: ",
			"parent" => false, // har denne typen parent mellom pri3 og pri4?
			"pri2_takeover" => true // tar medeier over for eier?
		),
		6 => array(
			"type" => "sykehus",
			"refobj" => "sykehuset",
			"typename" => "sykehus",
			"priority" => array(
				1 => "eier",
				2 => "medeier",
				3 => "funksjonær"
			),
			"priority_short" => array(
				1 => "eier",
				2 => "medeier",
				3 => "funksjonær"
			),
			"priority_rank" => array(
				0 => 5, // pusher (0 = alle som ikke har egen spesifisert)
				1 => 7, // forretningsmann
				2 => 6 // forhandler
			),
			"bydeler_graphic" => "/imgs/bydeler/sykehus.png",
			"bydeler_alt_pre" => "Sykehus: ",
			"parent" => false, // har denne typen parent mellom pri3 og pri4?
			"pri2_takeover" => true // tar medeier over for eier?
		),
		7 => array(
			"type" => "garasjeutleie",
			"refobj" => "utleiefirmaet",
			"typename" => "utleiefirma",
			"priority" => array(
				1 => "eier",
				2 => "funksjonær"
			),
			"priority_short" => array(
				1 => "eier",
				2 => "funksjonær"
			),
			"priority_rank" => array(
				0 => 5, // pusher (0 = alle som ikke har egen spesifisert)
				1 => 7, // forretningsmann
				2 => 6 // forhandler
			),
			"bydeler_graphic" => "/imgs/bydeler/firma.png",
			"bydeler_alt_pre" => "Garasjeutleie: ",
			"parent" => false, // har denne typen parent mellom pri3 og pri4?
			"pri2_takeover" => false // tar medeier over for eier?
		)
	);
	
	/** Maks broderskap en bruker kan være medlem av */
	const MAX_FAMILIES = 1;
	
	/** Maks broderskap en konkurrende gruppe kan bestå av */
	const MAX_FFF_FF_COUNT = 5;
	
	/** Beløp som må betales for å danne broderskap */
	const CREATE_COST = 20000000; // 20 mill
	
	/** Beløp som må betales for å bytte navn på broderskapet */
	const NAME_CHANGE_COST = 50000000; // 50 mill
	
	/** Beløp som må betales for å selge broderskapet til underboss */
	const SELL_COST = 125000000; // 125 mill
	
	/** Beløp som må betales for å kjøpe informasjon om rankstatus for broderskapene */
	const COMPETITION_INFO_COST = 50000000;
	
	/** Antall medlemmer broderskapet kan ha etter den dannes */
	const MEMBERS_LIMIT_DEFAULT = '0:5;1:1;2:1;3:3;4:0';
	
	/** Kostnad for å utvide broderskapet med en plass */
	const MEMBERS_LIMIT_INCREASE_COST = 3000000;
	
	/** Minste antall medlemmer man kan ha i et broderskap */
	const MEMBERS_LIMIT_TOTAL_MIN = 5;
	
	/** Maks antall medlemmer det er mulig å ha i et broderskap (databasefeltet overkjører dette) */
	const MEMBERS_LIMIT_TOTAL_MAX = 15;
	
	/** Maks antall medlemmer det er mulig å ha i et broderskap i konkurransemodus */
	const MEMBERS_LIMIT_TOTAL_MAX_COMP = 10;
	
	/** Hvor mye den periodiske betalinger øker per eksta medlemsplass */
	const PAY_COST_INCREASE_FFM = 5000000;
	
	/** Hvor mye den periodiske betalingen koster som utgangspunkt */
	const PAY_COST_DEFAULT = 50000000;
	
	/** Hvilket beløp den periodiske betalingen ikke kan gå under */
	const PAY_COST_MIN = 10000000;
	
	/** Hvor mye rank som setter prisen ned med 1 mill */
	const PAY_COST_RANK = 2500;
	
	/** Bank: Innskudd */
	const BANK_INNSKUDD = 1;
	/** Bank: Uttak */
	const BANK_UTTAK = 2;
	/** Bank: Donasjon */
	const BANK_DONASJON = 3;
	/** Bank: Betaling */
	const BANK_BETALING = 4;
	/** Bank: Tilbakebetaling */
	const BANK_TILBAKEBETALING = 5;
	/** Bank: Penger betalt inn til firmaet, blir ikke logget i bankloggen */
	const BANK_TJENT = 6;
	
	/** De ulike ikonene for bankhandlinger */
	public static $bank_ikoner = array();
	
	/** Beskrivelse for de ulike handlingene i banken */
	public static $bank_types = array(
		1 => "Innskudd",
		2 => "Uttak",
		3 => "Donasjon",
		4 => "Betaling",
		5 => "Tilbakebetaling"
	);
	
	/** De ulike hendelsene i loggsystemet */
	public static $log = array(
		// Inviter spiller: ACTION_USER_ID:INVITED_USER_ID:PRIORITY
		"member_invite" => array(1, "Medlem: Inviter spiller"),
		
		// Spiller godtar invitasjon: INVITED_USER_ID
		"member_invite_accept" => array(2, "Medlem: Godta invitasjon"),
		
		// En spiller avslår invitasjonen: INVITED_USER_ID
		"member_invite_decline" => array(3, "Medlem: Avslå invitasjon"),
		
		// Invitasjonen til en spiller blir trukket tilbake: ACTION_USER_ID:INVITED_USER_ID
		"member_invite_pullback" => array(4, "Medlem: Tilbaketrukket invitasjon"),
		
		// capo foreslår en soldier: ACTION_USER_ID:INVITED_USER_ID:PRIORITY
		"member_suggest" => array(5, "Medlem: Foreslå medlem"),
		
		// Forslaget om et medlem blir godtatt; personen blir invitert: ACTION_USER_ID:INVITED_USER_ID:PRIORITY:PARENT
		"member_suggest_accept" => array(6, "Medlem: Godta forslag"),
		
		// Forslaget om medlem blir avslått: ACTION_USER_ID:INVITED_USER_ID
		"member_suggest_decline" => array(7, "Medlem: Avslå forslag"),
		
		// Et medlem forlater: USER_ID:PRIORITY
		"member_leave" => array(8, "Medlem: Forlat"),
		
		// Et medlem forlater: USER_ID:PRIORITY
		"member_deactivated" => array(18, "Medlem: Lav helse"),
		
		// Et medlem blir kastet ut: ACTION_USER_ID:USER_ID:PRIORITY:NOTE
		"member_kicked" => array(9, "Medlem: Sparket"),
		
		// Posisjonen for et medlem blir endret: ACTION_USER_ID:USER_ID:OLD_PRIORITY:NEW_PRIORITY:OLD_PARENT:NEW_PARENT
		"member_priority" => array(10, "Medlem: Posisjon"),
		
		// Overordnet capo for et medlem blir endret: ACTION_USER_ID:USER_ID:OLD_PARENT:NEW_PARENT
		"member_parent" => array(11, "Medlem: Overordnet"),
		
		// Sett en spiller til en posisjon
		"member_set_priority" => array(21, "Medlem: Satt"),
		
		// Ny logo blir lastet opp: ACTION_USER_ID
		"logo" => array(12, "Ny logo"),
		
		// Beskrivelsen blir endret: ACTION_USER_ID
		"description" => array(13, "Endre beskrivelse"),
		
		// Salgsrelaterte meldinger
		"sell" => array(14, "Salg av FF"),
		
		// Forum: Forumtråd opprettet
		"forum_topic_add" => array(30, "Forum: Ny tråd"),
		
		// Forum: Forumtråd slettet
		"forum_topic_delete" => array(31, "Forum: Tråd slettet"),
		
		// Forum: Forumtråd gjenopprettet
		"forum_topic_restore" => array(32, "Forum: Tråd gjenopprettet"),
		
		// Forum: Forumtråd redigert
		"forum_topic_edit" => array(33, "Forum: Tråd redigert"),
		
		// Navnet endret
		"name" => array(15, "Nytt navn"),
		
		// endring av gebyr for bankoverføring
		"bank_overforing_tap_change" => array(16, "Overføringstap"),
		
		// redaktør endrer publisert artikkel
		"article_edited" => array(17, "Rediger artikkel"),
		
		// kastet ut spiller fra bomberommet
		"bomberom_kick" => array(20, "Bomberom utkastelse"),
		
		"dissolve" => array(22, "Oppløst"),
		
		// sette inn kuler
		"bullets_in" => array(23, "Kuler inn"),
		
		// ta ut kuler
		"bullets_out" => array(24, "Kuler ut"),
		
		// info
		"info" => array(25, "Informasjon")
	);
	
	/** Hendelser i logg systemet (reverse, id til navn) */
	public static $log_id = array();
	
	/** Maks antall firmaer man kan være medlem i */
	const FIRMS_MEMBERS_LIMIT = 3;
	
	/** Innstillinger for bankfirmaer */
	public static $type_bank = array(
		"bank_overforing_gebyr_min" => 0,
		"bank_overforing_gebyr_max" => 0.08,
		"eog_steps" => array(
			0.002,
			0,
			-0.002
		)
	);
	
	/**
	 * Prosent man får ved innbetaling av garasjer for GTA
	 */
	const GTA_PERCENT = 0.5;
	
	/** Standard pris for garasjeutleie */
	const GTA_GARAGE_PRICE_DEFAULT = 25000;
	
	const GTA_GARAGE_PRICE_LOW = 1000;
	const GTA_GARAGE_PRICE_HIGH = 50000;
	
	/**
	 * Prosent man får ved innbetaling for å sette spiller i bomberom
	 */
	const BOMBEROM_PERCENT = 0.3;
	
	/**
	 * Modifiers
	 */
	protected $modifiers;
	
	/** FF ID */
	public $id;
	
	/** Er FF aktiv */
	public $active = false;
	
	/** Er FF i konkurransemodus? */
	public $competition = false;
	
	/** Informasjon om FF */
	public $data;
	
	/** Informasjon om FF-typen (fra self::$types) */
	public $type;
	
	/**
	 * Teksten for type FF for referanse (f.eks. "broderskapet", "bomberommet")
	 */
	public $refstring;
	
	/** Medlemmene i FF */
	public $members;
	
	/**
	 * Egen brukerinformasjon som medlem
	 * @var ff_member
	 */
	public $uinfo = false;
	
	/** Tilgang til moderatorhandlinger */
	public $mod = false;
	
	/**
	 * Params
	 * @var params_update
	 */
	public $params = null;
	
	/** Last inn FF på normal måte */
	const LOAD_DEFAULT = 0;
	
	/** Ukke vis feilmeldinger */
	const LOAD_SILENT = 1;
	
	/** I tillegg til LOAD_SILENT: ikke avbryt på inaktivt FF */
	const LOAD_IGNORE = 2;
	
	/** I tillegg til LOAD_SILENT og LOAD_IGNORE: hopp over mod info og sett mod tilgang */
	const LOAD_SCRIPT = 3;
	
	/**
	 * Hent inn FF
	 * @return ff
	 */
	public static function get_ff($id = null, $modifiers = null)
	{
		$ff = new ff($id, $modifiers);
		
		// deaktivert?
		if ($modifiers < self::LOAD_IGNORE && !$ff->data)
		{
			// gi melding?
			if ($modifiers < self::LOAD_SILENT && !defined("SCRIPT_AJAX"))
			{
				ess::$b->page->add_message("Fant ikke FF.", "error");
				redirect::handle("/bydeler", redirect::ROOT);
			}
			
			return null;
		}
		
		// finnes ikke?
		if (!$ff->data)
		{
			return null;
		}
		
		return $ff;
	}
	
	/**
	 * Construct
	 * @param int $id
	 * @param bool $script_mode skal vi hoppe over feilmeldinger, tittel og ignorere deaktiverte FF?
	 * @param bool $silent_mode skal vi hoppe over feilmeldinger og tittel?
	 */
	protected function __construct($id = NULL, $modifiers, $ff_data = null, $members_data = null)
	{
		$this->modifiers = $modifiers;
		
		// finn id
		if ($id !== NULL)
		{
			$this->id = (int) $id;
		}
		
		// fra post
		elseif (isset($_POST['ff_id']))
		{
			$this->id = (int) $_POST['ff_id'];
		}
		
		// fra get
		elseif (isset($_GET['ff_id']))
		{
			$this->id = (int) $_GET['ff_id'];
		}
		
		// mangler
		else
		{
			return;
		}
		
		// hent data
		$this->data = $ff_data !== null ? $ff_data : self::load_data_result("ff_id = $this->id")->fetch();
		
		// finnes ikke?
		if (!$this->data)
		{
			return;
		}
		
		// aktivert?
		if ($this->data['ff_inactive'] == 0)
		{
			$this->active = true;
		}
		
		// sett opp info
		$this->type = &self::$types[$this->data['ff_type']];
		$this->refstring = $this->type['refobj'];
		$this->params = new params_update($this->data['ff_params'], "ff", "ff_params", "ff_id = $this->id");
		
		// konkurransemodus?
		if ($this->data['fff_active'] == 1)
		{
			$this->competition = true;
		}
		
		// hent medlemmer
		$this->load_members($members_data);
		
		// legg til navnet som tittel på siden
		if ($this->modifiers < self::LOAD_SILENT && !defined("SCRIPT_AJAX")) ess::$b->page->add_title($this->data['ff_name']);
	}
	
	/**
	 * Last inn flere FF samtidig
	 */
	public static function get_ff_group($ff_where)
	{
		// hent alle FF
		$result = self::load_data_result($ff_where);
		if ($result->rowCount() == 0) return array();
		
		$list = array();
		while ($row = $result->fetch())
		{
			$list[$row['ff_id']] = $row;
		}
		
		// hent medlemmer
		$members = self::load_members_data("ffm_ff_id IN (".implode(",", array_keys($list)).")");
		
		// opprett ff
		$ff_list = array();
		foreach ($list as $row)
		{
			$ff_list[$row['ff_id']] = new self($row['ff_id'], self::LOAD_SCRIPT, $row, isset($members[$row['ff_id']]) ? $members[$row['ff_id']] : array());
		}
		
		return $ff_list;
	}
	
	/**
	 * Hent data
	 */
	protected static function load_data_result($where)
	{
		// hent detaljer
		$result = \Kofradia\DB::get()->query("
			SELECT
				ff_id, ff_inactive, ff_inactive_time, ff_date_reg, ff_time_reset, ff_type, ff_name, ff_bank, ff_description, ff_up_limit, ff_up_limit_max,
				ff_is_crew, ff_params, ff_br_id, ff_logo IS NOT NULL has_ff_logo, ff_logo_path, ff_fse_id, ff_fff_id, ff_pay_next, ff_pay_status, ff_pay_points,
				ff_attack_failed_num, ff_attack_damaged_num, ff_attack_killed_num, ff_attack_bleed_num,
				ff_attacked_failed_num, ff_attacked_damaged_num, ff_attacked_killed_num, ff_attacked_bleed_num,
				ff_money_in, ff_money_out, ff_money_reset_time, ff_money_in_total, ff_money_out_total, ff_points,
				fff_id, fff_time_start, fff_time_expire, fff_time_expire_br, fff_required_points, fff_active,
				br_id, br_b_id, br_pos_x, br_pos_y
			FROM ff
				LEFT JOIN bydeler_resources ON ff_br_id = br_id
				LEFT JOIN ff_free ON fff_id = ff_fff_id
			WHERE $where");
		
		return $result;
	}
	
	/**
	 * Hent medlemmer for FF
	 */
	protected static function load_members_data($where)
	{
		$result = \Kofradia\DB::get()->query("
			SELECT
				ffm_ff_id, ffm_up_id, ffm_date_created, ffm_date_join, ffm_donate, ffm_priority, ffm_parent_up_id, ffm_status, ffm_params, ffm_forum_topics, ffm_forum_replies, ffm_earnings, ffm_earnings_ff, ffm_pay_points, ffm_log_new,
				up_u_id, up_name, up_access_level, up_last_online, up_points, up_points_rel,
				upr_rank_pos
			FROM ff_members
				JOIN users_players ON ffm_up_id = up_id
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE $where AND ffm_status != ".ff_member::STATUS_KICKED." AND ffm_status != ".ff_member::STATUS_DEACTIVATED."
			ORDER BY ffm_priority, up_name");
		
		$ff_members = array();
		while ($row = $result->fetch())
		{
			$ff_members[$row['ffm_ff_id']][] = $row;
		}
		
		return $ff_members;
	}
	
	/** Hent medlemmene */
	protected function load_members($members = null)
	{
		// hent medlemmene
		if ($members === null)
		{
			$members = self::load_members_data("ffm_ff_id = $this->id");
			$members = isset($members[$this->id]) ? $members[$this->id] : array();
		}
		
		$this->members_reset();
		
		// gå gjennom hvert medlem og legg til
		foreach ($members as $row)
		{
			// posisjonen
			if (!isset($this->type['priority'][$row['ffm_priority']]))
			{
				throw new HSException("Fant ikke posisjonene med prioritering {$row['ffm_priority']}.");
			}
			
			// opprett medlem objekt
			$member = new ff_member($row, $this);
			$member->attach();
		}
		
		$this->members_check_crew();
		
		// moderatortilgang?
		$this->mod = $this->modifiers >= self::LOAD_SCRIPT || access::has("mod");
	}
	
	/**
	 * Nullstill medlem-objektet
	 */
	protected function members_reset()
	{
		$this->members = array(
			"members" => array(),
			"members_priority" => array(),
			"members_parent" => array(),
			"suggested" => array(),
			"suggested_priority" => array(),
			"suggested_parent" => array(),
			"invited" => array(),
			"invited_priority" => array(),
			"invited_parent" => array()
		);
		$this->uinfo = false;
	}
	
	/**
	 * Sjekk om vi skal ha crewtilgang
	 */
	protected function members_check_crew()
	{
		if ($this->modifiers < self::LOAD_SCRIPT)
		{
			// er vi mod+ uten brukerinfo?
			if (access::has("mod") && !$this->uinfo)
			{
				$row = array(
					"ffm_up_id" => login::$user->player->id,
					"ffm_date_created" => 0,
					"ffm_date_join" => 0,
					"ffm_donate" => 0,
					"ffm_priority" => 0,
					"ffm_parent_up_id" => 0,
					"ffm_status" => 1,
					"ffm_params" => "",
					"ffm_forum_topics" => 0,
					"ffm_forum_replies" => 0,
					"ffm_earnings" => 0,
					"ffm_earnings_ff" => 0,
					"ffm_pay_points" => null,
					"ffm_log_new" => 0,
					"up_u_id" => login::$user->id,
					"up_name" => login::$user->player->data['up_name'],
					"up_access_level" => login::$user->player->data['up_access_level'],
					"up_last_online" => login::$user->player->data['up_last_online']
				);
				$this->uinfo = new ff_member($row, $this);
				$this->uinfo->crew();
			}
		}
	}
	
	/** Finn ut om vi har tilgang til en spesifikk side */
	public function access($name = true)
	{
		if ($name === true) return $this->mod || $this->uinfo !== false;
		
		// cache
		static $checked = array();
		if (isset($checked[$name])) return $checked[$name];
		
		// finn prioriterings-ID
		if (!is_int($name))
		{
			if (!in_array($name, $this->type['priority']))
			{
				throw new HSException("Ugyldig prioritering: $name.");
			}
			
			// finn ut prioriteringsID-en
			$id = array_search($name, $this->type['priority']);
		}
		else
		{
			$id = $name;
		}
		
		// mod
		if ($this->mod)
		{
			$checked[$name] = true;
			return true;
		}
		
		// ikke medlem eller deaktivert FF?
		if (!$this->uinfo || !$this->active)
		{
			$checked[$name] = false;
			return false;
		}
		
		// har vi denne eller lavere?
		if ($this->uinfo->data['ffm_priority'] <= $id)
		{
			$checked[$name] = true;
			return true;
		}
		
		$checked[$name] = false;
		return false;
	}
	
	/** Krev tilgang til å vise siden */
	public function needaccess($name, $text = "Du har ikke tilgang!")
	{
		if (!$this->access($name))
		{
			ess::$b->page->add_message($text, "error");
			$this->redirect();
		}
		
		return true;
	}
	
	/**
	 * Krev en spesiell type FF
	 */
	public function needtype($type)
	{
		if ($this->type['type'] != $type)
		{
			$this->redirect();
		}
	}
	
	/**
	 * Hent ID til forumet
	 */
	public function get_fse_id()
	{
		$fse_id = $this->data['ff_fse_id'];
		
		// må vi opprette forumet?
		if (!$fse_id)
		{
			\Kofradia\DB::get()->beginTransaction();
			
			$result = \Kofradia\DB::get()->query("SELECT ff_fse_id FROM ff WHERE ff_id = $this->id FOR UPDATE");
			$fse_id = $result->fetchColumn(0);
			if (!$fse_id)
			{
				// opprett forumet
				\Kofradia\DB::get()->exec("INSERT INTO forum_sections SET fse_ff_id = $this->id");
				$fse_id = \Kofradia\DB::get()->lastInsertId();
				
				// oppdater ff
				\Kofradia\DB::get()->exec("UPDATE ff SET ff_fse_id = $fse_id WHERE ff_id = $this->id");
			}
			
			// lagre i ff
			$this->data['ff_fse_id'] = $fse_id;
			
			\Kofradia\DB::get()->commit();
		}
		
		return $fse_id;
	}
	
	/** Send til ff-siden */
	public function redirect($extra = "")
	{
		if (!empty($extra)) $extra = "&" . $extra;
		redirect::handle("/ff/?ff_id={$this->id}$extra", redirect::ROOT);
	}
	
	/**
	 * Bankbevegelse
	 * @param int $type self::BANK_*
	 * @param int $amount
	 * @param string $note
	 * @param mixed $anonymous anonym handling (spillet utfører), evt. bestemt bruker som utfører
	 * @return bool success
	 */
	public function bank($type, $amount, $note = NULL, $anonymous = false)
	{
		return self::bank_static($type, $amount, $this->id, $note, $anonymous);
	}
	
	/**
	 * Bankbevegelse
	 * @param int $type self::BANK_*
	 * @param int $amount
	 * @param int $ff_id FF-ID hvis statisk kall
	 * @param string $note
	 * @param mixed $anonymous anonym handling (spillet utfører), evt. bestemt bruker som utfører
	 * @return bool success
	 */
	public static function bank_static($type, $amount, $ff_id, $note = NULL, $anonymous = false)
	{
		if (!$ff_id) throw new HSException("Mangler ff_id.");
		if (!$anonymous && !login::$logged_in) throw new HSNotLoggedIn();
		
		$amount = game::intval($amount);
		
		if ($type != self::BANK_TJENT)
		{
			$note = \Kofradia\DB::quote($note);
			$up_id = is_numeric($anonymous) ? (int) $anonymous : ($anonymous ? 'NULL' : intval(login::$user->player->id));
		}
		
		// trekk fra penger
		if ($type == self::BANK_UTTAK || $type == self::BANK_BETALING)
		{
			$a = \Kofradia\DB::get()->exec("UPDATE ff SET ff_bank = ff_bank - $amount WHERE ff_id = $ff_id AND ff_bank >= $amount");
			
			if ($a == 0)
			{
				return false;
			}
			
			\Kofradia\DB::get()->exec("INSERT INTO ff_bank_log (ffbl_ff_id, ffbl_type, ffbl_amount, ffbl_up_id, ffbl_note, ffbl_time, ffbl_balance) SELECT ff_id, $type, $amount, $up_id, $note, ".time().", ff_bank FROM ff WHERE ff_id = $ff_id");
			
			// oppdater daglig statistikk
			self::stats_update_static($ff_id, "money_out", $amount, true);
		}
		
		// legg til penger
		else
		{
			\Kofradia\DB::get()->exec("UPDATE ff SET ff_bank = ff_bank + $amount WHERE ff_id = $ff_id");
			if ($type != self::BANK_TJENT) \Kofradia\DB::get()->exec("INSERT INTO ff_bank_log (ffbl_ff_id, ffbl_type, ffbl_amount, ffbl_up_id, ffbl_note, ffbl_time, ffbl_balance) SELECT ff_id, $type, $amount, $up_id, $note, ".time().", ff_bank FROM ff WHERE ff_id = $ff_id");
			
			// oppdater daglig statistikk
			self::stats_update_static($ff_id, "money_in", $amount, $type != self::BANK_TJENT);
		}
		
		return true;
	}
	
	/**
	 * Oppdater statistikk
	 * @param string $type
	 * @param int $value
	 * @param bool $skip_ff ikke lagre i ff-tabellen
	 */
	public function stats_update($type, $value, $skip_ff = null)
	{
		return self::stats_update_static($this->id, $type, $value, $skip_ff);
	}
	
	/**
	 * Oppdater statistikk
	 * @param int $ff_id
	 * @param string $type
	 * @param int $value
	 * @param bool $skip_ff ikke lagre i ff-tabellen
	 */
	public static function stats_update_static($ff_id, $type, $value, $skip_ff = null)
	{
		$type = mb_strtolower($type);
		$value = game::intval($value);
		static $types = array("money_in", "money_out");
		
		// ugyldig type?
		if (!in_array($type, $types)) throw new HSException("Ugyldig type.");
		
		// oppdater ff
		if (!$skip_ff)
		{
			$c = \Kofradia\DB::get()->exec("
				UPDATE ff
				SET ff_{$type} = ff_{$type} + $value
				WHERE ff_id = $ff_id");
			
			// ingen ff ble oppdatert?
			if ($c == 0) return false;
		}
		
		// oppdater stats_daily for ff
		$today = \Kofradia\DB::quote(ess::$b->date->get()->format("Y-m-d"));
		$a = \Kofradia\DB::get()->exec("
			INSERT INTO ff_stats_daily
			SET ffsd_ff_id = $ff_id, ffsd_date = $today, ffsd_{$type} = $value
			ON DUPLICATE KEY UPDATE ffsd_{$type} = ffsd_{$type} + $value");
		
		return $a > 0;
	}
	
	/** Legg til logg */
	public function add_log($action, $data, $extra = false)
	{
		// finn action
		if (!isset(self::$log[$action]))
		{
			throw new HSException("Ukjent logg type: $action.");
		}
		
		$action_id = intval(self::$log[$action][0]);
		$data = \Kofradia\DB::quote($data);
		
		$extra = $extra !== false ? ", ffl_extra = ".\Kofradia\DB::quote($extra) : "";
		
		// legg til logg
		\Kofradia\DB::get()->exec("INSERT INTO ff_log SET ffl_time = ".time().", ffl_ff_id = {$this->id}, ffl_type = $action_id, ffl_data = $data$extra");
		
		// oppdater telleren hos medlemmene
		if ($this->active) \Kofradia\DB::get()->exec("UPDATE users_players, ff_members SET up_log_ff_new = up_log_ff_new + 1, ffm_log_new = ffm_log_new + 1 WHERE ffm_ff_id = $this->id AND ffm_status = 1 AND up_id = ffm_up_id");
	}
	
	/** Formatere hendelser i loggen */
	public function format_log($id, $time, $action, $data, $extra)
	{
		global $__server;
		
		// sett opp data
		switch (self::$log_id[$action])
		{
			// Forum: Ny forumtråd
			case "forum_topic_add":
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> opprettet <a href="'.$__server['relative_path'].'/forum/topic?id='.$info[1].'">'.htmlspecialchars(urldecode($info[2])).'</a> i forumet.';
			break;
			
			// Forum: Forumtråd slettet
			case "forum_topic_delete":
				$info = explode(":", $data);
				$title = $this->mod ? '<a href="'.$__server['relative_path'].'/forum/topic?id='.$info[1].'">'.htmlspecialchars(urldecode($info[2])).'</a>' : htmlspecialchars(urldecode($info[2]));
				$data = '<user id="'.$info[0].'" /> slettet '.$title.' fra forumet.';
			break;
			
			// Forum: Forumtråd gjenopprettet
			case "forum_topic_restore":
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> gjenopprettet <a href="'.$__server['relative_path'].'/forum/topic?id='.$info[1].'">'.htmlspecialchars(urldecode($info[2])).'</a> i forumet.';
			break;
			
			// Forum: Forumtråd redigert
			case "forum_topic_edit":
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> redigerte <a href="'.$__server['relative_path'].'/forum/topic?id='.$info[1].'">'.htmlspecialchars(urldecode($info[2])).'</a> i forumet.';
			break;
			
			// Medlem: Inviter spiller
			case "member_invite":
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> inviterte <user id="'.$info[1].'" /> til '.$this->refstring.' som <b>'.htmlspecialchars(urldecode($info[2])).(!empty($info[3]) ? ' underordnet <user id="'.$info[3].'" />' : '').'</b>.';
			break;
			
			// Medlem: Godta invitasjon
			case "member_invite_accept":
				$data = '<user id="'.$data.'" /> godtok invitasjonen og er nå medlem av '.$this->refstring.'.';
			break;
			
			// Medlem: Avslå invitasjon
			case "member_invite_decline":
				$data = '<user id="'.$data.'" /> avslo invitasjonen til '.$this->refstring.'.';
			break;
			
			// Medlem: Tilbaketrukket invitasjon
			case "member_invite_pullback":
				$info = explode(":", $data);
				if ($info[0])
				{
					$data = '<user id="'.$info[0].'" /> trakk tilbake invitasjonen til <user id="'.$info[1].'" />.';
				}
				else
				{
					$data = 'Invitasjonen til <user id="'.$info[1].'" /> ble trukket tilbake.';
				}
			break;
			
			// Medlem: Foreslå medlem
			case "member_suggest":
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> foreslo <user id="'.$info[1].'" /> som medlem av '.$this->refstring.' som <b>'.htmlspecialchars(urldecode($info[2])).'</b>.';
			break;
			
			// Medlem: Godta forslag
			case "member_suggest_accept":
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> godtok forslaget'.($info[3] ? ' til <user id="'.$info[3].'" />' : '').' om å invitere <user id="'.$info[1].'" /> til '.$this->refstring.' som <b>'.htmlspecialchars(urldecode($info[2])).'</b>. <user id="'.$info[1].'" /> er nå invitert.';
			break;
			
			// Medlem: Avslå forslag
			case "member_suggest_decline":
				$info = explode(":", $data);
				if ($info[0])
				{
					$data = '<user id="'.$info[0].'" /> avslo forslaget om å invitere <user id="'.$info[1].'" />.';
				}
				else
				{
					$data = 'Forslaget om å invitere <user id="'.$info[1].'" /> ble avslått.';
				}
			break;
			
			// Medlem: Forlat
			case "member_leave":
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> forlot '.$this->refstring.(isset($info[1]) ? ' fra sin posisjon som <b>'.htmlspecialchars(urldecode($info[1])).'</b>' : '').'.';
			break;
			
			// Medlem: Drept/for lav helse
			case "member_deactivated":
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> fikk for lite helse til å kunne opprettholde sitt medlemskap.'.(isset($info[1]) ? ' Hadde posisjonen <b>'.htmlspecialchars(urldecode($info[1])).'</b>' : '').'.';
			break;
			
			// Medlem: Sparket
			case "member_kicked":
				$info = explode(":", $data);
				$note = $note = empty($info[3]) ? '' : ' Begrunnelse: '.game::bb_to_html(urldecode($info[3]));
				$data = '<user id="'.$info[0].'" /> sparket <user id="'.$info[1].'" /> fra '.$this->refstring.' og sin posisjon som <b>'.htmlspecialchars(urldecode($info[2])).'</b>.'.$note;
			break;
			
			// Medlem: Posisjon
			case "member_priority":
				$info = explode(":", $data);
				if ($info[0])
				{
					$data = '<user id="'.$info[0].'" /> endret posisjonen til <user id="'.$info[1].'" /> fra <b>'.htmlspecialchars(urldecode($info[2])).'</b>'.(!empty($info[4]) ? ' underordnet <user id="'.$info[4].'" />' : '').' til <b>'.htmlspecialchars(urldecode($info[3])).'</b>'.(!empty($info[5]) ? ' underordnet <user id="'.$info[5].'" />' : '').'.';
				}
				else
				{
					// anonym
					$data = 'Posisjonen til <user id="'.$info[1].'" /> ble endret fra <b>'.htmlspecialchars(urldecode($info[2])).'</b>'.(!empty($info[4]) ? ' underordnet <user id="'.$info[4].'" />' : '').' til <b>'.htmlspecialchars(urldecode($info[3])).'</b>'.(!empty($info[5]) ? ' underordnet <user id="'.$info[5].'" />' : '').'.';
				}
			break;
			
			// Medlem Overordnet capo
			case "member_parent":
				$info = explode(":", $data);
				if ($info[0])
				{
					$data = '<user id="'.$info[0].'" /> endret overordnet til <user id="'.$info[1].'" /> fra <user id="'.$info[2].'" /> til <user id="'.$info[3].'" />.';
				}
				else
				{
					// anonym
					$data = 'Overordnet til <user id="'.$info[1].'" /> ble endret fra <user id="'.$info[2].'" /> til <user id="'.$info[3].'" />.';
				}
			break;
			
			// Sett en spiller til en bestemt posisjon
			case "member_set_priority":
				// syntax: up_id:priority_name:parent_up_id
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> ble satt som <b>'.htmlspecialchars(urldecode($info[1])).'</b>'.(!empty($info[2]) ? ' underordnet <user id="'.$info[2].'" />' : '').'.';
			break;
			
			// Ny logo
			case "logo":
				if (empty($data))
				{
					$data = "Logoen ble fjernet.";
				}
				else
				{
					$info = explode(":", $data);
					$data = isset($info[1]) && $info[1] == "removed"
						? '<user id="'.$info[0].'" /> fjernet logoen'
						: '<user id="'.$info[0].'" /> lastet opp'.(!empty($extra) ? ' ny logo' : ' en logo');
					$data .= ' for '.$this->type['refobj'].'.';
				}
				if (!empty($extra))
				{
					$data .= ' Gammel logo: <img src="'.ess::$s['rpath'].'/ff/_logo?ff_id='.$this->id.'&amp;log_id='.$id.'" alt="Gammel logo" />';
				}
			break;
			
			// Endre beskrivelse
			case "description":
				$data = '<user id="'.$data.'" /> redigerte beskrivelsen for '.$this->refstring.'.';
			break;
			
			// Salg
			case "sell":
				$info = explode(":", $data);
				switch ($info[0])
				{
					// starter salget:
					// {TYPE=init}:BOSS:UBOSS:FEE:AMOUNT
					case "init":
						$data = '<user id="'.$info[1].'" /> åpnet salg av '.$this->refstring.' til <user id="'.$info[2].'" /> for '.game::format_cash($info[4]).'.';
					break;
					
					// avbryter salget (trekker det tilbake)
					// {TYPE=abort}:BOSS:UBOSS:FEE:AMOUNT
					case "abort":
						$data = '<user id="'.$info[1].'" /> trakk tilbake salg av '.$this->refstring.' til <user id="'.$info[2].'" /> for '.game::format_cash($info[4]).'.';
					break;
					
					// godtar kjøpet (mottakeren)
					// {TYPE=approve}:BOSS:UBOSS:FEE:AMOUNT
					case "approve":
						$data = '<user id="'.$info[2].'" /> godtok salget av '.$this->refstring.' og har nå overtatt som '.htmlspecialchars($this->type['priority'][1]).'. <user id="'.$info[1].'" /> har blitt satt som '.htmlspecialchars($this->type['priority'][2]).'. Gebyr: '.game::format_cash($info[3]).'. Salgsbeløp: '.game::format_cash($info[4]).'.';
					break;
					
					// avslår kjøpet (mottakeren)
					// {TYPE=reject}:UBOSS:FEE:AMOUNT
					case "reject":
						$data = '<user id="'.$info[2].'" /> avslo salget av '.$this->refstring.' for '.game::format_cash($info[4]).'.';
					break;
				}
			break;
			
			// Nytt navn
			case "name":
				// syntax: gammelt navn:nytt navn:spiller som sendte søknad:innvilget av
				// syntax (abstrakt): ff_name:ff_name:up_id:up_id
				$info = explode(":", $data);
				$data = 'Navnet på '.$this->refstring.' ble endret fra '.htmlspecialchars(urldecode($info[0])).' til <b>'.htmlspecialchars(urldecode($info[1])).'</b>.';
			break;
			
			case "bank_overforing_tap_change":
				$info = explode(":", $data);
				$data = 'Overføringsgebyret endret seg med '.game::format_number($info[1]*100, 2).' % til <b>'.game::format_number(($info[0] + $info[1])*100, 2).' %</b>.';
			break;
			
			case "article_edited":
				// data: fna_id,up_id,fna_up_id,fna_title_org,fna_title_new,fna_text_old,fna_text_new
				$info = array_map("urldecode", explode(":", $data));
				$data = '<user id="'.$info[1].'" /> redigerte <a href="'.$__server['relative_path'].'/ff/avis?ff_id='.$this->id.'&amp;a&amp;ffna='.$info[0].'">artikkelen</a> til <user id="'.$info[2].'" />.';
			break;
			
			// kastet ut spiller fra bomberommet
			case "bomberom_kick":
				// syntax: up_id(som utfører handlingen):up_id(som ble kastet ut):up_brom_expire(når vi egentlig skulle gå ut av bomberommet)
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> kastet ut <user id="'.$info[1].'" /> fra bomberommet, som egentlig skulle sittet til '.ess::$b->date->get($info[2])->format().'.';
			break;
			
			// oppløst
			case "dissolve":
				// syntax: up_id(som utfører handlingen):up_id(som ble kastet ut):up_brom_expire(når vi egentlig skulle gå ut av bomberommet)
				$info = explode(":", $data);
				$data = ucfirst($this->type['refobj']).' ble oppløst.';
			break;
			
			// kuler inn
			case "bullets_in":
				// syntax: up_id:num
				$info = explode(":", $data);
				$data = '<user id="'.$info[0].'" /> satt inn '.fwords("%d kule", "%d kuler", $info[1]).' i kulelageret.';
			break;
			
			// kuler ut
			case "bullets_out":
				// syntax: up_id:num[:action_up]
				$info = explode(":", $data);
				if (isset($info[2]))
				{
					$data = '<user id="'.$info[2].'" /> tok ut '.fwords("%d kule", "%d kuler", $info[1]).' fra kulelageret og gav de til <user id="'.$info[0].'" />.';
				}
				else
				{
					$data = '<user id="'.$info[0].'" /> tok ut '.fwords("%d kule", "%d kuler", $info[1]).' fra kulelageret.';
				}
			break;
			
			// informasjon
			case "info":
				// behold data urørt
			break;
			
			// Dummy
			case "dummy":
				$data = $time;
			break;
			
			default:
				$data = htmlspecialchars($data);
		}
		
		return $data;
	}
	
	/**
	 * Last inn siden med malen
	 * @param bool $header vis header
	 * @param bool $footer vis footer
	 */
	public function load_page($header = true, $footer = true)
	{
		// hent ut data som er sendt så langt
		$data = @ob_get_contents();
		@ob_clean();
		
		// bygg opp header
		if ($header) $this->load_header();
		
		// send data
		echo $data;
		
		// bygg opp footer
		if ($footer) $this->load_footer();
		
		// last inn siden
		ess::$b->page->load();
	}
	
	/**
	 * Last inn header
	 */
	public function load_header()
	{
		// hjelpefunksjon
		$fn_playerlist = function($member)
		{
			return '<user id="'.$member->id.'" nolink />';
		};
		
		$reg_time = $this->data['ff_date_reg'];
		$has_bydel = isset(game::$bydeler[$this->data['br_b_id']]);
		$stiftet_og_bydel = '';
		if ($reg_time || $has_bydel)
		{
			$stiftet_og_bydel = ' <span class="bydel">(';
			if ($reg_time) $stiftet_og_bydel .= ess::$b->date->get($this->data['ff_date_reg'])->format(date::FORMAT_NOTIME);
			if ($reg_time && $has_bydel) $stiftet_og_bydel .= ' - ';
			if ($has_bydel) $stiftet_og_bydel .= htmlspecialchars(game::$bydeler[$this->data['br_b_id']]['name']);
			$stiftet_og_bydel .= ')</span>';
		}
		$membername = $this->type['type'] == "familie" ? 'Medlemmer' : 'Ansatte';
		
		echo '
<p class="firmalink mainboks">
	<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$this->id.'">
		<img src="'.htmlspecialchars($this->get_logo_path()).'" class="firma" alt="" />
		<span class="name">'.htmlspecialchars($this->data['ff_name']).(!$this->active ? ' (deaktivert)' : '').'</span>'.$stiftet_og_bydel.'<br />
		'.ucfirst($this->type['priority'][1]).': '.(isset($this->members['members_priority'][1]) ? implode(", ", array_map($fn_playerlist, $this->members['members_priority'][1])) : 'Ingen').'<br />
		'.ucfirst($this->type['priority'][2]).': '.(isset($this->members['members_priority'][2]) ? implode(", ", array_map($fn_playerlist, $this->members['members_priority'][2])) : 'Ingen').'<br />
		'.$membername.': '.count($this->members['members']).'
	</a>
</p>';
	}
	
	/**
	 * Last inn footer
	 */
	public function load_footer()
	{
		// har ikke tilgang til å administrere?
		if (!$this->access(true)) return;
		
		echo '
<div class="clear"></div>
<p class="firma_a2">
	<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$this->id.'">Forside</a>
	<a href="'.ess::$s['relative_path'].'/ff/panel?ff_id='.$this->id.'">Panel</a>';
		
		if ($this->access(2))
		{
			$membername = $this->type['type'] == "familie" ? 'Medlemmer' : 'Ansatte';
			
			echo '
	<a href="'.ess::$s['relative_path'].'/ff/banken?ff_id='.$this->id.'">Banken</a>
	<a href="'.ess::$s['relative_path'].'/ff/medlemmer?ff_id='.$this->id.'"><img src="'.STATIC_LINK.'/firma/ikon_ansatte.gif" alt="" /> '.$membername.'</a>
	<a href="'.ess::$s['relative_path'].'/ff/panel?ff_id='.$this->id.'&amp;a=beskrivelse"><img src="'.STATIC_LINK.'/firma/ikon_firmabeskrivelse.gif" alt="" /> Beskrivelse</a>';
		}
		
		// forskjellige typer firmaer
		switch ($this->type['type'])
		{
			case "bank":
				if ($this->access(3))
				{
					echo '
	<a href="'.ess::$s['relative_path'].'/ff/bank?ff_id='.$this->id.'">Bankpanel</a>';
				}
			break;
			
			case "avis":
				echo '
	<a href="'.ess::$s['relative_path'].'/ff/avis?ff_id='.$this->id.'&amp;u"><img src="'.STATIC_LINK.'/firma/ikon_avisutgivelser.gif" alt="" /> Avisutgivelser</a>
	<a href="'.ess::$s['relative_path'].'/ff/avis?ff_id='.$this->id.'&amp;a"><img src="'.STATIC_LINK.'/firma/ikon_avisartikler.gif" alt="" /> Avisartikler</a>';
			break;
			
			case "bomberom":
			case "familie":
				// ikke vis konkurrerende broderskap
				if (!$this->competition)
					echo '
	<a href="'.ess::$s['relative_path'].'/ff/bomberom?ff_id='.$this->id.'">Bomberommet</a>';
			break;
		}
		
		echo '
	<a href="'.ess::$s['relative_path'].'/ff/logg?ff_id='.$this->id.'">Logg</a>
	<a href="'.ess::$s['relative_path'].'/forum/forum?id='.$this->get_fse_id().'">Forum</a>
</p>';
	}
	
	/** Formater data for beskrivelsen */
	public function format_description($data = -1)
	{
		if ($data == -1) $data = $this->data['ff_description'];
		return game::format_data(game::format_data(game::format_data($data, "music_pre"), "bb"), "music_post");
	}
	
	/**
	 * Inviter spiller
	 * Utfører i hovedsak kun det å legge til spilleren i databasen, og sjekker ikke rankkrav osv
	 * @param int $up_id
	 * @param int $priority
	 * @param int $parent forelder til soldier
	 */
	public function player_invite($up_id, $priority, $parent = NULL)
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		global $_game;
		
		$up_id = (int) $up_id;
		$priority = (int) $priority;
		$parent = (int) $parent;
		
		if (!empty($parent) && $priority != 4) throw new HSException("Kun soldiers kan ha parent.");
		
		// sjekk at brukeren finnes og ikke allerede er medlem, invitert eller foreslått av capo
		$result = \Kofradia\DB::get()->query("
			SELECT up_id, up_name, up_access_level, !ISNULL(ffm_ff_id) AS in_fa
			FROM users_players
				LEFT JOIN ff_members ON ffm_up_id = up_id AND ffm_status != ".ff_member::STATUS_KICKED." AND ffm_status != ".ff_member::STATUS_DEACTIVATED." AND ffm_ff_id = $this->id
			WHERE up_id = $up_id
			GROUP BY up_id");
		$row = $result->fetch();
		
		// fant ikke brukeren?
		if (!$row)
		{
			return false;
		}
		
		// allerede medlem?
		if ($row['in_fa'])
		{
			return false;
		}
		
		// inviter
		$time = time();
		\Kofradia\DB::get()->exec("
			INSERT INTO ff_members
				SET ffm_up_id = $up_id, ffm_ff_id = {$this->id}, ffm_date_created = $time, ffm_date_join = $time, ffm_priority = $priority, ffm_status = 0, ffm_parent_up_id = $parent
			ON DUPLICATE KEY
				UPDATE ffm_ff_name = NULL, ffm_date_join = $time, ffm_priority = $priority, ffm_status = 0, ffm_parent_up_id = $parent");
		
		$info = $this->id.":".urlencode($this->data['ff_name']).":".urlencode($this->type['priority'][$priority]).($parent ? ":$parent" : "");
		player::add_log_static("ff_invite", $info, login::$user->player->id, $up_id);
		
		// legg til logg
		$this->add_log("member_invite", "".login::$user->player->id.":$up_id:".urlencode($this->type['priority'][$priority]).($parent ? ":$parent" : ""));
		
		return true;
	}
	
	/**
	 * Foreslå soldier (kun en capo kan foreslå)
	 * @param int $up_id
	 * @param int $priority
	 */
	public function player_suggest($up_id)
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		$up_id = (int) $up_id;
		
		// sjekk for feil
		if (!$this->uinfo || $this->uinfo->crew) throw new HSException("Spilleren må være medlem av FF for å kunne foreslå noen til den.");
		if ($this->uinfo->data['ffm_priority'] != 3) throw new HSException("Kun pri3 kan foreslå en spiller.");
		
		// hvilken posisjon kan vi foreslå til?
		$limits = $this->get_limits();
		$priority = isset($limits[4]) && $limits[4] >= 0 ? 4 : 3;
		
		// sjekk at brukeren finnes og ikke allerede er medlem, invitert eller foreslått av capo
		$result = \Kofradia\DB::get()->query("
			SELECT up_id, up_name, up_access_level, !ISNULL(ffm_ff_id) AS in_ff
			FROM users_players
				LEFT JOIN ff_members ON ffm_up_id = up_id AND ffm_status != ".ff_member::STATUS_KICKED." AND ffm_status != ".ff_member::STATUS_DEACTIVATED." AND ffm_ff_id = $this->id
			WHERE up_id = $up_id
			GROUP BY up_id");
		$row = $result->fetch();
		
		// fant ikke brukeren?
		if (!$row)
		{
			return false;
		}
		
		// allerede medlem?
		if ($row['in_ff'])
		{
			return false;
		}
		
		// foreslå
		$time = time();
		$parent = $this->type['parent'] ? ", ffm_parent_up_id = ".login::$user->player->id : "";
		\Kofradia\DB::get()->exec("
			INSERT INTO ff_members
				SET ffm_up_id = $up_id, ffm_ff_id = $this->id, ffm_date_created = $time, ffm_date_join = $time, ffm_priority = $priority, ffm_status = ".ff_member::STATUS_SUGGESTED."$parent
			ON DUPLICATE KEY
				UPDATE ffm_ff_name = NULL, ffm_date_join = $time, ffm_priority = $priority, ffm_status = ".ff_member::STATUS_SUGGESTED.$parent);
		
		// legg til logg
		$is_not_parent = $this->type['parent'] ? "0" : "1";
		$this->add_log("member_suggest", login::$user->player->id.":$up_id:".urlencode($this->type['priority'][$priority]).":$is_not_parent");
		
		return true;
	}
	
	/**
	 * Sett en spiller til en bestemt posisjon
	 * @param int $up_id
	 * @param int $priority
	 */
	public function player_set_priority($up_id, $priority, $parent = null, $skip_playerlog = null)
	{
		$up_id = (int) $up_id;
		$priority = (int) $priority;
		$parent = (int) $parent;
		
		// har vi denne spilleren allerede?
		if (isset($this->members['list'][$up_id]))
		{
			// endre posisjonen
			return $this->members['list'][$up_id]->change_priority($priority, $parent, true);
		}
		
		else
		{
			// sett som posisjonen
			$time = time();
			\Kofradia\DB::get()->exec("
				INSERT INTO ff_members
					SET ffm_up_id = $up_id, ffm_ff_id = $this->id, ffm_date_created = $time, ffm_date_join = $time, ffm_priority = $priority, ffm_status = 1, ffm_parent_up_id = $parent
				ON DUPLICATE KEY
					UPDATE ffm_ff_name = NULL, ffm_date_join = $time, ffm_priority = 1, ffm_status = 1, ffm_parent_up_id = $parent");
			
			// hendelse for spilleren
			if (!$skip_playerlog)
			{
				$info = $this->id.":".urlencode($this->data['ff_name']).":".urlencode($this->type['priority'][$priority]).":$parent";
				player::add_log_static("ff_member_set_priority", $info, null, $up_id);
			}
			
			// broderskaplogg
			$this->add_log("member_set_priority", "$up_id:".urlencode($this->type['priority'][$priority]).":$parent");
			
			$this->load_members();
			
			// trigger
			player::get($up_id)->trigger("ff_join", array(
					"ff" => $this,
					"member" => $this->members['list'][$up_id],
					"priority" => $priority,
					"parent" => $parent));
			
			return true;
		}
	}
	
	/**
	 * Nullstill FF
	 */
	public function reset($skip_reset_bank = null)
	{
		putlog("LOG", "FF nullstilt: #{$this->id} ({$this->data['ff_name']}) - forrige nullstilling={$this->data['ff_time_reset']}, forrige banknullstilling={$this->data['ff_money_reset_time']}, bank={$this->data['ff_bank']}");
		
		// nullstill forumet
		$this->reset_forum();
		
		// ta backup av FF
		database_archive::handle_ff($this->id);
		
		// fjern evt. params
		$keep = array("die_no_new", "bomberom_kapasitet", "bank_overforing_tap");
		$this->params->lock();
		foreach (array_keys($this->params->params) as $name)
		{
			if (!in_array($name, $keep)) $this->params->remove($name);
		}
		$this->params->commit();
		
		// hent logo og lagre den i logg
		$result = \Kofradia\DB::get()->query("SELECT ff_logo FROM ff WHERE ff_id = $this->id");
		$old = $result->fetchColumn(0);
		if (!empty($old))
		{
			$this->add_log("logo", null, base64_encode($old));
		}
		
		// oppdater FF
		$time = time();
		$bank = $skip_reset_bank ? "" : ", ff_bank = 0";
		\Kofradia\DB::get()->exec("
			UPDATE ff
			SET
				ff_time_reset = $time, ff_name = ".\Kofradia\DB::quote(ucfirst($this->type['typename']))."$bank, ff_description = NULL, ff_logo = NULL,
				ff_pay_next = NULL, ff_pay_status = 0, ff_pay_points = 0,
				ff_attack_failed_num = 0, ff_attack_damaged_num = 0, ff_attack_killed_num = 0, ff_attack_bleed_num = 0,
				ff_attacked_failed_num = 0, ff_attacked_damaged_num = 0, ff_attacked_killed_num = 0, ff_attacked_bleed_num = 0,
				ff_money_in = 0, ff_money_out = 0, ff_money_reset_time = 0, ff_money_in_total = 0, ff_money_out_total = 0, ff_points = 0
			WHERE ff_id = $this->id");
		$this->data = self::load_data_result("ff_id = $this->id")->fetch();
		
		$this->reset_date_reg();
	}
	
	/**
	 * Nullstill forumet
	 */
	public function reset_forum()
	{
		// slett alle forumtrådene som ikke er slettet
		\Kofradia\DB::get()->exec("UPDATE forum_topics SET ft_deleted = ".time()." WHERE ft_fse_id = ".$this->get_fse_id()." AND ft_deleted = 0");
	}
	
	/**
	 * Nullstill bankstatistikk
	 */
	public function reset_bank_stats()
	{
		// nullstill statistikken
		\Kofradia\DB::get()->exec("
			UPDATE ff
			SET
				ff_money_in_total = ff_money_in_total + ff_money_in,
				ff_money_out_total = ff_money_out_total + ff_money_out,
				ff_money_in = 0,
				ff_money_out = 0,
				ff_money_reset_time = ".time()."
			WHERE ff_id = {$this->id}");
	}
	
	/**
	 * Nullstill medlemmer (arkiverer alle medlemmene fra databasen)
	 */
	public function reset_members()
	{
		// eksporter
		database_archive::handle_ff_members($this->id);
		
		$this->members_reset();
		$this->members_check_crew();
	}
	
	/**
	 * Nullstill tidspunktet for stiftelse
	 */
	public function reset_date_reg($set_to_now = null)
	{
		$time = $set_to_now ? time() : 0;
		putlog("LOG", "STIFTELSESTIDSPUNKT FOR FF: Endret for FF #$this->id ({$this->data['ff_name']}) fra ".($this->data['ff_date_reg'] ? ess::$b->date->get($this->data['ff_date_reg'])->format() : "ingenting")." til ".ess::$b->date->get($time)->format());
		
		$this->data['ff_date_reg'] = $time;
		\Kofradia\DB::get()->exec("UPDATE ff SET ff_date_reg = $time WHERE ff_id = $this->id");
	}
	
	/**
	 * FF dør ut
	 * @param player $up_attack spilleren som angrep eier som førte til at FF ble lagt ned
	 */
	public function dies(player $up_attack = null)
	{
		global $_game, $__server;
		
		// allerede inaktiv?
		if (!$this->active) throw new HSException("FF er allerede lagt ned.");
		
		// legg til logg hos medlemmer og inviterte
		foreach ($this->members['members'] as $member)
		{
		    // Sjekk om spiller sitter i familie bomberom
            $result = \Kofradia\DB::get()->query("SELECT up_brom_ff_id, up_brom_expire FROM users_players WHERE up_id = ".$member->id);
            $row = $result->fetch();

            if ($row['up_brom_ff_id'] == $this->id && $row['up_brom_expire'] != 0) {

                // Fjern spillerne fra bomberom
                \Kofradia\DB::get()->exec("UPDATE users_players SET up_brom_expire = 0 WHERE up_id = ".$member->id);
            }


			// brukerlogg
			player::add_log_static("ff_dead", $this->refstring.":".urlencode($this->data['ff_name']), $this->id, $member->id);
		}
		foreach ($this->members['invited'] as $member)
		{
			// Trekk tilbake invitasjon
			$member->invite_pullback(true);

			// brukerlogg
			player::add_log_static("ff_dead_invited", $this->refstring.":".urlencode($this->data['ff_name']), $this->id, $member->id);
		}
		
		// fjern fra menyen hos medlemmer
		$this->remove_menu_entries();
		
		// logg
		putlog("CREWCHAN", ucfirst($this->refstring)." %u{$this->data['ff_name']}%u har blitt oppløst. {$__server['path']}/ff/?ff_id={$this->id}");
		putlog("INFO", ucfirst($this->refstring)." %u{$this->data['ff_name']}%u har blitt oppløst.");
		
		// live-feed
		livefeed::add_row(ucfirst($this->refstring)." ".htmlspecialchars($this->data['ff_name'])." ble oppløst.");
		
		// broderskap?
		if ($this->type['type'] == "familie")
		{
			// sett FF til inaktiv
			$time = time();
			$this->active = false;
			$this->data['ff_inactive'] = 1;
			$this->data['ff_inactive_time'] = $time;
			\Kofradia\DB::get()->exec("UPDATE ff SET ff_inactive = 1, ff_inactive_time = $time WHERE ff_id = $this->id");
			
			// legg ut konkurranse om nytt broderskap
			$others = false;
			if ($this->competition)
			{
				// er vi det eneste broderskapet igjen i konkurransen?
				$result = \Kofradia\DB::get()->query("SELECT COUNT(ff_id) FROM ff WHERE ff_fff_id = {$this->data['fff_id']} AND ff_inactive = 0 AND ff_id != $this->id");
				$others = $result->fetchColumn(0) > 0;
			}
			if (!$this->data['ff_is_crew'] && !$this->params->get("die_no_new") && !$others)
			{
				//self::create_competition();
				
				// sett params slik at det ikke blir lagt ut ny konkurranse dersom broderskapet blir aktivert og så deaktivert igjen
				$this->params->update("die_no_new", 1, true);
			}
			
			// hendelse
			$this->add_log("dissolve", null);
		}
		
		// firma
		else
		{
			$name_old = $this->data['ff_name'];
			
			// nullstill firmaet
			$this->reset((bool) $up_attack);
			$this->reset_members();
			
			// hendelse
			$this->add_log("dissolve", null);
			
			if ($up_attack)
			{
				// angriper overtar firmaet
				$this->player_set_priority($up_attack->id, 1, null, true);
				$this->reset_date_reg(true);
				
				// gi hendelse til angriper
				$up_attack->add_log("ff_takeover", $this->id.":".urlencode($name_old).":".urlencode($this->data['ff_name']).":".urlencode($this->type['refobj']).":".urlencode($this->type['priority'][1]), 0);
				
				// live-feed
				livefeed::add_row('<user id="'.$up_attack->id.'" /> tok over driften av '.$this->type['refobj'].' <a href="'.ess::$s['rpath'].'/ff/?ff_id='.$this->id.'">'.htmlspecialchars($this->data['ff_name']).'</a>'.($name_old != $this->data['ff_name'] ? ' (tidligere '.htmlspecialchars($name_old).')' : '').'.');
			}
			
			else
			{
				// opprett ny auksjon
				auksjon::create_auksjon_ff($this);
			}
		}
	}
	
	/**
	 * Fjern fra menyen til medlemmer
	 */
	public function remove_menu_entries()
	{
		// fjern fra menyen hos medlemmer
		if (count($this->members['members']) == 0) return;
		
		$up_id = array_keys($this->members['members']);
		\Kofradia\DB::get()->beginTransaction();
		$result = \Kofradia\DB::get()->query("SELECT u_id, u_params FROM users, users_players WHERE up_id IN (".implode(",", $up_id).") AND up_u_id = u_id FOR UPDATE");
		
		while ($user = $result->fetch())
		{
			$params = new params($user['u_params']);
			$container = new container($params->get("forums"));
			
			foreach ($container->items as $key => $row)
			{
				if ($row[0] != "ff") continue;
				if ($row[1] != $this->id) continue;
				
				unset($container->items[$key]);
				
				// fjerne hele container?
				if (count($container->items) == 0)
				{
					$params->remove("forums");
				}
				else
				{
					$params->update("forums", $container->build());
				}
				
				// lagre nye params
				\Kofradia\DB::get()->exec("UPDATE users SET u_params = ".\Kofradia\DB::quote($params->build())." WHERE u_id = {$user['u_id']}");
				break;
			}
		}
		
		// lagre endringer
		\Kofradia\DB::get()->commit();
	}
	
	/**
	 * Legg ut konkurranse om nytt broderskap
	 */
	public static function create_competition()
	{
		global $__server;
		
		// sett opp tidspunkter
		$time = ess::$b->date->get();
		$created = $time->format("U");
		
		// hvis klokka nå er over 18:00 velg neste dag
		if ($time->format("H") >= 18) $time->modify("+1 day");
		
		// velg et tilfeldig tidspunkt mellom 18:00 og 21:00
		$time->setTime(rand(18, 20), rand(0, 59), 0);
		
		$start = $time->format("U");
		$time->modify("+5 days");
		$expire = $time->format("U");
		
		// legg til
		\Kofradia\DB::get()->exec("INSERT INTO ff_free SET fff_time_created = $created, fff_time_start = $start, fff_time_expire = $expire, fff_required_points = 105000");
		putlog("CREWCHAN", "Ny konkurranse om broderskap planlagt - {$__server['path']}/ff/?fff_id=".\Kofradia\DB::get()->lastInsertId());
		
		// sørg for at scheduler settes til første konkurranse som avsluttes
		\Kofradia\DB::get()->exec("UPDATE scheduler SET s_next = IF(s_active = 0, $expire, LEAST(s_next, $expire)), s_active = 1 WHERE s_name = 'familier_free'");
		
		// live-feed
		livefeed::add_row('Ny konkurranse for broderskap er planlagt og starter '.ess::$b->date->get($start)->format().'. Broderskapet opprettes via <a href="'.ess::$s['relative_path'].'/bydeler">bydeler</a> når konkurransen har startet.');
	}
	
	/**
	 * Analyser ff_up_limit
	 */
	public function get_limits()
	{
		$ret = array(
			0 => 0
		);
		
		foreach (array_keys($this->type['priority']) as $id)
		{
			// standard er at det ikke kan være noen i posisjonen
			$ret[$id] = -1;
		}
		
		$info = explode(";", $this->data['ff_up_limit']);
		foreach ($info as $row)
		{
			$row = explode(":", $row);
			if (isset($row[1]) && is_numeric($row[0]) && is_numeric($row[1]) && ($row[0] == 0 || isset($this->type['priority'][$row[0]])))
			{
				$ret[$row[0]] = $row[1];
			}
		}
		
		return $ret;
	}
	
	/**
	 * Hent krav for rank for en posisjon
	 */
	public function get_priority_rank($priority)
	{
		if (!isset($this->type['priority_rank'][$priority]))
			$priority = 0;
		
		return $this->type['priority_rank'][$priority];
	}
	
	/**
	 * Finn ut begrensninger med tanke på medlemmer
	 * @param array $members liste over medlemmer som skal flyttes
	 */
	public function check_limits($members = null)
	{
		// hent begrensninger
		$limits = $this->get_limits();
		
		// skal vi flytte en bruker? ta med en ekstra i ledig plass
		$extra = 0;
		$priorities = array();
		if ($members)
		{
			$extra = count($members);
			
			// sett opp liste over antall spillere i hver prioritering vi flytter
			foreach ($members as $member)
			{
				if (!isset($priorities[$member->data['ffm_priority']])) $priorities[$member->data['ffm_priority']] = 0;
				$priorities[$member->data['ffm_priority']]++;
			}
		}
		
		// finn ut hvor mange ledige plasser vi har totalt
		$total_free = max(0, $limits[0]-count($this->members['members'])-count($this->members['invited'])+$extra);
		
		// sett opp oversikt per prosisjon
		$data = array(
			"max" => $limits[0],
			"total_free" => $total_free,
			"priorities" => array()
		);
		foreach ($limits as $id => $max)
		{
			if ($id == 0) continue;
			
			$num_members = isset($this->members['members_priority'][$id]) ? count($this->members['members_priority'][$id]) : 0;
			if (isset($priorities[$id])) $num_members -= $priorities[$id];
			$num_invited = isset($this->members['invited_priority'][$id]) ? count($this->members['invited_priority'][$id]) : 0;
			$min_rank = $this->get_priority_rank($id);
			
			$data['priorities'][$id] = array(
				"priority" => $id,
				"members" => $num_members,
				"invited" => $num_invited,
				"suggested" => isset($this->members['suggested_priority'][$id]) ? count($this->members['suggested_priority'][$id]) : 0,
				"max" => $max,
				"free" => $max == -1 ? 0 : ($max == 0 ? $total_free : min($total_free, max(0, $max-$num_members-$num_invited))),
				"min_rank" => $min_rank
			);
		}
		
		return $data;
	}
	
	/**
	 * Hent informasjon om medlemsbegrensning
	 * @return array(active, extra, extra_max, min, max)
	 */
	public function members_limit_max_info()
	{
		// gjelder kun broderskap
		if ($this->type['type'] != "familie") throw new HSException("Dette gjelder kun broderskap.");
		
		$limits = $this->get_limits();
		
		// mangler maksimal verdi i løpet av perioden?
		if (!$this->data['ff_up_limit_max'] && $limits[0] > 0)
		{
			$this->data['ff_up_limit_max'] = $limits[0];
			\Kofradia\DB::get()->exec("UPDATE ff SET ff_up_limit_max = {$this->data['ff_up_limit_max']} WHERE ff_id = $this->id");
		}
		
		return array(
			"active" => $limits[0],
			"extra" => max(0, $limits[0] - self::MEMBERS_LIMIT_TOTAL_MIN),
			"extra_max" => max(0, $this->data['ff_up_limit_max'] - self::MEMBERS_LIMIT_TOTAL_MIN),
			"min" => self::MEMBERS_LIMIT_TOTAL_MIN,
			"max" => $this->competition ? self::MEMBERS_LIMIT_TOTAL_MAX_COMP : self::MEMBERS_LIMIT_TOTAL_MAX
		);
	}
	
	/**
	 * Øk medlemsbegrensningen med 1
	 */
	public function members_limit_increase()
	{
		$max = $this->members_limit_max_info();
		
		// har ingen maks?
		if ($max['active'] == 0) throw new HSException("Det finnes ingen begrensnings fra før.");
		
		// kan ikke økes mer?
		if ($max['active'] == $max['max']) throw new HSException("Medlemsantallet kan ikke økes mer.");
		
		// sett opp ny begrensning
		$new_max = $max['active'] + 1;
		$new_limit = $this->members_limit_build($new_max);
		
		// forsøk å oppdater
		$a = \Kofradia\DB::get()->exec("
			UPDATE ff
			SET ff_up_limit = ".\Kofradia\DB::quote($new_limit)."
			WHERE ff_id = $this->id AND ff_up_limit = ".\Kofradia\DB::quote($this->data['ff_up_limit']));
		
		// endret seg?
		if ($a == 0)
		{
			ess::$b->page->add_message("Medlemsbegrensningen har endret seg siden du viste siden. Prøv på nytt om du fremdeles ønsker.", "error");
			return false;
		}
		
		// forsøk å trekk fra pengene
		if ($this->bank(ff::BANK_BETALING, ff::MEMBERS_LIMIT_INCREASE_COST, 'Økte medlemsbegrensningen til '.$new_max.'.'))
		{
			// lagre maks
			$this->data['ff_up_limit'] = $new_limit;
			$this->data['ff_up_limit_max'] = max($this->data['ff_up_limit_max'], $new_max);
			\Kofradia\DB::get()->exec("
				UPDATE ff
				SET ff_up_limit_max = $new_max
				WHERE ff_id = $this->id AND ff_up_limit_max < $new_max");
			
			ess::$b->page->add_message("Maks antall medlemmer for {$this->type['refobj']} ble økt til ".($new_max).". ".game::format_cash(ff::MEMBERS_LIMIT_INCREASE_COST)." ble trukket fra bankkontoen.");
			return true;
		}
		
		// sett ned begrensningen igjen
		\Kofradia\DB::get()->exec("
			UPDATE ff
			SET ff_up_limit = ".\Kofradia\DB::quote($this->data['ff_up_limit'])."
			WHERE ff_id = $this->id AND ff_up_limit = ".\Kofradia\DB::quote($new_limit));
		
		ess::$b->page->add_message("Det er ikke nok penger i banken for {$this->type['refobj']} til å øke medlemsbegrensningen.", "error");
		return false;
	}
	
	/**
	 * Senk medlemsbegrensningen med 1
	 */
	public function members_limit_decrease()
	{
		$max = $this->members_limit_max_info();
		
		// har ingen maks?
		if ($max['active'] == 0) throw new HSException("Det finnes ingen begrensnings fra før.");
		
		// kan ikke senkes mer?
		if ($max['active'] == $max['min']) throw new HSException("Medlemsantallet kan ikke senkes mer.");
		
		// sett opp ny begrensning
		$new_max = $max['active'] - 1;
		$new_limit = $this->members_limit_build($new_max);
		
		// forsøk å oppdater
		$a = \Kofradia\DB::get()->exec("
			UPDATE ff
			SET ff_up_limit = ".\Kofradia\DB::quote($new_limit)."
			WHERE ff_id = $this->id AND ff_up_limit = ".\Kofradia\DB::quote($this->data['ff_up_limit']));
		
		// endret seg?
		if ($a == 0)
		{
			ess::$b->page->add_message("Medlemsbegrensningen har endret seg siden du viste siden. Prøv på nytt om du fremdeles ønsker.", "error");
			return false;
		}
		
		ess::$b->page->add_message("Maks antall medlemmer for {$this->type['refobj']} ble senket til ".($new_max).".");
		return true;
	}
	
	/**
	 * Sett sammen ny medlemsbegrensning
	 */
	protected function members_limit_build($new_max)
	{
		$data = array();
		
		$info = explode(";", $this->data['ff_up_limit']);
		foreach ($info as $row)
		{
			$row = explode(":", $row);
			
			// erstatte denne?
			if ($row[0] == "0") $row[1] = $new_max;
			
			$data[] = implode(":", $row);
		}
		
		return implode(";", $data);
	}
	
	/**
	 * Endre navnet
	 * @param string $name nytt navn
	 * @param integer $request_up_id hvem som ba om forespørselen
	 * @param bool $mod moderator/spill-handling?
	 */
	public function change_name($name, $request_up_id = null, $mod = null)
	{
		// samme navn?
		if ($name == $this->data['ff_name']) return false;
		$old_name = $this->data['ff_name'];
		
		// sett nytt navn
		\Kofradia\DB::get()->exec("UPDATE ff SET ff_name = ".\Kofradia\DB::quote($name)." WHERE ff_id = $this->id");
		$this->data['ff_name'] = $name;
		
		// oppdater params
		if (!$mod) $this->params->update("name_changed", time(), true);
		
		// lagre logg
		$up_id = !$mod && login::$logged_in ? login::$user->player->id : 0;
		$request_up_id = (int) $request_up_id;
		if ($request_up_id == 0) $request_up_id = $up_id;
		$this->add_log("name", urlencode($old_name).':'.urlencode($name).':'.$request_up_id.':'.$up_id);
		
		// oppdater navnet hos alle medlemmer
		if (count($this->members['members']) > 0)
		{
			$up_id = array_keys($this->members['members']);
			\Kofradia\DB::get()->beginTransaction();
			$result = \Kofradia\DB::get()->query("SELECT u_id, u_params FROM users, users_players WHERE up_id IN (".implode(",", $up_id).") AND up_u_id = u_id FOR UPDATE");
			
			while ($user = $result->fetch())
			{
				$params = new params($user['u_params']);
				$container = new container($params->get("forums"));
				
				foreach ($container->items as $key => $row)
				{
					if ($row[0] != "ff") continue;
					if ($row[1] != $this->id) continue;
					
					// sjekk om lenken er oppdatert
					if ($row[2] != $this->data['ff_name'])
					{
						$container->items[$key][2] = $this->data['ff_name'];
						$params->update("forums", $container->build());
						
						// lagre nye params
						\Kofradia\DB::get()->exec("UPDATE users SET u_params = ".\Kofradia\DB::quote($params->build())." WHERE u_id = {$user['u_id']}");
						break;
					}
				}
			}
			
			// lagre endringer
			\Kofradia\DB::get()->commit();
		}
		
		// logg
		putlog("INFO", ucfirst($this->type['refobj'])." %u{$old_name}%u har endret navn til %u{$name}%u ".ess::$s['path']."/ff/?ff_id=$this->id");
		
		// live-feed
		livefeed::add_row(ucfirst($this->type['refobj']).' '.htmlspecialchars($old_name).' har endret navn til <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$this->id.'">'.htmlspecialchars($name).'</a>.');
		
		return true;
	}
	
	/**
	 * Hent hvor mye rank medlemmene har fått under konkurransemodus
	 */
	public function competition_rank_points()
	{
		global $_game;
		
		// hent samlet rank
		$total_rank = 0;
		
		foreach ($this->members['members'] as $member)
		{
			// crewmedlem?
			if ($member->data['up_access_level'] >= $_game['access_noplay']) continue;
			
			if ($member->data['ffm_pay_points'] === null)
			{
				// sørg for at brukeren har antall poeng ved join lagret
				\Kofradia\DB::get()->exec("UPDATE ff_members, users_players SET ffm_pay_points = up_points_rel WHERE ffm_ff_id = {$this->id} AND ffm_up_id = {$member->id} AND ffm_up_id = up_id");
				$this->data['ffm_pay_points'] = $this->data['up_points_rel'];
			}
			$total_rank += $member->data['up_points_rel']-$member->data['ffm_pay_points'];
		}
		
		
		return $total_rank;
	}
	
	/**
	 * Vant broderskapkonkurransen
	 */
	public function competition_won()
	{
		global $__server;
		
		// melding om at broderskapet vant
		putlog("INFO", ucfirst($this->refstring)." %u{$this->data['ff_name']}%u vant broderskapkonkurransen, og har nå muligheten til å etablere seg som et virkelig broderskap. {$__server['path']}/ff/?ff_id={$this->id}");
		
		// live-feed
		livefeed::add_row(ucfirst($this->refstring).' <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$this->id.'">'.htmlspecialchars($this->data['ff_name']).'</a> vant broderskapkonkurransen.');
		
		// sett neste broderskapkostnad
		$time = ess::$b->date->get();
		if ($time->format("H") > 12) $time->modify("+10 days");
		else $time->modify("+11 days");
		$time->setTime(12, 0, 0);
		$time = $time->format("U");
		\Kofradia\DB::get()->exec("UPDATE ff SET ff_pay_next = $time, ff_pay_status = 0, ff_pay_points = 0 WHERE ff_id = $this->id");
		$this->data['ff_pay_next'] = $time;
		$this->data['ff_pay_status'] = 0;
		$this->data['ff_pay_points'] = 0;
		
		$this->data['fff_active'] = 0;
		
		// send melding til boss og underboss
		for ($i = 1; $i <= 2; $i++)
		{
			if (isset($this->members['members_priority'][$i]))
			{
				foreach ($this->members['members_priority'][$i] as $member)
				{
					$member->up->send_message($member->id, "Broderskapkonkurranse vunnet: {$this->data['ff_name']}", ucfirst($this->refstring)." [iurl=/ff/?ff_id={$this->id}]{$this->data['ff_name']}[/iurl] vant broderskapkonkurransen.\n\n".ucfirst($this->refstring)." må nå velge bygning innen 24 timer for at broderskapet ikke skal dø ut.", true);
				}
			}
		}
		
		// utfør trigger hos spillere
		foreach ($this->members['members'] as $member){
			$member->up->trigger("ff_won_member", array(
					"ff" => $this,
					"member" => $member
			));
		}
	}
	
	/**
	 * Hent ut informasjon om innbetaling
	 */
	public function pay_info()
	{
		if ($this->data['ff_is_crew'] || $this->type['type'] != "familie") return false;
		
		// er vi i konkurranse?
		if ($this->competition) return false;
		
		// mangler vi tidspunkt?
		if (!$this->data['ff_pay_next'])
		{
			// sett opp tidspunkt om 10 dager
			$date = ess::$b->date->get();
			$date->modify("+10 days");
			$date->setTime(12, 0, 0);
			
			$this->data['ff_pay_next'] = $date->format("U");
			\Kofradia\DB::get()->exec("UPDATE ff SET ff_pay_next = {$this->data['ff_pay_next']} WHERE ff_id = $this->id");
		}
		
		// finn ut hvor mye rank vi har samlet opp
		$rank = $this->data['ff_pay_points'];
		foreach ($this->members['members'] as $member)
		{
			// crewmedlem?
			if ($member->data['up_access_level'] >= ess::$g['access_noplay']) continue;
			
			if ($member->data['ffm_pay_points'] === null) continue;
			$rank += $member->data['up_points_rel'] - $member->data['ffm_pay_points'];
		}
		
		// sett opp prisen
		$limits = $this->members_limit_max_info();
		$price_max = self::PAY_COST_DEFAULT + $limits['extra_max'] * self::PAY_COST_INCREASE_FFM;
		$price = max(self::PAY_COST_MIN, $price_max - floor($rank/self::PAY_COST_RANK)*1000000);
		
		// har vi gått over tidspunktet?
		if ($this->data['ff_pay_status'] == 1)
		{
			// øk prisen med 50 %
			$price *= 1.5;
		}
		
		return array(
			"next" => $this->data['ff_pay_next'],
			"rank" => $rank,
			"price" => $price,
			"price_max" => $price_max,
			"members_limit" => $limits['extra_max'],
			"in_time" => $this->data['ff_pay_status'] == 0,
		);
	}
	
	/**
	 * Utfør manuell innbetaling
	 * Trekker pengene fra det brukeren har på hånda
	 */
	public function pay_action()
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		
		// hent info
		$pay_info = $this->pay_info();
		
		// har ikke noe info?
		if (!$pay_info)
		{
			throw new HSException("Noe gikk galt.");
		}
		
		// kan vi ikke betale manuelt nå
		if ($pay_info['in_time'])
		{
			throw new HSException("Kan ikke betale manuelt før første frist har gått ut.");
		}
		
		// forsøk å trekk fra pengene
		$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - {$pay_info['price']} WHERE up_id = ".login::$user->player->id);
		if ($a > 0)
		{
			// betalingen var vellykket
			$this->pay_reset();
			
			return $pay_info['price'];
		}
		
		return false;
	}
	
	/**
	 * Utfør automatisk innbetaling fra banken
	 */
	public function pay_scheduler()
	{
		// hent info
		$pay_info = $this->pay_info();
		
		// kan vi ikke betale automatisk nå?
		if (!$pay_info['in_time'])
		{
			return false;
		}
		
		// forsøk å trekk fra pengene fra kontoen
		if ($this->bank(self::BANK_BETALING, $pay_info['price'], "Ukentlig kostnad for {$this->type['typename']}.", true))
		{
			// nullstill info
			$this->pay_reset();
			return true;
		}
		
		// sett opp tid til neste manuelle innbetaling
		$next = ess::$b->date->get($this->data['ff_pay_next']);
		$next->modify("+1 day");
		$next->setTime(12, 0, 0);
		$next = $next->format("U");
		\Kofradia\DB::get()->exec("UPDATE ff SET ff_pay_next = $next, ff_pay_status = 1 WHERE ff_id = $this->id");
		$this->data['ff_pay_next'] = $next;
		
		return false;
	}
	
	/**
	 * Nullstill betalingsinfo
	 */
	public function pay_reset()
	{
		$max = $this->members_limit_max_info();
		
		// lagre informasjon
		$info = $this->pay_info();
		$date = \Kofradia\DB::quote(ess::$b->date->get($this->data['ff_pay_next'])->format("Y-m-d"));
		\Kofradia\DB::get()->exec("
			INSERT INTO ff_stats_pay
			SET
				ffsp_ff_id = $this->id,
				ffsp_date = $date,
				ffsp_manual = ".($this->data['ff_pay_status'] == 0 ? 0 : 1).",
				ffsp_points = {$info['rank']},
				ffsp_up_limit = ".($max['min'] + $max['extra_max']).",
				ffsp_cost = {$info['price']}");
		$ffsp_id = \Kofradia\DB::get()->lastInsertId();
		
		// lagre informasjon om medlemmene
		$list = array();
		foreach ($this->members['members'] as $member)
		{
			// ikke crewmedlem
			$p = 0;
			if ($member->data['up_access_level'] < ess::$g['access_noplay'])
				$p = $member->data['ffm_pay_points'] !== null ? $member->data['up_points_rel'] - $member->data['ffm_pay_points'] : 0;
			
			$list[] = "($ffsp_id, {$member->data['ffm_up_id']}, {$member->data['ffm_priority']}, {$member->data['ffm_parent_up_id']}, $p)";
		}
		if (count($list) > 0)
		{
			\Kofradia\DB::get()->exec("
				INSERT INTO ff_stats_pay_members (ffspm_ffsp_id, ffspm_up_id, ffspm_priority, ffspm_parent_up_id, ffspm_points)
				VALUES ".implode(", ", $list));
		}
		
		// nullstill poeng for FF og sett neste tidspunkt
		// neste tidspunkt er 12:00 tidspunkt 10 dager frem i tid (9 dager hvis manuell innbetaling)
		$next = ess::$b->date->get($this->data['ff_pay_next']);
		$next->modify("+".($this->data['ff_pay_status'] == 0 ? 10 : 9)." day");
		$next->setTime(12, 0, 0);
		$next = $next->format("U");
		
		\Kofradia\DB::get()->exec("UPDATE ff SET ff_pay_points = 0, ff_pay_next = $next, ff_pay_status = 0, ff_up_limit_max = {$max['active']} WHERE ff_id = $this->id");
		$this->data['ff_pay_points'] = 0;
		$this->data['ff_pay_next'] = $next;
		$this->data['ff_pay_status'] = 0;
		$this->data['ff_up_limit_max'] = $max['active'];
		
		// nullstill telleren for spillerne
		\Kofradia\DB::get()->exec("UPDATE ff_members, users_players SET ffm_pay_points = up_points_rel WHERE ffm_ff_id = $this->id AND ffm_up_id = up_id");
		
		// finn ut hvor mye rank vi har samlet opp
		foreach ($this->members['members'] as $member)
		{
			$member->data['ffm_pay_points'] = $member->data['up_points_rel'];
		}
	}
	
	/**
	 * Hent salgsstatus
	 */
	public function sell_status($lock = false, $unlock = true)
	{
		// låse?
		if ($lock) $this->params->lock();
		
		// aktivt?
		$info = $this->params->get("sell");
		if (!isset($info))
		{
			if ($lock && $unlock) $this->params->commit();
			return false;
		}
		$info = explode(":", $info); // 0=up_id, 1=init_up_id, 2=time, 3=fee, 4=amount
		
		// sjekk at personen fremdeles er medlem og underboss
		// sjekk at boss fremdeles er boss
		if (!isset($this->members['members_priority'][2][$info[0]]) || !isset($this->members['members_priority'][1][$info[1]]))
		{
			$this->params->remove("sell", !$lock || $unlock);
			return false;
		}
		
		return array(
			"up_id" => $info[0],
			"init_up_id" => $info[1],
			"time" => $info[2],
			"fee" => $info[3],
			"amount" => $info[4]
		);
	}
	
	/**
	 * Start salg
	 */
	public function sell_init($up_id, $amount)
	{
		global $_game;
		
		$up_id = (int) $up_id;
		$amount = game::intval($amount);
		
		// negativt beløp?
		if ($amount < 0)
		{
			return "negative_amount";
		}
		
		// har ikke tilgang?
		if (!$this->access(1))
		{
			throw new HSException("Brukeren som starter salget må være boss.");
		}
		
		// hent nåværende status
		$status = $this->sell_status(true, false);
		if ($status !== false)
		{
			$this->params->commit();
			return false;
		}
		
		// sjekk at brukeren er underboss
		if (!isset($this->members['members_priority'][2][$up_id]))
		{
			$this->params->commit();
			return false;
		}
		
		// sett opp rank informasjon for underboss
		$player = $this->members['members'][$up_id];
		$rank_info = game::rank_info($player->data['up_points_rel'], $player->data['upr_rank_pos'], $player->data['up_access_level']);
		if ($rank_info['number'] < $this->type['priority_rank'][1])
		{
			$this->params->commit();
			return "player_rank";
		}
		
		// legg til oppføringen
		$info = array($up_id, $this->uinfo->id, time(), self::SELL_COST, $amount);
		$this->params->update("sell", implode(":", $info));
		
		// legg til logg hos underboss
		player::add_log_static("ff_diverse", '<user id="'.$this->uinfo->id.'" /> har startet salg av '.$this->refstring.' <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$this->id.'">'.htmlspecialchars($this->data['ff_name']).'</a>. <a href="'.ess::$s['relative_path'].'/ff/panel?ff_id='.$this->id.'&amp;a=sell">Godta/avslå &raquo;</a>', $this->id, $up_id);
		
		// logg i FF: {TYPE=init}:BOSS:UBOSS:FEE:AMOUNT
		$this->add_log("sell", "init:{$this->uinfo->id}:$up_id:".self::SELL_COST.":$amount");
		
		// avslutt transaction
		$this->params->commit();
		
		return true;
	}
	
	/**
	 * Trekk tilbake salg
	 */
	public function sell_abort()
	{
		global $_game;
		
		// kun boss kan trekke tilbake
		if (!$this->access(1))
		{
			throw new HSException("Kun boss kan trekke tilbake salg.");
		}
		
		// hent status
		$status = $this->sell_status(true);
		if (!$status)
		{
			return false;
		}
		
		// trekk tilbake
		$this->params->remove("sell");
		
		// legg til logg hos mottakeren
		player::add_log_static("ff_diverse", '<user id="'.$this->uinfo->id.'" /> har trukket tilbake salget av '.$this->refstring.' <a href="ff/?ff_id='.$this->id.'">'.htmlspecialchars($this->data['ff_name']).'</a>.', $this->id, $status['up_id']);
		
		// logg i FF: {TYPE=abort}:BOSS:UBOSS:FEE:AMOUNT
		$this->add_log("sell", "abort:{$status['init_up_id']}:{$status['up_id']}:{$status['fee']}:{$status['amount']}");
		
		// avslutt transaction
		$this->params->commit();
		
		return true;
	}
	
	/**
	 * Godta salg
	 */
	public function sell_approve()
	{
		global $_game;
		
		// hent status
		$status = $this->sell_status(true);
		if (!$status)
		{
			return false;
		}
		
		// sørg for at dette er brukeren som skulle selges til
		if (!$this->uinfo || $status['up_id'] != $this->uinfo->id)
		{
			$this->params->commit();
			throw new HSException("Kun brukeren som har mottatt salget kan behandle det.");
		}
			
		// forsøk å trekk fra pengene fra brukeren
		if ($status['amount'] > 0)
		{
			$a = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - {$status['amount']}, up_bank_sent = up_bank_sent + {$status['amount']}, up_bank_num_sent = up_bank_num_sent + 1 WHERE up_id = {$status['up_id']} AND up_cash >= {$status['amount']}");
			if ($a == 0)
			{
				// har ikke råd
				$this->params->commit();
				return 'player_cash';
			}
		}
		
		// forsøk å trekk fra pengene fra banken
		if (!$this->bank(self::BANK_BETALING, $status['fee'], "Gebyr for salg av {$this->refstring}.", true))
		{
			// FF dekker ikke gebyret
			if ($status['amount'] > 0)
			{
				// gi tilbake pengene
				\Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash + {$status['amount']}, up_bank_sent = up_bank_sent - {$status['amount']}, up_bank_num_sent = up_bank_num_sent - 1 WHERE up_id = {$status['up_id']}");
			}
			
			$this->params->commit();
			return 'ff_cash';
		}
		
		// banklogg
		if ($status['amount'] > 0)
		{
			\Kofradia\DB::get()->exec("INSERT INTO bank_log SET bl_sender_up_id = {$status['up_id']}, bl_receiver_up_id = {$status['init_up_id']}, amount = {$status['amount']}, time = ".time());
		}
		
		// fjern fra params
		$this->params->remove("sell");
		
		// sett selger som underboss
		$init_u = $this->members['members'][$status['init_up_id']];
		$init_u->change_priority(2, NULL, true);
		
		// sett kjøper som boss
		$buy_u = $this->members['members'][$status['up_id']];
		$buy_u->change_priority(1, NULL, true);
		
		// gi pengene til selger
		if ($status['amount'] > 0)
		{
			\Kofradia\DB::get()->exec("UPDATE users_players SET up_bank = up_bank + {$status['amount']}, up_bank_received = up_bank_received + {$status['amount']}, up_bank_num_received = up_bank_num_received + 1 WHERE up_id = {$status['init_up_id']}");
		}
		
		// legg til logg hos selgeren
		player::add_log_static("ff_diverse", '<user id="'.$this->uinfo->id.'" /> godtok salget av '.$this->refstring.' <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$this->id.'">'.htmlspecialchars($this->data['ff_name']).'</a> for '.game::format_cash($status['amount']).' og er nå satt som '.$this->type['priority'][1].'. Du ble satt som '.$this->type['priority'][2].'.', $this->id, $status['init_up_id']);
		
		// logg i FF: {TYPE=approve}:BOSS:UBOSS:FEE:AMOUNT
		$this->add_log("sell", "approve:{$status['init_up_id']}:{$status['up_id']}:{$status['fee']}:{$status['amount']}");
		
		// live-feed
		livefeed::add_row(ucfirst($this->type['refobj']).' <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$this->id.'">'.htmlspecialchars($this->data['ff_name']).'</a> ble solgt til <user id="'.$buy_u->id.'" />.');
		
		// avslutt transaction
		$this->params->commit();
		
		return true;
	}
	
	/**
	 * Avslå salg
	 */
	public function sell_reject()
	{
		global $_game;
		
		// hent status
		$status = $this->sell_status(true);
		if (!$status)
		{
			return false;
		}
		
		// sørg for at dette er brukeren som skulle selges til
		if (!$this->uinfo || $status['up_id'] != $this->uinfo->id)
		{
			$this->params->commit();
			throw new HSException("Kun brukeren som har mottatt salget kan behandle det.");
		}
		
		// fjern fra params
		$this->params->remove("sell");
		
		// legg til logg hos selgeren
		player::add_log_static("ff_diverse", '<user id="'.$status['up_id'].'" /> avslo kjøpet av '.$this->refstring.' <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$this->id.'">'.htmlspecialchars($this->data['ff_name']).'</a> for '.game::format_cash($status['amount']).'.', $this->id, $status['init_up_id']);
		
		// logg i FF: {TYPE=reject}:BOSS:UBOSS:FEE:AMOUNT
		$this->add_log("sell", "reject:{$status['init_up_id']}:{$status['up_id']}:{$status['fee']}:{$status['amount']}");
		
		// avslutt transaction
		$this->params->commit();
		
		return true;
	}
	
	/**
	 * Hent rankinformasjon for FF
	 */
	public function get_rank_info()
	{
		global $_game;
		
		// hent oversikt over medlemmer og vis hvor mye hver av dem har ranket som teller for FF
		$players = array();
		$max = 0;
		$min = 0;
		$total = $this->data['ff_pay_points'];
		$total_neg = $total < 0 ? $total : 0;
		$total_pos = $total > 0 ? $total : 0;
		foreach ($this->members['members'] as $member)
		{
			// crewmedlem?
			if ($member->data['up_access_level'] >= $_game['access_noplay'])
			{
				$num = 0;
			}
			else
			{
				$num = $member->data['ffm_pay_points'] !== null ? $member->data['up_points_rel'] - $member->data['ffm_pay_points'] : 0;
			}
			
			$players[] = array(
				"member" => $member,
				"points" => $num
			);
			
			$total += $num;
			if ($num > 0)
			{
				$total_pos += $num;
			}
			else
			{
				$total_neg += abs($num);
			}
			
			$max = max($max, $num);
			$min = min($min, $num);
		}
		$max = max($max, $this->data['ff_pay_points']);
		$min = min($min, $this->data['ff_pay_points']);
		$max_diff = max($max, -$min);
		$total_diff = max($total_pos, $total_neg);
		
		// sett opp riktig prosentsatser
		foreach ($players as &$info)
		{
			$info['percent_bar'] = $info['points'] < 0
				? -$info['points'] / $max_diff * 100
				: ($info['points'] == 0 ? 0 : $info['points'] / $max_diff * 100);
			$info['percent_text'] = $info['points'] == 0 ? 0 : $info['points'] / $total_diff * 100;
		}
		
		$arr = array(
			"min" => $min,
			"max" => $max,
			"total" => $total,
			"total_neg" => $total_neg,
			"total_pos" => $total_pos,
			"players" => $players
		);
		if ($this->data['ff_pay_points'] != 0)
		{
			$arr['others'] = array(
				"points" => $this->data['ff_pay_points'],
				"percent_bar" => $this->data['ff_pay_points'] < 0
					? -$this->data['ff_pay_points'] / $max_diff * 100
					: ($this->data['ff_pay_points'] == 0 ? 0 : $this->data['ff_pay_points'] / $max_diff * 100),
				"percent_text" => $this->data['ff_pay_points'] == 0 ? 0 : $this->data['ff_pay_points'] / $total_diff * 100
			);
		}
		
		return $arr;
	}
	
	/**
	 * Hent total rank for FF
	 * @param bool $others skal vi ta med gamle medlemmer av FF
	 */
	public function get_rank_total($others = true)
	{
		global $_game;
		
		$total = 0;
		foreach ($this->members['members'] as $member)
		{
			// crewmedlem?
			if ($this->data['up_access_level'] >= $_game['access_noplay']) continue;
			
			$total += $member->data['ffm_pay_points'] !== null ? $member->data['up_points_rel'] - $member->data['ffm_pay_points'] : 0;
		}
		
		if ($others) $total += $this->data['ff_pay_points'];
		
		return $total;
	}
	
	/**
	 * Hent frisk informasjon om de andre FF i konkurransen
	 */
	public function get_competition_info()
	{
		global $_game;
		
		$stats = array();
		
		// hent informasjon om alle FF i konkurransen
		$result = \Kofradia\DB::get()->query("
			SELECT ff_id, ff_name, SUM(CONVERT(up_points_rel-ffm_pay_points, SIGNED)) AS total_points
			FROM ff
				LEFT JOIN ff_members ON ffm_ff_id = ff_id AND ffm_status = 1
				LEFT JOIN users_players ON ffm_up_id = up_id AND up_access_level < {$_game['access_noplay']}
			WHERE ff_fff_id = {$this->data['fff_id']} AND ff_inactive = 0
			GROUP BY ff_id");
		while ($row = $result->fetch())
		{
			$stats[] = $row;
		}
		
		// returner informasjonen
		return array(
			"time" => time(),
			"up_id" => login::$logged_in ? login::$user->player->id : null,
			"stats" => $stats
		);
		
	}
	
	/**
	 * Kjøp informasjon om de andre FF i konkurransen
	 */
	public function buy_competition_info()
	{
		global $__server;
		
		// er ikke i konkurranse?
		if (!$this->competition) throw new HSException("Er ikke i konkurranse.");
		
		// er ikke boss?
		if (!$this->access(1)) throw new HSException("Ikke tilgang.");
		
		// hent nåværende info
		$info = $this->params->get("competition_info");
		if ($info)
		{
			$info = unserialize($info);
			if ($info['time'] > time()-3600*6) // 6 timer
			{
				return 'wait';
			}
		}
		
		// hent data
		$data = $this->get_competition_info();
		
		// kun denne FF?
		if (count($data['stats']) <= 1)
		{
			return 'none';
		}
		
		// forsøk å trekk pengene fra banken
		if (!$this->bank(self::BANK_BETALING, self::COMPETITION_INFO_COST, "Kostnad for å hente informasjon om de andre {$this->refstring} i konkurransen."))
		{
			return 'ff_cash';
		}
		
		// logg
		putlog("CREWCHAN", ucfirst($this->refstring)." %u{$this->data['ff_name']}%u kjøpte rankinformasjon om de andre konkurransedeltakere. {$__server['path']}/ff/?ff_id={$this->id}");
		
		// lagre informasjonen
		$this->params->update("competition_info", serialize($data), true);
		
		return $data;
	}
	
	/**
	 * Marker en endring utført i forumet slik at den blir synlig i menyen
	 */
	public function forum_changed()
	{
		\Kofradia\DB::get()->beginTransaction();
		
		// hent og lås params for alle medlemmer
		$result = \Kofradia\DB::get()->query("
			SELECT u_id, u_params
			FROM ff_members
				JOIN users_players ON ffm_up_id = up_id
				JOIN users ON u_id = up_u_id
			WHERE ffm_ff_id = $this->id AND ffm_status = 1
			FOR UPDATE");
		
		while ($u = $result->fetch())
		{
			// hopp over den aktive brukeren
			if (login::$logged_in && $u['u_id'] == login::$user->id) continue;
			
			$params = new params($u['u_params']);
			if (!$params->exists("forums")) continue;
			
			$container = new container($params->get("forums"));
			foreach ($container->items as $key => $row)
			{
				if ($row[0] != "ff") continue;
				if ($row[1] != $this->id) continue;
				
				// oppdater
				if (!isset($row[4])) $row[4] = 0;
				$row[4]++;
				
				// lagre
				$container->items[$key] = $row;
				$params->update("forums", $container->build());
				\Kofradia\DB::get()->exec("UPDATE users SET u_params = ".\Kofradia\DB::quote($params->build())." WHERE u_id = {$u['u_id']}");
				
				continue;
			}
		}
		
		// fullfør transaksjon
		\Kofradia\DB::get()->commit();
	}
	
	/**
	 * Hent tilgangsnivå man må være for å få tilgang til banken
	 */
	public function get_bank_write_priority()
	{
		// har vi gitt medeier tilgang?
		if ($this->params->get("bank_pri2_write"))
		{
			return 2;
		}
		
		return 1;
	}
	
	/**
	 * Gi medeier tilgang til å endre banken
	 * @param bool $b
	 */
	public function bank_write_pri2_change($b)
	{
		if ($b)
		{
			if ($this->params->get("bank_pri2_write")) return false;
			$this->params->update("bank_pri2_write", true, true);
		}
		
		else
		{
			if (!$this->params->exists("bank_pri2_write")) return false;
			$this->params->remove("bank_pri2_write", true);
		}
		
		// ble endret
		return true;
	}
	
	/**
	 * Oppdater attack antall for FF
	 * @param bool $targeted true hvis FF ble angrepet, false hvis det var FF som angrep
	 * @param string $type
	 * @param array $ff_id_list
	 */
	public static function attack_update($targeted, $type, $ff_id_list)
	{
		$type = mb_strtolower($type);
		static $types = array("failed", "damaged", "killed", "bleed");
		
		// ugyldig type
		if (!in_array($type, $types)) throw new HSException("Ugyldig type.");
		
		$c = count($ff_id_list);
		if ($c == 0) return 0;
		$ff_ids = implode(",", $ff_id_list);
		
		$suf = $targeted ? "ed" : "";
		
		// oppdater ff
		\Kofradia\DB::get()->exec("
			UPDATE ff
			SET ff_attack{$suf}_{$type}_num = ff_attack{$suf}_{$type}_num + 1
			WHERE ff_id IN ($ff_ids) AND ff_inactive = 0");
		
		// oppdater stats_daily for ff
		$today = \Kofradia\DB::quote(ess::$b->date->get()->format("Y-m-d"));
		\Kofradia\DB::get()->exec("
			INSERT INTO ff_stats_daily (ffsd_ff_id, ffsd_date, ffsd_attack{$suf}_{$type}_num)
			SELECT ff_id, $today, 1
			FROM ff
			WHERE ff_id IN ($ff_ids) AND ff_inactive = 0
			ON DUPLICATE KEY UPDATE ffsd_attack{$suf}_{$type}_num = ffsd_attack{$suf}_{$type}_num + 1");
		
		return $c;
	}
	
	/**
	 * Forandre medlemskap i FF fra deaktivert til forlat
	 * benyttes når helsen går over 40 % igjen og blir der i over 12 timer
	 * eller når spilleren blir medlem av nytt FF
	 */
	public static function set_leave($up_id)
	{
		$up_id = (int) $up_id;
		\Kofradia\DB::get()->exec("
			UPDATE ff_members
			SET ffm_ff_name = NULL, ffm_status = ".ff_member::STATUS_KICKED.", ffm_date_part = ".time()."
			WHERE ffm_up_id = $up_id AND ffm_status = ".ff_member::STATUS_DEACTIVATED);
		
		\Kofradia\DB::get()->exec("
			UPDATE users_players
			SET up_health_ff_time = NULL
			WHERE up_id = $up_id");
	}
	
	/**
	 * Hent adresse til logo
	 */
	public function get_logo_path()
	{
		return self::get_logo_path_static($this->id, $this->data['ff_logo_path']);
	}
	
	/**
	 * Hent adresse til logo
	 */
	public static function get_logo_path_static($ff_id, $ff_logo_path)
	{
		// hent fra databasen hvis dette ikke er hovedservern
		if (!MAIN_SERVER) return ess::$s['rpath']."/ff/_logo?ff_id=$ff_id";
		
		// har ikke noe bilde?
		if (empty($ff_logo_path)) return STATIC_LINK."/firma/ff_default.png";
		
		if (mb_substr($ff_logo_path, 0, 2) == "l:") return PROFILE_IMAGES_HTTP . "/" . mb_substr($ff_logo_path, 2);
		return $ff_logo_path;
	}
	
	/**
	 * Hent antall ledige plasser i bomberommet
	 */
	public function get_bomberom_places()
	{
		$result = \Kofradia\DB::get()->query("
			SELECT COUNT(*)
			FROM users_players
			WHERE up_brom_ff_id = {$this->id} AND up_brom_expire > ".time()." AND up_access_level != 0");
		
		$ant_i_bomberommet = $result->fetchColumn(0);
		$ledige_plasser = max(0, $this->get_bomberom_capacity() - $ant_i_bomberommet);

		return array(
			"in_brom" => $ant_i_bomberommet,
			"free" => $ledige_plasser
		);
	}
	
	/**
	 * Finn ut kapasitet i bomberommet
	 */
	public function get_bomberom_capacity()
	{
		// bomberomfirma?
		if ($this->type['type'] == "bomberom") return $this->params->get("bomberom_kapasitet", 0);
		
		// familie
		if ($this->type['type'] == "familie")
		{
			$max = $this->members_limit_max_info();
			return max(1, floor($max['active']/2));
		}
		
		throw new HSException("Ugyldig bomberom.");
	}
	
	/**
	 * Finn ut kapasitet for kulelager
	 */
	public function get_bullets_capacity()
	{
		// sett opp liste over spillere
		$up_list = array();
		foreach ($this->members['members'] as $ffm)
		{
			$up_list[] = $ffm->data['ffm_up_id'];
		}
		
		if (count($up_list) == 0) return 0;
		
		// hent rank for spillerne
		$result = \Kofradia\DB::get()->query("
			SELECT up_points
			FROM users_players
			WHERE up_id IN (".implode(",", $up_list).") AND up_access_level < ".ess::$g['access_noplay']);
		
		// antall kuler er sum(rank_nummer * 3)
		$sum = 0;
		while ($row = $result->fetch())
		{
			$rank = game::rank_info($row['up_points']);
			$sum += $rank['number'] * 3;
		}
		
		return $sum;
	}
	
	/**
	 * Forsøk å ta ut kuler av broderskap
	 */
	public function bullets_out($num, player $up, player $real_up = null)
	{
		$num = (int) $num;
		
		$cap = $this->get_bullets_capacity();
		$bullets = $this->params->get("bullets", 0);

		$up->lock();

		$up_cap = $up->weapon->data['bullets'];
		$up_bullets = $up->data['up_weapon_bullets'];
		$up_bullets_a = $up->data['up_weapon_bullets_auksjon'];
		
		// har vi kulene?
		if ($num > $bullets)
		{
			\Kofradia\DB::get()->commit();
			return "missing";
		}
		
		// er ikke plass?
		if ($up_bullets + $up_bullets_a + $num > $up_cap)
		{
			\Kofradia\DB::get()->commit();
			return "full";
		}
		
		// forsøk å trekk fra broderskapet
		$this->params->lock();
		if ($num > $this->params->get("bullets", 0))
		{
			$this->params->commit();
			return "missing";
		}
		$this->params->update("bullets", $this->params->get("bullets") - $num);
		
		// gi til spilleren
		\Kofradia\DB::get()->exec("
			UPDATE users_players
			SET up_weapon_bullets = up_weapon_bullets + $num
			WHERE up_id = ".$up->id);
		$up->data['up_weapon_bullets'] += $num;

		$this->params->commit(false);
		\Kofradia\DB::get()->commit();
		$this->params->commit(); // setter intern status i params_update til ulåst
		
		// FF-logg
		$this->add_log("bullets_out", "{$up->id}:$num".($real_up ? ":".$real_up->id : ""));
		
		return $num;
	}
	
	/**
	 * Forsøk å sett inn kuler i broderskapet
	 */
	public function bullets_in($num, player $up)
	{
		$num = (int) $num;
		
		$cap = $this->get_bullets_capacity();
		$bullets = $this->params->get("bullets", 0);
		
		$up_cap = $up->weapon->data['bullets'];
		$up_bullets = $up->data['up_weapon_bullets'];
		
		// har vi kulene?
		if ($num > $up_bullets)
		{
			return "missing";
		}
		
		// er ikke plass?
		if ($bullets + $num > $cap)
		{
			return "full";
		}
		
		// trekk fra spilleren
		$a = \Kofradia\DB::get()->exec("
			UPDATE users_players
			SET up_weapon_bullets = up_weapon_bullets - $num
			WHERE up_id = ".$up->id." AND up_weapon_bullets >= $num");
		if ($a == 0)
		{
			return "missing";
		}
		$up->data['up_weapon_bullets'] -= $num;
		
		// legg til i broderskapet
		$this->params->lock();
		$this->params->update("bullets", $this->params->get("bullets") + $num, true);
		
		// FF-logg
		$this->add_log("bullets_in", "{$up->id}:$num");
		
		return $num;
	}
	
	/**
	 * Hent FF til spillere
	 */
	public static function get_ff_list($up_list, $ff_type = null, $status = null, $show_crew_ff = null)
	{
		if (!is_array($up_list)) throw new HSException("Ugyldig inndata.");
		if (count($up_list) == 0) return array();
		
		// FF-status
		if (!$status) $status = ff_member::STATUS_MEMBER;
		else $status = (int) $status;
		
		// hvilke FF
		if ($ff_type)
		{
			if (is_array($ff_type)) $ff_type = " AND ff_type IN ".implode(",", $ff_type);
			else $ff_type = " AND ff_type = $ff_type";
		}
		else $ff_type = "";
		
		// hent FF
		$up_list = array_unique(array_map("intval", $up_list));
		$result = \Kofradia\DB::get()->query("
			SELECT ffm_up_id, ffm_priority, ff_id, ff_type, ff_name
			FROM
				ff_members
				JOIN ff ON ff_id = ffm_ff_id$ff_type AND ff_inactive = 0".($show_crew_ff ? "" : " AND ff_is_crew = 0")."
			WHERE ffm_up_id IN (".implode(", ", $up_list).") AND ffm_status = $status
			ORDER BY ff_name");
		
		$data = array();
		while ($row = $result->fetch())
		{
			$pos = ff::$types[$row['ff_type']]['priority'][$row['ffm_priority']];
			
			$row['priority'] = $pos;
			$row['link'] = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'" title="'.htmlspecialchars($pos).'">'.htmlspecialchars($row['ff_name']).'</a>';
			
			$data[] = $row;
		}
		
		return $data;
	}
	
	/**
	 * Hent broderskapet med flest rangeringspoeng
	 */
	public static function get_fam_points_rank()
	{
		// hent alle broderskapene
		$ff_list = self::get_ff_group("ff_type = ".self::TYPE_FAMILIE." AND ff_inactive = 0 AND ff_is_crew = 0");
		
		// hent alle eiere og medeiere
		$result = \Kofradia\DB::get()->query("
			SELECT ffm_up_id, ffm_ff_id, ffm_priority
			FROM ff_members JOIN ff ON ffm_ff_id = ff_id AND ff_type != ".self::TYPE_FAMILIE." AND ff_is_crew = 0 AND ff_inactive = 0
			WHERE ffm_priority <= 2 AND ffm_status = ".ff_member::STATUS_MEMBER);
		$list = array();
		while ($row = $result->fetch())
		{
			$list[$row['ffm_up_id']][] = $row['ffm_priority'];
		}
		
		// tell opp poeng i alle FF
		$ff_points_most_count = 0;
		$ff_points_most = null;
		$ff_points = array();
		foreach ($ff_list as $ff)
		{
			$points_ff = 0;  // disse 3 er bare for å bryte ned statistikken
			$points_up = 0;
			$points_kill = 0;
			foreach ($ff->members['members'] as $ffm)
			{
				// firmaer
				if (isset($list[$ffm->id]))
				{
					foreach ($list[$ffm->id] as $pri)
					{
						// eier = 3 poeng, medeier = 2 poeng
						$p = $pri == 1 ? 3 : 2;
						$points_ff += $p;
					}
				}
				
				// hent rank
				$rank = game::rank_info($ffm->data['up_points'], $ffm->data['upr_rank_pos'], $ffm->data['up_access_level']);
				$points_up += $rank['number']; // ett poeng per rank for medlemmene
				
				// sjekk rank posisjon
				if ($rank['pos'] == 1) $points_up += 3; // 3 poeng for Capo di tutti capi
				elseif ($rank['pos'] <= 5) $points_up += 2; // 2 poeng for Legend
				elseif ($rank['pos'] <= 15) $points_up += 1; // 1 poeng for Lucky Luciano
			}
			
			// 1 poeng per 5. drap
			$points_kill += floor(($ff->data['ff_attack_killed_num']+$ff->data['ff_attack_bleed_num']) / 5);
			
			// minus 1 poeng per 2. spiller i broderskapet som er drept
			$points_kill -= floor(($ff->data['ff_attacked_killed_num']+$ff->data['ff_attacked_bleed_num']) / 2);
			
			$points = $ff->data['ff_points'];
			$points += $points_ff + $points_up + $points_kill;
			
			$ff_points[] = $points;
			$ff->data['ff_points_sum'] = $points;
			$ff->data['ff_points_ff'] = $points_ff;
			$ff->data['ff_points_up'] = $points_up;
			$ff->data['ff_points_kill'] = $points_kill + $ff->data['ff_points'];
			
			// flest poeng?
			if ($points > $ff_points_most_count)
			{
				$ff_points_most_count = $points;
				$ff_points_most = $ff;
			}
		}
		
		// sorter FF etter poeng
		array_multisort($ff_points, SORT_DESC, SORT_NUMERIC, $ff_list);
		
		// sett opp med ID-er
		$ff_list_indexed = array();
		foreach ($ff_list as $ff)
		{
			$ff_list_indexed[$ff->id] = $ff;
		}
		
		// sjekk for all-time-high
		if ($ff_points_most) hall_of_fame::trigger("familie_rank", $ff_points_most);
		
		return $ff_list_indexed;
	}
	
	/**
	 * Trigger når en spiller dreper en annen
	 */
	public static function handle_up_kill(player $up, $data)
	{
		// bare skadet angrep?
		if (isset($data['attack']) && !$data['attack']['drept']) return;
		
		$killed = $data['up'];
		
		// utenfor topp 15?
		if ($killed->rank['pos'] > 15) return;
		
		// antall poeng vi skal gi
		// 1. plass = 2 poeng, 2.-15. plass = 1
		$p = $killed->rank['pos'] == 1 ? 2 : 1;
		
		// oppdater eventuelle FF
		$crew = " AND ff_is_crew ".($up->is_nostat() ? "!=" : "=")." 0";
		\Kofradia\DB::get()->exec("
			UPDATE users_players, ff, ff_members
			SET ff_points = ff_points + $p
			WHERE up_id = {$up->id} AND ffm_up_id = up_id AND ff_id = ffm_ff_id AND (ffm_status = ".ff_member::STATUS_MEMBER." OR ffm_status = ".ff_member::STATUS_DEACTIVATED.")$crew");
	}
}

/**
 * Medlem av FF
 */
class ff_member
{
	/** ID for spilleren */
	public $id = NULL;
	
	/** ID for brukeren */
	public $id_user = NULL;
	
	/** Info om medlemskapet */
	public $data = NULL;
	
	/**
	 * Params
	 * @var params_update
	 */
	public $params = NULL;
	
	/**
	 * Params for brukeren
	 * @var params_update
	 */
	public $params_user = NULL;
	
	/**
	 * Params for spilleren
	 * @var params_update
	 */
	public $params_player = NULL;
	
	/**
	 * FF medlemmet tilhører
	 * @var ff
	 */
	public $ff = NULL;
	
	/** Status for medlemmet */
	protected $status = NULL;
	
	/** Tilgang pga crew? */
	public $crew = false;
	
	/** Intern data */
	protected $i_data = array();
	
	/**
	 * Spilleren
	 * @var player
	 */
	public $up;
	
	/** Konstant: Bruker er slettet fra brukertabellen */
	const STATUS_DELETED = -1;
	
	/** Konstant: Invitert til FF */
	const STATUS_INVITED = 0;
	
	/** Konstant: Medlem av FF */
	const STATUS_MEMBER = 1;
	
	/** Konstant: Kastet ut av FF */
	const STATUS_KICKED = 2;
	
	/** Konstant: Foreslått til FF */
	const STATUS_SUGGESTED = 3;
	
	/** Medlem av FF da spilleren ble drept */
	const STATUS_DEACTIVATED = 4;
	
	/**
	 * Constructor
	 * @param array $member_info
	 */
	public function __construct($member_info, ff $ff)
	{
		$this->data = $member_info;
		$this->id = $this->data['ffm_up_id'];
		$this->id_user = $this->data['up_u_id'];
		$this->ff = $ff;
		$this->params = new params_update($this->data['ffm_params'], "ff_members", "ffm_params", "ffm_up_id = $this->id AND ffm_ff_id = {$this->ff->id}");
		$this->status = &$this->data['ffm_status'];
		
		$this->__wakeup();
	}
	
	/**
	 * Fiks objektet hvis det har vært serialized
	 */
	public function __wakeup($clean = NULL)
	{
		if (!isset($this->up) || $clean) unset($this->up);
	}
	
	/**
	 * Last inn objekter først når de skal benyttes
	 */
	public function __get($name)
	{
		switch ($name)
		{
			// spilleren
			case "up":
				$this->up = player::get($this->id);
				return $this->up;
		}
	}
	
	/**
	 * Koble seg til riktig sted i FF-objektet
	 */
	public function attach()
	{
		// koble til members array
		$this->ff->members['list'][$this->id] = $this;
		
		// koble til ulike grener i members array
		$this->reattach(true);
		
		// er dette den aktive brukeren og medlem?
		if ($this->status == self::STATUS_MEMBER && $this->ff->active && login::$logged_in && login::$user->player->id == $this->data['ffm_up_id'])
		{
			$this->ff->uinfo = $this;
		}
	}
	
	/**
	 * Fjern kobling fra FF-objektet til brukerobjektet
	 * @var bool $remove_player spilleren ble slettet fra ff_members tabellen
	 */
	protected function detach($remove_player = false)
	{
		unset($this->ff->members[$this->i_data['status']][$this->id]);
		unset($this->ff->members[$this->i_data['status'].'_priority'][$this->i_data['priority']][$this->id]);
		if (count($this->ff->members[$this->i_data['status'].'_priority'][$this->i_data['priority']]) == 0)
		{
			unset($this->ff->members[$this->i_data['status'].'_priority'][$this->i_data['priority']]);
		}
		
		// parent (eierforhold mellom capo og soldier)
		if (isset($this->i_data['parent']))
		{
			unset($this->ff->members[$this->i_data['status'].'_parent'][$this->i_data['parent']][$this->id]);
			if (count($this->ff->members[$this->i_data['status'].'_parent'][$this->i_data['parent']]) == 0)
			{
				unset($this->ff->members[$this->i_data['status'].'_parent'][$this->i_data['parent']]);
			}
			unset($this->i_data['parent']);
		}
		
		// fjerne link?
		if ($remove_player)
		{
			unset($this->ff->members['list'][$this->id]);
		}
	}
	
	/**
	 * Oppdater status (endrer koblinger i ff->members)
	 * Henter info fra $this->data
	 * @param bool $skip_old ikke fjern gamle referanser
	 */
	protected function reattach($skip_old = false)
	{
		// ny status er at brukeren ikke er medlem lenger?
		if ($this->data['ffm_status'] == self::STATUS_KICKED || $this->data['ffm_status'] == self::STATUS_DELETED || $this->data['ffm_status'] == self::STATUS_DEACTIVATED)
		{
			// fjern full link
			$this->detach(true);
			return;
		}
		
		// fjern gamle referanser
		if (!$skip_old)
		{
			$this->detach();
		}
		
		// status
		switch ($this->data['ffm_status'])
		{
			case self::STATUS_INVITED: $status = "invited"; break;
			case self::STATUS_MEMBER: $status = "members"; break;
			case self::STATUS_SUGGESTED: $status = "suggested"; break;
			default: throw new HSException("Ukjent status.");
		}
		
		// sett opp nye referanser
		$this->i_data['status'] = $status;
		$this->i_data['priority'] = $this->data['ffm_priority'];
		$this->ff->members[$status][$this->id] = $this;
		$this->ff->members[$status.'_priority'][$this->data['ffm_priority']][$this->id] = $this;
		
		// soldier?
		if ($this->data['ffm_priority'] == 4 && $this->ff->type['parent'])
		{
			// sett opp referanse
			$this->i_data['parent'] = $this->data['ffm_parent_up_id'];
			$this->ff->members[$status.'_parent'][$this->data['ffm_parent_up_id']][$this->id] = $this;
		}
	}
	
	/** Tilgang pga crew */
	public function crew()
	{
		$this->crew = true;
	}
	
	/** Hent navn for posisjon */
	public function get_priority_name()
	{
		if ($this->crew) return 'CREW';
		return $this->ff->type['priority'][$this->data['ffm_priority']];
	}
	
	/** Sett opp params for brukeren */
	public function params_load()
	{
		if ($this->params_player) return;
		
		if (login::$logged_in && login::$user->player->id == $this->id)
		{
			$this->params_user = login::$user->params;
			$this->params_player = login::$user->player->params;
		}
		
		else
		{
			// hent info fra databasen
			$result = \Kofradia\DB::get()->query("SELECT u_params, up_params FROM users, users_players WHERE up_id = $this->id AND up_u_id = u_id");
			$row = $result->fetch();
			$this->params_user = new params_update($row['u_params'], "users", "u_params", "u_id = $this->id_user");
			$this->params_player = new params_update($row['up_params'], "users_players", "up_params", "up_id = $this->id");
		}
	}
	
	/**
	 * Oppdater forumlenke
	 * @param bool $force legg til/fjern lenke
	 * @return true: lenken ble oppdatert, false: lenken ble ikke oppdatert, NULL: lenken finnes ikke
	 */
	public function forum_link($force = NULL)
	{
		$this->params_load();
		
		// finn ut om forumlenken er lagt til
		if ($force !== NULL) $this->params_user->lock();
		$container = new container($this->params_user->get("forums"));
		
		foreach ($container->items as $key => $row)
		{
			if ($row[0] != "ff") continue;
			if ($row[1] != $this->ff->id) continue;
			
			// fjerne lenken?
			if ($force === false)
			{
				unset($container->items[$key]);
				
				// fjerne hele container?
				if (count($container->items) == 0)
				{
					$this->params_user->remove("forums");
				}
				else
				{
					$this->params_user->update("forums", $container->build());
				}
				
				// lagre endringer
				$this->params_user->commit();
				return true;
			}
			
			// sjekk om lenken er oppdatert
			if ($row[2] != $this->ff->data['ff_name'])
			{
				$this->params_user->lock();
				
				$forums = $this->params_user->get("forums");
				$container = new container($forums);
				foreach ($container->items as $key => $row)
				{
					if ($row[0] != "ff" || $row[1] != $this->ff->id) continue;
					
					// oppdater navnet
					$container->items[$key][2] = $this->ff->data['ff_name'];
					$this->params_user->update("forums", $container->build());
					$this->params_user->commit();
					
					return true;
				}
				
				$this->params_user->commit();
				return NULL;
			}
			
			if ($force !== NULL) $this->params_user->commit();
			return false;
		}
		
		// legge til lenken?
		if ($force === true)
		{
			$container->items[] = array("ff", $this->ff->id, $this->ff->data['ff_name'], $this->ff->get_fse_id());
			$this->params_user->update("forums", $container->build());
			$this->params_user->commit();
			return true;
		}
		
		if ($force !== null) $this->params_user->commit();
		return NULL;
	}
	
	/**
	 * Marker forumet som sett
	 */
	public function forum_seen()
	{
		$this->params_load();
		
		$container = new container($this->params_user->get("forums"));
		foreach ($container->items as $key => $row)
		{
			if ($row[0] != "ff") continue;
			if ($row[1] != $this->ff->id) continue;
			
			// må oppdatere antallet?
			if (isset($row[4]) && $row[4] > 0)
			{
				$this->params_user->lock();
				
				$forums = $this->params_user->get("forums");
				$container = new container($forums);
				foreach ($container->items as $key => $row)
				{
					if ($row[0] != "ff" || $row[1] != $this->ff->id) continue;
					
					// fjern antallet og lagre
					unset($container->items[$key][4]);
					$this->params_user->update("forums", $container->build());
					$this->params_user->commit();
					
					return true;
				}
				
				$this->params_user->commit();
				return NULL;
			}
			
			return false;
		}
		
		return NULL;
	}
	
	/** Aksepter invitasjon */
	public function invite_accept()
	{
		// invitert?
		if ($this->status != self::STATUS_INVITED) throw new HSException("Medlemmet er ikke invitert til ".$this->ff->type['refobj'].".");
		
		// oppdater brukerinfo
		$this->data['ffm_date_join'] = time();
		$this->data['ffm_status'] = 1;
		$this->data['ffm_pay_points'] = $this->data['up_points_rel'];
		\Kofradia\DB::get()->exec("UPDATE ff_members, users_players SET ffm_date_join = {$this->data['ffm_date_join']}, ffm_status = 1, ffm_pay_points = up_points_rel, ffm_log_new = 0 WHERE ffm_up_id = $this->id AND ffm_ff_id = {$this->ff->id} AND ffm_up_id = up_id");
		$this->reattach();
		
		// legg til logg
		$this->ff->add_log("member_invite_accept", $this->id);
		
		// legg til FF-forum
		$this->forum_link(true);
		
		// fjern FF tilknyttet spilleren ved helse under 40 %
		ff::set_leave($this->id);
		
		// trigger for spiller
		player::get($this->id)->trigger("ff_join", array(
				"ff" => $this->ff,
				"member" => $this));
	}
	
	/**
	 * Avslå invitasjon
	 * @param bool $log legg til logg for at medlemmet avslår invitasjonen
	 */
	public function invite_decline($log = true)
	{
		// ikke invitert?
		if ($this->status != self::STATUS_INVITED) throw new HSException("Medlemmet er ikke invitert til ".$this->ff->type['refobj'].".");
		
		// oppdater brukerinfo
		$a = \Kofradia\DB::get()->exec("UPDATE ff_members SET ffm_status = ".self::STATUS_KICKED." WHERE ffm_ff_id = {$this->ff->id} AND ffm_up_id = $this->id AND ffm_date_join != ffm_date_created");
		if ($a == 0)
		{
			// brukeren har ikke vært fullverdg medlem - slett oppføringen
			\Kofradia\DB::get()->exec("DELETE FROM ff_members WHERE ffm_ff_id = {$this->ff->id} AND ffm_up_id = $this->id");
			
			// merk som slettet
			$this->data['ffm_status'] = self::STATUS_DELETED;
		}
		
		else
		{
			// brukeroppføringen blir beholdt
			$this->data['ffm_status'] = self::STATUS_KICKED;
		}
		
		$this->reattach();
		
		// legg til logg
		if ($log) $this->ff->add_log("member_invite_decline", $this->id);
	}
	
	/**
	 * Trekk tilbake invitasjon
	 * @param bool $anonymous skjules hvem som trakk tilbake invitasjonen (f.eks. systemet)
	 */
	public function invite_pullback($anonymous = false)
	{
		if (!$anonymous && !login::$logged_in) throw new HSNotLoggedIn();
		global $_game;
		
		$action_player = $anonymous ? 0 : login::$user->player->id;
		
		// fjern invitasjon
		$this->invite_decline(false);
		
		// brukerlogg (ff_id:ff_name)
		$info = $this->ff->id.":".urlencode($this->ff->data['ff_name']);
		player::add_log_static("ff_delinvite", $info, $action_player, $this->id);
		
		// FF-logg
		$this->ff->add_log("member_invite_pullback", "$action_player:$this->id");
	}
	
	/** Godkjenn forslag som medlem */
	public function suggestion_accept()
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		global $_game;
		
		// ikke foreslått?
		if ($this->status != self::STATUS_SUGGESTED) throw new HSException("Medlemmet er ikke foreslått til FF.");
		
		// opppdater brukerinfo
		$time = time();
		\Kofradia\DB::get()->exec("UPDATE ff_members SET ffm_date_join = $time, ffm_status = 0 WHERE ffm_ff_id = {$this->ff->id} AND ffm_up_id = $this->id");
		
		// brukerlogg
		$info = $this->ff->id.":".urlencode($this->ff->data['ff_name']).":".urlencode($this->get_priority_name()).":{$this->data['ffm_parent_up_id']}";
		player::add_log_static("ff_invite", $info, login::$user->player->id, $this->id);
		
		// legg til logg
		$this->ff->add_log("member_suggest_accept", login::$user->player->id.":$this->id:".urlencode($this->get_priority_name()).":{$this->data['ffm_parent_up_id']}");
	}
	
	/**
	 * Avslå forslag som medlem
	 * @param bool $anonymous skjules hvem som trakk tilbake invitasjonen (f.eks. systemet)
	 */
	public function suggestion_decline($anonymous = false)
	{
		if (!$anonymous && !login::$logged_in) throw new HSNotLoggedIn();
		$action_player = $anonymous ? 0 : login::$user->player->id;
		
		// ikke foreslått?
		if ($this->status != self::STATUS_SUGGESTED) throw new HSException("Medlemmet er ikke foreslått til FF.");
		
		// oppdater brukerinfo
		$a = \Kofradia\DB::get()->exec("UPDATE ff_members SET ffm_status = ".self::STATUS_KICKED." WHERE ffm_ff_id = {$this->ff->id} AND ffm_up_id = $this->id AND ffm_date_join != ffm_date_created");
		if ($a == 0)
		{
			// brukeren har ikke vært fullverdg medlem - slett oppføringen
			\Kofradia\DB::get()->exec("DELETE FROM ff_members WHERE ffm_ff_id = {$this->ff->id} AND ffm_up_id = $this->id");
			
			// merk som slettet
			$this->data['ffm_status'] = self::STATUS_DELETED;
		}
		
		else
		{
			// brukeroppføringen blir beholdt
			$this->data['ffm_status'] = self::STATUS_KICKED;
		}
		
		$this->reattach();
		
		// legg til logg
		$this->ff->add_log("member_suggest_decline", "$action_player:$this->id:".urlencode($this->get_priority_name()).":{$this->data['ffm_parent_up_id']}");
	}
	
	/** Spark spiller */
	public function kick($note = NULL)
	{
		if (!login::$logged_in) throw new HSNotLoggedIn();
		global $_game;
		
		// kast ut medlemmet
		$this->leave(false, true);
		
		// begrunnelse
		$note = urlencode(trim($note));
		
		// brukerlogg (ff_id:ff_name:note)
		$info = $this->ff->id.":".urlencode($this->ff->data['ff_name']).":".$note;
		player::add_log_static("ff_kick", $info, login::$user->player->id, $this->id);
		
		// FF-logg
		$this->ff->add_log("member_kicked", login::$user->player->id.":$this->id:".urlencode($this->get_priority_name()).":".$note);
	}
	
	/**
	 * Forlat FF
	 * @param bool $log lagre logg for at medlemmet forlater FF
	 * @param bool $kicked blir kastet ut
	 * @param bool $deactivated ble spilleren deaktivert/drept?
	 * @param player $up_attack spilleren som førte til hendelsen (angrep)
	 */
	public function leave($log = true, $kicked = false, $deactivated = false, player $up_attack = null)
	{
		global $_game;
		
		// ikke medlem?
		if ($this->status != self::STATUS_MEMBER || $this->crew) throw new HSException("Brukeren er ikke medlem av FF.");
		
		// oppdater brukerinfo
		$this->data['ffm_status'] = $deactivated || $kicked ? self::STATUS_DEACTIVATED : self::STATUS_KICKED;
		$more = $deactivated ? ", ffm_ff_name = ".\Kofradia\DB::quote($this->ff->data['ff_name']) : "";
		\Kofradia\DB::get()->exec("UPDATE ff_members SET ffm_date_part = ".time().", ffm_status = {$this->data['ffm_status']}$more WHERE ffm_ff_id = {$this->ff->id} AND ffm_up_id = $this->id");
		
		$this->reattach();
		
		// oppdater ff_pay_points hvis ikke crewmedlem
		if ($this->data['up_access_level'] < $_game['access_noplay'] && $this->data['ffm_pay_points'] !== null)
		{
			$points = $this->data['up_points_rel'] - $this->data['ffm_pay_points'];
			putlog("NOTICE", "FF RANK: FF {$this->ff->data['ff_name']} (#{$this->ff->id}), spilleren {$this->data['up_name']} (#{$this->id}) forlot FF. $points rankpoeng overført til ff_pay_points.");
			\Kofradia\DB::get()->exec("UPDATE ff SET ff_pay_points = ff_pay_points + $points WHERE ff_id = {$this->ff->id}");
		}
		
		// sjekk om brukeren er involvert i salg av FF
		$this->sell_remove();
		
		// legg til logg
		if ($log) $this->ff->add_log(($deactivated ? "member_deactivated" : "member_leave"), $this->id.":".urlencode($this->get_priority_name()));
		if ($deactivated) player::add_log_static("ff_low_health", $this->ff->id.":".urlencode($this->ff->data['ff_name']).":".urlencode($this->get_priority_name()).($this->data['ffm_parent_up_id'] ? ":".$this->data['ffm_parent_up_id'] : ""), null, $this->id);
		
		// fjern forum lenke
		$this->forum_link(false);
		
		// sjekk om brukeren har noen soldiers under seg
		if ($this->data['ffm_priority'] == 3)
		{
			$this->leave_capo_priority();
		}
		
		$died = false;
		
		// boss og ingen andre er boss?
		// hvis boss ble sparket (noe kun moderator kan gjøre), skjer ingenting
		if (!$kicked && $this->data['ffm_priority'] == 1 && (!isset($this->ff->members['members_priority'][1]) || count($this->ff->members['members_priority'][1]) == 0))
		{
			// har vi medeier og medeier kan ta over?
			if ($this->ff->type['pri2_takeover'] && isset($this->ff->members['members_priority'][2]) && count($this->ff->members['members_priority'][2]) > 0)
			{
				// velg medeier som skal ta over
				$underboss = array_rand($this->ff->members['members_priority'][2]);
				$this->ff->members['list'][$underboss]->change_priority(1, NULL, true);
			}
			
			else
			{
				// FF dør ut
				$this->ff->dies($up_attack);
				$died = true;
			}
		}
		
		// fjern antall nye logg hendelser og lagre tidspunkt for utkastelse
		$up = player::get($this->id);
		$up->data['up_health_ff_time'] = time();
		\Kofradia\DB::get()->exec("
			UPDATE users_players, ff_members
			SET up_log_ff_new = GREATEST(0, up_log_ff_new - ffm_log_new), up_health_ff_time = {$up->data['up_health_ff_time']}
			WHERE up_id = $this->id AND ffm_up_id = up_id AND ffm_ff_id = {$this->ff->id}");
		
		// slett evt. avisartikler som tilhørte spilleren
		new avis_slett_artikler();
		
		// trigger
		$up->trigger("ff_leave", array(
				"ff" => $this->ff,
				"member" => $this,
				"log" => $log,
				"kicked" => $kicked,
				"deactivated" => $deactivated,
				"up" => $up_attack));
		
		if ($died) return 'died';
	}
	
	/**
	 * Forlater posisjonen som capo
	 */
	protected function leave_capo_priority()
	{
		// inviterte?
		if (isset($this->ff->members['invited_parent'][$this->id]))
		{
			// fjern invitasjonen til de valgte
			foreach ($this->ff->members['invited_parent'][$this->id] as $member)
			{
				$member->invite_pullback(true);
			}
		}
		
		// foreslåtte?
		if (isset($this->ff->members['suggested_parent'][$this->id]))
		{
			// fjern invitasjonen til de valgte
			foreach ($this->ff->members['suggested_parent'][$this->id] as $member)
			{
				$member->suggestion_decline(true);
			}
		}
		
		// vanlige medlemmer?
		if (isset($this->ff->members['members_parent'][$this->id]))
		{
			// finn ID på de andre capoene
			$capos = isset($this->ff->members['members_priority'][3]) ? $this->ff->members['members_priority'][3] : array();
			unset($capos[$this->id]);
			
			// må vi sette en av soldierene til capo?
			if (count($capos) == 0)
			{
				// finn ut hvilken soldier som har vært medlem lengst
				$soldier_id = NULL;
				$soldier_time = NULL;
				foreach ($this->ff->members['members_parent'][$this->id] as $member)
				{
					if ($member->data['ffm_date_join'] < $soldier_time || $soldier_time === NULL)
					{
						$soldier_id = $member->id;
						$soldier_time = $member->data['ffm_date_join'];
					}
				}
				
				// sett soldieren til capo
				$member = $this->ff->members['list'][$soldier_id];
				$member->change_priority(3, NULL, true);
				$capos[$member->id] = true;
			}
			
			// send soldiers til tilfeldige capos
			if (isset($this->ff->members['members_parent'][$this->id]))
			{
				foreach ($this->ff->members['members_parent'][$this->id] as $member)
				{
					$member->change_priority($member->data['ffm_priority'], array_rand($capos), true);
				}
			}
		}
	}
	
	/**
	 * Endre posisjon
	 * @param int $priority hvilken posisjon
	 * @param int $parent underordnet hvem
	 * @param bool $anonymous anonym handling (med tanke på loggen til brukeren)
	 */
	public function change_priority($priority, $parent = NULL, $anonymous = false)
	{
		if (!$anonymous && !login::$logged_in) throw new HSNotLoggedIn();
		
		global $_game;
		$priority = (int) $priority;
		$parent = (int) $parent;
		$action_player = $anonymous ? 0 : login::$user->player->id;
		$sell = $anonymous == "sell";
		
		$old_priority = $this->data['ffm_priority'];
		$old_parent = $this->data['ffm_parent_up_id'];
		
		// ikke medlem?
		if ($this->status != self::STATUS_MEMBER || $this->crew) throw new HSException("Brukeren er ikke medlem av FF.");
		
		// ikke gyldig posisjon?
		if (!isset($this->ff->type['priority'][$priority])) throw new HSException("Ugyldig posisjon.");
		
		// kan ikke ha parent?
		if (!empty($parent) && $priority != 4) throw new HSException("Kun soldiers kan ha parent.");
		
		// ugyldig parent?
		if ($parent == $this->id) throw new HSException("Du kan ikke sette brukeren som parent av seg selv.");
		
		// ingenting endret
		if ($priority == $this->data['ffm_priority'] && $parent == $this->data['ffm_parent_up_id'])
		{
			return false;
		}
		
		// sjekk om brukeren er involvert i salg av FF
		if (!$sell) $this->sell_remove();
		
		// flytt medlemmet
		\Kofradia\DB::get()->exec("UPDATE ff_members SET ffm_priority = $priority, ffm_parent_up_id = $parent WHERE ffm_ff_id = {$this->ff->id} AND ffm_up_id = $this->id");
		
		// kun endret overordnet?
		if ($priority == $this->data['ffm_priority'])
		{
			// brukerlogg
			$info = $this->ff->id.":".urlencode($this->ff->data['ff_name']).":{$this->data['ffm_parent_up_id']}:$parent";
			player::add_log_static("ff_member_parent", $info, $action_player, $this->id);
			
			// FF-logg
			$this->ff->add_log("member_parent", "$action_player:$this->id:{$this->data['ffm_parent_up_id']}:$parent");
		}
		
		elseif (!$sell)
		{
			// brukerlogg
			$info = $this->ff->id.":".urlencode($this->ff->data['ff_name']).":".urlencode($this->ff->type['priority'][$this->data['ffm_priority']]).":".urlencode($this->ff->type['priority'][$priority]).":{$this->data['ffm_parent_up_id']}:$parent";
			player::add_log_static("ff_member_priority", $info, $action_player, $this->id);
			
			// FF-logg
			$this->ff->add_log("member_priority", "$action_player:$this->id:".urlencode($this->ff->type['priority'][$this->data['ffm_priority']]).":".urlencode($this->ff->type['priority'][$priority]).":{$this->data['ffm_parent_up_id']}:$parent");
		}
		
		// var capo før?
		if ($this->data['ffm_priority'] == 3 && $priority != 3)
		{
			$this->leave_capo_priority();
		}
		
		// reattach
		$this->data['ffm_priority'] = $priority;
		$this->data['ffm_parent_up_id'] = $parent;
		$this->reattach();
		
		// trigger
		player::get($this->id)->trigger("ff_priority_change", array(
				"ff" => $this->ff,
				"member" => $this,
				"priority_old" => $old_priority,
				"parent_old" => $old_parent,
				"up_id" => $action_player));
		
		return true;
	}
	
	/**
	 * Sørg for at spilleren forlater FF
	 * Uavhengig om spilleren er foreslått, invitert eller medlem
	 * @param bool $deactivated ble spilleren deaktivert/drept?
	 * @param player $up_attack spilleren som angrep som førte til dette
	 */
	public function remove_player($deactivated = false, player $up_attack = null)
	{
		switch ($this->data['ffm_status'])
		{
			// medlem?
			case self::STATUS_MEMBER:
				return $this->leave(true, false, $deactivated, $up_attack);
			break;
			
			// invitert?
			case self::STATUS_INVITED:
				$this->invite_pullback(true);
			break;
			
			// foreslått?
			case self::STATUS_SUGGESTED:
				$this->suggestion_decline(true);
			break;
		}
	}
	
	/**
	 * Fjern salg av FF hvis brukeren er involvert i det
	 */
	public function sell_remove()
	{
		$sell_status = $this->ff->sell_status();
		if ($sell_status){
			if ($sell_status['up_id'] == $this->id)
			{
				// avslå salg
				$this->ff->sell_reject();
			}
			elseif ($sell_status['init_up_id'] == $this->id)
			{
				// trekk tilbake salg
				$this->ff->sell_abort();
			}
		}
	}
}


// sett opp reverse logg
foreach (ff::$log as $name => $info)
{
	ff::$log_id[$info[0]] = $name;
}

// sett opp riktig adresse til bankikonene
ff::$bank_ikoner = array(
	"bank_inn" => '<img src="'.STATIC_LINK.'/firma/bank_inn.gif" alt="Innskudd" title="Innskudd" />',
	"bank_ut" => '<img src="'.STATIC_LINK.'/firma/bank_ut.gif" alt="Uttak" title="Uttak" />',
	"bank_doner" => '<img src="'.STATIC_LINK.'/firma/bank_doner.gif" alt="Donasjon" title="Donasjon" />',
	"bank_betaling" => '<img src="'.STATIC_LINK.'/firma/bank_betaling.gif" alt="Betaling" title="Betaling" />',
	"bank_tbetaling" => '<img src="'.STATIC_LINK.'/firma/bank_tbetaling.gif" alt="Tilbakebetaling" title="Tilbakebetaling" />'
);

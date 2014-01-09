<?php namespace Kofradia\Controller\Users;

use \Kofradia\Donation;
use \Kofradia\Users\Contact;
use \Kofradia\View;

class Contacts extends \Kofradia\Controller
{
	public $createResponseObject = false;
	
	/**
	 * Add title to page and deny guests
	 */
	public function before()
	{
		parent::before();

		\ess::$b->page->add_title("Kontakter");
		\access::no_guest();
	}

	/**
	 * Add new contact
	 */
	public function action_add($up_id)
	{
		$is_block = getval("type") == "block";
		
		if (!($player = \player::get($up_id)))
		{
			\ess::$b->page->add_message("Fant ikke spilleren.", "error");
			return \redirect::handle("/kontakter", \redirect::ROOT);
		}

		// død?
		if ($player->data['up_access_level'] == 0)
		{
			\ess::$b->page->add_message('Spilleren <user id="'.$player->id.'" /> er død og kan ikke legges til.', "error");
			return $player->redirect_to();
		}
		
		// meg selv?
		if ($player->id == $this->user->player->id)
		{
			\ess::$b->page->add_message("Du kan ikke legge til deg selv.", "error");
			return $player->redirect_to();
		}
		
		// avbryte?
		if (isset($_POST['abort']))
		{
			return $player->redirect_to();
		}
		
		// allerede lagt til?
		$contacts = $this->user->getContacts();
		foreach ($contacts as $contact)
		{
			if ($contact->getTargetPlayerID() == $player->id && $contact->isBlock() == $is_block)
			{
				\ess::$b->page->add_message('<user id="'.$player->id.'" /> er allerede i listen.', "error");
				return $player->redirect_to();
			}
		}

		// har vi info?
		if (isset($_POST['add']) && validate_sid(false))
		{
			// begrunnelse
			$info = trim(postval("info"));
			if (!Contact::validateInfoLength($info))
			{
				\ess::$b->page->add_message(($contact->isBlock() == 1 ? 'Begrunnelsen' : 'Informasjonen')." var for lang. Kan ikke være mer enn ".Contact::MAX_INFO_LENGTH." tegn (regnet uten BB koder).", "error");
			}
			
			else
			{
				$contact = Contact::create($this->user, $player, $info, $is_block);
				if (!$contact)
				{
					\ess::$b->page->add_message("Kunne ikke legge til spilleren. Ukjent feil.");
				}

				else
				{
					\ess::$b->page->add_message('<user id="'.$player->id.'" /> er nå '.($is_block ? 'blokkert' : 'lagt til i din kontaktliste').'.');
					return \redirect::handle("/kontakter", \redirect::ROOT);
				}
			}
		}
		
		return View::forge("users/contacts/add", array(
			"is_block" => $is_block,
			"player"   => $player));
	}

	/**
	 * Edit contact entry
	 */
	public function action_edit($uc_id)
	{
		// avbryte
		if (isset($_POST['abort']))
		{
			return \redirect::handle("/kontakter", \redirect::ROOT);
		}

		$contact = Contact::getContactById($uc_id);
		if (!$contact || $contact->getOwnerUserID() != $this->user->id)
		{
			\ess::$b->page->add_message("Fant ikke oppføringen.", "error");
			return \redirect::handle("/kontakter", \redirect::ROOT);
		}

		// lagre?
		if (isset($_POST['save']) && validate_sid(false))
		{
			// begrunnelse
			$info = trim(postval("info"));
			if (!Contact::validateInfoLength($info))
			{
				\ess::$b->page->add_message(($contact->isBlock() == 1 ? 'Begrunnelsen' : 'Informasjonen')." var for lang. Kan ikke være mer enn ".Contact::MAX_INFO_LENGTH." tegn (regnet uten BB koder).", "error");
			}

			// oppdater
			else
			{
				$contact->updateInfo($info);
				$text = $contact->isBlock() ? 'Begrunnelsen for blokkeringen til' : 'Informasjon for kontakten';
				\ess::$b->page->add_message($text.' <user id="'.$contact->getTargetPlayerID().'" /> ble oppdatert.');
				
				return \redirect::handle("/kontakter", \redirect::ROOT);
			}
		}

		return View::forge("users/contacts/edit", array(
			"contact" => $contact));
	}

	/**
	 * Delete a specific contact entry
	 */
	public function action_delete($up_id)
	{
		$player = \player::get($up_id);
		$contact = $player ? Contact::getContactByPlayer($this->user, $player, getval("type") == "block") : null;

		if (validate_sid(false) && $contact && $contact->delete())
		{
			\ess::$b->page->add_message('<user id="'.$player->id.'" /> ble fjernet.');
		}

		return \redirect::handle("/kontakter", \redirect::ROOT);
	}

	/**
	 * Delete selected contacts
	 */
	public function action_delete_many()
	{
		$removed = array();
		if (validate_sid(false) && isset($_POST['id']) && is_array($_POST['id']))
		{
			foreach ($_POST['id'] as $id)
			{
				$contact = Contact::getContactById($id);
				if (!$contact || $contact->getOwnerUserID() != $this->user->id)
				{
					break;
				}

				if ($contact->delete())
				{
					$removed[] = '<user id="'.$contact->getTargetPlayerID().'" />';
				}
			}
		}

		if ($removed)
		{
			\ess::$b->page->add_message('Du fjernet '.sentences_list($removed).' fra listen.');
		}

		return \redirect::handle("/kontakter", \redirect::ROOT);
	}

	/**
	 * View list of contacts
	 */
	public function action_list()
	{
		$sort_k = new \sorts("sort", "/kontakter");
		$sort_k->append("asc", "Navn", "up_name");
		$sort_k->append("desc", "Navn", "up_name DESC");
		$sort_k->append("asc", "Sist aktiv", "up_last_online DESC");
		$sort_k->append("desc", "Sist aktiv", "up_last_online");
		$sort_k->append("asc", "Lagt til som kontakt", "uc_time");
		$sort_k->append("desc", "Lagt til som kontakt", "uc_time DESC");
		$sort_k->set_active(getval('sort'), 0);
		
		$sort_b = new \sorts("sort", "/kontakter");
		$sort_b->append("asc", "Navn", "up_name");
		$sort_b->append("desc", "Navn", "up_name DESC");
		$sort_b->append("asc", "Sist aktiv", "up_last_online DESC");
		$sort_b->append("desc", "Sist aktiv", "up_last_online");
		$sort_b->append("asc", "Lagt til som blokkering", "uc_time");
		$sort_b->append("desc", "Lagt til som blokkering", "uc_time DESC");
		$sort_b->set_active(getval('sort'), 0);

		$sort_by = $sort_k->active()['params'];
		$contacts = Contact::getContacts($this->user, $sort_by);

		$by_type = array(
			Contact::TYPE_FRIEND => array(),
			Contact::TYPE_BLOCK  => array()
		);

		foreach ($contacts as $contact)
		{
			$by_type[$contact->getType()][] = $contact;
		}

		return View::forge("users/contacts/list", array(
			"friends" => $by_type[Contact::TYPE_FRIEND],
			"blocks"  => $by_type[Contact::TYPE_BLOCK],
			"friends_sort" => $sort_k,
			"blocks_sort" => $sort_b));
	}
}
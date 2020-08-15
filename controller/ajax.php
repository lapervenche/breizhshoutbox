<?php
/**
*
* @package Breizh Shoutbox Extension
* @copyright (c) 2018-2020 Sylver35  https://breizhcode.com
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace sylver35\breizhshoutbox\controller;

use sylver35\breizhshoutbox\core\shoutbox;
use phpbb\db\driver\driver_interface as db;
use phpbb\request\request;
use phpbb\auth\auth;

class ajax
{
	/* @var \sylver35\breizhshoutbox\core\shoutbox */
	protected $shoutbox;

	/** @var \phpbb\db\driver\driver_interface as db */
	protected $db;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/**
	 * Constructor
	 */
	public function __construct(shoutbox $shoutbox, db $db, request $request, auth $auth)
	{
		$this->shoutbox = $shoutbox;
		$this->db = $db;
		$this->request = $request;
		$this->auth = $auth;
	}

	/**
	 * Function construct_ajax
	 *
	 * @param string $mode Mode to switch
	 * @return void
	 */
	public function construct_ajax($mode)
	{
		$val = $this->shoutbox->shout_manage_ajax($mode, $this->request->variable('sort', 2), $this->request->variable('user', 0));
		$response = new \phpbb\json_response;
		$data = array();

		// We have our own error handling
		$this->db->sql_return_on_error(true);

		// Permissions verification
		if (!$this->auth->acl_get("u_shout{$val['perm']}"))
		{
			$this->shoutbox->shout_error("NO_VIEW{$val['privat']}_PERM");
			return;
		}

		switch ($mode)
		{
			case 'smilies':
				$data = $this->shoutbox->shout_ajax_smilies();
			break;

			case 'smilies_popup':
				$data = $this->shoutbox->shout_ajax_smilies_popup($this->request->variable('cat', -1));
			break;

			case 'display_smilies':
				$data = $this->shoutbox->shout_ajax_display_smilies($this->request->variable('smiley', 0), $this->request->variable('display', 3));
			break;

			case 'user_bbcode':
				$data = $this->shoutbox->shout_ajax_user_bbcode($this->request->variable('open', ''), $this->request->variable('close', ''), $this->request->variable('other', 0));
			break;

			case 'charge_bbcode':
				$data = $this->shoutbox->shout_ajax_charge_bbcode($val['id']);
			break;

			case 'online':
				$data = $this->shoutbox->shout_ajax_online();
			break;

			case 'rules':
				$data = $this->shoutbox->shout_ajax_rules($val['priv']);
			break;

			case 'preview_rules':
				$data = $this->shoutbox->shout_ajax_preview_rules($this->request->variable('content', '', true));
			break;

			case 'date_format':
				$data = $this->shoutbox->shout_ajax_date_format($this->request->variable('date', '', true));
			break;

			case 'action_sound':
				$data = $this->shoutbox->shout_ajax_action_sound($this->request->variable('sound', 1));
			break;

			case 'cite':
				$data = $this->shoutbox->shout_ajax_cite($val['id']);
			break;

			case 'action_user':
				$data = $this->shoutbox->shout_ajax_action_user($val);
			break;

			case 'action_post':
				$data = $this->shoutbox->shout_ajax_action_post($val, $this->request->variable('message', '', true));
			break;

			case 'action_del':
				$data = $this->shoutbox->shout_ajax_action_del($val);
			break;

			case 'action_del_to':
				$data = $this->shoutbox->shout_ajax_action_del_to($val);
			break;

			case 'action_remove':
				$data = $this->shoutbox->shout_ajax_action_remove($val);
			break;

			case 'delete':
				$data = $this->shoutbox->shout_ajax_delete($val, $this->request->variable('post', 0));
			break;

			case 'purge':
				$data = $this->shoutbox->shout_ajax_purge($val);
			break;

			case 'purge_robot':
				$data = $this->shoutbox->shout_ajax_purge_robot($val);
			break;

			case 'edit':
				$data = $this->shoutbox->shout_ajax_edit($val, $this->request->variable('shout_id', 0), $this->request->variable('chat_message', '', true));
			break;

			case 'post':
				$data = $this->shoutbox->shout_ajax_post($val, $this->request->variable('chat_message', '', true), $this->request->variable('name', '', true), $this->request->variable('cite', 0));
			break;

			case 'check':
			case 'check_pop':
			case 'check_priv':
				$data = $this->shoutbox->shout_ajax_check($val, $this->request->variable('on_bot', true));
			break;

			case 'view':
			case 'view_pop':
			case 'view_priv':
				$data = $this->shoutbox->shout_ajax_view($val, $this->request->variable('on_bot', true), $this->request->variable('start', 0));
			break;
		}

		// Send the response to the browser now
		$response->send($data, true);
	}
}

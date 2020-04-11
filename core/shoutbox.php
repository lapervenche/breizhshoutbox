<?php
/**
*
* @package Breizh Shoutbox Extension
* @copyright (c) 2018-2020 Sylver35  https://breizhcode.com
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace sylver35\breizhshoutbox\core;

use phpbb\json_response;
use phpbb\exception\http_exception;
use phpbb\cache\driver\driver_interface as cache;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\path_helper;
use phpbb\db\driver\driver_interface as db;
use phpbb\pagination;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\auth\auth;
use phpbb\user;
use phpbb\language\language;
use phpbb\log\log;
use Symfony\Component\DependencyInjection\Container;
use phpbb\extension\manager;

class shoutbox
{
	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var \phpbb\config\config */
	protected $config;

	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbb\path_helper */
	protected $path_helper;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\pagination */
	protected $pagination;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var Container */
	protected $phpbb_container;

	/** @var \phpbb\extension\manager */
	protected $ext_manager;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string phpEx */
	protected $php_ext;

	/** @var string root path web */
	protected $root_path_web;

	/** @var string ext path */
	protected $ext_path;

	/** @var string ext path web */
	protected $ext_path_web;

	/** @var string Custom form action */
	protected $u_action;

	/**
	* The database tables
	*
	* @var string */
	protected $shoutbox_table;
	protected $shoutbox_priv_table;
	protected $shoutbox_rules_table;

	/**
	 * Constructor
	 */
	public function __construct(cache $cache, config $config, helper $helper, path_helper $path_helper, db $db, pagination $pagination, request $request, template $template, auth $auth, user $user, language $language, log $log, Container $phpbb_container, manager $ext_manager, $root_path, $php_ext, $shoutbox_table, $shoutbox_priv_table, $shoutbox_rules_table)
	{
		$this->cache				= $cache;
		$this->config				= $config;
		$this->helper				= $helper;
		$this->path_helper			= $path_helper;
		$this->db					= $db;
		$this->pagination			= $pagination;
		$this->request				= $request;
		$this->template				= $template;
		$this->auth					= $auth;
		$this->user					= $user;
		$this->language				= $language;
		$this->log					= $log;
		$this->phpbb_container		= $phpbb_container;
		$this->ext_manager			= $ext_manager;
		$this->root_path			= $root_path;
		$this->php_ext				= $php_ext;
		$this->shoutbox_table		= $shoutbox_table;
		$this->shoutbox_priv_table	= $shoutbox_priv_table;
		$this->shoutbox_rules_table	= $shoutbox_rules_table;
		$this->root_path_web		= generate_board_url() . '/';
		$this->ext_path				= $this->ext_manager->get_extension_path('sylver35/breizhshoutbox', true);
		$this->ext_path_web			= $this->path_helper->update_web_root_path($this->ext_path);
	}

	/**
	* Prints a sql error.
	* @param string $sql Sql query
	* @param int $line Line number
	* @param string $file Filename
	* @return void
	*/
	private function shout_sql_error($sql, $line, $file)
	{
		$response = new json_response();
		$err = $this->db->sql_error();
		$content = array(
			'message'	=> $err['message'],
			'line'		=> $line,
			'file'		=> $file,
			'content'	=> $sql,
			'error'		=> true,
			't'			=> 1,
		);
		$response->send($content, true);
	}

	/**
	* Return error.
	* @param string $message Error
	* @return void
	*/
	public function shout_error($message, $on1 = false, $on2 = false, $on3 = false)
	{
		$response = new json_response();
		if ($this->language->is_set($message))
		{
			$message = $this->language->lang($message);
		}
		else
		{
			if ($on1 && !$on2 && !$on3)
			{
				$message = $this->language->lang($message, $on1);
			}
			else if ($on1 && $on2 && !$on3)
			{
				$message = $this->language->lang($message, $on1, $on2);
			}
			else if ($on1 && $on2 && $on3)
			{
				$message = $this->language->lang($message, $on1, $on2, $on3);
			}
		}
		$content = array(
			'type'		=> 10,
			'error'		=> true,
			'message'	=> $message,
		);
		$response->send($content, true);
	}

	/**
	* execute sql query or return error
	* @param string $sql
	* @param int $limit
	* @param int $start
	* @return string
	*/
	public function shout_sql_query($sql, $limit = false, $start = false)
	{
		if ($limit && $start)
		{
			$result = $this->db->sql_query_limit($sql, $limit, $start);
		}
		else if ($limit)
		{
			$result = $this->db->sql_query_limit($sql, $limit);
		}
		else
		{
			$result = $this->db->sql_query($sql);
		}
		if (!$result)
		{
			$this->shout_sql_error($sql, __LINE__, __FILE__);
			return false;
		}
		else
		{
			return $result;
		}
	}

	/**
	* Get the adm root path
	* @return string
	*/
	public function adm_relative_path()
	{
		return $this->path_helper->get_adm_relative_path();
	}

	/**
	* test if the extension abbc3 is running
	* @return bool
	*/
	public function abbc3_exist()
	{
		if ($this->phpbb_container->has('vse.abbc3.bbcodes_config'))
		{
			return true;
		}
		return false;
	}

	/**
	* test if the extension smiliecreator is running
	* @return bool
	*/
	public function smiliecreator_exist()
	{
		if ($this->phpbb_container->has('sylver35.smilecreator.listener'))
		{
			return true;
		}
		return false;
	}

	/**
	* test if the extension smiliescat is running
	* @return bool
	*/
	public function smiliescategory_exist()
	{
		if ($this->phpbb_container->has('sylver35.smiliescat.listener'))
		{
			return true;
		}
		return false;
	}

	/**
	* test if the extension breizhyoutube is running
	* @return bool
	*/
	public function breizhyoutube_exist()
	{
		if ($this->phpbb_container->has('sylver35.breizhyoutube.listener'))
		{
			return true;
		}
		return false;
	}

	/**
	* test if the extension relaxarcade is running
	* @return bool
	*/
	public function relaxarcade_exist()
	{
		if ($this->phpbb_container->has('teamrelax.relaxarcade.listener.main'))
		{
			return true;
		}
		return false;
	}

	/**
	* Runs the cron functions if time is up
	* Work with normal and private shoutbox
	*/
	private function execute_shout_cron($sort)
	{
		if ($sort)
		{
			$priv = '_priv';
			$private = '_PRIV';
			$shoutbox_table = $this->shoutbox_priv_table;
		}
		else
		{
			$priv = '';
			$private = '';
			$shoutbox_table = $this->shoutbox_table;
		}
		if ((time() - 900) <= $this->config["shout_last_run{$priv}"])
		{
			return;
		}

		if ($this->config["shout_prune{$priv}"] == '' || $this->config["shout_prune{$priv}"] == 0 || $this->config["shout_max_posts{$priv}"] > 0)
		{
			return;
		}
		else if (($this->config["shout_prune{$priv}"] > 0) && ($this->config["shout_max_posts{$priv}"] == 0))
		{
			$deleted = 0;
			$time = time() - ($this->config["shout_prune{$priv}"] * 3600);

			$sql = 'DELETE FROM ' . $shoutbox_table . " WHERE shout_time < '$time'";
			$this->db->sql_query($sql);
			$deleted = $this->db->sql_affectedrows();
			if ($deleted > 0)
			{
				$this->config->increment("shout_del_auto{$priv}", $deleted, true);
				if ($this->config["shout_log_cron{$priv}"])
				{
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, "LOG_SHOUT{$private}_PURGED", time(), array($deleted));
				}
				if ($this->config['shout_delete_robot'])
				{
					$this->post_robot_shout(0, '0.0.0.0', $sort, true, false, true, false, $deleted);
				}
			}
			$this->config->set("shout_last_run{$priv}", time(), true);
		}
	}

	/**
	* Delete posts when the maximum reaches
	* Work in normal and private shoutbox
	*/
	public function delete_shout_posts($sort)
	{
		$deleted	= '';
		$nb_to_del	= 9;// delete 10 messages in 1 operation
		if ($sort)
		{
			$shoutbox_table = $this->shoutbox_priv_table;
			$val_priv = '_priv';
			$val_priv_on = '_PRIV';
		}
		else
		{
			$shoutbox_table = $this->shoutbox_table;
			$val_priv = $val_priv_on = '';
		}

		$sql = 'SELECT COUNT(shout_id) as total
			FROM ' . $shoutbox_table;
		$result = $this->shout_sql_query($sql);
		if (!$result)
		{
			return;
		}
		$row_nb = $this->db->sql_fetchfield('total', $result);
		$this->db->sql_freeresult($result);
		
		if ($row_nb > ((int) $this->config["shout_max_posts{$val_priv}"] + $nb_to_del))
		{
			$delete = array();
			$sql = $this->db->sql_build_query('SELECT', array(
				'SELECT'	=> 'shout_id',
				'FROM'		=> array($shoutbox_table => ''),
				'ORDER_BY'	=> 'shout_time DESC',
			));
			$result = $this->shout_sql_query($sql, $this->config["shout_max_posts{$val_priv}"]);
			if (!$result)
			{
				return;
			}
			while ($row = $this->db->sql_fetchrow($result))
			{
				$delete[] = $row['shout_id'];
			}
			$sql = 'DELETE FROM ' . $shoutbox_table . ' 
				WHERE ' . $this->db->sql_in_set('shout_id', $delete, true);
			$this->db->sql_query($sql);
			$deleted = $this->db->sql_affectedrows();

			if ($this->config["shout_log_cron{$val_priv}"])
			{
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, "LOG_SHOUT{$val_priv_on}_REMOVED", time(), array($deleted));
			}
			$this->config->set("shout_del_auto{$val_priv}", $deleted, true);
			if ($this->config['shout_delete_robot'])
			{
				$this->post_robot_shout(0, '0.0.0.0', $sort, true, false, true, true, $deleted);
			}
		}
	}

	/*
	* Change time of the last message to one second +
	* to update the shoutbox of all users
	*/
	public function update_shout_messages($shoutbox_table, $post)
	{
		$sql = 'SELECT MAX(shout_id) AS shout_end
			FROM ' . $shoutbox_table;
		$result = $this->db->sql_query($sql);
		$max_shout = (int) $this->db->sql_fetchfield('shout_end', $result);
		$this->db->sql_freeresult($result);

		if ($max_shout != $post)
		{
			$sql = 'UPDATE ' . $shoutbox_table . ' 
				SET shout_time = shout_time + 1 
					WHERE shout_id = ' . $max_shout;
			$this->db->sql_query($sql);
		}
	}

	public function get_version()
	{
		if (($data = $this->cache->get('_shout_version')) === false)
		{
			$md_manager = $this->ext_manager->create_extension_metadata_manager('sylver35/breizhshoutbox');
			$meta = $md_manager->get_metadata();

			$data = array(
				'version'	=> $meta['version'],
				'homepage'	=> $meta['homepage'],
			);
			$this->cache->put('_shout_version', $data, 604800);// cache for 7 days
		}

		return $data;
	}

	/**
	* Check if the rules with apropriate language exist
	* @param string sort of shoutbox
	*/
	private function check_shout_rules($sort)
	{
		if ($this->config['shout_rules'])
		{
			$iso = $this->user->lang_name;
			if ($this->config->offsetExists("shout_rules{$sort}_{$iso}"))
			{
				if ($this->config["shout_rules{$sort}_{$iso}"])
				{
					return $iso;
				}
			}
			else
			{
				if ($this->config->offsetExists("shout_rules{$sort}_en"))
				{
					if ($this->config["shout_rules{$sort}_en"])
					{
						return 'en';
					}
				}
			}
		}
		return false;
	}

	/**
	* Get the rules from the cache
	*/
	private function get_shout_rules()
	{
		if (($rules = $this->cache->get('_shout_rules')) === false)
		{
			$sql_ary = array(
				'SELECT'	=> 'l.lang_iso, r.*',
				'FROM'		=> array(LANG_TABLE => 'l'),
				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array($this->shoutbox_rules_table => 'r'),
						'ON'	=> 'r.rules_lang = l.lang_iso',
					),
				),
			);
			$result = $this->shout_sql_query($this->db->sql_build_query('SELECT', $sql_ary));
			if (!$result)
			{
				return;
			}
			while ($row = $this->db->sql_fetchrow($result))
			{
				$rules[$row['lang_iso']] = array(
					'rules_id'				=> $row['id'],
					'rules_text'			=> $row['rules_text'],
					'rules_uid'				=> $row['rules_uid'],
					'rules_bitfield'		=> $row['rules_bitfield'],
					'rules_flags'			=> $row['rules_flags'],
					'rules_text_priv'		=> $row['rules_text_priv'],
					'rules_uid_priv'		=> $row['rules_uid_priv'],
					'rules_bitfield_priv'	=> $row['rules_bitfield_priv'],
					'rules_flags_priv'		=> $row['rules_flags_priv'],
				);
			}
			$this->db->sql_freeresult($result);

			$this->cache->put('_shout_rules', $rules, 604800);// cache for 7 days
		}

		return $rules;
	}

	/**
	* Displays the rules with apropriate language
	* @param $sort string sort of shoutbox 
	* Return array
	*/
	public function shout_rules($sort)
	{
		$content = array(
			'sort'	=> 0,
			'text'	=> '',
		);
		$iso = $this->check_shout_rules($sort);
		if ($iso)
		{
			$rules = $this->get_shout_rules();
			$text = $rules[$iso];
			if ($text["rules_text{$sort}"])
			{
				if (!function_exists('gen_sort_selects'))
				{
					include($this->root_path . 'includes/functions_content.' . $this->php_ext);
				}
				$on_rules = generate_text_for_display($text["rules_text{$sort}"], $text["rules_uid{$sort}"], $text["rules_bitfield{$sort}"], $text["rules_flags{$sort}"]);
				$content = array(
					'sort'	=> 1,
					'text'	=> $on_rules,
				);
			}
		}

		return $content;
	}

	/**
	* Displays list of users online
	* Replace urls for users actions shout
	* Return array
	*/
	public function shout_online()
	{
		$online = obtain_users_online();
		$online_strings = obtain_users_online_string($online);
		$l_online = $online_strings['l_online_users'];
		$l_time = $this->language->lang('VIEW_ONLINE_TIMES', (int) $this->config['load_online_time']);
		$content = array(
			'l_online'	=> $l_online . '<br />(' . $l_time . ')',
		);
		if ($online_strings['online_userlist'] == $this->language->lang('NO_ONLINE_USERS'))
		{
			$content = array_merge($content, array(
				'userlist'	=> $online_strings['online_userlist'],
			));
		}
		else if (strpos($online_strings['online_userlist'], 'avatar') !== false)
		{
			$content = array_merge($content, array(
				'userlist'	=> $online_strings['online_userlist'],
			));
		}
		else
		{
			$i = 0;
			$list = $this->language->lang('REGISTERED_USERS') . ' ';
			$userlist = str_replace($list, '', $online_strings['online_userlist']);
			$l_userlist = explode(', ', $userlist);
			foreach ($l_userlist as $user)
			{
				$list .= ($i > 0) ? ', ' : '';
				$id = $this->find_my_string($user, '&amp;u=', '" ');
				if ($id == 0 || $id == $this->user->data['user_id'])
				{
					$list .= $this->replace_shout_url($user);
				}
				else
				{
					$username = $this->find_my_string($user, '">', '</a>');
					$colour = $this->find_my_string($user, 'color: #', ';"');
					$list .= $this->construct_action_shout($id, $username, $colour);
				}
				$i++;
			}
			$content = array_merge($content, array(
				'userlist'	=> $list,
			));
		}

		return $content;
	}

	/**
	* Extract information from a string
	* @param $string	string where search in
	* @param $start		string start of search
	* @param $end		string end of search
	* Return string
	*/
	private function find_my_string($string, $start, $end)
	{
		$ini = strpos($string, $start);
		if ($ini == 0)
		{
			return $ini;
		}
		$ini += strlen($start);
		$len = strpos($string, $end, $ini) - $ini;
		$content = substr($string, $ini, $len);

		return $content;
	}

	public function shout_pagination($shout_table, $priv, $bot)
	{
		// If no robot messages
		if (!$bot)
		{
			$sql_where = 'shout_robot = 0';
		}
		else
		{
			// Read the forums permissions
			if ($this->auth->acl_gets('a_', 'm_'))
			{
				$sql_where = 'shout_forum = 0 OR (shout_forum <> 0)';
			}
			else
			{
				$sql_where = $this->auth->acl_getf_global('f_read') ? $this->db->sql_in_set('shout_forum', array_keys($this->auth->acl_getf('f_read', true)), false, true) . ' OR shout_forum = 0' : 'shout_forum = 0';
			}
		}

		// count personal messages if needed
		if ($this->user->data['user_id'] == ANONYMOUS || $this->user->data['is_bot'])
		{
			$sql_and = ' AND shout_inp = 0';
		}
		else
		{
			$sql_and = ' AND shout_inp = 0 OR (shout_inp = ' . $this->user->data['user_id'] . ' OR shout_user_id = ' . $this->user->data['user_id'] . ')';
		}

		$sql = $this->db->sql_build_query('SELECT', array(
			'SELECT'	=> 'COUNT(shout_id) as nr',
			'FROM'		=> array($shout_table => ''),
			'WHERE'		=> $sql_where . $sql_and,
		));
		$result = $this->db->sql_query($sql);
		$nb = (int) $this->db->sql_fetchfield('nr');
		$this->db->sql_freeresult($result);
		// Limit the number of messages to display
		$max_number = (int) $this->config["shout_max_posts_on{$priv}"];
		if ($max_number > 0)
		{
			$nb = ($nb > $max_number) ? $max_number : $nb;
		}

		return $nb;
	}

	/**
	* Displays the shoutbox
	*/
	public function shout_display($sort_of)
	{
		if (!$this->auth->acl_get('u_shout_view'))
		{
			$this->template->assign_var('S_DISPLAY_SHOUTBOX', false);
			return;
		}

		$is_user = ($this->user->data['is_registered'] && !$this->user->data['is_bot']) ? true : false;
		// Protection for private and define sort of shoutbox
		$in_priv = ($sort_of === 3) ? true : false;
		$_priv = ($in_priv) ? '_priv' : '';

		if ($in_priv)
		{
			if (!$this->auth->acl_get('u_shout_priv'))
			{
				$this->template->assign_var('S_DISPLAY_SHOUTBOX', false);
				return;
			}
			else
			{
				// Always post enter info in the private shoutbox -> toc toc toc, it's me ;)
				$this->post_robot_shout($this->user->data['user_id'], $this->user->ip, true, false, false, false, false);
			}
		}

		if ($this->config['shout_enable_robot'] && $this->config['shout_cron_hour'] == date('H'))
		{
			// Say hello Mr Robot :-)
			$this->hello_robot_shout();
			// Wish birthdays Mr Robot :-)
			$this->robot_birthday_shout();
		}

		// Define the username for anonymous here
		if (!$this->user->data['is_registered'])
		{
			$this->language->add_lang('ucp');
			$this->template->assign_vars(array(
				'SHOUT_USERNAME_EXPLAIN'	=> $this->language->lang($this->config['allow_name_chars'] . '_EXPLAIN', $this->language->lang('CHARACTERS', (int) $this->config['min_name_chars']), $this->language->lang('CHARACTERS', (int) $this->config['max_name_chars'])),
				'SHOUT_USERNAME'			=> $this->request->variable($this->config['cookie_name'] . '_shout-name', '', true, \phpbb\request\request_interface::COOKIE),
			));
			// Add form token for login box
			add_form_key('login', '_LOGIN');
		}

		// Load the user's preferences
		if ($is_user)
		{
			$shout = json_decode($this->user->data['user_shout']);
			if ($shout->index != '3')
			{
				$this->config['shout_index'] = $shout->index;
				$this->config['shout_forum'] = $shout->forum;
				$this->config['shout_topic'] = $shout->topic;
				$this->config['shout_position_index'] = $shout->index;
				$this->config['shout_position_forum'] = $shout->forum;
				$this->config['shout_position_topic'] = $shout->topic;
			}
		}

		// Active lateral panel or not
		$panel = false;
		if ($this->auth->acl_get('u_shout_lateral'))
		{
			if ($in_priv) // Activate it in private shoutbox
			{
				$this->config['shout_panel_auto'] = true; // Force autoload here
				$panel = true;
			}
			else // And verifie in another pages
			{
				$panel = ($this->config['shout_panel'] && $this->config['shout_panel_all']) ? true : false;
			}
		}
		$data = $this->get_version();

		$this->template->assign_vars(array(
			'S_DISPLAY_SHOUTBOX'	=> true,
			'COLOR_PANEL'			=> 3,
			'IN_SHOUT_POPUP'		=> ($sort_of === 1) ? true : false,
			'PANEL_ALL'				=> $panel,
			'S_IN_PRIV'				=> $in_priv,
			'TEXT_USER_TOP'			=> ($is_user && $this->auth->acl_get('u_shout_bbcode_change')) ? true : false,
			'ACTION_USERS_TOP'		=> ($is_user && ($this->auth->acl_get('u_shout_post_inp') || $this->auth->acl_get('a_') || $this->auth->acl_get('m_'))) ? true : false,
			'INDEX_SHOUT'			=> ((bool) $this->config['shout_index']) ? true : false,
			'INDEX_SHOUT_TOP'		=> ((int) $this->config['shout_position_index'] === 1) ? true : false,
			'INDEX_SHOUT_AFTER'		=> ((int) $this->config['shout_position_index'] === 4) ? true : false,
			'INDEX_SHOUT_END'		=> ((int) $this->config['shout_position_index'] === 2) ? true : false,
			'FORUM_SHOUT'			=> ((bool) $this->config['shout_forum']) ? true : false,
			'POS_SHOUT_FORUM_TOP'	=> ((int) $this->config['shout_position_forum'] === 1) ? true : false,
			'POS_SHOUT_FORUM_END'	=> ((int) $this->config['shout_position_forum'] === 2) ? true : false,
			'TOPIC_SHOUT'			=> ((bool) $this->config['shout_topic']) ? true : false,
			'POS_SHOUT_TOPIC_TOP'	=> ((int) $this->config['shout_position_topic'] === 1) ? true : false,
			'POS_SHOUT_TOPIC_END'	=> ((int) $this->config['shout_position_topic'] === 2) ? true : false,
			'SHOUT_EXT_PATH'		=> $this->ext_path_web,
			'S_SHOUT_VERSION'		=> $data['version'],
		));

		if ($this->auth->acl_get('u_shout_post') && $this->auth->acl_get('u_shout_bbcode'))
		{
			$this->language->add_lang('posting');
			$this->template->assign_vars(array(
				'SHOUT_POSTING'			=> true,
				'S_BBCODE_ALLOWED'		=> true,
				'S_BBCODE_IMG'			=> true,
				'S_LINKS_ALLOWED'		=> true,
				'S_BBCODE_QUOTE'		=> true,
				'S_BBCODE_FLASH'		=> false,
			));

			if (!function_exists('display_custom_bbcodes'))
			{
				include($this->root_path . 'includes/functions_display.' . $this->php_ext);
			}
			// Build custom bbcodes array
			display_custom_bbcodes();
		}

		$this->javascript_shout($sort_of);

		// Do the shoutbox Prune thang
		if ($this->config["shout_on_cron{$_priv}"] && ($this->config["shout_max_posts{$_priv}"] == 0))
		{
			if ($this->config["shout_last_run{$_priv}"] == '')
			{
				$this->config->set("shout_last_run{$_priv}", time() - 86400, true);
			}
			if (($this->config["shout_last_run{$_priv}"] + ($this->config["shout_prune{$_priv}"] * 3600)) < time())
			{
				$this->execute_shout_cron($in_priv);
			}
		}
	}

	public function remove_disallowed_bbcodes($sql_ary)
	{
		$disallowed_bbcodes = explode(', ', $this->config['shout_bbcode']);
		if (!empty($disallowed_bbcodes))
		{
			$sql_ary['WHERE'] .= ' AND ' . $this->db->sql_in_set('b.bbcode_tag', $disallowed_bbcodes, true);
		}

		return $sql_ary;
	}

	/**
	* Search compatibles browsers
	* To display correctly the shout
	* Return int value
	*/
	public function compatibles_browsers()
	{
		$sort = 0;
		$browser = strtolower($this->user->browser);

		if (!empty($browser))
		{
			if (preg_match("#ipad|tablet#i", $browser))// Ipad and tablet ok
			{
				$sort = 3;
			}
			else if (preg_match("#mobile|android|iphone|mobi|ipod|fennec|webos|j2me|midp|cdc|cdlc|bada#i", $browser))// Mobiles browsers
			{
				$sort = 2;
			}
			else if (strpos($browser, 'msie') === false)// Another browsers not IE
			{
				$sort = 5;
			}
			else if (preg_match("#msie 11\.0|msie 10\.0|msie 9\.0#i", $browser))// IE 9, 10 & 11
			{
				$sort = 4;
			}
			else// Another old IE versions
			{
				$sort = 1;
			}
		}

		return $sort;
	}

	/**
	* Displays the retractable lateral panel
	* Return true or false
	*/
	public function shout_panel()
	{
		if (!$this->auth->acl_get('u_shout_lateral') || $this->user->data['is_bot'] || $this->config['board_disable'])
		{
			$this->template->assign_vars(array(
				'KILL_LATERAL'	=> true,
				'ACTIVE_PANEL'	=> false,
				'S_IS_BOT'		=> ($this->user->data['is_bot']) ? true : false,
			));
			return false;
		}
		// Display only if we are not in excluded page
		if (!$this->kill_lateral_on())
		{
			$this->template->assign_var('KILL_LATERAL', true);
			return false;
		}
		else
		{
			$data = $this->get_version();
			$this->template->assign_vars(array(
				'S_IN_SHOUT_POP'	=> true,
				'S_IN_PRIV'			=> false,
				'ACTIVE_PANEL'		=> true,
				'S_IS_BOT'			=> false,
				'AUTO_PANEL'		=> $this->config['shout_panel_auto'] ? true : false,
				'PANEL_FLOAT'		=> $this->config['shout_panel_float'] ? 'left' : 'right',
				'PANEL_OPEN'		=> $this->ext_path_web. 'images/panel/' . $this->config['shout_panel_img'],
				'PANEL_CLOSE'		=> $this->ext_path_web. 'images/panel/' . $this->config['shout_panel_exit_img'],
				'PANEL_WIDTH'		=> $this->config['shout_panel_width'] . 'px',
				'PANEL_HEIGHT'		=> $this->config['shout_panel_height'] . 'px',
				'U_SHOUT_LATERAL'	=> $this->helper->route('sylver35_breizhshoutbox_controller_lateral'),
				'S_SHOUT_VERSION'	=> $data['version'],
			));
			return true;
		}
	}

	/*
	* Function for display or not the lateral panel
	* based on page list in config
	* Never display it for mobile phones (ipad ok)
	* Return true or false
	*/
	public function kill_lateral_on()
	{
		if (!$this->auth->acl_get('u_shout_lateral') || $this->user->data['is_bot']) // No permission
		{
			return false;
		}
		else if (!$this->user->data['is_registered'] && !$this->config['shout_panel'])
		{
			return false;
		}
		else if ($this->compatibles_browsers() == 2) // Not for mobile browsers (not ipad)
		{
			return false;
		}
		// Registred users can set this option
		else if ($this->user->data['is_registered'])
		{
			$set_option = false;
			$shout2 = json_decode($this->user->data['user_shoutbox']);
			if ($shout2->panel != 'N')
			{
				if (!$shout2->panel)
				{
					return false;
				}
				else if ($shout2->panel)
				{
					$set_option = true;
				}
			}
			if (!$this->config['shout_panel'] && !$set_option)
			{
				return false;
			}
		}

		$is_param = $is_dir = $_page = $param = $dir = $Page = false;// Initialise
		if (preg_match("#ucp|mcp|search#i", $this->user->page['page_name']) || preg_match("#adm#i", $this->user->page['page_dir']))// Exclude all pages in this list
		{
			return false;
		}
		$exclude_list = str_replace('&amp;', '&', $this->config['shout_page_exclude']);// Exclude list
		if ($exclude_list != '')
		{
			$on_page = ($this->user->page['page_dir'] ? $this->user->page['page_dir'].'/' : '') . $this->user->page['page_name'] . ($this->user->page['query_string'] ? '?' . $this->user->page['query_string'] : '');
			$on_page1 = ($this->user->page['page_dir'] ? $this->user->page['page_dir'].'/' : '') . $this->user->page['page_name'];
			$pages = explode('||', $exclude_list);
			foreach ($pages as $page)
			{
				$page = str_replace('app.php/', '', $page);
				if (preg_match("#{$page}#i", $this->user->page['page_name']))
				{
					return false;
				}
				else if (strpos($page, '?') !== false)
				{
					$is_param = true;
					list($_page, $param) = explode('?', $page);
					$query_string = ($this->user->page['query_string']) ? explode('&', $this->user->page['query_string']) : '-';
				}

				if (!$is_param) // exclude all pages with or without parameters
				{
					if ($on_page1 == $_page)
					{
						return false;
					}
				}
				else
				{
					if (empty($this->user->page['query_string']))
					{
						if ($on_page == $page)
						{
							return false;
						}
					}
					else
					{
						if ($on_page1 == $_page && ($this->user->page['query_string'] == $param || $query_string[0] == $param))
						{
							return false;
						}
					}
				}
			}
		}

		return true;// Ok, let's go to display it baby (^_^)
	}

	/*
	* Ignore info robot in forum messages
	* Return bool
	*/
	public function shout_post_hide($mode, $s_hide_robot)
	{
		if ($this->auth->acl_get('u_shout_hide') && $s_hide_robot)
		{
			if ($mode == 'edit' && !$this->config['shout_edit_robot'] && !$this->config['shout_edit_robot_priv'])
			{
				return false;
			}
			else
			{
				return true;
			}
		}
		else
		{
			return false;
		}
	}

	/*
	* Personalize message before submit
	* Return string
	*/
	public function personalize_shout_message($message)
	{
		if ($this->user->data['shout_bbcode'])
		{
			list($open, $close) = explode('||', $this->user->data['shout_bbcode']);
		}
		else
		{
			return $message;
		}
		// Don't personalize if somes bbcodes are presents
		if (strpos($message, '[spoil') !== false || strpos($message, '[hidden') !== false || strpos($message, '[offtopic') !== false || strpos($message, '[mod=') !== false || strpos($message, '[quote') !== false || strpos($message, '[code') !== false || strpos($message, '[list') !== false)
		{
			return $message;
		}

		return $open . $message . $close;
	}

	/*
	* Personalize robot messages before submit
	* Return string
	*/
	public function bbcode_shout_message($message)
	{
		return '[color=#' . $this->config['shout_color_message'] . '][i]' . $message . '[/i][/color]';
	}

	/*
	* Parse bbcodes in personalisation
	* before submit
	* Return array
	*/
	public function parse_shout_bbcodes($open, $close, $other)
	{
		// Return error no permission for change personalisation of another
		if ($other > 0 && ($other != $this->user->data['user_id']))
		{
			if (!$this->auth->acl_get('a_') && !$this->auth->acl_get('m_'))
			{
				return array(
					'sort'		=> 5,
					'message'	=> $this->language->lang('NO_SHOUT_PERSO_PERM'),
				);
			}
		}

		// prepare the list
		$open = str_replace('][', '], [', $open);
		$close = str_replace('][', '], [', $close);
		// explode it
		$array_open = explode(', ', $open);
		$array_close = explode(', ', $close);
		// for this user or an another?
		if ($other > 0)
		{
			$sql = $this->db->sql_build_query('SELECT', array(
				'SELECT'	=> 'shout_bbcode',
				'FROM'		=> array(USERS_TABLE => ''),
				'WHERE'		=> "user_id = $other",
			));
			$result = $this->db->sql_query_limit($sql, 1);
			$shout_bbcode = $this->db->sql_fetchfield('shout_bbcode');
			$this->db->sql_freeresult($result);
		}
		else
		{
			$shout_bbcode = $this->user->data['shout_bbcode'];
		}

		if ($open == 1 && $close == 1)// Any modification
		{
			if ($shout_bbcode)
			{
				return array(
					'sort'		=> 1,
				);
			}
			else
			{
				return array(
					'sort'		=> 4,
					'message'	=> $this->language->lang('SHOUT_BBCODE_ERROR_SHAME'),
				);
			}
		}
		else if ($open == '' && $close != '' || $open != '' && $close == '')// If one is empty
		{
			return array(
				'sort'		=> 2,
				'message'	=> $this->language->lang('SHOUT_BBCODE_ERROR'),
			);
		}
		else if (sizeof($array_open) != sizeof($array_close))// If the number of bbcodes opening and closing is different
		{
			return array(
				'sort'		=> 2,
				'message'	=> $this->language->lang('SHOUT_BBCODE_ERROR_COUNT'),
			);
		}
		else if (!preg_match("#^\[|\[|\]|\]$#", $open) || !preg_match("#^\[|\[|\[/|\]|\]$#", $close))// If a square bracket is absent
		{
			return array(
				'sort'		=> 2,
				'message'	=> $this->language->lang('SHOUT_BBCODE_ERROR_COUNT'),
			);
		}
		else
		{
			// Initalise closing of bbcodes and correct imbrication
			$s = $n = 0;
			$slash = $sort = array();
			$reverse_open = array_reverse($array_open);
			for ($i = 0, $nb = sizeof($reverse_open); $i < $nb; $i++)
			{
				$first = substr($reverse_open[$i], 0, strlen($array_close[$i])-2).']';
				if (strpos($array_close[$i], '[/') === false)
				{
					$slash[] = $array_close[$i];
					$s++;
				}
				else if ($first != str_replace('/', '', $array_close[$i]))
				{
					$sort[] = $array_close[$i];
					$n++;
				}
				else
				{
					continue;
				}
			}
			// Check closing of bbcodes
			if ($s)
			{
				$slash = implode(', ', $slash);
				return array(
					'sort'		=> 2,
					'message'	=> $this->language->lang($this->plural('SHOUT_BBCODE_ERROR_SLASH', $s), $s, $slash),
				);
			}
			// Check the correct imbrication of bbcodes
			if ($n)
			{
				$sort = implode(', ', $sort);
				return array(
					'sort'		=> 2,
					'message'	=> $this->language->lang($this->plural('SHOUT_BBCODE_ERROR_IMB', $n), $n, $sort),
				);
			}
			// Check opening and closing of bbcodes
			if ($shout_bbcode)
			{
				$shoutbbcode = explode('||', $shout_bbcode);
				if (str_replace('][', '], [', $shoutbbcode[0]) == $open && str_replace('][', '], [', $shoutbbcode[1]) == $close)
				{
					return array(
						'sort'		=> 4,
						'message'	=> $this->language->lang('SHOUT_BBCODE_ERROR_SHAME'),
					);
				}
			}
			// See for unautorised bbcodes
			$bbcode_array = explode(', ', $this->config['shout_bbcode_user'] . ', ' . $this->config['shout_bbcode']);
			foreach ($bbcode_array as $no)
			{
				if (strpos($close, "[/{$no}]") !== false)
				{
					return array(
						'sort'		=> 2,
						'message'	=> $this->language->lang('SHOUT_NO_CODE', "[{$no}][/{$no}]"),
					);
				}
			}
			// Limit font size for no admin
			if (strpos($open, '[size=') !== false && !$this->auth->acl_get('a_'))
			{
				$all = explode(', ', $open);
				foreach ($all as $is)
				{
					if (preg_match('/size=/i', $is))
					{
						$size = str_replace(array('[', 'size=', ']'), '', $is);
						if ($size > $this->config['shout_bbcode_size'])
						{
							return array(
								'sort'		=> 2,
								'message'	=> $this->language->lang('MAX_FONT_SIZE_EXCEEDED', $this->config['shout_bbcode_size']),
							);
						}
					}
					else
					{
						continue;
					}
				}
			}
			// No video here !
			$video_array = array('flash', 'swf', 'mp4', 'mts', 'avi', '3gp', 'asf', 'flv', 'mpeg', 'video', 'embed', 'BBvideo', 'scrippet', 'quicktime', 'ram', 'gvideo', 'youtube', 'veoh', 'collegehumor', 'dm', 'gamespot', 'gametrailers', 'ignvideo', 'liveleak');
			foreach ($video_array as $video)
			{
				if (strpos($open, '[' . $video) !== false || strpos($open, '<' . $video) !== false)
				{
					return array(
						'sort'		=> 2,
						'message'	=> $this->language->lang('SHOUT_NO_VIDEO'),
					);
				}
				else
				{
					continue;
				}
			}
			// If all is ok, return 3
			return array(
				'sort'	=> 3,
			);
		}
	}

	/*
	* Parse message before submit
	* Prevent some hacking too...
	*/
	public function parse_shout_message($message, $sort_shout = false, $mode = 'post', $robot = false)
	{
		$priv = (!$sort_shout) ? '' : '_priv';
		$on_priv = (!$sort_shout) ? '' : '_PRIV';
		// Set the minimum of caracters to 1 in a message to parse all the time here...
		// This will not alter the minimum in the post form...
		$this->config['min_post_chars'] = 1;

		// Delete enter message before...
		if (strpos($message, $this->language->lang('SHOUT_AUTO')) !== false)
		{
			$message = str_replace($this->language->lang('SHOUT_AUTO'), '', $message);
		}
		// Never post an empty message
		if (empty($message) || empty(preg_replace("(\[.+?\])is", '', $message)))
		{
			$this->shout_error('MESSAGE_EMPTY');
			return;
		}
		// Correct a bug with somes empty bbcodes
		if ($message == '[img][/img]' || $message == '[b][/b]' || $message == '[i][/i]' || $message == '[u][/u]' || $message == '[url][/url]')
		{
			$this->shout_error('MESSAGE_EMPTY');
			return;
		}
		$message = str_replace(array('/]', '&amp;amp;'), array(']', '&'), $message);

		// Store message length...
		// Permission to ignore the limit of characters in a message
		if (!$this->auth->acl_get('u_shout_limit_post') && $this->config['shout_max_post_chars'])
		{
			$message_length = ($mode == 'post') ? utf8_strlen($message) : utf8_strlen(preg_replace('#\[\/?[a-z\*\+\-]+(=[\S]+)?\]#ius', ' ', $message));
			if ($message_length > $this->config['shout_max_post_chars'])
			{
				$this->shout_error('TOO_MANY_CHARS_POST', $message_length, $this->config['shout_max_post_chars']);
				return;
			}
		}
		// See for unautorised bbcodes
		$bbcode_array = explode(', ', $this->config['shout_bbcode']);
		foreach ($bbcode_array as $no)
		{
			if (strpos($message, "[/{$no}]") !== false)
			{
				$this->shout_error('SHOUT_NO_CODE', "[{$no}][/{$no}]");
				return;
			}
		}
		// No video!
		$video_array = array('flash', 'swf', 'mp4', 'mts', 'avi', '3gp', 'asf', 'flv', 'mpeg', 'video', 'embed', 'BBvideo', 'scrippet', 'quicktime', 'ram', 'gvideo', 'youtube', 'veoh', 'collegehumor', 'dm', 'gamespot', 'gametrailers', 'ignvideo', 'liveleak');
		foreach ($video_array as $video)
		{
			if (strpos($message, '[' . $video) !== false && strpos($message, '[/' . $video) !== false || strpos($message, '<' . $video) !== false && strpos($message, '</' . $video) !== false)
			{
				$this->shout_error('SHOUT_NO_VIDEO');
				return;
			}
			else
			{
				continue;
			}
		}
		// Die script and vbscript for all the time...  and log it
		if (strpos($message, '&lt;script') !== false && strpos($message, '&lt;/script') !== false || strpos($message, '<script') !== false && strpos($message, '</script') !== false || 
			strpos($message, '&lt;vbscript') !== false && strpos($message, '&lt;/vbscript') !== false || strpos($message, '<vbscript') !== false && strpos($message, '</vbscript') !== false)
		{
			$this->log->add('user', $this->user->data['user_id'], $this->user->ip, 'LOG_SHOUT_SCRIPT' . $on_priv, time(), array('reportee_id' => $this->user->data['user_id']));
			$this->config->increment("shout_nr_log{$priv}", 1, true);
			$this->shout_error('SHOUT_NO_SCRIPT');
			return;
		}
		// Die applet for all the time...  and log it
		else if (strpos($message, '&lt;applet') !== false && strpos($message, '&lt;/applet') !== false || strpos($message, '<applet') !== false && strpos($message, '</applet') !== false)
		{
			$this->log->add('user', $this->user->data['user_id'], $this->user->ip, 'LOG_SHOUT_APPLET' .$on_priv, time(), array('reportee_id' => $this->user->data['user_id']));
			$this->config->increment("shout_nr_log{$priv}", 1, true);
			$this->shout_error('SHOUT_NO_APPLET');
			return;
		}
		// Die activex for all the time...  and log it
		else if (strpos($message, '&lt;activex') !== false && strpos($message, '&lt;/activex') !== false || strpos($message, '<activex') !== false && strpos($message, '</activex') !== false)
		{
			$this->log->add('user', $this->user->data['user_id'], $this->user->ip, 'LOG_SHOUT_ACTIVEX' .$on_priv, time(), array('reportee_id' => $this->user->data['user_id']));
			$this->config->increment("shout_nr_log{$priv}", 1, true);
			$this->shout_error('SHOUT_NO_ACTIVEX');
			return;
		}
		// Die about and chrome objects for all the time...  and log it
		else if (strpos($message, '&lt;object') !== false && strpos($message, '&lt;/object') !== false || strpos($message, '<object') !== false && strpos($message, '</object') !== false || 
				strpos($message, '&lt;about') !== false && strpos($message, '&lt;/about') !== false || strpos($message, '<about') !== false && strpos($message, '</about') !== false || 
				strpos($message, '&lt;chrome') !== false && strpos($message, '&lt;/chrome') !== false || strpos($message, '<chrome') !== false && strpos($message, '</chrome') !== false)
		{
			$this->log->add('user', $this->user->data['user_id'], $this->user->ip, 'LOG_SHOUT_OBJECTS' . $on_priv, time(), array('reportee_id' => $this->user->data['user_id']));
			$this->config->increment("shout_nr_log{$priv}", 1, true);
			$this->shout_error('SHOUT_NO_OBJECTS');
			return;
		}
		// Die iframe for all the time...  and log it
		else if (strpos($message, '&lt;iframe') !== false && strpos($message, '&lt;/iframe') !== false || strpos($message, '<iframe') !== false && strpos($message, '</iframe') !== false || strpos($message, '[iframe') !== false && strpos($message, '[/iframe') !== false)
		{
			$this->log->add('user', $this->user->data['user_id'], $this->user->ip, 'LOG_SHOUT_IFRAME' . $on_priv, time(), array('reportee_id' => $this->user->data['user_id']));
			$this->config->increment("shout_nr_log{$priv}", 1, true);
			$this->shout_error('SHOUT_NO_IFRAME');
			return;
		}
		if ($robot)
		{
			$message = $this->bbcode_shout_message($message);
		}

		return $this->shout_url_free_sid($message);
	}

	/*
	* Build a number for differentiate guests
	*/
	public function add_random_ip($username)
	{
		$rand = 0;
		$in = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
		$out = array('1','2','3','4','5','6','7','8','9','1','2','3','4','5','6','7','8','9','1','2','3','4','5','6','7','8');
		$ip = str_replace($in, $out, strtolower($this->user->ip));
		$act = explode('.', $this->user->ip);
		for ($i = 0, $nb = sizeof($act); $i < $nb; $i++)
		{
			if ($act[$i] == 0)
			{
				continue;
			}
			$rand = $rand + $act[$i];
		}
		$data = $username . ':' . round($rand/sizeof($act));

		return $data;
	}

	/* 
	* Construct/change profile url
	* to add actions in javascript
	* Only if user have right permissions
	* But never in acp
	* Return string
	*/
	public function construct_action_shout($id, $username = '', $colour = '', $acp = false)
	{
		if (!$id)
		{
			$username_full = get_username_string('no_profile', $id, $this->config['shout_name_robot'], $this->config['shout_color_robot']);
		}
		else if ($id == ANONYMOUS)
		{
			$username_full = get_username_string('no_profile', $id, $username, '6666FF');
		}
		else if (!$this->user->data['is_registered'] || $this->user->data['is_bot'])
		{
			$username_full = get_username_string('no_profile', $id, $username, $colour);
		}
		else if ($id === $this->user->data['user_id'] || $acp)
		{
			$username_full = get_username_string('full', $id, $username, $colour, false, append_sid("{$this->root_path_web}memberlist.{$this->php_ext}", "mode=viewprofile"));
		}
		else
		{
			if ($this->auth->acl_get('u_shout_post_inp') || $this->auth->acl_get('a_') || $this->auth->acl_get('m_'))
			{
				$username_full = $this->tpl('action', $id, $this->language->lang('SHOUT_ACTION_TITLE') . $username, get_username_string('no_profile', $id, $username, $colour));
			}
			else
			{
				$username_full = get_username_string('full', $id, $username, $colour, false, append_sid("{$this->root_path_web}memberlist.{$this->php_ext}", "mode=viewprofile"));
			}
		}

		return $username_full;
	}

	/* 
	* Construct url whithout sid
	* Because urls must be construct for all and use append_sid after
	*/
	private function shout_url_free_sid($content)
	{
		if (strpos($content, 'sid=') !== false)
		{
			$rep = explode('sid=', $content); // explode url
			if (sizeof($rep[1]) > 32) // the sid number is on second part
			{
				$sid_32 = substr($rep[1], 0, 32);
				$content = str_replace($sid_32, '', $content); // substact it
				$content = str_replace(array('&amp;sid=', '&sid=', '?sid=', '-sid='), '', $content);
				$content = str_replace(array('&&amp;', '&amp&amp;', '&amp;&amp;'), '&amp;', $content);
			}
			else
			{
				$content = $rep[0];
			}
		}
		return $content;
	}

	/*
	* Replace relatives urls with complete urls
	*/
	private function replace_shout_url($url)
	{
		return str_replace(array('./../../../../', './../../../', './../../', './../', './'), $this->root_path_web, $url);
	}

	/*
	* protect title value for robot messages
	*/
	private function shout_chars($value)
	{
		$value = str_replace(array('<t>', '</t>', '&lt;t&gt;', '&lt;/t&gt;'), '', $value);
		return htmlspecialchars($value, ENT_QUOTES);
	}

	/*
	* Forms for robot messages
	*/
	private function tpl($sort, $content1 = '', $content2 = '', $content3 = '')
	{
		$tpl = array(
			'action'	=> '<a onclick="shoutbox.actionUser(\'' . $content1 . '\');return false;" title="' . $content2 . '" class="username-coloured action-user">' . $content3 . '</a>',
			'cite'		=> '<span style="color:#' . $this->config['shout_color_message'] . ';font-weight:bold;">' . $content1 . ' </span> ' . $content2 . ' :: ' . $content3,
			'url'		=> '<a class="action-user" href="' . $content1 . '" title="' . $this->shout_chars(($content3 !== '') ? $content3 : $content2) . '">' . $content2 . '</a>',
			'italic'	=> '<span class="shout-italic" style="color:#' . $this->config['shout_color_message'] . '">' . $content1 . '</span>',
			'bold'		=> '<span class="shout-bold">',
			'close'		=> '</span>',
		);

		return $tpl[$sort];
	}

	/*
	* 
	*/
	public function shout_text_for_display($row, $sort, $acp)
	{
		if ($row['shout_info'])
		{
			$row['shout_text'] = $this->display_infos_robot($row, $acp);
		}
		else
		{
			$row['shout_text'] = generate_text_for_display($row['shout_text'], $row['shout_bbcode_uid'], $row['shout_bbcode_bitfield'], $row['shout_bbcode_flags']);
		}

		// Limit the max height for images
		$row['shout_text'] 	= str_replace('class="postimage"', 'class="postimage" style="max-height:200px;"', $row['shout_text']);

		// Active external links for all links in popup and private shoutbox
		if ($sort !== 2)
		{
			if (preg_match('/class=\"postlink\"/i', $row['shout_text']))
			{
				$row['shout_text'] = str_replace('class="postlink', 'onclick="window.open(this.href);return false;" class="postlink', $row['shout_text']);
			}
			else
			{
				$row['shout_text'] = str_replace(array('a href="', 'class="action-user"'), array('a onclick="window.open(this.href);return false;" href="', 'class="action-user" onclick="window.open(this.href);return false;"'), $row['shout_text']);
			}
		}
		else
		{
			$row['shout_text'] = str_replace('class="postlink', 'onclick="window.open(this.href);return false;" class="postlink' , $row['shout_text']);
		}

		return $this->replace_shout_url($row['shout_text']);
	}

	/*
	* Traduct and display infos robot
	* for all infos robot functions
	*/
	private function display_infos_robot($row, $acp)
	{
		$start = $this->language->lang('SHOUT_ROBOT_START');

		switch ($row['shout_info'])
		{
			case 1:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$message = $this->language->lang('SHOUT_SESSION_ROBOT', $username);
			break;
			case 2:
				$username = get_username_string('no_profile', $row['x_user_id'], $row['x_username'], $row['x_user_colour']);
				$message = $this->language->lang('SHOUT_SESSION_ROBOT_BOT', $start, $username);
			break;
			case 3:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$message = $this->language->lang('SHOUT_ENTER_PRIV', $start, $username);
			break;
			case 4:
				$message = $this->language->lang('SHOUT_PURGE_ROBOT', $start);
			break;
			case 5:
				$message = $this->language->lang('SHOUT_PURGE_PRIV', $start);
			break;
			case 6:
				$message = $this->language->lang('SHOUT_PURGE_SHOUT', $start);
			break;
			case 7:
				$message = $this->language->lang('SHOUT_PURGE_AUTO', $start, $row['shout_text']);
			break;
			case 8:
				$message = $this->language->lang('SHOUT_PURGE_PRIV_AUTO', $start, $row['shout_text']);
			break;
			case 9:
				$message = $this->language->lang('SHOUT_DELETE_AUTO', $start, $row['shout_text']);
			break;
			case 10:
				$message = $this->language->lang('SHOUT_DELETE_PRIV_AUTO', $start, $row['shout_text']);
			break;
			case 11:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				if ($row['shout_info_nb'] > 0)// With age to display in birthdays
				{
					$message = $this->language->lang('SHOUT_BIRTHDAY_ROBOT_FULL', $this->config['sitename'], $username,  $this->tpl('close'),  $this->tpl('bold') . $row['shout_info_nb']);
				}
				else// No age to display in birthdays
				{
					$message = $this->language->lang('SHOUT_BIRTHDAY_ROBOT', $this->config['sitename'], $username);
				}
			break;
			case 12:
				$message = $this->language->lang('SHOUT_HELLO_ROBOT',  $this->tpl('close'),  $this->tpl('bold') . $this->user->format_date($row['shout_time'], $this->language->lang('SHOUT_ROBOT_DATE'), true));
			break;
			case 13:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$message = $this->language->lang('SHOUT_NEWEST_ROBOT', $username, $this->config['sitename']);
			break;
			case 14:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_GLOBAL_ROBOT', $start, $username, $url);
			break;
			case 15:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_ANNOU_ROBOT', $start, $username, $url);
			break;
			case 16:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_POST_ROBOT', $start, $username, $url);
			break;
			case 17:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_EDIT_ROBOT', $start, $username, $url);
			break;
			case 18:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_TOPIC_ROBOT', $start, $username, $url);
			break;
			case 19:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_LAST_ROBOT', $start, $username, $url);
			break;
			case 20:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_QUOTE_ROBOT', $start, $username, $url);
			break;
			case 21:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_REPLY_ROBOT', $start, $username, $url);
			break;
			case 35:
				$title = (strlen($row['shout_text']) > 45) ? substr($row['shout_text'], 0, 42) . '...' : $row['shout_text'];
				$url =  $this->tpl('url', $this->helper->route('sylver35_breizhyoutube_controller', array('mode' => 'view', 'id' => $row['shout_info_nb'])), $title, $row['shout_text']);
				$cat_url = $this->tpl('url', $this->helper->route('sylver35_breizhyoutube_controller', array('mode' => 'cat', 'id' => $row['shout_robot'])), $row['shout_text2']);
				$message = $this->language->lang('SHOUT_NEW_VIDEO', $url, $cat_url);
			break;
			case 36:
				$url = $this->tpl('url', $this->helper->route('teamrelax_relaxarcade_page_games', array('gid' => $row['shout_info_nb'])), $row['shout_text']);
				$cat_url = ($row['shout_robot_user'] && $row['shout_text2']) ? $this->tpl('url', $this->helper->route('teamrelax_relaxarcade_page_list', array('cid' => $row['shout_robot_user'])), $row['shout_text2']) : false;
				$message = $this->language->lang('SHOUT_NEW_SCORE_RA_TXT', $row['shout_robot'], $url);
				$message .= ($cat_url) ? ' ' . $this->language->lang('IN') . ' ' . $cat_url : '';
			break;
			case 37:
				$url = $this->tpl('url', $this->helper->route('teamrelax_relaxarcade_page_games', array('gid' => $row['shout_info_nb'])), $row['shout_text']);
				$cat_url = ($row['shout_robot_user'] && $row['shout_text2']) ? $this->tpl('url', $this->helper->route('teamrelax_relaxarcade_page_list', array('cid' => $row['shout_robot_user'])), $row['shout_text2']) : false;
				$message = $this->language->lang('SHOUT_NEW_URECORD_RA_TXT', $row['shout_robot'], $url);
				$message .= ($cat_url) ? ' ' . $this->language->lang('IN') . ' ' . $cat_url : '';
			break;
			case 38:
				$url = $this->tpl('url', $this->helper->route('teamrelax_relaxarcade_page_games', array('gid' => $row['shout_info_nb'])), $row['shout_text']);
				$cat_url = ($row['shout_robot_user'] && $row['shout_text2']) ? $this->tpl('url', $this->helper->route('teamrelax_relaxarcade_page_list', array('cid' => $row['shout_robot_user'])), $row['shout_text2']) : false;
				$message = $this->language->lang('SHOUT_NEW_RECORD_RA_TXT', $row['shout_robot'], $url);
				$message .= ($cat_url) ? ' ' . $this->language->lang('IN') . ' ' . $cat_url : '';
			break;
			case 60:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_PREZ_ROBOT', $start, $username, $url);
			break;
			case 65:
				$username	= $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$message	= generate_text_for_display($row['shout_text'], $row['shout_bbcode_uid'], $row['shout_bbcode_bitfield'], $row['shout_bbcode_flags']);
				return $this->tpl('cite', $this->language->lang('SHOUT_USER_POST'), $username, $message);
			break;
			case 66:
				$username 	= $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$message	= generate_text_for_display($row['shout_text'], $row['shout_bbcode_uid'], $row['shout_bbcode_bitfield'], $row['shout_bbcode_flags']);
				return $this->tpl('cite', $this->language->lang('SHOUT_ACTION_CITE_ON'), $username, $message);
			break;
			case 70:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_PREZ_E_ROBOT', $start, $username, $url);
			break;
			case 71:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_PREZ_ES_ROBOT', $start, $username, $url);
			break;
			case 72:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_PREZ_F_ROBOT', $start, $username, $url);
			break;
			case 73:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_PREZ_FS_ROBOT', $start, $username, $url);
			break;
			case 74:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_PREZ_L_ROBOT', $start, $username, $url);
			break;
			case 75:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_PREZ_LS_ROBOT', $start, $username, $url);
			break;
			case 76:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_PREZ_R_ROBOT', $start, $username, $url);
			break;
			case 77:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_PREZ_RS_ROBOT', $start, $username, $url);
			break;
			case 80:
				$username = $this->construct_action_shout($row['x_user_id'], $row['x_username'], $row['x_user_colour'], $acp);
				$url =  $this->tpl('url', append_sid($this->replace_shout_url($row['shout_text2']), false), $row['shout_text']);
				$message = $this->language->lang('SHOUT_PREZ_Q_ROBOT', $start, $username, $url);
			break;
			case 99:
				$message = $this->language->lang('SHOUT_WELCOME');
			break;
		}

		return $this->tpl('italic', $message);
	}

	/*
	* Display infos Robot for purge, delete messages
	* and enter in the private shoutbox
	*/
	public function post_robot_shout($user_id, $ip, $priv = false, $purge = false, $robot = false, $auto = false, $delete = false, $deleted = '')
	{
		$sort_info	= 1;
		$message	= '-';
		$userid		= (int) $user_id;
		$_priv		= ($priv) ? '_priv' : '';
		$enter_priv	= ($priv && !$purge && !$robot && !$auto && !$delete) ? true : false;
		$shoutbox_table = ($priv) ?  $this->shoutbox_priv_table : $this->shoutbox_table;

		if (!$this->config['shout_enable_robot'] && !$enter_priv)
		{
			return;
		}

		if ($priv && $purge && !$robot && !$auto && !$delete)
		{
			$info = 5;
		}
		else if (!$priv && $purge && !$robot && !$auto && !$delete)
		{
			$info = 6;
		}
		else if (!$priv && $purge && !$robot && $auto && !$delete)
		{
			$message = $deleted;
			$info = 7;
		}
		else if ($priv && $purge && !$robot && $auto && !$delete)
		{
			$message = $deleted;
			$info = 8;
		}
		else if (!$priv && $purge && !$robot && $auto && $delete)
		{
			$message = $deleted;
			$info = 9;
		}
		else if ($priv && $purge && !$robot && $auto && $delete)
		{
			$message = $deleted;
			$info = 10;
		}
		else if ($robot && !$auto && !$delete)
		{
			$info = 4;
		}
		else if ($enter_priv)
		{
			$sql = $this->db->sql_build_query('SELECT', array(
				'SELECT'	=> 'shout_time',
				'FROM'		=> array($shoutbox_table => ''),
				'WHERE'		=> "shout_robot = 8 AND shout_robot_user = $userid AND shout_time BETWEEN " . (time() -60*30) . " AND " . time(),// 30 min For no enter message
			));
			$result = $this->db->sql_query($sql);
			$is_posted = $this->db->sql_fetchfield('shout_time');
			$this->db->sql_freeresult($result);
			if ($is_posted)
			{
				return;
			}
			$message = $this->user->data['username'];
			$sort_info = 8;
			$info = 3;
		}

		$sql_data = array(
			'shout_time'				=> time(),
			'shout_user_id'				=> 0,
			'shout_ip'					=> (string) $ip,
			'shout_text'				=> (string) $message,
			'shout_bbcode_uid'			=> '',
			'shout_bbcode_bitfield'		=> '',
			'shout_bbcode_flags'		=> 0,
			'shout_robot'				=> (int) $sort_info,
			'shout_robot_user'			=> (int) $userid,
			'shout_forum'				=> 0,
			'shout_info'				=> (int) $info,
		);

		$sql = 'INSERT INTO ' . $shoutbox_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
		$this->db->sql_query($sql);
		$this->config->increment("shout_nr{$priv}", 1, true);
	}

	/*
	* Display infos Robot for connections
	*/
	public function post_session_shout($event)
	{
		if ($event['session_user_id'] == ANONYMOUS || !$this->config['shout_enable_robot'])
		{
			return;
		}
		else if (!$this->config['shout_sessions'] && !$this->config['shout_sessions_priv'])
		{
			return;
		}
		else if ($event['session_viewonline'] != true)
		{
			return;
		}
		
		$bot = $this->user->data['is_bot'] ? true : false;
		if ($bot)
		{
			if (!$this->config['shout_sessions_bots'] && !$this->config['shout_sessions_bots_priv'])
			{
				return;
			}
		}

		$userid		= (int) $event['session_user_id'];
		$interval	= (int) $this->config['shout_sessions_time'] * 60;
		$is_posted 	= $is_posted_priv = false;
		
		if ($bot && $this->config['shout_sessions_bots'] || !$bot && $this->config['shout_sessions'])
		{
			$sql = $this->db->sql_build_query('SELECT', array(
				'SELECT'	=> 'shout_time',
				'FROM'		=> array($this->shoutbox_table => ''),
				'WHERE'		=> "shout_robot = 1 AND shout_robot_user = $userid AND shout_time BETWEEN " . (time() - $interval) . " AND " . time(),// $interval For no enter message if user was connect less than X minutes before
			));
			$result = $this->db->sql_query($sql);
			$is_posted = $this->db->sql_fetchfield('shout_time');
			$this->db->sql_freeresult($result);
		}
		if ($bot && $this->config['shout_sessions_bots_priv'] || !$bot && $this->config['shout_sessions_priv'])
		{
			$sql = $this->db->sql_build_query('SELECT', array(
				'SELECT'	=> 'shout_time',
				'FROM'		=> array($this->shoutbox_priv_table => ''),
				'WHERE'		=> "shout_robot = 1 AND shout_robot_user = $userid AND shout_time BETWEEN " . (time() - $interval) . " AND " . time(),// $interval For no enter message if user was connect less than X minutes before
			));
			$result = $this->db->sql_query($sql);
			$is_posted_priv = $this->db->sql_fetchfield('shout_time');
			$this->db->sql_freeresult($result);
		}

		$shout_info = ($bot) ? 2 : 1;
		$message = ($event['session_viewonline']) ? 'view' : 'hide';
		
		$sql_data = array(
			'shout_time'				=> time(),
			'shout_user_id'				=> 0,
			'shout_ip'					=> (string) $this->user->ip,
			'shout_text'				=> (string) $message,
			'shout_bbcode_uid'			=> '',
			'shout_bbcode_bitfield'		=> '',
			'shout_bbcode_flags'		=> 0,
			'shout_robot'				=> 1,
			'shout_robot_user'			=> $userid,
			'shout_forum'				=> 0,
			'shout_info'				=> $shout_info,
		);

		if (($bot && $this->config['shout_sessions_bots'] || !$bot && $this->config['shout_sessions']) && !$is_posted)
		{
			$sql = 'INSERT INTO ' . $this->shoutbox_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
			$this->db->sql_query($sql);
			$this->config->increment('shout_nr', 1, true);
		}
		if (($bot && $this->config['shout_sessions_bots_priv'] || !$bot && $this->config['shout_sessions_priv']) && !$is_posted_priv)
		{
			$sql = 'INSERT INTO ' .  $this->shoutbox_priv_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
			$this->db->sql_query($sql);
			$this->config->increment('shout_nr_priv', 1, true);
		}
	}

	/*
	* Display infos Robot for new posts, subjects, topics...
	*/
	public function advert_post_shoutbox($event)
	{
		if (!$this->config['shout_enable_robot'])
		{
			return;
		}
		$ok_shout		= $this->config['shout_post_robot'] ? true : false;
		$ok_shout_priv	= $this->config['shout_post_robot_priv'] ? true : false;
		$hide_robot		= (isset($event['data']['hide_robot'])) ? $event['data']['hide_robot'] : false;
		if (!$ok_shout && !$ok_shout_priv)
		{
			return;
		}
		if ($hide_robot != false)
		{
			return;
		}

		$ip				= (string) $this->user->ip;
		$userid 		= (int) $this->user->data['user_id'];
		$topic_id 		= (int) $event['data']['topic_id'];
		$forum_id 		= (int) $event['data']['forum_id'];
		$subject		= (string) $event['subject'];
		$post_mode		= (string) $event['mode'];
		$topic_type		= (string) $event['topic_type'];
		$url 			= (string) $this->shout_url_free_sid($event['url']);
		$is_approved	= (isset($event['post_visibility'])) ? $event['post_visibility'] : false;
		$is_prez_form 	= ($this->config->offsetExists('shout_prez_form') && ($forum_id == $this->config['shout_prez_form'])) ? true : false;
		$topic_poster 	= 0;

		$exclude_forums = array();
		if ($this->config['shout_exclude_forums'])
		{
			$exclude_forums = explode(',', $this->config['shout_exclude_forums']);
			if (in_array($forum_id, $exclude_forums))
			{
				return;
			}
		}

		// Parse web adress in subject to prevent bug
		$subject = str_replace(array('http://www.', 'http://', 'https://www.', 'https://', 'www.'), '', $subject);
		$subject = $this->db->sql_escape(str_replace("'", $this->language->lang('SHOUT_PROTECT'), $subject));

		if ($is_prez_form)
		{
			$sql = 'SELECT topic_poster 
				FROM ' . TOPICS_TABLE . ' 
				WHERE topic_id = ' . $topic_id;
			$result = $this->db->sql_query_limit($sql, 1);
			$topic_poster = $this->db->sql_fetchfield('topic_poster');
			$this->db->sql_freeresult($result);
			$prez_poster = ($topic_poster == $userid) ? 1 : 0;
		}

		$username = $this->user->data['username'];
		$user_colour = $this->user->data['user_colour'];
		if ($userid == ANONYMOUS)
		{
			$username = $this->language->lang('GUEST');
		}
		else if ($userid == 0)
		{
			$username = $this->config['shout_name_robot'];
			$user_colour = $this->config['shout_color_robot'];
		}

		if ($topic_type == 3 && $post_mode == 'post')
		{
			$post_mode = 'global';
		}
		else if ($topic_type == 2 && $post_mode == 'post')
		{
			$post_mode = 'annoucement';
		}
		// Delete Re: in the subject
		if (strpos($subject, 'Re: ') !== false)
		{
			$subject = str_replace('Re: ', '', $subject);
		}

		switch ($post_mode)
		{
			case 'global':
				$sort_info = 2;
				$info = 14;
			break;
			case 'annoucement':
				$sort_info = 2;
				$info = 15;
			break;
			case 'post':
				$sort_info = 2;
				$info = (!$is_prez_form) ? 16 : 60;
			break;
			case 'edit':
				if ($is_prez_form)
				{
					$info = ($prez_poster == 0) ? 70 : 71;
				}
				else
				{
					$info = 17;
				}
				$ok_shout = ($this->config['shout_edit_robot']) ? true : false;
				$ok_shout_priv = ($this->config['shout_edit_robot_priv']) ? true : false;
				$sort_info = 3;
			break;
			case 'edit_topic':
			case 'edit_first_post':
				if ($is_prez_form)
				{
					$info = ($prez_poster == 0) ? 72 : 73;
				}
				else
				{
					$info = 18;
				}
				$ok_shout = ($this->config['shout_edit_robot']) ? true : false;
				$ok_shout_priv = ($this->config['shout_edit_robot_priv']) ? true : false;
				$sort_info = 3;
			break;
			case 'edit_last_post':
				if ($is_prez_form)
				{
					$info = ($prez_poster == 0) ? 74 : 75;
				}
				else
				{
					$info = 19;
				}
				$ok_shout = ($this->config['shout_edit_robot']) ? true : false;
				$ok_shout_priv = ($this->config['shout_edit_robot_priv']) ? true : false;
				$sort_info = 3;
			break;
			case 'quote':
				$ok_shout = ($this->config['shout_rep_robot']) ? true : false;
				$ok_shout_priv = ($this->config['shout_rep_robot_priv']) ? true : false;
				$sort_info = 3;
				$info = (!$is_prez_form) ? 20 : 80;
			break;
			case 'reply':
				if ($is_prez_form)
				{
					$info = ($prez_poster == 0) ? 76 : 77;
				}
				else
				{
					$info = 21;
				}
				$ok_shout = ($this->config['shout_rep_robot']) ? true : false;
				$ok_shout_priv = ($this->config['shout_rep_robot_priv']) ? true : false;
				$sort_info = 3;
			break;
		}

		$sql_data = array(
			'shout_time'				=> (string) time(),
			'shout_user_id'				=> 0,
			'shout_ip'					=> (string) $ip,
			'shout_text'				=> (string) $subject,
			'shout_text2'				=> (string) $url,
			'shout_bbcode_uid'			=> '',
			'shout_bbcode_bitfield'		=> '',
			'shout_bbcode_flags'		=> 0,
			'shout_robot'				=> (int) $sort_info,
			'shout_robot_user'			=> (int) $userid,
			'shout_forum'				=> (int) $forum_id,
			'shout_info_nb'				=> (int) $forum_id,
			'shout_info'				=> (int) $info,
		);

		if ($ok_shout)
		{
			$sql = 'INSERT INTO ' . $this->shoutbox_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
			$this->db->sql_query($sql);
			$this->config->increment('shout_nr', 1, true);
		}
		if ($ok_shout_priv)
		{
			$sql = 'INSERT INTO ' . $this->shoutbox_priv_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
			$this->db->sql_query($sql);
			$this->config->increment('shout_nr_priv', 1, true);
		}
	}

	/*
	* Display info of birthdays
	*/
	public function robot_birthday_shout()
	{
		if (!$this->config['shout_birthday'] && !$this->config['shout_birthday_priv'])
		{
			return;
		}
		if ($this->config['shout_last_run_birthday'] == date('d-m-Y'))
		{
			return;
		}

		$is_posted = false;
		if ($this->config['shout_birthday'])
		{
			$sql = $this->db->sql_build_query('SELECT', array(
				'SELECT'	=> 'shout_id',
				'FROM'		=> array($this->shoutbox_table => ''),
				'WHERE'		=> 'shout_robot = 5 AND shout_info = 11 AND shout_time BETWEEN ' . (time() - 60 * 60) . ' AND ' . time(),
			));
			$result = $this->db->sql_query($sql);
			$is_posted = $this->db->sql_fetchfield('shout_id') ? true : false;
			$this->db->sql_freeresult($result);
		}
		else if ($this->config['shout_birthday_priv'])
		{
			$sql = $this->db->sql_build_query('SELECT', array(
				'SELECT'	=> 'shout_id',
				'FROM'		=> array($this->shoutbox_priv_table => ''),
				'WHERE'		=> 'shout_robot = 5 AND shout_info = 11 AND shout_time BETWEEN ' . (time() - 60 * 60) . ' AND ' . time(),
			));
			$result = $this->db->sql_query($sql);
			$is_posted = $this->db->sql_fetchfield('shout_id') ? true : false;
			$this->db->sql_freeresult($result);
		}

		if (!$is_posted)
		{
			$time = $this->user->create_datetime();
			$now = phpbb_gmgetdate($time->getTimestamp() + $time->getOffset());

			// Display birthdays of 29th february on 28th february in non-leap-years
			$leap_year_birthdays = '';
			if ($now['mday'] == 28 && $now['mon'] == 2 && !$time->format('L'))
			{
				$leap_year_birthdays = " OR u.user_birthday LIKE '" . $this->db->sql_escape(sprintf('%2d-%2d-', 29, 2)) . "%'";
			}

			$sql_ary = array(
				'SELECT' => 'u.user_id, u.user_birthday, u.group_id',
				'FROM' => array(
					USERS_TABLE => 'u',
				),
				'LEFT_JOIN' => array(
					array(
						'FROM' => array(BANLIST_TABLE => 'b'),
						'ON' => 'u.user_id = b.ban_userid',
					),
				),
				'WHERE' => "(b.ban_id IS NULL OR b.ban_exclude = 1)
					AND (u.user_birthday LIKE '" . $this->db->sql_escape(sprintf('%2d-%2d-', $now['mday'], $now['mon'])) . "%' $leap_year_birthdays)
					AND u.user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')',
			);

			$sql = $this->db->sql_build_query('SELECT', $sql_ary);
			$result = $this->db->sql_query($sql);
			$rows = $this->db->sql_fetchrowset($result);
			$this->db->sql_freeresult($result);

			if (!empty($rows))
			{
				foreach ($rows as $row)
				{
					$exclude_group = explode(', ', $this->config['shout_birthday_exclude']);
					if (in_array($row['group_id'], $exclude_group))
					{
						continue;
					}

					$birthday_year	= (int) substr($row['user_birthday'], -4);
					$birthday_age	= ($birthday_year) ? max(0, $now['year'] - $birthday_year) : 0;
					$message = 'SHOUT_BIRTHDAY_ROBOT';

					$sql_data = array(
						'shout_time'			=> time(),
						'shout_user_id'			=> 0,
						'shout_ip'				=> (string) $this->user->ip,
						'shout_text'			=> (string) $message,
						'shout_bbcode_uid'		=> '',
						'shout_bbcode_bitfield'	=> '',
						'shout_bbcode_flags'	=> 0,
						'shout_robot'			=> 5,
						'shout_robot_user'		=> (int) $row['user_id'],
						'shout_forum'			=> 0,
						'shout_info_nb'			=> (int) $birthday_age,
						'shout_info'			=> 11,
					);

					if ($this->config['shout_birthday'])
					{
						$sql = 'INSERT INTO ' . $this->shoutbox_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
						$this->db->sql_query($sql);
						$this->config->increment('shout_nr', 1, true);
					}
					if ($this->config['shout_birthday_priv'])
					{
						$sql = 'INSERT INTO ' . $this->shoutbox_priv_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
						$this->db->sql_query($sql);
						$this->config->increment('shout_nr_priv', 1, true);
					}
				}
			}
			$this->config->set('shout_last_run_birthday', date('d-m-Y'), true);
		}
	}

	/*
	* Display the date info Robot
	*/
	public function hello_robot_shout()
	{
		if (!$this->config['shout_hello'] && !$this->config['shout_hello_priv'])
		{
			return;
		}
		if ($this->config['shout_cron_run'] == date('d-m-Y'))
		{
			return;
		}

		$is_posted = false;
		if ($this->config['shout_hello'])
		{
			$sql = $this->db->sql_build_query('SELECT', array(
				'SELECT'	=> 'shout_id',
				'FROM'		=> array($this->shoutbox_table => ''),
				'WHERE'		=> 'shout_robot = 4 AND shout_info = 12 AND shout_time BETWEEN ' . (time() - 60*60) . ' AND ' . time(),
			));
			$result = $this->db->sql_query($sql);
			$is_posted = $this->db->sql_fetchfield('shout_id') ? true : false;
			$this->db->sql_freeresult($result);
		}
		else if ($this->config['shout_hello_priv'])
		{
			$sql = $this->db->sql_build_query('SELECT', array(
				'SELECT'	=> 'shout_id',
				'FROM'		=> array($this->shoutbox_priv_table => ''),
				'WHERE'		=> 'shout_robot = 4 AND shout_info = 12 AND shout_time BETWEEN ' . (time() - 60*60) . ' AND ' . time(),
			));
			$result = $this->db->sql_query($sql);
			$is_posted = $this->db->sql_fetchfield('shout_id');
			$this->db->sql_freeresult($result);
		}
		if (!$is_posted)
		{
			$sql_data = array(
				'shout_time'			=> time(),
				'shout_user_id'			=> 0,
				'shout_ip'				=> (string) $this->user->ip,
				'shout_text'			=> (string) date('d-m-Y'),
				'shout_bbcode_uid'		=> '',
				'shout_bbcode_bitfield'	=> '',
				'shout_bbcode_flags'	=> 0,
				'shout_robot'			=> 1,
				'shout_robot_user'		=> 0,
				'shout_forum'			=> 0,
				'shout_info'			=> 12,
			);

			if ($this->config['shout_hello'])
			{
				$sql = 'INSERT INTO ' . $this->shoutbox_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
				$this->db->sql_query($sql);
				$this->config->increment('shout_nr', 1, true);
			}
			if ($this->config['shout_hello_priv'])
			{
				$sql = 'INSERT INTO ' . $this->shoutbox_priv_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
				$this->db->sql_query($sql);
				$this->config->increment('shout_nr_priv', 1, true);
			}
			$this->config->set('shout_cron_run', date('d-m-Y'), true);
		}
	}

	/*
	* Display first connection for new users
	*/
	public function shout_add_newest_user($event)
	{
		if (!$this->config['shout_enable_robot'] || !$this->config['shout_newest'] && !$this->config['shout_newest_priv'])
		{
			return;
		}

		$sql_data = array(
			'shout_time'				=> time(),
			'shout_user_id'				=> 0,
			'shout_ip'					=> (string) $this->user->ip,
			'shout_text'				=> (string) $event['user_row']['username'],
			'shout_bbcode_uid'			=> '',
			'shout_bbcode_bitfield'		=> '',
			'shout_bbcode_flags'		=> 0,
			'shout_robot'				=> 6,
			'shout_robot_user'			=> (int) $event['user_id'],
			'shout_forum'				=> 0,
			'shout_info'				=> 13,
		);

		if ($this->config['shout_newest'])
		{
			$sql = 'INSERT INTO ' . $this->shoutbox_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
			$this->db->sql_query($sql);
			$this->config->increment('shout_nr', 1, true);
		}
		if ($this->config['shout_newest_priv'])
		{
			$sql = 'INSERT INTO ' . $this->shoutbox_priv_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
			$this->db->sql_query($sql);
			$this->config->increment('shout_nr_priv', 1, true);
		}
	}

	public function submit_new_video($event)
	{
		if (!$this->config['shout_enable_robot'] || !$this->config['shout_video_new'])
		{
			return;
		}

		$sql_data = array(
			'shout_time'				=> time(),
			'shout_user_id'				=> (int) $this->user->data['user_id'],
			'shout_ip'					=> (string) $this->user->ip,
			'shout_text'				=> (string) $event['video_title'],
			'shout_text2'				=> (string) $event['cat_title'],
			'shout_bbcode_uid'			=> '',
			'shout_bbcode_bitfield'		=> '',
			'shout_bbcode_flags'		=> 0,
			'shout_robot'				=> (int) $event['video_cat_id'],
			'shout_robot_user'			=> (int) $this->user->data['user_id'],
			'shout_forum'				=> 0,
			'shout_info_nb'				=> (int) $event['video_id'],
			'shout_info'				=> 35,
		);

		$sql = 'INSERT INTO ' . $this->shoutbox_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
		$this->db->sql_query($sql);
		$this->config->increment('shout_nr', 1, true);
	}

	public function submit_arcade_score($event, $type)
	{
		if (!$this->config['shout_enable_robot'])
		{
			return;
		}
		$submit = false;
		switch ($type)
		{
			case 2:
				$shout_info = 36;
				if ((($event['game_scoretype'] == 0) && ($event['gamescore'] > $event['mscore']))
					|| (($event['game_scoretype'] == 1) && ($event['gamescore'] < $event['mscore']))
					|| is_null($event['mscore']) || $event['muserid'] == 0)
				{
					$submit = true;
				}
			break;
			case 3:
				$shout_info = 37;
				if ((($event['game_scoretype'] == 0) && ($event['gamescore'] > $event['mscore']))
					|| (($event['game_scoretype'] == 1) && ($event['gamescore'] < $event['mscore']))
					|| is_null($event['mscore']) || $event['muserid'] == 0)
				{
					$submit = true;
				}
			break;
			case 4:
				$shout_info = 38;
				if ((($event['game_scoretype'] == 0) && ($event['gamescore'] > $event['highscore'])) 
					|| (($event['game_scoretype'] == 1) && ($event['gamescore'] < $event['highscore'])) 
					|| is_null($event['highscore']))
				{
					$submit = true;
				}
			break;
		}
		if ($submit)
		{
			$title = (isset($event['row']['ra_cat_title'])) ? $event['row']['ra_cat_title'] : '';
			$sql_data = array(
				'shout_time'				=> time(),
				'shout_user_id'				=> (int) $this->user->data['user_id'],
				'shout_ip'					=> (string) $this->user->ip,
				'shout_text'				=> (string) $event['row']['game_name'],
				'shout_text2'				=> (string) $title,
				'shout_bbcode_uid'			=> '',
				'shout_bbcode_bitfield'		=> '',
				'shout_bbcode_flags'		=> 0,
				'shout_robot'				=> (int) $event['gamescore'],
				'shout_robot_user'			=> (int) $event['row']['ra_cat_id'],
				'shout_forum'				=> 0,
				'shout_info_nb'				=> (int) $event['gid'],
				'shout_info'				=> $shout_info,
			);

			$sql = 'INSERT INTO ' . $this->shoutbox_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
			$this->db->sql_query($sql);
			$this->config->increment('shout_nr', 1, true);
		}
	}

	/*
	* Build radio input with specific lang
	*/
	public function construct_radio($name, $sort = 1, $outline = false, $on1 = '', $on2 = '')
	{
		switch ($sort)
		{
			case 1:
				$title1 = $this->language->lang('YES');
				$title2 = $this->language->lang('NO');
			break;
			case 2:
				$title1 = $this->language->lang('ENABLE');
				$title2 = $this->language->lang('DISABLE');
			break;
			case 3:
				$title1 = $this->language->lang($on1);
				$title2 = $this->language->lang($on2);
			break;
		}

		$check1 = ($this->config->offsetGet($name)) ? ' checked="checked" id="' . $name . '"' : '';
		$check2 = (!$this->config->offsetGet($name)) ? ' checked="checked" id="' . $name . '"' : '';

		$data = '<label title="' . $title1 . '"><input type="radio" class="radio" name="' . $name . '" value="1"' . $check1 . ' /> ' . $title1 . '</label>';
		$data .= ($outline) ? '<br /><br />' : '';
		$data .= '<label title="' . $title2 . '"><input type="radio" class="radio" name="' . $name . '" value="0"' . $check2 . ' /> ' . $title2 . '</label>';

		return $data;
	}

	/*
	* Build select for infos hour
	* 24 hours format only
	*/
	public function hour_select($value, $select_name)
	{
		$select = '<select id="' . $select_name . '" name="' . $select_name . '">';
		for ($i = 0; $i < 24; $i++)
		{
			$i = ($i < 10) ? '0' . $i : $i;
			$selected = ($i == $value) ? ' selected="selected"' : '';
			$select .= '<option value="' . $i . '"' . $selected . '>' . $i . "</option>\n";
		}
		$select .= '</select>';

		return $select;
	}

	/*
	* Display user avatar with resizing
	* Add avatar type for robot, users with no avatar and anonymous
	* Add title with username
	* Return string or false
	*/
	public function shout_user_avatar($row, $height, $force = false)
	{
		if (!$force)
		{
			if (!$this->user->optionget('viewavatars') || !$this->config['shout_avatar'] || !$this->config['allow_avatar'])
			{
				return false;
			}
		}
		if (!$row['user_id'] && $this->config['shout_avatar_robot'])
		{
			$val_src = $this->ext_path . 'images/' . $this->config['shout_avatar_img_robot'];
			$val_alt = $this->language->lang('SHOUT_AVATAR_TITLE', $this->config['shout_name_robot']);
		}
		else if ($row['user_id'] == ANONYMOUS && $this->config['shout_avatar_user'])
		{
			$val_src = $this->ext_path . 'images/anonym.png';
			$val_alt = $this->language->lang('SHOUT_AVATAR_TITLE', $this->language->lang('GUEST'));
		}
		else if ($row['user_id'] && !$row['user_avatar'] && $this->config['shout_avatar_user'])
		{
			$val_src = $this->ext_path . 'images/' . $this->config['shout_avatar_img'];
			$val_alt = $this->language->lang('SHOUT_AVATAR_NONE', $row['username']);
		}
		else if ($row['user_id'] && $row['user_avatar'] && $row['user_avatar_height'])
		{
			$avatar_height = ($row['user_avatar_height'] > $height) ? $height : $row['user_avatar_height'];
			$row['user_avatar_width'] = round($avatar_height / $row['user_avatar_height'] * $row['user_avatar_width']);
			$row['user_avatar_height'] = $avatar_height;
			$avatar = $this->replace_shout_url(phpbb_get_user_avatar($row, $this->language->lang('SHOUT_AVATAR_TITLE', $row['username'])));
			$avatar = str_replace('alt="', 'title="' . $this->language->lang('SHOUT_AVATAR_TITLE', $row['username']) . '" alt="', $avatar);

			return $avatar;
		}
		else
		{
			return false;
		}

		$row = array(
			'avatar'		=> $val_src,
			'avatar_type'	=> 'avatar.driver.upload',
			'avatar_height'	=> $height,
			'avatar_width'	=> '',
		);
		$avatar = phpbb_get_user_avatar($row, $val_alt);
		$avatar = str_replace(array('./download/file.php?avatar=', 'alt="'), array('', 'title="' . $val_alt . '" alt="'), $avatar);

		return $this->replace_shout_url($avatar);
	}

	public function build_adm_sound_select($sort)
	{
		$actual = $this->config["shout_sound_{$sort}"];
		$soundlist = filelist($this->ext_path . 'sounds/', '', 'mp3');
		if (sizeof($soundlist))
		{
			$select = (!$actual) ? ' selected="selected"' : '';
			$sound_select = '<option value="0"' . $select . '>' . $this->language->lang('SHOUT_SOUND_EMPTY') . '</option>';
			$soundlist = array_values($soundlist);
			foreach ($soundlist as $key => $sounds)
			{
				$sounds = str_replace('.mp3', '', $sounds);
				natcasesort($sounds);
				foreach ($sounds as $sound)
				{
					$selected = ($sound === $actual) ? ' selected="selected"' : '';
					$sound_select .= '<option title="' . $sound . '" value="' . $sound . '"' . $selected . '>' . $sound . "</option>\n";
				}
			}
			return $sound_select;
		}
		return false;
	}

	private function build_sound_select($actual, $sort)
	{
		if (!function_exists('filelist'))
		{
			include($this->root_path . 'includes/functions_admin.' . $this->php_ext);
		}
		$soundlist = filelist($this->ext_path . 'sounds/', '', 'mp3');

		$title = ($actual == 1) ? $this->language->lang('SHOUT_SOUND_EMPTY') : $actual;
		$select = ($actual == 1) ? ' selected="selected"' : '';
		$sound_select = '<select title="' . $title . '" id="shout_sound_' . $sort . '" name="shout_sound_' . $sort . '" onchange="configs.changeValue(this.value,\'sound_' . $sort . '\');">';
		$sound_select .= '<option value="1"' . $select . '>' . $this->language->lang('SHOUT_SOUND_EMPTY') . '</option>';
		$soundlist = array_values($soundlist);
		foreach ($soundlist as $key => $sounds)
		{
			$sounds = str_replace('.mp3', '', $sounds);
			natcasesort($sounds);
			foreach ($sounds as $sound)
			{
				$selected = ($sound == $actual) ? ' selected="selected"' : '';
				$sound_select .= '<option title="' . $sound . '" value="' . $sound . '"' . $selected . '>' . $sound . '</option>';
			}
		}
		$sound_select .= '</select>';

		return $sound_select;
	}

	public function plural($lang, $nr, $lang2 = '')
	{
		$text = $lang;
		$text .= ($nr > 1) ? 'S' : '';
		$text .= ($lang2) ? $lang2 : '';

		return $text;
	}

	public function purge_shout_admin($sort, $priv = false)
	{
		$shoutbox_table = ($priv) ? $this->shoutbox_priv_table : $this->shoutbox_table;
		$val_priv = $priv ? '_priv' : '';
		$val_priv_on = $priv ? '_PRIV' : '';

		$sql = 'DELETE FROM ' . $shoutbox_table . ' 
			WHERE shout_robot = 1 
				OR shout_robot = ' . (int) $sort;
		$this->db->sql_query($sql);
		$deleted = $this->db->sql_affectedrows();

		if ($deleted)
		{
			$this->config->increment("shout_del_purge{$val_priv}", $deleted, true);
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PURGE_SHOUTBOX' . $val_priv_on . '_ROBOT', time());
			$this->post_robot_shout(0, $this->user->ip, $priv, true, true);
			return false;
		}
		else
		{
			return true;
		}
	}

	public function build_select_img($color_path, $sort, $panel = false)
	{
		$select = '';
		$imglist = array_values(filelist($color_path));
		foreach ($imglist as $key => $image)
		{
			natcasesort($image);
			foreach ($image as $img)
			{
				$on_img = $img;
				$img = substr($img, 0, strrpos($img, '.'));
				$value = ($panel) ? $on_img : $img;
				$selected = ($this->config[$sort] == $value) ? ' selected="selected"' : '';
				$select .= '<option title="' . $img . '" value="' . $value . '"' . $selected . '>' . $img . "</option>\n";
			}
		}
		return $select;
	}

	public function build_select_position($value, $sort = false, $acp = false)
	{
		$option = array(
			'data'	=> '',
		);
		$selected_0 = $selected_1 = $selected_2 = $selected_4 = '';
		switch ($value)
		{
			case 0:
				$selected_0 = ' selected="selected"';
				$option['title'] = $this->language->lang('SHOUT_POSITION_NONE');
			break;
			case 1:
				$selected_1 = ' selected="selected"';
				$option['title'] = $this->language->lang('SHOUT_POSITION_TOP');
			break;
			case 2:
				$selected_2 = ' selected="selected"';
				$option['title'] = $this->language->lang('SHOUT_POSITION_END');
			break;
			case 4:
				$selected_4 = ' selected="selected"';
				$option['title'] = $this->language->lang('SHOUT_POSITION_AFTER');
			break;
		}
		$option['data'] .= (!$acp) ? '<option title="' . $this->language->lang('SHOUT_POSITION_NONE') . '" value="0"' . $selected_0 . '>' . $this->language->lang('SHOUT_POSITION_NONE') . '</option>' : '';
		$option['data'] .= '<option title="' . $this->language->lang('SHOUT_POSITION_TOP') . '" value="1"' . $selected_1 . '>' . $this->language->lang('SHOUT_POSITION_TOP') . '</option>';
		$option['data'] .= ($sort) ? '<option title="' . $this->language->lang('SHOUT_POSITION_AFTER') . '" value="4"' . $selected_4 . '>' . $this->language->lang('SHOUT_POSITION_AFTER') . '</option>' : '';
		$option['data'] .= '<option title="' . $this->language->lang('SHOUT_POSITION_END') . '" value="2"' . $selected_2 . '>' . $this->language->lang('SHOUT_POSITION_END') . '</option>';

		return $option;
	}

	public function build_dateformat_option($dateformat, $adm = false)
	{
		if ($adm) // Let the format_date function operate with the acp values
		{
			$old_tz = $this->user->timezone;
			$old_dst = $this->user->dst;
		}
		$options = '';
		$on_select = false;
		foreach ($this->language->lang_raw('dateformats') as $format => $null)
		{
			$selected = ($format == $dateformat) ? ' selected="selected"' : '';
			$on_select = ($format == $dateformat) ? true : $on_select;
			$options .= '<option value="' . $format . '"' . $selected . '>';
			$options .= $this->user->format_date(time(), $format, false) . ((strpos($format, '|') !== false) ? $this->language->lang('VARIANT_DATE_SEPARATOR') . $this->user->format_date(time(), $format, true) : '');
			$options .= '</option>';
		}
		$select = (!$on_select) ? ' selected="selected"' : '';
		$options .= '<option value="custom"' . $select . '>' . $this->language->lang('CUSTOM_DATEFORMAT') . '</option>';

		if ($adm) // Reset users date options
		{
			$this->user->timezone = $old_tz;
			$this->user->dst = $old_dst;
		}
		return $options;
	}

	private function return_bool($data)
	{
		return ($data) ? 'true' : 'false';
	}

	public function active_config_shoutbox()
	{
		if (!$this->user->data['is_registered'] || $this->user->data['is_bot'] || !$this->auth->acl_get('u_shout_post'))
		{
			throw new http_exception(403, 'NOT_AUTHORISED');
		}

		if ($this->request->is_set_post('submit'))
		{
			$user_shout	= array(
				'user'		=> $this->request->variable('user_sound', 1),
				'new'		=> $this->request->variable('shout_sound_new', '', true),
				'new_priv'	=> $this->request->variable('shout_sound_new_priv', '', true),
				'error'		=> $this->request->variable('shout_sound_error', '', true),
				'del'		=> $this->request->variable('shout_sound_del', '', true),
				'add'		=> $this->request->variable('shout_sound_add', '', true),
				'edit'		=> $this->request->variable('shout_sound_edit', '', true),
				'index'		=> $this->request->variable('position_index', 1),
				'forum'		=> $this->request->variable('position_forum', 1),
				'topic'		=> $this->request->variable('position_topic', 1),
			);
			$user_shout2 = array(
				'bar'		=> $this->request->variable('shout_bar', 0),
				'bar_pop'	=> $this->request->variable('shout_bar_pop', 0),
				'bar_priv'	=> $this->request->variable('shout_bar_priv', 0),
				'pagin'		=> $this->request->variable('shout_pagin', 0),
				'pagin_pop'	=> $this->request->variable('shout_pagin_pop', 0),
				'pagin_priv'=> $this->request->variable('shout_pagin_priv', 0),
				'defil'		=> $this->request->variable('shout_defil', 0),
				'panel'		=> $this->request->variable('shout_panel', 0),
				'dateformat'=> $this->request->variable('dateformat', '', true),
			);

			$sql = 'UPDATE ' . USERS_TABLE . " 
				SET user_shout = '" . $this->db->sql_escape(json_encode($user_shout)) . "' 
					WHERE user_id = " . (int) $this->user->data['user_id'];
			$this->db->sql_query($sql);

			$sql = 'UPDATE ' . USERS_TABLE . " 
				SET user_shoutbox = '" . $this->db->sql_escape(json_encode($user_shout2)) . "' 
					WHERE user_id = " . (int) $this->user->data['user_id'];
			$this->db->sql_query($sql);

			redirect($this->helper->route('sylver35_breizhshoutbox_controller_configshout'));
		}
		else if ($this->request->is_set_post('retour'))
		{
			$user_shout	= array(
				'user'		=> 2,
				'new'		=> 0,
				'new_priv'	=> 0,
				'error'		=> 0,
				'del'		=> 0,
				'add'		=> 0,
				'edit'		=> 0,
				'index'		=> 3,
				'forum'		=> 3,
				'topic'		=> 3,
			);
			$user_shout2 = array(
				'bar'		=> 'N',
				'bar_pop'	=> 'N',
				'bar_priv'	=> 'N',
				'pagin'		=> 'N',
				'pagin_pop'	=> 'N',
				'pagin_priv'=> 'N',
				'defil'		=> 'N',
				'panel'		=> 'N',
				'dateformat'=> ''
			);

			$sql = 'UPDATE ' . USERS_TABLE . " 
				SET user_shout = '" . $this->db->sql_escape(json_encode($user_shout)) . "' 
					WHERE user_id = " . (int) $this->user->data['user_id'];
			$this->db->sql_query($sql);

			$sql = 'UPDATE ' . USERS_TABLE . " 
				SET user_shoutbox = '" . $this->db->sql_escape(json_encode($user_shout2)) . "' 
					WHERE user_id = " . (int) $this->user->data['user_id'];
			$this->db->sql_query($sql);

			redirect($this->helper->route('sylver35_breizhshoutbox_controller_configshout'));
		}
		else
		{
			$this->language->add_lang('ucp');
			$shout	= json_decode($this->user->data['user_shout']);
			$shout2	= json_decode($this->user->data['user_shoutbox']);

			$shout->user		= ($shout->user != 2) ? $shout->user : $this->config['shout_sound_on'];
			$shout->new			= ($shout->new != '0') ? $shout->new : $this->config['shout_sound_new'];
			$shout->new_priv	= ($shout->new_priv != '0') ? $shout->new_priv : $this->config['shout_sound_new_priv'];
			$shout->error		= ($shout->error != '0') ? $shout->error : $this->config['shout_sound_error'];
			$shout->del			= ($shout->del != '0') ? $shout->del : $this->config['shout_sound_del'];
			$shout->add			= ($shout->add != '0') ? $shout->add : $this->config['shout_sound_add'];
			$shout->edit		= ($shout->edit != '0') ? $shout->edit : $this->config['shout_sound_edit'];
			$select_index		= $this->build_select_position(($shout->index == '3') ? $this->config['shout_position_index'] : $shout->index, true);
			$select_forum		= $this->build_select_position(($shout->forum == '3') ? $this->config['shout_position_forum'] : $shout->forum);
			$select_topic		= $this->build_select_position(($shout->topic == '3') ? $this->config['shout_position_topic'] : $shout->topic);
			$shout2->bar		= ($shout2->bar === 'N') ? $this->config['shout_bar_option'] : (bool) $shout2->bar;
			$shout2->bar_pop	= ($shout2->bar_pop === 'N') ? $this->config['shout_bar_option_pop'] :(bool) $shout2->bar_pop;
			$shout2->bar_priv	= ($shout2->bar_priv === 'N') ? $this->config['shout_bar_option_priv'] : (bool) $shout2->bar_priv;
			$shout2->pagin		= ($shout2->pagin === 'N') ? $this->config['shout_pagin_option'] : (bool) $shout2->pagin;
			$shout2->pagin_pop	= ($shout2->pagin_pop === 'N') ? $this->config['shout_pagin_option_pop'] : (bool) $shout2->pagin_pop;
			$shout2->pagin_priv	= ($shout2->pagin_priv === 'N') ? $this->config['shout_pagin_option_priv'] : (bool) $shout2->pagin_priv;
			$shout2->defil		= ($shout2->defil === 'N') ? $this->config['shout_defil'] : (bool) $shout2->defil;
			$shout2->panel		= ($shout2->panel === 'N') ? $this->config['shout_panel'] : (bool) $shout2->panel;
			$shout2->dateformat	= ($shout2->dateformat === '') ? $this->config['shout_dateformat'] : $shout2->dateformat;
			$data = $this->get_version();

			$this->template->assign_vars(array(
				'IN_SHOUT_CONFIG'		=> true,
				'S_PRIVATE'				=> $this->auth->acl_get('u_shout_priv') ? true : false,
				'S_POP'					=> $this->auth->acl_get('u_shout_popup') ? true : false,
				'SOUND_NEW_DISP'		=> ($shout->user && $shout->new != '1') ? true : false,
				'SOUND_NEW_PRIV_DISP'	=> ($shout->user && $shout->new_priv != '1') ? true : false,
				'SOUND_DEL_DISP'		=> ($shout->user && $shout->del != '1') ? true : false,
				'SOUND_ERROR_DISP'		=> ($shout->error != '1') ? true : false,
				'SOUND_ADD_DISP'		=> ($shout->user && $shout->add != '1') ? true : false,
				'SOUND_EDIT_DISP'		=> ($shout->user && $shout->edit != '1') ? true : false,
				'NO_SOUND_DEL'			=> $shout->del ? false : true,
				'NO_SOUND_ERROR'		=> $shout->error ? false : true,
				'NO_SOUND_ADD'			=> $shout->add ? false : true,
				'NO_SOUND_EDIT'			=> $shout->edit ? false : true,
				'NEW_SOUND'				=> $this->build_sound_select($shout->new, 'new'),
				'NEW_SOUND_PRIV'		=> $this->build_sound_select($shout->new_priv, 'new_priv'),
				'ERROR_SOUND'			=> $this->build_sound_select($shout->error, 'error'),
				'DEL_SOUND'				=> $this->build_sound_select($shout->del, 'del'),
				'ADD_SOUND'				=> $this->build_sound_select($shout->add, 'add'),
				'EDIT_SOUND'			=> $this->build_sound_select($shout->edit, 'edit'),
				'USER_SOUND_YES'		=> $shout->user ? true : false,
				'USER_SOUND_INFO'		=> $shout->new,
				'USER_SOUND_INFO_PRIV'	=> $shout->new_priv,
				'USER_SOUND_INFO_E'		=> $shout->error,
				'USER_SOUND_INFO_D'		=> $shout->del,
				'USER_SOUND_INFO_A'		=> $shout->add,
				'USER_SOUND_INFO_ED'	=> $shout->edit,
				'SHOUT_BAR'				=> $shout2->bar ? true : false,
				'SHOUT_BAR_POP'			=> $shout2->bar_pop ? true : false,
				'SHOUT_BAR_PRIV'		=> $shout2->bar_priv ? true : false,
				'SHOUT_PAGIN'			=> $shout2->pagin ? true : false,
				'SHOUT_PAGIN_POP'		=> $shout2->pagin_pop ? true : false,
				'SHOUT_PAGIN_PRIV'		=> $shout2->pagin_priv ? true : false,
				'SHOUT_DEFIL'			=> $shout2->defil ? true : false,
				'SHOUT_PANEL'			=> $shout2->panel ? true : false,
				'SELECT_ON_INDEX'		=> $select_index['data'],
				'SELECT_ON_FORUM'		=> $select_forum['data'],
				'SELECT_ON_TOPIC'		=> $select_topic['data'],
				'TITLE_ON_INDEX'		=> $select_index['title'],
				'TITLE_ON_FORUM'		=> $select_forum['title'],
				'TITLE_ON_TOPIC'		=> $select_topic['title'],
				'DATE_FORMAT'			=> $shout2->dateformat,
				'DATE_FORMAT_EX'		=> $this->user->format_date(time() - 60*61, $shout2->dateformat),
				'DATE_FORMAT_EX2'		=> $this->user->format_date(time() - 60*60*60, $shout2->dateformat),
				'S_DATEFORMAT_OPTIONS'	=> $this->build_dateformat_option($shout2->dateformat),
				'SHOUT_EXT_PATH'		=> $this->ext_path_web,
				'U_DATE_URL' 			=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'date_format')),
				'U_SHOUT_ACTION'		=> $this->helper->route('sylver35_breizhshoutbox_controller_configshout'),
				'SHOUTBOX_VERSION'		=> $this->language->lang('SHOUTBOX_VERSION_ACP_COPY', $data['homepage'], $data['version']),
			));
		}
	}

	public function javascript_shout($sort_of)
	{
		$on_priv = false;
		switch ($sort_of)
		{
			case 1:// Popup shoutbox
				$sort = $on_priv = '';
				$sort_p = '_pop';
				$sort_auth = '_view';
			break;
			case 2:// Normal shoutbox
				$sort = $sort_p = $on_priv = '';
				$sort_auth = '_view';
			break;
			case 3:// Private shoutbox
				$sort_auth = $sort = $sort_p = '_priv';
				$on_priv = '_PRIV';
				$on_priv = true;
			break;
		}

		if (!$this->auth->acl_get("u_shout{$sort_auth}"))
		{
			return;
		}

		// See compatibles browsers to change the display
		$compatible = ($this->compatibles_browsers() == 1) ? false : true;

		// Real member or not
		$is_user = ($this->user->data['is_registered'] && !$this->user->data['is_bot']) ? true : false;

		// Display the rules if wanted
		$rules = $rules_open = false;
		if ($this->check_shout_rules($sort))
		{
			$rules = true;
			// Display the rules opened by default if wanted
			$rules_open = ($this->config["shout_rules_open{$sort}"]) ? true : false;
		}

		// Construct the user's preferences
		if ($is_user)
		{
			$shout			= json_decode($this->user->data['user_shout']);
			$shout2			= json_decode($this->user->data['user_shoutbox']);
			$refresh		= $this->config['shout_temp_users']*1000;
			$inactiv		= ($this->auth->acl_get('u_shout_inactiv') || $on_priv) ? 0 : $this->config['shout_inactiv_member'];
			$shout->user	= ($shout->user == '2') ? $this->config['shout_sound_on'] : $shout->user;
			$sound = array(
				'new'		=> ($shout->new == '0') ? $this->config['shout_sound_new'] : $shout->new,
				'new_priv'	=> ($shout->new_priv == '0') ? $this->config['shout_sound_new_priv'] : $shout->new_priv,
				'error'		=> ($shout->error == '0') ? $this->config['shout_sound_error'] : $shout->error,
				'del'		=> ($shout->del == '0') ? $this->config['shout_sound_del'] : $shout->del,
				'add'		=> ($shout->add == '0') ? $this->config['shout_sound_add'] : $shout->add,
				'edit'		=> ($shout->edit == '0') ? $this->config['shout_sound_edit'] : $shout->edit,
				'active'	=> ($shout->user == '1') ? true : false,
			);
			$dateformat								= ($shout2->dateformat !== '') ? $shout2->dateformat : $this->config['shout_dateformat'];
			$config_shout['shout_bar_option']		= ($shout2->bar !== 'N') ? (bool) $shout2->bar : $this->config['shout_bar_option'];
			$config_shout['shout_bar_option_pop']	= ($shout2->bar_pop !== 'N') ? (bool) $shout2->bar_pop : $this->config['shout_bar_option_pop'];
			$config_shout['shout_bar_option_priv']	= ($shout2->bar_priv !== 'N') ? (bool) $shout2->bar_priv : $this->config['shout_bar_option_priv'];
			$config_shout['shout_pagin_option']		= ($shout2->pagin !== 'N') ? (bool) $shout2->pagin : $this->config['shout_pagin_option'];
			$config_shout['shout_pagin_option_pop']	= ($shout2->pagin_pop !== 'N') ? (bool) $shout2->pagin_pop : $this->config['shout_pagin_option_pop'];
			$config_shout['shout_pagin_option_priv'] = ($shout2->pagin_priv !== 'N') ? (bool) $shout2->pagin_priv : $this->config['shout_pagin_option_priv'];
			$config_shout['shout_defil']			= ($shout2->defil !== 'N') ? (bool) $shout2->defil : $this->config['shout_defil'];
		}
		else
		{
			$sound['new_priv'] = '';
			$refresh	= (!$this->user->data['is_registered']) ? $this->config['shout_temp_anonymous']*1000 : 60*1000;
			$inactiv	= $this->config['shout_inactiv_anony'];
			$dateformat	= $this->config['shout_dateformat'];
			$config_shout['shout_bar_option']		= $this->config['shout_bar_option'];
			$config_shout['shout_bar_option_pop']	= $this->config['shout_bar_option_pop'];
			$config_shout['shout_bar_option_priv']	= $this->config['shout_bar_option_priv'];
			$config_shout['shout_pagin_option']		= $this->config['shout_pagin_option'];
			$config_shout['shout_pagin_option_pop']	= $this->config['shout_pagin_option_pop'];
			$config_shout['shout_pagin_option_priv'] = $this->config['shout_pagin_option_priv'];
			$config_shout['shout_defil']			= $this->config['shout_defil'];
			if ($this->user->data['is_bot'])
			{
				$sound['active'] = false; // No sounds for bots, they have no ears [:-)
				$sound['new'] = $sound['error'] = $sound['del'] = $sound['add'] = $sound['edit'] = '';
			}
			else
			{
				$sound = array(
					'new'		=> $this->config['shout_sound_new'],
					'new_priv'	=> '',
					'error'		=> $this->config['shout_sound_error'],
					'del'		=> $this->config['shout_sound_del'],
					'add'		=> $this->config['shout_sound_add'],
					'edit'		=> $this->config['shout_sound_edit'],
					'active'	=> $this->config['shout_sound_on'] ? true : false,
				);
				if (isset($_COOKIE[$this->config['cookie_name'] . '_shout']))
				{
					$cookie = $this->request->variable($this->config['cookie_name']. '_shout', 'on', false, \phpbb\request\request_interface::COOKIE);
					$sound['active'] = ($cookie == 'on') ? true : false;
				}
			}
		}
		$inactiv	= ($inactiv > 0 && !$on_priv) ? round($inactiv * 60 / ($refresh / 1000)) : 0;
		$cookie_bot	= $this->request->variable($this->config['cookie_name']. '_set-robot', 'on', false, \phpbb\request\request_interface::COOKIE);
		$this->dt	= $this->user->create_datetime();
		$creator	= ($this->smiliecreator_exist()) ? true : false;
		$category	= ($this->smiliescategory_exist()) ? true : false;
		if ($creator)
		{
			$this->language->add_lang('smilie_creator', 'sylver35/smilecreator');
		}
		$this->config['shout_title'] = (!$this->config['shout_title']) ? $this->language->lang('SHOUT_START') : $this->config['shout_title'];
		$this->config['shout_title_priv'] = (!$this->config['shout_title_priv']) ? $this->language->lang('SHOUTBOX_SECRET') : $this->config['shout_title_priv'];
		$data = $this->get_version();

		// Construct global vars
		$settings_auth = array(
			'inactivity'		=> $inactiv,
			'requestOn'			=> $refresh,
			'sortShoutNb'		=> $sort_of,
			'perPage'			=> $this->config["shout_non_ie_nr{$sort_p}"],
			'maxPost'			=> $this->config['shout_max_post_chars'],
			'minName'			=> $this->config['min_name_chars'],
			'maxName'			=> $this->config['max_name_chars'],
			'userId'			=> $this->user->data['user_id'],
			'isGuest'			=> $this->return_bool($this->user->data['user_id'] == ANONYMOUS),
			'enableSound'		=> $this->return_bool($sound['active']),
			'isPriv'			=> $this->return_bool($on_priv),
			'isUser'			=> $this->return_bool($is_user),
			'rulesOk'			=> $this->return_bool($rules),
			'rulesOpen'			=> $this->return_bool($rules_open),
			'isCompatible'		=> $this->return_bool($compatible),
			'refresh'			=> $this->return_bool(strpos($this->config['shout_dateformat'], '|') !== false),
			'seeButtons'		=> $this->return_bool($this->config['shout_see_buttons']),
			'buttonsLeft'		=> $this->return_bool($this->config['shout_see_buttons_left']),
			'barHaute'			=> $this->return_bool($config_shout["shout_bar_option{$sort_p}"]),
			'sortPagin'			=> $this->return_bool($config_shout["shout_pagin_option{$sort_p}"]),
			'toBottom'			=> $this->return_bool($config_shout['shout_defil']),
			'buttonIp'			=> $this->return_bool($this->config['shout_see_button_ip']),
			'buttonCite'		=> $this->return_bool($this->config['shout_see_cite']),
			'purgeOn'			=> $this->return_bool($this->auth->acl_gets('m_shout_purge', 'a_shout_manage')),
			'onlineOk'			=> $this->return_bool($this->auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel')),
			'postOk'			=> $this->return_bool($this->auth->acl_get('u_shout_post')),
			'limitPost'			=> $this->return_bool($this->auth->acl_get('u_shout_limit_post')),
			'smiliesOk'			=> $this->return_bool($this->auth->acl_get('u_shout_smilies')),
			'imageOk'			=> $this->return_bool($this->auth->acl_get('u_shout_image')),
			'colorOk'			=> $this->return_bool($this->auth->acl_get('u_shout_color')),
			'bbcodeOk'			=> $this->return_bool($this->auth->acl_get('u_shout_bbcode')),
			'charsOk'			=> $this->return_bool($this->auth->acl_get('u_shout_chars')),
			'popupOk'			=> $this->return_bool($this->auth->acl_get('u_shout_popup')),
			'formatOk'			=> $this->return_bool($this->auth->acl_get('u_shout_bbcode_change') && $is_user),
			'privOk'			=> $this->return_bool($this->auth->acl_get('u_shout_priv') && $is_user),
			'creator'			=> $this->return_bool($creator),
			'category'			=> $this->return_bool($category),
		);

		$i = 0;
		$list_settings_auth = "var config = {\n		";
		foreach ($settings_auth as $key => $value)
		{
			$list_settings_auth .= $key . ':' . $value . ', ';
			if ($i > 17)
			{
				$list_settings_auth .= "\n		";
				$i = 0;
			}
			$i++;
		}

		$settings_string = array(
			'cookieName'		=> $this->config['cookie_name'] . '_',
			'cookieDomain'		=> '; domain=' . $this->config['cookie_domain'] . ($this->config['cookie_secure'] ? '; secure' : ''),
			'cookiePath'		=> '; path=' . $this->config['cookie_path'],
			'sortBot'			=> $cookie_bot,
			'extensionUrl'		=> $this->ext_path_web,
			'userTimezone'		=> phpbb_format_timezone_offset($this->dt->getOffset()),
			'dateFormat'		=> $dateformat,
			'dateDefault'		=> $this->config['shout_dateformat'],
			'newSound'			=> $sound["new{$sort}"],
			'errorSound'		=> $sound['error'],
			'delSound'			=> $sound['del'],
			'addSound'			=> $sound['add'],
			'editSound'			=> $sound['edit'],
			'direction'			=> ($this->language->lang('DIRECTION') == 'ltr') ? 'left' : 'right', 
			'buttonBg'			=> 'button_background_' . $this->config["shout_color_background{$sort_p}"], 
			'shoutHeight'		=> ($sort_of == 2) ? $this->config['shout_height'] : $this->config["shout_non_ie_height{$sort_p}"],  
			'widthPost'			=> $this->config["shout_width_post{$sort_p}"]. 'px', 
			'popupWidth'		=> $this->config['shout_popup_width'], 
			'popupHeight'		=> $this->config['shout_popup_height'], 
			'endClassBg'		=> $this->config["shout_button_background{$sort_p}"] ? ' button_background_' .$this->config["shout_color_background{$sort_p}"] : '',
			'titleUrl'			=> $data['homepage'],
			'popupUrl'			=> $this->helper->route('sylver35_breizhshoutbox_controller_popup'),
			'configUrl'			=> $this->helper->route('sylver35_breizhshoutbox_controller_configshout'),
			'checkUrl'			=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => "check{$sort_p}")),
			'viewUrl'			=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => "view{$sort_p}")),
			'postUrl'			=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'post')),
			'smilUrl'			=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'smilies')),
			'smilPopUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'smilies_popup')),
			'onlineUrl'			=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'online')),
			'soundUrl'			=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'action_sound')),
			'rulesUrl'			=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'rules')),
			'postingUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'posting')),
			'creatorUrl'		=> ($creator) ? $this->helper->route('sylver35_smilecreator_controller') : '',
		);
		if ($is_user)
		{
			$settings_string = array_merge($settings_string, array(
				'privUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_private'),
				'purgeUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'purge')),
				'purgeBotUrl'	=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'purge_robot')),
				'actUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'action_user')), 
				'actPostUrl'	=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'action_post')), 
				'actDelUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'action_del')),
				'actDelToUrl'	=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'action_del_to')), 
				'actRemoveUrl'	=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'action_remove')),
				'citeUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'cite')),
				'ubbcodeUrl'	=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'user_bbcode')), 
				'persoUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'charge_bbcode')),
				'deleteUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'delete')),
				'editUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'edit')),
				'dateUrl'		=> $this->helper->route('sylver35_breizhshoutbox_controller_ajax', array('mode' => 'date_format')),
			));
		}

		$i = 0;
		$list_settings_string = "	";
		foreach ($settings_string as $key => $value)
		{
			$list_settings_string .= $key . ":'" . $value . "', ";
			if ($i > 9)
			{
				$list_settings_string .= "\n		";
				$i = 0;
			}
			$i++;
		}
		$list_settings_string .= "\n	};";

		$lang_shout = array(
			'LOADING'				=> $this->language->lang('SHOUT_LOADING'),
			'TITLESOUND'			=> $this->language->lang('SHOUT_CLICK_SOUND_' . ($sound['active'] ? 'OFF' : 'ON')),
			'TITLE'					=> $this->config["shout_title{$sort}"],
			'SERVER_ERR'			=> $this->language->lang('SERVER_ERR'),
			'JS_ERR'				=> $this->language->lang('JS_ERR'),
			'ERROR'					=> $this->language->lang('ERROR'),
			'LINE'					=> $this->language->lang('LINE'),
			'FILE'					=> $this->language->lang('FILE'),
			'DETAILS'				=> $this->language->lang('POST_DETAILS'),
			'PRINT_VER'				=> $this->language->lang('SHOUTBOX_VER', $data['version']), 
			'MESSAGE'				=> $this->language->lang('SHOUT_MESSAGE'),
			'MESSAGES'				=> $this->language->lang('SHOUT_MESSAGES'),
			'SEPARATOR'				=> $this->language->lang('COMMA_SEPARATOR'),
			'SHOUT_SEP'				=> $this->language->lang('SHOUT_SEP'),
			'MSG_DEL_DONE'			=> $this->language->lang('MSG_DEL_DONE'),
			'NO_MESSAGE'			=> $this->language->lang('SHOUT_NO_MESSAGE'),
			'PAGE'					=> $this->language->lang('SHOUT_PAGE'),
			'NO_EDIT'				=> $this->language->lang('NO_SHOUT_EDIT'),
			'CANCEL'				=> $this->language->lang('CANCEL'),
			'NEXT'					=> $this->language->lang('NEXT'),
			'PREVIOUS'				=> $this->language->lang('PREVIOUS'),
			'AUTO'					=> $this->language->lang('SHOUT_AUTO'),
			'BBCODE_CLOSE'			=> $this->language->lang('SHOUT_DIV_BBCODE_CLOSE'),
			'ACTION_MSG'			=> $this->language->lang('SHOUT_ACTION_MSG'),
			'OUT_TIME'				=> $this->language->lang('SHOUT_OUT_TIME'),
			'NO_SHOUT_DEL'			=> $this->language->lang('NO_SHOUT_DEL'),
			'NO_SHOW_IP_PERM'		=> $this->language->lang('NO_SHOW_IP_PERM'),
			'SOUND_ON'				=> $this->language->lang('SHOUT_CLICK_SOUND_ON'),
			'SOUND_OFF'				=> $this->language->lang('SHOUT_CLICK_SOUND_OFF'),
			'MESSAGE_EMPTY'			=> $this->language->lang('MESSAGE_EMPTY'),
			'DIV_CLOSE'				=> $this->language->lang('SHOUT_DIV_CLOSE'),
			'NO_POST_PERM'			=> $this->language->lang('NO_POST_PERM'),
			'NO_POP'				=> $this->language->lang('NO_SHOUT_POP'),
			'POST_MESSAGE'			=> $this->language->lang('POST_MESSAGE'),
			'POST_MESSAGE_ALT'		=> $this->language->lang('POST_MESSAGE_ALT'),
			'POSTED'				=> $this->language->lang('POSTED'),
			'POP'					=> $this->language->lang('SHOUT_POP'),
			'ONLINE'				=> $this->language->lang('SHOUT_ONLINE'),
			'ONLINE_CLOSE'			=> $this->language->lang('SHOUT_ONLINE_CLOSE'),
			'COLOR'					=> $this->language->lang('SHOUT_COLOR'),
			'NO_COLOR'				=> $this->language->lang('NO_SHOUT_COLOR'),
			'COLOR_CLOSE'			=> $this->language->lang('SHOUT_COLOR_CLOSE'),
			'SMILIES'				=> $this->language->lang('SMILIES'),
			'NO_SMILIES'			=> $this->language->lang('NO_SMILIES'),
			'SMILIES_CLOSE'			=> $this->language->lang('SMILIES_CLOSE'),
			'CHARS'					=> $this->language->lang('SHOUT_CHARS'),
			'CHARS_CLOSE'			=> $this->language->lang('SHOUT_CHARS_CLOSE'),
			'NO_CHARS'				=> $this->language->lang('NO_SHOUT_CHARS'),
			'RULES'					=> $this->language->lang('SHOUT_RULES'),
			'RULES_PRIV'			=> $this->language->lang('SHOUT_RULES_PRIV'),
			'RULES_CLOSE'			=> $this->language->lang('SHOUT_RULES_CLOSE'),
			'MORE_SMILIES'			=> $this->language->lang('SHOUT_MORE_SMILIES'),
			'MORE_SMILIES_ALT'		=> $this->language->lang('SHOUT_MORE_SMILIES_ALT'),
			'LESS_SMILIES'			=> $this->language->lang('SHOUT_LESS_SMILIES'),
			'LESS_SMILIES_ALT'		=> $this->language->lang('SHOUT_LESS_SMILIES_ALT'),
			'TOO_BIG'				=> $this->language->lang('SHOUT_TOO_BIG'),
			'TOO_BIG2'				=> $this->language->lang('SHOUT_TOO_BIG2'),
			'ACTION_CITE'			=> $this->language->lang('SHOUT_ACTION_CITE'),
			'CITE_ON'				=> $this->language->lang('SHOUT_ACTION_CITE_ON'),
			'SHOUT_CLOSE'			=> $this->language->lang('SHOUT_CLOSE'),
			'BBCODES'				=> $this->language->lang('SHOUT_BBCODES'),
			'BBCODES_CLOSE'			=> $this->language->lang('SHOUT_BBCODES_CLOSE'),
			'NO_BBCODE'				=> $this->language->lang('NO_SHOUT_BBCODE'),
			'SENDING'				=> $this->language->lang('SENDING'),
			'DATETIME_0'			=> $this->language->lang(['datetime', 'AGO', 0]),
			'DATETIME_1'			=> $this->language->lang(['datetime', 'AGO', 1]),
			'DATETIME_2'			=> $this->language->lang(['datetime', 'AGO', 2]),
			'DATETIME_3'			=> $this->language->lang(['datetime', 'TODAY']),
			'ROBOT_ON'				=> $this->language->lang('SHOUT_ROBOT_ON'),
			'ROBOT_OFF'				=> $this->language->lang('SHOUT_ROBOT_OFF'),
			'SMILIE_CREATOR'		=> ($creator) ? $this->language->lang('SMILIE_CREATOR') : '',
		);
		if (!$this->user->data['is_registered'])
		{
			$lang_shout = array_merge($lang_shout, array(
				'CLICK_HERE'			=> $this->language->lang('SHOUT_CLICK_HERE'),
				'CHOICE_NAME'			=> $this->language->lang('SHOUT_CHOICE_NAME'),
				'CHOICE_YES'			=> $this->language->lang('SHOUT_CHOICE_YES'),
				'NO_POST_PERM_GUEST'	=> $this->language->lang('NO_POST_PERM_GUEST'),
				'AFFICHE'				=> $this->language->lang('SHOUT_AFFICHE'),
				'CACHE'					=> $this->language->lang('SHOUT_CACHE'),
				'CHOICE_NAME_ERROR'		=> $this->language->lang('SHOUT_CHOICE_NAME_ERROR'),
				'USERNAME_EXPLAIN'		=> $this->language->lang($this->config['allow_name_chars'] . '_EXPLAIN', $this->language->lang('CHARACTERS', (int) $this->config['min_name_chars']), $this->language->lang('CHARACTERS', (int) $this->config['max_name_chars'])),
			));
		}
		else if ($is_user)
		{
			$lang_shout = array_merge($lang_shout, array(
				'MSG_ROBOT'				=> $this->language->lang('SHOUT_ACTION_MSG_ROBOT', $this->construct_action_shout(0)),
				'PERSO'					=> $this->language->lang('SHOUT_PERSO'),
				'SENDING_EDIT'			=> $this->language->lang('SENDING_EDIT'),
				'EDIT_DONE'				=> $this->language->lang('EDIT_DONE'),
				'SHOUT_DEL'				=> $this->language->lang('SHOUT_DEL'),
				'DEL_SHOUT'				=> $this->language->lang('DEL_SHOUT'),
				'IP'					=> $this->language->lang('SHOUT_IP'),
				'POST_IP'				=> $this->language->lang('SHOUT_POST_IP'),
				'ONE_OPEN'				=> $this->language->lang('ONLY_ONE_OPEN'),
				'EDIT'					=> $this->language->lang('EDIT'),
				'SHOUT_EDIT'			=> $this->language->lang('SHOUT_EDIT'),
				'PRIV'					=> $this->language->lang('SHOUT_PRIV'),
				'CONFIG_OPEN'			=> $this->language->lang('SHOUT_CONFIG_OPEN'),
				'PURGE_ROBOT_ALT'		=> $this->language->lang('SHOUT_PURGE_ROBOT_ALT'),
				'PURGE_ROBOT_BOX'		=> $this->language->lang('SHOUT_PURGE_ROBOT_BOX'),
				'PURGE_ALT'				=> $this->language->lang('SHOUT_PURGE_ALT'),
				'PURGE_BOX'				=> $this->language->lang('SHOUT_PURGE_BOX'),
				'PURGE_PROCESS'			=> $this->language->lang('PURGE_PROCESS'),
			));
		}

		$i = 0;
		$list_settings_lang = "var bzhLang = {\n		";
		foreach ($lang_shout as $key => $value)
		{
			$list_settings_lang .= "'" . $key . "':" . json_encode($value) . ', ';
			if ($i > 7)
			{
				$list_settings_lang .= "\n		";
				$i = 0;
			}
			$i++;
		}
		$list_settings_lang .= "\n	};";

		$this->template->assign_vars(array(
			'LIST_SETTINGS_AUTH'		=> $list_settings_auth,
			'LIST_SETTINGS_STRING'		=> $list_settings_string,
			'LIST_SETTINGS_LANG'		=> $list_settings_lang,
			'ON_SHOUT_DISPLAY'			=> true,
		));
	}
}
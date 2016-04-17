<?php
namespace lib\utility;

/** Option: handle options of project from db **/
class option
{
	/**
	 * this library get options from db only one times!
	 * v1.1
	 */

	// declare private static variable to save options
	private static $options;

	/**
	 * get options from db and return the result
	 * @param  [type]  $_key  [description]
	 * @param  string  $_type [description]
	 * @param  boolean $_meta [description]
	 * @return [type]         [description]
	 */
	public static function get($_key = null, $_type = 'value', $_meta = false)
	{
		// fetch records from database
		if(!self::$options)
		{
			self::$options = self::fetch();
		}

		$result  = [];

		// check condition for show best result
		if($_key === true)
		{
			$result = self::$options;
		}
		elseif($_key && isset(self::$options[$_key]))
		{
			if($_type && !($_type === true))
			{
				if(isset(self::$options[$_key][$_type]))
				{
					if($_meta)
					{
						if(isset(self::$options[$_key][$_type][$_meta]))
						{
							$result = self::$options[$_key][$_type][$_meta];
						}
						else
						{
							$result = null;
						}
					}
					else
					{
						$result = self::$options[$_key][$_type];
					}
				}
				else
					$result = null;
			}
			else
			{
				$result = self::$options[$_key];
			}
		}
		else
		{
			$result = null;
		}
		// var_dump($result);
		return $result;
	}


	/**
	 * fetch options from db then fix and return result
	 * @param  boolean $_pemissionDetails [description]
	 * @return [type]                     [description]
	 */
	public static function fetch()
	{
		// connect to default database
		\lib\db::connect(true);

		// set query string
		$qry =
		"SELECT `options`.*
			FROM `options`
			WHERE user_id IS NULL AND
				post_id IS NULL AND
				(
					option_cat like 'option%' OR
					option_cat like 'permissions'
				)";

		// run query and give result
		$result = @mysqli_query(\lib\db::$link, $qry);
		// if result is not mysqli result return false
		if(!is_a($result, 'mysqli_result'))
		{
			// no record exist
			return '#NA';
		}

		// fetch all records
		$result     = \lib\db::fetch_all($result);
		$permList   = [];
		$qry_result = null;


		foreach ($result as $key => $row)
		{
			// save permissions to query result
			if($row['option_cat'] == 'permissions')
			{
				// if status is enable
				if($row['option_status'] == 'enable')
				{
					$qry_result['permissions']['meta'][$row['option_key']]         = json_decode($row['option_meta'], true);
					$qry_result['permissions']['meta'][$row['option_key']]['id']   = $row['option_key'];
					$qry_result['permissions']['meta'][$row['option_key']]['name'] = $row['option_value'];


					// save current user permission as option permission value
					if(isset($_SESSION['user']['permission']) && $row['option_key'] == $_SESSION['user']['permission'])
					{
						$qry_result['permissions']['value'] = $row['option_key'];
					}
				}
			}
			else
			{
				$myValue  = $row['option_value'];
				$myMeta   = $row['option_meta'];
				$myStatus = $row['option_status'];
				if($myStatus === 'enable' || $myStatus === 'on' || $myStatus === 'active')
				{
					$myStatus = true;
				}
				else
				{
					$myStatus = false;
				}

				if(substr($myValue, 0,1) == '{')
				{
					$myValue = json_decode($myValue, true);
				}

				if(substr($myMeta, 0,1) == '{')
				{
					$myMeta = json_decode($myMeta, true);
				}

				// save result
				$qry_result[$row['option_key']] =
				[
					'value'  => $myValue,
					'meta'   => $myMeta,
					'status' => $myStatus
				];
			}

		}

		return $qry_result;
	}


	/**
	 * return permission detail of requested
	 * work with permission id or name
	 * @param  [type] $_id if empty return current user permission
	 * @return [type]      array contain permission detail
	 */
	public static function permission($_id = null)
	{
		$permission = [];
		// use current user permission if isset
		if(!$_id && isset($_SESSION['user']['permission']))
		{
			$_id = $_SESSION['user']['permission'];
		}
		// if user pass string of permission name search with name
		if(!is_numeric($_id))
		{
			$permission = self::permList();
			$_id        = array_search($_id, $permission);
		}
		// search in permisssions and get detail of it
		$permission = self::get('permissions', 'meta', $_id);
		// return result
		return $permission;
	}


	/**
	 * return the list of permission
	 * key is id of permission
	 * value is the name of permission
	 * @return [type] [description]
	 */
	public static function permList($_flip = false)
	{
		$permList = self::get('permissions', 'meta');
		if(!$permList)
			$permList = [];
		$permList = array_column($permList, 'name', 'id');
		if($_flip)
		{
			$permList = array_flip($permList);
		}

		return $permList;
	}


	/**
	 * return the list of contents exist in current project and addons
	 * @return [type] [description]
	 */
	public static function contentList($_addMain = false)
	{
		// get all content exist in saloos and current project
		$addons   = glob(addons. "content_*", GLOB_ONLYDIR);
		$project  = glob(root. "content_*",   GLOB_ONLYDIR);
		$contents = array_merge($addons, $project);
		$myList   = [];

		foreach ($contents as $myContent)
		{
			$myContent = preg_replace("[\\\\]", "/", $myContent);
			$myContent = substr( $myContent, ( strrpos( $myContent, "/" ) + 1) );
			$myContent = substr( $myContent, ( strrpos( $myContent, "_" ) + 1) );
			array_push($myList, $myContent);
		}
		if($_addMain)
		{
			array_push($myList, 'home');
			$myList = array_reverse($myList);
		}
		$myList = array_flip($myList);
		unset($myList['account']);
		$myList = array_flip($myList);

		return $myList;
	}


	/**
	 * return list of languages in current project
	 * read form folders exist in includes/languages
	 * @return [type] [description]
	 */
	public static function languages($_dir = false)
	{
		// detect languages exist in current project
		$langList = glob(dir_includes.'languages/*', GLOB_ONLYDIR);
		$myList   = ['en_US' => 'English'];
		foreach ($langList as $myLang)
		{
			$myLang     = preg_replace("[\\\\]", "/", $myLang);
			$myLang     = substr( $myLang, (strrpos($myLang, "/" )+ 1));
			$myLangName = $myLang;
			$myLangDir  = 'ltr';
			switch (substr($myLang, 0, 2))
			{
				case 'fa':
					$myLangName = 'Persian - فارسی';
					$myLangDir  = 'rtl';
					break;

				case 'ar':
					$myLangName = 'Arabic - العربية';
					$myLangDir  = 'rtl';
					break;

				case 'en':
					$myLangName = 'English';
					$myLangDir  = 'ltr';
					break;

				case 'de':
					$myLangName = 'Deutsch';
					break;


				case 'fr':
					$myLangName = 'French';
					break;
			}
			$myList[$myLang] = $myLangName;
		}

		if($_dir)
		{
			return $myLangDir;

		}
		return $myList;
	}


	/**
	 * set new record in options
	 * @param [array] $_args contain key and value of new record
	 */
	public static function set($_args)
	{
		$datarow =
		[
			'option_status' => 'enable',
		];

		// add option user if set
		if(isset($_args['user']))
		{
			$op_user = $_args['user'];
			if($op_user === true)
			{
				$op_user = \lib\utility\visitor::user_id(false);
				if(!$op_user)
				{
					return false;
				}
			}
			if($op_user)
			{
				$datarow['user_id'] = $op_user;
			}
		}

		// add option post if set
		if(isset($_args['post']))
		{
			$datarow['post_id'] = $_args['post'];
		}

		// add option cat if set
		if(isset($_args['cat']))
		{
			$datarow['option_cat'] = $_args['cat'];
		}
		else
		{
			return false;
		}

		// add option key if set
		if(isset($_args['key']))
		{
			// replace _USER_ with user_id
			$_args['key'] = str_replace('_USER_', \lib\utility\visitor::user_id(false), $_args['key']);

			$datarow['option_key'] = $_args['key'];
		}
		else
		{
			return false;
		}

		// add option value if set
		if(isset($_args['value']))
		{
			$datarow['option_value'] = $_args['value'];
		}

		// add option meta if set
		if(isset($_args['meta']))
		{
			$datarow['option_meta'] = $_args['meta'];
		}

		// add option status if set
		if(isset($_args['status']))
		{
			// only allow defined$_args['status'])e
			switch ($op_status)
			{
				case 'enable':
				case 'disable':
				case 'expire':
					break;

				default:
					$op_status = 'enable';
					break;
			}
			$datarow['option_status'] = $op_status;
		}

		// create query string
		$qry_fields = implode(', ', array_keys($datarow));
		foreach ($datarow as $key => $value)
		{
			$datarow[$key] = "'". $value. "'";
		}
		$qry_values = implode(', ', $datarow);
		// create query string
		$qry = "INSERT INTO options ( $qry_fields ) VALUES ( $qry_values );";
		// connect to database
		\lib\db::connect(true);
		// execute query
		$result = @mysqli_query(\lib\db::$link, $qry);
		// give last insert id
		$last_id = @mysqli_insert_id(\lib\db::$link);
		// if have last insert it return it
		if($last_id)
		{
			return $last_id;
		}
		// return default value
		return false;
	}
}
?>
<?php
namespace lib\utility\telegram;

/** telegram **/
class tg
{
	/**
	 * this library get and send telegram messages
	 * v4.4
	 */
	public static $text;
	public static $chat_id;
	public static $message_id;
	public static $replyMarkup;
	public static $api_key     = null;
	public static $saveLog     = true;
	public static $response    = null;
	public static $callback    = false;
	public static $cmd         = null;
	public static $cmdFolder   = null;
	public static $useSample   = null;
	public static $method      = 'sendMessage';
	public static $defaultText = 'Undefined';
	public static $answer      = null;

	public static $priority    =
	[
		'callback',
		'menu',
		'user',
		'simple',
		'conversation',
	];


	/**
	 * hook telegram messages
	 * @param  boolean $_save [description]
	 * @return [type]         [description]
	 */
	public static function hook()
	{
		// if telegram is off then do not run
		if(!\lib\utility\option::get('telegram', 'status'))
			return 'telegram is off!';
		$message = json_decode(file_get_contents('php://input'), true);
		self::saveLog($message);
		self::$response = $message;
		return $message;
	}


	/**
	 * handle response and return needed key if exist
	 * @param  [type] $_needle [description]
	 * @return [type]          [description]
	 */
	public static function response($_needle = null, $_arg = 'id')
	{
		$data = null;

		switch ($_needle)
		{
			case 'update_id':
				if(isset(self::$response['update_id']))
				{
					$data = self::$response['update_id'];
				}
				break;

			case 'message_id':
				if(isset(self::$response['message']['message_id']))
				{
					$data = self::$response['message']['message_id'];
				}
				elseif(isset(self::$response['callback_query']['message']['message_id']))
				{
					$data = self::$response['callback_query']['message']['message_id'];
				}
				break;

			case 'callback_query_id':
				if(isset(self::$response['callback_query']['id']))
				{
					$data = self::$response['callback_query']['id'];
				}
				break;

			case 'from':
				if(isset(self::$response['message']['from']))
				{
					$data = self::$response['message']['from'];
				}
				elseif(isset(self::$response['callback_query']['from']))
				{
					$data = self::$response['callback_query']['from'];
				}
				if($_arg)
				{
					$data = $data[$_arg];
				}
				break;

			case 'chat':
				if(isset(self::$response['message']['chat']))
				{
					$data = self::$response['message']['chat'];
				}
				elseif(isset(self::$response['callback_query']['message']['chat']))
				{
					$data = self::$response['callback_query']['message']['chat'];
				}
				if($_arg)
				{
					$data = $data[$_arg];
				}
				break;

			case 'text':
				if(isset(self::$response['message']['text']))
				{
					$data = self::$response['message']['text'];
				}
				elseif(isset(self::$response['callback_query']['data']))
				{
					$data = 'cb_'.self::$response['callback_query']['data'];
				}
				break;

			default:
				break;
		}

		return $data;
	}


	/**
	 * handle tg requests
	 * @return [type] [description]
	 */
	public static function handle()
	{
		// run hook and get it
		self::hook();
		// detect cmd and save it in static value
		self::cmd();
		// extract chat_id if not exist return false
		self::$chat_id = self::response('chat');
		// define variables
		// call debug handler function
		self::debug_handler();
		// generate response from defined commands
		self::generateResponse();
		if(!self::$answer && self::$useSample)
		{
			self::generateResponse(true);
		}
		// send response and return result of it
		return self::sendResponse();
	}


	/**
	 * generate response and sending message
	 * @return [type] result of sending
	 */
	public static function sendResponse($_text = null, $_chat = null)
	{
		// if text is not set use user passed text
		if($_text)
		{
			self::$text = $_text;
		}
		// uf chat id is not set use user passed chat
		if($_chat)
		{
			self::$chat_id = $_chat;
		}
		// if chat or text is not set return false
		// if(!self::$chat_id || !self::$text)
		// {
		// 	return false;
		// }
		// generate data for response

		// set method if user wan to set it
		if(isset(self::$answer['method']))
		{
			self::$method = self::$answer['method'];
			unset(self::$answer['method']);
		}

		switch (self::$method)
		{
			// create send message format
			case 'sendMessage':
				// require chat id
				self::$answer['chat_id']    = self::$chat_id;
				// markdown is enable by default
				self::$answer['parse_mode'] = 'markdown';

				// create markup if exist
				if(self::$replyMarkup)
				{
					self::$answer['reply_markup'] = json_encode(self::$replyMarkup);
					self::$answer['force_reply'] = true;
				}
				else
				{
					self::$answer['reply_markup'] = null;
				}
				// add reply message id
				if(self::response('message_id'))
				{
					self::$answer['reply_to_message_id'] = self::response('message_id');
				}
				// for callbacks dont use reply message and only do work
				if(self::$callback)
				{
					unset(self::$answer['reply_to_message_id']);
					// $data['inline_message_id'] = $hook['callback_query']['id'];
					// $result = self::editMessageText($data);
					// fix it to work on the fly
				}
				break;


			case 'editMessageText':
				self::$answer['chat_id']    = self::$chat_id;
				self::$answer['message_id'] = self::response('message_id');
				self::$answer['parse_mode'] = 'markdown';
				// if callback is set then call one callback
				if(isset(self::$answer['callback']) && isset(self::$answer['callback']['text']))
				{
					// generate callback query
					$data =
					[
						'callback_query_id' => self::response('callback_query_id'),
						'text'              => self::$answer['callback']['text'],
					];
					if(isset(self::$answer['callback']['show_alert']))
					{
						$data['show_alert'] = self::$answer['callback']['show_alert'];
					}
					// call callback answer
					self::answerCallbackQuery($data);
				}

				break;

			default:
				break;
		}

		// call bot send message func
		$funcName = 'self::'. self::$method;
		$result   = call_user_func($funcName);
		// return result of sending
		return $result;
	}


	/**
	 * default action to handle message texts
	 * @param  [type] [description]
	 * @return [type]       [description]
	 */
	private static function generateResponse($forceSample = null)
	{
		$response  = null;
		// read from saloos command template
		$cmdFolder = __NAMESPACE__ .'\commands\\';

		// use user defined command
		if(!$forceSample && self::$cmdFolder)
		{
			$cmdFolder = self::$cmdFolder;
		}
		foreach (self::$priority as $class)
		{
			$funcName = $cmdFolder. $class.'::exec';
			// generate func name
			if(is_callable($funcName))
			{
				// get response
				self::$answer = call_user_func($funcName, self::$cmd);
				// if has response break loop
				if(self::$answer)
				{
					if($class === 'callback')
					{
						self::$callback = true;
					}
					break;
				}
			}
		}
		// call set response func
		self::setResponse(self::$answer);
		// if has response return true
		if(self::$answer)
		{
			return true;
		}
	}


	private static function setResponse($_response, $_useDefault = true)
	{
		// if does not have response return default text
		if(!$_response && $_useDefault && \lib\utility\option::get('telegram', 'meta', 'debug'))
		{
			// then if not exist set default text
			$_response = ['text' => self::$defaultText];
		}

		// set text if exist
		if(isset($_response['text']))
		{
			self::$text = $_response['text'];
		}
		// set replyMarkup if exist
		if(isset($_response['replyMarkup']))
		{
			self::$replyMarkup = $_response['replyMarkup'];
		}
	}


	/**
	 * debug mode give data from user
	 * @return [type] [description]
	 */
	public static function debug_handler()
	{
		if(\lib\utility\option::get('telegram', 'meta', 'debug'))
		{
			if(!self::$chat_id)
			{
				self::$chat_id = \lib\utility::get('id');
				if(!self::$cmd['text'])
				{
					self::$cmd = self::cmd(\lib\utility::get('text'));
				}
			}
		}
	}


	/**
	 * seperate input text to command
	 * @param  [type] $_input [description]
	 * @return [type]         [description]
	 */
	public static function cmd($_input = null)
	{
		// define variable
		$cmd =
		[
			'text'  => null,
			'command'  => null,
			'optional' => null,
			'argument' => null,
		];
		// if user dont pass input string use response text
		if(!$_input)
		{
			$_input = self::response('text');
		}
		$cmd['text'] = $_input;
		$text = explode(' ', $_input);
		if(isset($text[0]))
		{
			$cmd['command'] = $text[0];
			if(isset($text[1]))
			{
				$cmd['optional'] = $text[1];
				if(isset($text[2]))
				{
					$cmd['argument'] = $text[2];
				}
			}
		}
		self::$cmd = $cmd;
		// return analysed text given from user
		return $cmd;
	}


	/**
	 * save log of process into file
	 * @param  [type] $_data [description]
	 * @return [type]        [description]
	 */
	private static function saveLog($_data)
	{
		if(self::$saveLog)
		{
			file_put_contents('tg.json', json_encode($_data). "\r\n", FILE_APPEND);
		}
	}

	/**
	 * setWebhook for telegram
	 * @param string $_url  [description]
	 * @param [type] $_file [description]
	 */
	public static function setWebhook($_url = '', $_file = null)
	{
		if(empty($_url))
		{
			$_url = \lib\utility\option::get('telegram', 'meta', 'hook');
		}
		self::$answer = ['url' => $_url];
		self::$method = 'setWebhook';
		// if (!is_null($_file))
		// {
		// 	$data['certificate'] = \CURLFile($_file);
		// }
		return self::executeCurl('setWebhook', 'description') .': '. $_url;
	}


	/**
	 * execute telegram method
	 * @param  [type] $_name [description]
	 * @param  [type] $_args [description]
	 * @return [type]        [description]
	 */
	static function __callStatic($_name, $_args)
	{
		if(isset($_args[0]))
		{
			$_args = $_args[0];
		}
		return self::executeCurl($_name, $_args);
	}


	/**
	 * Execute cURL call
	 * @return mixed Result of the cURL call
	 */
	public static function executeCurl($_method = null, array $_data = null, $_output = null)
	{
		// if telegram is off then do not run
		if(!\lib\utility\option::get('telegram', 'status'))
			return 'telegram is off!';
		// get custom api key in custom conditon
		if(isset(self::$api_key) && self::$api_key)
		{
			$mykey = self::$api_key;
		}
		else
		{
			$mykey = \lib\utility\option::get('telegram', 'meta', 'key');
			// get key and botname
			// $mybot = \lib\utility\option::get('telegram', 'meta', 'bot');

		}
		// if key is not correct return
		if(strlen($mykey) < 20)
		{
			return 'api key is not correct!';
		}
		// if method is not set use global method
		if(!$_method)
		{
			$_method = self::$method;
		}
		// if data is not set use global answer
		if(!$_data)
		{
			$_data = self::$answer;
		}


		$ch = curl_init();
		if ($ch === false)
		{
			return 'Curl failed to initialize';
		}

		$curlConfig =
		[
			CURLOPT_URL            => "https://api.telegram.org/bot$mykey/$_method",
			CURLOPT_POST           => true,
			CURLOPT_RETURNTRANSFER => true,
			// CURLOPT_HEADER         => true, // get header
			CURLOPT_SAFE_UPLOAD    => true,
			CURLOPT_SSL_VERIFYPEER => false,
		];
		curl_setopt_array($ch, $curlConfig);

		if (!empty($_data))
		{
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($_data));
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
		}
		if(Tld === 'dev')
		{
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}

		$result = curl_exec($ch);
		if ($result === false)
		{
			return curl_error($ch). ':'. curl_errno($ch);
		}
		if (empty($result) | is_null($result))
		{
			return 'Empty server response';
		}
		curl_close($ch);
		//Logging curl requests
		if(substr($result, 0,1) === "{")
		{
			$result = json_decode($result, true);
			if($_output && isset($result[$_output]))
			{
				$result = $result[$_output];
			}
		}
		self::saveLog($result);
		// return result
		return $result;
	}
}
?>
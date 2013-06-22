<?php
class WebSocketClient
{
	public $id = -1;
	public $upgraded = false;
	public $sock = null;
	public $version = '';
	public $server = null;
	public $host = null;
	public $headers = array();
	public $COOKIE = array();
	public $SESSION = array();
	
	final public function sendData($payload)
	{
		if($this->version == 13)
		{
			$paylen = strlen($payload);
			
			if($paylen <= 125)
				$frame = pack('C2a*',  bindec('10000001'), $paylen, $payload);
			elseif($paylen < 65536)
				$frame = pack('C2na*', bindec('10000001'), 126, $paylen, $payload);
			elseif (PHP_INT_MAX > 2147483647)
			{
				$frame = pack('C2N2a*', bindec('10000001'), 127, $paylen >> 32, $paylen, $payload);
			}
			else
			{
				$frame = pack('C2N2a*', bindec('10000001'), 127,0,$paylen, $payload);
			}
		}
		elseif($this->version == 'hybi-00')
		{
			$frame = chr(0).$payload.chr(255);
		}
		else
		{
			$frame = $payload;
		}
		$res = socket_write($this->sock, $frame);
	}
	
	final public function broadcastData($payload, $excludeMe = true)
	{
		for($i = 0; $i < count($this->server->clients); $i++)
		{
			if(!$excludeMe || $this->server->clients[$i]->id != $this->id)
			if($this->server->clients[$i]->sock != null)
			{
				if($this->server->clients[$i]->version == 13)
				{
					$paylen = strlen($payload);
					if($paylen <= 125)
						$frame = pack('C2a*',  bindec('10000001'), $paylen, $payload);
					elseif($paylen < 65536)
						$frame = pack('C2na*', bindec('10000001'), 126, $paylen, $payload);
					elseif (PHP_INT_MAX > 2147483647)
					{
						$frame = pack('C2N2a*', bindec('10000001'), 127, $paylen >> 32, $paylen, $payload);
					}
					else
					{
						$frame = pack('C2N2a*', bindec('10000001'), 127,0,$paylen, $payload);
					}
				}
				elseif($this->server->clients[$i]->version == 'hybi-00')
				{
					$frame = chr(0).$payload.chr(255);
				}
				else
					$frame = $payload;
				$res = socket_write($this->server->clients[$i]->sock, $frame);
			}
		}
	}
}

class WebSocketServer
{
	private $host='';
	private $port=0;
	protected $max_clients=0;
	protected $db;
	public $clients = array();
	protected $client_object_name='WebSocketClient';
	protected $FLASH_POLICY_FILE = "<cross-domain-policy><allow-access-from domain=\"*\" to-ports=\"*\" /></cross-domain-policy>\0";
	
	final function __construct($host, $port, $max_clients=20,$db=null)
	{
		$this->host = $host;
		$this->port = $port;
		$this->max_clients = $max_clients;
		if(isset($db) AND $db != null)
		{
			$this->db = $db;
		}
		$this->FLASH_POLICY_FILE = str_replace('to-ports="*','to-ports="'.$port, $this->FLASH_POLICY_FILE);
		// No timeouts, flush content immediatly
		error_reporting(E_ERROR);
		set_time_limit(0);
		ob_implicit_flush();
	}
	
	final public function setClientObject($client_object_name)
	{
		$this->client_object_name = $client_object_name;
	}
	
	final private function doHandshake($client,$strHandshake)
	{
		$headers = $this->getHeaders($strHandshake);
		if(isset($headers['Sec-WebSocket-Version']) && $headers['Sec-WebSocket-Version']==13)
		{
			// Standards compliant (Chrome, Safari)
			$accept_key = $headers['Sec-WebSocket-Key'].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
			$accept_key = sha1($accept_key, true);
			$accept_key = base64_encode($accept_key);

			$upgrade = 'HTTP/1.1 101 Switching Protocols'."\r\n".
						'Upgrade: websocket'."\r\n" .
						'Connection: Upgrade'."\r\n" .
						'Sec-WebSocket-Accept: '.$accept_key."\r\n\r\n";
			$client->upgraded = true;
			$client->version = $headers['Sec-WebSocket-Version'];
		}
		else
		{
			//hybi-00 (Opera, wtf guys?)
			$key = md5(pack('N', $this->_doStuffToObtainAnInt32($headers['Sec-WebSocket-Key1'])).
						pack('N', $this->_doStuffToObtainAnInt32($headers['Sec-WebSocket-Key2'])).
						base64_decode($headers['request_content']),true);
			$upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
						"Upgrade: WebSocket\r\n" .
						"Connection: Upgrade\r\n" .
						"Sec-WebSocket-Origin: ".$headers['Origin']."\r\n" .
						"Sec-WebSocket-Location: ws://".$headers['Host']."/\r\n" .
						"\r\n".$key;
			$client->upgraded = true;
			$client->version = 'hybi-00';
		}
		$client->headers = $headers;
		$client->COOKIE = http_parse_cookie($headers['Cookie']);
		$client->COOKIE = $client->COOKIE->cookies;
		$client->SESSION = session_save_path().'/sess_'.$client->COOKIE['PHPSESSID'];
		if(file_exists($client->SESSION))
		{
			session_start();
			session_decode(file_get_contents($client->SESSION));
			$client->SESSION = $_SESSION;
		}
		socket_write($client->sock, $upgrade,strlen($upgrade))
			or die("Could not write output(handshake)\n");
		$this->onConnect($client);
	}
	
	final private function _doStuffToObtainAnInt32($key)
	{
		preg_match_all('#[0-9]#', $key, $number);
		preg_match_all('# #', $key, $space);
		return implode('', $number[0]) / count($space[0]);
	}
	
	final private function getHeaders($str_header)
	{
		$ret = array();
		$content = explode("\r\n\r\n", $str_header);
		if(count($content) > 1)
		{
			$str_header = $content[0];
			$ret['request_content'] = base64_encode($content[1]);
		}
		
		$arr_header = explode("\r\n", $str_header);
		$req = array_shift($arr_header);

		foreach($arr_header as $header)
		{
			$header = explode(': ', trim($header));
			$ret[$header[0]] = $header[1];
		}
		return $ret;
	}
	
	final private function processFrame($client,$rawframe)
	{
		if($client->version == 13)
		{
			// Peek the frames header.
			$frame = unpack('C2Header', $rawframe);
			$frame['FIN'] =		(bindec('10000000') & $frame['Header1']) >> 7;
			$frame['RSV1'] =	(bindec('01000000') & $frame['Header1']) >> 6;
			$frame['RSV2'] =	(bindec('00100000') & $frame['Header1']) >> 5;
			$frame['RSV3'] =	(bindec('00010000') & $frame['Header1']) >> 4;
			$frame['Opcode'] =	(bindec('00001111') & $frame['Header1']);
			$frame['Mask'] =	(bindec('10000000') & $frame['Header2']) >> 7;
			$frame['Length'] =	(bindec('01111111') & $frame['Header2']);
			unset($frame['Header1'], $frame['Header2']);
			// Parse frame
			switch ($frame['Length'])
			{
				case 126:
					$frame = array_merge($frame, unpack("x2/nLength/a4Masking/a*Payload", $rawframe));
					break;
				case 127:
					$frame = array_merge($frame, unpack("x2/n4Length/a4Masking/a*Payload", $rawframe));
					$frame['Length'] = $frame['Length1'] . $frame['Length2'] . $frame['Length3'] . $frame['Length4'];
					unset($frame['Length1'], $frame['Length2'], $frame['Length3'], $frame['Length4']);
					break;
				default:
					$frame = array_merge($frame, unpack("x2/a4Masking/a*Payload", $rawframe));
					break;
			}
			# Unmask Payload
			for ($i = 0, $k = strlen($frame['Payload']); $i < $k; ++$i)
				$frame['Payload'][$i] = $frame['Payload'][$i] ^ $frame['Masking'][$i % 4];

			$this->onMessage($client,$frame['Payload']);
		}
		else
		{
			$this->onMessage($client,str_replace(chr(0), '', str_replace(chr(255), '', $rawframe)));
		}
	}
	
	public function onMessage($client,$msg)
	{
	}
	
	public function onTick($client)
	{
	}
	
	public function onConnect($client)
	{
	}
	
	public function onDisconnect($client)
	{
	}
	
	public function sendData($client, $payload, $binary = FALSE)
	{
		if($client->version == 13)
		{
			$paylen = strlen($payload);
			
			if($paylen <= 125)
				$frame = pack('C2a*',  bindec('10000001'), $paylen, $payload);
			elseif($paylen < 65536)
				$frame = pack('C2na*', bindec('10000001'), 126, $paylen, $payload);
			elseif (PHP_INT_MAX > 2147483647)
			{
				$frame = pack('C2N2a*', bindec('10000001'), 127, $paylen >> 32, $paylen, $payload);
			}
			else
			{
				$frame = pack('C2N2a*', bindec('10000001'), 127,0,$paylen, $payload);
			}
		}
		elseif($client->version == 'hybi-00')
		{
			$frame = chr(0).$payload.chr(255);
		}
		else
			$frame = $payload;
		$res = socket_write($client->sock, $frame);
	}
	
	public function broadcastData($payload, $binary = FALSE)
	{
		for($i = 0; $i < count($this->clients); $i++)
		{
			if($this->clients[$i]->sock != null)
			{
				if($this->clients[$i]->version == 13)
				{
					$paylen = strlen($payload);
					if($paylen <= 125)
						$frame = pack('C2a*',  bindec('10000001'), $paylen, $payload);
					elseif($paylen < 65536)
						$frame = pack('C2na*', bindec('10000001'), 126, $paylen, $payload);
					elseif (PHP_INT_MAX > 2147483647)
					{
						$frame = pack('C2N2a*', bindec('10000001'), 127, $paylen >> 32, $paylen, $payload);
					}
					else
					{
						$frame = pack('C2N2a*', bindec('10000001'), 127,0,$paylen, $payload);
					}
				}
				elseif($client->version == 'hybi-00')
		                {
					$frame = chr(0).$payload.chr(255);
				}
				else
					$frame = $payload;
				$res = socket_write($this->clients[$i]->sock, $frame);
			}
		}
	}
	
	final public function start()
	{
		// Create socket
		$sock = socket_create(AF_INET,SOCK_STREAM,0)
			or die("[".date('Y-m-d H:i:s')."] Could not create socket\n");
		socket_set_option ($sock, SOL_SOCKET, SO_REUSEADDR, 1); 
		// Bind to socket
		socket_bind($sock,$this->host,$this->port)
			or die("[".date('Y-m-d H:i:s')."] Could not bind to socket\n");
		// Start listening
		socket_listen($sock)
			or die("[".date('Y-m-d H:i:s')."] Could not set up socket listener\n");

		rLog("Server started at ".$host.":".$port);
		// Server loop
		while(true)
		{
			socket_set_nonblock($sock);
			// Setup clients listen socket for reading
			$read[0] = $sock;
			for($i = 0; $i<$this->max_clients; $i++)
			{
				if($this->clients[$i]->sock != null)
				{
					$read[$i+1] = $this->clients[$i]->sock;
					if($this->clients[$i]->upgraded === true)
					{
						$this->onTick($this->clients[$i]);
					}
				}
			}
			// Set up a blocking call to socket_select()
			$ready = socket_select($read, $write = NULL, $except = NULL, $tv_sec = 0, $tv_usec = 10000);
			// If a new connection is being made add it to the clients array
			if(in_array($sock,$read))
			{
				for($i = 0;$i<$this->max_clients;$i++)
				{
					if($this->clients[$i]->sock==null)
					{
						$this->clients[$i] = new $this->client_object_name();
						if(($this->clients[$i]->sock = socket_accept($sock))<0)
						{
							rLog("socket_accept() failed: ".socket_strerror($this->clients[$i]->sock));
						}
						else
						{
							rLog("Client #".$i." connected");
							$this->clients[$i]->id = $i;
							$this->clients[$i]->server = &$this;
							$this->clients[$i]->host = socket_getpeername($this->clients[$i]->sock);
						}
						break;
					}
					elseif($i == $this->max_clients - 1)
					{
						rLog("Too many clients");
					}
				}
				if(--$ready <= 0)
				continue;
			}
			for($i=0;$i<$this->max_clients;$i++)
			{
				if(in_array($this->clients[$i]->sock,$read))
				{
					$input = socket_read($this->clients[$i]->sock,1024);
					if($input==null)
					{
						unset($this->clients[$i]);
						continue;
					}

					$n = trim($input);
					if($n == '<policy-file-request/>')
						socket_write($this->clients[$i]->sock, $this->FLASH_POLICY_FILE);
					else
					{
						if(!$this->clients[$i]->upgraded && substr($n, 0, 4) == 'GET ')
						{
							$this->doHandshake($this->clients[$i],$n);
						}
						else
						{
							$this->processFrame($this->clients[$i],$n);
						}
					}
				}
			}
		}
		// Close the master sockets
		socket_close($sock);
	}
}

function rLog($msg)
{
	$msg = "[".date('Y-m-d H:i:s')."] ".$msg;
	print($msg."\n");
}
?>
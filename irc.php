<?php
error_reporting(E_ALL);
include('websocket_server.class.php');

class myServer extends WebSocketServer
{
	public function onMessage($client,$msg)
	{
		echo 'Sending "'.$msg.'" to remote server.'."\r\n";
		if($client->outsock == null)
		{
			$client->sendData('error, not connected to remote server');
			return;
		}
		echo 'Sending "'.$msg.'" to remote server.'."\r\n";
		fwrite($client->outsock, $msg."\r\n");
	}
	
	public function onConnect($client)
	{
		$client->Connect();
	}
	
	public function onTick($client)
	{
		$buf = fgets($client->outsock, 100);
		if($buf)
		{
			echo ':'.$buf."\n";
			$client->sendData(utf8_encode($buf));
		}
	}
	
	public function onDisconnect($client)
	{
		//fsockclose($client->outsock);
	}
}

class myClient extends WebSocketClient
{
	public $outsock=null;
	
	public function Connect()
	{
		echo 'Connecting to irc..'."\n";
		$this->outsock = fsockopen('irc.homelien.no', 6667, $errno, $errstr, 1);
		if($this->outsock)
		{
			stream_set_timeout($this->outsock, 0, 100000);
			echo 'Connected (presumably)'."\n";
		}
	}
}

// Configuration variables
$host = '10.58.10.175';
$port = 4041;

$wsServer = new myServer($host,$port);
$wsServer->setClientObject('myClient');
$wsServer->start();

// Server functions
function rLog($msg){
             $msg = "[".date('Y-m-d H:i:s')."] ".$msg;
             print($msg."\n");
}
?>
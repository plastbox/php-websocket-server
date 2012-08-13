<?php
include('websocket_server.class.php');

class myServer extends WebSocketServer
{
	public function onMessage($client,$msg)
	{
		$com = explode(':', trim($msg));
		switch($com[0])
		{
			case 'nick':
				$client->sendData('You are now knows as '.$com[1]);
				$client->broadcastData($client->nick.' is now known as '.$com[1]);
				$client->nick = $com[1];
				break;
			default:
				$client->sendData('You said: '.$msg);
				$client->broadcastData($client->nick.' said: '.$msg);
				break;
		}
	}
	
	public function onConnect($client)
	{
		$client->nick = 'Guest #'.$client->id;
		$client->broadcastData($client->nick.' has connected.');
	}
	
	public function onDisconnect($client)
	{
		$client->broadcastData($client->nick.' has disconnected.');
	}
}

class myClient extends WebSocketClient
{
	public $nick = 'Guest';
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
<?php
include('websocket_server.class.php');

class myServer extends WebSocketServer
{
	public function onMessage($client,$msg)
	{
		for($i = 0; $i < $this->max_clients; $i++)
			if($this->clients[$i]->sock != null)
			{
				if($i == $client->id)
					$this->sendData($this->clients[$i], 'You said: '.$msg);
				else
					$this->sendData($this->clients[$i], $client->id.' said: '.$msg);
			}
	}
	
	public function onConnect($client,$msg)
	{
		for($i = 0; $i < $this->max_clients; $i++)
			if($this->clients[$i]->sock != null && $i != $client->id)
				$this->sendData($this->clients[$i], $client->id.' has connected.');
	}
	
	public function onDisconnect($client,$msg)
	{
	}
}

// Configuration variables
$host = '10.58.10.175';
$port = 4041;

$wsServer = new myServer($host,$port);
$wsServer->start();

// Server functions
function rLog($msg){
             $msg = "[".date('Y-m-d H:i:s')."] ".$msg;
             print($msg."\n");



?>

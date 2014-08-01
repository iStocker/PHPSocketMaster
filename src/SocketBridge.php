<?php

class SocketBridge extends SocketMaster implements iSocketBridge
{
	private $obj = null;

	public function __construct($socket, SocketEventReceptor $callback) 
	{ 
		$this->obj = $callback;
		$this->obj->setMother($this);
		$this->socketRef = $socket; 
		$this->onConnect(); 
	}

	private function onError($errorMessage)
	{
		if($obj == null) throw new exception('Not Set Callback in Socket Bridge');
		$this->obj->onError($errorMessage);
	}

	private function onConnect()
	{
		if($obj == null) throw new exception('Not Set Callback in Socket Bridge');
		$this->obj->onConnect();
	}

	private function onDiconnect()
	{
		if($obj == null) throw new exception('Not Set Callback in Socket Bridge');
		$this->obj->onDisconnect();
	}

	private function onReceiveMessage($message)
	{
		$this->obj->onReceiveMessage($message);
	}

}
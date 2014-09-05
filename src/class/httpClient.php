<?php namespace PHPSocketMaster;

/**
 * @class httpClient
 * @author Alexander
 * @version 1.0
 * clase dise�ada para hacer peticiones http a cualquier server
 * http.
 *
 * @example proximamente
 */

define('HTTP_GET', 'GET');
define('HTTP_POST', 'POST');
define('HCNL', "\r\n");

class httpClient implements iHttpClient
{
	use Property, Singleton;
	
	private $socket = null;
	private $saveHeaders = true;
	private $response = null;
	private $cookies = null;
	private $webpage = '';
	private $protocolHeader = 'http';
	private $version = '1.1';
	private $eof = false;
	private $first = true;
	private $lastResource = null;
	private $DefaultPort = 80;
	private $contentType = 'application/x-www-form-urlencoded';
	
	/**
	 * para crear el objeto usar el factory
	 * Function __construct
	 * @param SocketMaster $socket
	 * @param bool $saveHeaders
	 */
	private function __construct($webpage, $saveHeaders = true)
	{
		$this->socket = new HTTPSocketMaster($webpage, $this->DefaultPort);
		$this->saveHeaders = $saveHeaders;
		$this->webpage = $webpage;
	}
	
	public function get($resources, $params, $headers = array('User-Agent' => 'Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0', 'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Lenguaje' => 'es-ar,es;q=0.8,en-us;q=0.5,en;q=0.3', 'Connection' => 'keep-alive'))
	{
		// set eof as false
		$this->eof = false;
		// set first as true
		$this->first = true;
		// set me instance
		$this->socket->set_httpClient(self::get_instance());
		
		$res = null;
		$first = true;
		// agregamos ademas del host las cookies
		if(!empty($this->cookies)) $headers['Cookie'] = $this->implodeCookies($this->cookies);
		// agregamos el host
		$headers['Host'] = $this->webpage;
		// generamos la nueva peticion con variables
		if(!empty($params))
			foreach($params as $param => $val)
			{
				if($first == true)
				{
					$first = false;
					$res .= '?'.urlencode(trim($param)).'='.urlencode($val);	
				} else {
					$res .= '&'.urlencode(trim($param)).'='.urlencode($val);
				}	
			}
		// hacemos la conexion mandando la peticion
		$headers = $this->generateHeaders($this->protocolHeader.'://'.$this->webpage.'/'.$res, null, $headers, HTTP_GET);
		$this->lastResource = $res; 
		$this->socket->connect();
		$this->socket->send($headers, false);
		
		// esperamos la respuesta
		while($this->eof === false)
		{
			$this->socket->refresh();
		}
		
		// no es necesario volver a crear otro objeto, se puede desconectar
		// no recordaba que lo hab�a dise�ado as�
		$this->socket->disconnect();
	}	
	
	public function post($resources, $params, $headers = array('User-Agent' => 'Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0', 'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Lenguaje' => 'es-ar,es;q=0.8,en-us;q=0.5,en;q=0.3', 'Connection' => 'keep-alive'))
	{
		// set eof as false
		$this->eof = false;
		// set first as true
		$this->first = true;
		// set me instance
		$this->socket->set_httpClient(self::get_instance());
		// agregamos ademas del host las cookies
		if(!empty($this->cookies)) $headers['Cookie'] = $this->implodeCookies($this->cookies);
		// agregamos el host
		$headers['Host'] = $this->webpage;
		// hacemos la conexion mandando la peticion
		$headers = $this->generateHeaders($this->protocolHeader.'://'.$this->webpage.'/'.$resources, $params, $headers, HTTP_POST);
		$this->lastResource = $resources;
		$this->socket->connect();
		$this->socket->send($headers, false);
		// esperamos la respuesta
		while($this->eof === false)
		{
			$this->socket->refresh();
		}
		
		// no es necesario volver a crear otro objeto, se puede desconectar
		// no recordaba que lo hab�a dise�ado as�
		$this->socket->disconnect();
	}
	
	private function generateHeaders($resources, $params, $headers, $type = HTTP_GET)
	{
		$header_final = $type.' '.$resources.' '.strtoupper($this->protocolHeader).'/'.$this->version.HCNL;
		$addParams = null;
		$first = true;
		if($type == HTTP_POST) $headers['Content-Type'] = $this->contentType; 
		// evitamos foreach al dope
		if(!empty($params))
		{
			foreach($params as $param => $val )
			{
				if($first == true)
				{
					$first = false;
					$addParams .= $param . '=' . $val;
				} else {
					$addParams .= '&'.$param . '=' . $val;
				}
			}
			$addParams .= HCNL;
			$headers['Content-Length'] = strlen($addParams);
		}
		foreach($headers as $header => $val )
		{
			$header_final .= $header . ': '.$val.HCNL;
		}
		$header_final.=HCNL.$addParams;
		return $header_final;
	}
	
	public function onReceiveResponse($msg)
	{
		if($msg == null) $this->eof = true;
		if($this->first === true)
		{
			$this->first = false;
			$response = array();
			// parseamos las cabeceras
			$parts = explode(HCNL.HCNL, $msg);
			$headers = explode(HCNL, $parts[0]);
			$response['Header'] = $headers[0];
			for($i = 1; $i<count($headers); $i++)
			{
			preg_match("/(.*): (.*)/",$headers[$i],$match);
			$response[$match[1]] = $match[2];
			}
			$response['Main'] = $parts[1];
			// vemos si hay que guardar las cookies
			if($this->saveHeaders == true) $this->cookies = $this->explodeCookies($response['Set-Cookie']);
			// parsear cabeceras
			$this->response = $response;
			// redireccion
			if(isset($this->response['Location']) && $this->response['Location']!= $this->protocolHeader.'://'.$this->webpage.'/'.$this->lastResource) { $this->setEOF(); $this->response['Redirection'] = true; }
		} else {
			$response = $this->response;
			$response['Main'] .= $msg;
			$this->response = $response;
		}
	}
	
	public function get_response() { return $this->response; }
	public function set_response($val) { $this->response = null; }
	
	public function setEOF() { $this->eof = true; }
	
	public function set_socket(HTTPSocketMaster $val) { $this->socket = $val; } 

	public function set_saveHeaders($val) { $this->saveHeaders = $val; }
	public function get_saveHeaders() { return $this->saveHeaders;}

	public function get_contentType() { return $this->contentType; }
	public function set_contentType($val) { $this->contentType = $val; }
	
	/**
	 * implode cookies of array
	 * @param AssocArray $cookies
	 * @return string arrayString
	 */
	private function implodeCookies($cookies)
	{
		$first = false;
		$arrayString = null;
		foreach($cookies as $cookie => $value)
		{
			if($first === true)
			{
				$arrayString .= ';'.$cookie.'='.$value;
			} else { $arrayString = $cookie.'='.$value; $first = true; }
		}
		return $arrayString;
	}
	
	private function explodeCookies($cookies)
	{
		$individualCookieString = explode(';', $cookies);
		$out = $this->cookies;
		for($index = 0; $index < count($individualCookieString); $index++)
		{
			$vals = explode('=',$individualCookieString[$index]);
			$out[$vals[0]] = $vals[1];
		}
		return $out;
	}

}

class HTTPSocketMaster extends SocketMaster
{
	private $httpClient = null;
	
	public function set_httpClient($val)
	{
		$this->httpClient = $val;
	}
	
	// on Connect event
	public function onConnect()
	{
		// estamos conectados
		
	}
	
	// on disconnect event
	public function onDisconnect() 
	{
		if(!empty($this->httpClient)) 
			$this->httpClient->setEOF();		
	}
	
	// on receive message event
	public function onReceiveMessage($message)
	{
		$this->httpClient->onReceiveResponse($message);
	}
	
	// on error message event
	public function onError($errorMessage)
	{
		trigger_error('Oops HTTP error ocurred: '.$errorMessage, E_USER_ERROR);
	}
	
	public function onNewConnection(SocketBridge $socket) { }
}
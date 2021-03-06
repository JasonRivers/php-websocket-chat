<?php
$host = 'localhost'; //host
$port = '9000'; //port
$null = NULL; //null var
global $logFile;
global $connected_clients;
$connected_clients=array();
$logFile = '/tmp/chat_log'; // log for chat

//Create TCP/IP sream socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//reuseable port
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

//bind socket to specified host
socket_bind($socket, 0, $port);

//listen to port
socket_listen($socket);

//create & add listning socket to the list
$clients = array($socket);

//start endless loop, so that our script doesn't stop
while (true) {
	//manage multipal connections
	$changed = $clients;
	//returns the socket resources in $changed array
	socket_select($changed, $null, $null, 0, 10);
	
	//check for new socket
	if (in_array($socket, $changed)) {
		$socket_new = socket_accept($socket); //accpet new socket
		$clients[] = $socket_new; //add socket to client array
		
		$header = socket_read($socket_new, 1024); //read data sent by the socket
		perform_handshaking($header, $socket_new, $host, $port); //perform websocket handshake
		
		socket_getpeername($socket_new, $ip); //get ip address of connected socket
		// There is always 1 "client" as the server logs itself.
		if ( count($clients) == 2) {
			$response = mask(json_encode(array('type'=>'system', 'message'=>'Welcome, You are the only person currently connected'))); //prepare json data
			@socket_write($socket_new,$response,strlen($response));
		} else {
//			$response = mask(json_encode(array('type'=>'system', 'message'=>'Welcome to this chat'))); //prepare json data
//			@socket_write($socket_new,$response,strlen($response));
			send_log($socket_new);
		}
		
		//make room for new socket
		$found_socket = array_search($socket, $changed);
		unset($changed[$found_socket]);
	}
	
	//loop through all connected sockets
	foreach ($changed as $changed_socket) {	
		
		//check for any incomming data
		while(socket_recv($changed_socket, $buf, 1024, 0) >= 1)
		{
			$received_text = unmask($buf); //unmask data
			$tst_msg = json_decode($received_text); //json decode 
			if ($tst_msg->type == "connected") {
				$response_json = json_encode(array('type'=>'system', 'message'=>'User '.$tst_msg->name.' connected', 'ipaddress'=>$ip)); //prepare json data
				$response = mask($response_json); //prepare json for sending over websocket
				log_message($response_json); // log the message for later
				send_message($response); //notify all users about new connection
				$c=array('socket'=>$changed_socket, 'username'=>$tst_msg->name);
				array_push($connected_clients, $c);
			} else {
			$user_name = $tst_msg->name; //sender name
			$user_message = $tst_msg->message; //message text
			$user_color = $tst_msg->color; //color
			
			if ( ($user_name != "null" && $user_name != null) || ($user_message != "null" && $user_message != null)) {
			// Replace dodgy characters
				$user_message = str_replace("<", "&lt", $user_message);
				$user_message = str_replace(">", "&gt", $user_message);
				$user_message = str_replace("\"", "&quot", $user_message);

				 if (substr($user_message,0,1) === "/") {
					$command_response = '';
					switch ($user_message) {
						case "/who":
							$command_response='Connected Users:<br>';
							foreach($connected_clients as $key => $skt) {
								$command_response.=$skt['username']."<br>";
							}
							$response_text = mask(json_encode(array('type'=>'system', 'message'=>$command_response, 'color'=>$user_color)));
							send_pm($changed_socket, $response_text); //send data
							break;
						case (preg_match('/\/msg .*/', $user_message) ? true : false):
							$msgarr=explode(' ', $user_message, 3);
							$msgto=$msgarr[1];
							$command_response=$msgarr[2];
							$msgfrom="←[".$user_name."]";
							$msgreturnto="→[".$msgto."]";

							$userfound = 0;
							foreach($connected_clients as $key => $skt)
							{
								if ($skt['username'] == $msgto){
									$userfound = 1;
									$found_socket = array_search($skt['socket'], $clients);
									if ($found_socket != "")
									{
										$response_text = mask(json_encode(array('type'=>'pmsg', 'name'=>$msgfrom, 'message'=>$command_response, 'color'=>$user_color)));
										send_pm($clients[$found_socket], $response_text); //send data
										$response_text = mask(json_encode(array('type'=>'pmsg', 'name'=>$msgreturnto, 'message'=>$command_response, 'color'=>$user_color)));
										send_pm($changed_socket, $response_text); //send data
									}
								}
							}
							if (!$userfound ) {
									$command_response = "User $msgto wasn't found";
									$response_text = mask(json_encode(array('type'=>'system', 'message'=>$command_response, 'color'=>$user_color)));
									send_pm($changed_socket, $response_text); //send data
							}

				
							break;
						default:
							$command_response = "Server usage:<br>";
							$command_response.= "/who - Show connected users<br>";
							$command_response.= "/msg \$USER \$MESSAGE - Send \$MESSAGE to \$USER<br>";
							$response_text = mask(json_encode(array('type'=>'system', 'message'=>$command_response, 'color'=>$user_color)));
							send_pm($changed_socket, $response_text); //send data
							break;
					}

				} else {
				//prepare data to be sent to client
				$response_json = json_encode(array('type'=>'usermsg', 'name'=>$user_name, 'message'=>$user_message, 'color'=>$user_color));
				$response_text = mask($response_json);
				send_message($response_text); //send data
				log_message($response_json); // log the message for later
				}
			}
			}
			break 2; //exist this loop
		}
		
		$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
		if ($buf === false) { // check disconnected client
			// remove client for $clients array
			$found_socket = array_search($changed_socket, $clients);
			socket_getpeername($changed_socket, $ip);
			unset($clients[$found_socket]);
			foreach($connected_clients as $key => $skt)
			{
				$found_socket = array_search($changed_socket, $skt);
				if ($found_socket != "")
				{
					$rmuser=$skt['username'];
					$rmIndex=$key;
				}
			}
				unset($connected_clients[$rmIndex]);
		
			// if this is the last connection archive the log for later.
			// There's always 1 connection
			if ( count($clients) == 1) 
			{
				if (file_exists($logFile)) {
					rename($logFile,$logFile.date('YmdGis'));
				}
			} else {
				//notify all users about disconnected connection
				$response_json = json_encode(array('type'=>'system', 'message'=>$rmuser.' disconnected'));
				$response = mask($response_json);
				log_message($response_json); // log the message for later
				send_message($response);
			}
		}
	}
}
// close the listening socket
socket_close($socket);
function send_log($sckt)
{


	global $logFile;
	if (file_exists($logFile)) {
		$handle = fopen($logFile, "r");
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
			$l = trim($line, ",");
			$parr=json_decode($l, 1);
			if ( $parr['type'] == "usermsg" ) {
				$parr['type']="history";
				$parr['color']="cccccc";
				$response = mask(json_encode($parr));
				@socket_write($sckt,$response,strlen($response));
			}
			}
		}
		fclose($handle);
	}

}
function log_message($msg)
{

	echo $msg."\n";
	global $logFile;
	$linecount=0;
	$handle = fopen($logFile, "a+");
	while (!feof($handle)) {
		$line = fgets($handle);
		$linecount++;
	}
	if ( $linecount == 1) 
	{
		fwrite($handle,$msg."\n");
	}
	else
	{
		fwrite($handle,','.$msg."\n");
	}
	fclose($handle);
}

function send_pm($client,$msg)
{
	@socket_write($client,$msg,strlen($msg));
	return true;
}

function send_message($msg, $logmsg=1)
{
	global $clients;
	foreach($clients as $changed_socket)
	{
		@socket_write($changed_socket,$msg,strlen($msg));
	}
	return true;
}


//Unmask incoming framed message
function unmask($text) {
	$length = ord($text[1]) & 127;
	if($length == 126) {
		$masks = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length == 127) {
		$masks = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$masks = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	for ($i = 0; $i < strlen($data); ++$i) {
		$text .= $data[$i] ^ $masks[$i%4];
	}
	return $text;
}

//Encode message for transfer to client.
function mask($text)
{
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$header = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$header = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$header = pack('CCNN', $b1, 127, $length);
	return $header.$text;
}

//handshake new client.
function perform_handshaking($receved_header,$client_conn, $host, $port)
{
	$headers = array();
	$lines = preg_split("/\r\n/", $receved_header);
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
		{
			$headers[$matches[1]] = $matches[2];
		}
	}

	$secKey = $headers['Sec-WebSocket-Key'];
	$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
	//hand shaking header
	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
	"Upgrade: websocket\r\n" .
	"Connection: Upgrade\r\n" .
	"WebSocket-Origin: $host\r\n" .
	"WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($client_conn,$upgrade,strlen($upgrade));
}

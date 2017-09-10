<?php
include "DBCore.php";
include "password.php";
error_reporting(E_ALL);

class telebot extends Passwd {
	function __construct() {
		$this->init();
		$this->DB = new DBCore ('telegram', 'telegram');
		$this->getNews();
		$this->sendNews();
	}

	protected function init() {
		$this->tokeninit();
	}

	public function sendNews() {
		$sql = "SELECT * FROM `messages` WHERE `sent`= 0";
		$result = $this->DB->query($sql);
		if($result === 0) {
			$num = 0;
		} else {
			$num = mysqli_num_rows($result);
		}
		$k = 0;
		if ($num > 0) {
			while ($row = mysqli_fetch_assoc($result)) {
				$newsdata[$k]['accountid'] = $row["accountid"];
				$newsdata[$k]['messageid'] = $row["messageid"];
				$newsdata[$k]['wiki'] = $row["wiki"];
				$newsdata[$k]['type'] = $row["type"];
				$newsdata[$k]['data'] = json_decode($row["data"], true);
				$newsdata[$k]['timestamp'] = $row["timestamp"];
				$newsdata[$k]['id'] = $row["id"];
				$k++;
			}
		
			print_r($newsdata);

			for($i=0; count($newsdata) > $i; $i++) {
				if($newsdata[$i]['type'] == "edit-thank") {
					$text = $newsdata[$i]['data']['agent']['name'] . " thanked you for your edit on " . $newsdata[$i]['data']['title']['full'] . ".\n";
				} else if($newsdata[$i]['type'] == "edit-user-talk") {
					$text = $newsdata[$i]['data']['agent']['name'] . " edited your talk page. See revision: https://" . $newsdata[$i]['wiki'] . "/wiki/Special:Diff/" . $newsdata[$i]['data']['revid'] . "\n";
				} else if($newsdata[$i]['type'] == "mention") {
					$text = $newsdata[$i]['data']['agent']['name'] . " mentioned you on " . $newsdata[$i]['data']['title']['full'] . ". See revision: https://" . $newsdata[$i]['wiki'] . "/wiki/Special:Diff/" . $newsdata[$i]['data']['revid'] . "?markasread=" . $newsdata[$i]['messageid'] . "\n";
				} else if($newsdata[$i]['type'] == "emailuser") {
					$text = $newsdata[$i]['data']['agent']['name'] . " sent you an email.\n";
				} else if($newsdata[$i]['type'] == "page-linked") {
					$text = $newsdata[$i]['data']['agent']['name'] . " created a link to " . $newsdata[$i]['data']['title']['full'] . ".\n";
				} else if($newsdata[$i]['type'] == "mention-success") {
					$text = "Your mention on " . $newsdata[$i]['data']['title']['full'] . " was successful.\n";
				}

				$messagetext = $text;
				//$messagetext = "You got a new notification on " . $newsdata[$i]['wiki'] . "\n";
				$messagetext .= "Type of the action: " . $newsdata[$i]['type'] . "\n";
				$messagetext .= "If you want to know more, please visit: https://" . $newsdata[$i]['wiki'] . "/wiki/Special:Notifications?markasread=" . $newsdata[$i]['messageid'] . "\n";

				$sql = "SELECT * FROM `users` WHERE `userid`= " . $newsdata[$i]['accountid'];
				$result = $this->DB->query($sql);
				if($result === 0) {
					$num = 0;
				} else {
					$num = mysqli_num_rows($result);
				}
				$k = 0;
				if ($num > 0) {
					while ($row = mysqli_fetch_assoc($result)) {
						$userdata[$k]['userid'] = $row["userid"];
						$userdata[$k]['chatid'] = $row["chatid"];
						$k++;
					}
				}
				$sql = "UPDATE `telegram`.`messages` SET `sent` = '1' WHERE `messages`.`messageid` =" . $newsdata[$i]['messageid'];
				$this->DB->modify($sql);

				$this->sendMsg($userdata[0]['chatid'], $messagetext);
				// ToDo: Send
			}
		}
	}

	public function getNews() {
		$result = $this->DB->query("SELECT * FROM users");
		$num = mysqli_num_rows($result);
		$k = 0;
		if ($num > 0) {
			while ($row = mysqli_fetch_assoc($result)) {
				$userdata[$k]['userid'] = $row["userid"];
				$userdata[$k]['chatid'] = $row["chatid"];
				$k++;
			}
		} 
		print_r($userdata);
		for($i=0; count($userdata) > $i; $i++) {

			$sql = "SELECT * FROM accounts WHERE userid = " . $userdata[$i]['userid'];
			$result = $this->DB->query($sql);
			if($result !== 0) {
				$num = mysqli_num_rows($result);
				$k = 0;
				if ($num > 0) {
					while ($row = mysqli_fetch_assoc($result)) {
						$accountdata[$k]['userid'] = $row["userid"];
						$accountdata[$k]['wiki'] = $row["wiki"];
						$accountdata[$k]['username'] = $row["username"];
						$accountdata[$k]['password'] = $row["password"];
						$k++;
					}
				}

				for($j=0; count($accountdata) > $j; $j++) {
					$this->frageEchoAb($accountdata[$j]['username'], $accountdata[$j]['password'], $accountdata[$j]['wiki'], $accountdata[$j]['userid']);
				}
			}
		}
	}

	public function frageEchoAb($username, $password, $wiki, $userid) {
		echo $username . " on " . $wiki . "\n";
		$curl = curl_init();
		if ($curl === false) {
			throw new Exception('Curl initialization failed.');
		} else {
			$this->curlHandle = $curl;
		}
		$job = $username . "@" . $wiki;
		$this->login($username, $password, $wiki, $job);
		$echo = $this->httpRequest("action=query&format=php&meta=notifications", $wiki, $job, 'GET');
		$this->logout($wiki, $job);
		$echo = unserialize($echo);
		$tree = $echo['query']['notifications']['list'];

		for($i = 0; count($tree) > $i; $i++) {
			$id = $tree[$i]['id']; 
			$type = $tree[$i]['type'];
			$timestamp = $tree[$i]['timestamp']['unix'];
			if(isset($tree[$i]['read'])) {
				$read = true;
			} else {
				$read = false;
			}
			$message = json_encode($tree[$i]);
			$message = $this->DB->real_escape_string($message);
			$sql = "SELECT * FROM `messages` WHERE `accountid` =$userid AND `wiki` LIKE '$wiki' AND `messageid` = $id;";
			$result = $this->DB->query($sql);
			if($result === 0) {
				$num = 0;
			} else {
				$num = mysqli_num_rows($result);
			}
			if($num == 0) {
				if($read == true) {
					$sql = "INSERT INTO `messages` (`accountid`, `messageid`, `wiki`, `type`, `data`, `timestamp`, `sent`) VALUES ('" . $userid. "', '" . $id . "', '" . $wiki . "', '" . $type . "', '" . $message . "', " . $timestamp . ", 1)";
				} else {
					$sql = "INSERT INTO `messages` (`accountid`, `messageid`, `wiki`, `type`, `data`, `timestamp`, `sent`) VALUES ('" . $userid. "', '" . $id . "', '" . $wiki . "', '" . $type . "', '" . $message . "', " . $timestamp . ", 0)";
				}
				$this->DB->modify($sql);
			}
		}
	}

	/** login
	* loggt den Benutzer ein
	* Nicht! verwenden. Diese Methode wird nur von initcurl/initcurlargs genutzt.
	* @author Hgzh
	*/
	public function login($username, $password, $wiki, $job) {
		// get login token
		try {
			$result = $this->httpRequest('action=query&format=php&meta=tokens&type=login', $wiki, $job, 'GET');
		} catch (Exception $e) {
			throw $e;
		}
		$tree = unserialize($result);
		$lgToken = $tree['query']['tokens']['logintoken'];
		if ($lgToken === '')
			throw new Exception('Could not receive login token.');  
		// perform login
		try {
			$result = $this->httpRequest('action=login&format=php&lgname=' . urlencode($username) . '&lgpassword=' . urlencode($password) . '&lgtoken=' . urlencode($lgToken), $wiki, $job);
		} catch (Exception $e) {
			throw $e;
		}
		$tree = unserialize($result);
		$lgResult = $tree['login']['result'];
		// manage result
		if ($lgResult == 'Success')
			return true;
		else
			throw new Exception('Login failed with message ' . $lgResult);
	}

	/** logout
	* Loggt den Benutzer aus
	*/
	public function logout($wiki, $job) {
		try {
			$this->httpRequest('action=logout', $wiki, $job);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/** httpRequest
	* führt http(s) request durch
	* Wird meistens benutzt um die API anzusteuern
	* @param $pArguments - API-Parameter die aufgerufen werden sollen (beginnt normalerweise mit action=)
	* @param $job - Jobname, wird benutzt um die richtigen Cookies etc zu finden. Hier einfach $this->job benutzen.
	* @param $pMethod - [optional: POST] Methode des Requests. Bei querys sollte stattdessen GET genommen werden
	* @param $pTarget - [optional: w/api.php] Verwende diesen Parameter, wenn die API deines Wikis einen anderen Einstiegspfad hat. (Special:Version)
	* @author Hgzh
	* @returns Antwort der API
	*/
	protected function httpRequest($Arguments, $wiki, $Job, $Method = 'POST', $Target = 'w/api.php') {
		$baseURL = 'https://' . 
			$wiki . '/' . 
			$Target;
		$Method = strtoupper($Method);
		if ($Arguments != '') {
			if ($Method === 'POST') {
				$requestURL = $baseURL;
				$postFields = $Arguments;
			} elseif ($Method === 'GET') {
				$requestURL = $baseURL . '?' .
						$Arguments;
			} else 
				throw new Exception('Unknown http request method.');
		}
		if (!$requestURL) 
			throw new Exception('No arguments for http request found.');
		// set curl options
		curl_setopt($this->curlHandle, CURLOPT_USERAGENT, "LarusBot Telegram");
		curl_setopt($this->curlHandle, CURLOPT_URL, $requestURL);
		curl_setopt($this->curlHandle, CURLOPT_ENCODING, "UTF-8");
		curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, realpath('Cookies' . $Job . '.tmp'));
		curl_setopt($this->curlHandle, CURLOPT_COOKIEJAR, realpath('Cookies' . $Job . '.tmp'));
		// if posted, add post fields
		if ($Method === 'POST' && $postFields != '') {
			curl_setopt($this->curlHandle, CURLOPT_POST, 1);
			curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $postFields);
		} else {
			curl_setopt($this->curlHandle, CURLOPT_POST, 0);
			curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, '');
		}
		// perform request
		$rqResult = curl_exec($this->curlHandle);
		if ($rqResult === false)
			throw new Exception('Curl request failed: ' . curl_error($this->curlHandle));
		return $rqResult;
	}

	public function sendMsg($user, $msg) {
		if($user != "" AND $msg != "") {
			//$msg = urlencode($msg);
			$systemcall = "curl --data chat_id=" . $user . " --data-urlencode " . '"' . "text=" . $msg . '" ' . "https://api.telegram.org/bot" . $this->token . "/sendMessage";
			system($systemcall);
		}
	}
}

$tool = new telebot();
?>

<?php
require("../../telegram/password.php");
require("../../telegram/DBCore.php");
error_reporting(-1);
class Webhook extends Passwd {
	public function __construct() {
		//$password = Passwd::tokeninit();
		$this->DB = new DBCore ('telegram', 'telegram');
		$this->tokeninit();
		$content = file_get_contents("php://input");
		$update = json_decode($content, true);
		file_put_contents("data.txt", $content);

		$from = $update['message']['from']['id'];
		$name = $update['message']['from']['first_name'];
		$type = $update['message']['chat']['type'];
		$date = $update['message']['date'];
		$msg = $update['message']['text'];
		file_put_contents("data2.txt", $from . $name . $msg);
		$this->reactToMSG($from, $name, $type, $date, $msg);
	}

	protected function reactToMSG($fromid, $name, $type, $date, $msg) {
		if($type == "private") {
			if($msg == "/start") {
				$message = "Hey " . $name . "!\nNice to meet you. I am LarusBot and my purpose is to notify you, if anything happens on your MediaWikis Echo. But before I can do anything, you need to provide a Wiki, which I should watch for you. \nPlease use the command /newwiki to set up a new wiki. ";
				$this->sendMessage($fromid, $message);
			} else if ($msg == "/newwiki") {
				$message = "Please give me the URL of your wiki. For example: If you want to set up the english Wikipedia, type en.wikipedia.org \nIf you want to go back, just type back";
				$sql = "SELECT * FROM `regstatus` WHERE `chatid`= " . $fromid;
				$result = $this->DB->query($sql);
				if($result === 0) {
					$num = 0;
				} else {
					$num = mysqli_num_rows($result);
				}
				if($num === 0) {
					//keine andere registrierung am laufen
					//Dann Benutzer anlegen
					$sql = "INSERT INTO `telegram`.`regstatus` (`chatid`, `wikiprovided`, `usernameprovided`, `passwordprovided`, `id`) VALUES ('" . $fromid . "', '0', '0', '0', NULL)";
					$this->DB->modify($sql);
				}
				$this->sendMessage($fromid, $message);
			} else if ($msg == "/discardregistration") {
					$sql = "DELETE FROM `telegram`.`regstatus` WHERE `regstatus`.`chatid` = " . $fromid;
					$this->DB->modify($sql);
					$message = "I´m so sorry, that something went wrong!\nYou can use /newwiki to start again";
					$this->sendMessage($fromid, $message);
			} else if ($msg != "") {
				mysqli_real_escape_string($fromid);
				$sql = "SELECT * FROM `telegram`.`regstatus` WHERE `chatid`= " . $fromid;
				$result = $this->DB->query($sql);
				$k = 0;
				while ($row = mysqli_fetch_assoc($result)) {
					$regdata[$k]['chatid'] = $row["chatid"];
					$regdata[$k]['wikiprovided'] = $row["wikiprovided"];
					$regdata[$k]['usernameprovided'] = $row["usernameprovided"];
					$regdata[$k]['passwordprovided'] = $row["passwordprovided"];
					$regdata[$k]['id'] = $row["id"];
					$k++;
				}
				if($regdata[0]['wikiprovided'] == 0 && $msg !== "/discardregistration") {
					$message = "Great!\nPlease navigate now to https://" . $msg . "/wiki/Special:BotPasswords and create a Bot account with basic rights. This is actually the only way to access your Echo without providing your password!\nIf you want to make ensure, that only this bot can use this password, it is up to you to limit the allowed IPs to Wikimedia´s range: 208.80.155.0/24\nReady? Then please tell me the Bot account´s username. ";
					$sql = "UPDATE `regstatus` SET `wikiprovided` = '1' WHERE `chatid` = " . $fromid;
					$this->DB->modify($sql);
					mysqli_real_escape_string($msg);
					$sql = "UPDATE `regstatus` SET `wikidomain` = '". $msg ."' WHERE `chatid` = " . $fromid;
					$this->DB->modify($sql);
					$this->sendMessage($fromid, $message);
				} else if ($regdata[0]['usernameprovided'] == 0) {
					$message = "And now provide the generated Bot password (not your real password, which you are using for loggin in).";
					$sql = "UPDATE `regstatus` SET `usernameprovided` = '1' WHERE `chatid` = " . $fromid;
					$this->DB->modify($sql);
					mysqli_real_escape_string($msg);
					$sql = "UPDATE `regstatus` SET `wikiusername` = '". $msg ."' WHERE `chatid` = " . $fromid;
					$this->DB->modify($sql);
					$this->sendMessage($fromid, $message);
				} else if ($regdata[0]['passwordprovided'] == 0) {
					$sql = "UPDATE `regstatus` SET `passwordprovided` = '1' WHERE `chatid` = " . $fromid;
					$this->DB->modify($sql);
					mysqli_real_escape_string($msg);
					$sql = "UPDATE `regstatus` SET `wikipassword` = '". $msg ."' WHERE `chatid` = " . $fromid;
					$this->DB->modify($sql);
					$message = "Please confirm, that the data is correct. This will be the last chance to view them before I will start looking after your notifications.\nIf you detect any error, please use /discardregistration.\nIf the data is correct, use /saveregistration";
					$this->sendMessage($fromid, $message);
				} else if ($msg == "/saveregistration") {

					$sql = "SELECT * FROM `telegram`.`regstatus` WHERE `chatid`= " . $fromid;
					$result = $this->DB->query($sql);
					$k = 0;
					
					if($result === 0) {
						$num = 0;
					} else {
						$num = mysqli_num_rows($result);
					}
					if($num !== 0) {
						$message = "Try to log in...";
						$this->sendMessage($fromid, $message);

						while ($row = mysqli_fetch_assoc($result)) {
							$regdata[$k]['chatid'] = $row["chatid"];
							$regdata[$k]['wikidomain'] = $row["wikidomain"];
							$regdata[$k]['wikiusername'] = $row["wikiusername"];
							$regdata[$k]['wikipassword'] = $row["wikipassword"];
							$regdata[$k]['id'] = $row["id"];
							$k++;
						}
						$username = $regdata[0]['wikiusername'];
						$wiki = $regdata[0]['wikidomain'];
						$password = $regdata[0]['wikipassword']; 

						$job = $username . "@" . $wiki;

						try {
							$login = $this->login($username, $password, $wiki, $job);
							if($login == true) {
								$message = "Login data is correct";
								//Move to real table
							}
						} catch (Exeption $e) {
							$message = "Login data is NOT correct";
						} finally {
							$this->sendMessage($fromid, $message);
						}
						die();
					} else {
						$message = "Nothing to save :'(";
						$this->sendMessage($fromid, $message);
					}

					// ToDo: Check if it is right and move to other table.
				}

			}
		} else {
			die("Please only private chat");
			//ToDo: Notify user
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

	private function sendMessage($userid, $message) {
		if($userid != "" AND $message != "") {
			$systemcall = "curl --data chat_id=" . $userid . " --data-urlencode 'text=" . $message . "' https://api.telegram.org/bot" . $this->token . "/sendMessage";
			system($systemcall);
		}
	}

	function __destruct() {
		echo "OK.";
	}
}

$webhook = new Webhook();
?>

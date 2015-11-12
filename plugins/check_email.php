<?php
class check_email extends plugins {
	public $meta = array(
		'id' 	=> "email",
		'name' => "Check email",
		'version' => "1.0.0",
		'descrition' =>"Check email, autu guess mail server"
	);

	protected $options = array(
		'useDatabase' => true,
	);

	public $fields = array(
		"status" => "str",
		"server" => array(
			"domain" => "str",
			"port" => "int",
			"socketType" => "str",
			"type" => "str",
			"pcondition" => "str",
			"usertype" => "str",
		),
	);

	public function init() {
		parent::init();
		if ($this->db) {
			$this->db->query("CREATE TABLE IF NOT EXISTS `record` (
			 `host` varchar(100) NOT NULL,
			 `domain` varchar(100) NOT NULL DEFAULT '???',
			 `port` int(11) NOT NULL DEFAULT '0',
			 `socketType` tinyint(4) NOT NULL DEFAULT '0',
			 `type` tinyint(4) NOT NULL DEFAULT '0',
			 `pcondition` text,
			 `usertype` tinyint(3) NOT NULL DEFAULT '0',
			 PRIMARY KEY (`host`)
			)");
		}
	}
	public function check($user,$pass,$request) {
		$return = array();
		$mail = $this->getEmailAddress($user);
		$server = $this->searchServer($mail->domain);

		if ($server!=false) {
			if (!$this->getted) $this->addServer($mail->domain,$server);
		} else {
			if (!$this->getted) $this->addUnknowServer($mail->domain);
			$return["status"] = "WHERE";
			return $return;
		}

		if (isset($request["server"])) {
			$return["server"] = array(
				"domain" => $server->domain,
				"port" => $server->port,
				"socketType" => $server->socketType==1?"No Encrytion":($server->socketType==2?"SSL":"TLS") ,
				"type" => $server->type==1 ? "IMAP" : "POP",
				"usertype" => $server->usertype==0?"Don't know":($server->usertype==1?"Localpart":"Email address"),
				"pcondition" => $server->pcondition
			);
		}

		$r = $this->try_login($mail,$pass,$server);
		if ($r===true) $return["status"] = "LIVE";
		else $return["status"] = "DIE";
		return $return;
	}

	private function getEmailAddress($input) {
		$email = explode('@',$input);
		$domain = end($email);
		unset($email[key($email)]);
		$user = implode('@',$email);
		$e = new EmailAddress();
		$e->email = $input;
		$e->domain = $domain;
		$e->user = $user;
		return $e;
	}

	private function searchServer($domain) {
		// Get from database
		$s = $this->getDatabase($domain);
		if ($s===-1) return false;
		elseif ($s!=false) return $s;

		// Test Thunderbird server
		$s = $this->getThunderBirdServer($domain);
		if ($s!=false) return $s;
		if (getmxrr($domain,$mxhosts)) {
			foreach ($mxhosts as $mx) {
				$s = $this->getThunderBirdServer($this->getTopDomain($mx));
				if ($s!=false) return $s;
			}
		}

		// Guess Common server
		$s = $this->guessServer($domain);
		if ($s!=false) return $s;

		return false;
	}

	private function guessServer($domain) {
		$ld = $this->listdomain($domain);
		foreach ($ld as $d) {
			if ($s = $this->testServer($d)) return $s;
			if ($s = $this->testServer("mail.".$d)) return $s;
			if ($s = $this->testServer("imap.".$d)) return $s;
			if ($s = $this->testServer("pop.".$d)) return $s;
			if ($s = $this->testServer("pop3.".$d)) return $s;
		}
		return false;
	}
	private function getTopDomain($domain) {
		$sd = explode('.',$domain);
		$m = count($sd);
		if ($m < 3) return array($domain);
		$min = (strlen($sd[$m-1]) > 2 || strlen($sd[$m-2]) > 2) ? 2 : 3;
		return implode('.',array_slice($sd,$m-$min,$min));
	}
	private function getDatabase($domain) {
		if (isset($this->db)&&$this->db!=null&&$this->db!=false) {
			$s = $this->getServer($domain);
			if ($s!=false) {
				if ($s->domain == "???") return -1;
				elseif ($this->testServer($s->domain,$s->port,$s->type,$s->socketType)!=false) return $s;
				else $this->db->getted = false;
			}
		}
		return false;
	}
	private function getThunderBirdServer($domain) {
		$result = @file_get_contents("http://autoconfig.thunderbird.net/v1.1/".$domain);
		if ($result!='') {
			// have record
			$xml = simplexml_load_string($result);

			if ($xml) {
				if (count($xml->emailProvider->incomingServer)>1) {
					$server = $xml->emailProvider->incomingServer[0];
					foreach ($xml->emailProvider->incomingServer as $ser) {
						if ($ser->attributes()->type=="imap") $server = $ser; //prefer imap
					}
				} else {
					$server = $xml->emailProvider->incomingServer;
				}
				$s = new Server();
				$s->domain = (string)$server->hostname;
				$s->port = (int)$server->port;
				switch ($server->socketType) {
					case "SSL": $s->socketType = 2; break;
					case "STARTTLS": $s->socketType = 3; break;
					default: $s->socketType = 1;
				}
				switch ($server->username) {
					case "%EMAILADDRESS%": $s->usertype = 2; break;
					default: $s->usertype = 1;
				}
				switch ($server->attributes()->type) {
					case "imap": $s->type = 1; break;
					default: $s->type=2;
				}
				if ($this->testServer($s->domain,$s->port,$s->type,$s->socketType)!=false) return $s;
			}
		}
		return false;
	}
	private function testServer($domain,$port=0,$protocol=0,$type=0) {
		if ($port>0) {
			if ($protocol > 0) {
				if ($type>0) {
					$s = new Server();
					$s->domain = $domain;
					$s->port = $port;
					$s->type = $protocol;
					$s->socketType = $type;
					if ($this->try_connect($s)) return $s;
					else return false;
				} else {
					if ($s = $this->testServer($domain,$port,$protocol,1)) return $s;
					if ($s = $this->testServer($domain,$port,$protocol,3)) return $s;
				}
			} else {
				if ($s = $this->testServer($domain,$port,1,$type)) return $s;
				if ($s = $this->testServer($domain,$port,2,$type)) return $s;
			}
		} else {
			if (!$this->check_domain($domain,143,1,1)) return false;
			if ($s = $this->testServer($domain,143,1)) return $s;
			if ($s = $this->testServer($domain,993,1,2)) return $s;
			if ($s = $this->testServer($domain,110,2)) return $s;
			if ($s = $this->testServer($domain,995,2,2)) return $s;
		}
		return false;
	}
	private function check_domain($domain) {
		$i = @imap_open("{".$s->domain."}","","",OP_DEBUG,2);
		$r = ($i!=false||!preg_match("/Host not found/",imap_last_error()));
		imap_errors();
		imap_alerts();
		if ($i!=false) @imap_close($i);
		return $r;
	}
	private function try_connect($s) {
		$i = @imap_open("{" . $s->domain  . ":" .
			$s->port .
			($s->socketType==2?"/ssl":($s->socketType==3?"/tls":"")) .
			($s->type==2?"/pop3":"/imap") .
			($s->socketType!=2?"/novalidate-cert":"") .
			"}","","",OP_DEBUG,2
		);
		$r = ($i!=false||imap_last_error()=="Login aborted"||imap_last_error()=="Too many login failures");
		imap_errors();
		imap_alerts();
		if ($i!=false) @imap_close($i);
		return $r;
	}
	private function listdomain($domain) {
		$sd = explode('.',$domain);
		$m = count($sd);
		if ($m < 3) return array($domain);
		$min = (strlen($sd[$m-1]) > 2 || strlen($sd[$m-2]) > 2) ? 2 : 3;
		$r = array();
		for ($i=0;$i<=$m-$min;$i++)
			$r[] = implode('.',array_slice($sd,$i,$m-$i));
		return $r;
	}
	private function try_login($e,$p,$s) {
		if(!$this->checkPassCondition($p,$s->pcondition)) return false;
		if ($s->usertype > 0 && $s->usertype < 3) {
			if ($s->usertype==1) $u = $e->user;
			elseif ($s->usertype==2) $u = $e->email;
			return $this->login($u,$p,$s);
		} else {
			$s->usertype = 1;
			$r = $this->try_login($e,$p,$s);
			if ($r===true) {
				$this->db->addServer($e->domain,$s);
				return $r;
			}
			$s->usertype = 2;
			$r = $this->try_login($e,$p,$s);
			if ($r===true) {
				$this->addServer($e->domain,$s);
				return $r;
			}
		}

		return false;
	}
	private function login($u,$p,$s,$r=0) {
		$i = @imap_open("{" . $s->domain  . ":" .
			$s->port .
			($s->socketType==2?"/ssl":($s->socketType==3?"/tls":"/novalidate-cert")) .
			($s->type==2?"/pop3":"/imap") .
			"}",$u,$p,OP_DEBUG,1
		);
		if (imap_last_error()!="Login aborted"&&imap_last_error()!="Too many login failures") {
			if ($i!=false) $r = true;
			else {
				if ($r<2&&preg_match('/connection broken/i',imap_last_error())) {
					$r++;
					imap_errors();
					imap_alerts();
					return login($u,$p,$s,$r);
				}
				throw new Exception(imap_last_error());
			}
		} else {
			$r = false;
		}
		imap_errors();
		imap_alerts();
		if ($i!=false) @imap_close($i);
		return $r;
	}
	private function checkPassCondition($pass,$condition) {
		$valid = true;
		if ($condition) eval($condition);
		return $valid;
	}

	// database
	var $getted = false;
	private function getServer($domain) {
		try {
			$sth = $this->db->prepare("SELECT `domain`,`port`,`socketType`,`type`,`pcondition`,`usertype` FROM `record` WHERE host = ? LIMIT 1");
			$sth->execute(array($domain));
			if ($sth->rowCount() < 1) return false;
			$this->getted = true;
			return $sth->fetchAll(PDO::FETCH_CLASS,"Server");
		} catch (Exception $e) {
			return false;
		}
	}
	private function addServer($host,$s) {
		try {
			if (!is_a($s,"Server")) return;
			$sth = $this->db->prepare("DELETE FROM `record` WHERE host = ?");
			$sth->execute(array($host));
			$sth = $this->db->prepare("INSERT INTO `record` (`host`,`domain`,`port`,`socketType`,`type`,`pcondition`,`usertype`) VALUES (
				:host, :domain, :port, :socket, :type, :pcondition, :usertype)");
			$sth->execute(array($host, $s->domain, (int)$s->port, (int)$s->socketType, (int)$s->type, $s->pcondition, (int)$s->usertype));
		} catch (Exception $e) {}
	}
	private function addUnknowServer($host) {
		$s = new Server();
		$this->addServer($host,$s);
	}
}

// Prototype struct
	class EmailAddress {
		var $email;
		var $domain;
		var $user;
	}
	class Server {
		const NO_ENCRYPTION = 1,
			SSL = 2,
			TLS = 3,
			UNKNOW = 0,
			IMAP = 1,
			POP3 = 2,
			LOCALPART = 1,
			FULL_EMAIL_ADDRESS = 2;

		var $domain = "???";
		var $port = 0;
		var $socketType = Server::UNKNOW; // 1~>no encry, 2~>ssl, 3~>tls
		var $type = Server::UNKNOW; // 1~>imap, 2~>pop
		var $usertype = Server::UNKNOW; // 0~> don't know, 1~> localpart, 2~>emailaddress
		var $pcondition = '';
	}
?>

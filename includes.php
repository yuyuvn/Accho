<?php
class database extends mysqli {};
abstract class plugins {
	protected $db;
	public $id = "default";
	public $name = "Plugins";
	public $version = "0.0.0";
	public $descrition = "Abstract class for plugins";
	protected $options = array(
		"useDatabase" => false,
	);

	private $isInited = false;
	private $ssid;
	public $sock = "";

	abstract public function check($user,$pass,$request);

	public function init() {
		if ($this->isInited) return;

		$this->ssid = md5(mt_rand(0,999999999));

		try {
			if (isset($this->options['useDatabase']) && $this->options['useDatabase']) $this->db = new database(DB_SERVER,DB_USERNAME,DB_PASS,DB_NAME);
		} catch (Exception $e) {}

		$this->isInited = true;
	}

	protected function connect($url,$ref = "",$data = null, $config = array()) {
		$ch = curl_init($url);
			if (!$config["no_header"]) curl_setopt($ch, CURLOPT_HEADER, 1);
			if ($config["header_only"]) curl_setopt($ch, CURLOPT_NOBODY, 1);
			else curl_setopt($ch, CURLOPT_NOBODY, 0);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_COOKIEJAR, DIR."/ass/{$this->ssid}.cookie");
			curl_setopt($ch, CURLOPT_COOKIEFILE, DIR."/ass/{$this->ssid}.cookie");
			curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		if ($ref) curl_setopt($ch, CURLOPT_REFERER, $ref);
		if ($this->sock) curl_setopt($ch, CURLOPT_PROXY, $this->sock); // sock5 use "socks5://bob:marley@localhost:12345"
		if (is_array($data)) {
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		$d = curl_exec($ch);
		curl_close($ch);

		return $d;
	}

	protected function get_field($data,$form_name,$field="*") {
		$of = false;
		if (is_array($field)) {
			$of = true;
		} elseif ($field !== "*") {
			$field = array($field);
			$of = true;
		}

		if (!preg_match('/<form\s[^>]*?name\=[\\\'\"]' . $form_name . '[\\\'\"][^>]*?>.*?<\/form>/is',$data,$match)) {
			return false;
		}

		$rdata = array();
		$data = $match[0];
		if (!$of) {
			if (preg_match_all('/<input\s[^>]*?name\=[\\\'\"](.*?)[\\\'\"][^>]*?>/i',$data,$match,PREG_PATTERN_ORDER)) {
				$field = $match[1];
			} else {
				$field = array();
			}
		}

		foreach($field as $f) {
			if (preg_match('/<input\s[^>]*?name\=[\\\'\"]' . $f . '[\\\'\"][^>]*?>/i',$data,$match)) {
				if (preg_match('/\svalue\=[\\\'\"](.*?)[\\\'\"]/i',$match[0],$match)) {
					$rdata[$f] = $match[1];
				} else {
					$rdata[$f] = "";
				}
			} else {
				$rdata[$f] = "";
			}
		}

		return $rdata;
	}


	protected function startSession() {
		$f=fopen(DIR."/ass/{$this->ssid}.cookie",'wb');
		fclose($f);
	}

	protected function endSession() {
		unlink(DIR."/ass/{$this->ssid}.cookie");
	}
}

function checkdata($m,$p) {
	if ($m==''||$p=='') return false;
	return true;
}
function check_condition($m,$p) {
	if(strlen($p)<8) return false;
	if(!preg_match('/[A-Z]/',$p)) return false;
	if(!preg_match('/[0-9]/',$p)) return false;
	if($m==$p) return false;
	if(!preg_match('/[a-z]/',$p)) return false;
	for ($i=0;$i<strlen($p)-2;$i++) {
		if ($p{$i} == $p{$i+1} && $p{$i+1} == $p{$i+2}) return false;
	}
	return true;
}
?>

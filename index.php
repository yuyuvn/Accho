<?php
// database config
define('DB_SERVER',"localhost");
define('DB_USERNAME',"root");
define('DB_PASS',"");
define('DB_NAME',"checkmail");


// DO NOT EDIT BELOW IF YOU DON't KNOW WHAT ARE YOU DOING
set_time_limit(600);
header('Content-type: text/html; charset=utf-8');
define("DIR",dirname(__FILE__));

require_once('includes.php');

// get list plugins
$checkers = array();
foreach (glob("plugins/*.php") as $filename)
{
    require_once ($filename);
	$class = basename($filename,'.php');
	eval('$o = new ' . $class . '();');
	$checkers[$o->id] = $o;
}

$mode = isset($_REQUEST['js']) ? "js" : (isset($_POST['data'])? "Process" : "GUI");
if ($mode=="Process") {
	error_reporting(0); //E_ALL
	$return = array();
	try {
		$data = json_decode($_POST['data'],true);
		$m = $data['user'];
		$p = $data['pass'];
		$return = array("user"=>$m,"pass"=>$p);
		if (!checkdata($m,$p)) throw new Exception("Input not valid");
		
		foreach ($checkers as $id => $checker) {
			if (isset($data[$id])) {
				$checker->init();
				
				if (isset($_REQUEST['sock'])) {
					$checker->sock = $_REQUEST['sock'];
				}
				
				$return[$id] = $checker->check($m,$p,$data[$id]);
			}
		}
	} catch (Exception $e) {
		$return["error"] = $e->getMessage();
	}
	exit(json_encode($return));
} else if ($mode == "js") {
	if ($_REQUEST['js'] == "fields_declare") {
		error_reporting(0); //E_ALL
		$p = array();
		foreach ($checkers as $id => $checker) {
			try {
				if (isset($checker->fields)) {
					if (is_array($checker->fields) && count($checker->fields) == 0) continue;
					if ($checker->fields == '') continue;
					
					$p[] = $id . ":" . json_encode($checker->fields);
				}
			} catch (Exception $e) {
				echo "/*\nERROR:\n".$e."\n*/";
			}
		}
		
		if (count($p) > 0) echo 'interpreter.addParamsList({' . implode(',',$p) . '});';
		exit();
	} elseif ($_REQUEST['js'] == "functions_declare") {
		error_reporting(0); //E_ALL
		$fs = array();
		foreach ($checkers as $id => $checker) {
			try {
				if (isset($checker->cus_functs)) {
					if (!is_array($checker->cus_functs)) continue;
					foreach ($checker->cus_functs as $fname => $cf) {
						$f = array();
						
						$f['name'] = $fname;
						
						// list arguments
						$args = array();
						foreach ($cf['args'] as $a) $args[] = "\"$a\"";
						$f['args'] = "[" . implode(',',$args) . "]";
						
						// list optional arguments
						$args = array();
						foreach ($cf['opargs'] as $a) $args[] = "\"$a\"";
						$f['opargs'] = "[" . implode(',',$args) . "]";
						
						$f['type'] = '"' . $cf['type'] . '"';
						
						$f['code'] = 'function(arg) {' . $cf['code'] . '}';
						
						$fs[] = "{$f['name']}: {args: {$f['args']},opargs: {$f['opargs']},type: {$f['type']},code: {$f['code']}}";
					}
				}
			} catch (Exception $e) {
				echo "/*\nERROR:\n".$e."\n*/";
			}
		}
		if (count($fs) > 0) echo 'interpreter.addFunctions({' . implode(',',$fs) . '});';
		exit();
	}
}
?>
<!DOCTYPE html><html>
<head>
<title>Check account</title>
<style type="text/css">
.aqua {color: aqua}
.blue {color: blue}
.fuchsia {color: fuchsia}
.gray {color: gray}
.green {color: green}
.lime {color: lime}
.maroon {color: maroon}
.navy {color: navy}
.olive {color: olive}
.orange {color: orange}
.purple {color: purple}
.red {color: red}
.silver {color: silver}
.teal {color: teal}
.yellow {color: yellow}
#loadingIMG {visibility:hidden}
#columnlist {display:none;margin-top:5px}
#result {max-height: 500px;overflow: auto}
</style>
<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css" />
<script src="http://code.jquery.com/jquery-1.9.1.js"></script>
<script src="http://code.jquery.com/ui/1.10.3/jquery-ui.js"></script>
<script src="checkacc.js"></script>
<script src="outputtemplate.js"></script>
</head>
<body>
<form name="mform" action="<?php echo basename($_SERVER['PHP_SELF']) ?>" method="post" id="mform">
<input type="hidden" name="ot_value" id="ot_value" value="{input}" />
<div style="text-align:center">
<div class="option">
<textarea rows="15" cols="75" name="ml" id="ml">
</textarea>
<div style="margin-top: 10px">
Seperator: <input type="text" value="|" name="sep" size="1"/>
| <input type="checkbox" name="adc" checked id="adc" /> <label for="adc">Auto Detect Column</label>
<div id="columnlist">
User column (start from 0): <input type="text" value="1" name="mc" size="1"/>
| Pass column: <input type="text" value="2" name="pc" size="1"/>
</div>
</div>
</div>
<div style="margin: 10px" id="filter"></div>
<div>
<input type="button" name="showTemplate" value="Output Template" /> 
<input type="submit" value="Start" id="sbutton"/> 
<input type="button" value="Clear" id="cbutton"/> 
<input type="button" name="showSocks" value="Socks setting" />
</div>
<div id="loadingIMG"><img src="loading.gif" border="0" alt="Checking..." title="Checking..."/></div>
<div id="result" style="margin-top:2px">
</div>
</div>
</form>
<div style="display:none">
<div id="outputTemplate" title="Ouput template">
	<textarea name="ot" id="ot" rows="2" cols="30"></textarea>
</div>
<div id="sockSetting" title="Sock Settings">
	<textarea name="ls" id="ls" rows="10" cols="60"></textarea>
</div>
</div>
</body>
</html>
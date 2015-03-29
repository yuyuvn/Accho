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
define("VERSION", "1.0.0")

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
		
		echo "//Add fields\n";
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
		echo "//Add functions\n";
		if (count($fs) > 0) echo 'interpreter.addFunctions({' . implode(',',$fs) . '});';
		exit();
	}
}
?>
<!DOCTYPE html><html>
<head>
	<title>Accho v<?php echo VERSION ?></title>
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
	<link rel="stylesheet" href="bootstrap.min.css" />
</head>
<body>
	<form name="mform" action="<?php echo basename($_SERVER['PHP_SELF']) ?>" method="post" id="mform">
		<input type="hidden" name="ot_value" id="ot_value" value="{input}" />
		<input type="hidden" name="socks_value" id="socks_value" value="" />
		<div style="text-align:center">
			<div class="option">
				<div class="row">
					<div class="col-md-6 col-md-offset-3"><textarea rows="15" cols="75" name="ml" id="ml" class="form-control"></textarea></div>
				</div>
				<div class="row">
					<div style="margin-top: 10px" class="col-md-12">
						Seperator: <input type="text" value="|" name="sep" size="1"/>
						| <input type="checkbox" name="adc" checked id="adc" /> <label for="adc">Auto Detect Column</label>
						<div id="columnlist">
						User column (start from 0): <input type="text" value="1" name="mc" size="1" />
						| Pass column: <input type="text" value="2" name="pc" size="1"/>
						</div>
					</div>
				</div>
			</div>
		<div style="margin: 10px" id="filter"></div>
		<div>
			<button type="button" class="btn btn-default" name="showTemplate">Output Template</button>
			<button type="submit" class="btn btn-default" id="sbutton">Start</button>
			<button type="button" class="btn btn-default" id="cbutton">Clear</button>
			<button type="button" class="btn btn-default" name="showSocks">Socks setting</button>
		</div>
		<div id="loadingIMG"><img src="loading.gif" border="0" alt="Checking..." title="Checking..."/></div>
			<div id="result" style="margin-top:2px">
			</div>
		</div>
	</form>
	
	<div class="modal fade" id="modal" tabindex="-1" role="dialog" aria-labelledby="basicModal" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title"></h4>
				</div>
				<div class="modal-body">
					<textarea class="modal-text form-control"></textarea>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-primary button-save" data-dismiss="modal">Save changes</button>
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>

	<script src="jquery-1.11.2.min.js"></script>
	<script src="bootstrap.min.js"></script>
	<script src="checkacc.js"></script>
	<script src="outputtemplate.js"></script>
</body>
</html>
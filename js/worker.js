importScripts('jquery.hive.pollen.js');
importScripts('outputtemplate.js');

var classes;
var filter;
var ajaxurl;

var interpreter = new OutputTemplate();
interpreter.setParamsList({
	user:"str",
	pass:"str",
	input:"str",
});
importScripts('../index.php?js=fields_declare');
interpreter.addFunctions({
	addClass: {
		args: ["str"],
		opargs: [],
		type: "void",
		code: function(arg) {
			classes[arg[0]] = arg[0];
		}
	},
	setFilter: {
		args: ["str"],
		opargs: ["str"],
		type: "void",
		code: function(arg) {
			filter = escapeHtml(arg[0]);
			sendMessage("setFilter",arg);
		}
	},
	unsetFilter: {
		args: [],
		opargs: [],
		type: "void",
		code: function(arg) {
			filter = undefined;
		}
	},
	removeClass: {
		args: ["str"],
		opargs: [],
		type: "void",
		code: function(arg) {
			delete classes[arg[0]];
		}
	},
	removeAllClass: {
		args: ["str"],
		opargs: [],
		type: "void",
		code: function(arg) {
			classes = new Object();
		}
	},
});
importScripts('../index.php?js=functions_declare');
interpreter.setDebug({
	linespace: "<br>",
	func: function(string) { sendMessage("debug",string); },
	showToken: false,
	showObject: false,
	showError: false,
});
interpreter.setDataFunc(function(o,key) {
	var r = new Object();
	try {
		if (key in o.requestData) {
			var config = new Object();
			config["user"] = o.data["user"];
			config["pass"] = o.data["pass"];
			config["input"] = o.data["input"];
			config[key] = o.requestData[key];
			
			$.ajax.post({
				url: ajaxurl,
				data: {data: JSON.stringify(config)},
				success: function(data) {
					try {
						if (data.text=="") throw "Timeout";
						r = JSON.parse(data.text);
					} catch (e) {
						r["error"] = e;
						if (!(key in r)) r[key] = new Object();
						r[key]["status"] = "ERROR";
					}
				}
			});
		} else throw "Data not found";
	} catch (e) {
		r["error"] = e;
		if (!(key in r)) r[key] = new Object();
		r[key]["status"] = "ERROR";
	}
	return r;
});

self.addEventListener('message', function(e) {
	var d = JSON.parse(e.data);
	var mess = d.message;
	var data = d.data;
	
	if (mess === "prescan") { // data = string
		try {
			readOutputTemplate(data);
			sendMessage("prescan succeed",{input:data,requestData:interpreter.requestData});
		} catch (e) {
			sendMessage("prescan failed",e);
		}
	}
	if (mess === "scan") { // data = {user:string,pass:string,input:array}
		classes = new Object();
		filter = undefined;
		interpreter.reset();
		interpreter.data = data.data;
		interpreter.requestData = data.requestData;
		interpreter.input = data.input;
		ajaxurl = data.action;
		
		classes = new Object();
		filter = undefined;
		
		try {
			var s = escapeHtml(interpreter.scan());
			
			var r = interpreter.data;
			var cl = [];
			for (var cn in classes) cl.push(cn);
			var c = (cl.length > 0) ? "class=\""+cl.join(" ")+"\" " : "";
			var title = escapeHtml(r["user"]+"|"+r["pass"]);
			if (interpreter.errors.length > 0) {
				title = escapeHtml(interpreter.errors.join("\n"));
				filter = "error";
				c = "class=\"error\" ";				
			}
			sendMessage("result",{title:title,filter:filter,classes:c,output:s});
		} catch (e) {
			sendMessage("error",""+e);
		}
	}
}, false);

function readOutputTemplate(v) {	
	interpreter.setInput(v);
	interpreter.prescan();
}
function sendMessage(m,d) {
	self.postMessage(JSON.stringify({message:m,data:d}));
}

function escapeHtml(text) {
  return text
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
}
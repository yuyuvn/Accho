/*
	Clicia Scripting Language v1.0
	
	Syntax:
	{field}
	{fieldRoot.fieldChild}
	@function(argument1,argument2)
	boolean?true_exp
	boolean?true_exp:false_exp
*/

/*
	How to use:
	
	**** Create new Object ****
	var ot = new OutputTemplate()
	
	**** Config debug ****
	ot.setDebug({
		linespace: "\r\n", // line breack string for debug message
		func: function(string) { alert(string); }, // function callback when debug on
		showToken: true, // show each token be created
		showObject: true, // show each object be created
	});
	
	**** Add custom functions ****
	ot.addFunctions({
		function_name: { // consist only character or number (first character must be not-digit character)
			args: ["str","int"], // arguments type (str, int or bool)
			opargs: [], // optional arguments, like args
			type: "void", // return type, may have str or int or bool. Other values mean void
			code: function(arg) { alert(arg[0] + (arg[1]+1)); } // function code
		}, // you can add many function at one, or overwrite old function include default function
	});
	
	**** Add params ****
	ot.setParamsList({
		param1: "str", // param name and type
		param2: { // you can add nested params
			paramChild1: "int", // param type must be str, int or bool
			paramChild2: "bool" // param name only include only character or number (first character must be not-digit character)
		},
		param3: "int" // param name must diffrent to "input"
	});
	
	**** Set input ****
	ot.setInput("Hello World");
	
	**** Scan data request ****
	var dr = ot.scan(); // if don't set setData, it'll be premode
	
	**** Set data ****
	ot.setData(data);
	
	**** Run ****
	var r = ot.scan(); // if don't have data but still want to run, manual set premode to false: ot.premode = false;
	ot.reset(); // reset if you want to run second time, don't need to run reset if set new Input
*/

function OutputTemplate() {
	this.setInput("");
	this.paramsList = new Object();
	this.functionList = new Object();
	this.debug = {
		linespace: "<br>"
	};
	
	// default function
	this.addFunctions({
		upcase: {
			args: ["str"],
			opargs: [],
			type: "str",
			code: function(arg) {
				return arg[0].toUpperCase();
			}
		},
		downcase: {
			args: ["str"],
			opargs: [],
			type: "str",
			code: function(arg) {
				return arg[0].toLowerCase();
			}
		},
		string_replace: {
			args: ["str","str","str"],
			opargs: [],
			type: "str",
			code: function(arg) {
				return arg[0].replace(arg[1],arg[2]);
			}
		},
		preg_replace: {
			args: ["str","str","str"],
			opargs: ["str"],
			type: "str",
			code: function(arg) {
				if (arg.length > 3) return arg[0].replace(new RegExp(new RegExp(arg[1],arg[2]),arg[3]));
				else return arg[0].replace(new RegExp(arg[1]),arg[2]);
			}
		},
		match: {
			args: ["str","str"],
			opargs: ["str"],
			type: "bool",
			code: function(premode,arg) {
				if (arg.length > 2) return arg[0].match(new RegExp(arg[1],arg[2]));
				else return arg[0].match(new RegExp(arg[1]));
			}
		},
	});
}
OutputTemplate.prototype.setDebug = function(debugs) {
	for (var d in debugs) {
		this.debug[d] = debugs[d];
	}
}
OutputTemplate.prototype.setDataFunc = function(func) {
	this.dataFunc = func;
}
OutputTemplate.prototype.setInput = function(input) {
	this.input = input;
	this.requestData = new Object();
	this.reset();
}
OutputTemplate.prototype.setParamsList = function(params) {
	this.paramsList = params;
}
OutputTemplate.prototype.addParamsList = function(params) {
	this.paramsList = this.merge(this.paramsList,params);
}
OutputTemplate.prototype.addFunctions = function(func) {
	this.functionList = this.merge(this.functionList,func);
}
OutputTemplate.prototype.merge = function(a1,a2) {
	if (typeof a1 != "object" || typeof a2 != "object") throw "Can't merge this type";
	var r = new Object();
	for (var k in a1) {
		if (k in a2) {
			if (typeof a2[k] != "object") r[k] = a2[k];
			else r[k] = (typeof a1[k] == "object") ? this.merge(a1[k],a2[k]) : a1[k];
		} else r[k] = a1[k];
	}
	for (var k in a2) {
		if (k in r) continue;
		r[k] = a2[k];
	}
	return r;
}
OutputTemplate.prototype.reset = function() {
  this.pointer = 0;
  this.readingChar = "";
  this.output = "";
  this.errors = [];
  this.data = new Object();
}
OutputTemplate.prototype.readChar = function() {
	if (this.pointer < this.input.length) {
		this.readingChar = this.input.charAt(this.pointer);
		this.pointer++;
	} else if (this.pointer == this.input.length) {
		this.readingChar = "";
		this.pointer++;
	} else throw "input end";
}
OutputTemplate.prototype.nextChar = function() {
	if (this.pointer < this.input.length) {
		this.readingChar = this.input.charAt(this.pointer+1);
	} else return false;
}
OutputTemplate.prototype.readToken = function() {
	var s = "";
	try {
		switch(this.readingChar) {
		case "{": this.readChar();
			return this.newToken("{");
		case "}": this.readChar();
			return this.newToken("}");
		case "(": this.readChar();
			return this.newToken("(");
		case ")": this.readChar();
			return this.newToken(")");
		case "@": this.readChar();
			return this.newToken("@");
		case "=": this.readChar();
			return this.newToken("=");
		case "~": this.readChar();
			return this.newToken("~");
		case "?": this.readChar();
			return this.newToken("?");
		case ":": this.readChar();
			return this.newToken(":");
		case "&": this.readChar();
			return this.newToken("&");
		case "|": this.readChar();
			return this.newToken("|");
		case ",": this.readChar();
			return this.newToken(",");
		case ">":
			this.readChar();
			if (this.readingChar == "=") {
				this.readChar();
				return this.newToken(">=");
			}
			return this.newToken(">");
		case "<":
			this.readChar();
			if (this.readingChar == "=") {
				this.readChar();
				return this.newToken("<=");
			}
			return this.newToken("<");
		case "":
			throw "input end";
		default:
			if (!isNaN(parseInt(this.readingChar))) {
				try {
					while (!isNaN(parseInt(this.readingChar))) {
						s += this.readingChar;
						this.readChar();
					}
				} catch(err) {
				}
				return this.newToken('int',parseInt(s));
			} else {
				try {
					while (/[^0-9\{\}\(\)\@\=\~\>\<\r\n\?\:\,\|\&]/.test(this.readingChar)) {
						switch (this.readingChar) {
							case "\\":
								try {
									this.readChar();
								} catch (err) {
									if (err === "input end") this.readingChar = "\\";
								}
								if (this.isLineBreak(true)) {
									try {
										while (true) {
											if (this.readingChar == "\\") {
												this.readChar();
												if (this.isLineBreak(true))	break;
											} else {
												this.readChar();
											}
										}
									} catch(err) {
										if (err != "input end") throw err;
									}
									continue;
								}
								s += this.readingChar == "n" ? "\n" : this.readingChar;
								break;
							default:
								s += this.readingChar;
						}
						this.readChar();
					}
				} catch(err) {
					if (err != "input end") throw err;
				}
				return this.newToken('str',s);
			}
			throw "undentifed token";
		}
	} catch (err) {
		if (err === "input end") return this.newToken('end');
		else throw err;
	}
}
OutputTemplate.prototype.isLineBreak = function(skipLineBreak) {
	if (this.readingChar == "\r" || this.readingChar == "\n") {
		if (skipLineBreak) {
			if (this.readingChar == "\r") this.readChar();
			if (this.readingChar == "\n") this.readChar();
		}
		return true;
	}
	return false;
}
OutputTemplate.prototype.nextToken = function() {
	return this.token = this.readToken();
}
OutputTemplate.prototype.eat = function(tokenId) {
	if (this.token.id != tokenId) {
		throw "wrong token";
	} else this.nextToken();
}
OutputTemplate.prototype.readBranch = function(condition) {
	this.eat("?");
	var r = "";
	var op = this.premode;
	if (condition) {
		r = this.readExps();
		if (this.token.id == ":") {
			this.premode = true;
			this.nextToken();
			this.readExps();
			this.premode = op;
		}
	} else {
		this.premode = true;
		this.readExps();
		this.premode = op;
		if (this.token.id == ":") {
			this.nextToken();
			r = this.readExps();
		}
	}
	return r;
}
OutputTemplate.prototype.readExps = function() {
	var tid = this.token.id;
	var r = "";
	switch (tid) {
		case 'end':case ':':case ')':
			return r;
		default:
			r += this.readExp();
			r += this.readExps();
			break;
	}
	return r;
}
OutputTemplate.prototype.readExp = function() {
	var tid = this.token.id;
	var o;
	var r = "";
	switch (tid) {
		case 'int':
		case 'str':
		case '{':
		case '@':
		case '(':
			o = this.readFinalValue();
			if (o.type == "bool") {
				r += this.readBranch(o.value);
			} else {
				r += o.value;
			}
			break;
		case '}':
		case '=':
		case '~':
		case '?':
		case '&':
		case '|':
		case ',':
		case '>':
		case '<':
		case '>=':
		case '<=':
			r += this.token.id;
			this.nextToken();
			break;
		default:
			throw "Unexprected token "+ this.token.id;
	}
	return r;
}
OutputTemplate.prototype.readConst = function() {
	if (this.token.id == 'int' || this.token.id == 'str')
		return this.newObject(this.token.id,this.token.value);
	else throw "Unexprected token "+ this.token.id;
}
OutputTemplate.prototype.readField = function() {
	var premode = this.premode;
	this.eat("{");
	var t = this.token;
	this.nextToken();
	this.eat("}");
	if (t.id == 'int') {
		return this.newObject("str",premode ? "" : this.inputs[t.value]);
	} else if (t.id == 'str') {
		var tree = t.value.split(".");
		var dt = premode ? this.requestData : this.data;
		var pl = this.paramsList;
		var key;
		for (var i=0;i<tree.length;i++) {
			key = tree[i];
			if (key in pl) {
				pl = pl[key];
			} else throw "Field not exists: "+t.value;
			if (!premode) {
				if (key in dt) {
					dt = dt[key];
				} else {
					if (i == 0) {
						var gd = this.dataFunc(this,key);
						if ("error" in gd) {
							this.errors.push(gd["error"]);
							this.data = this.merge(this.data,gd);
							return this.newObject("str","");
						}
						this.data = this.merge(this.data,gd);
						dt = this.data[key];
					} else {
						return this.newObject("str","");
					}
				}
			} else {
				if (!(key in dt)) dt[key] = new Object();
				dt = dt[key];
			}
		}
		if (typeof pl != "string") throw "Field not exists: "+t.value;
		if (premode) return this.newObject(pl,1);
		else return this.newObject(pl,dt);
	} else throw "Unexprected token "+ tid;
}
OutputTemplate.prototype.readFunction = function() {
	this.eat("@");
	var fn = this.token.value;
	this.eat("str");
	this.eat("(");
	var ar = [];
	while (this.token.id != ")") {
		ar.push(this.readFinalValue());
		if (this.token.id != ")") this.eat(",");
	}
	this.eat(")");
	if (fn in this.functionList) {
		var f = this.functionList[fn];
		var args = this.readArgs(ar,f.args,f.opargs);
		if (this.premode) {
			switch (f.type) {
				case "int": return this.newObject("int",0);
				case "bool": return this.newObject("bool",false);
				default: break;
			}
		} else {
			var t = f.type;
			var r = f.code(args);
			switch (t) {
			case "bool": case "number": case "string":
				return this.newObject(f.type,r);
			default: break;
			}
		}		
	} else throw "Function not exists";
	return this.newObject("str","");
}
OutputTemplate.prototype.readArgs = function(args,list,olist) {
	var r = [];
	for (var i=0;i<list.length;i++) {
		if (i >= args.length) throw "Argument number exeption";
		try {
			this.checkEqualType2(args[i].type,list[i]);
		} catch (err) {
			if (err == "Wrong type") throw "Argument exception";
			throw err;
		}
		r.push(args[i].value);
	}
	for (j=0;i<args.length;i++,j++) {
		if (j >= olist.length) break;
		try {
			this.checkEqualType2(args[i].type,olist[j]);
		} catch (err) {
			if (err == "Wrong type") throw "Argument exception";
			throw err;
		}
		r.push(args[i].value);
	}
	return r;
}
OutputTemplate.prototype.readGroup = function() {
	this.eat("(");
	var o;
	var r = this.newObject("str","");
	while (this.token.id != ")") {
		o = this.readFinalValue();
		if (o.type == "bool") {
			if (this.token.id == "?") {
				r.value += this.readBranch(o.value);
			} else {
				r = o;
				break;
			}
		} else {
			r.value += o.value;
		}
	}
	this.eat(")");
	return r;
}
OutputTemplate.prototype.readValue = function() {
	var tid = this.token.id;
	var o;
	var t;
	switch (tid) {
		case 'int':
		case 'str':
			o = this.readConst();
			this.nextToken();
			break;
		case '{':
			o = this.readField();
			break;
		case '@':
			o = this.readFunction();
			break;
		case '(':
			o = this.readGroup();
			break;
		default:
			o = this.newObject("str",this.token.id);
			this.nextToken();
			break;
	}
	return o;
}
OutputTemplate.prototype.readFinalValue = function() {
	var o = this.readValue();
	var t = this.token.id;
	var ot = this.token;
	var rv;
	switch (t) {
		case "=":case "~":case ">":case ">=":case "<":case "<=":case "&":case "|":
			this.nextToken();
			if (this.token.id=="?") rv = this.newObject("str","");
			else rv = this.readFinalValue();
			return this.readCondition(o,ot,rv);					
		default:
			return o;
	}
}
OutputTemplate.prototype.checkEqualType = function(l,r) {
	if (l == r) return true;
	else if (l == "str" || r == "str") {
		if ((l = "int") || (r = "int")) {
			return true;
		}
	}
	throw "Wrong type";
}
OutputTemplate.prototype.checkEqualType2 = function(l,r) {
	if (l == r) return true;
	else if (l == "str") {
		if (r = "int") {
			r = "str";
			return true;
		}
	}
	throw "Wrong type";
}
OutputTemplate.prototype.checkInt = function(o) {
	if (o.type == "int") return true;
	if (!isNaN(o.value)) return true;
	throw "Wrong type";
}
OutputTemplate.prototype.checkBool = function(t) {
	if (t == "bool") return true;
	throw "Wrong type";
}
OutputTemplate.prototype.readCondition = function(lv,top,rv) {
	var op = top.id;
	try {
		switch (op) {
			case "=":
				this.checkEqualType(lv.type,rv.type);
				return this.newObject("bool",lv.value == rv.value);
			case "~":
				this.checkEqualType(lv.type,rv.type);
				return this.newObject("bool",lv.value != rv.value);
			case ">":
				this.checkInt(lv);
				this.checkInt(rv);
				return this.newObject("bool",lv.value > rv.value);
			case "<":
				this.checkInt(lv);
				this.checkInt(rv);
				return this.newObject("bool",lv.value < rv.value);
			case ">=":
				this.checkInt(lv);
				this.checkInt(rv);
				return this.newObject("bool",lv.value >= rv.value);
			case "<=":
				this.checkInt(lv);
				this.checkInt(rv);
				return this.newObject("bool",lv.value <= rv.value);
			case "&":
				this.checkBool(lv.type);
				this.checkBool(rv.type);
				return this.newObject("bool",lv.value && rv.value);
			case "|":
				this.checkBool(lv.type);
				this.checkBool(rv.type);
				return this.newObject("bool",lv.value || rv.value);
			default:
				throw "wrong op";
		}
	} catch (err) {
		if (err == "Wrong type") {
			var s = "";
			if (lv.type != "bool") s += lv.value;
			s += top.id;
			if (rv.type != "bool") s += rv.value;
			return this.newObject("str",s);
		}
	}
}
OutputTemplate.prototype.prescan = function() {
	this.premode = true;
	this.read();
}
OutputTemplate.prototype.scan = function() {	
	this.premode = false;
	return this.read();
}
OutputTemplate.prototype.read = function() {
	var re = "";
	try {
		this.readChar();
		this.token = this.readToken();
		while (this.token.id != "end") {
			re += this.readExps();
			if (this.token.id == ':' || this.token.id == ')') {
				re += this.token.id;
				this.nextToken();
			}
		}
	} catch(err) {
		if (err != "input end")	{
			this.db("Error: "+err+"\nCol: "+this.pointer);
			throw "Error: "+err+"\nCol: "+this.pointer;
		}
	}
	return re;
}
OutputTemplate.prototype.newToken = function(id,value) {
	if (this.debug["showToken"]) this.dbln("Token["+id+"]"+((typeof value != 'undefined')?"="+value:""));
	return {id:id,value:value};
}
OutputTemplate.prototype.newObject = function(type,value) {
	if (this.debug["showObject"]) this.dbln("Obj["+type+"]="+value);
	return {type:type,value:value};
}
OutputTemplate.prototype.db = function(string) {
	if (typeof this.debug["func"] != "undefined") this.debug["func"](string);
}
OutputTemplate.prototype.dbln = function(string) {
	this.db(string+this.debug["linespace"]);
}
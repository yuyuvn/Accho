var checking = false;
var mlist;
var ajax;
var worker;
var prescan_success_callback;
var requestData;
var title;
function setCookie(cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays*24*60*60*1000));
    var expires = "expires="+d.toGMTString();
    document.cookie = cname + "=" + cvalue + "; " + expires;
}
function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for(var i=0; i<ca.length; i++) {
        var c = ca[i].trim();
        if (c.indexOf(name) == 0) return c.substring(name.length,c.length);
    }
    return "";
}
$(function() {
	title = document.title;
	$('button:button[name="showTemplate"]').click(function() {
		//$( "#outputTemplate" ).dialog("open");
		showModal("Output theme",$("#ot_value").val(), function(event) {
			var v = event.data.body.val();
			worker = new Worker("js/worker.js");
			worker.addEventListener('message', function(e) {
				var d = JSON.parse(e.data);
				var mess = d.message;
				var data = d.data;
				if (mess === "prescan succeed") {
					event.data.modal.modal('hide');
					terminateWorker();
					setCookie("ca_outputTemplate",data.input,365);
					$("#ot_value").val(data.input);
					requestData = data.requestData;
				} else if (mess === "prescan failed") alert("Output template invalid: " + data);
			}, false);
			sendMessage("prescan",v);
			return false;
		});
	});
	$('button:button[name="showSocks"]').click(function() {
		showModal("Socks list",$("#socks_value").val(), function(event) {
			var v = event.data.body.val();
			$("#socks_value").val(v);
			return true;
		});
	});
	$("#mform").submit(function( event ) {
		if (checking) {
			StopChecking();
		} else {
			StartChecking();
		}
		return false;
	});

	$("#adc").change(function() {
		if (this.checked) {
			$("#columnlist").css("display","none");
		} else {
			$("#columnlist").css("display","block");
		}
	});


	$("#cbutton").click(function() {
		clear();
	});

	$("#ot_value").val(getCookie("ca_outputTemplate"));

	worker = new Worker("js/worker.js");
	worker.addEventListener('message', function(e) {
		var d = JSON.parse(e.data);
		var mess = d.message;
		var data = d.data;
		if (mess === "prescan succeed") {
			terminateWorker();
			requestData = data.requestData;
		} else if (mess === "prescan failed") alert("Output template invalid: " + data);
	}, false);
	sendMessage("prescan",$("#ot_value").val());

	$(document).on("change", "#filter input:checkbox", function(eventObject) {
		if ($(this).is(':checked')) {
			$("[filter="+$(this).attr("ref")+"]").css("display","");
		} else {
			$("[filter="+$(this).attr("ref")+"]").css("display","none");
		}
	});

	clear();
});
function clear() {
	$("#result").html("");
	$("#filter").html('Filter: <input type="checkbox" ref="error" id="filter_error" checked /> <label for="filter_error">Error</label>');
}
function changeFavicon(src) {
	var link = document.createElement('link');
    $("#dynamic-favicon").remove();
    link.id = 'dynamic-favicon';
    link.rel = 'icon';
    link.href = src;
    $('head').append(link);
}
function terminateWorker() {
	if (worker) {
		worker.terminate();
		worker = undefined;
	}
}
function StartChecking() {
	if (checking) return;
	if ($("input:text[name=sep]").val()=='') return;

	$("#sbutton").html("Stop");
	checking = true;

	jQuery(window).bind(
		"beforeunload",
		function() {
			return true;
		}
	);

	$("#loadingIMG").css("visibility","visible");
	changeFavicon('ass/loading.ico');

	$(".option").find("input,textarea").attr("disabled", "disabled");
	$('#modal').find(".button-save").attr("disabled", "disabled");
	mlist = $.trim($("#ml").val()).split("\n");

	auto_detect_column();

	Processing(true);
}
function StopChecking() {
	if (!checking) return;
	$("#sbutton").html("Start");
	checking = false;
	try {
		ajax.abort();
	}
	catch (err) {}
	changeFavicon('ass/favicon.ico');
	document.title = title;
	if (worker) worker.terminate();

	jQuery(window).unbind("beforeunload");

	$(".option").find("input,textarea").removeAttr("disabled");
	$('#modal').find(".button-save").removeAttr("disabled");
	$("#loadingIMG").css("visibility","hidden");
}

function escapeHtml(text) {
  return text
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
}
function auto_detect_column() {
	if ($("#adc").is(":checked")) {
		var c = detect_column();
		if (c!==false) {
			$("input:text[name=mc]").val(c[0]);
			$("input:text[name=pc]").val(c[1]);
		}
	}
}
function detect_column() {
	var rn = false;
	var m,c,r;
	$.each(mlist, function( index2, value ) {
		m = value.split($("input:text[name=sep]").val());
		c = 0;
		r = -1;
		$.each(m, function( index, value ) {
			if (value.match(/\@/)) {
				c++;
				r = index;
			}
		});
		if (c==1) {
			if (r<m.length-1) {
				rn = [r,r+1];
				return false;
			} else if (r>0) {
				rn = [r,r-1];
				return false;
			}
		}
	});
	return rn;
}
function Processing(next) {
	if (mlist.length==0) {
		StopChecking();
		return;
	}
	value = $.trim(mlist[0]);
	if (value=='') {
		mlist = mlist.slice(1);
		$("#ml").val(mlist.join("\n"));
		if (checking&&next) Processing(true);
		return;
	}
	m = value.split($("input:text[name=sep]").val());
	mc = parseInt($("input:text[name=mc]").val());
	pc = parseInt($("input:text[name=pc]").val());
	if (typeof m[mc] == 'undefined'||typeof m[pc] == 'undefined') {
		mlist = mlist.slice(1);
		$("#ml").val(mlist.join("\n"));
		if (checking&&next) Processing(true);
		return;
	}
	document.title = m[mc]+" - "+title;

	worker = new Worker("js/worker.js");
	worker.addEventListener('message', function(e) {
		var d = JSON.parse(e.data);
		var mess = d.message;
		var data = d.data;

		if (mess === "result") {
			$("#result").append("<div title=\""+data.title+"\" "+(data.filter?("filter=\""+data.filter+"\" "+($('#filter_'+data.filter).is(":checked")?"":"style=\"display:none\" ")):"")+data.classes+">"+data.output+"</div>");
			mlist = mlist.slice(1);
			$("#ml").val(mlist.join("\n"));
			worker.terminate();
			if (checking&&next) Processing(true);
		} else if (mess === "setFilter") {
			filter = escapeHtml(data[0]);
			if ($("#filter").find("#filter_"+filter).length == 0) {
				$("#filter").append(' | <input type="checkbox" id="filter_'+filter+'" ref="'+filter+'" checked /> <label for="filter_'+filter+'">'+(data.length>1?escapeHtml(data[1]):filter)+'</label> ');
			}
		} else if (mess === "error") {
			alert(data);
			StopChecking();
		} else if (mess === "debug") {
			$("body").append(data);
		} else if (mess === "stop") {
			StopChecking();
		}
	}, false);
	sendMessage("scan",{data:{user:m[mc],pass:m[pc],input:value},requestData:requestData,input:$("#ot_value").val(),inputs:m,action:$("#mform").attr("action")});
}
function sendMessage(m,d) {
	worker.postMessage(JSON.stringify({message:m,data:d}));
}
function showModal(title,body,callback) {
	$('#modal').find(".modal-title").html(title);
	$('#modal').find(".modal-text").val(body);
	$('#modal').modal('show');
	if (callback) $('#modal').find(".button-save").on('click', {body: $('#modal').find(".modal-text"), modal: $('#modal')}, callback);
	else $('#modal').find(".button-save").off('click');
}

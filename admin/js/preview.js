/******************************************************************************
 * util.js
 *
 * Copyright (c) 2011-2012, Academic ADL Co-Lab, University of Wisconsin-Extension
 * http://www.academiccolab.org/
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301  USA
 *****************************************************************************/
/*
 *  @author Cathrin Weiss (cathrin.weiss@uwex.edu)
 */

function doEncode(input){
	var  result = "";
	for (var i = 0; i < input.length; i++){
		var uri = encodeURIComponent(input[i]);
		var esc = escape(input[i]);
		if (uri == esc)
			result += uri;
		else if (uri == input[i])
			result += esc;
		else 
			result += uri;
	}
	return result;
}

/***
 * Read a json file, return the resulting object
 * argPath: the path
 * return: object
 */
function readJSON(argPath) {
	var d = new Date();
    var fPath = argPath + "?"+d.getTime();
    var json = null;
    $.ajax({
           'async': false,
           'global': false,
           'url': fPath,
           'dataType': "json",
           'timeout':2000,
           'success': function (data) {
			 json = data;
           },
           error: function (data) {
           json = null;
           }
           });
    return json;
    
}

/***
 * Read a text file, return the resulting object
 * argPath: the 
 * return: object
 */
function readText(argPath) {
	var d = new Date();
    var fPath = argPath+ "?"+d.getTime();
    var json = null;
    $.ajax({
           'async': false,
           'global': false,
           'url': fPath,
           'dataType': "text",           
           'success': function (data) {
           json = data;
           },
           error: function (data) {
           json = null;
           }
           });
    return json;
    
}

/*** 
* Display an item during preview
*/
function displayItem(div, item, bDir, title){
	div.append("title: "+item.title + "<br/>");
	div.append("type: "+item.type + "<br/>");
	var iPath = item.path;
	//iPath = iPath.replace(/\?/g, "%3F"); 
	var descPath = bDir + iPath + ".dsc";
	descPath = descPath.replace(title, encodeURIComponent(title));
	var path = bDir + iPath; 
	var ip = path.replace(title, encodeURIComponent(title));
	ip = ip.replace(/\'/g, escape("\'"));
	if (item.type == "text"){
		var dPath = path.replace(title, encodeURIComponent(title));
		var data = readText(dPath);				
		div.append("data: "+data + "<br/>");
		div.append("<hr><br/>");
	} else if (item.type == "image") {
		
		div.append("<img src='"+ip+"'></img><br/>");
		data = readText(descPath);
		div.append("description: "+data + "<br/>");
		div.append("<hr><br/>");
	} else if (item.type == "video") {
			var video = "<video src='"+ip+"'   controls >\
	        </video><p/>";
			var aTag = "<a href='"+ip+"' target='_blank'>Click here</a> in case video does not display inline<br/>";
			div.append(video);
			div.append(aTag);
			data = readText(descPath);
			div.append("description: "+data + "<br/>");
			div.append("<hr><br/>");
	} else if (item.type == "audio") {
			var audio = "<audio src='"+ip+"'   controls >\
			</audio><p/>";
			var aTag = "<a href='"+ip+"' target='_blank'>Click here</a> in case audio does not display inline<br/>";
			div.append(audio);
			div.append(aTag);
			data = readText(descPath);
			div.append("description: "+data + "<br/>");
			div.append("<hr><br/>");
	} else if (item.type == "question") {
		var data = readText(descPath);
		div.append("Question title: "+item.title+"<br/>");
		div.append("Question text: "+data+"<br/>");
		if (item.attachments && item.attachments.length > 0){
			div.append("question media: <br/>");
			var j = 0;
			while (j < item.attachments.length){
				displayItem(div, item.attachments[j], bDir, title);
				j++;
			}
		}
		div.append("question answers: <br/>");
		var aPath = path.replace(title, encodeURIComponent(title));
		var answerData = readJSON(aPath);
		var j =0;
		while (j < answerData.length){
			div.append("answer "+(j+1)+": "+answerData[j].text + "<br/>");
			div.append("  feedback: "+answerData[j].feedback + "<br/>");
			if ("correct" in answerData[j] && answerData[j].correct == "checked") {
				div.append("correct.<br/>");
			} else {
				div.append("wrong.<br/>");
			}
			j++;
		}
		div.append("<br/><br/><hr/>");

	} else if (item.type == "quiz"){
		var loc = path + "/manifest";
		loc = loc.replace(title, encodeURIComponent(title));
		var manifest = readJSON(loc);
		if (manifest == null || manifest.length == 0){
			div.append("No Quiz manifest! This likely means that no questions were specified for this quiz. <br/><hr/>");
			return false;
		} 
		
		var i = 0;
		while (i< manifest.length) {
			displayItem(div, manifest[i], bDir, title);
			i++;
		}
	
	} else {
		div.append(item.type + "<br/>");
		div.append(item.path + "<br/>");
		div.append("<hr><br/>");
	}
	
}

/***
* Render preview screen
*/
function renderPreview(baseDir, title, div){
	div.empty();
	var loc = window.location + "";	
	loc = loc.replace("admin/overview.php#","");
	loc = loc.replace("admin/overview.php","");	
	loc = loc + baseDir;
	var bLoc = loc;
	loc = loc + encodeURIComponent(title); 
	var d = new Date();
	loc = loc + "/manifest?" + d.getTime();
	var manifest = readJSON(loc);	
	if (manifest == null || manifest.length == 0){
		return false;
	}
	var i = 0;
	while (i< manifest.length) {
		displayItem(div, manifest[i], bLoc, title);
		i++;
	}
}

/***
* Call render preview function and open popup
*/
function doPreview(title){
	var requestWhat = 'uploads/tmp/preview-'+encodeURIComponent(title)+'/';
	var div = $("#dialog-preview");
	renderPreview(requestWhat, title, div);
	$( "#dialog-preview" ).dialog({
		height:500,
		width:600,
		modal: true,
		buttons: {
			"Close": function(){
				finalizePreview(title);
				$( this ).dialog( "close" );
			}
		}
	});
}

/***
* Clean up after preview 
*/
function finalizePreview(title){
	sendData = {'function':'removePreview','data':title};
	$.ajax({
		async: false,
		global: false,
		url: 'modify.php',
		type: 'post',
		dataType: "text",
		data: sendData,
	  success: function(data) {
		if (data == "OK.") {
		} 
	  },
	error: function(data) {
		alert("error..." + data) ;
	} 
	});
	return false;
	
}



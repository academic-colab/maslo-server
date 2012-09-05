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

/***
 * Read a json file, return the resulting object
 * argPath: the path
 * return: object
 */
function readJSON(argPath) {
    var fPath = argPath;
    if (argPath == null) {
        fPath = globalPack+"/manifest"; 
    }
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
    var fPath = argPath;
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
function displayItem(div, item, bDir){
	div.append("title: "+item.title + "<br/>");
	div.append("type: "+item.type + "<br/>");
	var descPath = bDir + item.path + ".dsc";
	var path = bDir + item.path
	if (item.type == "text"){
		var data = readText(path);				
		div.append("data: "+data + "<br/>");
		div.append("<hr><br/>");
	} else if (item.type == "image") {
		div.append("<img src='"+path+"'></img><br/>");
		data = readText(descPath);
		div.append("description: "+data + "<br/>");
		div.append("<hr><br/>");
	} else if (item.type == "video") {
			var video = '<video src="'+path+'"   controls >\
	        </video><p/>';
			var aTag = '<a href="'+path+'" target="_blank">Click here</a> in case video does not display inline<br/>'
			div.append(video);
			div.append(aTag);
			data = readText(descPath);
			div.append("description: "+data + "<br/>");
			div.append("<hr><br/>");
	} else if (item.type == "audio") {
			var audio = '<audio src="'+path+'"   controls >\
			</audio><p/>';
			var aTag = '<a href="'+path+'" target="_blank">Click here</a> in case audio does not play inline<br/>'
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
				displayItem(div, item.attachments[j], bDir);
				j++;
			}
		}
		div.append("question answers: <br/>");
		var answerData = readJSON(path);
		var j =0;
		while (j < answerData.length){
			div.append("answer "+(j+1)+": "+answerData[j].text + "<br/>");
			div.append("  feedback: "+answerData[j].feedback + "<br/>");
			if ("checked" in answerData[j]) {
				div.append("correct.<br/>");
			} else {
				div.append("wrong.<br/>");
			}
			j++;
		}
		div.append("<br/><br/><hr/>");

	} else if (item.type == "quiz"){
		var loc = bDir + item.path + "/manifest";
		loc = loc.replace(/ /g, '%20');
		var manifest = readJSON(loc);
		
		var i = 0;
		while (i< manifest.length) {
			displayItem(div, manifest[i], bDir);
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
	loc = loc + title;
	loc = loc.replace(/ /g, '%20')
	loc = loc + "/manifest"
	
	var manifest = readJSON(loc);	
	if (manifest == null)
		return false;
	var i = 0;
	while (i< manifest.length) {
		displayItem(div, manifest[i], bLoc);
		i++;
	}
}

/***
* Call render preview function and open popup
*/
function doPreview(title){
	var requestWhat = 'uploads/tmp/preview-'+title+'/';
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



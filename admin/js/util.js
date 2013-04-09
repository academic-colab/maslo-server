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
function showInfo(msg){
	$("#info-div").html(msg);
	$("#info-div").dialog({
		height:200,
		width:300,
		modal: true,
		buttons: {
			"Ok" :function(){$( this ).dialog( "close" );return false;}
		}
	});
}
	

function makeLinks() {
	$("#userBody a").unbind('click');
	$("#userBody a").click(function(e){
		var tr = $(this).parent().parent();
		fillUserInfo($(tr));
		return false;
	});
	return false;
}


function editUser(isNew){		
	var uName = $("#userName").val();
	var uPass1 = $("#userPassword1").val();
	var uPass2 = $("#userPassword2").val();
	var firstName = $("#firstName").val();
	var lastName = $("#lastName").val();
	var inst = $("#institution").val();	
	var admin = $("#adminRights").is(":checked");

	if (uName == "" || uPass1 == ""){
		showInfo("Please enter all required fields.");
		return false;
	}
	
	if (uPass1 != uPass2){	
		showInfo("Entered passwords mismatch.");
		return false;
	}
	
	isNew = (isNew == true)	? "true" : "false";
	var sendData = {'function':'editUser', 'userName':uName, 'userPass':uPass1, 'firstName':firstName, 'lastName':lastName, 'institution':inst, "isAdmin":admin, "isNew":isNew};

	var result = false;
	$.ajax({
		async: false,
		global: false,
		url: 'modify.php',
		type: 'post',
		dataType: "text",
		data: sendData,
	  success: function(data) {
		if (data == "OK.") {
			var tr = $("<tr></tr>");
			var td = "<td><a href='#'>"+uName+"</a></td>";
			tr.append(td);
			td = "<td>****</td>";
			tr.append(td);
			td = "<td>"+firstName+" "+lastName+"</td>";
			tr.append(td);
			td = "<input type='hidden' value='"+firstName+"'/>";
			tr.append(td);
			td = "<input type='hidden' value='"+lastName+"'/>";
			tr.append(td);
			td = "<td>"+inst+"</td>";
			tr.append(td);
			var hasAdmin = (admin) ? "yes" : "no";
			td = "<td>"+hasAdmin+"</td>";
			tr.append(td);
			td = $('<td class="icon"><img class="removeUser" src="images/remove.png" alt="Remove User" /></td>');
			tr.append(td);
			$('#userBody').append(tr);
			makeLinks();	
			initRemoveUserClick();
			result = true;				
	    	
		} else {
			showInfo("User not successfully saved.");
			return false;
		} 
	  },
	error: function(data) {
		showInfo("Error while saving user data: " + data) ;
	} 
	});
	return result;
}

function fillUserInfo(tr){		
	$("#userName").val(tr.find('a').html());
	$("#userName").attr("disabled", "disabled");
	
	$("#userPassword1").val("");
	$("#userPassword2").val("");
	$("#firstName").val(tr.children()[3].value);
	$("#lastName").val(tr.children()[4].value);
	$("#institution").val(tr.children()[5].innerHTML);			
	var admin = tr.children()[6].innerHTML == "yes";
	$("#adminRights").prop("checked", false);
	if (admin)
		$("#adminRights").prop("checked", true);
	$("#user-pass").dialog({
		height:520,
		width:500,
		modal: true,
		buttons: {
			"Save" :function(){
				var res = editUser(false); 
				if (res) {	
					$(tr).remove();					
					$( this ).dialog( "close" ); 							
				} 
				return false;
				},
			"Cancel":function(){$( this ).dialog( "close" );return false;}
		}
	});
}

function initRemoveUserClick() {
	$('img.removeUser').unbind('click');	
	$('img.removeUser').click(function(e) {
		var elt = $(this).parent().parent();
		$("#condemned").text("user '"+elt.find('a').html()+"'");
		var sendData = elt.find('a').html();
		sendData = sendData.replace(/ /g, ":::");
		sendData = {'function':'deleteUser','data':sendData};

		$( "#dialog-confirm" ).dialog({
			height:240,
			modal: true,
			buttons: {
				"Delete User": function() {
					$( this ).dialog( "close" );
					$.ajax({
						async: false,
						global: false,
						url: 'modify.php',
						type: 'post',
						dataType: "text",
						data: sendData,
					  success: function(data) {
						if (data == "OK.") {
					    	elt.remove();
						} 
					  },
					error: function(data) {
						alert("error..." + data) ;
					} 
					});
					return false;
				},
				Cancel: function() {
					$( this ).dialog( "close" );
					return false;
				}
			}
		});
		return false;
	});
}

function prepPreview(title){
	sendData = {'function':'preparePreview','data':title};
	$.ajax({
		async: false,
		global: false,
		url: 'modify.php',
		type: 'post',
		dataType: "text",
		data: sendData,
	  success: function(data) {
		if (data == "OK.") {
			doPreview(title);
		} 
	  },
	error: function(data) {
		alert("error..." + data) ;
	} 
	});
	return false;
}

// Category Management

function toggle(className){
	$(className).toggle();
}

function makeButtons(className) {
	var b = "&nbsp;&nbsp;&nbsp;<span class='"+className+"' style='display:none'><button class='nice tiny radius blue button' onClick='showThis(\"."+className+"\", false);'>Rename</button>&nbsp;<button class='nice tiny radius blue button' onClick='deleteThis(\"."+className+"\");'>Delete</button>&nbsp;<button class='nice tiny radius blue button' onClick='showThis(\"."+className+"\", true);'>Add Subcategory</button></span>";
	return b;
}

function deleteThis(arg, inter) {
	var name = $(arg).parent().find("span:first").text();
	var exists = false;
	$(".packCategory").each(function(){
		if ($(this).text() == name){
			exists = true;
		} else {
			var subs = $(arg).parent().parent().find(".categoryList");
			if (subs.length > 0) {
				for (var i = 0 ; i < subs.length ; i++){
					exists = exists || deleteThis($(subs[i]).find("button:first").parent(), true);
				}
			}
		}		
		return false;
	});
	if (inter != null)
		return exists;
	var oldHTML = $("#dialog-confirm").html();
	if (exists)
		$("#dialog-confirm").html("<p/>This will delete category "+name+" as well as all subcategories.<p/>\
	 Either the category or one of the subcategories is used by a current content pack. <p/>Are you sure you want to delete?<p/>");
	else 
		$("#dialog-confirm").html("<p/>This will delete category "+name+" as well as all subcategories.\
	 <p/>Are you sure you want to delete?<p/>");
	$("#dialog-confirm").dialog({
		height:300,
		width:400,
		modal: true,
		buttons: {
			OK: function(){
				$(arg).parent().parent().remove();
				$( this ).dialog( "close" );
				return false;
			},
			Cancel: function(){
				$( this ).dialog( "close" );
				return false;
			}
		},
		close : function(){
			$("#dialog-confirm").html(oldHTML);
			return false;
		}
	});
	return false;

}

function showThis(arg, makeSub){
	var tf = $(arg).parent().children()[0];
	if (!makeSub)
		$( "#name-edit" ).children()[0].value = tf.innerHTML;
	$( "#name-edit" ).dialog({
		height:200,
		width:400,
		modal: true,
		buttons: {	
			Save: function(){
				if ( $( "#name-edit" ).children()[0].value == ""){
					$( this ).dialog( "close" );
					return false;
				}
				var nVal = $( "#name-edit" ).children()[0].value
				var wantExit = false;
				$($( "#category-edit" ).find('.categoryList')).each(function(){
					var other = $($($(this).children()[0]).children()[0]).text();
					if (other == nVal){
						wantExit = true;
					} 
				});
				if (!wantExit) {
					if (makeSub){
						if ($(arg).parent().parent().find('ul').length == 0) {
							if ($("#category-edit").find('ul').length == 0)
								$("#category-edit").prepend("<ul></ul>");
							else 
								$(arg).parent().parent().append("<ul></ul>");							
						}
						var curName = "cat-id-"+$("#catMaxId").val();
						$($(arg).parent().parent().find('ul')[0]).append("<li class='categoryList'><span onmouseover='toggle(\"."+curName+"\")' onmouseout='toggle(\"."+curName+"\")'><span>"+nVal+"</span>"+makeButtons(curName)+"</span></li>");
						$("#catMaxId").val(""+(parseInt($("#catMaxId").val())+1));
					} else {
						tf.innerHTML = nVal;
					}
				}
				$( this ).dialog( "close" );
				return false;
			},			
			Close: function() {
				$( this ).dialog( "close" );
				return false;
			}
		}
	});
	return false;
}

function collectCategories(fromWhere){
	var l = $(fromWhere).find('ul:first');
	if (l.length == 0)
		return [];
	var lis = l.children();
	var lst = [];
	for (var i = 0; i < lis.length; i++){
		var m = {};
		var name = $(lis[i]).find("span:first").find("span:first").text();
		m["name"] = name;
		m["subs"] = collectCategories($(lis[i]));
		lst.push(m);
	}
	return lst;
}

function getCategories() {
	
	$("#catMaxId").val("0");	
	$.ajax({
		async: false,
		global: false,
		url: '../category.php',
		dataType: "text",
	  success: function(data) {
		var json = JSON.parse(data);
		var cats = json["categories"];
		var printCats = function(catList) {
			if (catList.length == 0)
				return "";
			var result = "<ul>";
			for (var i = 0 ; i < catList.length; ++i){
				var curName = "cat-id-"+$("#catMaxId").val();				
				result += "<li class='categoryList'><span onmouseover='toggle(\"."+curName+"\")' onmouseout='toggle(\"."+curName+"\")'><span>"+catList[i].name+"</span>"+makeButtons(curName)+"</span>";
				$("#catMaxId").val(""+(parseInt($("#catMaxId").val())+1));
				result += printCats(catList[i].subs);
				result += "</li>";
			}
			result += "</ul>";
			return result;
		};
		var s = printCats(cats);
		$("#category-edit").empty();
		$("#category-edit").append(s);
		$("#category-edit").append("<span><button class='nice small radius blue button' id='addMainCat' onClick='showThis(\"#addMainCat\", true);'>Add Category</button></span>");
		$( "#category-edit" ).dialog({
			height:580,
			width:480,
			modal: true,
			buttons: {		
				Save: function(){
					var lst = collectCategories($("#category-edit"));
					lst = JSON.stringify({"categories":lst});
					var sendData = {'function':'updateCategories','data':lst};
					var dialog = this;
					$.ajax({
						async: false,
						global: false,
						url: 'modify.php',
						type: 'post',
						dataType: "text",
						data: sendData,
					  success: function(data) {
						if (data == "OK.") {
							$( dialog ).dialog( "close" );
						} 
					  },
					error: function(data) {
						alert("error..." + data) ;
					} 
					});					
					return false;
				},	
				Discard: function() {
					$( this ).dialog( "close" );
					return false;
				}
			}
		});
		
	  },
	error: function(data) {
		alert("error..." + data) ;
	} 
	});
	return false;
}






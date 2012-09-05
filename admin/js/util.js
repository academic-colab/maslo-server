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

/******************************************************************************
 * user.js
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

function postMessage(msg, fun){
	$("#info-div").html(msg);
	$('#info-div').dialog({
		autoOpen: true,
		modal: true,
		width: 450,
		position: 'center',
		buttons: {
			"OK": function() {
				if (fun != null) {
					fun();
				}
				$(this).dialog("close");
			}
		}
	  });	
}

function checkFormValues(uName, password){
	if (uName.val().trim() == '' || password.val().trim() == '') {
		postMessage("You need to enter values into the required fields.");
		return false;
	}
	return true;
}

function verifyUserCred(uName, pass, sessionId){
	var pw = CryptoJS.MD5(pass)+CryptoJS.SHA256(sessionId);
	pw = CryptoJS.SHA256(pw);
	var sendData =  "userName="+uName+"&password="+pw;
	$.post("index.php",sendData,
	   function(data) {		 
		if (data=="NOK."){
			var action = function(){window.location.reload();}
			postMessage("Incorrect user name or password. Please check your credentials.", action);
		} else {
	     document.location.href=data;
		}
	   });
}

function initUser(sessionId){
	$('#user-pass').dialog({
		autoOpen: true,
		modal: true,
		width: 650,
		position: 'center',
		buttons: {
			"Login": function() {
				if (checkFormValues($("#userName"),$("#userPassword"))) {
					var userName = $("#userName").val();
					var userPW = $("#userPassword").val();
					$(this).dialog("close");
					verifyUserCred(userName, userPW, sessionId);
					
				}
			},
			"Browse as guest": function() { 
				$(this).dialog("close"); 
				document.location.href="overview.php";
			}
		}
	  });
	return false;	
}

function logout(){
	var sendData =  "logout=true";
	$.post("index.php",sendData,
	   function(data) {		 
	     document.location.href=data;
	   });
}


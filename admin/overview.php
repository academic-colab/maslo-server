<?php
/******************************************************************************
 * overview.php
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
require_once '../s3sdk/sdk.class.php';
require_once '../traverse.php';
putenv("LANG=en_US.UTF-8");
header('Content-Type: text/html; charset=utf-8'); 

class MyDB extends SQLite3
{
    function __construct($path)
    {
        $this->open($path);
    }

	function closeDB() {
		$this->close();
	}
}

session_start();

?>

<html>
<head>
	<link type="text/css" href="css/maslo-theme/jquery-ui-1.8.16.custom.css" rel="stylesheet" /> 
	<link type="text/css" href="css/foundation.css" rel="stylesheet" />
	<link type="text/css" href="css/screen.css" rel="stylesheet" />
	<title>MASLO Web Admin Console</title>
</head>
<body>
	<div id="divTabs"  class="ui-tabs-hide">
	<?php
	if (isset($_SESSION['user']) && isset($_SESSION['instance']) && strcmp($_SESSION['instance'],getcwd()) == 0)
		echo '<ul>
				<li><a href="#packs" id="packsClick">Content Pack Management</a></li>
				<li><a href="#users" id="usersClick">User Management</a></li>
			</ul>';
	?>
	
	<header>							
	<h3>Content Pack Overview</h3>		
	<div class="extra">
	<?php
	if (isset($_SESSION['user']) && isset($_SESSION['instance']) && strcmp($_SESSION['instance'],getcwd()) == 0){
		echo '<a href="#" onclick="logout();return false;">Logout</a>&nbsp;&nbsp;&nbsp;';
	} else {
		echo '<a href="index.php">Login</a>&nbsp;&nbsp;&nbsp;';
	}
	?>	
	<a href="#" onclick="help();return false;">Help</a><img src="images/maslo_icon_logo.png" /></div>
	<div class="loginInfo">
	<?php
	if (isset($_SESSION['user']) && isset($_SESSION['instance']) && strcmp($_SESSION['instance'],getcwd()) == 0){
		echo "<b>Logged in as: </b> ".$_SESSION['user'];
	} else {
		echo "<b>Not logged in.</b>";
	}
	?>	
				
	</header>

	<div id="edit" class="ui-tabs">			
		<div id="users"  class="ui-tabs">
			<?php
			/* If user has authenticated session, query all exisiting users and their roles. Display them in
			 	user mangement tab 
			*/
			if (isset($_SESSION['user']) && isset($_SESSION['instance']) && strcmp($_SESSION['instance'],getcwd()) == 0) {
			echo '<table>
				<thead>
					<tr>
						<th class="big">User name</th>
						<th class="big">Password</th>
						<th class="big">Name</th>
						<th class="big">Institution</th>
						<th class="big">Admin Rights</th>				
						<th>Remove</th>
					</tr>
				</thead><tbody id="userBody">';
			$db = new MyDB('../users.db');
			$db2 = new MyDB('admin.db');
			$query = "SELECT uname, firstName, lastName, institution from users";
			$query2 = "SELECT count(*) as count FROM users WHERE uname == :id";
			$stmt = $db->prepare($query);
			if ($stmt){
				$result = $stmt->execute();
				$resultRows = $result->fetchArray(SQLITE3_ASSOC);
				while ($resultRows){
					echo "<tr>";
					echo "<td><a href='#'>".$resultRows['uname']."</a></td>";
					echo "<td>****</td>";
					echo "<td>".$resultRows['firstName']." ".$resultRows['lastName']."</td>";
					echo '<input type="hidden" value="'.$resultRows['firstName'].'"/>';
					echo '<input type="hidden" value="'.$resultRows['lastName'].'"/>';
					echo "<td>".$resultRows['institution']."</td>";
					$stmt = $db2->prepare($query2);
					$adminRights = "no";
					if ($stmt) {
						$stmt->bindValue(':id',$resultRows['uname'] , SQLITE3_TEXT);
						$result2 = $stmt->execute();
						$resultRows2 = $result2->fetchArray(SQLITE3_ASSOC);
						if ($resultRows2) {
							if (intval($resultRows2["count"]) > 0 ) {
								$adminRights = "yes";
							}
						}
						
					}
					echo "<td>$adminRights</td>";
					echo '<td class="icon"><img class="removeUser" src="images/remove.png" alt="Remove User" /></td>';
					echo "</tr>";
					$resultRows = $result->fetchArray(SQLITE3_ASSOC);
				}
				
			}
			echo "</tbody></table>";
			$db->closeDB();
			$db2->closeDB();
		}
		?>
		</div>	
		
		<div id="packs"  class="ui-tabs">
		<table>
			<thead>
				<tr>
					<th class="big">Title</th>
					<th class="big">Category</th>
					<th class="big">Version</th>
					<th class="big">Last Modified</th>					
					<th class="big">Size</th>
					<?php
					if (isset($_SESSION['user']) && isset($_SESSION['instance']) && strcmp($_SESSION['instance'],getcwd()) == 0){
						echo "<th>Location</th>";
						echo "<th class=\"big\">Uploaded by</th>";
						echo "<th>Published</th>";
						echo "<th>Remove</th>";
					}
					?>
				</tr>
			</thead>
			<tbody id="contentBody">
				<?php
				// Retrieve and list all available content packs
				$default_dir = "../uploads/";
				$q_dir = "../qDir-uploads/";
				$s3ConfigStream = file_get_contents("../config.json");
				$s3Config = json_decode($s3ConfigStream, true);
				if ($s3Config["wantS3"] == "true") {
					$json = json_decode(traverseDirAmazon($s3Config["bucket"],$s3Config["baseDir"]),true);
					$qjson = json_decode(traverseDirAmazon($s3Config["bucket"],"qDir-".$s3Config["baseDir"]),true);
				} else {
					$json = json_decode(traverseDir($default_dir),true);
					$qjson = json_decode(traverseDir($q_dir),true);
				}
				$json = $json["data"];
				
				$db = new MyDB('../uploads/search.db');
				$query = "SELECT version, author, public, category FROM content where pack == :id";
				$j = 0;
				while ($j < 2) {
				$i = 0;
				while ($i < count($json)) {
					$stmt = $db->prepare($query);
					$author = "N/A";
					$version = "N/A";
					$published = 0;
					if ($stmt){
						$stmt->bindValue(':id', $json[$i]["title"], SQLITE3_TEXT);
						$result = $stmt->execute();
						$resultRows = $result->fetchArray(SQLITE3_ASSOC);
						$author = $resultRows["author"];
						$version = $resultRows["version"];
						$published =  $resultRows["public"];
						$category = $resultRows["category"];
					}
					$size = "N/A";
					if (array_key_exists("size", $json[$i]))
						$size = $json[$i]["size"];
					
					echo "<tr>";
					$title = $json[$i]["title"];
					$title = str_replace(">","&gt;", str_replace("<","&lt;", $title ) );
						
					echo "<td><a href='#' onclick='prepPreview($(this).text());'>".$title."</a></td>";
					if (isset($_SESSION['user']) && isset($_SESSION['instance']) && strcmp($_SESSION['instance'],getcwd()) == 0) {
						echo "<td class='packCategory' onClick='getCategories();return false;'>".$category."</td>";
					} else {
						echo "<td class='packCategory'>".$category."</td>";
					}
					echo "<td>".$version."</td>";
					echo "<td>".$json[$i]["date"]."</td>";					
					echo "<td>".$size."</td>";
					if (isset($_SESSION['user']) && isset($_SESSION['instance']) && strcmp($_SESSION['instance'],getcwd()) == 0) {						
						$loc = "Local";
						if ($s3Config["wantS3"] == "true") {
							$loc = "S3";
						}
						echo '<td>'.$loc.'</td>';
						echo "<td>".$author."</td>";
						if ($published == 1)
							echo '<td class="icon"><input type="checkbox" class="checkPub" checked="checked"></input></td>';
						else 
							echo '<td class="icon"><input type="checkbox"  class="checkPub"></input></td>';	
						echo '<td class="icon"><img class="remove" src="images/remove.png" alt="Remove Item" /></td>';
					}
					$i = $i+1;
				}
				$j = $j+1;
				$json = $qjson["data"]; 
				}
				$db->closeDB();				
				?>
				
			</tbody>
		</table>
		</div>
		

		<div class="action">
			<button type="button" id="addUsersButton" class="nice small radius blue button" href="#" style="visibility:hidden;">+ Add New User</button>
			&nbsp;
			<div class="alt">
				<input type="text" length="10" />
				<button type="button" class="ok nice small radius blue button" disabled="disabled">OK</button>
				<button type="button" class="cancel nice small radius white button">Cancel</button>
			</div>
			<div class="clear"></div>
		</div>
	</div>
	</div>	
	<div id="dialog-preview"  style="display: none" title="Content Pack Preview">
	</div>
	<div id="dialog-confirm" style="display: none" title="Delete">
		<p>The <span id="condemned"></span>
		will be permanently deleted and cannot be recovered.
		Are you sure?</p>
	</div>
	<div id="dialog-confirm-publish" style="display: none" title="Publish/Unpublish">
		<p>This will <span id="which-pub"></span> content pack <span id="pub-pack"></span>.
		Are you sure?</p>
	</div>
	<div id="info-div"  style="display: none" title="Info"></div>
	<div id="user-pass" style="display: none" title="Adding/Editing MASLO User">
		<form id="userPass" action="#">
		<table>
			<tbody>
				<tr>
					<td>User Name*</td>
					<td><input type="text" size="35"  id="userName"/></td>
				</tr>
				<tr>
					<td>Password*</td>
					<td><input type="password" size="35"  id="userPassword1"/></td>
				</tr>
				<tr>
					<td>Repeat Password*</td>
					<td><input type="password" size="35"  id="userPassword2"/></td>
				</tr>
				<tr>
					<td>First Name</td>
					<td><input type=" text" size="35"  id="firstName"/></td>
				</tr>
				<tr>
					<td>Last Name</td>
					<td><input type="text" size="35"  id="lastName"/></td>
				</tr>
				<tr>
					<td>Institution</td>
					<td><input type="text" size="35"  id="institution"/></td>
				</tr>
				<tr>
					<td>Admin Rights</td>
					<td><input type="checkbox"  id="adminRights"/></td>
				</tr>
				<input type="hidden" id="isUserEdit" value="true"/>
				<tr>
					<td colspan="2">&nbsp;</td>
				</tr>

			</tbody>
		</table>
		</form>
		*required
	</div>
	<div id="category-edit" style="display: none" title="Adding/Editing MASLO Categories">				
	</div>
	<div id="name-edit" style="display: none" title="Editing Name">				
		<input type="text" id="cat-name"></input>
		<input type="hidden" id="catMaxId" value="0"></input>
	</div>
	
</body>
<script type="text/javascript" src="js/jquery-1.6.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.14.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.watermark.min.js"></script>
<script type="text/javascript" src="js/jquery.cuteTime.min.js"></script>
<script type="text/javascript" src="js/help.js"></script>
<script type="text/javascript" src="js/md5.js"></script>
<script type="text/javascript" src="js/sha256.js"></script>
<script type="text/javascript" src="js/util.js"></script>
<script type="text/javascript" src="js/user.js"></script>
<script type="text/javascript" src="js/preview.js"></script>

<script type="text/javascript">

// -------------------------- SET UP PAGE -------------------------
$(document).ready(function() {

// -------------------------- REGISTER LINK CLICKS -------------------------
	$tabs = $( "#divTabs" ).tabs();
		
	
	$(".checkPub").click(function(){
		var tdTag = $($(this).parent().parent().children()[0]);
		var aTag = tdTag.find('a');
		if (aTag.length == 0)
			aTag = tdTag;
		var title = aTag.text();
		var titleOld = aTag.html();
		$("#pub-pack").html(titleOld);
		var sendData = title.replace(/ /g, ":::");
		var checkbox = $(this);
		if ($(this).is(":checked")) {
			sendData = {'function':'publishPack','data':sendData};
			$("#which-pub").html("publish");
			$( "#dialog-confirm-publish" ).dialog({
				height:240,
				modal: true,
				buttons: {
					"Publish": function() {
						$( this ).dialog( "close" );
						$.ajax({
							async: false,
							global: false,
							url: 'modify.php',
							type: 'post',
							dataType: "text",
							data: sendData,
						  success: function(data) {
							tdTag.html(titleOld);
							return false;
						  },
						error: function(data) {
							showInfo("Pack publishing failed: " + data) ;
							checkbox.removeAttr('checked');
						} 
						});

					},
					Cancel: function() {
						checkbox.removeAttr('checked');
						$( this ).dialog( "close" );
					}
				}
			});
		} else {
			sendData = {'function':'unPublishPack','data':sendData};
			$("#which-pub").html("unpublish");
			$( "#dialog-confirm-publish" ).dialog({
				height:240,
				modal: true,
				buttons: {
					"Unpublish": function() {
						$( this ).dialog( "close" );
						$.ajax({
							async: false,
							global: false,
							url: 'modify.php',
							type: 'post',
							dataType: "text",
							data: sendData,
						  success: function(data) {
							linkTag = $('<a href="#">'+titleOld+'</a>');
							linkTag.click(function(e){
								prepPreview(title);
								return false;
							});
							tdTag.html("");
							tdTag.append(linkTag);							
							return false;
						  },
						error: function(data) {
							checkbox.attr('checked', 'checked');
							showInfo("Pack unpublishing failed: " + data) ;
						} 
						});

					},
					Cancel: function() {
						checkbox.attr('checked', 'checked');
						$( this ).dialog( "close" );
					}
				}
			});
			
		}
		
	});
	
	$("#packsClick").click(function(){
		$("h3").html("Content Pack Overview");
		$tabs.tabs('select', "#packsClick");
		$("#addUsersButton").css({'visibility':'hidden'});
	});
	
	$("#usersClick").click(function(){
		$("h3").html("Users Overview");
		$tabs.tabs('select', '#usersClick');		
		$("#addUsersButton").css({'visibility':'visible'});
	});
	
	makeLinks();
	initRemoveUserClick();
	
		
	
	$("#addUsersButton").click(function(){
		$("#userName").removeAttr("disabled");
		$("#user-pass").dialog({
			height:520,
			width:500,
			modal: true,
			buttons: {
				"Save" :function(){
					var res = editUser(true); 
					if (res) {		
						$( this ).dialog( "close" ); 
					}
					return false; 
					},
				"Cancel":function(){$( this ).dialog( "close" );return false;}
			}
		});
	});
	
	
	$('img.remove').click(function(e) {
		var elt = $(this).parent().parent();
		var aTag = $(elt.children()[0]).find('a');
		if (aTag.length == 0)
			aTag = $(elt.children()[0]);
		var title = aTag.text();
		var titleOld = aTag.html();

		$("#condemned").html("content pack '"+titleOld+"'");
		var sendData = title;
		var isPublished = $(elt).find(".checkPub");
		isPublished = ($(isPublished).is(":checked")) ? "true" : "false";
		sendData = sendData.replace(/ /g, ":::");
		sendData = {'function':'deletePack','data':sendData, 'published':isPublished};

		$( "#dialog-confirm" ).dialog({
			height:240,
			modal: true,
			buttons: {
				"Delete Pack": function() {
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
						showInfo("Pack deletion failed: " + data) ;
					} 
					});
					
				},
				Cancel: function() {
					$( this ).dialog( "close" );
				}
			}
		});
		return false;
	});
});
</script>
</html>


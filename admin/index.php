<?php
/******************************************************************************
 * index.php
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
include_once("util.php");
session_start();

if (isset($_POST['logout'])){
	session_destroy();
	echo "index.php";
	exit;
} else if (isset($_SESSION['init'])){
	header("Location: overview.php");
	exit;
} else if (isset($_POST['userName'])) {
	$pw = $_POST['password'];
	$uname = $_POST['userName'];
	$res = verifyUser($uname, $pw);
	if ($res){
		$_SESSION['init'] = 0;
		$_SESSION['user'] = $uname;
		session_regenerate_id(false);
		echo "overview.php";
	} else {
		echo "NOK.";
	}
	exit;
} else {
	initDBs();	
}

?>

<html>
<head>
	<link type="text/css" href="css/maslo-theme/jquery-ui-1.8.16.custom.css" rel="stylesheet" /> 
	<link type="text/css" href="css/foundation.css" rel="stylesheet" />
	<link type="text/css" href="css/screen.css" rel="stylesheet" />
	<title>MASLO Web Admin Console</title>
</head>
<body>
	<div id="info-div" style="display: none" title="Info"></div>
	<div id="user-pass" style="display: none" title="Sign In">
		<form id="userPass" action="#">
		<table>
			<tbody>
				<tr>
					<td>User Name*</td>
					<td><input type="text" size="55"  id="userName"/></td>
				</tr>
				<tr>
					<td>Password*</td>
					<td><input type="password" size="55"  id="userPassword"/></td>
				</tr>
				<tr>
					<td colspan="2">&nbsp;</td>
				</tr>

			</tbody>
		</table>
		</form>
		*required
	</div>
	
</body>
<script type="text/javascript" src="js/jquery-1.6.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.14.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.watermark.min.js"></script>
<script type="text/javascript" src="js/jquery.cuteTime.min.js"></script>
<script type="text/javascript" src="js/help.js"></script>
<script type="text/javascript" src="js/md5.js"></script>
<script type="text/javascript" src="js/sha256.js"></script>
<script type="text/javascript" src="js/user.js"></script>

<script type="text/javascript">

// -------------------------- SET UP PAGE -------------------------
$(document).ready(function() {
	<?php
	echo "initUser('".hash('sha256',session_id())."')";
	?>


});	

</script>
</html>

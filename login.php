<?php
/******************************************************************************
 * login.php
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
date_default_timezone_set('America/Chicago');

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

function checkUser($userName, $userPass){
	$db = new MyDB('users.db');
	$query = "SELECT upass FROM users where uname == :id";
	$stmt = $db->prepare($query);
	$stmt->bindValue(':id', $userName, SQLITE3_TEXT);
	//$stmt->bindValue(':pass', $pw, SQLITE3_TEXT);
	$result = $stmt->execute();
	$resultRows = $result->fetchArray(SQLITE3_ASSOC);
	$pass = $resultRows["upass"];
	$pass = $pass.hash('sha256',hash('sha256',session_id()));
	$pass =  hash('sha256',$pass);
	$db->closeDB();
	if ($pass == $userPass) {
		return true;
	}
	return false;
}


if (array_key_exists("handshake", $_POST)){
	echo hash('sha256',session_id());
} else {
	if (checkUser($_POST['userName'],$_POST['password'])) {		
		session_regenerate_id(false);
		echo "OK.";
		return false;

	} else {	
		echo "DENIED.";	
	}
}

// In PHP versions earlier than 4.1.0, $HTTP_POST_FILES should be used instead
// of $_FILES.

//print_r($_POST);

?>
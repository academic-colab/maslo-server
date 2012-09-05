<?php
/******************************************************************************
 * modify.php
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


if (isset($_SESSION['init'])){
	
	$inputData = $_POST;
	$data = "";
	$function = "";
	$isPublished = false;
	foreach ($inputData as $key => $value) {		
		if ($key == "function"){
			$function = $value;
		} else if ($key=="published"){
			if ($value[0] == 't' || $value[0] == 'T'){
				$isPublished = true;
			}
		} else 
			$data = $value;
	}
	if ($data != "") {
		$data = str_replace(":::", " ",$data);
		$res = false;
		if ($function == "deletePack")
			$res = deleteData($data, $isPublished);
		else if ($function == "publishPack")
			$res = publishData($data);
		else if ($function == "unPublishPack")
			$res = unPublishData($data);
		else if ($function == "preparePreview")
			$res = preparePreview($data);
		else if ($function == "removePreview")
			$res = removePreview($data);
		else if ($function == "deleteUser")
			$res = deleteUser($data);
		else if ($function == "editUser") {
			$isAdmin = ($inputData["isAdmin"] == "true");
			$res = true;
			if ($inputData["isNew"] != "true")
				$res = deleteUser($inputData["userName"]);				
			if ($res)
				$res = insertUser($inputData["userName"], $inputData["userPass"], $inputData["firstName"], $inputData["lastName"], $inputData["institution"], $isAdmin);
		}
		if ($res) {
			echo "OK.";
		} else {
			echo "NOK. ".$inputData;
		}
	} else {
		echo "NODATA.";
	}
}

?>
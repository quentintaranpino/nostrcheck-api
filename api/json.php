<?php

/**
 *
 * @About:      Json nostr generator
 * @File:       json.php
 * @Date:       20032023
 * @Version:    0.1.1
 *
 */


//allow CORS
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization");

//If someone wants to see the entire list, die.
if (!isset($_GET['name']) || empty($_GET['name']) ){
	echo json_encode("Nothing to see here dude - Register at: nostrcheck.me");
	die();
}

// "domain username or "_" username. "_" is a special case for the root domain
$root_hex = "134743ca8ad0203b3657c20a6869e64f160ce48ae6388dc1f5ca67f346019ee7"; //Especify the root domain hexkey
if ($_GET['name'] === "_"){
	echo json_encode(array("names" => array("_" => $root_hex)));
	die();
}

// include database helper
require_once __DIR__  . '../../helper/database.php';

//Check the received apikey on DB
$db = new DbConnect();
$con = $db->connect();
$stmt = $con->prepare('SELECT username, hex FROM registered WHERE username = ? and domain = ?'); 
$stmt->bind_param('ss', $_GET['name'],$_SERVER['SERVER_NAME']); 
$stmt->execute();
$stmt->bind_result($username,$hexkey);
$stmt->fetch();
$stmt->close();


//If empty username we don't show anything
if(empty($username)){die();}

//If we have a username we show the hexkey
echo json_encode(array("names"=> array($username => $hexkey)));
exit();
?>
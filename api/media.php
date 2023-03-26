<?php

/**
 *
 * @About:      Media api for uploads (for testing purposes)
 * @File:       media.php
 * @Date:       26032023
 * @Version:    0.4.3
 *
 */

//Set test to true !important. This is for testing purposes only
$test = false;

//Allow CORS
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, HEAD");
header("Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization");

//Include config file
require_once __DIR__  . '../../helper/config.php';

//Include database helper
require_once __DIR__  . '../../helper/database.php';

//Transform helper
require  __DIR__ . '../../helper/transform.php';

//Token bucket
require  __DIR__ . '../../helper/tokenbucket.php';

//Token bucket database prepare
$db = new PDO('mysql:host='.$DATABASE_HOST.';dbname='.$DATABASE_NAME,  $DATABASE_USER, $DATABASE_PASS);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$storage = new TokenBucketStoragePDOMySQL($db, 'token_bucket');
$storage->prepare(); // Creates the table if needed
unset($db);

 // collect input parameters
$data = json_decode(file_get_contents("php://input"), true);
	
$fileName  		=  $_FILES['publicgallery']['name'];
$fileSize  		=  $_FILES['publicgallery']['size'];
$tempPath  		=  $_FILES['publicgallery']['tmp_name'];
$remoteapikey   =  $_POST['apikey']; // Apikey
$uploadtype  	=  $_POST['type']; //If avatar, banner or standard media
$remoteipaddr	= 	 	isset($_SERVER['HTTP_CLIENT_IP'])   //We collect HTTP_CLIENT_IP or HTTP_X_FORWARDED_FOR or REMOTE_ADDR and then we make a hash of it
						? $_SERVER['HTTP_CLIENT_IP'] 
						: (isset($_SERVER['HTTP_X_FORWARDED_FOR']) 
						? $_SERVER['HTTP_X_FORWARDED_FOR'] 
						: $_SERVER['REMOTE_ADDR']);


//Count tokens avalible for remote ip (hashed)
$bucket = new TokenBucket($storage, $remoteipaddr, 10, 5); //Maximum of 10 requests, refilling at a rate of 5,184 request per day (= 5,184 per day)
if (!$bucket->consume(1))
{
	$errorMSG = json_encode(array("apikey" => $remoteapikey,"request" => $remoteipaddr, "filesize" => $fileSize,"message" => "you don't have enought tokens", "status" => false, "type" => $uploadtype));
	echo $errorMSG;
	exit();
}

//Empty remote apikey (prevent DB access with malformed request)
if(empty($remoteapikey))
{
	$errorMSG = json_encode(array("apikey" => $remoteapikey,"request" => $remoteipaddr, "filesize" => $fileSize,"message" => "incorrect apikey", "status" => false, "type" => $uploadtype));
	echo $errorMSG;
	exit();
};

//Check the received apikey on DB
$db = new DbConnect();
$con = $db->connect();
$stmt = $con->prepare('SELECT username, apikey FROM registered WHERE apikey = ?');
$stmt->bind_param('s', $remoteapikey);
$stmt->execute();
$stmt->bind_result($username,$apikey);
$stmt->fetch();
$stmt->close();
$con->close();
unset($db);

//Empty server apikey or incorrect apikey
if(empty($apikey) || $remoteapikey != $apikey)
{
	$errorMSG = json_encode(array("apikey" => $remoteapikey,"request" => $remoteipaddr, "filesize" => $fileSize,"message" => "incorrect apikey", "status" => false, "type" => $uploadtype));
	echo $errorMSG;
	exit();
};

//Empty image
if(empty($fileName))
{
	$errorMSG = json_encode(array("apikey" => $remoteapikey,"request" => $remoteipaddr, "filesize" => $fileSize,"message" => "no file uploaded in post message", "status" => false, "type" => $uploadtype));
	echo $errorMSG;
	exit();
}

//check file size
if($fileSize > $MAX_FILE_SIZE || empty($fileSize))
{
	$errorMSG = json_encode(array("apikey" => $remoteapikey,"request" => $remoteipaddr, "filesize" => $fileSize,"message" => "file too large, max upload size is ".convert_filesize($MAX_FILE_SIZE), "status" => false, "type" => $uploadtype));
	echo $errorMSG;
	exit();
}

 //Allowed filetypes
 $mime_type = mime_content_type($tempPath);
 $allowedTypes = [
   'image/png',
   'image/jpg',
   'image/jpeg',
   'image/gif',
   'image/webp',
   'video/mp4',
   'video/quicktime',
   'video/mpeg',
   'video/webm',
   'audio/mpeg',
   'audio/mpg',
   'audio/mpeg3',
   'audio/mp3'
 ];
 if(!in_array($mime_type, $allowedTypes)) {
	$errorMSG = json_encode(array("apikey" => $remoteapikey,"request" => $remoteipaddr, "filesize" => $fileSize,"message" => "filetype not allowed ".$mime_type, "status" => false, "type" => $uploadtype));
	echo $errorMSG;
	exit();
 }

//We transform the extension from jpg jpeg and png to webp because after we convert to it
$mime_extension = [
	'image/png'       => 'jpg', //change for webp when will supported by clients
	'image/jpg'       => 'jpg', //change for webp when will supported by clients
	'image/jpeg'      => 'jpg', //change for webp when will supported by clients
	'image/gif'       => 'gif',
	'image/webp'      => 'jpg', //change for webp when will supported by clients
	'video/mp4'       => 'mp4',
	'video/quicktime' => 'mov',
	'video/mpeg'      => 'mpeg',
	'video/webm'      => 'webm',
	'audio/mpeg'      => 'mp3',
	'audio/mpg'       => 'mp3',
	'audio/mpeg3'     => 'mp3',
	'audio/mp3'       => 'mp3'
];

//Check if is an avatar, banner or general media.
if(empty($uploadtype) || $uploadtype === "media"){
	$newfilename 	= "nostrcheck.me_".rand(100000000000000000,999999999999999999).time().".".$mime_extension[$mime_type]; //New filename, random number + extension generated (example png->jpg)
}else if ($uploadtype === "avatar"){
	$newfilename = "avatar".".".$mime_extension[$mime_type];
}else if ($uploadtype === "banner"){
	$newfilename = "banner".".".$mime_extension[$mime_type];
}else{

	$errorMSG = json_encode(array("apikey" => $remoteapikey,"request" => $remoteipaddr, "filesize" => $fileSize,"message" => "This post upload type is not allowed", "status" => false, "type" => $uploadtype));
	echo $errorMSG;
	exit();
}

//Directories
$mediadir 		= "/var/www/nostrcheck/public_html/media/";
$targetdir 		= $mediadir.$username."/";
$fileURL 		= "https://nostrcheck.me/media/".$username."/".$newfilename;


//Check if media dir exist
if(!file_exists($mediadir))
{
	if (!mkdir($mediadir, 0775, true)){
		{
			$errorMSG = json_encode(array("apikey" => $remoteapikey,"request" => $remoteipaddr, "filesize" => $fileSize,"message" => "media folder error", "status" => false, "type" => $uploadtype));
			echo $errorMSG;
			exit();
		}
	};
};

//Check if target dir exist
if(!file_exists($targetdir))
{
	if (!mkdir($targetdir, 0775, true)){
		{
			$errorMSG = json_encode(array("apikey" => $remoteapikey,"request" => $remoteipaddr, "filesize" => $fileSize,"message" => "target folder error", "status" => false, "type" => $uploadtype));
			echo $errorMSG;
			exit();
		}
	};
};

//Check file exist our upload folder path (only for media)
if(file_exists($targetdir . $newfilename) && $uploadtype === "media") //If file exist error, but if it is an avatar or banner, whe overwrite it. 
{
	$errorMSG = json_encode(array("apikey" => $remoteapikey,"request" => $remoteipaddr, "filesize" => $fileSize,"message" => "this file alredy exist", "status" => false, "type" => $uploadtype));
	echo $errorMSG;
	exit();
}

//Compress size and move the image to target directory
$compressedImage = compressFile($tempPath, $targetdir . $newfilename, 60, $test); 
		
if($compressedImage){ 

	//If test is true we don't write to database
	if($test === true)
	{
		unlink($tempPath); 
		echo json_encode(array("apikey" => $remoteapikey, "request" => $remoteipaddr, "filesize" => $fileSize, "message" => "test ok", "status" => true, "type" => $uploadtype));
		exit();
	}

	//If the media has been compressed and moved to target directory we write new row to table userfiles
	$db = new DbConnect();
	$con = $db->connect();
	$stmt = $con->prepare("INSERT INTO userfiles (id, username, filename, public, date, remotehash, comments) VALUES (?, ?, ?, ?, ?, ?, ?)");
	$stmt->bind_param('sssssss', $val_id, $username, $newfilename, $val_public, $val_date, $remoteipaddr, $val_comments);
	$val_id = '0';
	$val_public = true;
	$val_date = date("Y-m-d H:i:s");
	$val_comments = "api";
	$result = $stmt->execute();
	$stmt->close();
	$con->close();

	if($result)
	{
		unlink($tempPath); 
		echo json_encode(array("apikey" => $remoteapikey, "request" => $remoteipaddr, "filesize" => $fileSize,"message" => "image uploaded successfully", "status" => true, "type" => $uploadtype, "URL" => $fileURL));
		exit();
	}else{
		unlink($tempPath); 
		unlink($targetdir . $newfilename); // If we can't insert into DB we delete the file.
		echo json_encode(array("apikey" => $remoteapikey, "request" => $remoteipaddr, "filesize" => $fileSize,"message" => "it was a problem processing this file", "status" => false, "type" => $uploadtype, "URL" => $fileURL));
		exit();
	}
	
}else{ 
	unlink($tempPath); // 
	echo json_encode(array("apikey" => $remoteapikey, "request" => $remoteipaddr, "filesize" => $fileSize,"message" => "it was a problem processing this file", "status" => false, "type" => $uploadtype, "URL" => $fileURL));
	exit();
} 

?>
<?php 

function compressFile($source, $destination, $quality, $test = false) { 

    if($test == true){return $source;} //If is a test, we return the original file (for testing purposes

    // Get image info 
    $imgInfo = getimagesize($source); 
    $mime = $imgInfo['mime']; 
  
    // Create a new image from file 
    switch($mime){ 
        case 'image/jpeg': 
            $image = imagecreatefromjpeg($source); 
            imagejpeg(webpprepare($image), $destination, $quality); //change for imagewebp when supported by clients
            return $destination; 
            break; 
        case 'image/png': 
            $image = imagecreatefrompng($source); 
            imagejpeg(webpprepare($image), $destination, $quality); //change for imagewebp when supported by clients
            return $destination; 
        case 'image/webp': 
            $image = imagecreatefromwebp($source); 
            imagejpeg(webpprepare($image), $destination, $quality); //change for imagewebp when supported by clients
            return $destination; 
        default: 
            break;
    } 
     
    //If is not an image, or if is a gif, we copy without compression
    copy($source, $destination);
    return $destination; 
}

//Prepare image file for webp conversion
function webpprepare($imagefile){
    ////Uncomment when supported by clients
       // imagepalettetotruecolor($imagefile);
       // imagealphablending($imagefile, true);
       // imagesavealpha($imagefile, true);
    return $imagefile;
}

function convert_filesize($bytes, $decimals = 2) { 
    $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB'); 
    $factor = floor((strlen($bytes) - 1) / 3); 
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor]; 
}

function sanitizeFilename(string $name): string {
    return preg_replace(
               '/\W+/',
               '',
               str_replace(' ', '', $name)
           );
}

?>
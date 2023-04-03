<?php 

require_once '../vendor/autoload.php'; //Composer autoload


function mediatransform($source, $destination, $filename, $quality, $test = false, $width = 1280, $height = 960) { 

    if($test == true){return $source;} //If is a test, we return the original file (for testing purposes

    // Get image info 
    $mime = mime_content_type($source);

    //Initialize ffmpeg
    $ffmpeg = FFMpeg\FFMpeg::create(array(
        'ffmpeg.binaries'  => '/bin/ffmpeg',
        'ffprobe.binaries' => '/bin/ffprobe',
        'timeout'          => 3600, // The timeout for the underlying process
        'ffmpeg.threads'   => 12,   // The number of threads that FFMpeg should use
    ));
  
    // Create a new image from file 
    switch($mime){ 
        case 'image/jpeg': 
            $image = imagecreatefromjpeg($source); 
            imagejpeg(webpprepare(resizeimage($image, $width, $height)), $destination . $filename, $quality); //change for imagewebp when supported by clients
            return $filename; 
            break; 
        case 'image/png': 
            $image = imagecreatefrompng($source); 
            imagejpeg(webpprepare(resizeimage($image, $width, $height)), $destination . $filename, $quality); //change for imagewebp when supported by clients
            return $filename; 
        case 'image/webp': 
            $image = imagecreatefromwebp($source); 
            imagejpeg(webpprepare(resizeimage($image, $width, $height)), $destination . $filename, $quality); //change for imagewebp when supported by clients
            return $filename; 
        case 'video/mp4':
            //If is a video, we compress and convert it to mp4
            $mp4 = $ffmpeg->open($source);
            $mp4->filters()->resize(new FFMpeg\Coordinate\Dimension(640, 480), 'width')->synchronize();
            $codec = new FFMpeg\Format\Video\X264();
            $codec->setKiloBitrate(960)->setAudioKiloBitrate(64);
            $mp4->save($codec, $destination . $filename);
            return $filename; 
        case 'image/gif':
            if (is_animated_gif($source)){
                $filename = str_replace('.gif', '.mp4', $filename);
                //If is an animated gif, we convert it to mp4
                $gif = $ffmpeg->open($source);
                $gif->filters()->resize(new FFMpeg\Coordinate\Dimension(640, 480), 'width')->synchronize();
                $codec = new FFMpeg\Format\Video\X264();
                $codec->setAdditionalParameters(array('-profile:v', 'baseline', '-pix_fmt', 'yuv420p', '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2'));
                $gif->save($codec, $destination . $filename) ;
                return $filename; 
            }else{
                $filename = str_replace('.gif', '.jpg', $filename);
                //If is a static gif, we convert it to jpg
                $image = imagecreatefromgif($source); 
                imagejpeg(webpprepare(resizeimage($image, $width, $height)), $destination . $filename, $quality); //change for imagewebp when supported by clients
                return $filename; 
            }
        default: 
            break;
    } 
     
    //If is not an image, or if is a gif, we copy without compression
    if(copy($source, $destination . $filename)) { 
        return $filename; 
    }

}

//Prepare image file for webp conversion
function webpprepare($imagefile){
    ////Uncomment when supported by clients
       // imagepalettetotruecolor($imagefile);
       // imagealphablending($imagefile, true);
       // imagesavealpha($imagefile, true);
    return $imagefile;
}

function resizeimage($imagefile, $newwidth = 1280, $newheight = 960){

    $width = imagesx($imagefile);
    $height = imagesy($imagefile);

    if ($width > $newwidth || $height > $newheight){
    if($width > $height){
        $newheight = ($height/$width)*$newwidth;
    }else{
        $newwidth = ($width/$height)*$newheight;
    }
    $newimage = imagecreatetruecolor($newwidth, $newheight);
    imagecopyresampled($newimage, $imagefile, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
    return $newimage;
    }else{
        return $imagefile;
    }
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

/**
 * Thanks to ZeBadger for original example, and Davide Gualano for pointing me to it
 * Original at http://it.php.net/manual/en/function.imagecreatefromgif.php#59787
 **/
function is_animated_gif( $filename )
{
    $raw = file_get_contents( $filename );

    $offset = 0;
    $frames = 0;
    while ($frames < 2)
    {
        $where1 = strpos($raw, "\x00\x21\xF9\x04", $offset);
        if ( $where1 === false )
        {
            break;
        }
        else
        {
            $offset = $where1 + 1;
            $where2 = strpos( $raw, "\x00\x2C", $offset );
            if ( $where2 === false )
            {
                break;
            }
            else
            {
                if ( $where1 + 8 == $where2 )
                {
                    $frames ++;
                }
                $offset = $where2 + 1;
            }
        }
    }

    return $frames > 1;
}

?>
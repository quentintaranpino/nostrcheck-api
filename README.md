# nostrcheck.me public Rest API


# Public gallery 

This document describes how to connect and interact with the [nostrcheck.me](http://nostrcheck.me/) public gallery Rest API.

## Connection

The connection to the rest of the API is made through this URL:

```html
https://nostrcheck.me/api/media.php
```

## Fields

The POST request sent by the remote server requires this fields:

| Name | type |
| --- | --- |
| apikey | string |
| publicgallery | file |
| type (optional) | string |

## Apikey

All **requests** to the API must include the corresponding **apikey**, which identifies the origin of the request. For a public upload public apikey must be used, to upload private files (assigned to a user) the user’s apikey must be used.

### Public apikey

```html
26d075787d261660682fb9d20dbffa538c708b1eda921d0efa2be95fbef4910a
```

## User’s apikey

Every registered user on [nostrcheck.me](http://nostrcheck.me) have a private apikey to interact with the API. This key allows uploads using a previously defined balance of tokens.

User apikey upload the attached file to user’s folder, example. 

```json
    "**URL**": "https://nostrcheck.me/media/USERNAME/filename.webp"
```

This balance can be assigned manually or via lightning invoice (TODO).

## Type

All request must include the **“type”** parameter, this describes what kind of you want to upload to the server. These are the supported types.

| media | Standard file upload, it goes to the user gallery (public or private) |
| --- | --- |
| avatar | Avatar file, it goes to the user folder with name “avatar” + extension |
| banner | Banner file, it goes to the user folder with name “banner” + extension |

### Inherited compatibility for old requests without "type”

The server will recognize any event without type as **"media"** (standard).

## Get started

Example with a curl statement to upload a file to the public gallery, using the public apikey

```bash
curl --location 'https://nostrcheck.me/api/media.php' \
--form 'publicgallery=@"FVoK1TKy9/file.jpg"' \
--form 'apikey="26d075787d261660682fb9d20dbffa538c708b1eda921d0efa2be95fbef4910a"' \
--form 'type="media"'
```

Example with a PHP file

```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://nostrcheck.me/api/media.php',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => array('publicgallery'=> new CURLFILE('FVoK1TKy9/file.jpg'),'apikey' => '26d075787d261660682fb9d20dbffa538c708b1eda921d0efa2be95fbef4910a','type' => 'media'),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;
```

## Return message

The server will return a json file with the following information.

```json
{
    "apikey": "YOUR API KEY",
    "request": "YOUR REQUEST ID",
    "filesize": FILESIZE (BYTES),
    "message": "THE RETURN MESSAGE",
    "status": TRUE OR FALSE,
    "type" : "UPLOAD TYPE"
}
```

If the file has been successfully uploaded, the server will also return the "URL" parameter with the final path of the multimedia file:

```json
{
    "apikey": "2aacf25cb9dfb01a464b9fa624f810d4dd86fdbfcd4cdea9d6853bc579e9ad89",
    "request": "16dd9cef6eeb07fc41c80afcb64dd38e62643edb14cd3db262d73a19caf2ea8d",
    "filesize": 306338,
    "message": "Image Uploaded Successfully",
    "status": true,
    "type": media,
    "**URL**": "https://nostrcheck.me/media/public/83953381677613282.webp"
}
```

## Requirements to upload a file

These are the requirements for a file to be uploaded and processed by the API.

| Max filesize | 4 MB |
| --- | --- |
| Max request | 5 per day (public apikey) |
| Filetypes | jpg, jpeg, gif, png, webp, mp3, mp4, webm, mpeg |

## File processing

All jpg, jpeg and png image **files will be compressed and ~~converted to webp~~**. Also, the resulting filename will be a random number between (10000000000 and 99999999999) + time(). 

(150323) ***Until Damus or other clients correctly implement the visualization in webp, the files will be compressed in jpg.***

Example:

```bash
34917061677595387.webp
```

Version 0.4.1 17032022

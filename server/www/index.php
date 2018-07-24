<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('GDOCS_DIR', "/var/gdocs-export");
define('KEY', "ThisIsASuperSecrectKey");

include('includes/Message.php');

$errors = array();




function endit($status,$message){
    return http_response_code($status) && die($message);
}

function upload() {
    if(!isset($_REQUEST['id']) || !isset($_REQUEST['token'])) endit(400, "Bad Request.");
    $gdocCurl = curl_init("https://docs.google.com/document/d/" . $_REQUEST['id'] . "/export?format=html");
    curl_setopt($gdocCurl, CURLOPT_HTTPHEADER, array("Authorization: Bearer " . $_REQUEST['token'])  );
    $fp = fopen(GDOCS_DIR . "/input/" . $_REQUEST['id'] . '.html',"w");
    curl_setopt($gdocCurl,CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($gdocCurl, CURLOPT_FILE,$fp);
    curl_setopt($gdocCurl, CURLOPT_RETURNTRANSFER, TRUE);

    $content = curl_exec($gdocCurl);

    //print_r($content); print_r(curl_errno($gdocCurl));

    $httpCode = curl_getinfo($gdocCurl, CURLINFO_HTTP_CODE);
    if($httpCode >= 400){
        curl_close($gdocCurl); fclose($fp);
        return endit($httpCode, "Invalid Request.");
    } else {
        fwrite($fp,$content);
        curl_close($gdocCurl); fclose($fp);
        return endit(200,'File successfully uploaded to the server.');
    }
}

function convert() {
    isset($_REQUEST['id']) or die(400);
    file_exists(GDOCS_DIR . "/input/" . $_REQUEST['id'] . ".html") or endit(404,"File not found.");
    //$theme = $_REQUEST['theme'] ? $_REQUEST['theme'] : "default";
    $command = "make -C " . GDOCS_DIR ." convert FILE_NAME=" . $_REQUEST['id']; // . " theme=" . $theme;
    exec($command,$irrelevant, $status);
    //Code below is for debugging purposes
    /*
    print_r($command); echo '<br>';
    print_r($irrelevant); echo '<br>';
    print_r($status); echo '<br>';
    */
    !$status or endit(500, "Conversion failed.");
    return endit(200,'File successfully converted.');
}

function download() {
    isset($_REQUEST['id']) or endit(400,"Bad Request.");
    $format = $_REQUEST['format'] ? $_REQUEST['format'] : "pdf";
    $path = GDOCS_DIR . "/build/" . $_REQUEST['id'] . "/" . $_REQUEST['id'] . "." . $format;
    file_exists($path) or endit(404,"File not found.");
    $fname = ($_REQUEST['name'] ? $_REQUEST['name'] : $_REQUEST['id']) . "." . $format;
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($fname).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

switch($_REQUEST['path']){
    case "upload":
        upload(); break;
    case "convert":
        convert(); break;
    case "download":
        download(); break;
    default:
        endit(400,"Invalid path.");
}

// Check for security key
if(!isset($_GET['key']) ||
  !(isset($_GET['key']) && $_GET['key'] === KEY)) {
    $errors[] = 'Key not found';
}
if(!isset($_GET['doc_id'])) $errors[] = 'Doc ID not found';
if(!isset($_GET['doc_name'])) $errors[] = 'Doc name not found';

if(!empty($errors)) {
  $message = new Message(400, 'Bad Request', $errors);
  $message->render();
}

// All good, move on to generate the fancy PDF
$doc_id = $_GET['doc_id'];
$doc_name = time() . '--' . $_GET['doc_name'];
$theme = (isset($_GET['theme'])) ? $_GET['theme'] : 'default';

$command = GDOCS_DIR . "/server/scripts/web-convert-gdoc.sh {$doc_id} {$doc_name} {$theme}";
$output = system($command);


if(file_exists(GDOCS_DIR . '/server/www/output/' . $doc_name . '.pdf')) {
  $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/output/' . $doc_name . '.pdf';
  $message = new Message(200, 'Success', array(), array('file' => $url));
  $message->render();
} else {
  $text = "File {$doc_name}.pdf failed to generate";
  $message = new Message(403, 'Error', array($text));
  $message->render();
}

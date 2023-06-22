<?php

ini_set('max_execution_time', '1700');
set_time_limit(1700);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);



////////////  F U N K T I O N S  /////////////////
{
function send_bearer($url, $token, $type = "GET", $param = []){
    $descriptor = curl_init($url);
     curl_setopt($descriptor, CURLOPT_POSTFIELDS, json_encode($param));
     curl_setopt($descriptor, CURLOPT_RETURNTRANSFER, 1);
     curl_setopt($descriptor, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer '.$token)); 
     curl_setopt($descriptor, CURLOPT_CUSTOMREQUEST, $type);
    $itog = curl_exec($descriptor);
    curl_close($descriptor);
    return $itog;
}
if (function_exists ("mb_str_split") === false) {
    function mb_str_split($string, $split_length = 1, $encoding = null) {
        if (null !== $string && !\is_scalar($string) && !(\is_object($string) && \method_exists($string, '__toString'))) {
            trigger_error('mb_str_split(): expects parameter 1 to be string, '.\gettype($string).' given', E_USER_WARNING);
            return null;
        }
        if (null !== $split_length && !\is_bool($split_length) && !\is_numeric($split_length)) {
            trigger_error('mb_str_split(): expects parameter 2 to be int, '.\gettype($split_length).' given', E_USER_WARNING);
            return null;
        }
        $split_length = (int) $split_length;
        if (1 > $split_length) {
            trigger_error('mb_str_split(): The length of each segment must be greater than zero', E_USER_WARNING);
            return false;
        }
        if (null === $encoding) {
            $encoding = mb_internal_encoding();
        } else {
            $encoding = (string) $encoding;
        }
        if (! in_array($encoding, mb_list_encodings(), true)) {
            static $aliases;
            if ($aliases === null) {
                $aliases = [];
                foreach (mb_list_encodings() as $encoding) {
                    $encoding_aliases = mb_encoding_aliases($encoding);
                    if ($encoding_aliases) {
                        foreach ($encoding_aliases as $alias) {
                            $aliases[] = $alias;
                        }
                    }
                }
            }
            if (! in_array($encoding, $aliases, true)) {
                trigger_error('mb_str_split(): Unknown encoding "'.$encoding.'"', E_USER_WARNING);
                return null;
            }
        }
        $result = [];
        $length = mb_strlen($string, $encoding);
        for ($i = 0; $i < $length; $i += $split_length) {
            $result[] = mb_substr($string, $i, $split_length, $encoding);
        }
        return $result;
    }
}
}

// Input data
$input = json_decode(file_get_contents("php://input"), true);
if ($input != NULL && is_array($input)) {
    $data = array_merge($_GET, $input);
} else {
    $data = array_merge($_GET, $_POST);
}


// Validation input data
if ($data["userId"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "'userId' is missing";
}
if ($data["token"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "'token' is missing";
}
if ($data["message"] == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "'message' is missing";
}
if ($result["state"] === false) {
    echo json_encode($result);
    exit;
}

// Search messages
$pages = 1;
for ($page = 1; $pages >= $page; $page ++) {
    $getMessages = json_decode(send_bearer("https://api.smartsender.com/v1/contacts/".$data["userId"]."/messages?limitation=20&page=".$page, $data["token"]), true);
    $pages = $getMessages["cursor"]["pages"];
    if (is_array($getMessages["collection"])) {
        foreach ($getMessages["collection"] as $oneMessage) {
            if ($data["type"] == "media") {
                if (stripos($oneMessage["content"]["resource"]["parameters"]["url"], $data["message"]) !== false) {
                   $trueMessage = $oneMessage["content"]["resource"]["parameters"]["url"];
                   break 2;
                }
            } else {
                if (stripos($oneMessage["content"]["resource"]["parameters"]["content"], $data["message"]) !== false) {
                   $trueMessage = $oneMessage["content"]["resource"]["parameters"]["content"];
                   break 2;
                }
            }
        }
    } else {
        $result["state"] = false;
        $result["error"]["message"][] = "failed search message";
        $result["error"]["smartsender"] = $getMessages;
        echo json_encode($result);
        exit;
    }
}
if ($trueMessage == NULL) {
    $result["state"] = false;
    $result["error"]["message"][] = "message not found";
    echo json_encode($result);
    exit;
}

// spit messages
$result["state"] = true;
$result["message"] = $trueMessage;
$result["split"] = mb_str_split($trueMessage, 250);
$splitMessage = $result["split"];
if ($data["vars"] != NULL && is_array($data["vars"])) {
    $cSplit = count($splitMessage);
    $cKeys = count($data["vars"]);
    if ($cSplit > $cKeys) {
        $splitMessage = array_chunk($splitMessage, $cKeys)[0];
    } else if ($cKeys > $cSplit) {
        $data["vars"] = array_chunk($data["vars"], $cSplit)[0];
    }
    $send["values"] = array_combine($data["vars"], $splitMessage);
    $result["smartsender"]["send"] = $send;
    $result["smartsender"]["result"] = json_decode(send_bearer("https://api.smartsender.com/v1/contacts/".$data["userId"], $data["token"], "PUT", $send), true);
}

echo json_encode($result);












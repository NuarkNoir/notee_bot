<?php
define("TOKEN", "");
define("BOTNAME", "nottee_bot");

require_once __DIR__."/vendor/autoload.php";
use SimpleCrud\Database;

$pdo = new \PDO("sqlite:" . __DIR__ . '/nottee_dbs.sqlite');
$db = new Database($pdo);

$bot = new PHPTelebot(TOKEN, BOTNAME);

$bot->cmd("/start", "Hello, I am note-taking bot! You can get help with `/help` command.\n".
                    "WARNING: DO NOT USE NOTTEE TO STORE SOMETHING IMPORTANT FOR YOU \n".
                    "(E.G. PASSWORDS, PINS, et.c.). YOU HAVE BEEN WARNED! I (@nuark) \n".
                    "AM NOT RESPONSIBLE FOR ANY LOST DATA");

$bot->cmd("/help",  "Use `{id} <linebreak> {your text}` to save something (to use in chats - ".
                    "prepend {id} with `/note`).\n".
                    "Note: {id} wil be of spaces/tabs, inner spaces will become underscores.\n".
                    "Then reference it with `/ref {id}`.\n".
                    "Also you can list them all with `/list`");

$bot->on("text", function() use ($db) {
    $message = Bot::message();
    if (substr($message["text"], 0, 6) === "/note ") {
        $message["text"] = substr($message["text"], 6);
    }
    else if ($message["chat"]["type"] != "private") {
        return "ok";
    }

    $result = "";
    $pieces = explode("\n", $message["text"], 2);
    if (count($pieces) !== 2) {
        $result = "Cannot parse your note. Right format is `{id} <linebreak> {text}`.";
    }
    else {
        $chatid = $message["chat"]["id"];
        $ownerid = $message["from"]["id"];
        $type = "text";
        list($refid, $data) = $pieces;
        $refid = str_ireplace(" ", "_", trim($refid));
        $noteid = "{$chatid}_{$ownerid}_{$refid}";
        $note = $db->note->getOrCreate([
            "noteid" => $noteid, 
            "chatid" => $chatid, 
            "ownerid" => $ownerid
        ]);
        if (isset($note->ref_id)) {
            $note->ref->data = $data;
            $note->ref->save();
            $result = "Note with `$refid` id updated";
        }
        else {
            $new_ref = $db->ref->create(["type" => $type, "data" => $data])->save();
            $note->ref_id = $new_ref->id;
            $result = "Note saved with `$refid` id";
        }
        $note->save();
    }

    return Bot::sendMessage($result);
});

$bot->cmd("/ref",  function($refid) use ($db) {
    $message = Bot::message();
    $result = "";

    $chatid = $message["chat"]["id"];
    $ownerid = $message["from"]["id"];
    $refid = str_ireplace(" ", "_", trim($refid));
    $noteid = "{$chatid}_{$ownerid}_{$refid}";
    print_r($noteid);

    $note = $db->note->get([
        "noteid" => $noteid, 
        "chatid" => $chatid, 
        "ownerid" => $ownerid
    ]);

    if (isset($note)) {
        if (isset($note->ref_id)) {
            $ref = $note->ref;
            $result = $ref->data;
        }
        else {
            $result = "Somehow, you got empty ref. Please, contact @nuark about this incident.";
        }
    }
    else {
        $result = "Note with id `$refid` not exists";
    }

    return Bot::sendMessage($result);
});

$bot->run();

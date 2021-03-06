<?php

/**
 * Created by PhpStorm.
 * User: Mohamad Amin
 * Date: 3/26/2016
 * Time: 12:58 AM
 */

require __DIR__ . '/vendor/autoload.php';
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

ignore_user_abort(true);//if caller closes the connection (if initiating with cURL from another PHP, this allows you to end the calling PHP script without ending this one)
set_time_limit(0);

$hLock=fopen(__FILE__.".lock", "w+");
if(!flock($hLock, LOCK_EX | LOCK_NB))
    die("Already running. Exiting...");

$API_KEY = 'Genius :D';
$BOT_NAME = 'MohandesplusBot';

$mysql_credentials = [
    'host'     => '',
    'user'     => '',
    'password' => '',
    'database' => '',
];

$telegram = null;

try {
    // Create Telegram API object
    $telegram = new Telegram($API_KEY, $BOT_NAME);
    // Enable MySQL
    $telegram->enableMySQL($mysql_credentials);
    // Handle telegram getUpdate request
    $telegram->addCommandsPath('commands');
    $telegram->setLogRequests(true);
    $telegram->setLogPath($BOT_NAME . '.log');
    $telegram->setLogVerbosity(3);
    $telegram->setDownloadPath('images');
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    // log telegram errors
    echo $e;
}

function checkQueueDatabase() {
    $database = new medoo([
        'database_type' => 'mysql',
        'database_name' => 'mohandesplusbot',
        'server' => 'localhost',
        'username' => 'root',
        'password' => 'MohandesPlus',
        'charset' => 'utf8mb4'
    ]);
    $datas = $database->select("queue", "*");
    foreach($datas as $data) {
        $time = $data['Time'];
        if (time() > $time) {
            $tData = [];
            $tData['chat_id'] = $data['Channel'];
            if ($data['MessageId'] != 0) {
                $tData['message_id'] = $data['MessageId'];
                $tData['text'] = "";
                Request::editMessageText($tData);
            } else {
                switch ($data['Type']) {
                    case 1:
                        $tData['text'] = $data['Text'];
                        $result = Request::sendMessage($tData);
                        break;
                    case 2:
                        if (strlen($data['Text']) > 200) {
                            $tData['parse_mode'] = 'Markdown';
                            $tData['text'] = $data['Text'].
                                '[ ]('.$data['Photo'].')';
                            Request::sendMessage($tData);
                        } else {
                            $tData['photo'] = $data['Photo'];
                            $tData['caption'] = $data['Text'];
                            Request::sendPhoto($tData);
                        }
                        break;
                    case 3:
                        $tData['video'] = $data['Video'];
                        $tData['caption'] = $data['Text'];
                        $result = Request::sendVideo($tData);
                        break;
                    case 4:
                        $tData['from_chat_id'] = $data['ChatId'];
                        $tData['message_id'] = $data['Text'];
                        $result = Request::forwardMessage($tData);
                        break;
                    case 5:
                        $tData['document'] = $data['Photo'];
                        $tData['caption'] = $data['Text'];
                        $result = Request::sendDocument($tData);
                        break;
                }
            }
            if ($data['EditTime'] == 0) {
                $database->delete("queue", [
                    "AND" => [
                        "Time" => $time
                    ]
                ]);
            } else {
                $tData = [];
                $tData['chat_id'] = $data['ChatId'];
                $tData['text'] = var_export($result, true);
                Request::sendMessage($tData);
            }
        }
    }

}

try {
    while (true) {
        $telegram->handleGetUpdates();
        checkQueueDatabase();
        usleep(2*1000*100);
    }
} catch (\Longman\TelegramBot\Exception\TelegramException $e) {
    echo $e;
}

flock($hLock, LOCK_UN);
fclose($hLock);
unlink(__FILE__.".lock");

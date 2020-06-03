<?php

class ArgParse
{
  private $_options;

  private $_startDate;
  private $_endDate;
  private $_emojiMark;
  private $_all = false;

  public function __construct()
  {
    $error_message = "Paramater: -c emoji-name -s YYYY-MM[-DD] [-e YYYY-MM[-DD]]";
    $this->_options = getopt("hs:e::c:");

    if(array_key_exists("h", $this->_options)) {
      fputs(STDERR, $error_message);
      exit(1);
    }
    if($this->_options === false) {
      fputs(STDERR, $error_message);
      exit(-1);
    }

    $this->_startDate = new DateTime($this->_options["s"]);

    if(array_key_exists("e", $this->_options)) {
      $this->_endDate = new DateTime($this->_options["e"]);
      $this->_endDate
        ->add(new DateInterval('P1D'))
        ->sub(new DateInterval('PT1S'));
    } else {
      $this->_endDate = new DateTime($this->_options["s"]);
      $this->_endDate
        ->add(new DateInterval('P1M'))
        ->sub(new DateInterval('PT1S'));
    }

    if(array_key_exists("c", $this->_options)) {
      $this->_emojiMark = $this->_options["c"];
      $this->_all = false;
    } else {
      $this->_emojiMark = "";
      $this->_all = true;
    }
  }

  public function getEmoji() {
    return $this->_emojiMark;
  }

  public function getStartDateTimestamp() {
    return $this->_startDate->getTimestamp() . ".000000";
  }

  public function getEndDateTimestamp() {
    return $this->_endDate->getTimestamp() . ".999999";
  }

  public function isAllEmoji() {
    return $this->_all;
  }
}

class SlackAPI
{
  const API_BASE_URL = "https://slack.com/api/";
  //ACCESSトークンは環境変数SLACK_TOKENから読み込む
  const TOKEN = "SLACK_TOKEN";

  const API_LIST_CHANNEL = "conversations.list";
  const API_LIST_MESSAGE = "conversations.history";
  const API_LIST_USER = "users.list";
  private $accessToken;

  private function fetchUrl($url)
  {
    $responseJSON = file_get_contents($url);
    $objJSON = json_decode(urldecode($responseJSON));
    if ($objJSON->ok !== true) {
      fputs(STDERR, "Fetch Error:" . $objJSON->error . "[" . $url . "]");
      return [];
    }
    return $objJSON;
  }

  private function createParamString($param)
  {
    $work = [];
    foreach ($param as $key => $value) {
      $work[] = $key . '=' . $value;
    }
    $paramString = '?' . implode('&', $work);
    return $paramString;
  }

  public function __construct()
  {
    $this->accessToken = getenv(self::TOKEN);
    //var_dump($this->accessToken);
  }

  public function getChannelObject()
  {
    $params = ["token" => $this->accessToken];
    $url = self::API_BASE_URL . self::API_LIST_CHANNEL . $this->createParamString($params);
    $obj = $this->fetchUrl($url);
    return $obj;
  }

  public function getUserObject()
  {
    $params = ["token" => $this->accessToken];
    $url = self::API_BASE_URL . self::API_LIST_USER . $this->createParamString($params);
    $obj = $this->fetchUrl($url);
    return $obj;
  }

  public function getMessageObjectByChannel($channel, $oldest, $latest)
  {
//    var_dump(new DateTime("@" . $oldest), new DateTime("@" . $latest));
    $params = ["token" => $this->accessToken, "channel" => $channel, "oldest" => $oldest, "latest" => $latest];
    $url = self::API_BASE_URL . self::API_LIST_MESSAGE . $this->createParamString($params);
    $obj = $this->fetchUrl($url);
    return $obj;
  }
}

class MakeList
{
  public function makeChannelList($obj)
  {
    $channelList = [];
    if (!property_exists($obj, "channels")) {
      return [];
    }
    foreach ($obj->channels as $channel) {
      if (
        property_exists($channel, "id") &&
        property_exists($channel, "name")
      ) {
        $channelList += [$channel->id => $channel->name];
      }
    }
//    var_dump($channelList);
    return $channelList;
  }

  public function makeUserList($obj)
  {
    $userList = [];
    foreach ($obj->members as $user) {
      if (
        property_exists($user->profile, "display_name") &&
        property_exists($user->profile, "real_name")
      ) {
        $name = ($user->profile->display_name !== "")
          ? $user->profile->display_name
          : $user->profile->real_name;
        //var_dump($user->id, $name);
        $userList += [$user->id => $name];
      }
    }
//    var_dump($userList);
    return $userList;
  }

  public function makeReactionList($obj)
  {
    $reactions = [];
    if (property_exists($obj, "messages")) {
      foreach ($obj->messages as $message) {
        if (
          property_exists($message, "user") &&
          property_exists($message, "reactions") &&
          property_exists($message, "ts")
        ) {
          foreach ($message->reactions as $reaction) {
            //リアクションプロパティがある場合
            if (
              property_exists($reaction, "name") &&
              property_exists($reaction, "count")
            ) {
              //リアクション名その他を取得
              $user = $message->user;
              $reactionName = $reaction->name;
              $timeStamp = $message->ts;
              $count = $reaction->count;
              $reactions[] = [$user, $reactionName, $timeStamp, $count];
            }
          }
        }
      }
    }
    return $reactions;
  }
}

$argParse = new ArgParse();
$slackAPI = new SlackAPI();
$makeList = new MakeList();

$userObject = $slackAPI->getUserObject();
$userList = $makeList->makeUserList($userObject);

$channelObject = $slackAPI->getChannelObject();
$channelList = $makeList->makeChannelList($channelObject);

$messageObjects = [];
foreach ($channelList as $channel => $_) {
  $messageObject = $slackAPI->getMessageObjectByChannel(
    $channel,
    $argParse->getStartDateTimestamp(),
    $argParse->getEndDateTimestamp()
  );
  $messageObjects[] = $messageObject;
  //sleep(1);
}
//var_dump($messageObjects);

$reactionList = [];
foreach ($messageObjects as $messageObject) {
  $reactionList[] = $makeList->makeReactionList($messageObject);
}
//var_dump($reactionList);

//配列のflat化
$reactionList2 = [];
foreach ($reactionList as $reactions) {
  foreach ($reactions as $reaction) {
    $reactionList2[] = $reaction;
  }
}
//var_dump($reactionList2);

//集計出来るように加工
$reactionStats = [];
foreach ($reactionList2 as $reaction) {
  [$user, $reactionName, $date, $count] = $reaction;
  for ($i = 0; $i < $count; $i++) {
    if ($argParse->isAllEmoji() || $reactionName === $argParse->getEmoji()) {
      $reactionStat = $userList[$user] . "," . $reactionName;
      $reactionStats[] = $reactionStat;
    }
  }
}

//集計
$statAll = array_count_values($reactionStats);
arsort($statAll);

echo "START:" . date("Y-m-d H:i:s", $argParse->getStartDateTimestamp()) . PHP_EOL;
echo "END  :" . date("Y-m-d H:i:s", $argParse->getEndDateTimestamp()) . PHP_EOL;
echo "emoji :" . $argParse->getEmoji() . ":" . PHP_EOL;
//結果表示
//print_r($statAll);
foreach($statAll as $key => $value) {
  echo explode(",", $key)[0] . "," . $value . PHP_EOL;
}
exit(0);

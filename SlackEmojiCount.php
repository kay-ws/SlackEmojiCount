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
    $this->_options = getopt("hs:e:c:");

    echo $error_message . PHP_EOL;

    if ($this->_options === false) {
      echo "ArgParse:Parse Error";
      exit(-1);
    }

    if (array_key_exists("h", $this->_options)) {
      exit(0);
    }

    if (!array_key_exists("s", $this->_options)) {
      echo "ArgParse:-s does't set";
      exit(-2);
    }
    $this->_startDate = new DateTime($this->_options["s"]);

    if (array_key_exists("e", $this->_options)) {
      if ($this->_options["s"] > $this->_options["e"]) {
        echo "ArgParse: -e must be bigger then -s";
        exit(-3);
      }
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

    if (array_key_exists("c", $this->_options)) {
      $this->_emojiMark = $this->_options["c"];
      $this->_all = false;
    } else {
      $this->_emojiMark = "";
      $this->_all = true;
    }
  }

  public function getEmoji()
  {
    return $this->_emojiMark;
  }

  public function getStartDateTimestamp()
  {
    //開始は0.000000マイクロ秒スタート
    return $this->_startDate->getTimestamp() . ".000000";
  }

  public function getEndDateTimestamp()
  {
    //終了は0.999999マイクロ秒ストップ
    return $this->_endDate->getTimestamp() . ".999999";
  }

  public function isAllEmoji()
  {
    return $this->_all;
  }
}

class SlackAPI
{
  const API_BASE_URL = "https://slack.com/api/";
  //ACCESSトークンはシステム環境変数SLACK_TOKENから読み込む
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
      echo  "Fetch Error:" . $objJSON->error . "[" . $url . "]" . PHP_EOL;
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
    //環境変数SLACK_TOKENからアクセストークン取得
    $this->accessToken = getenv(self::TOKEN);
    //var_dump($this->accessToken);
  }

  public function getChannel()
  {
    echo "get channels..." . PHP_EOL;
    $params = ["token" => $this->accessToken];
    $url = self::API_BASE_URL . self::API_LIST_CHANNEL . $this->createParamString($params);
    $obj = $this->fetchUrl($url);
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
    return $channelList;
  }

  public function getUser()
  {
    echo "get users..." . PHP_EOL;
    $params = ["token" => $this->accessToken];
    $url = self::API_BASE_URL . self::API_LIST_USER . $this->createParamString($params);
    $obj = $this->fetchUrl($url);
    $userList = [];
    foreach ($obj->members as $user) {
      if (
        property_exists($user->profile, "display_name") &&
        property_exists($user->profile, "real_name")
      ) {
        $name = ($user->profile->display_name !== "")
          ? $user->profile->display_name
          : $user->profile->real_name;
        $userList += [$user->id => $name];
      }
    }
    return $userList;
  }

  public function getMessageByChannel($key, $value, $oldest, $latest)
  {
    echo "get messages by channel..." . $value . PHP_EOL;

    $params = ["token" => $this->accessToken, "channel" => $key, "oldest" => $oldest, "latest" => $latest];
    $url = self::API_BASE_URL . self::API_LIST_MESSAGE . $this->createParamString($params);
    $obj = $this->fetchUrl($url);
    $reactions = [];
    if (property_exists($obj, "messages")) {
      foreach ($obj->messages as $message) {
        if (
          property_exists($message, "user") &&
          property_exists($message, "reactions")
        ) {
          foreach ($message->reactions as $reaction) {
            //リアクションプロパティがある場合
            if (
              property_exists($reaction, "name") &&
              property_exists($reaction, "count")
            ) {
              //投稿者名、リアクション名（スタンプ名）、件数を取得
              $user = $message->user;
              $reactionName = $reaction->name;
              $count = $reaction->count;

              $reactions[] = [$user, $reactionName, $count];
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

$userList = $slackAPI->getUser();

$channelList = $slackAPI->getChannel();

$reactions = [];
foreach ($channelList as $key => $value) {
  $messageList = $slackAPI->getMessageByChannel(
    $key,
    $value,
    $argParse->getStartDateTimestamp(),
    $argParse->getEndDateTimestamp()
  );
  $reactions = array_merge($reactions, $messageList);
}

//集計出来るように加工
$reactionStats = [];
foreach ($reactions as $reaction) {
  [$user, $reactionName, $count] = $reaction;
  if ($argParse->isAllEmoji() || $reactionName === $argParse->getEmoji()) {
    for ($i = 0; $i < $count; $i++) {
      $reactionStat = $userList[$user] . "," . $reactionName;
      $reactionStats[] = $reactionStat;
    }
  }
}

//集計
$statAll = array_count_values($reactionStats);

//ソート
arsort($statAll);

echo "START [" . date("Y-m-d H:i:s", $argParse->getStartDateTimestamp()) . "]" . PHP_EOL;
echo "END   [" . date("Y-m-d H:i:s", $argParse->getEndDateTimestamp()) . "]" . PHP_EOL;
echo "emoji [:" . $argParse->getEmoji() . ":]" . PHP_EOL;

//結果表示
if ($argParse->isAllEmoji()) {
  foreach ($statAll as $key => $value) {
    echo $key . "," . $value . PHP_EOL;
  }
} else {
  foreach ($statAll as $key => $value) {
    echo explode(",", $key)[0] . "," . $value . PHP_EOL;
  }
}
exit(0);

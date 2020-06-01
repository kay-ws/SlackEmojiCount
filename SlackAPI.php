<?php

class SlackAPI
{
  const API_BASE_URL = "https://slack.com/api/";
  //ACCESSトークンは環境変数SLACK_TOKENから読み込む
  //const ACCESS_TOKEN = "xoxp-321232085875-1121363210787-1164660960513-41ae9bfbf54cf6f537a016bc387d487f";
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

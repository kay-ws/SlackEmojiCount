<?php
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

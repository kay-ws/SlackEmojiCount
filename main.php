<?php

function argParse()
{
  $options = getopt("c:s:e::a");

  if ($options === false) {
    fputs(STDERR, 'Paramater Error: -c emoji-name -s YYYY-MM');
    exit(-1);
  }

  $emojiMark = $options["c"];
  $startDate = new DateTime($options["s"]);

  if(array_key_exists("e", $options)) {
    $endDate = new DateTime($options["e"]);
  } else {
    $endDate = new DateTime($options["s"]);
    $endDate
      ->add(new DateInterval('P1M'))
      ->sub(new DateInterval('PT1S'));
  }
  return [
    $emojiMark,
    $startDate->getTimestamp() . ".000000",
    $endDate->getTimestamp() . ".999999",
    array_key_exists("a", $options)
  ];
}

require 'SlackAPI.php';
require 'MakeList.php';

[$emojiMark, $startDate, $endDate, $all] = argParse();

$SlackAPI = new SlackAPI();
$MakeList = new MakeList();

$userObject = $SlackAPI->getUserObject();
$userList = $MakeList->makeUserList($userObject);

$channelObject = $SlackAPI->getChannelObject();
$channelList = $MakeList->makeChannelList($channelObject);

$messageObjects = [];
foreach ($channelList as $channel => $_) {
  $messageObject = $SlackAPI->getMessageObjectByChannel($channel, $startDate, $endDate);
  $messageObjects[] = $messageObject;
  sleep(1);
}
var_dump($messageObjects);

$reactionList = [];
foreach ($messageObjects as $messageObject) {
  $reactionList[] = $MakeList->makeReactionList($messageObject);
}
var_dump($reactionList);

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
    if ($all === true || $reactionName === $emojiMark) {
      $reactionStat = $userList[$user] . "," . $reactionName;
      $reactionStats[] = $reactionStat;
    }
  }
}

//集計
$statAll = array_count_values($reactionStats);

//結果表示
print_r($statAll);

exit(0);

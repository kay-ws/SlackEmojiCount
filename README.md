CommewのSlackから特定の期間に押された特定のスタンプの数を投稿者ごとに集計します。

コマンド説明

php main.php -c emoji-name -s start-date [-e end-date] [-a]

emoji-name：絵文字の左右::を取り除いたもの
start-date：YYYY/MM or YYYY/MM/DD
end-date：YYYY/MM or YYYY/MM/DD

-c 絵文字
-s 開始日
-e 終了日
-a 隠しパラメータ。-Cの絵文字指定をキャンセルし、期間内のすべての絵文字について出力する。

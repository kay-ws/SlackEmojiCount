CommewのSlackから特定の期間に押された特定のスタンプの数を投稿者ごとに集計します。

コマンド説明

php SlackEmojiCount.php -c emoji-name -s YYYY-MM[-DD] [-e YYYY-MM[-DD]]

パラメータ

"-c" 絵文字(両端の:を取り除いたもの)
"-s" 開始日 YYYY-MM[-DD] 
"-e" 終了日 YYYY-MM[-DD] (省略可。省略した場合は、開始日から1ヶ月間になるよう設定される)






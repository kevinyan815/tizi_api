#!/bin/sh
#computes experience that users get in this week#
cd /space1/tizi_api/application/third_party/tiku_cron/
/usr/bin/php stat_weekly_exp.php >> ../log/tiku_weekly_exp.log 2>&1 &

#send notice to site's all users#
cd /space1/tizi_api/application/third_party/tiku_cron/
/usr/bin/php send_site_notice.php >> ../log/tiku_send_notice.log 2>&1 &
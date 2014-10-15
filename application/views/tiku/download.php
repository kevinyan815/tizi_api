<?php

/*
 * 爱刷题下载的中间跳转页
 */
?>
<html>
    <head>
        <title>天天爱刷题下载</title>
    </head>
    <body></body>
        <script type="text/javascript">
        var agent = window.navigator;
        if (/android/i.test(navigator.userAgent)){
            //android device
        } else if (/ipad|iphone|mac/i.test(navigator.userAgent)) {
            //ios device
            window.location = 'https://itunes.apple.com/us/app/tian-tian-ai-shua-ti-gao-zhong/id866974171?l=zh&ls=1&mt=8';
        }

        </script>
</html>
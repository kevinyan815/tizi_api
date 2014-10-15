<?php

/*
 * 知识点树 测试页面
 */

?>
<html>
    <head>
    <title>知识点树</title>
    <style type="text/css">
        #search{
            height: 50px;
        }
        div {
            margin: 20px auto auto auto;
            width:  800px;
            height: auto;
        }
        select, input{
            width:100px;
            height: 30px;
            font-size: 15px;
        }
    </style>

    </head>
    <body>
        <div id="search">
            <form name="frm" action="" method="GET">
                <table width="800" cellpadding="0" cellspacing="0" border="1">
                    <tr>
                        <td>地区：
                          <select name="locationId">
                              <option value="0">请选择地区</option>
                              <?  foreach ($regions as $val) {
                                  $selected = $val['id'] == $locationId ? 'selected' : '';
                                  ?>
                              <option value="<?=$val['id']?>" <?=$selected?>><?=$val['name']?></option>
                              <?}?>
                          </select>  
                        </td>
                        <td>学科：
                            <select name="subjectId">
                              <option value="0">请选择学科</option>
                              <?  foreach ($subjects as $id => $name) {
                                  $selected = $id == $subjectId ? 'selected' : '';
                                  ?>
                              <option value="<?=$id?>" <?=$selected?>><?=$name?></option>
                              <?}?>
                          </select>  
                        </td>
                        <td>
                            <input type="submit" value="查看知识点"/>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div>
            <dl>
               <?foreach($knowledgeTree as $val) {?>
               <dt><?=$val['kId'] . ': ' . $val['name']?></dt>
               <?if(isset($val['sub_knowledge'])) foreach($val['sub_knowledge'] as $v) {?>
                    <dd><?=$v['kId'] . ': ' . $v['name']?></dd>
               <?}}?>
            </dl>
        </div>
    </body>
        <script type="text/javascript">
//    document.frm.onsubmit = function ()
//    {
//        alert(this.action);
//    }
    </script>
</html>
<?php

/**
 * 检查题目—知识点关联表里是否有重复关联的情况,并删除
 * @author yanhj
 */
require_once 'connect.inc.php';
if(PHP_SAPI != 'cli')die;
$sql = "SELECT COUNT(*) FROM questions";
$total = $db_tiku->get_var($sql);

$pageSize = 500;
$pages    = ceil($total / $pageSize);
$result_arr = array();
for($i = 1; $i <= $pages; $i++) {
    $start = ($i - 1) * $pageSize;
    $sql = "SELECT id as qId FROM questions LIMIT {$start}, {$pageSize}";
    $qIdArr = $db_tiku->get_results($sql);
    foreach($qIdArr as $val) {
        $sql = "SELECT id, kId FROM knowledge_question_rel WHERE qId = {$val['qId']}";
        $kIdArr = $db_tiku->get_results($sql);
        $filterArr = array();
        if(is_array($kIdArr))foreach($kIdArr as $kIdVal) {
            if(!in_array($kIdVal['kId'], $filterArr)) {
                $filterArr[] = $kIdVal['kId'];
            } else {
                echo "question_id: {$val['qId']}, knowledge_id: {$kIdVal['kId']}, id: {$kIdVal['id']}" .chr(10);
                $sql = "DELETE FROM knowledge_question_rel WHERE id={$kIdVal['id']}";
                $db_tiku->query($sql);
                
//                $result_arr[$val['qId']][] = array('id' => $kIdVal['id'], 'kId' => $kIdVal['kId']);
            }
        }
    }
}
?>

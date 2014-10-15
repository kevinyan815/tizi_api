<?php
/*
 * 移动题库项目中用到的公共类库
 * @copyright March 28 2014
 */

class Tiku
{
    /**
     * 构造方法
     */
    public function __construct()
    {
        
    }
    
    /**
     * 格式化响应数据
     * @param int $status 状态码 1:成功,2:失败
     * @param array $data 响应数据
     * @param int $error_code 错误码
     * @param string $error_message 错误信息
     * @return return formated data
     */
    public function formatResponse($status, $data = array(), $error_code = 0, $error_message = '')
    {       
        $response = array();
        switch($status) {
            case 0:
                $response['response_status'] = 'error';
                $response['response_error_code'] = $error_code;
                $response['response_error_message'] = $error_message;
                break;
            case 1:
                $response['response_status'] = 'ok';
                $response['response_data'] = $data;
                break;
            defult:
                break;
        }
        
        return $response;
    }
    
    /**
     * 解析用户做完题提交上来的答案字符串
     * @param string $answerStr  字符串格式类似于1:1,2:1,3:1,4:1,5:0
     * @param int $type type等于1,用逗号分隔;type等于2,用分号分隔
     * @return array             返回将$answerStr解析成的数组
     */
    public function parseAnswerStr($answerStr,$type)
    {
    	if ($type == 1) {
            $answerArr = explode(',', $answerStr);
    	} else if ($type == 2){
            $answerArr = explode(';', $answerStr);
    	}

        $answerDetail = array();
        foreach($answerArr as $val) {
            $arr = explode(':', $val);
            if(count($arr) < 2) {
                $responseData = $this->formatResponse(0, array(), 10030, '请按正确的格式提交题目数据');
                echo json_encode($responseData);die;
            }
            $answerDetail[$arr[0]] = $arr[1];
        }
        
        return $answerDetail;
    }
    
    /*
     * 学科数组
     */
    public function subjectArray()
    {
        $arr = array(
                1 => '语文', 
                2 => '英语',
                3 => '理数',
                4 => '文数', 
                5 => '物理', 
                6 => '化学', 
                7 => '生物', 
                8 => '历史', 
                9 => '地理', 
                10 => '政治'
                );
        return $arr;
    }
}
?>

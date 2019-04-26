<?php
/**
 * 头条云图抓取抖音兴趣分类预估覆盖人数
 */
error_reporting(E_ALL); //E_ALL ^E_NOTICE
ini_set('display_errors', 1); //显示错误信息
ini_set('memory_limit', '1024M');
ini_set("max_execution_time", 0);
set_time_limit(0);
ignore_user_abort(true); //关闭页面继续执行


try {
    $category = json_decode(file_get_contents('category.json'), true);
    $category = handleCategory($category);

    $tags = [];
    //print_r($category);die;
    foreach ($category as $value) {
        list($c1, $c2, $c3) = explode('_', $value);
        $tags[$c1][] = $value;
        $tags[$c1 . '_' . $c2][] = $value;
        $tags[$value] = $value;
    }
    //print_r($tags);die;
    $title = ['一级分类', '二级分类', '三级分类', '全部', '今日头条', '西瓜视频', '火山小视频', '抖音短视频'];
    $fileName = date('Ymd') . '.csv';
    ob_end_clean();
    ob_start();
    header("Content-Type: text/csv");
    header("Content-Disposition:filename=" . $fileName);
    $fp = fopen('php://output', 'w');
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));// 转码 防止乱码
    fputcsv($fp, $title);
    foreach ($tags as $key => $tag) {
        @list($c1, $c2, $c3) = explode('_', $key);
        $data = fetchData($tag);
        $row = [$c1, $c2, $c3, $data['全部'], $data['今日头条'], $data['西瓜视频'], $data['火山小视频'], $data['抖音短视频']];
        fputcsv($fp, $row);
        ob_flush();
        flush();
    }
    ob_flush();
    flush();
    ob_end_clean();
} catch (Exception $e) {
    write_log('errno: ' . $e->getCode() . ' error: ' . $e->getMessage());
    die(json_encode([
        'code' => $e->getCode(),
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE));
}


/**
 * 处理分类
 */
function handleCategory($category) {
    static $data = [];
    foreach ($category as $val) {
        if(gettype($val['value']) == 'integer') {
            handleCategory($val['children']);
        }else{
            $data[] = $val['value'];
        }
    }
    return $data;
}

/**
 * 获取数据
 */
function fetchData($tagName) {
    $url = 'https://yuntu.toutiao.com/api/dmp/audience/estimate';
    $cookie = 'tt_webid=6672615032210032132; gr_user_id=14dbdfe8-d337-4ddf-a0bf-c2fd7aea6f49; grwng_uid=bd74b9c9-4d45-4837-9b40-8c68d5e0b833; ccid=5d6fedd85d554a4e9e1b5be4bd0e2942; aefa4e5d2593305f_gr_last_sent_cs1=72598982699; __tea_sdk__user_unique_id=72598982699; __tea_sdk__ssid=c0806a26-f2e9-4a93-ac0b-3971b3159c71; utm_source=toutiao; aefa4e5d2593305f_gr_cs1=72598982699; odin_tt=8eec12ee33903a33fc497d924b1370a43f5c7e3ba98a54d3c7059bc1557c318402f6d68d7b6e9767ae193d88352417213687a0db6cd3f3de25033717d101502c; passport_auth_status=41eef193d987b54e1f03dc35ff083a2f; part=stable; sso_uid_tt=9f3f6fe7f5591fb10de7c66d8f106fca; toutiao_sso_user=0ac71366d160ca13796b5e51161f0055; login_flag=f85027604eccd64690d1443eaa5233b9; sid_tt=2cf4328fe1579ec244df28c02ffbcfcf; uid_tt=a442c9979e593e04a468261e43939ebc; sessionid=2cf4328fe1579ec244df28c02ffbcfcf; sid_guard="2cf4328fe1579ec244df28c02ffbcfcf|1556241583|2591999|Sun\054 26-May-2019 01:19:42 GMT"; 9390d5fd9d875737_gr_session_id=9e894bd1-a91a-4d9b-bafa-27afb8b14b02; 9390d5fd9d875737_gr_session_id_9e894bd1-a91a-4d9b-bafa-27afb8b14b02=true; 9390d5fd9d875737_gr_last_sent_sid_with_cs1=9e894bd1-a91a-4d9b-bafa-27afb8b14b02; 9390d5fd9d875737_gr_last_sent_cs1=104521717133; 9390d5fd9d875737_gr_cs1=104521717133';
    $headers = [
        ':authority: yuntu.toutiao.com',
        ':method: POST',
        ':path: /api/dmp/audience/estimate',
        ':scheme: https',
        'accept: application/json, text/plain, */*',
        'accept-encoding: gzip, deflate, br',
        'accept-language: zh-CN,zh;q=0.9,en;q=0.8',
        'cookie: ' . $cookie,
        'origin: https://yuntu.toutiao.com',
        'referer: https://yuntu.toutiao.com/adver/audience/audience_rule/',
        'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
        'x-csrftoken: [object Object]',
        'x-requested-with: XMLHttpRequest'
    ];
    $param = [];
    $param['type'] = '169';
    $param['data'] = [];
    $param['data']['tag_name'] = $tagName;
    $postData = [];
    $postData['operation_param'] = [$param];
    $postData['operation_rule'] = '0';
    $postData['app_ids'] = [];
    $jsonStr = json_encode($postData, 320);
    $response = curl_json($url, $jsonStr, $headers, $request);
    if($response['status'] !== 0) {
        throw new Exception($response['msg'], $response['status']);
    }
    $data = array_slice($response['data'], 0, 5, true);
    $result = [];
    foreach ($data as $k => $v) {
        if($name = convertMark($k)) {
            $result[$name] = number_format($v);
        }
    }
    return $result;
}

/**
 * 转换标识
 */
function convertMark($id) {
    $list = [
        0 => '全部',
        13 => '今日头条',
        32 => '西瓜视频',
        1112 => '火山小视频',
        1128 => '抖音短视频'
    ];
    return isset($list[$id]) ? $list[$id] : false;
}

/**
 * curl post json数据
 */
function curl_json($url, $jsonStr, $headers = [], &$request = null, $timeout = 60)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip'); //解决乱码
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen($jsonStr)
    ], $headers));
    $response = curl_exec($ch);
    $request = curl_getinfo($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        write_log("request url($url) error $error" . print_r($request, true), './logs/curl.log');
        throw new Exception($error);
    }
    curl_close($ch);
    return json_decode($response, true);
}

/**
 * 写入日志
 * @param string|array $values
 * @param string $dir
 * @return bool|int
 */
function write_log($values, $filePath = null)
{
    if (is_array($values)) {
        $values = print_r($values, true);
    }
    $content = '[' . date('Y-m-d H:i:s') . ']' . PHP_EOL . $values . PHP_EOL . PHP_EOL;
    try {
        if(!$filePath) {
            $filePath = './logs/';
            !is_dir($filePath) && mkdir($filePath, 0755, true);
            $filePath = $filePath . date('Ymd') . '.log';
        }else{
            $dir = dirname($filePath);
            !is_dir($dir) && mkdir($dir, 0755, true);
        }
        return file_put_contents($filePath, $content, FILE_APPEND);
    } catch (\Exception $e) {
        return false;
    }
}

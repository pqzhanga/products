<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace crmeb\services;


use think\facade\Db;
use think\facade\Log;

class PayfastService
{
    /**
     * @var Client
     */
    protected $application;
    protected $num = 0;

    /**
     * @var array
     */
    protected $config;

    public function __construct()
    {
//        $this->config = $config;
//        $this->application = new Client('');
    }

    public static function create($type = '')
    {
        return new self(self::getConfig($type));
    }

    public static function getConfig($type = '')
    {
//        $config = systemConfig(['site_url', 'alipay_app_id', 'alipay_public_key', 'alipay_private_key', 'alipay_open']);

    }


    public function payOrderRefund($trade_sn, array $data)
    {

        if($data['adminId'])   throw new \Exception('请联系平台退款!');
        else          return ;

//                $mchtCd = "MCHT965103250";
                $mchtCd = "MCHT033600448";
                $typeField = "ORG";
                $secretKey = "L2DYNQ5YR9P532ZTX8WNTBWX";
                $orgCd = "202010209693553";

                $outOrderId = $trade_sn.date('dHis');
                $oglOrdDate = date('Ymd',strtotime($data['pay_time']));

                //交易码，退款固定：TRANS0107
                $reqData["trscode"] = "TRANS0107";
                //商户编号
                $reqData["mchtCd"] = $mchtCd;

                $reqData["oglOrdId"] = $trade_sn;

                $reqData["outOrderId"] =$outOrderId ;

                //原消费的交易日期
                $reqData["oglOrdDate"] = $oglOrdDate;// "20200810";
                //退款金额（单位:元）
                $reqData["transAmt"] = $data['refund_price'] ;

                $encReqData =   openssl_encrypt(json_encode( $reqData ),'des-ede3', $secretKey, 0);
                $data = [];
                $data["typeField"] = $typeField;
                $data["keyField"] = $orgCd;
                $data["dataField"] = $encReqData;

                $encRespStr = $this->send_request('https://api.yunfastpay.com', json_encode($data));

                if(is_array($encRespStr)) {
                    Log::info('-------------error------退款失败:' . var_export(  ['$trade_sn'=>$trade_sn,'$resdata'=>$encRespStr]  , true));
                    throw new \Exception('退款失败(6)!:'.$encRespStr['msg']);
                }

                $encRespStr = json_decode($encRespStr,true);


                if($encRespStr['dataField']) {
                    $resdata = openssl_decrypt(base64_decode($encRespStr['dataField']), 'des-ede3', $secretKey, 1);
                    $resdata = json_decode($resdata, true);

                    Log::info('-------------error------退款:' . var_export(  ['$trade_sn'=>$trade_sn,'$resdata'=>$resdata]  , true));

                    if ($resdata['respCode'] == '0000') {
                        /* 交易正常 */
                        if("100" == $resdata["transStatus"]){
                            /*交易成功*/
                            return;
                        }elseif("101" == $resdata["transStatus"]){
                           $res = $this->queryorderinfo($outOrderId,$oglOrdDate);
                           if(!$res)  {
                               Log::info('-------------error------退款 多次查询失败(3):' . var_export(  ['$trade_sn'=>$trade_sn,'$outOrderId'=>$outOrderId]  , true));
                               throw new \Exception('退款失败(3)!');
                           }else{
                               return ;
                           }
                        }
                    }
                    Log::info('-------------error------ 退款失败(2):' . var_export(  $resdata  , true));
                    throw new \Exception($resdata['respMsg'].',退款失败(2)!');
                }
                Log::info('-------------error------ 退款失败(1):' . var_export(  $encRespStr  , true));
                throw new \Exception('退款失败(1)!');
    }


    private function queryorderinfo($outOrderId,$time){
        $this->num = $this->num + 1;
        if($this->qry($outOrderId,$time)) return true;
        sleep(1);
        if($this->num > 20) return false;
        return $this->queryorderinfo($outOrderId,$time);
    }

    private  function  qry($outOrderId,$time){

        $typeField = "ORG";
        $secretKey = "L2DYNQ5YR9P532ZTX8WNTBWX";
        $mchtCd = "MCHT033600448";
        $orgCd = "202010209693553";
        // $reqUrl = "http://test.api.route.hangmuxitong.com";
        $reqUrl = "https://api.yunfastpay.com";

        //交易码，查询固定：TRANS0102
        $reqData["trscode"] = "TRANS0102";
        //商户编号
        $reqData["mchtCd"] = 'MCHT033600448';
        //原消费的外部订单号oglOrdId
        $reqData["oglOrdId"] = $outOrderId;
        //原消费的交易日期
        $reqData["oglOrdDate"] = $time;

        Log::write('退款查询订单接口请求数据：' . json_encode($reqData),'notice');

        $encReqData =   openssl_encrypt(json_encode( $reqData ),'des-ede3', $secretKey, 0);

        $data = [];
        $data["typeField"] = $typeField;
        $data["keyField"] = $orgCd;
        $data["dataField"] = $encReqData;

        $encRespStr = $this->send_request($reqUrl, json_encode($data));
        $respMsg = json_decode($encRespStr, true);
        $respStr = openssl_decrypt(base64_decode($respMsg["dataField"]), 'des-ede3', $secretKey, 1);

        $respData = json_decode($respStr, true);

        if("0000" == $respData["respCode"]){
            if("100" == $respData["transStatus"]){
                return  true;
            }else if("102" ==  $respData["transStatus"]){
                /*交易失败*/
            }
        }

        return  false;

    }

    private function send_request($url, $params = [], $method = 'POST', $options = [])
    {
        $method = strtoupper($method);
        $protocol = substr($url, 0, 5);
        $query_string = is_array($params) ? http_build_query($params) : $params;

        $ch = curl_init();
        $defaults = [];
        if ('GET' == $method)
        {
            $geturl = $query_string ? $url . (stripos($url, "?") !== FALSE ? "&" : "?") . $query_string : $url;
            $defaults[CURLOPT_URL] = $geturl;
        }
        else
        {
            $defaults[CURLOPT_URL] = $url;
            if ($method == 'POST')
            {
                $defaults[CURLOPT_POST] = 1;
            }
            else
            {
                $defaults[CURLOPT_CUSTOMREQUEST] = $method;
            }
            $defaults[CURLOPT_POSTFIELDS] = $query_string;
        }
        $defaults[CURLOPT_HEADER] = FALSE;
        $defaults[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.98 Safari/537.36";
        $defaults[CURLOPT_FOLLOWLOCATION] = TRUE;
        $defaults[CURLOPT_RETURNTRANSFER] = TRUE;
        $defaults[CURLOPT_CONNECTTIMEOUT] = 30;
        $defaults[CURLOPT_TIMEOUT] = 30;

        // disable 100-continue
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:", "Content-Type:application/json"));

        if ('https' == $protocol)
        {
            $defaults[CURLOPT_SSL_VERIFYPEER] = FALSE;
            $defaults[CURLOPT_SSL_VERIFYHOST] = FALSE;
        }

        curl_setopt_array($ch, (array) $options + $defaults);

        $ret = curl_exec($ch);
        $err = curl_error($ch);
        if (FALSE === $ret || !empty($err))
        {
            $errno = curl_errno($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            return [
                'ret'   => FALSE,
                'errno' => $errno,
                'msg'   => $err,
                'info'  => $info,
            ];
        }
        curl_close($ch);
        return $ret;
    }


    public function notify($type, array $data)
    {

    }
}

<?php

namespace PhalApi\Xpush\Engine;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

class Aliyun
{
    protected $config;
    protected $debug;

    public function __construct($config = null)
    {
        $di = \PhalApi\DI();
        $this->debug = $di->debug;
        $this->config = $config;
        if (null == $this->config) {
            $this->config = $di->config->get('app.Xpush.aliyun');
        }
        AlibabaCloud:: accessKeyClient($this->config['accessKeyId'], $this->config['accessKeySecret'])
            ->regionId($this->config['regionId'])   // 设置客户端区域，使用该客户端且没有单独设置的请求都使用此设置
            ->timeout(6)                            // 超时10秒，使用该客户端且没有单独设置的请求都使用此设置
            ->connectTimeout(10)                    // 连接超时10秒，当单位小于1，则自动转换为毫秒，使用该客户端且没有单独设置的请求都使用此设置
            //->debug(true) 						// 开启调试，CLI下会输出详细信息，使用该客户端且没有单独设置的请求都使用此设置
            ->asDefaultClient();
    }

    public function getConfig()
    {
        return $this->config;
    }

    private function rpcRequest($action, $params)
    {
        $di = \PhalApi\DI();
        $rs = [
            'code' => 0,
            'msg' => '',
            'data' => null,
        ];
        try {
            $result = AlibabaCloud::rpc()
                ->product('Push')
                ->version('2016-08-01')
                ->action($action)
                ->method('POST')
                ->host('cloudpush.aliyuncs.com')
                ->options([
                    'query' => array_merge([
                        'AppKey' => $this->config['appKey'],
                        'RegionId' => $this->config['regionId'],
                    ], $params),
                ])
                ->request();
            if ($result->isSuccess()) {
                $rs['code'] = 1;
                $rs['msg'] = 'success';
                $rs['data'] = $result->toArray();
                if ($this->debug) {
                    $di->logger->info(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Response' => $response['body']]);
                }
            } else {
                $rs['code'] = -1;
                $rs['msg'] = $result;
                if ($this->debug) {
                    $di->logger->info(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Failed' => $result]);
                }
            }
        } catch (ClientException $e) {
            $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['ClientException' => $e->getErrorMessage()]);
            $rs['code'] = -1;
            $rs['msg'] = $result;
            $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Failed' => $result]);
        } catch (ServerException $e) {
            $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['ServerException' => $e->getErrorMessage()]);
            $rs['code'] = -1;
            $rs['msg'] = $result;
            $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Failed' => $result]);
        }

        return $rs;
    }

    public function push($pushTpye, $target, $targetValue, $deviceType, $content, $title, $extras)
    {
        $params = [
            'PushType' => $pushTpye,
            'DeviceType' => $deviceType,
            'Target' => $target,
            'TargetValue' => $targetValue,
            'Body' => $content,
            'Title' => $title,
            'StoreOffline' => 'true',
            //'ExpireTime' => gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 day')),
        ];
        if (strtolower($deviceType) === strtolower('ANDROID')) {
            $params = array_merge($params, [
                'AndroidNotifyType' => 'BOTH',  //通知的提醒方式 "VIBRATE" : 震动 "SOUND" : 声音 "BOTH" : 声音和震动 NONE : 静音
                'AndroidNotificationBarType' => '1',    //通知栏自定义样式0-100
                'AndroidNotificationBarPriority' => '0',    //Android通知在通知栏展示时排列位置的优先级 -2 -1 0 1 2
                'AndroidOpenType' => 'APPLICATION', //点击通知后动作 "APPLICATION" : 打开应用 "ACTIVITY" : 打开AndroidActivity "URL" : 打开URL "NONE" : 无跳转
                //'AndroidOpenUrl' => '', 				             //Android收到推送后打开对应的url,仅当AndroidOpenType="URL"有效
                //'AndroidActivity' => '',
                'AndroidRemind' => 'true',  //推送类型为消息时设备不在线，则这条推送会使用辅助弹窗功能。默认值为False，仅当PushType=MESSAGE时生效。
                'AndroidPopupActivity' => $this->config['androidPopupActivity'],    //设定通知打开的activity，仅当AndroidOpenType="Activity"有效
                'AndroidPopupTitle' => $title,
                'AndroidPopupBody' => $content,
                'AndroidMusic' => 'default',
                'AndroidNotificationChannel' => $this->config['androidChannel'],    //设置NotificationChannel参数
                'AndroidExtParameters' => $extras,  // 设定android类型设备通知的扩展属性
            ]);
        } elseif (strtolower($deviceType) === strtolower('IOS')) {
            $params = array_merge($params, [
                'iOSMusic' => 'default',
                'iOSApnsEnv' => 'PRODUCT',
                'iOSBadgeAutoIncrement' => 'false',
                'iOSSilentNotification' => 'false',
                'iOSMutableContent' => 'true',
                'iOSRemind' => 'true',
                'iOSRemindBody' => $content,
                'iOSExtParameters' => $extras,
            ]);
        }

        return $this->rpcRequest('Push', $params);
    }

    public function bindAlias($aliasName, $deviceId)
    {
        $params = [
            'AppKey' => $this->config['appKey'],
            'AliasName' => $aliasName,
            'DeviceId' => $deviceId,
        ];

        return $this->rpcRequest('BindAlias', $params);
    }
}
